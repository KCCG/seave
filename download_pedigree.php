<?php
	if (isset($_POST["content"], $_POST["filename"])) {
		header('Content-Type: text/tsv; charset=utf-8');
		header('Content-Disposition: attachment; filename='.htmlspecialchars($_POST["filename"], ENT_QUOTES, 'UTF-8').'.ped');

		echo htmlspecialchars($_POST["content"], ENT_QUOTES, 'UTF-8');
	}
?>
