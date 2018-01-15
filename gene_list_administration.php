<?php	
	$body_class = "no-sidebar"; // This page does not have a sidebar
	
	require 'php_header.php'; // Require the PHP header housing required PHP functions
	require 'html_header.php'; // Require the HTML header housing the HTML structure of the page
?>

</div>

<!-- Main -->
<div class="wrapper">
	<div class="container" id="main">

		<!-- Content -->
		<article id="content">
			<header>
				<h2>Need to make some changes?</h2>
				<p>The gene list administration options below let you add, remove and modify genes in gene lists to your heart's content.</p>
			</header>
			<?php
				// If the user is an administrator
				if (!is_user_administrator()) {
					error("You do not have permission to view this page.");
					
					echo "<a href=\"home\" class=\"button\">Home</a>";
				} else {
					// Fetch list of gene lists
					$gene_lists = fetch_gene_lists();
					
					if ($gene_lists === false) {
						error("Could not fetch gene lists.");
					} else {

						#############################################
						# SUCCESS/ERROR MESSAGES
						#############################################
						
						// If a non-fatal error occurred that the user needs to be shown, show it
						if (isset($_SESSION["gene_list_admin_error"])) {
							error($_SESSION["gene_list_admin_error"]);
							
							unset($_SESSION["gene_list_admin_error"]);
						}
	
						if_set_display_error("gene_list_admin_cant_fetch_gene_lists", "Couldn't determine existing gene lists, please try again.");
											
						if_set_display_error("gene_list_admin_add_genes_no_lists_selected", "You must select one or more gene lists to add your gene(s) to.");
						if_set_display_error("gene_list_admin_add_genes_cant_find_gene_list_id", "Could not find the gene list id for one of gene lists you selected.");
						if_set_display_error("gene_list_admin_add_genes_no_genes", "You did not submit any genes to add to the gene list(s).");
						if_set_display_error("gene_list_admin_add_genes_all_failed_validation", "All of the genes you submitted failed validation so no changes were made.");
						if_set_display_error("gene_list_admin_add_genes_could_not_add_gene", "There was a problem adding one or more genes to the database.");
						if_set_display_error("gene_list_admin_add_genes_no_genes_added", "No genes were added to any lists.");
						if_set_display_error("gene_list_admin_add_genes_problem_validating_genes", "There was a problem validating genes entered.");
						
						if_set_display_error("gene_list_admin_delete_genes_no_lists_selected", "You must select one or more gene lists to delete your gene(s) from.");
						if_set_display_error("gene_list_admin_delete_genes_cant_find_gene_list_id", "Could not find the gene list id for one of the gene lists you specified.");
						if_set_display_error("gene_list_admin_delete_genes_no_genes", "You did not submit any genes to add to the gene list(s).");
						if_set_display_error("gene_list_admin_delete_genes_all_failed_validation", "All of the genes you submitted failed validation so no changes were made.");
						if_set_display_error("gene_list_admin_delete_genes_no_genes_deleted", "Could not delete any of the genes you entered from any of the gene lists you selected.");
						
						if_set_display_error("gene_list_admin_add_gene_list_empty", "The gene list name you submitted is empty.");
						if_set_display_error("gene_list_admin_add_gene_list_already_exists", "The new gene list name you submitted already exists.");
						if_set_display_error("gene_list_admin_add_gene_list_cant_add_gene_list", "Could not add your gene list to the database.");
						
						if_set_display_error("gene_list_admin_delete_gene_list_no_such_list", "The gene list you submitted does not exist.");
						if_set_display_error("gene_list_admin_delete_gene_list_cant_delete_gene_list", "There was a problem removing the gene list.");
						
						if_set_display_error("gene_list_admin_rename_gene_list_empty", "The new gene list name you submitted is empty.");
						if_set_display_error("gene_list_admin_rename_gene_list_already_exists", "The new gene list name you submitted already exists.");
						if_set_display_error("gene_list_admin_rename_gene_list_old_list_doesnt_exist", "The gene list you specified to rename does not exist.");
						if_set_display_error("gene_list_admin_rename_gene_list_cant_rename_gene_list", "Could not rename your gene list in the database.");
						
						if (isset($_SESSION["gene_list_admin_add_genes_success"])) { if_set_display_success("gene_list_admin_add_genes_success", "Successfully made a total of ".$_SESSION["gene_list_admin_add_genes_success"]." connections between genes and lists."); }
						if (isset($_SESSION["gene_list_admin_delete_genes_success"])) { if_set_display_success("gene_list_admin_delete_genes_success", "Successfully deleted a total of ".$_SESSION["gene_list_admin_delete_genes_success"]." connections between genes and lists."); }
						if (isset($_SESSION["gene_list_admin_add_gene_list_success"])) { if_set_display_success("gene_list_admin_add_gene_list_success", "Successfully added your gene list ".$_SESSION["gene_list_admin_add_gene_list_success"]."."); }
						if (isset($_SESSION["gene_list_admin_delete_gene_list_success"])) { if_set_display_success("gene_list_admin_delete_gene_list_success", "Successfully deleted your gene list ".$_SESSION["gene_list_admin_delete_gene_list_success"]."."); }
						if (isset($_SESSION["gene_list_admin_rename_gene_list_success"])) { if_set_display_success("gene_list_admin_rename_gene_list_success", "Successfully renamed gene list ".$_SESSION["gene_list_admin_rename_gene_list_success"]."."); }

						#############################################
						# VIEW LIST OF GENES
						#############################################
						
						if (isset($_GET["view_genes_in_gene_list"])) {
							success("Showing genes for list \"".$_GET["view_genes_in_gene_list"]."\"");
							
							list_of_genes_table($_GET["view_genes_in_gene_list"]);
						}
						
						#############################################
						# GENE LIST MANAGEMENT FORMS
						#############################################
						
						#############################################
						# Add gene(s) to list(s) form
						#############################################
						
						echo "<div class=\"row\">";
							echo "<section class=\"6u 12u(narrower)\">";
								echo "<h2>Add gene(s) to list(s)</h2>";	
								
								echo "<p>Select one or more gene lists for addition then type the genes you would like to add separated by semicolons below. Your list of genes will be validated against Ensembl 75 to make sure all genes you input can be queried in Seave.</p>";
	
								echo "<form action=\"actions/action_gene_list_administration\" method=\"post\">";
									echo "<div class=\"row 50%\">";
										echo "<div class=\"12u\">";
											echo "<select name=\"add_to_gene_list[]\" size=\"10\" multiple=\"multiple\">";
												foreach (array_keys($gene_lists) as $gene_list) { # Go through every gene list
													echo "<option value=\"".$gene_list."\">".$gene_list." (".$gene_lists[$gene_list].")</option>";
												}
											echo "</select>";
										echo "</div>";
										
										echo "<div class=\"12u\">";
											echo "<input type=\"text\" name=\"genes_to_add\" placeholder=\"e.g. BRCA1;PIK3CA;TP53\">";
										echo "</div>";
									echo "</div>";
									echo "<div class=\"row 50%\">";
										echo "<div class=\"12u\">";
											echo "<ul class=\"actions\">";
												echo "<li><input type=\"submit\" value=\"Add gene(s)\" /></li>";
											echo "</ul>";
										echo "</div>";
									echo "</div>";
								echo "</form>";
							echo "</section>";
							
							#############################################
							# Delete gene(s) from list(s)
							#############################################
							
							echo "<section class=\"6u 12u(narrower)\">";
								echo "<h2>Delete gene(s) from list(s)</h2>";	
								
								echo "<p>Select one or more gene lists for deletion then type the genes you would like to delete separated by semicolons below. Seave will make sure the genes you enter exist and then remove them from all selected lists if they are present.</p>";
	
								echo "<form action=\"actions/action_gene_list_administration\" method=\"post\">";
									echo "<div class=\"row 50%\">";
										echo "<div class=\"12u\">";
											echo "<select name=\"delete_from_gene_list[]\" size=\"10\" multiple=\"multiple\">";
												foreach (array_keys($gene_lists) as $gene_list) { # Go through every gene list
													echo "<option value=\"".$gene_list."\">".$gene_list." (".$gene_lists[$gene_list].")</option>";
												}
											echo "</select>";
										echo "</div>";
											
										echo "<div class=\"12u\">";
											echo "<input type=\"text\" name=\"genes_to_delete\" placeholder=\"e.g. BRCA1;PIK3CA;TP53\">";
										echo "</div>";
									echo "</div>";
									echo "<div class=\"row 50%\">";
										echo "<div class=\"12u\">";
											echo "<ul class=\"actions\">";
												echo "<li><input type=\"submit\" value=\"Delete gene(s)\" /></li>";
											echo "</ul>";
										echo "</div>";
									echo "</div>";
								echo "</form>";
							echo "</section>";
						echo "</div>";
	
						#############################################
						# Add gene list
						#############################################
						
						echo "<div class=\"row\">";
							echo "<section class=\"6u 12u(narrower)\">";
							
								echo "<h2 style=\"padding-top:10px;\">Add gene list</h2>";
								
								echo "<p>Type in a new gene list name in the box below. Your new gene list name cannot already exist in Seave.</p>";
								
								echo "<form action=\"actions/action_gene_list_administration\" method=\"get\">";
									echo "<div class=\"row 50%\">";
										echo "<div class=\"12u\">";
											echo "<input type=\"text\" name=\"add_gene_list_name\" placeholder=\"Gene list name\">";
										echo "</div>";
									echo "</div>";
									echo "<div class=\"row 50%\">";
										echo "<div class=\"12u\">";
											echo "<ul class=\"actions\">";
												echo "<li><input type=\"submit\" value=\"Add gene list\"></li>";
											echo "</ul>";
										echo "</div>";
									echo "</div>";
								echo "</form>";
							echo "</section>";
							
							#############################################
							# Delete gene list
							#############################################
						
							echo "<section class=\"6u 12u(narrower)\">";
								
								echo "<h2 style=\"padding-top:10px;\">Delete gene list</h2>";
								
								echo "<p>Select a gene list to delete from the dropdown below.</p>";
								
								echo "<form action=\"actions/action_gene_list_administration\" method=\"get\">";
									echo "<div class=\"row 50%\">";
										echo "<div class=\"12u\">";
											echo "<select name=\"delete_gene_list\">";
												foreach (array_keys($gene_lists) as $gene_list) { # Go through every gene list
													echo "<option value=\"".$gene_list."\">".$gene_list." (".$gene_lists[$gene_list].")</option>";
												}
											echo "</select>";
										echo "</div>";
									echo "</div>";
									
									echo "<p style=\"color: red;\">Warning: once a gene list is deleted it cannot be restored.</p>";
									
									echo "<div class=\"row 50%\">";
										echo "<div class=\"12u\">";
											echo "<ul class=\"actions\">";
												echo "<li><input type=\"submit\" value=\"Delete gene list\"></li>";
											echo "</ul>";
										echo "</div>";
									echo "</div>";
								echo "</form>";
							echo "</section>";
							
						echo "</div>";
						
						#############################################
						# Rename gene list
						#############################################
						
						echo "<div class=\"row\">";
							echo "<section class=\"6u 12u(narrower)\">";
							
								echo "<h2 style=\"padding-top:10px;\">Rename gene list</h2>";
								
								echo "<p>Select a gene list you would like to rename from the dropdown then type the new name for the list in the box below. Your new list name cannot already exist in Seave.</p>";
								
								echo "<form action=\"actions/action_gene_list_administration\" method=\"get\">";
									echo "<div class=\"row 50%\">";
										echo "<div class=\"12u\">";
											echo "<select name=\"rename_gene_list\">";
												foreach (array_keys($gene_lists) as $gene_list) { # Go through every gene list
													echo "<option value=\"".$gene_list."\">".$gene_list." (".$gene_lists[$gene_list].")</option>";
												}
											echo "</select>";
										echo "</div>";
										
										echo "<div class=\"12u\">";
											echo "<input type=\"text\" name=\"new_gene_list_name\" placeholder=\"New gene list name\">";
										echo "</div>";
									echo "</div>";
									
									echo "<div class=\"row 50%\">";
										echo "<div class=\"12u\">";
											echo "<ul class=\"actions\">";
												echo "<li><input type=\"submit\" value=\"Rename gene list\"></li>";
											echo "</ul>";
										echo "</div>";
									echo "</div>";
								echo "</form>";
							echo "</section>";
							
							#############################################
							# View gene list
							#############################################
							
							echo "<section class=\"6u 12u(narrower)\">";
								
								echo "<h2 style=\"padding-top:10px;\">View genes in gene list</h2>";
								
								echo "<p>Select a gene list to view the genes for from the dropdown below.</p>";
								
								echo "<form action=\"gene_list_administration\" method=\"get\">";
									echo "<div class=\"row 50%\">";
										echo "<div class=\"12u\">";
											echo "<select name=\"view_genes_in_gene_list\">";
												foreach (array_keys($gene_lists) as $gene_list) { # Go through every gene list
													echo "<option value=\"".$gene_list."\">".$gene_list." (".$gene_lists[$gene_list].")</option>";
												}
											echo "</select>";
										echo "</div>";
									echo "</div>";
									
									echo "<div class=\"row 50%\">";
										echo "<div class=\"12u\">";
											echo "<ul class=\"actions\">";
												echo "<li><input type=\"submit\" value=\"View genes\"></li>";
											echo "</ul>";
										echo "</div>";
									echo "</div>";
								echo "</form>";
							echo "</section>";
						echo "</div>";
					}
				}
			?>
		</article>
		
	</div>
</div>

<?php
	require 'html_footer.php';
?>