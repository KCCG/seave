<?php
	
// These are functions for importing GBS data

#############################################
# STORE INFORMATION FOR GBS IMPORTATION IN SESSION VARIABLES
#############################################

function log_gbs_import_info($session_variable_name, $information) {
	$_SESSION["gbs_import_".$session_variable_name] = $information;
}

#############################################
# GBS IMPORT FROM CNVnator FILE
#############################################

function gbs_import_cnvnator($open_data_file_handle, $sample) {
	// Create an array to store block information
	$genome_block_store = array();
	
	// Go through the file line by line
	while (($line = fgets($open_data_file_handle)) !== false) {
		// Split the row into an array by column
		$columns = explode("\t", $line);
		
		// If it's an empty line, ignore it
		if (count($columns) == 0) {
			continue;
		}
		
		// If the line does not have the 4 expected columns, quit out and display it to the user
		if (count($columns) != 4) {
			// Save the line to display to the user
			log_gbs_import_info("error", "A row in your CNVnator output file did not contain the right amount of columns, it should be 4. Line: ".$line);
			
			return false;
		}
		
		// Remove space/newline characters from the fourth column (the last one which has \n at the end)
		$columns[3] = preg_replace("/[\n\r\s]*/", "", $columns[3]);
		
		// Make sure the last 3 columns are numeric (the copy number can be zero which sometimes returns false on is_numeric)
		if (!is_numeric($columns[1]) || !is_numeric($columns[2]) || !is_numeric($columns[3])) {
			// Save the line to display to the user
			log_gbs_import_info("error", "A column that should be numeric was found not to be in your CNVnator output. Line: ".$line);
			
			return false;
		}
		
		// Save the current genome block ID, this is zero based so using count(<current block ids>) gets the next new one as count is 1 based
		$current_genome_block_id = count($genome_block_store);
		
		// Save the block coordinates
		$genome_block_store[$current_genome_block_id]["chromosome"] = $columns[0];
		$genome_block_store[$current_genome_block_id]["start"] = $columns[1];
		$genome_block_store[$current_genome_block_id]["end"] = $columns[2];
		// Format: $genome_block_store[block id][chromosome/start/end] = <value>
		
		// Save the copy number which is stored normalized to 1 by CNVnator so de-normalize it to 2
		$genome_block_store[$current_genome_block_id]["event"] = $columns[3]/0.5;
		// Format: $genome_block_store[block id]["event"] = <value>
		
		// Save the sample
		$genome_block_store[$current_genome_block_id]["samples"][] = $sample;
		// Format: $genome_block_store[block id]["samples"] = <array of samples>
	}
	
	return $genome_block_store;
}

#############################################
# GBS IMPORT FROM ROHmer FILE
#############################################

function gbs_import_rohmer($open_data_file_handle, $sample) {
	// Create an array to store block information
	$genome_block_store = array();
	
	// Go through the file line by line
	while (($line = fgets($open_data_file_handle)) !== false) {
		// Split the row into an array by column
		$columns = explode("\t", $line);
		
		// If it's an empty line, ignore it
		if (count($columns) == 0) {
			continue;
		}
		
		// If the line does not have the 5 expected columns, quit out and display it to the user
		if (count($columns) != 5) {
			// Save the line to display to the user
			log_gbs_import_info("error", "A row in your ROHmer output file did not contain the right amount of tab delimited columns, it should be 5. Line: ".$line);
			
			return false;
		}
		
		// Remove space/newline characters from the fourth column (the last one which has \n at the end)
		$columns[4] = preg_replace("/[\n\r\s]*/", "", $columns[4]);
		
		// Make sure the start, end and HET_freq columns are numeric
		if (!is_numeric($columns[1]) || !is_numeric($columns[2]) || !is_numeric($columns[4])) {
			// Save the line to display to the user
			log_gbs_import_info("error", "A column that should be numeric was found not to be in your ROHmer output. Line: ".$line);
			
			return false;
		}
		
		// Save the current genome block ID, this is zero based so using count(<current block ids>) gets the next new one as count is 1 based
		$current_genome_block_id = count($genome_block_store);
		
		// Save the block coordinates
		$genome_block_store[$current_genome_block_id]["chromosome"] = $columns[0];
		$genome_block_store[$current_genome_block_id]["start"] = $columns[1];
		$genome_block_store[$current_genome_block_id]["end"] = $columns[2];
		// Format: $genome_block_store[block id][chromosome/start/end] = <value>
		
		// Save the sample for the block
		$genome_block_store[$current_genome_block_id]["samples"][] = $sample;
		// Format: $genome_block_store[block id]["samples"] = <array of samples>
		
		// Save the event type
		$genome_block_store[$current_genome_block_id]["event"] = "RoH";
		// Format: $genome_block_store[block id]["event"] = <value>
		
		// Save the % HET sites annotation
		$genome_block_store[$current_genome_block_id]["annotations"]["HET_freq"][$sample] = $columns[4];
		// Format: $genome_block_store[block id]["annotations"][tag][sample] = <value>
	}
	
	return $genome_block_store;
}

#############################################
# GBS IMPORT FROM CNVkit FILE
#############################################

function gbs_import_cnvkit($open_data_file_handle, $sample) {
	// Create an array to store block information
	$genome_block_store = array();
	
	// Go through the file line by line
	while (($line = fgets($open_data_file_handle)) !== false) {
		// Split the row into an array by column
		$columns = explode("\t", $line);
		
		// If it's an empty line, ignore it
		if (count($columns) == 0) {
			continue;
		}
		
		// Ignore the header line
		if ($columns[0] == "chromosome") {
			continue;
		}
		
		// If the line does not have the expected number of columns, quit out and display an errror to the user
		if (count($columns) != 8) {
			// Save the line to display to the user
			log_gbs_import_info("error", "A row in your CNVkit output file did not contain the right amount of tab delimited columns, it should be 8. Line: ".$line);
			
			return false;
		}
		
		// Remove space/newline characters from the fourth column (the last one which has \n at the end)
		$columns[7] = preg_replace("/[\n\r\s]*/", "", $columns[7]);
		
		// Make sure the start, end and log_2 columns are numeric
		if (!is_numeric($columns[1]) || !is_numeric($columns[2]) || !is_numeric($columns[4])) {
			// Save the line to display to the user
			log_gbs_import_info("error", "A column that should be numeric was found not to be in your CNVkit output. Line: ".$line);
			
			return false;
		}
		
		// Save the current genome block ID, this is zero based so using count(<current block ids>) gets the next new one as count is 1 based
		$current_genome_block_id = count($genome_block_store);
		
		// Save the block coordinates
		$genome_block_store[$current_genome_block_id]["chromosome"] = $columns[0];
		$genome_block_store[$current_genome_block_id]["start"] = $columns[1];
		$genome_block_store[$current_genome_block_id]["end"] = $columns[2];
		// Format: $genome_block_store[block id][chromosome/start/end] = <value>
		
		// Save the sample for the block
		$genome_block_store[$current_genome_block_id]["samples"][] = $sample;
		// Format: $genome_block_store[block id]["samples"] = <array of samples>
		
		// Save the copy number which is stored as log2 by converting with 2^[log2 value]*2
		$genome_block_store[$current_genome_block_id]["event"] = round(pow(2, $columns[4])*2, 2);
		// Format: $genome_block_store[block id]["event"] = <value>
		
		// Save the annotations
		$genome_block_store[$current_genome_block_id]["annotations"]["depth"][$sample] = $columns[5];
		$genome_block_store[$current_genome_block_id]["annotations"]["probes"][$sample] = $columns[6];
		$genome_block_store[$current_genome_block_id]["annotations"]["weight"][$sample] = $columns[7];
		// Format: $genome_block_store[block id]["annotations"][tag][sample] = <value>
	}
	
	return $genome_block_store;
}

