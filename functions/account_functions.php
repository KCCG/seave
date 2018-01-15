<?php

#############################################
# IS THE USER LOGGED IN AT ALL
#############################################

function is_user_logged_in() {
	// If the logged in session variable is set
	if (isset($_SESSION["logged_in"]["email"], $_SESSION["logged_in"]["user_id"], $_SESSION["logged_in"]["is_administrator"])) {
		// If the logged in session variable has been populated away from the default empty string state
		if ($_SESSION["logged_in"]["email"] != "" && $_SESSION["logged_in"]["user_id"] != "" && $_SESSION["logged_in"]["is_administrator"] != "") {
			return true;
		}
	}
	
	return false;
}

#############################################
# IS THE USER LOGGED IN AS AN ADMINISTRATOR
#############################################

function is_user_administrator() {
	if (isset($_SESSION["logged_in"]["is_administrator"]) && $_SESSION["logged_in"]["is_administrator"] == "1") {
		return true;
	} else {
		return false;
	}
}

#############################################
# FETCH ALL ACCOUNT GROUPS
#############################################

function fetch_account_groups($user_email = NULL) { // Optional parameter for specifying a user ID, this will only return groups for the user specified as opposed to all groups
	$group_information = array();
	
	$sql = "SELECT ";
		$sql .= "ACCOUNTS.groups.id, ";
		$sql .= "ACCOUNTS.groups.group_name, ";
		$sql .= "ACCOUNTS.groups.group_description, ";
		$sql .= "ACCOUNTS.groups.date_added ";
	$sql .= "FROM ";
		$sql .= "ACCOUNTS.groups";
		
		// If the user's email is empty (this happens when a user is not logged in), fetch information for the Public group
		if ($user_email === "") {
			$sql .= " WHERE ";
			
			$sql .= "ACCOUNTS.groups.group_name = ?";
		// If a $user_email has been supplied, fetch group information for that account
		} elseif ($user_email != NULL) {
			$sql .= " INNER JOIN ACCOUNTS.users_in_groups ON ACCOUNTS.groups.id = ACCOUNTS.users_in_groups.group_id ";
			$sql .= " INNER JOIN ACCOUNTS.users ON ACCOUNTS.users_in_groups.user_id = ACCOUNTS.users.id ";
			
			$sql .= "WHERE ";
			
			$sql .= "ACCOUNTS.users.email = ?";
		}
	
	$sql .= ";";
	
	$statement = $GLOBALS["mysql_connection"]->prepare($sql);
	
	// If the user's email is empty (this happens when a user is not logged in), fetch information for the Public group
	if ($user_email === "") {
		$statement->execute(["Public"]);
	// If a $user_email has been supplied, fetch group information for that account
	} elseif ($user_email != NULL) {
		$statement->execute([$user_email]);
	// If all groups are to be returned
	} else {
		$statement->execute();
	}
	
	while ($row = $statement->fetch()) {
		$group_information[$row["id"]]["group_name"] = $row["group_name"];
		$group_information[$row["id"]]["group_description"] = $row["group_description"];
		$group_information[$row["id"]]["date_added"] = $row["date_added"];
	}
	
	return $group_information;
}

#############################################
# FETCH ALL USER ACCOUNTS
#############################################

function fetch_account_users() {
	$user_information = array();
	
	$sql = "SELECT ";
		$sql .= "ACCOUNTS.users.id, ";
		$sql .= "ACCOUNTS.users.email, ";
		$sql .= "ACCOUNTS.users.is_administrator, ";
		$sql .= "ACCOUNTS.users.date_added ";
	$sql .= "FROM ";
		$sql .= "ACCOUNTS.users";
	$sql .= ";";
	
	$statement = $GLOBALS["mysql_connection"]->prepare($sql);
	
	$statement->execute();
	
	while ($row = $statement->fetch()) {
		$user_information[$row["id"]]["email"] = $row["email"];
		$user_information[$row["id"]]["is_administrator"] = $row["is_administrator"];
		$user_information[$row["id"]]["date_added"] = $row["date_added"];
	}
	
	return $user_information;
}

#############################################
# FETCH ALL USER GROUP MEMBERSHIPS
#############################################

