from __future__ import print_function
import re
import sys


#
# Strelka VCF files are not particularly suitable for downstream tools.
# 1) The FORMAT field lacks GQ, AD, GQ which are used by many variant interpretation
# tools, and can be derived from various fields in INFO.
# 2) the sample names are NORMAL TUMOR, which is not ideal for multi-sample projects.
#
# usage:
# gunzip -c my.vcf.gz | python strelka_add_to_FORMAT.py | bgzip > fixed.vcf.gz
#
# authors:
# Mark Pinese, Mark Cowley, KCCG, Garvan Institute
#

def eprint(*args, **kwargs):
    print(*args, file=sys.stderr, **kwargs)


def makeIndelGT(sgt):
    if sgt == 'ref':
        return '0/0'
    elif sgt == 'het':
        return '0/1'
    elif sgt == 'hom':
        return '1/1'
    else:
        raise (ValueError('Unknown indel SGT encountered: {}'.format(sgt)))


def makeSNVGT(sgt, ref, alts):
    sgt_parts = list(sgt)
    gt_values = [ref] + alts.split(',')
    transformed_gt_parts = [str(gt_values.index(sgt_part)) for sgt_part in sgt_parts]
    return '/'.join(transformed_gt_parts)


def makeSNVAD(format_tags, sample_parts, ref, alts):
    # Split in case multi-allelic
    alts = alts.split(',')
    
    # Determine the reference read count for tier 1
    ref_counts = sample_parts[format_tags.index(ref + "U")].split(',')
    counts = [ref_counts[0]]  # Tier 1 with [0]
    
    # If the alt is a dot, set the alt count to 0
    for alt in alts:
        if alt is ".":
            counts.append("0")
        else:
            alt_counts = sample_parts[format_tags.index(alt + "U")].split(',')
            counts.append(alt_counts[0])  # Tier 1 with [0]
    
    # Return the first tier count for ref and all alts
    return ','.join(counts)


def makeIndelAD(format_tags, sample_parts):
    # Determine the reference read count for both tiers
    ref_count = sample_parts[format_tags.index("TAR")].split(',')
    
    # Determine the alternate read count for both tiers
    alt_count = sample_parts[format_tags.index("TIR")].split(',')
    
    # Return the first tier count for ref and alt
    return ref_count[0] + ',' + alt_count[0]


def makeSNVDP(format_tags, sample_parts):
    # Extract the AU/CU/GU/TU for both tiers
    au_count = sample_parts[format_tags.index("AU")].split(',')
    cu_count = sample_parts[format_tags.index("CU")].split(',')
    gu_count = sample_parts[format_tags.index("GU")].split(',')
    tu_count = sample_parts[format_tags.index("TU")].split(',')
    
    # Add AU, CU, GU, TU values from tier 1 to get total depth
    depth = int(au_count[0]) + int(cu_count[0]) + int(gu_count[0]) + int(tu_count[0])
    
    return depth


def makeIndelDP(format_tags, sample_parts):
    # Extract the TAR/TIR for both tiers
    tar_count = sample_parts[format_tags.index("TAR")].split(',')
    tir_count = sample_parts[format_tags.index("TIR")].split(',')
    
    # Add TAR and TIR to get the total depth for tier 1
    depth = int(tar_count[0]) + int(tir_count[0])
    
    return depth


