#!/usr/bin/perl 

use strict; 
use warnings;

# This script is written to be piped into by a Gemini query output for compound heterozygotes
# Importantly: a single line must be prepended to this piped input that specifies the affected and unaffected sample names (e.g. affected:<sample name>;;;<sample name> unaffected:<sample name>;;;<sample name>;;;<sample name>
# Example of using it from the command line: gemini query -q "select *,(gts).(*) from variants where (aaf_esp_all<0.01 or aaf_esp_all is null) and (aaf_1kg_all<0.01 or aaf_1kg_all is null) and (impact_severity='MED' or impact_severity='HIGH') and gene in (select gene from variants where (aaf_esp_all<0.01 or aaf_esp_all is null) and (aaf_1kg_all<0.01 or aaf_1kg_all is null) and (impact_severity='MED' or impact_severity='HIGH') group by gene having count(*)>1) order by gene" --gt-filter "gt_types.14-059-0394 == HET and gt_types.14-059-0404 == HET or gt_types.14-059-0394 == HET and gt_types.14-059-0402 == HET" --header /Users/velimir/Desktop/sieve-data/RTT1-VEP.gemini.db | (echo "unaffected:14-059-0404;;;14-059-0402 affected:14-059-0394 " && cat) | perl /Users/velimir/Documents/sieve/sieve/scripts/comp_het_filter.pl

# LIMITATION: This script will take a long time to run a massive list if, for example, a very loose Gemini query is input into it

######################################################
# READ INPUT AND OBTAIN SAMPLE NAMES AND COLUMN POSITIONS
# THEN HASH EACH VARIANT RESULT ROW
######################################################

my $line_counter = 0; # Line counter for keeping track of parsing position

my @sample_names_affected;
my @sample_names_unaffected;

my %sample_column_numbers;

my %gene_positions;

my $num_column_gene;
my $num_column_alt;
my $num_column_start;
my $num_column_end;

my %result_rows;
my %concat_gts;
my @exclude_genes;

# Read from piped input and capture family sample names, header column positions and then each variant row
while (<>) { # Read from piped input
	my $line = $_; # Capture the current input line
	
	$line =~ s/\n//;
	
	#############################################
	# PARSE PREPENDED LINE WITH SAMPLE NAMES
	#############################################
	
	# The first line of the input is the prepended line specifying the sample names of the affected and unaffected samples
	if ($line_counter == 0) {
		if ($line =~ /affected:(.*?)\s/) {
			@sample_names_affected = split(/;;;/, $1); # Split the columns into elements of an array
		}
		
		if ($line =~ /unaffected:(.*?)\s/) {
			@sample_names_unaffected = split(/;;;/, $1); # Split the columns into elements of an array
		}

		if (scalar(@sample_names_affected) == 0) {
			print "FATAL ERROR: sample name(s) of affected individuals have not been correctly specified to the comp het filter script.";
			
			exit;
		}
	
	#############################################
	# PARSE GEMINI HEADER LINE FOR COLUMN NUMBERS
	#############################################
	
	} elsif ($line_counter == 1) { # The second line of the input is the header line from the Gemini output
		my @columns = split(/\t/, $line); # Split the columns into elements of an array
		
		for (my $i=0; $i<scalar(@columns); $i++) { # Go through every column
			if ($columns[$i] eq "gene") { # Capture the 'gene' column position
				$num_column_gene = $i;
			} elsif ($columns[$i] eq "alt") { # Capture the 'alt' column position
				$num_column_alt = $i;
			} elsif ($columns[$i] =~ /.*gts\.(.*)/) { # If this is a sample genotype column
				my $temp_sample_name = $1;
				
				if (grep(/^$temp_sample_name$/, @sample_names_affected) || grep(/^$temp_sample_name$/, @sample_names_unaffected)) {
					$sample_column_numbers{$1} = $i;
				}
			} elsif ($columns[$i] eq "start") { # Capture the 'start' column position
				$num_column_start = $i;
			} elsif ($columns[$i] eq "end") { # Capture the 'end' column position
				$num_column_end = $i;
			}
		}
		
		# If a column number has not been found for one of the specified sample names
		if ((scalar(@sample_names_affected) + scalar(@sample_names_unaffected)) != scalar(keys(%sample_column_numbers))) {
			print "FATAL ERROR: could not find one of the required column numbers in the header row.";
			
			exit;
		}
		
		print $line."\n"; # Print the header line so it gets used downstream by Seave
		
	#############################################
	# HASH RESULT ROWS
	#############################################
	
	} else { # If this is a result row, save it to a hash for parsing
		my @split_columns = split(/\t/, $line); # Split by column
		
		# Concatenate genotypes
		foreach my $sample (keys(%sample_column_numbers)) {
			if (!defined($concat_gts{$split_columns[$num_column_gene]}{$sample})) {
				$concat_gts{$split_columns[$num_column_gene]}{$sample} = $split_columns[$sample_column_numbers{$sample}];
			} else {
				$concat_gts{$split_columns[$num_column_gene]}{$sample} .= ";".$split_columns[$sample_column_numbers{$sample}];
			}
		}
		
		$result_rows{$line_counter} = $line; # Store the raw lines for removing and outputting later
	}
	
	$line_counter++; # Iterate the line counter
}
	
