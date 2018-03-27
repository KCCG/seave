<?php

#############################################
# GENERATE LIST OF GENE LISTS AVAILABLE
#############################################

function fetch_gene_lists() {
	$gene_list = array();
	
	$sql = "SELECT ";
		$sql .= "list_name, ";
		$sql .= "(SELECT COUNT(*) FROM KCCG_GENE_LISTS.genes_in_lists WHERE list_id = gene_lists.list_id) AS num_genes ";
	$sql .= "FROM ";
		$sql .= "KCCG_GENE_LISTS.gene_lists ";
	$sql .= "ORDER BY list_name";
	$sql .= ";";
	
	$statement = $GLOBALS["mysql_connection"]->prepare($sql);
	
	$statement->execute();
	
	while ($list = $statement->fetch()) {
		$gene_list[$list["list_name"]] = $list["num_genes"];
	}
	
	return $gene_list;
}

#############################################
# FETCH GENOMICS ENGLAND PANELAPP GENE COUNTS
#############################################

function fetch_panel_counts_ge_panelapp() {
	$ge_panelapp_panels = array();
	
	$sql = "SELECT ";
		$sql .= "panels.name, ";
		$sql .= "genes_in_panels.confidence_level, ";
		$sql .= "COUNT(*) AS num_genes ";
	$sql .= "FROM ";
		$sql .= "GEPANELAPP.genes_in_panels ";
	$sql .= "LEFT JOIN GEPANELAPP.panels ON GEPANELAPP.genes_in_panels.panel_id = GEPANELAPP.panels.id ";
	$sql .= "GROUP BY panels.name, genes_in_panels.confidence_level ";
	$sql .= "ORDER BY panels.name";
	$sql .= ";";
	
	$statement = $GLOBALS["mysql_connection"]->prepare($sql);
	
	$statement->execute();
	
	while ($panels = $statement->fetch()) {
		$ge_panelapp_panels[$panels["name"]][$panels["confidence_level"]] = $panels["num_genes"];
	}
	// Format: $ge_panelapp_panels[<panel name>][<confidence level>] = number of genes
	
	// Go through each panel
	foreach (array_keys($ge_panelapp_panels) as $ge_panelapp_panel) {
		// If the current panel has both high and moderate evidence genes
		if (isset($ge_panelapp_panels[$ge_panelapp_panel]["HighEvidence"], $ge_panelapp_panels[$ge_panelapp_panel]["ModerateEvidence"])) {
			// Inject in a new evidence level which combines the high and moderate counts
			$ge_panelapp_panels[$ge_panelapp_panel]["HighModerateEvidence"] = $ge_panelapp_panels[$ge_panelapp_panel]["HighEvidence"] + $ge_panelapp_panels[$ge_panelapp_panel]["ModerateEvidence"];
		}
	}
	
	return $ge_panelapp_panels;
}

#############################################
# VALIDATE A GENE LIST AGAINST THE LIST OF GENES THAT ARE ANNOTATED IN THE DATABASES
#############################################

function validate_gene_list($gene_list) { // Input array of genes to validate
	// If the file doesn't exist
	if (!file_exists("../assets/validation_gene_list.txt")) {
		return false;
	}
	
	$gene_list_file = fopen("../assets/validation_gene_list.txt", "r");
	
	// If the file couldn't be opened
	if (!$gene_list_file) {
		return false;
	}
	
	$validation_gene_list = array(); // Define an array to store the validation gene list
	$failed_genes = array(); // Define an array to store genes not present in the validation gene list
	
	while (($line = fgets($gene_list_file)) !== false) { // Go through the file line by line
		if (strlen($line) > 0) {
			$line = preg_replace("/[\n\r\s]/", "", $line); // Remove newline characters
			
			array_push($validation_gene_list, $line);
		}
	}
	
	fclose($gene_list_file);
	
	foreach ($gene_list as $query_gene) { // Go through each gene in the query gene list
		if (!in_array($query_gene, $validation_gene_list)) { // If the query gene is not in the validation list, push it to a failed genes array
			array_push($failed_genes, $query_gene);
		}
	}

	return $failed_genes;
}

#############################################
# DETERMINE THE GENE LIST ID IN THE DB OF A GENE LIST STRING
#############################################

