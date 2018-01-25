<?php
	
#############################################
# PRINT A CELL OF A TABLE
#############################################

function print_table_cell($content, $new_tab_link, $same_tab_link, $color) {
	echo "<td ";
	
	// If the content contains a link/div/image, remove it for the tooltip text but use the whole variable for the actual cell value to keep the link in place
	$content_tooltip = preg_replace("/<a href.*>/", "", $content);
	$content_tooltip = preg_replace("/&nbsp;/", "", $content_tooltip);
	$content_tooltip = preg_replace("/<div.*>/", "", $content_tooltip);
	$content_tooltip = preg_replace("/<img.*>/", "", $content_tooltip);
	
	// Check whether the content is greater than 10 characters in length and print a tooltip if so
	if (strlen($content_tooltip) > 10) { # If the column has a lot of text (e.g. is a long INDEL) it will be truncated to make the table fit the page, the full content is put in a tooltip so the user can still see it
		echo "title=\"".$content_tooltip."\" ";
	}
	
	// If a link to open in a new tab has been supplied (takes priority over a same tab link)
	if ($new_tab_link != "") {
		echo "onclick=\"window.open('".$new_tab_link."');\" ";
	// If a link to open in the same tab has been supplied
	} elseif ($same_tab_link != "") {
		echo "onclick=\"window.location.href='".$same_tab_link."';\" ";
	}
	
	// If a color has been supplied for the cell text
	if ($color != "") {
		echo "style=\"overflow: hidden; white-space: nowrap; color: ".$color.";";
	} else {
		echo "style=\"overflow: hidden; white-space: nowrap;";
	}
	
	// Cursor to pointer when a $new_tab_link was supplied to differentiate these cells as linking out to another website
	if ($new_tab_link != "") {
		echo " cursor: pointer;";
	}
	
	echo "\">";
	
	// If a link to open in a new tab has been supplied, create a placeholder <a> which will highlight the text as if it's a link to show the user that the cell links out to something else
	if ($new_tab_link != "") { # If a link is supplied, it needs to be applied inside the <td> while keeping the title= as the original content
		echo "<a>";
	}
	
	// Print the cell content
	echo $content;
	
	if ($new_tab_link != "") {
		echo "</a>";
	}
	
	echo "</td>";
}

#############################################
# PRINT DATABASE TABLE GIVEN DB INFORMATION
#############################################

