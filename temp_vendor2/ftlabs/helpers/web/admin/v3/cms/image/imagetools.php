<?php
/**
 * imageProcess() deals with moving pictures to the correct
 * location, resizing if necessary. Attemps to conserve transparency
 * and image ratio. Can read jpegs, pngs, gifs, bitmaps or GD2, and
 * can output jpeg, png or gif.
 *
 * Accepted Function Inputs: $imagePath - URL of the image file
 * $targetFolder - folder in which to save the new image $targetName
 * - name to call the new image, NOT including file extension
 * $uploadedFileChecks - true/false - check whether the original
 * file was uploaded via $_POST $deleteOriginal - true/false -
 * delete the original or keep it. $overwriteFile - true/false -
 * overwrite an existing file or generate unique filename $cropTop -
 * number of pixels to crop from the top $cropRight - number of
 * pixels to crop from the right $cropBottom - number of pixels to
 * crop from the bottom $cropLeft - number of pixels to crop from
 * the left $targetWidth - new width of the image $targetHeight -
 * new height of the image $maxX - maximum height of the image (will
 * resize proportionally to fit) $maxY - maximum width of the image
 * (will resize proportionally to fit) $saveAsType - converts image
 * to "png", "jpg", "gif" if necessary $sharpenLevel - optional
 * level of sharpening, integer from 0 upwards. Corresponds with
 * simple editor, so 1 is a little, 2 is medium, 4 is heavy, etc.
 * $jpegCompression - sets JPEG compression level from 0 to 100,
 * where 0 is smallest file
 *
 * Returns the image name (not including path but including
 * extension) if successful, and false if unsuccessful.
 *
 * @copyright The Financial Times Limited [All Rights Reserved]
 */

include("phpunsharpmask.php");

// Set the time limit again:
set_time_limit(120);

