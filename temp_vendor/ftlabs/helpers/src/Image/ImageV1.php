<?php
/**
 * Assanka Image class
 *
 * Provides a convenience class for image handling and processing.
 * The image is instantiated with a path to an image, returning false
 * if the image cannot be read, and provides information about
 * the image properties and methods to resize, crop, or convert the
 * image.
 *
 * Supports reading GIFs, PNGs, JPEGs and BMPs, and outputting to JPEGs, true-colour
 * PNGs, and paletted GIFs. Supports a few basic properties via getters and setters.
 * Provides a few simple transforms - constraining images within specified dimensions, resizing to
 * specified dimensions, or framing within a larger space.
 *
 * Transparency between GIFs, 8-bit PNGs, and 24-bit PNGs is supported as much as possible.
 *
 * @copyright The Financial Times Limited [All Rights Reserved]
 * @codingstandard ftlabs-phpcs
 */

namespace FTLabs\Image;

use FTLabs\Common\CommonV2;

class ImageV1 {

	public $errornum, $errorstring;
	private $_isvalid, $_width, $_height, $_originalformat, $_mimetype, $_resource, $_haschanged, $_filepath, $_overwritefiles, $_transcolour, $_transfull, $_bgchangerequired, $_bghaschanged, $_convertlocation, $_identifylocation;
	private $_bgcolourred = 255;
	private $_bgcolourgreen = 255;
	private $_bgcolourblue = 255;
	private $_bgcolouralpha = 127;
	private $_jpegcompressionlevel = 80;
	private $_xaxisanchorpoint = 0.5;
	private $_yaxisanchorpoint = 0.5;
	private $_targetformat = "PNG";
	private $_temporarydirectory = "/tmp";
	private $_temporaryfilepath = false;
	private $_useconvert = false;
	private $_convertactionstack = array();

	/**
	 * Constructor method.  Attempts to open and parse the image specified, ready for processing.
	 *
	 * @param string $path               The path to the image file, either as a local filepath or as an http:// or https:// prefixed URL.
	 * @param string $temporarydirectory The path to the directory to use when working with temporary files; currently only required when a URL is passed as the path. (optional)
	 * @return object An instance of the Image class with the properties set as appropriate for the loaded image, or if the loading failed with error properties set.
	 */
	function __construct($path, $temporarydirectory = "/tmp") {
		$this->_filepath = $path;
		$this->_temporarydirectory = $temporarydirectory;

		// Determine whether the Imagemagick 'convert' utility is installed (making use of it will improve speed and memory usage)
		$convlocations = array("convert", "/usr/bin/convert", "/usr/local/bin/convert");
		while (empty($this->_convertlocation) and $loc = each($convlocations)) {
			$return = shell_exec($loc["value"]." -version 2>&1");
			if (strpos($return, "ImageMagick") !== false) $this->_convertlocation = $loc["value"];
		}
		$identifylocations = array("identify", "/usr/bin/identify", "/usr/local/bin/identify");
		while (empty($this->_identifylocation) and $loc = each($identifylocations)) {
			$return = shell_exec($loc["value"]." -version 2>&1");
			if (strpos($return, "ImageMagick") !== false) $this->_identifylocation = $loc["value"];
		}
		if (!empty($this->_convertlocation)) $this->_useconvert = true;

		// Attempt to ensure a nice large memory size limit, for fully decoding images and constructing
		// new optimised variants in the native path, and storing resources when using ImageMagick
		if (!$this->_useconvert and $curlimit = ini_get("memory_limit")) {
			if (!is_numeric($curlimit)) {
				$multipliers = array("K"=>1024, "M"=>1048576, "G"=>1073741824, "T"=>1099511627776);
				$curlimit = substr($curlimit, 0, -1) * $multipliers[substr($curlimit, -1)];
			}
			if ($curlimit < (128 * $multipliers["M"])) ini_set("memory_limit", "128M");
		}

		// If a URL has been supplied, download the file to a temporary location
		if (stripos($path, "http://") === 0 or stripos($path, "https://") === 0) {

			// Get an appropriate extension if possible
			if (strrpos($path, ".") > strlen($path) - 6) $extension = substr($path, strrpos($path, "."));
			else $extension = "";

			// Generate a path in the temporary directory and open a filehandle
			$this->_temporaryfilepath = $this->_temporarydirectory.'/ASSKImageTmp_'.substr(md5($path), 0, 10).substr(md5(rand()), 0, 10).$extension;
			$filepointer = fopen($this->_temporaryfilepath, 'w');

			// Download the image to the filehandle
			$curlhandle = curl_init($path);
			curl_setopt($curlhandle, CURLOPT_FAILONERROR, true);
			curl_setopt($curlhandle, CURLOPT_FILE, $filepointer);
			$urlreadable = curl_exec($curlhandle);
			curl_close($curlhandle);
			fclose($filepointer);

			// If the file could not be downloaded, error
			if (!$urlreadable) {
				$this->_isvalid = false;
				$this->errorstring = "The specified URL could not be downloaded as a file.";
				$this->errornum = 3;
				return;
			}

			$this->_filepath = $this->_temporaryfilepath;
		}

		// Do some basic error checking on the file to be read
		if (!file_exists($this->_filepath) or !is_readable($this->_filepath)) {
			$this->_isvalid = false;
			$this->errorstring = "The file could not be ".((file_exists($this->_filepath))?"opened":"found").".";
			$this->errornum = file_exists($this->_filepath)?0:1;
			return;
		}

		// Attempt to get the image dimensions, at the same time determining the format
		// ImageMagick doesn't support filenames with : (mistakes it for filetype specifier)
		if ($this->_useconvert && false === strpos($this->_filepath, ':')) {
			$detailsarray = array();
			$error = false;
			$detectedwidth = false;
			$detectedheight = false;
			$detectedformat = false;

			// TODO:RB:20080819: Currently identify and convert both process the image more heavily
			// than is required at this stage.  It would be useful to separate out width/height
			// checks and isImage checks for speed reasons when deciding whether to accept files -
			// PDFs, for example, require rasterization to determine dimensions but not to ping the format.
			if (!empty($this->_identifylocation)) {
				$details = shell_exec($this->_identifylocation." -ping ".escapeshellarg($this->_filepath)."[0] 2>&1");
				if (preg_match("/\s([a-z0-9]{1,6})\s([0-9]+)x([0-9]+)(\s|\+)/i", $details, $matches)) {
					$detectedformat = $matches[1];
					$detectedwidth = $matches[2];
					$detectedheight = $matches[3];
				}
			} else {
				$details = shell_exec($this->_convertlocation." ".escapeshellarg($this->_filepath)."[0] -identify -ping /dev/null 2>&1");
				if (preg_match("/Format\:\s*([a-z0-9]+)/i", $details, $matches)) {
					$detectedformat = $matches[1];
				}
				if (preg_match("/Geometry\:\s*([0-9]+)x([0-9]+)/i", $details, $matches)) {
					$detectedwidth = $matches[1];
					$detectedheight = $matches[2];
				}
			}

			if (empty($details)) {
				$this->_isvalid = false;
				$this->errorstring = "The file was not recognised as a valid image, because identify call failed";
				$this->errornum = 2;
				return;
			}
			if (!$detectedformat or !$detectedwidth) {
				$this->_isvalid = false;
				$this->errorstring = "The file was not recognised as a valid image, because format was not found in identify output: $details";
				$this->errornum = 2;
				return;
			}

			if ($detectedformat === 'JPEG') {
				$detectedformat = 'JPG';
			}

			$this->_width = $detectedwidth;
			$this->_height = $detectedheight;
			$this->_originalformat = $detectedformat;
			$this->_mimetype = $this->_convertFormatToMime($detectedformat);
		} else {
			$imagedetails = @getimagesize($this->_filepath);
			if (!$imagedetails) {
				$this->_isvalid = false;
				$this->errorstring = "The file was not recognised as a valid image, because getimagesize failed";
				$this->errornum = 2;
				return;
			}
			$this->_width = $imagedetails[0];
			$this->_height = $imagedetails[1];
			$this->_mimetype = $imagedetails["mime"];

			// Switch according to the format, creating the resource at the same time
			switch ($imagedetails[2]) {
				case 1:
					$this->_originalformat = "GIF";
					$this->_resource = @imagecreatefromgif($this->_filepath);
					break;
				case 2:
					$this->_originalformat = "JPG";
					$this->_resource = @imagecreatefromjpeg($this->_filepath);
					break;
				case 3:
					$this->_originalformat = "PNG";
					$this->_resource = @imagecreatefrompng($this->_filepath);
					break;
				case 6:
					$this->_originalformat = "BMP";
					$this->_resource = $this->_imageCreateFromBmp($this->_filepath);
					break;
				case 15:

					// Note that this is "Wireless Bitmap", not "Windows Bitmap"...
					$this->_originalformat = "WBMP";
					$this->_resource = @imagecreatefromwbmp($this->_filepath);
					break;
				default:
					$this->_isvalid = false;
					$this->errorstring = "The image of type '".$this->_mimetype."' you selected is valid, but is an unsupported format.  Please provide images in JPEG, GIF or PNG format.";
					$this->errornum = 10;
					return;
			}

			// Double-check the resource
			if (!$this->_resource) {
				$this->_isvalid = false;
				$this->errorstring = "The image selected could not be fully opened, possibly because of a corrupted file.";
				$this->errornum = 11;
				return;
			}

			// If this is a paletted image, allow the true colours to keep shining through
			if (imagecolorstotal($this->_resource)) {
				$transcolourindex = imagecolortransparent($this->_resource);
				if ($transcolourindex != -1) {
					$this->_transcolour = @imagecolorsforindex($this->_resource, $transcolourindex);
				}
				$truecolourresource = imagecreatetruecolor($this->_width, $this->_height);
				imagecopy($truecolourresource, $this->_resource, 0, 0, 0, 0, $this->_width, $this->_height);
				if ($transcolourindex != -1) {
					imagealphablending($truecolourresource, false);
					imagesavealpha($truecolourresource, false);
					for ($x = 0; $x < $this->_width; $x++) {
						for ($y = 0; $y < $this->_height; $y++) {
							if (imagecolorat($this->_resource, $x, $y) == $transcolourindex) {
								imagesetpixel($truecolourresource, $x, $y, 127 << 24);
							}
						}
					}
				}
				$this->_transfull = true;

				// Replace the original resource with the updated resource
				imagedestroy($this->_resource);
				$this->_resource = imagecreatetruecolor($this->_width, $this->_height);
				$this->_applyTransparencyProtection($this->_resource);
				imagecopy($this->_resource, $truecolourresource, 0, 0, 0, 0, $this->_width, $this->_height);
				imagedestroy($truecolourresource);
			}

			// If transparency needs to be preserved, mark as such
			if (empty($this->_transcolour) and empty($this->_transfull) and $this->_originalformat == "PNG") {
				$this->_transfull = true;
			}
		}
		$this->_isvalid = true;
	}

