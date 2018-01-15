<?php	
	$body_class = "homepage";
	
	require 'php_header.php'; // Require the PHP header housing required PHP functions
	require 'html_header.php'; // Require the HTML header housing the HTML structure of the page
?>

	<!-- Hero -->
	<section id="hero" class="container">
		<header>
			<h2>Seave is a comprehensive<br> <strong>variant filtration platform</strong> <br> for clinical genomics</h2>
		</header>
		<p>Designed and built by <a href="https://www.vel.nz" target="_blank"><strong>Vel</strong></a> at the <a href="https://www.garvan.org.au/research/kinghorn-centre-for-clinical-genomics/clinical-genomics" target="_blank">KCCG</a> (<a href="https://www.garvan.org.au" target="_blank">Garvan Institute</a>)</p>
		<ul class="actions">
			<li><a href="databases?restart=true" class="button">Take me to the data</a></li>
			
			<?php
				// If the user is logged in and an adminstrator, show the data administration option
				if (is_user_administrator()) {
					echo "<br><br><li><a href=\"gbs_administration\" class=\"button\">GBS administration</a></li>";
					echo "<li><a href=\"database_administration\" class=\"button\">Data administration</a></li>";
					echo "<br><br><li><a href=\"user_administration\" class=\"button\">User administration</a></li>";
					echo "<li><a href=\"gene_list_administration\" class=\"button\">Gene list administration</a></li>";
				}
			?>
			
		</ul>
	</section>

</div>

<!-- Features 1 -->
<div class="wrapper">
	<div class="container">
		<div class="row">
			<section class="6u 12u(narrower) feature">
				<div class="image-wrapper first">
					<a href="http://gemini.readthedocs.org" class="image featured" target="_blank"><img src="images/gemini.png" style="width: 45%; margin-left: auto; margin-right: auto;" alt="" /></a>
				</div>
				<header>
					<h2>GEMINI & GBS backend</h2>
				</header>
				<p>Seave utilizes the powerful GEMINI platform for short variant storage and annotation. GEMINI is designed to be a flexible framework for exploring genetic variation in the context of the wealth of genome annotations available for the human genome. Longer variants such as CNVs, SVs, losses of heterozygosity and regions of homozygosity are stored in the Seave Genome Block Store (GBS). The GBS can be queried along with short variants or separately for specific genes, regions or for overlaps.</p>
				<ul class="actions">
					<li><a href="http://gemini.readthedocs.org" class="button" target="_blank">More on GEMINI</a></li>
					<li><a href="gbs" class="button" target="_blank">More on the GBS</a></li>
				</ul>
			</section>
			<section class="6u 12u(narrower) feature">
				<div class="image-wrapper">
					<a href="data_sources" class="image featured"><img src="images/db.png" style="width: 32%; margin-left: auto; margin-right: auto;" alt="" /></a>
				</div>
				<header>
					<h2>Extensive data integration</h2>
				</header>
				<p>Integration of external data sources allows you to see the latest information about your variants and their likely impact on genes of interest without having to navigate to individual sources manually. Variant annotations are sourced from OMIM, ClinVar, COSMIC, Orphanet, CADD, internal allele frequencies, RVIS, MITOMAP and many more. These prediction algorithms, conservation scores and other related information (such as allele frequencies across human populations) allow rapid variant filtering and prioritisation.</p>
				<ul class="actions">
					<li><a href="data_sources" class="button" target="_blank">More on integrated data sources</a></li>
				</ul>
			</section>
		</div>
	</div>
</div>

<!-- Promo -->
<div id="promo-wrapper">
	<section id="promo">
		<h2>Advanced familial filters rapidly refine your search</h2>
		<a href="familial_filters" class="button">Read more</a>
	</section>
</div>

<!-- Features 2 -->
<div class="wrapper">
	<section class="container">
		<header class="major">
			<h2>There's a <em>world</em> of difference between gene panels and genomes</h2>
			<p>No matter the size of your data, Seave has you covered</p>
		</header>
		<div class="row features">
			<section class="4u 12u(narrower) feature">
				<div class="image-wrapper first">
					<a href="#" class="image featured"><img src="images/DNAStrand-panel.png" style="width: 100%; margin-left: auto; margin-right: auto;" alt="" /></a>
				</div>
				<p>Tried and tested gene panels offer a rapid screen to confirm or diagnose a known condition. Typically made up of small numbers of genes, these panels yield <strong>hundreds to thousands</strong> of variants at a high sequencing depth.</p>
			</section>
			<section class="4u 12u(narrower) feature">
				<div class="image-wrapper">
					<a href="#" class="image featured"><img src="images/DNAStrand-exome.png" alt="" /></a>
				</div>
				<p>Exomes offer a cost-effective method to explore variation in the coding regions of the genome. Focussing on ~1.5% of the genome, exomes typically yield <strong>hundreds of thousands</strong> of variants.</p>
			</section>
			<section class="4u 12u(narrower) feature">
				<div class="image-wrapper">
					<a href="#" class="image featured"><img src="images/DNAStrand-genome.png" style="width: 100%; margin-left: auto; margin-right: auto;" alt="" /></a>
				</div>
				<p>Sporting as many as <strong>10 million</strong> variants across a typical family, genomes are no easy nut to crack. Seave makes interrogating this massive volume of data simple with rigorous filters that intelligently cast away likely benign variants.</p>
			</section>
		</div>
	</section>
</div>

<?php
require 'html_footer.php';
?>