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
				<h2>GBS Administration</h2>
				<p>The GBS administration options below let you make changes to the content of the GBS. Changes made will affect all user accounts.</p>
			</header>
			<?php

				#############################################
				# CHECK ADMIN STATUS
				#############################################

				// If the user is not logged in as an administrator
				if (!is_user_administrator()) {
					error("You do not have permission to view this page.");
				} else {
					
					#############################################
					# FETCH SAMPLES AND METHODS
					#############################################
					
					$samples_and_methods = fetch_samples_and_methods_gbs();
					
					if ($samples_and_methods === false) {
						error("There was a problem fetching samples and methods in the GBS.");
					} else {
					
						#############################################
						# IF AN ERROR OCCURRED WHEN PARSING
						#############################################
			
						echo "<section class=\"12u 12u(narrower)\">";
							# General errors applicable to all imports
							if_set_display_error("gbs_import_no_valid_data", "The data you submitted did not have any valid genomic blocks. Please check your output file to make sure it has data in the expected format.");					
							if_set_display_error("gbs_import_missing_posts", "You did not submit all necessary information for importing genome blocks.");
							if_set_display_error("gbs_import_missing_file", "No file of genome blocks was uploaded for importation.");
							if_set_display_error("gbs_import_invalid_method", "The genome block method specified is invalid.");
							if_set_display_error("gbs_import_missing_sample_name", "You did not specify any sample names for which to add genome blocks.");
							if_set_display_error("gbs_import_cant_open_genome_blocks_file", "There was a problem opening your genome blocks file for reading.");
							if_set_display_error("gbs_import_import_data_already_exists", "You already have imported data saved, either accept or decline this data before trying to import more.");
							if_set_display_error("gbs_import_gbs_data_already_exists", "The GBS already has data for the sample and method you submitted. Delete this before importing an updated set.");
							if_set_display_error("gbs_import_cant_tell_if_gbs_data_already_exists", "Problem determining if the GBS already contains data for the sample(s) and method you submitted.");
							if_set_display_error("gbs_import_problem_parsing", "There was a problem parsing your input file.");
							if_set_display_error("gbs_import_problem_parsing_samples", "There was a problem parsing samples in your input file.");				
							
							# CNVnator-specific errors
							if_set_display_error("gbs_import_invalid_cnvnator_output_file_format", "You must upload the BED file output from CNVnator.");
							
							# Sequenza-specific errors
							if_set_display_error("gbs_import_invalid_sequenza_output_file_format", "You must upload the output file from Sequenza ending with \"segments.txt\".");
							
							# ROHmer-specific errors
							if_set_display_error("gbs_import_invalid_rohmer_output_file_format", "You must upload the BED file output from ROHmer.");
							
							# CNVkit-specific errors
							if_set_display_error("gbs_import_invalid_cnvkit_output_file_format", "You must upload the CNS file output from CNVkit.");
							
							# LUMPY-specific errors
							if_set_display_error("gbs_import_invalid_lumpy_output_file_format", "You must upload the VCF file output from LUMPY.");

							# VarpipeSV-specific errors
							if_set_display_error("gbs_import_invalid_varpipesv_output_file_format", "You must upload the VCF file output from VarpipeSV.");

							# Manta-specific errors
							if_set_display_error("gbs_import_invalid_manta_output_file_format", "You must upload the *somaticSV.vcf file output from Manta.");
							
							if (isset($_SESSION["gbs_import_error"])) {
								if_set_display_error("gbs_import_error", $_SESSION["gbs_import_error"]);
							}
						echo "</section>";
			
						#############################################
						# IF AN ERROR OCCURRED WHEN STORING
						#############################################
						
						echo "<section class=\"12u 12u(narrower)\">";
							if_set_display_error("gbs_store_no_genomic_blocks", "You have not imported any genomic blocks.");
							if_set_display_error("gbs_store_cant_add_sample", "There was a problem adding a sample to the GBS.");
							if_set_display_error("gbs_store_cant_add_chromosome", "There was a problem adding a chromosome to the GBS.");
							if_set_display_error("gbs_store_cant_add_annotation_tag", "There was a problem adding an annotation tag to the GBS.");
							if_set_display_error("gbs_store_problem_adding_block", "There was a problem adding a genomic block to the GBS.");
		
							if_set_display_success("gbs_store_success", "Successfully imported your data into the GBS.");
						echo "</section>";
						
						#############################################
						# IF AN ERROR OCCURRED WHEN DELETING
						#############################################
						
						echo "<section class=\"12u 12u(narrower)\">";
							if_set_display_error("gbs_delete_no_posts", "You must select a sample and method to delete.");
							if_set_display_error("gbs_delete_fail", "There was a problem deleting the blocks from the GBS.");
							
							if_set_display_success("gbs_delete_success", "Successfully deleted the genomic blocks from the GBS.");
						echo "</section>";
						
						#############################################
						# IF GENOMIC BLOCKS HAVE BEEN SUCCESSFULLY PARSED
						#############################################
						
						if (isset($_SESSION["gbs_import_genome_blocks"], $_SESSION["gbs_import_samples"])) {
							echo "<section class=\"12u 12u(narrower)\">";
								echo "<header><h2>Verify Imported Genomic Blocks</h2></header>";
								
								echo "<p>You have submitted genomic blocks for upload that have been successfully processed. Below you will find a table showing these blocks, please make sure the information looks correct before submitting it to be stored in the GBS.</p>";
																	
								// Print the genome blocks table
								if (!genome_blocks_table()) {
									error("Problem displaying your genomic blocks, please try again.");
								}
																	
								echo "<br><a href=\"actions/action_store_genome_blocks?confirm=true\" class=\"button\">This data is correct, import it</a> ";
								
								echo "<a href=\"actions/action_store_genome_blocks?confirm=false\" class=\"button\">This data is incorrect, start over</a>";
						
							echo "</section>";
			
						#############################################
						# GBS ADMINISTATION FORMS
						#############################################
			
						} else {
							echo "<div class=\"row\">";

								#############################################
								# Import genomic blocks
								#############################################

								echo "<section class=\"6u 12u(narrower)\">";					
									echo "<header><h2>Import genomic blocks</h2></header>";
									
									echo "<p>The GBS allows importing data for specific methods. First select the method then fill in any additional required data and finally select your data file.</p>";
								
									echo "<form action=\"actions/action_import_genome_blocks\" method=\"post\" enctype=\"multipart/form-data\">";
										echo "<h4>Select an importation method</h4>";
										
										echo "<input type=\"radio\" id=\"label_cnvnator\" name=\"method\" value=\"CNVnator\" onclick=\"javascript:showdiv('cnvnator');\" checked>";
										echo "<label for=\"label_cnvnator\">CNVnator</label>";
										echo "<input type=\"radio\" id=\"label_lumpy\" name=\"method\" value=\"LUMPY\" onclick=\"javascript:showdiv('lumpy');\">";
										echo "<label for=\"label_lumpy\">LUMPY</label>";
										echo "<input type=\"radio\" id=\"label_sequenza\" name=\"method\" value=\"Sequenza\" onclick=\"javascript:showdiv('sequenza');\">";
										echo "<label for=\"label_sequenza\">Sequenza</label>";
										echo "<input type=\"radio\" id=\"label_rohmer\" name=\"method\" value=\"ROHmer\" onclick=\"javascript:showdiv('rohmer');\">";
										echo "<label for=\"label_rohmer\">ROHmer</label>";
										echo "<input type=\"radio\" id=\"label_varpipesv\" name=\"method\" value=\"VarpipeSV\" onclick=\"javascript:showdiv('varpipesv');\">";
										echo "<label for=\"label_varpipesv\">VarpipeSV</label>";
										echo "<input type=\"radio\" id=\"label_manta\" name=\"method\" value=\"Manta\" onclick=\"javascript:showdiv('manta');\">";
										echo "<label for=\"label_manta\">Manta</label>";
										echo "<input type=\"radio\" id=\"label_cnvkit\" name=\"method\" value=\"CNVkit\" onclick=\"javascript:showdiv('cnvkit');\">";
										echo "<label for=\"label_cnvkit\">CNVkit</label>";
										echo "<br><br>";
										
										echo "<h4>Sample affected by genomic blocks</h4>";
										echo "<div class=\"row\">";
											echo "<div class=\"8u\">";
												echo "<div class=\"selection\" id=\"cnvnator\">";
													echo "<input type=\"text\" name=\"import_sample_cnvnator\">";
													echo "<p style=\"font-size:75%;\">Enter the sample name CNVnator was run on above.</p>";
												echo "</div>";
												
												echo "<div class=\"selection\" id=\"sequenza\" style=\"display: none;\">";
													echo "<input type=\"text\" name=\"import_sample_sequenza\">";
													echo "<p style=\"font-size:75%;\">Enter the sample name Sequenza was run on above.</p>";
												echo "</div>";
												
												echo "<div class=\"selection\" id=\"rohmer\" style=\"display: none;\">";
													echo "<input type=\"text\" name=\"import_sample_rohmer\">";
													echo "<p style=\"font-size:75%;\">Enter the sample name ROHmer was run on above.</p>";
												echo "</div>";
												
												echo "<div class=\"selection\" id=\"varpipesv\" style=\"display: none;\">";
													echo "<p style=\"color:red; font-size:8pt;\">VarpipeSV contains sample names in the VCF output.</p>";
												echo "</div>";
												
												echo "<div class=\"selection\" id=\"manta\" style=\"display: none;\">";
													echo "<p style=\"color:red; font-size:8pt;\">Manta contains sample names in the VCF output.</p>";
												echo "</div>";
												
												echo "<div class=\"selection\" id=\"lumpy\" style=\"display: none;\">";
													echo "<p style=\"color:red; font-size:8pt;\">LUMPY contains sample names in the VCF output.</p>";
												echo "</div>";
												
												echo "<div class=\"selection\" id=\"cnvkit\" style=\"display: none;\">";
													echo "<input type=\"text\" name=\"import_sample_cnvkit\">";
													echo "<p style=\"font-size:75%;\">Enter the sample name CNVkit was run on above.</p>";
												echo "</div>";
											echo "</div>";
										echo "</div>";
										
										echo "<h4>Upload genomic blocks</h4>";
										echo "<div class=\"row\">";
											echo "<div class=\"8u\">";
												echo "<input type=\"file\" name=\"genomeblocks\" id=\"genomeblocks\">";
											echo "</div>";
										echo "</div>";
										
										echo "<div class=\"selection\" id=\"varpipesv\" style=\"display: none;\">";
											echo "<p style=\"font-size:75%;\">Select the .vcf file.</p>";
										echo "</div>";
										
										echo "<div class=\"selection\" id=\"rohmer\" style=\"display: none;\">";
											echo "<p style=\"font-size:75%;\">Select the .bed file.</p>";
										echo "</div>";
										
										echo "<div class=\"selection\" id=\"lumpy\" style=\"display: none;\">";
											echo "<p style=\"font-size:75%;\">Select the .vcf file.</p>";
										echo "</div>";
										
										echo "<div class=\"selection\" id=\"cnvnator\" style=\"display: none;\">";
											echo "<p style=\"font-size:75%;\">Select the .bed file.</p>";
										echo "</div>";
										
										echo "<div class=\"selection\" id=\"sequenza\" style=\"display: none;\">";
											echo "<p style=\"font-size:75%;\">Select the segments.txt file.</p>";
										echo "</div>";
										
										echo "<div class=\"selection\" id=\"manta\" style=\"display: none;\">";
											echo "<p style=\"font-size:75%;\">Select the somaticSV.vcf file.</p>";
										echo "</div>";
										
										echo "<div class=\"selection\" id=\"cnvkit\" style=\"display: none;\">";
											echo "<p style=\"font-size:75%;\">Select the .cns file.</p>";
										echo "</div>";
										
										echo "<div class=\"row\">";
											echo "<div class=\"12u\">";
												echo "<ul class=\"actions\">";
													echo "<li><input type=\"submit\" value=\"Import genomic blocks\"></li>";
												echo "</ul>";
											echo "</div>";
										echo "</div>";
									echo "</form>";
								echo "</section>";

								#############################################
								# Delete genomic blocks
								#############################################
																
								echo "<section class=\"6u 12u(narrower)\">";
									echo "<header><h2>Delete genomic blocks</h2></header>";	
									
									echo "<p>First select a sample name then a method for which the sample has data in the GBS.</p>";
		
									echo "<form name=\"delete_blocks\" action=\"actions/action_delete_genome_blocks\" method=\"post\">";
										echo "<div class=\"row 50%\">";
											echo "<div class=\"12u\">";
												echo "<h4>Samples with GBS data</h4>";
												echo "<select name=\"samples\" size=\"5\" onChange=\"updatemethods(this.selectedIndex)\">";
													// If samples and methods were successfully fetched
													if ($samples_and_methods !== false) {
														// Go through every sample in the GBS
														foreach (array_keys($samples_and_methods) as $sample) {
															echo "<option value=\"".$sample."\">".$sample."</option>";
														}
													}
												echo "</select>";
											echo "</div>";
											
											echo "<div class=\"12u\">";
												echo "<h4>Method(s) for sample selected</h4>";
												echo "<select name=\"methods\" size=\"5\"></select>"; // Populated by Javascript below
											echo "</div>";
										echo "</div>";
										
										echo "<div class=\"row 50%\">";
											echo "<div class=\"12u\">";
												echo "<ul class=\"actions\">";
													echo "<li><input type=\"submit\" value=\"Delete blocks\" /></li>";
												echo "</ul>";
											echo "</div>";
										echo "</div>";
									echo "</form>";
									
									// If samples and methods were successfully fetched
									if ($samples_and_methods !== false) {
										// Create required Javascript variables
										$javascripts .= "var sampleslist=document.delete_blocks.samples; ";
										$javascripts .= "var methodslist=document.delete_blocks.methods; ";
										$javascripts .= "var samples=new Array(); ";
										
										// Counter for the creating an index of the samples
										$sample_count = 0;
										
										// Create a Javascript array to populate the methods select box
										foreach (array_keys($samples_and_methods) as $sample) {
											$javascripts .= "samples[".$sample_count."]=[";
											
											foreach ($samples_and_methods[$sample] as $method) {
												$javascripts .= "\"".$method."\", ";
											}
											
											$javascripts = substr($javascripts, 0, -2); // Remove the last ", " that was added by the loop above
											$javascripts .= "]; ";
											
											$sample_count++;
										}
										
										// Javascript functino to display the methods relevant to a clicked sample name
										$javascripts .= "function updatemethods(selectedsample) {";
											$javascripts .= "methodslist.options.length=0;";
										        $javascripts .= "for (i=0; i<samples[selectedsample].length; i++) {";
										        	$javascripts .= "methodslist.options[methodslist.options.length]=new Option(samples[selectedsample][i], samples[selectedsample][i]);";
												$javascripts .= "}";
										$javascripts .= "}";
									}
								echo "</section>";	
							echo "</div>";
						}
					}
				}
			?>
		</article>
		
	</div>
</div>

<?php
	require 'html_footer.php';
?>