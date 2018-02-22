<?php
	
	require 'php_header.php'; // Require the PHP header housing required PHP functions
	require 'variables.php'; # Add functions to define and update session variables
	
	#############################################
	# IF THE DATA BEING IMPORTED IS A GEMINI DB
	#############################################
	
	// If no import type is supplied, assume it is a GEMINI DB (for compatibility reasons with old DX importer app)
	if ($_GET["import_type"] == "gemini_db") {
		
		#############################################
		# MAKE SURE A TOKEN AND URL ARE SUPPLIED
		#############################################
		
		if (!isset($_GET["token"]) || !isset($_GET["url"]) || !isset($_GET["group"])) {
			echo "Fail: no token, db url or group supplied";
			
			exit;
		}
		
		#############################################
		# DOES THE URL END IN .DB
		#############################################
		
		if (!preg_match("/.db$/", $_GET["url"])) {
			echo "Fail: URL does not end with .db";
			
			exit;
		}
		
		#############################################
		# DOES THE URL EXIST
		#############################################
		
		if (!does_url_exist($_GET["url"])) {
			echo "Fail: url does not exist";
			
			exit;
		}
	
		#############################################
		# DOES SEAVE HAVE ENOUGH STORAGE SPACE
		#############################################
		
		if (((disk_total_space($GLOBALS["configuration_file"]["gemini"]["db_dir"]) - disk_free_space($GLOBALS["configuration_file"]["gemini"]["db_dir"])) / disk_total_space($GLOBALS["configuration_file"]["gemini"]["db_dir"])) > 0.98) {
			echo "Fail: Seave is running low on storage space ".storage_space("used")."/".storage_space("total");
			
			exit;
		}
		
		#############################################
		# VALIDATE TOKEN
		#############################################
		
		if ($_GET["token"] != $GLOBALS["configuration_file"]["dx_import"]["gemini_db_import_token"]) {
			echo "Fail: bad token";
			
			exit;
		}
		
		#############################################
		# VALIDATE THE GROUP SUBMITTED EXISTS
		#############################################
		
		$account_groups = fetch_account_groups();
		
		if ($account_groups === false) {
			echo "Fail: can't fetch groups;";
			
			exit;
		}
		
		$group_found_flag = 0;
		
		foreach (array_keys($account_groups) as $group_id) {
			if ($account_groups[$group_id]["group_name"] == $_GET["group"]) {
				$group_found_flag = 1;
			}
		}
		
		if ($group_found_flag == 0) {
			echo "Fail: group specified doesn't exist;";
			
			exit;
		}
		
		#############################################
		# CREATE THE GROUP DIRECTORY IF IT DOESN'T ALREADY EXIST
		#############################################
		
		if (!is_dir($GLOBALS["configuration_file"]["gemini"]["db_dir"]."/".$_GET["group"])) {
			if (!mkdir($GLOBALS["configuration_file"]["gemini"]["db_dir"]."/".$_GET["group"])) {
				echo "Fail: couldn't create local group directory;";
				
				exit;
			}
		}
		
		$db_path_to_download_to = $GLOBALS["configuration_file"]["gemini"]["db_dir"]."/".$_GET["group"];
		
		#############################################
		# DOES A DATABASE WITH THE FILENAME ALREADY EXIST
		#############################################
		
		// If the database already exists
		if (file_exists($db_path_to_download_to."/".basename($_GET["url"]))) {
			// If a parameter was passed for how to deal with existing data and it's not to fail
			if (isset($_GET["existing_data_behaviour"]) && $_GET["existing_data_behaviour"] != "fail") {
				// If the behaviour for existing data is to skip it (but return a success)
				if ($_GET["existing_data_behaviour"] == "skip") {
					echo "Success: existing data found so the database was not imported.";
					
					exit;
				// If the behaviour is to overwrite existing data
				} elseif ($_GET["existing_data_behaviour"] == "overwrite") {
					// Delete the existing database
					if (delete_database($_GET["group"]."/".basename($_GET["url"]))) {
						echo "Notice: deleted existing database due to the overwrite parameter;";
					} else {
						echo "Fail: problem deleting existing data";
						
						exit;
					}
				} else {
					echo "Fail: invalid parameter value passed for whether to delete existing data";
					
					exit;
				}
			// By default fail if the data already exists
			} else {
				echo "Fail: a database with that filename already exists";
				
				exit;
			}
		}
		
		#############################################
		# DOWNLOAD DATABASE
		#############################################
	
		$download_file_cmd = "wget --progress=dot:giga -O '".$db_path_to_download_to."/".basename($_GET["url"])."' '".$_GET["url"]."'";
		
		exec($download_file_cmd, $download_file_cmd_result, $exit_code);
				
		// If the download was not successful
		if (!isset($exit_code) || $exit_code != "0") {
			// Delete the non-completed DB if it wasn't fully downloaded
			unlink($db_path_to_download_to."/".basename($_GET["url"]));
			
			echo "Fail: could not download db";
			
			exit;
		}
	
		#############################################
		# IF AN MD5 WAS SENT, COMPARE THE MD5 CHECKSUM OF THE FILE TO THE SUBMITTED ONE
		#############################################
		
		if (isset($_GET["md5"])) {
			$downloaded_db_md5_hash = md5_file($db_path_to_download_to."/".basename($_GET["url"]));
			
			// If there was a problem generating an MD5 hash for the downloaded DB
			if ($downloaded_db_md5_hash === false) {
				unlink($db_path_to_download_to."/".basename($_GET["url"]));
				
				echo "Fail: problem generating MD5 hash for downloaded database";
				
				exit;
			}
			
			// If the submitted MD5 hash and the MD5 hash of the downloaded DB don't match
			if ($_GET["md5"] != $downloaded_db_md5_hash) {
				unlink($db_path_to_download_to."/".basename($_GET["url"]));
				
				echo "Fail: MD5 hash submitted didn't match to the one for the downloaded file";
				
				exit;
			}
		}
		
		#############################################
		# ANNOTATE WITH PED FILE IF SUBMITTED
		#############################################
		
		if (isset($_GET["pedfile_url"])) {
			// Check the URL exists
			if (!does_url_exist($_GET["pedfile_url"])) {
				echo "Warning: PED file url does not exist, will not annotate database;";
			// Make sure the file ends with .ped
			} elseif (!preg_match("/.ped$/", $_GET["pedfile_url"])) {
				echo "Warning: PED filename does not end with .ped, will not annotate database;";
			} else {
				// Download the PED file
				$download_file_cmd = "wget --progress=dot:giga -O '/tmp/".basename($_GET["pedfile_url"])."' '".$_GET["pedfile_url"]."'";
				
				exec($download_file_cmd, $download_file_cmd_result, $exit_code);
						
				// If the download was not successful
				if (!isset($exit_code) || $exit_code != "0") {
					// Delete the non-completed PED file if it wasn't fully downloaded
					unlink("/tmp/".basename($_GET["pedfile_url"]));
					
					echo "Warning: could not download PED file, will not annotate database;";
				} else {
					exec($GLOBALS["configuration_file"]["gemini"]["binary"].' amend --sample '.'/tmp/'.basename($_GET["pedfile_url"]).' '.$db_path_to_download_to.'/'.escape_database_filename(basename($_GET["url"])), $query_result, $exit_code); # Execute the Gemini query
		
					if ($exit_code == 0) {
						echo "Success: annotated GEMINI DB with supplied PED file;";
					} else {
						echo "Warning: could not annotate GEMINI DB with supplied PED file;";
					}
				}
			}
		}
		
		#############################################
		# GENERATE A DATABASE SUMMARY
		#############################################
	
		// Generate a database summary (don't check if it is successful or not)
		generate_db_summary($db_path_to_download_to."/".basename($_GET["url"]));
		
		// If the database to be imported is not a test, log the successful import
		if (!isset($_GET["test"])) {
			log_website_event("Imported a database from DX, database filename '".basename($_GET["url"])."' to '".$db_path_to_download_to."'");
		}
		
		echo "Success: imported GEMINI DB";
	
		#############################################
		# SMOKE TEST DELETION
		#############################################
		
		// If a test of the page is being performed
		if (isset($_GET["test"])) {
			// If the test is from Bamboo
			if ($_GET["test"] == "bamboo") {
				if (delete_database($_GET["group"]."/".basename($_GET["url"]))) {
					echo ";Success: deleted Bamboo smoke test database";
				} else {
					echo ";Fail: problem deleting the smoke test database";
				}
			}
		}
		
	#############################################
	# IF THE DATA BEING IMPORTED IS FOR THE GBS
	#############################################
	
	} elseif ($_GET["import_type"] == "GBS") {
		
		#############################################
		# ONLY ALLOW ONE GBS IMPORT AT ONCE
		#############################################
		
		// Note: the reason for this is that we have encountered errors with MySQL deadlocks and foreign key constraint fails where importing multiple methods for the same sample results in UPDATE and DELETE statements running at the same time and locking different tables or removing a key that is required by the other import; importing one at a time will mean these errors don't occur
		
		for ($i = 0; $i < 500; $i++) {
			// If a current GBS import is already in progress
			if (file_exists("/tmp/gbs_currently_importing_lock")) {
				// Wait for 5 seconds
				sleep(5);
			} else {
				// Create a temporary file indicating a GBS import is in progress
				if ($output = fopen("/tmp/gbs_currently_importing_lock", "w")) {
					fclose($output);
					
					// Break out of the for loop
					break;
				} else {
					echo "Fail: could not create temporary file indicating GBS import in progress";
					
					exit;
				}
			}
		}
		
		// If the number of delayed executions is 500 or greater (about 41 minutes of delays with a wait time of 5 seconds)
		if ($i > 499) {
			echo "Fail: too many delayed executions of importing data into the GBS, perhaps you launched too many long import jobs at once?";
				
			exit;
		}
		
		#############################################
		# MAKE SURE REQUIRED INFORMATION IS SUPPLIED
		#############################################
		
		if (!isset($_GET["token"]) || !isset($_GET["url"]) || !isset($_GET["method"])) {
			echo "Fail: missing required information";
			
			exit_and_delete_gbs_import_lock();
		}
		
		// Make sure a sample name has been supplied for the methods that need it
		if (!isset($_GET["sample_name"]) && in_array($_GET["method"], array("CNVnator", "ROHmer", "Sequenza", "PURPLE", "CNVkit"))) {
			echo "Fail: missing sample name for GBS method that requires one";
			
			exit_and_delete_gbs_import_lock();
		}
		
		// Make sure the sample name isn't empty
		if (isset($_GET["sample_name"]) && $_GET["sample_name"] == "") {
			echo "Fail: empty sample name";
			
			exit_and_delete_gbs_import_lock();
		}
		
		#############################################
		# VALIDATE TOKEN
		#############################################
	
		// GBS token
		if ($_GET["token"] != $GLOBALS["configuration_file"]["dx_import"]["gbs_import_token"]) {
			echo "Fail: bad token";
			
			exit_and_delete_gbs_import_lock();
		}
		
		#############################################
		# DOES THE URL END IN THE EXPECTED EXTENSION FOR THE METHOD
		#############################################
		
		if ($_GET["method"] == "CNVnator") {
			if (!preg_match("/.bed$/", $_GET["url"]) && !preg_match("/.bed.gz$/", $_GET["url"])) {
				echo "Fail: URL does not end with .bed/.bed.gz for CNVnator file import";
				
				exit_and_delete_gbs_import_lock();
			}
		} elseif ($_GET["method"] == "ROHmer") {
			if (!preg_match("/.bed$/", $_GET["url"]) && !preg_match("/.bed.gz$/", $_GET["url"])) {
				echo "Fail: URL does not end with .bed/.bed.gz for ROHmer file import";
				
				exit_and_delete_gbs_import_lock();
			}
		} elseif ($_GET["method"] == "Sequenza") {
			if (!preg_match("/segments.txt$/", $_GET["url"]) && !preg_match("/segments.txt.gz$/", $_GET["url"])) {
				echo "Fail: URL does not end with segments.txt/segments.txt.gz for Sequenza file import";
				
				exit_and_delete_gbs_import_lock();
			}
		} elseif ($_GET["method"] == "PURPLE") {
			if (!preg_match("/purple.cnv$/", $_GET["url"]) && !preg_match("/purple.cnv.gz$/", $_GET["url"])) {
				echo "Fail: URL does not end with purple.cnv/purple.cnv.gz for PURPLE file import";
				
				exit_and_delete_gbs_import_lock();
			}
		} elseif ($_GET["method"] == "VarpipeSV") {
			if (!preg_match("/.vcf$/", $_GET["url"]) && !preg_match("/.vcf.gz$/", $_GET["url"])) {
				echo "Fail: URL does not end with .vcf/.vcf.gz for VarpipeSV file import";
				
				exit_and_delete_gbs_import_lock();
			}
		} elseif ($_GET["method"] == "Manta") {
			if (!preg_match("/somaticSV.vcf$/", $_GET["url"]) && !preg_match("/somaticSV.vcf.gz$/", $_GET["url"])) {
				echo "Fail: URL does not end with somaticSV.vcf/somaticSV.vcf.gz for Manta file import";
				
				exit_and_delete_gbs_import_lock();
			}
		} elseif ($_GET["method"] == "CNVkit") {
			if (!preg_match("/.cns$/", $_GET["url"]) && !preg_match("/.cns.gz$/", $_GET["url"])) {
				echo "Fail: URL does not end with .cns/.cns.gz for CNVkit file import";
				
				exit_and_delete_gbs_import_lock();
			}
		} elseif ($_GET["method"] == "LUMPY") {
			if (!preg_match("/.vcf$/", $_GET["url"]) && !preg_match("/.vcf.gz$/", $_GET["url"])) {
				echo "Fail: URL does not end with .vcf/.vcf.gz for LUMPY file import";
				
				exit_and_delete_gbs_import_lock();
			}
		} else {
			echo "Fail: unknown method specified";
			
			exit_and_delete_gbs_import_lock();
		}
		
		#############################################
		# DOES THE URL EXIST
		#############################################
		
		if (!does_url_exist($_GET["url"])) {
			echo "Fail: url does not exist";
			
			exit_and_delete_gbs_import_lock();
		}
		
		#############################################
		# DOWNLOAD DATA
		#############################################
		
		// Set where the data should be downloaded to locally
		$download_data_path = "/tmp/".basename($_GET["url"]);
		
		// Shell command to execute to download the data
		$download_file_cmd = "wget -O '".$download_data_path."' '".$_GET["url"]."'";
		
		exec($download_file_cmd, $download_file_cmd_result, $exit_code);
				
		// If the download was unsuccessful
		if (!isset($exit_code) || $exit_code != "0") {
			// Delete the non-completed data if it wasn't fully downloaded
			unlink($download_data_path);
			
			echo "Fail: could not download data";
			
			exit_and_delete_gbs_import_lock();
		}
	
		#############################################
		# IF AN MD5 WAS SENT, COMPARE THE MD5 CHECKSUM OF THE FILE TO THE SUBMITTED ONE
		#############################################
		
		if (isset($_GET["md5"])) {
			$downloaded_data_md5_hash = md5_file($download_data_path);
			
			// If there was a problem generating an MD5 hash for the downloaded data
			if ($downloaded_data_md5_hash === false) {
				unlink($download_data_path);
				
				echo "Fail: problem generating MD5 hash for downloaded data";
				
				exit_and_delete_gbs_import_lock();
			}
			
			// If the submitted MD5 hash and the MD5 hash of the downloaded data don't match
			if ($_GET["md5"] != $downloaded_data_md5_hash) {
				unlink($download_data_path);
				
				echo "Fail: MD5 hash submitted didn't match to the one for the downloaded data";
				
				exit_and_delete_gbs_import_lock();
			}
		}
		
		#############################################
		# IF THE INPUT FILE IS GZIPPED, UNZIP IT
		#############################################
		
		if (preg_match("/.gz$/", $download_data_path)) {
			$ungzip_file_cmd = "gunzip --force ".$download_data_path; // --force to overwrite existing files; added after finding that files had not been deleted one time for some reason and future imports of the same data were failing with a gunzip problem
			
			exec($ungzip_file_cmd, $ungzip_file_cmd_result, $exit_code_gunzip);
			
			// If the gunzip was unsuccessful
			if (!isset($exit_code_gunzip) || $exit_code_gunzip != "0") {
				// Delete the downloaded data since it couldn't be gunzipped
				unlink($download_data_path);
				
				echo "Fail: could not gunzip data";
				
				exit_and_delete_gbs_import_lock();
			}
			
			// Remove the .gz extension from the downloaded data file as it has now been gunzipped
			$download_data_path = preg_replace("/.gz$/", "", $download_data_path);
		}
		
		#############################################
		# OPEN DATA FILE
		#############################################
		
		// Open the genome blocks file for parsing
		$genome_blocks_file = fopen($download_data_path, "r");
		
		// If the genome blocks file couldn't be opened
		if ($genome_blocks_file === false) {
			unlink($download_data_path);
			
			echo "Fail: can't open downloaded data for parsing";
			
			exit_and_delete_gbs_import_lock();
		}
		
		#############################################
		# EXTRACT SAMPLE NAMES FOR THE METHODS THAT REQUIRE IT
		#############################################
		
		if (in_array($_GET["method"], array("VarpipeSV", "Manta", "LUMPY"))) {
			$samples = gbs_parse_for_samples($genome_blocks_file);
			
			if ($samples === false || count($samples) == 0) {
				unlink($download_data_path);
				
				echo "Fail: couldn't extract sample names from input file";
				
				exit_and_delete_gbs_import_lock();
			}
		// If the sample name was supplied, save it to the same array structure for consistency
		} else {
			$samples[] = $_GET["sample_name"];
		}
		
		#############################################
		# ARE THE SAMPLES + METHOD ALREADY IN THE GBS
		#############################################
		
		foreach ($samples as $sample) {
			$already_in_gbs = is_sample_and_software_in_gbs($sample, $_GET["method"]);
			
			if ($already_in_gbs === false) {
				unlink($download_data_path);
				
				echo "Fail: problem determining if a sample and method are already in the GBS";
				
				exit_and_delete_gbs_import_lock();
			}
			
			if ($already_in_gbs === true) {
				// If a parameter was passed for how to deal with existing data and it's not to fail
				if (isset($_GET["existing_data_behaviour"]) && $_GET["existing_data_behaviour"] != "fail") {
					// If the behaviour for existing data is to skip it (but return a success)
					if ($_GET["existing_data_behaviour"] == "skip") {
						unlink($download_data_path);
						
						echo "Success: existing data found for at least one sample so no data was imported.";
					
						exit_and_delete_gbs_import_lock();
					// If the behaviour is to overwrite existing data
					} elseif ($_GET["existing_data_behaviour"] == "overwrite") {
						// Delete the existing GBS data
						if (!delete_blocks_gbs($sample, $_GET["method"])) {
							unlink($download_data_path);
							
							echo "Fail: problem deleting existing GBS data for sample ".$sample;
							
							exit_and_delete_gbs_import_lock();
						}
						
						echo "Notice: deleted existing GBS data for sample ".$sample." due to the overwrite parameter;";
					}
				// By default fail if the data already exists
				} else {
					unlink($download_data_path);
					
					echo "Fail: sample '".$sample."' and method already in the GBS";
				
					exit_and_delete_gbs_import_lock();
				}
			}
		}
		
		#############################################
		# PARSE DATA FILE BASED ON METHOD
		#############################################
		
		if ($_GET["method"] == "CNVnator") {
			// Parse the data file and save the blocks
			$genome_block_store = gbs_import_cnvnator($genome_blocks_file, $samples[0]);
		} elseif ($_GET["method"] == "ROHmer") {
			// Parse the data file and save the blocks
			$genome_block_store = gbs_import_rohmer($genome_blocks_file, $samples[0]);
		} elseif ($_GET["method"] == "Sequenza") {
			// Parse the data file and save the blocks
			$genome_block_store = gbs_import_sequenza($genome_blocks_file, $samples[0]);
		} elseif ($_GET["method"] == "PURPLE") {
			// Parse the data file and save the blocks
			$genome_block_store = gbs_import_purple($genome_blocks_file, $samples[0]);
		} elseif ($_GET["method"] == "CNVkit") {
			// Parse the data file and save the blocks
			$genome_block_store = gbs_import_cnvkit($genome_blocks_file, $samples[0]);
		} elseif ($_GET["method"] == "VarpipeSV") {
			// Parse the data file and save the blocks
			list($genome_block_store, $block_links, $unique_annotation_tags) = gbs_import_varpipesv($genome_blocks_file, $samples, "dx-import");
		} elseif ($_GET["method"] == "Manta") {
			// Parse the data file and save the blocks
			list($genome_block_store, $block_links, $unique_annotation_tags) = gbs_import_manta($genome_blocks_file, $samples, "dx-import");
		} elseif ($_GET["method"] == "LUMPY") {
			// Parse the data file and save the blocks
			list($genome_block_store, $block_links, $unique_annotation_tags) = gbs_import_lumpy($genome_blocks_file, $samples, "dx-import");
		}
		
		if (!isset($genome_block_store) || $genome_block_store === false) {
			echo "Fail: parsing failed, ".$_SESSION["gbs_import_error"];
			
			unset($_SESSION["gbs_import_error"]);
			
			unlink($download_data_path);
			
			exit_and_delete_gbs_import_lock();
		}
		
		// If no blocks were found
		if (count($genome_block_store) == 0) {
			echo "Fail: no data to import";
			
			unlink($download_data_path);
			
			exit_and_delete_gbs_import_lock();
		}
		
		fclose($genome_blocks_file);
		
		// Delete the parsed downloaded file
		unlink($download_data_path);
		
		#############################################
		# IMPORT THE GENOMIC BLOCKS INTO THE GBS
		#############################################
		
		// Add the sample names to the database if it they are not already present
		foreach ($samples as $sample) {
			if (add_sample_to_gbs($sample) === false) {
				echo "Fail: problem adding sample to GBS";
				
				exit_and_delete_gbs_import_lock();
			}
		}
		
		####################
		
		// Add any chromosomes to the GBS that aren't already in there
		if (!gbs_add_chromosomes($genome_block_store)) {
			echo "Fail: problem adding chromosome to GBS";
				
			exit_and_delete_gbs_import_lock();
		}
		
		####################
		
		// If annotation tags are present for the data
		if (isset($unique_annotation_tags)) {
			// Add the annotation tag(s) to the database if they have not been seen before
			if (add_annotation_tags_to_gbs($unique_annotation_tags) === false) {
				echo "Fail: problem adding annotation tag to the GBS";
				
				exit_and_delete_gbs_import_lock();
			}
		}
		
		####################
		
		if (isset($block_links)) {
			$gbs_store_result = gbs_store_blocks($genome_block_store, $_GET["method"], $block_links);
		} else {
			$gbs_store_result = gbs_store_blocks($genome_block_store, $_GET["method"], array());
		}
		
		if ($gbs_store_result === false) {
			// Go through each sample and delete imported blocks
			gbs_failed_import_roll_back($samples, $_GET["method"]);
			
			echo "Fail: There was a problem adding a genomic block to the GBS.";
			
			exit_and_delete_gbs_import_lock();
		}
		
		####################
		
		log_website_event("Automatically imported GBS blocks for sample(s) '".implode(", ", $samples)."' and method '".$_GET["method"]."'");
		
		echo "Success: imported GBS data";
		
		#############################################
		# SMOKE TEST DELETION
		#############################################
		
		// If a test of the page is being performed
		if (isset($_GET["test"])) {
			// If the test is from Bamboo
			if ($_GET["test"] == "bamboo") {
				if (gbs_failed_import_roll_back($samples, $_GET["method"])) {
					echo ";Success: deleted Bamboo smoke test GBS data";
					
					log_website_event("Automatically deleted smoke test GBS blocks for samples) '".implode(", ", $samples)."' and method '".$_GET["method"]."'");
				} else {
					echo ";Fail: problem deleting the smoke test GBS data";
				}
			}
		}
		
		#############################################
		
		exit_and_delete_gbs_import_lock();
	} else {
		echo "Fail: no/unknown import type supplied";
		
		exit;
	}
	
	#############################################
	# CUSTOM FUNCTIONS
	#############################################
		
	// Function to be used instead of exit for GBS imports
	function exit_and_delete_gbs_import_lock() {
		// Delete the file preventing other GBS imports
		unlink("/tmp/gbs_currently_importing_lock");
		
		exit;
	}
	
?>