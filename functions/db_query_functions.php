<?php
	
// These are functions for extracting information from databases, be they external or Gemini

#############################################
# GEMINI QUERY CONSTRUCTION AND EXECUTION
#############################################

function gemini_query() {
	// Make sure the database exists
	if (!file_exists($GLOBALS["configuration_file"]["gemini"]["db_dir"]."/".$_SESSION["query_group"]."/".$_SESSION["query_db"])) {
		return false;
	}

	$previous_filter_flag = 0; # Flag for whether a filter was used and therefore an AND is required for the next filter, off by default
	$query_filters = ""; # Define the query filters variables to populate it later
	
	######################
	
	// If a family was specified, fetch information for it from the database
	if ($_SESSION["family"] != "") {
		$family_info = extract_familial_information($_SESSION["query_group"]."/".$_SESSION["query_db"]);
	}
	
	######################
	
	// bash -c to force the execution to happen in bash (set -o pipefail doesn't work in /bin/sh)
	// set -o pipefail ensures that any failure amongst the pipes makes it to the end rather than the last command being the exit status
	$cmd = "bash -c '(set -o pipefail && ".$GLOBALS["configuration_file"]["gemini"]["binary"].' ';
	
	######################
	
	$cmd .= 'query -q "SELECT '; # Start the query
	
	######################
	
	// If information is only to be returned for the family selected
	if ($_SESSION["return_information_for"] == "family_only" && $_SESSION["family"] != "") {
		$cmd .= '*, ';
		
		// Go through each sample in the family selected
		foreach (array_keys($family_info[$_SESSION["family"]]) as $sample_in_family) {
			$cmd .= 'gts.'.$sample_in_family.', ';
			$cmd .= 'gt_depths.'.$sample_in_family.', ';
			$cmd .= 'gt_ref_depths.'.$sample_in_family.', ';
			$cmd .= 'gt_alt_depths.'.$sample_in_family.', ';
			$cmd .= 'gt_quals.'.$sample_in_family.', ';
		}
		
		$cmd = substr($cmd, 0, -2); // Remove the last ", " that was added by the loop above
	} else {
		$cmd .= '*, (gts).(*), (gt_depths).(*), (gt_ref_depths).(*), (gt_alt_depths).(*), (gt_quals).(*)'; # Columns to pull out
	}
	
	######################
	
	$cmd .= ' FROM variants '; # Table to search
	
	######################
	
	if (filter_flag()) {  # If result filters are required, a WHERE must be printed in the statement
		$cmd .= 'WHERE ';
		
		######################

		# SWITCH FROM USING $cmd TO $query_filters HERE
		# because the compound het analysis requires printing the filters twice
		
		######################
		
		// If one or more search regions were specified to search, create the per-chromosome search queries
		if ($_SESSION["regions"] != "") {
			$regions = explode(";", $_SESSION["regions"]);
			
			foreach ($regions as $region) {
				preg_match('/([\w]*?):([0-9]*)\-([0-9]*)/', $region, $matches); # Pull out the chromosome, start and end using a regex
				
				// Push the search region for the current chromosome to an array
				$search_regions[$matches[1]][] = "((start BETWEEN ".$matches[2]. " AND ".$matches[3].") OR (end BETWEEN ".$matches[2]." AND ".$matches[3]."))";
			}
		}
		
		// If one or more search regions were specified to exclude, create the per-chromosome exclusion queries
		if ($_SESSION["exclude_regions"] != "") {
			$regions = explode(";", $_SESSION["exclude_regions"]);
			
			foreach ($regions as $region) {
				preg_match('/([\w]*?):([0-9]*)\-([0-9]*)/', $region, $matches); # Pull out the chromosome, start and end using a regex
				
				// If no search intervals were supplied for this chromosome, we need to explicitly search the whole chromosome in order to exclude the exlusion regions
				if (!isset($search_regions[$matches[1]])) {
					$search_regions[$matches[1]][] = "((start BETWEEN 0 AND 500000000) OR (end BETWEEN 0 AND 500000000))";
				}
				
				// Push the exclusion search region for the current chromosome to an array
				$exclude_regions[$matches[1]][] = "((start NOT BETWEEN ".$matches[2]. " AND ".$matches[3].") OR (end NOT BETWEEN ".$matches[2]." AND ".$matches[3]."))";
			}
		}

		// If search regions and exclude regions have been supplied (if only exclusion regions are supplied, search are created as part of that) or only search regions have been supplied
		if (isset($search_regions, $exclude_regions) || isset($search_regions)) {
			$regions_query = ""; # Variable to store the SQL query for the search/exlusion regions
			
			$regions_query .= "(";
			
			// Create a separate statement for each chromosome separated by OR
			foreach (array_keys($search_regions) as $chromosome) {
				$regions_query .= "(";
				
				$regions_query .= 'chrom = \"'.$chromosome.'\" AND ';
				
				$regions_query .= "(";
				
				// Create a separate statement for each inclusion region separated by OR and in a bracket of its own
				foreach ($search_regions[$chromosome] as $region_sql) {
					$regions_query .= $region_sql." OR ";
				}
				
				$regions_query = substr($regions_query, 0, -4); // Remove the last " OR " that was added by the loop above
				
				$regions_query .= ")";
				
				// If exclusion regions exist, create a separate statement for each exclusion region separated by OR and also separated from the inclusion statement with AND
				if (isset($exclude_regions) && isset($exclude_regions[$chromosome])) {
					$regions_query .= " AND ";
					
					$regions_query .= "(";
					
					foreach ($exclude_regions[$chromosome] as $region_sql) {
						$regions_query .= $region_sql." OR ";
					}
					
					$regions_query = substr($regions_query, 0, -4); // Remove the last " OR " that was added by the loop above
					
					$regions_query .= ")";
				}
				
				$regions_query .= ") OR ";
			}
			
			$regions_query = substr($regions_query, 0, -4); // Remove the last " OR " that was added by the loop above
			
			$regions_query .= ")";
			
			$query_filters .= add_filter($previous_filter_flag, $regions_query);
			
			$previous_filter_flag = 1; # Makes sure that downstream queries add an AND
		}
		
		######################
		
		if ($_SESSION["search_variant_type"] == "snp" || $_SESSION["search_variant_type"] == "indel") {
			$query_filters .= add_filter($previous_filter_flag, 'type = \"'.$_SESSION["search_variant_type"].'\"');
			
			$previous_filter_flag = 1; # Makes sure that downstream queries add an AND
		}
		
		######################
		
		// Array of genes to search, will have 0 elements if no genes submitted
		if (count($_SESSION["gene_list_to_search"]) > 0) { # If genes were entered
			$gene_filters = 'gene IN (\"'; // Start the gene query
			
			$gene_filters .= implode('\", \"', $_SESSION["gene_list_to_search"]); // Join all genes together so they end up in this format (\"GENE1\", \"GENE2\"), single genes will just be printed without the imploding glue
			
			$gene_filters .= '\")';
			
			$query_filters .= add_filter($previous_filter_flag, $gene_filters);
			
			$previous_filter_flag = 1; # Makes sure that downstream queries add an AND
		}
		
		######################
		
		// Array of genes to exclude, will have 0 elements if no genes submitted		
		if (count($_SESSION["gene_list_to_exclude"]) > 0) { # If exclusion genes were entered
			$gene_filters = 'gene NOT IN (\"'; // Start the gene query
			
			$gene_filters .= implode('\", \"', $_SESSION["gene_list_to_exclude"]); // Join all genes together so they end up in this format (\"GENE1\", \"GENE2\"), single genes will just be printed without the imploding glue
			
			$gene_filters .= '\")';
			
			$query_filters .= add_filter($previous_filter_flag, $gene_filters);
			
			$previous_filter_flag = 1; # Makes sure that downstream queries add an AND
		}
			
		######################

		if ($_SESSION["search_impact"] != "" && $_SESSION["search_impact"] != "all") {
			if ($_SESSION["search_impact"] == "high") {
				$query_filters .= add_filter($previous_filter_flag, 'impact_severity = \"HIGH\"');
			} elseif ($_SESSION["search_impact"] == "medhigh") {
				$query_filters .= add_filter($previous_filter_flag, 'impact_severity = \"HIGH\" OR impact_severity = \"MED\"');
			} elseif ($_SESSION["search_impact"] == "lof") {
				$query_filters .= add_filter($previous_filter_flag, 'is_lof = 1');
			} elseif ($_SESSION["search_impact"] == "coding") {
				$query_filters .= add_filter($previous_filter_flag, 'is_coding = 1');
			}
		
			$previous_filter_flag = 1; # Makes sure that downstream queries add an AND	
		}
		
		######################
		
		if ($_SESSION["min_cadd"] > 0) { # If the minimum variant quality filter has been selected
			$query_filters .= add_filter($previous_filter_flag, 'cadd_scaled >= '.$_SESSION["min_cadd"].' OR cadd_scaled is null');
						
			$previous_filter_flag = 1; # Makes sure that downstream queries add an AND
		}
		
		######################
		
		if ($_SESSION["min_qual"] > 0) { # If the minimum variant quality filter has been selected
			$query_filters .= add_filter($previous_filter_flag, 'qual >= '.$_SESSION["min_qual"]);
			
			$previous_filter_flag = 1; # Makes sure that downstream queries add an AND
		}
		
		######################
		
		if ($_SESSION["1000gmaf"] > 0) { # Only incorporate a 1000 genomes MAF filter if the desired MAF is > 0
			$query_filters .= add_filter($previous_filter_flag, 'aaf_1kg_all < '.($_SESSION["1000gmaf"]/100).' OR aaf_1kg_all is null');
			
			$previous_filter_flag = 1; # Makes sure that downstream queries add an AND
		}
		
		######################
		
		if ($_SESSION["espmaf"] > 0) { # Only incorporate an ESP MAF filter if the desired MAF is > 0
			$query_filters .= add_filter($previous_filter_flag, 'aaf_esp_all < '.($_SESSION["espmaf"]/100).' OR aaf_esp_all is null');
			
			$previous_filter_flag = 1; # Makes sure that downstream queries add an AND
		}
		
		######################
		
		if ($_SESSION["exacmaf"] > 0) { # Only incorporate an ExAC MAF filter if the desired MAF is > 0
			$query_filters .= add_filter($previous_filter_flag, 'aaf_adj_exac_all < '.($_SESSION["exacmaf"]/100).' OR aaf_adj_exac_all is null');
			
			$previous_filter_flag = 1; # Makes sure that downstream queries add an AND
		}
		
		######################
		
		if ($_SESSION["exclude_dbsnp_common"] == 1) {
			$query_filters .= add_filter($previous_filter_flag, 'is_dbsnp_common = 0');
			
			$previous_filter_flag = 1; # Makes sure that downstream queries add an AND
		}
		
		if ($_SESSION["exclude_dbsnp_flagged"] == 1) {
			$query_filters .= add_filter($previous_filter_flag, 'is_dbsnp_flagged = 0');
			
			$previous_filter_flag = 1; # Makes sure that downstream queries add an AND
		}
		
		######################
		
		if ($_SESSION["exclude_failed_variants"] == 1) {
			$query_filters .= add_filter($previous_filter_flag, 'filter is null OR filter like \"%LOHPASS%\"');
			
			$previous_filter_flag = 1; # Makes sure that downstream queries add an AND
		}
		
		######################

		# SWITCH BACK TO USING $cmd INSTEAD OF $query_filters HERE
		# because the compound het analysis requires printing the filters twice
		
		######################
		
		if ($_SESSION["analysis_type"] == "analysis_comp_het") { # If the analysis type is compound heterozygous mutations
			$cmd .= $query_filters;
			$cmd .= ' AND gene IN (SELECT gene FROM variants WHERE ';
			$cmd .= $query_filters;
		} else { # If this is not a comp het analysis, just print the query filters
			$cmd .= $query_filters;
		}
	}
	
	######################
	
	if ($_SESSION["analysis_type"] == "analysis_comp_het") { # If the analysis type is compound heterozygous mutations
		$cmd .= ' GROUP BY gene HAVING count(*)>1) ORDER BY gene';
	}
	
	######################
	
	$cmd .= '"'; # Closing quotation mark for the query
	
	$previous_filter_flag = 0; # Reset previous filter flag for the --gt-filter filters (if any)
	
	######################
	
	if (gt_filter_flag()) { # If a further query filter is required, a --gt-filter must be printed in the query
		$cmd .= ' --gt-filter "';

		######################
		
		// If the current database has pedigree information and an analysis type has been specified and a family to analyze has been specified
		if ($_SESSION["hasped"] == "Yes" && $_SESSION["analysis_type"] != "" && $_SESSION["family"] != "") {
			// Pull out the affected status of all samples in the current family
			$family_affected_status = family_affected_status($family_info[$_SESSION["family"]]);
			
			#############################################
			# NONE FILTER
			#############################################
			
			if ($_SESSION["analysis_type"] == "analysis_none") {
				$cmd .= '(';
				
				// Go through every individual within the selected family
				foreach (array_keys($family_info[$_SESSION["family"]]) as $sample_name) {
					$cmd .= '(';
						$cmd .= 'gt_types.'.$sample_name.' == HOM_ALT';
					$cmd .= ')';
					
					$cmd .= ' or ';
					
					$cmd .= '(';
						$cmd .= 'gt_types.'.$sample_name.' == HET';
					$cmd .= ')';
					
					$cmd .= ' or ';
				}
				
				$cmd = substr($cmd, 0, -4); // Remove the last ' or ' that was added by the loop
				
				$cmd .= ')';

			#############################################
			# HOMOZYGOUS RECESSIVE FILTER
			#############################################
			
			} elseif ($_SESSION["analysis_type"] == "analysis_hom_rec") {
				$cmd .= '(';
				
				// Go through every individual within the selected family
				foreach (array_keys($family_info{$_SESSION["family"]}) as $sample_name) {
					// If the individual is affected
					if (is_affected($family_info{$_SESSION["family"]}{$sample_name}{"phenotype"})) {
						$cmd .= '(';
						$cmd .= 'gt_types.'.$sample_name.' == HOM_ALT';
						$cmd .= ')';
					// If the individual is unaffected
					} elseif (is_unaffected($family_info{$_SESSION["family"]}{$sample_name}{"phenotype"})) {
						$cmd .= '(';
						$cmd .= 'gt_types.'.$sample_name.' != HOM_ALT';
						$cmd .= ')';
					} else {
						continue;
					}
					
					$cmd .= ' and ';
				}
				
				$cmd = substr($cmd, 0, -5); // Remove the last ' and ' that was added by the loop
				
				$cmd .= ')';
				
			#############################################
			# HETEROZYGOUS DOMINANT FILTER
			#############################################
			
			} elseif ($_SESSION["analysis_type"] == "analysis_het_dom") {
				$cmd .= '(';
				
				// Go through every individual within the selected family
				foreach (array_keys($family_info{$_SESSION["family"]}) as $sample_name) {
					// If the individual is affected
					if (is_affected($family_info{$_SESSION["family"]}{$sample_name}{"phenotype"})) {
						$cmd .= '(';
						$cmd .= 'gt_types.'.$sample_name.' == HET';
						$cmd .= ')';
					// If the individual is unaffected
					} elseif (is_unaffected($family_info{$_SESSION["family"]}{$sample_name}{"phenotype"})) {
						$unaffected_individuals_present_flag = 1;
						
						$cmd .= '(';
						$cmd .= 'gt_types.'.$sample_name.' != HET';
						$cmd .= ')';
					} else {
						continue;
					}
					
					$cmd .= ' and ';
				}
				
				$cmd = substr($cmd, 0, -5); // Remove the last ' and ' that was added by the loop
				
				$cmd .= ')';
				
				######################
				
				// If there are more than 1 unaffected individuals, print a separate block to make sure all unaffecteds have the same genotype type (e.g. HOM_ALT or HOM_REF, ignoring ./.)
				if ($unaffected_individuals_present_flag == 1 && count($family_affected_status["unaffected"]) > 1) {
					$cmd .= ' and (';
					$cmd .= '(';
					
					######################
					
					// Go through every unaffected individual within the selected family
					foreach (array_keys($family_info{$_SESSION["family"]}) as $sample_name) {
						if (is_unaffected($family_info{$_SESSION["family"]}{$sample_name}{"phenotype"})) {
							$cmd .= '(';
								$cmd .= '(';
									$cmd .= 'gt_types.'.$sample_name.' == HOM_ALT';
								$cmd .= ')';
								
							$cmd .= ' or ';
								
								$cmd .= '(';
									$cmd .= 'gt_types.'.$sample_name.' == UNKNOWN';
								$cmd .= ')';
							$cmd .= ')';
							
							$cmd .= ' and ';
						}
					}
					
					$cmd = substr($cmd, 0, -5); // Remove the last ' and ' that was added by the loop
					
					$cmd .= ')';
					
					######################
					
					$cmd .= ' or (';
					
					// Go through every unaffected individual within the selected family
					foreach (array_keys($family_info{$_SESSION["family"]}) as $sample_name) {
						if (is_unaffected($family_info{$_SESSION["family"]}{$sample_name}{"phenotype"})) {
							$cmd .= '(';
								$cmd .= '(';
									$cmd .= 'gt_types.'.$sample_name.' == HOM_REF';
								$cmd .= ')';
								
							$cmd .= ' or ';
								
								$cmd .= '(';
									$cmd .= 'gt_types.'.$sample_name.' == UNKNOWN';
								$cmd .= ')';
							$cmd .= ')';
							
							$cmd .= ' and ';
						}
					}
					
					$cmd = substr($cmd, 0, -5); // Remove the last ' and ' that was added by the loop
					
					$cmd .= ')';
					
					######################
					
					$cmd .= ')';
					
				}
				
			#############################################
			# DE NOVO DOMINANT FILTER
			#############################################	
			
			} elseif ($_SESSION["analysis_type"] == "analysis_denovo_dom") { # If the analysis type is de novo dominant	
				$cmd .= '(';
				
				// Go through every individual within the selected family (all unaffected's HOM_ALT)
				foreach (array_keys($family_info{$_SESSION["family"]}) as $sample_name) {
					// If the individual is affected
					if (is_affected($family_info{$_SESSION["family"]}{$sample_name}{"phenotype"})) {
						$cmd .= '(';
						$cmd .= 'gt_types.'.$sample_name.' == HET';
						$cmd .= ')';
					// If the individual is unaffected
					} elseif (is_unaffected($family_info{$_SESSION["family"]}{$sample_name}{"phenotype"})) {
						$cmd .= '(';
						$cmd .= 'gt_types.'.$sample_name.' == HOM_ALT';
						$cmd .= ')';
					} else {
						continue;
					}
					
					$cmd .= ' and ';
				}
				
				$cmd = substr($cmd, 0, -5); // Remove the last ' and ' that was added by the loop
				
				$cmd .= ') or (';

				// Go through every individual within the selected family (all unaffected's HOM_REF)
				foreach (array_keys($family_info{$_SESSION["family"]}) as $sample_name) {
					// If the individual is affected
					if (is_affected($family_info{$_SESSION["family"]}{$sample_name}{"phenotype"})) {
						$cmd .= '(';
						$cmd .= 'gt_types.'.$sample_name.' == HET';
						$cmd .= ')';
					// If the individual is unaffected
					} elseif (is_unaffected($family_info{$_SESSION["family"]}{$sample_name}{"phenotype"})) {
						$cmd .= '(';
						$cmd .= 'gt_types.'.$sample_name.' == HOM_REF';
						$cmd .= ')';
					} else {
						continue;
					}
					
					$cmd .= ' and ';
				}
				
				$cmd = substr($cmd, 0, -5); // Remove the last ' and ' that was added by the loop
				
				$cmd .= ') or (';

				// Go through every individual within the selected family (all unaffected's HOM_REF)
				foreach (array_keys($family_info{$_SESSION["family"]}) as $sample_name) {
					// If the individual is affected
					if (is_affected($family_info{$_SESSION["family"]}{$sample_name}{"phenotype"})) {
						$cmd .= '(';
						$cmd .= 'gt_types.'.$sample_name.' == HET';
						$cmd .= ')';
					// If the individual is unaffected
					} elseif (is_unaffected($family_info{$_SESSION["family"]}{$sample_name}{"phenotype"})) {
						$cmd .= '(';
						$cmd .= 'gt_types.'.$sample_name.' == UNKNOWN';
						$cmd .= ')';
					} else {
						continue;
					}
					
					$cmd .= ' and ';
				}
				
				$cmd = substr($cmd, 0, -5); // Remove the last ' and ' that was added by the loop
				
				$cmd .= ')';
				
			#############################################
			# COMPOUND HETEROZYGOUS FILTER
			#############################################
			
			} elseif ($_SESSION["analysis_type"] == "analysis_comp_het") { # If the analysis type is compound heterozygous mutations
				$cmd .= '(';
				
				$unaffected_individuals_present_flag = 0;
				
				// Go through every individual within the selected family
				foreach (array_keys($family_info{$_SESSION["family"]}) as $sample_name) {
					// If the individual is affected, add a "must be HET" clause
					if (is_affected($family_info{$_SESSION["family"]}{$sample_name}{"phenotype"})) {
						$cmd .= '(';
						$cmd .= 'gt_types.'.$sample_name.' == HET';
						$cmd .= ')';
						
						$cmd .= ' and ';
					// If the individual is unaffected, add a "must NOT be HOM_ALT" clause
					} elseif (is_unaffected($family_info{$_SESSION["family"]}{$sample_name}{"phenotype"})) { // Set a flag to true if there are unaffected individuals (this is needed for the second part of the query for unaffected individuals)
						$cmd .= '(';
						$cmd .= 'gt_types.'.$sample_name.' != HOM_ALT';
						$cmd .= ')';
						
						$cmd .= ' and ';
						
						$unaffected_individuals_present_flag = 1;
					}
				}
				
				$cmd = substr($cmd, 0, -5); // Remove the last ' and ' that was added by the loop
				
				$cmd .= ')';
				
				// If there are more than 1 unaffected individuals, print a separate block with 'or' instead of 'and' as only one needs to be HET (if there is just one it's genotype can be anything)
				if ($unaffected_individuals_present_flag == 1 && count($family_affected_status["unaffected"]) > 1) {
					# Only add this block if there are more than one unaffected individuals
					$cmd .= ' and (';
					
					// Go through every unaffected individual within the selected family (at least one needs to be HET)
					foreach (array_keys($family_info{$_SESSION["family"]}) as $sample_name) {
						if (is_unaffected($family_info{$_SESSION["family"]}{$sample_name}{"phenotype"})) {
							$cmd .= '(';
							$cmd .= 'gt_types.'.$sample_name.' == HET';
							$cmd .= ')';
							
							$cmd .= ' or ';
						}
					}
					
					$cmd = substr($cmd, 0, -4); // Remove the last ' or ' that was added by the loop
					
					$cmd .= ')';
				}
			}
			
			$previous_filter_flag = 1; # Makes sure that downstream queries add an AND
		}
		
		######################
		
		if ($_SESSION["min_seq_depth"] != 0) { # If the minimum sequencing depth is not set to 0 (0 means ignore this filter)
			if ($previous_filter_flag == 1) { # If there has already been a filter before this one we need an AND
				$cmd .= ' and (';
			} else {
				$cmd .= '(';
			}
			
			// If an actual family to query was specified (not the whole database), apply the minimum depth only to family members
			if ($_SESSION["family"] != "" && $_SESSION["family"] != "entiredatabase") {
				// Go through each sample in the family selected
				foreach (array_keys($family_info[$_SESSION["family"]]) as $sample_in_family) {
					$cmd .= '(';
					$cmd .= 'gt_depths.'.$sample_in_family.' >= '.$_SESSION["min_seq_depth"];
					$cmd .= ')';
					
					$cmd .= ' and ';
				}
				
				$cmd = substr($cmd, 0, -5); // Remove the last ' and ' that was added by the loop
			// Otherwise apply the minimum depth to all samples in the database
			} else {
				$cmd .= '(gt_depths).(*).(>='.$_SESSION["min_seq_depth"].').(all)';
			}
			
			$cmd .= ')';
			
			$previous_filter_flag = 1; # Makes sure that downstream queries add an AND
		}
		
		######################
		
		$cmd .= '"'; # Closing quotation mark for the --gt-filter
	}
	
	$cmd .= ' --header --show-samples --sample-delim ";" '; # Return a header row
	
	
	######################
	
	$cmd .= $GLOBALS["configuration_file"]["gemini"]["db_dir"]."/".$_SESSION["query_group"]."/".escape_database_filename($_SESSION["query_db"]); # The database to search
	
	######################
	
	# If the comp het filter is engaged, pipe to my perl script and prepend some required information for it
	if ($_SESSION["analysis_type"] == "analysis_comp_het") {
		$unaffected_individuals_present_flag = 0;
		
		$cmd .= ' | (echo "affected:';
		
		foreach (array_keys($family_info{$_SESSION["family"]}) as $sample_name) {
			if (is_affected($family_info{$_SESSION["family"]}{$sample_name}{"phenotype"})) {
				$cmd .= $sample_name.";;;";
			} else { // Set a flag to true if there are unaffected individuals (this is needed for the second part of the query for unaffected individuals)
				$unaffected_individuals_present_flag = 1;
			}
		}

		$cmd = substr($cmd, 0, -3); // Remove the last ';;;' that was added by the loop
		
		// If there are unaffected individuals, print a their names as the input to the custom filter
		if ($unaffected_individuals_present_flag == 1) {
			$cmd .= ' unaffected:';
			
			foreach (array_keys($family_info{$_SESSION["family"]}) as $sample_name) {
				if (is_unaffected($family_info{$_SESSION["family"]}{$sample_name}{"phenotype"})) {
					$cmd .= $sample_name.";;;";
				}
			}
			$cmd = substr($cmd, 0, -3); // Remove the last ';;;' that was added by the loop
		}
		
		$cmd .= ' " && cat)';
		
		$cmd .= ' | perl '.basename("..").'/scripts/comp_het_filter.pl';
	}
	
	######################
	
	$cmd .= ' | head -n '.($_SESSION["return_num_variants"] + 1); # Limit the number of rows returned	
	$cmd .= ')\''; // For closing off the "(set -o pipefail ...
	
	######################
	
	exec($cmd, $result, $exit_code); # Execute the Gemini query
	
	return array($result, $cmd, $exit_code);
}

