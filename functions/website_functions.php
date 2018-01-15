<?php

#############################################
# GENERIC ERROR STYLE
#############################################

function error($error_text) {
	echo "<h3 style=\"text-align:center; color:red;\">".$error_text."</h3><br>";
}

#############################################
# GENERIC SUCCESS STYLE
#############################################

function success($success_text) {
	echo "<h3 style=\"text-align:center; color:green;\">".$success_text."</h3><br>";
}

#############################################
# ERROR FUNCTION FOR THE RESULTS PAGE TO STOP THE QUERY GOING AHEAD
#############################################

function stop_query_error($error_text) {
	error($error_text);
	
	$_SESSION["query_error"] = 1;
}

#############################################
# PRINT A BOX TO DYNAMICALLY DISPLAY/HIDE COLUMNS
#############################################

function column_filter($table, $column_index, $column_array, $label, $checked = NULL) {
	$column_not_found = 0; # Flag for whether a requested column is not present in the column index of the database
	
	if (count($column_array) == 0) {
		return false;
	}
	
	foreach ($column_array as $column) { # Go through every requested column
		if (!isset($column_index{$column})) { # Check whether the requested column is NOT present in the column index
			$column_not_found = 1;
		}
	}
	
	if ($column_not_found != 1) { # If all columns are present in the database, create the button to show columns
		$temp = "<input type=\"checkbox\" ";
		
		// If the user is using Chrome or Safari, if they click on a variant to see all columns for it then go back to the results page, all the columns in the table are reset to defaults but the buttons under the table remain ticked meaning they are out of sync, turn off autocompleting so the show/hide buttons are refreshed along with the DataTable
		if (strpos($_SERVER['HTTP_USER_AGENT'], 'Chrome') !== false || strpos($_SERVER['HTTP_USER_AGENT'], 'Safari') !== false) {
			$temp .= "autocomplete=\"off\"";
		}
		
		$temp .= "id=\"".$column_array[0]."\" name=\"".$column_array[0]."\" onclick=\"fnShowHideMultiple('#".$table."',[";
			
		$i = 0;
		foreach ($column_array as $column_name) {
			if ($i == 0) {
				$temp .= $column_index{$column_name};
			} else {
				$temp .= ", ".$column_index{$column_name};
			}

			$i++;
		}
		$temp .= "]);\"";
		
		if ($checked == "checked") {
			$temp .= "checked=\"\"";
		}
		
		$temp .= "><label for=\"".$column_array[0]."\">".$label."</label>";
		
		echo $temp;
	}
}

#############################################
# DETERMINE A MASTER GENE LIST FROM GENE LISTS SELECTED AND ENTERED MANUALLY
#############################################

function determine_gene_list($gene_lists_selected, $gene_list_input) {
	$gene_list = array();
	
	// If a custom gene list has been selected from the dropdown
	if (is_array($gene_lists_selected) && count($gene_lists_selected) > 0) {
		// Fetch the list of gene lists in the database
		if ($gene_lists = fetch_gene_lists()) { // Extract gene lists from the database
			// Go through every gene list selected
			foreach ($gene_lists_selected as $gene_list_name) {
				// Check whether the gene list submitted by the user is in the database
				if (in_array($gene_list_name, array_keys($gene_lists))) {
					// Fetch the list of genes for the current gene list
					$list_of_genes_for_current_gene_list = return_list_of_genes_using_list_name($gene_list_name);
					
					if ($list_of_genes_for_current_gene_list === false) {
						return false;
					} elseif (count($list_of_genes_for_current_gene_list["gene"]) > 0) {
						$gene_list = array_merge($gene_list, $list_of_genes_for_current_gene_list["gene"]);
					}
				} else {
					return false;
				}
			}
		} else {
			return false;
		}
	}
	
	// If a gene list was manually input
	if ($gene_list_input != "") {
		// Merge any genes from gene lists selected and manually entered genes into a single array
		$gene_list = array_merge($gene_list, preg_split("/[;,\s]/", $gene_list_input));
	}
	
	$gene_list = preg_grep('/^\s*\z/', $gene_list, PREG_GREP_INVERT); // Strip out elements that are just spaces or are empty e.g. BRCA1; ;BRCA2; has 2 elements that need to be stripped out
	
	$gene_list = preg_replace("/[\n\r\s]/", "", $gene_list); // Remove newline characters
	
	// Remove duplicate genes that may have come in from multiple lists
	$gene_list = array_unique($gene_list);
	
	// Reindex the array
	$gene_list = array_values($gene_list);

	return $gene_list;
}

