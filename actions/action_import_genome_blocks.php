<?php

	require basename("..").'/php_header.php'; // Require the PHP header housing required PHP functions
	
	#############################################
	# CHECK POST/SESSION VARIABLES
	#############################################
	
	// If the user is not logged in as an administrator
	if (!is_user_administrator()) {
		gbs_administration_page_redirect();
	}
	
	// If the all of the variables in the form were not submitted
	if (!isset($_POST["method"], $_FILES["genomeblocks"])) {
		gbs_administration_page_redirect("missing_posts");
	}
	
	// Make sure the file to import is not empty
	if ($_FILES["genomeblocks"]["name"] == "") {
		gbs_administration_page_redirect("missing_file");
	}
	
	// Make sure import data doesn't already exist
	if (isset($_SESSION["gbs_import_genome_blocks"]) || isset($_SESSION["gbs_import_samples"]) || isset($_SESSION["gbs_import_method"])) {
		gbs_administration_page_redirect("import_data_already_exists");
	}
	
	#############################################
	# CNVnator INPUT FILE
	#############################################

	if ($_POST["method"] == "CNVnator") {
		// If no sample name was submitted
		if (!isset($_POST["import_sample_cnvnator"])) {
			gbs_administration_page_redirect("missing_posts");
		}
		
		// If no sample name was entered
		if (strlen($_POST["import_sample_cnvnator"]) == 0) {
			gbs_administration_page_redirect("missing_sample_name");
		}
		
		// Check whether there is already data for the sample and method in the GBS and fail if so		
		$already_in_gbs = is_sample_and_software_in_gbs($_POST["import_sample_cnvnator"], $_POST["method"]);
		
		if ($already_in_gbs === true) {
			gbs_administration_page_redirect("gbs_data_already_exists");
		} elseif ($already_in_gbs === false) {
			gbs_administration_page_redirect("cant_tell_if_gbs_data_already_exists");
		}
		
		// Check file extension - only allow BED format from CNVnator
		if (!preg_match("/.bed$/", $_FILES["genomeblocks"]["name"])) {
			gbs_administration_page_redirect("invalid_cnvnator_output_file_format");
		}

		// Open the genome blocks file for parsing
		$genome_blocks_file = fopen($_FILES['genomeblocks']['tmp_name'], "r");
		
		// If the genome blocks file couldn't be opened
		if ($genome_blocks_file === false) {
			gbs_administration_page_redirect("cant_open_genome_blocks_file");
		}
		
		// Parse the data file and save the blocks
		$genome_block_store = gbs_import_cnvnator($genome_blocks_file, $_POST["import_sample_cnvnator"]);
		
		// If there was a failure parsing
		if ($genome_block_store === false) {
			gbs_administration_page_redirect();
		}
		
		// If no blocks were found
		if (count($genome_block_store) == 0) {
			gbs_administration_page_redirect("no_valid_data");
		}
		
		// Save the genome blocks
		log_gbs_import_info("genome_blocks", $genome_block_store);
		
		// Save the sample name
		log_gbs_import_info("samples", array($_POST["import_sample_cnvnator"]));
		
		// Save the method
		log_gbs_import_info("method", $_POST["method"]);
		
		gbs_administration_page_redirect();
		
	#############################################
	# ROHmer INPUT FILE
	#############################################

	} elseif ($_POST["method"] == "ROHmer") {
		// If no sample name was submitted
		if (!isset($_POST["import_sample_rohmer"])) {
			gbs_administration_page_redirect("missing_posts");
		}
		
		// If no sample name was entered
		if (strlen($_POST["import_sample_rohmer"]) == 0) {
			gbs_administration_page_redirect("missing_sample_name");
		}
		
		// Check whether there is already data for the sample and method in the GBS and fail if so		
		$already_in_gbs = is_sample_and_software_in_gbs($_POST["import_sample_rohmer"], $_POST["method"]);
		
		if ($already_in_gbs === true) {
			gbs_administration_page_redirect("gbs_data_already_exists");
		} elseif ($already_in_gbs === false) {
			gbs_administration_page_redirect("cant_tell_if_gbs_data_already_exists");
		}
		
		// Check file extension - only allow BED format from ROHmer
		if (!preg_match("/.bed$/", $_FILES["genomeblocks"]["name"])) {
			gbs_administration_page_redirect("invalid_rohmer_output_file_format");
		}

		// Open the genome blocks file for parsing
		$genome_blocks_file = fopen($_FILES['genomeblocks']['tmp_name'], "r");
		
		// If the genome blocks file couldn't be opened
		if ($genome_blocks_file === false) {
			gbs_administration_page_redirect("cant_open_genome_blocks_file");
		}
		
		// Parse the data file and save the blocks
		$genome_block_store = gbs_import_rohmer($genome_blocks_file, $_POST["import_sample_rohmer"]);
		
		// If there was a failure parsing
		if ($genome_block_store === false) {
			gbs_administration_page_redirect();
		}
		
		// If no blocks were found
		if (count($genome_block_store) == 0) {
			gbs_administration_page_redirect("no_valid_data");
		}
		
		// Save the genome blocks
		log_gbs_import_info("genome_blocks", $genome_block_store);
		
		// Save the the unique annotation tags
		log_gbs_import_info("unique_annotation_tags", $GLOBALS['default_rohmer_columns']);
		
		// Save the sample name
		log_gbs_import_info("samples", array($_POST["import_sample_rohmer"]));
		
		// Save the method
		log_gbs_import_info("method", $_POST["method"]);
		
		gbs_administration_page_redirect();
	
	#############################################
	# CNVkit INPUT FILE
	#############################################

	} elseif ($_POST["method"] == "CNVkit") {
		// If no sample name was submitted
		if (!isset($_POST["import_sample_cnvkit"])) {
			gbs_administration_page_redirect("missing_posts");
		}
		
		// If no sample name was entered
		if (strlen($_POST["import_sample_cnvkit"]) == 0) {
			gbs_administration_page_redirect("missing_sample_name");
		}
		
		// Check whether there is already data for the sample and method in the GBS and fail if so		
		$already_in_gbs = is_sample_and_software_in_gbs($_POST["import_sample_cnvkit"], $_POST["method"]);
		
		if ($already_in_gbs === true) {
			gbs_administration_page_redirect("gbs_data_already_exists");
		} elseif ($already_in_gbs === false) {
			gbs_administration_page_redirect("cant_tell_if_gbs_data_already_exists");
		}
		
		// Check file extension
		if (!preg_match("/.cns$/", $_FILES["genomeblocks"]["name"])) {
			gbs_administration_page_redirect("invalid_cnvkit_output_file_format");
		}

		// Open the genome blocks file for parsing
		$genome_blocks_file = fopen($_FILES['genomeblocks']['tmp_name'], "r");
		
		// If the genome blocks file couldn't be opened
		if ($genome_blocks_file === false) {
			gbs_administration_page_redirect("cant_open_genome_blocks_file");
		}
		
		// Parse the data file and save the blocks
		$genome_block_store = gbs_import_cnvkit($genome_blocks_file, $_POST["import_sample_cnvkit"]);
		
		// If there was a failure parsing
		if ($genome_block_store === false) {
			gbs_administration_page_redirect();
		}
		
		// If no blocks were found
		if (count($genome_block_store) == 0) {
			gbs_administration_page_redirect("no_valid_data");
		}
		
		// Save the genome blocks
		log_gbs_import_info("genome_blocks", $genome_block_store);
		
		// Save the the unique annotation tags
		log_gbs_import_info("unique_annotation_tags", $GLOBALS['default_cnvkit_columns']);
		
		// Save the sample name
		log_gbs_import_info("samples", array($_POST["import_sample_cnvkit"]));
		
		// Save the method
		log_gbs_import_info("method", $_POST["method"]);
		
		gbs_administration_page_redirect();
		
	#############################################
	# Sequenza INPUT FILE
	#############################################

	} elseif ($_POST["method"] == "Sequenza") {
		// If no sample name was submitted
		if (!isset($_POST["import_sample_sequenza"])) {
			gbs_administration_page_redirect("missing_posts");
		}
		
		// If no sample name was entered
		if (strlen($_POST["import_sample_sequenza"]) == 0) {
			gbs_administration_page_redirect("missing_sample_name");
		}
		
		// Check whether there is already data for the sample and method in the GBS and fail if so		
		$already_in_gbs = is_sample_and_software_in_gbs($_POST["import_sample_sequenza"], $_POST["method"]);
		
		if ($already_in_gbs === true) {
			gbs_administration_page_redirect("gbs_data_already_exists");
		} elseif ($already_in_gbs === false) {
			gbs_administration_page_redirect("cant_tell_if_gbs_data_already_exists");
		}
		
		// Check file extension - only allow "*segments.txt" format from Sequenza
		if (!preg_match("/segments.txt$/", $_FILES["genomeblocks"]["name"])) {
			gbs_administration_page_redirect("invalid_sequenza_output_file_format");
		}
		
		// Open the genome blocks file for parsing
		$genome_blocks_file = fopen($_FILES['genomeblocks']['tmp_name'], "r");
		
		// If the genome blocks file couldn't be opened
		if ($genome_blocks_file === false) {
			gbs_administration_page_redirect("cant_open_genome_blocks_file");
		}
		
		// Parse the data file and save the blocks
		$genome_block_store = gbs_import_sequenza($genome_blocks_file, $_POST["import_sample_sequenza"]);
		
		// If there was a failure parsing
		if ($genome_block_store === false) {
			gbs_administration_page_redirect();
		}
		
		// If no blocks were found
		if (count($genome_block_store) == 0) {
			gbs_administration_page_redirect("no_valid_data");
		}
		
		// Save the genome blocks
		log_gbs_import_info("genome_blocks", $genome_block_store);
		
		// Save the the unique annotation tags
		log_gbs_import_info("unique_annotation_tags", $GLOBALS['default_sequenza_columns']);
		
		// Save the sample name
		log_gbs_import_info("samples", array($_POST["import_sample_sequenza"]));
		
		// Save the method
		log_gbs_import_info("method", $_POST["method"]);
		
		gbs_administration_page_redirect();

	#############################################
	# VarpipeSV INPUT FILE
	#############################################

	} elseif ($_POST["method"] == "VarpipeSV") {
		// Check file extension - only allow VCF format from VarpipeSV
		if (!preg_match("/.vcf$/", $_FILES["genomeblocks"]["name"])) {
			gbs_administration_page_redirect("invalid_varpipesv_output_file_format");
		}
		
		// Open the genome blocks file for parsing
		$genome_blocks_file = fopen($_FILES['genomeblocks']['tmp_name'], "r");
		
		// If the genome blocks file couldn't be opened
		if ($genome_blocks_file === false) {
			gbs_administration_page_redirect("cant_open_genome_blocks_file");
		}
		
		// Extract the samples from the input file
		$samples = gbs_parse_for_samples($genome_blocks_file);
			
		if ($samples === false || count($samples) == 0) {
			gbs_administration_page_redirect("problem_parsing_samples");
		}
		
		// Check whether any of the samples are already in the GBS
		foreach ($samples as $sample) {
			$already_in_gbs = is_sample_and_software_in_gbs($sample, $_POST["method"]);
		
			if ($already_in_gbs === true) {
				gbs_administration_page_redirect("gbs_data_already_exists");
			} elseif ($already_in_gbs === false) {
				gbs_administration_page_redirect("cant_tell_if_gbs_data_already_exists");
			}
		}
		
		// Parse the data file and save the blocks
		list($genome_block_store, $block_links, $unique_annotation_tags) = gbs_import_varpipesv($genome_blocks_file, $samples, "web");
		
		// If there was a failure parsing
		if (!isset($genome_block_store)) {
			gbs_administration_page_redirect("problem_parsing");
		}
		
		#############################################
		# Make sure at least one block was found
		#############################################
		
		// If no blocks were found
		if (count($genome_block_store) == 0) {
			gbs_administration_page_redirect("no_valid_data");
		}
		
		#############################################
		# Save the data to show to the user and redirect
		#############################################
		
		// Save the genome blocks
		log_gbs_import_info("genome_blocks", $genome_block_store);
		
		// Save the block links
		log_gbs_import_info("block_links", $block_links);
		
		// Save the the unique annotation tags
		log_gbs_import_info("unique_annotation_tags", $unique_annotation_tags);
		
		// Save the sample names
		log_gbs_import_info("samples", $samples);
		
		// Save the method
		log_gbs_import_info("method", $_POST["method"]);
		
		gbs_administration_page_redirect();
	
	#############################################
	# Manta INPUT FILE
	#############################################

	} elseif ($_POST["method"] == "Manta") {
		// Check file extension - only allow *somaticSV.vcf format from Manta
		if (!preg_match("/somaticSV.vcf$/", $_FILES["genomeblocks"]["name"])) {
			gbs_administration_page_redirect("invalid_manta_output_file_format");
		}
		
		// Open the genome blocks file for parsing
		$genome_blocks_file = fopen($_FILES['genomeblocks']['tmp_name'], "r");
		
		// If the genome blocks file couldn't be opened
		if ($genome_blocks_file === false) {
			gbs_administration_page_redirect("cant_open_genome_blocks_file");
		}
		
		// Extract the samples from the input file
		$samples = gbs_parse_for_samples($genome_blocks_file);
			
		if ($samples === false || count($samples) == 0) {
			gbs_administration_page_redirect("problem_parsing_samples");
		}
		
		// Check whether any of the samples are already in the GBS
		foreach ($samples as $sample) {
			$already_in_gbs = is_sample_and_software_in_gbs($sample, $_POST["method"]);
		
			if ($already_in_gbs === true) {
				gbs_administration_page_redirect("gbs_data_already_exists");
			} elseif ($already_in_gbs === false) {
				gbs_administration_page_redirect("cant_tell_if_gbs_data_already_exists");
			}
		}
		
		// Parse the data file and save the blocks
		list($genome_block_store, $block_links, $unique_annotation_tags) = gbs_import_manta($genome_blocks_file, $samples, "web");
		
		// If there was a failure parsing
		if (!isset($genome_block_store)) {
			gbs_administration_page_redirect("problem_parsing");
		}
		
		#############################################
		# Make sure at least one block was found
		#############################################
		
		// If no blocks were found
		if (count($genome_block_store) == 0) {
			gbs_administration_page_redirect("no_valid_data");
		}
		
		#############################################
		# Save the data to show to the user and redirect
		#############################################
		
		// Save the genome blocks
		log_gbs_import_info("genome_blocks", $genome_block_store);
		
		// Save the block links
		log_gbs_import_info("block_links", $block_links);
		
		// Save the the unique annotation tags
		log_gbs_import_info("unique_annotation_tags", $unique_annotation_tags);
		
		// Save the sample names
		log_gbs_import_info("samples", $samples);
		
		// Save the method
		log_gbs_import_info("method", $_POST["method"]);
		
		gbs_administration_page_redirect();
			
	#############################################
	# LUMPY INPUT FILE
	#############################################

	} elseif ($_POST["method"] == "LUMPY") {
		// Check file extension - only allow VCF format from lumpy
		if (!preg_match("/.vcf$/", $_FILES["genomeblocks"]["name"])) {
			gbs_administration_page_redirect("invalid_lumpy_output_file_format");
		}
		
		// Open the genome blocks file for parsing
		$genome_blocks_file = fopen($_FILES['genomeblocks']['tmp_name'], "r");
		
		// If the genome blocks file couldn't be opened
		if ($genome_blocks_file === false) {
			gbs_administration_page_redirect("cant_open_genome_blocks_file");
		}
		
		// Extract the samples from the input file
		$samples = gbs_parse_for_samples($genome_blocks_file);
		
		if ($samples === false || count($samples) == 0) {
			gbs_administration_page_redirect("problem_parsing_samples");
		}
		
		// Check whether any of the samples are already in the GBS
		foreach ($samples as $sample) {
			$already_in_gbs = is_sample_and_software_in_gbs($sample, $_POST["method"]);
		
			if ($already_in_gbs === true) {
				gbs_administration_page_redirect("gbs_data_already_exists");
			} elseif ($already_in_gbs === false) {
				gbs_administration_page_redirect("cant_tell_if_gbs_data_already_exists");
			}
		}
		
		// Parse the data file and save the blocks
		list($genome_block_store, $block_links, $unique_annotation_tags) = gbs_import_lumpy($genome_blocks_file, $samples, "web");
		
		// If there was a failure parsing
		if (!isset($genome_block_store)) {
			gbs_administration_page_redirect("problem_parsing");
		}
		
		// If no blocks were found
		if (count($genome_block_store) == 0) {
			gbs_administration_page_redirect("no_valid_data");
		}
		
		// Save the genome blocks
		log_gbs_import_info("genome_blocks", $genome_block_store);
		
		// Save the block links
		log_gbs_import_info("block_links", $block_links);
		
		// Save the the unique annotation tags
		log_gbs_import_info("unique_annotation_tags", $unique_annotation_tags);
		
		// Save the sample name
		log_gbs_import_info("samples", $samples);
		
		// Save the method
		log_gbs_import_info("method", $_POST["method"]);
		
		gbs_administration_page_redirect();
	}

	#############################################
	
	// Redirect back to the import page if no other redirect has been applied
	gbs_administration_page_redirect();
						
	#############################################
	# PAGE FUNCTIONS
	#############################################
	
	// Function to redirect to the GBS administration page
	function gbs_administration_page_redirect($session_variable_name = NULL) {
		if (isset($session_variable_name)) {
			$_SESSION["gbs_import_".$session_variable_name] = 1;
		}

		header("Location: ".basename("..")."/gbs_administration");
			
		exit;
	}
	
?>