function database_table($db_information) {
	// Define an array to hold unique sample names
	$unique_samples = array();
	
	// Go through each database and add the samples to an array
	foreach (array_keys($db_information) as $group) {
		foreach (array_keys($db_information[$group]) as $file) {
			$unique_samples = array_merge($unique_samples, explode(";", $db_information[$group][$file]["Sample Names"]));
		}
	}
	
	// Save unique samples from the total samples array
	$unique_samples = array_values(array_unique($unique_samples));
	
	// Check for GBS presence for all unique samples
	$GBS_presence = fetch_gbs_samples_presence($unique_samples);
	
	// If the GBS query failed
	if ($GBS_presence === false) {
		return false;
	}
	
	echo "<table id=\"db_information\" class=\"display\" cellspacing=\"0\" width=\"100%\" style=\"table-layout:fixed\">";
		echo "<thead>";
			echo "<tr>";
				echo "<th>Database</th>";
				
				echo "<th>Group</th>";
				
				// Print the header row populated from the first database information (they are all the same)
				$first_group = array_keys($db_information)[0];
				$first_db_in_first_group = array_keys($db_information[$first_group])[0];		
				
				// Go through each database information column
				foreach (array_keys($db_information[$first_group][$first_db_in_first_group]) as $column) {
					// Skip the Pedigree column as this is only used to determine what color pedigree icon to print and what page to link to
					if ($column == "Pedigree") {
						continue;
					}
					
					echo "<th>".$column."</th>";
				}
				
				echo "<th>Actions</th>";
				
			echo "</tr>";
		echo "</thead>";
		
		echo "<tbody>";
			foreach (array_keys($db_information) as $group) { // Go through the databases
				foreach (array_keys($db_information[$group]) as $file) { // Go through the databases
					$unclickable_db_flag = 0; // Flag for checking whether a database is disallowed from analysis
					foreach ($GLOBALS['disallowed_db_versions'] as $version) { // Go through each of the disallowed versions
						if (strpos($db_information[$group][$file]["GEMINI"], "v".$version) === 0) { // Does the version of the current db match one of the disallowed ones? If so set the flag to true
							$unclickable_db_flag = 1;
						}
					}
					
					// Check whether the database has been archived
					if (preg_match("/(Archived to NCI)/", $file)) {
						$unclickable_db_flag = 1;
					}
					
					// If the database is an allowed version and has not been archived, make it clickable to go to the query page
					if ($unclickable_db_flag == 0) {
						if ($db_information[$group][$file]["Pedigree"] == "No") {
							echo "<tr onclick=\"window.location.href='actions/action_analysis_types?group=".$group."&query_db=".$file."&hasped=".$db_information[$group][$file]["Pedigree"]."';\">";
						} elseif ($db_information[$group][$file]["Pedigree"] == "Yes") {
							echo "<tr onclick=\"window.location.href='analysis_selection?group=".$group."&query_db=".$file."&hasped=".$db_information[$group][$file]["Pedigree"]."';\">";
						}
					// If the database is not the right version or has been archived, stop it being clickable
					} else {
						echo "<tr>";
					}
						// If the row is disabled, highlight the DB name in red
						if ($unclickable_db_flag == 1) {
							print_table_cell($file, "", "", "#960018"); # Print a cell with the database name
						} else {
							print_table_cell($file, "", "", ""); # Print a cell with the database name
						}
						
						print_table_cell($group, "", "", ""); # Print a cell with the group name
						
						$db_information[$group][$file]["Actions"] = "";
						
						// If the user is logged in, add the modification of pedigree action
						if (is_user_logged_in()) {
							$db_information[$group][$file]["Actions"] .= "<a href=\"modify_pedigree?group=".$group."&query_db=".$file."\" style=\"border-bottom: 0px; margin:auto;\">";
							
							// If no pedigree has been defined yet, print a red icon to edit it
							if ($db_information[$group][$file]["Pedigree"] == "No") {
								$db_information[$group][$file]["Actions"] .= "<img src=\"images/pedigree_icon_red.png\" style=\"width: 20px; vertical-align: middle;\" alt=\"Modify pedigree\">";	
							// If a pedigree has been defined, print a black icon
							} elseif ($db_information[$group][$file]["Pedigree"] == "Yes") {
								$db_information[$group][$file]["Actions"] .= "<img src=\"images/pedigree_icon.png\" style=\"width: 20px; vertical-align: middle;\" alt=\"Modify pedigree\">";
							}
							
							$db_information[$group][$file]["Actions"] .= "</a>";
						}
						
						// If the database summary reports exist, print a link to view them
						if (file_exists($GLOBALS["configuration_file"]["gemini"]["db_dir"]."/".$group."/".$file.".all_variants.summary") && file_exists($GLOBALS["configuration_file"]["gemini"]["db_dir"]."/".$group."/".$file.".rare_variants.summary")) {
							$db_information[$group][$file]["Actions"] .= " <a href=\"database_summary?group=".$group."&query_db=".$file."\" style=\"border-bottom: 0px;\"><img src=\"images/clipboard_icon.png\" style=\"width: 15px; vertical-align: middle;\" alt=\"View variant report\"></a>";
						}
						
						// Flag to print the GBS icon for the current DB
						$GBS_icon_printed = 0;
						
						// If samples in the current DB are in the GBS, print a GBS query icon
						foreach (explode(";", $db_information[$group][$file]["Sample Names"]) as $sample) {
							// If at least one sample in the DB is in the GBS and the GBS icon has not been printed yet, print it
							if (isset($GBS_presence[$sample]) && $GBS_icon_printed == 0) {
								$db_information[$group][$file]["Actions"] .= " <a href=\"gbs_query?group=".$group."&query_db=".$file."&hasped=".$db_information[$group][$file]["Pedigree"]."\" style=\"border-bottom: 0px;\"><img src=\"images/GBS-Icon.png\" style=\"width: 20px; vertical-align: middle;\" alt=\"Query GBS\"></a>";
								
								$GBS_icon_printed = 1;
							}
						}
						
						// Unset the pedigree Yes/No so it is not printed in the table
						unset($db_information[$group][$file]["Pedigree"]);
						
						foreach ($db_information[$group][$file] as $column) { # Go through the remaining columns and print a cell for each one
							// If this is the database version column and it is an unsupported version, highlight it red so the user knows the problem
							if ($column == $db_information[$group][$file]["GEMINI"] && $unclickable_db_flag == 1) {
								print_table_cell($column, "", "", "#960018");
							} else {
								print_table_cell($column, "", "", "");
							}
						}
					echo "</tr>";
				}
			}
		echo "</tbody>";
	echo "</table>";
}

