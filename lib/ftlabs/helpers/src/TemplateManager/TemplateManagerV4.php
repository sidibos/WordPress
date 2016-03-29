<?php
/**
 * Assanka template manager
 *
 * Produces output by combining template files with data
 * from a controller script.
 *
 * TODO:SH:20140724: Add codingstandards tag
 * @copyright The Financial Times Limited [All Rights Reserved]
 */

namespace FTLabs\TemplateManager;

use FTLabs\Common\CommonV2;

class TemplateManagerV4 {

	protected $_assignedvars;
	private $_templatestring, $_templatedirs, $_templatecache, $_starttime, $_ascendtree, $_ascendfirst;

	function __construct($temdirs = false, $ascendtree = false, $ascendfirst = true) {
		$this->_templatedirs = array();
		if ($temdirs) $this->setTemplateDirs($temdirs);
		$this->_assignedvars = array();
		$this->_starttime = microtime(true);
		$this->_ascendtree = $ascendtree;
		$this->_ascendfirst = $ascendfirst;
	}

	function setTemplateDirs($temdirs) {

		// Clear any template directories already assigned
		$this->_templatedirs = array();

		// Ensure the input was an array
		if (!is_array($temdirs)) {
			$temdirs = array($temdirs);
		}

		// Check each supplied template directory exists,
		// and add it to the template if so.
		foreach ($temdirs as $temdir) {
			$temdir = rtrim($temdir, '/');
			if (!is_dir($temdir)) {
				throw new TemplateManagerException('Template directory not found.  Template manager supports only absolute paths and template strings.');
			} else {
				$this->_templatedirs[] = $temdir;
			}
		}
	}

	function getTemplateDirs() {
		return $this->_templatedirs;
	}

	function set($a=false, $b=null) {
		if (is_string($a) and is_scalar($b)) {
			$this->_assignedvars[$a] = array($b);
		} elseif (is_string($a) and is_array($b) and !empty($b)) {
			$mode = "";
			foreach ($b as $key=>$val) {
				if (is_integer($key) and is_scalar($val) and (!$mode or $mode=="num")) $mode = "num";
				elseif (!is_integer($key) and is_scalar($val) and (!$mode or $mode="assoc")) $mode = "assoc";
				elseif (!is_scalar($val)); // Do nothing
				else trigger_error("Invalid template data assignment", E_USER_ERROR);
			}
			$this->_assignedvars[$a] = ($mode=="num") ? $b : array($b);
		} elseif (is_string($a) and !$b) {
			unset($this->_assignedvars[$a]);
		} elseif (is_array($a) and !$b) {
			foreach ($a as $key=>$val) $this->set($key, $val);
		} elseif (is_string($a) and is_object($b)) {
			$this->_assignedvars[$a][] = $b;
		} else {
			return false;
		}
	}

	function add($a=false, $b=false) {
		if (is_string($a) and is_scalar($b)) {
			if (!isset($this->_assignedvars[$a])) $this->_assignedvars[$a] = array();
			if (!is_array($this->_assignedvars[$a])) $this->_assignedvars[$a] = array($this->_assignedvars[$a]);
			$this->_assignedvars[$a][] = $b;
		} elseif (is_string($a) and is_array($b) and !empty($b)) {
			$mode = "";
			foreach ($b as $key=>$val) {
				if (is_integer($key) and is_scalar($val) and (!$mode or $mode=="num")) $mode = "num";
				elseif (!is_integer($key) and is_scalar($val) and (!$mode or $mode="assoc")) $mode = "assoc";
				elseif (!is_scalar($val)); // Do nothing
				else trigger_error("Invalid template data assignment", E_USER_ERROR);
			}
			if ($mode=="num") {
				if (empty($this->_assignedvars[$a])) $this->_assignedvars[$a] = array();
				$this->_assignedvars[$a] = array_merge($this->_assignedvars[$a], $b);
			} else {
				$this->_assignedvars[$a][] = $b;
			}
		} elseif (is_array($a) and !$b) {
			foreach ($a as $key=>$val) $this->add($key, $val);
		} elseif (is_string($a) and is_object($b)) {
			$this->_assignedvars[$a][] = $b;
		} else {
			return false;
		}
	}

	function clearAssigned() {
		$keys = func_get_args();
		foreach ($keys as $key) {
			if (isset($this->_assignedvars[$key])) {
				unset($this->_assignedvars[$key]);
			}
		}
	}

