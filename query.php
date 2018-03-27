<?php	
	$body_class = "right-sidebar"; // This is a page with a right sidebar
	
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
				<h2><strong>OK</strong>, you have some data. Now <strong>filter it</strong>.</h2>
				<p>Select from the filtration options below.</p>
			</header>
			<a class="image featured"><img src="images/query.jpg" alt="" /></a>
			
			<?php
				
				#############################################
				# ERROR HANDLING
				#############################################
				
				// If no database was selected, the query form should not be displayed
				if ($_SESSION["query_db"] == "" || $_SESSION["query_group"] == "") {
					error("No database and group was selected for query. Your session may have expired, please go back and select a database to analyse.");
					
					echo "<br><a href=\"databases?restart=true\" class=\"button\">Start over</a>";
				// If no family or analysis type were selected but pedigree information exists
				} elseif (if_set_display_error("query_no_family_or_analysis_type", "No family or analysis type was supplied but your database has pedigree information.")) {
				// If the user submitted a group they don't have access to
				} elseif (if_set_display_error("query_no_access_to_group", "You don't have access to the group specified.")) {
				// If a database to analyze exists, go on with showing the form
				} else {
					
					#############################################
					# TEST FOR THE PRESENCE OF CUSTOM COLUMNS BEFORE DISPLAYING THE QUERY FORM
					#############################################
					
					// Test for dbsnp columns
					if (are_custom_columns_present($_SESSION["query_group"], $_SESSION["query_db"], "is_dbsnp_common, is_dbsnp_flagged")) {
						$_SESSION["dbsnp_columns_exist"] = 1;
					}
					
					#############################################
					# DATABASE SELECTED
					#############################################
					
					echo "<div class=\"row\">";
						echo "<section class=\"6u 12u(narrower)\">";
							echo "<h3>Database selected</h3>";	
							
							echo "<p>".$_SESSION["query_db"]."</p>";
						echo "</section>";
						
						#############################################
						# FAMILY SELECTED
						#############################################
						
						echo "<section class=\"6u 12u(narrower)\">";
							echo "<h3>Family selected</h3>";	
							
							if ($_SESSION["family"] == "entiredatabase" || $_SESSION["family"] == "") { // If the "Entire Database" option has been selected on the family page or nothing was selected because the database doesn't include a pedigree, print this
								echo "<p>Entire Database</p>";
							} elseif ($_SESSION["family"] != "entiredatabase" && $_SESSION["family"] != "") { // If there a family has been selected, print it's name
								echo "<p>".$_SESSION["family"]."</p>";
							}
						echo "</section>";
					echo "</div>";
					
					echo "<form action=\"actions/action_run_query\" method=\"post\">";
												
					    echo "<div class=\"row\">";
					    	
					    	// Extract gene lists from the database
					    	$gene_lists = fetch_gene_lists();
					    	
					    	// Extract GE PanelApp panels from the database
					    	$ge_panelapp_panels = fetch_panel_counts_ge_panelapp();
					    	
					    	#############################################
							# ONLY SEARCH GENOMIC LOCATIONS
							#############################################
							
					    	echo "<section class=\"6u 12u(narrower)\">";
								echo "<h3>Inclusion genomic location(s)</h3>";
								
								######################
								
								echo "<h4>Search region(s)</h4>";
								
								$input_search_regions = "<input type=\"text\" name=\"regions\" "; # Start of the box
								if ($_SESSION["regions"] != "") { # If the form has already been submitted, retain the values
									$input_search_regions .= "value=\"".$_SESSION["regions"]."\">";
								} else {
									$input_search_regions .= "placeholder=\"e.g. chr2:15483-25583;chr1:37211-67824;chr5;MT\">";
								}
								echo $input_search_regions; # Print the box
								echo "<p class=\"query_label\">Separate multiple regions to search with a <strong>semicolon</strong>. To search all regions, leave this box blank. Any genes specified will be restricted to these coordinates.</p>";
								
								######################
								
								echo "<h4>Search gene list(s)</h4>";

								echo "<select name=\"gene_list_selection[]\" id=\"gene_list_selection\" style=\"display: inline;\" size=\"5\" multiple=\"multiple\">";
									// If gene lists were fetched from the DB
									if ($gene_lists !== false) {
										foreach (array_keys($gene_lists) as $gene_list_name) {
											// If the current option was previously selected, make it selected
											if (is_array($_SESSION["gene_list_selection"]) && in_array($gene_list_name, $_SESSION["gene_list_selection"])) {
												echo "<option value=\"".$gene_list_name."\" selected>".$gene_list_name." (".$gene_lists[$gene_list_name].")</option>";
											} else {
												echo "<option value=\"".$gene_list_name."\">".$gene_list_name." (".$gene_lists[$gene_list_name].")</option>";
											}
										}
										
										echo "</select>";
										
										echo "<p style=\"padding: 0em 0.5em 0em 0.5em;\" class=\"button\" onclick=\"$('#gene_list_selection option:selected').removeAttr('selected');\">Clear</p>";
									} else {
										echo "</select>"; // Need to close select in order to print error
										
										error("Could not extract gene lists from the database.");
									}
								
								######################
								
								echo "<h4>Genomics England PanelApp Panels</h4>";
								
								echo "<select name=\"panelapp_panel_selection[]\" id=\"panelapp_panel_selection\">";
									echo "<option value=\"None\"";
									// If no panel has been previously selected, show the default value
									if (!isset($_SESSION["panelapp_panel_selection"]) || $_SESSION["panelapp_panel_selection"] == "None" || $_SESSION["panelapp_panel_selection"] == "") {
										echo " selected";
									}
									echo ">Click to select a panel</option>";
									
									// If PanelApp panels were fetched from the DB
									if ($ge_panelapp_panels !== false) {
										foreach (array_keys($ge_panelapp_panels) as $ge_panelapp_panel_name) {
											foreach (array_keys($ge_panelapp_panels[$ge_panelapp_panel_name]) as $ge_panelapp_confidence) {
												// Only interested in high and highmoderate evidence levels
												if (!in_array($ge_panelapp_confidence, array("HighEvidence", "HighModerateEvidence"))) {
													continue;
												}
												
												echo "<option value=\"".$ge_panelapp_panel_name."-".$ge_panelapp_confidence."\"";
												if (isset($_SESSION["panelapp_panel_selection"]) && $_SESSION["panelapp_panel_selection"] == $ge_panelapp_panel_name."-".$ge_panelapp_confidence) {
													echo " selected";
												}
												echo ">".$ge_panelapp_panel_name." - ".$ge_panelapp_confidence." (".$ge_panelapp_panels[$ge_panelapp_panel_name][$ge_panelapp_confidence].")</option>";
											}
										}
										
										echo "</select>";
									} else {
										echo "</select>"; // Need to close select in order to print error
										
										error("Could not extract GE PanelApp panels from the database.");
									}
								echo "<p style=\"padding: 0em 0.5em 0em 0.5em;\" class=\"button\" onclick=\"$('#panelapp_panel_selection option:selected').removeAttr('selected');\">Clear</p>";
								
								######################
								
								echo "<h4>Search custom gene list</h4>";
								
								$input_search_genes = "<input type=\"text\" name=\"genes\" ";
								if ($_SESSION["gene_list"] != "") { # If the form has already been submitted, retain the values
									$input_search_genes .= "value=\"".$_SESSION["gene_list"]."\">";
								} else {
									$input_search_genes .= "placeholder=\"e.g. BRCA1;PIK3CA;TP53\">";
								}
								echo $input_search_genes; # Print the box
								echo "<p class=\"query_label\">Separate multiple genes with a semicolon, comma or space. To search all genes, leave this box blank.</p>";
								
							echo "</section>";
							
							#############################################
							# EXCLUDE GENOMIC LOCATIONS
							#############################################
							
					    	echo "<section class=\"6u 12u(narrower)\">";
								echo "<h3>Exclusion genomic location(s)</h3>";
								
								######################
								
								echo "<h4>Exclude region(s)</h4>";
								$input_exclude_regions = "<input type=\"text\" name=\"exclude_regions\" "; # Start of the box
								if ($_SESSION["exclude_regions"] != "") { # If the form has already been submitted, retain the values
									$input_exclude_regions .= "value=\"".$_SESSION["exclude_regions"]."\">";
								} else {
									$input_exclude_regions .= "placeholder=\"e.g. chr2:15483-25583;chr1:37211-67824;chr5;MT\">";
								}
								echo $input_exclude_regions; # Print the box
								echo "<p class=\"query_label\">Separate multiple regions to exclude with a <strong>semicolon</strong>. To search all regions, leave this box blank. Any genes specified will be restricted to these coordinates.</p>";
								
								######################
								
								echo "<h4>Exclude gene list(s)</h4>";
	
								echo "<select name=\"gene_list_exclusion_selection[]\" id=\"gene_list_exclusion_selection\" style=\"display: inline;\" size=\"5\" multiple=\"multiple\">";
									// If gene lists were fetched from the DB
									if ($gene_lists !== false) {
										foreach (array_keys($gene_lists) as $gene_list_name) {
											// If the current option was previously selected, make it selected
											if (is_array($_SESSION["gene_list_exclusion_selection"]) && in_array($gene_list_name, $_SESSION["gene_list_exclusion_selection"])) {;
												echo "<option value=\"".$gene_list_name."\" selected>".$gene_list_name." (".$gene_lists[$gene_list_name].")</option>";
											} else {
												echo "<option value=\"".$gene_list_name."\">".$gene_list_name." (".$gene_lists[$gene_list_name].")</option>";
											}
										}
										
										echo "</select>";
										
										echo "<p style=\"padding: 0em 0.5em 0em 0.5em;\" class=\"button\" onclick=\"$('#gene_list_exclusion_selection option:selected').removeAttr('selected');\">Clear</p>";
									} else {
										echo "</select>"; // Need to close select in order to print error
										
										error("Could not extract gene lists from the database.");
									}
									
								######################
								
								echo "<h4>Genomics England PanelApp Panels</h4>";
								
								echo "<select name=\"panelapp_panel_exclusion_selection[]\" id=\"panelapp_panel_exclusion_selection\">";
									echo "<option value=\"None\"";
									// If no panel has been previously selected, show the default value
									if (!isset($_SESSION["panelapp_panel_exclusion_selection"]) || $_SESSION["panelapp_panel_exclusion_selection"] == "None" || $_SESSION["panelapp_panel_exclusion_selection"] == "") {
										echo " selected";
									}
									echo ">Click to select a panel</option>";
									
									// If PanelApp panels were fetched from the DB
									if ($ge_panelapp_panels !== false) {
										foreach (array_keys($ge_panelapp_panels) as $ge_panelapp_panel_name) {
											foreach (array_keys($ge_panelapp_panels[$ge_panelapp_panel_name]) as $ge_panelapp_confidence) {
												// Only interested in high and highmoderate evidence levels
												if (!in_array($ge_panelapp_confidence, array("HighEvidence", "HighModerateEvidence"))) {
													continue;
												}
												
												echo "<option value=\"".$ge_panelapp_panel_name."-".$ge_panelapp_confidence."\"";
												if (isset($_SESSION["panelapp_panel_exclusion_selection"]) && $_SESSION["panelapp_panel_exclusion_selection"] == $ge_panelapp_panel_name."-".$ge_panelapp_confidence) {
													echo " selected";
												}
												echo ">".$ge_panelapp_panel_name." - ".$ge_panelapp_confidence." (".$ge_panelapp_panels[$ge_panelapp_panel_name][$ge_panelapp_confidence].")</option>";
											}
										}
										
										echo "</select>";
									} else {
										echo "</select>"; // Need to close select in order to print error
										
										error("Could not extract GE PanelApp panels from the database.");
									}
								echo "<p style=\"padding: 0em 0.5em 0em 0.5em;\" class=\"button\" onclick=\"$('#panelapp_panel_exclusion_selection option:selected').removeAttr('selected');\">Clear</p>";
								
								######################
								
								echo "<h4>Exclude custom gene list</h4>";
								
								$input_exclude_genes = "<input type=\"text\" name=\"exclude_genes\" ";
								if ($_SESSION["exclusion_gene_list"] != "") { # If the form has already been submitted, retain the values
									$input_exclude_genes .= "value=\"".$_SESSION["exclusion_gene_list"]."\">";
								} else {
									$input_exclude_genes .= "placeholder=\"e.g. BRCA1;PIK3CA;TP53\">";
								}
								echo $input_exclude_genes; # Print the box
								echo "<p class=\"query_label\">Separate multiple genes with a semicolon, comma or space. To not exclude any genes, leave this box blank.</p>";
	
							echo "</section>";
							
						echo "</div>";
						
						echo "<div class=\"row\">";
						
							#############################################
							# VARIANT IMPACT
							#############################################
							
							echo "<section class=\"6u 12u(narrower)\">";
								echo "<h3>Impact</h3>";	
								
								######################
								
								echo "<h4>Restrict variants by impact</h4>";
								
								// Loss of function variants only
								echo "<input type=\"radio\" id=\"radiolofimpact\" name=\"variant_impact\" value=\"lof\"";
								if ($_SESSION["search_impact"] == "lof") {
									echo " checked=\"\">";
								} else {
									echo ">";
								}
								echo "<label for=\"radiolofimpact\">Loss of Function</label>";
								
								// High impact variants only
								echo "<input type=\"radio\" id=\"radiohighimpact\" name=\"variant_impact\" value=\"high\"";
								if ($_SESSION["search_impact"] == "high") {
									echo " checked=\"\">";
								} else {
									echo ">";
								}
								echo "<label for=\"radiohighimpact\">High Impact</label>";
								
								// Note on disabling "coding" and "all impacts" when an analysis type is selected:
								// GEMINI stores genotypes as encoded blobs in the SQLite database, in order to check whether a specific sample is HET/HOM_ALT/HOM_REF, it needs to unpack this field
								// Under the right (read: wrong) circumstances, this unpacking has to be carried out on almost every variant in the database which freezes Seave for a long time for the user performing the query
								
								// Examples of where this happens:
								// Running an "All Impacts" comp het query with max variants at 1000 which returns all variant across the whole DB and pipes this into the Perl script (100% CPU usage for 20+ minutes, mostly by the Perl script so this could be reduced)
								// Running an "All Impacts" dominant query with max variants at 1000 where there are many members in the family (5+) so most of the variants in the database have to be unpacked to find 1000 that match the inheritance pattern (~6 minutes/query)
								
								// Basically, enabling "All Impacts" and "Coding Only" leads to unpredictable behaviour where most queries will still be quick, but some will completely freeze the session and stress the server which makes Seave look bad
								
								// Medium and high impact variants only
								echo "<input type=\"radio\" id=\"radiomedhighimpact\" name=\"variant_impact\" value=\"medhigh\"";
								// If the impact previously selected is medhigh or no impact has been selected but an analysis type has been selected (this becomes the default option)
								if ($_SESSION["search_impact"] == "medhigh" || (($_SESSION["search_impact"] == "" || $_SESSION["search_impact"] == "coding" || $_SESSION["search_impact"] == "all") && $_SESSION["analysis_type"] != "analysis_none")) {
									echo " checked=\"\">";
								} else {
									echo ">";
								}
								echo "<label for=\"radiomedhighimpact\">High & Medium Impact</label>";
								
								// Coding variants only
								echo "<input type=\"radio\" id=\"radiocodingimpact\" name=\"variant_impact\" value=\"coding\"";
								// Only check the box if the search impact type has been previously selected and the analysis type is empty
								if ($_SESSION["search_impact"] == "coding" && ($_SESSION["analysis_type"] == "analysis_none" || $_SESSION["analysis_type"] == "")) {
									echo " checked=\"\">";
								// Disable if an analysis type has been selected
								} elseif ($_SESSION["analysis_type"] != "analysis_none" && $_SESSION["analysis_type"] != "") {
									echo " disabled=\"\">";
								} else {
									echo ">";
								}
								echo "<label for=\"radiocodingimpact\">Coding</label>";
	
								// All impacts
								echo "<input type=\"radio\" id=\"radioallimpacts\" name=\"variant_impact\" value=\"all\"";
								// Only check the box if the search impact has not been previously set, or has been set to all, and no analysis type has been supplied
								if (($_SESSION["search_impact"] == "" || $_SESSION["search_impact"] == "all") && ($_SESSION["analysis_type"] == "analysis_none" || $_SESSION["analysis_type"] == "")) {
									echo " checked=\"\">";
								// Disable if an analysis type has been selected
								} elseif ($_SESSION["analysis_type"] != "analysis_none" && $_SESSION["analysis_type"] != "") {
									echo " disabled=\"\">";
								} else {
									echo ">";
								}
								echo "<label for=\"radioallimpacts\">All Impacts</label>";
																
								######################
								
								// If the form has already been submitted, retain the value
								if ($_SESSION["min_cadd"] != "") {
									$default_scaled_cadd_score = $_SESSION["min_cadd"];
								// Determine the default value from the config file
								} elseif (isset($GLOBALS["configuration_file"]["default_query_parameters"]["default_scaled_cadd_score"]) && is_numeric($GLOBALS["configuration_file"]["default_query_parameters"]["default_scaled_cadd_score"])) {
									$default_scaled_cadd_score = $GLOBALS["configuration_file"]["default_query_parameters"]["default_scaled_cadd_score"];
								// Default value if not in config file
								} else {
									$default_scaled_cadd_score = "0";
								}
								
								echo "<h4 style=\"padding-top:20px;\">Minimum scaled CADD score</h4>";
								
								echo "<input type=\"range\" name=\"min_cadd\" id=\"min_cadd\" min=\"0\" max=\"30\" step=\"1\" value=\"".$default_scaled_cadd_score."\" oninput=\"outputUpdateMinCADD(value)\">";
								echo "<output for=\"min_cadd\" id=\"mincaddvalue\">".$default_scaled_cadd_score."</output><br />";
								echo "<p class=\"query_label\"><strong>All variants without CADD scores are returned.</strong> For no minimum scaled CADD score, set this value to 0.</p>";
								
								echo "<script>"; # Function for updating the real-time slider values
									echo "function outputUpdateMinCADD(val) {";
										echo "document.querySelector('#mincaddvalue').value = val;";
									echo "}";
								echo "</script>";
								
							echo "</section>";
						
							echo "<section class=\"6u 12u(narrower)\">";
								echo "<h3>Prevalence</h3>";
								
								######################
								
								// If the form has already been submitted, retain the value
								if ($_SESSION["1000gmaf"] != "") {
									$default_1000g_frequency = $_SESSION["1000gmaf"];
								// Determine the default value for 1000G from the config file
								} elseif (isset($GLOBALS["configuration_file"]["default_query_parameters"]["default_1000g_frequency"]) && is_numeric($GLOBALS["configuration_file"]["default_query_parameters"]["default_1000g_frequency"])) {
									$default_1000g_frequency = $GLOBALS["configuration_file"]["default_query_parameters"]["default_1000g_frequency"];
								// Default value if not in config file
								} else {
									$default_1000g_frequency = "1";
								}
								
								// If the form has already been submitted, retain the value
								if ($_SESSION["espmaf"] != "") {
									$default_esp_frequency = $_SESSION["espmaf"];
								// Determine the default value for ESP from the config file
								} elseif (isset($GLOBALS["configuration_file"]["default_query_parameters"]["default_esp_frequency"]) && is_numeric($GLOBALS["configuration_file"]["default_query_parameters"]["default_esp_frequency"])) {
									$default_esp_frequency = $GLOBALS["configuration_file"]["default_query_parameters"]["default_esp_frequency"];
								// Default value if not in config file
								} else {
									$default_esp_frequency = "1";
								}
								
								// If the form has already been submitted, retain the value
								if ($_SESSION["exacmaf"] != "") {
									$default_exac_frequency = $_SESSION["exacmaf"];
								// Determine the default value for ExAC from the config file
								} elseif (isset($GLOBALS["configuration_file"]["default_query_parameters"]["default_exac_frequency"]) && is_numeric($GLOBALS["configuration_file"]["default_query_parameters"]["default_exac_frequency"])) {
									$default_exac_frequency = $GLOBALS["configuration_file"]["default_query_parameters"]["default_exac_frequency"];
								// Default value if not in config file
								} else {
									$default_exac_frequency = "1";
								}
								
								echo "<h4>Frequency in control databases</h4>";
								
								echo "<label for=\"1000gmaf\" style=\"display: inline;\">1000 Genomes</label><br />";
								echo "<input type=\"range\" name=\"1000gmaf\" id=\"1000gmaf\" min=\"0\" max=\"10\" step=\"0.1\" value=\"".$default_1000g_frequency."\" oninput=\"document.querySelector('#thousandgenomesmafvalue').value = value;\">";
								echo "<output for=\"1000gmaf\" id=\"thousandgenomesmafvalue\">".$default_1000g_frequency."</output>%<br />";
								
								echo "<label for=\"espmaf\" style=\"display: inline;\">ESP</label><br />";
								echo "<input type=\"range\" name=\"espmaf\" id=\"espmaf\" min=\"0\" max=\"10\" step=\"0.1\" value=\"".$default_esp_frequency."\" oninput=\"document.querySelector('#espmafvalue').value = value;\">";
								echo "<output for=\"espmaf\" id=\"espmafvalue\">".$default_esp_frequency."</output>%<br>";
								
								echo "<label for=\"exacmaf\" style=\"display: inline;\">ExAC</label><br />";
								echo "<input type=\"range\" name=\"exacmaf\" id=\"exacmaf\" min=\"0\" max=\"10\" step=\"0.1\" value=\"".$default_exac_frequency."\" oninput=\"document.querySelector('#exacmafvalue').value = value;\">";
								echo "<output for=\"exacmaf\" id=\"exacmafvalue\">".$default_exac_frequency."</output>%";
								
								echo "<p class=\"query_label\">Variants will be returned that are either below the allele frequency set or not present in the database. <strong>For no minimum allele frequency, set the value to 0%.</strong></p>";
								
								// Custom dbsnp columns
								if ($_SESSION["dbsnp_columns_exist"] == 1) {
									if ($_SESSION["exclude_dbsnp_common"] == 1) {
										echo "<input type=\"checkbox\" id=\"is_dbsnp_common\" name=\"is_dbsnp_common\" value=\"true\" checked=\"\">";
									} else {
										echo "<input type=\"checkbox\" id=\"is_dbsnp_common\" name=\"is_dbsnp_common\" value=\"true\">";
									}
									echo "<label for=\"is_dbsnp_common\">Exclude dbSNP Common</label>";
									
									if ($_SESSION["exclude_dbsnp_flagged"] == 1) {
										echo "<input type=\"checkbox\" id=\"is_dbsnp_flagged\" name=\"is_dbsnp_flagged\" value=\"true\" checked=\"\">";
									} else {
										echo "<input type=\"checkbox\" id=\"is_dbsnp_flagged\" name=\"is_dbsnp_flagged\" value=\"true\">";
									}
									echo "<label for=\"is_dbsnp_flagged\">Exclude dbSNP Flagged</label>";
								}
							echo "</section>";	
							
						echo "</div>";
						
						echo "<div class=\"row\">";
							#############################################
							# VARIANT QUALITY
							#############################################
								
					    	echo "<section class=\"6u 12u(narrower)\">";
		
								echo "<h3>Quality</h3>";
								
								######################
								
								// If the form has already been submitted, retain the value
								if ($_SESSION["min_seq_depth"] != "") {
									$default_sequencing_depth = $_SESSION["min_seq_depth"];
								// Determine the default value from the config file
								} elseif (isset($GLOBALS["configuration_file"]["default_query_parameters"]["default_sequencing_depth"]) && is_numeric($GLOBALS["configuration_file"]["default_query_parameters"]["default_sequencing_depth"])) {
									$default_sequencing_depth = $GLOBALS["configuration_file"]["default_query_parameters"]["default_sequencing_depth"];
								// Default value if not in config file
								} else {
									$default_sequencing_depth = "0";
								}
								
								echo "<h4>Minimum sequencing depth in all samples selected</h4>";

								echo "<input type=\"range\" name=\"min_seq_depth\" id=\"min_seq_depth\" min=\"0\" max=\"100\" step=\"10\" value=\"".$default_sequencing_depth."\" oninput=\"outputUpdateNumSeqDepth(value)\">";
								echo "<output for=\"min_seq_depth\" id=\"numminseqdepthvalue\">".$default_sequencing_depth."</output><br />";
								echo "<p class=\"query_label\">For no minimum sequencing depth, set this value to 0.</p>";
								
								echo "<script>"; # Function for updating the real-time slider values
									echo "function outputUpdateNumSeqDepth(val) {";
										echo "document.querySelector('#numminseqdepthvalue').value = val;";
									echo "}";
								echo "</script>";
								
								######################
								
								// If the form has already been submitted, retain the value
								if ($_SESSION["min_qual"] != "") {
									$default_minimum_variant_quality = $_SESSION["min_qual"];
								// Determine the default value from the config file
								} elseif (isset($GLOBALS["configuration_file"]["default_query_parameters"]["default_minimum_variant_quality"]) && is_numeric($GLOBALS["configuration_file"]["default_query_parameters"]["default_minimum_variant_quality"])) {
									$default_minimum_variant_quality = $GLOBALS["configuration_file"]["default_query_parameters"]["default_minimum_variant_quality"];
								// Default value if not in config file
								} else {
									$default_minimum_variant_quality = "0";
								}
								
								echo "<h4>Minimum variant quality</h4>";
							
								echo "<input type=\"range\" name=\"min_qual\" id=\"min_qual\" min=\"0\" max=\"1000\" step=\"20\" value=\"".$default_minimum_variant_quality."\" oninput=\"outputUpdateMinQual(value)\">";
								echo "<output for=\"min_qual\" id=\"minqualvalue\">".$default_minimum_variant_quality."</output><br />";
								echo "<p class=\"query_label\">For no minimum variant quality, set this value to 0.</p>";
								
								echo "<script>"; # Function for updating the real-time slider values
									echo "function outputUpdateMinQual(val) {";
										echo "document.querySelector('#minqualvalue').value = val;";
									echo "}";
								echo "</script>";
								
								######################
								
								// If the form has already been submitted, retain the value
								if ($_SESSION["exclude_failed_variants"] != "") {
									$default_exclude_failed_variants = $_SESSION["exclude_failed_variants"];
								// Determine the default value from the config file
								} elseif (isset($GLOBALS["configuration_file"]["default_query_parameters"]["default_exclude_failed_variants"]) && $GLOBALS["configuration_file"]["default_query_parameters"]["default_exclude_failed_variants"] == 1) {
									$default_exclude_failed_variants = 1;
								// Default value if not in config file
								} else {
									$default_exclude_failed_variants = 0;
								}
								
								echo "<input type=\"checkbox\" id=\"exclude_failed_variants\" name=\"exclude_failed_variants\" value=\"true\"";
									// If the form has not been submitted yet, has been submitted as true or is in the config file as true
									if ($default_exclude_failed_variants == 1) {
										echo " checked=\"\">";
									} else {
										echo ">";
									}
								echo "<label for=\"exclude_failed_variants\">Exclude Failed Variants</label>";
								
								######################
							
							echo "</section>";
							
							#############################################
							# VARIANT TYPES
							#############################################
							
							echo "<section class=\"6u 12u(narrower)\">";
							
								echo "<h3>Variant type(s)</h3>";
								if ($_SESSION["search_variant_type"] == "snp") {
									echo "<input type=\"radio\" id=\"radiosnp\" name=\"variant_type\" value=\"snp\" checked=\"\">";
									echo "<label for=\"radiosnp\">SNPs</label>";
								} else {
									echo "<input type=\"radio\" id=\"radiosnp\" name=\"variant_type\" value=\"snp\">";
									echo "<label for=\"radiosnp\">SNPs</label>";
								}
								
								if ($_SESSION["search_variant_type"] == "indel") {
									echo "<input type=\"radio\" id=\"radioindel\" name=\"variant_type\" value=\"indel\" checked=\"\">";
									echo "<label for=\"radioindel\">INDELs</label>";
								} else {
									echo "<input type=\"radio\" id=\"radioindel\" name=\"variant_type\" value=\"indel\">";
									echo "<label for=\"radioindel\">INDELs</label>";
								}
									
								if ($_SESSION["search_variant_type"] == "" || $_SESSION["search_variant_type"] == "both") {
									echo "<input type=\"radio\" id=\"radioboth\" name=\"variant_type\" value=\"both\" checked=\"\">";
									echo "<label for=\"radioboth\">Both</label>";
								} else {
									echo "<input type=\"radio\" id=\"radioboth\" name=\"variant_type\" value=\"both\">";
									echo "<label for=\"radioboth\">Both</label>";
								}
													
								######################
								
								// Determine the maximum value from the config file
								if (isset($GLOBALS["configuration_file"]["default_query_parameters"]["max_short_variants_to_return"]) && is_numeric($GLOBALS["configuration_file"]["default_query_parameters"]["max_short_variants_to_return"])) {
									$max_short_variants_to_return = $GLOBALS["configuration_file"]["default_query_parameters"]["max_short_variants_to_return"];
								// Default value
								} else {
									$max_short_variants_to_return = "1000";
								}
								
								// If the form has already been submitted, retain the value
								if ($_SESSION["return_num_variants"] != "") {
									$default_short_variants_to_return = $_SESSION["return_num_variants"];
								// Determine the default value from the config file
								} elseif (isset($GLOBALS["configuration_file"]["default_query_parameters"]["default_short_variants_to_return"]) && is_numeric($GLOBALS["configuration_file"]["default_query_parameters"]["default_short_variants_to_return"])) {
									$default_short_variants_to_return = $GLOBALS["configuration_file"]["default_query_parameters"]["default_short_variants_to_return"];
								// Default value if not in config file
								} else {
									$default_short_variants_to_return = "200";
								}
								
								echo "<h3 style=\"padding-top:20px;\">Maximum number of variants to return</h3>";
								
								echo "<input type=\"range\" name=\"num_variants\" id=\"num_variants\" min=\"25\" max=\"".$max_short_variants_to_return."\" step=\"25\" value=\"".$default_short_variants_to_return."\" oninput=\"outputUpdateNumVariants(value)\">";
								echo "<output for=\"num_variants\" id=\"numvariantsvalue\">".$default_short_variants_to_return."</output>";
								
								echo "<script>"; # Function for updating the real-time slider values
									echo "function outputUpdateNumVariants(val) {";
										echo "document.querySelector('#numvariantsvalue').value = val;";
									echo "}";
								echo "</script>";
							echo "</section>";
						echo "</div>";
						
						######################
					
						echo "<br><input type=\"submit\" value=\"Launch query\">";
						
						######################
								
					echo "</form>";
					
					if ($_SESSION["hasped"] == "Yes") { // Only display the back button (to the analysis selection page) when pedigree information is present
						echo "<br><a href=\"analysis_selection\" class=\"button\" style=\"padding: 0.4em 1em 0.4em 1em;\">Back to family and analysis selection</a><br>";
					}
					
					echo "<br><a href=\"databases?restart=true\" class=\"button\" style=\"padding: 0.4em 1em 0.4em 1em;\">Start over</a>";
				}
			?>
		</article>

	</div>
</div>

<?php
	require 'html_footer.php';
?>