#############################################
# PRINT GENE LIST TABLE
#############################################

function list_of_genes_table($gene_list_title) {
	$genes_in_gene_list = return_list_of_genes_using_list_name($gene_list_title);
	
	if ($genes_in_gene_list === false) {
		error("Could not generate list of genes for the gene list specified.");
		
		return false;
	}
	
	sort($genes_in_gene_list["gene"]);
	
	echo "<table id=\"list_of_genes\" class=\"display\" cellspacing=\"0\" width=\"100%\" style=\"table-layout:fixed\">";
		echo "<thead>";
			echo "<tr>";
				echo "<th>Number</th>";
				
				echo "<th>Gene</th>";
				
				echo "<th>Time Added</th>";
			echo "</tr>";
		echo "</thead>";
		
		echo "<tbody>";
			for ($i=0; $i<count($genes_in_gene_list["gene"]); $i++) {
				echo "<tr>";
					print_table_cell(($i + 1), "", "", ""); # Print a cell with the database name
					
					print_table_cell($genes_in_gene_list["gene"][$i], "", "", ""); # Print a cell with the gene name
					
					print_table_cell($genes_in_gene_list["time"][$i], "", "", ""); # Print a cell with the time the gene was added to the list
				echo "</tr>";
			}
		echo "</tbody>";
	echo "</table>";
	
	// Set the time zone to Sydney for the output file
	date_default_timezone_set('Australia/Sydney');
	
	// Generate an output filename with the current time and gene list name as a timestamp
	$output_filename = date("Y_m_d_H_i_s",time())."_".preg_replace("/ /", "_", $gene_list_title);
	
	// Save the full path where the output file will be for opening it
    $output_full_path = "temp/".$output_filename.".tsv";
    
    // Open the output file for writing
    $output = fopen($output_full_path, "w");
    
    if ($output === false) {
	    return false;
    }
    
    // Print the header
    fwrite($output, "Gene\tTime Added\n");
    
    // Output each of the genes returned
    for ($i = 0; $i < count($genes_in_gene_list["gene"]); $i++) {
	    fwrite($output, $genes_in_gene_list["gene"][$i]."\t".$genes_in_gene_list["time"][$i]."\n");
    }
    
    fclose($output);
    
    // Create a variable with the URL to the gene list TSV
    $url_output_path = "temp/".$output_filename.".tsv";
    
    // Download link to gene list TSV
    echo "<div style=\"text-align:right; padding-top:10px; padding-bottom:40px;\"><strong><a href=\"".$url_output_path."\">Download gene list (.tsv format)</a></strong></div>";
}

#############################################
# PRINT ANNOTATION HISTORY TABLE
#############################################

