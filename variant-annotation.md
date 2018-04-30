# Variant Annotation
Seave manages [GEMINI](https://gemini.readthedocs.io/) databases to store and query SNV and Indel variants. For a number of reasons, we have opted to decouple the variant annotation from Seave:
* We wanted to handle WGS-sized data, which have >3Gb VCF files which are cumbersome to upload via a webpage;
* We wanted to leverage Gemini databases, as they are portable and convenient to query variants;
* We have a production analysis pipeline, so adding an analysis module at the end to push data into Seave was straightforward;
* Variant annotation using VEP and GEMINI is computationally expensive for WGS data, where we typically use 16- to 32-core servers. We felt that it makes sense to keep this heavy compute as part of the production genomics pipeline.

We use VEP and GEMINI in our production pipeline, and the b37d5 (1000 genomes + decoy) reference genome. To make it easier to adopt Seave, we provide the VEP and GEMINI command lines. Other options may work too, but this is what works well for us.

## SNV and Indels
### SNV and Indel VCF creation
#### Germline DNA
* VCF files are created by standard production pipelines. Our primary germline variant caller is GATK HaplotypeCaller v3.3. We use gVCF mode, so each sample has a g.vcf.gz file, which are joint-called in cohots using GATK GenotypeGVCFs.
* For WGS data we then apply VQSR.
#### Somatic DNA
* Our primary somatic variant caller is Strelka2. By default, the VCF files are incompatible with Gemini as they lack the required GT field, and the optional AD, DP, GQ fields, and always name the samples NORMAL and TUMOR.
* We have a script to post-process Strelka VCF files to make them compatible with Gemini, and provide fields that are useful for Seave.
* see strelka_add_to_FORMAT.py

### VCF normalisation
* Gemini (and many other tools) don't handle multi-allelic alleles well, and some variant callers don't properly left-align or normalise the variants.
* To improve the compatibility of the VCF files, the Gemini [documentation](https://gemini.readthedocs.io/en/latest/#new-gemini-workflow) describes the necessary steps to properly normalise a VCF file.

### VEP
* We use [Variant Effect Predictor](https://asia.ensembl.org/info/docs/tools/vep/script/vep_download.html) for annotating variants. Seave works well with VEOP v74, v79, v87.
* There is a newer version of [vep](https://github.com/Ensembl/ensembl-vep) which we have not thoroughly tested, so we recommend using the older [ensembl-tools-vep](https://asia.ensembl.org/info/docs/tools/vep/script/vep_download.html).
* See ./Makefile.vep87 to install VEP and the required plugins.
* We do not find the upstream_gene_variant and downstream_gene_variant annotations useful for coding, or regulatory variant analyses, so we delete them. See filter_vep.py.

	/vep/variant_effect_predictor.pl -i ./in/vcfgz/* --species homo_sapiens \
	  --vcf -o output.vcf --stats_file "$vcfgz_prefix".vep.html \
	  --offline --fork `nproc` --no_progress \
	  --canonical --polyphen b --sift b --symbol --numbers --terms so --biotype --total_length \
	  --plugin LoF,human_ancestor_fa:false \
	  --fields Consequence,Codons,Amino_acids,Gene,SYMBOL,Feature,EXON,PolyPhen,SIFT,Protein_position,BIOTYPE,CANONICAL,Feature_type,cDNA_position,CDS_position,Existing_variation,DISTANCE,STRAND,CLIN_SIG,LoF_flags,LoF_filter,LoF,RadialSVM_score,RadialSVM_pred,LR_score,LR_pred,CADD_raw,CADD_phred,Reliability_index,HGVSc,HGVSp \
	  --fasta /vep/homo_sapiens/87_GRCh37/Homo_sapiens.GRCh37.75.dna.primary_assembly.fa.gz \
	  --hgvs --shift_hgvs 1 \
	  --dir /vep

	#
	# VEP79 introduced spaces into the INFO field, for the sift & polyphen2 annotations. fix them.
	#
	perl -ne 'if ($_ !~ /^#/) { $_ =~ s/ /_/g; print $_; } else { print $_; }' output.vcf > output.clean.vcf
	mv output.clean.vcf output.vcf
	
	#
	# replace upstream_gene_variant/downstream_gene_variant with intergenic_variant
	#
	python ./filter_vep.py --vcf output.vcf \
	  | vcf-sort \
	  | bgzip > output.sorted.vcf.gz
	  tabix -p vcf output.sorted.vcf.gz

### GEMINI
* Gemini installation is straightforward from the [documentation](https://gemini.readthedocs.io/en/latest/#new-installation). We have tested extensively with v0.11.0, and more recenlty with 0.17, 0.18.

	gemini load -v "$vcfgz_path" --cores `nproc` --skip-gerp-bp -t VEP out.db

### Push data into Seave
* An API token is required to push data into the Server. See the [Administration Guide] (https://github.com/KCCG/seave-documentation). Alternatively, Adminstrators can import data via a URL.
* variables: filename (the gemini db name to import), group (data can be imported and assigned to a group).

	TOKEN=xxxxxxxxxxxxxxx
	MD5=$(md5sum "$filename" | cut -d" " -f1)
	SEAVE="https://www.seave.bio"
	SEAVE_URL="${SEAVE}/dx_import.php?token=${TOKEN}&url=${DX_URL}&md5=${MD5}"
	SEAVE_URL="${SEAVE_URL}&import_type=gemini_db"
	SEAVE_URL="${SEAVE_URL}&group=${group}"
	OUTCOME=$(curl '-s' '-S' '-L' "${SEAVE_URL}")
	echo "Seave reported: $OUTCOME"

## Genome Block Store (GBS)
To manage CNV and SV data, we developed the GBS. The GBS is a scalable MySQL database designed to store large genomic segments or blocks (e.g. deletions, duplications, inversions, MEIs or ROH), or linked blocks (e.g. gene fusion breakpoints), with additional annotations (e.g., copy number or breakpoint read depth).

CNV/SV data is created by our production bioinformatic pipelines, and then imported into the GBS, as single-sample files.

### Push CNV/SV data into the GBS
* An API token is required to push data into the Server. See the [Administration Guide] (https://github.com/KCCG/seave-documentation). Alternatively, Adminstrators can import data via a URL.
* variables: filename (the CNV/SV file to be imported), method (name of the GBS-supported method), sample_name (optional sample_name), group (data can be imported and assigned to a group).

	TOKEN=xxxxxxxxxxxxxxx
	MD5=$(md5sum "$filename" | cut -d" " -f1)
	SEAVE="https://www.seave.bio"
	SEAVE_URL="${SEAVE}/dx_import.php?token=${TOKEN}&url=${DX_URL}&md5=${MD5}"

	all_methods=(CNVnator LUMPY Sequenza ROHmer VarpipeSV Manta CNVkit)
	if [[ ! " ${all_methods[@]} " =~ " ${method} " ]]; then
		echo "A valid 'method' must be specified if data is being imported into the GBS."
		exit 1
	fi

	#
	# VCF files contain the sample names in the header. Many CNV/SV callers produce TSV files, so you also
	# Need to specify the sample name.
	#
	sample_name_methods=(CNVnator Sequenza ROHmer CNVkit)
	# If the method specified doesn't include a sample name in the file to import, make sure the user specified one
	if [[ " ${sample_name_methods[@]} " =~ " ${method} " ]]; then
		if [[ "$sample_name" == "" ]]; then
			echo "You must specify a sample name for GBS import methods that don't include a sample name in the file to import"
			exit 1
		fi
		SEAVE_URL="${SEAVE_URL}&sample_name=${sample_name}"
	fi

	SEAVE_URL="${SEAVE_URL}&import_type=GBS&method=${method}"
	SEAVE_URL="${SEAVE_URL}&group=${group}"
	OUTCOME=$(curl '-s' '-S' '-L' "${SEAVE_URL}")
	echo "Seave reported: $OUTCOME"

