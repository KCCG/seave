<?php
	
// These are functions for specific GBS analyses and outputting results

#############################################
# QUERY THE GBS FOR METHOD OVERLAPS AND RETURN STRINGS FOR DISPLAY
#############################################

function analysis_type_method_overlaps_gbs($samples_to_query) {
	if (count($samples_to_query) == 0) {
		return false;
	}
	
	#############################################
	# WRITE EACH SAMPLE + METHOD TO BEDS
	#############################################
	
	foreach ($samples_to_query as $sample_name) {
		$sample_method_bed_files[$sample_name] = write_samples_methods_to_beds(array($sample_name), array()); // No method names supplied to get all methods for the sample
		//$sample_method_bed_files[$sample_name][<bed file path>]["method"/"sample"] = <value>
		
		if ($sample_method_bed_files[$sample_name] === false) {
			return false;
		}
		
		// If there are less than 2 methods for the current sample, remove it from further analysis
		if (count(array_keys($sample_method_bed_files[$sample_name])) < 2) {
			// Delete any BED files exported
			foreach (array_keys($sample_method_bed_files[$sample_name]) as $bed_path) {
				delete_temporary_bed_file($bed_path);
			}
			
			unset($sample_method_bed_files[$sample_name]);
		}
	}
	
	#############################################
	# BEDTOOLS INTERSECT THE MULTIPLE METHODS PER SAMPLE
	#############################################
	
	foreach (array_keys($sample_method_bed_files) as $sample_name) {
		// Generate an intersect BED for all methods
		$intersect_bed_path = bedtools_intersect_bed_files(array_keys($sample_method_bed_files[$sample_name]));
		
		if ($intersect_bed_path === false) {
			return false;
		}
		
		$intersect_bed_paths[$sample_name] = $intersect_bed_path;
	}
	
	#############################################
	# IMPORT INTERSECT BED(S)
	#############################################
	
	foreach (array_keys($intersect_bed_paths) as $sample_name) {
		$method_overlap_blocks[$sample_name] = bedtools_parse_bed_file($intersect_bed_paths[$sample_name]);
	
		if ($method_overlap_blocks[$sample_name] === false) {
			return false;
		}
	}
	
	#############################################
	# DELETE ALL TEMPORARY BED FILES
	#############################################
	
	foreach (array_keys($sample_method_bed_files) as $sample_name) {
		foreach (array_keys($sample_method_bed_files[$sample_name]) as $bed_path) {
			delete_temporary_bed_file($bed_path);
		}
	}
	
	foreach (array_keys($intersect_bed_paths) as $sample_name) {
		delete_temporary_bed_file($intersect_bed_paths[$sample_name]);
	}
	
	#############################################
	# CREATE SQL QUERIES AND EXECUTE THEM TO FETCH ANNOTATIONS FOR OVERLAPPING BLOCKS
	#############################################
	
	// Query the GBS for each sample separately 
	foreach (array_keys($method_overlap_blocks) as $sample_name) {
		// If there are no overlaps for the current sample
		if (count(array_keys($method_overlap_blocks[$sample_name])) == 0) {
			continue;
		}
		
		// Create arrays to store parameters for the GBS SQL queries for execution
		$query_parameters_GBS = array();
		$query_parameters_GBS_temporary = array();
		
		// Variable to hold the total number of overlaps to search
		$total_block_count_to_search = 0;
		
		// Populate the temporary table query parameters
		foreach (array_keys($method_overlap_blocks[$sample_name]) as $chromosome) {
			foreach (array_keys($method_overlap_blocks[$sample_name][$chromosome]) as $start) {
				foreach (array_keys($method_overlap_blocks[$sample_name][$chromosome][$start]) as $end) {
					$total_block_count_to_search++;
					
					array_push($query_parameters_GBS_temporary, $chromosome, $start, $end);
				}
			}
		}
		
		// Create the SQL to create a temporary table containing all GBS query coordinates
		$sql_GBS_temporary = create_temporary_query_coordinates_table_gbs($total_block_count_to_search);
		
		// Create the SQL to query the GBS for all blocks overlapping with the temporary query coordinates table
		$sql_GBS = query_blocks_by_position_gbs(1, "all", "do_not_restrict_cn"); // CN already restricted by write_samples_methods_to_beds()
		
		// Populate the GBS query parameters (only 1 sample)
		$query_parameters_GBS[] = $sample_name;
		
		######################
		
		// Perform a multi-query by sending all queries at once
		$statement = $GLOBALS["mysql_connection"]->prepare($sql_GBS_temporary);
		
		$statement->execute($query_parameters_GBS_temporary);
		
		// Perform a multi-query by sending all queries at once
		$statement = $GLOBALS["mysql_connection"]->prepare($sql_GBS);
		
		$statement->execute($query_parameters_GBS);
		
	    do {
		    $mysql_result = $statement->fetchAll(PDO::FETCH_NUM); // Fetches as $mysql_result[row array (0,1,2,3...)][column array (0,1,2,3...)] = value
		    
		    // Go through each row returned
			foreach (array_keys($mysql_result) as $result_id) {
	            // Store the results in this format:
	            // $GBS_results[<sample name>][<overlap region>][<method name>][<block coordinates>][<copy_number/annotation_tags/event_type>] = <string value>/<array>
	            
	            $GBS_results[$mysql_result[$result_id][0]][$mysql_result[$result_id][7].":".$mysql_result[$result_id][8]."-".$mysql_result[$result_id][9]][$mysql_result[$result_id][3]][$mysql_result[$result_id][4].":".$mysql_result[$result_id][5]."-".$mysql_result[$result_id][6]]["copy_number"] = $mysql_result[$result_id][2];
	            $GBS_results[$mysql_result[$result_id][0]][$mysql_result[$result_id][7].":".$mysql_result[$result_id][8]."-".$mysql_result[$result_id][9]][$mysql_result[$result_id][3]][$mysql_result[$result_id][4].":".$mysql_result[$result_id][5]."-".$mysql_result[$result_id][6]]["event_type"] = $mysql_result[$result_id][1];
                
                 // If a non-empty annotation tag is present, add it to the annotation tags array
				if ($mysql_result[$result_id][10] != "") {
                	$GBS_results[$mysql_result[$result_id][0]][$mysql_result[$result_id][7].":".$mysql_result[$result_id][8]."-".$mysql_result[$result_id][9]][$mysql_result[$result_id][3]][$mysql_result[$result_id][4].":".$mysql_result[$result_id][5]."-".$mysql_result[$result_id][6]]["annotation_tags"][] = $mysql_result[$result_id][10].":".$mysql_result[$result_id][11];
                }
	        }
	    // Ask for the next result
	    } while ($statement->nextRowset());
	}
	
	#############################################
	# DETERMINE THE MAXIMUM NUMBER OF METHODS PER SAMPLE FOR OUTPUT STRING
	#############################################
	
	$max_methods_per_sample = 0;
	
	foreach (array_keys($GBS_results) as $sample_name) {
		foreach (array_keys($GBS_results[$sample_name]) as $overlap_region) {
			if (count(array_keys($GBS_results[$sample_name][$overlap_region])) > $max_methods_per_sample) {
				$max_methods_per_sample = count(array_keys($GBS_results[$sample_name][$overlap_region]));
			}
		}
	}
	
	#############################################
	# CREATE OUTPUT STRINGS PER BLOCK
	#############################################

	// Create an array to hold the output strings
	$method_overlaps_blocks_output = array();
	
    // Create the header line
	$header_line = "Sample\tMethods\tOverlapping Coordinates\tOverlap Size (bp)";
    // Print the maximum number of overlapping methods as columns
	for ($i = 1; $i <= $max_methods_per_sample; $i++) {
		$header_line .= "\tMethod ".$i." Block(s)";
	}
	array_push($method_overlaps_blocks_output, $header_line);
    
    // Create strings per result row and save them
	foreach (array_keys($GBS_results) as $sample_name) {
		foreach (array_keys($GBS_results[$sample_name]) as $overlap_region) {			
			// Print sample name
			$output_string = $sample_name."\t";
			
			// Print methods
			$output_string .= implode(";", array_keys($GBS_results[$sample_name][$overlap_region]))."\t";
			
			// Print overlap region
			$output_string .= $overlap_region."\t";
			
			// Extract start and end and print the block size
			preg_match("/([0-9]*)\-([0-9]*)/", $overlap_region, $matches);
			$output_string .= $matches[2] - $matches[1]."\t";
			
			$num_method_block_annotations_output = 0;
			
			// Output the block(s) column
			foreach (array_keys($GBS_results[$sample_name][$overlap_region]) as $method_name) {
				foreach (array_keys($GBS_results[$sample_name][$overlap_region][$method_name]) as $block_coordinates) {					
					$output_string .= "Block coordinates: ".$block_coordinates.", ";

					$output_string .= "Type: ".$GBS_results[$sample_name][$overlap_region][$method_name][$block_coordinates]["event_type"].", ";
					
					// If the copy number is not absent
					if ($GBS_results[$sample_name][$overlap_region][$method_name][$block_coordinates]["copy_number"] != "") {
						$output_string .= "Copy number: ".$GBS_results[$sample_name][$overlap_region][$method_name][$block_coordinates]["copy_number"].", ";
					}
					
					// If annotations are present, add them too
					if (isset($GBS_results[$sample_name][$overlap_region][$method_name][$block_coordinates]["annotation_tags"])) {
						$output_string .= "Annotations: (".implode(";", $GBS_results[$sample_name][$overlap_region][$method_name][$block_coordinates]["annotation_tags"])."); ";
					}
				}
				
				$output_string = substr($output_string, 0, -2); // Remove the last "; " that was added by the loop above
				
				// Iterate the number of method block annotations output
				$num_method_block_annotations_output++;
				
				// If there are more method block annotations, print a tab character for a new column
				if ($num_method_block_annotations_output < count(array_keys($GBS_results[$sample_name][$overlap_region]))) {
					$output_string .= "\t";
				}
			}
			
			// If the current sample has less overlap methods than another one, print some empty columns on the end
			if ($num_method_block_annotations_output < $max_methods_per_sample) {
				for ($i = 0; $i < ($max_methods_per_sample - $num_method_block_annotations_output); $i++) {
					$output_string .= "\t";
				}
			}
			
			array_push($method_overlaps_blocks_output, $output_string);
		}
	}
    
    return $method_overlaps_blocks_output;
}