function annotation_history_table($annotation_information) {	
	echo "<table id=\"annotation_history\" class=\"display\" cellspacing=\"0\" width=\"100%\" style=\"table-layout:fixed\">";
		echo "<thead>";
			echo "<tr>";
				echo "<th>Update Time</th>";
				
				echo "<th>Version</th>";
				
				echo "<th>Update Method</th>";
			echo "</tr>";
		echo "</thead>";
		
		echo "<tbody>";
			foreach (array_keys($annotation_information["versions"]) as $annotation_version) {
				echo "<tr>";
					print_table_cell($annotation_information["versions"][$annotation_version]["update_time"], "", "", "");
					
					print_table_cell($annotation_information["versions"][$annotation_version]["version"], "", "", "");
					
					print_table_cell($annotation_information["versions"][$annotation_version]["update_method"], "", "", "");
				echo "</tr>";
			}
		echo "</tbody>";
	echo "</table>";
}

#############################################
# PRINT USERS IN GROUP TABLE
#############################################

function users_in_group_table($group_id, $account_information) {
	echo "<table id=\"users_in_group\" class=\"display\" cellspacing=\"0\" width=\"100%\" style=\"table-layout:fixed\">";
		echo "<thead>";
			echo "<tr>";
				echo "<th>User Email</th>";
				
				echo "<th>Is Administrator</th>";
				
				echo "<th>Date Added to Group</th>";
				
				echo "<th>Date User Created</th>";
			echo "</tr>";
		echo "</thead>";
		
		echo "<tbody>";
		// If there are users in the current group
		if (isset($account_information["users_in_groups"]["users_per_group"][$group_id])) {
			// Go through each user in the group
			foreach (array_keys($account_information["users_in_groups"]["users_per_group"][$group_id]) as $user_id) {
				echo "<tr>";
					print_table_cell($account_information["users"][$user_id]["email"], "", "", "");
					
					print_table_cell($account_information["users"][$user_id]["is_administrator"], "", "", "");
					
					print_table_cell($account_information["users_in_groups"]["users_per_group"][$group_id][$user_id]["date_added"], "", "", "");
				
					print_table_cell($account_information["users"][$user_id]["date_added"], "", "", "");
				echo "</tr>";
			}
		}
		echo "</tbody>";
	echo "</table>";
}

#############################################
# PRINT DATABASE SUMMARY TABLE
#############################################

function database_summary_table($group, $db_filename) {
	$db_path = $GLOBALS["configuration_file"]["gemini"]["db_dir"]."/".$group."/".$db_filename;
	
	// If the database or summary files don't exist
	if (!file_exists($db_path) || !file_exists($db_path.".all_variants.summary") || !file_exists($db_path.".rare_variants.summary")) {
		return false;
	}
	
	$db_summary_all_variants_file = fopen($db_path.".all_variants.summary", "r");
	$db_summary_rare_variants_file = fopen($db_path.".rare_variants.summary", "r");

	// If the file couldn't be opened/doesn't exist
	if (!$db_summary_all_variants_file || !$db_summary_rare_variants_file) {
		return false;
	}
	
	$summary_hash = array();
	
	// Go through the all variants file line by line
	while (($line = fgets($db_summary_all_variants_file)) !== false) {
		$columns = explode("\t", $line);
		
		// Only hash rows with two columns
		if (count($columns) == 2) {
			// Ignore the header row
			if ($columns[0] == "Title") { 
				continue; 
			}
			
			// Hash the number of variants
			$summary_hash[$columns[0]]["All"] = $columns[1];
		}	
	}
	
	// Go through the all variants file line by line
	while (($line = fgets($db_summary_rare_variants_file)) !== false) {
		$columns = explode("\t", $line);
		
		// Only hash rows with two columns
		if (count($columns) == 2) {
			// Ignore the header row
			if ($columns[0] == "Title") { 
				continue; 
			}
			
			// Hash the number of variants
			$summary_hash[$columns[0]]["Rare"] = $columns[1];
		}	
	}

	echo "<table id=\"db_summary\" class=\"display\" cellspacing=\"0\" width=\"100%\" style=\"table-layout:fixed\">";
		echo "<thead>";
			echo "<tr>";
				echo "<th>Title</th>";
				
				echo "<th>All Variants</th>";
				
				echo "<th>Rare Variants</th>";
			echo "</tr>";
		echo "</thead>";
		
		echo "<tbody>";

			foreach (array_keys($summary_hash) as $title) {
				echo "<tr>";
					print_table_cell($title, "", "", ""); 
					
					print_table_cell($summary_hash[$title]["All"], "", "", "");
					
					print_table_cell($summary_hash[$title]["Rare"], "", "", "");
				echo "</tr>";
			}
		
		echo "</tbody>";
	echo "</table>";
	
	return true;
}

