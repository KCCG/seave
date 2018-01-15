<?php	
	$body_class = "no-sidebar"; // Page without a sidebar
	
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
				<h2>Pull up a chair and <strong>grab some data</strong>.</h2>
				<p><em>First</em>, you need to select some data in a database. <em>Click the database row</em> you would like to query. Databases with <em>pedigree information</em> can utilise advanced queries and it is recommended you add this information to your databases.</p>
			</header>
			
			<a class="image featured"><img class="image featured" src="images/white-servers.jpg" alt="" /></a>
			
			<?php
			
				#############################################
				# HANDLE SESSIONS
				#############################################
				
				// If a restart button has been clicked (i.e. wipe session data)
			    if (isset($_GET["restart"])) {
			        clean_session();
			    // Otherwise check whether a session exists and create one if not
			    } else {
			        renew_session();
			    }
				
				#############################################
				# FETCH DATABASE INFORMATION AND DISPLAY TABLE
				#############################################
				
				echo "<h2 style=\"padding-top:20px;\">Available databases</h2>";
				
				// Only fetches the databases the current user has access to
				$db_information = parse_databases();
				
				// If databases were found, display them in a table
				if ($db_information !== false) {
					database_table($db_information);
				// If no databases found, display an error
				} else {
					error("No databases available.");
				}
	
				// Display the disk space available if the user is logged in as an admin
				if (is_user_administrator()) {
					echo "<p style=\"text-align:center;\">Disk space currently in use: ".storage_space("used")."/".storage_space("total")."</p>";
				}
				
				#############################################
				# PRINT GEMINI VERSION
				#############################################
				
				$query = $GLOBALS["configuration_file"]["gemini"]["binary"]." -v 2>&1";

				exec($query, $query_result, $exit_code); # Execute the GEMINI query

				if ($exit_code == 0) {
					$query_result[0] = preg_replace("/^gemini\s/", "", $query_result[0]); // Remove the "gemini " before the version itself
					
					echo "<p style=\"text-align:center;\">Seave is running GEMINI version ".$query_result[0].".</p>";
				}
				
				#############################################
				# DISPLAY LOGIN PROMPT IF USER IS NOT LOGGED IN
				#############################################
					
				if (!is_user_logged_in()) {
					echo "<h3 style=\"padding-bottom:20px; padding-top: 20px; text-align:center;\">To see private databases, you need to log in.</h3>";
				}
				
			?>
		</article>
	</div>
</div>

<?php
	require 'html_footer.php';
?>