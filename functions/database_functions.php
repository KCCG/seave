<?php

// These are functions for either connecting/manipulating external database connections (e.g. MySQL) or for manipulating GEMINI database files themselves

#############################################
# ESTABLISH MYSQL CONNECTION
#############################################

function establish_mysql_connection() {
	// MySQL connection information
	if (!isset($GLOBALS["configuration_file"]["mysql"]["server"], $GLOBALS["configuration_file"]["mysql"]["username"], $GLOBALS["configuration_file"]["mysql"]["password"])) {
		return false;
	}
	
	// Connect and create the PDO object
	$pdo_options = [
	    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
	    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
	    PDO::ATTR_EMULATE_PREPARES   => true,
	];
	
	try {
		$mysql_connection = new PDO("mysql:host=".$GLOBALS["configuration_file"]["mysql"]["server"].";charset=utf8", $GLOBALS["configuration_file"]["mysql"]["username"], $GLOBALS["configuration_file"]["mysql"]["password"], $pdo_options);
	} catch (Exception $e) {
		echo "Problem connecting to the database.";
		
		exit;
	}
	
	return $mysql_connection;
}

#############################################
# KILL MYSQL CONNECTION
#############################################

function kill_mysql_connection() {
	// Close connection
	if ($GLOBALS["mysql_connection"] = null) {
		return true;
	} else {
		return false;
	}
}

#############################################
# DELETE A GEMINI DATABASE
#############################################

function delete_database($db_path) {
	// The supplied path is relative to the DB dir root so the full path to this
	$db_path = $GLOBALS["configuration_file"]["gemini"]["db_dir"]."/".$db_path;
	
	// Make sure the database exists
	if (!file_exists($db_path)) {
		return false;
	}
	
	// Delete the DB itself
	unlink($db_path);
	
	delete_database_cache($db_path);
	
	return true;
}

#############################################
# DELETE A GEMINI DATABASE CACHE
#############################################

function delete_database_cache($db_path, $tsv_only = null) {
	// If a cache has been generated, delete it
	if (file_exists($db_path.".tsv")) {
		unlink($db_path.".tsv");
	}
	
	// If only the TSV is to be deleted
	if (isset($tsv_only) && $tsv_only == "tsv_only") {
		return true;
	}
	
	// If a all variants summary has been generated, delete it
	if (file_exists($db_path.".all_variants.summary")) {
		unlink($db_path.".all_variants.summary");
	}
	
	// If a rare variants summary has been generated, delete it
	if (file_exists($db_path.".rare_variants.summary")) {
		unlink($db_path.".rare_variants.summary");
	}
	
	return true;
}

#############################################
# RENAME A GEMINI DATABASE AND ASSOCIATED FILES
#############################################

function rename_database($old_db_path, $new_db_path) {
	// Rename the database itself
	if (!rename($old_db_path, $new_db_path)) {
		return false;
	}
	
	// Rename the TSV cache if it exists
	if (file_exists($old_db_path.".tsv")) {
		rename($old_db_path.".tsv", $new_db_path.".tsv");
	}
	
	// Rename the variant summaries if they exist
	if (file_exists($old_db_path.".all_variants.summary") && file_exists($old_db_path.".rare_variants.summary")) {
		rename($old_db_path.".all_variants.summary", $new_db_path.".all_variants.summary");
		rename($old_db_path.".rare_variants.summary", $new_db_path.".rare_variants.summary");
	}
	
	return true;
}

#############################################
# PARSE GEMINI DB FILES AVAILABLE
#############################################

function parse_all_available_database_files() {
	$databases = array();
	//$databases[<group name>] = array(<database filename 1>, <database filename 2>)
	
	// Fetch all groups
	$group_information = fetch_account_groups();
	
	if ($group_information === false) {
		return false;
	}
	
	// Go through each group to find DBs
	foreach (array_keys($group_information) as $group_id) {
		// If the group directory exists
		if (is_dir($GLOBALS["configuration_file"]["gemini"]["db_dir"]."/".$group_information[$group_id]["group_name"])) {
			// Fetch all the files in the directory
			$db_dir_files = scandir($GLOBALS["configuration_file"]["gemini"]["db_dir"]."/".$group_information[$group_id]["group_name"]);
		
			// Create an empty array to hold any databases found (will be empty if there are none)
			$databases[$group_information[$group_id]["group_name"]] = array();
			
			// Go through each file/directory found
			foreach ($db_dir_files as $file) {
				// Ignore . and .. "files"
				if (!in_array($file, array(".", ".."))) {
					// If the file is a GEMINI DB, save it to the array
					if (preg_match('/\.db$/', $file)) {
						$databases[$group_information[$group_id]["group_name"]][] = $file;
					}
				}
			}
		}
	}
	
	return $databases;
}

