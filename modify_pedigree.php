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
					<h2>Let's change things up.</h2>
					<p>You can modify the pedigree information of any database you have access to on the web without needing to create a pedigree (ped) file. Simply modify and submit the form below and the database will be updated.</p>
				</header>
				
				<?php
					// Check whether the user is in the group specified
					if (isset($_GET["group"])) {
						$user_in_group = is_user_in_group($_SESSION["logged_in"]["email"], $_GET["group"]);
					} elseif (isset($_POST["group"])) {
						$user_in_group = is_user_in_group($_SESSION["logged_in"]["email"], $_POST["group"]);
					}
					
					// Make sure the user is logged in
					if (!is_user_logged_in()) {
						error("You must be logged in to make modifications to databases.");
					} elseif (!isset($user_in_group) || $user_in_group !== true) {
						error("You do not have access to the group specified.");
					} else {
						
						#############################################
						# IF THE PEDIGREE MODIFICATION FORM HAS BEEN SUBMITTED
						#############################################
						
						if (isset($_POST["database"], $_POST["num_samples"], $_POST["group"])) {							
							$form_error = 0;
							
							// Make sure there are more than 0 samples
							if ($_POST["num_samples"] > 0) {
								// Go through every sample that should have been submitted
								for ($i = 1; $i <= $_POST["num_samples"]; $i++) {
									// Make sure the sample name has been set (required for phenotype and gender)
									if (isset($_POST["sample_".$i])) {
										// If the sample name contains periods (.), that will come through in the value of $_POST["sample_".$i], however, for the _phenotype, _gender, etc PHP converts these dots to underscores, so do a preg replace to for compatibility
										$safe_php_sample_name = preg_replace("/\./", "_", $_POST["sample_".$i]);
										
										// Make sure the phenotype and gender have been submitted for the sample
										if (!isset($_POST[$safe_php_sample_name."_phenotype"]) || !isset($_POST[$safe_php_sample_name."_gender"]) || !isset($_POST[$safe_php_sample_name."_family"])) {
											error("A phenotype, gender or family name have not been supplied for sample ID ".$i."(".$_POST["sample_".$i].").");
											
											$form_error = 1; // Set the form error flag to true to stop further processing
											
											break; // Stop the loop execution
										}
									} else {
										error("Sample name not specified for sample ID ".$i);
										
										$form_error = 1; // Set the form error flag to true to stop further processing
										
										break; // Stop the loop execution
									}
								}
							} else {
								error("The number of samples must be 1 or more.");
							}
							
							// If an error has occurred with the from, don't go any further with processing it
							if ($form_error == 1) {
								error("An error has occurred with processing the pedigree modification page. Please go back and try again.");
							} else {
								// Create a path to a temporary ped file based on an md5 hash of the database name
								$output_filename = "/tmp/".md5($_POST["database"]).".ped";
								
								// Open the temporary file for writing
								if ($output = fopen($output_filename, "w")) {
									// Go through every sample that was submitted
									for ($i = 1; $i <= $_POST["num_samples"]; $i++) {
										// If the sample name contains periods (.), that will come through in the value of $_POST["sample_".$i], however, for the _phenotype, _gender, etc PHP converts these dots to underscores, so do a preg replace to for compatibility
										$safe_php_sample_name = preg_replace("/\./", "_", $_POST["sample_".$i]);
										
										// Set the gender to the PED file equivalent
										if ($_POST[$safe_php_sample_name."_gender"] == "male") {
											$gender = "1";
										} elseif ($_POST[$safe_php_sample_name."_gender"] == "female") {
											$gender = "2";
										} else {
											$gender = "3";
										}
										
										// Set the phenotype to the PED file equivalent
										if ($_POST[$safe_php_sample_name."_phenotype"] == "affected") {
											$phenotype = "2";
										} elseif ($_POST[$safe_php_sample_name."_phenotype"] == "unaffected") {
											$phenotype = "1";
										} elseif ($_POST[$safe_php_sample_name."_phenotype"] == "unknown") {
											$phenotype = "-9";
										}
										
										// If the family submitted was empty, set it to 0 (i.e. None value)
										if ($_POST[$safe_php_sample_name."_family"] == "") {
											$family = "0";
										} else {
											$family = htmlspecialchars($_POST[$safe_php_sample_name."_family"], ENT_QUOTES, 'UTF-8');
										}
										
										// Write each sample to the temporary PED file
										fwrite($output, $family."\t".$_POST["sample_".$i]."\t0\t0\t".$gender."\t".$phenotype."\n");
									}
									
									// Close the temporary file
									fclose($output);
									
									// Annotate the Gemini database with the new information
									exec($GLOBALS["configuration_file"]["gemini"]["binary"].' amend --sample '.$output_filename.' '.$GLOBALS["configuration_file"]["gemini"]["db_dir"].'/'.$_POST["group"].'/'.escape_database_filename($_POST["database"]), $query_result, $exit_code); # Execute the Gemini query

									if ($exit_code == 0) {
										log_website_event("Manually modified pedigree information using the web form for database '".$_POST['database']."'");
										
										echo "<h3>Successfully updated the pedigree annotation for the database ".$_POST['database']."!</h3>";
										
										echo "<br><br><a href=\"analysis_selection?group=".$_POST["group"]."&query_db=".$_POST["database"]."&hasped=Yes\" class=\"button\">Start filtering variants in this database</a>";
										
										echo "<br><br><a href=\"modify_pedigree?group=".$_POST["group"]."&query_db=".$_POST["database"]."\" class=\"button\" style=\"padding: 0.4em 1em 0.4em 1em;\">Back to modifying this database</a>";
										
										echo "<br><br><a href=\"databases?restart=true\" class=\"button\" style=\"padding: 0.4em 1em 0.4em 1em;\">Back to databases</a>";
									} else {
										error("Could not update annotation. GEMINI error.");
									}
									
									// Delete the temporary PED file
									unlink($output_filename);
									
									// Delete the TSV cache for the database forcing a refresh of the PED status
									delete_database_cache($GLOBALS["configuration_file"]["gemini"]["db_dir"]."/".$_POST["group"]."/".$_POST["database"], "tsv_only"); // Only delete the TSV with the second parameter, don't want to delete the DB summaries
								} else {
									error("Cannot create temporary ped file.");
								}
							}
							
						#############################################
						# IF A DATABASE HAS BEEN SUPPLIED FOR EDITING
						#############################################
						
						} elseif (isset($_GET["query_db"], $_GET["group"])) {
							// Extract familial information from the database for each family
							if ($family_info = extract_familial_information($_GET["group"]."/".$_GET["query_db"])) {
								echo "<div class=\"row\">";
									echo "<section style=\"padding-top:20px;\">";
										echo "<h3>Database selected</h3>";	
										
										echo "<p>".htmlspecialchars($_GET["query_db"], ENT_QUOTES, 'UTF-8')."</p>";
									echo "</section>";
								echo "</div>";
								
								echo "<section style=\"padding-top:20px;\">";
									echo "<h3>View and modify pedigree information</h3>";
									
									echo "<form method=\"post\" style=\"padding-top:10px;\" action=\"modify_pedigree\">";
										echo "<input type=\"hidden\" name=\"group\" value=\"".htmlspecialchars($_GET["group"], ENT_QUOTES, 'UTF-8')."\">"; // Hidden field for the database to modify
										echo "<input type=\"hidden\" name=\"database\" value=\"".htmlspecialchars($_GET["query_db"], ENT_QUOTES, 'UTF-8')."\">"; // Hidden field for the database to modify
										
										echo "<div class=\"row 25%\">";
											echo "<div class=\"3u 3u(mobile)\">";
												echo "Sample";
											echo "</div>";
											echo "<div class=\"3u 3u(mobile)\">";
												echo "Family";
											echo "</div>";
											echo "<div class=\"3u 3u(mobile)\">";	
												echo "Phenotype";
											echo "</div>";
											echo "<div class=\"2u$ 2u(mobile)\">";
												echo "Gender";
											echo "</div>";
										echo "</div>";
										
										$sample_counter = 1; // Counter for the number of samples - to be used in a hidden submit field
										
										// Define a variable to store the family's pedigree for output to a PED file
										$ped_output = "";
										
										// Go through every family
										foreach (array_keys($family_info) as $family) {
											// Go through every sample
											foreach (array_keys($family_info[$family]) as $sample) {
												$safe_php_sample_name = htmlspecialchars($sample, ENT_QUOTES, 'UTF-8');
												
												echo "<div class=\"row 25%\">";
													// The static sample name
													echo "<div class=\"3u 3u(mobile)\">";
														echo "<p style=\"padding-top:0.5em; min-width: 200px;\">".$safe_php_sample_name."</p>";
														echo "<input type=\"hidden\" name=\"sample_".$sample_counter."\" value=\"".$safe_php_sample_name."\">";
														
														$sample_counter++; // Iterate the sample counter for the next sample
													echo "</div>";
													
													// The family name input box
													echo "<div class=\"3u 3u(mobile)\">";
														echo "<input name=\"".$safe_php_sample_name."_family\" type=\"text\" style=\"padding: 0.5em; width:90%;\" value=\"".htmlspecialchars($family, ENT_QUOTES, 'UTF-8')."\">";
													echo "</div>";
													
													// The phenotype radios
													echo "<div class=\"3u 3u(mobile)\">";
														echo "<input type=\"radio\" id=\"".$safe_php_sample_name."_phenotype_affected\" name=\"".$safe_php_sample_name."_phenotype\" value=\"affected\"";
														
														// Check the radio if the sample is affected
														if (is_affected($family_info[$family][$sample]["phenotype"])) {
															echo " checked=\"\"";
														}
														
														echo ">";
														echo "<label for=\"".$safe_php_sample_name."_phenotype_affected\" style=\"width:80px;\">Affected</label>"; // Radio label
														
														echo "<input type=\"radio\" id=\"".$safe_php_sample_name."_phenotype_unaffected\" name=\"".$safe_php_sample_name."_phenotype\" value=\"unaffected\"";
														
														// Check the radio if the sample is unaffected
														if (is_unaffected($family_info[$family][$sample]["phenotype"])) {
															echo " checked=\"\"";
														}
														
														echo ">";
														echo "<label for=\"".$safe_php_sample_name."_phenotype_unaffected\" style=\"width:80px;\">Unaffected</label>"; // Radio label
														
														echo "<input type=\"radio\" id=\"".$safe_php_sample_name."_phenotype_unknown\" name=\"".$safe_php_sample_name."_phenotype\" value=\"unknown\"";
														
														// Check the radio if the sample is unknown
														if (is_unknown_phenotype($family_info[$family][$sample]["phenotype"])) {
															echo " checked=\"\"";
														}
														
														echo ">";
														echo "<label for=\"".$safe_php_sample_name."_phenotype_unknown\" style=\"width:80px;\">Unknown</label>"; // Radio label
													echo "</div>";
													
													// The gender radios
													echo "<div class=\"2u$ 2u(mobile)\">";
														echo "<input type=\"radio\" id=\"".$safe_php_sample_name."_gender_male\" name=\"".$safe_php_sample_name."_gender\" value=\"male\"";
														
														// Check the radio if the sample is male
														if ($family_info[$family][$sample]["sex"] == "1") {
															echo " checked=\"\"";
														}
														
														echo ">";
														echo "<label for=\"".$safe_php_sample_name."_gender_male\" style=\"width:40px;\">M</label>"; // Radio label
														
														echo "<input type=\"radio\" id=\"".$safe_php_sample_name."_gender_female\" name=\"".$safe_php_sample_name."_gender\" value=\"female\"";
														
														// Check the radio if the sample is female
														if ($family_info[$family][$sample]["sex"] == "2") {
															echo " checked=\"\"";
														}
														
														echo ">";
														echo "<label for=\"".$safe_php_sample_name."_gender_female\" style=\"width:40px;\">F</label>"; // Radio label
														
														echo "<input type=\"radio\" id=\"".$safe_php_sample_name."_gender_unknown\" name=\"".$safe_php_sample_name."_gender\" value=\"unknown\"";
														
														// Check the radio if the sample is not male or female (i.e. unknown)
														if ($family_info[$family][$sample]["sex"] != "1" && $family_info[$family][$sample]["sex"] != "2") {
															echo " checked=\"\"";
														}
														
														echo ">";
														echo "<label for=\"".$safe_php_sample_name."_gender_unknown\" style=\"width:40px;\">U</label>"; // Radio label
													echo "</div>";
												echo "</div>";
																									
												#############################################
												# PROCESS AND SAVE FAMILIAL INFORMATION IN PED FORMAT
												#############################################
												
												// Check whether the gender of the sample has not been set and change it to the unknown value if so
												if ($family_info[$family][$sample]["sex"] == "None") {
													$sex_ped_output = "-9";
												} else {
													$sex_ped_output = $family_info[$family][$sample]["sex"];
												}
												
												// Check whether the phenotype of the sample has not been set and change it to the unknown value if so
												if ($family_info[$family][$sample]["phenotype"] == "None") {
													$phenotype_ped_output = "-9";
												} else {
													$phenotype_ped_output = $family_info[$family][$sample]["phenotype"];
												}
												
												// Save a row to the pedigree output file variable in case the user wants to download it as a file
												$ped_output .= $family."\t".$sample."\t0\t0\t".$sex_ped_output."\t".$phenotype_ped_output."\n";
											}
										}
												
										echo "<input type=\"hidden\" name=\"num_samples\" value=\"".($sample_counter - 1)."\">";
										
										echo "<div class=\"row 50%\">";
											echo "<div class=\"12u\">";
												echo "<ul class=\"actions\">";
													echo "<li><input type=\"submit\" value=\"Modify pedigree\" /></li>";
												echo "</ul>";
											echo "</div>";
										echo "</div>";
									echo "</form>";
									
									// Form for downloading the pedigree as a file
									echo "<form method=\"post\" action=\"download_pedigree\">";
										echo "<input type=\"submit\" value=\"Download current pedigree as PED file\" style=\"padding: 0.4em 1em 0.4em 1em;\">";
										echo "<input type=\"hidden\" name=\"filename\" value=\"".$_GET["query_db"]."\">"; // Hidden field for the database filename to use for the PED filename
										echo "<input type=\"hidden\" name=\"content\" value=\"".$ped_output."\">"; // Hidden field for the database to modify
									echo "</form><br>";
									
									echo "<a href=\"databases?restart=true\" class=\"button\" style=\"padding: 0.4em 1em 0.4em 1em;\">Back to databases</a>";
								echo "</section>";
							}
						}
					}
				?>
			</article>
	</div>
</div>

<?php
	require 'html_footer.php';
?>