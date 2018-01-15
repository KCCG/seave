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
				<h2>Need to make some changes?</h2>
				<p>The user administration options below let you add, remove, view and modify users and groups to your heart's content.</p>
			</header>
			<?php
				// If the user is not logged in or an administrator
				if (!is_user_administrator()) {
					error("You do not have permission to view this page.");
					
					echo "<a href=\"home\" class=\"button\">Home</a>";
				} else {
					
					#############################################
					# FETCH USER & GROUP INFORMATION
					#############################################
					
					// Fetch all users, groups and users in groups from the DB
					$account_information = fetch_account_all_information();
	
					if ($account_information === false) {
						error("Problem fetching Seave users and groups. Please try again.");
					}
					
					#############################################
					# SUCCESS/ERROR MESSAGES
					#############################################
					
					if_set_display_error("user_admin_no_access", "You do not have access to make user administration changes.");
					if_set_display_error("user_admin_cant_fetch_account_information", "There was a problem fetching Seave account information for modification, please try again.");
					
					if_set_display_error("user_admin_cant_find_user", "The user specified doesn't seem to exist.");
					if_set_display_error("user_admin_cant_find_group", "The group specified doesn't seem to exist.");
					if_set_display_error("user_admin_cant_make_change_in_db", "There was a problem making the change requested in the database.");
					
					if_set_display_error("user_admin_add_group_name_length", "The group name you entered must be between 1 and 50 characters long.");
					if_set_display_error("user_admin_add_group_name_invalid_characters", "The group name can only contain letters and numbers (no spaces)");
					if_set_display_error("user_admin_add_group_description_length", "The group description you entered is too long.");
					if_set_display_error("user_admin_add_group_description_invalid_characters", "The group description can only contains letters, numbers and spaces.");
					if_set_display_error("user_admin_add_group_already_exists", "The group name you specified already exists.");
					if_set_display_error("user_admin_add_group_cant_create_diretory", "There was a problem creating the directory to hold the databases.");
					
					if_set_display_error("user_admin_delete_group_cant_delete_group_not_present_locally", "The group specified to delete is not present on the version of Seave you are using.");
					if_set_display_error("user_admin_delete_group_databases_present", "You cannot delete a group with databases in it. Move them out first.");
					
					if_set_display_error("user_admin_add_user_email_invalid", "The email address you entered is not a valid email address.");
					if_set_display_error("user_admin_add_user_password_empty", "You must enter a password for the new user.");
					if_set_display_error("user_admin_add_user_already_exists", "The email address you have specified is already registered for an account.");
					
					if_set_display_error("user_admin_delete_user_last_administrator", "You can't remove a user who is the only administrator.");
					
					if_set_display_error("user_admin_add_user_to_group_user_already_in_group", "The user you selected is already in the group specified.");
					
					if_set_display_error("user_admin_remove_user_from_group_user_not_in_group", "The user you selected to remove from a group is not in that group.");
					
					if_set_display_error("user_admin_change_password_password_empty", "You did not submit a new password for the user.");
					
					if_set_display_error("user_admin_remove_administrator_user_not_administrator", "The user you selected is not an administrator.");
					if_set_display_error("user_admin_remove_administrator_no_other_administrators", "You can't remove administrator access from the only administrator.");
					
					if_set_display_error("user_admin_make_administrator_user_already_administrator", "The user you selected is already an administrator.");
					
					//if_set_display_error("user_admin_", "");
					
					if (isset($_SESSION["user_admin_add_group_success"])) { if_set_display_success("user_admin_add_group_success", "Successfully added your group '".$_SESSION["user_admin_add_group_success"]."'"); }
					if (isset($_SESSION["user_admin_delete_group_success"])) { if_set_display_success("user_admin_delete_group_success", "Successfully deleted the group '".$_SESSION["user_admin_delete_group_success"]."'"); }
					if (isset($_SESSION["user_admin_add_user_success"])) { if_set_display_success("user_admin_add_user_success", "Successfully added new user '".$_SESSION["user_admin_add_user_success"]."'"); }
					if (isset($_SESSION["user_admin_delete_user_success"])) { if_set_display_success("user_admin_delete_user_success", "Successfully deleted user '".$_SESSION["user_admin_delete_user_success"]."'"); }
					if (isset($_SESSION["user_admin_add_user_to_group_success"])) { if_set_display_success("user_admin_add_user_to_group_success", "Successfully added the user ".$_SESSION["user_admin_add_user_to_group_success"]); }
					if (isset($_SESSION["user_admin_remove_user_from_group_success"])) { if_set_display_success("user_admin_remove_user_from_group_success", "Successfully removed the user '".$_SESSION["user_admin_remove_user_from_group_success"]."'"); }
					if (isset($_SESSION["user_admin_change_password_success"])) { if_set_display_success("user_admin_change_password_success", "Successfully changed the password for '".$_SESSION["user_admin_change_password_success"]."'"); }
					if (isset($_SESSION["user_admin_remove_administrator_success"])) { if_set_display_success("user_admin_remove_administrator_success", "Successfully removed administrator '".$_SESSION["user_admin_remove_administrator_success"]."'"); }
					if (isset($_SESSION["user_admin_make_administrator_success"])) { if_set_display_success("user_admin_make_administrator_success", "Successfully granted administrator access to '".$_SESSION["user_admin_make_administrator_success"]."'"); }
					
					//if (isset($_SESSION["user_admin_"])) { if_set_display_success("user_admin_", ": ".$_SESSION["user_admin_"]); }
					
					#############################################
					# VIEW REQUESTED USERS IN GROUP INFORMATION
					#############################################
					
					if (isset($_GET["view_users_in_group"])) {
						success("Showing all users in the group: \"".$account_information["groups"][$_GET["view_users_in_group"]]["group_name"]."\"");
							
						users_in_group_table($_GET["view_users_in_group"], $account_information);
					}
					
					#############################################
					# ADD A GROUP FORM
					#############################################
					
					echo "<div class=\"row\">";
						echo "<section class=\"6u 12u(narrower)\">";
							echo "<h2>Add a group</h2>";	
							
							echo "<p>To add an account group you will need a group name and description. The group name must be unique within Seave and <strong>consist only of letters and numbers.</strong></p>";

							echo "<form action=\"actions/action_user_administration\" method=\"post\">";
								echo "<div class=\"row 50%\">";
									echo "<div class=\"12u\">";
										echo "<h4>Group name</h4>";
										echo "<input type=\"text\" name=\"group_name\" maxlength=\"50\" placeholder=\"E.g. Smith\">";
									echo "</div>";
									echo "<div class=\"12u\">";
										echo "<h4>Short description</h4>";
										echo "<input type=\"text\" name=\"group_description\" maxlength=\"100\" placeholder=\"E.g. Smith Lab\">";
									echo "</div>";
								echo "</div>";
								echo "<div class=\"row 50%\">";
									echo "<div class=\"12u\">";
										echo "<ul class=\"actions\">";
											echo "<li><input type=\"submit\" value=\"Add group\" /></li>";
										echo "</ul>";
									echo "</div>";
								echo "</div>";
							echo "</form>";
						echo "</section>";
						
						#############################################
						# ADD A USER FORM
						#############################################
						
						echo "<section class=\"6u 12u(narrower)\">";
							echo "<h2>Add a user</h2>";	
							
							echo "<p>To add a user you will need a valid email address and password for the user. The email address must be unique within Seave.</p>";

							echo "<form action=\"actions/action_user_administration\" method=\"post\">";
								echo "<div class=\"row 50%\">";
									echo "<div class=\"12u\">";
										echo "<h4>Email address</h4>";
										echo "<input type=\"email\" name=\"add_email\" maxlength=\"255\" placeholder=\"E.g. john@smith.com\">";
									echo "</div>";
									echo "<div class=\"12u\">";
										echo "<h4>Password</h4>";
										echo "<input type=\"password\" name=\"add_password\" placeholder=\"E.g. Dw3Bd7Etin\">";
									echo "</div>";
								echo "</div>";
								echo "<div class=\"row 50%\">";
									echo "<div class=\"12u\">";
										echo "<ul class=\"actions\">";
											echo "<li><input type=\"submit\" value=\"Add user\" /></li>";
										echo "</ul>";
									echo "</div>";
								echo "</div>";
							echo "</form>";
						echo "</section>";
					echo "</div>";

					#############################################
					# REMOVE A GROUP FORM
					#############################################
					
					echo "<div class=\"row\">";
						echo "<section class=\"6u 12u(narrower)\">";
						
							echo "<h2 style=\"padding-top:10px;\">Remove a group</h2>";
							
							echo "<p><strong>A group can only be removed when there are no databases within it</strong>. Removing a group will not remove any users, it is your responsibility to ensure no users are left without any group memberships.</p>";
							
							echo "<form action=\"actions/action_user_administration\" method=\"post\">";
								echo "<div class=\"row 50%\">";
									echo "<div class=\"12u\">";
										echo "<h4>Select a group</h4>";
										echo "<select name=\"delete_group\">";
											// Go through each group
											foreach (array_keys($account_information["groups"]) as $group_id) {
												echo "<option value=\"".$account_information["groups"][$group_id]["group_name"]."\">".$account_information["groups"][$group_id]["group_name"]."</option>";
											}
										echo "</select>";
									echo "</div>";
								echo "</div>";
								echo "<div class=\"row 50%\">";
									echo "<div class=\"12u\">";
										echo "<ul class=\"actions\">";
											echo "<li><input type=\"submit\" value=\"Remove group\" /></li>";
										echo "</ul>";
									echo "</div>";
								echo "</div>";
							echo "</form>";
						echo "</section>";
						
						#############################################
						# REMOVE A USER FORM
						#############################################
					
						echo "<section class=\"6u 12u(narrower)\">";
							
							echo "<h2 style=\"padding-top:10px;\">Remove a user</h2>";
							
							echo "<p>Removing a user means they will no longer have access to Seave and all of their group memberships will be removed.</p>";
							
							echo "<form action=\"actions/action_user_administration\" method=\"post\" enctype=\"multipart/form-data\">";
								echo "<div class=\"row 50%\">";
									echo "<div class=\"12u\">";
										echo "<h4>Select a user</h4>";
										echo "<select name=\"delete_user\">";
											// Go through each user
											foreach (array_keys($account_information["users"]) as $user_id) {
												echo "<option value=\"".$account_information["users"][$user_id]["email"]."\">".$account_information["users"][$user_id]["email"]."</option>";
											}
										echo "</select>";
									echo "</div>";		
								echo "</div>";
								echo "<div class=\"row 50%\">";
									echo "<div class=\"12u\">";
										echo "<ul class=\"actions\">";
											echo "<li><input type=\"submit\" value=\"Remove user\"></li>";
										echo "</ul>";
									echo "</div>";
								echo "</div>";
							echo "</form>";
						echo "</section>";	
					echo "</div>";
					
					#############################################
					# ADD A USER TO A GROUP FORM
					#############################################
					
					echo "<div class=\"row\">";
						echo "<section class=\"6u 12u(narrower)\">";
						
							echo "<h2 style=\"padding-top:10px;\">Add a user to a group</h2>";
							
							echo "<p>Associate a user with a group here. The user will be able to see and query all databases within the group immediately upon being added.</p>";
							
							echo "<form name=\"add_groups_per_user\" action=\"actions/action_user_administration\" method=\"post\">";
								echo "<div class=\"row 50%\">";
									echo "<div class=\"12u\">";
										echo "<h4>Select a user</h4>";
										echo "<select name=\"add_user_to_group_user\" size=\"5\" onChange=\"updateGroupsAdd(this.selectedIndex)\">";
											// Go through each user
											foreach (array_keys($account_information["users"]) as $user_id) {
												echo "<option value=\"".$account_information["users"][$user_id]["email"]."\">".$account_information["users"][$user_id]["email"]."</option>";
											}
										echo "</select>";
									echo "</div>";
									
									echo "<div class=\"12u\">";
										echo "<h4>Group(s) available for selected user</h4>";
										echo "<select name=\"add_user_to_group_group\" size=\"5\"></select>"; // Populated by Javascript below
									echo "</div>";
								echo "</div>";
								
								echo "<div class=\"row 50%\">";
									echo "<div class=\"12u\">";
										echo "<ul class=\"actions\">";
											echo "<li><input type=\"submit\" value=\"Add user to group\" /></li>";
										echo "</ul>";
									echo "</div>";
								echo "</div>";
							echo "</form>";
							
							// Create required Javascript variables
							$javascripts .= "var groupslistAdd=document.add_groups_per_user.add_user_to_group_group; ";
							$javascripts .= "var usersAdd=new Array(); ";
							
							// Counter for the creating an index of the users
							$user_count = 0;
							
							// Create a Javascript array to populate the groups select box
							foreach (array_keys($account_information["users"]) as $user_id) {
								// Make an array of groups per user
								$javascripts .= "usersAdd[".$user_count."]=[";
								
								// Go through each group
								foreach (array_keys($account_information["groups"]) as $group_id) {
									// If the current group does not have any users, or the current user is not in the current group
									if (!isset($account_information["users_in_groups"]["users_per_group"][$group_id]) || !in_array($user_id, array_keys($account_information["users_in_groups"]["users_per_group"][$group_id]))) {
										$javascripts .= "\"".$account_information["groups"][$group_id]["group_name"]."\", ";
									}
								}
								
								// If any groups were added the for current user
								if (substr($javascripts, -1) != "[") {
									$javascripts = substr($javascripts, 0, -2); // Remove the last ", " that was added by the loop above
								}
								
								$javascripts .= "]; ";
								
								$user_count++;
							}
							
							// Javascript function to display the groups relevant to a clicked user name
							$javascripts .= "function updateGroupsAdd(selecteduser) {";
								$javascripts .= "groupslistAdd.options.length=0;";
							        $javascripts .= "for (i=0; i<usersAdd[selecteduser].length; i++) {";
							        	$javascripts .= "groupslistAdd.options[groupslistAdd.options.length]=new Option(usersAdd[selecteduser][i], usersAdd[selecteduser][i]);";
									$javascripts .= "}";
							$javascripts .= "}";
						echo "</section>";

						#############################################
						# REMOVE A USER FROM A GROUP
						#############################################
						
						echo "<section class=\"6u 12u(narrower)\">";
							echo "<h2 style=\"padding-top:10px;\">Remove a user from a group</h2>";
							
							echo "<p>Remove a user from a group here. The user will no longer be able to see and query databases within the group.</p>";
							
							echo "<form name=\"remove_groups_per_user\" action=\"actions/action_user_administration\" method=\"post\">";
								echo "<div class=\"row 50%\">";
									echo "<div class=\"12u\">";
										echo "<h4>Select a user</h4>";
										echo "<select name=\"remove_user_from_group_user\" size=\"5\" onChange=\"updateGroupsRemove(this.selectedIndex)\">";
											// Go through each user
											foreach (array_keys($account_information["users"]) as $user_id) {
												echo "<option value=\"".$account_information["users"][$user_id]["email"]."\">".$account_information["users"][$user_id]["email"]."</option>";
											}
										echo "</select>";
									echo "</div>";
									
									echo "<div class=\"12u\">";
										echo "<h4>Group(s) for user selected</h4>";
										echo "<select name=\"remove_user_from_group_group\" size=\"5\"></select>"; // Populated by Javascript below
									echo "</div>";
								echo "</div>";
								
								echo "<div class=\"row 50%\">";
									echo "<div class=\"12u\">";
										echo "<ul class=\"actions\">";
											echo "<li><input type=\"submit\" value=\"Remove user from group\" /></li>";
										echo "</ul>";
									echo "</div>";
								echo "</div>";
							echo "</form>";
							
							// Create required Javascript variables
							$javascripts .= "var groupslistRemove=document.remove_groups_per_user.remove_user_from_group_group; ";
							$javascripts .= "var usersRemove=new Array(); ";
							
							// Counter for the creating an index of the users
							$user_count = 0;
							
							// Create a Javascript array to populate the groups select box
							foreach (array_keys($account_information["users"]) as $user_id) {
								// Make an array of groups per user
								$javascripts .= "usersRemove[".$user_count."]=[";
								
								// If the current user is in any groups
								if (isset($account_information["users_in_groups"]["groups_per_user"][$user_id])) {
									// Go through each group the user is in and add it to the JS array
									foreach (array_keys($account_information["users_in_groups"]["groups_per_user"][$user_id]) as $group_id) {
										$javascripts .= "\"".$account_information["groups"][$group_id]["group_name"]."\", ";
									}
									
									$javascripts = substr($javascripts, 0, -2); // Remove the last ", " that was added by the loop above
								}
								
								$javascripts .= "]; ";
								
								$user_count++;
							}
							
							// Javascript function to display the groups relevant to a clicked user name
							$javascripts .= "function updateGroupsRemove(selecteduser) {";
								$javascripts .= "groupslistRemove.options.length=0;";
							        $javascripts .= "for (i=0; i<usersRemove[selecteduser].length; i++) {";
							        	$javascripts .= "groupslistRemove.options[groupslistRemove.options.length]=new Option(usersRemove[selecteduser][i], usersRemove[selecteduser][i]);";
									$javascripts .= "}";
							$javascripts .= "}";
						echo "</section>";
					echo "</div>";
						
					#############################################
					# CHANGE USER'S PASSWORD FORM
					#############################################
					
					echo "<div class=\"row\">";
						echo "<section class=\"6u 12u(narrower)\">";
						
							echo "<h2 style=\"padding-top:10px;\">Change a user's password</h2>";
							
							echo "<p>All passwords on Seave are stored securely as a salted hash. If a user needs a password changed, change it here and notify them of the change.</p>";
							
							echo "<form action=\"actions/action_user_administration\" method=\"post\">";
								echo "<div class=\"row 50%\">";
									echo "<div class=\"12u\">";
									echo "<h4>Select a user</h4>";
										echo "<select name=\"change_password_user\">";
											// Go through each user
											foreach (array_keys($account_information["users"]) as $user_id) {
												echo "<option value=\"".$account_information["users"][$user_id]["email"]."\">".$account_information["users"][$user_id]["email"]."</option>";
											}
										echo "</select>";
									echo "</div>";
									
									echo "<div class=\"12u\">";
										echo "<h4>New password</h4>";
										echo "<input type=\"password\" name=\"change_password_new_password\" placeholder=\"E.g. Dw3Bd7Etin\">";
									echo "</div>";
									
									echo "<div class=\"12u\">";
										echo "<ul class=\"actions\">";
											echo "<li><input type=\"submit\" value=\"Change password\" /></li>";
										echo "</ul>";
									echo "</div>";
								echo "</div>";
							echo "</form>";
						echo "</section>";
						
						#############################################
						# VIEW ALL USERS IN A GROUP FORM
						#############################################
						
						echo "<section class=\"6u 12u(narrower)\">";
						
							echo "<h2 style=\"padding-top:10px;\">View all users in a group</h2>";
							
							echo "<p>Select the group for which you would like to see all users. You will be able to see when each user was added to the group.</p>";
							
							echo "<form action=\"user_administration\" method=\"get\">";
								echo "<div class=\"row 50%\">";
									echo "<div class=\"12u\">";
										echo "<h4>Select a group</h4>";
										echo "<select name=\"view_users_in_group\">";
											// Go through each group
											foreach (array_keys($account_information["groups"]) as $group_id) {
												echo "<option value=\"".$group_id."\">".$account_information["groups"][$group_id]["group_name"]."</option>";
											}
										echo "</select>";
									echo "</div>";
								echo "</div>";
								
								echo "<div class=\"row 50%\">";
									echo "<div class=\"12u\">";
										echo "<ul class=\"actions\">";
											echo "<li><input type=\"submit\" value=\"View group members\" /></li>";
										echo "</ul>";
									echo "</div>";
								echo "</div>";
							echo "</form>";
						echo "</section>";
					echo "</div>";
					
					#############################################
					# MAKE USER ADMINISTRATOR
					#############################################
					
					echo "<div class=\"row\">";
						echo "<section class=\"6u 12u(narrower)\">";
						
							echo "<h2 style=\"padding-top:10px;\">Make a user an administrator</h2>";
							
							echo "<p>A Seave administrator is able to make changes to users, gene lists, genome blocks and all variant databases. Select a user below who you would like to grant administrator access to Seave.</p>";
							
							echo "<form action=\"actions/action_user_administration\" method=\"post\">";
								echo "<div class=\"row 50%\">";
									echo "<div class=\"12u\">";
									echo "<h4>Select a non-administrator user</h4>";
										echo "<select name=\"make_administrator_user\">";
											// Go through each user
											foreach (array_keys($account_information["users"]) as $user_id) {
												// If the current user is not an administrator
												if ($account_information["users"][$user_id]["is_administrator"] == "0") {
													echo "<option value=\"".$account_information["users"][$user_id]["email"]."\">".$account_information["users"][$user_id]["email"]."</option>";
												}
											}
										echo "</select>";
									echo "</div>";
									
									echo "<div class=\"12u\">";
										echo "<ul class=\"actions\">";
											echo "<li><input type=\"submit\" value=\"Make administrator\" /></li>";
										echo "</ul>";
									echo "</div>";
								echo "</div>";
							echo "</form>";
						echo "</section>";
						
						#############################################
						# REMOVE USER'S ADMINISTRATOR STATUS
						#############################################
						
						echo "<section class=\"6u 12u(narrower)\">";
						
							echo "<h2 style=\"padding-top:10px;\">Remove administrator access</h2>";
							
							echo "<p>Select a user below whose administrator access you would like to remove. The user will no longer be able to make major changes to Seave but will be able to query the databases in the groups in which they belong.</p>";
							
							echo "<form action=\"actions/action_user_administration\" method=\"post\">";
								echo "<div class=\"row 50%\">";
									echo "<div class=\"12u\">";
										echo "<h4>Select an administrator user</h4>";
										echo "<select name=\"delete_administrator_user\">";
											// Go through each user
											foreach (array_keys($account_information["users"]) as $user_id) {
												// If the current user is an administrator
												if ($account_information["users"][$user_id]["is_administrator"] == "1") {
													echo "<option value=\"".$account_information["users"][$user_id]["email"]."\">".$account_information["users"][$user_id]["email"]."</option>";
												}
											}
										echo "</select>";
									echo "</div>";
								echo "</div>";
								
								echo "<div class=\"row 50%\">";
									echo "<div class=\"12u\">";
										echo "<ul class=\"actions\">";
											echo "<li><input type=\"submit\" value=\"Remove administrator access\" /></li>";
										echo "</ul>";
									echo "</div>";
								echo "</div>";
							echo "</form>";
						echo "</section>";
					echo "</div>";
				}

			?>
		</article>
		
	</div>
</div>

<?php
	
	require 'html_footer.php';
?>