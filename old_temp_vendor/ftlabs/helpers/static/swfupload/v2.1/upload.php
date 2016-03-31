<?php
$dir = (isset($_POST["desturi"]) and preg_match("/^\/[a-z0-9\/]*$/i", $_POST["desturi"])) ? $_POST["desturi"] : "/lib/tmp/uploadedfiles";
$dir = $_SERVER["DOCUMENT_ROOT"].$dir;

if (!is_dir($dir) and !@mkdir($dir, 0777, true)) finish("Error #5: Unable to create directory");
if (!is_writable($dir)) finish("Error #1: Unable to use directory");

if (!empty($_FILES["newfile"]) and (empty($_FILES["newfile"]["error"]) or $_FILES["newfile"]["error"] != UPLOAD_ERR_NO_FILE)) {
	if (!empty($_FILES["newfile"]["error"]) and $_FILES["newfile"]["error"] == UPLOAD_ERR_INI_SIZE) {
		finish("Error #6: The size of the uploaded file exceeds the limit set on this server.  Contact the server administrator for more details or to increase the limit");
	} elseif (!empty($_FILES["newfile"]["error"]) and $_FILES["newfile"]["error"] == UPLOAD_ERR_FORM_SIZE) {
		finish("Error #7: The size of the uploaded file exceeds the limit set on this website.  Contact the server administrator for more details or to increase the limit");
	} elseif (!empty($_FILES["newfile"]["error"]) and $_FILES["newfile"]["error"] == UPLOAD_ERR_PARTIAL) {
		finish("Error #8: The file was only partially received.  You may be able to upload the file successfully if you try again");
	} else {
		$newname = uniqueFilename($dir, $_FILES["newfile"]["name"]);
		if (@move_uploaded_file($_FILES['newfile']['tmp_name'], $dir."/".$newname)) {
			@chmod($dir."/".$newname, 0777);
			finish($newname);
		} else {
			finish("Error #2: Unable to move uploaded file");
		}
	}
} else {
	finish("Error #3: No files were received");
}


function uniqueFilename($path, $name) {
	if (preg_match("/^(.*)(\([0-9]+\))?\.([^\.]+)$/Ui", $name, $matches)) {
		$newname = $matches[1].".".$matches[3];
		for ($i=1; file_exists($path."/".$newname); $i++) {
			$newname = $matches[1]."(".$i.").".$matches[3];
		}
		return $newname;
	} else {
		$newname = $name;
		for ($i=1; file_exists($path."/".$newname); $i++) {
			$newname = $name."(".$i.")";
		}
		return $newname;
	}
}

function finish($op) {
	if (!empty($_POST['type']) and $_POST['type'] == 'legacy') {
		?>
		<html>
		<head>
		<style type='text/css'>
		body { margin:0; padding:0; font-size: 12px; font-family: sans-serif; overflow: hidden; background-color: #f0f0f0 }
		</style>
		</head>
		<body onload="parent.FileUploader.bridge(<?=$_POST['uplid']?>, '<?=rawurlencode($op)?>')">
		<p><?=$op?></p>
		</body>
		</html>
		<?php
	} else {
		echo $op;
	}
}

?>