function imageProcess($imagePath, $targetFolder, $targetName, $uploadedFileChecks, $deleteOriginal, $overwriteFile, $cropTop, $cropRight, $cropBottom, $cropLeft, $targetWidth, $targetHeight, $maxX, $maxY, $saveAsType = "jpg", $sharpenLevel = 0, $jpegCompression = 80, $frameSmallImages = false, $frameColorR = 255, $frameColorG = 255, $frameColorB = 255) {
	$imageCreate = false;

	// Make an initial check whether the original file exists
	if (!file_exists($imagePath)) return false;
	
	// If necessary check for $_POST uploaded files and abort if the check is failed
	if ($uploadedFileChecks && !is_uploaded_file($imagePath)) {
		if (($deleteOriginal == true) && !(($overwriteFile == true) && (strcmp($imagePath, $targetFolder.$imageName)))) unlink($imagePath);
		return false;
	}
	
	// Get the size of the original image and abort if image reading fails
	$imageSize = @getimagesize($imagePath);
	if (!$imageSize) {
		if (($deleteOriginal == true) && !(($overwriteFile == true) && (strcmp($imagePath, $targetFolder.$imageName)))) unlink($imagePath);
		return false;
	}
	
	// Check supplied variables for errors, and correct if necessary
	if ($jpegCompression < 0) $jpegCompression = 0;
	if ($jpegCompression > 100) $jpegCompression = 100;
	if ($cropTop < 0) $cropTop = 0;
	if ($cropRight < 0) $cropRight = 0;
	if ($cropBottom < 0) $cropBottom = 0;
	if ($cropLeft < 0) $cropLeft = 0;
	if ($maxX < 1) $maxX = 1;
	if ($maxY < 1) $maxY = 1;
	if (($cropTop + $cropBottom) > $imageSize[1]) $cropTop = $cropBottom = 0;
	if (($cropRight + $cropLeft) > $imageSize[0]) $cropRight = $cropLeft = 0;
	if (($saveAsType != "png") && ($saveAsType != "gif")) $saveAsType = "jpg";

	// If the target width values are 0 or -1, use the original image dimensions;
	// thus feeding in an image with no target dimensions but maximum sizes allows
	// downsizing where appropriate
	if ($targetWidth < 1) $targetWidth = $imageSize[0];
	if ($targetHeight < 1) $targetHeight = $imageSize[1];

	// Read in the source image.  This uses the image info supplied by
	// getimagesize, where: 1=GIF, 2=JPG, 3=PNG, 4=SWF, 5=PSD, 6=BMP...15=WBMP
	switch ($imageSize[2]) {
		case 1:
			$imageSource = @imagecreatefromgif($imagePath);
			break;
		case 2:
			$imageSource = @imagecreatefromjpeg($imagePath);
			break;
		case 3:
			$imageSource = @imagecreatefrompng($imagePath);
			break;
		case 6:
			include ("imagecreatefrombmp.php");
			$imageSource = @imagecreatefrombmp($imagePath);
			break;
		default:
			$imageSource = @imagecreatefromstring(file_get_contents($imageString));
	}

	// If image reading failed, delete if necessary and return false
	if (!$imageSource) {
		if (($deleteOriginal == true) && !(($overwriteFile == true) && (strcmp($imagePath, $targetFolder.$imageName)))) unlink($imagePath);
		return false;
	}
	
	// Crop the image if necessary
	if ($cropTop || $cropRight || $cropBottom || $cropLeft) {
		$imageSize[0] = $imageSize[0] - $cropLeft - $cropRight;
		$imageSize[1] = $imageSize[1] - $cropTop - $cropBottom;
		$imageCropped = imagecreatetruecolor($imageSize[0], $imageSize[1]);
		if ($saveAsType == "png" || $saveAsType == "gif") {
			imagealphablending($imageCropped, false);
			imagesavealpha($imageCropped,true);
		}
		imagecopy($imageCropped, $imageSource, 0, 0, $cropLeft, $cropTop, $imageSize[0], $imageSize[1]);
		imagedestroy($imageSource);
		$imageSource = imagecreatetruecolor($imageSize[0], $imageSize[1]);
		imagecopy($imageSource, $imageCropped, 0, 0, 0, 0, $imageSize[0], $imageSize[1]);
		imagedestroy($imageCropped);
		$imageCreate = true;
	}
	
	// Next check if picture size exceeds maximum desired; if it does, resize to fit.
	// This needs to be run over both dimensions so that even if both x and y
	// are rescaled the scaled versions will be within bounds.
	if (($targetWidth > $maxX) || ($targetHeight > $maxY)) {
		if ($imageSize[0] > $maxX) {
			$targetWidth = $maxX;
			$targetHeight = ($targetWidth/$imageSize[0]) * $imageSize[1];
		} else {
			$targetHeight = $maxY;
			$targetWidth = ($targetHeight/$imageSize[1]) * $imageSize[0];
		}
		if ($targetHeight > $maxY) {
			$targetWidth = ($maxY/$targetHeight) * $targetWidth;
			$targetHeight = $maxY;
		}
	}
	
	// Resample down if necessary.
	if (($targetWidth < $imageSize[0]) || ($targetHeight < $imageSize[1])) {
		$imageResampled = imagecreatetruecolor($targetWidth, $targetHeight);
		if ($saveAsType == "png" || $saveAsType == "gif") {
			imagealphablending($imageResampled, false);
			imagesavealpha($imageResampled,true);
		}

		// If the target is much smaller than the current image, use
		// imagecopyresized to resize down to four times the target, then
		// resample down
		if (($imageSize[1] > (4*$targetHeight)) && ($imageSize[0] > (4*$targetWidth))) {
			$imageResized = imagecreatetruecolor($targetWidth*4, $targetHeight*4);
			if ($saveAsType == "png" || $saveAsType == "gif") {
				imagealphablending($imageResampled, false);
				imagesavealpha($imageResampled,true);
			}
			imagecopyresized($imageResized, $imageSource, 0, 0, 0, 0, $targetWidth*4, $targetHeight*4, $imageSize[0], $imageSize[1]);
			imagecopyresampled($imageResampled, $imageResized, 0, 0, 0, 0, $targetWidth, $targetHeight, $targetWidth*4, $targetHeight*4);		
			imagedestroy($imageResized);
		} else {
			imagecopyresampled($imageResampled, $imageSource, 0, 0, 0, 0, $targetWidth, $targetHeight, $imageSize[0], $imageSize[1]);		
		}
		
		imagedestroy($imageSource);
		$imageSource = imagecreatetruecolor($targetWidth, $targetHeight);
		imagecopy($imageSource, $imageResampled, 0, 0, 0, 0, $targetWidth, $targetHeight);
		imagedestroy($imageResampled);
		$imageCreate = true;
	}
	
	// Frame if necessary.
	if ((($maxX > $targetWidth) || ($maxY > $targetHeight)) && $frameSmallImages) {
		$imageResampled = imagecreatetruecolor($maxX, $maxY);
		if ($saveAsType == "png" || $saveAsType == "gif") {
			imagealphablending($imageResampled, false);
			imagesavealpha($imageResampled,true);
		}
		$bgcolor = imagecolorallocate($imageResampled, $frameColorR, $frameColorG, $frameColorB);
		imagefilledrectangle($imageResampled, 0, 0, $maxX - 1, $maxY - 1, $bgcolor);
		imagecopy($imageResampled, $imageSource, floor(($maxX - $targetWidth)/2), floor(($maxY - $targetHeight)/2), 0, 0, $targetWidth, $targetHeight);
		imagedestroy($imageSource);
		$imageSource = imagecreatetruecolor($maxX, $maxY);
		imagecopy($imageSource, $imageResampled, 0, 0, 0, 0, $maxX, $maxY);
		imagedestroy($imageResampled);
		$imageCreate = true;
	}

	// If the image hasn't been resized, check whether the extension matches; if
	// it does, there's no need to recompress the image.
	if (!$imageCreate) {	
		$imageExtension = end(explode(".", $imagePath));
		if (strcasecmp($imageExtension,"jpeg") == 0) $imageExtension = "jpg";
		if (strcasecmp($imageExtension, $saveAsType) != 0) $imageCreate = true;
	}
	
	// Thanks to Torstein H¿nsi's phpUnsharpMask, a sharpening operation can
	// be performed without the ImageMagick dependancy.
	if ($sharpenLevel) {
		
		// UnsharpMask as a command is fairly versatile, but for simplicity's
		// sake wetake an integer and generate 'standard' other values from it.
		// As $sharpenLevel goes, 0 is none, 1 is mild, 2 is medium, 4 is fairly heavy.
		$unsharpAmount = 50 * $sharpenLevel;
		$unsharpRadius = 0.2 + (0.2 * $sharpenLevel);
		$unsharpThreshold = 3.5 - (0.5 * $sharpenLevel);
		if ($unsharpThreshold < 0) $unsharpThreshold = 0; 
		UnsharpMask($imageSource, $unsharpAmount, $unsharpRadius, $unsharpThreshold);
		$imageCreate = true;
	}

	// Recompress or convert the image if necessary
	if ($imageCreate) {
		imageinterlace($imageSource, 1);
		if ($overwriteFile == true) {
			$imageName = $targetName.".".$saveAsType;
			if (file_exists($targetFolder.$imageName)) unlink($targetFolder.$imageName);
		} else {
			$imageName = getUniqueName($targetFolder, $targetName, $saveAsType);
		}
		
		if ($saveAsType == "png") {
			imagepng($imageSource, $targetFolder.$imageName);
		} else if ($saveAsType == "jpg") {
			imagejpeg($imageSource, $targetFolder.$imageName, $jpegCompression);
		} else if ($saveAsType == "gif") {
			if (function_exists("imagegif")) imagegif($imageSource, $targetFolder.$imageName);
			else {
				if (($deleteOriginal == true) && !(($overwriteFile == true) && (strcmp($imagePath, $targetFolder.$imageName)))) unlink($imagePath);
				return false;
			}
		}
		
		// Delete the original if required.
		if (($deleteOriginal == true) && !(($overwriteFile == true) && (strcmp($imagePath, $targetFolder.$imageName)))) unlink($imagePath);
		
	// Otherwise just move the original, replacing the extension with all-lowercase
	} else {
		$imageName = $targetName.".".strtolower($fileExtension);
		if ($overwriteFile == true) {
			if (file_exists($targetFolder.$imageName)) unlink($targetFolder.$imageName);
		}
		if ($deleteOriginal == true) {
			rename($imagePath, $targetFolder.$imageName);
		} else {
			copy($imagePath, $targetFolder.$imageName);
		}
	}
	imagedestroy($imageSource);
	return $imageName;
}

function getUniqueName($path, $name, $extension) {
	$suffix = 0;
	$uniqueName = $name;
	while (file_exists($path.$uniqueName.".".$extension)) {
		$suffix++;
		$uniqueName = $name." (".$suffix.")";
	}
	return $uniqueName.".".$extension;
}
?>