	/**
	 * Returns true if the image can be parsed and is in a recognised format
	 *
	 * @return boolean
	 */
	public function isValidImage() {
		return $this->_isvalid;
	}

	/**
	 * Returns the current image fwidth
	 *
	 * @return integer the current width
	 */
	public function getWidth() {
		return $this->_width;
	}

	/**
	 * Returns the current image height
	 *
	 * @return integer the current height
	 */
	public function getHeight() {
		return $this->_height;
	}

	/**
	 * Returns the original format of the image
	 *
	 * @return string The format string describing the input format of the image - JPG, JP2, GIF, PNG, WBMP, or BMP.
	 */
	public function getFormat() {
		return $this->_originalformat;
	}

	/**
	 * Returns an appropriate MIME type string for the input image.
	 *
	 * @return string The mime string.
	 */
	public function getMimeType() {
		return $this->_mimetype;
	}

	/**
	 * Sets the current target format
	 *
	 * @param string $format The target format - valid choices are JPG, JP2, GIF, or PNG.
	 * @return boolean Whether the operation was successful
	 */
	public function setTargetFormat($format) {
		$format = strtoupper($format);
		if ($format == 'JPEG') $format = 'JPG';
		switch ($format) {
			case "JP2":
				if (!$this->_useconvert) {
					trigger_error('JPEG2000 is unsupported as an output format unless ImageMagick is used', E_USER_ERROR);
				}
			case "PNG":
			case "JPG":
			case "GIF":
				$this->_targetformat = $format;
				return true;
				break;
			default:
				return false;
		}
	}

	/**
	 * Returns the current target format
	 *
	 * @return string The current target format string - JPG, JP2, GIF, or PNG.
	 */
	public function getTargetFormat() {
		return $this->_targetformat;
	}

	/**
	 * Returns an appropriate MIME type string for the target image format.
	 *
	 * @return string The mime string.
	 */
	public function getTargetMimeType() {
		return $this->_convertFormatToMime($this->_targetformat);
	}

	/**
	 * Returns the current image resource handle
	 *
	 * Since the callign script may manipulate the image directly once it has the handle, the image is also marked as
	 * invalid to prevent potential errors from the image no longer being as expected
	 *
	 * @return resource The current image resource, in true colour.
	 */
	public function getResourceAndInvalidate() {

		// Apply background colour if necessary
		if ($this->_bgchangerequired and !$this->_bghaschanged) {
			$this->constrainAndFrameTo($this->_width, $this->_height);
		}

		if ($this->_useconvert) {
			$imagedata = shell_exec($this->_convertlocation." ".escapeshellarg($this->_filepath)." ".implode(" ", $this->_convertactionstack)." -quality 00 PNG:- 2>&1");
			$this->_resource = imagecreatefromstring($imagedata);
		}

		$this->_isValid = false;
		return $this->_resource;
	}

