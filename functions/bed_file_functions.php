<?php

#############################################
# CREATE TEMPORARY BED FILE FROM ARRAY
#############################################

// $bed_output must be an array of lines to output to a BED file, these should be preconstructed as "chr\tstart\tend\t<other>" or just 3 columns
function create_temporary_bed_file($filename, array $bed_output) {
	if (count($bed_output) == 0){
		return false;
	}
	
	$output_path = basename("..")."/temp/".$filename.".bed";
	   	
   	// If a BED file with the same filename already exists, quit
   	if (file_exists($output_path)) {
	   	return false;
   	}
   	
   	// Create and open the output file for writing
   	if (!($output = fopen($output_path, "w"))) {
	   	return false;
   	}
	
	// Go through each line and output it to the file
	foreach ($bed_output as $line) {
		fwrite($output, $line);
	}
   	
   	fclose($output);
   	
   	return $output_path;
}

#############################################
# DELETE TEMPORARY BED FILE
#############################################

function delete_temporary_bed_file($bed_path) {
	// Delete the BED file
	if (file_exists($bed_path)) {
		if (unlink($bed_path) === true) {
			return true;
		} else {
			return false;
		}
	} else {
		return null;
	}
}

#############################################
# RUN BEDTOOLS INTERSECT ON AN ARRAY OF BED FILES
# Returns regions shared by ALL input BED files
#############################################

function bedtools_intersect_bed_files(array $paths) {
	// If no files are supplied, return an empty string
	if (count($paths) == 0) {
		return "";
	// If one file is supplied, return it back as it is the intersect of all available files (1 of 1)
	} elseif (count($paths) == 1) {
		return $paths[0];
	// Otherwise if there is more than one file, perform the bedtools intersect
	} else {
		// Create a suffix for the intersect BED file so if this function is called multiple times with the same $paths[0], the intersect returned is a different file each time
		$suffix = rand_string();

		// Go through each BED file to intersect
		for ($i = 0; $i < count($paths); $i++) {
			// Make sure the file exists
			if (!file_exists($paths[$i])) {
				return false;
			}
			
			// Ignore the first BED file
			if ($i == 0) {
				continue;
			} else {				
				$cmd = $GLOBALS["configuration_file"]["bedtools"]["binary"]." intersect -a ";
				
				// If this is the second file in the loop, intersect with the first
				if ($i == 1) {
					$cmd .= $paths[0];
				// If this is a subsequent file in the loop, intersect with the previous intersection
				} else {
					$cmd .= $paths[0].".".$suffix.".".($i - 1).".intersect.bed";
				}
				
				$cmd .= " -b ".$paths[$i]." > ".$paths[0].".".$suffix.".".$i.".intersect.bed";
				
				exec($cmd, $result, $exit_code); # Execute the command
				
				if ($exit_code != 0) {
					return false;
				}
				
				// Delete the previous intersect file
				if ($i > 1) {
					unlink($paths[0].".".$suffix.".".($i - 1).".intersect.bed");
				}
			}
		}
		
		// Sort the final intersect as downstream bedtools tools require a sorted BED file
		$cmd = $GLOBALS["configuration_file"]["bedtools"]["binary"]." sort -i ".$paths[0].".".$suffix.".".($i - 1).".intersect.bed > ".$paths[0].".".$suffix.".".($i - 1).".sorted.intersect.bed";
		
		exec($cmd, $result, $exit_code); # Execute the command
				
		if ($exit_code != 0) {
			return false;
		}
		
		// Delete the unsorted final intersect
		delete_temporary_bed_file($paths[0].".".$suffix.".".($i - 1).".intersect.bed");
		
		// Return the sorted final intersect
		return $paths[0].".".$suffix.".".($i - 1).".sorted.intersect.bed";
	}
}

#############################################
# RUN BEDTOOLS INTERSECT ON TWO INPUT BED FILES
# Returns longform intersect regions with the following per line:
# 1) 4 columns for the intersection 2) 4 columns from the first file 3) 4 columns from the second file
# This allows seeing the overlapping coordinates and the original information from both BED files per intersection
#############################################

function bedtools_intersect_two_bed_files_long($bed_path_one, $bed_path_two) {
	// Make sure the input files exist
	if (!file_exists($bed_path_one) || !file_exists($bed_path_two)) {
		return false;
	}
	
	// bash -c to force the execution to happen in bash (set -o pipefail doesn't work in /bin/sh)
	// set -o pipefail ensures that any failure amongst the pipes makes it to the end rather than the last command being the exit status
	// This prints the intersect as the first 4 columns and then the original coordinates for each of the inputs as the following 8 columns
	$cmd = "bash -c '(set -o pipefail && paste <(".$GLOBALS["configuration_file"]["bedtools"]["binary"]." intersect -a ".$bed_path_one." -b ".$bed_path_two.") <(".$GLOBALS["configuration_file"]["bedtools"]["binary"]." intersect -a ".$bed_path_one." -b ".$bed_path_two." -wa -wb) > ".$bed_path_one.".intersect.bed)'";
	
	exec($cmd, $result, $exit_code); # Execute the command
	
	if ($exit_code != 0) {
		return false;
	}
	
	return $bed_path_one.".intersect.bed";
}

