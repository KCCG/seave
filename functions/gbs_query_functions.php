<?php
	
// These are functions for querying the GBS for blocks, genes and annotations

#############################################
# CREATE SQL QUERY STRING TO QUERY THE GBS FOR A GIVEN METHOD AND NUMBER OF SAMPLES
#############################################

function query_blocks_by_position_gbs($num_samples, $method, $cn_restriction_flag, $block_size_restriction_flag) {
	$sql = "SELECT ";
		$sql .= "GBS.samples.sample_name, ";
		$sql .= "GBS.event_types.event_type, ";
		$sql .= "GBS.block_store.event_cn, ";
		$sql .= "GBS.methods.method_name, ";
		$sql .= "GBS.chromosomes.chromosome, ";
		$sql .= "GBS.block_store.start, ";
		$sql .= "GBS.block_store.end, ";
		$sql .= "GBS.query_coordinates.chromosome AS 'query_chromosome', "; // Query chromosome
		$sql .= "GBS.query_coordinates.query_start AS 'query_start', "; // Query start position
		$sql .= "GBS.query_coordinates.query_end AS 'query_end', "; // Query end position
		$sql .= "GBS.annotation_tags.tag_name, ";
		$sql .= "GBS.annotation_values.annotation_value ";
		
	$sql .= "FROM ";
		$sql .= "GBS.block_store ";
	
	$sql .= "INNER JOIN GBS.chromosomes ON GBS.block_store.chr_id = GBS.chromosomes.id ";
	
	// Join the table with the query coordinates so that only rows in the GBS DB matching the query coordinates are returned
	$sql .= "JOIN GBS.query_coordinates ON ";
		$sql .= "GBS.chromosomes.chromosome = GBS.query_coordinates.chromosome ";
		
		$sql .= "AND ";
		
		$sql .= "(";
			// Case where block spans the entire start and end of the variant
			//      . .
			// ------------
			$sql .= "(";
					$sql .= "GBS.block_store.start <= GBS.query_coordinates.query_start "; // IMPORTANT: this parameter should be the query start position
				$sql .= "AND ";
					$sql .= "GBS.block_store.end >= GBS.query_coordinates.query_end"; // IMPORTANT: this parameter should be the query end position
			$sql .= ") ";
			$sql .= "OR ";
			// Case where the block spans the start of the variant
			//      . .
			// -------
			$sql .= "(";
					$sql .= "GBS.block_store.start <= GBS.query_coordinates.query_start "; // IMPORTANT: this parameter should be the query start position
				$sql .= "AND ";
					$sql .= "GBS.block_store.end >= GBS.query_coordinates.query_start"; // IMPORTANT: this parameter should be the query start position
			$sql .= ") ";
			$sql .= "OR ";
			// Case where the block spans the end of the variant
			//      . .
			//       -------
			$sql .= "(";
					$sql .= "GBS.block_store.start <= GBS.query_coordinates.query_end "; // IMPORTANT: this parameter should be the query end position
				$sql .= "AND ";
					$sql .= "GBS.block_store.end >= GBS.query_coordinates.query_end"; // IMPORTANT: this parameter should be the query end position
			$sql .= ") ";
			$sql .= "OR ";
			// Case where the block spans the width of the variant
			//  .     .
			//   ----
			$sql .= "(";
					$sql .= "GBS.block_store.start >= GBS.query_coordinates.query_start "; // IMPORTANT: this parameter should be the query start position
				$sql .= "AND ";
					$sql .= "GBS.block_store.end <= GBS.query_coordinates.query_end"; // IMPORTANT: this parameter should be the query end position
			$sql .= ")";
		$sql .= ") ";
		
	$sql .= "INNER JOIN GBS.event_types ON GBS.block_store.event_type_id = GBS.event_types.id ";
	$sql .= "INNER JOIN GBS.sample_groups ON GBS.block_store.id = GBS.sample_groups.block_store_id ";
	$sql .= "INNER JOIN GBS.samples ON GBS.sample_groups.sample_id = GBS.samples.id ";
	$sql .= "INNER JOIN GBS.methods ON GBS.block_store.method_id = GBS.methods.id ";
	$sql .= "LEFT OUTER JOIN GBS.annotation_values ON GBS.block_store.id = GBS.annotation_values.block_store_id "; // LEFT OUTER JOIN means extra rows will be returned if annotation tags/values are present, otherwise just 1 row will be returned with null for annotation_tag and annotation_value
	$sql .= "INNER JOIN GBS.annotation_tags ON GBS.annotation_values.annotation_id = GBS.annotation_tags.id ";
	
	$sql .= "WHERE ";
		$sql .= "GBS.samples.sample_name IN (";
			
			$sql .= str_repeat("?, ", $num_samples); // Query samples (? for each sample)

			$sql = substr($sql, 0, -2); // Remove the last ", " that was added above
			
		$sql .= ")";
		// If a method was specified, add a restriction
		if ($method != "all") {
			$sql .= " AND ";
			
			$sql .= "GBS.methods.method_name = ?"; // Query method
		}
		
		// If a copy number restriction is to be performed
		if ($cn_restriction_flag == "restrict_cn") {
			$sql .= " AND ";
			
				$sql .= "(";
					$sql .= "GBS.block_store.event_cn <= ? ";
					
					$sql .= "OR ";
					
					$sql .= "GBS.block_store.event_cn >= ? ";
					
					$sql .= "OR ";
					
					$sql .= "GBS.block_store.event_cn IS NULL";
				$sql .= ")";
		}
		
		// If failed variants should be excluded
		if ($_SESSION["gbs_exclude_failed_variants"] == "1") {
			$sql .= " AND ";
				$sql .= "(";
					// If there are no FT or FILTER annotation tags for the current block
					$sql .= "(SELECT COUNT(*) FROM GBS.annotation_values INNER JOIN GBS.annotation_tags ON GBS.annotation_values.annotation_id = GBS.annotation_tags.id WHERE GBS.annotation_values.block_store_id = GBS.block_store.id AND GBS.annotation_tags.tag_name IN ('FT', 'FILTER')) = 0 ";
					
					$sql .= "OR ";
					
					// If the number of 'PASS' values for FT or FILTER annotation tags is 1 or more (can't just use this statement as a value of 0 could be because there are no FT/FILTER tags for the current block or there are but they aren't PASS)
					$sql .= "(SELECT COUNT(*) FROM GBS.annotation_values INNER JOIN GBS.annotation_tags ON GBS.annotation_values.annotation_id = GBS.annotation_tags.id WHERE GBS.annotation_values.block_store_id = GBS.block_store.id AND GBS.annotation_tags.tag_name IN ('FT', 'FILTER') AND GBS.annotation_values.annotation_value = 'PASS') > 0";
				$sql .= ")";
		}
		
		// If a minimum block size is to be searched
		if ($block_size_restriction_flag == "restrict_block_size") {
			$sql .= " AND ";
				$sql .= "(GBS.block_store.end - GBS.block_store.start) >= ?";
		}
	$sql .= ";";
	
	return $sql;
}

