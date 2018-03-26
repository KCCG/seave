<?php

	require basename("..").'/php_header.php'; // Require the PHP header housing required PHP functions

	#############################################
	# CHECK POST/SESSION VARIABLES AND SET NEW ONES WHERE REQUIRED
	#############################################
	
	// Make sure there is a saved database session variable that is not empty
	if (!isset($_SESSION["query_db"], $_SESSION["query_group"]) || $_SESSION["query_db"] == "" || $_SESSION["query_group"] == "") {
		results_page_redirect("no_database");
	}
	
	// Make sure all of the query page options have been POSTed
	if (!isset($_POST["regions"], $_POST["exclude_regions"], $_POST["min_seq_depth"], $_POST["genes"], $_POST["exclude_genes"], $_POST["num_variants"], $_POST["variant_type"], $_POST["1000gmaf"], $_POST["espmaf"], $_POST["exacmaf"], $_POST["variant_impact"], $_POST["min_cadd"], $_POST["min_qual"], $_POST["num_variants"], $_POST["variant_type"])) {		
		results_page_redirect("missing_post_variables");
	}
	
	######################
	
	$parse_regions_result = parse_submitted_regions(htmlspecialchars($_POST["regions"], ENT_QUOTES, 'UTF-8'), "regions");
	
	if ($parse_regions_result !== true) {
		log_results_page_error($parse_regions_result."<br>");
		
		results_page_redirect("invalid_search_regions");
	}
	
	$parse_regions_result = parse_submitted_regions(htmlspecialchars($_POST["exclude_regions"], ENT_QUOTES, 'UTF-8'), "exclude_regions");
	
	if ($parse_regions_result !== true) {
		log_results_page_error($parse_regions_result."<br>");
		
		results_page_redirect("invalid_exclusion_regions");
	}
	
	######################
	
	// Store the lists selected in the multi-select box so they can be restored on the query page (only used for this)
	if (isset($_POST["gene_list_selection"])) {
		$_SESSION["gene_list_selection"] = $_POST["gene_list_selection"];
	// If no list was selected reset to nothing (in case some were selected before)
	} else {
		$_SESSION["gene_list_selection"] = "";
	}
	
	// Store the lists selected in the multi-select boxe so they can be restored on the query page (only used for this)
	if (isset($_POST["gene_list_exclusion_selection"])) {
		$_SESSION["gene_list_exclusion_selection"] = $_POST["gene_list_exclusion_selection"];
	// If no list was selected reset to nothing (in case some were selected before)
	} else {
		$_SESSION["gene_list_exclusion_selection"] = "";
	}
	
	// Store the values entered into the manual gene list boxes so they can be restored when the user goes back to the query page (only used for this)
	$_SESSION["exclusion_gene_list"] = htmlspecialchars($_POST["exclude_genes"], ENT_QUOTES, 'UTF-8');
	$_SESSION["gene_list"] = htmlspecialchars($_POST["genes"], ENT_QUOTES, 'UTF-8');
	
	######################
	
	// If no gene lists to search were selected and nothing was typed in the manual entry box, set the gene list to search to an empty array
	if (!isset($_POST["gene_list_selection"]) && $_SESSION["gene_list"] == "") {
		$_SESSION["gene_list_to_search"] = array();
	// If gene lists were selected or typed in the manual entry box
	} else {
		// If gene lists were selected
		if (isset($_POST["gene_list_selection"])) {
			$gene_list_result = determine_gene_list($_POST["gene_list_selection"], $_SESSION["gene_list"]);
			
			if ($gene_list_result === false) {
				results_page_redirect("cant_determine_gene_list");
			} else {
				$_SESSION["gene_list_to_search"] = $gene_list_result;
			}
		// If no gene lists were selected but something was typed in the manual entry box
		} else {
			$gene_list_result = determine_gene_list("", $_SESSION["gene_list"]);
			
			if ($gene_list_result === false) {
				results_page_redirect("cant_determine_gene_list");
			} else {
				$_SESSION["gene_list_to_search"] = $gene_list_result;
			}
		}
		
		if (count($_SESSION["gene_list_to_search"]) > 0) { // Only try to validate a gene list if query genes exist
			$failed_genes = validate_gene_list($_SESSION["gene_list_to_search"]); // Generate a list of genes failing validation
			
			if ($failed_genes === false) {
				// Log that an error occurred in validating genes but otherwise keep going with the query
				log_results_page_error("There was a problem validating genes entered/selected.<br>");
			} elseif (count($failed_genes) > 0) {
				log_results_page_error("At least one gene you want to search for is not present in the Ensembl 75 list and therefore probably isn't in the database. Below is a list of all genes failing this validation:<br>");
				
				foreach ($failed_genes as $failed_gene) {
					log_results_page_error($failed_gene." ");
				}
				
				log_results_page_error("<br>");
			}
		} else {
			log_results_page_error("You submitted gene lists to search but they are empty or invalid so no gene filter was applied.<br>");
		}
	}
	
	######################
	
	// If no gene lists to search were selected and nothing was typed in the manual entry box, set the gene list to search to an empty array
	if (!isset($_POST["gene_list_exclusion_selection"]) && $_SESSION["exclusion_gene_list"] == "") {
		$_SESSION["gene_list_to_exclude"] = array();
	// If gene lists were selected or typed in the manual entry box
	} else {
		// If gene lists were selected
		if (isset($_POST["gene_list_exclusion_selection"])) {
			$gene_list_result = determine_gene_list($_POST["gene_list_exclusion_selection"], $_SESSION["exclusion_gene_list"]);
			
			if ($gene_list_result === false) {
				results_page_redirect("cant_determine_exclusion_gene_list");
			} else {
				$_SESSION["gene_list_to_exclude"] = $gene_list_result;
			}
		// If no gene lists were selected but something was typed in the manual entry box
		} else {
			$gene_list_result = determine_gene_list("", $_SESSION["exclusion_gene_list"]);
			
			if ($gene_list_result === false) {
				results_page_redirect("cant_determine_exclusion_gene_list");
			} else {
				$_SESSION["gene_list_to_exclude"] = $gene_list_result;
			}
		}
		
		if (count($_SESSION["gene_list_to_exclude"]) > 0) { // Only try to validate a gene list if query genes exist
			$failed_genes = validate_gene_list($_SESSION["gene_list_to_exclude"]); // Generate a list of genes failing validation

			if ($failed_genes === false) {
				// Log that an error occurred in validating genes but otherwise keep going with the query
				log_results_page_error("There was a problem validating genes entered/selected.<br>");
			} elseif (count($failed_genes) > 0) {
				log_results_page_error("At least one gene you want to exclude is not present in the Ensembl 75 list and therefore probably isn't in the database. Below is a list of all genes failing this validation:<br>");
				
				foreach ($failed_genes as $failed_gene) {
					log_results_page_error($failed_gene." ");
				}
				
				log_results_page_error("<br>");
			}
		} else {
			log_results_page_error("You submitted gene lists to exclude but they were empty or invalid so no exclusion gene filter was applied.<br>");
		}
	}
	
	######################

	// If the two dbsnp columns exist, then check whether they were ticked for removal
	if ($_SESSION["dbsnp_columns_exist"] == 1) {
		if (isset($_POST["is_dbsnp_common"]) && $_POST["is_dbsnp_common"] == "true") {
			$_SESSION["exclude_dbsnp_common"] = 1;
		} else {
			$_SESSION["exclude_dbsnp_common"] = "false";
		}
		
		if (isset($_POST["is_dbsnp_flagged"]) && $_POST["is_dbsnp_flagged"] == "true") {
			$_SESSION["exclude_dbsnp_flagged"] = 1;
		} else {
			$_SESSION["exclude_dbsnp_flagged"] = "";
		}
	}
		
	######################
	
	$_SESSION["min_seq_depth"] = htmlspecialchars($_POST["min_seq_depth"], ENT_QUOTES, 'UTF-8');
	
	######################
	
	$_SESSION["1000gmaf"] = htmlspecialchars($_POST["1000gmaf"], ENT_QUOTES, 'UTF-8');
	
	######################
	
	$_SESSION["espmaf"] = htmlspecialchars($_POST["espmaf"], ENT_QUOTES, 'UTF-8');
	
	######################
	
	$_SESSION["exacmaf"] = htmlspecialchars($_POST["exacmaf"], ENT_QUOTES, 'UTF-8');

	######################
	
	$_SESSION["search_impact"] = htmlspecialchars($_POST["variant_impact"], ENT_QUOTES, 'UTF-8');
	
	######################
	
	$_SESSION["min_cadd"] = htmlspecialchars($_POST["min_cadd"], ENT_QUOTES, 'UTF-8');
	
	######################
	
	$_SESSION["min_qual"] = htmlspecialchars($_POST["min_qual"], ENT_QUOTES, 'UTF-8');
	
	######################
	
	// If the exclude failed variants button was ticked
	if (isset($_POST["exclude_failed_variants"])) {
		$_SESSION["exclude_failed_variants"] = 1;
	} else {
		$_SESSION["exclude_failed_variants"] = "false";
	}
	
	######################
	
	if (preg_match('/^([0-9]+)$/', $_POST["num_variants"], $matches)) { # Extract the number entered
		$_SESSION["return_num_variants"] = $_POST["num_variants"];
	} else { # If something was entered but doesn't match the regex, give an error
		results_page_redirect("incorrect_number_of_variants");
	}
	
	######################
	
	if ($_POST["variant_type"] == "snp" || $_POST["variant_type"] == "indel" || $_POST["variant_type"] == "both") { # Check that either snp, indel or both were selected as the variant type to search
		$_SESSION["search_variant_type"] = $_POST["variant_type"];
	} else {
		results_page_redirect("incorrect_variant_type");
	}

	#############################################
	# FETCH ANNOTATIONS INFORMATION
	#############################################
	
	// Fetch the annotations from the DB
	$annotations = fetch_all_annotations();
	
	if ($annotations === false) {
		results_page_redirect("cant_fetch_annotation_information");
	}
	
	#############################################
	# GEMINI QUERY
	#############################################
	
	list($result, $cmd, $exit_code) = gemini_query(); # Return the results array, query command and the php exit code
	
	#############################################
	# MySQL EXTERNAL DATABASES INTERGRATION
	#############################################
	
	if ($exit_code == 0) { // If the Gemini query worked, obtain information from external databases
		// Hash the results and check whether any of the result rows have a different number of columns as the header row, if more return false, if less than simply add empty values for the missing columns
		$result = process_and_validate_num_columns($result);
		
		if ($result === false) {
			results_page_redirect("more_columns_in_result_row_than_header");
		}
		
		#############################################
		# MYSQL INTEGRATION
		#############################################
		
		// Modify the Gemini results to append information from MySQL - this function will return the original Gemini results if it can't fetch this information for a number of reasons
		$result_mysql = mysql_integration($result);
		
		// If the MySQL result was successfully added to the results, substitute in the updated results over the initial Gemini ones
		if ($result_mysql !== false) {
			$result = $result_mysql;
			
			unset($result_mysql);
		} else {
			unset($result_mysql);
		}
		
		// If an error with the integration was found, display it to the user
		if (isset($_SESSION["mysql_integration_error"])) {
			log_results_page_error($_SESSION["mysql_integration_error"]."<br>");
			
			unset($_SESSION["mysql_integration_error"]);
		}
		
		#############################################
		# CREATE NEW COLUMNS FOR EASIER DISPLAY IN THE SEAVE TABLE AND RESULTS
		#############################################
		
		// Create the new columns as empty arrays
		$result["Variant"] = array();
		$result["Type"] = array();
		
		// Only add an inheritance pattern selected column if one was selected
		if ($_SESSION["analysis_type"] != "" && $_SESSION["analysis_type"] != "analysis_none") {
			$result["Inheritance Pattern Selected"] = array();
		}
		
		// Go through every result row
		for ($i = 0; $i < count($result["chrom"]); $i++) {
			// Add the variant type column value
			if (strpos($result["ref"][$i], ',') !== false || strpos($result["alt"][$i], ',') !== false) {
				array_push($result["Type"], "Multiallelic");
			} elseif (strlen($result["ref"][$i]) == 1 && strlen($result["alt"][$i]) > 1) {
				array_push($result["Type"], "Insertion");
			} elseif (strlen($result["ref"][$i]) > 1 && strlen($result["alt"][$i]) == 1) {
				array_push($result["Type"], "Deletion");
			} elseif (strlen($result["ref"][$i]) > 1 && strlen($result["alt"][$i]) > 1) {
				array_push($result["Type"], "MNP");
			} elseif (strlen($result["ref"][$i]) == 1 && strlen($result["alt"][$i]) == 1) {
				array_push($result["Type"], "SNP");
			} else {
				array_push($result["Type"], "");
			}
			
			// Add the pseudo-HGVS variant location/ref/alt column value by concatenating required columns; different for MT
			if ($result["chrom"][$i] == "chrMT") {
				array_push($result["Variant"], "m.".($result["start"][$i] + 1).$result["ref"][$i].">".$result["alt"][$i]);
			} else {
				array_push($result["Variant"], $result["chrom"][$i].":g.".($result["start"][$i] + 1).$result["ref"][$i].">".$result["alt"][$i]);
			}
			
			// If an inheritance pattern was selected, add it to every variant
			if ($_SESSION["analysis_type"] != "" && $_SESSION["analysis_type"] != "analysis_none") {
				// Convert to human-friendly description
				if ($_SESSION["analysis_type"] == "analysis_hom_rec") {
					array_push($result["Inheritance Pattern Selected"], "Homozygous Recessive");
				} elseif ($_SESSION["analysis_type"] == "analysis_het_dom") {
					array_push($result["Inheritance Pattern Selected"], "Heterozygous Dominant");
				} elseif ($_SESSION["analysis_type"] == "analysis_comp_het") {
					array_push($result["Inheritance Pattern Selected"], "Compound Heterozygous");
				} elseif ($_SESSION["analysis_type"] == "analysis_denovo_dom") {
					array_push($result["Inheritance Pattern Selected"], "De Novo Dominant");
				} else {
					array_push($result["Inheritance Pattern Selected"], $_SESSION["analysis_type"]);
				}
			}
		}

		#############################################
		# STORE AND AMALGAMATE DP, AO, AND GQ COLUMNS
		#############################################
		
		// Go through each column
		foreach (array_keys($result) as $column) {
			// Look at all gts.<sample name> columns to find sample names
			if (preg_match('/^gts\.(.*)/', $column, $matches)) {
				$sample_name = $matches[1];
				
				// Go through each variant for the current sample and amalgamate the relevant columns
				for ($i = 0; $i < count($result["chrom"]); $i++) {
					// Format: <calculated VAF> (<alt depth>/<total depth>)				
					
					// Empty variable to store the constructed column
					$VAF_column_value = "";
					
					// If the alt or total depth is unknown or not a number, print a . for the VAF
					if ($result["gt_alt_depths.".$sample_name][$i] == "-1" || $result["gt_depths.".$sample_name][$i] == "-1" || !is_numeric($result["gt_alt_depths.".$sample_name][$i]) || !is_numeric($result["gt_depths.".$sample_name][$i])) {
						$VAF_column_value .= ".";
					// If the depths are numbers, calculate VAF
					} else {
						// Mitochondrial VAFs should be returned with a higher precision given mitochondria do not follow the standard rules
						if ($result["chrom"][$i] == "chrMT") {
							$rounding_dp = 4;
						} else {
							$rounding_dp = 2;
						}
						
						// If either of the depths is a zero, print the VAF as 0
						if ($result["gt_alt_depths.".$sample_name][$i] === "0" || $result["gt_depths.".$sample_name][$i] === "0") {
							$VAF_column_value .= "0.".str_repeat("0", $rounding_dp);
						} else {
							$VAF_column_value .= sprintf('%.'.$rounding_dp.'f', round($result["gt_alt_depths.".$sample_name][$i] / $result["gt_depths.".$sample_name][$i], $rounding_dp)); // round() for applying Swedish rounding to the VAF calculation and sprintf to turn values of 0 or 1 into 0.00 and 1.00
						}
					}
					
					$VAF_column_value .= " (";
						// Print the alt depth
						if ($result["gt_alt_depths.".$sample_name][$i] == "-1") {
							$VAF_column_value .= ".";
						} else {
							$VAF_column_value .= $result["gt_alt_depths.".$sample_name][$i];
						}
						
						$VAF_column_value .= "/";
						
						// Print the total depth
						if ($result["gt_depths.".$sample_name][$i] == "-1") {
							$VAF_column_value .= ".";
						} else {
							$VAF_column_value .= $result["gt_depths.".$sample_name][$i];
						}
					$VAF_column_value .= ")";
					
					// Store the amalgamated VAF column
					$result["VAF ".$sample_name][] = $VAF_column_value;
					
					// Create the GQ column with conditional processing of -1 values
					if ($result["gt_quals.".$sample_name][$i] == "-1") {
						$result["GQ ".$sample_name][] = ".";
					} else {
						$result["GQ ".$sample_name][] = $result["gt_quals.".$sample_name][$i];
					}
				}
			}
		}
		
		#############################################
		# MAX RESULTS REACHED WARNING
		#############################################
	    
	    if (count($result["chrom"]) >= $_SESSION["return_num_variants"]) {
			log_results_page_error("Warning: Your filtration options returned the maximum number of variants allowed. This means <em>you are likely not seeing all of the variants for your query</em>. Consider increasing this maximum on the query page and/or tightening your filtration parameters to reduce the number of results.<br>");
		}
		
		#############################################
		# RENAME COLUMNS
		#############################################
		
		// Go through each result column and rename specific columns
		foreach (array_keys($result) as $column) {
			// Replace multiple columns matching regex
			
			rename_result_column_prefix($column, "gts\.", "GT ");

			#############################################
			// Replace single column
			
			rename_result_column_replace($column, "qual", "Quality");

			rename_result_column_replace($column, "gene", "Gene");
			
			rename_result_column_replace($column, "depth", "Cohort Depth");
			
			rename_result_column_replace($column, "qual_depth", "Quality by Depth");
			
			rename_result_column_replace($column, "rms_map_qual", "RMS Mapping Quality");
			
			rename_result_column_replace($column, "cyto_band", "Cyto Band");
			
			rename_result_column_replace($column, "vep_canonical", "Canonical Isoform");
			
			rename_result_column_replace($column, "vep_strand", "Strand");
			
			rename_result_column_replace($column, "vep_cdna_position", "cDNA Position");
			
			rename_result_column_replace($column, "vep_cds_position", "CDS Position");
			
			rename_result_column_replace($column, "cadd_raw", "CADD Raw");
			rename_result_column_replace($column, "cadd_scaled", "CADD Scaled");
			
			rename_result_column_replace($column, "clinvar_rs", "ClinVar Variation ID");
			rename_result_column_replace($column, "clinsig", "ClinVar Clinical Significance");
			rename_result_column_replace($column, "clintrait", "ClinVar Trait");
			
			rename_result_column_replace($column, "cosmic_number", "COSMIC ID");
			rename_result_column_replace($column, "cosmic_count", "COSMIC Count");
			rename_result_column_replace($column, "cosmic_primary_site", "COSMIC Primary Site");
			rename_result_column_replace($column, "cosmic_primary_histology", "COSMIC Primary Histology");
			
			rename_result_column_replace($column, "polyphen_pred", "PolyPhen Prediction");
			rename_result_column_replace($column, "polyphen_score", "PolyPhen Score");
			
			rename_result_column_replace($column, "sift_pred", "SIFT Prediction");
			rename_result_column_replace($column, "sift_score", "SIFT Score");
			
			rename_result_column_replace($column, "exon", "Exon");
			
			rename_result_column_replace($column, "is_lof", "Is Loss of Function");
			
			rename_result_column_replace($column, "vep_lof", "Is LOFTEE LoF");
			rename_result_column_replace($column, "vep_lof_filter", "LOFTEE Filter");
			rename_result_column_replace($column, "vep_lof_flags", "LOFTEE Flags");
			
			rename_result_column_replace($column, "is_exonic", "Is Exonic");
			
			rename_result_column_replace($column, "is_coding", "Is Coding");
			
			rename_result_column_replace($column, "is_coding_or_splice", "Is Coding or Splice");
			
			rename_result_column_replace($column, "allele_count", "Allele Count");
			
			rename_result_column_replace($column, "num_alleles", "# Alleles");

			rename_result_column_replace($column, "impact", "Impact (GEMINI)");
			
			rename_result_column_replace($column, "chrom", "Chr");
			
			rename_result_column_replace($column, "start", "Start");
			
			rename_result_column_replace($column, "end", "End");
			
			rename_result_column_replace($column, "filter", "Filter");
			
			rename_result_column_replace($column, "ref", "Ref");
			
			rename_result_column_replace($column, "alt", "Alt");
			
			rename_result_column_replace($column, "transcript", "Transcript");
			
			rename_result_column_replace($column, "codon_change", "Codon Change");
			
			rename_result_column_replace($column, "aa_change", "AA Change");
			
			rename_result_column_replace($column, "aa_length", "AA Length");
			
			rename_result_column_replace($column, "impact_severity", "Impact Severity");
			
			rename_result_column_replace($column, "impact_so", "Impact");
			
			rename_result_column_replace($column, "vep_hgvsc", "HGVS.c");
			rename_result_column_replace($column, "vep_HGVSc", "HGVS.c");
			
			rename_result_column_replace($column, "vep_hgvsp", "HGVS.p");
			rename_result_column_replace($column, "vep_HGVSp", "HGVS.p");
			
			rename_result_column_replace($column, "variant_samples", "Variant Samples");
			
			rename_result_column_replace($column, "num_hom_ref", "# Samples HOM REF");
			
			rename_result_column_replace($column, "num_het", "# Samples HET");
			rename_result_column_replace($column, "het_samples", "HET Samples");
			rename_result_column_replace($column, "HET_samples", "HET Samples");
			
			rename_result_column_replace($column, "num_hom_alt", "# Samples HOM ALT");
			rename_result_column_replace($column, "hom_alt_samples", "HOM ALT Samples");
			rename_result_column_replace($column, "HOM_ALT_samples", "HOM ALT Samples");
			
			rename_result_column_replace($column, "num_unknown", "# Samples UNKNOWN");
		}
		
		#############################################
		# DELETE UNNECESSARY COLUMNS
		#############################################
		
		$columns_to_delete = array();
		
		// Go through each result column and add columns to be deleted based on regexs
		foreach (array_keys($result) as $current_column) {
			if (preg_match('/^gt_depths\..*/', $current_column, $matches)) {
				array_push($columns_to_delete, $current_column);
			} elseif (preg_match('/^gt_ref_depths\..*/', $current_column, $matches)) {
				array_push($columns_to_delete, $current_column);
			} elseif (preg_match('/^gt_alt_depths\..*/', $current_column, $matches)) {
				array_push($columns_to_delete, $current_column);
			} elseif (preg_match('/^gt_quals\..*/', $current_column, $matches)) {
				array_push($columns_to_delete, $current_column);
			}
		}
		
		array_push($columns_to_delete, "sv_cipos_start_left");
		array_push($columns_to_delete, "sv_cipos_end_left");
		array_push($columns_to_delete, "sv_cipos_start_right");
		array_push($columns_to_delete, "sv_cipos_end_right");
		array_push($columns_to_delete, "sv_length");
		array_push($columns_to_delete, "sv_is_precise");
		array_push($columns_to_delete, "sv_tool");
		array_push($columns_to_delete, "sv_evidence_type");
		array_push($columns_to_delete, "sv_event_id");
		array_push($columns_to_delete, "sv_mate_id");
		array_push($columns_to_delete, "sv_strand");
		array_push($columns_to_delete, "in_omim");
		array_push($columns_to_delete, "clinvar_sig");
		array_push($columns_to_delete, "clinvar_disease_name");
		array_push($columns_to_delete, "clinvar_dbsource");
		array_push($columns_to_delete, "clinvar_dbsource_id");
		array_push($columns_to_delete, "clinvar_origin");
		array_push($columns_to_delete, "clinvar_dsdb");
		array_push($columns_to_delete, "clinvar_dsdbid");
		array_push($columns_to_delete, "clinvar_disease_acc");
		array_push($columns_to_delete, "clinvar_in_locus_spec_db");
		array_push($columns_to_delete, "clinvar_on_diag_assay");
		array_push($columns_to_delete, "clinvar_causal_allele");
		array_push($columns_to_delete, "vep_distance");
		array_push($columns_to_delete, "vep_feature_type");
		array_push($columns_to_delete, "call_rate");
		array_push($columns_to_delete, "vcf_id");
		array_push($columns_to_delete, "variant_id");
		array_push($columns_to_delete, "type");
		array_push($columns_to_delete, "Impact (GEMINI)");
		array_push($columns_to_delete, "anno_id");
		array_push($columns_to_delete, "exome_chip");
		array_push($columns_to_delete, "anc_allele");
		array_push($columns_to_delete, "rms_bq");
		array_push($columns_to_delete, "cigar");
		array_push($columns_to_delete, "strand_bias");
		array_push($columns_to_delete, "in_hom_run");
		array_push($columns_to_delete, "haplotype_score");
		array_push($columns_to_delete, "allele_bal");
		array_push($columns_to_delete, "in_hm2");
		array_push($columns_to_delete, "in_hm3");
		array_push($columns_to_delete, "is_somatic");
		array_push($columns_to_delete, "somatic_score");
		array_push($columns_to_delete, "gms_illumina");
		array_push($columns_to_delete, "gms_solid");
		array_push($columns_to_delete, "gms_iontorrent");
		array_push($columns_to_delete, "in_cse");
		array_push($columns_to_delete, "sub_type");
		array_push($columns_to_delete, "biotype");
		array_push($columns_to_delete, "aaf");
		array_push($columns_to_delete, "num_reads_w_dels");
		array_push($columns_to_delete, "in_segdup");
		array_push($columns_to_delete, "is_conserved");
		array_push($columns_to_delete, "fitcons");
		array_push($columns_to_delete, "gerp_bp_score");
		array_push($columns_to_delete, "gerp_element_pval");
		array_push($columns_to_delete, "hwe");
		array_push($columns_to_delete, "inbreeding_coeff");
		array_push($columns_to_delete, "pi");
		array_push($columns_to_delete, "recomb_rate");
		array_push($columns_to_delete, "grc");
		array_push($columns_to_delete, "num_mapq_zero");
		array_push($columns_to_delete, "cosmic_ids");	
		
		// Go through each column in the and delete it if it exists in the result
		foreach ($columns_to_delete as $column) {
			if (isset($result[$column])) {
				unset($result[$column]);
			}
		}

		#############################################
		# REARRANGE COLUMNS
		#############################################
		
		// Define an array to hold the column order for rearrangement
		$column_order = array();
		
		// The column order depends on the order in which the below are pushed to the array, any columns not specified will be kept in the order they are in already in the result array but after the specified columns

		array_push($column_order, "Variant");
		array_push($column_order, "Chr");
		array_push($column_order, "Start");
		array_push($column_order, "End");
		array_push($column_order, "Ref");
		array_push($column_order, "Alt");
		array_push($column_order, "Quality");
		array_push($column_order, "Cohort Depth");
		array_push($column_order, "Quality by Depth");
		array_push($column_order, "RMS Mapping Quality");
		array_push($column_order, "Filter");
		array_push($column_order, "Cyto Band");
		array_push($column_order, "Gene");
		array_push($column_order, "Transcript");
		array_push($column_order, "Canonical Isoform");
		array_push($column_order, "Strand");
		array_push($column_order, "HGVS.c");
		array_push($column_order, "HGVS.p");
		array_push($column_order, "cDNA Position");
		array_push($column_order, "CDS Position");
		array_push($column_order, "Codon Change");
		array_push($column_order, "AA Change");
		array_push($column_order, "Exon");
		array_push($column_order, "Type");
		array_push($column_order, "Impact");
		array_push($column_order, "Impact Severity");
		array_push($column_order, "Is Loss of Function");
		array_push($column_order, "Is LOFTEE LoF");
		array_push($column_order, "LOFTEE Filter");
		array_push($column_order, "LOFTEE Flags");
		array_push($column_order, "Is Exonic");
		array_push($column_order, "Is Coding");
		array_push($column_order, "Is Coding or Splice");
		array_push($column_order, "Inheritance Pattern Selected");
		array_push($column_order, "Variant Samples");
		array_push($column_order, "# Samples HOM REF");
		array_push($column_order, "# Samples HET");
		array_push($column_order, "HET Samples");
		array_push($column_order, "# Samples HOM ALT");
		array_push($column_order, "HOM ALT Samples");
		array_push($column_order, "# Samples UNKNOWN");
		
		// Add GT columns
		foreach (array_keys($result) as $column) {
			if (preg_match("/GT\s.*/", $column)) {
				array_push($column_order, $column);
			}
		}
		
		array_push($column_order, "Allele Count");
		array_push($column_order, "# Alleles");
		
		// Add depth & quality columns
		foreach (array_keys($result) as $column) {
			if (preg_match("/VAF\s.*/", $column)) {
				array_push($column_order, $column);
			}
			
			if (preg_match("/GQ\s.*/", $column)) {
				array_push($column_order, $column);
			}
		}
				
		array_push($column_order, "in_exac");
		array_push($column_order, "aaf_exac_all");
		array_push($column_order, "aaf_adj_exac_all");
		array_push($column_order, "aaf_adj_exac_afr");
		array_push($column_order, "aaf_adj_exac_amr");
		array_push($column_order, "aaf_adj_exac_eas");
		array_push($column_order, "aaf_adj_exac_fin");
		array_push($column_order, "aaf_adj_exac_nfe");
		array_push($column_order, "aaf_adj_exac_oth");
		array_push($column_order, "aaf_adj_exac_sas");
		array_push($column_order, "MGRB AF");
		array_push($column_order, "in_1kg");
		array_push($column_order, "aaf_1kg_amr");
		array_push($column_order, "aaf_1kg_eas");
		array_push($column_order, "aaf_1kg_sas");
		array_push($column_order, "aaf_1kg_afr");
		array_push($column_order, "aaf_1kg_eur");
		array_push($column_order, "aaf_1kg_all");
		array_push($column_order, "in_esp");
		array_push($column_order, "aaf_esp_ea");
		array_push($column_order, "aaf_esp_aa");
		array_push($column_order, "aaf_esp_all");
		array_push($column_order, "in_dbsnp");
		array_push($column_order, "is_dbsnp_common");
		array_push($column_order, "is_dbsnp_flagged");
		array_push($column_order, "rs_ids");
		array_push($column_order, "OMIM Numbers");
		array_push($column_order, "OMIM Titles");
		array_push($column_order, "OMIM Status");
		array_push($column_order, "OMIM Disorders");
		array_push($column_order, "Orphanet Disorders");
		array_push($column_order, "Is Orphanet AR");
		array_push($column_order, "Is Orphanet AD");
		array_push($column_order, "Genomics England PanelApp");
		array_push($column_order, "ClinVar Variation ID");
		array_push($column_order, "ClinVar Clinical Significance");
		array_push($column_order, "ClinVar Trait");
		array_push($column_order, "COSMIC ID");
		array_push($column_order, "COSMIC Count");
		array_push($column_order, "COSMIC Primary Site");
		array_push($column_order, "COSMIC Primary Histology");
		array_push($column_order, "CGC Associations");
		array_push($column_order, "CGC Mutation Types");
		array_push($column_order, "CGC Translocation Partners");
		array_push($column_order, "MITOMAP AF");
		array_push($column_order, "MITOMAP Disease");
		array_push($column_order, "CADD Raw");
		array_push($column_order, "CADD Scaled");
		array_push($column_order, "PolyPhen Prediction");
		array_push($column_order, "PolyPhen Score");
		array_push($column_order, "SIFT Prediction");
		array_push($column_order, "SIFT Score");
		array_push($column_order, "PROVEAN_pred");
		array_push($column_order, "PROVEAN_score");
		array_push($column_order, "RVIS ExAC 0.05% Percentile");
		array_push($column_order, "MetaSVM_score");
		array_push($column_order, "MetaSVM_rankscore");
		array_push($column_order, "MetaSVM_pred");
		array_push($column_order, "MetaLR_score");
		array_push($column_order, "MetaLR_rankscore");
		array_push($column_order, "MetaLR_pred");
		array_push($column_order, "GERP++_NR");
		array_push($column_order, "FATHMM_score");
		array_push($column_order, "FATHMM_rankscore");
		array_push($column_order, "FATHMM_pred");
		array_push($column_order, "pfam_domain");
		array_push($column_order, "Uniprot_acc");
		array_push($column_order, "Uniprot_id");
		array_push($column_order, "Uniprot_aapos");
		array_push($column_order, "AA Length");
		array_push($column_order, "rmsk");
		array_push($column_order, "in_cpg_island");
		array_push($column_order, "encode_tfbs");
		array_push($column_order, "encode_dnaseI_cell_count");
		array_push($column_order, "encode_dnaseI_cell_list");
		array_push($column_order, "encode_consensus_gm12878");
		array_push($column_order, "encode_consensus_h1hesc");
		array_push($column_order, "encode_consensus_helas3");
		array_push($column_order, "encode_consensus_hepg2");
		array_push($column_order, "encode_consensus_huvec");
		array_push($column_order, "encode_consensus_k562");
		array_push($column_order, "vista_enhancers");
		array_push($column_order, "info");
		
		// Go through each column in the predefined order and move these (in order) to the start of a new result array while deleting the old elements to save RAM
		foreach ($column_order as $column) {
			if (isset($result[$column])) {
				$sorted_result[$column] = $result[$column];
				
				unset($result[$column]);
			}
		}
		
		// Concatenate the remaining columns in the original result array
		foreach (array_keys($result) as $column) {
			$sorted_result[$column] = $result[$column];
			
			unset($result[$column]);
		}
		
		#############################################
		# OUTPUT RESULTS TO TSV
		#############################################
		
		date_default_timezone_set('Australia/Sydney');
		
		$output_filename = date("Y_m_d_H_i_s",time())."_".rand_string("5"); // Generate an output filename with the current time and a random string for uniqueness/security
        $output_full_path = basename("..")."/temp/".$output_filename.".tsv"; // Save the full path where the output file will be for opening it
        
        $output = fopen($output_full_path, "w") or results_page_redirect("cant_create_output_file");
        
        // Output the column header
        foreach (array_keys($sorted_result) as $column) {
	        fwrite($output, $column."\t");
	    }
	    
	    fwrite($output, "\n");
        
        // Go through every Gemini result 
        for ($i = 0; $i < count($sorted_result["Chr"]); $i++) {
	        // Go through every column
	        foreach (array_keys($sorted_result) as $column) {
		        fwrite($output, $sorted_result[$column][$i]."\t");
	        }
	        
	        fwrite($output, "\n");
	    }
	    
	    fwrite($output, "\nQuery information:");
	    
	    fwrite($output, "\nGEMINI query\t".$cmd);
	    fwrite($output, "\nTime\t".date("d/m/Y (H:i:s)",time()));
	    
	    fwrite($output, "\n\nUser information:");
	    // If the user is not logged in
	    if (!isset($_SESSION["logged_in"]["email"]) || $_SESSION["logged_in"]["email"] == "") {
		    fwrite($output, "\nUsername\tNone (Public)");
		// If the user is logged in, print their email
	    } else {
		    fwrite($output, "\nUsername\t".$_SESSION["logged_in"]["email"]);
	    }
	    fwrite($output, "\nIP\t".$_SERVER['REMOTE_ADDR']);
	    
	    fwrite($output, "\n\nAnnotation versions used:");
	    
	    // Go through each annotation
		foreach (array_keys($annotations) as $annotation_name) {
			fwrite($output, "\n".$annotation_name."\t".$annotations[$annotation_name]["current_version"]);
		}
		
		fwrite($output, "\n\nEND");
	    
	    fclose($output);
	    
	    #############################################
		# OUTPUT ANY ERRORS TO ERR FILE
		#############################################
		
		if (isset($_SESSION["query_error"])) {
			$error_output_full_path = basename("..")."/temp/".$output_filename.".err"; // Save the full path where the output file will be for opening it
			
			$output = fopen($error_output_full_path, "w") or results_page_redirect("cant_create_output_file");
		    
			fwrite($output, $_SESSION["query_error"]);
		    
		    fclose($output);
		    
		    unset($_SESSION["query_error"]);
		}
		
	    #############################################
		# OUTPUT GEMINI QUERY COMMAND TO GEM FILE
		#############################################
	    
	    $gemini_command_output_full_path = basename("..")."/temp/".$output_filename.".gem"; // Save the full path where the output file will be for opening it
		
		$output = fopen($gemini_command_output_full_path, "w") or results_page_redirect("cant_create_output_file");
	    
	    fwrite($output, $cmd);
	    
	    fclose($output);
	    
	    #############################################
		# ON SUCCESS REDIRECT TO RESULTS
		#############################################
	    
	    log_website_event("GEMINI query group '".$_SESSION["query_group"]."', database '".$_SESSION["query_db"]."', command '".$cmd."', results file '".$output_filename.".tsv'");
	    
	    header("Location: ".basename("..")."/results?query=".$output_filename);
	    
	    exit;
	} else {
		results_page_redirect("gemini_query_error");
	}
						
	#############################################
	# PAGE FUNCTIONS
	#############################################
	
	// Function to log query error without a redirect to the results page
	function log_results_page_error($error_description) {
		if (isset($_SESSION["query_error"])) {
			$_SESSION["query_error"] .= $error_description;
		} else {
			$_SESSION["query_error"] = $error_description;
		}
	}
	
	// Function to redirect to results page
	function results_page_redirect($session_variable_name = NULL) {
		if (isset($session_variable_name)) {
			$_SESSION["query_".$session_variable_name] = 1;
		}

		header("Location: ".basename("..")."/results");
			
		exit;
	}
	
	// Function to rename a result column with a full replacement
	function rename_result_column_replace($current_column, $replace, $new_column) {
		// Use the query result from outside the function
		global $result;
		
		if (preg_match('/^'.$replace.'$/', $current_column)) {
			$result[$new_column] = $result[$current_column];
			
			unset($result[$current_column]);
		}
	}
	
	// Function to rename a result column with a prefix
	function rename_result_column_prefix($current_column, $regex, $prefix) {
		// Use the query result from outside the function
		global $result;
		
		if (preg_match('/^'.$regex.'(.*)/', $current_column, $matches)) {
			$result[$prefix.$matches[1]] = $result[$current_column];
			
			unset($result[$current_column]);
		}
	}

?>