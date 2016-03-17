<?php
	header("Content-Type: text/javascript");
	
	// If there are any project-specific files, this is where to insert them
	if (file_exists($_SERVER["DOCUMENT_ROOT"]."/lib/inc/fckconfig.js")) include($_SERVER["DOCUMENT_ROOT"]."/lib/inc/fckconfig.js");
?>