#############################################
# CREATE SQL QUERY STRING TO CREATE A TEMPORARY TABLE WITH GBS QUERY COORDINATES
#############################################

function create_temporary_query_coordinates_table_gbs($num_coordinates) {
	// Delete the temporary table if it already exists, allows calling this function multiple times in the same connection
	$sql = "DROP TABLE IF EXISTS GBS.query_coordinates; ";
	
	$sql .= "CREATE TEMPORARY TABLE GBS.query_coordinates (";
				
		$sql .= "id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, ";
		$sql .= "chromosome VARCHAR(15) NOT NULL, ";
		$sql .= "query_start INT UNSIGNED NOT NULL, ";
		$sql .= "query_end INT UNSIGNED NOT NULL, ";
		$sql .= "INDEX query (chromosome, query_start, query_end)";
	
	$sql .= ");";
	
	$sql .= "INSERT INTO GBS.query_coordinates (chromosome, query_start, query_end) VALUES ";
		
		// Add each set of coordinates to the query
		$sql .= str_repeat("(?, ?, ?), ", $num_coordinates);
		
		// Remove the last ", " added by the string repeat
		$sql = substr($sql, 0, -2);
		
	$sql .= ";";
	
	return $sql;
}

#############################################
# QUERY THE GBS FOR ALL BLOCKS FOR A GIVEN LIST OF SAMPLES AND METHODS
#############################################

