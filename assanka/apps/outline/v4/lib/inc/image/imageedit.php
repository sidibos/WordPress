<?php
/**
 * Displays an editor interface, providing live previews of some
 * transformations using javascript. On submission, parses the data
 * and passes to imagetools.php
 *
 * @copyright The Financial Times Limited [All Rights Reserved]
 */

require_once("../global");

// First check to see if a form submision is occurring
if (!empty($_POST["path"])) {
	include("imagetools.php");
	
	if (!empty($_POST["overwritefile"])) $overwriteFile = true;
	else $overwriteFile = false;
	
	if (!empty($_POST["percentwidth"]) and empty($_POST["pixelwidth"])) {
		$_POST["pixelwidth"] = ($_POST["originalwidth"] - $_POST["cropleft"] - $_POST["cropright"])*($_POST["percentwidth"]/100);
		$_POST["pixelheight"] = ($_POST["originalheight"] - $_POST["croptop"] - $_POST["cropbottom"])*($_POST["percentheight"]/100);
	} else if (!empty($_POST["pixelwidth"]) and empty($_POST["percentwidth"])) {
		$_POST["percentwidth"] = ($_POST["pixelwidth"] * 100)/($_POST["originalwidth"] - $_POST["cropleft"] - $_POST["cropright"]);
		$_POST["percentheight"] = ($_POST["pixelheight"] * 100)/($_POST["originalheight"] - $_POST["croptop"] - $_POST["cropbottom"]);
	}
	if (!empty($_POST["autosharpen"])) {
		$_POST["sharpenlevel"] = 0;
		if (($_POST["percentwidth"] < 100) || ($_POST["percentheight"] < 100)) $_POST["sharpenlevel"]++;
		$_POST["sharpenlevel"] += (200 - $_POST["percentwidth"] - $_POST["percentheight"])/100;
	}
	
	$_POST["dir"] = (($_POST["dir"] == ".")?"":$_POST["dir"]."/");
	$loadcoreCheck = strpos($_POST["dir"], "loadcore");
	if ($loadcoreCheck !== false) $_POST["dir"] = CORE_PATH.substr($_POST["dir"], $loadcoreCheck + 9);
	
	if ($_POST["submit"] == "Save") {
		$newFileName = imageProcess($_POST["path"], $_POST["dir"], $_POST["filename"], false, false, $overwriteFile, round($_POST["croptop"]), round($_POST["cropright"]), round($_POST["cropbottom"]), round($_POST["cropleft"]), round($_POST["pixelwidth"]), round($_POST["pixelheight"]), 12000, 12000, $_POST["fileformat"], $_POST["sharpenlevel"], 80);
		if ($newFileName == false) trigger_error("An error occurred while trying to process an image; original ".$_POST["path"].", new ".$_POST["dir"].$POST["filename"].".", E_USER_ERROR);
	}
	header("Location: ".$_POST["refreshto"]);
	exit();
}

?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="en" xml:lang="en">
<head>
	<title>Outline Image Editor</title>
	<meta http-equiv="Content-Type" content="text/html; charset=ISO-8859-1" />
	<link rel="stylesheet" type="text/css" href="imagecss" title="Screen Style" media="screen" />