function fetch_account_users_in_groups() {
	$users_in_groups_information = array();
	
	$sql = "SELECT ";
		$sql .= "ACCOUNTS.users_in_groups.user_id, ";
		$sql .= "ACCOUNTS.users_in_groups.group_id, ";
		$sql .= "ACCOUNTS.users_in_groups.date_added ";
	$sql .= "FROM ";
		$sql .= "ACCOUNTS.users_in_groups";
	$sql .= ";";
	
	$statement = $GLOBALS["mysql_connection"]->prepare($sql);
	
	$statement->execute();
	
	while ($row = $statement->fetch()) {
		$users_in_groups_information["groups_per_user"][$row["user_id"]][$row["group_id"]]["date_added"] = $row["date_added"];
		$users_in_groups_information["users_per_group"][$row["group_id"]][$row["user_id"]]["date_added"] = $row["date_added"];
	}
	
	return $users_in_groups_information;
}

#############################################
# FETCH ALL USER INFORMATION
#############################################

function fetch_account_all_information() {
	$account_information["groups"] = fetch_account_groups();
	
	$account_information["users"] = fetch_account_users();
	
	$account_information["users_in_groups"] = fetch_account_users_in_groups();
	
	// If any of the fetches failed
	if ($account_information["groups"] === false || $account_information["users"] === false || $account_information["users_in_groups"] === false) {
		return false;
	} else {
		return $account_information;
	}
}

#############################################
# ADD NEW ACCOUNT GROUP
#############################################

function accounts_add_group($group_name, $group_description) {
	$sql = "INSERT INTO ";
		$sql .= "ACCOUNTS.groups (group_name, group_description, date_added) ";
	$sql .= "VALUES ";
		$sql .= "(?, ?, now())";
	$sql .= ";";
	
	$statement = $GLOBALS["mysql_connection"]->prepare($sql);
	
	$statement->execute([$group_name, $group_description]);
	
	$rows_affected = $statement->rowCount();
	
	// Make sure 1 row was affected
	if ($rows_affected === 1) {
		return true;
	} else {
		return false;
	}
}

#############################################
# DELETE ACCOUNT GROUP
#############################################

function accounts_delete_group($group_name) {
	$sql = "DELETE FROM ";
		$sql .= "ACCOUNTS.groups ";
	$sql .= "WHERE ";
		$sql .= "group_name = ?";
	$sql .= ";";
	
	$statement = $GLOBALS["mysql_connection"]->prepare($sql);
	
	$statement->execute([$group_name]);
	
	$rows_affected = $statement->rowCount();
	
	// Make sure 1 row was affected
	if ($rows_affected === 1) {
		return true;
	} else {
		return false;
	}
}

#############################################
# ADD USER
#############################################

function accounts_add_user($email, $password) {
	// Hash the password
	$hashed_password = password_hash($password, PASSWORD_DEFAULT, ['cost' => 14]);
	
	$sql = "INSERT INTO ";
		$sql .= "ACCOUNTS.users (email, password, is_administrator, date_added) ";
	$sql .= "VALUES ";
		$sql .= "(?, ?, 0, now())";
	$sql .= ";";
	
	$statement = $GLOBALS["mysql_connection"]->prepare($sql);
	
	$statement->execute([$email, $hashed_password]);
	
	$rows_affected = $statement->rowCount();
	
	// Make sure 1 row was affected
	if ($rows_affected === 1) {
		return true;
	} else {
		return false;
	}
}

#############################################
# DELETE USER
#############################################

function accounts_delete_user($email) {
	$sql = "DELETE FROM ";
		$sql .= "ACCOUNTS.users ";
	$sql .= "WHERE ";
		$sql .= "email = ?";
	$sql .= ";";
	
	$statement = $GLOBALS["mysql_connection"]->prepare($sql);
	
	$statement->execute([$email]);
	
	$rows_affected = $statement->rowCount();
	
	// Make sure 1 row was affected
	if ($rows_affected === 1) {
		return true;
	} else {
		return false;
	}
}

#############################################
# ADD USER TO GROUP
#############################################

function accounts_add_user_to_group($user_id, $group_id) {
	// Make sure the IDs are numbers
	if (!is_int($user_id) || !is_int($group_id)) {
		return false;
	}
	
	$sql = "INSERT INTO ";
		$sql .= "ACCOUNTS.users_in_groups (user_id, group_id, date_added) ";
	$sql .= "VALUES ";
		$sql .= "(?, ?, now())";
	$sql .= ";";
	
	$statement = $GLOBALS["mysql_connection"]->prepare($sql);
	
	$statement->execute([$user_id, $group_id]);
	
	$rows_affected = $statement->rowCount();
	
	// Make sure 1 row was affected
	if ($rows_affected === 1) {
		return true;
	} else {
		return false;
	}
}