function fetch_blocks_for_samples_methods_events_gbs(array $sample_names, array $method_names, array $event_types) {
	// Check that at least one sample has been supplied
	if (count($sample_names) == 0) {
		return false;
	}
	
	$sql = "SELECT ";
		$sql .= "GBS.block_store.id, ";
		$sql .= "GBS.samples.sample_name, ";
		$sql .= "GBS.methods.method_name, ";
		$sql .= "GBS.chromosomes.chromosome, ";
		$sql .= "GBS.block_store.start, ";
		$sql .= "GBS.block_store.end, ";
		$sql .= "GBS.event_types.event_type ";

	$sql .= "FROM ";
		$sql .= "GBS.block_store ";

	$sql .= "INNER JOIN GBS.chromosomes ON GBS.block_store.chr_id = GBS.chromosomes.id ";
	$sql .= "INNER JOIN GBS.sample_groups ON GBS.block_store.id = GBS.sample_groups.block_store_id ";
	$sql .= "INNER JOIN GBS.samples ON GBS.sample_groups.sample_id = GBS.samples.id ";
	$sql .= "INNER JOIN GBS.methods ON GBS.block_store.method_id = GBS.methods.id ";
	$sql .= "INNER JOIN GBS.event_types ON GBS.block_store.event_type_id = GBS.event_types.id ";

	$sql .= "WHERE ";
		$sql .= "GBS.samples.sample_name IN (";
			$sql .= str_repeat("?, ", count($sample_names));
			
			$sql = substr($sql, 0, -2); // Remove the last ", " that was added above
		$sql .= ") ";
	
	// If method names have been supplied, restrict the results by method
	if (count($method_names) > 0) {	
		$sql .= "AND ";
	
			$sql .= "GBS.methods.method_name IN (";
				$sql .= str_repeat("?, ", count($method_names));
			
				$sql = substr($sql, 0, -2); // Remove the last ", " that was added above			
			$sql .= ") ";
	}
	
	// If event types have been supplied, restrict the results by event type
	if (count($event_types) > 0) {
		$sql .= "AND ";
	
			$sql .= "GBS.event_types.event_type IN (";
				$sql .= str_repeat("?, ", count($event_types));
			
				$sql = substr($sql, 0, -2); // Remove the last ", " that was added above			
			$sql .= ") ";
	}
	
	// If the analysis type is not one that doesn't use copy number restrictions and a copy number restriction has been specified, add the SQL clause
	if (!in_array($_SESSION["gbs_analysis_type"], array("rohmer", "svfusions")) && is_numeric($_SESSION["gbs_cngreaterthan"]) && is_numeric($_SESSION["gbs_cnlessthan"])) {
		$sql .= "AND ";
		
			$sql .= "(";
				$sql .= "GBS.block_store.event_cn <= ? ";
				
				$sql .= "OR ";
				
				$sql .= "GBS.block_store.event_cn >= ? ";
				
				$sql .= "OR ";
				
				$sql .= "GBS.block_store.event_cn IS NULL";
			$sql .= ") ";
	}
	
	// If failed variants should be excluded
	if ($_SESSION["gbs_exclude_failed_variants"] == "1") {
		$sql .= " AND ";
			$sql .= "(";
				// If there are no FT or FILTER annotation tags for the current block
				$sql .= "(SELECT COUNT(*) FROM GBS.annotation_values INNER JOIN GBS.annotation_tags ON GBS.annotation_values.annotation_id = GBS.annotation_tags.id WHERE GBS.annotation_values.block_store_id = GBS.block_store.id AND GBS.annotation_tags.tag_name IN ('FT', 'FILTER')) = 0 ";
				
				$sql .= "OR ";
				
				// If the number of 'PASS' values for FT or FILTER annotation tags is 1 or more (can't just use this statement as a value of 0 could be because there are no FT/FILTER tags for the current block or there are but they aren't PASS)
				$sql .= "(SELECT COUNT(*) FROM GBS.annotation_values INNER JOIN GBS.annotation_tags ON GBS.annotation_values.annotation_id = GBS.annotation_tags.id WHERE GBS.annotation_values.block_store_id = GBS.block_store.id AND GBS.annotation_tags.tag_name IN ('FT', 'FILTER') AND GBS.annotation_values.annotation_value = 'PASS') > 0";
			$sql .= ")";
	}
	
	// If the analysis type is not SV Fusions (which only has tiny block sizes) and if a minimum block size is to be used for queries, add the SQL clause
	if ($_SESSION["gbs_analysis_type"] != "svfusions" && is_numeric($_SESSION["gbs_minblocksize"])) {
		$sql .= " AND ";
			$sql .= "(GBS.block_store.end - GBS.block_store.start) >= ?";
	}
	
	$sql .= ";";
	
	#############################################
	
	$parameter_values = array_merge($sample_names, $method_names, $event_types);
	
	#############################################
	
	// If the analysis type is not one that doesn't use copy number restrictions and a copy number restriction has been specified, add the SQL parameters
	if (!in_array($_SESSION["gbs_analysis_type"], array("rohmer", "svfusions")) && is_numeric($_SESSION["gbs_cngreaterthan"]) && is_numeric($_SESSION["gbs_cnlessthan"])) {
		array_push($parameter_values, $_SESSION["gbs_cnlessthan"], $_SESSION["gbs_cngreaterthan"]);
	}
	
	#############################################
	
	// If the analysis type is not SV Fusions (which only has tiny block sizes) and if a minimum block size is to be used for queries, add the parameter
	if ($_SESSION["gbs_analysis_type"] != "svfusions" && is_numeric($_SESSION["gbs_minblocksize"])) {
		$parameter_values[] = $_SESSION["gbs_minblocksize"];
	}
	
	#############################################
	
	$statement = $GLOBALS["mysql_connection"]->prepare($sql);
	
	$statement->execute($parameter_values);
	
	#############################################
	
	// Create the results array
	$GBS_results = array();
	
	while ($row = $statement->fetch()) {
		$GBS_results[$row["sample_name"]][$row["method_name"]][$row["id"]]["chromosome"] = $row["chromosome"];
		$GBS_results[$row["sample_name"]][$row["method_name"]][$row["id"]]["start"] = $row["start"];
		$GBS_results[$row["sample_name"]][$row["method_name"]][$row["id"]]["end"] = $row["end"];
		$GBS_results[$row["sample_name"]][$row["method_name"]][$row["id"]]["event_type"] = $row["event_type"];
	}

	return $GBS_results;
}