	/**
	 * Set output JPEG or JPEG2000 compression level
	 *
	 * Only applicable when the target format is set to JPG or JP2, otherwise has no effect.
	 *
	 * @param boolean $level Compression level, from 0 to 100 (automatically mapped to JP2 ratio)
	 * @return boolean Whether the operation was successful
	 */
	public function setJpegCompressionLevel($level = 80) {
		if (!is_numeric($level)) return false;
		$level = (int)$level;
		if ($level < 0 or $level > 100) return false;

		$this->_jpegcompressionlevel = $level;
		return true;
	}

	/**
	 * Sets action to be taken when output filename matches an existing file
	 *
	 * Set to true to overwrite existing files, false to cause the target filename to be altered during save to find an unused name.
	 *
	 * @param boolean $overwrite Whether or not to overwrite existing files
	 * @return void
	 */
	public function setShouldOverwriteFiles($overwrite = true) {
		$this->_overwritefiles = $overwrite?true:false;
	}

	/**
	 * Returns true if existing files can be overwritten during save
	 *
	 * @return boolean
	 */
	public function getShouldOverwriteFiles() {
		return (($this->_overwritefiles)?true:false);
	}

	/**
	 * Sets the current background colour for filling margins of images
	 *
	 * When an image is resized to a fixed width and height such that its aspect ratio changes, but the entire source image is to be retained in view (@see constrainAndFrameTo), margins will appear in the image, either at the top and bottom, or the left and right.  This method sets the colour of those margin areas.
	 *
	 * @param integer $red   The 8-bit red component of the background colour, from 0 to 255.
	 * @param integer $green The 8-bit green component of the background colour, from 0 to 255.
	 * @param integer $blue  The 8-bit blue component of the background colour, from 0 to 255.
	 * @param integer $alpha The 7-bit alpha component of the background colour, from 0 to 127, where 0 is opaque and 127 is transparent.  Values higher than 0 but lower than 127 are only valid where the output format is a 24-bit PNG; GIFs and 8-bit PNGs support a single fully transparent colour, and this colour will be set as the transparent colour for putput images of those types if the alpha value is 127.
	 *
	 * @return void
	 */
	public function setBackgroundColor($red, $green, $blue, $alpha = 0) {

		// Validate...
		$red = (int)$red;
		$green = (int)$green;
		$blue = (int)$blue;
		$alpha = (int)$alpha;
		if ($red < 0 or $red > 255 or $green < 0 or $green > 255 or $blue < 0 or $blue > 255 or $alpha < 0 or $alpha > 127) {
			return false;
		}

		$this->_bgchangerequired = true;
		$this->_bgcolourred = $red;
		$this->_bgcolourgreen = $green;
		$this->_bgcolourblue = $blue;
		$this->_bgcolouralpha = $alpha;

		return true;
	}

	/**
	 * Returns the current background colour used for filling image margins
	 *
	 * @return array Array of colour components as 8-bit values, with the following keys: 0 = red, 1 = green, 2 = blue, 3 = alpha, "red" = red, "green" = green, "blue" = blue, "alpha" = alpha.
	 */
	public function getBackgroundColor() {
		return array(0 => $this->_bgcolourred, 1 => $this->_bgcolourgreen, 2 => $this->_bgcolourblue, 3 => $this->_bgcolouralpha, "red" => $this->_bgcolourred, "green" => $this->_bgcolourgreen, "blue" => $this->_bgcolourblue, "alpha" => $this->bgcolouralpha);
	}

	/**
	 * Set the point around which constrains and crops should be centered; if the image
	 * is cropped or framed following a resize, the default is to centrally align the
	 * operation, but this can be used to crop or frame from a corner or edge instead.
	 * Defaults to 0.5, 0.5 - center.
	 *
	 * COMPLEX:RB:20140403: I think this is only ever used for 0, 0.5 or 1 values.  This
	 * simplifies convert logic a lot so I'm deprecating all other behaviours!
	 *
	 * @param float $xAxis The point along the x axis to use, where 0 is left and 1 is right
	 * @param float $yAxis The point along the y axis to use, where 0 is bottom and 1 is top
	 * @return void
	 */
	public function setAnchorPoint($xAxis = 0.5, $yAxis = 0.5) {
		$this->_xaxisanchorpoint = min(1, max(0, $xAxis));
		$this->_yaxisanchorpoint = 1 - min(1, max(0, $yAxis));
		if ($this->_useconvert and (($this->_xaxisanchorpoint != 0 and $this->_xaxisanchorpoint != 0.5 and $this->_xaxisanchorpoint != 1) or ($this->_xaxisanchorpoint != 0 and $this->_xaxisanchorpoint != 0.5 and $this->_xaxisanchorpoint != 1))) {
			$this->_useconvert = false;
			trigger_error('Convert fast path only supports simple anchor points', E_USER_DEPRECATED);
		}
	}

	/**
	 * Resizes the image resource to fit within a specified width and/or height.
	 *
	 * The input image will remain unchanged if its width and height are already less than the specified dimensions.  If the image is larger on one or both dimensions than the specified limits, it will be reduced in size such that it retains its aspect ratio, and fits inside the specified limits.
	 *
	 * @param integer $width  The width the image must not exceed. 0 or false is treated as infinite (Optional)
	 * @param integer $height The height the image must not exceed. 0 or false is treated as infinite (Optional)
	 * @return boolean Whether the constrain was successfully executed
	 */
	public function constrainWithin($width = false, $height = false) {
		if (!$this->_isvalid or (!$width and !$height)) return false;

		// If using convert, add the appropriate action to the action stack and return.
		// The \> option tells imagemagick never to enlarge the image.
		if ($this->_useconvert) {
			$this->_convertactionstack[] = '-resize '.($width ? $width : '').'x'.($height ? $height : '').'\>';
			return true;
		}

		// Target dimensions of zero, or not set, are ignored; for the purposes of the
		// calculation, this means a very large number.
		if (!$width) $width = INF;
		if (!$height) $height = INF;

		// If the image already fits within the constrain box, no action is required.
		if ($this->_width <= $width and $this->_height <= $height) return true;

		// Determine the new dimensions of the image by making a check against width or height
		if ($this->_width > $width) {
			$targetwidth = $width;
			$targetheight = round(($targetwidth / $this->_width) * $this->_height);
			if ($targetheight > $height) {
				$targetwidth = round(($height / $targetheight) * $targetwidth);
				$targetheight = $height;
			}
		} else {
			$targetheight = $height;
			$targetwidth = ($targetheight / $this->_height) * $this->_width;
		}

		// Resize the image resource and return the status
		return $this->_imageProcess($targetwidth, $targetheight);
	}

