#!/usr/bin/perl

use strict;
use warnings;

# For interacting with the Selenium Web Driver server
use Selenium::Remote::Driver;
# Note: the Selenium server must be running with java -jar /path/to/selenium-server-standalone-2.53.0.jar

# For downloading results files
use LWP::Simple;
# Make sure to install Mozilla::CA from cpan if using a URL with https if you get an error like this: "500 Can't verify SSL peers without knowing which Certificate Authorities to trust"

####################################

# Create Selenium driver object
my $driver = Selenium::Remote::Driver->new;
#my $driver = Selenium::Remote::Driver->new(
#	'browser_name' => 'firefox'
#);

# When searching for elements, poll up to this long in ms to find them
$driver->set_implicit_wait_timeout(10000);

####################################
# Define global variables

# Hashes to store query parameters
my %query_parameters_javascript;
my %query_parameters_click;
my %query_parameters_value;

# Stores the path and filename of the output XML file
my $xml_output_file;

# Store the login information used to run the tests
my $seave_login_username;
my $seave_login_password;

# Stores whether a fatal resting error has occurred meaning no further tests can be run, either for all tests or for just a single Seave query
my $fatal_testing_error_flag = 0;
my $fatal_seave_query_testing_error_flag = 0;

# Stores the number of Seave queries executed for outputting as test case numbers
my $seave_query_count = 1;

# Capture script arguments
if (scalar(@ARGV) != 3) {
	die "FATAL ERROR: three arguments must be specified: 1) XML output path and filename 2) Seave login username 3) Seave login password.";
} else {
	$xml_output_file = $ARGV[0];
	$seave_login_username = $ARGV[1];
	$seave_login_password = $ARGV[2];
}

####################################
# Create and start populating the output XML file

open(XMLOUT, ">$xml_output_file") or die "Cannot open file \"$xml_output_file\" for writing."; 

print XMLOUT '<?xml version="1.0" encoding="UTF-8" ?>'."\n";

print XMLOUT '<testsuites>'."\n";

####################################
# Test suite to log in and navigate to the databases page

print XMLOUT "\t".'<testsuite name="log_in_and_navigate_to_databases">'."\n";
	print XMLOUT "\t\t".'<testcase name="navigate_to_seave">'."\n";
		# Navigate to the Seave home page
		if (!navigate_to_page('https://dev.seave.bio')) {
			print_fatal_testcase("Could not navigate to https://dev.seave.bio");
		}
	print XMLOUT "\t\t".'</testcase>'."\n";
	
	print XMLOUT "\t\t".'<testcase name="click_log_in_link">'."\n";
		# Click the "Log In" link in the main menu
		if (!find_and_click_page_element('link_text', 'Log In')) {
			print_fatal_testcase("Could not find 'Log In' link or click it on the home page");
		}	
	print XMLOUT "\t\t".'</testcase>'."\n";
	
	print XMLOUT "\t\t".'<testcase name="fill_in_and_submit_log_in_form">'."\n";
		# Find the email element and input an email
		if (!find_and_input_values_into_page_element('name', 'email', $seave_login_username)) {
			print_fatal_testcase("Could not input the email into the log in form");
		}
		
		# Find the password name element and input a password
		if (!find_and_input_values_into_page_element('name', 'password', $seave_login_password)) {
			print_fatal_testcase("Could not input the password into the log in form");
		}
		
		# Submit the log in form
		if (!find_and_submit_form("actions/action_log_in")) {
			print_fatal_testcase("Could not submit the log in form");
		}
	print XMLOUT "\t\t".'</testcase>'."\n";
	
	print XMLOUT "\t\t".'<testcase name="navigate_to_databases">'."\n";
		# Find the 'Take me to the data' button and click it
		if (!find_and_click_page_element('link_text', 'Take me to the data')) {
			print_fatal_testcase("Could not find and/or click the button to navigate to databases - most likely the login failed");
		}	
	print XMLOUT "\t\t".'</testcase>'."\n";
print XMLOUT "\t".'</testsuite>'."\n";

####################################
# Set query parameters to apply to all queries

add_query_parameter_javascript("min_cadd", "15");
add_query_parameter_javascript("num_variants", "1000");
add_query_parameter_javascript("min_qual", "200");

#add_query_parameter_click("xpath", '//label[@for="radiomedhighimpact"]');
add_query_parameter_click("xpath", '//label[@for="exclude_failed_variants"]');

#add_query_parameter_value("name", "genes", "BRCA1");

####################################
# Execute queries, modifying gene lists where needed

