<?php
	
// These are functions for administrating the GBS, this including importing and deleting blocks as well as checking whether something exists in the GBS.

#############################################
# IS A SAMPLE IN THE GBS FOR A GIVEN SOFTWARE/METHOD
#############################################

function is_sample_and_software_in_gbs($sample_name, $method) {
	$sql = "SELECT ";
		$sql .= "COUNT(GBS.block_store.id) as num_rows ";
	
	$sql .= "FROM ";
		$sql .= "GBS.block_store ";
	
	$sql .= "INNER JOIN GBS.sample_groups ON GBS.block_store.id = GBS.sample_groups.block_store_id ";
	
	$sql .= "WHERE ";
		$sql .= "GBS.block_store.method_id = (SELECT GBS.methods.id FROM GBS.methods WHERE GBS.methods.method_name = ?) ";
	$sql .= "AND ";
		$sql .= "GBS.sample_groups.sample_id = (SELECT GBS.samples.id FROM GBS.samples WHERE GBS.samples.sample_name = ?)";
	$sql .= ";";
	
	$statement = $GLOBALS["mysql_connection"]->prepare($sql);
	
	$statement->execute([$method, $sample_name]);
	
	$row = $statement->fetch();
	
	// If 1 or more blocks are already present
	if ($row["num_rows"] > 0) {
		return true;
	// Return null if no blocks were found
	} else {
		return null;
	}
}

#############################################
# ADD A SAMPLE TO THE GBS UNLESS IT IS ALREADY PRESENT
#############################################

function add_sample_to_gbs($sample_name) {
	$sql = "INSERT IGNORE INTO "; // INSERT IGNORE will only insert if the value isn't already present
		$sql .= "GBS.samples ";
		$sql .= "(sample_name) ";
	$sql .= "VALUES ";
		$sql .= "(?)";
	$sql .= ";";
	
	$statement = $GLOBALS["mysql_connection"]->prepare($sql);
	
	$statement->execute([$sample_name]);
	
	// No reason to check for rows affected as this can be 0 when the IGNORE works as it should
	
	return true;
}

#############################################
# ADD A CHROMOSOME TO THE GBS UNLESS IT IS ALREADY PRESENT
#############################################

function add_chromosome_to_gbs($chromosome) {
	$sql = "INSERT IGNORE INTO "; // INSERT IGNORE will only insert if the value isn't already present
		$sql .= "GBS.chromosomes ";
		$sql .= "(chromosome) ";
	$sql .= "VALUES ";
		$sql .= "(?)";
	$sql .= ";";
	
	$statement = $GLOBALS["mysql_connection"]->prepare($sql);
	
	$statement->execute([$chromosome]);
	
	// No reason to check for rows affected as this can be 0 when the IGNORE works as it should
	
	return true;
}

#############################################
# ADD ANNOTATION TAG(S) TO THE GBS UNLESS THEY ARE ALREADY PRESENT
#############################################

function add_annotation_tags_to_gbs(array $annotation_tags) {
	if (count($annotation_tags) == 0) {
		return null;
	}
	
	$sql = "INSERT IGNORE INTO "; // INSERT IGNORE will only insert if the value isn't already present
		$sql .= "GBS.annotation_tags ";
		$sql .= "(tag_name) ";
	$sql .= "VALUES ";
		// For every annotation tag, print a query parameter
		for ($i = 0; $i < count($annotation_tags); $i++) {
			$sql .= "(?), ";
		}
		
		$sql = substr($sql, 0, -2); // Remove the last ", " that was added by the loop above
	$sql .= ";";
	
	$statement = $GLOBALS["mysql_connection"]->prepare($sql);
	
	$statement->execute($annotation_tags);
	
	return true;
}

#############################################
# IMPORT THE BLOCKS INTO THE GBS
#############################################