	/**
	 * Resizes the image to completely fill a specified frame, and crops as necessary if the aspect ratio differs
	 *
	 * For images larger than the specified limits, the image will be scaled down until ONE dimension fits within the specified frame, cropping equally from opposing edges of any overflow on the other dimension. Images smaller than the specified dimensions will not be increased in size, but the background colour will be applied as a frame.  This method is therefore guaranteed to return an image or precisely the dimensions requested.
	 *
	 * @param integer $width  The width of the target image
	 * @param integer $height The height of the target image
	 * @return boolean Whether the constrain and crop was successfully executed
	 */
	public function constrainAndCropTo($width, $height) {
		if (!$this->_isvalid or !$width or !$height) return false;

		// If using convert, add the appropriate action to the action stack and return.
		// The \> option tells imagemagick never to enlarge the image, and the ^ option
		// is the "fill area" flag
		if ($this->_useconvert) {
			$this->_convertactionstack[] = '-gravity '.$this->_getAnchorGravity();
			$this->_convertactionstack[] = '-resize '.$width.'x'.$height.'^\>';
			$this->_convertactionstack[] = '-crop '.$width.'x'.$height.'+0+0\>';
			return true;
		}

		// Resize the image resource and return the status
		return $this->_imageProcess($width, $height, "crop");
	}

	/**
	 * Resizes the image to fit within a specified width and height, filling in margins with the background colour.
	 *
	 * This method is guaranteed to return an image of exactly the requested dimensions.  If the image is too large, it will be scaled down until BOTH dimensions fit within the specified frame, and any shortfall on either dimension will be padded with the backround colour.
	 *
	 * @param integer $width  The width of the target image
	 * @param integer $height The height the target image
	 * @return boolean Whether the contrain and frame was successfully executed
	 */
	public function constrainAndFrameTo($width, $height) {
		if (!$this->_isvalid or !$width or !$height) return false;

		// If using convert, add the appropriate action to the action stack and return.
		// This sets up a background colour and then performs the resize operation.
		if ($this->_useconvert) {
			$this->_convertactionstack[] = '-resize '.$width.'x'.$height.'\>';
			$this->_convertactionstack[] = "-alpha Set -background 'rgba(".$this->_bgcolourred.", ".$this->_bgcolourgreen.", ".$this->_bgcolourblue.", ".(1 - ($this->_bgcolouralpha / 127)).")'";
			$this->_convertactionstack[] = '-gravity '.$this->_getAnchorGravity();
			$this->_convertactionstack[] = '-extent '.$width.'x'.$height;
			return true;
		}

		// Resize the image resource and return the status
		return $this->_imageProcess($width, $height, "frame");
	}

	/**
	 * Resize the image to specified new dimensions. If only one dimension is supplied, the
	 * image is resized proportionally on the other dimension.
	 *
	 * @param integer $width  The width of the target image (Optional)
	 * @param integer $height The height the target imge (Optional)
	 * @return boolean Whether the contrain and frame was successfully executed
	 */
	public function resizeTo($width = false, $height = false) {
		if (!$this->_isvalid or (!$width and !$height)) return false;

		// If using convert, add the action to the stack and return.
		// ! tells ImageMagick to ignore the standard aspect ratio constraints
		if ($this->_useconvert) {
			$this->_convertactionstack[] = '-resize '.($width ? $width : '').'x'.($height ? $height : '').(($width and $height) ? '!' : '');
			return true;
		}

		if (!$width) $width = (($height / $this->_height) * $this->_width);
		if (!$height) $height = (($width / $this->_width) * $this->_height);

		// Resize the image resource and return the status
		return $this->_imageProcess($width, $height);
	}

	/**
	 * Trim a specified colour from the edges of the image, with the ability to allow
	 * slight variations in colour and to over-trim the image to remove extra pixels for
	 * fades.
	 * Note this is only supported when using the `convert` codepath.
	 *
	 * @param integer $red        The 8-bit red component of the background colour, from 0 to 255.
	 * @param integer $green      The 8-bit green component of the background colour, from 0 to 255.
	 * @param integer $blue       The 8-bit blue component of the background colour, from 0 to 255.
	 * @param float   $colourFuzz (optional) The amount of colour variation to tolerate, where 0 is no variation and 1 is 100% variation
	 * @param integer $overTrim   (optional) The number of additional pixels to trim from all edges
	 *
	 * @return boolean Whether the trim command was successfully readied
	 */
	public function trimColor($red, $green, $blue, $colourFuzz = 0.05, $overTrim = 0) {
		if (!$this->_useconvert) {
			throw new ImageException('TrimColour operations are only supported with ImageMagick');
		}

		// Validate...
		$red = (int)$red;
		$green = (int)$green;
		$blue = (int)$blue;
		if ($red < 0 or $red > 255 or $green < 0 or $green > 255 or $blue < 0 or $blue > 255) {
			return false;
		}

		$fuzzPercentage = round(max(0, min(1, $colourFuzz)) * 100);

		// Add a border of 1px of the specified colour so only that colour will be trimmed
		$this->_convertactionstack[] = "-bordercolor 'rgb(" . $red . ',' . $green . ',' . $blue .")'";
		$this->_convertactionstack[] = '-border 1x1 +repage';

		// Add the trim operation itself
		if ($fuzzPercentage) {
			$this->_convertactionstack[] = '-fuzz ' . $fuzzPercentage . '%';
		}
		$this->_convertactionstack[] = '-trim +repage -fuzz 0%';

		// Add an overtrim if specified
		if ($overTrim) {
			$this->_convertactionstack[] = '-shave ' . $overTrim . 'x' . $overTrim . ' +repage';
		}

		return true;
	}
	/**
	 * Output the image to the browser, in the current target image format, prefixed with appropriate content-type header.  Returns false on failure.
	 *
	 * @return boolean
	 */
	public function outputToBrowser() {
		if (!$this->_isvalid) return false;

		$imagedata = $this->returnAsData();
		if ($imagedata) {
			header("Content-type: ".$this->_convertFormatToMime($this->_targetformat));
			echo $imagedata;
		} else {
			return false;
		}
	}

	/**
	 * Returns the image, in the current target image format, as a data string
	 *
	 * @return string The data for the image.
	 */
	public function returnAsData() {
		if (!$this->_isvalid) return false;

		// Use the appropriate private function to return the data as a string
		return $this->_imageOutput($this->_targetformat);
	}


