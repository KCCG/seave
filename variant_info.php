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
				<h2>Here's all the <strong>variant information</strong> you could ever want.</h2>
				<p>The table below displays all available information for the selected variant. To select another from the query results, go back and click another row.</p>
			</header>
			<?php
			
				#############################################
				# MAKE SURE A VARIANT WAS SUBMITTED AND SAVE IT
				#############################################    
			    
			    if (isset($_GET["variant"], $_GET["output_filename"]) && (preg_match('/([\w]*?):([0-9]*)\-([0-9]*)/', $_GET["variant"], $matches))) {
			        $chromosome = $matches[1];
			        $start = $matches[2];
			        $end = $matches[3];
			        $output_filename = "temp/".$_GET["output_filename"];
			    } else {
			        error("No/incorrect variant specified.");
			    }
			    
			    #############################################
				# GO THROUGH THE QUERY OUTPUT FILE AND FETCH THE VARIANT OF INTEREST
				#############################################
				
				if (isset($chromosome, $start, $end, $output_filename)) {
					$query_result_file = fopen($output_filename, "r") or error("Unable to open file!");
				    
				    $line_counter = 0;
				    
				    // Go through every line in the query result file
				    while (($line = fgets($query_result_file)) !== false) {
					    if ($line_counter == 0) {
						    $headers = explode("\t", $line); // Create an array of the column headers
						    
						    // Save each of the headers and their position in the output file
						    for ($i = 0; $i < count($headers); $i++) { 
							    $headers_column_numbers[$headers[$i]] = $i;
						    }
					    } else {
						    $tsv_columns = explode("\t", $line); // Create an array of the current columns' contents
							
							// If the row does not have the same number of columns as the header row, ignore it (it's probably a whitespace row or the Gemini query information row)
							if (count($tsv_columns) != count($headers)) { 
								continue;
							}
							
							// If the columns in the current result row match with the query from the URL, save it
				        	if ($tsv_columns[$headers_column_numbers{"Chr"}] == $chromosome && $tsv_columns[$headers_column_numbers{"Start"}] == $start && $tsv_columns[$headers_column_numbers{"End"}] == $end) {
					        	if (!isset($result_row)) {
						        	$result_row = $tsv_columns;
					        	} else {
						        	error("Error: More than one result row found.");
					        	}
				        	}
					    }
					    
			        	$line_counter++;
			        }
			    
				    fclose($query_result_file);
				}
				
				#############################################
				# OUTPUT THE RESULT TO A TABLE
				#############################################
				
				echo "<table id=\"variant_information\" class=\"display\" cellspacing=\"0\" width=\"100%\" style=\"table-layout:fixed\">";
					echo "<thead>";
						echo "<tr>";
							echo "<th>Key</th>";
							echo "<th>Value</th>";
						echo "</tr>";
					echo "</thead>";
					
					echo "<tbody>";
						foreach (array_keys($headers_column_numbers) as $header) {
							echo "<tr>";
								echo "<td>".$header."</td>";
								
								print_table_cell($result_row[$headers_column_numbers[$header]], "", "", "");
							echo "</tr>";	
						}
					echo "</tbody>";
				echo "</table>";
				
				######################
								
				echo "<br><a onclick=\"goBack()\" class=\"button\">Back to results</a>";
				
				echo "<br><br><a href=\"databases?restart=true\" class=\"button\" style=\"padding: 0.4em 1em 0.4em 1em;\">Start over</a>";
			?>
		</article>
		
	</div>
</div>

<?php
	require 'html_footer.php';
?>