#############################################
# DETERMINE A MASTER LIST OF CHROMOSOMAL COORDINATES SUBMITTED MANUALLY
#############################################

function parse_submitted_regions($submitted_regions, $session_name) {
	// Create an array from the submitted list of regions
	$regions = explode(";", $submitted_regions);

	// If no region was specified, set the session variable to nothing
	if (count($regions) == 1 && $regions[0] == "") {
		$_SESSION[$session_name] = "";
		
		return true;
	// If at least one region was specified
	} else {
		$_SESSION[$session_name] = ""; // Clear any previous regions specified
		
		// Go through every region specified
		for ($i = 0; $i < count($regions); $i++) {
			// If the region specified matches the chr1:<number>-<number> format which can include commas in the numbers and spaces
			if (preg_match('/^\s*([\w]*?):\s*([0-9,]*)\s*\-\s*([0-9,]*)\s*$/', $regions[$i], $matches)) {
				// Remove commas from the start and end coordinates if present
				$matches[2] = preg_replace("/,/", "", $matches[2]);
				$matches[3] = preg_replace("/,/", "", $matches[3]);

				if ($matches[2] > $matches[3]) { # If the start is greater than the end, ignore the region with an error
					return "You specified a search region where the start is larger than the end: ".$regions[$i].". It will be ignored.";
				} else {
					// If no "chr" has been put in front of the chromosome number/X/Y, add it
					if (is_numeric($matches[1]) || $matches[1] == "X" || $matches[1] == "Y") {
						$matches[1] = "chr".$matches[1];
					// If the chromosome specified is mitochondria, change from common ways of putting it to the correct one in Gemini
					} elseif (in_array($matches[1], array("M", "MT", "chromMT", "chrM"))) {
						$matches[1] = "chrMT";
					}
					
					if ($_SESSION[$session_name] == "") {
						$_SESSION[$session_name] = $matches[1].":".$matches[2]."-".$matches[3];
					} else {
						$_SESSION[$session_name] .= ";".$matches[1].":".$matches[2]."-".$matches[3];
					}
				}
			// If the region specified is just the chromosome with no interval
			} elseif (preg_match('/^\s*([\w]*?)\s*$/', $regions[$i], $matches)) { # If the region matches the chr1:<number>-<number> format
				// If no "chr" has been put in front of the chromosome number/X/Y, add it
				if (is_numeric($matches[1]) || $matches[1] == "X" || $matches[1] == "Y") {
					$matches[1] = "chr".$matches[1];
				// If the chromosome specified is mitochondria, change from common ways of putting it to the correct one in Gemini
				} elseif (in_array($matches[1], array("M", "MT", "chromMT", "chrM"))) {
					$matches[1] = "chrMT";
				}
				
				// If no interval is supplied just set an interval that is outside the length of any human chromosome
				if ($_SESSION[$session_name] == "") {
					$_SESSION[$session_name] = $matches[1].":0-500000000";
				} else {
					$_SESSION[$session_name] .= ";".$matches[1].":0-500000000";
				}
			} else {
				return "You specified an incorrect search region: ".$regions[$i].". Search regions must be in this format: chr1:10,000-100,000 and separated by semicolons.";
			}
		}
		
		return true;
	}
}

#############################################
# GENERATE A RANDOM STRING
#############################################

function rand_string() {
	$str = 'abcdefghijklmnopqrstuvwxyz';
	
	$shuffled = str_shuffle($str);
	
	$str = md5(date("Y_m_d_H_i_s",time()).$shuffled);
	
	return $str;
}

#############################################
# CHECK WHETHER A GIVEN URL EXISTS
#############################################

function does_url_exist($url) {
	$ch = curl_init($url);    
	
	curl_setopt($ch, CURLOPT_NOBODY, true);
	
	curl_exec($ch);
	
	$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
	
	if ($code == 200) {
		$status = true;
	} else {
		$status = false;
	}
	
	curl_close($ch);
	
	return $status;
}

#############################################
# ENTER PASSWORD FORM
#############################################

