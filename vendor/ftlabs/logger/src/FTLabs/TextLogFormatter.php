<?php
/**
 * Renders error report as plain text
 *
 * @codingstandard ftlabs-phpcs
 * @copyright The Financial Times Limited [All rights reserved]
 */

namespace FTLabs;

class TextLogFormatter implements LogFormatterInterface {
	protected $devmode;

	/**
	 * Template is read from ./LogFormatter/ directory
	 *
	 * @param string $template filename
	 */
	function __construct($template) {
		$this->template = $template;
	}

	function formattedLogMessage($level, $msg, $context, $timestamp = null) {
		return "[$level] $msg";
	}

	protected function getTemplateVariables(ErrorLog $errorLog) {
		$op = array();
		$op["errstr"] = $errorLog->getTitle();
		$op["errline"] = $errorLog->getLine();
		$op["errfile"] = $errorLog->getFile();
		$op['host'] = $errorLog->getHostname();
		$op["line"] = "";
		if ($errorLog->getCodeContext()) {
			foreach ($errorLog->getCodeContext() as $linenum  => $line) {
				if (is_numeric($linenum)) $op['line'] .= $linenum.": ".$line."\n";
			}
		} else {
			$op['line'] = "No code context available";
		}
		$op["url"] = $errorLog->getReferrer() ? $errorLog->getReferrer() : "javascript:history.go(-1)";
		$op["errorcode"] = $errorLog->getAggregationHash();

		return $op;
	}

	protected function renderedTemplate($_template_name, array $arguments) {
		extract($arguments, EXTR_SKIP);
		unset($arguments);
		ob_start();
		include __DIR__."/LogFormatter/".$_template_name.".inc";
		return ob_get_clean();
	}

	function formattedErrorLog(ErrorLog $error) {
		$vars = $this->getTemplateVariables($error);

		$vars["textdebug"] = $this->generatePlainBacktrace($error->getBacktrace());

		$errlog = $error->getAsSerializableErrorTree();
		$tree = self::generateTree($errlog);

		// Avoid flooding terminal with large context, when run locally (assuming php-cli) let user read it from a file
		if (PHP_SAPI == 'cli') {
			$contextFile = tempnam(sys_get_temp_dir(), 'ErrorContext_');
			file_put_contents($contextFile, $tree);
			$vars['textdebug'] .= "\nContext saved to a file:\nless $contextFile\n\n";
		} else {
			$vars["textdebug"] .= $tree."\n";
		}

		return array('text/plain;charset=UTF-8', $this->renderedTemplate($this->template, $vars));
	}

	/**
	 * Generate a plain text backtrace from the output of debug_backtrace();
	 *
	 * Produces a plain text rendering of the output of the PHP debug_backtrace function. The format produced is similar to debug_print_backtrace, but with the advantage that the output is returned rather than printed to the output buffer.
	 *
	 * @param array $data Output from debug_backtrace()
	 * @return string Plain text backtrace
	 */
	private function generatePlainBackTrace($data) {
		if (!$data) return false;
		$op = "";
		foreach ($data as $idx => $call) {
			$op .= '#'.$idx.'  ';
			$op .= (!empty($call['class'])) ? $call['class'].$call['type'] : '';
			$op .= !empty($call['function']) ? $call['function'] : '';
			if (!empty($call['file'])) $op .= ' called at '.$call['file'].
						(!empty($call['line']) ? ':'.$call['line'] : '').
					"\n";
		}
		return $op;
	}