	/**
	 * Saves the image to a specified directory
	 *
	 * Uses either a specified filename, or an automatically generated filename based on the original name.
	 * Depending on the setting of setShouldOverwriteFiles, any existing file will either be overwritten,
	 * or a unique filename will be generated.
	 *
	 * @param string $directory The target directory (not including a trailing slash)
	 * @param string $filename  The target filename (Optional)
	 * @return mixed The new filename, or false in case of error.
	 */
	public function saveToPath($directory, $filename = false) {
		if (!$this->_isvalid) return false;

		// Perform basic error checking on the directory - exists and is considered writable
		if (!is_dir($directory) or !is_writable($directory)) {
			$this->errorstring = "The destination directory is not writable or does not exist.";
			$this->errornum = 20;
			return false;
		}

		// If a filename has been provided, ensure it is valid according to the overwrite setting
		if ($filename) {
			$filename = str_replace("/", "-", $filename);

		// If no filename has been provided, use the currently existing filename as a basis for the new:
		} else {
			$extensions = array("PNG"=>"png", "JPG"=>"jpg", "GIF"=>"gif", "JP2"=>"jp2");
			$oldfilename = end(explode("/", $this->_filepath));
			if (strrpos($oldfilename, ".")) $oldfilename = substr($oldfilename, 0, strrpos($oldfilename, "."));
			$filename = $oldfilename.".".$extensions[$this->_targetformat];
		}

		// If we're set to overwrite files, and the file already exists, attempt to unlink the target file
		// first so the image creation functions won't report any problems.
		if ($this->_overwritefiles) {

			// Only do so for filepaths which are different from the original, so that altering
			// an image in-place can still function.  In this case, ensure the file is writable.
			if ($directory."/".$filename == $this->_filepath) {
				if (!is_writable($this->_filepath)) {
					$this->errorstring = "The file you are attempting to overwrite is not writable.";
					$this->errornum = 22;
					return false;
				}
			} elseif (file_exists($directory."/".$filename)) {
				if (!@unlink($directory."/".$filename)) {
					$this->errorstring = "The destination file could not be overwritten.";
					$this->errornum = 21;
					return false;
				}
			}

		// If overwrite is set to off, generate a unique filename
		} else {
			$filename = CommonV2::uniqueFilename($directory, $filename);
		}

		// Use the appropriate private function to return the data as a string, returning the status
		$status = $this->_imageOutput($this->_targetformat, $directory."/".$filename);

		// If the process has been successful, return the new filename, else return false
		return ($status)?$filename:false;
	}


	/**
	 * A static function to remove comments, EXIF data, and other application-specific information
	 * from JPEG data supplied as a string.  This does not recompress the JPEG data, only removes
	 * comments, and so has no additional effect on the image quality.
	 *
	 * @param string $jpegData The JPEG image data as a string.
	 *
	 * @return string The JPEG data with EXIF data and comments removed, or the untouched data.
	 */
	public static function stripEXIFDataFromJPEGData($jpegData) {
		static $jpegHeader = "\xFF\xD8";

		$_getMarkerPositionInData = function($jpegDataString, $previousMarkerPosition) {
			static $jpegMarkerByte = "\xFF";
			return strpos($jpegDataString, $jpegMarkerByte, $previousMarkerPosition);
		};

		$_getMarkerMetaDataLength = function($jpegDataString, $markerPosition) {
			static $jpegMarkerApplicationOrdLow = 225;
			static $jpegMarkerApplicationOrdHigh = 239;
			static $jpegMarkerCommentByte = "\xFE";

			// Safety-check
			if (strlen($jpegDataString) < $markerPosition + 4) return 0;

			// Check the byte following the marker.  If it's in-between the low and high points for
			// JPEG application segments (FF E1 to FF EF), or is a JPEG comment section (FF FE), the
			// section is removable metadata.
			$markertype = substr($jpegDataString, $markerPosition + 1, 1);
			$markertypeord = ord($markertype);
			$ismetadata = (($markertypeord >= $jpegMarkerApplicationOrdLow and $markertypeord <= $jpegMarkerApplicationOrdHigh) or $markertype == $jpegMarkerCommentByte);

			// If the data is not metadata, return a length of 0.
			if (!$ismetadata) return 0;

			// The next two bytes indicate the metadata length, including the length marker
			// but not the jpeg marker.  Return the value.
			$metaDataStringLength = 2;
			$metaDataStringLength += 256 * ord($jpegDataString[$markerPosition + 2]);
			$metaDataStringLength += ord($jpegDataString[$markerPosition + 3]);
			return $metaDataStringLength;
		};

		// Check the start of the string for the jpeg marker; if it isn't found, return the unaltered string
		if (strlen($jpegData) < 2 or substr($jpegData, 0, 2) !== $jpegHeader) return;

		$markerposition = strlen($jpegHeader);

		// Process the string looking for each jpeg marker
		while ($markerposition = $_getMarkerPositionInData($jpegData, $markerposition)) {

			// If this section is *not* metadata, continue
			$metaDataLength = $_getMarkerMetaDataLength($jpegData, $markerposition);
			if (!$metaDataLength) {
				$markerposition += 2;
				continue;
			}

			// Continue until the next non-metadata part of the data.  If another marker is *not* found,
			// also don't strip the metadata.
			while ($nextmarkerposition = $_getMarkerPositionInData($jpegData, $markerposition + $metaDataLength)) {
				$nextMetaDataLength = $_getMarkerMetaDataLength($jpegData, $nextmarkerposition);
				if ($nextMetaDataLength) {
					$metaDataLength += $nextMetaDataLength;
					continue;
				}

				// New position is not metadata - remove the metadata chunk in its entirety.
				$jpegData = substr($jpegData, 0, $markerposition) . substr($jpegData, $markerposition + $metaDataLength);
				break;
			}
		}

		return $jpegData;
	}


	/**
	 * A static function to apply PNG compression - via OptiPNG - to the supplied file.
	 * The resulting image should be visually identical, although encoding errors will
	 * be fixed and therefore could result in small filesize increases in edge cases
	 * (although the image would previously fail on some devices).
	 *
	 * Any errors during compression will throw an exception.
	 *
	 * @param string $imagePath  The path to the image to compress, which can be PNG, GIF, BMP, or TIFF.
	 * @param string $targetPath The optional path to save the compressed image to; if not supplied, the file will be compressed in-place.
	 *
	 * @return void
	 */
	public static function optimizePNG($imagePath, $targetPath = false) {
		if (!$targetPath) {
			$targetPath = $imagePath;
		}

		exec('optipng -fix '.escapeshellcmd($imagePath).' -out '.escapeshellcmd($targetPath).' 2>&1', $optipngoutput, $compressstatus);

		if ($compressstatus) {
			throw new ImageException('PNG compression failed', get_defined_vars());
		}
	}