#############################################
# GBS IMPORT FROM Sequenza FILE
#############################################

function gbs_import_sequenza($open_data_file_handle, $sample) {
	// Create an array to store block information
	$genome_block_store = array();
	
	// Go through the file line by line
	while (($line = fgets($open_data_file_handle)) !== false) {
		// Split the row into an array by column
		$columns = explode("\t", $line);
		
		// If it's an empty line, ignore it
		if (count($columns) == 0) {
			continue;
		}
		
		// Ignore the header line
		if ($columns[0] == "\"chromosome\"") {
			continue;
		}
		
		// If the line does not have the 4 expected columns, quit out and display it to the user
		if (count($columns) != 13) {
			// Save the line to display to the user
			log_gbs_import_info("error", "A row in your Sequenza output file did not contain the right amount of columns, it should be 13. Line: ".$line);
			
			return false;
		}
		
		// Remove space/newline characters from the 13th column (the last one which has \n at the end)
		$columns[12] = preg_replace("/[\n\r\s]*/", "", $columns[12]);
		
		// Remove quotes around chromosomes
		$columns[0] = preg_replace("/\"/", "", $columns[0]);
		
		// Make sure all columns are numeric
		foreach (array_keys($columns) as $column) {
			if (!is_numeric($column)) {
				// Save the line to display to the user
				log_gbs_import_info("error", "Found a line which contains one or more non-numeric values. All values should be numeric except the chromosome. Line: ".$line);
				
				return false;
			}
		}
		
		// Ignore all events at a copy number of 2 where the A and B alleles are 1 and 1 (i.e. it is a normal block of the genome with no LoH)
		if ($columns[9] == 2 && $columns[10] == 1 && $columns[11] == 1) {
			continue;
		}
					
		// Save the current genome block ID, this is zero based so using count(<current block ids>) gets the next new one as count is 1 based
		$current_genome_block_id = count($genome_block_store);
		
		// Save the block coordinates
		$genome_block_store[$current_genome_block_id]["chromosome"] = $columns[0];
		$genome_block_store[$current_genome_block_id]["start"] = $columns[1];
		$genome_block_store[$current_genome_block_id]["end"] = $columns[2];
		// Format: $genome_block_store[block id][chromosome/start/end] = <value>
		
		// Save the sample for the block
		$genome_block_store[$current_genome_block_id]["samples"][] = $sample;
		// Format: $genome_block_store[block id]["samples"] = <array of samples>
		
		// Save the copy number for the block
		$genome_block_store[$current_genome_block_id]["event"] = $columns[9];
		// Format: $genome_block_store[block id]["event"] = <value>
		
		// Save the annotations for the block
		$genome_block_store[$current_genome_block_id]["annotations"]["CN.A"][$sample] = $columns[10];
		$genome_block_store[$current_genome_block_id]["annotations"]["CN.B"][$sample] = $columns[11];
		$genome_block_store[$current_genome_block_id]["annotations"]["AF.B"][$sample] = $columns[3];
		$genome_block_store[$current_genome_block_id]["annotations"]["DP.ratio"][$sample] = $columns[6];
		// Format: $genome_block_store[block id]["annotations"][tag][sample] = <value>
	}
	
	return $genome_block_store;
}

#############################################
# GBS IMPORT FROM PURPLE FILE
#############################################

function gbs_import_purple($open_data_file_handle, $sample) {
	// Create an array to store block information
	$genome_block_store = array();
	
	// Go through the file line by line
	while (($line = fgets($open_data_file_handle)) !== false) {
		// Split the row into an array by column
		$columns = explode("\t", $line);
		
		// If it's an empty line, ignore it
		if (count($columns) == 0) {
			continue;
		}
		
		// Ignore the header line
		if ($columns[0] == "#chromosome") {
			continue;
		}
		
		// If the line does not have the expected number of columns, quit out and display it to the user
		if (count($columns) != 10) {
			// Save the line to display to the user
			log_gbs_import_info("error", "A row in your PURPLE output file did not contain the right amount of columns, it should be 10. Line: ".$line);
			
			return false;
		}
		
		// Remove space/newline characters from the 10th column (the last one which has \n at the end)
		$columns[9] = preg_replace("/[\n\r\s]*/", "", $columns[9]);
		
		// Make sure the coordinate and copy number columns are numeric
		if (!is_numeric($columns[1]) || !is_numeric($columns[2]) || !is_numeric($columns[3])) {
			// Save the line to display to the user
			log_gbs_import_info("error", "Found a line which contains one or more non-numeric values for coordinates or copy number. Line: ".$line);
			
			return false;
		}
					
		// Save the current genome block ID, this is zero based so using count(<current block ids>) gets the next new one as count is 1 based
		$current_genome_block_id = count($genome_block_store);
		
		// Save the block coordinates
		$genome_block_store[$current_genome_block_id]["chromosome"] = $columns[0];
		$genome_block_store[$current_genome_block_id]["start"] = $columns[1];
		$genome_block_store[$current_genome_block_id]["end"] = $columns[2];
		// Format: $genome_block_store[block id][chromosome/start/end] = <value>
		
		// Save the sample for the block
		$genome_block_store[$current_genome_block_id]["samples"][] = $sample;
		// Format: $genome_block_store[block id]["samples"] = <array of samples>
		
		// Save the copy number for the block (round to 2dp)
		$genome_block_store[$current_genome_block_id]["event"] = round($columns[3], 2);
		// Format: $genome_block_store[block id]["event"] = <value>
		
		// Save the annotations for the block
		$genome_block_store[$current_genome_block_id]["annotations"]["bafCount"][$sample] = $columns[4];
		$genome_block_store[$current_genome_block_id]["annotations"]["observedBAF"][$sample] = $columns[5];
		$genome_block_store[$current_genome_block_id]["annotations"]["actualBAF"][$sample] = $columns[6];
		$genome_block_store[$current_genome_block_id]["annotations"]["segmentStartSupport"][$sample] = $columns[7];
		$genome_block_store[$current_genome_block_id]["annotations"]["segmentEndSupport"][$sample] = $columns[8];
		$genome_block_store[$current_genome_block_id]["annotations"]["method"][$sample] = $columns[9];
		// Format: $genome_block_store[block id]["annotations"][tag][sample] = <value>
	}
	
	return $genome_block_store;
}

#############################################
# GBS IMPORT FROM VarpipeSV FILE
#############################################

