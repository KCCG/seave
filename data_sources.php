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
				<h2>Integration of <strong>external data</strong> sources puts you in <strong>control</strong>.</h2>
				<p>The automated integration of variant annotations from a variety of external databases and methods gives you all the information you need to decide if your variants are important. Annotation sources with the versions used by Seave are listed below.</p>
			</header>
			
			<?php
			
			// Fetch the annotations from the DB
			$annotations = fetch_all_annotations();
			
			if ($annotations === false || count(array_keys($annotations)) == 0) {
				error("Error fetching annotations.");
			} else {
				// Create a variable to store the current box positioning (right or left) to write the correct div classes
				$positioning = "left";
				
				// Go through each annotation
				foreach (array_keys($annotations) as $annotation_name) {
					// The opening div class for the left and right boxes row
					if ($positioning == "left") {
						echo "<div class=\"row\">";
					}
					
					echo "<section class=\"6u 12u(narrower)\">";
						echo "<h2>".$annotation_name."</h2>";
						
						echo "<strong>Description:</strong> ".$annotations[$annotation_name]["description"]."<br>";
						
						echo "<strong>Annotated by:</strong> ";
						if ($annotations[$annotation_name]["group_name"] == "") {
							echo "Seave";
						} else {
							echo $annotations[$annotation_name]["group_name"];
						}
						echo "<br>";
						echo "<strong>Current version:</strong> ".$annotations[$annotation_name]["current_version"]."<br>";
						echo "<strong>Last updated:</strong> ".$annotations[$annotation_name]["current_version_update_time"]."<br>";
					echo "</section>";
					
					// The closing div class for the left and right boxes row
					if ($positioning == "right") {
						echo "</div>";
					}
					
					// Swap positioning
					if ($positioning == "left") {
						$positioning = "right";
					} elseif ($positioning == "right") {
						$positioning = "left";
					}
				}
			}
			
			?>
		</article>
	</div>
</div>

<?php
	include 'html_footer.php';
?>