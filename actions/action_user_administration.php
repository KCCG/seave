<?php

	require basename("..").'/php_header.php'; // Require the PHP header housing required PHP functions

	#############################################
	# CHECK THAT THE USER HAS ACCESS
	#############################################
	
	if (!is_user_administrator()) {
		user_admin_redirect("no_access");
	}
	
	#############################################
	# FETCH ACCOUNT INFORMATION
	#############################################
	
	$account_information = fetch_account_all_information();
	
	if ($account_information === false) {
		user_admin_redirect("cant_fetch_account_information");
	}
	
    #############################################
	# PROCESS THE SUBMITTED FORM
	#############################################
		
	#############################################
	# Add group
	#############################################
	
	if (isset($_POST["group_name"], $_POST["group_description"])) {
		// QC group name
		if (strlen($_POST["group_name"]) > 50 || strlen($_POST["group_name"]) == 0) {
			user_admin_redirect("add_group_name_length");
		} elseif (!preg_match('/^[0-9A-Za-z]+$/', $_POST["group_name"])) {
			user_admin_redirect("add_group_name_invalid_characters");
		}
		
		// QC group description
		if (strlen($_POST["group_description"]) > 100) {
			user_admin_redirect("add_group_description_length");
		} elseif (!preg_match('/^[0-9A-Za-z ]+$/', $_POST["group_description"])) {
			user_admin_redirect("add_group_description_invalid_characters");
		}
		
		// Check that the group doesn't already exist
		foreach (array_keys($account_information["groups"]) as $group_id) {
			if (strtolower($account_information["groups"][$group_id]["group_name"]) == strtolower($_POST["group_name"])) { // Using strtolower() here to stop upper/lower case text appearing as different
				user_admin_redirect("add_group_already_exists");
			}
		}
		
		// Make the new group's directory if it doesn't already exist
		if (!file_exists($GLOBALS["configuration_file"]["gemini"]["db_dir"]."/".$_POST["group_name"])) {
			if (!mkdir($GLOBALS["configuration_file"]["gemini"]["db_dir"]."/".$_POST["group_name"])) {
				user_admin_redirect("add_group_cant_create_diretory");
			}
		}
		
		// Add the group to the DB
		if (!accounts_add_group($_POST["group_name"], $_POST["group_description"])) {
			user_admin_redirect("cant_make_change_in_db");
		}
		
		log_website_event("Added new user group '".$_POST["group_name"]."'");

		user_admin_redirect("add_group_success", $_POST["group_name"]);
	}
	
	#############################################
	# Add user
	#############################################
	
	if (isset($_POST["add_email"], $_POST["add_password"])) {
		// QC email
		if (!filter_var($_POST["add_email"], FILTER_VALIDATE_EMAIL)) {
			user_admin_redirect("add_user_email_invalid");
		}
		
		// QC password
		if (strlen($_POST["add_password"]) == 0) {
			user_admin_redirect("add_user_password_empty");
		}
		
		// Check that the email isn't already registered for an account
		foreach (array_keys($account_information["users"]) as $user_id) {
			if (strtolower($account_information["users"][$user_id]["email"]) == strtolower($_POST["add_email"])) { // Using strtolower() here to stop upper/lower case text appearing as different
				user_admin_redirect("add_user_already_exists");
			}
		}
		
		// Add the user to the DB
		if (!accounts_add_user(strtolower($_POST["add_email"]), $_POST["add_password"])) { // Using strtolower() here so all emails are stored lower case in the database, this is how submitted ones are compared when logging in
			user_admin_redirect("cant_make_change_in_db");
		}
		
		log_website_event("Added new user '".$_POST["add_email"]."'");

		user_admin_redirect("add_user_success", $_POST["add_email"]);
	}
	
	#############################################
	# Delete group
	#############################################
	
	if (isset($_POST["delete_group"])) {		
		// Make sure the group exists in the DB
		$group_exists_flag = 0;
		
		foreach (array_keys($account_information["groups"]) as $group_id) {
			if ($account_information["groups"][$group_id]["group_name"] == $_POST["delete_group"]) {
				$group_exists_flag = 1;
			}
		}
		
		if ($group_exists_flag == 0) {
			user_admin_redirect("cant_find_group");
		}
		
		// Make sure the group exists locally (for example, to prevent deleting a group on production from dev)
		if (!file_exists($GLOBALS["configuration_file"]["gemini"]["db_dir"]."/".$_POST["delete_group"])) {
			user_admin_redirect("delete_group_cant_delete_group_not_present_locally");
		}
		
		// Make sure the group has no databases in it
		$databases_in_group = parse_all_available_database_files();
		
		if (count($databases_in_group[$_POST["delete_group"]]) > 0) {
			user_admin_redirect("delete_group_databases_present");
		}
		
		// Delete the group from the DB (user associations are automatically deleted)
		if (!accounts_delete_group($_POST["delete_group"])) {
			user_admin_redirect("cant_make_change_in_db");
		}
		
		// Delete the directory for the group, this function will only work if the directory is empty, whether it is empty is not checked though because I don't want to enforce a directory having no extra files in order for a group to be deleted; under normal operation the directory will be empty once all databases are moved out of it
		rmdir($GLOBALS["configuration_file"]["gemini"]["db_dir"]."/".$_POST["delete_group"]);
		
		log_website_event("Deleted the user group '".$_POST["delete_group"]."'");

		user_admin_redirect("delete_group_success", $_POST["delete_group"]);
	}
	
	#############################################
	# Delete user
	#############################################
	
	if (isset($_POST["delete_user"])) {
		// Make sure the user is in the database
		$user_exists = 0;
		
		foreach (array_keys($account_information["users"]) as $user_id) {
			if ($account_information["users"][$user_id]["email"] == $_POST["delete_user"]) {
				$user_id_found = $user_id;
			}
		}
		
		if (!isset($user_id_found)) {
			user_admin_redirect("cant_find_user");
		}
		
		// If the user being removed is an administator
		if ($account_information["users"][$user_id_found]["is_administrator"] == 1) {
			// Make sure they aren't the last administrator
			$administrators_count = 0;
		
			foreach (array_keys($account_information["users"]) as $user_id) {
				if ($account_information["users"][$user_id]["is_administrator"] == "1") {
					$administrators_count++;
				}
			}
			
			if ($administrators_count < 2) {
				user_admin_redirect("delete_user_last_administrator");
			}
		}
		
		// Delete the user from the DB (group associations automatically deleted)
		if (!accounts_delete_user($_POST["delete_user"])) {
			user_admin_redirect("cant_make_change_in_db");
		}
		
		log_website_event("Deleted the user '".$_POST["delete_user"]."'");

		user_admin_redirect("delete_user_success", $_POST["delete_user"]);
	}
	
	#############################################
	# Add a user to a group
	#############################################
	
	if (isset($_POST["add_user_to_group_user"], $_POST["add_user_to_group_group"])) {
		// Make sure the user is in the database
		foreach (array_keys($account_information["users"]) as $user_id) {
			if ($account_information["users"][$user_id]["email"] == $_POST["add_user_to_group_user"]) {
				$user_id_found = $user_id;
			}
		}
		
		if (!isset($user_id_found)) {
			user_admin_redirect("cant_find_user");
		}
		
		// Make sure the group is in the database
		foreach (array_keys($account_information["groups"]) as $group_id) {
			if ($account_information["groups"][$group_id]["group_name"] == $_POST["add_user_to_group_group"]) {
				$group_id_found = $group_id;
			}
		}
		
		if (!isset($group_id_found)) {
			user_admin_redirect("cant_find_group");
		}
		
		// Make sure the user isn't already in the group
		if (isset($account_information["users_in_groups"]["groups_per_user"][$user_id_found][$group_id_found])) {
			user_admin_redirect("add_user_to_group_user_already_in_group");
		}
		
		// Add the user to the group
		if (!accounts_add_user_to_group($user_id_found, $group_id_found)) {
			user_admin_redirect("cant_make_change_in_db");
		}
		
		log_website_event("Added the user '".$_POST["add_user_to_group_user"]."' to group '".$_POST["add_user_to_group_group"]."'");

		user_admin_redirect("add_user_to_group_success", $_POST["add_user_to_group_user"]." to group ".$_POST["add_user_to_group_group"]);
	}
	
	#############################################
	# Remove a user from a group
	#############################################
	
	if (isset($_POST["remove_user_from_group_user"], $_POST["remove_user_from_group_group"])) {
		// Make sure the user is in the database
		foreach (array_keys($account_information["users"]) as $user_id) {
			if ($account_information["users"][$user_id]["email"] == $_POST["remove_user_from_group_user"]) {
				$user_id_found = $user_id;
			}
		}
		
		if (!isset($user_id_found)) {
			user_admin_redirect("cant_find_user");
		}
		
		// Make sure the group is in the database
		foreach (array_keys($account_information["groups"]) as $group_id) {
			if ($account_information["groups"][$group_id]["group_name"] == $_POST["remove_user_from_group_group"]) {
				$group_id_found = $group_id;
			}
		}
		
		if (!isset($group_id_found)) {
			user_admin_redirect("cant_find_group");
		}
		
		// Make sure the user is in the group
		if (!isset($account_information["users_in_groups"]["groups_per_user"][$user_id_found][$group_id_found])) {
			user_admin_redirect("remove_user_from_group_user_not_in_group");
		}
		
		// Remove the user from the group
		if (!accounts_remove_user_from_group($user_id_found, $group_id_found)) {
			user_admin_redirect("cant_make_change_in_db");
		}
		
		log_website_event("Removed the user '".$_POST["remove_user_from_group_user"]."' from group '".$_POST["remove_user_from_group_group"]."'");

		user_admin_redirect("remove_user_from_group_success", $_POST["remove_user_from_group_user"]." from group ".$_POST["remove_user_from_group_group"]);
	}
	
	#############################################
	# Change a user's password
	#############################################
	
	if (isset($_POST["change_password_user"], $_POST["change_password_new_password"])) {
		// Make sure the user is in the database
		foreach (array_keys($account_information["users"]) as $user_id) {
			if ($account_information["users"][$user_id]["email"] == $_POST["change_password_user"]) {
				$user_id_found = $user_id;
			}
		}
		
		if (!isset($user_id_found)) {
			user_admin_redirect("cant_find_user");
		}
		
		// QC password
		if (strlen($_POST["change_password_new_password"]) == 0) {
			user_admin_redirect("change_password_password_empty");
		}
		
		// Change the user's password
		if (!accounts_change_password($_POST["change_password_user"], $_POST["change_password_new_password"])) {
			user_admin_redirect("cant_make_change_in_db");
		}
		
		log_website_event("Changed a user's password: ".$_POST["change_password_user"]."");
		
		user_admin_redirect("change_password_success", $_POST["change_password_user"]);
	}
	
	#############################################
	# Make a user an administrator
	#############################################
	
	if (isset($_POST["make_administrator_user"])) {
		// Make sure the user is in the database
		foreach (array_keys($account_information["users"]) as $user_id) {
			if ($account_information["users"][$user_id]["email"] == $_POST["make_administrator_user"]) {
				$user_id_found = $user_id;
			}
		}
		
		if (!isset($user_id_found)) {
			user_admin_redirect("cant_find_user");
		}
		
		// Make sure the user is not an administrator already
		if ($account_information["users"][$user_id_found]["is_administrator"] == "1") {
			user_admin_redirect("make_administrator_user_already_administrator");
		}
		
		// Make the user an administrator
		if (!accounts_modify_administrator($_POST["make_administrator_user"], "1")) {
			user_admin_redirect("cant_make_change_in_db");
		}
		
		log_website_event("Made a user an administrator: ".$_POST["make_administrator_user"]."");
		
		user_admin_redirect("make_administrator_success", $_POST["make_administrator_user"]);
	}
	
	#############################################
	# Remove a user's administrator access
	#############################################
	
	if (isset($_POST["delete_administrator_user"])) {
		// Make sure the user is in the database
		foreach (array_keys($account_information["users"]) as $user_id) {
			if ($account_information["users"][$user_id]["email"] == $_POST["delete_administrator_user"]) {
				$user_id_found = $user_id;
			}
		}
		
		if (!isset($user_id_found)) {
			user_admin_redirect("cant_find_user");
		}
		
		// Make sure the user is an administrator
		if ($account_information["users"][$user_id_found]["is_administrator"] != "1") {
			user_admin_redirect("remove_administrator_user_not_administrator");
		}
		
		// Make sure there are other administrators available
		$administrators_count = 0;
		
		foreach (array_keys($account_information["users"]) as $user_id) {
			if ($account_information["users"][$user_id]["is_administrator"] == "1") {
				$administrators_count++;
			}
		}
		
		if ($administrators_count < 2) {
			user_admin_redirect("remove_administrator_no_other_administrators");
		}
		
		// Remove the user's administrator access
		if (!accounts_modify_administrator($_POST["delete_administrator_user"], "0")) {
			user_admin_redirect("cant_make_change_in_db");
		}
		
		log_website_event("Removed a user's administrator access: ".$_POST["delete_administrator_user"]."");
		
		user_admin_redirect("remove_administrator_success", $_POST["delete_administrator_user"]);
	}
    
	#############################################
		
	// Redirect to the user admin page if no other redirect has been applied
	user_admin_redirect();
						
	#############################################
	# PAGE FUNCTIONS
	#############################################
	
	// Function to redirect to DB administration page
	function user_admin_redirect($session_variable_name = NULL, $session_variable_value = NULL) {
		if (isset($session_variable_name) && isset($session_variable_value)) {
			$_SESSION["user_admin_".$session_variable_name] = $session_variable_value;
		} elseif (isset($session_variable_name)) {
			$_SESSION["user_admin_".$session_variable_name] = 1;
		}
		
		header("Location: ".basename("..")."/user_administration");
			
		exit;
	}

?>