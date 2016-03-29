<?php
/**
 * Library of validation functions
 *
 * @codingstandard ftlabs-phpcs
 * @copyright The Financial Times Limited [All Rights Reserved]
 */

namespace FTLabs\Validator;

use FTLabs\Common\CommonV3;

class ValidatorV2 {

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
		return (bool)preg_match("!^$addr_spec\z!", $str);
	}

	public static function isDate($str) {
		return (bool)CommonV3::convertHumanTime($str);
	}

	public static function isURL($str) {
		$scheme		= "(http:\/\/|https:\/\/)";
		$www		= "www\.";
		$ip			= "\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}";
		$name		= "[a-z0-9][\_\-a-z0-9]*\.";
		$tld		= "[a-z]{2,}(\.[a-z]{2,2})?";
		$the_rest	= '(\/?[a-z0-9\._\/\,\:\|\^\@\!~#&=;%+?$-_\\+\\*\'\\(\\)]+[a-z0-9\/#=?\\$\\-\\_\\.\\+\\!\\*\'\\(\\)\\,])?';
		$pattern	= "/^(".$scheme."?(?(2)(".$ip."|(".$name.")+".$tld.")|(".$www."(".$name.")+".$tld.")))(".$the_rest.")\z/is";
		if (!preg_match("/^".$scheme."/is", $str)) $str = "http://".$str;
		return (bool)preg_match($pattern, $str, $m);
	}
	public static function isURI($str) {
		return self::isURL($str);
	}

	public static function isPostCode($str) {
		return (bool)preg_match("/^(((([A-PR-UWYZ][0-9][0-9A-HJKS-UW]?)|" . "([A-PR-UWYZ][A-HK-Y][0-9][0-9ABEHMNPRV-Y]?))\s{0,2}[0-9]" . "([ABD-HJLNP-UW-Z]{2}))|(GIR\s{0,2}0AA))\z/i", $str);
	}

	public static function isUKPhone($str) {
		return (bool)preg_match("/^[0-9 \+\(\)]{5,20}\z/i", $str);
	}

	public static function isPhone($str) {
		return (bool)preg_match("/^[0-9 \+\(\)]{5,20}\z/i", $str);
	}

	public static function isAlpha($str) {
		return ctype_alpha($str);
	}

	public static function isAlphanum($str) {
		return ctype_alnum($str);
	}

	public static function isInteger($str) {

		// Function is_int() always returns false for strings
		// Function ctype_digit() returns false for ints, so cast to string
		return ctype_digit((string)$str);
	}

	public static function isNumeric($str) {
		return is_numeric($str);
	}

	public static function isJson($str) {
		return ($str and (null === json_decode($str))) ? false : true;
	}

	public static function getJsonError() {

		$errors = array(
			JSON_ERROR_NONE           => 'No error has occurred',
			JSON_ERROR_DEPTH          => 'The maximum stack depth has been exceeded',
			JSON_ERROR_STATE_MISMATCH => 'Invalid or malformed JSON',
			JSON_ERROR_CTRL_CHAR      => 'Control character error, possibly incorrectly encoded',
			JSON_ERROR_SYNTAX         => 'Syntax error',
			JSON_ERROR_UTF8           => 'Malformed UTF-8 characters, possibly incorrectly encoded'
		);

		$code = json_last_error();
		return isset($errors[$code]) ? $errors[$code] : null;
	}

	public static function isXML($str) {
		libxml_use_internal_errors(true);
		libxml_clear_errors();
		$options = (strpos($str, '<!DOCTYPE') !== false) ? (LIBXML_DTDLOAD + LIBXML_DTDVALID) : 0;
		simplexml_load_string($str, 'SimpleXMLElement', $options);
		$errors = libxml_get_errors();
		return (empty($errors) or $errors[0]->level == LIBXML_ERR_WARNING);
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
			if (is_array($valid) and count($valid) >= 2) {
				$msg = $valid[1];
				$valid = $valid[0];
			}
		} elseif ($field["type"] == "select" or $field["type"] == "enum") {
			if (!isset($field["options"]) or !is_array($field["options"])) return "Options not set";
			$valid = in_array($value, array_keys($field["options"]));
			$msg = "Selection must be one of the available options";
		} elseif ($field["type"] == "checklist") {
			if (!isset($field["options"]) or !is_array($field["options"])) return "Options not set";
			if (!is_array($value)) return "Submitted data is not an array";
			$valid = true;
			foreach ($value as $subval) {
				if (!in_array($subval, array_keys($field["options"]))) {
					$valid = false;
					break;
				}
			}
			$msg = "Selections must be from the available options";
		} elseif ($field["type"] == "upload" or $field["type"] == "imageupload") {
			$destdir = (!empty($field["uploaddest"])) ? $field["uploaddest"] : "/lib/tmp/uploadedfiles";
			$filepath = $_SERVER["DOCUMENT_ROOT"].$destdir."/".$value;
			$valid = is_file($filepath);
			if (!$valid and !empty($field["existingfile"]) and $field["existingfile"] == $value) $valid = true;
			$msg = "No file received";
		} elseif ($field["type"] != "hidden" and !empty($field["validation"])) {
			if ($field["validation"] == "postcode") {
				$valid = self::isPostCode($value);
				$msg = "Not a valid UK Postcode";
			} elseif ($field["validation"] == "email") {
				$valid = self::isEmail($value);
				$msg = "Not a valid email address";
			} elseif ($field["validation"] == "url") {
				$valid = self::isURL($value);
				$msg = "Not a valid web address (URL)";
			} elseif ($field["validation"] == "phone") {
				$valid = self::isPhone($value);
				$msg = "Not a valid telephone number";
			} elseif ($field["validation"] == "json") {
				$valid = self::isJson($value);
				$msg = "Not a valid JSON string";
			} else {
				$valid = (bool)preg_match($field["validation"], $value);
				$msg = "Not the correct format";
			}
		} else {
			switch ($field["type"]) {
				case "float":
					$value = str_replace(array(" ", ","), "", $value);
					$valid = is_numeric($value);
					$msg = "Not a valid number";
					break;
				case "integer":
					$value = str_replace(array(" ", ","), "", $value);
					$valid = (is_numeric($value) and (strpos($value, '.') === false));
					$msg = "Not a whole number";
					break;
				case "date":
				case "datetime":
				case "datetimeseconds":
					$valid = (bool)CommonV3::convertHumanTime($value);
					$msg = "Not a valid date";
					break;
				case "time":
				case "timeseconds":

					// Time is either number of seconds since midnight or a valid strtotime time.
					$valid = (is_numeric($value) or strtotime($value));
					$msg = "Not a valid time";
					break;
				case "text":
				case "textarea":
				case "richtext":
				case "autocomplete":
				case "check":
				case "checklist":
				case "boolean":
				case "dummy":
				case "password":
				case "hidden":
					$valid = is_scalar($value);
					$msg = "Value must be scalar";
					break;
				case "spacer":
					$valid = is_null($value);
					$msg = "Value must be null";
					break;
				default:
					trigger_error("Unable to validate field - this is an unknown field type '".$field["type"]."' and no validation has been supplied.", E_USER_NOTICE);
					$valid = true;
				}
		}
		if (!empty($field['validationmsg'])) $msg = $field['validationmsg'];
		return ($valid) ? false : $msg;
	}

	public static function normalise($value, $field) {

		if (is_null($value)) return null;
		if (!isset($field["type"])) $field["type"] = null;

		switch ($field['type']) {
			case "boolean":
			case "check":
				return (bool)$value;
			case "checklist":
				$retvalue = array();
				if (isset($field["options"]) and is_array($value)) foreach ($value as $subval) {
					if (in_array($subval, array_keys($field["options"]))) $retvalue[] = $subval;
				}
				return $retvalue;
			case "date":
			case "datetime":
			case "datetimeseconds":
				if ($date = CommonV3::convertHumanTime($value)) return $date;
				return null;
			case "timeseconds":

				// Timeseconds is the same as time except also has seconds.
				$secs = ":s";
			case "time":
				if (!isset($secs)) $secs = "";

				// If value is a number, then assume number of seconds since midnight.
				if (is_numeric($value)) return date("H:i".$secs, strtotime("midnight") + $value);

				// Otherwise use strtotime to work out wether value is a valid time.
				if ($timestamp = strtotime($value)) return date("H:i".$secs, $timestamp);
				return null;
			case "upload":
			case "imageupload":
				$destdir = (!empty($field["uploaddest"])) ? $field["uploaddest"] : "/lib/tmp/uploadedfiles";
				$retvalue = $_SERVER["DOCUMENT_ROOT"].$destdir."/".$value;
				if (!is_file($retvalue)) return $value;
				return $retvalue;
			case "integer":
				if (!is_scalar($value)) return 0;
				return (int)$value;
			case "float":
				if (!is_scalar($value)) return (float)0;
				return (float)$value;
			case "select":
			case "enum":
				if (isset($field["options"]) and in_array($value, array_keys($field["options"]))) return $value;
				return null;
			case "text":
			case "textarea":
			case "richtext":
			case "autocomplete":
			case "hidden":
			case "dummy":
			case "password":
				if (is_scalar($value)) return (string)$value;
				if (is_object($value) and method_exists($value, '__toString')) return (string)$value;
				return null;
			case "spacer":
				return null;
			default:
				trigger_error("Unable to normalise field - this is an unknown field type '".$field["type"]."'.", E_USER_NOTICE);
				return $value;
		}

	}
}