#############################################
# MySQL INTEGRATION FOR GEMINI RESULTS
#############################################

function mysql_integration($result) {
		
	######################
	# Make sure all columns required for DB queries are present
	######################
	
	if (!isset($result["chrom"]) || !isset($result["start"]) || !isset($result["end"]) || !isset($result["ref"]) || !isset($result["alt"]) || !isset($result["gene"])) {
		$_SESSION["mysql_integration_error"] = "Could not determine column numbers for important GEMINI columns.";
		
		return false; // Return the original input if column numbers couldn't be identified
	}

	#############################################
	# FETCH FAMILY INFORMATION FOR GBS
	#############################################
	
	$family_info = extract_familial_information($_SESSION["query_group"]."/".$_SESSION["query_db"]);
	
	// If family information could not be fetched
	if ($family_info === false) {
		return false;
	}
	
	#############################################
	# CREATE A LIST OF SAMPLES BEING QUERIED
	#############################################
	
	$samples_to_query = array();
	
	// If the query is for the whole cohort
	if ($_SESSION["return_information_for"] == "" || $_SESSION["return_information_for"] == "cohort") {
		// Go through each family and save all the samples in each one
		foreach (array_keys($family_info) as $family) {
			foreach (array_keys($family_info[$family]) as $sample) {
				array_push($samples_to_query, $sample);
			}
		}
	// If a family was specified, only samples within it are to be queried
	} elseif ($_SESSION["return_information_for"] == "family_only") {
		foreach (array_keys($family_info[$_SESSION["family"]]) as $sample) {
			array_push($samples_to_query, $sample);
		}
	} else {
		return false;
	}

	#############################################
	# CHECK WHETHER THE SAMPLES TO QUERY ARE IN THE GBS
	#############################################
	
	$GBS_presence = fetch_gbs_samples_presence($samples_to_query);
	
	if ($GBS_presence === false) {
		return false;
	}
	
	// If one or more samples is in the GBS
	if (count($GBS_presence) > 0) {
		$gbs_samples_present = 1;
	}
	
	######################
	# Append the custom columns in the header row if they are set to be added
	######################
	
	if (isset($GLOBALS["configuration_file"]["query_databases"]["dbnsfp"]) && $GLOBALS["configuration_file"]["query_databases"]["dbnsfp"] == 1) {
		// Set the results hash up for containing an array for each of the result columns to expect from dbNSFP
		foreach ($GLOBALS['default_dbnsfp_columns'] as $dbnsfp_column) {
			// Ignore position columns, create others
			if ($dbnsfp_column == "chr" || $dbnsfp_column == "pos(1-based)" || $dbnsfp_column == "ref" || $dbnsfp_column == "alt") {
				continue;
			} else {
				$result[$dbnsfp_column] = array();
			}
		}
	}
	
	if (isset($GLOBALS["configuration_file"]["query_databases"]["rvis"]) && $GLOBALS["configuration_file"]["query_databases"]["rvis"] == 1) {
		// Set the results hash up for containing an array for each of the result columns to expect from RVIS
		$result["RVIS ExAC 0.05% Percentile"] = array();
	}
	
	if (isset($GLOBALS["configuration_file"]["query_databases"]["clinvar"]) && $GLOBALS["configuration_file"]["query_databases"]["clinvar"] == 1) {
		// Set the results hash up for containing an array for each of the result columns to expect from ClinVar
		foreach ($GLOBALS['default_clinvar_columns'] as $clinvar_column) {
			// Ignore position columns, print others
			if ($clinvar_column == "chr" || $clinvar_column == "position" || $clinvar_column == "ref" || $clinvar_column == "alt") {
				continue;
			} else {
				$result[$clinvar_column] = array();
			}
		}
	}
	
	if (isset($GLOBALS["configuration_file"]["query_databases"]["cosmic"]) && $GLOBALS["configuration_file"]["query_databases"]["cosmic"] == 1) {
		// Set the results hash up for containing an array for each of the result columns to expect from COSMIC
		foreach ($GLOBALS['default_cosmic_columns'] as $cosmic_column) {
			// Ignore position columns, print others
			if ($cosmic_column == "chr" || $cosmic_column == "pos" || $cosmic_column == "ref" || $cosmic_column == "alt") {
				continue;
			} else {
				$result[$cosmic_column] = array();
			}
		}
	}
	
	if (isset($GLOBALS["configuration_file"]["query_databases"]["omim"]) && $GLOBALS["configuration_file"]["query_databases"]["omim"] == 1) {	
		// Set the results hash up for containing an array for each of the result columns to expect from OMIM
		$result["OMIM Numbers"] = array();
		$result["OMIM Titles"] = array();
		$result["OMIM Status"] = array();
		$result["OMIM Disorders"] = array();
	}
	
	if (isset($GLOBALS["configuration_file"]["query_databases"]["mitomap"]) && $GLOBALS["configuration_file"]["query_databases"]["mitomap"] == 1) {	
		// Set the results hash up for containing an array for each of the result columns to expect from MITOMAP
		$result["MITOMAP AF"] = array();
		$result["MITOMAP Disease"] = array();
	}
	
	if (isset($GLOBALS["configuration_file"]["query_databases"]["cosmic_cgc"]) && $GLOBALS["configuration_file"]["query_databases"]["cosmic_cgc"] == 1) {	
		// Set the results hash up for containing an array for each of the result columns to expect from COSMIC_CGC
		$result["CGC Associations"] = array();
		$result["CGC Mutation Types"] = array();
		$result["CGC Translocation Partners"] = array();
	}
	
	if (isset($GLOBALS["configuration_file"]["query_databases"]["orphanet"]) && $GLOBALS["configuration_file"]["query_databases"]["orphanet"] == 1) {
		// Set the results hash up for containing an array for each of the result columns to expect from Orphanet
		$result["Orphanet Disorders"] = array();
		$result["Is Orphanet AR"] = array();
		$result["Is Orphanet AD"] = array();
	}
		
	if (isset($GLOBALS["configuration_file"]["query_databases"]["mgrb_afs"]) && $GLOBALS["configuration_file"]["query_databases"]["mgrb_afs"] == 1) {
		// Set the results hash up for containing an array for each of the result columns to expect from MGRB
		$result["MGRB AF"] = array();
	}
	
	// If samples to query are present in the GBS
	if (isset($gbs_samples_present)) {
		// Set the results hash up for containing an array for each of the result column to expect from the GBS
		$result["GBS"] = array();
	}
	
	######################
	# Define variables to store MySQL queries and parameters
	######################
	
	$sql_dbnsfp = "";
	$sql_rvis = "";
	$sql_clinvar = "";
	$sql_cosmic = "";
	$sql_omim = "";
	$sql_mitomap = "";
	$sql_cosmic_cgc = "";
	$sql_orphanet = "";
	$sql_MGRB_AF = "";
	$sql_GBS = "";
	$sql_GBS_temporary = "";
	
	$query_parameters_dbnsfp = array();
	$query_parameters_rvis = array();
	$query_parameters_clinvar = array();
	$query_parameters_cosmic = array();
	$query_parameters_omim = array();
	$query_parameters_mitomap = array();
	$query_parameters_cosmic_cgc = array();
	$query_parameters_orphanet = array();
	$query_parameters_MGRB_AF = array();
	$query_parameters_GBS = array();
	$query_parameters_GBS_temporary = array();
	
	######################
	# Create MySQL queries
	######################
	
	// Create an array to store genes after they have been used for a query to not query the same gene twice
	$unique_genes_to_query = array();
	
	// Go through every result row returned from Gemini
	for ($i = 0; $i < count($result["chrom"]); $i++) {
		// Generate the chromosome name without the 'chr' prefix used in b37
		$current_non_chr_chromosome = str_replace('chr', '', $result["chrom"][$i]);
		
		// Only query dbNSFP if the configuration file specifies it
		if (isset($GLOBALS["configuration_file"]["query_databases"]["dbnsfp"]) && $GLOBALS["configuration_file"]["query_databases"]["dbnsfp"] == 1) {
			// Don't query multiallelic or INDEL sites since these can't be in dbNSFP
			if (strpos($result["alt"][$i], ',') !== true && strlen($result["alt"][$i]) == 1 && strlen($result["ref"][$i]) == 1) {
				// Create the base dbNSFP query if it has not been set yet
				if ($sql_dbnsfp == "") {
					$sql_dbnsfp .= "SELECT ";
					
					$sql_dbnsfp .= "`".implode("`, `", $GLOBALS['default_dbnsfp_columns'])."`";
				
					$sql_dbnsfp .= " FROM DBNSFP.v29 WHERE ";
				}
				
				// Add the variant-specific info
				$sql_dbnsfp .= "(chr = ? AND "; // dbNSFP doesn't use the "chr" prefix
				
				$sql_dbnsfp .= "`pos(1-based)` = ? AND ";
				
				$sql_dbnsfp .= "ref = ? AND ";
				
				$sql_dbnsfp .= "alt = ?";
				
				$sql_dbnsfp .= ") OR ";
				
				// Populate the query parameters				
				array_push($query_parameters_dbnsfp, $current_non_chr_chromosome, ($result["start"][$i] + 1), $result["ref"][$i], $result["alt"][$i]);
			}
		}
				
		######################

		// Only query MGRB AF if the configuration file specifies it
		if (isset($GLOBALS["configuration_file"]["query_databases"]["mgrb_afs"]) && $GLOBALS["configuration_file"]["query_databases"]["mgrb_afs"] == 1) {		
			// Create the base MGRB VAFs query if it has not been set yet
			if ($sql_MGRB_AF == "") {
				$sql_MGRB_AF .= "SELECT chr, pos, ref, alt, ac, an, filters FROM MGRB.mgrb_vafs WHERE ";
			}
			
			// Add the variant-specific info
			$sql_MGRB_AF .= "(chr = ? AND "; // MGRB doesn't use the "chr" prefix
			
			$sql_MGRB_AF .= "pos = ? AND ";
			
			$sql_MGRB_AF .= "ref = ? AND ";
			
			$sql_MGRB_AF .= "alt = ?";
			
			$sql_MGRB_AF .= ") OR ";
			
			// Populate the query parameters
			array_push($query_parameters_MGRB_AF, $current_non_chr_chromosome, ($result["start"][$i] + 1), $result["ref"][$i], $result["alt"][$i]);
		}

		######################

		// Only query ClinVar if the configuration file specifies it
		if (isset($GLOBALS["configuration_file"]["query_databases"]["clinvar"]) && $GLOBALS["configuration_file"]["query_databases"]["clinvar"] == 1) {
			// Create the base ClinVar query if it has not been set yet
			if ($sql_clinvar == "") {
				$sql_clinvar .= "SELECT chr, position, ref, alt, clinvar_rs, clinsig, clintrait FROM CLINVAR.`clinvar` WHERE ";
			}
			
			// Add the variant-specific info
			$sql_clinvar .= "(chr = ? AND "; // ClinVar doesn't use the "chr" prefix
			
			$sql_clinvar .= "position = ? AND ";
			
			$sql_clinvar .= "ref = ? AND ";
			
			$sql_clinvar .= "alt = ?";
			
			$sql_clinvar .= ") OR ";
			
			// Populate the query parameters
			array_push($query_parameters_clinvar, $current_non_chr_chromosome, ($result["start"][$i] + 1), $result["ref"][$i], $result["alt"][$i]);
		}
		
		######################

		// Only query MITOMAP if the configuration file specifies it
		if (isset($GLOBALS["configuration_file"]["query_databases"]["mitomap"]) && $GLOBALS["configuration_file"]["query_databases"]["mitomap"] == 1) {
			// Only query MITOMAP for mitochondrial variants, it only contains these
			if ($result["chrom"][$i] == "chrMT") {
				// Create the base MITOMAP query if it has not been set yet
				if ($sql_mitomap == "") {
					$sql_mitomap .= "SELECT chr, pos, ref, alt, AC, AF, Disease, DiseaseStatus FROM MITOMAP.`mitomap` WHERE ";
				}
				
				// Add the variant-specific info
				$sql_mitomap .= "(chr = ? AND ";
				
				$sql_mitomap .= "pos = ? AND ";
				
				$sql_mitomap .= "ref = ? AND ";
				
				$sql_mitomap .= "alt = ?";
				
				$sql_mitomap .= ") OR ";
				
				// Populate the query parameters
				array_push($query_parameters_mitomap, "MT", ($result["start"][$i] + 1), $result["ref"][$i], $result["alt"][$i]);
			}
		}
		
		######################
		
		// Only query COSMIC if the configuration file specifies it
		if (isset($GLOBALS["configuration_file"]["query_databases"]["cosmic"]) && $GLOBALS["configuration_file"]["query_databases"]["cosmic"] == 1) {
			// Create the base COSMIC query if it has not been set yet
			if ($sql_cosmic == "") {
				$sql_cosmic .= "SELECT COSMIC.variants.chr, COSMIC.variants.pos, COSMIC.variants.ref, COSMIC.variants.alt, COSMIC.cosmic_numbers.cosmic_number, COSMIC.cosmic_numbers.cosmic_count, COSMIC.cosmic_numbers.cosmic_primary_site, COSMIC.cosmic_numbers.cosmic_primary_histology FROM COSMIC.variants ";

				$sql_cosmic .= "INNER JOIN COSMIC.cosmic_number_to_variant ON COSMIC.cosmic_number_to_variant.variant_id = COSMIC.variants.id ";
			
				$sql_cosmic .= "INNER JOIN COSMIC.cosmic_numbers ON COSMIC.cosmic_numbers.cosmic_number = COSMIC.cosmic_number_to_variant.cosmic_number ";
				
				$sql_cosmic .= "WHERE ";
			}
			
			// Add the variant-specific info
			$sql_cosmic .= "(COSMIC.variants.chr = ? AND "; // COSMIC doesn't use the "chr" prefix
			
			$sql_cosmic .= "COSMIC.variants.pos = ? AND ";
			
			$sql_cosmic .= "COSMIC.variants.ref = ? AND ";
			
			$sql_cosmic .= "COSMIC.variants.alt = ?";
			
			$sql_cosmic .= ") OR ";
			
			// Populate the query parameters
			array_push($query_parameters_cosmic, $current_non_chr_chromosome, ($result["start"][$i] + 1), $result["ref"][$i], $result["alt"][$i]);
		}

		######################
		
		// If samples to query are present in the GBS
		if (isset($gbs_samples_present)) {
			// Create the base GBS query if it has not been set yet
			if ($sql_GBS == "") {
				// Create the SQL to create a temporary table containing all GBS query coordinates
				$sql_GBS_temporary = create_temporary_query_coordinates_table_gbs(count($result["chrom"]));
				
				######################
				
				// Create the SQL to query the GBS for all blocks overlapping with the temporary query coordinates table
				$sql_GBS = query_blocks_by_position_gbs(count($samples_to_query), "all", "do_not_restrict_cn", "do_not_restrict_block_size");
				
				// Populate the GBS query parameters
				foreach ($samples_to_query as $sample) {
					$query_parameters_GBS[] = $sample;
				}
			}
			
			// Populate the query parameters
			array_push($query_parameters_GBS_temporary, $current_non_chr_chromosome, $result["start"][$i], $result["end"][$i]);
		}
		
		######################
		
		// If the gene has not been seen before, query it, otherwise don't
		if (!in_array($result["gene"][$i], $unique_genes_to_query) && $result["gene"][$i] != "None") {
			// Only query RVIS if the configuration file specifies it
			if (isset($GLOBALS["configuration_file"]["query_databases"]["rvis"]) && $GLOBALS["configuration_file"]["query_databases"]["rvis"] == 1) {
				// Create the base RVIS query if it has not been set yet
				if ($sql_rvis == "") {
					$sql_rvis .= "SELECT ";
				
					$sql_rvis .= "`".implode("`, `", $GLOBALS['default_rvis_columns'])."`";
				
					$sql_rvis .= " FROM RVIS.rvis WHERE ";
				}
				
				// Add the gene-specific info
				$sql_rvis .= "(gene = ?) OR ";
				
				// Populate the query parameters				
				$query_parameters_rvis[] = $result["gene"][$i];
			}
			
			######################
			
			// Only query COSMIC CGC if the configuration file specifies it
			if (isset($GLOBALS["configuration_file"]["query_databases"]["cosmic_cgc"]) && $GLOBALS["configuration_file"]["query_databases"]["cosmic_cgc"] == 1) {
				// Create the base COSMIC CGC query if it has not been set yet
				if ($sql_cosmic_cgc == "") {
					$sql_cosmic_cgc .= "SELECT ";
				
					$sql_cosmic_cgc .= "gene, associations, mutation_types, translocation_partner ";
				
					$sql_cosmic_cgc .= "FROM COSMIC_CGC.cosmic_cgc WHERE ";
				}
				
				// Add the gene-specific info
				$sql_cosmic_cgc .= "(gene = ?) OR ";
				
				// Populate the query parameters
				$query_parameters_cosmic_cgc[] = $result["gene"][$i];
			}
			
			######################

			// Only query OMIM if the configuration file specifies it
			if (isset($GLOBALS["configuration_file"]["query_databases"]["omim"]) && $GLOBALS["configuration_file"]["query_databases"]["omim"] == 1) {				
				// Create the base OMIM query if it has not been set yet
				if ($sql_omim == "") {
					$sql_omim .= "SELECT ";
						$sql_omim .= "OMIM.omim_genes.gene_name, ";
						$sql_omim .= "omim_numbers.omim_number, ";
						$sql_omim .= "omim_numbers.omim_title, ";
						$sql_omim .= "omim_numbers.omim_status, ";
						$sql_omim .= "omim_disorders.omim_disorder ";
					$sql_omim .= "FROM ";
						$sql_omim .= "OMIM.omim_numbers ";
					$sql_omim .= "INNER JOIN OMIM.omim_number_to_gene ON OMIM.omim_number_to_gene.omim_number = OMIM.omim_numbers.omim_number ";
					$sql_omim .= "INNER JOIN OMIM.omim_genes ON OMIM.omim_genes.gene_id = OMIM.omim_number_to_gene.gene_id ";
					$sql_omim .= "INNER JOIN OMIM.omim_disorders_to_omim_numbers ON OMIM.omim_disorders_to_omim_numbers.omim_number = OMIM.omim_numbers.omim_number ";
					$sql_omim .= "INNER JOIN OMIM.omim_disorders ON OMIM.omim_disorders_to_omim_numbers.disorder_id = OMIM.omim_disorders.disorder_id ";
					$sql_omim .= "WHERE ";
				}
				
				// Add the gene-specific info
				$sql_omim .= "(OMIM.omim_genes.gene_name = ?) OR ";
					
				// Populate the query parameters
				$query_parameters_omim[] = $result["gene"][$i];
			}
				
			######################

			// Only query Orphanet if the configuration file specifies it
			if (isset($GLOBALS["configuration_file"]["query_databases"]["orphanet"]) && $GLOBALS["configuration_file"]["query_databases"]["orphanet"] == 1) {			
				// Create the base Orphanet query if it has not been set yet
				if ($sql_orphanet == "") {
					$sql_orphanet .= "SELECT ";
						$sql_orphanet .= "ORPHANET.orphanet_genes.gene_name, ";
						$sql_orphanet .= "ORPHANET.orphanet_disorders.orphanet_name, ";
						$sql_orphanet .= "ORPHANET.orphanet_disorders.orphanet_number, ";
						$sql_orphanet .= "ORPHANET.association_types.association_type, ";
						$sql_orphanet .= "ORPHANET.association_statuses.association_status, ";
						$sql_orphanet .= "ORPHANET.orphanet_inheritances.inheritance_name, ";
						$sql_orphanet .= "ORPHANET.orphanet_age_of_onsets.age_of_onset ";
					$sql_orphanet .= "FROM ";
						$sql_orphanet .= "ORPHANET.orphanet_genes ";
					$sql_orphanet .= "INNER JOIN ORPHANET.genes_to_disorders ON ORPHANET.genes_to_disorders.gene_id = ORPHANET.orphanet_genes.id ";
					$sql_orphanet .= "INNER JOIN ORPHANET.orphanet_disorders ON ORPHANET.orphanet_disorders.id = ORPHANET.genes_to_disorders.disorder_id ";
					$sql_orphanet .= "LEFT JOIN ORPHANET.association_types ON ORPHANET.association_types.id = ORPHANET.genes_to_disorders.association_type_id ";
					$sql_orphanet .= "LEFT JOIN ORPHANET.association_statuses ON ORPHANET.association_statuses.id = ORPHANET.genes_to_disorders.association_status_id ";
					$sql_orphanet .= "LEFT JOIN ORPHANET.age_of_onsets_to_disorders ON ORPHANET.age_of_onsets_to_disorders.disorder_id = ORPHANET.orphanet_disorders.id ";
					$sql_orphanet .= "LEFT JOIN ORPHANET.inheritances_to_disorders ON ORPHANET.inheritances_to_disorders.disorder_id = ORPHANET.orphanet_disorders.id ";
					$sql_orphanet .= "LEFT JOIN ORPHANET.orphanet_age_of_onsets ON ORPHANET.orphanet_age_of_onsets.id = ORPHANET.age_of_onsets_to_disorders.age_of_onset_id ";
					$sql_orphanet .= "LEFT JOIN ORPHANET.orphanet_inheritances ON ORPHANET.orphanet_inheritances.id = ORPHANET.inheritances_to_disorders.inheritance_id ";
					$sql_orphanet .= "WHERE ";
				}
				
				// Add the gene-specific info
				$sql_orphanet .= "(ORPHANET.orphanet_genes.gene_name = ?) OR ";
				
				// Populate the query parameters
				$query_parameters_orphanet[] = $result["gene"][$i];
			}
			
			######################
			
			// Store the gene as it has now been used for a query and shouldn't be queried again
			array_push($unique_genes_to_query, $result["gene"][$i]);
		}
	}
	
	#############################################
	# RUN THE MYSQL QUERIES
	#############################################
	
	// If the query is not empty, close it off and run it
	if (strlen($sql_dbnsfp) > 0) {
		// Remove the last " OR " added by the loop
		$sql_dbnsfp = substr($sql_dbnsfp, 0, -4);
		
		// Close off the query
		$sql_dbnsfp .= ";"; 
		
		// Perform a multi-query by sending all queries at once
		$statement = $GLOBALS["mysql_connection"]->prepare($sql_dbnsfp);
	
		$statement->execute($query_parameters_dbnsfp);
		
	    do {
		    $mysql_result = $statement->fetchAll(PDO::FETCH_NUM); // Fetches as $mysql_result[row array (0,1,2,3...)][column array (0,1,2,3...)] = value
		    
		    foreach (array_keys($mysql_result) as $result_id) {   
                for ($z = 4; $z < count($GLOBALS['default_dbnsfp_columns']); $z++) { // Ignore the position columns
	                // Remove all newline characters
	                $mysql_result[$result_id][$z] = preg_replace("/[\n\r]/", "", $mysql_result[$result_id][$z]);
	                
	                // Save the dbNSFP results
	                $dbnsfp_results[$mysql_result[$result_id][0]][$mysql_result[$result_id][1]][$mysql_result[$result_id][2]][$mysql_result[$result_id][3]][$GLOBALS['default_dbnsfp_columns'][$z]] = $mysql_result[$result_id][$z];
                }
	        }
	    // Ask for the next result
	    } while ($statement->nextRowset());
	}
	
	######################
	
	// If the query is not empty, close it off and run it
	if (strlen($sql_rvis) > 0) {
		// Remove the last " OR " added by the loop
		$sql_rvis = substr($sql_rvis, 0, -4);
		
		// Close off the query
		$sql_rvis .= ";"; 
		
		// Perform a multi-query by sending all queries at once
		$statement = $GLOBALS["mysql_connection"]->prepare($sql_rvis);
	
		$statement->execute($query_parameters_rvis);
		
	    do {
	        $mysql_result = $statement->fetchAll(PDO::FETCH_NUM); // Fetches as $mysql_result[row array (0,1,2,3...)][column array (0,1,2,3...)] = value
		    
		    foreach (array_keys($mysql_result) as $result_id) {
                $rvis_results[$mysql_result[$result_id][0]]["RVIS ExAC 0.05% Percentile"] = $mysql_result[$result_id][1];
            }
	    // Ask for the next result
	    } while ($statement->nextRowset());
	}
	
	######################
	
	// If the query is not empty, close it off and run it
	if (strlen($sql_cosmic_cgc) > 0) {
		// Remove the last " OR " added by the loop
		$sql_cosmic_cgc = substr($sql_cosmic_cgc, 0, -4);
		
		// Close off the query
		$sql_cosmic_cgc .= ";"; 
		
		// Perform a multi-query by sending all queries at once
		$statement = $GLOBALS["mysql_connection"]->prepare($sql_cosmic_cgc);
	
		$statement->execute($query_parameters_cosmic_cgc);
		
	    do {
		    $mysql_result = $statement->fetchAll(PDO::FETCH_NUM); // Fetches as $mysql_result[row array (0,1,2,3...)][column array (0,1,2,3...)] = value
		    
            foreach (array_keys($mysql_result) as $result_id) {
				// Save the COSMIC CGC results
	            $cosmic_cgc_results[$mysql_result[$result_id][0]]["CGC Associations"] = $mysql_result[$result_id][1];
	            $cosmic_cgc_results[$mysql_result[$result_id][0]]["CGC Mutation Types"] = $mysql_result[$result_id][2];
	            
	            if ($mysql_result[$result_id][3] == "") {
		            $cosmic_cgc_results[$mysql_result[$result_id][0]]["CGC Translocation Partners"] = ".";
	            } else {
		            $cosmic_cgc_results[$mysql_result[$result_id][0]]["CGC Translocation Partners"] = $mysql_result[$result_id][3];
				}
            }
	    // Ask for the next result
	    } while ($statement->nextRowset());
	}
	
	######################
	
	// If the query is not empty, close it off and run it
	if (strlen($sql_clinvar) > 0) {
		// Remove the last " OR " added by the loop
		$sql_clinvar = substr($sql_clinvar, 0, -4);
		
		// Close off the query
		$sql_clinvar .= ";"; 
		
		// Perform a multi-query by sending all queries at once
		$statement = $GLOBALS["mysql_connection"]->prepare($sql_clinvar);
	
		$statement->execute($query_parameters_clinvar);
		
	    do {
		    $mysql_result = $statement->fetchAll(PDO::FETCH_NUM); // Fetches as $mysql_result[row array (0,1,2,3...)][column array (0,1,2,3...)] = value
			
            foreach (array_keys($mysql_result) as $result_id) {
                for ($z = 4; $z < count($GLOBALS['default_clinvar_columns']); $z++) { // Skip position columns with z = 4
	                // Save the ClinVar results
	                $clinvar_results[$mysql_result[$result_id][0]][$mysql_result[$result_id][1]][$mysql_result[$result_id][2]][$mysql_result[$result_id][3]][$GLOBALS['default_clinvar_columns'][$z]] = $mysql_result[$result_id][$z];
                }
            }
	    // Ask for the next result
	    } while ($statement->nextRowset());
	}
	
	######################
	
	// If the query is not empty, close it off and run it
	if (strlen($sql_mitomap) > 0) {
		// Remove the last " OR " added by the loop
		$sql_mitomap = substr($sql_mitomap, 0, -4);
		
		// Close off the query
		$sql_mitomap .= ";"; 
		
		// Perform a multi-query by sending all queries at once
		$statement = $GLOBALS["mysql_connection"]->prepare($sql_mitomap);
		
		$statement->execute($query_parameters_mitomap);
		
	    do {
		    $mysql_result = $statement->fetchAll(PDO::FETCH_NUM); // Fetches as $mysql_result[row array (0,1,2,3...)][column array (0,1,2,3...)] = value
		    
            foreach (array_keys($mysql_result) as $result_id) {
	            // If the AC/AF is NULL in the DB (because there's a disease but no AC/AF)
				if ($mysql_result[$result_id][5] == "" && $mysql_result[$result_id][4] == "") {
					$mitomap_results[$mysql_result[$result_id][0]][$mysql_result[$result_id][1]][$mysql_result[$result_id][2]][$mysql_result[$result_id][3]]["MITOMAP AF"] = "No Result";
				// Amalgamate the AC and AF columns
				} else {
					$mitomap_results[$mysql_result[$result_id][0]][$mysql_result[$result_id][1]][$mysql_result[$result_id][2]][$mysql_result[$result_id][3]]["MITOMAP AF"] = sprintf('%.4f', $mysql_result[$result_id][5])." (AC: ".$mysql_result[$result_id][4].")"; // Display the AF with 4 decimal places no matter what it is for consistency, in the DB it's stored as float 0-1 but sometimes 0, sometimes 0.01 etc
				}
				
				// If the Disease/DiseaseStatus is NULL in the DB (because there's a AC/AF but no Disease/DiseaseStatus)
				if ($mysql_result[$result_id][6] == "" && $mysql_result[$result_id][7] == "") {
					$mitomap_results[$mysql_result[$result_id][0]][$mysql_result[$result_id][1]][$mysql_result[$result_id][2]][$mysql_result[$result_id][3]]["MITOMAP Disease"] = "No Result";
				// Amalgamate the Disease and DiseaseStatus columns
				} else {
					$mitomap_results[$mysql_result[$result_id][0]][$mysql_result[$result_id][1]][$mysql_result[$result_id][2]][$mysql_result[$result_id][3]]["MITOMAP Disease"] = $mysql_result[$result_id][6]." (Status: ".$mysql_result[$result_id][7].")";
				}
	        }
	    // Ask for the next result
	    } while ($statement->nextRowset());
	}
	
	######################
	
	// If the query is not empty, close it off and run it
	if (strlen($sql_cosmic) > 0) {
		// Remove the last " OR " added by the loop
		$sql_cosmic = substr($sql_cosmic, 0, -4);
		
		// Close off the query
		$sql_cosmic .= ";";
		
		// Perform a multi-query by sending all queries at once
		$statement = $GLOBALS["mysql_connection"]->prepare($sql_cosmic);
		
		$statement->execute($query_parameters_cosmic);
		
	    do {
		    $mysql_result = $statement->fetchAll(PDO::FETCH_NUM); // Fetches as $mysql_result[row array (0,1,2,3...)][column array (0,1,2,3...)] = value
		    
            foreach (array_keys($mysql_result) as $result_id) {
                for ($z = 4; $z < count($GLOBALS['default_cosmic_columns']); $z++) { // Skip position columns with z = 4
	                // Save the COSMIC results
	                if (!isset($cosmic_results[$mysql_result[$result_id][0]][$mysql_result[$result_id][1]][$mysql_result[$result_id][2]][$mysql_result[$result_id][3]][$GLOBALS['default_cosmic_columns'][$z]])) {
		                $cosmic_results[$mysql_result[$result_id][0]][$mysql_result[$result_id][1]][$mysql_result[$result_id][2]][$mysql_result[$result_id][3]][$GLOBALS['default_cosmic_columns'][$z]] = $mysql_result[$result_id][$z];
	                } else {
		            	$cosmic_results[$mysql_result[$result_id][0]][$mysql_result[$result_id][1]][$mysql_result[$result_id][2]][$mysql_result[$result_id][3]][$GLOBALS['default_cosmic_columns'][$z]] .= ",".$mysql_result[$result_id][$z];
	                }
                }
            }
	    // Ask for the next result
	    } while ($statement->nextRowset());
	}
	
	######################
	
	// If the query is not empty, close it off and run it
	if (strlen($sql_omim) > 0) {
		// Remove the last " OR " added by the loop
		$sql_omim = substr($sql_omim, 0, -4);
		
		// Close off the query
		$sql_omim .= ";";
		
		// Perform a multi-query by sending all queries at once
		$statement = $GLOBALS["mysql_connection"]->prepare($sql_omim);
		
		$statement->execute($query_parameters_omim);
		
	    do {
            $mysql_result = $statement->fetchAll(PDO::FETCH_NUM); // Fetches as $mysql_result[row array (0,1,2,3...)][column array (0,1,2,3...)] = value
		    
            foreach (array_keys($mysql_result) as $result_id) {
				$omim_gene_to_omim_number[$mysql_result[$result_id][0]][] = $mysql_result[$result_id][1]; // Save an array of OMIM numbers for each gene
				$omim_number_info[$mysql_result[$result_id][1]]["omim_title"] = $mysql_result[$result_id][2]; // Save OMIM title
				$omim_number_info[$mysql_result[$result_id][1]]["omim_status"] = $mysql_result[$result_id][3]; // Save OMIM status
				
				// Push all disorders for the omim number to an array
				$omim_number_disorders[$mysql_result[$result_id][1]][] = $mysql_result[$result_id][4];
            }
	    // Ask for the next result
	    } while ($statement->nextRowset());
	    
	    // Go through each gene and make sure the OMIM numbers for each one are unique
        if (isset($omim_gene_to_omim_number)) {
	        foreach (array_keys($omim_gene_to_omim_number) as $gene_name) {
	            $omim_gene_to_omim_number[$gene_name] = array_unique($omim_gene_to_omim_number[$gene_name]);
	        }
	    }
	}
	
	######################
	
	// If the query is not empty, close it off and run it
	if (strlen($sql_orphanet) > 0) {
		// Remove the last " OR " added by the loop
		$sql_orphanet = substr($sql_orphanet, 0, -4);
		
		// Close off the query
		$sql_orphanet .= ";";
		
		// Perform a multi-query by sending all queries at once
		$statement = $GLOBALS["mysql_connection"]->prepare($sql_orphanet);
		
		$statement->execute($query_parameters_orphanet);
		
	    do {
		    $mysql_result = $statement->fetchAll(PDO::FETCH_NUM); // Fetches as $mysql_result[row array (0,1,2,3...)][column array (0,1,2,3...)] = value
		    
            foreach (array_keys($mysql_result) as $result_id) {
	            $orphanet_gene_to_orphanet_number[$mysql_result[$result_id][0]][] = $mysql_result[$result_id][2]; // Save an array of Orphanet disorder numbers for each gene
	            
				$orphanet_disorders[$mysql_result[$result_id][2]]["name"] = $mysql_result[$result_id][1];
				
				// If no association type and status has been set yet, set the first ones
				if (!isset($orphanet_disorders[$mysql_result[$result_id][2]]["genes"][$mysql_result[$result_id][0]]["association_type"])) {
					$orphanet_disorders[$mysql_result[$result_id][2]]["genes"][$mysql_result[$result_id][0]]["association_type"][] = $mysql_result[$result_id][3];
					$orphanet_disorders[$mysql_result[$result_id][2]]["genes"][$mysql_result[$result_id][0]]["association_status"][] = $mysql_result[$result_id][4];
				} else {
					// Go through every existing association type and status
					for ($i = 0; $i < count($orphanet_disorders[$mysql_result[$result_id][2]]["genes"][$mysql_result[$result_id][0]]["association_type"]); $i++) {
						// If the association type and status are the same as the current row, set a flag to true
						if ($orphanet_disorders[$mysql_result[$result_id][2]]["genes"][$mysql_result[$result_id][0]]["association_type"][$i] == $mysql_result[$result_id][3] && $orphanet_disorders[$mysql_result[$result_id][2]]["genes"][$mysql_result[$result_id][0]]["association_status"][$i] == $mysql_result[$result_id][4]) {
							$orphanet_association_found_flag = 1;
						}
					}
					
					// If the current association type and status haven't been seen before, push them to the arrays
					if (!isset($orphanet_association_found_flag)) {
						$orphanet_disorders[$mysql_result[$result_id][2]]["genes"][$mysql_result[$result_id][0]]["association_type"][] = $mysql_result[$result_id][3];
						$orphanet_disorders[$mysql_result[$result_id][2]]["genes"][$mysql_result[$result_id][0]]["association_status"][] = $mysql_result[$result_id][4];
					}
				}			
				
				// If the inheritance is not NULL
				if ($mysql_result[$result_id][5] != "") {
					// If the inheritances array is not set or is set but the value has not been seen before
					if (!isset($orphanet_disorders[$mysql_result[$result_id][2]]["inheritances"]) || !in_array($mysql_result[$result_id][5], $orphanet_disorders[$mysql_result[$result_id][2]]["inheritances"])) {
						$orphanet_disorders[$mysql_result[$result_id][2]]["inheritances"][] = $mysql_result[$result_id][5];
					}
				}
				
				// If the age of onset is not NULL
				if ($mysql_result[$result_id][6] != "") {
					// If the ages of onset array is not set or is set but the value has not been seen before
					if (!isset($orphanet_disorders[$mysql_result[$result_id][2]]["ages_of_onset"]) || !in_array($mysql_result[$result_id][6], $orphanet_disorders[$mysql_result[$result_id][2]]["ages_of_onset"])) {
						$orphanet_disorders[$mysql_result[$result_id][2]]["ages_of_onset"][] = $mysql_result[$result_id][6];
					}
				}
            }
	    // Ask for the next result
	    } while ($statement->nextRowset());
	    
	    // Go through each gene and make sure the Orphanet numbers for each one are unique
        if (isset($orphanet_gene_to_orphanet_number)) {
	        foreach (array_keys($orphanet_gene_to_orphanet_number) as $gene_name) {
	            $orphanet_gene_to_orphanet_number[$gene_name] = array_unique($orphanet_gene_to_orphanet_number[$gene_name]);
	        }
	    }
	}
		
	######################
	
	// If the query is not empty, close it off and run it
	if (strlen($sql_MGRB_AF) > 0) {
		// Remove the last " OR " added by the loop
		$sql_MGRB_AF = substr($sql_MGRB_AF, 0, -4);
		
		// Close off the query
		$sql_MGRB_AF .= ";";
		
		// Perform a multi-query by sending all queries at once
		$statement = $GLOBALS["mysql_connection"]->prepare($sql_MGRB_AF);
		
		$statement->execute($query_parameters_MGRB_AF);
		
	    do {
            $mysql_result = $statement->fetchAll(PDO::FETCH_NUM); // Fetches as $mysql_result[row array (0,1,2,3...)][column array (0,1,2,3...)] = value
		    
            foreach (array_keys($mysql_result) as $result_id) {
	            // Determine the PASS/FAIL status of the variant in the MGRB
	            if ($mysql_result[$result_id][6] == "") {
		            $MGRB_passfail = "P";
	            } else {
		            $MGRB_passfail = "F";
	            }
	            
                // Round and display the VAF to 4 decimal places, even if it's 0 it will be 0.0000
                $result_mgrb_af = sprintf('%0.4f', ($mysql_result[$result_id][4] / $mysql_result[$result_id][5]))." (".$mysql_result[$result_id][4]."/".$mysql_result[$result_id][5].";".$MGRB_passfail.")";
				
				$MGRB_AF_results[$mysql_result[$result_id][0]][$mysql_result[$result_id][1]][$mysql_result[$result_id][2]][$mysql_result[$result_id][3]] = $result_mgrb_af;
            }
	    // Ask for the next result
	    } while ($statement->nextRowset());
	}
	
	######################
	
	// If the query is not empty, close it off and run it
	if (strlen($sql_GBS) > 0) {
		// Perform a multi-query by sending all queries at once
		$statement = $GLOBALS["mysql_connection"]->prepare($sql_GBS_temporary);
		
		$statement->execute($query_parameters_GBS_temporary);
		
		// Perform a multi-query by sending all queries at once
		$statement = $GLOBALS["mysql_connection"]->prepare($sql_GBS);
		
		$statement->execute($query_parameters_GBS);
		
	    do {
		    $mysql_result = $statement->fetchAll(PDO::FETCH_NUM); // Fetches as $mysql_result[row array (0,1,2,3...)][column array (0,1,2,3...)] = value
		    
		    // Go through each row returned
			foreach (array_keys($mysql_result) as $result_id) {
                // Store the results in this format:
                // $GBS_results[<variant chromosome>][<variant start>][<variant end>][<sample name>][<event type>][<method name>][<block_coordinates/copy_number/annotation_tags>] = <string value>/<array>
                
                $GBS_results[$mysql_result[$result_id][7]][$mysql_result[$result_id][8]][$mysql_result[$result_id][9]][$mysql_result[$result_id][0]][$mysql_result[$result_id][1]][$mysql_result[$result_id][3]]["block_coordinates"] = $mysql_result[$result_id][4].":".$mysql_result[$result_id][5]."-".$mysql_result[$result_id][6];
                $GBS_results[$mysql_result[$result_id][7]][$mysql_result[$result_id][8]][$mysql_result[$result_id][9]][$mysql_result[$result_id][0]][$mysql_result[$result_id][1]][$mysql_result[$result_id][3]]["copy_number"] = $mysql_result[$result_id][2];
                $GBS_results[$mysql_result[$result_id][7]][$mysql_result[$result_id][8]][$mysql_result[$result_id][9]][$mysql_result[$result_id][0]][$mysql_result[$result_id][1]][$mysql_result[$result_id][3]]["annotation_tags"][] = $mysql_result[$result_id][10].":".$mysql_result[$result_id][11];
	        }
	    // Ask for the next result
	    } while ($statement->nextRowset());
	}		

	#############################################
	# PROCESS THE RESULTS
	#############################################

	// Go through every result row returned from Gemini
	for ($i = 0; $i < count($result["chrom"]); $i++) {			
		// Generate the chromosome name without the 'chr' prefix used in b37
		$current_non_chr_chromosome = str_replace('chr', '', $result["chrom"][$i]);
		
		######################
		// dbNSFP
		
		if (isset($GLOBALS["configuration_file"]["query_databases"]["dbnsfp"]) && $GLOBALS["configuration_file"]["query_databases"]["dbnsfp"] == 1) {
			// If the site is a SNP
			if (strpos($result["alt"][$i], ',') !== true && strlen($result["alt"][$i]) == 1 && strlen($result["ref"][$i]) == 1) {
				if (isset($dbnsfp_results[$current_non_chr_chromosome][($result["start"][$i] + 1)][$result["ref"][$i]][$result["alt"][$i]])) {
					// Go through each non-positional dbNSFP column
					for ($z = 4; $z < count($GLOBALS['default_dbnsfp_columns']); $z++) { // Ignore the position columns with z = 4
						array_push($result[$GLOBALS['default_dbnsfp_columns'][$z]], $dbnsfp_results[$current_non_chr_chromosome][($result["start"][$i] + 1)][$result["ref"][$i]][$result["alt"][$i]][$GLOBALS['default_dbnsfp_columns'][$z]]); 
					}			
				} else {
					for ($z = 4; $z < count($GLOBALS['default_dbnsfp_columns']); $z++) {
						array_push($result[$GLOBALS['default_dbnsfp_columns'][$z]], "No Result");
					}
				}
			// If the site is not a SNP
			} else {
				for ($z = 4; $z < count($GLOBALS['default_dbnsfp_columns']); $z++) {
					array_push($result[$GLOBALS['default_dbnsfp_columns'][$z]], "Not Applicable");
				}
			}
		}

		######################
		// RVIS
		
		if (isset($GLOBALS["configuration_file"]["query_databases"]["rvis"]) && $GLOBALS["configuration_file"]["query_databases"]["rvis"] == 1) {
			if (isset($rvis_results[$result["gene"][$i]])) {
				array_push($result["RVIS ExAC 0.05% Percentile"], $rvis_results[$result["gene"][$i]]["RVIS ExAC 0.05% Percentile"]);
			} else {
				array_push($result["RVIS ExAC 0.05% Percentile"], "No Result");
			}
		}
		
		######################
		// COSMIC CGC
		
		if (isset($GLOBALS["configuration_file"]["query_databases"]["cosmic_cgc"]) && $GLOBALS["configuration_file"]["query_databases"]["cosmic_cgc"] == 1) {
			if (isset($cosmic_cgc_results[$result["gene"][$i]])) {
				array_push($result["CGC Associations"], $cosmic_cgc_results[$result["gene"][$i]]["CGC Associations"]);
				array_push($result["CGC Mutation Types"], $cosmic_cgc_results[$result["gene"][$i]]["CGC Mutation Types"]);
				array_push($result["CGC Translocation Partners"], $cosmic_cgc_results[$result["gene"][$i]]["CGC Translocation Partners"]);
			} else {
				array_push($result["CGC Associations"], "No Result");
				array_push($result["CGC Mutation Types"], "No Result");
				array_push($result["CGC Translocation Partners"], "No Result");
			}
		}

		######################
		// ClinVar
		
		if (isset($GLOBALS["configuration_file"]["query_databases"]["clinvar"]) && $GLOBALS["configuration_file"]["query_databases"]["clinvar"] == 1) {
			if (isset($clinvar_results[$current_non_chr_chromosome][($result["start"][$i] + 1)][$result["ref"][$i]][$result["alt"][$i]])) {
				// Go through each non-positional ClinVar column
				for ($z = 4; $z < count($GLOBALS['default_clinvar_columns']); $z++) { // Ignore the position columns with z = 4
					array_push($result[$GLOBALS['default_clinvar_columns'][$z]], $clinvar_results[$current_non_chr_chromosome][($result["start"][$i] + 1)][$result["ref"][$i]][$result["alt"][$i]][$GLOBALS['default_clinvar_columns'][$z]]); 
				}			
			} else {
				for ($z = 4; $z < count($GLOBALS['default_clinvar_columns']); $z++) {
					array_push($result[$GLOBALS['default_clinvar_columns'][$z]], "No Result");
				}
			}
		}
		
		######################
		// MITOMAP
		
		if (isset($GLOBALS["configuration_file"]["query_databases"]["mitomap"]) && $GLOBALS["configuration_file"]["query_databases"]["mitomap"] == 1) {
			if (isset($mitomap_results["MT"][($result["start"][$i] + 1)][$result["ref"][$i]][$result["alt"][$i]])) {
				array_push($result["MITOMAP AF"], $mitomap_results["MT"][($result["start"][$i] + 1)][$result["ref"][$i]][$result["alt"][$i]]["MITOMAP AF"]);
				array_push($result["MITOMAP Disease"], $mitomap_results["MT"][($result["start"][$i] + 1)][$result["ref"][$i]][$result["alt"][$i]]["MITOMAP Disease"]);			
			} else {
				array_push($result["MITOMAP AF"], "No Result");
				array_push($result["MITOMAP Disease"], "No Result");			
			}
		}

		######################
		// COSMIC
		
		if (isset($GLOBALS["configuration_file"]["query_databases"]["cosmic"]) && $GLOBALS["configuration_file"]["query_databases"]["cosmic"] == 1) {
			if (isset($cosmic_results[$current_non_chr_chromosome][($result["start"][$i] + 1)][$result["ref"][$i]][$result["alt"][$i]])) {
				// Go through each non-positional COSMIC column
				for ($z = 4; $z < count($GLOBALS['default_cosmic_columns']); $z++) { // Ignore the position columns with z = 4
					array_push($result[$GLOBALS['default_cosmic_columns'][$z]], $cosmic_results[$current_non_chr_chromosome][($result["start"][$i] + 1)][$result["ref"][$i]][$result["alt"][$i]][$GLOBALS['default_cosmic_columns'][$z]]); 
				}			
			} else {
				for ($z = 4; $z < count($GLOBALS['default_cosmic_columns']); $z++) {
					array_push($result[$GLOBALS['default_cosmic_columns'][$z]], "No Result");
				}
			}
		}
		
		######################
		// OMIM
		
		if (isset($GLOBALS["configuration_file"]["query_databases"]["omim"]) && $GLOBALS["configuration_file"]["query_databases"]["omim"] == 1) {
			// If the current gene had OMIM results
			if (isset($omim_gene_to_omim_number[$result["gene"][$i]])) {
				// Print the OMIM number(s) for the gene
				array_push($result["OMIM Numbers"], implode(";", $omim_gene_to_omim_number[$result["gene"][$i]]));
				
				// Define variables to store the concatenated OMIM information
				$omim_titles = "";
				$omim_statuses = "";
				$omim_disorders = "";
				
				// Go through each OMIM number for the current gene
				foreach ($omim_gene_to_omim_number[$result["gene"][$i]] as $omim_number) {
					$omim_titles .= $omim_number_info[$omim_number]["omim_title"].";";
					$omim_statuses .= $omim_number_info[$omim_number]["omim_status"].";";
					
					// If no disorders were found
					if (count($omim_number_disorders[$omim_number]) == 1 && $omim_number_disorders[$omim_number][0] == "") {
						$omim_disorders .= $omim_number.":None;";
					} else {
						$omim_disorders .= $omim_number.":".implode(";", $omim_number_disorders[$omim_number]).";";
					}
				}
				
				$omim_titles = substr($omim_titles, 0, -1); // Remove the last ";" that was added by the loop above
				$omim_statuses = substr($omim_statuses, 0, -1); // Remove the last ";" that was added by the loop above
				$omim_disorders = substr($omim_disorders, 0, -1); // Remove the last ";" that was added by the loop above
				
				// Print the titles, statuses and disorders
				array_push($result["OMIM Titles"], $omim_titles);
				array_push($result["OMIM Status"], $omim_statuses);
				array_push($result["OMIM Disorders"], $omim_disorders);
			} else {
				array_push($result["OMIM Numbers"], "None");
				array_push($result["OMIM Titles"], "None");
				array_push($result["OMIM Status"], "None");
				array_push($result["OMIM Disorders"], "None");
			}
		}
		
		######################
		// Orphanet
		
		if (isset($GLOBALS["configuration_file"]["query_databases"]["orphanet"]) && $GLOBALS["configuration_file"]["query_databases"]["orphanet"] == 1) {
			// If the current gene had Orphanet results
			if (isset($orphanet_gene_to_orphanet_number[$result["gene"][$i]])) {
				$orphanet_disorders_output = "";
				$is_orphanet_ar = 0;
				$is_orphanet_ad = 0;
				
				// Go through each disorder for the current gene
				foreach ($orphanet_gene_to_orphanet_number[$result["gene"][$i]] as $orphanet_disorder) {
					if (isset($orphanet_disorders[$orphanet_disorder]["genes"][$result["gene"][$i]]["association_type"])) {
						// Go through each association type and status for the current disorder<->gene association
						for ($x = 0; $x < count($orphanet_disorders[$orphanet_disorder]["genes"][$result["gene"][$i]]["association_type"]); $x++) {
							// If this is not the first association type
							if ($x > 0) {
								$orphanet_disorders_output .= "[AND ";
							}
							
							$orphanet_disorders_output .= $orphanet_disorders[$orphanet_disorder]["genes"][$result["gene"][$i]]["association_status"][$x]." ";
							$orphanet_disorders_output .= $orphanet_disorders[$orphanet_disorder]["genes"][$result["gene"][$i]]["association_type"][$x];
							
							// If this is not the first association type
							if ($x > 0) {
								$orphanet_disorders_output .= "] ";
							} else {
								$orphanet_disorders_output .= " ";
							}
						}
					}
					
					$orphanet_disorders_output .= $orphanet_disorders[$orphanet_disorder]["name"]." ";
					
					$orphanet_disorders_output .= "(".$orphanet_disorder."); ";
					
					// If the current disorder has inheritances specified
					if (isset($orphanet_disorders[$orphanet_disorder]["inheritances"])) {
						// Go through each inheritance
						foreach ($orphanet_disorders[$orphanet_disorder]["inheritances"] as $inheritance) {
							// Shorten inheritance types
							if ($inheritance == "Autosomal dominant") { $inheritance = "AD"; $is_orphanet_ad = 1; }
							if ($inheritance == "Autosomal recessive") { $inheritance = "AR"; $is_orphanet_ar = 1; }
							if ($inheritance == "Mitochondrial inheritance") { $inheritance = "MT"; }
							if ($inheritance == "X-linked dominant") { $inheritance = "XLD"; }
							if ($inheritance == "X-linked recessive") { $inheritance = "XLR"; }
							if ($inheritance == "Y-linked") { $inheritance = "YL"; }
							
							$orphanet_disorders_output .= $inheritance.", ";
						}
						
						$orphanet_disorders_output = substr($orphanet_disorders_output, 0, -2); // Remove the last ", " that was added by the loop above
					}
					
					$orphanet_disorders_output .= "; ";
					
					// If the current disorder has ages of onset specified
					if (isset($orphanet_disorders[$orphanet_disorder]["ages_of_onset"])) {
						// Go through each age of onset
						foreach ($orphanet_disorders[$orphanet_disorder]["ages_of_onset"] as $age_of_onset) {
							$orphanet_disorders_output .= $age_of_onset.", ";
						}
						
						$orphanet_disorders_output = substr($orphanet_disorders_output, 0, -2); // Remove the last ", " that was added by the loop above
					}
					
					$orphanet_disorders_output .= " // ";
				}
				
				$orphanet_disorders_output = substr($orphanet_disorders_output, 0, -4); // Remove the last " // " that was added by the loop above
				
				array_push($result["Orphanet Disorders"], $orphanet_disorders_output);
				array_push($result["Is Orphanet AR"], $is_orphanet_ar);
				array_push($result["Is Orphanet AD"], $is_orphanet_ad);
			} else {
				array_push($result["Orphanet Disorders"], "None");
				array_push($result["Is Orphanet AR"], "0");
				array_push($result["Is Orphanet AD"], "0");
			}
		}
				
		######################
		// MGRB VAFs
		
		if (isset($GLOBALS["configuration_file"]["query_databases"]["mgrb_afs"]) && $GLOBALS["configuration_file"]["query_databases"]["mgrb_afs"] == 1) {
			if (isset($MGRB_AF_results[$current_non_chr_chromosome][($result["start"][$i] + 1)][$result["ref"][$i]][$result["alt"][$i]])) {
				array_push($result["MGRB AF"], $MGRB_AF_results[$current_non_chr_chromosome][($result["start"][$i] + 1)][$result["ref"][$i]][$result["alt"][$i]]);
			} else {
				array_push($result["MGRB AF"], "0");
			}
		}
		
		######################
		// GBS
		//$GBS_results[<variant chromosome>][<variant start>][<variant end>][<sample name>][<event type>][<method name>][<block_coordinates/copy_number>] = <string value>
		
		// If samples to query are present in the GBS
		if (isset($gbs_samples_present)) {
			if (isset($GBS_results[$current_non_chr_chromosome][$result["start"][$i]][$result["end"][$i]])) {
				$result_string = "";
				
				// Go through every sample with a GBS result
				foreach (array_keys($GBS_results[$current_non_chr_chromosome][$result["start"][$i]][$result["end"][$i]]) as $sample_name) {	
					// Go through every event type identified for the sample
					foreach (array_keys($GBS_results[$current_non_chr_chromosome][$result["start"][$i]][$result["end"][$i]][$sample_name]) as $event_type) {						
						// Go through every method
						foreach (array_keys($GBS_results[$current_non_chr_chromosome][$result["start"][$i]][$result["end"][$i]][$sample_name][$event_type]) as $method_name) {
							$result_string .= $sample_name.": ";
						
							$result_string .= $event_type." ";
							
							$result_string .= "(".$method_name.") - ";
							
							$result_string .= "Block coordinates=".$GBS_results[$current_non_chr_chromosome][$result["start"][$i]][$result["end"][$i]][$sample_name][$event_type][$method_name]["block_coordinates"];
							
							if ($GBS_results[$current_non_chr_chromosome][$result["start"][$i]][$result["end"][$i]][$sample_name][$event_type][$method_name]["copy_number"] != "") {								
								$result_string .= ", Event copy number=".$GBS_results[$current_non_chr_chromosome][$result["start"][$i]][$result["end"][$i]][$sample_name][$event_type][$method_name]["copy_number"];
							}
	
							// If the first annotation tag is just ":", there are no annotations for this block (: is put in between the tag and value, when neither exist it's all that's put in)
							if ($GBS_results[$current_non_chr_chromosome][$result["start"][$i]][$result["end"][$i]][$sample_name][$event_type][$method_name]["annotation_tags"][0] == ":") {
								$result_string .= "; ";
							} else {
								$result_string .= ", Annotations=";
								
								foreach ($GBS_results[$current_non_chr_chromosome][$result["start"][$i]][$result["end"][$i]][$sample_name][$event_type][$method_name]["annotation_tags"] as $annotation) {
									$result_string .= $annotation.", ";
								}
								
								$result_string = substr($result_string, 0, -2); // Remove the last ", " that was added by the loop above
								
								$result_string .= "; ";
							}
						}
					}
				}
				
				array_push($result["GBS"], $result_string);
			} else {
				array_push($result["GBS"], ".");
			}
		}
	}
	
	return $result; // Return the modified input or the original input if there was no DB connection
}

