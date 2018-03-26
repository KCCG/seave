<?php	
	$body_class = "no-sidebar"; // This page does not have a sidebar
	
	require 'php_header.php'; // Require the PHP header housing required PHP functions
	require 'html_header.php'; // Require the HTML header housing the HTML structure of the page
?>

</div>

<!-- Main -->
<div class="wrapper">
	<div class="container" id="main" style="padding-bottom:1em;">

		<!-- Content -->
		<article id="content">
			<header>
				<h2>Great. It's time for some <strong>results</strong>.</h2>
				<p>The table below displays your variants. <em>Click any row</em> to fetch all GEMINI information for that variant in a separate table.</p>
			</header>
			<?php

			#############################################
			# ERROR HANDLING
			#############################################

			// If no database session variable exists or it is empty
			if_set_display_error("query_no_database", "You have not selected a database to query. Perhaps your session expired and you need to log in again?");
			
			// If one or more POST variables were missing
			if_set_display_error("query_missing_post_variables", "One or more search parameters were not submitted on the query page.");
			
			// If the search region(s) were not correct
			if_set_display_error("query_invalid_search_regions", "There was a problem with the search region(s) you specified.");
			
			// If the exlude search regions were not correct
			if_set_display_error("query_invalid_exclusion_regions", "There was a problem with the exclusion region(s) you specified.");
			
			// If there is a problem creating the full array of genes to search
			if_set_display_error("query_cant_determine_gene_list", "Could not create a combined set of genes to search.");
			
			// If there is a problem creating the full array of genes to exclude from searching
			if_set_display_error("query_cant_determine_exclusion_gene_list", "Could not create a combined set of genes to exclude from searching.");
			
			// If a non-numeric value was specified for the number of variants to return
			if_set_display_error("query_incorrect_number_of_variants", "The number of variants to search submitted was not a number.");
			
			// If the variant type to return is not one of the valid presets
			if_set_display_error("query_incorrect_variant_type", "An invalid variant type to return was specified.");

			// If annotations couldn't be fetched from the DB
			if_set_display_error("query_cant_fetch_annotation_information", "There was a problem fetching Seave annotation information.");
			
			// If the output file for the Gemini query can't be created
			if_set_display_error("query_cant_create_output_file", "Unable to create output file.");
			
			// If the exit code from the Gemini query wasn't zero
			if_set_display_error("query_gemini_query_error", "There was a problem running GEMINI. GEMINI gave an error and quit without outputting any variants.");
			
			// If a result row had more columns than the header row
			if_set_display_error("more_columns_in_result_row_than_header", "One of the result rows had more columns than the header row.");
			
			// If greater detail about the error was saved, display it
			if (isset($_SESSION["query_error"])) {
				error($_SESSION["query_error"]);
				
				unset($_SESSION["query_error"]);
			}
			
			#############################################
			# GRAB ANY ERRORS THAT OCCURRED DURING THE QUERY
			#############################################
			
			$errors_file = "temp/".$_GET["query"].".err";
			
			// If the errors file exists
			if (file_exists($errors_file)) {
				// Read it in
				if ($error_lines = file($errors_file, FILE_IGNORE_NEW_LINES)) {
					foreach ($error_lines as $error_line) {
						echo "<h4 style=\"color:red;\">";
								echo $error_line;
						echo "</h4>";
					}
				}	
			}
			
			#############################################
			# CHECK THAT A QUERY RESULT HAS BEEN SUPPLIED AND EXISTS
			#############################################
			
			if (!isset($_GET["query"])) {
				error("No query result specified.");
			} else {
				$result_file = $_GET["query"].".tsv";
				$result_file_path = "temp/".$_GET["query"].".tsv";
				
				if (!file_exists("temp/".$_GET["query"].".tsv")) {
					error("This query result does not exist. Perhaps it's too old and has been purged?");
				} else {
					if ($result = file($result_file_path, FILE_IGNORE_NEW_LINES)) {
						
						#############################################
						# ADD EXTRA COLUMNS
						#############################################
						
						// Go through every variant
						for ($i = 0; $i < count($result); $i++) {
							// Print the column header
							if ($i == 0) {
								// Impact Summary column
								$result[$i] .= "\tImpact Summary";
								
								// IGV column
								$result[$i] .= "\tIGV";
							// Print an empty column value for each variant (populated later based on values in other columns)
							} else {
								// Impact Summary column
								$result[$i] .= "\t ";
								
								// IGV column
								$result[$i] .= "\t ";
							}
						}
						
						#############################################
						# REGENERATE COLUMN INDEX AND LIST OF HIDDEN COLUMNS
						#############################################
						
						$header = explode("\t", $result[0]); # Split the header row into an array by column
						
						$hidden_columns = array(); # Define an array with the columns numbers to hide from the results table
						$column_index = array(); # Define an array with the columns numebers to hide from the results table
						
						for ($x = 0; $x < count($header); $x++) { # Create a column index
							$column_index[$header[$x]] = $x; # $column_index{<column name>} = <column id/number>;
							
							array_push($hidden_columns, $x); # Put all columns into the hidden columns array for removal below
						}

						foreach ($GLOBALS['default_columns'] as $column) { # Go through every default column
							if (isset($column_index{$column})) { # Make sure the current default column exists in the database
								unset($hidden_columns[$column_index{$column}]); # Remove the key of the current default column from the hidden columns list
							} else {
								error("Could not find default column '$column' in database.");
							}
						}
						
						#############################################
						# TAKE THE RESULTS TABLE OUT OF THE NORMAL PAGE LAYOUT AND GIVE IT A NEW DIV
						#############################################
						
						echo "</article>";
						
						echo "</div>";
						
						echo "<div class=\"container\" id=\"results_table\">";
						
						#############################################
						# TABULATE RESULTS
						#############################################
						
						echo "<table id=\"gemini\" class=\"display\" cellspacing=\"0\" width=\"100%\" style=\"table-layout:fixed; display:none;\">";
							echo "<thead>";
								echo "<tr>";
									foreach ($header as $column) {
										if (strlen($column) > 10) { # If the column is longer than 10 characters, put the text in a tooltip
											echo "<th title=\"".$column."\" style=\"line-height: 100%; font-size: 90%; font-weight: normal; overflow:hidden;\">".$column."</th>";
										} else {
											echo "<th style=\"overflow: hidden; white-space: nowrap; font-size: 90%; font-weight: normal; overflow:hidden;\">".$column."</th>";
										}
									}
								echo "</tr>";
							echo "</thead>";
							
							echo "<tbody>";
								// Go through each variant
								for ($x = 1; $x < count($result); $x++) { // $x = 1 to avoid the header which has already been processed
									$columns = explode("\t", $result[$x]);
									
									// If the current row does not have the same number of columns as the header, go to the next row
									if (count($columns) != count($header)) {
										continue;
									}
									
									echo "<tr>";
										// Go through each column value for the current variant
										for ($i = 0; $i < count($columns); $i++) {
											// Print each database cell and link out to external websites for specific columns
											if (isset($column_index{"Gene"}) && $column_index{"Gene"} == $i) { # Check whether the current cell is in the "Gene" column and add an external link if so
												print_table_cell($columns[$i], "http://www.genecards.org/cgi-bin/carddisp.pl?gene=".$columns[$i], "", "");
											// Check whether the current cell is in the "Variant" column and add an external link to the UCSC browser if so
											} elseif (isset($column_index{"Variant"}) && $column_index{"Variant"} == $i) {
												// Save the chromosome to a variable for potential modification in the link
												$chromosome = $columns[$column_index{"Chr"}];
												
												// If the chromosome is the mitochondria (chMT in our DBs), change it to the way UCSC specifies it (chrM)
												if ($chromosome == "chrMT") {
													$chromosome = "chrM";
												}
												
												print_table_cell($columns[$i], "https://genome.ucsc.edu/cgi-bin/hgTracks?db=hg19&position=".$chromosome."%3A".($columns[$column_index{"Start"}] - 50)."-".($columns[$column_index{"End"}] + 50)."&highlight=hg19.".$chromosome."%3A".($columns[$column_index{"Start"}] + 1)."-".$columns[$column_index{"End"}]."%23f35858", "", "");
											// Check whether the current cell is in the "Transcript" column and add an external link to Ensembl if so
											} elseif (isset($column_index{"Transcript"}) && $column_index{"Transcript"} == $i) {
												print_table_cell($columns[$i], "http://www.ensembl.org/Homo_sapiens/Transcript/Summary?db=core;t=".$columns[$column_index{"Transcript"}], "", "");
											// Link out to ClinVar for the "ClinVar Variation ID" column if there is a value in it
											} elseif (isset($column_index{"ClinVar Variation ID"}) && $column_index{"ClinVar Variation ID"} == $i && $columns[$column_index{"ClinVar Variation ID"}] != "No Result") {
												print_table_cell($columns[$i], "http://www.ncbi.nlm.nih.gov/clinvar/?term=".$columns[$column_index{"ClinVar Variation ID"}], "", "");
											// Link out to dbSNP for the rs_ids column
											} elseif (isset($column_index{"rs_ids"}) && $column_index{"rs_ids"} == $i && $columns[$column_index{"rs_ids"}] != "None") {
												$rs_ids = explode(",", $columns[$column_index{"rs_ids"}]); // Multiple dbSNP ids are comma separated in cells, split by comma so only the first one can be used for the link
												print_table_cell($columns[$i], "http://www.ncbi.nlm.nih.gov/projects/SNP/snp_ref.cgi?searchType=adhoc_search&type=rs&rs=".$rs_ids[0], "", "");
											// Link out to COSMIC for the cosmic_ids column
											} elseif (isset($column_index{"COSMIC ID"}) && $column_index{"COSMIC ID"} == $i && $columns[$column_index{"COSMIC ID"}] != "No Result") {
												$cosmic_numbers = explode(",", $columns[$column_index{"COSMIC ID"}]); // Multiple COSMIC IDs are semicolon separated in cells, split by semicolon so only the first one can be used for the link
												print_table_cell($columns[$i], "http://grch37-cancer.sanger.ac.uk/cosmic/search?q=".$cosmic_numbers[0], "", "");
											// Link out to PanelApp for the Genomics England PanelApp column
											} elseif (isset($column_index["Genomics England PanelApp"]) && $column_index["Genomics England PanelApp"] == $i && $columns[$column_index["Genomics England PanelApp"]] != "No Result") {
												print_table_cell($columns[$i], "https://panelapp.genomicsengland.co.uk/panels/genes/".$columns[$column_index["Gene"]], "", "");								
											// Link out to OMIM for the OMIM Numbers column
											} elseif (isset($column_index{"OMIM Numbers"}) && $column_index{"OMIM Numbers"} == $i && $columns[$column_index{"OMIM Numbers"}] != "None") {
												$omim_numbers = explode(";", $columns[$column_index{"OMIM Numbers"}]); // Multiple OMIM numbers are semicolon separated in cells, split by semicolon so only the first one can be used for the link
												print_table_cell($columns[$i], "http://www.omim.org/entry/".$omim_numbers[0], "", "");
											// Populate the IGV column using the variant coordinate
											} elseif (isset($column_index["IGV"]) && $column_index["IGV"] == $i) {
												$igv_link = "<a href=\"igv_link.php?locus=".$columns[$column_index["Chr"]].":".($columns[$column_index["Start"]] + 1)."\" style=\"padding: 0.4em 0.5em 0.4em 0.5em; font-size: 90%; line-height: 100%;\" class=\"button\" target=\"_blank\" title=\"Navigate to the variant position in a currently open IGV session\">IGV</a>";
												print_table_cell($igv_link, "", "", "");
											// Populate the Impact Summary column using several other columns if they are present
											} elseif (isset($column_index["Impact Summary"]) && $column_index["Impact Summary"] == $i) {
												// Clear the column for printing
												$columns[$i] = "";
												
												$num_damaging_impacts = 0;
												
												######################
												
												// If the OMIM Disorders column is set
												if (isset($column_index["OMIM Disorders"])) {
													// Explode the values by semicolon
													$values = explode(";", $columns[$column_index["OMIM Disorders"]]);
													
													// Go through each value
													foreach ($values as $value) {
														// If a value is not "None" or "<number>:None", flag it
														if ($value != "None" && !preg_match("/[0-9]*:None/", $value)) {
															$omim_disorder_found = 1;
														}
													}
													
													// If an OMIM disorder was found, print an affected box
													if (isset($omim_disorder_found)) {
														// Increase the damaging impacts count
														$num_damaging_impacts++;
														
														$columns[$i] .= "<div style=\"width: 12px; height: 12px; display: inline-block; background-color: #E3170D; margin-right:3px;\" title=\"Has OMIM Disorder\"></div>";
														
														unset($omim_disorder_found);
													// If the OMIM Disorders column is set and is None (by deduction), print an unaffected box
													} else {
														$columns[$i] .= "<div style=\"width: 12px; height: 12px; display: inline-block; background-color: #c9c9c9; margin-right:3px;\" title=\"No OMIM Disorder\"></div>";
													}
												}
												
												######################
												
												// If the Orphanet Disorders is set and is not "None", print an affected box
												if (isset($column_index["Orphanet Disorders"]) && $columns[$column_index["Orphanet Disorders"]] != "None") {
													// Increase the damaging impacts count
													$num_damaging_impacts++;
														
													$columns[$i] .= "<div style=\"width: 12px; height: 12px; display: inline-block; background-color: #E3170D; margin-right:3px;\" title=\"Has Orphanet Disorder\"></div>";
												// If the Orphanet Disorders is set and is "None", print an unaffected box
												} elseif (isset($column_index["Orphanet Disorders"]) && $columns[$column_index["Orphanet Disorders"]] == "None") {
													$columns[$i] .= "<div style=\"width: 12px; height: 12px; display: inline-block; background-color: #c9c9c9; margin-right:3px;\" title=\"No Orphanet Disorder\"></div>";														
												}
												
												######################
												
												if (isset($column_index["ClinVar Clinical Significance"])) {
													if (in_array($columns[$column_index["ClinVar Clinical Significance"]], array("Pathogenic", "Likely_pathogenic", "association", "Affects", "risk_factor"))) {
														// Increase the damaging impacts count
														$num_damaging_impacts++;
														
														$columns[$i] .= "<div style=\"width: 12px; height: 12px; display: inline-block; background-color: #E3170D; margin-right:3px;\" title=\"ClinVar Pathogenic, Likely Pathogenic, Associated, Affects or Risk Factor\"></div>";
													} elseif (in_array($columns[$column_index["ClinVar Clinical Significance"]], array("Benign", "Likely_benign"))) {
														$columns[$i] .= "<div style=\"width: 12px; height: 12px; display: inline-block; background-color: #fff; margin-right:3px;\" title=\"Clinvar Benign or Likely Benign\"></div>";
													} else {
														$columns[$i] .= "<div style=\"width: 12px; height: 12px; display: inline-block; background-color: #c9c9c9; margin-right:3px;\" title=\"Lacking, conflicting or uncertain ClinVar Information\"></div>";
													}
												}
												
												######################
												
												// If the COSMIC ID is set and is not "No Result", print an affected box
												if (isset($column_index["COSMIC ID"]) && $columns[$column_index["COSMIC ID"]] != "No Result") {
													// Increase the damaging impacts count
													$num_damaging_impacts++;
													
													$columns[$i] .= "<div style=\"width: 12px; height: 12px; display: inline-block; background-color: #E3170D; margin-right:3px;\" title=\"Has COSMIC ID\"></div>";
												// If the COSMIC ID is set and is "No Result", print an unaffected box
												} elseif (isset($column_index["COSMIC ID"]) && $columns[$column_index["COSMIC ID"]] == "No Result") {
													$columns[$i] .= "<div style=\"width: 12px; height: 12px; display: inline-block; background-color: #c9c9c9; margin-right:3px;\" title=\"No COSMIC ID\"></div>";														
												}

												######################
												
												// If the CADD Scaled column is set and is >=15, print an affected box
												if (isset($column_index["CADD Scaled"]) && is_numeric($columns[$column_index["CADD Scaled"]]) && $columns[$column_index["CADD Scaled"]] >= 15) {
													// Increase the damaging impacts count
													$num_damaging_impacts++;
														
													$columns[$i] .= "<div style=\"width: 12px; height: 12px; display: inline-block; background-color: #E3170D; margin-right:3px;\" title=\"CADD Scaled >=15\"></div>";
												// If the CADD Scaled column is set but is "None", print a no data box
												} elseif (isset($column_index["CADD Scaled"]) && $columns[$column_index["CADD Scaled"]] == "None") {
													$columns[$i] .= "<div style=\"width: 12px; height: 12px; display: inline-block; background-color: #c9c9c9; margin-right:3px;\" title=\"No CADD Score\"></div>";
												// If the CADD Scaled column is set and <15, print an unaffected box
												} elseif (isset($column_index["CADD Scaled"]) && is_numeric($columns[$column_index["CADD Scaled"]]) && $columns[$column_index["CADD Scaled"]] < 15) {
													$columns[$i] .= "<div style=\"width: 12px; height: 12px; display: inline-block; background-color: #fff; margin-right:3px;\" title=\"CADD Scaled <15\"></div>";														
												}

												######################
												
												// If the "RVIS ExAC 0.05% Percentile" column is set and is <=25%, print an affected box
												if (isset($column_index["RVIS ExAC 0.05% Percentile"]) && is_numeric($columns[$column_index["RVIS ExAC 0.05% Percentile"]]) && $columns[$column_index["RVIS ExAC 0.05% Percentile"]] <= 25) {
													// Increase the damaging impacts count
													$num_damaging_impacts++;
													
													$columns[$i] .= "<div style=\"width: 12px; height: 12px; display: inline-block; background-color: #E3170D; margin-right:3px;\" title=\"RVIS <=25%\"></div>";
												// If the "RVIS ExAC 0.05% Percentile" column is set and "No Result", print a no data box
												} elseif (isset($column_index["RVIS ExAC 0.05% Percentile"]) && $columns[$column_index["RVIS ExAC 0.05% Percentile"]] == "No Result") {
													$columns[$i] .= "<div style=\"width: 12px; height: 12px; display: inline-block; background-color: #c9c9c9; margin-right:3px;\" title=\"No RVIS Value\"></div>";
												// If the "RVIS ExAC 0.05% Percentile" column is set and >25, print an unaffected box
												} elseif (isset($column_index["RVIS ExAC 0.05% Percentile"]) && is_numeric($columns[$column_index["RVIS ExAC 0.05% Percentile"]]) && $columns[$column_index["RVIS ExAC 0.05% Percentile"]] > 25) {
													$columns[$i] .= "<div style=\"width: 12px; height: 12px; display: inline-block; background-color: #fff; margin-right:3px;\" title=\"RVIS >25%\"></div>";														
												}

												######################
												
												// If the PolyPhen Prediction column is set and is "possibly_damaging" or "probably_damaging", print an affected box
												if (isset($column_index["PolyPhen Prediction"]) && ($columns[$column_index["PolyPhen Prediction"]] == "possibly_damaging" || $columns[$column_index["PolyPhen Prediction"]] == "probably_damaging")) {
													// Increase the damaging impacts count
													$num_damaging_impacts++;
													
													$columns[$i] .= "<div style=\"width: 12px; height: 12px; display: inline-block; background-color: #E3170D; margin-right:3px;\" title=\"PolyPhen Possibly or Probably Damaging\"></div>";
												// If the PolyPhen Prediction is set and "None", print a no data box
												} elseif (isset($column_index["PolyPhen Prediction"]) && ($columns[$column_index["PolyPhen Prediction"]] == "None" || $columns[$column_index["PolyPhen Prediction"]] == "")) {
													$columns[$i] .= "<div style=\"width: 12px; height: 12px; display: inline-block; background-color: #c9c9c9; margin-right:3px;\" title=\"No PolyPhen Prediction\"></div>";
												// If the PolyPhen Prediction is set and "benign" or "unknown", print an unaffected box
												} elseif (isset($column_index["PolyPhen Prediction"]) && ($columns[$column_index["PolyPhen Prediction"]] == "benign" || $columns[$column_index["PolyPhen Prediction"]] == "unknown")) {
													$columns[$i] .= "<div style=\"width: 12px; height: 12px; display: inline-block; background-color: #fff; margin-right:3px;\" title=\"PolyPhen Benign or unknown\"></div>";														
												}

												######################
												
												// If the SIFT Prediction column is set and is "deleterious", print an affected box
												if (isset($column_index["SIFT Prediction"]) && in_array($columns[$column_index["SIFT Prediction"]], array("deleterious", "deleterious_low_confidence", "deleterious_-_low_confidence"))) {
													// Increase the damaging impacts count
													$num_damaging_impacts++;
													
													$columns[$i] .= "<div style=\"width: 12px; height: 12px; display: inline-block; background-color: #E3170D; margin-right:3px;\" title=\"SIFT Deleterious\"></div>";
												// If the SIFT Prediction is set and "None", print a no data box
												} elseif (isset($column_index["SIFT Prediction"]) && ($columns[$column_index["SIFT Prediction"]] == "None" || $columns[$column_index["SIFT Prediction"]] == "")) {
													$columns[$i] .= "<div style=\"width: 12px; height: 12px; display: inline-block; background-color: #c9c9c9; margin-right:3px;\" title=\"No SIFT Prediction\"></div>";
												// If the SIFT Prediction is set and "benign", print an unaffected box
												} elseif (isset($column_index["SIFT Prediction"]) && in_array($columns[$column_index["SIFT Prediction"]], array("benign", "tolerated", "tolerated_low_confidence", "tolerated_-_low_confidence"))) {
													$columns[$i] .= "<div style=\"width: 12px; height: 12px; display: inline-block; background-color: #fff; margin-right:3px;\" title=\"SIFT Tolerated or Benign\"></div>";														
												}

												######################
												
												// If the PROVEAN_score column is set
												if (isset($column_index["PROVEAN_score"])) {
													// Explode the values by semicolon
													$values = explode(";", $columns[$column_index["PROVEAN_score"]]);
													
													// Go through each value
													foreach ($values as $value) {
														// Flag for if a numeric value was found (dots in the data rather than numbers sometimes)
														if (is_numeric($value)) {
															$provean_numeric_value_found = 1;
														}
														
														// If a value <= -2.5 is found, flag it
														if (is_numeric($value) && $value <= -2.5) {
															$provean_damaging_found = 1;
														}
													}
													
													// If a damaging value was found, print an affected box
													if (isset($provean_damaging_found)) {
														// Increase the damaging impacts count
														$num_damaging_impacts++;
														
														$columns[$i] .= "<div style=\"width: 12px; height: 12px; display: inline-block; background-color: #E3170D; margin-right:3px;\" title=\"PROVEAN <=-2.5\"></div>";
													// If a numeric value was found, but not below -2.5 (since the above didn't get called), print an unaffected box
													} elseif (isset($provean_numeric_value_found)) {
														$columns[$i] .= "<div style=\"width: 12px; height: 12px; display: inline-block; background-color: #fff; margin-right:3px;\" title=\"PROVEAN >-2.5\"></div>";
													// If no numeric value/no result was found (by deduction), print a no data box
													} else {
														$columns[$i] .= "<div style=\"width: 12px; height: 12px; display: inline-block; background-color: #c9c9c9; margin-right:3px;\" title=\"No PROVEAN Value\"></div>";
													}
													
													unset($provean_numeric_value_found);
													unset($provean_damaging_found);
												}
												
												######################
												
												// Prepend an invisible count of damaging and benign impacts to enable sorting this column on the results page
												$columns[$i] = "<div style=\"font-size: 0%; display: none;\">".$num_damaging_impacts."</div>".$columns[$i];

												######################
												
												print_table_cell($columns[$i], "", "variant_info?variant=".$columns[$column_index["Chr"]].":".$columns[$column_index["Start"]]."-".$columns[$column_index["End"]]."&output_filename=".$result_file, "");													
											} else {
												print_table_cell($columns[$i], "", "variant_info?variant=".$columns[$column_index["Chr"]].":".$columns[$column_index["Start"]]."-".$columns[$column_index["End"]]."&output_filename=".$result_file, "");
											}
										}
									echo "</tr>";
								}
							echo "</tbody>";
						echo "</table>";
						
						#############################################
						# BRING THE REST OF THE PAGE BACK INTO THE NORMAL PAGE LAYOUT
						#############################################
						
						echo "</div>";
						
						echo "<div class=\"container\" id=\"main\">";
						echo "<article id=\"content\">";
						
						#############################################
						# OPTIONS UNDER THE TABLE
						#############################################
						
						// Download query results link
						echo "<div style=\"text-align:right; padding-top:10px;\"><strong><a href=\"".$result_file_path."\">Download query results (.tsv format)</a></strong></div>";
						
						echo "<p style=\"line-height: 2em; font-size: 60%; text-align:right; margin-bottom: 0em;\" onclick=\"restrict_width('results_table');\">Increase/decrease table width</p>";
						// Alternative style with arrow: echo "<div style=\"text-align:right; padding-bottom: 0em;\"><i class=\"fa fa-arrows-h\" style=\"font-size: 500%;\" onclick=\"restrict_width('results_table');\"></i></div>";
						
						// Grab the GEMINI query used
						$gemini_command_file = "temp/".$_GET["query"].".gem";
						
						// If the gemini command file exists
						if (file_exists($gemini_command_file)) {
							// Read it in
							if ($gemini_command = file($gemini_command_file, FILE_IGNORE_NEW_LINES)) {
								echo "<p style=\"line-height: 2em; font-size: 60%; text-align:right;\" onclick=\"toggle('gemini_query');\">Show/hide GEMINI query</p>";
						
								echo "<div id=\"gemini_query\" style=\"display: none\">";
								
									echo "<p style=\"font-size: 60%;\"><strong>Your GEMINI Query</strong>: ".$gemini_command[0]."</p>";
								
								echo "</div>";
							}	
						}
						
						#############################################
						# SHOW OR HIDE SPECIFIC COLUMNS
						#############################################
						
						echo "<h3>Show or hide specific columns</h3>";
						
						echo "<p>Click on one or more buttons in each section to dynamically show or hide the columns in the results table above.</p>";
						
						#############################################
						
						echo "<h4 style=\"padding-bottom:10px;\"><strong>Variant and gene information</strong></h4>";
						
						column_filter("gemini", $column_index, array("Variant", "Type"), "Variant & Type", "checked");
						column_filter("gemini", $column_index, array("Gene", "Impact"), "Gene & Impact", "checked");
						column_filter("gemini", $column_index, array("Quality"), "Variant Quality", "checked");
						
						$gts_ids = preg_grep("/GT */", array_keys($column_index)); # Return all rows matching the regex
						$gts_ids = array_keys(array_flip($gts_ids)); # Flip the keys and values so the sample names are the keys, only keep the keys
						column_filter("gemini", $column_index, $gts_ids, "Genotypes");
						
						column_filter("gemini", $column_index, array("GBS"), "Genome Block Store");
													
						$vaf = preg_grep("/VAF\s.*/", array_keys($column_index)); # Return all rows matching the regex
						$vaf = array_keys(array_flip($vaf)); # Flip the keys and values so the sample names are the keys, only keep the keys
						
						$gq = preg_grep("/GQ\s.*/", array_keys($column_index)); # Return all rows matching the regex
						$gq = array_keys(array_flip($gq)); # Flip the keys and values so the sample names are the keys, only keep the keys
						
						column_filter("gemini", $column_index, $vaf, "Variant Allele Frequency");
						column_filter("gemini", $column_index, $gq, "Genotype Quality");
						column_filter("gemini", $column_index, array("HGVS.c", "HGVS.p"), "HGVS");
													
						column_filter("gemini", $column_index, array("Codon Change", "Transcript", "CDS Position"), "Transcript Impact");
						column_filter("gemini", $column_index, array("AA Change", "AA Length"), "Protein Impact");
						
						// If the custom dbsnp columns exist in the database, show them when the dbsnp button is clicked
						if ($_SESSION["dbsnp_columns_exist"] == 1) {
							column_filter("gemini", $column_index, array("in_dbsnp", "rs_ids", "is_dbsnp_common", "is_dbsnp_flagged"), "dbSNP");
						} else {
							column_filter("gemini", $column_index, array("in_dbsnp", "rs_ids"), "dbSNP");
						}
						
						column_filter("gemini", $column_index, array("Uniprot_acc", "Uniprot_id", "Uniprot_aapos"), "UniProt");
						column_filter("gemini", $column_index, array("Chr", "Start", "End"), "Genomic Location");
						column_filter("gemini", $column_index, array("Ref", "Alt"), "Ref/Alt");
						column_filter("gemini", $column_index, array("Filter"), "VCF FILTER");
						column_filter("gemini", $column_index, array("IGV"), "Navigate in IGV");
						
						#############################################
						
						echo "<h4 style=\"padding-bottom:10px; padding-top:10px;\"><strong>Allele frequencies</strong></h4>";

						column_filter("gemini", $column_index, array("MGRB AF"), "MGRB Allele Frequencies", "checked");
						column_filter("gemini", $column_index, array("aaf_1kg_afr", "aaf_1kg_all", "aaf_1kg_amr", "aaf_1kg_eas", "aaf_1kg_eur", "aaf_1kg_sas"), "1000 Genomes");							
						column_filter("gemini", $column_index, array("aaf_esp_ea", "aaf_esp_aa", "aaf_esp_all"), "ESP");							
						column_filter("gemini", $column_index, array("in_exac", "aaf_exac_all", "aaf_adj_exac_afr", "aaf_adj_exac_all", "aaf_adj_exac_amr", "aaf_adj_exac_eas", "aaf_adj_exac_fin", "aaf_adj_exac_nfe", "aaf_adj_exac_oth", "aaf_adj_exac_sas"), "ExAC");
						column_filter("gemini", $column_index, array("MITOMAP AF"), "MITOMAP");

						#############################################
						
						echo "<h4 style=\"padding-bottom:10px; padding-top:10px;\"><strong>Disease phenotypes</strong></h4>";
						
						column_filter("gemini", $column_index, array("Impact Summary"), "Impact Summary", "checked");
						column_filter("gemini", $column_index, array("OMIM Numbers", "OMIM Titles", "OMIM Status", "OMIM Disorders"), "OMIM");
						column_filter("gemini", $column_index, array("Orphanet Disorders"), "Orphanet");
						column_filter("gemini", $column_index, array("ClinVar Variation ID", "ClinVar Clinical Significance", "ClinVar Trait"), "ClinVar");							
						column_filter("gemini", $column_index, array("COSMIC ID", "COSMIC Count", "COSMIC Primary Site", "COSMIC Primary Histology"), "COSMIC");
						column_filter("gemini", $column_index, array("Genomics England PanelApp"), "Genomics England PanelApp");
						column_filter("gemini", $column_index, array("CGC Associations", "CGC Mutation Types", "CGC Translocation Partners"), "COSMIC Census");
						column_filter("gemini", $column_index, array("MITOMAP Disease"), "MITOMAP");
						
						#############################################
						
						echo "<h4 style=\"padding-bottom:10px; padding-top:10px;\"><strong>Functional prediction and conservation scores</strong></h4>";
						
						column_filter("gemini", $column_index, array("CADD Scaled", "CADD Raw"), "CADD Scores");
						column_filter("gemini", $column_index, array("RVIS ExAC 0.05% Percentile"), "RVIS Percentile");						
						column_filter("gemini", $column_index, array("FATHMM_score", "FATHMM_rankscore", "FATHMM_pred"), "FATHMM");							
						column_filter("gemini", $column_index, array("MetaLR_score", "MetaLR_rankscore", "MetaLR_pred"), "MetaLR");							
						column_filter("gemini", $column_index, array("MetaSVM_score", "MetaSVM_rankscore", "MetaSVM_pred"), "MetaSVM");														
						column_filter("gemini", $column_index, array("PROVEAN_score", "PROVEAN_pred"), "PROVEAN");
						column_filter("gemini", $column_index, array("GERP++_NR"), "GERP++");
						column_filter("gemini", $column_index, array("SIFT Prediction", "SIFT Score"), "SIFT");
						column_filter("gemini", $column_index, array("PolyPhen Prediction", "PolyPhen Score"), "PolyPhen2");
					}
				}
			}
				
			#############################################
			# NAVIGATION BUTTONS
			#############################################
			
			echo "<br><br>";
			
			echo "<a href=\"query\" class=\"button\">Back to query options</a>";
			
			if ($_SESSION["hasped"] == "Yes") { // Only display the back button (to the analysis selection page) when pedigree information is present
				echo "<br><br><a href=\"analysis_selection\" class=\"button\" style=\"padding: 0.4em 1em 0.4em 1em;\">Back to family and analysis selection</a>";
			}
			
			echo "<br><br><a href=\"databases?restart=true\" class=\"button\" style=\"padding: 0.4em 1em 0.4em 1em;\">Start over</a>";
			?>
		</article>
		
	</div>
</div>

<?php
	require 'html_footer.php';
?>