#############################################
# PRINT STORED GENOME BLOCKS TABLE
#############################################

function genome_blocks_table() {
	// Make sure the session with the genome blocks and samples is set
	if (!isset($_SESSION["gbs_import_genome_blocks"], $_SESSION["gbs_import_method"], $_SESSION["gbs_import_samples"]) || count($_SESSION["gbs_import_genome_blocks"]) == 0) {
		return false;
	}
	
	echo "<table id=\"genomic_blocks\" class=\"display\" cellspacing=\"0\" width=\"100%\" style=\"table-layout:fixed\">";
		echo "<thead>";
			echo "<tr>";
				echo "<th>Location</th>";
				
				echo "<th>Sample(s)</th>";
				
				if (in_array($_SESSION["gbs_import_method"], array("CNVnator", "CNVkit", "Sequenza", "PURPLE"))) {
					echo "<th>Copy Number</th>";
				} elseif (in_array($_SESSION["gbs_import_method"], array("VarpipeSV", "Manta", "LUMPY"))) {
					echo "<th>Event Type</th>";
				} else {
					echo "<th>Event</th>";
				}
				
				// If there are annotations, print a column of them
				if (isset($_SESSION["gbs_import_unique_annotation_tags"])) {
					echo "<th>Annotation(s)</th>";
				}
			echo "</tr>";
		echo "</thead>";
		
		echo "<tbody>";
			foreach (array_keys($_SESSION["gbs_import_genome_blocks"]) as $block_id) {
				echo "<tr>";
					// Print the cell for the Location column
					print_table_cell($_SESSION["gbs_import_genome_blocks"][$block_id]["chromosome"].":".$_SESSION["gbs_import_genome_blocks"][$block_id]["start"]."-".$_SESSION["gbs_import_genome_blocks"][$block_id]["end"], "", "", "");
					
					// Print the cell containing all affected samples
					print_table_cell(implode("; ", $_SESSION["gbs_import_genome_blocks"][$block_id]["samples"]), "", "", "");
					
					// Print the cell containing the event type
					print_table_cell($_SESSION["gbs_import_genome_blocks"][$block_id]["event"], "", "", "");
					
					// If there are annotations, print a cell of all the annotations for this block for all samples
					if (isset($_SESSION["gbs_import_genome_blocks"][$block_id]["annotations"])) {
						$annotations_cell = "[";
						
						// Go through every annotation tag
						foreach (array_keys($_SESSION["gbs_import_genome_blocks"][$block_id]["annotations"]) as $annotation_tag) {
							$annotations_cell .= $annotation_tag.": ";
							
							// Go through every sample for the current annotation tag
							foreach (array_keys($_SESSION["gbs_import_genome_blocks"][$block_id]["annotations"][$annotation_tag]) as $sample_name) {
								$annotations_cell .= $sample_name."=".$_SESSION["gbs_import_genome_blocks"][$block_id]["annotations"][$annotation_tag][$sample_name]."; ";
							}
							
							$annotations_cell = substr($annotations_cell, 0, -2); // Remove the last ", " that was added by the loop above
							
							$annotations_cell .= "] [";
						}
						
						$annotations_cell = substr($annotations_cell, 0, -2); // Remove the last " [" that was added by the loop above
						
						print_table_cell($annotations_cell, "", "", "");
					}	
				echo "</tr>";
			}
		echo "</tbody>";
	echo "</table>";
	
	return true;
}

?>