function gbs_import_varpipesv($open_data_file_handle, $samples, $import_type) {
	// Create an array to store block information
	$genome_block_store = array();
	
	// Array to store unique instances of all annotation tags found in the VCF
	$unique_annotation_tags = array();
	
	// Create an array to store linked blocks
	$block_links = array();
	
	#############################################
	# Validate input file as being from VarpipeSV
	#############################################
	
	// Go through the file line by line for QC purposes
	while (($line = fgets($open_data_file_handle)) !== false) {
		// Make sure the VCF includes a "#source=VarpipeSV" statement
		if (preg_match("/#source=VarpipeSV/", $line)) {
			$varpipesv_flag = 1;
			
			break;
		}
	}
	
	if (!isset($varpipesv_flag)) {
		log_gbs_import_info("error", "Your VCF does not appear to have been produced by VarpipeSV.");
		
		return false;
	}
	
	#############################################
	
	// Reset file pointer to the start of the file to go through it again
	fseek($open_data_file_handle, 0);
	
	#############################################
	
	// Go through the file line by line for saving data
	while (($line = fgets($open_data_file_handle)) !== false) {
					
		#############################################
		# QC the line
		#############################################
		
		// If the line is a header line, ignore it
		if (preg_match("/^#.*/", $line)) {
			continue;
		}
		
		// Split the row into an array by column
		$columns = explode("\t", $line);
		
		// If it's an empty line, ignore it
		if (count($columns) == 0 || count($columns) == 1) {
			continue;
		}
		
		// If the line does not have the number of expected columns, quit out and display it to the user
		if (count($columns) != (9 + count($samples))) {
			log_gbs_import_info("error", "A line in your VarpipeSV output file did not contain the right amount of columns (9 + the number of samples). Line: ".$line);
		
			return false;
		}
		
		#############################################
		# Extract the event type and FORMAT block tags
		#############################################
		
		// Grab the event type
		preg_match('/SVTYPE=([\w]*);/', $line, $matches_event_type);
		
		// Ignore non-DUP/DEL/BND/INV rows
		if (!in_array($matches_event_type[1], array("DEL", "DUP", "BND", "INV"))) {
			continue;
		}
		
		// Save the FORMAT tags for the current row
		$format_tags = explode(":", $columns[8]);
								
		#############################################
		# Extract the end or pair coordinates of the block
		#############################################
	
		// If the event is a DEL, DUP or INV, only save the end coordinate (chr is the same)
		if (in_array($matches_event_type[1], array("DEL", "DUP", "INV"))) {
			preg_match('/END=([0-9]*)/', $line, $matches_end_coordinate);
		// If the event is a BND, save the chr and coordinate of the paired location
		} elseif ($matches_event_type[1] == "BND") {
			preg_match('/[\[\]]([\w\.]*):([0-9]*)[\[\]]/', $columns[4], $matches_end_coordinate);
		}

		#############################################
		# Save the block(s) coordinates
		#############################################
		
		// Save the current genome block ID, this is zero based so using count(<current block ids>) gets the next new one as count is 1 based
		$current_genome_block_id = count($genome_block_store);

		// If the event is a DEL or DUP, only save it for the one set of coordinates as one block
		if ($matches_event_type[1] == "DEL" || $matches_event_type[1] == "DUP") {
			$genome_block_store[$current_genome_block_id]["chromosome"] = $columns[0];
			$genome_block_store[$current_genome_block_id]["start"] = $columns[1];
			$genome_block_store[$current_genome_block_id]["end"] = $matches_end_coordinate[1];
		// If the event is a INV, save both sets of coordinates as 2 blocks
		} elseif ($matches_event_type[1] == "INV") {
			// First block
			$genome_block_store[$current_genome_block_id]["chromosome"] = $columns[0];
			$genome_block_store[$current_genome_block_id]["start"] = $columns[1];
			$genome_block_store[$current_genome_block_id]["end"] = ($columns[1]+1);
			
			// Paired block
			$genome_block_store[($current_genome_block_id + 1)]["chromosome"] = $columns[0];
			$genome_block_store[($current_genome_block_id + 1)]["start"] = $matches_end_coordinate[1];
			$genome_block_store[($current_genome_block_id + 1)]["end"] = ($matches_end_coordinate[1]+1);
		// If the event is a BND, save both sets of coordinates as 2 blocks
		} elseif ($matches_event_type[1] == "BND") {
			// First block
			$genome_block_store[$current_genome_block_id]["chromosome"] = $columns[0];
			$genome_block_store[$current_genome_block_id]["start"] = $columns[1];
			$genome_block_store[$current_genome_block_id]["end"] = ($columns[1]+1);
			
			// Paired block
			$genome_block_store[($current_genome_block_id + 1)]["chromosome"] = $matches_end_coordinate[1];
			$genome_block_store[($current_genome_block_id + 1)]["start"] = $matches_end_coordinate[2];
			$genome_block_store[($current_genome_block_id + 1)]["end"] = ($matches_end_coordinate[2]+1);
		}
		// Format: $genome_block_store[block id][chromosome/start/end] = <value>
		
		#############################################
		# Save the block(s) event type
		#############################################
		
		$genome_block_store[$current_genome_block_id]["event"] = $matches_event_type[1];
		// Format: $genome_block_store[block id]["event"] = <value>
		
		// If the event is a BND or INV, save the event type for the second block too
		if (in_array($matches_event_type[1], array("BND", "INV"))) {
			$genome_block_store[($current_genome_block_id + 1)]["event"] = $matches_event_type[1];
		}

		#############################################
		# Save desired annotations from the INFO block for later saving per sample
		#############################################
		
		// Save these in a separate variable to be added into $genome_block_store along with the FORMAT tags
		$info_annotations_current_line = array();
		
		// Split the info tags into an array
		$info_tags = explode(";", $columns[7]);
		
		// Go through each info tag
		foreach ($info_tags as $info_tag) {
			// If the current tag has a name=value, extract them
			if (!preg_match('/(.*)=(.*)/', $info_tag, $info_tags_matches)) {
				continue;
			}
			
			//$info_tags_matches[1] = <tag name>
			//$info_tags_matches[2] = <tag value>
			
			// If the current tag name is one of the annotation tags to save, save it
			if (in_array($info_tags_matches[1], $GLOBALS['default_varpipesv_columns'])) {
				$info_annotations_current_line[$info_tags_matches[1]] = $info_tags_matches[2];
				
				// Go through each tag and see if it has been seen before, if not add it to an array of unique tags
				if (!in_array($info_tags_matches[1], $unique_annotation_tags)) {
					array_push($unique_annotation_tags, $info_tags_matches[1]);
				}
			}
		}
		
		#############################################
		# Save the samples and annotations for each sample for the block(s)
		#############################################
		
		// Go through each sample block to check which samples are associated with the event
		for ($i = 9; $i < count($columns); $i++) {
			// If the current sample is affected by the current block
			if (preg_match('/1\/1/', $columns[$i])) {
				// Save the sample for the first block
				$genome_block_store[$current_genome_block_id]["samples"][] = $samples[($i-9)];
				// Format: $genome_block_store[block id]["samples"] = <array of samples>
				
				// If the event is a BND or INV, save the sample for the second block too
				if (in_array($matches_event_type[1], array("BND", "INV"))) {
					$genome_block_store[($current_genome_block_id + 1)]["samples"][] = $samples[($i-9)];
				}
				
				// Split the FORMAT tag values
				$sample_tag_values = explode(":", $columns[$i]);
				
				// If the number of values for the current sample is not the same as the number of tags from the FORMAT block
				if (count($format_tags) != count($sample_tag_values)) {
					log_gbs_import_info("error", "Found a line where one or more of the samples do not contain annotations for all FORMAT tags. Line: ".$line);
		
					return false;
				}
				
				// Go through every FORMAT tag
				for ($x = 0; $x < count($format_tags); $x++) {
					// Only save annotation tags + values for annotation tags that we want to save
					if (!in_array($format_tags[$x], $GLOBALS['default_varpipesv_columns'])) {
						continue;
					// If the tag is one of the allowed/expected ones
					} else {
						// Go through each tag and see if it has been seen before, if not add it to an array of unique tags
						if (!in_array($format_tags[$x], $unique_annotation_tags)) {
							array_push($unique_annotation_tags, $format_tags[$x]);
						}
					}
					
					// Remove spaces and newlines from the tag value
					$sample_tag_values[$x] = preg_replace("/[\n\r\s]*/", "", $sample_tag_values[$x]);
					
					// Save the annotation for the first block
					$genome_block_store[$current_genome_block_id]["annotations"][$format_tags[$x]][$samples[($i-9)]] = $sample_tag_values[$x];
					// Format: $genome_block_store[block id]["annotations"][tag][sample] = <value>
					
					// If the event is a BND or INV, save the annotation for the second block too
					if (in_array($matches_event_type[1], array("BND", "INV"))) {
						$genome_block_store[($current_genome_block_id + 1)]["annotations"][$format_tags[$x]][$samples[($i-9)]] = $sample_tag_values[$x];
					}
				}
				
				// Go through each annotation extracted from the INFO block that is to be added as an annotation to the block + sample
				foreach (array_keys($info_annotations_current_line) as $info_annotation) {
					// Save the annotation for the first block
					$genome_block_store[$current_genome_block_id]["annotations"][$info_annotation][$samples[($i-9)]] = $info_annotations_current_line[$info_annotation];
					// Format: $genome_block_store[block id]["annotations"][tag][sample] = <value>

					// If the event is a BND or INV, save the annotation for the second block too
					if (in_array($matches_event_type[1], array("BND", "INV"))) {
						$genome_block_store[($current_genome_block_id + 1)]["annotations"][$info_annotation][$samples[($i-9)]] = $info_annotations_current_line[$info_annotation];
					}
				}
			}
		}

		#############################################
		# Make sure at least one sample was associated with the current row
		#############################################
		
		if (!isset($genome_block_store[$current_genome_block_id]["samples"])) {
			log_gbs_import_info("error", "Found a line where no sample was affected by the block. Line: ".$line);
		
			return false;
		}
					
		#############################################
		# Link the 2 breakpoint blocks if the current row is a BND or INV
		#############################################
		
		if (in_array($matches_event_type[1], array("BND", "INV"))) {
			// Determine the current block link ID as the count of the array
			$current_block_link_id = count($block_links);
			
			// Save the link type
			$block_links[$current_block_link_id]["link_type"] = $matches_event_type[1];
			
			// Save the two blocks
			$block_links[$current_block_link_id]["linked_blocks"] = array();
			array_push($block_links[$current_block_link_id]["linked_blocks"], $current_genome_block_id);
			array_push($block_links[$current_block_link_id]["linked_blocks"], ($current_genome_block_id + 1));
		}
	}
	
	return array($genome_block_store, $block_links, $unique_annotation_tags);
}

