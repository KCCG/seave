<?php
				
	$body_class = "no-sidebar";
	
	require 'php_header.php'; // Require the PHP header housing required PHP functions
	require 'html_header.php'; // Require the HTML header housing the HTML structure of the page
?>

</div>

<!-- Main -->
<div class="wrapper">
	<div class="container" id="main">

		<!-- Content -->
		<article id="content">
			<header>
				<h2>Log in to see private databases.</h2>
				<p>Seave allows users to access different sets of databases by logging in with different accounts. Log in to view and query databases you have access to.</p>
			</header>
			
			<?php
				
				#############################################
				# IF THE USER SUCCESSFULLY LOGGED IN
				#############################################
				
				if (isset($_SESSION["logged_in"]["email"]) && if_set_display_success("login_success", "Successfully logged in as ".$_SESSION["logged_in"]["email"]."!<br><strong>Redirecting home.</strong>")) {
					echo "<meta http-equiv=\"refresh\" content=\"2; url=home\">"; // Redirect with 2 second delay

				#############################################
				# IF THE USER IS ALREADY LOGGED IN (redirect from action page)
				#############################################

				} elseif (if_set_display_error("login_already_logged_in", "You are already logged in.")) {
					echo "<meta http-equiv=\"refresh\" content=\"1; url=home\">"; // Redirect with 1 second delay
				
				#############################################
				# IF THERE WAS A PROBLEM CHECKING THE EMAIL/PASSWORD COMBO ON THE ACTION PAGE
				#############################################

				} elseif (if_set_display_error("login_problem_logging_in", "There was a problem logging you in. Please try again.")) {
					password_form();
				
				#############################################
				# IF THE EMAIL/PASSWORD SUBMITTED WERE WRONG
				#############################################

				} elseif (isset($_SESSION["login_bad_username_password"])) {
					password_form("Incorrect email/password");
					
					unset($_SESSION["login_bad_username_password"]);
				
				#############################################
				# IF THE USER IS ALREADY LOGGED IN (navigating to this page)
				#############################################
				
				} elseif (is_user_logged_in()) {
					error("You are already logged in.");
					
					echo "<meta http-equiv=\"refresh\" content=\"2; url=home\">"; // Redirect with 2 second delay
				
				#############################################
				# IF NO ACTION RESULT WAS SET, DISPLAY THE LOGIN FORM
				#############################################

				} else {
					password_form();
				}

			?>
		</article>
		
	</div>
</div>

<?php
	
	require 'html_footer.php';
?>