#############################################
# EXTRACT THE GEMINI DB FILENAME FROM THE PATH + FILENAME
#############################################

function database_name_from_path($db_path) {
	$db_filename = preg_replace("/.*\/(.*)/", "$1", $db_path); // Remove the path and only keep the database name
	
	return $db_filename;
}

#############################################
# EXTRACT THE GEMINI DB PATH FROM THE PATH + FILENAME
#############################################

function database_path_from_path_filename($db_path) {
	$db_path = preg_replace("/(.*)\/.*/", "$1", $db_path); // Remove the filename and only keep the database path
	
	return $db_path;
}

#############################################
# PARSE GEMINI DATABASES FILES AND HASH THEIR INFORMATION
#############################################

function parse_databases() {
	// Fetch the groups the user has access to (users that are not logged in are handled)
	$group_information = fetch_account_groups($_SESSION["logged_in"]["email"]);
	
	if ($group_information === false) {
		return false;
	}
	
	// Go through each group for the user
	foreach (array_keys($group_information) as $group_id) {
		// The current group's expected DB directory
		$db_dir = $GLOBALS["configuration_file"]["gemini"]["db_dir"]."/".$group_information[$group_id]["group_name"];
		
		// If the directory for the group doesn't exist, move on to the next one
		if (!is_dir($db_dir)) {
			continue;
		}
		
		// Fetch all the files in the directory
		$db_dir_files = scandir($db_dir);
		
		foreach ($db_dir_files as $file) { # Extract database information for each GEMINI database
			if (preg_match('/\.db$/', $file)) {
				$db_file_found_flag = 1; // Set a flag that at least one DB file was found
				
				// If the database has been archived
				if (preg_match("/(Archived to NCI)/", $file)) {
					$db_information[$group_information[$group_id]["group_name"]][$file]["Sample Names"] = "0";
					$db_information[$group_information[$group_id]["group_name"]][$file]["Samples"] = "";
					$db_information[$group_information[$group_id]["group_name"]][$file]["Variants"] = "0";
					$db_information[$group_information[$group_id]["group_name"]][$file]["Size"] = "0";
					$db_information[$group_information[$group_id]["group_name"]][$file]["Date"] = date("d/m/Y", filemtime($db_dir."/".$file));;
					$db_information[$group_information[$group_id]["group_name"]][$file]["GEMINI"] = "";
					$db_information[$group_information[$group_id]["group_name"]][$file]["Pedigree"] = "";
				// If there is a previously generated file with the database information, use that
				} elseif (file_exists($db_dir."/".$file.".tsv")) {
					$input_filename = $db_dir."/".$file.".tsv";
					$input = fopen($input_filename, "r") or error("Warning: cannot open database cache file. Database ".$file." excluded from the table!");
					
					$cache = fread($input,filesize($input_filename));
					$columns = explode("\t", $cache);
					
					$db_information[$group_information[$group_id]["group_name"]][$file]["Sample Names"] = $columns[2];
					$db_information[$group_information[$group_id]["group_name"]][$file]["Samples"] = $columns[0];
					$db_information[$group_information[$group_id]["group_name"]][$file]["Variants"] = $columns[1];
					$db_information[$group_information[$group_id]["group_name"]][$file]["Size"] = $columns[5];
					$db_information[$group_information[$group_id]["group_name"]][$file]["Date"] = $columns[6];
					$db_information[$group_information[$group_id]["group_name"]][$file]["GEMINI"] = $columns[3];
					$db_information[$group_information[$group_id]["group_name"]][$file]["Pedigree"] = $columns[4];
								
					fclose($input);
				// If this is the first time this database has been seen, fetch its database information and save it to a file
				} else {
					$query_result = ""; # Clear/define the query result variable
					$db_information[$group_information[$group_id]["group_name"]][$file]["Sample Names"] = "";
					$db_information[$group_information[$group_id]["group_name"]][$file]["Samples"] = "";
					$db_information[$group_information[$group_id]["group_name"]][$file]["Variants"] = "";
					$db_information[$group_information[$group_id]["group_name"]][$file]["Size"] = "";
					$db_information[$group_information[$group_id]["group_name"]][$file]["Date"] = "";
					$db_information[$group_information[$group_id]["group_name"]][$file]["GEMINI"] = "";
					$db_information[$group_information[$group_id]["group_name"]][$file]["Pedigree"] = "";
					
					######################
					
					$db_information[$group_information[$group_id]["group_name"]][$file]["Date"] = date("d/m/Y", filemtime($db_dir."/".$file));
					
					######################
					
					$db_information[$group_information[$group_id]["group_name"]][$file]["Size"] = bytes_to_human_readable(filesize($db_dir."/".$file));
					
					######################
					
					$query = $GLOBALS["configuration_file"]["gemini"]["binary"].' query -q "SELECT name, family_id, paternal_id, maternal_id, phenotype FROM samples" '.$db_dir."/".escape_database_filename($file);
					
					exec($query, $query_result, $exit_code); # Execute the Gemini query
		
					$db_information[$group_information[$group_id]["group_name"]][$file]["Samples"] = count($query_result);
					
					######################
					
					foreach ($query_result as $sample) {
						$columns = explode("\t", $sample);
						
						if ($db_information[$group_information[$group_id]["group_name"]][$file]["Sample Names"] == "") {
							$db_information[$group_information[$group_id]["group_name"]][$file]["Sample Names"] = $columns[0]; # The first column is the sample name
						} else { # If this is not the first sample, add a semicolon to separate it from the previous one
							$db_information[$group_information[$group_id]["group_name"]][$file]["Sample Names"] .= ";".$columns[0];
						}
						
						$no_info = array('None', '-9', '0'); // Array of permissable "no information" values
						
						if ($db_information[$group_information[$group_id]["group_name"]][$file]["Pedigree"] != "Yes" && in_array($columns[1], $no_info) && in_array($columns[2], $no_info) && in_array($columns[3], $no_info) && in_array($columns[4], $no_info)) {
							$db_information[$group_information[$group_id]["group_name"]][$file]["Pedigree"] = "No";
						} else {
							$db_information[$group_information[$group_id]["group_name"]][$file]["Pedigree"] = "Yes";
						}
					}
					
					######################
					
					$query_result = ""; # Clear/define the query result variable
					
					$query = $GLOBALS["configuration_file"]["gemini"]["binary"].' query -q "SELECT count(*) FROM variants" '.$db_dir."/".escape_database_filename($file);
													
					exec($query, $query_result, $exit_code); # Execute the Gemini query
		
					$db_information[$group_information[$group_id]["group_name"]][$file]["Variants"] = $query_result[0];
					
					######################
					
					$query_result = ""; # Clear the query result variable
					
					$db_version = obtain_gemini_database_version($db_dir."/".$file);
					
					if ($db_version === false) {
						$db_information[$group_information[$group_id]["group_name"]][$file]["GEMINI"] = "vUnknown";
					} else {
						$db_information[$group_information[$group_id]["group_name"]][$file]["GEMINI"] = "v".$db_version;
					}
					
					######################
					
			        $output_filename = $db_dir."/".$file.".tsv";
					
					// Only write the tsv cache if samples are present - otherwise keep checking each time the home page is loaded
			        if ($db_information[$group_information[$group_id]["group_name"]][$file]["Samples"] != 0) {
				   		$output = fopen($output_filename, "w") or error("Warning: cannot create database information cache file.");
		
					    fwrite($output, $db_information[$group_information[$group_id]["group_name"]][$file]["Samples"]."\t".$db_information[$group_information[$group_id]["group_name"]][$file]["Variants"]."\t".$db_information[$group_information[$group_id]["group_name"]][$file]["Sample Names"]."\t".$db_information[$group_information[$group_id]["group_name"]][$file]["GEMINI"]."\t".$db_information[$group_information[$group_id]["group_name"]][$file]["Pedigree"]."\t".$db_information[$group_information[$group_id]["group_name"]][$file]["Size"]."\t".$db_information[$group_information[$group_id]["group_name"]][$file]["Date"]);
						
						fclose($output);
				    }
				}
			}
		}
	}
	
	// If at least one database file was found
	if (isset($db_file_found_flag)) {
		return $db_information;
	// If no database files were found
	} else {
		return false;
	}
}

#############################################
# ESCAPE DATABASE FILENAMES TO ALLOW SPACES IN THEM
#############################################

function escape_database_filename($db_filename) {
	$escaped_db_filename = preg_replace("/;\<\>&/", "", $db_filename); // Get rid of dangerous characters that shouldn't ever be in a filename
	$escaped_db_filename = preg_replace("/\s/", "\\ ", $escaped_db_filename); // Replace spaces in database names with "\ " to escape them so GEMINI works with databases with spaces in their name
	$escaped_db_filename = preg_replace("/([\(\)\[\]\{\}])/", "\\\\$1", $escaped_db_filename); // Replace brackets in database names with "\<bracket>" to escape them so GEMINI works with databases with brackets in their name

	return $escaped_db_filename;
}


?>