#############################################
# QUERY THE GBS FOR LINKED BLOCK ANNOTATIONS FOR BLOCK IDS
#############################################

function fetch_linked_block_annotations_gbs(array $block_ids, array $samples) {
	// Check that at least one block ID and sample have been supplied
	if (count($block_ids) == 0 || count($samples) == 0) {
		return false;
	}
	
	// Go through each block ID
	for ($i = 0; $i < count($block_ids); $i++) {
		// If the block ID ends with -1 or -2, strip these off (these are added for split breakpoints for single blocks (e.g. deletions which are 1 block but are split into 2 for this analysis)
		if (preg_match("/\-[1-2]$/", $block_ids[$i])) {
			$block_ids[$i] = substr($block_ids[$i], 0, -2);
		}
	}
	
	$sql = " SELECT ";
		$sql .= "GBS.event_links.link_id, ";
		$sql .= "GBS.block_store.id AS block_id, ";
		$sql .= "GBS.samples.sample_name, ";
		$sql .= "GBS.methods.method_name, ";
		$sql .= "GBS.chromosomes.chromosome, ";
		$sql .= "GBS.block_store.start, ";
		$sql .= "GBS.block_store.end, ";
		$sql .= "GBS.event_types.event_type, ";
		$sql .= "GBS.link_types.link_type, ";
		$sql .= "GBS.annotation_tags.tag_name, ";
		$sql .= "GBS.annotation_values.annotation_value ";
	
	$sql .= "FROM GBS.block_store ";
		
	$sql .= "INNER JOIN GBS.chromosomes ON GBS.block_store.chr_id = GBS.chromosomes.id ";
	$sql .= "INNER JOIN GBS.sample_groups ON GBS.block_store.id = GBS.sample_groups.block_store_id ";
	$sql .= "INNER JOIN GBS.samples ON GBS.sample_groups.sample_id = GBS.samples.id ";
	$sql .= "INNER JOIN GBS.methods ON GBS.block_store.method_id = GBS.methods.id ";
	$sql .= "INNER JOIN GBS.event_types ON GBS.block_store.event_type_id = GBS.event_types.id ";
	$sql .= "LEFT JOIN GBS.event_links ON GBS.block_store.id = GBS.event_links.block_store_id "; // LEFT JOIN to still return all blocks where there is no link
	$sql .= "LEFT JOIN GBS.links ON GBS.event_links.link_id = GBS.links.id ";
	$sql .= "LEFT JOIN GBS.link_types ON GBS.links.link_type_id = GBS.link_types.id ";
	$sql .= "LEFT OUTER JOIN GBS.annotation_values ON GBS.block_store.id = GBS.annotation_values.block_store_id ";
	$sql .= "INNER JOIN GBS.annotation_tags ON GBS.annotation_values.annotation_id = GBS.annotation_tags.id ";
	
	$sql .= "WHERE ";
		$sql .= "(";
			// This will fetch linked blocks that may not be in the list of block id's to search, i.e. the breakpoint that did not overlap with a gene where its partner did
			$sql .= "GBS.event_links.link_id IN (SELECT GBS.event_links.link_id FROM GBS.event_links WHERE GBS.event_links.block_store_id IN (";
				$sql .= str_repeat("?, ", count($block_ids));
				
				$sql = substr($sql, 0, -2); // Remove the last ", " that was added above			
			$sql .= ")) ";
			
			$sql .= "OR ";
			
			// This will fetch all remaining blocks that aren't in event_links because they are just single blocks (e.g. deletions)
			$sql .= "GBS.block_store.id IN (";
				$sql .= str_repeat("?, ", count($block_ids));
			
				$sql = substr($sql, 0, -2); // Remove the last ", " that was added above
			$sql .= ") ";
		$sql .= ") ";	
	$sql .= "AND ";
		$sql .= "GBS.samples.sample_name IN (";
			$sql .= str_repeat("?, ", count($samples));
			
			$sql = substr($sql, 0, -2); // Remove the last ", " that was added above			
		$sql .= ")";
	$sql .= ";";
	
	$parameter_values = array_merge($block_ids, $block_ids, $samples);
	
	$statement = $GLOBALS["mysql_connection"]->prepare($sql);
	
	$statement->execute($parameter_values);
	
	// Create an array to store unique annotation tags
	$unique_annotation_tags = array();
	
	// Create the results array
	$GBS_results = array();
	
	while ($row = $statement->fetch()) {
		// If there is no link ID because the block ID doesn't have 2 breakpoints stored as separate blocks (e.g. deletion), create a link ID so downstream code still sees it as a separate fusion
		if ($row["link_id"] == "") {
			$row["link_id"] = $row["block_id"]."-nolink";
			
			$row["link_type"] = $row["event_type"];
		}
		
		// Save all information per link
		$GBS_results["blocks_per_link"][$row["link_id"]]["block_ids"][$row["block_id"]] = 1; // Do it like this as there are multiple rows per event + block from the DB
		$GBS_results["blocks_per_link"][$row["link_id"]]["link_type"] = $row["link_type"];
		
		// Save all per block information
		$GBS_results["blocks"][$row["block_id"]]["method"] = $row["method_name"];
		$GBS_results["blocks"][$row["block_id"]]["chromosome"] = $row["chromosome"];
		$GBS_results["blocks"][$row["block_id"]]["start"] = $row["start"];
		$GBS_results["blocks"][$row["block_id"]]["end"] = $row["end"];
		$GBS_results["blocks"][$row["block_id"]]["event_type"] = $row["event_type"];
		
		// Per sample information (information that is at the sample level)
		$GBS_results["blocks"][$row["block_id"]]["samples"][$row["sample_name"]]["annotations"][$row["tag_name"]] = $row["annotation_value"];
		
		// If the current annotation tag has not been seen before
		if (!in_array($row["tag_name"], $unique_annotation_tags)) {
			$unique_annotation_tags[] = $row["tag_name"];
		}
	}
	
	$GBS_results["unique_annotation_tags"] = $unique_annotation_tags;
	
	// Returned:
	//$GBS_results["blocks_per_link"][<link id>]["block_ids"][<block id>] = 1;
	//$GBS_results["blocks_per_link"][<link id>]["link_type"] = <value>
	
	//$GBS_results["blocks"][<block id>][<method>/<chromosome>/<start>/<end>/<event_type>] = <value>
	//$GBS_results["blocks"][<block id>]["samples"][<sample name>]["annotations"][<tag name>] = <tag value>
	
	//$GBS_results["unique_annotation_tags"] = <array of annotation tags>
	
	return $GBS_results;
}

