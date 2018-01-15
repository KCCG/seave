<?php	
	$body_class = "no-sidebar";
	
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
				<h2>Familial filters help you hone in on the variant of interest.</h2>
				<p>By using the known affected and unaffected status of a group of closely related people, familial filters are able to rapidly discard large amounts of variants that are unlikely to be disease-causing in affected members.</p>
			</header>
			
			<div class="container">
				<div class="row">
					<section class="6u 12u(narrower) feature">
						<div class="image-wrapper first">
							<a class="image featured first"><img src="images/AutDom.png" style="width:40%; margin-left: auto; margin-right: auto;" alt="" /></a>
						</div>
						<header>
							<a name="het_dom"></a>
							<h2>Heterozygous dominant</h2>
						</header>
						<p>The heterozygous dominant analysis type searches for variants where an affected parent and unaffected parent give rise to an affected child. This is caused by the passage of a heterozygous genotype from the affected parent to affected children. <br><br>All variants will be returned where <strong>affected individuals have a heterozygous genotype</strong> and all <strong>unaffected individuals do not have a heterozygous genotype</strong> (i.e. homozygous reference or homozygous alternate).</p>
						<p>This analysis is equivalent to <strong>autosomal dominant</strong> if searching within the autosome and to <strong>X-linked dominant</strong> if searching within the X chromosome.</p>
						<p><strong>Examples</strong></p>
						
						<table style="width:100%;">
							<tr>
							   <td><a class="image featured"><img src="images/AutDom.png" style="width: 60%; margin-left: auto; margin-right: auto;" alt="" /></a></td>
							   <td><a class="image featured"><img src="images/AutDom-alt.png" style="width: 60%; margin-left: auto; margin-right: auto;" alt="" /></a></td>
							</tr>
							<tr>
								<td><a class="image featured"><img src="images/AutDom-alt2.png" style="width: 60%; margin-left: auto; margin-right: auto;" alt="" /></a></td>
								<td><a class="image featured"><img src="images/AutDom-alt3.png" style="width: 60%; margin-left: auto; margin-right: auto;" alt="" /></a></td>
							</tr>
						</table>
					</section>
					<section class="6u 12u(narrower) feature">
						<div class="image-wrapper">
							<a class="image featured"><img src="images/AutRec.png" style="width:40%; margin-left: auto; margin-right: auto;" alt="" /></a>
						</div>
						<header>
							<a name="hom_rec"></a>
							<h2>Homozygous/hemizygous recessive</h2>
						</header>
						<p>The homozygous recessive analysis type searches for variants where two unaffected parents with heterozygous genotypes give rise to a homozygous alternate genotype in affected children. <br><br>All variants will only be returned where <strong>all affected individuals have a homozygous alternate genotype</strong> and <strong>all unaffected individuals do not have a homozygous alternate genotype</strong> (i.e. heterozygous or homozygous reference). Homozygous reference genotypes are allowed because unaffected siblings can have this genotype if both parents are heterozygous.</p>
						<p>This analysis is equivalent to <strong>autosomal recessive</strong> if searching within the autosome and to <strong>X-linked recessive</strong> if searching within the X chromosome.</p>
						<p><strong>Examples</strong></p>
						
						<table style="width:100%;">
							<tr>
							   <td><a class="image featured"><img src="images/AutRec.png" style="width: 60%; margin-left: auto; margin-right: auto;" alt="" /></a></td>
							   <td><a class="image featured"><img src="images/AutRec-alt.png" style="width: 60%; margin-left: auto; margin-right: auto;" alt="" /></a></td>
							</tr>
							<tr>
								<td><a class="image featured"><img src="images/AutRec-alt2.png" style="width: 60%; margin-left: auto; margin-right: auto;" alt="" /></a></td>
								<td><a class="image featured"><img src="images/AutRec-alt3.png" style="width: 60%; margin-left: auto; margin-right: auto;" alt="" /></a></td>
							</tr>
						</table>
					</section>
				</div>
			</div>
			
			<div class="container">
				<div class="row">
					<section class="6u 12u(narrower) feature">
						<div class="image-wrapper first">
							<a class="image featured first"><img src="images/CompHet.png" style="width:70%; margin-left: auto; margin-right: auto;" alt="" /></a>
						</div>
						<header>
							<a name="comp_het"></a>
							<h2>Compound heterozygous</h2>
						</header>
						<p>The compound heterozygous analysis type is <strong>gene-centric</strong> and searches for a scenario where at least two separate (i.e. different genomic positions) heterozygous variants in the same gene in unaffected parents are both transmitted to an affected child on different strands. <br><br>All variants will be returned for a gene where <strong>affected individuals all share a heterozygous genotype</strong> and <strong>for one position in a gene one unaffected individual shares this heterozygous genotype with the affecteds and in another position another unaffected individual shares the heterozygous genotype with the converse not being true</strong>.</p>
						<p><strong>Examples</strong></p>
						
						<table style="width:100%;">
							<tr>
							   <td><a class="image featured"><img src="images/CompHet.png" style="width: 60%; margin-left: auto; margin-right: auto;" alt="" /></a></td>
							</tr>
							<tr> 
							   <td><a class="image featured"><img src="images/CompHet-alt.png" style="width: 60%; margin-left: auto; margin-right: auto;" alt="" /></a></td>
							</tr>
							<tr> 
							   <td><a class="image featured"><img src="images/CompHet-alt2.png" style="width: 60%; margin-left: auto; margin-right: auto;" alt="" /></a></td>
							</tr>
							<tr> 
							   <td><a class="image featured"><img src="images/CompHet-alt3.png" style="width: 60%; margin-left: auto; margin-right: auto;" alt="" /></a></td>
							</tr>
							<tr> 
							   <td><a class="image featured"><img src="images/CompHet-alt4.png" style="width: 60%; margin-left: auto; margin-right: auto;" alt="" /></a></td>
							</tr>
						</table>
					</section>
					<section class="6u 12u(narrower) feature">
						<div class="image-wrapper">
							<a class="image featured"><img src="images/DeNovoDom.png" style="width:40%; margin-left: auto; margin-right: auto;" alt="" /></a>
						</div>
						<header>
							<a name="de_novo_dom"></a>
							<h2>De novo dominant</h2>
						</header>
						<p>The de novo dominant analysis type searches for a non-mendelian inheritance pattern where a mutation arises spontaneously within affected individuals and the causative allele is not present in any unaffected individual. Affected individuals therefore share a heterozygous variant that is not present in unaffected individuals and could not arise through mendelian inheritance. <br><br>All variants will be returned where <strong>all affected individuals share a heterozygous variant</strong> and <strong>all unaffected individuals either share a homozygous reference or homozygous alternate genotype</strong>.</p>
						<p><strong>Examples</strong></p>
						
						<table style="width:100%;">
							<tr>
							   <td><a class="image featured"><img src="images/DeNovoDom.png" style="width: 60%; margin-left: auto; margin-right: auto;" alt="" /></a></td>
							   <td><a class="image featured"><img src="images/DeNovoDom-alt.png" style="width: 60%; margin-left: auto; margin-right: auto;" alt="" /></a></td>
							</tr>
							<tr>
								<td><a class="image featured"><img src="images/DeNovoDom-alt2.png" style="width: 60%; margin-left: auto; margin-right: auto;" alt="" /></a></td>
							</tr>
						</table>
					</section>
				</div>
			</div>
		</article>

		
	</div>
</div>

<?php
	require 'html_footer.php';
?>