</head>
<body onresize = "redisplayImage();">
<div id="theimageframe">
<?php
	$infoText = "";
	$imagePath = $imageURL = $_GET["imageurl"];
	if (strpos($imagePath, "//") !== false) {
		$imagePath = substr($imagePath, strpos($imagePath, "//") + 2);
		if (strpos($imagePath, "/") !== false) $imagePath = substr($imagePath, strpos($imagePath, "/"));
	}
	$loadcoreCheck = strpos($imagePath, "loadcore");
	if ($loadcoreCheck !== false) $imagePath = CORE_PATH.substr($imagePath, $loadcoreCheck + 9);
	else $imagePath = $_SERVER['DOCUMENT_ROOT'].$imagePath;
	$fileCheck = true;
	if (!file_exists($imagePath)) {
		$fileCheck = false;
		$imageSize = array(400,300);
	} else {
		$imageSize = @getimagesize($imagePath);
		if ($imageSize === false) $fileCheck = false;
	}
	if (!$fileCheck) {
		?>
		<div style="text-align: left;" id="theimage">
		<h1>An error has occurred</h1>
		The image requested could not be read.  This may be because the image is in the wrong format, or because the requested image could not be found.  This image editor is capable of working with images in GIF, JPEG (JPG), PNG or Windows Bitmap (BMP) format.  If you believe that this error should not have occurred, please file a support request noting the file path: <?=$imagePath?> (url was <?=$imageURL?>) .
		</div>
		<?php
	} else {
		$fileParts = pathinfo($imagePath);
		$fileParts["basename"] = substr($fileParts["basename"], 0, strlen($fileParts["basename"])-(strlen($fileParts["extension"])+1));
		echo ("<img src=\"".$imageURL."\" alt=\"Preview of edits on ".$fileParts["basename"].".".$fileParts["extension"]."\" id=\"theimage\" />");
	}
