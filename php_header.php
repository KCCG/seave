<?php

#############################################
# SESSIONS
#############################################

session_set_cookie_params(10800); // Each client should remember their session ID for 3 hours

session_start(); # Enable sessions

#############################################
# INCLUSIONS
#############################################

require 'variables.php'; # Add global variables

// Require functions file which includes all other separate function files
require 'functions/functions.php';

// If the logged_in session variable is not set (i.e. no set of session variables currently exist for the user), create a new session
if (!isset($_SESSION["logged_in"])) {
	clean_session();
}

#############################################
# ESTABLISH DATABASE CONNECTION
#############################################

// Connect to MySQL db
$GLOBALS["mysql_connection"] = establish_mysql_connection();

// If there was a problem connecting to MySQL, unset the connection variable
if ($GLOBALS["mysql_connection"] === false) {
	echo "Can't establish database connection. Please try again.";
	
	exit;
}

?>