#############################################
# GBS IMPORT FROM Manta FILE
#############################################

function gbs_import_manta($open_data_file_handle, $samples, $import_type) {
	// Create an array to store block information
	$genome_block_store = array();
	
	// Array to store unique instances of all annotation tags found in the VCF
	$unique_annotation_tags = array();
	
	// Array to store the VCF row IDs for BND events with their corresponding genome block ID for linking pairs
	$bnd_id_to_gb_id = array();
	
	// Create an array to store linked blocks
	$block_links = array();
	
	#############################################
	# Validate input file as being from Manta
	#############################################
	
	// Go through the file line by line for QC purposes
	while (($line = fgets($open_data_file_handle)) !== false) {
		// Make sure the VCF includes a "##source=GenerateSVCandidates" statement put in by Manta
		if (preg_match("/##source=GenerateSVCandidates/", $line)) {
			$manta_flag = 1;
			
			break;
		}
	}
	
	if (!isset($manta_flag)) {
		log_gbs_import_info("error", "Your VCF does not appear to have been produced by Manta.");
		
		return false;
	}
	
	#############################################
	# Validate samples
	#############################################
	
	// If only 1 sample is present in the VCF, there is no normal/tumour pair
	if (count($samples) == 1) {
		log_gbs_import_info("error", "The Manta VCF you supply must have at least 2 samples, the first of which must be a normal");
		
		return false;
	}
	
	// Remove the first sample which should be the germline
	unset($samples[0]);
	
	// Re-index array
	$samples = array_values($samples);
	
	#############################################
	
	// Reset file pointer to the start of the file to go through it again
	fseek($open_data_file_handle, 0);
	
	#############################################
	
	// Go through the file line by line for saving data
	while (($line = fgets($open_data_file_handle)) !== false) {
					
		#############################################
		# QC the line
		#############################################
		
		// If the line is a header line, ignore it
		if (preg_match("/^#.*/", $line)) {
			continue;
		}
		
		// Split the row into an array by column
		$columns = explode("\t", $line);
		
		// If it's an empty line, ignore it
		if (count($columns) == 0 || count($columns) == 1) {
			continue;
		}
		
		// If the line does not have the number of expected columns, quit out and display it to the user
		if (count($columns) != (10 + count($samples))) {
			log_gbs_import_info("error", "A line in your Manta output file did not contain the right amount of columns (10 + the number of tumour samples). Line: ".$line);
		
			return false;
		}
		
		#############################################
		# Extract the event type and FORMAT block tags
		#############################################
		
		// Grab the event type
		preg_match('/SVTYPE=([\w]*);/', $line, $matches_event_type);
		
		// Ignore non-DUP/DEL/BND/INV rows
		if (!in_array($matches_event_type[1], array("DEL", "DUP", "BND", "INV"))) {
			continue;
		}
		
		$event_is_tandem_dup = 0;
		
		// Check if the event is a tandem duplication (these are marked as SVTYPE=DUP so need a separate parse)
		if (preg_match('/<DUP:TANDEM>/', $line)) {
			$event_is_tandem_dup = 1;
		}
		
		// Save the FORMAT tags for the current row
		$format_tags = explode(":", $columns[8]);
		
		#############################################
		# Extract the end or pair coordinates of the block
		#############################################
		
		$matches_end_coordinate = "";
		
		// If the event is a DEL, DUP or INV, only save the end coordinate (chr is the same)
		if (in_array($matches_event_type[1], array("DEL", "DUP", "INV"))) {
			preg_match('/END=([0-9]*)/', $line, $matches_end_coordinate);
			
			// If no end coordinate was found
			if ($matches_end_coordinate == "") {
				log_gbs_import_info("error", "Could not find the event end coordinate for the event on line: ".$line);
			
				return false;
			}
		// If the event is a BND, extract the MATEID
		} elseif ($matches_event_type[1] == "BND") {
			preg_match('/MATEID=(.*?);/', $columns[7], $matches_mateid); // 8th column is INFO
			
			// If the MATEID was not found
			if (!isset($matches_mateid[1])) {
				log_gbs_import_info("error", "Could not find the MATEID for a BND variant on line: ".$line);
		
				return false;
			}
		}
		
		#############################################
		# Parse any confidence intervals around the start and/or end
		#############################################
		
		// Find the confidence interval around the start coordinate
		preg_match('/CIPOS=([\-0-9]*),([0-9]*);/', $line, $matches_cipos);
		
		// Find the confidence interval around the end coordinate
		preg_match('/CIEND=([\-0-9]*),([0-9]*);/', $line, $matches_ciend);
		
		// If a confidence interval around pos was found, save the offsets
		if (isset($matches_cipos[1], $matches_cipos[2])) {
			$cipos_start_offset = $matches_cipos[1];
			$cipos_end_offset = $matches_cipos[2];
		// Otherwise make the offsets zero so just the breakpoint will be saved
		} else {
			$cipos_start_offset = 0;
			$cipos_end_offset = 0;
		}
		
		// If a confidence interval around end was found, save the offsets
		if (isset($matches_ciend[1], $matches_ciend[2])) {
			$ciend_start_offset = $matches_ciend[1];
			$ciend_end_offset = $matches_ciend[2];
		// Otherwise make the offsets zero so just the breakpoint will be saved
		} else {
			$ciend_start_offset = 0;
			$ciend_end_offset = 0;
		}
		
		#############################################
		# Save the block(s) coordinates
		#############################################
		
		// Save the current genome block ID, this is zero based so using count(<current block ids>) gets the next new one as count is 1 based
		$current_genome_block_id = count($genome_block_store);

		// If the event is a DEL or DUP, only save it for the one set of coordinates as one block
		if ($matches_event_type[1] == "DEL" || $matches_event_type[1] == "DUP") {
			$genome_block_store[$current_genome_block_id]["chromosome"] = $columns[0];
			$genome_block_store[$current_genome_block_id]["start"] = $columns[1] + $cipos_start_offset; // The start offset will always be negative or zero, ignore the pos end offset as it's already inside the block
			$genome_block_store[$current_genome_block_id]["end"] = $matches_end_coordinate[1] + $ciend_end_offset; // The end offset will always be positive or zero, ignore the end start offset as it's already inside the block
		// If the event is a INV, save both sets of coordinates as 2 blocks
		} elseif ($matches_event_type[1] == "INV") {
			// First block
			$genome_block_store[$current_genome_block_id]["chromosome"] = $columns[0];
			$genome_block_store[$current_genome_block_id]["start"] = $columns[1] + $cipos_start_offset; // The start offset will always be negative or zero
			// If there is a positive end offset
			if ($cipos_end_offset > 0) {
				$genome_block_store[$current_genome_block_id]["end"] = $columns[1] + $cipos_end_offset; // The end offset will always be positive or zero
			// If there is a start offset and no end offset, keep the end as it was called (the breakpoint will be longer than 1 already due to the start offset)
			} elseif ($cipos_end_offset == 0 && $cipos_start_offset < 0) {
				$genome_block_store[$current_genome_block_id]["end"] = $columns[1];
			// If there is no start and end offset, offset the end by 1 to make the breakpoint 1bp in length
			} else {
				$genome_block_store[$current_genome_block_id]["end"] = ($columns[1] + 1); // +1 for the event to be stored as hitting a precise basepair
			}
			
			// Paired block
			$genome_block_store[($current_genome_block_id + 1)]["chromosome"] = $columns[0];
			$genome_block_store[($current_genome_block_id + 1)]["start"] = $matches_end_coordinate[1] + $ciend_start_offset; // The start offset will always be negative or zero
			// If there is a positive end offset
			if ($ciend_end_offset > 0) {
				$genome_block_store[($current_genome_block_id + 1)]["end"] = $matches_end_coordinate[1] + $ciend_end_offset; // The end offset will always be positive or zero
			// If there is a start offset and no end offset, keep the end as it was called (the breakpoint will be longer than 1 already due to the start offset)
			} elseif ($ciend_end_offset == 0 && $ciend_start_offset < 0) {
				$genome_block_store[($current_genome_block_id + 1)]["end"] = $matches_end_coordinate[1];
			// If there is no start and end offset, offset the end by 1 to make the breakpoint 1bp in length
			} else {
				$genome_block_store[($current_genome_block_id + 1)]["end"] = ($matches_end_coordinate[1] + 1); // +1 for the event to be stored as hitting a precise basepair
			}
			
			#############################################
			
			// Link the 2 breakpoint blocks
			
			// Determine the current block link ID as the count of the array
			$current_block_link_id = count($block_links);
			
			// Save the link type
			$block_links[$current_block_link_id]["link_type"] = $matches_event_type[1];
			
			// Save the two blocks
			$block_links[$current_block_link_id]["linked_blocks"] = array();
			array_push($block_links[$current_block_link_id]["linked_blocks"], $current_genome_block_id);
			array_push($block_links[$current_block_link_id]["linked_blocks"], ($current_genome_block_id + 1));
		// If the event is a BND
		} elseif ($matches_event_type[1] == "BND") {
			// First block
			$genome_block_store[$current_genome_block_id]["chromosome"] = $columns[0];
			$genome_block_store[$current_genome_block_id]["start"] = $columns[1] + $cipos_start_offset; // The start offset will always be negative or zero, ignore the pos end offset as it's already inside the block;
			// If there is a positive end offset
			if ($cipos_end_offset > 0) {
				$genome_block_store[$current_genome_block_id]["end"] = $columns[1] + $cipos_end_offset; // The end offset will always be positive or zero
			// If there is a start offset and no end offset, keep the end as it was called (the breakpoint will be longer than 1 already due to the start offset)
			} elseif ($cipos_end_offset == 0 && $cipos_start_offset < 0) {
				$genome_block_store[$current_genome_block_id]["end"] = $columns[1];
			// If there is no start and end offset, offset the end by 1 to make the breakpoint 1bp in length
			} else {
				$genome_block_store[$current_genome_block_id]["end"] = ($columns[1] + 1); // +1 for the event to be stored as hitting a precise basepair
			}
			
			// Save the genome block ID for the variant ID
			$bnd_id_to_gb_id[$columns[2]] = $current_genome_block_id;
			
			#############################################
			
			// Link the 2 breakpoint blocks
			
			// If the mate has already been parsed, link them
			if (isset($bnd_id_to_gb_id[$matches_mateid[1]])) {
				// Determine the current block link ID as the count of the array
				$current_block_link_id = count($block_links);
				
				// Save the link type
				$block_links[$current_block_link_id]["link_type"] = $matches_event_type[1];
				
				// Save the two blocks
				$block_links[$current_block_link_id]["linked_blocks"] = array();
				array_push($block_links[$current_block_link_id]["linked_blocks"], $current_genome_block_id);
				array_push($block_links[$current_block_link_id]["linked_blocks"], $bnd_id_to_gb_id[$matches_mateid[1]]);
			}
		}
		// Format: $genome_block_store[block id][chromosome/start/end] = <value>
		
		#############################################
		# Save the block(s) event type
		#############################################
		
		// If the current line is for a tandem duplication
		if ($event_is_tandem_dup == 1) {
			$genome_block_store[$current_genome_block_id]["event"] = "TANDEMDUP";
		// For all other event types, just save what was parsed
		} else {
			$genome_block_store[$current_genome_block_id]["event"] = $matches_event_type[1];
		}
		// Format: $genome_block_store[block id]["event"] = <value>
		
		// If the event is a INV, save the event type for the second block too
		if ($matches_event_type[1] == "INV") {
			$genome_block_store[($current_genome_block_id + 1)]["event"] = $matches_event_type[1];
		}

		#############################################
		# Save desired annotations from the INFO block for later saving per sample
		#############################################
		
		// Save these in a separate variable to be added into $genome_block_store along with the FORMAT tags
		$info_annotations_current_line = array();
		
		// Split the info tags into an array
		$info_tags = explode(";", $columns[7]);
		
		#############################################
		
		// Inject the FILTER column as if it was an INFO tag
		array_push($info_tags, "FILTER=".$columns[6]);
		
		// See if the tag has been seen before, if not add it to an array of unique tags
		if (!in_array("FILTER", $unique_annotation_tags)) {
			array_push($unique_annotation_tags, "FILTER");
		}
		
		#############################################
		
		// Inject the germline PR and SR FORMAT tags (if they are present) as GPR and GSR, as if they are INFO tags
		
		// Split the germline FORMAT tag values
		$germline_tag_values = explode(":", $columns[9]);
		
		// Go through every FORMAT tag
		for ($i = 0; $i < count($format_tags); $i++) {
			// If the FORMAT tag is one that needs to be saved from the germline
			if (in_array($format_tags[$i], array("PR", "SR"))) {
				// Inject the tag with a "G" prefix and use the germline value
				array_push($info_tags, "G".$format_tags[$i]."=".$germline_tag_values[$i]);
				
				// See if the tag has been seen before, if not add it to an array of unique tags
				if (!in_array("G".$format_tags[$i], $unique_annotation_tags)) {
					array_push($unique_annotation_tags, "G".$format_tags[$i]);
				}
			}
		}
		
		#############################################
		
		// Go through each info tag
		foreach ($info_tags as $info_tag) {
			// If the current tag has a name=value, extract them
			if (preg_match('/(.*)=(.*)/', $info_tag, $info_tags_matches)) {
				$tag = $info_tags_matches[1];
				$tag_value = $info_tags_matches[2];
			} else {
				$tag = $info_tag;
				$tag_value = "1";
			}
			
			// If the current tag name is one of the annotation tags to save, save it
			if (in_array($tag, $GLOBALS['default_manta_columns'])) {
				$info_annotations_current_line[$tag] = $tag_value;
				
				// See if the tag has been seen before, if not add it to an array of unique tags
				if (!in_array($tag, $unique_annotation_tags)) {
					array_push($unique_annotation_tags, $tag);
				}
			}
		}
		
		#############################################
		# Save the samples and annotations for each sample for the block(s)
		#############################################
		
		// Go through each sample block (not counting the first one which is assumed to be the normal) to check which samples are associated with the event
		for ($i = 10; $i < count($columns); $i++) { // 10 is the second sample
			// Save the sample for the first block
			$genome_block_store[$current_genome_block_id]["samples"][] = $samples[($i-10)];
			// Format: $genome_block_store[block id]["samples"] = <array of samples>
			
			// If the event is INV, save the sample for the second block too
			if ($matches_event_type[1] == "INV") {
				$genome_block_store[($current_genome_block_id + 1)]["samples"][] = $samples[($i-10)];
			}
			
			// Split the FORMAT tag values
			$sample_tag_values = explode(":", $columns[$i]);
			
			// If the number of values for the current sample is not the same as the number of tags from the FORMAT block
			if (count($format_tags) != count($sample_tag_values)) {
				log_gbs_import_info("error", "Found a line where one or more of the samples do not contain annotations for all FORMAT tags. Line: ".$line);
	
				return false;
			}
			
			// Go through every FORMAT tag
			for ($x = 0; $x < count($format_tags); $x++) {
				// Only save annotation tags + values for annotation tags that we want to save
				if (!in_array($format_tags[$x], $GLOBALS['default_manta_columns'])) {
					continue;
				// If the tag is one of the allowed/expected ones
				} else {
					// Go through each tag and see if it has been seen before, if not add it to an array of unique tags
					if (!in_array($format_tags[$x], $unique_annotation_tags)) {
						array_push($unique_annotation_tags, $format_tags[$x]);
					}
				}
				
				// Remove spaces and newlines from the tag value
				$sample_tag_values[$x] = preg_replace("/[\n\r\s]*/", "", $sample_tag_values[$x]);
				
				// Save the annotation for the first block
				$genome_block_store[$current_genome_block_id]["annotations"][$format_tags[$x]][$samples[($i-10)]] = $sample_tag_values[$x];
				// Format: $genome_block_store[block id]["annotations"][tag][sample] = <value>
				
				// If the event is a INV, save the annotation for the second block too
				if ($matches_event_type[1] == "INV") {
					$genome_block_store[($current_genome_block_id + 1)]["annotations"][$format_tags[$x]][$samples[($i-10)]] = $sample_tag_values[$x];
				}
			}
			
			// Go through each annotation extracted from the INFO block that is to be added as an annotation to the block + sample
			foreach (array_keys($info_annotations_current_line) as $info_annotation) {
				// Save the annotation for the first block
				$genome_block_store[$current_genome_block_id]["annotations"][$info_annotation][$samples[($i-10)]] = $info_annotations_current_line[$info_annotation];
				// Format: $genome_block_store[block id]["annotations"][tag][sample] = <value>

				// If the event is a INV, save the annotation for the second block too
				if ($matches_event_type[1] == "INV") {
					$genome_block_store[($current_genome_block_id + 1)]["annotations"][$info_annotation][$samples[($i-10)]] = $info_annotations_current_line[$info_annotation];
				}
			}
		}
	}
	
	return array($genome_block_store, $block_links, $unique_annotation_tags);
}