#############################################
# TEST WHETHER A 'WHERE' CLAUSE IS REQUIRED
#############################################

function filter_flag() {
	if ($_SESSION["exclude_failed_variants"] == 1 || $_SESSION["min_cadd"] > 0 || $_SESSION["regions"] != "" || $_SESSION["exclude_regions"] != "" || count($_SESSION["gene_list_to_search"]) > 0 || count($_SESSION["gene_list_to_exclude"]) > 0 || $_SESSION["search_impact"] == "high" || $_SESSION["search_impact"] == "medhigh" || $_SESSION["search_impact"] == "coding" || $_SESSION["min_qual"] > 0 || $_SESSION["search_variant_type"] == "snp" || $_SESSION["search_variant_type"] == "indel" || $_SESSION["1000gmaf"] > 0 || $_SESSION["exclude_dbsnp_common"] == 1 || $_SESSION["exclude_dbsnp_flagged"] == 1 || $_SESSION["espmaf"] > 0 || $_SESSION["exacmaf"] > 0) {
		return true;
	} else {
		return false;
	}
}

#############################################
# TEST WHETHER A '--gt-filter' CLAUSE IS REQUIRED
#############################################

function gt_filter_flag() {
	if ($_SESSION["min_seq_depth"] != 0 || ($_SESSION["hasped"] == "Yes" && $_SESSION["analysis_type"] != "" && $_SESSION["family"] != "")) {
		return true;
	} else {
		return false;
	}
}

