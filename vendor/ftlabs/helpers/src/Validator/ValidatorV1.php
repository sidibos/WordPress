<?php
/**
 * Library of validation functions
 *
 * @codingstandard ftlabs-phpcs
 * @copyright The Financial Times Limited [All Rights Reserved]
 */

namespace FTLabs\Validator;

use FTLabs\Common\CommonV2;

class ValidatorV1 {

	public static function isEmail($str) {
		$qtext = '[^\\x0d\\x22\\x5c\\x80-\\xff]';
		$dtext = '[^\\x0d\\x5b-\\x5d\\x80-\\xff]';
		$atom = '[^\\x00-\\x20\\x22\\x28\\x29\\x2c\\x2e\\x3a-\\x3c\\x3e\\x40\\x5b-\\x5d\\x7f-\\xff]+';
		$quoted_pair = '\\x5c[\\x00-\\x7f]';
		$domain_literal = "\\x5b($dtext|$quoted_pair)*\\x5d";
		$quoted_string = "\\x22($qtext|$quoted_pair)*\\x22";
		$domain_ref = $atom;
		$sub_domain = "($domain_ref|$domain_literal)";
		$word = "($atom|$quoted_string)";
		$domain = "$sub_domain(\\x2e$sub_domain)*";
		$local_part = "$word(\\x2e$word)*";
		$addr_spec = "$local_part\\x40$domain";
		return preg_match("!^$addr_spec\z!", $str) ? 1 : 0;
	}

	public static function isDate($str) {
		return (CommonV2::convertHumanTime($str)) ? true : false;
	}

	public static function isURL($str) {
		$scheme		= "(http:\/\/|https:\/\/)";
		$www		= "www\.";
		$ip			= "\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}";
		$name		= "[a-z0-9][-a-z0-9]+\.";
		$tld		= "[a-z]{2,}(\.[a-z]{2,2})?";
		$the_rest	= "(\/?[a-z0-9\._\/\,\:\|\^\@\!~#&=;%+?-]+[a-z0-9\/#=?])?";
		$pattern	= "/^(".$scheme."?(?(2)(".$ip."|(".$name.")+".$tld.")|(".$www."(".$name.")+".$tld.")))(".$the_rest.")\z/is";
		if (!preg_match("/^".$scheme."/is", $str)) $str = "http://".$str;
		$op = preg_match($pattern, $str, $m) ? strtolower($m[1]).$m[9] : false;
		return $op;
	}
	public static function isURI($str) {
		return self::isURL($str);
	}

	public static function isPostCode($str) {
		return preg_match("/^(((([A-PR-UWYZ][0-9][0-9A-HJKS-UW]?)|" . "([A-PR-UWYZ][A-HK-Y][0-9][0-9ABEHMNPRV-Y]?))\s{0,2}[0-9]" . "([ABD-HJLNP-UW-Z]{2}))|(GIR\s{0,2}0AA))\z/i", $str);
	}

	public static function isUKPhone($str) {
		return preg_match("/^[0-9 \+\(\)]{5,20}\z/i", $str);
	}

	public static function isPhone($str) {
		return preg_match("/^[0-9 \+\(\)]{5,20}\z/i", $str);
	}

	public static function isAlpha($str) {
		return ctype_alpha($str);
	}

	public static function isAlphanum($str) {
		return ctype_alnum($str);
	}

	public static function isInteger($str) {

		// The function is_int() always returns false for strings; ctype_digit() returns false for ints, so cast to string
		return ctype_digit((string)$str);
	}

	public static function isNumeric($str) {
		return is_numeric($str);
	}

	public static function isXML($str) {
		libxml_use_internal_errors(true);
		libxml_clear_errors();
		$options = (strpos($str, '<!DOCTYPE') !== false) ? (LIBXML_DTDLOAD + LIBXML_DTDVALID) : 0;
		simplexml_load_string($str, 'SimpleXMLElement', $options);
		$errors = libxml_get_errors();
		return (empty($errors) or $errors[0]->level == LIBXML_ERR_WARNING) ? true : false;
	}

	public static function isXHTMLFragment($str, $usesafemode=false) {
		if ($usesafemode) {
			return self::isXML("<!DOCTYPE fragment_under_test PUBLIC \"-//W3C//DTD XHTML 1.0 Transitional//EN\" \"http://www.assanka.net/dtds/xhtml-content-restrictive.dtd\">\n<fragment_under_test>".$str."</fragment_under_test>");
		} else {
			return self::isXML("<!DOCTYPE html PUBLIC \"-//W3C//DTD XHTML 1.0 Transitional//EN\" \"http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd\">\n<html><head><title>Test fragment</title></head><body>".$str."</body></html>");
		}
	}

	public static function getXHTMLErrors($str, $usesafemode=false) {
		if ($usesafemode) {
			$str = "<!DOCTYPE fragment_under_test PUBLIC \"-//W3C//DTD XHTML 1.0 Transitional//EN\" \"http://www.assanka.net/dtds/xhtml-content-restrictive.dtd\">\n<fragment_under_test>".$str."</fragment_under_test>";
		} else {
			$str = "<!DOCTYPE html PUBLIC \"-//W3C//DTD XHTML 1.0 Transitional//EN\" \"http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd\">\n<html><head><title>Test fragment</title></head><body>".$str."</body></html>";
		}
		libxml_use_internal_errors(true);
		libxml_clear_errors();
		$options = (strpos($str, '<!DOCTYPE') !== false) ? (LIBXML_DTDLOAD + LIBXML_DTDVALID) : 0;
		simplexml_load_string($str, 'SimpleXMLElement', $options);
		$errors = libxml_get_errors();
		$errorlist = array();
		foreach ($errors as $e) {
			$msg = preg_replace("/Element (\w+) does not carry attribute (\w+)/", "Required attribute '$2' must be present on $1 elements", $e->message);
			$errorlist[] = 'Line '.$e->line.': '.$msg;
		}
		return $errorlist;
	}