	function clearAllAssigned() {
		$this->_assignedvars = array();
	}

	function renderFromString($str) {
		$this->_templatestring = $str;
		return $this->render();
	}

	function getTemplatePath($templatename) {

		$template = $templatename.'.html';
		$tempath = false;

		// First check for absolute paths
		if (substr($template, 0, 1) == '/') {
			if (file_exists($template)) $tempath = $template;
		}

		// Then check for paths relative to the current directory
		if (!$tempath) {
			$tempath = dirname($_SERVER['SCRIPT_FILENAME']).'/'.$template;
			if (!file_exists($tempath)) $tempath = false;
		}

		// If the template file still hasn't been found, check the template directories, ascending
		// the directory tree of each if necessary.
		if (!$tempath and $this->_templatedirs) {
			$checkedpaths = array();

			// Split apart template directory, if any, and the base filename.
			$supplieddir = dirname($template);
			if ($supplieddir == '/' or $supplieddir == '.') $supplieddir = '';
			$templatefilename = basename($template);

			// Build a list of directories to check, based on the template directories
			$dirstack = array();
			foreach ($this->_templatedirs as $dir) {
				$dirstack[] = $dir.(($supplieddir)?'/'.$supplieddir:'');
			}

			// Scan through the directory list, checking for the template file
			while ($temdir = array_shift($dirstack)) {
				$checkedpaths[$temdir] = true;

				$tempathtocheck = $temdir.'/'.$templatefilename;
				if (file_exists($tempathtocheck)) {
					$tempath = $tempathtocheck;
					break;
				}

				// If ascending the directory tree is on, add a new path to the directory list
				if ($this->_ascendtree) {
					$parentdir = dirname($temdir);
					if (empty($checkedpaths[$parentdir])) {

						// If ascendfirst is set to true, prepend the path; otherwise append it
						if ($this->_ascendfirst) array_unshift($dirstack, $parentdir);
						else $dirstack[] = $parentdir;
					}
				}
			}
		}
		return $tempath;
	}

	function templateExists($templatename) {
		$tempath = $this->getTemplatePath($templatename);
		return !empty($tempath);
	}