#############################################
# GBS IMPORT FROM LUMPY FILE
#############################################

function gbs_import_lumpy($open_data_file_handle, $samples, $import_type) {
	// Create an array to store block information
	$genome_block_store = array();
	
	// Array to store unique instances of all annotation tags found in the VCF
	$unique_annotation_tags = array();
	
	// Create an array to store linked blocks
	$block_links = array();
	
	#############################################
	# Validate input file as being from LUMPY
	#############################################
	
	// Go through the file line by line for QC purposes
	while (($line = fgets($open_data_file_handle)) !== false) {
		// Make sure the VCF includes a "#source=LUMPY" statement
		if (preg_match("/#source=LUMPY/", $line)) {
			$lumpy_flag = 1;
			
			break;
		}
	}
	
	if (!isset($lumpy_flag)) {
		log_gbs_import_info("error", "Your VCF does not appear to have been produced by LUMPY.");
			
		return false;
	}
	
	#############################################
	
	// Reset file pointer to the start of the file to go through it again
	fseek($open_data_file_handle, 0);
	
	#############################################
	
	// Go through the file line by line for saving data
	while (($line = fgets($open_data_file_handle)) !== false) {
		// If the line is a header line, ignore it
		if (preg_match("/^#.*/", $line)) {
			continue;
		}
		
		// Split the row into an array by column
		$columns = explode("\t", $line);
		
		// If it's an empty line, ignore it
		if (count($columns) == 0) {
			continue;
		}
		
		// If the line does not have the number of expected columns, quit out and display it to the user
		if (count($columns) != (9 + count($samples))) {
			log_gbs_import_info("error", "A line in your LUMPY output file did not contain the right amount of columns (9 + the number of samples). Line: ".$line);
			
			return false;
		}
		
		#############################################
		# Extract the event type and FORMAT block tags
		#############################################
		
		// Grab the event type
		preg_match('/SVTYPE=([\w]*);/', $line, $matches_event_type);
		
		// Ignore non-DUP/DEL/BND/INV rows
		if (!in_array($matches_event_type[1], array("DEL", "DUP", "BND", "INV"))) {
			continue;
		}
		
		// Extract the FORMAT tags for the current row
		$format_tags = explode(":", $columns[8]);
		
		#############################################
		# Extract the end or pair coordinates of the block
		#############################################
		
		$matches_end_coordinate = "";
		
		// If the event is a DEL, DUP or INV, only save the end coordinate (chr is the same)
		if (in_array($matches_event_type[1], array("DEL", "DUP", "INV"))) {
			preg_match('/END=([0-9]*)/', $line, $matches_end_coordinate);
			
			// If no end coordinate was found
			if ($matches_end_coordinate == "") {
				log_gbs_import_info("error", "Could not find the event end coordinate for the event on line: ".$line);
				
				return false;
			}
		// If the event is a BND, save the chr and coordinate of the paired location
		} elseif ($matches_event_type[1] == "BND") {
			preg_match('/[\[\]]([\w\.]*):([0-9]*)[\[\]]/', $columns[4], $matches_end_coordinate);
		}
		
		#############################################
		# Save the block(s) coordinates
		#############################################
				
		// Save the current genome block ID, this is zero based so using count(<current block ids>) gets the next new one as count is 1 based
		$current_genome_block_id = count($genome_block_store);
		
		// If the event is a DEL or DUP, only save it for the one set of coordinates as one block
		if ($matches_event_type[1] == "DEL" || $matches_event_type[1] == "DUP") {
			$genome_block_store[$current_genome_block_id]["chromosome"] = $columns[0];
			$genome_block_store[$current_genome_block_id]["start"] = $columns[1];
			$genome_block_store[$current_genome_block_id]["end"] = $matches_end_coordinate[1];
		// If the event is a INV, save both sets of coordinates as 2 blocks
		} elseif ($matches_event_type[1] == "INV") {
			// First block
			$genome_block_store[$current_genome_block_id]["chromosome"] = $columns[0];
			$genome_block_store[$current_genome_block_id]["start"] = $columns[1];
			$genome_block_store[$current_genome_block_id]["end"] = ($columns[1]+1); // +1 for the event to be stored as hitting a precise basepair
			
			// Paired block
			$genome_block_store[($current_genome_block_id + 1)]["chromosome"] = $columns[0];
			$genome_block_store[($current_genome_block_id + 1)]["start"] = $matches_end_coordinate[1];
			$genome_block_store[($current_genome_block_id + 1)]["end"] = ($matches_end_coordinate[1]+1); // +1 for the event to be stored as hitting a precise basepair
			
			#############################################
			
			// Link the 2 breakpoint blocks
			
			// Determine the current block link ID as the count of the array
			$current_block_link_id = count($block_links);
			
			// Save the link type
			$block_links[$current_block_link_id]["link_type"] = $matches_event_type[1];
			
			// Save the two blocks
			$block_links[$current_block_link_id]["linked_blocks"] = array();
			array_push($block_links[$current_block_link_id]["linked_blocks"], $current_genome_block_id);
			array_push($block_links[$current_block_link_id]["linked_blocks"], ($current_genome_block_id + 1));
		// If the event is a BND
		} elseif ($matches_event_type[1] == "BND") {
			// First block
			$genome_block_store[$current_genome_block_id]["chromosome"] = $columns[0];
			$genome_block_store[$current_genome_block_id]["start"] = $columns[1];
			$genome_block_store[$current_genome_block_id]["end"] = ($columns[1]+1); // +1 for the event to be stored as hitting a precise basepair
			
			// Save the genome block ID for the BND coordinate
			$bnd_to_gb_id[$columns[0].":".$columns[1]] = $current_genome_block_id;
			
			#############################################
			
			// Link the 2 breakpoint blocks
			
			// If the mate has already been parsed, link them
			if (isset($bnd_to_gb_id[$matches_end_coordinate[1].":".$matches_end_coordinate[2]])) {
				// Determine the current block link ID as the count of the array
				$current_block_link_id = count($block_links);
				
				// Save the link type
				$block_links[$current_block_link_id]["link_type"] = $matches_event_type[1];
				
				// Save the two blocks
				$block_links[$current_block_link_id]["linked_blocks"] = array();
				array_push($block_links[$current_block_link_id]["linked_blocks"], $current_genome_block_id);
				array_push($block_links[$current_block_link_id]["linked_blocks"], $bnd_to_gb_id[$matches_end_coordinate[1].":".$matches_end_coordinate[2]]);
			}
		}
		// Format: $genome_block_store[block id][chromosome/start/end] = <value>
		
		#############################################
		# Save the block(s) event type
		#############################################
		
		$genome_block_store[$current_genome_block_id]["event"] = $matches_event_type[1];
		// Format: $genome_block_store[block id]["event"] = <DEL/DUP>
		
		// If the event is a INV, save the event type for the second block too
		if ($matches_event_type[1] == "INV") {
			$genome_block_store[($current_genome_block_id + 1)]["event"] = $matches_event_type[1];
		}
		
		#############################################
		# Save desired annotations from the INFO block for later saving per sample
		#############################################
		
		// Save these in a separate variable to be added into $genome_block_store along with the FORMAT tags
		$info_annotations_current_line = array();
		
		// Split the info tags into an array
		$info_tags = explode(";", $columns[7]);
		
		#############################################
		
		// Go through each info tag
		foreach ($info_tags as $info_tag) {
			// If the current tag has a name=value, extract them
			if (preg_match('/(.*)=(.*)/', $info_tag, $info_tags_matches)) {
				$tag = $info_tags_matches[1];
				$tag_value = $info_tags_matches[2];
			} else {
				$tag = $info_tag;
				$tag_value = "1";
			}
			
			// If the tag is in the FORMAT blocks per sample, do not save it from the INFO block; LUMPY has some of the same tags in INFO and FORMAT and the per-sample FORMAT ones are preferred
			if (in_array($tag, $format_tags)) {
				continue;
			}
			
			// If the current tag name is one of the annotation tags to save, save it
			if (in_array($tag, $GLOBALS['default_lumpy_columns'])) {
				$info_annotations_current_line[$tag] = $tag_value;
				
				// See if the tag has been seen before, if not add it to an array of unique tags
				if (!in_array($tag, $unique_annotation_tags)) {
					array_push($unique_annotation_tags, $tag);
				}
			}
		}
		
		#############################################
		# Save the samples and annotations for each sample for the block(s)
		#############################################
		
		// Go through each sample block to check which samples are associated with the event
		for ($i = 9; $i < count($columns); $i++) { // 9 is the first sample
			// Save the sample for the first block
			$genome_block_store[$current_genome_block_id]["samples"][] = $samples[($i-9)];
			// Format: $genome_block_store[block id]["samples"] = <array of samples>
			
			// If the event is INV, save the sample for the second block too
			if ($matches_event_type[1] == "INV") {
				$genome_block_store[($current_genome_block_id + 1)]["samples"][] = $samples[($i-9)];
			}
			
			// Split the FORMAT tag values
			$sample_tag_values = explode(":", $columns[$i]);
			
			// If the number of values for the current sample is not the same as the number of tags from the FORMAT block
			if (count($format_tags) != count($sample_tag_values)) {
				log_gbs_import_info("error", "Found a line where one or more of the samples do not contain annotations for all FORMAT tags. Line: ".$line);
	
				return false;
			}
			
			// Go through every FORMAT tag
			for ($x = 0; $x < count($format_tags); $x++) {
				// Only save annotation tags + values for annotation tags that we want to save
				if (!in_array($format_tags[$x], $GLOBALS['default_lumpy_columns'])) {
					continue;
				// If the tag is one of the allowed/expected ones
				} else {
					// Go through each tag and see if it has been seen before, if not add it to an array of unique tags
					if (!in_array($format_tags[$x], $unique_annotation_tags)) {
						array_push($unique_annotation_tags, $format_tags[$x]);
					}
				}
				
				// Remove spaces and newlines from the tag value
				$sample_tag_values[$x] = preg_replace("/[\n\r\s]*/", "", $sample_tag_values[$x]);
				
				// Save the annotation for the first block
				$genome_block_store[$current_genome_block_id]["annotations"][$format_tags[$x]][$samples[($i-9)]] = $sample_tag_values[$x];
				// Format: $genome_block_store[block id]["annotations"][tag][sample] = <value>
				
				// If the event is a INV, save the annotation for the second block too
				if ($matches_event_type[1] == "INV") {
					$genome_block_store[($current_genome_block_id + 1)]["annotations"][$format_tags[$x]][$samples[($i-9)]] = $sample_tag_values[$x];
				}
			}
			
			// Go through each annotation extracted from the INFO block that is to be added as an annotation to the block + sample
			foreach (array_keys($info_annotations_current_line) as $info_annotation) {
				// Save the annotation for the first block
				$genome_block_store[$current_genome_block_id]["annotations"][$info_annotation][$samples[($i-9)]] = $info_annotations_current_line[$info_annotation];
				// Format: $genome_block_store[block id]["annotations"][tag][sample] = <value>
				
				// If the event is a INV, save the annotation for the second block too
				if ($matches_event_type[1] == "INV") {
					$genome_block_store[($current_genome_block_id + 1)]["annotations"][$info_annotation][$samples[($i-9)]] = $info_annotations_current_line[$info_annotation];
				}
			}
		}
	}
	
	return array($genome_block_store, $block_links, $unique_annotation_tags);
}