add_unique_gene_list_query_parameter("Neuromuscular Orphanet May 2015");
execute_query("MND1and2.db", "Bur", "het_dom", 14);
execute_query("MND1and2.db", "Al-H", "hom_rec", 5);
execute_query("MND1and2.db", "Burg", "denovo_dom", 1);
execute_query("MND1and2.db", "Kah", "hom_rec", 2);
execute_query("MND1and2.db", "Mew", "hom_rec", 1);
execute_query("MND1and2.db", "Rif", "hom_rec", 4);
execute_query("MND1and2.db", "Zub", "hom_rec", 8);

add_unique_gene_list_query_parameter("Craniofacial Orphanet May 2015");
execute_query("CLP_total.vep.db", "SA", "het_dom", 2);
execute_query("CLP_total.vep.db", "4527", "het_dom", 0);

add_unique_gene_list_query_parameter("Multiple Congenital Anomalies Nijmegen Mar 2014");
execute_query("CLP_total.vep.db", "4527", "het_dom", 78);

add_unique_gene_list_query_parameter("ID Gilissen 2014");
execute_query("CLP_total.vep.db", "BCDS-11", "denovo_dom", 1);

$driver->quit;

####################################

# Close off the XML output file

print XMLOUT '</testsuites>'."\n";

close XMLOUT;

print "Testing complete.\n";

####################################

exit;
























####################################
# Function to execute a query from the databases page through to the results TSV

