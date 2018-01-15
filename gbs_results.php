<?php	
	$body_class = "no-sidebar"; // This page does not have a sidebar
	
	require 'php_header.php'; // Require the PHP header housing required PHP functions
	require 'html_header.php'; // Require the HTML header housing the HTML structure of the page
?>

</div>

<!-- Main -->
<div class="wrapper">
	<div class="container" id="main" style="padding-bottom:0em;">

		<!-- Content -->
		<article id="content">
			<header>
				<h2>Great. It's time for some <strong>results</strong>.</h2>
				<p>The table below displays your blocks. You can save these results by clicking the "Download query results" link below the results table, alternatively, you can share these results by sending the URL of the page to anyone you wish to show the data.</p>
			</header>
			<?php

				#############################################
				# ERROR HANDLING
				#############################################

				// If no database session variable exists or it is empty
				//if_set_display_error("query_no_database", "You have no selected a database to query. Perhaps your session expired and you need to log in again?");
								
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

						if ($result = file($result_file_path, FILE_IGNORE_NEW_LINES)) {
							
							#############################################
							# ADD EXTRA COLUMNS
							#############################################
							
							// Go through every variant
							for ($i = 0; $i < count($result); $i++) {
								// Print the column header
								if ($i == 0) {
									// IGV column
									$result[$i] .= "\tIGV";
								// Print an empty column value for each variant (populated later based on values in other columns)
								} else {
									// IGV column
									$result[$i] .= "\t ";
								}
							}
							
							#############################################
							# GENERATE COLUMN INDEX AND LIST OF HIDDEN COLUMNS
							#############################################
							
							$header = explode("\t", $result[0]); # Split the header row into an array by column
							
							$hidden_columns_gbs = array(); // Define an array with the columns numbers to hide from the results table
							$column_index = array(); // Define an array with the columns numebers to hide from the results table
							
							for ($i = 0; $i < count($header); $i++) { # Create a column index
								$column_index[$header[$i]] = $i; 
								# Format: $column_index[<column name>] = <column number>;
								
								// If the current column is not whitelisted, add it to the array of hidden columns
								if (!in_array($header[$i], $GLOBALS['whitelisted_gbs_results_columns'])) {
									array_push($hidden_columns_gbs, $i);
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
							
							echo "<table id=\"gbs\" class=\"display\" cellspacing=\"0\" width=\"100%\" style=\"table-layout:fixed; display:none;\">";
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
											// Go through each column for the current variant
											for ($i = 0; $i < count($columns); $i++) {
												// If the current column is one of the block coordinates columns
												if ((isset($column_index["Block1 Coordinates"], $column_index["Block2 Coordinates"]) && ($column_index["Block1 Coordinates"] == $i || $column_index["Block2 Coordinates"] == $i)) || (isset($column_index["Block Coordinates"]) && $column_index["Block Coordinates"] == $i) || (isset($column_index["Overlapping Coordinates"]) && $column_index["Overlapping Coordinates"] == $i) || (isset($column_index["ROH Coordinates"]) && $column_index["ROH Coordinates"] == $i)) {
													// Grab the correct coordinate set for the column
													if (isset($column_index["Block1 Coordinates"]) && $column_index["Block1 Coordinates"] == $i) {
														$coordinates = $columns[$column_index["Block1 Coordinates"]];
													} elseif (isset($column_index["Block2 Coordinates"]) && $column_index["Block2 Coordinates"] == $i) {
														$coordinates = $columns[$column_index["Block2 Coordinates"]];
													} elseif (isset($column_index["Block Coordinates"]) && $column_index["Block Coordinates"] == $i) {
														$coordinates = $columns[$column_index["Block Coordinates"]];
													} elseif (isset($column_index["Overlapping Coordinates"]) && $column_index["Overlapping Coordinates"] == $i) {
														$coordinates = $columns[$column_index["Overlapping Coordinates"]];
													} elseif (isset($column_index["ROH Coordinates"]) && $column_index["ROH Coordinates"] == $i) {
														$coordinates = $columns[$column_index["ROH Coordinates"]];
													}
													
													// Extract the block coordinates
													preg_match('/(.*):(.*)\-(.*)/', $coordinates, $matches);
													
													// If the chromosome is the mitochondria (chMT in our DBs), change it to the way UCSC specifies it (chrM)
													if ($matches[1] == "chrMT") {
														$matches[1] = "chrM";
													}
												
													print_table_cell($columns[$i], "https://genome.ucsc.edu/cgi-bin/hgTracks?db=hg19&position=".$matches[1]."%3A".$matches[2]."-".$matches[3], "", "");
												// Populate the IGV column using the block coordinates
												} elseif (isset($column_index["IGV"]) && $column_index["IGV"] == $i) {
													if (isset($column_index["Block1 Coordinates"], $column_index["Block2 Coordinates"])) {
														// Extract both block coordinates
														preg_match('/(.*):(.*)\-(.*)/', $columns[$column_index["Block1 Coordinates"]], $block1_matches);
														preg_match('/(.*):(.*)\-(.*)/', $columns[$column_index["Block2 Coordinates"]], $block2_matches);
														
														// Block 1 button
														$igv_link = "<a href=\"igv_link.php?locus=".$block1_matches[1].":".$block1_matches[2]."-".$block1_matches[3]."\" style=\"padding: 0.4em 0.5em 0.4em 0.5em; line-height: 100%; font-size: 90%;\" class=\"button\" target=\"_blank\" title=\"Navigate to the block 1 coordinates in a currently open IGV session\">&lt;-IGV</a> ";
														// Block2 button
														$igv_link .= "<a href=\"igv_link.php?locus=".$block2_matches[1].":".$block2_matches[2]."-".$block2_matches[3]."\" style=\"padding: 0.4em 0.5em 0.4em 0.5em; line-height: 100%; font-size: 90%;\" class=\"button\" target=\"_blank\" title=\"Navigate to the block 2 coordinates in a currently open IGV session\">IGV-&gt;</a>";
													} elseif (isset($column_index["Block Coordinates"])) {
														// Extract the block coordinates
														preg_match('/(.*):(.*)\-(.*)/', $columns[$column_index["Block Coordinates"]], $matches);
														
														// IGV button
														$igv_link = "<a href=\"igv_link.php?locus=".$matches[1].":".$matches[2]."-".$matches[3]."\" style=\"padding: 0.4em 0.5em 0.4em 0.5em; line-height: 100%; font-size: 90%;\" class=\"button\" target=\"_blank\" title=\"Navigate to the block 1 coordinates in a currently open IGV session\">IGV</a>";
													} elseif (isset($column_index["Overlapping Coordinates"])) {
														// Extract the overlapping block coordinates
														preg_match('/(.*):(.*)\-(.*)/', $columns[$column_index["Overlapping Coordinates"]], $matches);
														
														// IGV button
														$igv_link = "<a href=\"igv_link.php?locus=".$matches[1].":".$matches[2]."-".$matches[3]."\" style=\"padding: 0.4em 0.5em 0.4em 0.5em; line-height: 100%; font-size: 90%;\" class=\"button\" target=\"_blank\" title=\"Navigate to the overlapping coordinates in a currently open IGV session\">IGV</a>";
													} elseif (isset($column_index["ROH Coordinates"])) {
														// Extract the overlapping block coordinates
														preg_match('/(.*):(.*)\-(.*)/', $columns[$column_index["ROH Coordinates"]], $matches);
														
														// IGV button
														$igv_link = "<a href=\"igv_link.php?locus=".$matches[1].":".$matches[2]."-".$matches[3]."\" style=\"padding: 0.4em 0.5em 0.4em 0.5em; line-height: 100%; font-size: 90%;\" class=\"button\" target=\"_blank\" title=\"Navigate to the ROH coordinates in a currently open IGV session\">IGV</a>";
													}
													
													print_table_cell($igv_link, "", "", "");
												} else {
													print_table_cell($columns[$i], "", "", "");
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
							
							#############################################
							# SHOW OR HIDE SPECIFIC COLUMNS
							#############################################
							
							echo "<h3>Show or hide specific columns</h3>";
							
							echo "<p>Click on one or more buttons to dynamically show or hide the columns in the results table above.</p>";
							
							#############################################
							
							column_filter("gbs", $column_index, array("IGV"), "Navigate in IGV");
						}
					}
				}
					
				#############################################
				# NAVIGATION BUTTONS
				#############################################
				
				echo "<br><br>";
				
				echo "<a href=\"gbs_query\" class=\"button\">Back to query options</a>";
								
				echo "<br><br><a href=\"databases?restart=true\" class=\"button\" style=\"padding: 0.4em 1em 0.4em 1em;\">Start over</a>";
			?>
		</article>
		
	</div>
</div>

<?php
	require 'html_footer.php';
?>