#############################################
# RUN BEDTOOLS UNION ON AN ARRAY OF SAMPLES TO COMBINE THEM INTO ONE BED FILE
#############################################

function bedtools_unionbedg_bed_files(array $paths) {
	// If no files are supplied
	if (count($paths) == 0) {
		return false;
	// If one file is supplied, return it back as it is the union of all available files (1 of 1)
	} elseif (count($paths) == 1) {
		return $paths[0];
	}
	
	// Note: this command doesn't seem to work with a 3 column BED file but requires at least 4!
	$cmd = $GLOBALS["configuration_file"]["bedtools"]["binary"]." unionbedg -i ";
	
	// Go through each BED file
	for ($i = 0; $i < count($paths); $i++) {
		// Make sure the file exists
		if (!file_exists($paths[$i])) {
			return false;
		}
		
		// Add each bed file for the unionbedg command
		$cmd .= $paths[$i]." ";
	}
	
	// Complete the command by piping out the results to a new file
	$cmd .= "> ".$paths[0].".union.bed";
	
	exec($cmd, $result, $exit_code); // Execute the command
			
	if ($exit_code != 0) {
		return false;
	} else {
		return $paths[0].".union.bed";
	}
}

#############################################
# RUN BEDTOOLS MERGE ON BED FILE TO COMBINE AND EXTEND BLOCKS
#############################################

function bedtools_merge_bed_file($bed_path, $optional_parameters) {
	// Make sure the BED file exists
	if (!file_exists($bed_path)) {
		return false;
	}
	
	// Perform the bedtools merge on the input file
	$cmd = $GLOBALS["configuration_file"]["bedtools"]["binary"]." merge ";
	
	// If optional parameters were supplied, add them
	if ($optional_parameters != "") {
		$cmd .= $optional_parameters;
	}
	
	$cmd .= " -i ".$bed_path." > ".$bed_path.".merged.bed";
	
	exec($cmd, $result, $exit_code); // Execute the command
		
	if ($exit_code != 0) {
		return false;
	} else {
		return $bed_path.".merged.bed";
	}
}

#############################################
# RUN BEDTOOLS SUBTRACT ON TWO BED FILES
#############################################

function bedtools_subtract_bed_files($A_bed_path, $B_bed_path) {
	// Make sure the input files exist
	if (!file_exists($A_bed_path) || !file_exists($B_bed_path)) {
		return false;
	}
	
	$cmd = $GLOBALS["configuration_file"]["bedtools"]["binary"]." subtract -a ".$A_bed_path." -b ".$B_bed_path." > ".$A_bed_path.".subtracted.bed";
	
	exec($cmd, $result, $exit_code); # Execute the command
				
	if ($exit_code != 0) {
		return false;
	}
	
	return $A_bed_path.".subtracted.bed";
}

#############################################
# READ A BED FILE INTO AN ARRAY
#############################################

function bedtools_parse_bed_file($bed_path) {
	// Make sure the input file exists
	if (!file_exists($bed_path)) {
		return false;
	}
	
	// Define the array to hold the BED regions
	$bed_regions = array();
	
	// Open the bed file for parsing
	$bed_file = fopen($bed_path, "r");
	
	// If the bed file couldn't be opened
	if ($bed_file === false) {
		return false;
	}
	
	// Set a variable to hold the number of columns in the BED file - this should not change through it
	$num_columns_in_bed = "";
	
	// Go through the file line by line
	while (($line = fgets($bed_file)) !== false) {
		// Split the row into an array by column
		$columns = explode("\t", $line);
		
		// If the number of columns in the BED file hasn't been set yet, set it
		if ($num_columns_in_bed == "") {
			$num_columns_in_bed = count($columns);
		}
		
		// If it's an empty line, ignore it
		if (count($columns) == 0) {
			continue;
		// If the number of columns is not the same as in the first line
		} elseif (count($columns) != $num_columns_in_bed) {
			return false;
		}
		
		// Determine the number of times the current chr, start and end have been seen before for storing BED intervals with identical coordinates separately
		// Note: the reason for this is that you can get a BED file where an identical interval has been produced with different downstream columns annotations, a good example is intersecting a 1bp breakpoint with all genes where 2 genes are both at the breakpoint position; this would produce 2 lines of output and the 4th column can't be used to differentiate them so an event ID per coordinate set is needed
		if (!isset($bed_regions[$columns[0]][$columns[1]][$columns[2]])) {
			$current_event_id = 0;
		} else {
			$current_event_id = count($bed_regions[$columns[0]][$columns[1]][$columns[2]]);
		}
				
		// Go through each column starting with the third one (end coordinates, 0-indexed so $i == 2)
		for ($i = 2; $i < count($columns); $i++) {
			// If the current column is column 3, define an empty array to hold any further columns
			if ($i == 2) {
				$bed_regions[$columns[0]][$columns[1]][$columns[2]][$current_event_id] = array();
			// Otherwise save the column value with the key of the real column number	
			} else {
				// Remove space/newline characters from the fourth column (the last one which has \n at the end)
				$columns[$i] = preg_replace("/[\n\r\s]*$/", "", $columns[$i]);
				
				$bed_regions[$columns[0]][$columns[1]][$columns[2]][$current_event_id][($i + 1)] = $columns[$i];
			}
		}
	}
	
	fclose($bed_file);
	
	return $bed_regions;
}

?>