#############################################
# IF A FATAL ERROR OCCURRED WITH STORING BLOCKS, DELETE ALL PARTIALLY IMPORTED DATA
#############################################

function gbs_failed_import_roll_back(array $samples, $method) {
	// Go through each sample and delete imported blocks
	foreach ($samples as $sample) {
		delete_blocks_gbs($sample, $method);
	}
	
	return true;
}

#############################################
# FIND UNIQUE CHROMOSOMES FROM PARSED BLOCKS AND ADD THEM TO GBS
#############################################

function gbs_add_chromosomes($genome_block_store) {
	$unique_chromosomes = array();
		
	// Go through every block and save all chromosomes to an array
	foreach (array_keys($genome_block_store) as $block_id) {
		// Save all chromosomes to an array
		array_push($unique_chromosomes, $genome_block_store[$block_id]["chromosome"]);
	}
	
	// Remove duplicate entries leaving unique chromosomes
	$unique_chromosomes = array_unique($unique_chromosomes);
	
	// Add the chromosomes to the database if it is not already present
	foreach ($unique_chromosomes as $chromosome) {
		if (add_chromosome_to_gbs($chromosome) === false) {
			return false;
		}
	}
	
	return true;
}

#############################################
# STORE GBS BLOCKS IN THE DB
#############################################