	/**
	 * Return or output the image
	 *
	 * @param string $format     Output image format
	 * @param string $targetpath Path to save the file; if no path is provided, the data will be returned as a string (Optional)
	 * @return mixed Image data or true on success, false on failure
	 */
	private function _imageOutput($format, $targetpath = NULL) {
		$status = false;

		// Apply background colour if necessary
		if ($this->_bgchangerequired and !$this->_bghaschanged) {
			$this->constrainAndFrameTo($this->_width, $this->_height);
		}

		$this->_mimetype = $this->_convertFormatToMime($format);

		if ($this->_useconvert) {
			$filepath = (($targetpath)?$targetpath:"-");
			$imagedata = false;
			switch($format) {

				// Output PNGs with interlacing and high compression
				case "PNG":
					$imagedata = shell_exec($this->_convertlocation." ".escapeshellarg($this->_filepath)."[0] ".implode(" ", $this->_convertactionstack)." -strip -density 72x72 -quality 95 -interlace line  -colorspace RGB PNG:".escapeshellarg($filepath)." 2>&1");
					break;

				// Output JPEGs with interlacing and compression - as set, or using the default level
				case "JPG":
					$imagedata = shell_exec($this->_convertlocation." ".escapeshellarg($this->_filepath)."[0] -background 'rgb(255, 255, 255)' ".implode(" ", $this->_convertactionstack)." -flatten -strip -density 72x72 -quality ".$this->_jpegcompressionlevel." -interlace line -colorspace RGB JPG:".escapeshellarg($filepath)." 2>&1");
					break;

				case "JP2":
					$imagedata = shell_exec($this->_convertlocation." ".escapeshellarg($this->_filepath)."[0] -background 'rgb(255, 255, 255)' ".implode(" ", $this->_convertactionstack)." -flatten -define jp2:lazy -strip -density 72x72 -define jp2:rate=".round($this->_jpegcompressionlevel / 100, 4)." -colorspace RGB JP2:".escapeshellarg($filepath)." 2>&1");
					break;

				// Output GIFs as a palletted image with transparency where available.
				case "GIF":
					$imagedata = shell_exec($this->_convertlocation." ".escapeshellarg($this->_filepath)."[0] ".implode(" ", $this->_convertactionstack)." -flatten -density 72x72 GIF:".escapeshellarg($filepath)." 2>&1");
					break;
			}
			if ($targetpath) {
				return true;
			} else {
				return $imagedata;
			}

		} else {
			if (!$targetpath) {
				ob_start();
			}
			switch($format) {

				// PNGs are simple: switch on interlacing, output with max compression.
				case "PNG":
					imageinterlace($this->_resource, 1);
					$status = @imagepng($this->_resource, $targetpath, 9, NULL);
					break;

				// For JPEGs, output with the compression as set, or the original if appropriate
				case "JPG":
					imageinterlace($this->_resource, 1);

					// For JPEGs, passthrough if possible to avoid recompression and so preserve quality
					if (!$this->_haschanged and $this->_jpegcompressionlevel >= 80 and is_readable($this->_filepath)) {
						$jpegdata = @file_get_contents($this->_filepath);
						if ($targetpath) {
							$status = @file_put_contents($targetpath, $data);
						} else {
							if ($jpegdata) {
								echo $jpegdata;
								$status = true;
							} else {
								$status = false;
							}
						}
						if (!$jpegdata or ($targetpath and !$status)) {
							$status = @imagejpeg($this->_resource, $targetpath, $this->_jpegcompressionlevel);
						}
					} else {
						$status = @imagejpeg($this->_resource, $targetpath, $this->_jpegcompressionlevel);
					}
					break;

				// GIFs require conversion to a paletted image before output
				case "GIF":

					$paletteresource = imagecreatetruecolor($this->_width, $this->_height);
					imagecopy($paletteresource, $this->_resource, 0, 0, 0, 0, $this->_width, $this->_height);
					imagetruecolortopalette($paletteresource, false, 255);

					// Attempt to detect if a transparenct colour should be used to preserve transparency
					if (!empty($this->_transcolour) and !$this->_bghaschanged) {
						$transcolour = imagecolorallocate($paletteresource, $this->_transcolour["red"], $this->_transcolour["green"], $this->_transcolour["blue"]);
						imagecolortransparent($paletteresource, $transcolour);
					} else {
						$i = 1;
						while (imagecolorexact($paletteresource, 0, 255 - floor($i / 2), 255 - ceil($i / 2)) != -1) {
							$i++;
						}
						$transcolour = imagecolorallocate($paletteresource, 0, 255 - floor($i / 2), 255 - ceil($i / 2));
						imagecolortransparent($paletteresource, $transcolour);
					}

					// Preserve transparency where appropriate
					if (!$this->_bghaschanged or $this->_bgcolouralpha > 125) {
						for ($x = 0; $x < $this->_width; $x++) {
							for ($y = 0; $y < $this->_height; $y++) {
								$rgba = imagecolorat($this->_resource, $x, $y);
								if ((($rgba & 0x7F000000) >> 24) > 125) {
									imagesetpixel($paletteresource, $x, $y, $transcolour);
								}
							}
						}
					}

					$status = @imagegif($paletteresource, $targetpath);
					imagedestroy($paletteresource);
					break;
			}
			if (!$targetpath) {
				$imagedata = ob_get_contents();
				ob_end_clean();
				return (($status)?$imagedata:false);
			} else {
				return $status;
			}
		}
	}