function determine_gene_list_id($gene_list_name) {
	// Determine the list id of the gene list name submitted
	$sql = "SELECT ";
		$sql .= "list_id ";
	$sql .= "FROM ";
		$sql .= "KCCG_GENE_LISTS.gene_lists ";
	$sql .= "WHERE ";
		$sql .= "list_name = ?";
	$sql .= ";";
	
	$statement = $GLOBALS["mysql_connection"]->prepare($sql);
	
	$statement->execute([$gene_list_name]);
	
	$rows_returned = $statement->rowCount();
	
	if ($rows_returned !== 1) {
		return false;
	}
	
	$row = $statement->fetch();
	
	return $row["list_id"];
}

#############################################
# DETERMINE THE GENE ID IN THE DB BASED ON GENE NAME STRING
#############################################

function determine_gene_id($gene_name) {
	// Determine the list of the of the gene list name submitted
	$sql = "SELECT ";
		$sql .= "gene_id ";
	$sql .= "FROM ";
		$sql .= "KCCG_GENE_LISTS.genes ";
	$sql .= "WHERE ";
		$sql .= "gene_name = ?";
	$sql .= ";";
	
	$statement = $GLOBALS["mysql_connection"]->prepare($sql);
	
	$statement->execute([$gene_name]);
	
	$rows_returned = $statement->rowCount();
	
	if ($rows_returned !== 1) {
		return false;
	}
	
	$row = $statement->fetch();
	
	return $row["gene_id"];
}

#############################################
# CHECK WHETHER A GENE IS IN A GENE LIST
#############################################

function is_gene_in_gene_list($gene_id, $gene_list_title) {
	$sql = "SELECT ";
		$sql .= "* ";
	$sql .= "FROM ";
		$sql .= "KCCG_GENE_LISTS.genes_in_lists ";
	$sql .= "WHERE ";
		$sql .= "gene_id = ? ";
	$sql .= "AND ";
		$sql .= "list_id = (SELECT list_id FROM KCCG_GENE_LISTS.gene_lists WHERE list_name = ?)";
	$sql .= ";";
	
	$statement = $GLOBALS["mysql_connection"]->prepare($sql);
	
	$statement->execute([$gene_id, $gene_list_title]);
	
	$rows_returned = $statement->rowCount();
	
	if ($rows_returned === 1) {
		return true;
	} else {
		return false;
	}
}

#############################################
# RETURN ALL GENES FOR A GIVEN GENE LIST NAME
#############################################

function return_list_of_genes_using_list_name($gene_list_title) {
	// Define arrays to hold the results
	$gene_list["gene"] = array();
	$gene_list["time"] = array();
	
	// Fetch the gene list id
	$gene_list_id = determine_gene_list_id($gene_list_title);
	
	if ($gene_list_id === false) {
		return false;
	}
	
	$sql = "SELECT ";
		$sql .= "KCCG_GENE_LISTS.genes.gene_name, ";
		$sql .= "KCCG_GENE_LISTS.genes_in_lists.date_added ";
	$sql .= "FROM ";
		$sql .= "KCCG_GENE_LISTS.genes ";
	$sql .= "INNER JOIN KCCG_GENE_LISTS.genes_in_lists ON KCCG_GENE_LISTS.genes.gene_id = KCCG_GENE_LISTS.genes_in_lists.gene_id ";
	$sql .= "WHERE ";
		$sql .= "KCCG_GENE_LISTS.genes.gene_id = ANY (SELECT KCCG_GENE_LISTS.genes_in_lists.gene_id FROM KCCG_GENE_LISTS.genes_in_lists WHERE KCCG_GENE_LISTS.genes_in_lists.list_id = ?) ";
	$sql .= "AND ";
		$sql .= "KCCG_GENE_LISTS.genes_in_lists.list_id = ?";
	$sql .= ";";
	
	$statement = $GLOBALS["mysql_connection"]->prepare($sql);
	
	$statement->execute([$gene_list_id, $gene_list_id]);
	
	$rows_returned = $statement->rowCount();
	
	if ($rows_returned === 0) {
		return $gene_list;
	} elseif ($rows_returned > 0) {
		while ($row = $statement->fetch()) {
			array_push($gene_list["gene"], $row["gene_name"]);
			array_push($gene_list["time"], $row["date_added"]);
		}
		
		return $gene_list;
	} else {
		return false;
	}
}

