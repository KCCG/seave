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
				<h2>Everything you <strong>need to know</strong> about your database.</h2>
				<p>Below you will find a summary of your database including the number of variants in various categories of effect. Rare variants are defined as occurring at a frequency of less than 1% in ExAC, ESP and 1000 Genomes. All variant counts are of VCF QC-passed variants except the "Total Variants" count.</p>
			</header>
			
			<?php
				
				// Check whether the user is in the group specified
				$user_in_group = is_user_in_group($_SESSION["logged_in"]["email"], $_GET["group"]);
				
				if (!isset($_GET["query_db"], $_GET["group"])) {
					error("You must specify a group and database to view summary information for.");
				} elseif ($user_in_group !== true) {
					error("You do not have access to the group specified.");
				} else {
					// Make sure the summary files exist for the database specified
					if (!file_exists($GLOBALS["configuration_file"]["gemini"]["db_dir"]."/".$_GET["group"]."/".$_GET["query_db"].".all_variants.summary") || !file_exists($GLOBALS["configuration_file"]["gemini"]["db_dir"]."/".$_GET["group"]."/".$_GET["query_db"].".rare_variants.summary")) {
						error("The database you specified does not have summary information generated for it.");
					} else {
						
						echo "<h2>Database:</h2>";
						
						print $_GET["query_db"];
						
						echo "<br><br>";
						
						echo "<h2>Database variants summary:</h2>";
						
						if (!database_summary_table($_GET["group"], $_GET["query_db"])) {
							error("Could not create database summary table.");
						}
					}
				}
				
				echo "<br><a href=\"databases\" class=\"button\">Back to databases</a><br>";
				
			?>
		</article>
		
	</div>
</div>

<?php
	require 'html_footer.php';
?>