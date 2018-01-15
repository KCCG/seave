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
	if (!isset($_POST["samples"], $_POST["methods"])) {
		gbs_administration_page_redirect("no_posts");
	}
	
	// Note: not validating POST values as if these are incorrect the delete function won't work, if the user is logged in as admin there's no reason to impose a secondary check for accurate form usage since they can already delete everything
	
	#############################################
	# DELETE THE BLOCKS
	#############################################
	
	if (!delete_blocks_gbs($_POST["samples"], $_POST["methods"])) {
		gbs_administration_page_redirect("fail");
	} else {
		log_website_event("Deleted GBS blocks for sample '".$_POST["samples"]."' and method '".$_POST["methods"]."'");
		
		gbs_administration_page_redirect("success");
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
			$_SESSION["gbs_delete_".$session_variable_name] = 1;
		}

		header("Location: ".basename("..")."/gbs_administration");
			
		exit;
	}
?>