function import_block_into_gbs($chromosome, $start, $end, $method, $block_value) {	
	$parameter_values = array();
	
	$sql = "INSERT INTO ";
		$sql .= "GBS.block_store ";
		$sql .= "(event_type_id, event_cn, method_id, chr_id, start, end, date_added) ";
	$sql .= "VALUES ";
		$sql .= "(";
			$sql .= "(SELECT GBS.event_types.id FROM GBS.event_types WHERE GBS.event_types.event_type = ?), ";
			$sql .= "?, ";
			$sql .= "(SELECT GBS.methods.id FROM GBS.methods WHERE GBS.methods.method_name = ?), ";
			$sql .= "(SELECT GBS.chromosomes.id FROM GBS.chromosomes WHERE GBS.chromosomes.chromosome = ?), ";
			$sql .= "?, ";
			$sql .= "?, ";
			$sql .= "now()";
		$sql .= ")";
	$sql .= ";";
	
	// Populate the event type parameter
	
	// If the event is a deletion
	if ($block_value == "DEL") {
		array_push($parameter_values, "deletion");
	// If the event is a duplication
	} elseif ($block_value == "DUP") {
		array_push($parameter_values, "duplication");
	// If the event is a INV
	} elseif ($block_value == "INV") {
		array_push($parameter_values, "inversion");
	// If the event is a BND
	} elseif ($block_value == "BND") {
		array_push($parameter_values, "BND");
	// If the event is a tandem duplication
	} elseif ($block_value == "TANDEMDUP") {
		array_push($parameter_values, "tandem duplication");
	// If the block is from ROHmer which only calls RoH events
	} elseif ($block_value == "RoH") {
		array_push($parameter_values, "roh");
	// If the block is from a method that includes a copy number estimate, work out the event type from the block copy number
	} elseif (in_array($method, array("CNVnator", "Sequenza", "CNVkit", "PURPLE"))) {
		// If the event is a deletion
		if ($block_value < 2) {
			array_push($parameter_values, "deletion");
		// If the event is a duplication
		} elseif ($block_value >= 2) {
			array_push($parameter_values, "duplication");
		} else {
			return false;
		}
	} else {
		return false;
	}
	
	// Populate the copy number
	
	// Some tools don't make a numeric copy number estimate
	if (in_array($method, array("LUMPY", "VarpipeSV", "Manta", "ROHmer"))) {
		array_push($parameter_values, NULL);
	// If the method is produces a copy number estimate
	} elseif (in_array($method, array("CNVnator", "Sequenza", "CNVkit", "PURPLE"))) {
		// Store the numeric copy number estimate
		array_push($parameter_values, $block_value);
	} else {
		return false;
	}
	
	array_push($parameter_values, $method);
	
	array_push($parameter_values, $chromosome);
	
	array_push($parameter_values, $start);
	
	array_push($parameter_values, $end);
	
	$statement = $GLOBALS["mysql_connection"]->prepare($sql);
	
	$statement->execute($parameter_values);
	
	$rows_returned = $statement->rowCount();
	
	if ($rows_returned !== 1) {
		return false;
	}
	
	// Free the result on the server so another can be executed
	$statement->closeCursor();
	
	// Return the inserted ID
	return $GLOBALS["mysql_connection"]->lastInsertId();
}

#############################################
# SQL QUERY TEXT FOR LINKING A SAMPLE TO A GENOMIC BLOCK
#############################################

function link_sample_to_block_gbs_sql_query() {
	$sql = "INSERT INTO ";
		$sql .= "GBS.sample_groups ";
		$sql .= "(block_store_id, sample_id) ";
	$sql .= "VALUES ";
		$sql .= "(";
			$sql .= "?, ";
			
			$sql .= "(SELECT GBS.samples.id FROM GBS.samples WHERE GBS.samples.sample_name = ?)";
		$sql .= ")";
	$sql .= "; ";

	return $sql;
}

#############################################
# SQL QUERY TEXT FOR LINKING MULTIPLE BLOCKS AS A SINGLE EVENT
#############################################

