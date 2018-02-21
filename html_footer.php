<!-- Footer -->
			<div id="footer-wrapper">
				<div id="copyright" class="container">
					<ul class="menu">
						<li>For research use only. Not for use in diagnostic procedures.</li>
					</ul>
					
					<ul class="menu">
						<li>&copy; 2018 Garvan Institute. All rights reserved.</li>
						<li><a href="https://github.com/KCCG/seave">Code</a></li>
						<li><a href="https://github.com/KCCG/seave-documentation">Documentation</a></li>
						<li>Contact: <a href="mailto:v.gayevskiy@garvan.org.au">Vel</a></li>
					</ul>
				</div>
			</div>
        
        <!-- DataTables CSS -->
		<link rel="stylesheet" type="text/css" href="assets/DataTables-1.10.4/media/css/jquery.dataTables.css">
		  
		<!-- DataTables -->
		<script type="text/javascript" charset="utf8" src="assets/DataTables-1.10.4/media/js/jquery.dataTables.js"></script>
		<script type="text/javascript" charset="utf8" src="assets/DataTables-1.10.4/extensions/sorting/natural.js"></script> <!-- Natural sorting -->
		<script type="text/javascript" charset="utf8" src="assets/DataTables-1.10.4/extensions/File-Size/file-size.js"></script> <!-- Fize size sorting (e.g. 300MB < 3GB not the other way around) -->
		<script type="text/javascript" charset="utf8" src="assets/DataTables-1.10.4/extensions/sorting/date-eu.js"></script> <!-- Date sorting -->

		<script>

			//############################################
			// ENABLE DATATABLES FOR ALL TABLES
			//############################################

			$(document).ready( function () {
			    $('#db_information').DataTable( {
				    "iDisplayLength": 25, // Display up to 25 databases by default
				    "columnDefs": [
						{ "width": "20%", "targets": 0 }, // Specify a column width for the db name column
						{ "width": "15%", "targets": 2 }, // Specify a column width for the sample names column
						{ type: 'file-size', targets: 5 }, // Specify the 6th column as a file size to sort by file size
						{ type: 'date-eu', targets: 6 } // Specify the 7th column as a date column for sorting
					]
			    } );
			    
			    $('#list_of_genes').DataTable( {
				    "iDisplayLength": 25 // Display up to 25 genes by default
			    } );
			    
			    $('#annotation_history').DataTable( {
				    "iDisplayLength": 25 // Display up to 25 genes by default
			    } );
			    
			    $('#users_in_group').DataTable( {
				    "iDisplayLength": 25 // Display up to 25 genes by default
			    } );
			    
			    $('#genomic_blocks').DataTable( {
				    "iDisplayLength": 10, // Display up to 10 events by default
				    columnDefs: [
						{ type: 'natural', targets: 0 } // Natural sorting in the Location column
					]
			    } );
			    
			    $('#db_summary').DataTable( {
				    "iDisplayLength": 50, // Display up to 50 summary rows by default
				    "order": [[ 1, "desc" ]]
			    } );
			    
			    $('#variant_information').DataTable();
			    
			    var table = $('#gemini').DataTable( {	    
				    columnDefs: [
						{ type: 'natural', targets: 0 }, // Natural sort the first "Variant" column
						<?php
						// Define an array of columns to sort numerically
						$numeric_sort_columns = array("COSMIC Count");
						
						// Check if any of the columns to sort numerically are present in the column index, if so print the column number to sort
						foreach ($numeric_sort_columns as $numeric_sort_column_name) {
							if (isset($column_index[$numeric_sort_column_name])) {
								echo "{ type: 'numeric', targets: ".$column_index[$numeric_sort_column_name]." }, ";
							}
						}
						
						if (isset($column_index["IGV"])) {
							echo "{ width: \"50px\", targets: ".$column_index["IGV"]." }, ";
						}
						?>
					]
	  			} );
	  			
	  			$('#gemini').show(); // The HTML table is hidden by default, after the datatable is loaded with the above function, it can be unhidden

	  			var gbs_table = $('#gbs').DataTable( {
		  			columnDefs: [
						{ type: 'natural', targets: 0 }, // Natural sort the first column
						<?php
						// Define an array of columns to sort naturally
						$natural_sort_columns = array("Copy Number");
						
						// Check if any of the columns to sort naturally are present in the column index, if so print the column number to sort
						foreach ($natural_sort_columns as $natural_sort_column_name) {
							if (isset($column_index[$natural_sort_column_name])) {
								echo "{ type: 'natural', targets: ".$column_index[$natural_sort_column_name]." }, ";
							}
						}
						
						if (isset($column_index["IGV"])) {
							// If there are going to be 2 breakpoints, make the column wider
							if (isset($column_index["Block2 Coordinates"])) {
								$width = "104px";
							// Default single block width
							} else {
								$width = "50px";
							}
							
							echo "{ width: \"".$width."\", targets: ".$column_index["IGV"]." }, ";
						}
						?>
					]
	  			} );
	  			
	  			$('#gbs').show(); // The HTML table is hidden by default, after the datatable is loaded with the above function, it can be unhidden
				
				<?php
					// Hide all non-default columns in the GEMINI results table
					if (isset($hidden_columns) && count($hidden_columns) > 0) {
						echo "table.columns( [ ".implode(", ", $hidden_columns)." ] ).visible( false, false )";
					}
					
					// Hide all non-default columns in the GBS results table
					if (isset($hidden_columns_gbs) && count($hidden_columns_gbs) > 0) {
						echo "gbs_table.columns( [ ".implode(", ", $hidden_columns_gbs)." ] ).visible( false, false )"; # Hide all non-default columns						
					}
				?>
	
			} );
			
			//############################################
			// FUNCTION TO TOGGLE SPECIFIC COLUMNS BASED ON THEIR INDEX NUMBER
			//############################################
			
			function fnShowHideMultiple(table, cols) {
				var oTable = $(table).dataTable();
				
				// Save the current page number
				var page = oTable.api().page();
				
				for (iCol in cols) {
					var bVis = oTable.fnSettings().aoColumns[cols[iCol]].bVisible;
					oTable.fnSetColumnVis( cols[iCol], bVis ? false : true );
				}
				
				// Restore the saved page number
				oTable.fnPageChange( page );
			}
			
			//############################################
			// GO BACK TO PREVIOUS PAGE FUNCTION
			//############################################
	
			function goBack() {
				window.history.back()
			}
	
			//############################################
			// SHOW A SPECIFIC FAMILY FORM WHILE HIDING ALL OTHERS - SHORT VARIANTS
			//############################################

			function showfamily(thechosenone) {
				// Hide all the family analysis types
				$('.families').hide();
				
				// If the family selected is "Entire Dataset" then hide the "Return information for:" section, otherwise show it
				if (thechosenone == "family_info_entiredatabase") {
					$('.return_information_section').hide();
				} else {
					$('.return_information_section').show();
				}
				
				// Show the family analysis type set for the family clicked
				$("[id='" + thechosenone + "']").show();
				//$('#' + thechosenone).show();
			}
			
			//############################################
			// SHOW A SPECIFIC FAMILY FORM WHILE HIDING ALL OTHERS - GBS
			//############################################

			function showfamilygbs(thechosenone) {
				// Hide all the family analysis types
				$('.families').hide();
				
				// Show the family analysis type set for the family clicked
				$("[id='" + thechosenone + "']").show();
				//$('#' + thechosenone).show();
				
				if (document.getElementById(thechosenone + "gene_lists").checked == true) {
					showdiv('lists');
				} else if (document.getElementById(thechosenone + "sample_overlaps").checked == true) {
					showdiv('sample_overlaps');
				} else if (document.getElementById(thechosenone + "method_overlaps").checked == true) {
					showdiv('method_overlaps');
				} else if (document.getElementById(thechosenone + "genomic_coordinates").checked == true) {
					showdiv('positions');
				} else if (document.getElementById(thechosenone + "rohmer").checked == true) {
					showdiv('rohmer');
				}
			}

			//############################################
			// SHOW A SPECIFIC DIV WHILE HIDING ALL OTHERS
			//############################################
			
			function showdiv(thechosenone) {
				// Hide all the selection divs
				$('.selection').hide();
				
				// Show selection div for the one clicked
				$("[id='" + thechosenone + "']").show();
			}
			
			//############################################
			// SHOW/HIDE A SINGLE DIV AS A TOGGLE
			//############################################

			function toggle(id) {
		        var state = document.getElementById(id).style.display;

	            if (state == 'block') {
	                document.getElementById(id).style.display = 'none';
	            } else {
	                document.getElementById(id).style.display = 'block';
	            }
		    }
		    
		    //############################################
			// INCREASE/DECREASE WIDTH AS A TOGGLE
			//############################################
			
		    function restrict_width(id) {
			    var state = document.getElementById(id).style.width;
			    
			    if (state == '92%') {
				    document.getElementById(id).style.width = '';
	            } else {
	                document.getElementById(id).style.width = '92%';
	            }
		    }
		
			//############################################
			// GOOGLE ANALYTICS
			//############################################
			
			(function(i,s,o,g,r,a,m){i['GoogleAnalyticsObject']=r;i[r]=i[r]||function(){
			(i[r].q=i[r].q||[]).push(arguments)},i[r].l=1*new Date();a=s.createElement(o),
			m=s.getElementsByTagName(o)[0];a.async=1;a.src=g;m.parentNode.insertBefore(a,m)
			})(window,document,'script','//www.google-analytics.com/analytics.js','ga');
			
			ga('create', 'UA-19500984-6', 'auto');
			ga('send', 'pageview');
		</script>
		
		<?php
		
		#############################################
		# MISCELLANEOUS JAVASCRIPTS ADDED ON PAGES
		#############################################

		if ($javascripts != "") { # Other javascripts required within pages need to be printed at the end
			echo "<script>".$javascripts."</script>";	
		}
		
		#############################################
		# KILL DB CONNECTION IF SET
		#############################################
		
		if (isset($GLOBALS["mysql_connection"])) {
			kill_mysql_connection();
		}
		
		?>
	</body>
</html>
