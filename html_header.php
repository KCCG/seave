<!DOCTYPE HTML>
<html>
	<head>
		<title>Seave</title>
		<html lang="en">
		<meta http-equiv="content-type" content="text/html; charset=utf-8" />
		<meta name="description" content="" />
		<meta name="keywords" content="" />
		<!--[if lte IE 8]><script src="css/ie/html5shiv.js"></script><![endif]-->
		<script src="js/jquery.min.js"></script>
		<script src="js/jquery.dropotron.min.js"></script>
		<script src="js/skel.min.js"></script>
		<script src="js/skel-layers.min.js"></script>
		<script src="js/init.js"></script>
		<noscript>
			<link rel="stylesheet" href="css/skel.css" />
			<link rel="stylesheet" href="css/style.css" />
		</noscript>
		<!--[if lte IE 8]><link rel="stylesheet" href="css/ie/v8.css" /><![endif]-->
	</head>
	<body class="<?php echo $body_class; ?>">

		<!-- Header -->
			<div id="header-wrapper">
				<div id="header" class="container">

					<!-- Logo -->
						<h1 id="logo">
							<a href="home">Seave
								<?php
									// If the current Seave deployment is for development/testing purposes
									if ($GLOBALS["configuration_file"]["version"]["name"] == "dev") {
										echo "<div class=\"subtitle\">Development</div>";
									// If the current Seave deployment is for training purposes
									} elseif ($GLOBALS["configuration_file"]["version"]["name"] == "training") {
										echo "<div class=\"subtitle\">Training</div>";
									// The default Seave version is for research use only
									} else {
										echo "<div class=\"subtitle\">Research</div>";
									}
								?>
							</a>
						</h1>

					<!-- Nav -->
						<nav id="nav">
							<ul>
								<?php 
									// If the user is an administrator, display administration options in a dropdown
									if (is_user_administrator()) {
										?>
										<li>
											<a href="databases?restart=true">Databases</a>
											<ul>
												<li><a href="databases">Databases</a></li>
												<li><a href="database_administration">Database Administration</a></li>
												<li><a href="user_administration">User Administration</a></li>
												<li><a href="gene_list_administration">Gene List Administration</a></li>
												<li><a href="gbs_administration">GBS Administration</a></li>
											</ul>
										</li>
										<?php
									// If the user is not an administrator, don't display any sub-options
									} else {
										?>
										<li><a href="databases?restart=true">Databases</a></li>
										<?php
									}
								?>
								<li>
									<a href="familial_filters">Familial Filters</a>
									<ul>
										<li><a href="familial_filters#het_dom">Heterozygous Dominant</a></li>
										<li><a href="familial_filters#hom_rec">Homozygous Recessive</a></li>
										<li><a href="familial_filters#comp_het">Compound Heterozygous</a></li>
										<li><a href="familial_filters#de_novo_dom">De Novo Dominant</a></li>
									</ul>
								</li>
								
								<li class="break">
									<a href="data_sources">Data Sources</a>
									<ul>
										<li><a href="data_sources">External Annotations</a></li>
										<li><a href="gbs">Genome Block Store</a></li>
									</ul>
								</li>
								
								<?php
								if (is_user_logged_in()) {
									echo "<li><a href=\"logout\">Log Out</a></li>";
								} else {
									echo "<li><a href=\"login\">Log In</a></li>";
								}
								?>
								
							</ul>
						</nav>
				</div>