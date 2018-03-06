<?php	
	$body_class = "right-sidebar"; // This page has a right sidebar
	
	require 'php_header.php'; // Require the PHP header housing required PHP functions
	require 'html_header.php'; // Require the HTML header housing the HTML structure of the page
?>

</div>

<!-- Main -->
<div class="wrapper">
	<div class="container" id="main">
		<div class="row 150%">
			<div class="8u 12u(narrower)">

				<!-- Content -->
				<article id="content">
					<header>
						<h2>One or more samples in your database are in the <strong>GBS</strong>.</h2>
						<p>You can use the information in the GBS to determine if samples in your database are affected by various genomic events. This can be at specific coordinates, genes or gene lists. You can also determine whether any genomic events overlap for multiple samples.</p>
					</header>
					
					<!--<a class="image featured"><img src="images/family.jpg" alt="" /></a>-->
					
					<?php

						#############################################
						# ERROR HANDLING
						#############################################
						
						if_set_display_error("gbs_query_insufficient_data", "No family or analysis type specified for analysis."); 
						if_set_display_error("gbs_query_cant_fetch_family_information", "Couldn't obtain information for the families in your database.");
						if_set_display_error("gbs_query_invalid_family", "The family specified for analysis is not in the database selected.");
						if_set_display_error("gbs_query_cant_fetch_overlapping_blocks", "There was a problem fetching overlapping blocks from the GBS.");
						if_set_display_error("gbs_query_cant_fetch_rohmer_blocks", "There was a problem running your ROHmer query.");
						if_set_display_error("gbs_query_cant_fetch_svfusions_blocks", "There was a problem running your SV Fusions query.");
						if_set_display_error("gbs_query_cant_fetch_genomic_coordinates_blocks", "There was a problem running your GBS query for genomic coordinates.");
						if_set_display_error("gbs_query_cant_fetch_gene_lists_blocks", "There was a problem running your GBS query for gene lists.");
						if_set_display_error("gbs_query_cant_create_output_file", "There was a problem outputting the results from the GBS to an output file.");
						if_set_display_error("gbs_query_no_regions_submitted", "You must specify genomic coordinates to filter on.");
						if_set_display_error("gbs_query_invalid_search_regions", "The genomic coordinates you submitted are not in the correct format.");
						if_set_display_error("gbs_query_no_genes_specified", "You must select one or more gene lists or specify one or more custom genes to search.");
						if_set_display_error("gbs_query_cant_determine_gene_list", "There was a problem determining the gene list to search.");
						if_set_display_error("gbs_query_cant_determine_missing_genes", "There was a problem determining whether your genes are mapped to coordinates in the GBS.");
						if_set_display_error("gbs_query_no_genes_remaining_to_query", "None of the genes you submitted are mapped to coordinates to search in the GBS.");

						#############################################
						# PROCESS SUBMITTED DATABASE
						#############################################
							
						// If a query database was selected on the database page and it doesn't equal the previously queried database
						if (isset($_GET["group"], $_GET["query_db"], $_GET["hasped"]) && ($_SESSION["query_group"] != $_GET["group"] || $_SESSION["query_db"] != $_GET["query_db"])) {
							// Make sure the user is in the group specified
							$user_in_group = is_user_in_group($_SESSION["logged_in"]["email"], $_GET["group"]);
							
							// If the user is in the group, clear all session variables then set the query group, database and hasped status to the one selected on the database page
							if ($user_in_group === true) {
								session_update_db($_GET["group"], $_GET["query_db"], $_GET["hasped"]);
							} else {
								error("You do not have access to the group selected.");
							}
						} elseif ($_SESSION["query_db"] == "") { # If no database was selected
							error("You need to select a database before querying the GBS.");
							
							$requirements_failed_flag = 1;
						}
						
						#############################################
						# EXTRACT GENE LISTS FROM THE DB
						#############################################
						
					    $gene_lists = fetch_gene_lists();
					    
					    if ($gene_lists === false) {
						    error("Could not fetch gene lists from the database.");
						    
						    $requirements_failed_flag = 1;
					    }
						
						#############################################
						# EXTRACT FAMILIAL INFORMATION FOR THE DB
						#############################################
						
						if (isset($_SESSION["query_db"], $_SESSION["query_group"]) && $_SESSION["query_db"] != "" && $_SESSION["query_group"] != "") {
							$family_info = extract_familial_information($_SESSION["query_group"]."/".$_SESSION["query_db"]);
							
							if ($family_info === false) {
								error("Problem extracting familial information for the database.");
								
								$requirements_failed_flag = 1;
							}
							
							// Inject an "entiredatabase" family which contains all the samples from the other families - this is needed to print the correct options on the form below
							foreach (array_keys($family_info) as $family_name) {
								foreach (array_keys($family_info[$family_name]) as $sample_name) {
									$family_info["entiredatabase"][$sample_name] = array_merge_recursive($family_info[$family_name][$sample_name]);
								}
							}
						} else {
							$requirements_failed_flag = 1;
						}
						
						#############################################
						
						// If the correct information was fetched from the various databases
						if (!isset($requirements_failed_flag)) {

							#############################################
							# FETCH GBS PRESENCE INFORMATION FOR THE SAMPLES IN THE DB
							#############################################
							
							$GBS_presence = fetch_gbs_samples_presence(array_keys($family_info["entiredatabase"]));
							
							if ($GBS_presence === false) {
								error("There was a problem determining whether the samples in the database are in the GBS.");
								
								$requirements_failed_flag = 1;
							} elseif (count($GBS_presence) == 0) {
								error("None of the samples in your database are in the GBS.");
								
								$requirements_failed_flag = 1;
							}
						}
						
						#############################################
						
						// If the correct information was fetched from the various databases
						if (!isset($requirements_failed_flag)) {
						
							#############################################
							# DISPLAY SELECTED DB INFO
							#############################################
							
							echo "<h3 style=\"padding-top:10px;\">Database selected</h3>";
							echo "<p>".$_SESSION["query_db"]."</p>";
	
							echo "<form action=\"actions/action_gbs_analysis\" method=\"post\">";
								echo "<h3 style=\"padding-bottom:10px;\">Select a family to analyse</h3>";
								
								#############################################
								# FAMILY SELECTION RADIOS
								#############################################
								
								// Print the Entire Dataset option first
								// If there has been no family selected before or the entire dataset has already been selected, select the Entire Dataset option
								echo "<input type=\"radio\" id=\"family_entiredatabase\" name=\"family\" value=\"entiredatabase\" onclick=\"showfamilygbs('entiredatabase');\"";
								if ($_SESSION["gbs_family"] == "" || $_SESSION["gbs_family"] == "entiredatabase") {
									echo " checked>";
								} else {
									echo ">";
								}
								echo "<label for=\"family_entiredatabase\">Entire Dataset</label>";
								
								// If there is more than one family or there is one but it is not the missing family value of zero
								if (count(array_keys($family_info)) > 1 || (count(array_keys($family_info)) == 1 && !isset($family_info[0]))) {
									// Go through every family
									foreach (array_keys($family_info) as $family_id) {
										// Skip the entire database option as it was already printed above
										if ((string) $family_id == "entiredatabase") {
											continue;
										}
										
										echo "<input type=\"radio\" id=\"family_".$family_id."\" name=\"family\" value=\"".$family_id."\" onclick=\"showfamilygbs('$family_id');\"";
										if ($_SESSION["gbs_family"] == (string) $family_id) { # If this is the family previously selected, mark the radio as checked to correspond with the family information below (the (string) forces the family name to be treated as a string which prevents "entiredatabase" being equal to int(0) and the wrong family being selected when one of the families is names zero
											echo " checked>";
										} else {
											echo ">";
										}
										
										echo "<label for=\"family_".$family_id."\">";
										if ($family_id == "None") {
											echo "No Family Specified";
										} else {
											echo $family_id;
										}
										echo "</label>";
									}
								}	
								
								#############################################
								# FAMILY COMPOSITION AND AFFECTED STATUS INFO
								#############################################
								
								echo "<h3 style=\"padding-top:20px; padding-bottom:10px;\">Family information</h3>";
								
								// Go through every family
								foreach (array_keys($family_info) as $family_id) {
									$family_affected_status = family_affected_status($family_info[$family_id]); # Extract the affected and unaffected samples
									
									#############################################
									# PRINT FAMILY INFORMATION
									#############################################
										
									echo "<div class=\"families\" id=\"".$family_id."\"";
										// If the current family is the entire database and either no family has been previously selected for analysis or no family has been selected at all, show this div
										if ((string) $family_id == "entiredatabase" && ($_SESSION["gbs_family"] == "" || $_SESSION["gbs_family"] == "entiredatabase")) {
											echo ">";
										// If this family was previously selected, display its info immediately, otherwise hide it (the (string) forces the family name to be treated as a string which prevents "entiredatabase" being equal to int(0) and the wrong family being selected when one of the families is names zero
										} elseif ($_SESSION["gbs_family"] == (string) $family_id) {
											echo ">";
										} else {
											echo " style=\"display: none\">";
										}
										
										// Do not print family information for the entire database - this can be way too long
										if ((string) $family_id == "entiredatabase") {
											echo "Not applicable.";
										// Do print family information for individual families
										} else {
											foreach (array_keys($family_info[$family_id]) as $sample_name) {
												echo $sample_name;
												
												// Print the gender of the sample
												if ($family_info[$family_id][$sample_name]["sex"] == "1") {
													echo " (Male)";
												} elseif ($family_info[$family_id][$sample_name]["sex"] == "2") {
													echo " (Female)";
												} else {
													echo " (Unknown Gender)";
												}
												
												// Print the affected status of the sample
												if (is_affected($family_info[$family_id][$sample_name]["phenotype"])) {
													echo " - Affected";
												} elseif (is_unaffected($family_info[$family_id][$sample_name]["phenotype"])) {
													echo " - Unaffected";
												} elseif (is_unknown_phenotype($family_info[$family_id][$sample_name]["phenotype"])) {
													echo " - Unknown";
												}
												
												// Print the GBS methods for the sample
												if (isset($GBS_presence[$sample_name])) {
													echo " - <span class=\"gbs_present\">".implode("</span> <span class=\"gbs_present\">", array_keys($GBS_presence[$sample_name]["methods"]))."</span><br>";
												} else {
													echo " - <span class=\"gbs_absent\">No GBS Method(s)</span><br>";
												}
											}
										}
										
										echo "<p style=\"padding-top:6px; font-style:italic;\"><strong>Please ensure this information is correct before proceeding.</strong></p>";

										#############################################
										# DEFINE ANALYSIS TYPES
										#############################################
										
										// Enable/disable each analysis type for the current family by default
										$analysis_types["gene_lists"] = 1;
										$analysis_types["sample_overlaps"] = 0;
										$analysis_types["method_overlaps"] = 0;
										$analysis_types["genomic_coordinates"] = 1;
										$analysis_types["rohmer"] = 0;
										$analysis_types["svfusions"] = 0;
										// Note: when adding a new analysis type, make sure to add it to html_footer.php to correctly show descriptions and query options for selected analysis types
										
										#############################################
										# DETERMINE WHETHER ANALYSIS TYPES ARE APPROPRIATE FOR THE FAMILY
										#############################################
										
										// Conditions to enable the sample overlaps analysis - 2+ samples in the family with the same method
										foreach (array_keys($family_info[$family_id]) as $sample_one) {
											foreach (array_keys($family_info[$family_id]) as $sample_two) {
												// Don't want to compare the same sample against itself
												if ($sample_one == $sample_two) {
													continue;
												}
												
												// If both samples are in the GBS and the sample overlaps analysis has not been enabled yet
												if ($analysis_types["sample_overlaps"] == 0 && isset($GBS_presence[$sample_one]) && isset($GBS_presence[$sample_two])) {
													// Go through the first sample's methods
													foreach (array_keys($GBS_presence[$sample_one]["methods"]) as $sample_one_method) {
														// Check if the method is also present for the second sample
														if (in_array($sample_one_method, array_keys($GBS_presence[$sample_two]["methods"]))) {
															$analysis_types["sample_overlaps"] = 1;
														}
													}
												}
											}
										}
										
										// Conditions to enable the method overlaps analysis - at least one sample has more than one method
										foreach (array_keys($family_info[$family_id]) as $sample) {
											if (isset($GBS_presence[$sample]) && count($GBS_presence[$sample]["methods"]) > 1) {
												$analysis_types["method_overlaps"] = 1;
											}
										}
										
										// Conditions to enable the ROHmer analysis
										foreach ($family_affected_status["affected"] as $sample) {
											if (isset($GBS_presence[$sample]) && in_array("ROHmer", array_keys($GBS_presence[$sample]["methods"]))) {
												$analysis_types["rohmer"] = 1;
											}
										}
										
										// Conditions to enable the SV fusions analysis - at least one sample had one or more BND/INV events
										foreach (array_keys($family_info[$family_id]) as $sample) {
											if (isset($GBS_presence[$sample]) && (in_array("BND", array_keys($GBS_presence[$sample]["event_types"])) || in_array("inversion", array_keys($GBS_presence[$sample]["event_types"])))) {
												$analysis_types["svfusions"] = 1;
											}
										}
									
										#############################################
										# ANALYSIS TYPE RADIOS
										#############################################
										
										echo "<h3 style=\"padding-top:10px; padding-bottom:10px;\">Select an analysis type</h3>";
										
										// Flag for whether each family has data in the GBS
										$family_gbs_data_flag = 0;
										
										// Go through each sample in the family and try to find at least one with data in the GBS
										foreach (array_keys($family_info[$family_id]) as $sample_name) {
											if (isset($GBS_presence[$sample_name])) {
												$family_gbs_data_flag = 1;
												
												// Stop the foreach loop as at least one sample in the family has data in the GBS
												break;
											}
										}
										
										// Go through each analysis type and print radio buttons for each one
										foreach (array_keys($analysis_types) as $analysis_type) {
											echo "<input type=\"radio\" id=\"".$family_id.$analysis_type."\" name=\"".$family_id."analysis_type\" value=\"".$analysis_type."\"";
											
											// Disable all analysis type if no samples in the current family have data in the GBS
											if ($family_gbs_data_flag == 0) {
												echo " disabled=\"\"";
											// Disable analysis types if they have been marked as disabled
											} elseif ($analysis_type == "sample_overlaps" && $analysis_types["sample_overlaps"] == 0) {
												echo " disabled=\"\"";
											} elseif ($analysis_type == "rohmer" && $analysis_types["rohmer"] == 0) {
												echo " disabled=\"\"";
											} elseif ($analysis_type == "svfusions" && $analysis_types["svfusions"] == 0) {
												echo " disabled=\"\"";
											} elseif ($analysis_type == "method_overlaps" && $analysis_types["method_overlaps"] == 0) {
												echo " disabled=\"\"";
											}
											
											// Add onclick javascript events to show analysis descriptions or options and hide/show the CN restriction section based on analysis type
											if ($analysis_type == "gene_lists") {
												echo " onclick=\"showdiv('lists'); document.getElementById('cnrestriction').style.display = 'block';\"";
											} elseif ($analysis_type == "sample_overlaps") {
												echo " onclick=\"showdiv('sample_overlaps'); document.getElementById('cnrestriction').style.display = 'block';\"";
											} elseif ($analysis_type == "method_overlaps") {
												echo " onclick=\"showdiv('method_overlaps'); document.getElementById('cnrestriction').style.display = 'block';\"";
											} elseif ($analysis_type == "genomic_coordinates") {
												echo " onclick=\"showdiv('positions'); document.getElementById('cnrestriction').style.display = 'block';\"";
											} elseif ($analysis_type == "rohmer") {
												echo " onclick=\"showdiv('rohmer'); document.getElementById('cnrestriction').style.display = 'none';\"";
											} elseif ($analysis_type == "svfusions") {
												echo " onclick=\"showdiv('svfusions'); document.getElementById('cnrestriction').style.display = 'block';\"";
											}
											
											// Select the current analysis type if it was previously selected or one has not been selected before
											if (((string) $family_id == $_SESSION["gbs_family"] && $_SESSION["gbs_analysis_type"] == $analysis_type) || ($_SESSION["gbs_analysis_type"] == "" && $analysis_type == "gene_lists") || ((string) $family_id != $_SESSION["gbs_family"] && $analysis_type == "gene_lists")) {
												echo " checked=\"\"";
											}
																
											echo ">";
											
											echo "<label for=\"".$family_id.$analysis_type."\">"; // Radio label
											
												if ($analysis_type == "gene_lists") {
													echo "Gene List(s)";
												} elseif ($analysis_type == "sample_overlaps") {
													echo "Sample Overlaps";
												} elseif ($analysis_type == "method_overlaps") {
													echo "Method Overlaps";
												} elseif ($analysis_type == "genomic_coordinates") {
													echo "Genomic Coordinates";
												} elseif ($analysis_type == "rohmer") {
													echo "ROHmer";
												} elseif ($analysis_type == "svfusions") {
													echo "SV Fusions";
												}
											
											echo "</label>";
										}
									echo "</div>";
								}
										
								#############################################
								# DESCRIPTIONS AND OPTIONS FOR ANALYSIS TYPES
								#############################################
								
								echo "<br>";
								
								// The gene lists selection div
								echo "<div class=\"selection\" id=\"lists\"";
								// If the gene lists analysis type was previously used or nothing was previously used, display it
								if ($_SESSION["gbs_analysis_type"] == "gene_lists" || $_SESSION["gbs_analysis_type"] == "") {
									echo ">";
								} else {
									echo " style=\"display: none;\">";
								}
									echo "<h3 padding-bottom:10px;\">Options</h3>";
									
									echo "<h4>Select one or more gene lists</h4>";
									
									echo "<select name=\"gene_list_selection[]\" id=\"gene_list_selection\" style=\"display: inline;\" size=\"10\" multiple=\"multiple\">";
										foreach (array_keys($gene_lists) as $gene_list_name) {
											// If the current option was previously selected, make it selected
											if (is_array($_SESSION["gbs_gene_list_selection"]) && in_array($gene_list_name, $_SESSION["gbs_gene_list_selection"])) {
												echo "<option value=\"".$gene_list_name."\" selected>".$gene_list_name." (".$gene_lists[$gene_list_name].")</option>";
											} else {
												echo "<option value=\"".$gene_list_name."\">".$gene_list_name." (".$gene_lists[$gene_list_name].")</option>";
											}
										}
									echo "</select>";
									
									echo "<p style=\"padding: 0em 0.5em 0em 0.5em;\" class=\"button\" onclick=\"$('#gene_list_selection option:selected').removeAttr('selected');\">Clear</p>";
								
									echo "<h4>Search custom gene list</h4>";
								
									$input_search_genes = "<input type=\"text\" name=\"genes\" ";
									if ($_SESSION["gbs_gene_list"] != "") { # If the form has already been submitted, retain the values
										$input_search_genes .= "value=\"".$_SESSION["gbs_gene_list"]."\">";
									} else {
										$input_search_genes .= "placeholder=\"e.g. BRCA1;PIK3CA;TP53\">";
									}
									echo $input_search_genes; # Print the box
									echo "<p class=\"query_label\">Separate multiple genes with a semicolon, comma or space.</p>";
								echo "</div>";
								
								// The sample overlapping blocks selection div
								echo "<div class=\"selection\" id=\"sample_overlaps\"";
								// If the overlapping blocks analysis type was not previously used, hide it
								if ($_SESSION["gbs_analysis_type"] == "sample_overlaps") {
									echo ">";
								} else {
									echo " style=\"display: none;\">";
								}
									echo "<h3 padding-bottom:10px;\">Description</h3>";
									
									echo "<p>Overlaps will be returned between samples where all methods are the same.</p>";
								echo "</div>";
								
								// The method overlapping blocks selection div
								echo "<div class=\"selection\" id=\"method_overlaps\"";
								// If the overlapping blocks analysis type was not previously used, hide it
								if ($_SESSION["gbs_analysis_type"] == "method_overlaps") {
									echo ">";
								} else {
									echo " style=\"display: none;\">";
								}
									echo "<h3 padding-bottom:10px;\">Description</h3>";
									
									echo "<p>Overlaps will be returned between methods where the sample is the same.</p>";
								echo "</div>";
								
								// The genomic coordinates blocks selection div
								echo "<div class=\"selection\" id=\"positions\"";
								// If the overlapping blocks analysis type was not previously used, hide it
								if ($_SESSION["gbs_analysis_type"] == "genomic_coordinates") {
									echo ">";
								} else {
									echo " style=\"display: none;\">";
								}
									echo "<h3 padding-bottom:10px;\">Options</h3>";
									
									echo "<h4>Search region(s)</h4>";
								
									$input_search_regions = "<input type=\"text\" name=\"regions\" "; # Start of the box
									if ($_SESSION["gbs_regions"] != "") { # If the form has already been submitted, retain the values
										$input_search_regions .= "value=\"".$_SESSION["gbs_regions"]."\">";
									} else {
										$input_search_regions .= "placeholder=\"e.g. chr2:15483-25583;chr1:37211-67824;chr5;MT\">";
									}
									echo $input_search_regions; # Print the box
									echo "<p class=\"query_label\">Separate multiple regions to search with a <strong>semicolon</strong>.</p>";
								echo "</div>";

								// The ROHmer selection div
								echo "<div class=\"selection\" id=\"rohmer\"";
								// If the ROHmer analysis type was not previously used, hide it
								if ($_SESSION["gbs_analysis_type"] == "rohmer") {
									echo ">";
								} else {
									echo " style=\"display: none;\">";
								}
									echo "<h3 padding-bottom:10px;\">Description</h3>";
									
									echo "<p>First, shared RoH blocks are extracted for affected individuals. Then, any regions within these blocks that are shared with at least one of the unaffected individuals will be removed. The <strong>remaining regions are shared by affected individuals and not present in any unaffected individual</strong>.</p>";
								echo "</div>";
								
								// The SV Fusions selection div
								echo "<div class=\"selection\" id=\"svfusions\"";
								// If the SV Fusions analysis type was not previously used, hide it
								if ($_SESSION["gbs_analysis_type"] == "svfusions") {
									echo ">";
								} else {
									echo " style=\"display: none;\">";
								}
									echo "<h3 padding-bottom:10px;\">Description</h3>";
									
									echo "<p>Returns all translocation and inversion events where one or both of the break points are inside a gene. Select a gene list or enter a manual gene list to restrict your search to specific genes, otherwise don't select anything to search the entire genome.</p>";
									
									echo "<h3 padding-bottom:10px;\">Options</h3>";
									
									echo "<h4>Select one or more gene lists</h4>";
									
									echo "<select name=\"svfusions_gene_list_selection[]\" id=\"svfusions_gene_list_selection\" style=\"display: inline;\" size=\"10\" multiple=\"multiple\">";
										foreach (array_keys($gene_lists) as $gene_list_name) {
											// If the current option was previously selected, make it selected
											if (is_array($_SESSION["gbs_svfusions_gene_list_selection"]) && in_array($gene_list_name, $_SESSION["gbs_svfusions_gene_list_selection"])) {
												echo "<option value=\"".$gene_list_name."\" selected>".$gene_list_name." (".$gene_lists[$gene_list_name].")</option>";
											} else {
												echo "<option value=\"".$gene_list_name."\">".$gene_list_name." (".$gene_lists[$gene_list_name].")</option>";
											}
										}
									echo "</select>";
									
									echo "<p style=\"padding: 0em 0.5em 0em 0.5em;\" class=\"button\" onclick=\"$('#svfusions_gene_list_selection option:selected').removeAttr('selected');\">Clear</p>";
								
									echo "<h4>Search custom gene list</h4>";
								
									$input_search_genes = "<input type=\"text\" name=\"svfusions_genes\" ";
									if ($_SESSION["gbs_svfusions_gene_list"] != "") { # If the form has already been submitted, retain the values
										$input_search_genes .= "value=\"".$_SESSION["gbs_svfusions_gene_list"]."\">";
									} else {
										$input_search_genes .= "placeholder=\"e.g. BRCA1;PIK3CA;TP53\">";
									}
									echo $input_search_genes; # Print the box
									echo "<p class=\"query_label\">Separate multiple genes with a semicolon, comma or space.</p>";
								echo "</div>";
								
								#############################################
								
								// If the form has already been submitted, retain the value
								if ($_SESSION["gbs_cnlessthan"] != "") {
									$default_cn_less_than = $_SESSION["gbs_cnlessthan"];
								// Otherwise determine the default value from the config file
								} elseif (isset($GLOBALS["configuration_file"]["default_query_parameters"]["default_gbs_cnlessthan"]) && is_numeric($GLOBALS["configuration_file"]["default_query_parameters"]["default_gbs_cnlessthan"])) {
									$default_cn_less_than = $GLOBALS["configuration_file"]["default_query_parameters"]["default_gbs_cnlessthan"];
								// Or use a default value if not in config file
								} else {
									$default_cn_less_than = "1.5";
								}
								
								// If the form has already been submitted, retain the value
								if ($_SESSION["gbs_cngreaterthan"] != "") {
									$default_cn_greater_than = $_SESSION["gbs_cngreaterthan"];
								// Otherwise determine the default value from the config file
								} elseif (isset($GLOBALS["configuration_file"]["default_query_parameters"]["default_gbs_cngreaterthan"]) && is_numeric($GLOBALS["configuration_file"]["default_query_parameters"]["default_gbs_cngreaterthan"])) {
									$default_cn_greater_than = $GLOBALS["configuration_file"]["default_query_parameters"]["default_gbs_cngreaterthan"];
								// Or use a default value if not in config file
								} else {
									$default_cn_greater_than = "2.5";
								}
								
								echo "<div class=\"row\" id=\"cnrestriction\"";
								// If the ROHmer analysis type was previously used, hide it
								if ($_SESSION["gbs_analysis_type"] == "rohmer") {
									echo " style=\"display: none;\">";
								} else {
									echo ">";
								}
									// Less than
									echo "<section class=\"6u 12u(narrower)\">";
										echo "<label for=\"cnlessthan\" style=\"display: inline;\">Deletion copy number</label><br>";
										echo "<input type=\"range\" name=\"cnlessthan\" id=\"cnlessthan\" min=\"0\" max=\"2\" step=\"0.1\" value=\"".$default_cn_less_than."\" oninput=\"document.querySelector('#cnlessthanvalue').value = value;\">";
										echo "<output for=\"cnlessthan\" id=\"cnlessthanvalue\">".$default_cn_less_than."</output>";
										
										echo "<p class=\"query_label\">Return deletion variants with a copy number below this value. <strong>To return all deletion variants, set this value to 2.</strong> All variants with no copy number will be returned.</p>";
									echo "</section>";
									
									// Greater than
									echo "<section class=\"6u 12u(narrower)\">";
										echo "<label for=\"cngreaterthan\" style=\"display: inline;\">Gain copy number</label><br>";
										echo "<input type=\"range\" name=\"cngreaterthan\" id=\"cngreaterthan\" min=\"2\" max=\"15\" step=\"0.5\" value=\"".$default_cn_greater_than."\" oninput=\"document.querySelector('#cngreaterthanvalue').value = value;\">";
										echo "<output for=\"cngreaterthan\" id=\"cngreaterthanvalue\">".$default_cn_greater_than."</output>";
										
										echo "<p class=\"query_label\">Return amplified variants with a copy number above this value. <strong>To return all amplified variants, set this value to 2.</strong> All variants with no copy number will be returned.</p>";
									echo "</section>";
								echo "</div>";
								
								echo "<br>";
								
								#############################################
								
								echo "<input type=\"submit\" value=\"Launch query\">";
							
							echo "</form>";
							
							echo "<br /><a href=\"databases?restart=true\" style=\"padding: 0.4em 1em 0.4em 1em;\" class=\"button\">Start over</a>";
						}
					?>
				</article>

			</div>
			<div class="4u 12u(narrower)">

				<!-- Sidebar -->
					<section id="sidebar">
						<section>
							<header>
								<h3>GBS analyses</h3>
							</header>
							<p>You can query the GBS in one of three ways for all samples in your database, or for a subset grouped as a family. Ultimately, the goal of these queries is to obtain a list of genomic blocks that match specific criteria for a set of samples.</p>
						</section>
						<section>
							<header>
								<h3>Gene list(s)</h3>
							</header>
							<a class="image featured"><img src="images/GBS_gene_lists.png" style="width: 60%; margin-right: auto;" alt="" /></a>
							<p>Querying gene lists will return all blocks for all selected samples that overlap with any of the genes in one or more gene lists specified.</p>
						</section>
						<section>
							<header>
								<h3>Overlapping blocks</h3>
							</header>
							<a class="image featured"><img src="images/GBS_overlapping_blocks.png" style="width: 60%; margin-right: auto;" alt="" /></a>
							<p>Blocks will be returned for all selected samples that overlap by one or more bases.</p>
						</section>
						<section>
							<header>
								<h3>Genomic coordinates</h3>
							</header>
							<a class="image featured"><img src="images/GBS_genomic_coordinates.png" style="width: 60%; margin-right: auto;" alt="" /></a>
							<p>Querying using coordinates will return blocks for all selected samples where a block overlaps with one or more samples at one or more bases.</p>
						</section>
					</section>

			</div>
		</div>
	</div>
</div>

<?php
	require 'html_footer.php';
?>