function gbs_store_blocks($genome_block_store, $method, $block_links) {
	// Go through every block
	foreach (array_keys($genome_block_store) as $block_id) {
		// The block event can either be copy number or an event type when no copy number is estimated (e.g. deletion, duplication) or nothing
		if (isset($genome_block_store[$block_id]["event"])) {
			$block_value = $genome_block_store[$block_id]["event"];
		} else {
			$block_value = "NULL";
		}
		
		// Import the block into the GBS and save the new block ID
		$block_store_id = import_block_into_gbs($genome_block_store[$block_id]["chromosome"], $genome_block_store[$block_id]["start"], $genome_block_store[$block_id]["end"], $method, $block_value);
		
		if ($block_store_id === false) {
			return false;
		}
		
		// Create a variable to hold multiple queries to send at once
		$sql_queries = "";
		
		// Stores the parameters for the SQL queries for execution
		$query_parameters = array();
		
		// Go through each sample
		foreach ($genome_block_store[$block_id]["samples"] as $sample) {
			// Query for linking the sample to the block
			$sql_queries .= link_sample_to_block_gbs_sql_query();
			
			array_push($query_parameters, $block_store_id, $sample);
			
			// If annotation information is present for the current block
			if (isset($genome_block_store[$block_id]["annotations"])) {
				// Go through each annotation tag
				foreach (array_keys($genome_block_store[$block_id]["annotations"]) as $annotation_tag) {
					// If the current sample has annotation information, add it to the GBS
					if (isset($genome_block_store[$block_id]["annotations"][$annotation_tag][$sample])) {
						// Query for adding the annotation to the block
						$sql_queries .= annotate_block_gbs_sql_query();
						
						array_push($query_parameters, $block_store_id, $sample, $annotation_tag, $genome_block_store[$block_id]["annotations"][$annotation_tag][$sample]);
					}
				}
			}
		}
		
		// Perform a multi-query by sending all queries at once
		$statement = $GLOBALS["mysql_connection"]->prepare($sql_queries);
	
		$statement->execute($query_parameters);
		
		$rows_affected = $statement->rowCount();
		
		// At least one row should have been affected
		if ($rows_affected === 0) {
			return false;
		}
		
		// Free the result on the server so another can be executed
		$statement->closeCursor();
		
		// If block links were submitted, save all block store IDs for each chr start end so they can be linked later
		if (isset($block_links)) {
			$stored_block_store_ids[$block_id] = $block_store_id;
		}
	}
	
	#############################################
	
	//$block_links[<link type>][<link number>][<block number>][<chromosome/start/end>] = value
	
	// If block links were submitted, link them
	if (count($block_links) > 0) {
		// Go through each link event
		foreach (array_keys($block_links) as $link_number) {
			$block_ids_for_link_event = array();
			
			// Go through every genome block ID for the current link event
			foreach ($block_links[$link_number]["linked_blocks"] as $block_id) {
				// Check that a block store DB ID has been saved for the current event block ID
				if (!isset($stored_block_store_ids[$block_id])) {
					return false;
				}
				
				// Save the block store IDs relevant to the chr, start and end within an array to be supplied to a link function along with $link_type
				array_push($block_ids_for_link_event, $stored_block_store_ids[$block_id]);					
			}
			
			// Link the blocks in the GBS
			if (!link_blocks_gbs($block_links[$link_number]["link_type"], $block_ids_for_link_event)) {
				return false;
			}
		}
	}
	
	return true;
}

#############################################
# EXTRACT SAMPLES FROM GBS INPUT FILES
#############################################

function gbs_parse_for_samples($open_data_file_handle) {
	// Array to store sample names parsed from the VCF
	$samples = array();
	
	// Go through the file line by line
	while (($line = fgets($open_data_file_handle)) !== false) {		
		// If the header line is found
		if (preg_match("/^#CHROM.*/", $line)) {
			$header_line_found_flag = 1;
			
			$columns = explode("\t", $line);
			
			// Go through each sample name and save them to an array
			for ($i = 9; $i < count($columns); $i++) {
				// Remove spaces and newlines from the sample name
				$columns[$i] = preg_replace("/[\n\r\s]*/", "", $columns[$i]);
				
				array_push($samples, $columns[$i]);
			}
			
			// Stop going through the file after the header line is found
			break;
		}
	}
	
	// Reset file pointer to the start of the file for further parsing
	fseek($open_data_file_handle, 0);
	
	// If the header line with samples was not found in the input file
	if (!isset($header_line_found_flag)) {
		return false;
	} else {
		return $samples;
	}
}

?>