#############################################
# WRITE A GENE LIST TO A BED FILE
#############################################

function write_gene_list_to_bed(array $gene_list) {
	$sql = "SELECT ";
		$sql .= "GBS.genes_to_positions.id, ";
		$sql .= "GBS.genes_to_positions.gene_name, ";
		$sql .= "GBS.genes_to_positions.chromosome, ";
		$sql .= "GBS.genes_to_positions.start, ";
		$sql .= "GBS.genes_to_positions.end ";
	
	$sql .= "FROM ";
		$sql .= "GBS.genes_to_positions ";
	
	// Restrict the search by gene names if they were supplied
	if (count($gene_list) > 0) {
		$sql .= "WHERE ";
			$sql .= "GBS.genes_to_positions.gene_name IN (";
				$sql .= str_repeat("?, ", count($gene_list));
				
				$sql = substr($sql, 0, -2); // Remove the last ", " that was added above		
			$sql .= ")";
	
	// If no genes were supplied, write all of them
	} else {
		$sql = substr($sql, 0, -1); // Remove the " " that was added above
	}
	$sql .= ";";
	
	$statement = $GLOBALS["mysql_connection"]->prepare($sql);
	
	$statement->execute($gene_list);
	
	$rows_affected = $statement->rowCount();
	
	// Must have at least one result row
	if ($rows_affected === 0) {
		return false;
	}
	
	$bed_output = array();
	
	// Go through each result and save the BED row to an array for output
	while ($row = $statement->fetch()) {
		$bed_output[] = $row["chromosome"]."\t".$row["start"]."\t".$row["end"]."\t".$row["gene_name"]."\n";
	}
	
	// Create a random string for the temporary BED filename
	$bed_filename = rand_string();
	
	// Write the gene coordinates to a temporary BED file
	$bed_path = create_temporary_bed_file($bed_filename, $bed_output);
	
	if ($bed_path === false) {
		return false;
	} else {
		return $bed_path;
	}
}

