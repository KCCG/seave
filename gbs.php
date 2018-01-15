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
				<h2>Genome Block Store</h2>
				<p>The GBS is a database of annotated genomic blocks produced from any software for any type of medium/large genomic event. Genomic blocks consist of start and end coordinates within a genome and are typically generated when inferring copy number variations, structural variations, losses of heterozygosity and linkage.</p>
			</header>
						
			<header class="major">
				<h2>Precisely and rapidly interrogate the GBS</h2>
				<p>Three ways to search let you identify the events that are important to you</p>
			</header>
			
			<div class="row features">
				<section class="4u 12u(narrower) feature">
					<div class="image-wrapper first">
						<a href="#" class="image featured"><img src="images/GBS_gene_lists.png" alt="" style="width: 50%; margin-left: auto; margin-right: auto;" /></a>
					</div>
					<header>
						<h3>Gene List(s)</h3>
					</header>
					<p>The GBS allows you to determine whether your candidate list of genes has been affected by any large-scale genomic events. These events have been shown to have massive functional roles in disease so rapid interrogation is key to rapid diagnosis.</p>
				</section>
				<section class="4u 12u(narrower) feature">
					<div class="image-wrapper">
						<a href="#" class="image featured"><img src="images/GBS_overlapping_blocks.png" alt="" style="width: 50%; margin-left: auto; margin-right: auto;" /></a>
					</div>
					<header>
						<h3>Overlapping Blocks</h3>
					</header>
					<p>Overlapping genomic blocks can be the product of agreement between different tools for calling genomic blocks within a single individual, or they can be overlapping between different individuals for the same or different methods.</p>
						
				</section>
				<section class="4u 12u(narrower) feature">
					<div class="image-wrapper">
						<a href="#" class="image featured"><img src="images/GBS_genomic_coordinates.png" alt="" style="width: 50%; margin-left: auto; margin-right: auto;" /></a>
					</div>
					<header>
						<h3>Genomic Coordinates</h3>
					</header>
					<p>Searching for all blocks within a specific set of genomic coordinates allows you to determine all important blocks in your area of interest. Many conditions are closely tied with a specific genomic location so being able to narrow your search greatly increases the chance of finding your answer rapidly.</p>
				</section>
			</div>

			<header class="major">
				<h2>Passive query</h2>
				<p>Query the GBS without even knowing you are</p>
			</header>

			<p>Storing genome blocks in the GBS enables rapid integration of genomic block information with short nucleotide variants in Seave. If a short variant in a sample is within a genomic block stored in the GBS for that sample, the GBS hit is displayed along with the rest of the annotation for the variant allowing an unparalleled integration of genomic information.</p>
		</article>
	</div>
</div>

<?php

	require 'html_footer.php';

?>