#############################################
# QUERY THE GBS FOR SAMPLE OVERLAPS AND RETURN STRINGS FOR DISPLAY
#############################################

function analysis_type_sample_overlaps_gbs($samples_to_query, $methods_to_query) {
	// Must be at least 2 samples to overlap and 1+ methods
	if (count($samples_to_query) < 2 || count($methods_to_query) == 0) {
		return false;
	}
	
	#############################################
	# WRITE EACH SAMPLE + METHOD TO BEDS
	#############################################
	
	foreach ($methods_to_query as $method_name) {
		$sample_method_bed_files[$method_name] = write_samples_methods_to_beds($samples_to_query, array($method_name));
		//$sample_method_bed_files[$method_name][<bed file path>]["method"/"sample"] = <value>
		
		if ($sample_method_bed_files[$method_name] === false) {
			return false;
		}
		
		// If there are less than 2 samples for the current method, remove it from further analysis
		if (count(array_keys($sample_method_bed_files[$method_name])) < 2) {
			// Delete any BED files expored
			foreach (array_keys($sample_method_bed_files[$method_name]) as $bed_path) {
				delete_temporary_bed_file($bed_path);
			}
			
			unset($sample_method_bed_files[$method_name]);
		}
	}
	
	#############################################
	# BEDTOOLS INTERSECT THE MULTIPLE SAMPLES PER SAMPLE
	#############################################
	
	foreach (array_keys($sample_method_bed_files) as $method_name) {
		// Generate an intersect BED for all samples
		$intersect_bed_path = bedtools_intersect_bed_files(array_keys($sample_method_bed_files[$method_name]));
		
		if ($intersect_bed_path === false) {
			return false;
		}
		
		$intersect_bed_paths[$method_name] = $intersect_bed_path;
	}
	
	#############################################
	# IMPORT INTERSECT BED(S)
	#############################################
	
	foreach (array_keys($intersect_bed_paths) as $method_name) {
		$sample_overlap_blocks[$method_name] = bedtools_parse_bed_file($intersect_bed_paths[$method_name]);
	
		if ($sample_overlap_blocks[$method_name] === false) {
			return false;
		}
	}
	
	#############################################
	# DELETE ALL TEMPORARY BED FILES
	#############################################
	
	foreach (array_keys($sample_method_bed_files) as $method_name) {
		foreach (array_keys($sample_method_bed_files[$method_name]) as $bed_path) {
			delete_temporary_bed_file($bed_path);
		}
	}
	
	foreach (array_keys($intersect_bed_paths) as $method_name) {
		delete_temporary_bed_file($intersect_bed_paths[$method_name]);
	}
	
	#############################################
	# CREATE SQL QUERIES AND EXECUTE THEM TO FETCH ANNOTATIONS FOR OVERLAPPING BLOCKS
	#############################################
	
	// Query the GBS for each method separately
	foreach (array_keys($sample_overlap_blocks) as $method_name) {
		// If there are no overlaps for the current method
		if (count(array_keys($sample_overlap_blocks[$method_name])) == 0) {
			continue;
		}
		
		// Create arrays to store parameters for the GBS SQL queries for execution
		$query_parameters_GBS = array();
		$query_parameters_GBS_temporary = array();
		
		// Variable to hold the total number of overlaps to search
		$total_block_count_to_search = 0;
		
		// Populate the temporary table query parameters
		foreach (array_keys($sample_overlap_blocks[$method_name]) as $chromosome) {
			foreach (array_keys($sample_overlap_blocks[$method_name][$chromosome]) as $start) {
				foreach (array_keys($sample_overlap_blocks[$method_name][$chromosome][$start]) as $end) {
					$total_block_count_to_search++;
					
					array_push($query_parameters_GBS_temporary, $chromosome, $start, $end);
				}
			}
		}
		
		// Create the SQL to create a temporary table containing all GBS query coordinates
		$sql_GBS_temporary = create_temporary_query_coordinates_table_gbs($total_block_count_to_search);
		
		// Create the SQL to query the GBS for all blocks overlapping with the temporary query coordinates table
		$sql_GBS = query_blocks_by_position_gbs(count($samples_to_query), $method_name, "do_not_restrict_cn"); // CN already restricted by write_samples_methods_to_beds()
		
		// Populate the GBS query parameters
		foreach ($samples_to_query as $sample) {
			$query_parameters_GBS[] = $sample;
		}
		
		$query_parameters_GBS[] = $method_name;
		
		######################
		
		// Perform a multi-query by sending all queries at once
		$statement = $GLOBALS["mysql_connection"]->prepare($sql_GBS_temporary);
		
		$statement->execute($query_parameters_GBS_temporary);
		
		// Perform a multi-query by sending all queries at once
		$statement = $GLOBALS["mysql_connection"]->prepare($sql_GBS);
		
		$statement->execute($query_parameters_GBS);
		
	    do {
		    $mysql_result = $statement->fetchAll(PDO::FETCH_NUM); // Fetches as $mysql_result[row array (0,1,2,3...)][column array (0,1,2,3...)] = value
		    
		    // Go through each row returned
			foreach (array_keys($mysql_result) as $result_id) {
                // Store the results in this format:
                // $GBS_results[<method name>][<overlap region>][<sample name>][<block coordinates>][<copy_number/annotation_tags/event_type>] = <string value>/<array>
                
                $GBS_results[$mysql_result[$result_id][3]][$mysql_result[$result_id][7].":".$mysql_result[$result_id][8]."-".$mysql_result[$result_id][9]][$mysql_result[$result_id][0]][$mysql_result[$result_id][4].":".$mysql_result[$result_id][5]."-".$mysql_result[$result_id][6]]["copy_number"] = $mysql_result[$result_id][2];
                $GBS_results[$mysql_result[$result_id][3]][$mysql_result[$result_id][7].":".$mysql_result[$result_id][8]."-".$mysql_result[$result_id][9]][$mysql_result[$result_id][0]][$mysql_result[$result_id][4].":".$mysql_result[$result_id][5]."-".$mysql_result[$result_id][6]]["event_type"] = $mysql_result[$result_id][1];
                
                // If a non-empty annotation tag is present, add it to the annotation tags array
                if ($mysql_result[$result_id][10] != "") {
	               $GBS_results[$mysql_result[$result_id][3]][$mysql_result[$result_id][7].":".$mysql_result[$result_id][8]."-".$mysql_result[$result_id][9]][$mysql_result[$result_id][0]][$mysql_result[$result_id][4].":".$mysql_result[$result_id][5]."-".$mysql_result[$result_id][6]]["annotation_tags"][] = $mysql_result[$result_id][10].":".$mysql_result[$result_id][11];
				}
	        }
	    // Ask for the next result
	    } while ($statement->nextRowset());
	}
	
	#############################################
	# DETERMINE THE MAXIMUM NUMBER OF SAMPLES PER METHOD FOR OUTPUT STRING
	#############################################
	
	$max_samples_per_method = 0;
	
	foreach (array_keys($GBS_results) as $method_name) {
		foreach (array_keys($GBS_results[$method_name]) as $overlap_region) {
			if (count(array_keys($GBS_results[$method_name][$overlap_region])) > $max_samples_per_method) {
				$max_samples_per_method = count(array_keys($GBS_results[$method_name][$overlap_region]));
			}
		}
	}
	
	#############################################
	# CREATE OUTPUT STRINGS PER BLOCK
	#############################################
	
	// Create an array to hold the output strings
	$sample_overlaps_blocks_output = array();
	
    // Create the header line
	$header_line = "Method\tSamples\tOverlapping Coordinates\tOverlap Size (bp)";
    // Print the maximum number of overlapping samples as columns
	for ($i = 1; $i <= $max_samples_per_method; $i++) {
		$header_line .= "\tSample ".$i." Block(s)";
	}
	array_push($sample_overlaps_blocks_output, $header_line);
    
    // Create strings per result row and save them
	foreach (array_keys($GBS_results) as $method_name) {
		foreach (array_keys($GBS_results[$method_name]) as $overlap_region) {
			// Print method name
			$output_string = $method_name."\t";
			
			// Print samples
			$output_string .= implode(";", array_keys($GBS_results[$method_name][$overlap_region]))."\t";
			
			// Print overlap region
			$output_string .= $overlap_region."\t";
			
			// Extract start and end and print the block size
			preg_match("/([0-9]*)\-([0-9]*)/", $overlap_region, $matches);
			$output_string .= $matches[2] - $matches[1]."\t";
			
			$num_sample_block_annotations_output = 0;
			
			// Output the block(s) column
			foreach (array_keys($GBS_results[$method_name][$overlap_region]) as $sample_name) {
				foreach (array_keys($GBS_results[$method_name][$overlap_region][$sample_name]) as $block_coordinates) {					
					$output_string .= "Block coordinates: ".$block_coordinates.", ";

					$output_string .= "Type: ".$GBS_results[$method_name][$overlap_region][$sample_name][$block_coordinates]["event_type"].", ";
					
					// If the copy number is not absent
					if ($GBS_results[$method_name][$overlap_region][$sample_name][$block_coordinates]["copy_number"] != "") {
						$output_string .= "Copy number: ".$GBS_results[$method_name][$overlap_region][$sample_name][$block_coordinates]["copy_number"].", ";
					}
					
					// If annotations are present, add them too
					if (isset($GBS_results[$method_name][$overlap_region][$sample_name][$block_coordinates]["annotation_tags"])) {
						$output_string .= "Annotations: (".implode(";", $GBS_results[$method_name][$overlap_region][$sample_name][$block_coordinates]["annotation_tags"])."); ";
					}
				}
				
				$output_string = substr($output_string, 0, -2); // Remove the last "; " that was added by the loop above
				
				// Iterate the number of sample block annotations output
				$num_sample_block_annotations_output++;
				
				// If there are more sample block annotations, print a tab character for a new column
				if ($num_sample_block_annotations_output < count(array_keys($GBS_results[$method_name][$overlap_region]))) {
					$output_string .= "\t";
				}
			}
			
			// If the current method has less overlap samples than another one, print some empty columns on the end
			if ($num_sample_block_annotations_output < $max_samples_per_method) {
				for ($i = 0; $i < ($max_samples_per_method - $num_sample_block_annotations_output); $i++) {
					$output_string .= "\t";
				}
			}
			
			array_push($sample_overlaps_blocks_output, $output_string);
		}
	}
    
    return $sample_overlaps_blocks_output;
}

