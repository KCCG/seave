<?php

	require basename("..").'/php_header.php'; // Require the PHP header housing required PHP functions

	#############################################
	# CHECK THAT THE USER HAS ACCESS
	#############################################
	
	if (!is_user_administrator()) {
		db_admin_redirect("no_access");
	}
	
    #############################################
	# IF A NEW DATABASE HAS BEEN SUBMITTED
	#############################################
	
	if (isset($_POST['db_url'], $_POST['add_db_group'])) { // If a database url was specified to upload to Seave
		// Make sure the URL ends with .db
		if (!preg_match("/.db$/", $_POST['db_url'])) {
			db_admin_redirect("new_db_bad_filename");
		}
		
		// Make sure the URL exists (the server returns a valid HTML code)
		if (!does_url_exist($_POST['db_url'])) {
			db_admin_redirect("new_db_bad_url");
		}
		
		// Make sure the group specified exists in the database
		$group_information = fetch_account_groups();
		
		if ($group_information === false) {
			db_admin_redirect();
		}
		
		$group_found_flag = 0;
		
		foreach (array_keys($group_information) as $group_id) {
			if ($group_information[$group_id]["group_name"] == $_POST['add_db_group']) {
				$group_found_flag = 1;
			}
		}
		
		// If the group wasn't found
		if ($group_found_flag == 0) {
			db_admin_redirect("new_db_group_doesnt_exist");
		}
		
		// Create the group folder locally if it doesn't already exist
		if (!is_dir($GLOBALS["configuration_file"]["gemini"]["db_dir"]."/".$_POST['add_db_group'])) {
			mkdir($GLOBALS["configuration_file"]["gemini"]["db_dir"]."/".$_POST['add_db_group']);
		}
		
		// If the database file doesn't already exist
		if (file_exists($GLOBALS["configuration_file"]["gemini"]["db_dir"]."/".$_POST['add_db_group']."/".basename($_POST['db_url']))) {
			db_admin_redirect("new_db_db_exists");
		}
		
		$download_file_cmd = "wget -O '".$GLOBALS["configuration_file"]["gemini"]["db_dir"]."/".$_POST['add_db_group']."/".basename($_POST['db_url'])."' '".$_POST['db_url']."'";

		exec($download_file_cmd, $download_file_cmd_result, $exit_code);
		
		if ($exit_code == "0") {
			log_website_event("Manually imported database '".$_POST['db_url']."'");
			
			db_admin_redirect("new_db_success", basename($_POST['db_url']));
		} else {
			db_admin_redirect("new_db_download_fail", $_POST['db_url']);
		}
	}
	
	#############################################
	# IF A DATABASE IS TO BE DELETED
	#############################################
	
	if (isset($_POST['delete_db_name'])) { // If a database file was specified to delete
		// Delete the database and associated caches
		if (delete_database($_POST['delete_db_name'])) {
			log_website_event("Deleted database '".$_POST['delete_db_name']."'");
			
			db_admin_redirect("delete_db_success", database_name_from_path($_POST['delete_db_name']));
		} else {
			db_admin_redirect("delete_db_fail", database_name_from_path($_POST['delete_db_name']));
		}
	}
	
	#############################################
	# IF A DATABASE SUMMARY REPORT IS TO BE REGENERATED
	#############################################
	
	if (isset($_POST['regenerate_summary_db_name'])) { // If a database file was specified to regenerate a report for
		// Generate the summary information
		if (generate_db_summary($GLOBALS["configuration_file"]["gemini"]["db_dir"]."/".$_POST['regenerate_summary_db_name'])) {
			log_website_event("Regenerated database summary for '".$_POST['regenerate_summary_db_name']."'");
			
			db_admin_redirect("db_summary_success", database_name_from_path($_POST['regenerate_summary_db_name']));
		} else {
			db_admin_redirect("db_summary_failed", database_name_from_path($_POST['regenerate_summary_db_name']));
		}
	}
	
	#############################################
	# IF A DATABASE IS TO BE ANNOTATED
	#############################################
	
	if (isset($_POST['database_dropdown'], $_FILES['pedfile'])) { // If the database annotation form has been submitted with a database and ped file
		if ($_FILES["pedfile"]["size"] > 1000000) { // Check file size
			db_admin_redirect("db_annotation_large_ped");
		} elseif (!preg_match("/.ped$/", $_FILES["pedfile"]["name"])) { // Check file extension
			db_admin_redirect("db_annotation_no_ped");
		} else {
			exec($GLOBALS["configuration_file"]["gemini"]["binary"].' amend --sample '.$_FILES['pedfile']['tmp_name'].' '.$GLOBALS["configuration_file"]["gemini"]["db_dir"]."/".escape_database_filename($_POST['database_dropdown']), $query_result, $exit_code); # Execute the Gemini query
		
			if ($exit_code == 0) {
				log_website_event("Manually annotated a database with a PED file, database '".$_POST['database_dropdown']."'");
				
				// Also delete the various caches if they exist
				delete_database_cache($GLOBALS["configuration_file"]["gemini"]["db_dir"]."/".$_POST['database_dropdown'], "tsv_only");
				
				db_admin_redirect("db_annotation_success", database_name_from_path($_POST['database_dropdown']));
			} else {
				db_admin_redirect("db_annotation_failed");
			}
		}
	}
	
	#############################################
	# IF A DATABASE IS TO BE RENAMED
	#############################################
	
	if (isset($_POST['database_dropdown'], $_POST['db_new_name'])) { // If the database renaming form has been submitted with a database and new database name
		// Make sure the selected DB exists
		if (!file_exists($GLOBALS["configuration_file"]["gemini"]["db_dir"]."/".$_POST['database_dropdown'])) {
			db_admin_redirect("db_rename_nonexistant_db");
		}
		
		// If the new database name is blank or doesn't end with .db
		if ($_POST['db_new_name'] == "" || substr(strrchr($_POST['db_new_name'], '.'), 1) != "db") {
			db_admin_redirect("db_rename_no_new_db");
		}
		
		// Grab the group name from the database to rename
		preg_match("/(.*)\//", $_POST['database_dropdown'], $matches);
		
		// If the new file already exists at the group path of the current DB selected
		if (file_exists($GLOBALS["configuration_file"]["gemini"]["db_dir"]."/".$matches[1]."/".$_POST['db_new_name'])) {
			db_admin_redirect("db_rename_new_db_already_exists");
		}
		
		// Rename the database file and associated files
		if (!rename_database($GLOBALS["configuration_file"]["gemini"]["db_dir"]."/".$_POST['database_dropdown'], $GLOBALS["configuration_file"]["gemini"]["db_dir"]."/".$matches[1]."/".$_POST['db_new_name'])) {
			db_admin_redirect("db_rename_fail");
		}
		
		log_website_event("Renamed database '".$_POST['database_dropdown']."' to '".$_POST['db_new_name']."'");
		
		db_admin_redirect("db_rename_success", database_name_from_path($_POST['database_dropdown'])." to ".$_POST['db_new_name']);
	}
	
	#############################################
	# IF A DATABASE IS TO BE MOVED
	#############################################
	
	if (isset($_POST['move_db_name'], $_POST['move_db_group'])) { // If the database moving form has been submitted with a database and account name
		// Make sure the group specified exists in the database
		$group_information = fetch_account_groups();
		
		if ($group_information === false) {
			db_admin_redirect();
		}
		
		$group_found_flag = 0;
		
		foreach (array_keys($group_information) as $group_id) {
			if ($group_information[$group_id]["group_name"] == $_POST['move_db_group']) {
				$group_found_flag = 1;
			}
		}
		
		// If the group wasn't found
		if ($group_found_flag == 0) {
			db_admin_redirect("db_move_nonexistant_group");
		}
		
		// Create the group folder locally if it doesn't already exist
		if (!is_dir($GLOBALS["configuration_file"]["gemini"]["db_dir"]."/".$_POST['move_db_group'])) {
			mkdir($GLOBALS["configuration_file"]["gemini"]["db_dir"]."/".$_POST['move_db_group']);
		}
		
		// Make sure the selected DB exists
		if (!file_exists($GLOBALS["configuration_file"]["gemini"]["db_dir"]."/".$_POST['move_db_name'])) {
			db_admin_redirect("db_move_nonexistant_db");
		}
		
		// Make sure a database with the same filename doesn't exist in the target group
		if (file_exists($GLOBALS["configuration_file"]["gemini"]["db_dir"]."/".$_POST['move_db_group']."/".database_name_from_path($_POST['move_db_name']))) {
			db_admin_redirect("db_move_already_exists");
		}

		// Move the database file
		if (!rename_database($GLOBALS["configuration_file"]["gemini"]["db_dir"]."/".$_POST['move_db_name'], $GLOBALS["configuration_file"]["gemini"]["db_dir"]."/".$_POST['move_db_group']."/".database_name_from_path($_POST['move_db_name']))) {
			db_admin_redirect("db_move_fail");
		}
		
		log_website_event("Moved database to a different group, database '".$_POST['move_db_name']."' to '".$_POST['move_db_group']."'");
		
		db_admin_redirect("db_move_success", database_name_from_path($_POST['move_db_name'])." to ".$_POST['move_db_group']);
	}
	
	#############################################
	# IF ANNOTATION VERSIONS ARE TO BE FETCHED
	#############################################
	
	if (isset($_POST['view_annotation_history'])) {
		$annotation_information = fetch_annotation_info($_POST['view_annotation_history']);
		
		if ($annotation_information === false) {
			db_admin_redirect("could_not_fetch_annotation_information");
		}
		
		$_SESSION["db_admin_annotation"] = $_POST['view_annotation_history'];
		$_SESSION["db_admin_annotation_information"] = $annotation_information;
		
		db_admin_redirect();
	}

	#############################################
		
	// Redirect to the DB admin page
	db_admin_page_redirect();
						
	#############################################
	# PAGE FUNCTIONS
	#############################################
	
	// Function to redirect to DB administration page
	function db_admin_redirect($session_variable_name = NULL, $session_variable_value = NULL) {
		if (isset($session_variable_name) && isset($session_variable_value)) {
			$_SESSION["db_admin_".$session_variable_name] = $session_variable_value;
		} elseif (isset($session_variable_name)) {
			$_SESSION["db_admin_".$session_variable_name] = 1;
		}

		header("Location: ".basename("..")."/database_administration");
			
		exit;
	}

?>