	public static function checkField($value, $field) {

		$msg = "Invalid value";
		if (!isset($field["type"])) $field["type"] = "text";
		if (isset($field['validationcallback']) and is_callable($field['validationcallback'])) {
			$valid = call_user_func($field['validationcallback'], $value);
			if (is_array($valid)) {
				$msg = $valid[1];
				$valid = $valid[0];
			}
		} elseif ($field["type"] == "select" or $field["type"] == "enum") {
			$valid = (in_array($value, array_keys($field["options"]))) ? 1 : 0;
			$msg = "Selection must be one of the available options";
		} elseif ($field["type"] == "upload" or $field["type"] == "imageupload") {
			$destdir = (!empty($field["uploaddest"])) ? $field["uploaddest"] : "/lib/tmp/uploadedfiles";
			$filepath = $_SERVER["DOCUMENT_ROOT"].$destdir."/".$value;
			$valid = (is_file($filepath)) ? 1 : 0;
			if (!$valid and !empty($field["existingfile"]) and $field["existingfile"] == $value) $valid = 1;
			$msg = "No file received";
		} elseif ($field["type"] != "hidden" and !empty($field["validation"])) {
			if ($field["validation"] == "date") {
				$valid = (CommonV2::convertHumanTime($value)) ? 1 : 0;
				$msg = "Not a valid date";
			} elseif ($field["validation"] == "postcode") {
				$valid = (self::isPostCode($value) == true);
				$msg = "Not a valid UK Postcode";
			} elseif ($field["validation"] == "email") {
				$valid = (self::isEmail($value) == true);
				$msg = "Not a valid email address";
			} elseif ($field["validation"] == "url") {
				$valid = (self::isURL($value) == true);
				$msg = "Not a valid web address (URL)";
			} elseif ($field["validation"] == "phone") {
				$valid = (self::isPhone($value) == true);
				$msg = "Not a valid telephone number";
			} elseif ($field["validation"] == "word") {
				$valid = (preg_match("/[\w ]{3,}/", $value) == true);
				$msg = "Not valid";
				trigger_error('Deprecated validation method', E_USER_NOTICE);
			} else {
				$valid = (preg_match($field["validation"], $value) == true);
				$msg = "Not the correct format";
			}
		} else {
			switch ($field["type"]) {
				case "float":
					$value = str_replace(array(" ", ","), "", $value);
					$valid = (is_numeric($value)) ? 1:0;
					$msg = "Not a valid number";
					break;
				case "integer":
					$value = str_replace(array(" ",","), "", $value);
					$valid = (preg_match("/^\-?\d+$/", $value)) ? 1:0;
					$msg = "Must be a whole number";
					break;
				case "date":
				case "datetime":
				case "datetimeseconds":
				case "time":
				case "timeseconds":
					$valid = (CommonV2::convertHumanTime($value)) ? 1 : 0;
					$msg = "Not a valid date or time";
					break;
				case "percent":
					$pc = trim($value, " %");
					$valid = (is_numeric($pc)) ? 1 : 0;
					$msg = "Could not interpret this as a percentage";
					break;
				case "text":
				case "textarea":
				case "richtext":
				case "autocomplete":
				case "colorpick":
				case "check":
				case "checklist":
				case "boolean":
				case "dummy":
				case "password":
					$valid = 1;
					break;
				default:
					trigger_error("Unable to validate field - this is an unknown field type '".$field["type"]."' and no validation has been supplied.", E_USER_NOTICE);
					$valid = 1;
				}
		}
		if (!empty($field['validationmsg'])) $msg = $field['validationmsg'];
		return ($valid) ? 0 : $msg;
	}

	public static function normalise($value, $field) {

		$ischk = (isset($field["type"]) and ($field["type"] == "boolean" or $field["type"] == "check"));
		$isdate = (isset($field["type"]) and ($field["type"] == "date" or $field["type"] == "datetime" or $field["type"] == "datetimeseconds"));
		$isfile = (isset($field["type"]) and ($field["type"] == "upload" or $field["type"] == "imageupload"));
		$isnumeric = (isset($field["type"]) and ($field["type"] == "integer" or $field["type"] == "float"));
		if ($value !== null) {
			if ($ischk and !isset($field["options"])) {
				$retvalue = ((isset($value[0]) and $value[0]) or (is_scalar($value) and $value)) ? 1 : 0;
			} elseif ($isdate) {
				$date = CommonV2::convertHumanTime($value);
				$retvalue = (is_numeric($date)) ? $date : null;
			} elseif ($isfile) {
				$destdir = (!empty($field["uploaddest"])) ? $field["uploaddest"] : "/lib/tmp/uploadedfiles";
				$retvalue = $_SERVER["DOCUMENT_ROOT"].$destdir."/".$value;
				if (!is_file($retvalue)) $retvalue = $value;
			} elseif (isset($field["type"]) and $field["type"] == "percent") {
				$pc = trim($value, " %");
				$retvalue = (is_numeric($pc)) ? ($pc / 100) : $value;
			} elseif ($isnumeric and is_numeric($value)) {
				$retvalue = (float)$value;
			} else {
				$retvalue = $value;
			}
		} elseif ($ischk and !isset($field["options"])) {
			$retvalue = 0;
		} elseif ($ischk) {
			$retvalue = array();
		} else {
			$retvalue = null;
		}
		return $retvalue;
	}
}