#############################################
# QUERY THE GBS FOR THE SV FUSIONS ANALYSIS TYPE AND RETURN STRINGS FOR DISPLAY
#############################################

function analysis_type_svfusions_gbs(array $samples_to_query, array $gene_list_to_search) {
	
	#############################################
	# CREATE THE OUTPUT HEADER FIRST IN CASE NO RESULTS ARE FOUND AFTER THE INTERSECTION
	#############################################
	
	// Create an array to hold output lines with both breakpoints inside genes
	$output_lines["both"] = array();
	
	// Create an array to hold output lines with one breakpoint inside a gene
	$output_lines["one"] = array();
	
	// Create an array to hold the final output strings
	$svfusions_blocks_output = array();
	
	// Create the header line - currently assuming there will be 2 blocks per event max but this could change in the future
	$svfusions_blocks_output[] = "Link Type\tSample(s)\tMethod\tGene(s)\tBlock1 Coordinates\tBlock2 Coordinates";
	
	#############################################
	# TEST SAMPLES INPUT
	#############################################

	// There must be at least one sample supplied
	if (count($samples_to_query) == 0) {
		return false;
	}
	
	#############################################
	# WRITE ALL GENES AND THEIR POSITIONS OUT TO A BED FILE
	#############################################
	
	$gene_list_bed_path = write_gene_list_to_bed($gene_list_to_search); // Note: $gene_list_to_search can be an empty array which will search all genes
	
	if ($gene_list_bed_path === false) {
		return false;
	}
	
	#############################################
	# WRITE ALL POTENTIALLY FUSING BLOCKS PER SAMPLE OUT TO BED FILES
	#############################################
	
	$samples_bed_paths = write_event_types_per_sample_to_beds($samples_to_query, array("inversion", "BND", "deletion"), "split_deletions");
	// Note: this will write block types with 2 breakpoints (e.g. INV/BND) on 2 lines with their separate block IDs but for blocks where it's one event (e.g. deletions) it will write the single block ID followed by -1 and -2 for the 2 breakpoints at the start and end
	
	if ($samples_bed_paths === false) {
		return false;
	}
	
	#############################################
	# BEDTOOLS INTERSECT TO FIND BLOCKS OVERLAPPING GENES PER SAMPLE
	#############################################
	
	foreach (array_keys($samples_bed_paths) as $sample_bed_path) {
		// Intersect the gene list BED file with the current sample BED file - results in a 12-column BED file (4 for intersection, 4 from first parameter BED file and 4 from second parameter BED file)
		$intersect_bed_path = bedtools_intersect_two_bed_files_long($sample_bed_path, $gene_list_bed_path);
		
		if ($intersect_bed_path === false) {
			return false;
		}
		
		// If the intersection is not empty, i.e. contains blocks
		if (filesize($intersect_bed_path) != 0) {			
			// Save the sample and methods of the merged intersect BED file
			$intersect_bed_paths[$intersect_bed_path]["sample"] = $samples_bed_paths[$sample_bed_path]["sample"];
			$intersect_bed_paths[$intersect_bed_path]["methods"] = $samples_bed_paths[$sample_bed_path]["methods"];
		// If the intersection is empty
		} else {
			// Delete the empty intersect BED
			delete_temporary_bed_file($intersect_bed_path);
		}
	}
	
	#############################################
	# RETURN THE HEADER OUTPUT IF NO SUCCESSFUL INTERSECTIONS WERE CREATED
	#############################################
	
	// If none of the blocks intersected with the gene list supplied
	if (!isset($intersect_bed_paths)) {
		return $svfusions_blocks_output;
	}
	
	#############################################
	# IMPORT INTERSECT BEDS
	#############################################
	
	foreach (array_keys($intersect_bed_paths) as $intersect_bed_path) {
		$intersect_blocks[$intersect_bed_path] = bedtools_parse_bed_file($intersect_bed_path);
	
		if ($intersect_blocks[$intersect_bed_path] === false) {
			return false;
		}
	}
	
	#############################################
	# DELETE ALL TEMPORARY BED FILES
	#############################################
	
	foreach (array_keys($samples_bed_paths) as $bed_path) {
		delete_temporary_bed_file($bed_path);
	}
	
	foreach (array_keys($intersect_bed_paths) as $intersect_bed_path) {
		delete_temporary_bed_file($intersect_bed_path);
	}
	
	delete_temporary_bed_file($gene_list_bed_path);
	
	#############################################
	# PARSE FOR SUCCESSFULLY INTERSECTED BLOCK IDS
	#############################################
	
	$intersection_results = array();
	
	foreach (array_keys($intersect_blocks) as $intersect_bed_path) {
		foreach (array_keys($intersect_blocks[$intersect_bed_path]) as $chromosome) {
			foreach (array_keys($intersect_blocks[$intersect_bed_path][$chromosome]) as $start) {
				foreach (array_keys($intersect_blocks[$intersect_bed_path][$chromosome][$start]) as $end) {
					foreach (array_keys($intersect_blocks[$intersect_bed_path][$chromosome][$start][$end]) as $event_id) {
						// 4th column holds the GBS block ID from the intersection
						// Store variable information in arrays because one block ID could overlap multiple genes
						$intersection_results[$intersect_blocks[$intersect_bed_path][$chromosome][$start][$end][$event_id]["4"]]["chromosome"] = $chromosome; // Chromosome applies to all coordinates for the current event
						$intersection_results[$intersect_blocks[$intersect_bed_path][$chromosome][$start][$end][$event_id]["4"]]["gene"][] = $intersect_blocks[$intersect_bed_path][$chromosome][$start][$end][$event_id]["12"]; // Gene from the intersection (12th column of the BED)
						
						$intersection_results[$intersect_blocks[$intersect_bed_path][$chromosome][$start][$end][$event_id]["4"]]["intersection_start"][] = $start; // Intersecting coordinates (where gene and event breakpoint overlap)
						$intersection_results[$intersect_blocks[$intersect_bed_path][$chromosome][$start][$end][$event_id]["4"]]["intersection_end"][] = $end; // Intersecting coordinates (where gene and event breakpoint overlap)
						
						$intersection_results[$intersect_blocks[$intersect_bed_path][$chromosome][$start][$end][$event_id]["4"]]["event_start"][] = $intersect_blocks[$intersect_bed_path][$chromosome][$start][$end][$event_id]["6"]; // Event coordinates (6/7th column of the BED)
						$intersection_results[$intersect_blocks[$intersect_bed_path][$chromosome][$start][$end][$event_id]["4"]]["event_end"][] = $intersect_blocks[$intersect_bed_path][$chromosome][$start][$end][$event_id]["7"]; // Event coordinates (6/7th column of the BED)
						
						$intersection_results[$intersect_blocks[$intersect_bed_path][$chromosome][$start][$end][$event_id]["4"]]["gene_start"][] = $intersect_blocks[$intersect_bed_path][$chromosome][$start][$end][$event_id]["10"]; // Gene coordinates (10/11th column of the BED)
						$intersection_results[$intersect_blocks[$intersect_bed_path][$chromosome][$start][$end][$event_id]["4"]]["gene_end"][] = $intersect_blocks[$intersect_bed_path][$chromosome][$start][$end][$event_id]["11"]; // Gene coordinates (10/11th column of the BED)
						
						// Format:
						// $intersection_results[<GBS block ID>][<chromosome>] = <value>
						// $intersection_results[<GBS block ID>][<gene/intersection_start/intersection_end/event_start/event_end/gene_start/gene_end>][<array id>] = <value>
						
						// Note: don't forget that block types which do not have 2 GBS breakpoint blocks per event (e.g. deletions) are stored with their <block id>-1 and -2 for the 2 breakpoints, this is important for matching to DB results later
					}
				}
			}
		}
	}
	
	// Array to store block IDs that were successfully intersected with the gene list (i.e. overlap a gene)
	$intersected_block_ids = array_keys($intersection_results);
	
	// Only keep unique block IDs for DB query
	$intersected_block_ids = array_unique($intersected_block_ids);
	
	// Re-index the array
	$intersected_block_ids = array_values($intersected_block_ids);
	
	#############################################
	# QUERY THE GBS FOR ANNOTATIONS AND LINKED BLOCKS
	#############################################
	
	// Note: this function will strip the -1 and -2 from single block IDs (e.g. deletions) so that the block ID matches the DB
	$GBS_results = fetch_linked_block_annotations_gbs($intersected_block_ids, $samples_to_query);
	
	if ($GBS_results === false) {
		return false;
	}
	
	//$GBS_results["blocks_per_link"][<link id>]["block_ids"][<block id>] = 1;
	//$GBS_results["blocks_per_link"][<link id>]["link_type"] = <value>
	
	//$GBS_results["blocks"][<block id>][<method>/<chromosome>/<start>/<end>/<event_type>] = <value>
	//$GBS_results["blocks"][<block id>]["samples"][<sample name>]["annotations"][<tag name>] = <tag value>
	
	//$GBS_results["unique_annotation_tags"] = <array of annotation tags>
	
	#############################################
	# ADD ALL UNIQUE ANNOTATION TAGS FOUND TO THE OUTPUT HEADER
	#############################################
	
	if (count($GBS_results["unique_annotation_tags"]) > 0) {
		foreach ($GBS_results["unique_annotation_tags"] as $annotation_tag) {
			// Modify tags from their default Manta names to human-friendly column headers
			if ($annotation_tag == "SOMATICSCORE") {
				$annotation_tag = "Somatic Score";
			} elseif ($annotation_tag == "GPR") {
				$annotation_tag = "Germline Spanning Reads";
			} elseif ($annotation_tag == "GSR") {
				$annotation_tag = "Germline Split Reads";
			} elseif ($annotation_tag == "PR") {
				$annotation_tag = "Somatic Spanning Reads";
			} elseif ($annotation_tag == "SR") {
				$annotation_tag = "Somatic Split Reads";
			} elseif ($annotation_tag == "FILTER") {
				$annotation_tag = "VCF Filter";
			} elseif ($annotation_tag == "IMPRECISE") {
				$annotation_tag = "Imprecise Flag";
			} elseif ($annotation_tag == "BND_DEPTH") {
				$annotation_tag = "BND Depth";
			} elseif ($annotation_tag == "HOMLEN") {
				$annotation_tag = "Homology Length";
			} elseif ($annotation_tag == "HOMSEQ") {
				$annotation_tag = "Homology Sequence";
			} elseif ($annotation_tag == "SVINSLEN") {
				$annotation_tag = "Insertion Length";
			} elseif ($annotation_tag == "SVINSSEQ") {
				$annotation_tag = "Insertion Sequence";
			} elseif ($annotation_tag == "JUNCTION_SOMATICSCORE") {
				$annotation_tag = "Junction Somatic Score";
			}
			
			// Add the annotation tag to the results header
			$svfusions_blocks_output[0] .= "\t".$annotation_tag;
		}
	}
	
	#############################################
	# CREATE OUTPUT STRINGS PER EVENT
	#############################################
	
	foreach (array_keys($GBS_results["blocks_per_link"]) as $link_id) {
		// If the current link only has 1 block ID because it's a block type that has been artificially split into 2 blocks at the breakpoints (e.g. deletion)
		if (count($GBS_results["blocks_per_link"][$link_id]["block_ids"]) == 1) {
			// The GBS results list the results for the single block
			$GBS_results_first_block_id = array_keys($GBS_results["blocks_per_link"][$link_id]["block_ids"])[0];
			$GBS_results_second_block_id = array_keys($GBS_results["blocks_per_link"][$link_id]["block_ids"])[0];
			
			// The intersection results list the results per fake breakpoint
			$intersection_first_block_id = array_keys($GBS_results["blocks_per_link"][$link_id]["block_ids"])[0]."-1";
			$intersection_second_block_id = array_keys($GBS_results["blocks_per_link"][$link_id]["block_ids"])[0]."-2";
		// Otherwise if there are multiple breakpoints for the event (e.g. inversion) the block IDs are the same between DB results and the intersection
		} else {
			$GBS_results_first_block_id = array_keys($GBS_results["blocks_per_link"][$link_id]["block_ids"])[0];
			$GBS_results_second_block_id = array_keys($GBS_results["blocks_per_link"][$link_id]["block_ids"])[1];
			
			$intersection_first_block_id = array_keys($GBS_results["blocks_per_link"][$link_id]["block_ids"])[0];
			$intersection_second_block_id = array_keys($GBS_results["blocks_per_link"][$link_id]["block_ids"])[1];
		}
		
		#############################################
		
		// If both blocks for the current link were found to overlap with genes
		if (isset($intersection_results[$intersection_first_block_id], $intersection_results[$intersection_second_block_id])) {
			$breakpoints_inside_genes = "both";
		} else {
			$breakpoints_inside_genes = "one";
		}
		
		#############################################
		
		// Go through each sample, there shouldn't be a situation where 2 linked blocks don't have the same samples
		foreach (array_keys($GBS_results["blocks"][$GBS_results_first_block_id]["samples"]) as $sample) {
			$output_string = "";
		
			#############################################
			
			// Link type
			$output_string = $GBS_results["blocks_per_link"][$link_id]["link_type"]."\t";
			
			#############################################
			
			// Sample(s)
			$output_string .= $sample."\t";
	
			#############################################
			
			// Method (same per intersection)
			$output_string .= $GBS_results["blocks"][$GBS_results_first_block_id]["method"]."\t";
			
			#############################################
			
			// Gene(s)
			
			// If the first block in the link was found to overlap one or more genes
			if (isset($intersection_results[$intersection_first_block_id])) {
				$block1_genes = $intersection_results[$intersection_first_block_id]["gene"];
			} else {
				$block1_genes = array(".");
			}
			
			// If the second block in the link was found to overlap one or more genes
			if (isset($intersection_results[$intersection_second_block_id])) {
				$block2_genes = $intersection_results[$intersection_second_block_id]["gene"];
			} else {
				$block2_genes = array(".");
			}
			
			// Save all pairwise overlapping genes between the 2 blocks
			foreach ($block1_genes as $gene1) {
				foreach ($block2_genes as $gene2) {
					$output_string .= $gene1."-".$gene2."; ";
				}
			}
			
			$output_string = substr($output_string, 0, -2); // Remove the last "; " that was added by the loop above
			
			$output_string .= "\t";
					
			#############################################
			
			// Block coordinates
			$output_string .= $GBS_results["blocks"][$GBS_results_first_block_id]["chromosome"].":".$GBS_results["blocks"][$GBS_results_first_block_id]["start"]."-".$GBS_results["blocks"][$GBS_results_first_block_id]["end"]."\t";
			$output_string .= $GBS_results["blocks"][$GBS_results_second_block_id]["chromosome"].":".$GBS_results["blocks"][$GBS_results_second_block_id]["start"]."-".$GBS_results["blocks"][$GBS_results_second_block_id]["end"]."\t";
			
			#############################################
			
			// Annotations
			
			// Go through each unique annotation tag in the results
			foreach ($GBS_results["unique_annotation_tags"] as $annotation_tag) {
				// For some tags, output just the value for the first block because we know values for both blocks are always the same
				if (in_array($annotation_tag, array("SOMATICSCORE"))) {
					$output_string .= $GBS_results["blocks"][$GBS_results_first_block_id]["samples"][$sample]["annotations"][$annotation_tag]."\t";
				} else {
					// If the annotation tag exists for the current sample in the first block, print it out
					if (isset($GBS_results["blocks"][$GBS_results_first_block_id]["samples"][$sample]["annotations"][$annotation_tag])) {
						$output_string .= $GBS_results["blocks"][$GBS_results_first_block_id]["samples"][$sample]["annotations"][$annotation_tag]."-";
					} else {
						$output_string .= ".-";
					}
					
					// If the annotation tag exists for the current sample in the second block, print it out
					if (isset($GBS_results["blocks"][$GBS_results_second_block_id]["samples"][$sample]["annotations"][$annotation_tag])) {
						$output_string .= $GBS_results["blocks"][$GBS_results_second_block_id]["samples"][$sample]["annotations"][$annotation_tag]."\t";
					} else {
						$output_string .= ".\t";
					}
				}
			}
			
			#############################################
			
			$output_string = substr($output_string, 0, -1); // Remove the last "\t" from the end of the line
			
			#############################################
			
			// If the current intersect had both breakpoints inside one or more genes
			if ($breakpoints_inside_genes == "both") {
				$output_lines["both"][] = $output_string;
			} else {
				$output_lines["one"][] = $output_string;
			}
		}
	}
	
	// First add intersections where both breakpoints are inside genes to the final output lines
	foreach ($output_lines["both"] as $output_line) {
		$svfusions_blocks_output[] = $output_line;
	}
	
	// Then add intersections where only one breakpoint is inside a gene to the final output lines
	foreach ($output_lines["one"] as $output_line) {
		$svfusions_blocks_output[] = $output_line;
	}
	
	return $svfusions_blocks_output;
}