	/**
	 * Generate an HTML representation of an associative array
	 *
	 * Produces a nested HTML unordered list containing all the name-value pairs from an array.  This is used to serialise the error handler's memory dump prior to logging to disk, display on screen or posting to the support system.  This method calls itself recursively.
	 *
	 * @param array $dumparr         Data to serialise
	 * @param int   $level           Level of recursion, set automatically on recursive calls - should not be set be the caller
	 * @param int   &$itemsgenerated limits size of output
	 * @return string HTML unordered list
	 */
	private static function generateTree($dumparr, $level=0, &$itemsgenerated=1) {
		static $objstore, $objidmap;
		if ($level == 0) {
			$objstore = array();
			$objidmap = array();
		}

		if ($itemsgenerated++ * $level > 1000) return "\n…Too many items…\n";

		if (!$objstore and isset($dumparr['objstore'])) {
			$objstore = $dumparr['objstore'];
			unset($dumparr['objstore']);
		}
		if (!$objidmap) $objidmap = array();
		if (is_array($dumparr)) {
			$op = "";
			foreach ($dumparr as $key => $data) {
				$key = str_replace(array("\r","\n"), array("\\r","\\n"), $key);
				$value = $data;
				$subtree = null;
				$type = 'unknown';
				$id = null;
				$isref = false;
				$keyclasses = array('key');
				$liclasses = array();
				$interp = '';
				if (in_array($key, array("_errorhandler_objid", "_errorhandler_classname", "_errorhandler_indexed", "_errorhandler_type"))) continue;
				if (is_object($data)) {
					$value = get_class($data);
				}
				elseif (is_array($data)) {
					$type = 'array';
					if (isset($data['_errorhandler_objid'])) {
						$id = $value['_errorhandler_objid'];
						if (isset($data['_errorhandler_type'])) $type = $data['_errorhandler_type'];
						if (($type == 'object' and isset($objstore[$id]['_errorhandler_indexed'])) OR ($type == 'array' and isset($data['_errorhandler_arrref']))) {
							$isref = true;
							$value = null;
							$keyclasses[] = 'objreference';
						} elseif ($type == 'object') {
							if (isset($objstore[$data['_errorhandler_objid']])) {
								$tmp =& $objstore[$data['_errorhandler_objid']];
							} else {
								$tmp =& $data['_errorhandler_objid'];
							}
							if (is_array($tmp)) {
								$tmp['_errorhandler_indexed'] = $id;
								$subtree = $tmp;
								$value = isset($subtree['_errorhandler_classname']) ? "instance of ".htmlentities($subtree['_errorhandler_classname']) : "";
							} else {
								$value = '** invalid input **';
								$subtree = '';
							}
						} else {
							$subtree = $data;
							$value = "";
						}
					} elseif (isset($value['_errorhandler_type'])) {
						$type = $value['_errorhandler_type'];
						$value = isset($value['_errorhandler_value']) ? $value['_errorhandler_value'] : '';
					} else {
						$value = '';
						$subtree = $data;
					}

				} elseif (is_bool($value)) {
					$type = 'bool';
					$value = (($value)?'True':'False');
				} elseif (is_int($value)) {
					$type = 'int';
					if ($value > 631152000 and $value < 2145916800) {
						$interp = "Unix timestamp? ".date('r', $data);
					}

				} elseif (is_float($value)) {
					$type = 'float';
				} elseif (is_string($value)) {
					$type = 'string';
				} elseif ($value === null) {
					$type = 'null';
				} else {
					$value = (string)$value;
				}

				if ($key == 'errline' || $key == 'calledfrom' || $key == 'Backtrace') {
					$liclasses[] = 'open';
				}

				if ($id) {
					if (!empty($objidmap[$id])) {
						$domid = $objidmap[$id];
						$keyclasses[] = $domid;
					} else {
						$domid = $objidmap[$id] = 'obj_'.count($objidmap);
					}
				}
				if ($level > 20) $subtree = null;

				$value = (string)$value;
				if (function_exists('mb_check_encoding') && !mb_check_encoding($value, "UTF-8")) {
					$value = mb_convert_encoding($value, "UTF-8", "Windows-1252");
				}

				$op .= "\n".str_repeat(" ", $level) . "$key = $type " . ($isref ? "#".$domid : "'$value'") . ($interp ?: "") .
					($subtree ? self::generateTree($subtree, ($level + 1), $itemsgenerated) : "");
			}
		} else {
			$op = "";
		}
		return $op;
	}
}