sub execute_query {
	my ($database, $family, $analysis, $expected_variants) = @_;
	
	# If a fatal error has occurred previously, set the fatal query flag to true
	if ($fatal_testing_error_flag == 1) {
		$fatal_seave_query_testing_error_flag = 1;
	}
	
	print XMLOUT "\t".'<testsuite name="seave_query_'.$seave_query_count.'">'."\n";
		print XMLOUT "\t\t".'<properties>'."\n";
			# Replace " with the XML-friendly &quot;
			(my $xml_friendly_database = $database) =~ s/"/&quot;/g;
			(my $xml_friendly_family = $family) =~ s/"/&quot;/g;
			(my $xml_friendly_analysis = $analysis) =~ s/"/&quot;/g;
			
			print XMLOUT "\t\t\t".'<property name="database" value="'.$xml_friendly_database.'"/>'."\n";
			print XMLOUT "\t\t\t".'<property name="family" value="'.$xml_friendly_family.'"/>'."\n";
			print XMLOUT "\t\t\t".'<property name="analysis_type" value="'.$xml_friendly_analysis.'"/>'."\n";
			
			# Go through the set of JavaScript parameters to print the parameters for the current query
			foreach my $target (keys %query_parameters_javascript) {
				foreach my $value (@{$query_parameters_javascript{$target}}) {
					# Replace " with the XML-friendly &quot;
					(my $xml_friendly_target = $target) =~ s/"/&quot;/g;
					(my $xml_friendly_value = $value) =~ s/"/&quot;/g;
					
					print XMLOUT "\t\t\t".'<property name="javascript_'.$xml_friendly_target.'" value="'.$xml_friendly_value.'"/>'."\n";
				}
			}
	
			# Go through the set of click parameters to print the parameters for the current query
			foreach my $target (keys %query_parameters_click) {
				foreach my $value (@{$query_parameters_click{$target}}) {
					# Replace " with the XML-friendly &quot;
					(my $xml_friendly_target = $target) =~ s/"/&quot;/g;
					(my $xml_friendly_value = $value) =~ s/"/&quot;/g;

					print XMLOUT "\t\t\t".'<property name="click_'.$xml_friendly_target.'" value="'.$xml_friendly_value.'"/>'."\n";
				}
			}
	
			# Go through the set of input value parameters to print the parameters for the current query
			foreach my $category (keys %query_parameters_value) {
				foreach my $target (@{$query_parameters_value{$category}}) {
					foreach my $value (@{$query_parameters_value{$category}{$target}}) {
						# Replace " with the XML-friendly &quot;
						(my $xml_friendly_category = $category) =~ s/"/&quot;/g;
						(my $xml_friendly_target = $target) =~ s/"/&quot;/g;
						(my $xml_friendly_value = $value) =~ s/"/&quot;/g;

						print XMLOUT "\t\t\t".'<property name="value_'.$xml_friendly_category.'_'.$xml_friendly_target.'" value="'.$xml_friendly_value.'"/>'."\n";
					}
				}
			}
		print XMLOUT "\t\t".'</properties>'."\n";
		
		print XMLOUT "\t\t".'<testcase name="navigate_to_database">'."\n";
			# Find a specific database in the databases table and click it
			if (!find_and_click_page_element('xpath', '//table[@id="db_information"]//tr//td[@title="'.$database.'"]')) {
				print_fatal_seave_query_testcase("Could not find the database on the databases page");
			}
		print XMLOUT "\t\t".'</testcase>'."\n";

		print XMLOUT "\t\t".'<testcase name="select_family">'."\n";
			# Find a specific family in the database and click it
			if (!find_and_click_page_element('xpath', '//label[@for="family_'.$family.'"]')) {
				print_fatal_seave_query_testcase("Could not find the family on the analysis selection page");
			}
		print XMLOUT "\t\t".'</testcase>'."\n";

		print XMLOUT "\t\t".'<testcase name="select_analysis_type">'."\n";
			# Select a specific analysis type for the family
			if (!find_and_click_page_element('xpath', '//label[@for="'.$family.'analysis_'.$analysis.'"]')) {
				print_fatal_seave_query_testcase("Could not find the analysis type on the analysis selection page");
			}
		print XMLOUT "\t\t".'</testcase>'."\n";

		print XMLOUT "\t\t".'<testcase name="submit_analysis_type">'."\n";
			# Find the form element and submit the form
			if (!find_and_submit_form("actions/action_analysis_types")) {
				print_fatal_seave_query_testcase("Could not find and/or submit the analysis selection form");
			}
		print XMLOUT "\t\t".'</testcase>'."\n";
		
		# If Javascript query parameters exist
		if (keys %query_parameters_javascript > 0) {
			print XMLOUT "\t\t".'<testcase name="modify_javascript_elements">'."\n";
				# Go through the set of JavaScript parameters to apply to the query
				foreach my $target (keys %query_parameters_javascript) {
					foreach my $value (@{$query_parameters_javascript{$target}}) {
						if (!execute_javascript_on_current_page("javascript:document.getElementById(\"".$target."\").value=".$value.";")) {
							print_fatal_seave_query_testcase("Could not modify Javascript element '".$target."' with value '".$value."'");
						}
					}
				}
			print XMLOUT "\t\t".'</testcase>'."\n";
		}
		
		# If click query parameters exist
		if (keys %query_parameters_click > 0) {
			print XMLOUT "\t\t".'<testcase name="click_html_elements">'."\n";
				# Go through the set of click parameters to apply to the query
				foreach my $target (keys %query_parameters_click) {
					foreach my $value (@{$query_parameters_click{$target}}) {
						if (!find_and_click_page_element($target, $value)) {
							print_fatal_seave_query_testcase("Could not find and/or click HTML element '".$target."' with value '".$value."'");
						}
					}
				}
			print XMLOUT "\t\t".'</testcase>'."\n";
		}
		
		# If input value query parameters exist
		if (keys %query_parameters_value > 0) {
			print XMLOUT "\t\t".'<testcase name="input_value_elements">'."\n";
				# Go through the set of input value parameters to apply to the query
				foreach my $category (keys %query_parameters_value) {
					foreach my $target (@{$query_parameters_value{$category}}) {
						foreach my $value (@{$query_parameters_value{$category}{$target}}) {
							if (!find_and_input_values_into_page_element($category, $target, $value)) {
								print_fatal_seave_query_testcase("Could not find and/or input value '".$value."' into HTML element '".$target."' (category '".$category."')");
							}
						}
					}
				}
			print XMLOUT "\t\t".'</testcase>'."\n";
		}
		
		print XMLOUT "\t\t".'<testcase name="submit_query">'."\n";
			# Find the form element and submit the form
			if (!find_and_submit_form("actions/action_run_query")) {
				print_fatal_seave_query_testcase("Could not find and/or submit the query form");
			}
		print XMLOUT "\t\t".'</testcase>'."\n";
		
		print XMLOUT "\t\t".'<testcase name="variant_count_as_expected">'."\n";
			# Fetch the "Showing x to y of z entries" text under the results table
			my $num_variants = extract_text_from_page_element("//div[\@class='dataTables_info']");
			
			if (!$num_variants) {
				print_fatal_seave_query_testcase("Could not find the number of variants returned on the results page");
			}
			
			# Extract the number of variants found
			if ($num_variants =~ /.*?([0-9]*)\sentries$/) {
				$num_variants = $1;
			} else {
				print_fatal_seave_query_testcase("Could not find the number of variants returned in the table rows string");
			}
			
			# Compare the number of variants found with what is expected
			if ($expected_variants != $num_variants) {
				print_fatal_seave_query_testcase("The number of variants found does not match the expected value");
			}
			
			print XMLOUT "\t\t\t".'<system-out>'."\n";
				print XMLOUT "\t\t\t\t".'Results URL: '.$driver->get_current_url.''."\n";
				print XMLOUT "\t\t\t\t".'Expecting '.$expected_variants.' variants and found '.$num_variants.''."\n";
			print XMLOUT "\t\t\t".'</system-out>'."\n";	
		print XMLOUT "\t\t".'</testcase>'."\n";
		
		print XMLOUT "\t\t".'<testcase name="reset_query">'."\n";
			# Click the start over button to go back to the databases, this is a forced call so it will run even if one of the above failed
			if (!find_and_click_page_element('link_text', 'Databases', 'forced')) {
				# This is actually fatal as the next Seave query expects to be on the databases page
				print_fatal_testcase("Could not find or click the Databases link to go back to the databases page");
			}
		print XMLOUT "\t\t".'</testcase>'."\n";
	print XMLOUT "\t".'</testsuite>'."\n";		

	# Iterate the number of Seave queries performed
	$seave_query_count++;
	
	# Reset the fatal flag for Seave queries
	$fatal_seave_query_testing_error_flag = 0;
}

