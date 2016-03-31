<?php
/**
 * Formats timestamp and key-value pairs in a splunk-compatible way.
 *
 * @codingstandard ftlabs-phpcs
 * @copyright The Financial Times Limited [All rights reserved]
 */

namespace FTLabs;

class SplunkLogFormatter extends KeyValueLogFormatter {
	public function formattedLogMessage($level, $msg, $vars, $timestamp = null) {

		// Splunk displays events in correct chronological order only if the timestamps are in the same format.
		if ($timestamp) {
			$sec = floor($timestamp);
			$msec = $timestamp - $sec;
		} else {
			list($msec, $sec) = explode(' ', microtime(false));
		}

		return
			gmdate("Y-m-d\TH:i:s", $sec).substr(sprintf("%0.3f",$msec),1).'Z '.
			parent::formattedLogMessage(
				$level,
				$msg,
				$this->flattenVars($vars)
			);
	}

	/**
	 * If $vars is an array, this method transforms it to a single dimensional array,
	 * so that it can be better logged to Splunk:
	 *  - All elements that are objects will be transformed to string using either their
	 *    __toString() method or a class name.
	 *  - Context of a FTLabs\Exception object stored in $vars['exception'] array will be
	 *    merged into the resulting array.
	 *  - All elements that are arrays will be merged into $vars array with their keys
	 *    prefixed with the element's key. If the element is a multidimensional array,
	 *    only first dimension will be merged. Original
	 *    array will not be set in the resulting flattened array.
	 *  - If $vars['error']['context'] is an array, it will be merged into the resulting array.
	 *  - Elements that are not an object nor an array, will be left untransformed.
	 * If $vars is not an array, it will be returned untransformed.
	 *
	 * Example:
	 * array (
	 *    'string' => 'string',
	 *    'int'    => 1,
	 *    'object' => new ObjectClass(),
	 *    'array'  => array(
	 *        'string' => 'array_string',
	 *        'int'    => 2,
	 *        'array'  => array(
	 *            'string' => 'array_string',
	 *            'int'    => 3,
	 *        ),
	 *    ),
	 * );
	 *
	 * will be transformed into:
	 *
	 * array (
	 *    'string'       => 'string',
	 *    'int'          => 1,
	 *    'object'       => 'ObjectClass',
	 *    'array_string' => 'array_string',
	 *    'array_int'    => 2,
	 *    'array_array'  => '{string:"array_string",int:3}',
	 * );
	 *
	 * @param mixed $vars
	 * @return mixed
	 */
	protected function flattenVars($vars) {
		if (!is_array($vars)) return $vars;

		if (isset($vars['exception']) && $vars['exception'] instanceof \FTLabs\Exception) {
			$ctx = $vars['exception']->getContext();
			if (is_array($ctx)) $vars = array_merge($vars, $ctx);
		} elseif (isset($vars['error']) && is_array($vars['error']) && isset($vars['error']['context']) && is_array($vars['error']['context'])) {
			$vars = array_merge($vars, $vars['error']['context']);
		}

		$flatVars = array();
		foreach ($vars as $key => $val) {
			if (is_array($val)) {
				$flatVars = array_merge($flatVars, $this->flattenArray($val, $key));
				continue;
			} elseif (is_object($val)) {
				$val = $this->flattenObject($val);
			}

			$flatVars[$key] = $val;
		}

		return $flatVars;
	}

	/**
	 * Method prefixes $array keys with $prefix and json encode any elements that are also an array.
	 *
	 * @param array $array Original array to be flattened
	 * @param string $prefix String to prefix all keys with
	 * @return array
	 */
	protected function flattenArray(array $array, $prefix) {
		$ret = array();

		$lengthUsed = 0;
		foreach ($array as $key => $val) {
			$ret[$prefix . '_' . $key] = $this->encode($val, $lengthUsed, 1);
		}

		return $ret;
	}

	/**
	 * Convert arbitrary structure to a string
	 */
	private function encode($obj, &$lengthUsed, $depth) {
		$lengthUsed++;

		if (is_scalar($obj) || null === $obj) {
			$out = substr(@json_encode($obj), 0, 20000);
			$lengthUsed += strlen($out)/100;
			return $out;
		}
		if (is_array($obj) && isset($obj[0])) {
			if ($lengthUsed > 500/$depth) {
				return "[…".count($obj)."]";
			}
			$out = "[";
			foreach($obj as $k => $v) {
				$out .= $this->encode($v, $lengthUsed, $depth+1) . ",";
			}
			return rtrim($out,",") . "]";
		}
		if (is_object($obj) || is_array($obj)) {
			if ($lengthUsed > 500/$depth) {
				return '{…}';
			}
			$out = "{";
			foreach($obj as $k => $v) {
				$out .= @json_encode($k) . ":" . $this->encode($v, $lengthUsed, $depth+1) . ",";
			}
			return rtrim($out,",") . "}";
		}
		return "{". gettype($obj) . "?}";
	}

	/**
	 * Method returns $object->__toString() if it exists or class name otherwise.
	 *
	 * @param object $object
	 * @return string
	 */
	protected function flattenObject($object) {
		return method_exists($object, '__toString') ? $object->__toString() : get_class($object);
	}
}
