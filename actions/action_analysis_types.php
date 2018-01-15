<?php

	require basename("..").'/php_header.php'; // Require the PHP header housing required PHP functions

	#############################################
	# SET UP SESSIONS
	#############################################	
	
	// If a query database was selected on the database page and it doesn't equal the previously queried database
	if (isset($_GET["group"], $_GET["query_db"], $_GET["hasped"]) && ($_SESSION["query_group"] != $_GET["group"] || $_SESSION["query_db"] != $_GET["query_db"])) {
		$escaped_group = htmlspecialchars($_GET["group"], ENT_QUOTES, 'UTF-8');
		$escaped_query_db = htmlspecialchars($_GET["query_db"], ENT_QUOTES, 'UTF-8');
		$escaped_hasped = htmlspecialchars($_GET["hasped"], ENT_QUOTES, 'UTF-8');
		
		// Make sure the user is in the group specified
		$user_in_group = is_user_in_group($_SESSION["logged_in"]["email"], $escaped_group);
		
		// If the user is in the group, clear all session variables then set the query group, database and hasped status to the one selected on the database page
		if ($user_in_group === true) {
			session_update_db($escaped_group, $escaped_query_db, $escaped_hasped);
		} else {
			query_page_redirect("no_access_to_group");
		}
	}
	
	#############################################
	# CAPTURE THE ANALYSIS TYPE, FAMILY NAME AND INFORMATION TO SHOW
	#############################################
	
	if ($_SESSION["hasped"] == "Yes") { // If the current database has pedigree information, it should have had an analysis type and return information specified
		// If a family and analysis type have been selected
		if (isset($_GET["family"], $_GET[preg_replace("/\s/", "_", $_GET["family"])."analysis_type"])) {
			$_SESSION["analysis_type"] = htmlspecialchars($_GET[preg_replace("/\s/", "_", $_GET["family"])."analysis_type"], ENT_QUOTES, 'UTF-8');
			$_SESSION["family"] = htmlspecialchars($_GET["family"], ENT_QUOTES, 'UTF-8');
			
			// Process the return information section
			if (isset($_GET["return_information"]) && $_GET["return_information"] == "cohort") {
				$_SESSION["return_information_for"] = "cohort";
			} elseif (isset($_GET["return_information"]) && $_GET["return_information"] == "family_only") {
				$_SESSION["return_information_for"] = "family_only";
			} else {
				$_SESSION["return_information_for"] = "";
			}
		} elseif (isset($_GET["family"]) && $_GET["family"] == "entiredatabase") { // If no selection has been made or the entire dataset is to be analysed
			$_SESSION["analysis_type"] = ""; // A blank analysis_type doesn't restrict on anything at all where analysis_none still restricts on the variant being present in at least one of the members of the family
			$_SESSION["family"] = "entiredatabase";
			
			// If no family was selected (i.e. entire database), then ignore what was sent in the return information
			$_SESSION["return_information_for"] = "";
		} else {
			query_page_redirect("no_family_or_analysis_type");
		}
	}
	
	// Redirect to the query page for db query
	query_page_redirect();
						
	#############################################
	# PAGE FUNCTIONS
	#############################################
	
	// Function to redirect to query page
	function query_page_redirect($session_variable_name = NULL) {
		if (isset($session_variable_name)) {
			$_SESSION["query_".$session_variable_name] = 1;
		}

		header("Location: ".basename("..")."/query");
			
		exit;
	} 
?>