#############################################
# PRINT A FILTER IN THE GEMINI QUERY
#############################################

function add_filter($previous_filter_flag, $filter) {
	$cmd = "";
	
	if ($previous_filter_flag == 1) { # If there has already been a filter before this one we need an AND
		$cmd .= ' AND (';
	} else {
		$cmd .= '(';
	}
	
	$cmd .= $filter;
	
	$cmd .= ')';
	
	return $cmd;
}

#############################################
# GENERATE DATABASE SUMMARY
#############################################

function generate_db_summary($db_path) {
	// Make sure the DB exists
	if (!file_exists($db_path)) {
		return false;
	}
	
	######################
	# Determine the database GEMINI version
	######################
	
	$db_version = obtain_gemini_database_version($db_path);
					
	if ($db_version === false) {
		return false;
	} else {
		// Strip any letters e.g. 0.11.1a -> 0.11.1
		$db_version = preg_replace("/[A-Za-z]/", "", $db_version);
	}
	
	######################
	# Different impact names based on database GEMINI version
	######################
	
	// Starting at 0.18 GEMINI are now reporting the sequencing ontology impacts rather than the ones they used before, this DB summary produces a lot of erroneous zeros if this isn't accounted for
	
	if ($db_version >= 0.18) {
		$intergenic = "intergenic_variant";
		$synonymous = "synonymous_variant";
		$intron = "intron_variant";
		$upstream = "upstream_gene_variant";
		$downstream = "downstream_gene_variant";
		$stop_gained = "stop_gained";
		$stop_lost = "stop_lost";
		$frame_shift = "frameshift_variant";
		$splice_acceptor = "splice_acceptor_variant";
		$splice_donor = "splice_donor_variant";
	} else {
		$intergenic = "intergenic";
		$synonymous = "synonymous_coding";
		$intron = "intron";
		$upstream = "upstream";
		$downstream = "downstream";
		$stop_gained = "stop_gain";
		$stop_lost = "stop_loss";
		$frame_shift = "frame_shift";
		$splice_acceptor = "splice_acceptor";
		$splice_donor = "splice_donor";
	}
	
	######################
	# Total variants
	######################
	
	$variant_numbers_query["Total Variants"] = "SELECT COUNT(*) FROM variants";
	
	######################
	# Total variants + PASS
	######################
	
	$variant_numbers_query["Total Passed Variants"] = "SELECT COUNT(*) FROM variants WHERE (filter is null)";
	
	######################
	# Intergenic variants
	######################
	
	$variant_numbers_query["Intergenic Variants"] = "SELECT COUNT(*) FROM variants WHERE (impact = '".$intergenic."' or impact = '".$upstream."' or impact = '".$downstream."') AND (filter is null)";
	
	######################
	# Intergenic variants + ENCODE element
	######################
	
	$variant_numbers_query["Intergenic Variants With ENCODE Element"] = "SELECT COUNT(*) FROM variants WHERE (impact = '".$intergenic."' or impact = '".$upstream."' or impact = '".$downstream."') and (encode_tfbs != 'None' or encode_dnaseI_cell_count != 'None') AND (filter is null)";
	
	######################
	# Intergenic variants + >15 CADD
	######################
	
	$variant_numbers_query["Intergenic Variants With >15 CADD"] = "SELECT COUNT(*) FROM variants WHERE (impact = '".$intergenic."') AND (cadd_scaled > 15) AND (filter is null)";
	
	######################
	# Low impact
	######################
	
	$variant_numbers_query["Low Impact Variants"] = "SELECT COUNT(*) FROM variants WHERE (impact_severity = 'LOW') AND (filter is null)";
	
	######################
	# Low impact + silent
	######################
	
	$variant_numbers_query["Low Impact Synonymous"] = "SELECT COUNT(*) FROM variants WHERE (impact = '".$synonymous."') AND (filter is null)";
	
	######################
	# Low impact + intronic (overriding Gemini's medium call for splice region variants)
	######################
	
	$variant_numbers_query["Low Impact Intronic"] = "SELECT COUNT(*) FROM variants WHERE (impact = '".$intron."' or impact = 'splice_region') AND (filter is null)";
	
	######################
	# Low impact + upstream/downstream
	######################
	
	$variant_numbers_query["Low Impact Up/Downstream"] = "SELECT COUNT(*) FROM variants WHERE (impact = '".$upstream."' or impact = '".$downstream."') AND (filter is null)";
	
	######################
	# Medium impact
	######################
	
	$variant_numbers_query["Medium Impact"] = "SELECT COUNT(*) FROM variants WHERE (impact_severity = 'MED') AND (filter is null)";
	
	######################
	# Medium impact + Polyphen2 damaging
	######################
	
	$variant_numbers_query["Medium Impact Polyphen2 Damaging"] = "SELECT COUNT(*) FROM variants WHERE (polyphen_pred = 'probably_damaging') AND (filter is null)";
	
	######################
	# Medium impact + SIFT
	######################
	
	$variant_numbers_query["Medium Impact SIFT Damaging"] = "SELECT COUNT(*) FROM variants WHERE (sift_pred = 'deleterious') AND (filter is null)";
	
	######################
	# Medium impact + Polyphen2 + SIFT
	######################
	
	$variant_numbers_query["Medium Impact Polyphen2 & SIFT Damaging"] = "SELECT COUNT(*) FROM variants WHERE (polyphen_pred = 'probably_damaging') AND (sift_pred = 'deleterious') AND (filter is null)";
	
	######################
	# Medium impact + CADD > 15
	######################
	
	$variant_numbers_query["Medium Impact With >15 CADD"] = "SELECT COUNT(*) FROM variants WHERE (impact_severity = 'MED') AND (cadd_scaled > 15) AND (filter is null)";
	
	######################
	# Medium impact + conserved
	######################
	
	$variant_numbers_query["Medium Impact Conserved"] = "SELECT COUNT(*) FROM variants WHERE (impact_severity = 'MED') AND (is_conserved = 1) AND (filter is null)";
	
	######################
	# High impact
	######################
	
	$variant_numbers_query["High Impact"] = "SELECT COUNT(*) FROM variants WHERE (impact_severity = 'HIGH') AND (filter is null)";

	######################
	# High impact + stop gain
	######################
	
	$variant_numbers_query["High Impact Stop Gained"] = "SELECT COUNT(*) FROM variants WHERE (impact = '".$stop_gained."') AND (filter is null)";
	
	######################
	# High impact + stop lost
	######################
	
	$variant_numbers_query["High Impact Stop Lost"] = "SELECT COUNT(*) FROM variants WHERE (impact = '".$stop_lost."') AND (filter is null)";
	
	######################
	# High impact + frameshift
	######################
	
	$variant_numbers_query["High Impact Frame Shift"] = "SELECT COUNT(*) FROM variants WHERE (impact = '".$frame_shift."') AND (filter is null)";
	
	######################
	# High impact + essential splice acceptor
	######################
	
	$variant_numbers_query["High Impact Splice Acceptor"] = "SELECT COUNT(*) FROM variants WHERE (impact = '".$splice_acceptor."') AND (filter is null)";
	
	######################
	# High impact + essential splice donor
	######################
	
	$variant_numbers_query["High Impact Splice Donor"] = "SELECT COUNT(*) FROM variants WHERE (impact = '".$splice_donor."') AND (filter is null)";
	
	######################
	# High impact + essential splice
	######################
	
	$variant_numbers_query["High Impact Splice Site"] = "SELECT COUNT(*) FROM variants WHERE (impact = '".$splice_donor."' or impact = '".$splice_acceptor."') AND (filter is null)";
	
	######################
	# High impact + LoF-HC
	######################
	
	$variant_numbers_query["High Impact High Confidence Loss of Function"] = "SELECT COUNT(*) FROM variants WHERE (vep_lof = 'HC') AND (filter is null)";
		
	######################
	# High impact + LoF-LC
	######################
	
	$variant_numbers_query["High Impact Low Confidence Loss of Function"] = "SELECT COUNT(*) FROM variants WHERE (vep_lof = 'LC') AND (filter is null)";
	
	######################
	# Run DB queries
	######################
		
	foreach (array_keys($variant_numbers_query) as $query_title) {
		$num_variants = obtain_variant_count_from_gemini($db_path, $variant_numbers_query[$query_title], "all variants");
	
		if ($num_variants !== false) {
			$database_summary_all_variants[$query_title] = $num_variants;
		}
		
		$num_variants = obtain_variant_count_from_gemini($db_path, $variant_numbers_query[$query_title], "rare variants");
	
		if ($num_variants !== false) {
			$database_summary_rare_variants[$query_title] = $num_variants;
		}
	}
	
	######################
	# Write to output files
	######################
	
	// If at least one variant count was obtained for all variants
	if (count($database_summary_all_variants) > 0) {
		$output_filename = $db_path.".all_variants.summary";
	   	
	   	// If a summary report already exists, delete it
	   	if (file_exists($output_filename)) {
		   	unlink($output_filename);
	   	}
	   	
	   	// Create and open the output file for writing
	   	if (!($output = fopen($output_filename, "w"))) {
		   	return false;
	   	}
	   	
	   	// Header line all variants
		fwrite($output, "Title\tNumber of Variants\n");
		
		// Go through every output row and write it to the file
		foreach (array_keys($database_summary_all_variants) as $title) {
			fwrite($output, $title."\t".$database_summary_all_variants[$title]."\n");
		}
	   	
	   	fclose($output);
	} else {
		return false;
	}
	
	// If at least one variant count was obtained for all variants
	if (count($database_summary_rare_variants) > 0) {
		$output_filename = $db_path.".rare_variants.summary";
	   	
	   	// If a summary report already exists, delete it
	   	if (file_exists($output_filename)) {
		   	unlink($output_filename);
	   	}
	   	
	   	// Create and open the output file for writing
	   	if (!($output = fopen($output_filename, "w"))) {
		   	return false;
	   	}
	   	
	   	// Header line all variants
		fwrite($output, "Title\tNumber of Variants\n");
		
		// Go through every output row and write it to the file
		foreach (array_keys($database_summary_rare_variants) as $title) {
			fwrite($output, $title."\t".$database_summary_rare_variants[$title]."\n");
		}
	   	
	   	fclose($output);
	} else {
		return false;
	}
	
	return true;
}

