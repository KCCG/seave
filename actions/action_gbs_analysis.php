<?php

	require basename("..").'/php_header.php'; // Require the PHP header housing required PHP functions

	#############################################
	# VERIFY SESSION
	#############################################
	
	if (!isset($_SESSION["query_db"], $_SESSION["query_group"]) || $_SESSION["query_db"] == "" || $_SESSION["query_group"] == "") {
		gbs_query_page_redirect();
	}
	
	#############################################
	# VERIFY REQUIRED POSTS
	#############################################
	
	if (!isset($_POST["family"]) || !isset($_POST[preg_replace("/\s/", "_", $_POST["family"])."analysis_type"])) {
		gbs_query_page_redirect("insufficient_data");
	}
	
	#############################################
	# CAPTURE THE SUBMITTED VALUES
	#############################################
	
	$_SESSION["gbs_analysis_type"] = htmlspecialchars($_POST[preg_replace("/\s/", "_", $_POST["family"])."analysis_type"], ENT_QUOTES, 'UTF-8');
	
	$_SESSION["gbs_family"] = htmlspecialchars($_POST["family"], ENT_QUOTES, 'UTF-8');
	
	// If a numeric greater than copy number has been submitted, update the global value - for analysis types that don't display this parameter in the query form, the downstream functions will ignore the value so there is no need to reset it, keeping it set means it will be saved for rerunning analyses that do use it
	if (!in_array($_SESSION["gbs_analysis_type"], array("rohmer", "svfusions")) && isset($_POST["cngreaterthan"]) && is_numeric($_POST["cngreaterthan"])) {
		$_SESSION["gbs_cngreaterthan"] = htmlspecialchars($_POST["cngreaterthan"], ENT_QUOTES, 'UTF-8');
	}
	
	// If a numeric less than copy number has been submitted, update the global value - for analysis types that don't display this parameter in the query form, the downstream functions will ignore the value so there is no need to reset it, keeping it set means it will be saved for rerunning analyses that do use it
	if (!in_array($_SESSION["gbs_analysis_type"], array("rohmer", "svfusions")) && isset($_POST["cnlessthan"]) && is_numeric($_POST["cnlessthan"])) {
		$_SESSION["gbs_cnlessthan"] = htmlspecialchars($_POST["cnlessthan"], ENT_QUOTES, 'UTF-8');
	}
	
	// If a numeric minimum block size has been submitted, update the global value, otherwise reset to default - for analysis types that don't display this parameter in the query form, the downstream functions will ignore the value so there is no need to reset it, keeping it set means it will be saved for rerunning analyses that do use it
	if (isset($_POST["minblocksize"]) && is_numeric($_POST["minblocksize"])) {
		$_SESSION["gbs_minblocksize"] = $_POST["minblocksize"];
	}
	
	// If a exclude failed variants filter has been submitted, that means it was selected as true, so update the global value to the true value
	if (isset($_POST["exclude_failed_variants"])) {
		$_SESSION["gbs_exclude_failed_variants"] = "1";
	// If the analysis type is not ROHmer, which doesn't use the exclude failed variants filter, then no POST value means the box was unticked so the value should be set to the false value
	} elseif ($_SESSION["gbs_analysis_type"] != "rohmer") {
		$_SESSION["gbs_exclude_failed_variants"] = "2";
	}
	
	#############################################
	# EXTRACT FAMILIAL INFORMATION FOR THE DB
	#############################################
	
	$family_info = extract_familial_information($_SESSION["query_group"]."/".$_SESSION["query_db"]);
	
	if ($family_info === false) {
		gbs_query_page_redirect("cant_fetch_family_information");
	}

	#############################################
	# DETERMINE WHICH OF THE SAMPLES TO BE QUERIED ARE PRESENT IN THE GBS
	#############################################
	
	$samples_to_query_gbs = array();
	
	// If the entire database is to be queried, save all samples
	if ($_SESSION["gbs_family"] == "entiredatabase") {
		// Go through each family and save all the samples in each one
		foreach (array_keys($family_info) as $family) {
			foreach (array_keys($family_info[$family]) as $sample) {
				array_push($samples_to_query_gbs, $sample);
			}
		}
	// If a family was specified to be queried
	} elseif (isset($family_info[$_SESSION["gbs_family"]])) {
		foreach (array_keys($family_info[$_SESSION["gbs_family"]]) as $sample) {
			array_push($samples_to_query_gbs, $sample);
		}
	} else {
		gbs_query_page_redirect("invalid_family");
	}
	
	$GBS_presence = fetch_gbs_samples_presence($samples_to_query_gbs);
	//$GBS_presence[<sample name>]["methods"] = array(<method1>, <method2>)
	
	if ($GBS_presence === false) {
		return false;
	}
	
	#############################################
	# CREATE ARRAYS OF SAMPLES IN THE GBS BY PHENOTYPE
	#############################################
	
	$samples_to_query = array();
	$affected_samples_to_query = array();
	$unaffected_samples_to_query = array();
	$svfusions_samples_to_query = array();
	
	$families_to_query = array();
	
	// If the entire dataset is to be queried, fetch all samples
	if ($_SESSION["gbs_family"] == "entiredatabase") {
		$families_to_query = array_keys($family_info);
	// If a family was specified to be queried
	} elseif (isset($family_info[$_SESSION["gbs_family"]])) {
		$families_to_query = array($_SESSION["gbs_family"]);
	} else {
		gbs_query_page_redirect("invalid_family");
	}
	
	// Go through each family and sample and pull out samples relevant to different analyses
	foreach ($families_to_query as $family) {
		foreach (array_keys($family_info[$family]) as $sample_name) {
			// If the sample is in the GBS, save it
			if (isset($GBS_presence[$sample_name])) {
				// Save the sample to the overall samples to query array
				array_push($samples_to_query, $sample_name);
				
				// Check the affected status and save the sample to separate affected status arrays
				if (is_affected($family_info[$family][$sample_name]["phenotype"])) {
					array_push($affected_samples_to_query, $sample_name);
				} elseif (is_unaffected($family_info[$family][$sample_name]["phenotype"])) {
					array_push($unaffected_samples_to_query, $sample_name);
				}
				
				// If the sample has a BND/INV event, save it for SV Fusions analysis
				if (in_array("BND", array_keys($GBS_presence[$sample_name]["event_types"])) || in_array("inversion", array_keys($GBS_presence[$sample_name]["event_types"]))) {
					array_push($svfusions_samples_to_query, $sample_name);
				}
			}
		}
	}
	
	#############################################
	# ANALYSIS TYPE SAMPLE OVERLAPPING BLOCKS
	#############################################
	
	if ($_SESSION["gbs_analysis_type"] == "sample_overlaps") {
		$all_methods_with_samples_in_gbs = array();
		$methods_with_potential_sample_overlaps = array();
		$samples_with_methods_with_potential_overlaps = array();
		
		// Create a single array of all methods for all samples in the GBS (i.e. with repeating values where there are more than 1)
		foreach ($samples_to_query as $sample_name) {
			$all_methods_with_samples_in_gbs = array_merge($all_methods_with_samples_in_gbs, array_keys($GBS_presence[$sample_name]["methods"]));
		}
		
		// Count the number of occurrences of each method
		$all_methods_with_samples_in_gbs = array_count_values($all_methods_with_samples_in_gbs);
		
		// Save all methods that occur more than once to a separate array
		foreach (array_keys($all_methods_with_samples_in_gbs) as $method_name) {
			if ($all_methods_with_samples_in_gbs[$method_name] > 1) {
				array_push($methods_with_potential_sample_overlaps, $method_name);
			}
		}
		
		// Go through each method per sample and save all samples where one of their methods has 2+ samples
		foreach ($samples_to_query as $sample_name) {
			foreach (array_keys($GBS_presence[$sample_name]["methods"]) as $method_name) {
				if (in_array($method_name, $methods_with_potential_sample_overlaps)) {
					array_push($samples_with_methods_with_potential_overlaps, $sample_name);
				}
			}
		}
		
		$overlapping_blocks = analysis_type_sample_overlaps_gbs($samples_with_methods_with_potential_overlaps, $methods_with_potential_sample_overlaps);
		
		if ($overlapping_blocks === false) {
			gbs_query_page_redirect("cant_fetch_overlapping_blocks");
		}
		
		log_website_event("GBS query for sample overlaps, samples '".implode(", ", $samples_with_methods_with_potential_overlaps)."', methods '".implode(",", $methods_with_potential_sample_overlaps)."'");
		
		output_results_to_tsv_and_redirect($overlapping_blocks);
		
	#############################################
	# ANALYSIS TYPE METHOD OVERLAPPING BLOCKS
	#############################################
	
	} elseif ($_SESSION["gbs_analysis_type"] == "method_overlaps") {
		$samples_with_potential_method_overlaps = array();
		
		// Save all samples with 2 or more methods, ignore the rest
		foreach ($samples_to_query as $sample_name) {
			if (count($GBS_presence[$sample_name]["methods"]) > 1) {
				array_push($samples_with_potential_method_overlaps, $sample_name);
			}
		}
		
		$overlapping_blocks = analysis_type_method_overlaps_gbs($samples_with_potential_method_overlaps);
		
		if ($overlapping_blocks === false) {
			gbs_query_page_redirect("cant_fetch_overlapping_blocks");
		}
		
		log_website_event("GBS query for method overlaps, samples '".implode(", ", $samples_with_potential_method_overlaps)."'");
		
		output_results_to_tsv_and_redirect($overlapping_blocks);
		
	#############################################
	# ANALYSIS TYPE METHOD ROHMER
	#############################################
	
	} elseif ($_SESSION["gbs_analysis_type"] == "rohmer") {
		$rohmer_blocks = analysis_type_rohmer_gbs($affected_samples_to_query, $unaffected_samples_to_query);
		
		if ($rohmer_blocks === false) {
			gbs_query_page_redirect("cant_fetch_rohmer_blocks");
		}
		
		log_website_event("GBS query for ROHmer, affected samples '".implode(", ", $affected_samples_to_query)."', unaffected samples '".implode(", ", $unaffected_samples_to_query)."'");
		
		output_results_to_tsv_and_redirect($rohmer_blocks);
		
	#############################################
	# ANALYSIS TYPE METHOD SV FUSIONS
	#############################################
	
	} elseif ($_SESSION["gbs_analysis_type"] == "svfusions") {		
		// Store the lists selected in the multi-select box so they can be restored on the query page (only used for this)
		if (isset($_POST["gbs_gene_list_selection"])) {
			$_SESSION["gbs_gbs_gene_list_selection"] = $_POST["gbs_gene_list_selection"];
		// If no list was selected reset to nothing (in case some were selected before)
		} else {
			$_SESSION["gbs_gbs_gene_list_selection"] = "";
		}
		
		// Store the manual entry gene list submitted
		if (isset($_POST["svfusions_genes"])) {
			$_SESSION["gbs_svfusions_gene_list"] = htmlspecialchars($_POST["svfusions_genes"], ENT_QUOTES, 'UTF-8');
		} else {
			$_SESSION["gbs_svfusions_gene_list"] = "";
		}
		
		// If no gene list was selected and no genes were typed into the manual entry box
		if ($_SESSION["gbs_gbs_gene_list_selection"] == "" && $_SESSION["gbs_svfusions_gene_list"] == "") {
			// Empty array of genes to search (means all will be searched)
			$gene_list_to_search = array();
		// If at least one gene list was selected or genes were typed into the manual entry box
		} else {
			// If at least one gene list was selected
			if (isset($_POST["gbs_gene_list_selection"])) {
				$gene_list_to_search = determine_gene_list($_POST["gbs_gene_list_selection"], $_SESSION["gbs_svfusions_gene_list"]);
			// Otherwise just parse the manual entry list
			} else {
				$gene_list_to_search = determine_gene_list("", $_SESSION["gbs_svfusions_gene_list"]);
			}
			
			if ($gene_list_to_search === false) {
				gbs_query_page_redirect("cant_determine_gene_list");
			}
			
			// If no genes were parsed out of the gene lists selected and manual list submitted
			if (count($gene_list_to_search) == 0) {
				log_results_page_error("No genes were extracted from the gene list(s) you specified. Returning results for all genes in the genome.");
			} else {
				// Determine which genes (if any) are not in the GBS gene name -> co-ordinate table, these are genes that will not be searched for
				$missing_genes = validate_gene_list_gbs($gene_list_to_search);
				
				if ($missing_genes === false) {
					gbs_query_page_redirect("cant_determine_missing_genes");
				}
				
				// If missing genes were found
				if (count($missing_genes) > 0) {
					// Save any genes that were not found to a string to display to the user
					log_results_page_error("Some genes do not have coordinates stored in the GBS for block searching, no blocks will be returned for these genes: ".implode(", ", $missing_genes)."\n");
					
					// Subtract the missing genes from the genes to search
					foreach ($gene_list_to_search as $gene) {
						// If the gene is not in the GBS
						if (array_search($gene, $missing_genes) !== false) { // array_search returns an ID which can evaluate to true/false, the function will return false when it doesn't find a match, check this with === or !==
							// Delete it from the gene list to search
							unset($gene_list_to_search[array_search($gene, $gene_list_to_search)]);
						}
					}
					
					// Reindex the array
					$gene_list_to_search = array_values($gene_list_to_search);
					
					// Check that genes are remaining to search
					if (count($gene_list_to_search) == 0) {
						log_results_page_error("No valid genes were extracted from the gene list(s) you specified. Returning results for all genes in the genome.");
					}
				}
			}
		}
		
		$svfusions_blocks = analysis_type_svfusions_gbs($svfusions_samples_to_query, $gene_list_to_search);
		
		if ($svfusions_blocks === false) {
			gbs_query_page_redirect("cant_fetch_svfusions_blocks");
		}
		
		log_website_event("GBS query for SV Fusions, samples '".implode(", ", $svfusions_samples_to_query)."', gene(s) to search '".implode(", ", $gene_list_to_search)."'");
		
		output_results_to_tsv_and_redirect($svfusions_blocks);
		
	#############################################
	# ANALYSIS TYPE GENE LIST(S)
	#############################################
	
	} elseif ($_SESSION["gbs_analysis_type"] == "gene_lists") {
		// Store the lists selected in the multi-select box so they can be restored on the query page (only used for this)
		if (isset($_POST["gene_list_selection"])) {
			$_SESSION["gbs_gene_list_selection"] = $_POST["gene_list_selection"];
		// If no list was selected reset to nothing (in case some were selected before)
		} else {
			$_SESSION["gbs_gene_list_selection"] = "";
		}
		
		// Store the manual entry gene list submitted
		if (isset($_POST["genes"])) {
			$_SESSION["gbs_gene_list"] = htmlspecialchars($_POST["genes"], ENT_QUOTES, 'UTF-8');
		} else {
			$_SESSION["gbs_gene_list"] = "";
		}
		
		// If no gene list was selected and no genes were typed into the manual entry box
		if ($_SESSION["gbs_gene_list_selection"] == "" && $_SESSION["gbs_gene_list"] == "") {
			gbs_query_page_redirect("no_genes_specified");
		}
		
		// If gene lists were selected
		if (isset($_POST["gene_list_selection"])) {
			$gene_list_to_search = determine_gene_list($_POST["gene_list_selection"], $_SESSION["gbs_gene_list"]);
		} else {
			$gene_list_to_search = determine_gene_list("", $_SESSION["gbs_gene_list"]);
		}
		
		if ($gene_list_to_search === false) {
			gbs_query_page_redirect("cant_determine_gene_list");
		}
		
		// If no genes were parsed out of the gene lists selected and manual list submitted
		if (count($gene_list_to_search) == 0) {
			gbs_query_page_redirect("no_genes_specified");
		}
		
		// Determine which genes (if any) are not in the GBS gene name -> co-ordinate table, these are genes that will not be searched for
		$missing_genes = validate_gene_list_gbs($gene_list_to_search);
		
		if ($missing_genes === false) {
			gbs_query_page_redirect("cant_determine_missing_genes");
		}
		
		// If missing genes were found
		if (count($missing_genes) > 0) {
			// Save any genes that were not found to a string to display to the user
			log_results_page_error("Some genes do not have coordinates stored in the GBS for block searching, no blocks will be returned for these genes: ".implode(", ", $missing_genes)."\n");
		
			// Subtract the missing genes from the genes to search
			foreach ($gene_list_to_search as $gene) {
				// If the gene is not in the GBS
				if (array_search($gene, $missing_genes) !== false) { // array_search returns an ID which can evaluate to true/false, the function will return false when it doesn't find a match, check this with === or !==
					// Delete it from the gene list to search
					unset($gene_list_to_search[array_search($gene, $gene_list_to_search)]);
				}
			}
			
			// Reindex the array
			$gene_list_to_search = array_values($gene_list_to_search);
			
			// Check that genes are remaining to search
			if (count($gene_list_to_search) == 0) {
				gbs_query_page_redirect("no_genes_remaining_to_query");
			}
		}
				
		// Run the analysis for blocks
		$gene_lists_blocks_output = analysis_type_gene_lists_gbs($gene_list_to_search, $samples_to_query);
		
		if ($gene_lists_blocks_output === false) {
			gbs_query_page_redirect("cant_fetch_gene_lists_blocks");
		}
		
		log_website_event("GBS query for gene lists, samples '".implode(", ", $samples_to_query)."', gene(s) to search '".implode(", ", $gene_list_to_search)."'");
		
		output_results_to_tsv_and_redirect($gene_lists_blocks_output);
		
	#############################################
	# ANALYSIS TYPE GENOMIC COORDINATES
	#############################################
	
	} elseif ($_SESSION["gbs_analysis_type"] == "genomic_coordinates") {
		// Check for submitted regions
		if (!isset($_POST["regions"]) || $_POST["regions"] == "") {
			gbs_query_page_redirect("no_regions_submitted");
		}
		
		// Parse the submitted regions for correct formatting
		$parse_regions_result = parse_submitted_regions(htmlspecialchars($_POST["regions"], ENT_QUOTES, 'UTF-8'), "gbs_regions");
		
		if ($parse_regions_result !== true) {
			gbs_query_page_redirect("invalid_search_regions");
		}
		
		$genomic_coordinates_blocks_output = analysis_type_genomic_coordinates_gbs($samples_to_query);
		
		if ($genomic_coordinates_blocks_output === false) {
			gbs_query_page_redirect("cant_fetch_genomic_coordinates_blocks");
		}
		
		log_website_event("GBS query for genomic coordinates, samples '".implode(", ", $samples_to_query)."', regions to search '".$_POST["regions"]."'");
		
		output_results_to_tsv_and_redirect($genomic_coordinates_blocks_output);
	}

	#############################################
		
	// Redirect to the query page for db query
	gbs_query_page_redirect();
						
	#############################################
	# PAGE FUNCTIONS
	#############################################
	
	// Function to redirect to query page
	function gbs_query_page_redirect($session_variable_name = NULL) {
		if (isset($session_variable_name)) {
			$_SESSION["gbs_query_".$session_variable_name] = 1;
		}
		
		// If a non-fatal error occurred, clear it as a fatal error occurred for this function to be called 
		unset($_SESSION["gbs_query_error"]);

		header("Location: ".basename("..")."/gbs_query");
			
		exit;
	}
	
	// Function to log query error without a redirect to the results page
	function log_results_page_error($error_description) {
		if (isset($_SESSION["gbs_query_error"])) {
			$_SESSION["gbs_query_error"] .= $error_description;
		} else {
			$_SESSION["gbs_query_error"] = $error_description;
		}
	}
	
	// Function to output any non-fatal errors to an err file
	function output_errors_to_err_file($output_filename) {
		if (isset($_SESSION["gbs_query_error"])) {
			$error_output_full_path = basename("..")."/temp/".$output_filename.".err"; // Save the full path where the output file will be for opening it
			
			$output = fopen($error_output_full_path, "w") or gbs_query_page_redirect("cant_create_output_file");
		    
			fwrite($output, $_SESSION["gbs_query_error"]);
		    
		    fclose($output);
		    
		    unset($_SESSION["gbs_query_error"]);
		}
	}
	
	// Function to take an array of output line strings and write them to a results file and then redirect to it
	function output_results_to_tsv_and_redirect($output_lines_array) {
		date_default_timezone_set('Australia/Sydney');
		
		$output_filename = date("Y_m_d_H_i_s",time())."_".rand_string("5"); // Generate an output filename with the current time and a random string for uniqueness/security
        $output_full_path = basename("..")."/temp/".$output_filename.".tsv"; // Save the full path where the output file will be for opening it
        
        $output = fopen($output_full_path, "w") or gbs_query_page_redirect("cant_create_output_file");
        
        // Output all lines to the file
        foreach ($output_lines_array as $row) {
			fwrite($output, $row."\n");
		}
	    
	    fclose($output);
	    
	    output_errors_to_err_file($output_filename);
	    
	    header("Location: ".basename("..")."/gbs_results?query=".$output_filename);
			
		exit;
	}

?>