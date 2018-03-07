<?php	
	$body_class = "right-sidebar"; // This page has a right sidebar
	
	require 'php_header.php'; // Require the PHP header housing required PHP functions
	require 'html_header.php'; // Require the HTML header housing the HTML structure of the page
?>

</div>

<!-- Main -->
<div class="wrapper">
	<div class="container" id="main">
		<div class="row 150%">
			<div class="8u 12u(narrower)">

				<!-- Content -->
				<article id="content">
					<header>
						<h2>Your database contains <strong>families</strong>.</h2>
						<p>You can choose to use familial information to conduct variant filtration on members of a single family using predefined analysis methods. Alternatively, you can choose to analyse the entire dataset.</p>
					</header>
					
					<a class="image featured"><img src="images/family.jpg" alt="" /></a>
					
					<?php
						// If a query database was selected on the database page and it doesn't equal the previously queried database
						if (isset($_GET["group"], $_GET["query_db"], $_GET["hasped"]) && ($_SESSION["query_group"] != $_GET["group"] || $_SESSION["query_db"] != $_GET["query_db"])) {
							// Make sure the user is in the group specified
							$user_in_group = is_user_in_group($_SESSION["logged_in"]["email"], $_GET["group"]);
							
							// If the user is in the group, clear all session variables then set the query group, database and hasped status to the one selected on the database page
							if ($user_in_group === true) {
								session_update_db($_GET["group"], $_GET["query_db"], $_GET["hasped"]);
							} else {
								error("You do not have access to the group selected.");
							}
						} elseif ($_SESSION["query_db"] == "") { # If no database was selected
							error("You need to select a database before selecting an analysis type.");
						}
						
						// If a query group and database have been selected
						if ($_SESSION["query_group"] != "" && $_SESSION["query_db"] != "") {
							echo "<form action=\"actions/action_analysis_types\" method=\"get\">";
								// Check whether the current database has pedigree information inside it
						    	if ($_SESSION["hasped"] == "Yes") {
							    	$family_info = extract_familial_information($_SESSION["query_group"]."/".$_SESSION["query_db"]); # Extract familial information from the database for all families
									
									echo "<h3 style=\"padding-top:10px;\">Database selected</h3>"; // Print the database being used
									echo "<p>".$_SESSION["query_db"]."</p>";
									
									echo "<h3 style=\"padding-bottom:10px;\">Select a family to analyse</h3>";
									
									#############################################
									# FAMILY SELECTION RADIOS
									#############################################
									
									// If there has been no family selected before or the entire dataset has already been selected, select the Entire Dataset option
									echo "<input type=\"radio\" id=\"family_entiredatabase\" name=\"family\" value=\"entiredatabase\" onclick=\"showfamily('family_info_entiredatabase');\"";
									if ($_SESSION["family"] == "" || $_SESSION["family"] == "entiredatabase") {
										echo " checked>";
									} elseif ($_SESSION["family"] != "") {
										echo ">";
									}
									echo "<label for=\"family_entiredatabase\">Entire Dataset</label>";
									
									// If there is more than one family or there is one but it is not the missing family value of zero
									if (count(array_keys($family_info)) > 1 || (count(array_keys($family_info)) == 1 && !isset($family_info[0]))) {
										// Go through every family
										foreach (array_keys($family_info) as $family_id) {				
											echo "<input type=\"radio\" id=\"family_".$family_id."\" name=\"family\" value=\"".$family_id."\" onclick=\"showfamily('family_info_$family_id');\"";
											if ($_SESSION["family"] == (string) $family_id) { # If this is the family previously selected, mark the radio as checked to correspond with the family information below (the (string) forces the family name to be treated as a string which prevents "entiredatabase" being equal to int(0) and the wrong family being selected when one of the families is names zero
												echo " checked>";
											} else {
												echo ">";
											}
											
											echo "<label for=\"family_".$family_id."\">";
											if ($family_id == "None") {
												echo "No Family Specified";
											} else {
												echo $family_id;
											}
											echo "</label>";
										}
									}
									
									#############################################
									# FAMILY COMPOSITION AND AFFECTED STATUS INFO
									#############################################
									
									echo "<h3 style=\"padding-top:20px; padding-bottom:10px;\">Family information</h3>";
									
									// Familial information for the "Entire Dataset" option
									echo "<div class=\"families\" id=\"family_info_entiredatabase\"";
										// If the option is selected, print the text, otherwise hide the div
										if ($_SESSION["family"] == "" || $_SESSION["family"] == "entiredatabase") {
											echo ">";
										} else {
											echo " style=\"display: none\">";
										}
										echo "Not applicable.<br>";
									echo "</div>";
									
									// Go through every family
									foreach (array_keys($family_info) as $family_id) {
										$family_affected_status = family_affected_status($family_info{$family_id}); # Extract the affected and unaffected samples
										
										echo "<div class=\"families\" id=\"family_info_".$family_id."\"";
											// If this family was previously selected, display its info immediately, otherwise hide it (the (string) forces the family name to be treated as a string which prevents "entiredatabase" being equal to int(0) and the wrong family being selected when one of the families is names zero
											if ($_SESSION["family"] == (string) $family_id) {
												echo ">";
											} else {
												echo " style=\"display: none\">";
											}
											
											foreach (array_keys($family_info{$family_id}) as $sample_name) {
												echo $sample_name;
												
												// Print the gender of the sample
												if ($family_info{$family_id}{$sample_name}{"sex"} == "1") {
													echo " (Male)";
												} elseif ($family_info{$family_id}{$sample_name}{"sex"} == "2") {
													echo " (Female)";
												} else {
													echo " (Unknown Gender)";
												}
												
												// Print the affected status of the individual
												if (is_affected($family_info{$family_id}{$sample_name}{"phenotype"})) {
													echo " - Affected<br>";
												} elseif (is_unaffected($family_info{$family_id}{$sample_name}{"phenotype"})) {
													echo " - Unaffected<br>";
												} elseif (is_unknown_phenotype($family_info{$family_id}{$sample_name}{"phenotype"})) {
													echo " - Unknown<br>";
												}
											}
							
											echo "<p style=\"padding-top:6px; font-style:italic;\"><strong>Please ensure this information is correct before proceeding.</strong></p>";
											
											#############################################
											# ANALYSIS TYPE RADIOS
											#############################################
											
											echo "<h3 style=\"padding-bottom:10px;\">Select an analysis type</h3>";
											
											$analysis_types = array(); // Define an array housing all available analysis types
											array_push($analysis_types, "analysis_none", "analysis_hom_rec", "analysis_het_dom", "analysis_comp_het", "analysis_denovo_dom");
											
											foreach ($analysis_types as $analysis_type) { // Go through each analysis type and print radio buttons for each one
												echo "<input type=\"radio\" id=\"".$family_id.$analysis_type."\" name=\"".$family_id."analysis_type\" value=\"".$analysis_type."\"";
												
												# Disable the heterozygous dominant analysis if there are no affected at all
												if ($analysis_type == "analysis_het_dom" && count($family_affected_status["affected"]) == 0) {
													echo " disabled=\"\"";
												# Disable the homozygous recessive analysis if there are no affected at all
												} elseif ($analysis_type == "analysis_hom_rec" && count($family_affected_status["affected"]) == 0) {
													echo " disabled=\"\"";
												# Disable the compound heterozygous analysis if there are no affected at all
												} elseif ($analysis_type == "analysis_comp_het" && count($family_affected_status["affected"]) == 0) {
													echo " disabled=\"\"";
												# Disable the de novo dominant analysis if there are no affected at all
												} elseif ($analysis_type == "analysis_denovo_dom" && count($family_affected_status["affected"]) == 0) {
													echo " disabled=\"\"";
												}
												
												if (($family_id == $_SESSION["family"] && $_SESSION["analysis_type"] == $analysis_type) || ($_SESSION["analysis_type"] == "" && $analysis_type == "analysis_none") || ($family_id != $_SESSION["family"] && $analysis_type == "analysis_none")) { // Check the current analysis type if it was previously selected or one has not been selected before 
													echo " checked=\"\"";
												}
																	
												echo ">";
												
												echo "<label for=\"".$family_id.$analysis_type."\">"; // Radio label
												
													if ($analysis_type == "analysis_none") {
														echo "None";
													} elseif ($analysis_type == "analysis_hom_rec") {
														echo "Homozygous Recessive";
													} elseif ($analysis_type == "analysis_het_dom") {
														echo "Heterozygous Dominant";
													} elseif ($analysis_type == "analysis_comp_het") {
														echo "Compound Heterozygous";
													} elseif ($analysis_type == "analysis_denovo_dom") {
														echo "De Novo Dominant";
													}
												
												echo "</label>";
											}
										echo "</div>";
									}
									
									#############################################
									# RESTRICT COLUMNS RETURNED
									#############################################
									
									// Familial information for the "Entire Dataset" option
									echo "<div class=\"return_information_section\"";
										// If the option is selected, print the text, otherwise hide the div
										if ($_SESSION["family"] == "" || $_SESSION["family"] == "entiredatabase") {
											echo " style=\"display: none\">";
										} else {
											echo ">";
										}
										
										// If there is only one family in the database, there is no point displaying an option for whether to return information from all families
										if (count(array_keys($family_info)) != 1) {
											echo "<h3 style=\"padding-top:20px; padding-bottom:10px;\">Return variant information for</h3>";
											
											echo "<input type=\"radio\" id=\"information_family_only\" name=\"return_information\" value=\"family_only\"";
											
											// If no option has been previously selected or it was family only, select this radio
											if ($_SESSION["return_information_for"] == "" || $_SESSION["return_information_for"] == "family_only") {
												echo " checked>";
											} else {
												echo ">";
											}
											
											echo "<label for=\"information_family_only\">Selected Family Only</label>";
											
											#############################################
											
											echo "<input type=\"radio\" id=\"information_cohort\" name=\"return_information\" value=\"cohort\"";
											
											// If the cohort option was previously selected, select it again
											if ($_SESSION["return_information_for"] == "cohort") {
												echo " checked>";
											} else {
												echo ">";
											}
											
											echo "<label for=\"information_cohort\">Entire Cohort</label>";
										}
									echo "</div>";
									
									#############################################
									# ANALYSIS TYPE RADIOS
									#############################################
									
									/*echo "<h3 style=\"padding-top:20px; padding-bottom:10px;\">Query type</h3>";
									
									echo "<input type=\"radio\" id=\"radio_query_page\" name=\"query_type\" value=\"query_page\" checked>";
									echo "<label for=\"radio_query_page\">Query Parameters Page</label>";
									
									echo "<input type=\"radio\" id=\"radio_cancer\" name=\"query_type\" value=\"cancer\">";
									echo "<label for=\"radio_cancer\">Cancer Summary</label>";
									
									echo "<br>";*/
									
								}
	
								echo "<br><input type=\"submit\" value=\"Proceed to query options\">";
							
							echo "</form>";
							
							echo "<br /><a href=\"databases?restart=true\" style=\"padding: 0.4em 1em 0.4em 1em;\" class=\"button\">Start over</a>";
						}
					?>
				</article>

			</div>
			<div class="4u 12u(narrower)">

				<!-- Sidebar -->
					<section id="sidebar">
						<section>
							<header>
								<h3>Familial filtering</h3>
							</header>
							<p>Click each of the headings below if you would like more information regarding the filtration mechanism and for different example familial scenarios.</p>
						</section>
						<section>
							<header>
								<h3>Heterozygous dominant</h3>
							</header>
							<a class="image featured"><img src="images/AutDom.png" style="width: 60%; margin-right: auto;" alt="" /></a>
							<p>All affected individuals have a heterozygous genotype and all unaffected individuals do not have a heterozygous genotype. Equivalent to autosomal dominant in the autosome and X-linked dominant in the X chromosome.</p>
						</section>
						<section>
							<header>
								<h3>Homozygous/hemizygous recessive</h3>
							</header>
							<a class="image featured"><img src="images/AutRec.png" style="width: 60%; margin-right: auto;" alt="" /></a>
							<p>All affected individuals have a homozygous alternate genotype and all unaffected individuals do not have a homozygous alternate genotype. Equivalent to autosomal recessive in the autosome and X-linked recessive in the X chromosome.</p>
						</section>
						<section>
							<header>
								<h3>Compound heterozygous</h3>
							</header>
							<a class="image featured"><img src="images/CompHet.png" style="width: 80%; margin-right: auto;" alt="" /></a>
							<p>Affected individuals all share a heterozygous genotype and for one position in a gene one unaffected individual shares this heterozygous genotype with the affecteds and in another position another unaffected individual shares the heterozygous genotype with the converse not being true.</p>
						</section>
						<section>
							<header>
								<h3>De novo dominant</h3>
							</header>
							<a class="image featured"><img src="images/DeNovoDom.png" style="width: 60%; margin-right: auto;" alt="" /></a>
							<p>All affected individuals share a heterozygous variant and all unaffected individuals either share a homozygous reference or homozygous alternate genotype.</p>
						</section>
						<section>
							<header>
								<h3>None</h3>
							</header>
							<p>The none analysis type returns all variants in the database where at least one of the samples in the family selected has a variant.</p>
						</section>
					</section>

			</div>
		</div>
	</div>
</div>

<?php
	require 'html_footer.php';
?>