#############################################
# QUERY THE GBS FOR THE ROHMER ANALYSIS TYPE AND RETURN STRINGS FOR DISPLAY
#############################################

function analysis_type_rohmer_gbs(array $affected_samples_to_query, array $unaffected_samples_to_query) {
	
	#############################################
	# TEST SAMPLES INPUT
	#############################################

	// There must be at least one affected sample supplied
	if (count($affected_samples_to_query) == 0) {
		return false;
	}
	
	#############################################
	# WRITE ROHMER BLOCKS FOR AFFECTED AND UNAFFECTED SAMPLES TO BED FILES
	#############################################
	
	$unaffected_samples_bed_paths = array();
	$affected_samples_bed_paths = array();
	
	$affected_samples_bed_paths = write_samples_methods_to_beds($affected_samples_to_query, array("ROHmer"));
	//$affected_samples_bed_paths[<bed file path>]["method"/"sample"] = <value>
	
	// If there are unaffected samples, fetch their ROHmer blocks
	if (count($unaffected_samples_to_query) != 0) {
		$unaffected_samples_bed_paths = write_samples_methods_to_beds($unaffected_samples_to_query, array("ROHmer"));
	}
	
	if ($unaffected_samples_bed_paths === false || $affected_samples_bed_paths === false) {
		return false;
	}
	
	#############################################
	# BEDTOOLS INTERSECT ALL AFFECTED SAMPLES
	#############################################
	
	$affected_intersect_bed_path = bedtools_intersect_bed_files(array_keys($affected_samples_bed_paths));
	
	if ($affected_intersect_bed_path === false) {
		return false;
	}
	
	#############################################
	# BEDTOOLS UNION ALL UNAFFECTED SAMPLES
	#############################################
	
	if (count(array_keys($unaffected_samples_bed_paths)) != 0) {
		$unaffected_union_bed_path = bedtools_unionbedg_bed_files(array_keys($unaffected_samples_bed_paths));
		
		if ($unaffected_union_bed_path === false) {
			return false;
		}
		
		$unaffected_merged_union_bed_path = bedtools_merge_bed_file($unaffected_union_bed_path, "");
		
		if ($unaffected_merged_union_bed_path === false) {
			return false;
		}
	}
	
	#############################################
	# BEDTOOLS SUBTRACT UNAFFECTED REGIONS FROM AFFECTED REGIONS
	#############################################
	
	// If no unaffected samples were supplied, the string is empty
	if (!isset($unaffected_merged_union_bed_path)) {
		// Set the subtracted bed path to the affected samples intersect as there was nothing to subtract
		$subtracted_bed_path = $affected_intersect_bed_path;
	} else {
		// Subtract the unaffected union from the affected intersect to get the regions shared by affecteds and not present in any unaffecteds
		$subtracted_bed_path = bedtools_subtract_bed_files($affected_intersect_bed_path, $unaffected_merged_union_bed_path);
		
		if ($subtracted_bed_path === false) {
			return false;
		}
	}
	
	#############################################
	# IMPORT SUBTRACTED BED
	#############################################
	
	// Import the BED file produced into an array
	$rohmer_blocks = bedtools_parse_bed_file($subtracted_bed_path);
	
	if ($rohmer_blocks === false) {
		return false;
	}
	
	#############################################
	# DELETE TEMPORARY FILES
	#############################################
	
	// Delete all produced single-sample BED files for unaffected samples
	foreach (array_keys($unaffected_samples_bed_paths) as $bed_path) {
		delete_temporary_bed_file($bed_path);
	}
	
	// Delete all produced single-sample BED files for affected samples
	foreach (array_keys($affected_samples_bed_paths) as $bed_path) {
		delete_temporary_bed_file($bed_path);
	}
	
	// Delete the affected intersect BED which had to have been produced
	delete_temporary_bed_file($affected_intersect_bed_path);
	
	// Delete the unaffected union and merged BED files if they were produced
	if (isset($unaffected_union_bed_path) && isset($unaffected_merged_union_bed_path)) {
		delete_temporary_bed_file($unaffected_union_bed_path);
		delete_temporary_bed_file($unaffected_merged_union_bed_path);
	}
	
	// If a subtracted BED file was produced, delete it
	if ($subtracted_bed_path != $affected_intersect_bed_path) {
		delete_temporary_bed_file($subtracted_bed_path);
	}
	
	#############################################
	# CREATE SQL QUERIES AND EXECUTE THEM TO FETCH ANNOTATIONS FOR OVERLAPPING BLOCKS
	#############################################
	
	// Create arrays to store parameters for the GBS SQL queries for execution
	$query_parameters_GBS = array();
	$query_parameters_GBS_temporary = array();
	
	// Variable to hold the total number of overlaps to search
	$total_block_count_to_search = 0;
	
	// For the affected samples, fetch all overlapping block IDs with each of the intervals in the imported BED file
	foreach (array_keys($rohmer_blocks) as $chromosome) {
			foreach (array_keys($rohmer_blocks[$chromosome]) as $start) {
				foreach (array_keys($rohmer_blocks[$chromosome][$start]) as $end) {
				$total_block_count_to_search++;
				
				array_push($query_parameters_GBS_temporary, $chromosome, $start, $end);
			}
		}
	}
	
	// Create the SQL to create a temporary table containing all GBS query coordinates
	$sql_GBS_temporary = create_temporary_query_coordinates_table_gbs($total_block_count_to_search);
	
	// Create the SQL to query the GBS for all blocks overlapping with the temporary query coordinates table
	$sql_GBS = query_blocks_by_position_gbs(count($affected_samples_to_query), "ROHmer", "do_not_restrict_cn"); // No CN for this data so no need to restrict
	
	// Populate the GBS query parameters
	foreach ($affected_samples_to_query as $sample) {
		$query_parameters_GBS[] = $sample;
	}
	
	$query_parameters_GBS[] = "ROHmer";
	
	######################
	
	// If there are overlapping blocks to search
	if ($total_block_count_to_search > 0) {
		// Perform a multi-query by sending all queries at once
		$statement = $GLOBALS["mysql_connection"]->prepare($sql_GBS_temporary);
		
		$statement->execute($query_parameters_GBS_temporary);
		
		// Perform a multi-query by sending all queries at once
		$statement = $GLOBALS["mysql_connection"]->prepare($sql_GBS);
		
		$statement->execute($query_parameters_GBS);
		
	    do {
		    $mysql_result = $statement->fetchAll(PDO::FETCH_NUM); // Fetches as $mysql_result[row array (0,1,2,3...)][column array (0,1,2,3...)] = value
		    
		    // Go through each row returned
			foreach (array_keys($mysql_result) as $result_id) {
                // Store the results in this format:
                // $annotated_GBS_results[<roh region>][<sample name>][<block coordinates>][<annotations>] = <string value>/<array>
                $annotated_GBS_results[$mysql_result[$result_id][7].":".$mysql_result[$result_id][8]."-".$mysql_result[$result_id][9]][$mysql_result[$result_id][0]][$mysql_result[$result_id][4].":".$mysql_result[$result_id][5]."-".$mysql_result[$result_id][6]] = array();
                
                // If a non-empty annotation tag is present, add it to the annotation tags array
                if ($mysql_result[$result_id][10] != "") {
	               $annotated_GBS_results[$mysql_result[$result_id][7].":".$mysql_result[$result_id][8]."-".$mysql_result[$result_id][9]][$mysql_result[$result_id][0]][$mysql_result[$result_id][4].":".$mysql_result[$result_id][5]."-".$mysql_result[$result_id][6]]["annotations"][] = $mysql_result[$result_id][10].":".$mysql_result[$result_id][11];
				}
	        }
		// Ask for the next result
		} while ($statement->nextRowset());
	}
	
	#############################################
	# CREATE OUTPUT STRINGS PER BLOCK
	#############################################
	
	// Create an array to hold the output strings
	$rohmer_blocks_output = array();
	
	// Create the header line
	$header_line = "ROH Coordinates\tBlock Size (bp)";
	// Go through each sample
	for ($i = 1; $i <= count($annotated_GBS_results[array_keys($annotated_GBS_results)[0]]); $i++) {
		$header_line .= "\tBlock ".$i." Annotations";
	}
	array_push($rohmer_blocks_output, $header_line);
	
	// Go through each set of blocks and create the string for output
	foreach (array_keys($annotated_GBS_results) as $coordinates) {
		// Overlapping coordinates column
		$output_string = $coordinates."\t";
		
		// Extract start and end and print the block size
		preg_match("/([0-9]*)\-([0-9]*)/", $coordinates, $matches);
		$output_string .= $matches[2] - $matches[1]."\t";
		
		// Block annotations column(s)
		// Go through each sample to create an annotations column for each one
		foreach (array_keys($annotated_GBS_results[$coordinates]) as $sample_name) {
			foreach (array_keys($annotated_GBS_results[$coordinates][$sample_name]) as $block_coordinates) {
				$output_string .= "Sample ".$sample_name."; ";
				
				$output_string .= "Block coordinates ".$block_coordinates;
				
				// If the first annotation tag is empty (i.e. no annotations present), don't print annotations
				if (isset($annotated_GBS_results[$coordinates][$sample_name][$block_coordinates]["annotations"])) {
					$output_string .= "; Annotations ";
					
					foreach ($annotated_GBS_results[$coordinates][$sample_name][$block_coordinates]["annotations"] as $annotation) {
						$output_string .= $annotation.", ";
					}
				
					$output_string = substr($output_string, 0, -2); // Remove the last ", " that was added by the loop above
					
					$output_string .= "\t";
				} else {
					$output_string .= "\t";
				}
			}
		}
		
		$output_string = substr($output_string, 0, -1); // Remove the last "\t" that was added by the loop above
		
		// Store the overlapping blocks string	
		array_push($rohmer_blocks_output, $output_string);
	}		
	
	#############################################
	
	return $rohmer_blocks_output;
}