?>
</div>
<div id="infopane">
<div id="infobar">
&nbsp;
</div>
</div>
<div id="toolpane">
<div id="tools">
<form method="post" action="imageedit.php">
<h4>Crop</h4>
<div>
<input type="hidden" name="path" value="<?=$imagePath?>" />
<input type="hidden" name="refreshto" value="<?=$_GET["refreshto"]?>" />
<input type="hidden" name="dir" value="<?=$fileParts["dirname"]?>" />
<input type="hidden" name="originalwidth" value="<?=$imageSize[0]?>" />
<input type="hidden" name="originalheight" value="<?=$imageSize[1]?>" />
<label for="cropleftinput" class="formlabel">Left:</label>
<input type="text" name="cropleft" id="cropleftinput" value="0" class="textinput" onkeyup="updateCrop('left');" onblur="updateCrop('left');" />
<span class="infotext">Max: <span id="maxcropleft"><?=($imageSize[0]-1)?></span></span><br class="clear" />
<label for="croprightinput" class="formlabel">Right:</label>
<input type="text" name="cropright" id="croprightinput" value="0" class="textinput" onkeyup="updateCrop('right');" onblur="updateCrop('right');" />
<span class="infotext">Max: <span id="maxcropright"><?=($imageSize[0]-1)?></span></span><br class="clear" />
<label for="croptopinput" class="formlabel">Top:</label>
<input type="text" name="croptop" id="croptopinput" value="0" class="textinput" onkeyup="updateCrop('top');" onblur="updateCrop('top');" />
<span class="infotext">Max: <span id="maxcroptop"><?=($imageSize[1]-1)?></span></span><br class="clear" />
<label for="cropbottominput" class="formlabel">Bottom:</label>
<input type="text" name="cropbottom" id="cropbottominput" value="0" class="textinput" onkeyup="updateCrop('bottom');" onblur="updateCrop('bottom');" />
<span class="infotext">Max: <span id="maxcropbottom"><?=($imageSize[1]-1)?></span></span><br class="clear" />
<br />
</div>
<h4>Resize</h4>
<div>
<div class="vbuffer"><input type="checkbox" name="lockaspect" id="lockaspectcheckbox" checked="checked" class="inset" />
<label for="lockaspectcheckbox">Lock Aspect Ratio</label></div>
<div class="vbuffer"><input type="radio" name="resizemethod" id="resizebypixels" value="pixels" checked="checked" class="inset" onclick="toggleResizeInterface();" />
<label for="resizebypixels">New Size</label></div>
<label for="pixelwidthinput" class="formlabel">Width:</label>
<input type="text" name="pixelwidth" id="pixelwidthinput" value="<?=$imageSize[0]?>" class="textinput" onkeyup="updateSize('pixelwidth',false);" onblur="updateSize('pixelwidth',false);" />
<span class="infotext" style="margin-left: 5px;"><acronym title='A pixel is one of the dots, or squares, that make up a computer display.'>(pixels)</acronym></span><br class="clear" />
<label for="pixelheightinput" class="formlabel">Height:</label>
<input type="text" name="pixelheight" id="pixelheightinput" value="<?=$imageSize[1]?>" class="textinput" onkeyup="updateSize('pixelheight',false);" onblur="updateSize('pixelheight',false);" />
<span class="infotext" style="margin-left: 5px;"><acronym title='A pixel is one of the dots, or squares, that make up a computer display.'>(pixels)</acronym></span><br class="clear" />
<div class="vbuffer"><input type="radio" name="resizemethod" id="resizebypercent" value="percent" class="inset" onclick="toggleResizeInterface();" />
<label for="resizebypercent">Percentage of Original</label></div>
<label for="percentwidthinput" class="formlabel">Width:</label>
<input type="text" name="percentwidth" id="percentwidthinput" value="100" class="textinput" disabled="disabled" onkeyup="updateSize('percentwidth',false);" onblur="updateSize('percentwidth',false);" />
<span class="inputaligned">%</span><br class="clear" />
<label for="percentheightinput" class="formlabel">Height:</label>
<input type="text" name="percentheight" id="percentheightinput" value="100" class="textinput" disabled="disabled" onkeyup="updateSize('percentheight',false);" onblur="updateSize('percentheight',false);" />
<span class="inputaligned">%</span><br class="clear" />
<br />
</div>
<h4>Sharpen</h4>
<div>
<div class="vbuffer"><input type="checkbox" name="autosharpen" id="autosharpencheckbox" checked="checked" class="inset" onclick="toggleSharpen();" />
<label for="autosharpencheckbox">Automatic</label></div>
<label for="sharpenlevelinput" class="formlabel">Level:</label>
<select name="sharpenlevel" id="sharpenlevelinput" disabled="disabled">
<option value="0">None</option>
<option value="1">Light</option>
<option value="2">Moderate</option>
<option value="3">Heavy</option>
<option value="4">Extreme</option>
</select>
<br /><br />
</div>
<h4>Save</h4>
<div>
<label for="fileformatinput" class="formlabel">Format:</label>
<select name="fileformat" id="fileformatinput">
<option value="jpg">JPEG</option>
<option value="png"<?=((!strcasecmp($fileParts["extension"], "png") || !strcasecmp($fileParts["extension"], "gif"))?" selected=\"selected\"":"")?>>PNG</option>
</select>
<div class="vbuffer"><input type="checkbox" name="generatefilename" id="generatefilenamecheckbox" checked="checked" class="inset" onclick="toggleFilename();" />
<label for="generatefilenamecheckbox">Generate Unique Name</label></div>
<span style="display: none;" id="filenamespan">
<label for="filenameinput" class="formlabel">Name:</label>
<input type="text" name="filename" id="filenameinput" value="<?=$fileParts["basename"]?>" class="textinput" style="width: 115px;" /><br />
<input type="checkbox" name="overwritefile" id="overwritefilecheckbox" class="inset" style="margin-left: 25px;" />
<label for="overwritefilecheckbox">Overwrite File</label></span><br />
<input type="submit" name="submit" id="submitbutton" value="Save" class="button" onclick="if(restoreHeight) window.resizeTo(restoreWidth, restoreHeight); if(restoreX) window.moveTo(restoreX, restoreY);" />
<input type="submit" name="submit" value="Cancel" class="button" onclick="if(restoreHeight) window.resizeTo(restoreWidth, restoreHeight); if(restoreX) window.moveTo(restoreX, restoreY);" />
</div>
</form>
</div>
</div>
<script type="text/javascript">
	var origImageSize = new Array(<?=$imageSize[0]?>,<?=$imageSize[1]?>);
	var associatedInfo = new Array();
	associatedInfo['top'] = new Array('bottom', 1, 'height');
	associatedInfo['right'] = new Array('left', 0, 'width');
	associatedInfo['bottom'] = new Array('top', 1, 'height');
	associatedInfo['left'] = new Array('right', 0, 'width');
	associatedInfo['pixelwidth'] = new Array('pixels', 'percentwidth', 0, 'left', 'right', 'pixelheight');
	associatedInfo['pixelheight'] = new Array('pixels', 'percentheight', 1, 'top', 'bottom', 'pixelwidth');
	associatedInfo['percentwidth'] = new Array('percent', 'pixelwidth', 0, 'left', 'right', 'percentheight');
	associatedInfo['percentheight'] = new Array('percent', 'pixelheight', 1, 'top', 'bottom', 'percentwidth');
	var infoSize = 100;
	
	// Store all the values in javascript, for better handling of things like
	// empty fields, and improved updating of linked fields.
	var crop = new Array();
	crop['top'] = new Array (0,0);
	crop['right'] = new Array (0,0);
	crop['bottom'] = new Array (0,0);
	crop['left'] = new Array (0,0);
	var imageSize = new Array();
	imageSize['pixelwidth'] = new Array(<?=$imageSize[0]?>, <?=$imageSize[0]?>);
	imageSize['pixelheight'] = new Array(<?=$imageSize[1]?>, <?=$imageSize[1]?>);
	imageSize['percentwidth'] = new Array(100, 100);
	imageSize['percentheight'] = new Array(100, 100);
	var restoreHeight = 0;
	var restoreWidth = 0;
	var restoreX = 0;
	var restoreY = 0;

	function toggleSharpen() {
		document.getElementById('sharpenlevelinput').disabled = document.getElementById('autosharpencheckbox').checked;
		autoSharpen();
	}
	function toggleResizeInterface() {
		document.getElementById('percentwidthinput').disabled = document.getElementById('resizebypixels').checked;
		document.getElementById('percentheightinput').disabled = document.getElementById('resizebypixels').checked;
		document.getElementById('pixelwidthinput').disabled = document.getElementById('resizebypercent').checked;
		document.getElementById('pixelheightinput').disabled = document.getElementById('resizebypercent').checked;
	}
	function toggleFilename() {
		if (document.getElementById('generatefilenamecheckbox').checked) {
			document.getElementById('overwritefilecheckbox').checked = false;
			document.getElementById('filenameinput').value = '<?=urlencode($fileParts["basename"])?>';
			document.getElementById('filenamespan').style.display = 'none';
		} else {
			document.getElementById('filenamespan').style.display = 'block';
		}
	}
	function updateCrop(callingField) {
		opposingField = associatedInfo[callingField];
		callingInput = document.getElementById('crop'+callingField+'input');
		// First replace error fields with 0, to prevent odd errors.
		if ((callingInput.value == '') || isNaN(callingInput.value)) {
			imageSize['pixel'+opposingField[2]][1] = imageSize['pixel'+opposingField[2]][1] + crop[callingField][1];
			crop[callingField][1] = crop[callingField][0] = 0;
		} else {
			imageSize['pixel'+opposingField[2]][1] = crop[callingField][0] + imageSize['pixel'+opposingField[2]][1] - parseInt(callingInput.value);
			crop[callingField][1] = crop[callingField][0] = parseInt(callingInput.value);
		}
		newMaxValue = origImageSize[opposingField[1]] - crop[callingField][0] - 1;
		if (newMaxValue - crop[opposingField[0]][0] < 0) {
			if (newMaxValue < 0) newMaxValue = 0;
			crop[callingField][0] = 0;
			imageSize['pixel'+opposingField[2]][1] = imageSize['pixel'+opposingField[2]][1] + parseInt(callingInput.value);
			document.getElementById('maxcrop'+callingField).setAttribute("class", "warninginfotext");
			document.getElementById('maxcrop'+callingField).setAttribute("className", "warninginfotext");
			document.getElementById('maxcrop'+opposingField[0]).setAttribute("class", "warninginfotext");
			document.getElementById('maxcrop'+opposingField[0]).setAttribute("className", "warninginfotext");
		} else {
			document.getElementById('maxcrop'+callingField).setAttribute("class", "");
			document.getElementById('maxcrop'+callingField).setAttribute("className", "");
			document.getElementById('maxcrop'+opposingField[0]).setAttribute("class", "");
			document.getElementById('maxcrop'+opposingField[0]).setAttribute("className", "");
		}
		document.getElementById('maxcrop'+opposingField[0]).innerHTML = newMaxValue;
		document.getElementById('pixel'+opposingField[2]+'input').value = imageSize['pixel'+opposingField[2]][1];
		updateSize('pixel'+opposingField[2], true);
	}
	function updateSize(callingField, cropCalled) {
		fieldInfo = associatedInfo[callingField];
		if (fieldInfo[0] == 'pixels') {
			callingInput = document.getElementById(callingField+'input');
			if ((callingInput.value != '') && !isNaN(callingInput.value) && (callingInput.value >= 1)) {
				imageSize[callingField][1] = parseFloat(callingInput.value);
				if (cropCalled == false) {
					imageSize[fieldInfo[1]][0] = ((imageSize[callingField][1])*100)/(origImageSize[fieldInfo[2]]-crop[fieldInfo[3]][0]-crop[fieldInfo[4]][0]);
					imageSize[fieldInfo[1]][1] = imageSize[fieldInfo[1]][0];
					if (document.getElementById('lockaspectcheckbox').checked == true) {
						imageSize[fieldInfo[5]][1] = imageSize[fieldInfo[5]][0]*(imageSize[callingField][1]/imageSize[callingField][0]);
						imageSize[fieldInfo[5]][0] = imageSize[fieldInfo[5]][1];
						imageSize[associatedInfo[fieldInfo[5]][1]][0] = ((imageSize[fieldInfo[5]][0])*100)/(origImageSize[associatedInfo[fieldInfo[5]][2]]-crop[associatedInfo[fieldInfo[5]][3]][0]-crop[associatedInfo[fieldInfo[5]][4]][0]);
						imageSize[associatedInfo[fieldInfo[5]][1]][1] = imageSize[associatedInfo[fieldInfo[5]][1]][0];
						document.getElementById(fieldInfo[5]+'input').value = imageSize[fieldInfo[5]][0];
						document.getElementById([associatedInfo[fieldInfo[5]][1]]+'input').value = imageSize[associatedInfo[fieldInfo[5]][1]][0];
					}
				}
				imageSize[callingField][0] = imageSize[callingField][1];
				document.getElementById(fieldInfo[1]+'input').value = imageSize[fieldInfo[1]][0];
			} else {
				//Disable submit button etc
			}
		} else {
			callingInput = document.getElementById(callingField+'input');
			if ((callingInput.value != '') && !isNaN(callingInput.value) && (callingInput.value >= 1)) {
				imageSize[callingField][1] = parseFloat(callingInput.value);
				imageSize[fieldInfo[1]][0] = ((imageSize[callingField][1])/100)*(origImageSize[fieldInfo[2]]-crop[fieldInfo[3]][0]-crop[fieldInfo[4]][0]);
				imageSize[fieldInfo[1]][1] = imageSize[fieldInfo[1]][0];
				if ((document.getElementById('lockaspectcheckbox').checked == true) && (cropCalled == false)) {
					imageSize[fieldInfo[5]][1] = imageSize[fieldInfo[5]][0]*(imageSize[callingField][1]/imageSize[callingField][0]);
					imageSize[fieldInfo[5]][0] = imageSize[fieldInfo[5]][1];
					imageSize[associatedInfo[fieldInfo[5]][1]][0] = ((imageSize[fieldInfo[5]][0])/100)*(origImageSize[associatedInfo[fieldInfo[5]][2]]-crop[associatedInfo[fieldInfo[5]][3]][0]-crop[associatedInfo[fieldInfo[5]][4]][0]);
					imageSize[associatedInfo[fieldInfo[5]][1]][1] = imageSize[associatedInfo[fieldInfo[5]][1]][0];
					document.getElementById(fieldInfo[5]+'input').value = imageSize[fieldInfo[5]][0];
					document.getElementById([associatedInfo[fieldInfo[5]][1]]+'input').value = imageSize[associatedInfo[fieldInfo[5]][1]][0];
				}
				imageSize[callingField][0] = imageSize[callingField][1];
				document.getElementById(fieldInfo[1]+'input').value = imageSize[fieldInfo[1]][0];
			} else {
				//Disable submit button etc
			}
		}
		redisplayImage();
		if (document.getElementById('autosharpencheckbox').checked == true) autoSharpen();
	}
	function redisplayImage() {
		n = 1;
		availableWidth = document.body.offsetWidth - 200;
		availableHeight = document.body.offsetHeight - 30;
		
		if (availableWidth < 50) availableWidth = 50;
		if (availableHeight < 50) availableHeight = 50;
		newImageWidth = (((origImageSize[0] - crop['left'][0] - crop['right'][0]) * imageSize['percentwidth'][0]) / 100);
		if (isNaN(newImageWidth) || (newImageWidth <= 0)) newImageWidth = origImageSize[0];
		newImageHeight = (((origImageSize[1] - crop['top'][0] - crop['bottom'][0]) * imageSize['percentheight'][0]) / 100);
		if (isNaN(newImageHeight) || (newImageHeight <= 0)) newImageHeight = origImageSize[1];
		while (newImageWidth > availableWidth) {
			n = n*2;
			newImageWidth = Math.round(newImageWidth/2);
			newImageHeight = Math.round(newImageHeight/2);
		}
		while (newImageHeight > availableHeight) {
			n=n*2;
			newImageWidth = Math.round(newImageWidth/2);
			newImageHeight = Math.round(newImageHeight/2);
		}
		infoSize = 100/n;
		document.getElementById('theimageframe').style.clip = 'rect('+Math.round((crop['top'][0]*imageSize['percentheight'][0])/(n*100))+'px,'+Math.round(((origImageSize[0]-crop['right'][0])*imageSize['percentwidth'][0])/(n*100))+'px,'+Math.round(((origImageSize[1]-crop['bottom'][0])*imageSize['percentheight'][0])/(n*100))+'px,'+Math.round((crop['left'][0]*imageSize['percentwidth'][0])/(n*100))+'px'+')';
		document.getElementById('theimage').style.width = Math.round((origImageSize[0] * imageSize['percentwidth'][0]) / (n * 100))+'px';
		document.getElementById('theimage').style.height = Math.round((origImageSize[1] * imageSize['percentheight'][0]) / (n * 100))+'px';
		document.getElementById('theimageframe').style.top = Math.round(((availableHeight - ((origImageSize[1] - crop['top'][0] - crop['bottom'][0]) * imageSize['percentheight'][0])/(n*100))/2) - ((crop['top'][0] * imageSize['percentheight'][0])/(n*100)))+'px';
		document.getElementById('theimageframe').style.left = Math.round(((availableWidth - ((origImageSize[0] - crop['left'][0] - crop['right'][0]) * imageSize['percentwidth'][0])/(n*100))/2) - ((crop['left'][0] * imageSize['percentwidth'][0])/(n*100)))+'px';
		updateFormSubmit();
		updateInfoBar();
	}
	function updateFormSubmit () {
		submitDisabled = false;
		cropTop = document.getElementById('croptopinput').value;
		cropRight = document.getElementById('croprightinput').value;
		cropBottom = document.getElementById('cropbottominput').value;
		cropLeft = document.getElementById('cropleftinput').value;
		sizeWidth = document.getElementById('pixelwidthinput').value;
		sizeHeight = document.getElementById('pixelheightinput').value;
		percentWidth = document.getElementById('percentwidthinput').value;
		percentHeight = document.getElementById('percentheightinput').value;
		if ((cropTop == '') || isNaN(cropTop) || (parseInt(cropTop) >= <?=$imageSize[1]?>)) submitDisabled = true;
		if ((cropRight == '') || isNaN(cropRight) || (parseInt(cropRight) >= <?=$imageSize[0]?>)) submitDisabled = true;
		if ((cropBottom == '') || isNaN(cropBottom) || (parseInt(cropBottom) >= <?=$imageSize[1]?>)) submitDisabled = true;
		if ((cropLeft == '') || isNaN(cropLeft) || (parseInt(cropLeft) >= <?=$imageSize[0]?>)) submitDisabled = true;
		if (((parseInt(cropLeft) + parseInt(cropRight)) >= <?=$imageSize[0]?>) || ((parseInt(cropTop) + parseInt(cropBottom)) >= <?=$imageSize[1]?>)) submitDisabled = true;
		if ((sizeWidth == '') || isNaN(sizeWidth) || (parseInt(sizeWidth) < 1)) submitDisabled = true;
		if ((sizeHeight == '') || isNaN(sizeHeight) || (parseInt(sizeHeight) < 1)) submitDisabled = true;
		if ((percentWidth == '') || isNaN(percentWidth) || (parseInt(percentWidth) < 1)) submitDisabled = true;
		if ((percentHeight == '') || isNaN(percentHeight) || (parseInt(percentHeight) < 1)) submitDisabled = true;
		document.getElementById('submitbutton').disabled = submitDisabled;
	}
	function updateInfoBar() {
		currentInfo = '';
		if (infoSize != 100) currentInfo = 'Preview shown at '+infoSize+'% of actual size.&nbsp;&nbsp;';
		if ((infoSize != 100) || (imageSize['percentwidth'][0] != 100) || (imageSize['percentheight'][0] != 100)) currentInfo += 'Low-quality preview.  ';
		if (currentInfo.length) {
			document.getElementById('infobar').innerHTML = currentInfo;
			document.getElementById('infopane').style.display = 'block';
		} else {
			document.getElementById('infopane').style.display = 'none';
		}
	}
	function autoSharpen() {
		sharpenLevel = 0;
		if ((imageSize['percentwidth'][0] < 100) || (imageSize['percentheight'][0] < 100)) sharpenLevel++;
		if ((imageSize['percentwidth'][0] <= 60) || (imageSize['percentheight'][0] <= 60)) sharpenLevel++;
		document.getElementById('sharpenlevelinput').options[sharpenLevel].selected = true;
	}
	
	redisplayImage();
	if (window.outerHeight && ((window.outerWidth < 700) || (window.outerHeight < 600))) {
		restoreHeight = window.outerHeight;
		restoreWidth = window.outerWidth;
		if (window.screenLeft) {
			restoreX = window.screenLeft;
			restoreY = window.screenTop;
		} else if (window.screenX) {
			restoreX = window.screenX;
			restoreY = window.screenY;
		}
		window.resizeTo(screen.availWidth - 40, screen.availHeight - 40);
		if (restoreX || restoreY) window.moveTo(20,20);
	} else if (document.body.clientHeight && window.screenTop) {
		restoreX = window.screenLeft;
		restoreY = window.screenTop;
		window.moveTo(restoreX, restoreY);
		barLeft = window.screenLeft - restoreX;
		barTop = window.screenTop - restoreY;
		restoreX = window.screenLeft - (2 * barLeft);
		restoreY = window.screenTop - (2 * barTop);
		if ((document.body.clientHeight < 600) || (document.body.clientWidth < 700)) {
			restoreHeight = document.body.clientHeight + barTop + 8;
			restoreWidth = document.body.clientWidth + barLeft + 8;
			window.resizeTo(screen.availWidth - 40, screen.availHeight - 40);
			window.moveTo(20,20);
		} else {
			window.moveTo(restoreX, restoreY);
		}
	}	
	document.getElementById('theimageframe').style.display = 'block';
	// Workaround for browsers which seem to apply CSS late
	self.setTimeout('redisplayImage()',500);
</script>
</body>
</html>