	/**
	 * Render from a supplied template location, or from a pre-set string.
	 *
	 * If a template location was supplied, it is used to check in the following locations:
	 * a) Using the supplied template location as a cwd or absolute path.
	 * b) Using the supplied template location appended to the first template path.
	 * c) Any additional template paths, in order
	 * d) If ascendtree is true, the parent paths of any template locations
	 * e) If ascendtree is true, the grandparent paths of any template locations, etc
	 * If ascendfirst is true, then (d) and (e) are checked before (c).
	 */
	function render($templatename = false) {

		// If a template location is supplied, use it to select the template string by finding an
		// associated template file on disk - relatively, absolute, or via template folders.
		if ($templatename) {
			$template = $templatename.'.html';

			// Use a cache if available
			if (isset($this->_templatecache[$template])) {
				$temstr = $this->_templatecache[$template];

			// Otherwise, work out the location of the template file on disk.
			} else {
				$tempath = $this->getTemplatePath($templatename);

				// Error if the template couldn't be found in any location
				if (!$tempath) trigger_error("Template '".$templatename."' not found in any valid location", E_USER_ERROR);

				// Get the file contents and also keep in the cache for future reads
				$temstr = @file_get_contents($tempath) or trigger_error("Failure opening template file '".$tempath."'", E_USER_ERROR);
				$this->_templatecache[$template] = $temstr;
			}
		} elseif ($this->_templatestring) {
			$temstr = $this->_templatestring;
			unset($this->_templatestring);
		} else {
			trigger_error("No template specified", E_USER_ERROR);
		}

		// Read and process template commands
		$cb = 0;
		$z=0;
		$blocks = array(0=>array("str"=>$temstr, "pos"=>0, "op"=>"", "itrcount"=>0, "rules"=>array()));
		$data = $this->_assignedvars;
		while (true) {
			++$z;

			$pos = strpos($blocks[$cb]["str"], "<!--@", $blocks[$cb]["pos"]);

			if ($pos !== false) {

				// Extract the command contents
				$end = strpos($blocks[$cb]["str"], "-->", $pos);
				$cmd = trim(substr($blocks[$cb]["str"], $pos+5, ($end - $pos - 5)));
			}

			if ($pos === false or $end === false) {
				$blocks[$cb]["op"] .= substr($blocks[$cb]["str"], $blocks[$cb]["pos"]);
				$blocks[$cb]["itrcount"]++;
				if (isset($blocks[$cb]["rules"]["repeatfor"]) and count($this->_assignedvars[$blocks[$cb]["rules"]["repeatfor"][0]]) > $blocks[$cb]["itrcount"]) {
					$blocks[$cb]["pos"] = 0;
				} else {
					if ($cb===0) break;
					$op = $blocks[$cb]["op"];
					unset($blocks[$cb]);
					end($blocks);
					$cb = key($blocks);
					$blocks[$cb]["op"] .= $op;
				}
				$data = $this->_assignedvars;
				foreach ($blocks as $block) {
					if (isset($block["rules"]["repeatfor"]) and isset($data[$block["rules"]["repeatfor"][0]][$block["itrcount"]])) {
						$x = $this->_assignedvars[$block["rules"]["repeatfor"][0]][$block["itrcount"]];
						if (is_scalar($x)) $x = array("value"=>$x, $block["rules"]["repeatfor"][0]=>$x);
						$data = array_merge($data, $x);
						break;
					}
				}
				continue;
			}
			if (is_array($data)) foreach ($data as $key=>$val) if (is_scalar($val)) $data[$key] = array($val);


			// Extract any modifiers
			$modifiers = array();
			if ($pipepos = strpos($cmd, "|")) {
				$modstr = substr($cmd, $pipepos+1, strlen($cmd)-$pipepos-1);
				$cmd = substr($cmd, 0, $pipepos);
				$inquote = 0;
				$currentmod = "";
				$currentargs = array();
				$buffer = "";
				$len = strlen($modstr);
				for ($i = 0; $i < $len; ++$i) {
					$c = $modstr[$i];
					if (!$inquote and $c == ",") {
						if (!$currentmod) {
							$modifiers[$buffer] = array();
						} else {
							$buffer = str_replace(array("\\n", "\\t", "\\r"), array("\n", "\t", "\r"), $buffer);
							$currentargs[] = $buffer;
							$modifiers[$currentmod] = $currentargs;
						}
						$currentmod = $buffer = "";
						$currentargs = array();
					} elseif (!$inquote and $c == ":") {
						if (!$currentmod) {
							$currentmod = $buffer;
						} else {
							$currentargs[] = $buffer;
						}
						$buffer = "";
					} elseif ($inquote and ($c=="\"" or $c=="\\") and $i+1 < $len and $modstr[$i+1] == "\"") {
						$buffer .= "\"";
						++$i;
					} elseif ($c == "\"") {
						$inquote = !$inquote;
					} else {
						$buffer .= $c;
					}
				}
				if (!$currentmod) {
					$modifiers[$buffer] = array();
				} else {
					$buffer = str_replace(array("\\n", "\\t", "\\r"), array("\n", "\t", "\r"), $buffer);
					$currentargs[] = $buffer;
					$modifiers[$currentmod] = $currentargs;
				}
			}

			// Check for a special command prefix
			if ($colonpos = strpos($cmd, ":")) {
				list($prefix,$key) = explode(":", $cmd);
			} else {
				$prefix = false;
				$key = $cmd;
			}


			/* Generate a value for the command output */

			$op = "";

			// No prefix - use data from assignedvars
			if (!$prefix) {
				if (isset($data[$key])) $op = $data[$key];
				if (is_array($op) and is_array($op[0])) {
					trigger_error("Cannot use template collection '$key' outside a BLOCK", E_USER_ERROR);
				}

			// Function call
			} elseif ($prefix=="FN") {
				if (function_exists($key)) {
					$call_args = array();
					if (!empty($modifiers['args'])) {
						foreach (explode(';', $modifiers['args'][0]) as $arg) {

							// If this argument is a template variable, find the appropriate template variable
							if (strlen($arg) > 1 and $arg[0] == '$') {
								$varname = substr($arg, 1);

								// If this template variable was set, add it to the list of
								// arguments to send to the template function
								if (isset($data[$varname])) {
									if (is_array($data[$varname]) and count($data[$varname]) == 1) {
										$call_args[] = $data[$varname][0];
									} else {
										$call_args[] = $data[$varname];
									}

								// Otherwise, add a null value
								} else {
									$call_args[] = null;
								}

							// If not a template variable, use the literal string value of the argument
							} else {
								$call_args[] = $arg;
							}
						}
					}
					$op = call_user_func_array($key, $call_args);
				}

			// Server variable
			} elseif ($prefix=="SERVER") {
				$op = isset($_SERVER[$key]) ? $_SERVER[$key] : '';

			// Built-in values
			} elseif ($prefix=="SPECIAL") {
				switch (strtolower($key)) {
					case "now":
						$op = time();
						break;
					case "exectime":
						$x = microtime(true);
						$op = $x-$this->_starttime;
						break;
					case "islive":
						$op = (!empty($_SERVER["IS_LIVE"]))?key($modifiers):"";
						break;
				}

			// Start of a block
			} elseif ($prefix=="BLOCK") {
				if (isset($blocks[$key])) trigger_error("Cannot nest block '$key' inside itself", E_USER_ERROR);
				if (empty($key)) trigger_error("No key specified for block", E_USER_ERROR);
				if (!($endblock=strpos($blocks[$cb]["str"], "<!--@ENDBLOCK:".$key."-->", $pos))) trigger_error("BLOCK specified with no matching ENDBLOCK for block ".$key, E_USER_ERROR);
				$endblocklen = strlen("<!--@ENDBLOCK:".$key."-->");
				$blocks[$cb]["op"] .= substr($blocks[$cb]["str"], $blocks[$cb]["pos"], ($pos-$blocks[$cb]["pos"]));
				$blocks[$cb]["pos"] = $endblock+$endblocklen;

				if ((isset($modifiers["printifset"]) and isset($data[$modifiers["printifset"][0]][0])) or
				(isset($modifiers["ifset"]) and isset($data[$modifiers["ifset"][0]][0])) or
				(isset($modifiers["printifnotset"]) and !isset($data[$modifiers["printifnotset"][0]][0])) or
				(isset($modifiers["ifnotset"]) and !isset($data[$modifiers["ifnotset"][0]][0])) or
				(isset($modifiers["printiftrue"]) and !empty($data[$modifiers["printiftrue"][0]][0])) or
				(isset($modifiers["iftrue"]) and !empty($data[$modifiers["iftrue"][0]][0])) or
				(isset($modifiers["printiffalse"]) and empty($data[$modifiers["printiffalse"][0]][0])) or
				(isset($modifiers["iffalse"]) and empty($data[$modifiers["iffalse"][0]][0])) or

				(isset($modifiers["printifequalto"]) and isset($data[$modifiers["printifequalto"][0]][0]) and $data[$modifiers["printifequalto"][0]][0] == $modifiers["printifequalto"][1]) or
				(isset($modifiers["ifequalto"]) and isset($data[$modifiers["ifequalto"][0]][0]) and $data[$modifiers["ifequalto"][0]][0] == $modifiers["ifequalto"][1]) or
				(isset($modifiers["printifnotequalto"]) and (!isset($data[$modifiers["printifnotequalto"][0]][0]) or $data[$modifiers["printifnotequalto"][0]][0] != $modifiers["printifnotequalto"][1])) or
				(isset($modifiers["ifnotequalto"]) and (!isset($data[$modifiers["ifnotequalto"][0]][0]) or $data[$modifiers["ifnotequalto"][0]][0] != $modifiers["ifnotequalto"][1])) or

				(isset($modifiers["printifeven"]) and isset($data[$modifiers["printifeven"][0]][0]) and !((float)$data[$modifiers["printifeven"][0]][0] % 2)) or
				(isset($modifiers["ifeven"]) and isset($data[$modifiers["ifeven"][0]][0]) and !((float)$data[$modifiers["ifeven"][0]][0] % 2)) or
				(isset($modifiers["printifodd"]) and isset($data[$modifiers["printifodd"][0]][0]) and ((float)$data[$modifiers["printifodd"][0]][0] % 2)) or
				(isset($modifiers["ifodd"]) and isset($data[$modifiers["ifodd"][0]][0]) and ((float)$data[$modifiers["ifodd"][0]][0] % 2)) or
				(isset($modifiers["printiffirst"]) and $blocks[$cb]["itrcount"]==0) or
				(isset($modifiers["iffirst"]) and $blocks[$cb]["itrcount"]==0) or
				(isset($modifiers["printifnotfirst"]) and $blocks[$cb]["itrcount"]!=0) or
				(isset($modifiers["ifnotfirst"]) and $blocks[$cb]["itrcount"]!=0) or
				(isset($modifiers["printiflast"]) and $blocks[$cb]["itrcount"] == count($this->_assignedvars[$block["rules"]["repeatfor"][0]])-1) or
				(isset($modifiers["iflast"]) and $blocks[$cb]["itrcount"] == count($this->_assignedvars[$block["rules"]["repeatfor"][0]])-1) or
				(isset($modifiers["printifnotlast"]) and $blocks[$cb]["itrcount"] != count($this->_assignedvars[$block["rules"]["repeatfor"][0]])-1) or
				(isset($modifiers["ifnotlast"]) and $blocks[$cb]["itrcount"] != count($this->_assignedvars[$block["rules"]["repeatfor"][0]])-1) or

				(isset($modifiers["printifmore"]) and isset($data[$modifiers["printifmore"][0]][0]) and ($data[$modifiers["printifmore"][0]][0] > (float)$modifiers["printifmore"][1])) or
				(isset($modifiers["ifmore"]) and isset($data[$modifiers["ifmore"][0]][0])  and ($data[$modifiers["ifmore"][0]][0] > (float)$modifiers["ifmore"][1])) or
				(isset($modifiers["printifless"]) and isset($data[$modifiers["printifless"][0]][0]) and ($data[$modifiers["printifless"][0]][0] < (float)$modifiers["printifless"][1])) or
				(isset($modifiers["ifless"]) and isset($data[$modifiers["ifless"][0]][0])  and ($data[$modifiers["ifless"][0]][0] < (float)$modifiers["ifless"][1])) or

				((isset($modifiers["printifnumeric"]) and is_numeric($data[$modifiers["printifnumeric"][0]][0])) or
				(isset($modifiers["ifnumeric"]) and is_numeric($data[$modifiers["ifnumeric"][0]][0])) or
				(isset($modifiers["printifnotnumeric"]) and !is_numeric($data[$modifiers["printifnotnumeric"][0]][0])) or
				(isset($modifiers["ifnotnumeric"]) and !is_numeric($data[$modifiers["ifnotnumeric"][0]][0]))) or

				((isset($modifiers["repeatfor"]) and !empty($data[$modifiers["repeatfor"][0]][0])) or
				!count($modifiers))) {
					$blocks[$key] = array("str"=>substr($blocks[$cb]["str"], ($end+3), ($endblock-($end+3))), "pos"=>0, "op"=>"", "rules"=>$modifiers, "itrcount"=>0);
					$cb = $key;
				}

				// Redefine data in context of block
				$data = $this->_assignedvars;
				foreach ($blocks as $block) {
					if (isset($block["rules"]["repeatfor"]) and isset($data[$block["rules"]["repeatfor"][0]][$block["itrcount"]])) {
						$x = $this->_assignedvars[$block["rules"]["repeatfor"][0]][$block["itrcount"]];
						if (is_scalar($x)) $x = array("value"=>$x, $block["rules"]["repeatfor"][0]=>$x);
						$data = array_merge($data, $x);
						break;
					}
				}
				continue;

			} else {
				$op = "";
			}

			// Apply modifiers
			foreach ($modifiers as $key=>$val) {
				if ($key=="gluewith" and is_array($op)) {
					$op = join($val[0], $op);
				}
			}
			if (is_array($op) and is_object($op[0]) and !method_exists($op[0], '__toString')) $op = $op[0];
			if (is_array($op)) $op = join("", $op);
			foreach ($modifiers as $key=>$val) {
				if (method_exists(__CLASS__, "modifier_".$key)) {
					$args = (count($val)) ? array_merge(array($op), $val) : array($op);
					$op = call_user_func_array(array(__CLASS__, "modifier_".$key), $args);
				}
			}

			// Add completed output into the output buffer
			$blocks[$cb]["op"] .= substr($blocks[$cb]["str"], $blocks[$cb]["pos"], ($pos-$blocks[$cb]["pos"])) . $op;
			$blocks[$cb]["pos"] = $end+3;

		}


		$final = $blocks[0]["op"];

		// Return the final output
		return $final;

	}