#############################################
# MAKE SURE THAT THE NUMBER OF COLUMNS IN EACH ROW COMING OUT OF GEMINI IS THE SAME AS THE NUMBER HEADER COLUMNS
#############################################

function process_and_validate_num_columns($result) {
	// Explode the header columns into an array
	$header_columns = explode("\t", $result[0]);
	
	// Prepare the results hash to store an array of each row value for each column
	foreach ($header_columns as $header_column) {
		$hashed_result[$header_column] = array();
	}
	
	// Go through each result row
	for ($i = 0; $i < count($result); $i++) {
		// Skip the header row, already hashed the columns
		if ($i == 0) {
			continue;
		}
		
		// Explode the result row into an array
		$row_array = explode("\t", $result[$i]);
	
		// Check whether the results row has more columns than the header (should not happen)
		if (count($row_array) > count($header_columns)) {
			return false;
		}
		
		// Go through each column in the header row
		for ($x = 0; $x < count($header_columns); $x++) {
			// Hash the results in this format: $result[column1] = array(row1_value, row2_value, ...), $result[column2] = array(row1_value, row2_value, ...)
			
			// If the result row has a value for the current header column (sometimes this doesn't happen as Gemini can output less columns in a result row than in the header!)
			if (isset($row_array[$x])) {
				array_push($hashed_result[$header_columns[$x]], $row_array[$x]);
			} else {
				array_push($hashed_result[$header_columns[$x]], "");
			}
		}
	}
	
	return $hashed_result;
}