function link_blocks_gbs($link_type, array $block_store_ids) {
	if (count($block_store_ids) == 0) {
		return false;
	}
	
	// Create a link ID for the blocks to link
	$sql = "INSERT INTO ";
		$sql .= "GBS.links ";
		$sql .= "(link_type_id) ";
	$sql .= "VALUES ";
		$sql .= "(";
			$sql .= "(SELECT GBS.link_types.id FROM GBS.link_types WHERE GBS.link_types.link_type = ?)";
		$sql .= ")";
	$sql .= "; ";
	
	$statement = $GLOBALS["mysql_connection"]->prepare($sql);
	
	$statement->execute([$link_type]);
	
	$rows_returned = $statement->rowCount();
	
	if ($rows_returned !== 1) {
		return false;
	}
	
	// Return the inserted ID
	$link_id = $GLOBALS["mysql_connection"]->lastInsertId();
	
	$parameter_values = array();
	
	// Link the block ID to the link ID for each block ID
	$sql = "INSERT INTO ";
		$sql .= "GBS.event_links ";
		$sql .= "(link_id, block_store_id) ";
	$sql .= "VALUES ";
		foreach ($block_store_ids as $block_store_id) {
			$sql .= "(?, ?), ";
			
			array_push($parameter_values, $link_id);
			array_push($parameter_values, $block_store_id);
		}
		
		$sql = substr($sql, 0, -2); // Remove the last ", " that was added by the loop above
	$sql .= "; ";

	$statement = $GLOBALS["mysql_connection"]->prepare($sql);
	
	$statement->execute($parameter_values);
	
	$rows_returned = $statement->rowCount();
	
	if ($rows_returned === 0) {
		return false;
	} else {
		return true;
	}
}

#############################################
# SQL QUERY TEXT FOR ANNOTATING A GENOMIC BLOCK
#############################################

function annotate_block_gbs_sql_query() {
	$sql = "INSERT INTO ";
		$sql .= "GBS.annotation_values ";
		$sql .= "(block_store_id, sample_id, annotation_id, annotation_value) ";
	$sql .= "VALUES ";
		$sql .= "(";
			$sql .= "?, ";
			
			$sql .= "(SELECT GBS.samples.id FROM GBS.samples WHERE GBS.samples.sample_name = ?), ";
			
			$sql .= "(SELECT GBS.annotation_tags.id FROM GBS.annotation_tags WHERE GBS.annotation_tags.tag_name = ?), ";
			
			$sql .= "?";
		$sql .= ")";
	$sql .= "; ";

	return $sql;
}

#############################################
# FETCH ALL SAMPLES AND THEIR METHODS IN THE GBS
#############################################

function fetch_samples_and_methods_gbs() {
	$samples_and_methods = array();
	
	$sql = "SELECT DISTINCT ";
		$sql .= "GBS.samples.sample_name, ";
		$sql .= "GBS.methods.method_name ";
	
	$sql .= "FROM ";
		$sql .= "GBS.block_store ";
	
	$sql .= "INNER JOIN GBS.sample_groups ON GBS.block_store.id = GBS.sample_groups.block_store_id ";
	$sql .= "INNER JOIN GBS.samples ON GBS.sample_groups.sample_id = GBS.samples.id ";
	$sql .= "INNER JOIN GBS.methods ON GBS.block_store.method_id = GBS.methods.id ";
	
	$sql .= "WHERE ";
		$sql .= "GBS.block_store.id = GBS.sample_groups.block_store_id";
	$sql .= ";";
	
	$statement = $GLOBALS["mysql_connection"]->prepare($sql);
	
	$statement->execute();
	
	while ($row = $statement->fetch()) {
		$samples_and_methods[$row["sample_name"]][] = $row["method_name"];
	}
	
	return $samples_and_methods;
}

#############################################
# DELETE GBS BLOCKS
#############################################

function delete_blocks_gbs($sample_name, $method) {
	// This function uses a 2 stage approach to deleting genome blocks
	// First, it removes the association of the sample with all blocks where the block has the specified method and sample associated with it
	// Second, it removes all blocks that have no samples associated with them
	// The reason for this approach is that a block can have multiple samples associated with it, so in this case the first stage will remove this association for the sample to be deleted and the second stage will do nothing, i.e. leaving the other samples still associated with the block and not deleting it
	
	// Delete all rows in GBS.sample_groups where the sample and method for the block match
	$sql = "DELETE ";
		$sql .= "GBS.sample_groups ";
	$sql .= "FROM ";
		$sql .= "GBS.sample_groups ";

	$sql .= "INNER JOIN GBS.block_store ON GBS.sample_groups.block_store_id = GBS.block_store.id ";
	$sql .= "INNER JOIN GBS.samples ON GBS.sample_groups.sample_id = GBS.samples.id ";
	$sql .= "INNER JOIN GBS.methods ON GBS.block_store.method_id = GBS.methods.id ";
	
	$sql .= "WHERE ";
		$sql .= "GBS.methods.method_name = ? ";
	$sql .= "AND ";
		$sql .= "GBS.samples.sample_name = ?";
	$sql .= ";";
	
	$statement = $GLOBALS["mysql_connection"]->prepare($sql);
	
	$statement->execute([$method, $sample_name]);
	
	$rows_affected = $statement->rowCount();
	
	if ($rows_affected === 0) {
		return false;
	}
	
	#############################################
	
	// Check for orphan blocks with no samples in GBS.sample_groups and delete them (these would be left over by deleting the only sample in GBS.sample_groups)
	$sql = "DELETE ";
		$sql .= "GBS.block_store ";
	$sql .= "FROM ";
		$sql .= "GBS.block_store ";

	$sql .= "WHERE NOT EXISTS "; // Delete all rows in the block_store table where there are no rows with the block_store id in the sample_groups table
		$sql .= "(SELECT 1 FROM GBS.sample_groups WHERE GBS.sample_groups.block_store_id = GBS.block_store.id)";
	$sql .= ";";
	
	$statement = $GLOBALS["mysql_connection"]->prepare($sql);
	
	$statement->execute();
	
	$rows_affected = $statement->rowCount();
	
	if ($rows_affected > 0) {
		return true;
	} else {
		return false;
	}
}