#############################################
# WRITE SAMPLE + METHOD PAIRS TO BED FILES
#############################################

function write_samples_methods_to_beds(array $sample_names, array $method_names) {
	// At least one sample must be supplied
	if (count($sample_names) == 0) {
		return false;
	}
	
	#############################################
	# FETCH ALL BLOCKS FOR SAMPLES AND METHODS
	#############################################
	
	$GBS_results = fetch_blocks_for_samples_methods_events_gbs($sample_names, $method_names, array());
	
	if ($GBS_results === false) {
		return false;
	}
	
	#############################################
	# CREATE BED FILES PER METHOD + SAMPLE
	#############################################
	
	// Generate a random string to append to the BED filenames (to protect against conflicts and deleting files in use by other processes at the same time)
	$suffix = rand_string();
	
	// Empty array to house BED files
	$output_bed_files = array();
	
	// Go through each sample found
	foreach (array_keys($GBS_results) as $sample_name) {
		// Go through each method for the sample
		foreach (array_keys($GBS_results[$sample_name]) as $method_name) {
			// Array of lines to output to a BED file
			$bed_output = array();
			
			// Go through each block
			foreach (array_keys($GBS_results[$sample_name][$method_name]) as $block_id) {
				// Create the line in the BED file for the sample + block
				array_push($bed_output, $GBS_results[$sample_name][$method_name][$block_id]["chromosome"]."\t".$GBS_results[$sample_name][$method_name][$block_id]["start"]."\t".$GBS_results[$sample_name][$method_name][$block_id]["end"]."\t".$block_id."\n");
			}
			
			// Write the blocks to a temporary BED file
			$bed_path = create_temporary_bed_file($method_name.".".$sample_name.".".$suffix, $bed_output);
			
			if ($bed_path === false) {
				return false;
			}
			
			// Save the file name of the BED file and associated sample and method to have sets for bedtools analysis
			$output_bed_files[$bed_path]["method"] = $method_name;
			$output_bed_files[$bed_path]["sample"] = $sample_name;
		}
	}
	
	if (count(array_keys($output_bed_files)) == 0) {
		return false;
	} else {
		return $output_bed_files;
	}
}

