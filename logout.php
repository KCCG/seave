<?php
	session_start(); # Enable sessions
	
	require 'php_header.php'; // Require the PHP header housing required PHP functions

	if (is_user_logged_in()) {
		clean_session("logout");
	}
	
	header('Location: home');
?>