#############################################
# REMOVE USER FROM GROUP
#############################################

function accounts_remove_user_from_group($user_id, $group_id) {
	// Make sure the IDs are numbers
	if (!is_int($user_id) || !is_int($group_id)) {
		return false;
	}
	
	$sql = "DELETE FROM ";
		$sql .= "ACCOUNTS.users_in_groups ";
	$sql .= "WHERE ";
		$sql .= "user_id = ? ";
	$sql .= "AND ";
		$sql .= "group_id = ?";
	$sql .= ";";
	
	$statement = $GLOBALS["mysql_connection"]->prepare($sql);
	
	$statement->execute([$user_id, $group_id]);
	
	$rows_affected = $statement->rowCount();
	
	// Make sure 1 row was affected
	if ($rows_affected === 1) {
		return true;
	} else {
		return false;
	}
}

#############################################
# CHANGE A USER'S PASSWORD
#############################################

function accounts_change_password($email, $password) {
	// Hash the password
	$hashed_password = password_hash($password, PASSWORD_DEFAULT, ['cost' => 14]);
	
	$sql = "UPDATE ";
		$sql .= "ACCOUNTS.users ";
	$sql .= "SET ";
		$sql .= "password = ? ";
	$sql .= "WHERE ";
		$sql .= "email = ?";
	$sql .= ";";
	
	$statement = $GLOBALS["mysql_connection"]->prepare($sql);
	
	$statement->execute([$hashed_password, $email]);
	
	$rows_affected = $statement->rowCount();
	
	// Make sure 1 row was affected
	if ($rows_affected === 1) {
		return true;
	} else {
		return false;
	}
}

#############################################
# MODIFY A USER'S ADMINISTRATOR ACCESS
#############################################

function accounts_modify_administrator($email, $admin_access) {
	// Validate the $admin_access parameter
	if ($admin_access != "0" && $admin_access != "1") {
		return false;
	}
	
	$sql = "UPDATE ";
		$sql .= "ACCOUNTS.users ";
	$sql .= "SET ";
		$sql .= "is_administrator = ? ";
	$sql .= "WHERE ";
		$sql .= "email = ?";
	$sql .= ";";
	
	$statement = $GLOBALS["mysql_connection"]->prepare($sql);
	
	$statement->execute([$admin_access, $email]);
	
	$rows_affected = $statement->rowCount();
	
	// Make sure 1 row was affected
	if ($rows_affected === 1) {
		return true;
	} else {
		return false;
	}
}

#############################################
# VALIDATE SUBMITTED CREDENTIALS AND LOG USER IN
#############################################

function log_in($email, $password) {
	// Convert the email submitted to lower case (same as what is stored in the DB), this controls for people putting in J.Smith@institute.com when the DB is j.smith@institute.com
	$email = strtolower($email);
	
	// If the user is already logged in, fail
	if (is_user_logged_in()) {
		return false;
	}
	
	$sql = "SELECT ";
		$sql .= "id, ";
		$sql .= "email, ";
		$sql .= "password, ";
		$sql .= "is_administrator, ";
		$sql .= "date_added ";
	$sql .= "FROM ";
		$sql .= "ACCOUNTS.users ";
	$sql .= "WHERE ";
		$sql .= "email = ?";
	$sql .= ";";
	
	$statement = $GLOBALS["mysql_connection"]->prepare($sql);
	
	$statement->execute([$email]);
	
	$rows_returned = $statement->rowCount();
	
	if ($rows_returned !== 1) {
		return null;
	}
	
	$account_info = $statement->fetch();
	
	// If the password for the email matches
	if (password_verify($password, $account_info["password"])) {
		$_SESSION["logged_in"]["user_id"] = $account_info["id"];
		$_SESSION["logged_in"]["email"] = $account_info["email"];
		$_SESSION["logged_in"]["is_administrator"] = $account_info["is_administrator"];
		
		return true;
	} else {
		return null;
	}
}


#############################################
# CHECK WHETHER A USER IS IN A SPECIFIC GROUP
#############################################

function is_user_in_group($user_email, $group_name) {
	$user_groups = fetch_account_groups($user_email);
	
	if ($user_groups === false) {
		return false;
	}
	
	foreach (array_keys($user_groups) as $group_id) {
		if ($user_groups[$group_id]["group_name"] == $group_name) {
			return true;
		}
	}
	
	return null;
}

?>