#############################################
# WRITE EVENT TYPE BLOCKS TO BED FILES PER SAMPLE
#############################################

function write_event_types_per_sample_to_beds(array $sample_names, array $event_types, array $split_event_types) {
	// At least one sample and event type must be supplied
	if (count($sample_names) == 0 || count($event_types) == 0) {
		return false;
	}
	
	#############################################
	# FETCH ALL BLOCKS FOR SAMPLES AND EVENT TYPES
	#############################################
	
	$GBS_results = fetch_blocks_for_samples_methods_events_gbs($sample_names, array(), array_merge($event_types, $split_event_types));
	
	if ($GBS_results === false) {
		return false;
	}
	
	#############################################
	# CREATE BED FILES PER SAMPLE
	#############################################
	
	// Generate a random string to append to the BED filenames (to protect against conflicts and deleting files in use by other processes at the same time)
	$suffix = rand_string();
	
	// Empty array to house BED files
	$output_bed_files = array();
	
	// Go through each sample found
	foreach (array_keys($GBS_results) as $sample_name) {
		// Array of lines to output to a BED file
		$bed_output = array();
		
		// Array of methods with desired event types found
		$methods = array();
		
		// Go through each method with desired event types for the sample
		foreach (array_keys($GBS_results[$sample_name]) as $method_name) {
			// Go through each block
			foreach (array_keys($GBS_results[$sample_name][$method_name]) as $block_id) {
				// If the current event is one of the ones for which to split one block into 2 breakpoints because the block is not stored as 2 breakpoints (e.g. deletions)
				if (in_array($GBS_results[$sample_name][$method_name][$block_id]["event_type"], $split_event_types)) {
					// Create 2 lines in the BED file for the sample + split block into start and end and add a -1/-2 to the end of the block id to enable teasing apart what genes they overlapped with later
					array_push($bed_output, $GBS_results[$sample_name][$method_name][$block_id]["chromosome"]."\t".$GBS_results[$sample_name][$method_name][$block_id]["start"]."\t".($GBS_results[$sample_name][$method_name][$block_id]["start"] + 1)."\t".$block_id."-1\n");
					array_push($bed_output, $GBS_results[$sample_name][$method_name][$block_id]["chromosome"]."\t".$GBS_results[$sample_name][$method_name][$block_id]["end"]."\t".($GBS_results[$sample_name][$method_name][$block_id]["end"] + 1)."\t".$block_id."-2\n");
				} else {
					// Create the line in the BED file for the sample + block
					array_push($bed_output, $GBS_results[$sample_name][$method_name][$block_id]["chromosome"]."\t".$GBS_results[$sample_name][$method_name][$block_id]["start"]."\t".$GBS_results[$sample_name][$method_name][$block_id]["end"]."\t".$block_id."\n");
				}
			}
			
			$methods[] = $method_name;
		}
		
		// Write the blocks to a temporary BED file
		$bed_path = create_temporary_bed_file($sample_name.".".$suffix, $bed_output);
		
		if ($bed_path === false) {
			return false;
		}
		
		// Save the file name of the BED file and associated sample to have sets for bedtools analysis
		$output_bed_files[$bed_path]["sample"] = $sample_name;
		
		// Save all the methods that contained blocks with the desired event types
		$output_bed_files[$bed_path]["methods"] = $methods;
	}
	
	if (count(array_keys($output_bed_files)) == 0) {
		return false;
	} else {
		return $output_bed_files;
	}
}

?>
