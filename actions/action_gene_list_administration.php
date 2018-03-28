<?php

	require basename("..").'/php_header.php'; // Require the PHP header housing required PHP functions

	#############################################
	# CHECK THAT THE USER HAS ACCESS
	#############################################
	
	if (!is_user_administrator()) {
		gene_list_admin_redirect("no_access");
	}
	
	#############################################
	# FETCH GENE LISTS
	#############################################
	
	$gene_lists = fetch_gene_lists();
	
	if ($gene_lists === false) {
		gene_list_admin_redirect("cant_fetch_gene_lists");
	}
	
    #############################################
	# PROCESS SUBMITTED FORMS
	#############################################
		
	#############################################
	# Add gene(s) to gene list(s)
	#############################################
	
	if (isset($_POST["genes_to_add"])) {
		// Check that one or more gene lists were selected
		if (!isset($_POST["add_to_gene_list"]) || !is_array($_POST["add_to_gene_list"])) {
			gene_list_admin_redirect("add_genes_no_lists_selected");
		}
		
		// Validate the gene lists selected and fetch their ids
		foreach ($_POST["add_to_gene_list"] as $gene_list_title) {
			if ($gene_list_id = determine_gene_list_id($gene_list_title)) {
				$gene_list_ids[$gene_list_title] = $gene_list_id;
			} else {
				gene_list_admin_redirect("add_genes_cant_find_gene_list_id");
			}
		}
		
		// Determine a list of genes to add while removing duplicates and invalid genes
		$gene_list = determine_gene_list("", $_POST["genes_to_add"], ""); // Array of genes, will have 0 elements if no genes submitted
		
		// Only try to validate a gene list if query genes exist
		if ($gene_list === false || count($gene_list) == 0) {
			gene_list_admin_redirect("add_genes_no_genes");
		}
		
		// Generate a list of genes failing validation
		$failed_genes = validate_gene_list($gene_list);
		
		if ($failed_genes === false) {
			gene_list_admin_redirect("add_genes_problem_validating_genes");
		// If genes not present in the validation list were found, print errors for each of them and delete them from the array of genes to add
		} elseif (count($failed_genes) > 0) {
			log_page_error("At least one gene you want to add is not present in the Ensembl 75 list and therefore cannot be annotated in a database. Below is a list of all genes failing this validation:<br>");
			
			foreach ($failed_genes as $failed_gene) {
				log_page_error($failed_gene." ");
				
				$key = array_search($failed_gene, $gene_list); // Find the array key of the gene to be deleted
				
				// Remove the failed gene from the array of genes to add
				if ($key !== false) {
				    unset($gene_list[$key]);
				}
			}
			
			log_page_error("<br>");
			
			// Reindex the array
			$gene_list = array_values($gene_list);
		}
		
		// Make sure there are still genes to add after the validation and if so proceed to add them
		if (count($gene_list) == 0) {
			gene_list_admin_redirect("add_genes_all_failed_validation");
		}
		
		// Variable to track the total number of genes added to lists (each connection counts as an addition)
		$successful_addition = 0;
		
		// Go through each validated gene to add
		foreach ($gene_list as $gene_to_add) {
			// Check whether the gene is already in the genes table, if so grab its ID
			$gene_id = determine_gene_id($gene_to_add);
			
			// If the gene is not in the genes table yet, add it and capture the new gene id
			if ($gene_id === false) {
				$gene_id = add_new_gene_to_db_gene_list($gene_to_add);
				
				// Make sure the gene was successfully added and an ID was returned
				if ($gene_id === false) {
					gene_list_admin_redirect("add_genes_could_not_add_gene");
				}
			}

			// Add a connection of the gene with the gene lists if it doesn't already exist
			foreach ($_POST["add_to_gene_list"] as $gene_list_to_add_to) {
				// Check whether the gene is already in the gene list
				if (is_gene_in_gene_list($gene_id, $gene_list_to_add_to)) {
					if (isset($existing_genes)) {
						$existing_genes .= $gene_to_add." (".$gene_list_to_add_to.") ";
					} else {
						$existing_genes = "The following genes are already present in a list you selected to add them to: ".$gene_to_add." (".$gene_list_to_add_to.") ";
					}
				// If the gene is not already in the gene list, add it
				} else {
					if (add_gene_to_gene_list($gene_id, $gene_list_ids[$gene_list_to_add_to])) {
						$successful_addition++;
					} else {
						gene_list_admin_redirect("add_genes_could_not_add_gene");
					}
				}
			}
		}
		
		// If one or more of the genes is already present in one or more of the lists it was selected to add to
		if (isset($existing_genes)) {
			log_page_error($existing_genes."<br>");
		}
		
		// If at least one gene was inserted
		if ($successful_addition > 0) {
			log_website_event("Added gene(s) to gene list(s), gene(s) '".$_POST["genes_to_add"]."' to list(s) '".implode(", ", $_POST["add_to_gene_list"])."'");
			
			gene_list_admin_redirect("add_genes_success", $successful_addition);
		} else {
			gene_list_admin_redirect("add_genes_no_genes_added");
		}
	}
	
	#############################################
	# Delete gene(s) from gene list(s)
	#############################################
	
	if (isset($_POST["genes_to_delete"])) {
		// Check that one or more gene lists were selected
		if (!isset($_POST["delete_from_gene_list"]) || !is_array($_POST["delete_from_gene_list"])) {
			gene_list_admin_redirect("delete_genes_no_lists_selected");
		}
		
		// Validate the gene lists selected and fetch their ids
		foreach ($_POST["delete_from_gene_list"] as $gene_list_title) {
			if ($gene_list_id = determine_gene_list_id($gene_list_title)) {
				$gene_list_ids[$gene_list_title] = $gene_list_id;
			} else {
				gene_list_admin_redirect("delete_genes_cant_find_gene_list_id");
			}
		}
		
		// Determine a list of genes to delete while removing duplicates and invalid genes
		$gene_list = determine_gene_list("", $_POST["genes_to_delete"], ""); // Array of genes, will have 0 elements if no genes submitted
		
		// Only try to validate a gene list if query genes exist
		if ($gene_list === false || count($gene_list) == 0) {
			gene_list_admin_redirect("delete_genes_no_genes");
		}

		// Go through each gene
		foreach ($gene_list as $gene_name) {
			// Try to find the gene id of the current gene, if found then save it
			$gene_id = determine_gene_id($gene_name);
			
			if ($gene_id === false) {
				// Log genes not in Seave to display to the user
				if (isset($missing_genes)) {
					$missing_genes .= $gene_name." ";
				} else {
					$missing_genes = "The following genes are not present in Seave: ".$gene_name." ";
				}
				
				$key = array_search($gene_name, $gene_list); // Find the array key of the gene to be deleted
				
				// Remove the failed gene from the array of genes to delete
				if ($key !== false) {
				    unset($gene_list[$key]);
				}
			} else {
				$gene_ids[$gene_name] = $gene_id;
			}
		}
		
		// Reindex the array
		$gene_list = array_values($gene_list);
		
		// If genes not present in the db were found
		if (isset($missing_genes)) {
			log_page_error($missing_genes."<br>");
			
			unset($missing_genes);
		}
		
		// Make sure there are still genes to delete after the validation and if so proceed to delete them from the lists specified
		if (count($gene_list) == 0) {
			gene_list_admin_redirect("delete_genes_all_failed_validation");
		}
			
		// Variable to track the total number of genes deleted from lists (each connection deleted counts as a deletion)
		$successful_deletion = 0;
		
		// Go through every gene id to delete
		foreach ($gene_list as $gene_name) {
			// Go through every gene list id to delete from
			foreach (array_keys($gene_list_ids) as $gene_list_title) {
				// Check whether the current gene is in the current gene list
				if (is_gene_in_gene_list($gene_ids[$gene_name], $gene_list_title)) {
					if (remove_gene_from_gene_list($gene_ids[$gene_name], $gene_list_ids[$gene_list_title])) {
						$successful_deletion++;
					} else {
						log_page_error("Could not delete gene ".$gene_name." from the gene list \"".$gene_list_title."\". ");
					}
				} else {
					if (isset($missing_genes)) {
						$missing_genes .= $gene_name." ";
					} else {
						$missing_genes = "The following genes are not in one or more of the gene lists selected: ".$gene_name." ";
					}
				}
			}
		}
		
		// If some of the genes specified did not belong to one or more of the gene lists
		if (isset($missing_genes)) {
			log_page_error($missing_genes."<br>");
			
			unset($missing_genes);
		}
		
		// If at least one gene was deleted
		if ($successful_deletion > 0) {
			log_website_event("Deleted gene(s) from gene list(s), gene(s) '".$_POST["genes_to_delete"]."' to list(s) '".implode(", ", $_POST["delete_from_gene_list"])."'");
			
			gene_list_admin_redirect("delete_genes_success", $successful_deletion);
		} else {
			gene_list_admin_redirect("delete_genes_no_genes_deleted");
		}
	}
	
	#############################################
	# Add new gene list
	#############################################
	
	if (isset($_GET["add_gene_list_name"])) {
		// Check if no gene list name was submitted
		if (strlen($_GET["add_gene_list_name"]) == 0) {
			gene_list_admin_redirect("add_gene_list_empty");
		}
		
		// Check whether the gene list already exists
		if (in_array($_GET["add_gene_list_name"], array_keys($gene_lists))) {
			gene_list_admin_redirect("add_gene_list_already_exists");
		}
		
		// Insert the gene list into the gene list store
		if (add_gene_list($_GET["add_gene_list_name"])) {
			log_website_event("Added new gene list '".$_GET["add_gene_list_name"]."'");

			gene_list_admin_redirect("add_gene_list_success", $_GET["add_gene_list_name"]);
		} else {
			gene_list_admin_redirect("add_gene_list_cant_add_gene_list");
		}
	}
	
	#############################################
	# Delete gene list
	#############################################
	
	if (isset($_GET["delete_gene_list"])) {
		// Fetch the gene list id of the gene list to delete
		$gene_list_id = determine_gene_list_id($_GET["delete_gene_list"]);
		
		if ($gene_list_id === false) {
			gene_list_admin_redirect("delete_gene_list_no_such_list");
		}
		
		// Delete the genes from the list and then the list itself
		if (!remove_gene_list($gene_list_id)) {
			gene_list_admin_redirect("delete_gene_list_cant_delete_gene_list");
		} else {
			log_website_event("Deleted gene list '".$_GET["delete_gene_list"]."' gene list ID '".$gene_list_id."'");
			
			gene_list_admin_redirect("delete_gene_list_success", $_GET["delete_gene_list"]);
		}
	}
	
	#############################################
	# Rename gene list
	#############################################
	
	if (isset($_GET["rename_gene_list"], $_GET["new_gene_list_name"])) {
		// Check if no new gene list name was submitted
		if (strlen($_GET["new_gene_list_name"]) == 0) {
			gene_list_admin_redirect("rename_gene_list_empty");
		}
		
		// Check whether the new gene list name already exists
		if (in_array($_GET["new_gene_list_name"], array_keys($gene_lists))) {
			gene_list_admin_redirect("rename_gene_list_already_exists");
		}
		
		// Check whether the gene list name to rename exists
		if (!in_array($_GET["rename_gene_list"], array_keys($gene_lists))) {
			gene_list_admin_redirect("rename_gene_list_old_list_doesnt_exist");
		}
		
		// Fetch the gene list ID
		$gene_list_id = determine_gene_list_id($_GET["rename_gene_list"]);
		
		if ($gene_list_id === false) {
			gene_list_admin_redirect("rename_gene_list_cant_rename_gene_list");
		}
		
		// Rename the gene list
		if (!rename_gene_list($gene_list_id, $_GET["new_gene_list_name"])) {
			gene_list_admin_redirect("rename_gene_list_cant_rename_gene_list");
		} else {
			log_website_event("Renamed gene list '".$_GET["rename_gene_list"]."' to '".$_GET["new_gene_list_name"]."', gene list ID '".$gene_list_id."'");

			gene_list_admin_redirect("rename_gene_list_success", $_GET["rename_gene_list"]." to ".$_GET["new_gene_list_name"]);
		}
	}
    
	#############################################
		
	// Redirect to the gene list admin page if no other redirect has been applied
	gene_list_admin_redirect();
						
	#############################################
	# PAGE FUNCTIONS
	#############################################
	
	// Function to redirect to DB administration page
	function gene_list_admin_redirect($session_variable_name = NULL, $session_variable_value = NULL) {
		if (isset($session_variable_name) && isset($session_variable_value)) {
			$_SESSION["gene_list_admin_".$session_variable_name] = $session_variable_value;
		} elseif (isset($session_variable_name)) {
			$_SESSION["gene_list_admin_".$session_variable_name] = 1;
		}

		header("Location: ".basename("..")."/gene_list_administration");
			
		exit;
	}
	
	// Function to log an error without a redirect to the gene list admin page
	function log_page_error($error_description) {
		if (isset($_SESSION["gene_list_admin_error"])) {
			$_SESSION["gene_list_admin_error"] .= $error_description;
		} else {
			$_SESSION["gene_list_admin_error"] = $error_description;
		}
	}

?>