####################################
# Function to download the results file and validate the number of lines

# Commented out as there is no need to parse the results file itself yet
=begin comment

sub parse_results {
	my ($results_url) = @_;
	
	my $results_domain;
	my $results_filename;
	
	if ($results_url =~ /^(http.*?)\/results\?query=(.*)/) { # Expecting something like https://dev.seave.bio/results?query=2016_05_23_15_23_50_873fda7e74a428228e3717201328644b
		$results_domain = $1."/temp/";;
		$results_filename = $2.".tsv";
		
		# Download the results file
		my $results = get($results_domain.$results_filename);
		
		# Split the results into an array by line
		my @lines = split(/\n/, $results, -1); # The -1 here allows multiple delimiters after each other to still trigger as empty elements http://stackoverflow.com/questions/3711649/perl-split-with-empty-text-before-after-delimiters
		
		# Flag for whether the last variant has been reached
		my $last_variant_reached = 0;
		
		# The number of variants found
		my $num_variants = 0;
		
		for (my $i = 0; $i < scalar(@lines); $i++) {
			# Ignore the header line and any lines after the last variant has been reached
			if ($i == 0 || $last_variant_reached == 1) {
				next;
			}
			
			if (length($lines[$i]) == 0) {
				$last_variant_reached = 1;
				
				next;
			}
			
			$num_variants++;
		}

		print "Results page URL: ".$results_url."\n";
		print "Results TSV URL: ".$results_domain.$results_filename."\n";
		print "Number of variants found: ".$num_variants."\n";
		
		return 1;
	} else {
		return 0;
	}
}

=cut

####################################
# Function to find an element on the current page and click it

sub find_and_click_page_element {
	my ($locator, $target, $forced) = @_;
	
	# If a fatal testing error has occurred, quit without even trying to run the test UNLESS only a Seave query error occurred and we are being forced to run it regardless
	if ($fatal_testing_error_flag == 1 || ($fatal_seave_query_testing_error_flag == 1 && !defined($forced))) {
		return 0;
	}
	
	# Run the action on the driver
	eval {
		my $element = $driver->find_element($target, $locator);
	
		$element->click();
	};
	
	# If the action failed
	if($@) {
		# Debugging:
		#print "Error: $@\n";
		
		return 0;
	} else {
		return 1;
	}
}

####################################
# Function to find an element on the current page and input a value into it

sub find_and_input_values_into_page_element {
	my ($locator, $target, $input_value) = @_;

	# If a fatal testing error has occurred, quit without even trying to run the test
	if ($fatal_testing_error_flag == 1 || $fatal_seave_query_testing_error_flag == 1) {
		return 0;
	}
	
	# Run the action on the driver
	eval {
		my $element = $driver->find_element($target, $locator);
		
		$element->send_keys($input_value);
	};
	
	# If the action failed
	if($@) {
		# Debugging:
		#print "Error: $@\n";
		
		return 0;
	} else {
		return 1;
	}
}

####################################
# Function to find a form and submit it

