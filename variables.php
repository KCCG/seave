<?php
	
#############################################
# PROCESS THE CONFIGURATION FILE
#############################################

$ini_filename = "config.ini";

// Check whether the config file is in the current directory
if (!file_exists($ini_filename)) {
	$ini_filename = basename("..")."/config.ini";
}

// Check whether the config file is in a directory up
if (!file_exists($ini_filename)) {
	echo "No configuration file found.";
	
	exit;
}

// Parse the configuration file into a multi-dimensional array
$GLOBALS["configuration_file"] = parse_ini_file($ini_filename, TRUE); // The second parameter is for processing sections in the INI file as dimensions in the array

// Make sure there the configuration file isn't empty (lacking in sections)
if (count($GLOBALS["configuration_file"]) == 0) {
	echo "Empty Seave configuration file.";
	
	exit;
}

// Make sure all expected parameters are present
if (!isset($GLOBALS["configuration_file"]["version"]["name"], $GLOBALS["configuration_file"]["gemini"]["db_dir"], $GLOBALS["configuration_file"]["gemini"]["binary"], $GLOBALS["configuration_file"]["bedtools"]["binary"], $GLOBALS["configuration_file"]["mysql"]["server"], $GLOBALS["configuration_file"]["mysql"]["username"], $GLOBALS["configuration_file"]["mysql"]["password"], $GLOBALS["configuration_file"]["dx_import"]["gbs_import_token"], $GLOBALS["configuration_file"]["dx_import"]["gemini_db_import_token"])) {
	echo "Missing Seave configuration parameters.";
	
	exit;
}

#############################################
# VARIABLES USED THROUGHOUT THE WEBSITE
#############################################
	
$javascripts = ""; // Variable containing page-specific javascript script to be included in the footer

#############################################
# DEFAULT GEMINI COLUMNS TO DISPLAY IN THE RESULTS TABLE
#############################################

$GLOBALS['default_columns'] = array();
array_push($GLOBALS['default_columns'], "Variant", "Type", "Gene", "Impact", "Quality", "MGRB AF", "Impact Summary"); # The default Gemini query columns to return

#############################################
# DEFAULT dbNSFP COLUMNS TO FETCH
#############################################

$GLOBALS['default_dbnsfp_columns'] = array();
array_push($GLOBALS['default_dbnsfp_columns'], "chr", "pos(1-based)", "ref", "alt", "PROVEAN_score", "PROVEAN_pred", "FATHMM_score", "FATHMM_rankscore", "FATHMM_pred", "MetaLR_score", "MetaLR_rankscore", "MetaLR_pred", "MetaSVM_score", "MetaSVM_rankscore", "MetaSVM_pred", "GERP++_NR", "Uniprot_acc", "Uniprot_id", "Uniprot_aapos"); # The default dbNSFP query columns to return

#############################################
# DEFAULT RVIS COLUMNS TO FETCH
#############################################

$GLOBALS['default_rvis_columns'] = array();
array_push($GLOBALS['default_rvis_columns'], "gene", "percentile");

#############################################
# DEFAULT ClinVar COLUMNS TO FETCH
#############################################

$GLOBALS['default_clinvar_columns'] = array();
array_push($GLOBALS['default_clinvar_columns'], "chr", "position", "ref", "alt", "clinvar_rs", "clinsig", "clintrait");

#############################################
# DEFAULT COSMIC COLUMNS TO FETCH
#############################################

$GLOBALS['default_cosmic_columns'] = array();
array_push($GLOBALS['default_cosmic_columns'], "chr", "pos", "ref", "alt", "cosmic_number", "cosmic_count", "cosmic_primary_site", "cosmic_primary_histology");

#############################################
# DEFAULT VarpipeSV COLUMNS TO SAVE
#############################################

$GLOBALS['default_varpipesv_columns'] = array();
array_push($GLOBALS['default_varpipesv_columns'], "GT", "FT", "RARE", "SU", "SUF", "PE", "SR", "DRF", "DRA", "DRAN", "DRAP", "QBP", "CNEUTR", "TOOL", "KDBC", "VAF1KG", "GC", "CR", "AMQ", "SEGD", "HR", "HC");

#############################################
# DEFAULT Manta COLUMNS TO SAVE
#############################################

$GLOBALS['default_manta_columns'] = array();
array_push($GLOBALS['default_manta_columns'], "SR", "PR", "GSR", "GPR", "FILTER", "IMPRECISE", "HOMLEN", "HOMSEQ", "SVINSLEN", "SVINSSEQ", "LEFT_SVINSSEQ", "RIGHT_SVINSSEQ", "BND_DEPTH", "SOMATICSCORE", "JUNCTION_SOMATICSCORE");

#############################################
# DEFAULT LUMPY COLUMNS TO SAVE
#############################################

$GLOBALS['default_lumpy_columns'] = array();
array_push($GLOBALS['default_lumpy_columns'], "IMPRECISE", "SU", "PE", "SR", "EV", "DRF", "DBP", "QBP", "ICN", "FT");

#############################################
# DEFAULT ROHmer COLUMNS TO SAVE
#############################################

$GLOBALS['default_rohmer_columns'] = array();
array_push($GLOBALS['default_rohmer_columns'], "HET_freq");

#############################################
# DEFAULT CNVkit COLUMNS TO SAVE
#############################################

$GLOBALS['default_cnvkit_columns'] = array();
array_push($GLOBALS['default_cnvkit_columns'], "depth", "probes", "weight");

#############################################
# DEFAULT Sequenza COLUMNS TO SAVE
#############################################

$GLOBALS['default_sequenza_columns'] = array();
array_push($GLOBALS['default_sequenza_columns'], "CN.A", "CN.B", "AF.B", "DP.ratio");

#############################################
# DEFAULT PURPLE COLUMNS TO SAVE
#############################################

$GLOBALS['default_purple_columns'] = array();
array_push($GLOBALS['default_purple_columns'], "bafCount", "observedBAF", "actualBAF", "segmentStartSupport", "segmentEndSupport", "method");

#############################################
# WHITELISTED GBS RESULTS PAGE COLUMNS
#############################################

$GLOBALS['whitelisted_gbs_results_columns'] = array();

array_push($GLOBALS['whitelisted_gbs_results_columns'], "FILTER", "VCF Filter", "Link Type", "Sample(s)", "Sample", "Samples", "Event Type", "Block Size (bp)", "Copy Number", "Method", "Methods", "Gene(s)", "Block1 Coordinates", "Block2 Coordinates", "Block Coordinates", "Overlapping Coordinates", "Overlap Size (bp)", "Method 1 Block(s)", "Method 2 Block(s)", "ROH Coordinates", "Gene");

#############################################
# DISALLOWED GEMINI DB VERSIONS
#############################################

$GLOBALS['disallowed_db_versions'] = array(); // An array of database versions that are not allowed in Sieve (not compatible with the version of Gemini the server is running)
array_push($GLOBALS['disallowed_db_versions'], "0.10", "0.09", "0.08", "0.07", "0.06", "0.05", "0.04", "0.03", "0.02", "0.01");

?>