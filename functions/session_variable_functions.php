<?php
	
# Functions exclusively used to manipulate session variables

# Wipes all sessions variables to either create a new session or wipe an existing one
function clean_session($logout = NULL) {
	// If the login status is already set don't reset it unless a logout flag saying "logout" has been specified
	if (!isset($_SESSION["logged_in"]) || (isset($logout) && $logout == "logout")) {
		$_SESSION["logged_in"] = array();
		$_SESSION["logged_in"]["email"] = "";
		$_SESSION["logged_in"]["user_id"] = "";
		$_SESSION["logged_in"]["is_administrator"] = "";
	}
	
	$_SESSION["query_group"] = "";
	$_SESSION["query_db"] = "";
	$_SESSION["hasped"] = "";
	
	// GBS
	$_SESSION["gbs_family"] = "";
	$_SESSION["gbs_analysis_type"] = "";
	$_SESSION["gbs_regions"] = ""; // For the genomic coordinates analysis type
	$_SESSION["gbs_gene_list_selection"] = ""; // For the gene list(s) analysis type
	$_SESSION["gbs_gene_list"] = ""; // For the gene list(s) analysis type
	$_SESSION["gbs_svfusions_gene_list_selection"] = ""; // For the SV Fusions analysis type
	$_SESSION["gbs_svfusions_gene_list"] = "";// For the SV Fusions analysis type
	
	// Regions for inclusion/exclusion manually entered
	$_SESSION["regions"] = "";
	$_SESSION["exclude_regions"] = "";
	
	// Gene lists for inclusion/exclusion manually entered
	$_SESSION["gene_list"] = "";
	$_SESSION["exclusion_gene_list"] = "";
	
	$_SESSION["gene_list_to_search"] = "";
	$_SESSION["gene_list_to_exclude"] = "";
	
	// Gene lists selected for inclusion/exclusion from the multi-select box
	$_SESSION["gene_list_selection"] = "";
	$_SESSION["gene_list_exclusion_selection"] = "";
	
	// Custom dbsnp columns
	$_SESSION["dbsnp_columns_exist"] = ""; // For whether the custom dbsnp columns exist
	$_SESSION["exclude_dbsnp_common"] = ""; // For storing the checkbox value of the exclude dbsnp common column (on by default)
	$_SESSION["exclude_dbsnp_flagged"] = ""; // For storing the checkbox value of the exclude dbsnp flagged column
	
	$_SESSION["search_impact"] = "";
	$_SESSION["min_seq_depth"] = "";
	$_SESSION["min_qual"] = "";
	$_SESSION["min_cadd"] = "";
	$_SESSION["return_num_variants"] = "";
	$_SESSION["search_variant_type"] = "";
	$_SESSION["1000gmaf"] = "";
	$_SESSION["espmaf"] = "";
	$_SESSION["exacmaf"] = "";
	$_SESSION["analysis_type"] = "";
	$_SESSION["family"] = "";
	$_SESSION["return_information_for"] = ""; // The "Return information for:" option on the family analysis page
	$_SESSION["exclude_failed_variants"] = ""; // The exclude failed variants button
}

# Checks whether a session exists and creates one if not
function renew_session() {
	if (!isset($_SESSION["query_db"])) { # If the query_db variable isn't defined (the main one) then a session doesn't exist
		clean_session();
	}
}

# Update the query database for the current session
function session_update_db($group, $db, $hasped) {
	clean_session();
	
	$_SESSION["query_group"] = htmlspecialchars($group, ENT_QUOTES, 'UTF-8');
	$_SESSION["query_db"] = htmlspecialchars($db, ENT_QUOTES, 'UTF-8');
	$_SESSION["hasped"] = htmlspecialchars($hasped, ENT_QUOTES, 'UTF-8');
}

?>