def transformDataLine(line, normal_index, tumour_index):
    # Split the VCF line by tab characters
    line_parts = line.split('\t')
    
    # Split the FORMAT block by : for individual tags
    format_tags = line_parts[8].split(':')
    
    # Split the sample FORMAT values by : for individual values
    normal_sample_format_values = line_parts[9 + normal_index].split(':')
    tumour_sample_format_values = line_parts[9 + tumour_index].split(':')
    
    # Make sure there are the same number of FORMAT tags and values for both samples
    if len(format_tags) != len(normal_sample_format_values) or len(format_tags) != len(tumour_sample_format_values):
        raise (ValueError('Unequal number of FORMAT tags and values in line: {}'.format(line)))
    
    # Save the ref and alt values
    ref = line_parts[3]  # REFs seem to always be one value, never multi-allelic
    alts = line_parts[4]  # ALTs can be 2 values if multi-allelic, this seems to only occur for SNPs
    
    # Find the SGT and split by normal/tumour
    sgt_match = re.search('SGT=([^-]+)->([^;]+)', line_parts[7])
    sgt_normal = sgt_match.group(1)
    sgt_tumour = sgt_match.group(2)
    
    # Find the Strelka quality score
    quality_match = re.search(';(QS[SI])=([0-9]*)', line_parts[7])
    
    # Add GQ FORMAT tag
    format_tags.append('GQ')
    
    # Add GQ values for both samples
    normal_sample_format_values.append(".")
    tumour_sample_format_values.append(quality_match.group(2))
    
    # If this is an Indel line (they only have ref/hom/het in SGT)
    if sgt_normal == 'ref' or sgt_normal == 'hom' or sgt_normal == 'het':
        # Make sure TAR/TIR are defined in the FORMAT block
        if "TAR" not in format_tags or "TIR" not in format_tags:
            raise (ValueError('Missing TAR/TIR in Indel FORMAT block in line: {}'.format(line)))
        
        # Prepend GT FORMAT tag (so it's output first for GATK)
        format_tags.insert(0, 'GT')  # Inefficient inserting of GT but it's ok for a tiny list
        
        # Prepend GT values for both samples
        normal_sample_format_values.insert(0, makeIndelGT(sgt_normal))
        tumour_sample_format_values.insert(0, makeIndelGT(sgt_tumour))
        
        # Modify existing DP values for both samples
        normal_sample_format_values[format_tags.index("DP")] = makeIndelDP(format_tags, normal_sample_format_values)
        tumour_sample_format_values[format_tags.index("DP")] = makeIndelDP(format_tags, tumour_sample_format_values)
        
        # Add AD FORMAT tag
        format_tags.append('AD')
        
        # Add AD values for both samples
        normal_sample_format_values.append(makeIndelAD(format_tags, normal_sample_format_values))
        tumour_sample_format_values.append(makeIndelAD(format_tags, tumour_sample_format_values))
    
    # If this is a SNP line
    else:
        # Make sure AU/CU/GU/TU are defined in the FORMAT block
        if "AU" not in format_tags or "CU" not in format_tags or "GU" not in format_tags or "TU" not in format_tags:
            raise (ValueError('Missing AU/CU/GU/TU in SNP FORMAT block in line: {}'.format(line)))
        
        # Prepend GT FORMAT tag (so it's output first for GATK)
        format_tags.insert(0, 'GT')  # Inefficient inserting of GT but it's ok for a tiny list
        
        # Prepend GT values for both samples
        normal_sample_format_values.insert(0, makeSNVGT(sgt_normal, ref, alts))
        tumour_sample_format_values.insert(0, makeSNVGT(sgt_tumour, ref, alts))
        
        # Modify existing DP values for both samples
        normal_sample_format_values[format_tags.index("DP")] = makeSNVDP(format_tags, normal_sample_format_values)
        tumour_sample_format_values[format_tags.index("DP")] = makeSNVDP(format_tags, tumour_sample_format_values)
        
        # Add AD FORMAT tag
        format_tags.append('AD')
        
        # Add AD values for both samples
        normal_sample_format_values.append(makeSNVAD(format_tags, normal_sample_format_values, ref, alts))
        tumour_sample_format_values.append(makeSNVAD(format_tags, tumour_sample_format_values, ref, alts))
    
    new_format_field = ':'.join(str(v) for v in format_tags)
    new_normal_field = ':'.join(str(v) for v in normal_sample_format_values)
    new_tumour_field = ':'.join(str(v) for v in tumour_sample_format_values)
    
    line_parts[8] = new_format_field
    line_parts[9 + normal_index] = new_normal_field
    line_parts[9 + tumour_index] = new_tumour_field
    
    return '\t'.join(line_parts)


if __name__ == '__main__':
    import sys
    
    state = 'in_header_before_format'
    
    for line in sys.stdin:
        line = line.rstrip()
        if state == 'in_header_before_format':
            if line.startswith('##FORMAT='):
                state = 'in_header_after_format'
                sys.stdout.write('##FORMAT=<ID=GT,Number=1,Type=String,Description=\"Genotype\">\n')
                sys.stdout.write(
                    '##FORMAT=<ID=GQ,Number=1,Type=Integer,Description=\"Quality score for any somatic snv/indel ('
                    'duplicated from QSS/QSI), ie. for the ALT allele to be present at a significantly different '
                    'frequency in the tumor and normal\">\n')
                sys.stdout.write(
                    '##FORMAT=<ID=AD,Number=.,Type=Integer,Description=\"Allelic depths for the ref and alt alleles '
                    'in the order listed\">\n')
            sys.stdout.write(line + '\n')
        elif state == 'in_header_after_format':
            if line.startswith('#CHROM'):
                line_parts = line.split('\t')
                if len(line_parts) != 11:
                    eprint("Expect 11 records in the VCF header line. found " + str(len(line_parts)))
                sample_ids = line_parts[9:]
                #eprint("sample ids: " + ", ".join(sample_ids))
                sample_ids_upper = [x.upper() for x in sample_ids]
                if len(sample_ids) != 2:
                    sys.exit('strelka_add_GT.py requires two samples in the input VCF; {} were found'.format(
                            len(sample_ids)))
                if 'NORMAL' not in sample_ids_upper or 'TUMOR' not in sample_ids_upper:
                    # by default, Strelka reports the data with NORMAL, TUMOR in that order.
                    normal_index = 0
                    tumour_index = 1
                else:
                    normal_index = sample_ids_upper.index('NORMAL')
                    tumour_index = sample_ids_upper.index('TUMOR')
                eprint("Selected normal sample: " + sample_ids[normal_index])
                eprint("Selected tumour sample: " + sample_ids[tumour_index])
                state = 'in_data'
            sys.stdout.write(line + '\n')
        elif state == 'in_data':
            sys.stdout.write(transformDataLine(line, normal_index, tumour_index) + '\n')