#############################################
# QUERY THE GBS FOR THE GENOMIC COORDINATES ANALYSIS TYPE AND RETURN STRINGS FOR DISPLAY
#############################################

function analysis_type_genomic_coordinates_gbs(array $samples_to_query) {
	// Check that GBS search regions have been specified
	if (!isset($_SESSION["gbs_regions"])) {
		return false;
	}
	
	#############################################
	# CREATE SQL QUERIES AND EXECUTE THEM
	#############################################
	
	// Create arrays to store parameters for the GBS SQL queries for execution
	$query_parameters_GBS = array();
	$query_parameters_GBS_temporary = array();
	
	// Variable to hold the total number of overlaps to search
	$total_block_count_to_search = 0;
	
	// Split regions into an array
	$regions = explode(";", $_SESSION["gbs_regions"]);
	
	// Populate the temporary table query parameters
	foreach ($regions as $region) {
		preg_match('/([\w]*?):([0-9]*)\-([0-9]*)/', $region, $matches); # Pull out the chromosome, start and end using a regex
		
		$chromosome = $matches[1];
		$start = $matches[2];
		$end = $matches[3];
		
		// The GBS does not use "chr" in chromosome names so remove it
		$chromosome = preg_replace("/chr/", "", $chromosome);
		
		array_push($query_parameters_GBS_temporary, $chromosome, $start, $end);
		
		$total_block_count_to_search++;
	}
	
	// Create the SQL to create a temporary table containing all GBS query coordinates
	$sql_GBS_temporary = create_temporary_query_coordinates_table_gbs($total_block_count_to_search);
	
	// Populate the GBS query parameters
	foreach ($samples_to_query as $sample) {
		$query_parameters_GBS[] = $sample;
	}
		
	// Create the SQL to query the GBS for all blocks overlapping with the temporary query coordinates table
	// If the user specified a numeric copy number restriction
	if (is_numeric($_SESSION["gbs_cngreaterthan"]) && is_numeric($_SESSION["gbs_cnlessthan"])) {
		$sql_GBS = query_blocks_by_position_gbs(count($samples_to_query), "all", "restrict_cn");
		
		// Add in the copy number restriction parameters
		array_push($query_parameters_GBS, $_SESSION["gbs_cnlessthan"], $_SESSION["gbs_cngreaterthan"]);
	} else {
		$sql_GBS = query_blocks_by_position_gbs(count($samples_to_query), "all", "do_not_restrict_cn"); // CN not specified in a way it can be restricted
	}
	
	######################
	
	// Perform a multi-query by sending all queries at once
	$statement = $GLOBALS["mysql_connection"]->prepare($sql_GBS_temporary);
	
	$statement->execute($query_parameters_GBS_temporary);
	
	// Perform a multi-query by sending all queries at once
	$statement = $GLOBALS["mysql_connection"]->prepare($sql_GBS);
	
	$statement->execute($query_parameters_GBS);
	
    do {
	    $mysql_result = $statement->fetchAll(PDO::FETCH_NUM); // Fetches as $mysql_result[row array (0,1,2,3...)][column array (0,1,2,3...)] = value
	    
	    // Go through each row returned
		foreach (array_keys($mysql_result) as $result_id) {
            // Store the results in this format:
            // $GBS_results[<query location>][<sample name>][<event type>][<method name>][<block coordinates>][<copy_number/annotation_tags/block_size>] = <string value>/<array>
            
            $GBS_results[$mysql_result[$result_id][7].":".$mysql_result[$result_id][8]."-".$mysql_result[$result_id][9]][$mysql_result[$result_id][0]][$mysql_result[$result_id][1]][$mysql_result[$result_id][3]][$mysql_result[$result_id][4].":".$mysql_result[$result_id][5]."-".$mysql_result[$result_id][6]]["copy_number"] = $mysql_result[$result_id][2];
            $GBS_results[$mysql_result[$result_id][7].":".$mysql_result[$result_id][8]."-".$mysql_result[$result_id][9]][$mysql_result[$result_id][0]][$mysql_result[$result_id][1]][$mysql_result[$result_id][3]][$mysql_result[$result_id][4].":".$mysql_result[$result_id][5]."-".$mysql_result[$result_id][6]]["block_size"] = $mysql_result[$result_id][6] - $mysql_result[$result_id][5];
            
            // If a non-empty annotation tag is present, add it to the annotation tags array
            if ($mysql_result[$result_id][10] != "") {
               $GBS_results[$mysql_result[$result_id][7].":".$mysql_result[$result_id][8]."-".$mysql_result[$result_id][9]][$mysql_result[$result_id][0]][$mysql_result[$result_id][1]][$mysql_result[$result_id][3]][$mysql_result[$result_id][4].":".$mysql_result[$result_id][5]."-".$mysql_result[$result_id][6]]["annotation_tags"][] = $mysql_result[$result_id][10].":".$mysql_result[$result_id][11];
			}
        }
    // Ask for the next result
    } while ($statement->nextRowset());
	
	#############################################
	# CREATE OUTPUT STRINGS PER BLOCK
	#############################################

	// Create an array to hold the output strings
	$genomic_coordinates_blocks_output = array();
	
	// Create the header line
	array_push($genomic_coordinates_blocks_output, "Query Coordinates\tSample\tEvent Type\tMethod\tBlock Coordinates\tBlock Size (bp)\tCopy Number\tAnnotations");
        
    // Create strings per result row and save them
    foreach (array_keys($GBS_results) as $query_location) {
        foreach (array_keys($GBS_results[$query_location]) as $sample_name) { 
	        foreach (array_keys($GBS_results[$query_location][$sample_name]) as $event_type) {
		        foreach (array_keys($GBS_results[$query_location][$sample_name][$event_type]) as $method_name) {
			        foreach (array_keys($GBS_results[$query_location][$sample_name][$event_type][$method_name]) as $block_coordinates) {
				        $output_string = $query_location."\t";
				        
				        $output_string .= $sample_name."\t";
				        
				        $output_string .= $event_type."\t";
				        
				        $output_string .= $method_name."\t";
				        
				        $output_string .= $block_coordinates."\t";
				        
				        $output_string .= $GBS_results[$query_location][$sample_name][$event_type][$method_name][$block_coordinates]["block_size"]."\t";
				        
				        if ($GBS_results[$query_location][$sample_name][$event_type][$method_name][$block_coordinates]["copy_number"] == "") {
					        $output_string .= "None\t";
				        } else {
					        $output_string .= $GBS_results[$query_location][$sample_name][$event_type][$method_name][$block_coordinates]["copy_number"]."\t";
					    }
				        
				        // Stores the string of annotations that needs to be built up
						$annotation_output = "";
				        
				        // If annotation tags are present
				        if (isset($GBS_results[$query_location][$sample_name][$event_type][$method_name][$block_coordinates]["annotation_tags"])) {
					        // Go through each annotation and save them to a string
					        foreach ($GBS_results[$query_location][$sample_name][$event_type][$method_name][$block_coordinates]["annotation_tags"] as $annotation) {
						        $annotation_output .= $annotation.", ";
					        }
					    }
				        
				        // If annotations are present
				        if (strlen($annotation_output) > 0) {
				        	$annotation_output = substr($annotation_output, 0, -2); // Remove the last ", " that was added by the loop above
				        }
				        
				        if ($annotation_output != "") {
					        $output_string .= $annotation_output;
				        } else {
					        $output_string .= "None";
				        }
				        
				        array_push($genomic_coordinates_blocks_output, $output_string);
				    }
			    }
	        }
        }
    }
	    
    return $genomic_coordinates_blocks_output;
}

