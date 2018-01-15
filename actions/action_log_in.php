<?php

	require basename("..").'/php_header.php'; // Require the PHP header housing required PHP functions
			    				
	#############################################
	# IF THE USER IS ALREADY LOGGED IN
	#############################################
	
	if (is_user_logged_in()) {
		login_page_redirect("already_logged_in");
	}

	#############################################
	# IF A PASSWORD HAS BEEN SUBMITTED, LOG THE USER IN
	#############################################
		
	if (!isset($_POST["email"], $_POST["password"])) {
		login_page_redirect();
	}
	
	$log_in_result = log_in($_POST["email"], $_POST["password"]);
	
	// If the user was successfully logged in
	if ($log_in_result === true) {
		log_website_event("Logged in");
		
		login_page_redirect("success");
	// If there is no account for the email/password combo submitted
	} elseif ($log_in_result === null) {
		log_website_event("Failed login attempt, email submitted: ".htmlspecialchars($_POST["email"], ENT_QUOTES, 'UTF-8'));
		
		login_page_redirect("bad_username_password");
	// If there was a problem with checking the username/password combo
	} elseif ($log_in_result === false) {
		login_page_redirect("problem_logging_in");
	}
				
	#############################################
	# PAGE FUNCTIONS
	#############################################
	
	// Function to redirect to login page
	function login_page_redirect($session_variable_name = NULL) {
		if (isset($session_variable_name)) {
			$_SESSION["login_".$session_variable_name] = 1;
		}

		header("Location: ".basename("..")."/login");
			
		exit;
	} 
?>