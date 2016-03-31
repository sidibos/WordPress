<?php
/**
 * Renders error report as HTML
 *
 * @codingstandard ftlabs-phpcs
 * @copyright The Financial Times Limited [All rights reserved]
 */

namespace FTLabs;

use FTLabs\ErrorLog;

// Extends TextLogFormatter only to reuse couple of methods
class HtmlLogFormatter extends TextLogFormatter implements LogFormatterInterface {

	function formattedErrorLog(ErrorLog $error) {
		$vars = $this->getTemplateVariables($error);

		$errlog = $error->getAsSerializableErrorTree();
		unset($errlog["Error details"]["codecontext"]);
		unset($errlog["Error details"]["file"]);
		unset($errlog["Error details"]["errline"]);

		if (isset($errlog["Backtrace"])) {
			$newBacktrace = array();
			foreach($errlog["Backtrace"] as $key => $bt) {
				$name = isset($bt['class']) ? $bt['class'] : '';
				$name .= isset($bt['type']) ? $bt['type'] : '';
				$name .= isset($bt['function']) ? $bt['function']."()" : '';
				$name .= isset($bt['file']) ? " in ".$bt['file'] : '';
				$name .= isset($bt['line']) ? ":".$bt['line'] : '';
				if (!$name || isset($newBacktrace[$name])) $name = "#$key $name";
				$newBacktrace[$name] = $bt;
			}
			$errlog["Backtrace"] = $newBacktrace;
		}


		$vars["debug"] = self::generateTree($errlog);


		$txt = new TextLogFormatter(str_replace('_html','_text', $this->template));
		list(,$vars['textversion']) = $txt->formattedErrorLog($error);

		return array('text/html;charset=UTF-8',$this->renderedTemplate($this->template, $vars));
	}

	/**
	 * Generate an HTML representation of an associative array
	 *
	 * Produces a nested HTML unordered list containing all the name-value pairs from an array.  This is used to serialise the error handler's memory dump prior to logging to disk, display on screen or posting to the support system.  This method calls itself recursively.
	 *
	 * @param array $dumparr        Data to serialise
	 * @param int   $level          Level of recursion, set automatically on recursive calls - should not be set be the caller
	 * @param int   $itemsgenerated limits size of output
	 * @return string HTML unordered list
	 */
	private static function generateTree($dumparr, $level=0, &$itemsgenerated=1) {
		static $objstore, $objidmap;
		if ($level == 0) {
			$objstore = array();
			$objidmap = array();
		}

		if ($itemsgenerated++ * $level > 5000) return '<ul><li>…Too many items…</li></ul>';

		if (!$objstore and isset($dumparr['objstore'])) {
			$objstore = $dumparr['objstore'];
			unset($dumparr['objstore']);
		}
		if (!$objidmap) $objidmap = array();
		if (is_array($dumparr)) {
			$op = "";
			foreach ($dumparr as $key => $data) {
				$key = str_replace(array("\r","\n"), array("\\r","\\n"), htmlentities($key, ENT_COMPAT, 'UTF-8'));
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
				if (function_exists('mb_check_encoding') && !mb_check_encoding($value, 'UTF-8')) {
					$value = mb_convert_encoding($value, "UTF-8", "Windows-1252");
				}
				$value = rtrim(strtr(htmlspecialchars($value, ENT_COMPAT, 'UTF-8'), array(
					"\r"=>'<i>\\r</i>',
					"\n"=>"<i>\\n</i>\n",
					"\t"=>"<i>\\t  </i>",
				)), "\n");

				$op .= "<li class='" . join(' ', $liclasses) . "'>".
					"<span" . (($id and !$isref) ? " id='".$domid."'" : "") . " class='".join(' ', $keyclasses)."'>".$key.
						" <span class='type'>".$type."</span>" .
						" <span class='val'>" .
							($isref ? "<a href='#".$domid."' class='objreflink'>see other reference</a>" : $value) .
						"</span>" .
						($interp?"<span class='interp'>".$interp."</span>":"") .
					"</span>" .
					($subtree ? self::generateTree($subtree, ($level + 1), $itemsgenerated) : "") .
					"</li>\n";
			}
			if ($op) $op = "<ul" .  ($level == 0 ? ' class="tree open"' : '' ) . ">\n" . $op . "</ul>\n";
		} else {
			$op = "";
		}
		return $op;
	}
}