	/**
	 * Resizes and/or frames the image
	 *
	 * A worker function to perform resizes and frames on an image resource, attempting to preserve
	 * as much quality as possible (and transparency for GIFs and PNGs where appropriate).
	 *
	 * @param integer $targetwidth  The target width to resize the image resource to.  Required for non-convert path.
	 * @param integer $targetheight The target height to resize the image resource to.  Required for non-convert path.
	 * @param string  $useframe     If this is set to "frame", aspect ratio will be maintained and the remaining space filled with the background colour.  If this is set to "crop", aspect ratio will be maintained and any excess size once the closest dimensions have been matches is cropped (see constrainAndCropTo()).  If this is set to false, aspect ratio will be ignored and the resource resized to exactly match the dimensions specified (Optional)
	 * @return boolean Whether the resize was successfully executed.
	 */
	private function _imageProcess($targetwidth, $targetheight, $useframe = false) {

		if ($this->_useconvert) {
			throw new ImageException('_imageProcess code path should only be used with the native GD code path');
		}

		// Determine whether the image needs to be resized
		if ($this->_width > $targetwidth or $this->_height > $targetheight or !$useframe) {

			// For speed and efficiency, if the target image is much smaller than the current image
			// - as can be the case when downsampling a large image for a thumbnail - use imagecopyresized
			// to resize down to a reasonable image quickly, followed by a standard resample.
			if ($this->_height > (4 * $targetheight) and $this->_width > (4 * $targetwidth)) {

				// Determine the target height and width proportionally to preserve aspect ratio for useframe
				if ($useframe) {
					$rescaleratio = min($targetwidth / $this->_width, $targetheight / $this->_height);
					$resamplewidth = round($this->_width * $rescaleratio * 4);
					$resampleheight = round($this->_height * $rescaleratio * 4);
				} else {
					$resampleheight = $targetheight * 4;
					$resamplewidth = $targetwidth * 4;
				}

				$resizeresource = imagecreatetruecolor($resamplewidth, $resampleheight);
				$this->_applyTransparencyProtection($resizeresource);

				// Perform a resize, in preparation for the resample
				imagecopyresized($resizeresource, $this->_resource, 0, 0, 0, 0, $resamplewidth, $resampleheight, $this->_width, $this->_height);

				// Replace the original resource with the updated resource
				imagedestroy($this->_resource);
				$this->_resource = imagecreatetruecolor($resamplewidth, $resampleheight);
				$this->_applyTransparencyProtection($this->_resource);
				imagecopy($this->_resource, $resizeresource, 0, 0, 0, 0, $resamplewidth, $resampleheight);
				imagedestroy($resizeresource);
				$this->_width = $resamplewidth;
				$this->_height = $resampleheight;
			}


			if ($this->_height > $targetheight or $this->_width > $targetwidth or !$useframe) {

				// Determine the target height and width proportionally to preserve aspect ratio for useframe.
				// Depending on whether we're cropping or framing the image, we use the max and min sizes as
				// appropriate.
				if ($useframe == "frame") {
					$rescaleratio = min($targetwidth / $this->_width, $targetheight / $this->_height);
				} elseif ($useframe == "crop") {
					$rescaleratio = min(1, max($targetwidth / $this->_width, $targetheight / $this->_height));
				}
				if ($useframe) {
					$resamplewidth = round($this->_width * $rescaleratio);
					$resampleheight = round($this->_height * $rescaleratio);
				} else {
					$resampleheight = $targetheight;
					$resamplewidth = $targetwidth;
				}

				// If a resize is required, use imagecopyresampled to preserve as much quality as possible.
				$resampleresource = imagecreatetruecolor($resamplewidth, $resampleheight);
				$this->_applyTransparencyProtection($resampleresource);

				// Perform the resample
				imagecopyresampled($resampleresource, $this->_resource, 0, 0, 0, 0, $resamplewidth, $resampleheight, $this->_width, $this->_height);

				// Replace the original resource with the updated resource
				imagedestroy($this->_resource);
				$this->_resource = imagecreatetruecolor($resamplewidth, $resampleheight);
				$this->_applyTransparencyProtection($this->_resource);
				imagecopy($this->_resource, $resampleresource, 0, 0, 0, 0, $resamplewidth, $resampleheight);
				imagedestroy($resampleresource);

				$this->_width = $resamplewidth;
				$this->_height = $resampleheight;
			}

			$this->_haschanged = true;
		}

		// Frame/crop and background if necessary.
		if (($useframe and ($this->_width != $targetwidth or $this->_height != $targetheight)) or $this->_bgcolouralpha != 127) {

			// Perform a copy onto a new canvas of the correct dimensions.
			$frameresource = imagecreatetruecolor($targetwidth, $targetheight);
			$this->_applyTransparencyProtection($frameresource);

			imagealphablending($frameresource, false);
			$framecolour = imagecolorallocatealpha($frameresource, $this->_bgcolourred, $this->_bgcolourgreen, $this->_bgcolourblue, $this->_bgcolouralpha);
			imagefilledrectangle($frameresource, 0, 0, $targetwidth - 1, $targetheight - 1, $framecolour);
			imagesavealpha($frameresource, true);
			imagealphablending($frameresource, true);

			imagecopy($frameresource, $this->_resource, floor(($targetwidth - $this->_width) * $this->_xaxisanchorpoint), floor(($targetheight - $this->_height) * $this->_yaxisanchorpoint), 0, 0, $this->_width, $this->_height);

			imagedestroy($this->_resource);
			$this->_resource = imagecreatetruecolor($targetwidth, $targetheight);
			$this->_applyTransparencyProtection($this->_resource);
			imagecopy($this->_resource, $frameresource, 0, 0, 0, 0, $targetwidth, $targetheight);
			imagedestroy($frameresource);

			$this->_width = $targetwidth;
			$this->_height = $targetheight;

			$this->_haschanged = true;
			$this->_bghaschanged = true;
		}

		return true;
	}

	/**
	 * Preserve transparency on an image resource where appropriate.
	 *
	 * @param imageresource &$resource The image resource to attempt to preserve transparency to.
	 * @return void
	 */
	private function _applyTransparencyProtection(&$resource) {
		imagealphablending($resource, false);
		$transcolour = imagecolorallocatealpha($resource, 0, 0, 0, 127);
		imagefilledrectangle($resource, 0, 0, imagesx($resource) - 1, imagesy($resource) - 1, $transcolour);
		imagesavealpha($resource, true);
	}

