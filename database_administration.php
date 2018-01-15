<?php	
	$body_class = "no-sidebar"; // This page does not have a sidebar
	
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
				<h2>Need to make some changes?</h2>
				<p>The database administration options below let you add, remove and modify databases to your heart's content.</p>
			</header>
			<?php
				// If the user is an administrator
				if (is_user_administrator()) {

					#############################################
					# FETCH AVAILABLE GEMINI DBS
					#############################################
					
					$databases = parse_all_available_database_files();
					
					#############################################
					# FETCH ANNOTATION INFORMATION
					#############################################
					
					// Fetch all annotations from the DB (including inactive ones)
					$annotations = fetch_all_annotations("all");
					
					if ($annotations === false || count(array_keys($annotations)) == 0) {
						error("Problem fetching Seave annotations. Please try again.");
					}
					
					#############################################
										
					// Disk space available
					echo "<h3 style=\"text-align:center;\">Disk space currently in use: <br>".storage_space("used")."/".storage_space("total")."</h3><br>";

					#############################################
					# SUCCESS/ERROR MESSAGES
					#############################################
									
					if_set_display_error("db_admin_new_db_bad_url", "The URL your specified does not exist (did not return 200).");
					if (isset($_SESSION["db_admin_new_db_download_fail"])) { if_set_display_error("db_admin_new_db_download_fail", "Error downloading database file:<br>".$_SESSION["db_admin_new_db_download_fail"]); }
					if_set_display_error("db_admin_new_db_bad_filename", "The database file you submitted does not end with \".db\" and was therefore not added.");
					if_set_display_error("db_admin_new_db_db_exists", "A database with that filename already exists on Seave. Please delete it first before importing again.");
					if_set_display_error("db_admin_new_db_group_doesnt_exist", "The group specified doesn't exist.");
					if (isset($_SESSION["db_admin_delete_db_fail"])) { if_set_display_error("db_admin_delete_db_fail", "Could not delete database file:<br>".$_SESSION["db_admin_delete_db_fail"]); }
					if_set_display_error("db_admin_db_summary_no_db", "The database you specified to regenerate a report for does not exist.");
					if (isset($_SESSION["db_admin_db_summary_failed"])) { if_set_display_error("db_admin_db_summary_failed", "Could not regenerate summary report for the database:<br>".$_SESSION["db_admin_db_summary_failed"]); }
					if_set_display_error("db_admin_db_annotation_large_ped", "The PED file specified is too large (over 1MB).");
					if_set_display_error("db_admin_db_annotation_no_ped", "The file you uploaded does not end with .ped.");
					if_set_display_error("db_admin_db_annotation_failed", "Could not update annotation. GEMINI error.");
					if_set_display_error("db_admin_db_rename_nonexistant_db", "The database you selected to rename does not exist.");
					if_set_display_error("db_admin_db_rename_no_new_db", "You did not submit a new database name to replace the old one or it did not end with .db.");
					if_set_display_error("db_admin_db_rename_new_db_already_exists", "The name database name you specified already exists. Please choose another.");
					if_set_display_error("db_admin_db_rename_fail", "Problem renaming the database.");
					if_set_display_error("db_admin_db_move_nonexistant_group", "The group you specified does not exist.");
					if_set_display_error("db_admin_db_move_nonexistant_db", "The database you specified does not exist.");
					if_set_display_error("db_admin_db_move_already_exists", "A database with the same filename as the one you are moving already exists in the group you specified.");
					if_set_display_error("db_admin_db_move_fail", "Could not move the database.");
					if_set_display_error("db_admin_could_not_fetch_annotation_information", "Problem fetching annotation information.");
					
					if (isset($_SESSION["db_admin_delete_db_success"])) { if_set_display_success("db_admin_delete_db_success", "Successfully deleted database file: <br>".$_SESSION["db_admin_delete_db_success"]); }
					if (isset($_SESSION["db_admin_db_summary_success"])) { if_set_display_success("db_admin_db_summary_success", "Successfully regenerated summary report for the database:<br>".$_SESSION["db_admin_db_summary_success"]); }
					if (isset($_SESSION["db_admin_db_annotation_success"])) { if_set_display_success("db_admin_db_annotation_success", "Successfully updated the pedigree annotation for the database:<br>".$_SESSION["db_admin_db_annotation_success"]); }
					if (isset($_SESSION["db_admin_db_rename_success"])) { if_set_display_success("db_admin_db_rename_success", "Successfully renamed the database:<br>".$_SESSION["db_admin_db_rename_success"]); }
					if (isset($_SESSION["db_admin_db_move_success"])) { if_set_display_success("db_admin_db_move_success", "Successfully moved the database:<br>".$_SESSION["db_admin_db_move_success"]); }
					if (isset($_SESSION["db_admin_new_db_success"])) { if_set_display_success("db_admin_new_db_success", "Successfully downloaded database file:<br>".$_SESSION["db_admin_new_db_success"]); }

					#############################################
					# VIEW REQUESTED ANNOTATION INFORMATION
					#############################################
					
					if (isset($_SESSION["db_admin_annotation_information"])) {
						success("Showing annotation history for the annotation: \"".$_SESSION["db_admin_annotation"]."\"");
							
						annotation_history_table($_SESSION["db_admin_annotation_information"]);
						
						unset($_SESSION["db_admin_annotation"]);
						unset($_SESSION["db_admin_annotation_information"]);
					}
					
					#############################################
					# ADD A DATABASE FORM
					#############################################
					
					echo "<div class=\"row\">";
						echo "<section class=\"6u 12u(narrower)\">";
							echo "<h2>Add a new database</h2>";	
							
							echo "<p>New databases must be added by specifying a URL to the database file. This functionality is targeted towards imports from DNA Nexus or Dropbox which allow you to generate a download link your database. Databases will be placed in the default Seave group.</p>";

							echo "<form action=\"actions/action_database_administration\" method=\"post\">";
								echo "<div class=\"row 50%\">";
									echo "<div class=\"12u\">";
										echo "<h4>URL to database</h4>";
										echo "<input type=\"text\" name=\"db_url\" placeholder=\"http://www.example.com/database.db\">";
									echo "</div>";
								echo "</div>";
								echo "<div class=\"row 50%\">";
									echo "<div class=\"12u\">";
										echo "<h4>Group to import into</h4>";
										echo "<select name=\"add_db_group\">";
											// Go through each group
											foreach (array_keys($databases) as $group) {
												echo "<option value=\"".$group."\">".$group."</option>";
											}
										echo "</select>";
									echo "</div>";
								echo "</div>";
								echo "<div class=\"row 50%\">";
									echo "<div class=\"12u\">";
										echo "<ul class=\"actions\">";
											echo "<li><input type=\"submit\" value=\"Add database\" /></li>";
										echo "</ul>";
									echo "</div>";
								echo "</div>";
							echo "</form>";
							
							echo "<p style=\"color: red;\">Important: databases must end with a .db suffix. Once you press the submit button below, you will need to wait for the server to download the file you specified. Please DO NOT refresh or stop the page during this time, this process could take quite some time if the database you specified is large.</p>";
						echo "</section>";
						
						#############################################
						# DELETE DATABASE FORM
						#############################################
						
						echo "<section class=\"6u 12u(narrower)\">";
							echo "<h2>Delete a database</h2>";	
							
							echo "<p>If you no longer require a database or something went wrong with a database import, you may wish to delete a database. Simply select the database you wish to delete below.</p>";

							echo "<form action=\"actions/action_database_administration\" method=\"post\">";
								echo "<div class=\"row 50%\">";
									echo "<div class=\"12u\">";
										echo "<h4>Database to delete</h4>";
										echo "<select name=\"delete_db_name\">";
											// Go through each group
											foreach (array_keys($databases) as $group) {
												// Go through each database file
												foreach ($databases[$group] as $database) {
													$db_name_for_dropdown = $group." - ".$database; // Remove the path for printing to the dropdown as the db name
													
													echo "<option value=\"".$group."/".$database."\">".$db_name_for_dropdown."</option>";
												}
											}
										echo "</select>";
									echo "</div>";
								echo "</div>";
								echo "<div class=\"row 50%\">";
									echo "<div class=\"12u\">";
										echo "<ul class=\"actions\">";
											echo "<li><input type=\"submit\" value=\"Delete database\" /></li>";
										echo "</ul>";
									echo "</div>";
								echo "</div>";
							echo "</form>";
							
							echo "<p style=\"color: red;\">Caution: once a database is deleted it cannot be restored.</p>";
							
						echo "</section>";
					echo "</div>";

					#############################################
					# ANNOTATE DATABASE FORM
					#############################################
					
					echo "<div class=\"row\">";
						echo "<section class=\"6u 12u(narrower)\">";
						
							echo "<h2 style=\"padding-top:10px;\">Annotate a database</h2>";
							
							echo "<p>To unlock the family-based analysis types within Seave you need to specify familial information.</p>";
							
							echo "<p>This is done using a pedigree file (.ped) and the form below allows you to upload a PED file to annotate any database. After the database has been successfully annotated with your pedigree file, the familial analysis page will appear upon selecting the database for analysis.</p>";
								
							echo "<form action=\"actions/action_database_administration\" method=\"post\" enctype=\"multipart/form-data\">";
								echo "<div class=\"row 50%\">";
									echo "<div class=\"12u\">";
										echo "<h4>Database to annotate</h4>";
										echo "<select name=\"database_dropdown\">";
											// Go through each group
											foreach (array_keys($databases) as $group) {
												// Go through each database file
												foreach ($databases[$group] as $database) {
													$db_name_for_dropdown = $group." - ".$database; // Remove the path for printing to the dropdown as the db name
													
													echo "<option value=\"".$group."/".$database."\">".$db_name_for_dropdown."</option>";
												}
											}
										echo "</select>";
									echo "</div>";
									
									echo "<div class=\"12u\">";
										echo "<h4>Upload pedigree file</h4>";
										echo "<input type=\"file\" name=\"pedfile\" id=\"pedfile\"><br>";
									echo "</div>";
								echo "</div>";
								echo "<div class=\"row 50%\">";
									echo "<div class=\"12u\">";
										echo "<ul class=\"actions\">";
											echo "<li><input type=\"submit\" value=\"Annotate database\"></li>";
										echo "</ul>";
									echo "</div>";
								echo "</div>";
							echo "</form>";
						echo "</section>";
						
						#############################################
						# RENAME A DATABASE
						#############################################
					
						echo "<section class=\"6u 12u(narrower)\">";
							
							echo "<h2 style=\"padding-top:10px;\">Rename a database</h2>";
							
							echo "<p>If you need to rename a database, select the database to be renamed from the dropdown below and type in a new name in the box below the dropdown.</p>";
							
							echo "<form action=\"actions/action_database_administration\" method=\"post\" enctype=\"multipart/form-data\">";
								echo "<div class=\"row 50%\">";
									echo "<div class=\"12u\">";
										echo "<h4>Database to rename</h4>";
										echo "<select name=\"database_dropdown\">";
											// Go through each group
											foreach (array_keys($databases) as $group) {
												// Go through each database file
												foreach ($databases[$group] as $database) {
													$db_name_for_dropdown = $group." - ".$database; // Remove the path for printing to the dropdown as the db name
													
													echo "<option value=\"".$group."/".$database."\">".$db_name_for_dropdown."</option>";
												}
											}
										echo "</select>";
									echo "</div>";
									
									echo "<div class=\"12u\">";
										echo "<h4>New filename</h4>";
										echo "<input type=\"text\" name=\"db_new_name\" placeholder=\"new_name.db\">";
									echo "</div>";
								
								echo "</div>";
								echo "<div class=\"row 50%\">";
									echo "<div class=\"12u\">";
										echo "<ul class=\"actions\">";
											echo "<li><input type=\"submit\" value=\"Rename database\"></li>";
										echo "</ul>";
									echo "</div>";
								echo "</div>";
							echo "</form>";
							
							echo "<p style=\"color: red;\">Important: databases must end with a .db suffix.</p>";
							
						echo "</section>";
						
					echo "</div>";
					
					#############################################
					# RE-GENERATE DATABASE SUMMARY
					#############################################
					
					echo "<div class=\"row\">";
						echo "<section class=\"6u 12u(narrower)\">";
						
							echo "<h2 style=\"padding-top:10px;\">Re-generate a summary</h2>";
							
							echo "<p>Database summary reports are automatically generated when a database is imported. However, if changes to Seave are made to modify this report, you may want an updated report.</p>";
								
							echo "<form action=\"actions/action_database_administration\" method=\"post\">";
								echo "<div class=\"row 50%\">";
									echo "<div class=\"12u\">";
										echo "<h4>Database to generate a summary for</h4>";
										echo "<select name=\"regenerate_summary_db_name\">";
											// Go through each group
											foreach (array_keys($databases) as $group) {
												// Go through each database file
												foreach ($databases[$group] as $database) {
													$db_name_for_dropdown = $group." - ".$database; // Remove the path for printing to the dropdown as the db name
													
													echo "<option value=\"".$group."/".$database."\">".$db_name_for_dropdown."</option>";
												}
											}
										echo "</select>";
									echo "</div>";
								echo "</div>";
								echo "<div class=\"row 50%\">";
									echo "<div class=\"12u\">";
										echo "<ul class=\"actions\">";
											echo "<li><input type=\"submit\" value=\"Re-generate summary\" /></li>";
										echo "</ul>";
									echo "</div>";
								echo "</div>";
							echo "</form>";
							
							echo "<p style=\"color: red;\">Important: this report may take several minutes to generate, during which time Seave will be unusable for you.</p>";
						
						echo "</section>";

						#############################################
						# MOVE DATABASE FROM ONE GROUP TO ANOTHER
						#############################################
						
						echo "<section class=\"6u 12u(narrower)\">";
						
							echo "<h2 style=\"padding-top:10px;\">Move database</h2>";
							
							echo "<p>Databases on Seave belong to a single group. To move a database from one group to another, select it from the dropdown below and then select the group to move it to from the second dropdown.</p>";
								
							echo "<form action=\"actions/action_database_administration\" method=\"post\">";
								echo "<div class=\"row 50%\">";
									echo "<div class=\"12u\">";
										echo "<h4>Database to move</h4>";
										echo "<select name=\"move_db_name\">";
											// Go through each group
											foreach (array_keys($databases) as $group) {
												// Go through each database file
												foreach ($databases[$group] as $database) {
													$db_name_for_dropdown = $group." - ".$database; // Remove the path for printing to the dropdown as the db name
													
													echo "<option value=\"".$group."/".$database."\">".$db_name_for_dropdown."</option>";
												}
											}
										echo "</select>";
									echo "</div>";
									
									echo "<div class=\"12u\">";
										echo "<h4>Group to move the database into</h4>";
										echo "<select name=\"move_db_group\">";
											// Go through each group
											foreach (array_keys($databases) as $group) {
												echo "<option value=\"".$group."\">".$group."</option>";
											}
										echo "</select>";
									echo "</div>";
								echo "</div>";
								echo "<div class=\"row 50%\">";
									echo "<div class=\"12u\">";
										echo "<ul class=\"actions\">";
											echo "<li><input type=\"submit\" value=\"Move database\" /></li>";
										echo "</ul>";
									echo "</div>";
								echo "</div>";
							echo "</form>";
						
						echo "</section>";
					echo "</div>";
						
					#############################################
					# VIEW ALL ANNOTATION VERSIONS FOR A GIVEN ANNOTATION
					#############################################
						
					echo "<div class=\"row\">";
						echo "<section class=\"6u 12u(narrower)\">";
						
							echo "<h2 style=\"padding-top:10px;\">View annotation history</h2>";
							
							echo "<p>Annotations within Seave are updated over time. This section allows you to see how a given annotation has been updated including the versions used, the method of update and time of update.</p>";
								
							echo "<form action=\"actions/action_database_administration\" method=\"post\">";
								echo "<div class=\"row 50%\">";
									echo "<div class=\"12u\">";
										echo "<h4>Annotation source</h4>";
										echo "<select name=\"view_annotation_history\">";
											// Go through each annotation
											foreach (array_keys($annotations) as $annotation_name) {
												echo "<option value=\"".$annotation_name."\">".$annotation_name."</option>";
											}
										echo "</select>";
									echo "</div>";
								echo "</div>";
								echo "<div class=\"row 50%\">";
									echo "<div class=\"12u\">";
										echo "<ul class=\"actions\">";
											echo "<li><input type=\"submit\" value=\"View annotation history\" /></li>";
										echo "</ul>";
									echo "</div>";
								echo "</div>";
							echo "</form>";
						
						echo "</section>";
					echo "</div>";
					
				} else {
					error("You do not have permission to view this page.");
					
					echo "<a href=\"home\" class=\"button\">Home</a>";
				}

			?>
		</article>
		
	</div>
</div>

<?php
	
	require 'html_footer.php';
?>