#############################################
# ADD A NEW GENE
#############################################

function add_new_gene_to_db_gene_list($gene_name) {
	$sql = "INSERT INTO ";
		$sql .= "KCCG_GENE_LISTS.genes (gene_name, date_added) ";
	$sql .= "VALUES ";
		$sql .= "(?, now())";
	$sql .= ";";
	
	$statement = $GLOBALS["mysql_connection"]->prepare($sql);
	
	$statement->execute([$gene_name]);
	
	$rows_returned = $statement->rowCount();
	
	if ($rows_returned !== 1) {
		return false;
	}
	
	// Return the inserted ID
	return $GLOBALS["mysql_connection"]->lastInsertId();
}

#############################################
# ADD GENE TO GENE LIST
#############################################

function add_gene_to_gene_list($gene_id, $list_id) {
	$sql = "INSERT INTO ";
		$sql .= "KCCG_GENE_LISTS.genes_in_lists (gene_id, list_id, date_added) ";
	$sql .= "VALUES ";
		$sql .= "(?, ?, now())";
	$sql .= ";";
	
	$statement = $GLOBALS["mysql_connection"]->prepare($sql);
	
	$statement->execute([$gene_id, $list_id]);
	
	$rows_returned = $statement->rowCount();
	
	if ($rows_returned === 1) {
		return true;
	} else {
		return false;
	}
}

#############################################
# REMOVE GENE FROM GENE LIST
#############################################

function remove_gene_from_gene_list($gene_id, $list_id) {
	$sql = "DELETE FROM ";
		$sql .= "KCCG_GENE_LISTS.genes_in_lists ";
	$sql .= "WHERE ";
		$sql .= "gene_id = ? ";
	$sql .= "AND ";
		$sql .= "list_id = ?";
	$sql .= ";";
	
	$statement = $GLOBALS["mysql_connection"]->prepare($sql);
	
	$statement->execute([$gene_id, $list_id]);
	
	$rows_returned = $statement->rowCount();
	
	if ($rows_returned === 1) {
		return true;
	} else {
		return false;
	}				
}

#############################################
# ADD GENE LIST
#############################################

function add_gene_list($list_name) {
	$sql = "INSERT INTO ";
		$sql .= "KCCG_GENE_LISTS.gene_lists (list_name, date_added) ";
	$sql .= "VALUES ";
		$sql .= "(?, now())";
	$sql .= ";";
	
	$statement = $GLOBALS["mysql_connection"]->prepare($sql);
	
	$statement->execute([$list_name]);
	
	$rows_returned = $statement->rowCount();
	
	if ($rows_returned === 1) {
		return true;
	} else {
		return false;
	}
}

#############################################
# REMOVE GENE LIST
#############################################

function remove_gene_list($list_id) {
	// Delete all genes in the list
	$sql = "DELETE FROM ";
		$sql .= "KCCG_GENE_LISTS.genes_in_lists ";
	$sql .= "WHERE ";
		$sql .= "list_id = ?";
	$sql .= ";";
	
	$statement = $GLOBALS["mysql_connection"]->prepare($sql);
	
	$statement->execute([$list_id]);
	
	// Delete the list itself
	$sql = "DELETE FROM ";
		$sql .= "KCCG_GENE_LISTS.gene_lists ";
	$sql .= "WHERE ";
		$sql .= "list_id = ?";
	$sql .= ";";
	
	$statement = $GLOBALS["mysql_connection"]->prepare($sql);
	
	$statement->execute([$list_id]);
	
	$rows_returned = $statement->rowCount();
	
	if ($rows_returned === 1) {
		return true;
	} else {
		return false;
	}
}

#############################################
# RENAME GENE LIST
#############################################

function rename_gene_list($list_id, $new_list_name) {
	$sql = "UPDATE ";
		$sql .= "KCCG_GENE_LISTS.gene_lists ";
	$sql .= "SET ";
		$sql .= "list_name = ? ";
	$sql .= "WHERE ";
		$sql .= "list_id = ?";
	$sql .= ";";
	
	$statement = $GLOBALS["mysql_connection"]->prepare($sql);
	
	$statement->execute([$new_list_name, $list_id]);
	
	$rows_returned = $statement->rowCount();
	
	if ($rows_returned === 1) {
		return true;
	} else {
		return false;
	}
}

?>