	/**
	 * Import images from bitmap format
	 *
	 * Cleaned up version of code pasted to the PHP manual by DH Kold (admin@dhkold.com) in 2005.
	 *
	 * @param string $filepath The path to the original bitmap image file.
	 * @return object The image resource if successful
	 */
	private function _imageCreateFromBmp($filepath) {
		if (!$f1 = @fopen($filepath, "rb")) return false;

		// Read the bitmap's vital details
		$file = unpack("vfile_type/Vfile_size/Vreserved/Vbitmap_offset", fread($f1, 14));
		if ($file["file_type"] != 19778) return false;

		$bmp = unpack('Vheader_size/Vwidth/Vheight/vplanes/vbits_per_pixel/Vcompression/Vsize_bitmap/Vhoriz_resolution/Vvert_resolution/Vcolors_used/Vcolors_important', fread($f1, 40));
		$bmp["colors"] = pow(2, $bmp["bits_per_pixel"]);
		if ($bmp["size_bitmap"] == 0) $bmp["size_bitmap"] = $file["file_size"] - $file["bitmap_offset"];
		$bmp["bytes_per_pixel"] = $bmp["bits_per_pixel"] / 8;
		$bmp["bytes_per_pixel2"] = ceil($bmp["bytes_per_pixel"]);
		$bmp["decal"] = $bmp["width"] * $bmp["bytes_per_pixel"] / 4;
		$bmp["decal"] -= floor($bmp["width"] * $bmp["bytes_per_pixel"] / 4);
		$bmp["decal"] = 4 - (4 * $bmp["decal"]);
		if ($bmp["decal"] == 4) $bmp["decal"] = 0;

		// Construct the array of palette colours
		$palette = array();
		if ($bmp["colors"] < 16777216) {
			$palette = unpack("V".$bmp["colors"], fread($f1, $bmp["colors"] * 4));
		}

		// Create the image resource
		$img = fread($f1, $bmp['size_bitmap']);
		$space = chr(0);
		$res = imagecreatetruecolor($bmp["width"], $bmp["height"]);
		$p = 0;
		$y = $bmp["height"] - 1;
		while ($y >= 0) {
			$x = 0;
			while ($x < $bmp["width"]) {
				if ($bmp["bits_per_pixel"] == 24) {
					$color = unpack("V", substr($img, $p, 3).$space);
				} elseif ($bmp["bits_per_pixel"] == 16) {
					$color = unpack("n", substr($img, $p, 2));
					$color[1] = $palette[$color[1] + 1];
				} elseif ($bmp["bits_per_pixel"] == 8) {
					$color = unpack("n", $space.substr($img, $p, 1));
					$color[1] = $palette[$color[1] + 1];
				} elseif ($bmp["bits_per_pixel"] == 4) {
					$color = unpack("n", $space.substr($img, floor($p), 1));
					if (($p * 2) % 2 == 0) {
						$color[1] = ($color[1] >> 4);
					} else {
						$color[1] = ($color[1] & 0x0F);
					}
					$color[1] = $palette[$color[1] + 1];
				} elseif ($bmp["bits_per_pixel"] == 1) {
					$color = unpack("n", $space.substr($img, floor($p), 1));
					if (($p * 8) % 8 == 0) $color[1] = $color[1] >> 7;
					elseif (($p * 8) % 8 == 1) $color[1] = ($color[1] & 0x40) >> 6;
					elseif (($p * 8) % 8 == 2) $color[1] = ($color[1] & 0x20) >> 5;
					elseif (($p * 8) % 8 == 3) $color[1] = ($color[1] & 0x10) >> 4;
					elseif (($p * 8) % 8 == 4) $color[1] = ($color[1] & 0x8) >> 3;
					elseif (($p * 8) % 8 == 5) $color[1] = ($color[1] & 0x4) >> 2;
					elseif (($p * 8) % 8 == 6) $color[1] = ($color[1] & 0x2) >> 1;
					elseif (($p * 8) % 8 == 7) $color[1] = ($color[1] & 0x1);
					$color[1] = $palette[$color[1] + 1];
				} else {
					return false;
				}
				imagesetpixel($res, $x, $y, $color[1]);
				$x++;
				$p += $bmp["bytes_per_pixel"];
			}
			$y--;
			$p += $bmp["decal"];
		}

		// Close the file
		fclose($f1);

		return $res;
	}

	/**
	 * Retrieve the imagemagick "gravity" for the current anchor points.
	 *
	 * @return string an ImageMagick-compatible "gravity" string.
	 */
	private function _getAnchorGravity() {
		$gravities = array(
			'0' => array('0' => 'SouthWest', '0.5' => 'West', '1' => 'NorthWest'),
			'0.5' => array('0' => 'South', '0.5' => 'Center', '1' => 'North'),
			'1' => array('0' => 'SouthEast', '0.5' => 'East', '1' => 'NorthEast')
		);
		return $gravities[(string)$this->_xaxisanchorpoint][(string)$this->_yaxisanchorpoint];
	}

	/**
	 * Returns the mime type that equates to a specified ImageMagick-detected file type
	 *
	 * @param string $format The ImageMagick format string (see "convert -identify -list format")
	 * @return string The associated mime type (or 'application/octet-stream' for unrecognised types)
	 */
	private function _convertFormatToMime($format) {
		$formats = array(
			"bmp" => "image/bmp",
			"bmp2" => "image/bmp",
			"bmp3" => "image/bmp",
			"cur" => "image/x-win-bitmap", // MS icon format
			"epdf" => "application/pdf",
			"epi" => "application/postscript",
			"eps" => "application/postscript",
			"eps2" => "application/postscript",
			"eps3" => "application/postscript",
			"epsf" => "application/postscript",
			"epsi" => "application/postscript",
			"ept" => "application/postscript",
			"ept2" => "application/postscript",
			"ept3" => "application/postscript",
			"fax" => "image/g3fax", // TIFF-based fax.  Non-square pixels...
			"fits" => "image/x-fits",
			"g3" => "image/g3fax", // See 'fax'
			"gif" => "image/gif",
			"gif87" => "image/gif",
			"icb" => "application/x-icb", // Targa
			"ico" => "image/x-win-bitmap", // MS icon format
			"icon" => "image/x-win-bitmap", // MS icon format
			"jng" => "image/jng",
			"jp2" => "image/jp2", // JPEG 2000
			"jpeg" => "image/jpeg",
			"jpg" => "image/jpeg",
			"m2v" => "video/mpeg2",
			"miff" => "application/x-mif", // ImageMagick format
			"mng" => "video/mng",
			"mpeg" => "video/mpeg",
			"mpg" => "video/mpeg",
			"otb" => "image/x-otb", // On-the-air bitmap (?)
			"p7" => "image/x-xv", // PPM-based XV thumbnails
			"palm" => "image/x-palm",
			"pbm" => "image/pbm", // Portable bitmap, 1-bit colour
			"pcd" => "image/pcd", // Photo CD
			"pcds" => "image/pcd", // Photo CD
			"pcl" => "application/pcl",
			"pct" => "image/pict",
			"pcx" => "image/x-pcx", // IBM-PC paintbrush
			"pdb" => "application/vnd.palm", // Palm database imageviewer images
			"pdf" => "application/pdf",
			"pgm" => "image/x-pgm", // Portable graymap format (?)
			"picon" => "image/xpm", // Personal icon (?)
			"pict" => "image/pict",
			"pjpeg" => "image/pjpeg",
			"png" => "image/png",
			"png24" => "image/png",
			"png32" => "image/png",
			"png8" => "image/png",
			"pnm" => "image/pbm", // Portable anymap (?)
			"ppm" => "image/x-ppm",
			"ps" => "application/postscript",
			"psd" => "image/x-photoshop",
			"ptif" => "image/x-ptiff", // Pyramid encoded TIFFs
			"ras" => "image/ras", // Sun rasterfiles
			"sgi" => "image/sgi",
			"sun" => "image/ras",
			"svg" => "image/svg+xml",
			"svgz" => "image/svg",
			"tga" => "image/tga",
			"tif" => "image/tiff",
			"tiff" => "image/tiff",
			"vda" => "image/vda", // Targa
			"vst" => "image/vst", // Targa
			"wbmp" => "image/vnd.wap.wbmp", // Wireless bitmap (not Windows bitmap!)
			"xbm" => "image/x-xbitmap", // XWindows 1-bit colour bitmap
			"xpm" => "image/x-xbitmap", // XWindows colour bitmap
			"xwd" => "image/xwd", // XWindows system window dump
		);

		$format = strtolower($format);
		if (!empty($formats[$format])) return $formats[$format];
		return "application/octet-stream";
	}

	/**
	 * Delete temporary files on exit
	 *
	 * @return void
	 */
	function __destruct() {
		if ($this->_temporaryfilepath and is_file($this->_temporaryfilepath)) {
			@unlink($this->_temporaryfilepath);
		}
	}
}