#############################################
# EXTRACT FAMILIAL INFORMATION FROM DB
#############################################

function extract_familial_information($query_db) {
	// The actual folder housing the databases the user has access to within the databases path
	$db_dir = $GLOBALS["configuration_file"]["gemini"]["db_dir"]."/".$query_db;
	
	if (!file_exists($db_dir)) {
		return false;
	}
	
	$query = $GLOBALS["configuration_file"]["gemini"]["binary"].' query -q "SELECT name, family_id, phenotype, sex FROM samples" '.escape_database_filename($db_dir);
	
	exec($query, $query_result, $exit_code); # Execute the Gemini query
	
	for ($i = 0; $i < count($query_result); $i++) { # Go through every sample returned
		$columns = explode("\t", $query_result[$i]); # Split the result into an array by column
		
		# Save individual results as variables for easy referencing
		$sample_name = $columns[0];
		$sample_family_id = $columns[1];
		$sample_phenotype = $columns[2];
		$sample_sex = $columns[3];
		
		$family_info[$sample_family_id][$sample_name]["sex"] = $sample_sex;
		$family_info[$sample_family_id][$sample_name]["phenotype"] = $sample_phenotype;
	}
	
	if (!isset($family_info)) {
		return false;
	} else {
		return $family_info;
	}
}