sub find_and_submit_form {
	my ($form_action) = @_;

	# If a fatal testing error has occurred, quit without even trying to run the test
	if ($fatal_testing_error_flag == 1 || $fatal_seave_query_testing_error_flag == 1) {
		return 0;
	}
	
	# Run the action on the driver
	eval {
		my $element = $driver->find_element('//form[@action="'.$form_action.'"]', 'xpath');
		
		# Submit the form
		$element->submit();
	};
	
	# If the action failed
	if($@) {
		# Debugging:
		#print "Error: $@\n";
		
		return 0;
	} else {
		return 1;
	}
}

####################################
# Function to execute JavaScript on the current page

sub execute_javascript_on_current_page {
	my ($javascript) = @_;

	# If a fatal testing error has occurred, quit without even trying to run the test
	if ($fatal_testing_error_flag == 1 || $fatal_seave_query_testing_error_flag == 1) {
		return 0;
	}
	
	# Run the action on the driver
	eval {
		$driver->execute_script($javascript);
	};
	
	# If the action failed
	if($@) {
		# Debugging:
		#print "Error: $@\n";
		
		return 0;
	} else {
		return 1;
	}
}

####################################
# Function to navigate to a specific URL

sub navigate_to_page {
	my ($url) = @_;

	# If a fatal testing error has occurred, quit without even trying to run the test
	if ($fatal_testing_error_flag == 1 || $fatal_seave_query_testing_error_flag == 1) {
		return 0;
	}
	
	# Run the action on the driver
	eval {
		$driver->get($url);
	};
	
	# If the action failed
	if($@) {
		# Debugging:
		#print "Error: $@\n";
		
		return 0;
	} else {
		return 1;
	}
}

####################################
# Function to get the text from a particular element

sub extract_text_from_page_element {
	my ($element) = @_;
	
	# Stores the extracted text
	my $text; 
	
	# If a fatal testing error has occurred, quit without even trying to run the test
	if ($fatal_testing_error_flag == 1 || $fatal_seave_query_testing_error_flag == 1) {
		return 0;
	}
	
	# Run the action on the driver
	eval {
		$text = $driver->get_text($element);
	};
	
	# If the action failed
	if($@) {
		# Debugging:
		#print "Error: $@\n";
		
		return 0;
	} else {
		return $text;
	}
}

####################################
# Function to add a JavaScript query parameter to the query

sub add_query_parameter_javascript {
	my ($target, $value) = @_;
	
	push (@{$query_parameters_javascript{$target}}, $value);
	
	return 1;
}

####################################
# Function to add a click parameter to the query

sub add_query_parameter_click {
	my ($target, $value) = @_;
	
	push (@{$query_parameters_click{$target}}, $value);
	
	return 1;
}

####################################
# Function to add a value parameter to the query

sub add_query_parameter_value {
	my ($category, $target, $value) = @_;
	
	push (@{$query_parameters_value{$category}{$target}}, $value);
	
	return 1;
}

####################################
# Function to modify query parameters to use a specific gene list

sub add_unique_gene_list_query_parameter {
	my ($gene_list) = @_;
	
	# Go through every target
	foreach my $target (keys %query_parameters_click) {
		# Go through every value by array id
		for (my $i = 0; $i < scalar(@{$query_parameters_click{$target}}); $i++) {
			# If the value is a gene list selection
			if (@{$query_parameters_click{$target}}[$i] =~ /gene_list_selection/) {
				# Delete the array element with the gene list
				delete(@{$query_parameters_click{$target}}[$i]);
			}
		}
	}
	
	add_query_parameter_click("xpath", '//select[@id="gene_list_selection"]//option[@value="'.$gene_list.'"]');
}

####################################
# Function to print a failed testcase when a fatal error occurs for ALL tests

sub print_fatal_testcase {
	my ($failed_description) = @_;
	
	# If this is the first fatal error, print the failed description
	if ($fatal_testing_error_flag == 0) {
		print XMLOUT "\t\t\t".'<failure>'.$failed_description.'</failure>'."\n";
		
		$fatal_testing_error_flag = 1;
	# If this isn't the first fatal error, mark the test as skipped
	} elsif ($fatal_testing_error_flag == 1 || $fatal_seave_query_testing_error_flag == 1) {
		print XMLOUT "\t\t\t".'<skipped/>'."\n";
	}
}

####################################
# Function to print a failed testcase when a fatal error occurs within a single Seave query

sub print_fatal_seave_query_testcase {
	my ($failed_description) = @_;
	
	# If this is the first fatal error, print the failed description
	if ($fatal_seave_query_testing_error_flag == 0) {
		print XMLOUT "\t\t\t".'<failure>'.$failed_description.'</failure>'."\n";
		
		$fatal_seave_query_testing_error_flag = 1;
	}
}