	/* Modifiers */

	static function modifier_capitalise($ip) { return ucwords(strtolower($ip)); }
	static function modifier_lowercase($ip) { return strtolower($ip); }
	static function modifier_uppercase($ip) { return strtoupper($ip); }
	static function modifier_nl2br($ip) { return nl2br($ip); }
	static function modifier_collapse_whitespace($ip) { return preg_replace("/\s+/", " ", $ip); }
	static function modifier_trim($ip, $chars=" \t\n\r\0") { return trim($ip, $chars); }
	static function modifier_rtrim($ip, $chars=" \t\n\r\0") { return rtrim($ip, $chars); }
	static function modifier_ltrim($ip, $chars=" \t\n\r\0") { return ltrim($ip, $chars); }
	static function modifier_strip_tags($ip) { return strip_tags($ip); }
	static function modifier_regex_replace($ip, $pattern, $replacement) { return preg_replace($pattern, $replacement, $ip); }
	static function modifier_replace($ip, $search, $replacement) { return str_replace($search, $replacement, $ip); }
	static function modifier_stringformat($ip, $arg) { return sprintf($ip, $arg); }
	static function modifier_dateformat($ip, $format) {
		$op = CommonV2::convertHumanTime($ip);
		if (is_numeric($op) and $op != -1) return date($format, $op);
		if ($op instanceof DateTime) return $op->format($format);
		return $ip;
	}
	static function modifier_prepend($ip, $str) { return (is_numeric($ip) or $ip) ? $str.$ip : ""; }
	static function modifier_append($ip, $str) { return (is_numeric($ip) or $ip) ? $ip.$str : ""; }
	static function modifier_default($ip, $str) { return (is_numeric($ip) or $ip) ? $ip : $str; }
	static function modifier_count_characters($ip) { return strlen($ip); }
	static function modifier_count_words($ip) { return str_word_count($ip); }
	static function modifier_round($ip, $precision=0) { return round($ip, $precision); }
	static function modifier_numberformat($ip, $precision=false) { return (is_numeric($ip)) ? number_format($ip, $precision) : ''; }
	static function modifier_numberprintable($ip, $maxprecision=false) {
		if (!is_numeric($ip)) return '';
		$number = number_format($ip, $maxprecision);
		if ($maxprecision) {
			while ($number{strlen($number)-1} == "0") $number = substr($number, 0, -1);
			if ($number{strlen($number)-1} == ".") $number = substr($number, 0, -1);
		}
		return $number;
	}
	static function modifier_financial($ip, $symbpos="prefix", $currencysymb="&#163;", $decimalplaces=2, $decpoint=".", $thousep=",", $nilprompt="Nil") {
		if (!is_numeric($ip)) return $ip;
		$number = round($ip, $decimalplaces);
		$prefix = ($symbpos=="prefix")?$currencysymb:"";
		$postfix = ($symbpos=="postfix")?$currencysymb:"";
		if ($number == 0) return $nilprompt;
		$op = $prefix.number_format(abs($number), $decimalplaces, $decpoint, $thousep).$postfix;
		if ($number < 0) $op = "&minus;&nbsp;".$op;
		return $op;
	}
	static function modifier_percent($ip, $precision=0, $nilprompt='0') {
		if (!is_numeric($ip)) return $ip;
		$number = round(($ip*100), $precision);
		if ($number == 0) return $nilprompt;
		$op = number_format(abs($number), $precision)."%";
		if ($number < 0) $op = "&minus;&nbsp;".$op;
		return $op;
	}
	static function modifier_bytes($ip) {
		if (!is_numeric($ip)) return $ip;
		if ($ip > 1099511627776) {
			$factor = 1099511627776;
			$unit = "TB";
		} elseif ($ip > 1073741824) {
			$factor = 1073741824;
			$unit = "GB";
		} elseif ($ip > 1048576) {
			$factor = 1048576;
			$unit = "MB";
		} elseif ($ip > 1024) {
			$factor = 1024;
			$unit = "KB";
		} else {
			$factor = 1;
			$unit = "B";
		}
		$precision = ($ip/$factor >= 100) ? 0 : 1;
		return round($ip/$factor, $precision).$unit;
	}
	static function modifier_timestring($ip) {
		if (!is_numeric($ip)) return $ip;
		$prefix = '';
		if ($ip < 0) {
			$prefix = '-';
			$ip = abs($ip);
		}
		if ($ip == 0) {
			return "0";
		} elseif ($ip < 60) {
			return $prefix . (($ip == 1) ? '1 second' : $ip.' seconds');
		} elseif ($ip < 3600) {
			return $prefix . (($ip < 90) ? '1 minute' : round($ip/60).' minutes');
		} elseif ($ip < 86400) {
			return $prefix . (($ip < 5400) ? '1 hour' : round($ip/3600).' hours');
		} elseif ($ip < 14*86400) {
			return $prefix . (($ip < 1.5*86400)? '1 day' : round($ip/86400).' days');
		} elseif ($ip < 8*(7*86400)) {
			return $prefix . round($ip/(7*86400)) . ' weeks';
		} elseif ($ip < 365*86400) {
			return $prefix . round($ip/(30.5*86400)) . ' months';
		} else {
			return $prefix . (($ip < (1.5*(365*86400))) ? '1 year' : round($ip/(365.24*86400)).' years');
		}
	}
	static function modifier_timepast($ip, $spanclass=false, $titleformat=false, $rawformat=false, $cutoff=false) {
		$unix = CommonV2::convertHumanTime($ip);
		if ($unix instanceof DateTime) $unix = $unix->format('U');
		if (!is_numeric($unix)) return $ip;
		$timediff = time() - $unix;
		$elapsed = abs($timediff);
		if (!$cutoff or $elapsed < $cutoff or !$titleformat) {
			if ($elapsed < 10) {
				$op = 'just now';
			} else {
				$op = self::modifier_timestring($elapsed);
				$op = ($timediff > 0) ? $op . ' ago' : 'in ' . $op;
			}
			if ($spanclass) {
				$html = '<span class="'.$spanclass.'"';
				if ($titleformat) $html .= ' title="'.date($titleformat, $unix).'"';
				if ($rawformat) $html .= ' raw="'.date($rawformat, $unix).'"';
				$html .= '>'.$op.'</span>';
				$op = $html;
			}
		} else {
			$op = date($titleformat, $unix);
		}
		return $op;
	}
	static function modifier_truncate($ip, $len, $style="end", $collapsestr="...") {
		if (strlen($ip) > $len) {
			if ($style=="end") {
				return substr($ip, 0, $len-strlen($collapsestr)).$collapsestr;
			} elseif ($style=="middle") {
				return substr($ip, 0, floor(($len-strlen($collapsestr))/2)) . $collapsestr . substr($ip, (strlen($ip)-floor(($len-strlen($collapsestr))/2)));
			} else {
				return $collapsestr . substr($ip, strlen($ip) - ($len-strlen($collapsestr)));
			}
		} else {
			return $ip;
		}
	}
	static function modifier_autolink($text) {

		// Find and mask HTML tags.  Links first, masking the entire link, then all other HTML tags
		$protected = array();
		preg_match_all("/<a .*<\/a>/siU", $text, $m, PREG_PATTERN_ORDER);
		if (!empty($m)) {
			$m = array_unique($m[0]);
			foreach ($m as $link) {
				$id = "{#!#".count($protected)."#!#}";
				$protected[$id] = $link;
				$text = str_replace($link, $id, $text);
			}
		}
		preg_match_all("/<.*>/siU", $text, $m, PREG_PATTERN_ORDER);
		if (!empty($m)) {
			$m = array_unique($m[0]);
			foreach ($m as $tag) {
				$id = "{#!#".count($protected)."#!#}";
				$protected[$id] = $tag;
				$text = str_replace($tag, $id, $text);
			}
		}

		// Match and autolink web URLs
		$scheme		= "(http:\/\/|https:\/\/)";
		$www		= "www\.";
		$ip			= "\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}";
		$name		= "[a-z0-9][-a-z0-9.]+\.";
		$tld		= "[a-z]{2,}(\.[a-z]{2,2})?\.?";
		$the_rest	= "(\/[a-z0-9\._\/\,\:\|\^\@\!~#&=;%+?-\[\]\-\(\)]+[a-z0-9\(\)\/#=?\[\]])?\/?";
		$pattern	= "/".$scheme."?(?(1)(".$ip."|(".$name.")+(".$tld."))|(".$www."(".$name.")+(".$tld.")))".$the_rest."/is";
		if (preg_match_all($pattern, $text, $m)) {
			$urls = array_flip($m[0]);
			foreach ($urls as $key=>$val) {
				$urls[$key] = '<a href="'.((strpos($key, ':') === false)?'http://'.$key:$key).'" target="_blank">'.self::modifier_truncate($key, 40, 'middle').'</a>';
			}
			$text = strtr($text, $urls);
		}

		// Match and autolink email addresses
		$pattern = "/[A-z0-9][\w\.\-\+\_\=]*@[A-z0-9][\w\-\.]+\.[A-z0-9]{2,6}/i";
		if (preg_match_all($pattern, $text, $m)) {
			$emails = array_flip($m[0]);
			foreach ($emails as $addr=>$val) {
				$emails[$addr] = '<a href="mailto:'.$addr.'">'.$addr.'</a>';
			}
			$text = strtr($text, $emails);
		}

		// Unmask HTML tags
		$text = str_replace(array_keys($protected), $protected, $text);

		return $text;
	}
	static function modifier_encode_quotes($ip) { return addslashes($ip); }
	static function modifier_encode_html($ip, $doubleencode=0) {
		if (empty($ip)) return $ip;
		$ret = @htmlentities($ip, ENT_COMPAT, "UTF-8", ($doubleencode==true));
		if (empty($ret)) $ret = str_replace(array("<", ">", "\"", "'"), array("&lt;", "&gt;", "&quot;", "&#039;"), $ip);
		return $ret;
	}
	static function modifier_encode_url($ip) { return rawurlencode($ip); }
	static function modifier_encode_javascriptvariable($ip) { return CommonV2::jsentities($ip); }
	static function modifier_encode_xml($ip) { return CommonV2::xmlentities($ip); }
	static function modifier_render_bbcode($ip) {

		// For simple bb tags, support only the tag itself and no attributes
		do {
			$regexcheck = $ip;
			$ip = preg_replace("/\[(em|strong|code|kbd|blockquote|pre)([^\]]*)?\](.*)\[\/\\1\]/isU", "<$1>$3</$1>", $ip);
		} while ($regexcheck != $ip);

		// Strip extra linebreaks in preformatted text to avoid nl2br/pre combinations
		do {
			$regexcheck = $ip;
			$ip = preg_replace("/\\<pre\>(.*)\<br\s?\/?\>([\n\r])(.*)\<\/pre\>/isU", "<pre>$1$2$3</pre>", $ip);
		} while ($regexcheck != $ip);

		// Add support for links and emails
		$ip = preg_replace("/\[(a|url)\=([\"\'])([^\\2]+)\\2\](.*)\[\/\\1]/isU", "<a href=\"$3\">$4</a>", $ip);
		$ip = preg_replace("/\[a href\=([\"\'])([^\\1]+)\\1\](.*)\[\/a]/isU", "<a href=\"$2\">$3</a>", $ip);
		$ip = preg_replace("/\[email\=([\"\'])([^\\1]+)\\1\](.*)\[\/email]/isU", "<a href=\"mailto:$2\">$3</a>", $ip);

		// For acronym tags, support the tag and title attribute only
		$ip = preg_replace("/\[acronym\=([\"\'])([^\\1]+)\\1\](.*)\[\/acronym\]/isU", "<acronym title=\"$2\">$3</acronym>", $ip);

		return $ip;
	}
	static function modifier_breakonsymb($ip, $insertstr = '<wbr />') {

		// Find and mask HTML tags
		$protected = array();
		preg_match_all("/<.*>/siU", $ip, $m, PREG_PATTERN_ORDER);
		if (!empty($m)) {
			$m = array_unique($m[0]);
			foreach ($m as $tag) {
				$id = "{#!#".count($protected)."#!#}";
				$protected[$id] = $tag;
				$ip = str_replace($tag, $id, $ip);
			}
		}
		$op = preg_replace("/(?<![<>])([,\._\+\-\;\/])/iU", "$1".$insertstr, $ip);
		$op = str_replace(array_keys($protected), $protected, $op);
		return $op;
	}
	static function modifier_nowrap($ip) { return '<span style="white-space:nowrap">'.$ip.'</span>'; }
	static function modifier_wordwrap($ip, $width=72, $break="\n", $cut=false) {
		return wordwrap($ip, $width, $break, $cut);
	}
	static function modifier_ischecked($ip, $val=true) { return ($ip==$val) ? ' checked="checked"' : ''; }
	static function modifier_isselected($ip, $val=true) { return ($ip==$val) ? ' selected="selected"' : ''; }
	static function modifier_truefalse($ip) { return ($ip) ? 'True' : 'False'; }
	static function modifier_yesno($ip) { return ($ip) ? 'Yes' : 'No'; }
}