#############################################
# CHECK FOR THE PRESENCE OF CUSTOM DBSNP COLUMNS IN THE DB
#############################################

function are_custom_columns_present($group, $query_db, $columns) {
	// The actual folder housing the databases the user has access to within the databases path
	$db_dir = $GLOBALS["configuration_file"]["gemini"]["db_dir"]."/".$group."/".$query_db;
	
	if (!file_exists($db_dir)) {
		return false;
	}

	$query = $GLOBALS["configuration_file"]["gemini"]["binary"].' query -q "SELECT '.$columns.' FROM variants" '.escape_database_filename($db_dir)." | head -n 1";
	
	exec($query, $query_result, $exit_code); # Execute the Gemini query

	if (strpos($query_result[0], "no such column") !== false) {
		return false;
	 } else {
		 return true;
	 }
}

#############################################
# OBTAIN THE NUMBER OF VARIANTS FOR A GEMINI QUERY
#############################################

function obtain_variant_count_from_gemini($db_path, $query, $rare_filter) {
	// Set up the start of the Gemini query
	$query = $GLOBALS["configuration_file"]["gemini"]["binary"].' query -q "'.$query;
	
	// If only rare variants are to be returned
	if ($rare_filter == "rare variants") {
		// Check whether a WHERE was already in the query and if so append an AND
		if (preg_match('/ WHERE /', $query)) {
			$query .= ' AND ';
		// If there isn't already a WHERE, append a WHERE
		} else {
			$query .= ' WHERE ';
		}
		
		$query .= '(aaf_1kg_all < 0.01 OR aaf_1kg_all is null) AND (aaf_esp_all < 0.01 OR aaf_esp_all is null) AND (aaf_adj_exac_all < 0.01 OR aaf_adj_exac_all is null)" ';
	// Otherwise generate them for all samples
	} else {
		$query .= '" ';
	}
	
	$query .= escape_database_filename($db_path);
	
	exec($query, $query_result, $exit_code); # Execute the Gemini query
	
	// If the query succeeded
	if ($exit_code == 0) {
		return $query_result[0];
	} else {
		return false;
	}
}

