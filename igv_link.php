<?php

// NOTE: this page MUST be loaded in http:// rather than https:// or it will not work!
// This is because most browsers will refuse to load the insecure http://localhost:60151... link from a secure page.
// IGV does not work with https://localhost:60151... link
// The .htaccess file gets around this by forcing Apache to load just this page with http:// and while this works on the servers, it does not seem to work locally with XAMPP on Vel's computer so this is just a note to that effect.

// Navigate to the IGV server
echo "<iframe src=\"http://localhost:60151/goto?locus=".$_GET["locus"]."\"></iframe>";

// Close the current window/tab
echo "<script type=\"text/javascript\" charset=\"utf8\">";
	echo "setTimeout(function(){ close(); }, 100)"; // 100 milliseconds until the tab closes gives the iframe enough time to load the URL
echo "</script>";

?>