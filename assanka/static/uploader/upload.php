<?php

if (!empty($_FILES)) {
	$dir = (isset($_POST["uploaddest"]) and preg_match("/^\/[a-z0-9\/]*$/i", $_POST["uploaddest"])) ? $_POST["uploaddest"] : "/lib/tmp/uploadedfiles";
	$dir = $_SERVER["DOCUMENT_ROOT"].$dir;

	if (!is_dir($dir) and !@mkdir($dir, 0777, true)) finish("Error #5: Unable to create directory (".$dir.")");
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
				@chmod($dir."/".$newname, 0766);
				finish($newname, filesize($dir."/".$newname));
			} else {
				finish("Error #2: Unable to move uploaded file");
			}
		}
	} else {
		finish("Error #3: No files were received");
	}
} elseif (!empty($_GET['showfile'])) {
	$_POST['type'] = 'legacy';
	$_POST['uplid'] = $_GET['uplid'];
	$_POST['uploaddest'] = $_GET['uploaddest'];
	$_POST['imagecapture'] = $_GET['imagecapture'];
	if (!empty($_GET['oldvalue'])) $_POST['oldvalue'] = $_GET['oldvalue'];
	if (empty($_GET['size']) or !is_numeric($_GET['size'])) {
		$fullpath = $_SERVER["DOCUMENT_ROOT"].$_GET['uploaddest'].'/'.$_GET['showfile'];
		$_GET['size'] = (is_file($fullpath)) ? filesize($fullpath) : 0;
	}
	finish($_GET['showfile'], $_GET['size']);
} else {
	?>
	<html>
	<head>
	<style type='text/css'>
		body { margin:0; padding:0; font-size: 12px; font-family: sans-serif; overflow:hidden; background-color: #f0f0f0 }
		a { text-decoration: none }
		a:hover { text-decoration: underline }
		#parinprog, #largefilewarn { display: none }
		#screencaplink { font-size: 11px }
	</style>
	<script type="text/javascript" src="/corestatic/javascript/jquery/1.4.1.js"></script>
	<script type="text/javascript">
	function dosubmit(msg, suppresswarning) {
		$('#frm, #screencaplink').hide();
		$('#parinprog').show();
		$('#progmsg').html(msg);
		$('#largefilewarn').hide();
		if (!suppresswarning) setTimeout(function() { $('#largefilewarn').show() }, 5000);
	}
	</script>
	</head>
	<body scroll='no'>
	<form id='frm' enctype='multipart/form-data' method='post' action='/corestatic/uploader/upload.php' onsubmit='dosubmit("Uploading, please wait,")'>
	<input type='hidden' name='type' value='legacy' />
	<input type='hidden' name='oldvalue' value='<?=@$_GET["value"]?>' />
	<input id='hiduplid' type='hidden' name='uplid' value='<?=@$_GET["uplid"]?>' />
	<input id='hiduploaddest' type='hidden' name='uploaddest' value='<?=$_GET["uploaddest"]?>' />
	<input id='filnewfile' type='file' name='newfile' onchange='dosubmit(); this.parentNode.submit()' />
	</form>
	<div id='parinprog'><span id="progmsg"></span>  <span id="largefilewarn">Uploading large files may take some time.</span></div>
	<?php
	if (!empty($_GET['imagecapture'])) {
		?>
		<div id="appletcont"></div>
		<script type="text/javascript">
			$(function() {
				if (navigator.javaEnabled()) {
					$("#filnewfile").after('<br/><a id="screencaplink" href="javascript:void(0)" onclick="doScreenCap()">or upload an image directly from your clipboard</a>');
				}
			});

			function doScreenCap() {
				$('#screencaplink').blur();
				if ($('#clipapplet').length) {
					$('#clipapplet').get(0).jsGetClipboardImage();
				} else {
					dosubmit("Loading Java applet, please wait...", true);
					setTimeout(function() {
						$('#appletcont').html('<applet archive="/corestatic/uploader/java/AssankaClipboardAccess.jar?1271681910,/corestatic/uploader/java/commons-codec.jar,/corestatic/uploader/java/commons-httpclient.jar,/corestatic/uploader/java/commons-logging.jar,/corestatic/uploader/java/commons-lang.jar" alt="Assanka Clipboard applet" name="AssankaClipboardApplet" width="0" height="0" code="ClipboardApplet" mayscript="true" id="clipapplet"><param name="error_code" value="E_TEST" /><param name="post_url" value="http<?php echo (!empty($_SERVER['HTTPS'])) ? 's':'' ?>://<?php echo $_SERVER['HTTP_HOST'] ?>/corestatic/uploader/java/receive?uploaddest=<?php echo $_GET['uploaddest'] ?>" /><param name="json_data" value="Base64encodedjson" /><param name="mayscript" value="yes" /><param name="scriptable" value="true" /><param name="name" value="AssankaClipboardApplet" /></applet>');
						appletReadyWait();
					}, 10);
				}

			}

			function appletReadyWait() {
				if (typeof document.getElementById('clipapplet').registerCallback == 'function') {
					document.getElementById('clipapplet').registerCallback('onBeginCapture', 'screencapBeginCapture');
					document.getElementById('clipapplet').registerCallback('onNoImage', 'screencapNoImage');
					document.getElementById('clipapplet').registerCallback('onBeginSend', 'screencapBeginSend');
					document.getElementById('clipapplet').registerCallback('onUploadComplete', 'screencapUploadComplete');
					document.getElementById('clipapplet').registerCallback('onError', 'screencapError');
					dosubmit("Reading your clipboard");
					document.getElementById('clipapplet').jsGetClipboardImage();
				} else {
					setTimeout(appletReadyWait, 500);
				}
			}

			function screencapBeginCapture() {
				$('#progmsg').html('Capturing image from clipboard');
			}

			function screencapBeginSend() {
				$('#progmsg').html('Uploading image');
			}

			function screencapUploadComplete(json) {
				eval("var result = "+json);
				if (result.size) {
					$('#progmsg').html('Done');
					location.href='/corestatic/uploader/upload.php?showfile=' + encodeURIComponent(result.filename) + '&size=' + result.size + '&uplid=<?php echo $_GET['uplid'] ?>&oldvalue=<?php echo $_GET['value'] ?>&uploaddest=<?php echo $_GET['uploaddest'] ?>&imagecapture=1';
				} else {
					screencapReset();
				}
			}

			function screencapNoImage() {
				screencapReset();
				alert("You do not appear to have an image on your clipboard.  If you are trying to take a screenshot, bring the error up on screen and press Print Screen (sometimes 'PrtSc'), then click the link to upload your image.");
			}

			function screencapError(e) {
				screencapReset();
				if (e && e != 'null') alert(e);
			}

			function screencapReset() {
				$('#frm, #screencaplink').show();
				$('#parinprog').hide();
			}
		</script>
		<?php
	}
	?>
	</body>
	</html>
	<?php
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

function finish($op, $size=false) {
	if (!empty($_POST['type']) and $_POST['type'] == 'legacy') {
		$uploaddest = (empty($_POST["uploaddest"])?"":$_POST["uploaddest"]);
		$uplid = (empty($_POST["uplid"])?"":$_POST["uplid"]);
		$imagecapture = (empty($_POST["imagecapture"])?"":$_POST["imagecapture"]);
		$oldvalue = (empty($_POST["oldvalue"])?"":$_POST["oldvalue"]);
		?>
		<html>
		<head>
		<style type='text/css'>
		body { margin:0; padding:0; font-size: 12px; font-family: sans-serif; overflow: hidden; background-color: #f0f0f0 }
		</style>
		<script type="text/javascript">
			var qry = "upload.php?uploaddest=<?=enc($uploaddest)?>&type=legacy&uplid=<?=enc($uplid)?>&imagecapture=<?=enc($imagecapture)?>";
			var uplid = "<?=enc($uplid)?>";
			var oldvalue = "<?=enc($oldvalue)?>";
			function bridge(id, op) {
				op = decodeURIComponent(op);
				if (op.indexOf('Error #')==0) {
					alert(op);
					location.href = qry+'&value='+oldvalue;
				} else {
					window.parent.document.getElementById(uplid).value=op;
				}
			}
		</script>
		</head>
		<body onload="bridge('<?=enc($uplid)?>', '<?=enc($op)?>')">
		<?php
			echo "<div style='float:left; margin-right: 5px; -moz-border-radius: 2px; -webkit-border-radius: 2px; border-radius: 2px; padding: 1px 4px; border: 1px solid #999; background-color: #f7f7f7; line-height:20px; max-width: 300px;'>".$op;
			if ($size) {
				echo ", ";
				if ($size < 1024) {
					echo $size."b";
				} elseif ($size < 1024^2) {
					echo round($size/1024, 2)."K";
				} elseif ($size < 1024^3) {
					echo round($size/(1024^2), 2)."MB";
				}
			}
			?></div><span style="line-height: 20px">  [<a href="javascript:void(0)" onclick="location.href=qry+'&value=<?=enc($op)?>'">Change</a>]</span><?php
		?>
		</body>
		</html>
		<?php
	} else {
		echo $op;
	}
}

function enc($str) {
	return str_replace("'", "%27", rawurlencode($str));
}
?>