#############################################
# FETCH THE GBS PRESENCE AND METHODS FOR AN ARRAY OF SAMPLE NAMES
#############################################

function fetch_gbs_samples_presence(array $sample_names) {
	// Define the results array
	$GBS_presence = array();
	
	// If no samples are provided, return the results empty array
	if (count($sample_names) == 0) {
		return $GBS_presence;
	}
	
	$sql = "SELECT DISTINCT ";
		$sql .= "GBS.methods.method_name, ";
		$sql .= "GBS.samples.sample_name, ";
		$sql .= "GBS.event_types.event_type ";
	
	$sql .= "FROM ";
		$sql .= "GBS.block_store ";
		
	$sql .= "INNER JOIN GBS.sample_groups ON GBS.block_store.id = GBS.sample_groups.block_store_id ";
	$sql .= "INNER JOIN GBS.samples ON GBS.sample_groups.sample_id = GBS.samples.id ";
	$sql .= "INNER JOIN GBS.methods ON GBS.block_store.method_id = GBS.methods.id ";
	$sql .= "INNER JOIN GBS.event_types ON GBS.block_store.event_type_id = GBS.event_types.id ";
	
	$sql .= "WHERE ";
		$sql .= "GBS.samples.sample_name IN (";
			$sql .= str_repeat("?, ", count($sample_names));
			
			$sql = substr($sql, 0, -2); // Remove the last ", " that was added above
		$sql .= ")";
	$sql .= ";";
	
	$statement = $GLOBALS["mysql_connection"]->prepare($sql);
	
	$statement->execute($sample_names);
	
	while ($row = $statement->fetch()) {
		// Save all event types per method
		$GBS_presence[$row["sample_name"]]["methods"][$row["method_name"]][] = $row["event_type"];
		
		// Save all methods per event type
		$GBS_presence[$row["sample_name"]]["event_types"][$row["event_type"]][] = $row["method_name"];
	}
	
	return $GBS_presence;
}

#############################################
# VALIDATE GENE LIST AGAINST THE GBS
#############################################

function validate_gene_list_gbs(array $gene_list) {
	// Make sure genes were submitted
	if (count($gene_list) == 0) {
		return false;
	}

	$sql = "SELECT DISTINCT ";
		$sql .= "GBS.genes_to_positions.gene_name ";
	
	$sql .= "FROM ";
		$sql .= "GBS.genes_to_positions ";
		
	$sql .= "WHERE ";
		$sql .= "GBS.genes_to_positions.gene_name IN (";
			$sql .= str_repeat("?, ", count($gene_list));
			
			$sql = substr($sql, 0, -2); // Remove the last ", " that was added above
		$sql .= ")";
	$sql .= ";";
	
	$statement = $GLOBALS["mysql_connection"]->prepare($sql);
	
	$statement->execute($gene_list);
	
	while ($row = $statement->fetch()) {
		// Remove the gene from the gene list, remaining genes will be ones that weren't found
		unset($gene_list[array_search($row["gene_name"], $gene_list)]);
	}
	
	// Re-index the array
	$gene_list = array_values($gene_list);
	
	// Array of genes that are not in the GBS coordinates table
	return $gene_list;
}

?>