#############################################
# QUERY THE GBS FOR THE GENE LISTS ANALYSIS TYPE AND RETURN STRINGS FOR DISPLAY
#############################################

function analysis_type_gene_lists_gbs(array $gene_list, array $samples_to_query) {
	// Make sure both samples and genes were supplied to search
	if (count($gene_list) == 0 || count($samples_to_query) == 0) {
		return false;
	}
	
	#############################################
	# WRITE GENE LIST COORDINATES OUT TO A BED FILE
	#############################################
	
	$gene_list_bed_path = write_gene_list_to_bed($gene_list);
	
	if ($gene_list_bed_path === false) {
		return false;
	}
	
	#############################################
	# WRITE EACH SAMPLE + METHOD TO BEDS
	#############################################
	
	$sample_method_bed_files = write_samples_methods_to_beds($samples_to_query, array()); // No method names supplied to get all methods for the samples
	// Format: $sample_method_bed_files[<bed file path>]["method"/"sample"] = <value>
	
	if ($sample_method_bed_files === false) {
		return false;
	}
	
	#############################################
	# BEDTOOLS INTERSECT THE GENE COORDINATES AGAINST EACH SET OF SAMPLES + METHOD BLOCKS TO FIND OVERLAPS THEN MERGE THE INTERSECT BLOCKS
	#############################################
	
	foreach (array_keys($sample_method_bed_files) as $sample_method_bed_file) {
		// Intersect the gene list BED file with the current sample + method BED file - 4th column in this BED file is the query gene name
		$intersect_bed_path = bedtools_intersect_bed_files(array($gene_list_bed_path, $sample_method_bed_file));
		
		if ($intersect_bed_path === false) {
			return false;
		}
		
		// If the intersection is not empty, i.e. contains blocks
		if (filesize($intersect_bed_path) != 0) {
			// Perform a merge on the resulting intersect to remove blocks inside other blocks or to extend blocks, specify bedtools merge parameters to keep the gene in the 4th column as this is needed below
			$merged_intersect_bed_path = bedtools_merge_bed_file($intersect_bed_path, "-c 4 -o distinct");
	
			if ($merged_intersect_bed_path === false) {
				return false;
			}
			
			// Save the method and sample of the merged intersect BED file
			$merged_intersect_bed_paths[$merged_intersect_bed_path]["method"] = $sample_method_bed_files[$sample_method_bed_file]["method"];
			$merged_intersect_bed_paths[$merged_intersect_bed_path]["sample"] = $sample_method_bed_files[$sample_method_bed_file]["sample"];
		}
		
		// Delete the intersect BED as it is no longer needed
		delete_temporary_bed_file($intersect_bed_path);
	}
	
	#############################################
	# IMPORT INTERSECT BED
	#############################################
	
	foreach (array_keys($merged_intersect_bed_paths) as $intersect_bed_path) {
		$gene_list_blocks[$intersect_bed_path] = bedtools_parse_bed_file($intersect_bed_path);
	
		if ($gene_list_blocks[$intersect_bed_path] === false) {
			return false;
		}
	}
	
	#############################################
	# DELETE ALL TEMPORARY BED FILES
	#############################################
	
	foreach (array_keys($sample_method_bed_files) as $bed_path) {
		delete_temporary_bed_file($bed_path);
	}
	
	foreach (array_keys($merged_intersect_bed_paths) as $intersect_bed_path) {
		delete_temporary_bed_file($intersect_bed_path);
	}
	
	delete_temporary_bed_file($gene_list_bed_path);
	
	#############################################
	# CREATE SQL QUERIES AND EXECUTE THEM TO FETCH ANNOTATIONS FOR OVERLAPPING BLOCKS
	#############################################
	
	// Stores unique annotation tags for all results for printing as separate columns in the output
	$unique_annotation_tags = array();
	
	// Query the GBS for each sample/method combination separately 
	foreach (array_keys($gene_list_blocks) as $sample_method_intersect) {
		// If there are no overlaps for the current sample/method combination
		if (count(array_keys($gene_list_blocks[$sample_method_intersect])) == 0) {
			continue;
		}
		
		// Create arrays to store parameters for the GBS SQL queries for execution
		$query_parameters_GBS = array();
		$query_parameters_GBS_temporary = array();
		
		// Variable to hold the total number of overlaps to search
		$total_block_count_to_search = 0;
		
		// Array to hold genes that were parsed from each BED file per sample/method intersection
		$parsed_gene_registry = array();
		
		// Populate the temporary table query parameters
		foreach (array_keys($gene_list_blocks[$sample_method_intersect]) as $chromosome) {
			foreach (array_keys($gene_list_blocks[$sample_method_intersect][$chromosome]) as $start) {
				foreach (array_keys($gene_list_blocks[$sample_method_intersect][$chromosome][$start]) as $end) {
					array_push($query_parameters_GBS_temporary, $chromosome, $start, $end);
					
					$total_block_count_to_search++;
					
					// Go through each event_id, equivalent to a separate gene overlap, and add the overlapping genes to a registry
					foreach (array_keys($gene_list_blocks[$sample_method_intersect][$chromosome][$start][$end]) as $event_id) {
						// Pull out the gene which was parsed out of the 4th column in the intersect BED
						$parsed_gene_registry[$sample_method_intersect][$chromosome][$start][$end][] = $gene_list_blocks[$sample_method_intersect][$chromosome][$start][$end][$event_id]["4"];
					}
				}
			}
		}
		
		// Create the SQL to create a temporary table containing all GBS query coordinates
		$sql_GBS_temporary = create_temporary_query_coordinates_table_gbs($total_block_count_to_search);
		
		// Create the SQL to query the GBS for all blocks overlapping with the temporary query coordinates table
		$sql_GBS = query_blocks_by_position_gbs(1, $merged_intersect_bed_paths[$sample_method_intersect]["method"], "do_not_restrict_cn"); // CN already restricted by write_samples_methods_to_beds()
		
		// Populate the GBS query parameters (only 1 sample and method)
		array_push($query_parameters_GBS, $merged_intersect_bed_paths[$sample_method_intersect]["sample"], $merged_intersect_bed_paths[$sample_method_intersect]["method"]);
		
		######################
		
		// Perform a multi-query by sending all queries at once
		$statement = $GLOBALS["mysql_connection"]->prepare($sql_GBS_temporary);
		
		$statement->execute($query_parameters_GBS_temporary);
		
		// Perform a multi-query by sending all queries at once
		$statement = $GLOBALS["mysql_connection"]->prepare($sql_GBS);
		
		$statement->execute($query_parameters_GBS);
		
	    do {
		    $mysql_result = $statement->fetchAll(PDO::FETCH_NUM); // Fetches as $mysql_result[row array (0,1,2,3...)][column array (0,1,2,3...)] = value
		    
		    // Go through each row returned
			foreach (array_keys($mysql_result) as $result_id) {
				// Go through each gene overlapping with the current query chr, start and end
                foreach ($parsed_gene_registry[$sample_method_intersect][$mysql_result[$result_id][7]][$mysql_result[$result_id][8]][$mysql_result[$result_id][9]] as $gene) {
	                // Store the results in this format:
	                // $GBS_results[<query gene>][<sample name>][<event type>][<method name>][<block coordinates>][<copy_number/annotation_tags/block_size>] = <string value>/<array>
	                                
	                $GBS_results[$gene][$mysql_result[$result_id][0]][$mysql_result[$result_id][1]][$mysql_result[$result_id][3]][$mysql_result[$result_id][4].":".$mysql_result[$result_id][5]."-".$mysql_result[$result_id][6]]["copy_number"] = $mysql_result[$result_id][2];
	                $GBS_results[$gene][$mysql_result[$result_id][0]][$mysql_result[$result_id][1]][$mysql_result[$result_id][3]][$mysql_result[$result_id][4].":".$mysql_result[$result_id][5]."-".$mysql_result[$result_id][6]]["block_size"] = $mysql_result[$result_id][6] - $mysql_result[$result_id][5];
	                
	                // If a non-empty annotation tag is present, add it to the annotation tags array
	                if ($mysql_result[$result_id][10] != "") {
		               $GBS_results[$gene][$mysql_result[$result_id][0]][$mysql_result[$result_id][1]][$mysql_result[$result_id][3]][$mysql_result[$result_id][4].":".$mysql_result[$result_id][5]."-".$mysql_result[$result_id][6]]["annotation_tags"][$mysql_result[$result_id][10]] = $mysql_result[$result_id][11];
					
					   // If the current annotation tag has not been seen before, save it
					   if (!in_array($mysql_result[$result_id][10], $unique_annotation_tags)) {
						   array_push($unique_annotation_tags, $mysql_result[$result_id][10]);
					   }
					}
				}
	        }
	    // Ask for the next result
	    } while ($statement->nextRowset());
	}
	
	#############################################
	# CREATE OUTPUT STRINGS PER BLOCK
	#############################################

	// Create an array to hold the output strings
	$gene_lists_blocks_output = array();
	
	$header_line = "Gene\tSample\tEvent Type\tMethod\tBlock Coordinates\tBlock Size (bp)\tCopy Number";
    
    // Add each annotation tag as a column
    foreach ($unique_annotation_tags as $annotation_tag) {
	    $header_line .= "\t".$annotation_tag;
	}
	
	// Save the header line
    array_push($gene_lists_blocks_output, $header_line);
    
    // Create strings per result row and save them
    foreach (array_keys($GBS_results) as $query_gene) {
        foreach (array_keys($GBS_results[$query_gene]) as $sample_name) { 
	        foreach (array_keys($GBS_results[$query_gene][$sample_name]) as $event_type) {
		        foreach (array_keys($GBS_results[$query_gene][$sample_name][$event_type]) as $method_name) {
			        foreach (array_keys($GBS_results[$query_gene][$sample_name][$event_type][$method_name]) as $block_coordinates) {
				        $output_string = $query_gene."\t";
				        
				        $output_string .= $sample_name."\t";
				       
				        $output_string .= $event_type."\t";
				        
				        $output_string .= $method_name."\t";
				        
				        $output_string .= $block_coordinates."\t";
				        
				        $output_string .= $GBS_results[$query_gene][$sample_name][$event_type][$method_name][$block_coordinates]["block_size"]."\t";
				        
				        if ($GBS_results[$query_gene][$sample_name][$event_type][$method_name][$block_coordinates]["copy_number"] == "") {
					        $output_string .= "None\t";
				        } else {
					        $output_string .= $GBS_results[$query_gene][$sample_name][$event_type][$method_name][$block_coordinates]["copy_number"]."\t";
					    }
				        
				        // Go through each unique annotation tag for the dataset
				        foreach ($unique_annotation_tags as $annotation_tag) {
					        // If the current sample + method has a value for the current annotation tag
					        if (isset($GBS_results[$query_gene][$sample_name][$event_type][$method_name][$block_coordinates]["annotation_tags"][$annotation_tag])) {
						        $output_string .= $GBS_results[$query_gene][$sample_name][$event_type][$method_name][$block_coordinates]["annotation_tags"][$annotation_tag]."\t";
					        } else {
						        $output_string .= ".\t";
					        }
				        }
				        
				        $output_string = substr($output_string, 0, -1); // Remove the last "\t" that was added by the loop above
				        
				        array_push($gene_lists_blocks_output, $output_string);
				    }
			    }
	        }
        }
    }
	
    return $gene_lists_blocks_output;
}

?>
