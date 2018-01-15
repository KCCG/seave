<?php
	
	require basename("..").'/php_header.php'; // Require the PHP header housing required PHP functions
	
	#############################################
	# CHECK GET/SESSION VARIABLES
	#############################################
	
	// If the user is not logged in as an administrator
	if (!is_user_administrator()) {
		gbs_administration_page_redirect();
	}
	
	// If no GET was supplied
	if (!isset($_GET["confirm"])) {
		gbs_administration_page_redirect();
	}
	
	// If genomic blocks have not been imported
	if (!isset($_SESSION["gbs_import_genome_blocks"]) || !isset($_SESSION["gbs_import_samples"]) || !isset($_SESSION["gbs_import_method"])) {
		gbs_administration_page_redirect("no_genomic_blocks");
	}
		
	#############################################
	# IF THE STORED GENOMIC BLOCKS WERE CONFIRMED BY THE USER AS INCORRECT
	#############################################
	
	if ($_GET["confirm"] == "false") {
		// The redirect automatically deletes all stored genomic block data
		gbs_administration_page_redirect();
		
	#############################################
	# IF THE STORED GENOMIC BLOCKS HAVE BEEN CONFIRMED BY THE USER AS CORRECT
	#############################################

	} elseif ($_GET["confirm"] == "true") {
		// Go through every sample in the data
		foreach ($_SESSION["gbs_import_samples"] as $sample) {
			// Add the sample name to the database if it is not already present
			if (add_sample_to_gbs($sample) === false) {
				gbs_administration_page_redirect("cant_add_sample");
			}
		}
		
		#############################################
		
		// Add any chromosomes to the GBS that aren't already in there
		if (!gbs_add_chromosomes($_SESSION["gbs_import_genome_blocks"])) {
			gbs_administration_page_redirect("cant_add_chromosome");
		}
		
		#############################################
		
		// If annotation tags are present for the data
		if (isset($_SESSION["gbs_import_unique_annotation_tags"])) {
			// Add the annotation tag(s) to the database if they have not been seen before
			if (add_annotation_tags_to_gbs($_SESSION["gbs_import_unique_annotation_tags"]) === false) {
				gbs_administration_page_redirect("cant_add_annotation_tag");
			}
		}
		
		#############################################
		
		if (isset($_SESSION["gbs_import_block_links"])) {
			$gbs_store_result = gbs_store_blocks($_SESSION["gbs_import_genome_blocks"], $_SESSION["gbs_import_method"], $_SESSION["gbs_import_block_links"]);
		} else {
			$gbs_store_result = gbs_store_blocks($_SESSION["gbs_import_genome_blocks"], $_SESSION["gbs_import_method"], array());
		}
		
		if ($gbs_store_result === false) {
			// Go through each sample and delete imported blocks
			gbs_failed_import_roll_back($_SESSION["gbs_import_samples"], $_SESSION["gbs_import_method"]);
				
			gbs_administration_page_redirect("problem_adding_block");
		}
		
		#############################################
		
		log_website_event("Manually imported GBS blocks for sample(s) '".implode(", ", $_SESSION["gbs_import_samples"])."' and method '".$_SESSION["gbs_import_method"]."'");
		
		gbs_administration_page_redirect("success");
	} else {
		gbs_administration_page_redirect();
	}
	
	// Redirect back to the import page if no other redirect has been applied
	gbs_administration_page_redirect();
	
	#############################################
	# PAGE FUNCTIONS
	#############################################
	
	// Function to redirect to the GBS administration page
	function gbs_administration_page_redirect($session_variable_name = NULL) {
		// If there is genomic block data saved in session variables, delete it
		unset($_SESSION["gbs_import_genome_blocks"]);
		unset($_SESSION["gbs_import_samples"]);
		unset($_SESSION["gbs_import_unique_annotation_tags"]);
		unset($_SESSION["gbs_import_method"]);
		unset($_SESSION["gbs_import_block_links"]);
		
		if (isset($session_variable_name)) {
			$_SESSION["gbs_store_".$session_variable_name] = 1;
		}

		header("Location: ".basename("..")."/gbs_administration");
			
		exit;
	}
	
?>