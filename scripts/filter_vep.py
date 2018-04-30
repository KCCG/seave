import argparse
import gzip
import re
import sys

# Filter records from a VEP annotated VCF file. 
# Specifically, this filters [downstream_gene_variant', 'upstream_gene_variant' records, and replaces
# them with intergenic_variant if doing so removes all annotations.
# 
# Acknowledgements to Konrad Karczewski (konradjk), accessed 2016-06-26
# https://raw.githubusercontent.com/konradjk/loftee/master/src/read_vep_vcf.py
#
# Mark Cowley, KCCG.
#

__author__ = 'marcow'

def dict2orderedValues (d, keys):
    res = []
    for key in keys:
        res.append(d[key])
    return res

def main(args):
    f = gzip.open(args.vcf) if args.vcf.endswith('.gz') else open(args.vcf)
    vep_field_names = None
    header = None
    for line in f:
        line = line.strip()

        if args.ignore:
            print >> sys.stdout, line
            continue

        # Reading header lines to get VEP and individual arrays
        if line.startswith('#'):
            print >> sys.stdout, line
            line = line.lstrip('#')
            if line.find('ID=CSQ') > -1:
                print >> sys.stdout, '##command=' + " ".join(sys.argv)
                vep_field_names = line.split('Format: ')[-1].strip('">').split('|')
                blank_record = [''] * len(vep_field_names)
                blank_record[vep_field_names == 'Consequence'] = 'intergenic_variant'
                blank_record = dict(zip(vep_field_names, blank_record))
            if line.startswith('CHROM'):
                header = line.split()
                header = dict(zip(header, range(len(header))))
            continue

        if vep_field_names is None:
            print >> sys.stderr, "VCF file does not have a VEP header line. Exiting."
            sys.exit(1)
        if header is None:
            print >> sys.stderr, "VCF file does not have a header line (CHROM POS etc.). Exiting."
            sys.exit(1)

        # Pull out annotation info from INFO and ALT fields
        # fields = vcf line, can be accessed using:
        # fields[header['CHROM']] for chromosome,
        # fields[header['ALT']] for alt allele,
        # or samples using sample names, as fields[header['sample1_name']]
        fields = line.split('\t')
        
        info_field = dict([(x.split('=', 1)) if '=' in x else (x, x) for x in re.split(';(?=\w)', fields[header['INFO']])])

        if 'CSQ' not in info_field:
            print >> sys.stdout, line
            continue

        # annotations = list of dicts, each corresponding to a transcript-allele pair
        # (each dict in annotations contains keys from vep_field_names)
        annotations = [dict(zip(vep_field_names, x.split('|'))) for x in info_field['CSQ'].split(',') if len(vep_field_names) == len(x.split('|'))]
       
        selected = [x for x in annotations if x['Consequence'] not in
                    ['downstream_gene_variant', 'upstream_gene_variant']]
        if len(selected) == 0:
            selected = [blank_record]
        hits = ','.join(['|'.join(dict2orderedValues(x, vep_field_names)) for x in selected])
        
        print >> sys.stdout, re.sub(';CSQ=[^\t]*', ';CSQ=' + hits, line)

    f.close()

if __name__ == '__main__':
    parser = argparse.ArgumentParser()

    parser.add_argument('--vcf', '--input', '-i', help='Input VCF file (from VEP+LoF); may be gzipped', required=True)
    parser.add_argument('--ignore', help='Pass through all variants.', action='store_true', default=False)
    args = parser.parse_args()
    if args.ignore:
        print >> sys.stderr, "Skipping up/downstream filtering"
    main(args)