#############################################
# DETERMINE GENES THAT HAVE NON-COMPHET INHERITANCE PATTERNS
#############################################

if (scalar(@sample_names_unaffected) != 0) { # Only perform inheritance pattern checking if there are unaffected samples to compare against
	foreach my $gene (keys(%concat_gts)) {
		# Go through each unaffected and compare it against each affected in turn
		foreach my $unaffected_sample (@sample_names_unaffected) {
			foreach my $affected_sample (@sample_names_affected) {
				# If the concatenated genotypes match, mark the gene for deletion
				if ($concat_gts{$gene}{$unaffected_sample} eq $concat_gts{$gene}{$affected_sample}) {
					if (!grep(/^$gene$/, @exclude_genes)) { # If the gene has not been added to a list genes to exclude already
						push (@exclude_genes, $gene);
					}
				}
			}
		}
	}
}

#############################################
# REMOVE GENES WHERE ONLY ONE UNAFFECTED SAMPLE IS PRESENT AND IT DOES NOT HAVE 1 HOM AND 1 HET GTS
#############################################

if (scalar(@sample_names_unaffected) == 1) {
	foreach my $gene (keys(%concat_gts)) {
		# Genotypes are concatenated with a semicolon as a delimiter, split them
		my @split_unaffected_gts = split(/;/, $concat_gts{$gene}{$sample_names_unaffected[0]});

		# Two flags for whether a variant of a specific type was found
		my $het_flag = 0;
		my $hom_flag = 0;
		
		# Go through each genotype
		foreach my $unaffected_gts (@split_unaffected_gts) {
			# Make sure one is het and one is hom by setting flags if each is found
			if ($unaffected_gts =~ /([ATCG]*)\/([ATCG]*)/) {
				if ($1 ne $2) {
					$het_flag = 1;
				} else {
					$hom_flag = 1;
				}
			}
		}
		
		# If a het and hom genotype have not been found for this single unaffected sample, remove all variants in this gene
		if ($het_flag != 1 || $hom_flag != 1) {
			push (@exclude_genes, $gene);
		}
	}
}

######################################################
# REMOVE ANY VARIANTS THAT ARE THE ONLY VARIANTS BELONGING TO A GENE
# This occurs due to the --gt-filter command filtering out some variants leaving singletons
# Also due to rows being deleted above leaving one remaining

# REMOVE ALL VARIANTS FOR GENES WHERE THE COMP HET PATTERN HAS NOT BEEN MET
######################################################

my @genes;
my @two_plus_occurrence_genes;

# Create a list of genes for each variant (in order to remove singletons)
foreach my $result_num (keys %result_rows) { # Go through every raw result row
	my @split_columns = split(/\t/, $result_rows{$result_num}); # Split by column
	
	push (@genes, $split_columns[$num_column_gene]);
}

# Only keep genes that are present 2+ times in the array (equal to 'uniq -d')
foreach my $gene (@genes) { # Go through every gene
	if (scalar(grep(/^$gene$/, @genes)) > 1) { # If the gene is present more than once in the total list
		if (!grep(/^$gene$/, @two_plus_occurrence_genes)) { # If the gene has not been added to a list of unique genes present 2 or more times
			push(@two_plus_occurrence_genes, $gene);
		}
	}
}

# Remove all rows in the original result that have genes only once or where the gene has been marked for deletion for not matching the comp het pattern
foreach my $result_num (keys %result_rows) {
	my @split_columns = split(/\t/, $result_rows{$result_num}); # Split by column
	
	if (!grep(/^$split_columns[$num_column_gene]$/, @two_plus_occurrence_genes)) {
		delete $result_rows{$result_num};
	} elsif (grep(/^$split_columns[$num_column_gene]$/, @exclude_genes)) {
		delete $result_rows{$result_num};
	}
}

######################################################
# PRINT REMAINING ROWS TO OUTPUT
######################################################

# Go through the raw results and only keep those rows that affect the gene in the final gene list
foreach my $result_num (keys %result_rows) {
	print $result_rows{$result_num}."\n";
}

exit;