function password_form($error = NULL) {
	echo "<section class=\"12u 12u(narrower)\">";		
		echo "<form action=\"actions/action_log_in\" method=\"post\" style=\"text-align:center;\">";
			echo "<h3 style=\"padding-bottom:10px;\">Enter account details:</h3>";
			
			// If an error message has been supplied, display it
			if (!empty($error)) {
				error($error);
				echo "<br>";
			}
			
			echo "<div class=\"row 50%\">";
				echo "<div class=\"12u\">";
					echo "<input type=\"email\" style=\"display: inline; width: 300px;\" name=\"email\" placeholder=\"Email\">";
				echo "</div>";
			echo "</div>";
			
			echo "<div class=\"row 50%\">";
				echo "<div class=\"12u\">";
					echo "<input type=\"password\" style=\"display: inline; width: 300px;\" name=\"password\" placeholder=\"Password\">";
				echo "</div>";
			echo "</div>";
			
			echo "<div class=\"row 50%\">";
				echo "<div class=\"12u\">";
					echo "<ul class=\"actions\">";
						echo "<li><input type=\"submit\" value=\"Log In\" /></li>";
						echo "<li><input type=\"reset\" value=\"Clear\" /></li>";
					echo "</ul>";
				echo "</div>";
			echo "</div>";
		echo "</form>";
	echo "</section>";
}

#############################################
# CONVERT BYTES TO HUMAN READABLE MB/GB
#############################################

function bytes_to_human_readable($bytes) {
	$si_prefix = array( 'B', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB' );
	$base = 1024;
	$class = min((int)log($bytes, $base), count($si_prefix) - 1);
	
	return sprintf('%1.2f', $bytes/pow($base,$class)).' '.$si_prefix[$class];	
}

#############################################
# RETURN STORAGE SPACE USAGE
#############################################

function storage_space($type) {
	$disk_space_total = disk_total_space($GLOBALS["configuration_file"]["gemini"]["db_dir"]); # Determine the total disk space available in bytes
	$disk_space_used = $disk_space_total - disk_free_space($GLOBALS["configuration_file"]["gemini"]["db_dir"]); # Determine the disk space used in bytes
	
	$disk_space_total = bytes_to_human_readable($disk_space_total); # Convert to human readable space
	$disk_space_used = bytes_to_human_readable($disk_space_used); # Convert to human readable space
	
	if ($type == "total") {
		return $disk_space_total;
	} elseif ($type == "used") {
		return $disk_space_used;
	}
}

#############################################
# DISPLAY ERRORS FROM ACTION PAGE
#############################################

function if_set_display_error($session_name, $error_message) {
	if (isset($_SESSION[$session_name])) {
		error($error_message);
		
		unset($_SESSION[$session_name]);
		
		return true;
	} else {
		return false;
	}
}

#############################################
# DISPLAY SUCCESS FROM ACTION PAGE
#############################################

function if_set_display_success($session_name, $success_message) {
	if (isset($_SESSION[$session_name])) {
		success($success_message);
		
		unset($_SESSION[$session_name]);
		
		return true;
	} else {
		return false;
	}
}

#############################################
# LOG IMPORTANT WEBSITE EVENTS
#############################################

function log_website_event($event) {
	$query_parameters = array();
	
	$sql = "INSERT INTO ";
		$sql .= "LOGGING.website_events ";
		$sql .= "(user_id, user_email, seave_version, event, ip, time) ";
	$sql .= "VALUES ";
		$sql .= "(?, ?, ?, ?, INET_ATON(?), now())";
	$sql .= ";";
	
	// If the user is logged in, log their user ID and email
	if (isset($_SESSION["logged_in"]["user_id"], $_SESSION["logged_in"]["email"]) && $_SESSION["logged_in"]["user_id"] != "" && $_SESSION["logged_in"]["email"] != "") {
		array_push($query_parameters, $_SESSION["logged_in"]["user_id"]);
		array_push($query_parameters, $_SESSION["logged_in"]["email"]);
	// Otherwise log NULL values
	} else {
		array_push($query_parameters, NULL);
		array_push($query_parameters, NULL);
	}
	
	// Seave version
	array_push($query_parameters, $GLOBALS["configuration_file"]["version"]["name"]);
	
	// Event
	array_push($query_parameters, $event);
	
	// If the IP address of the user is local (i.e. most likely local dev), just save the IP as 0.0.0.0
	if ($_SERVER['REMOTE_ADDR'] == "::1") {
		array_push($query_parameters, "0.0.0.0");
	// Otherwise save the user's IP
	} else {
		array_push($query_parameters, $_SERVER['REMOTE_ADDR']);
	}
	
	$statement = $GLOBALS["mysql_connection"]->prepare($sql);
	
	$statement->execute($query_parameters);
	
	$rows_affected = $statement->rowCount();
	
	// Make sure 1 row was affected
	if ($rows_affected === 1) {
		return true;
	} else {
		return false;
	}
}

?>