#############################################
# DETERMINE THE VERSION OF GEMINI USED TO MAKE A DATABASE
#############################################

function obtain_gemini_database_version($db_path) {
	$query = $GLOBALS["configuration_file"]["gemini"]["binary"].' query -q "SELECT version FROM version" '.escape_database_filename($db_path);
					
	exec($query, $query_result, $exit_code); # Execute the Gemini query
	
	// If the query succeeded
	if ($exit_code == 0) {
		return $query_result[0];
	} else {
		return false;
	}
}

#############################################
# CHECK WHETHER A PHENOTYPE IS AFFECTED
#############################################

function is_affected($phenotype) {
	if ($phenotype == "2") {
		return true;
	} else {
		return false;
	}	
}

#############################################
# CHECK WHETHER A PHENOTYPE IS UNAFFECTED
#############################################

function is_unaffected($phenotype) {
	if ($phenotype == "1") {
		return true;
	} else {
		return false;
	}	
}

#############################################
# CHECK WHETHER A PHENOTYPE IS UNAFFECTED
#############################################

function is_unknown_phenotype($phenotype) {
	if ($phenotype != "1" && $phenotype != "2") {
		return true;
	} else {
		return false;
	}	
}

#############################################
# QUANTIFY THE NUMBER OF AFFECTED AND UNAFFECTED SAMPLES
#############################################

function family_affected_status($family) { # Input is $family_info{$sample_family_id}
	$affected_status["affected"] = array();
	$affected_status["unaffected"] = array();
	$affected_status["unknown"] = array();
	
	foreach (array_keys($family) as $sample_name) {
		if (is_affected($family[$sample_name]["phenotype"])) {
			array_push($affected_status["affected"], $sample_name);
		} elseif (is_unaffected($family[$sample_name]["phenotype"])) {
			array_push($affected_status["unaffected"], $sample_name);
		} elseif (is_unknown_phenotype($family[$sample_name]["phenotype"])) {
			array_push($affected_status["unknown"], $sample_name);
		}
	}
	
	return $affected_status;
}

?>
