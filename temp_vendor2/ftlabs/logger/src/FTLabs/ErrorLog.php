<?php
/**
 * Serializable representation of Error Report for Logger and Error Aggregator
 *
 * @codingstandard ftlabs-phpcs
 * @copyright The Financial Times Limited [All rights reserved]
 */

namespace FTLabs;

use Psr\Log\LogLevel;

class ErrorLog {

	// For abbreviate
	const MAX_SCALAR_SIZE = 4096;
	const MAX_ARRAY_SIZE = 40;
	const MAX_TREE_DEPTH = 8;
	const CONTEXT_LINE_COUNT = 3;
	const MODERATE_CPU_LOAD = 6;
	const HIGH_CPU_LOAD = 10;

	public static $version = 10;

	protected $req, $ed, $debug_data;

	private static $php_error_names = array(
		E_ERROR  => "E_ERROR",
		E_WARNING  => "E_WARNING",
		E_PARSE  => "E_PARSE",
		E_NOTICE  => "E_NOTICE",
		E_CORE_ERROR  => "E_CORE_ERROR",
		E_CORE_WARNING  => "E_CORE_WARNING",
		E_COMPILE_ERROR  => "E_COMPILE_ERROR",
		E_COMPILE_WARNING  => "E_COMPILE_WARNING",
		E_USER_ERROR  => "E_USER_ERROR",
		E_USER_WARNING  => "E_USER_WARNING",
		E_USER_NOTICE  => "E_USER_NOTICE",
		E_STRICT  => "E_STRICT",
		E_RECOVERABLE_ERROR  => "E_RECOVERABLE_ERROR",
		E_DEPRECATED => 'E_DEPRECATED',
		E_USER_DEPRECATED => 'E_USER_DEPRECATED',
	);

	/**
	 * Expects JSON-encoded value from getAsSerializableErrorTree()
	 *
	 * @param string $data JSON
	 * @return ErrorLog
	 */
	public static function createFromJSON($json) {
		$data = @json_decode($json, true);
		if (null === $data) {
			throw new LoggerException("JSON decode failed", array(
				'eh:caller'=>true,
				'eh:tolerance'=>'5/day',
				'data'=>$json,
				'err'=>json_last_error(),
				'msg'=>function_exists('json_last_error_msg') ? json_last_error_msg() : null,
			));
		}
		self::stripArrayObjIds($data);

		// Report MUST have a timestamp. If one wasn't provided (e.g. JS reports shouldn't include it) current time is added (report will be saved to the DB with this timestamp)
		if (!isset($data['Error details']['time'])) {
			$data['Error details']['time'] = gmdate('Y-m-d H:i:s').'Z';
		}
		return new self($data);
	}

	/**
	 * abbreviate() puts _errorhandler_type=array in all arrays, and that breaks foreach() later
	 *
	 * @param array &$data unserialized error report data
	 * @return void
	 */
	private static function stripArrayObjIds(&$data) {
		if (is_array($data)) {
			if (isset($data['_errorhandler_type']) && $data['_errorhandler_type'] === 'array') {
				unset($data['_errorhandler_type'], $data['_errorhandler_objid']);
			}
			foreach ($data as $k => $v) {
				if (is_array($v)) self::stripArrayObjIds($data[$k]);
			}
		}
	}

	/**
	 * Creates error report for the current PHP process, inspecting backtrace, globals, etc.
	 *
	 * @param string $severity severity of the error
	 * @param string $errstr   error message
	 * @param array  $context  context of the error, 'error' and 'exception' keys have special handling
	 * @return ErrorLog
	 */
	public static function createFromEnvironment($severity, $errstr, array $context) {

		if (isset($context['exception']) && $context['exception'] instanceof \Exception) {
			$errtree = self::debugInfoFromException($context);
		} elseif (isset($context['error']) && is_array($context['error']) && isset($context['error']['errno'])) {
			$errtree = self::debugInfoFromError($context);
		} else {
			$errtree = self::debugInfoFromEnvironment($context);
		}

		$errtree["Server variables"] = $_SERVER;
		$errtree["Error details"]['msg'] = $errstr;
		$errtree["Error details"]['severity'] = $severity;
		$errtree["Error details"]['server'] = trim(@shell_exec('hostname'));
		$errtree["Error details"]['calledfrom'] = $_SERVER["SCRIPT_NAME"];

		// If the script is being executed in the context of an HTTP Request, add the request details to the error log
		if (self::isHTTP()) {
			$req = array();
			if (isset($_SERVER["REQUEST_METHOD"])) $req["method"] = $_SERVER["REQUEST_METHOD"];
			if (isset($_SERVER["REQUEST_URI"])) $req["requesturi"] = $_SERVER["REQUEST_URI"];
			if (isset($_SERVER["HTTP_HOST"])) $req["host"] = $_SERVER["HTTP_HOST"];
			if (isset($_SERVER["PHP_AUTH_USER"])) $req["user"] = $_SERVER["PHP_AUTH_USER"];
			if (isset($_SERVER["HTTP_REFERER"])) $req["referrer"] = $_SERVER["HTTP_REFERER"];
			if (isset($_SERVER["HTTP_USER_AGENT"])) $req["useragent"] = $_SERVER["HTTP_USER_AGENT"];
			if (isset($_SERVER["REMOTE_ADDR"])) $req["visitor"] = $_SERVER["REMOTE_ADDR"];
			if (isset($_GET)) $req["GET data"] = $_GET;
			if (isset($_POST)) $req["POST data"] = $_POST;
			if (isset($_COOKIE)) $req["Cookies"] = $_COOKIE;
			if (isset($_FILES)) $req["File uploads"] = $_FILES;
			$req['Headers'] = function_exists('apache_request_headers') ? apache_request_headers() : array();
			$errtree['HTTP Request'] = $req;
		}

		return self::createFromErrorTree($errtree);
	}

	/**
	 * Creates custom error
	 *
	 * @param array $errtree Error details in "Helpdesk" format
	 * @return ErrorLog
	 * @see ErrorLog::getAsSerializableErrorTree()
	 * @see ErrorLog::createFromEnvironment()
	 */
	public static function createFromErrorTree(array $errtree) {

		if (!isset($errtree['Error details'])) throw new LoggerException("Missing Error details", array('eh:caller'=>true, 'context'=>get_defined_vars()));

		$reportTime = isset($errtree['Error details']['Error handling']['tags']['timestamp']) ? $errtree['Error details']['Error handling']['tags']['timestamp'] : time();

		$errtree['Error details'] = array_merge(array(
			"msg" => "(unknown error)",
			"errline" => null,
			"file" => null,
			"server" => null,
			"time" => @gmdate("Y-m-d H:i:s\Z", $reportTime), // @, because may be called in environments without date.timezone set
			"localtime" => @date("Y-m-d H:i:s", $reportTime),
			"Error handling" => array(),
		), $errtree['Error details']);

		$ed = &$errtree['Error details'];
		if (!is_string($ed['msg'])) throw new LoggerException("Error message is not a string", array('eh:caller'=>true, 'context'=>get_defined_vars()));

		if (!isset($errtree['Backtrace'])) $errtree['Backtrace'] = array();
		$shortmsg = self::shortenMessage($ed['msg'], $ed['server'], $errtree["Backtrace"]);
		$isInOutputBuffer = isset($ed['source']) && $ed['source'] == 'output buffer';
		list($hashtrace, $hash) = self::createAggregationHash($errtree["Backtrace"], $ed, $shortmsg, $isInOutputBuffer);

		$ed["Error handling"] = array_merge(array(
				"shortmsg" => $shortmsg,
				"hash" => $hash,
				"hashtrace" => $hashtrace,
				"logger_version"=> static::$version,
		), $ed["Error handling"]);

		// eh:caller tag shows file/line of the caller instead of place where error has been thrown (e.g. db->query() line rather than insides of DB wrapper)
		if (isset($ed['Error handling']['tags'], $ed['Error handling']['tags']['caller'], $errtree['Backtrace'])) {
			foreach ($errtree['Backtrace'] as $bt) {
				if (isset($bt['function']) && $bt['function'] == 'call_user_func_array') {
					continue; // pointing to some glue code is unlikely to be useful
				}

				if (isset($bt['line'], $bt['file'])) {
					array_unshift($errtree['Backtrace'], array(
						'file' => $ed['file'],
						'line' => $ed['errline'],
					));
					$ed['file'] = $bt['file'];
					$ed['errline'] = $bt['line'];
					break;
				}
			}
		}

		if (isset($ed['file'], $ed['errline'])) {
			$ed["codecontext"] = self::readCodeContextFromFile($ed['file'], $ed['errline']);
		}
		if (isset($ed['file'])) {
			$ed["gitdeployed"] = self::readDeploymentMetadataFromPath(dirname($ed['file']));
		}

		return new ErrorLog($errtree);
	}

	public static function isHTTP() {
		return (PHP_SAPI != 'cli') and isset($_SERVER["REQUEST_METHOD"]);
	}

	/**
	 * If context contains 'exception' key it's handled according to PSR-3. In addition FTLabs\Exception may augument the context.
	 *
	 * @param array $context error context with 'exception' key
	 * @return array
	 */
	private static function debugInfoFromException(array $context) {
		$exception = $context['exception'];
		unset($context['exception']);

		// Include public properties of the exception
		$publicProperties = get_object_vars($exception);
		if ($publicProperties) {
			$context['exception'] = $publicProperties;
		}

		// Context can include source key that hints whether it's been uncaught exception or explicitly logged one
		$source = isset($context['source']) ? $context['source'] : null;
		unset($context['source']);

		$errstr = $exception->getMessage();

		if ($exception instanceof \AssankaException) {
			list($errstr, $tags) = Logger::extractErrorTags($errstr);
		} elseif ($exception instanceof \FTLabs\Exception) {
			$tags = $exception->getTags();
		} else {
			$tags = array();
		}

		if ($exception instanceof \FTLabs\Exception || $exception instanceof \AssankaException) {
			$ectx = $exception->getContext();
			if (is_array($ectx)) {
				$context = array_merge($context, $ectx);
			} elseif ($ectx !== null) {
				$context[] = $ectx;
			}
		}

		// When exception class suggests the mistake has been made by function's caller tag it automatically as such
		if ($exception instanceof \InvalidCallException || $exception instanceof \FTLabs\InvalidCallException ||
			$exception instanceof \InvalidArgumentException || $exception instanceof \BadMethodCallException || $exception instanceof \BadFunctionCallException ||
			$exception instanceof \Psr\Log\InvalidArgumentException) {
			$tags['caller'] = true;
		}

		$previous = $exception->getPrevious();
		if ($previous) {
			$context['previous_exception'] = $previous;
		}

		// PHP's ErrorException has getSeverity() that is NOT PSR-3 severity, it returns PHP error number
		$php_errno = $exception instanceof \ErrorException ? $exception->getSeverity() : E_ERROR;
		return array(
			"Error details" => array(
				"msg" => $errstr,
				"file" => $exception->getFile(),
				"errline" => $exception->getLine(),
				"level" => $php_errno,
				"levelname" => self::$php_error_names[$php_errno],
				'exception' => get_class($exception),
				'exception_code' => $exception->getCode(),
				"source" => $source,
				"Error handling" => array(
					"tags" => $tags,
				),
			),
			"Context" => $context,
			"Backtrace" => $exception->getTrace(),
		);
	}

	/**
	 * If context contains 'error' key it's interpreted as output of error handler, @see Logger::_phpErrorHandlerCallback()
	 *
	 * @param array $context context with 'error' key
	 * @return array
	 */
	private static function debugInfoFromError(array $context) {
		$error = $context['error'];
		unset($context['error']);

		$source = isset($context['source']) ? $context['source'] : null;
		unset($context['source']);

		if (isset($error['context'])) {
			$context = $error['context'];
		}

		return array(
			"Error details" => array(
				"file" => isset($error['file']) ? $error['file'] : NULL,
				"errline" => isset($error['line']) ? $error['line'] : NULL,
				"level" => $error['errno'],
				"levelname" => self::$php_error_names[$error['errno']],
				"source" => $source,
				"Error handling" => array(
					"tags" => isset($error['tags']) && is_array($error['tags']) ? $error['tags'] : array(),
				),
			),
			"Context" => $context,
			"Backtrace" => isset($error['backtrace']) && is_array($error['backtrace']) ? $error['backtrace'] : array(),
		);
	}

	private static $ignore_backtrace_from_classes = array(
		'Psr\Log\AbstractLogger' => true,
		'FTLabs\ErrorLog' => true,
		'FTLabs\Logger' => true,
	);

	/**
	 * Create error info from backtrace (used in case error has been reported via logger->error() rather than PHP error handler)
	 *
	 * @param array $context arbitrary context
	 * @return array
	 */
	private static function debugInfoFromEnvironment(array $context) {

		// Internal calls of the logger/error handler are noise in the backtrace
		$backtrace = debug_backtrace();
		for ($i = 1; $i < count($backtrace); $i++) {
			if (!isset($backtrace[$i]['class'], self::$ignore_backtrace_from_classes[$backtrace[$i]['class']])) {
				break;
			}
		}
		$backtrace = array_slice($backtrace, $i - 1);

		$tags = array();
		foreach ($context as $k => $v) {
			if ('eh:' === substr($k, 0, 3)) {
				$tags[substr($k,3)] = $v;
				unset($context[$k]);
			}
		}

		$errtree = array(
			"Error details" => array(
				"level" => E_ERROR,
				"levelname" => self::$php_error_names[E_ERROR],
				"source" => "ErrorLog env",
				'Error handling' => array(
					'tags' => $tags,
				),
			),
			"Context" => $context,
			"Backtrace" => $backtrace,
		);

		// Since it's not an error from error handler, explicit file/line is not available
		// backtrace is not guaranteed to contain line number for all items (internal callbacks, etc.)
		if (isset($backtrace[0]['file'])) $errtree['Error details']['file'] = $backtrace[0]['file'];
		if (isset($backtrace[0]['line'])) $errtree['Error details']['errline'] = $backtrace[0]['line'];
		return $errtree;
	}

	private static function createAggregationHash(array $backtrace, array $errorDetails, $shortmsg, $isInOutputBuffer) {
		$errorHandling = $errorDetails["Error handling"];
		$tags = isset($errorHandling['tags']) ? $errorHandling['tags'] : array();

		if (!empty($errorHandling['hashtrace'])) {
			$hashtrace = $errorHandling['hashtrace'];
		} elseif (preg_match('/^MySQL Too many connections/', $shortmsg)) {
			$hashtrace = $shortmsg;
		} else {

			// Remove includes from backtrace before hashing, to group together the same error triggered via different include paths
			$hashtrace = $backtrace;
			$stopat = array("require", "include", "require_once", "include_once");
			for ($i = 0, $s = count($hashtrace); $i < $s; $i++) {
				if (isset($hashtrace[$i]["function"]) and in_array($hashtrace[$i]["function"], $stopat)) {
					$hashtrace = array_slice($hashtrace, 0, $i);
					break;
				}
				unset($hashtrace[$i]["args"]);
				unset($hashtrace[$i]["object"]);
			}

			if ($hashtrace) {

				// Serialise the hashable backtrace.  print_r will fail below if the error handler is running in an output buffer callback, because in order to return the string rather than echoing it, it uses the output buffer, and access to the output buffer is not available in an output buffer callback.  So if this fails, revert to serialise.  If that fails, just use the error string.
				$serialised = $isInOutputBuffer ? false : @print_r($hashtrace, true);
				$hashtrace = $serialised ?: @serialize($hashtrace);
			}
			if (!$hashtrace) {
				$simplified = substr(preg_replace('/[0-9]+/','0', $shortmsg), 0, 150);
				$hashtrace = $simplified."\n".$errorDetails['file'];
			}
		}

		$hashtrace = strtr($hashtrace, "\n", " ");

		if (!empty($errorHandling['hash'])) {
			$hash = $errorHandling['hash'];
		} elseif (!empty($tags['hashcode'])) {
			$hash = $tags['hashcode'];
		} else {
			$hash = sprintf('%08X', crc32($hashtrace));
		}

		return array($hashtrace, $hash);
	}

	/**
	 * 	Add a snippet of code from around the point of the error
	 *
	 * @param string $errfile file path
	 * @param int    $errline line
	 * @return array
	 */
	private static function readCodeContextFromFile($errfile, $errline) {
		$context = array();
		if (is_file($errfile)) {
			$errorfilecontent = file($errfile);
			$minline = max(0, ($errline - 1) - self::CONTEXT_LINE_COUNT);
			$maxline = min(count($errorfilecontent) - 1, ($errline - 1) + self::CONTEXT_LINE_COUNT);
			for ($i = $minline; $i <= $maxline; $i++) {
				$context[$i + 1] = rtrim($errorfilecontent[$i]);
			}
		}
		return $context;
	}

	/**
	 * Walk up directory structure to find ./gitdeployed and return its contents
	 *
	 * @param string $dir starting point
	 * @return string
	 */
	private static function readDeploymentMetadataFromPath($dir) {
		while ($dir && $dir != '/') {
			$deploymentFile = $dir.'/gitdeployed';
			if (is_readable($deploymentFile)) {
				return @parse_ini_file($deploymentFile);
			}
			$parent = dirname($dir);
			if ($dir == $parent) break;
			$dir = $parent;
		}
		return null;
	}

	private static function shortenMessage($errstr, $server, array $backtrace) {

		self::convertToUTF8($errstr);

		$shortmsg = ($a = strpos($errstr, "occured in query")) ? substr($errstr, 0, $a) : $errstr;
		$shortmsg = preg_replace("/\[\<.*\>\]/U", "", $shortmsg);
		$shortmsg = preg_replace_callback("/\([^()]{30,300}\)/u", function($m){
			if (mb_strlen($m[0]) > 50) {
				return mb_substr($m[0], 0, 20).'…'.mb_substr($m[0], -25);
			}
			return '('.mb_substr($m[0], -25);
		}, $shortmsg);


		// Special case for MySQL too many connections or system error 95
		if (preg_match('/mysql_connect\(\)\s*:\s*(Too many connections|Lost connection to MySQL server)/i', $shortmsg)) {

			for ($i = 0; isset($backtrace[$i]) and $backtrace[$i]['function'] != 'mysql_connect'; $i++) {
			} // Skipping over all items in the backtrace which are not the mysql_connect call

			$dbhost = $backtrace[$i]['args'][0];
			$servers = ($dbhost == $server or $dbhost == 'localhost' or $dbhost == '127.0.0.1') ? $server : $dbhost."/".$server;
			$shortmsg = "MySQL Too many connections (".$servers.")";
		}

		return $shortmsg;
	}

	/**
	 * Abbreviates a nested data structure, for debug purposes
	 *
	 * To reduce storage requirements, bandwidth use and size of debug pages displayed in the browser, this method selectively truncates and simplifies a PHP data structure, returning a new data structure.
	 *
	 * Any object instances within the structure will be removed and replaced with a string identifier, with the object itself moved to the $indexedobjs array.  Private members of indexed objects will be exposed and turned into arrays.  However, private members of two object instances that are both references to the same third object cannot be paired, and these references will therefore be expanded in duplicate.
	 *
	 * Numerically indexed arrays with more than MAX_ARRAY_SIZE elements will be truncated to the maximum number of elements, with elements removed from the middle of the array.
	 *
	 * Any scalar value larger than MAX_SCALAR_SIZE will be truncated to MAX_SCALAR_SIZE bytes, with data being removed from the middle of the value, and replaced with the string ' ... '
	 *
	 * Does not allow the structure to recurse beyond MAX_TREE_DEPTH levels.
	 *
	 * @param array   &$data         Data to abbreviate
	 * @param integer $level         Level of recursion, set automatically on recursive calls - should not be set be the caller
	 * @param boolean $isglobals     Set to true if this element should be fully retained and not truncated
	 * @param boolean $inspectarrays Set to true to resolve references where multiple variables reference the same array.  Pollutes the array with error handler debug keys, so should only be used if the error is fatal (ie the arrays are not going to be used again)
	 * @param array   &$indexedobjs  Store for objects referenced via _errorhandler_objid
	 * @return array Abbreviated data
	 */
	protected static function abbreviate(&$data, $level=0, $isglobals=false, $inspectarrays=true, &$indexedobjs) {
		if (is_object($data) and !($data instanceOf Closure)) {
			$objid = spl_object_hash($data);
			if (isset($indexedobjs[$objid])) {
				return array("_errorhandler_objid" => $objid, "_errorhandler_type" => 'object');
			} else {

				$indexedobjs[$objid] = array();

				// Retrieve public members from the object (references are preserved)
				$vars = array();
				$a = get_object_vars($data);
				foreach ($a as $k => $v) {
					if (!isset($vars[$k])) {
						try {
							$vars[$k] = @$data->$k;
						} catch(\Exception $e) {
							/* ignore - the getter can throw, e.g. disconnected mysqli does */
						}
					}
				}

				// Cast object to an array to gain access to private members.  References are broken, so only populate properties that have not already been discovered by get_object_vars.
				$x = (array)$data;
				foreach ($x as $k => $v) {
					$k = preg_replace('/^\0.*?\0(.+)$/', "$1", $k);
					if (!isset($vars[$k])) $vars[$k] = $v;
				}

				$vars['_errorhandler_classname'] = get_class($data);
				$vars['_errorhandler_objid'] = $objid;
				$vars['_errorhandler_type'] = 'object';
				$indexedobjs[$objid] = self::abbreviate($vars, $level, false, $inspectarrays, $indexedobjs);
				return array("_errorhandler_objid" => $objid, "_errorhandler_type" => 'object');
			}
		}
		if (is_array($data) and $level >= self::MAX_TREE_DEPTH) return "ERROR HANDLER MAX TREE DEPTH REACHED";
		if (is_object($data) and $data instanceOf Closure) {
			return array("_errorhandler_type" => "closure");
		} elseif (is_array($data)) {
			if (isset($data['_errorhandler_objid'], $data['_errorhandler_type']) and $data['_errorhandler_type'] == 'array') {
				return array("_errorhandler_type" => 'array', "_errorhandler_arrref" => $data['_errorhandler_objid'], "_errorhandler_objid" => $data['_errorhandler_objid']);
			} else {
				if ($inspectarrays) {
					if (!isset($data['_errorhandler_objid'])) {
						$arrid = count($indexedobjs);
						$data['_errorhandler_objid'] = $arrid;
						$data['_errorhandler_type'] = 'array';
						$indexedobjs[$arrid] = 'array';
					}
				}
				$returnarr = array();
				$boundary = floor(self::MAX_ARRAY_SIZE / 2);
				$s = count($data);
				$i = 0;
				foreach ($data as $k => $v) {
					if ($isglobals or $i < $boundary or $i > ($s - $boundary)) {
						$passwordkeys = array('pw', 'pass', 'password', 'password2', 'vaultkey', 'secret', 'passverify', 'verifypass');
						if (is_string($k) and (in_array($k, $passwordkeys))) {
							$x = "--Password-like key: value deleted--";
						} else {
							$x = self::abbreviate($data[$k], $level + 1, ($k == "Globals" or $k == "Backtrace" or $k == 'Server variables'), $inspectarrays, $indexedobjs);
						}
						$returnarr[$k] = $x;
					} elseif ($i == $boundary) {
						$returnarr['_errorhandler_debug'] = ($s - self::MAX_ARRAY_SIZE)." element(s) removed";
					}
					$i++;
				}
				return $returnarr;
			}

		} elseif (is_bool($data)) {
			return ($data == true);

		} elseif (is_resource($data)) {
			return array('_errorhandler_type'  => 'PHP resource handle', '_errorhandler_value' => (integer)$data);

		} elseif (is_int($data)) {
			return $data + 0;

		} elseif (is_float($data)) {
			if ($data === INF) return '**INF**';
			elseif ($data === -INF) return '**-INF**';
			elseif ($data !== $data) return '**NAN**';
			else return $data + 0;
		} elseif (is_string($data)) {
			$data = trim($data);
			if (isset($_SERVER["PHP_AUTH_PW"])) $data = str_replace($_SERVER["PHP_AUTH_PW"], "--PHP_AUTH_PW Password Deleted--", $data);

			$validEncoding = self::convertToUTF8($data);
			if (!$validEncoding) {

				// Convert unknown encodings to urlencode (with spaces for wrapping)
				$data = strtr(rawurlencode($data),"%"," ");
			}

			// Shorten if necessary
			if (strlen($data) > self::MAX_SCALAR_SIZE) {
				$segmentlen = floor((self::MAX_SCALAR_SIZE - 5) / 2);
				$missing = mb_strlen($data) - self::MAX_SCALAR_SIZE;
				$data = mb_substr($data, 0, $segmentlen) . " ... ".$missing." chrs ... " . mb_substr($data, (strlen($data) - $segmentlen));
			}

			// If hex, wrap in a special type marker
			if (!$validEncoding) $data = array('_errorhandler_type'  => 'String (urlencoded unrecognised charset)', '_errorhandler_value' => $data);

			return $data;


		} elseif ($data === null) {
			return null;

		} else {
			return array('_errorhandler_type' => 'unknown');
		}
	}

	/**
	 * Returns true if succesful, false if string is binary
	 *
	 * @param string &$data input/output
	 * @return bool
	 */
	private static function convertToUTF8(&$data) {
		if (!function_exists('mb_check_encoding')) {
			return false;
		}

		if (mb_check_encoding($data, 'UTF-8')) {
			return true;
		}
		if (mb_check_encoding($data, 'Windows-1252')) {
			mb_convert_encoding($data, 'UTF-8', 'Windows-1252');
			return true;
		}
		return false;
	}

	/**
	 * Creates ErrorLog
	 *
	 * @param array $debug_data Debug data in "Helpdesk" format, use createFromEnvironment() and getAsSerializableErrorTree() to see the structure.
	 */
	function __construct($debug_data) {
		if (!is_array($debug_data) || !isset($debug_data['Error details'])) {
			throw new LoggerException("Missing 'Error details' in the data", array('eh:caller'=>true, 'context'=>get_defined_vars()));
		}
		$this->debug_data = $debug_data;
		$this->ed = &$debug_data['Error details'];

		if (!isset($this->ed['Error handling']['hash'])) {
			throw new LoggerException('Required parameter Error details > Error handling > hash was not supplied', array('eh:caller'=>true, 'context'=>get_defined_vars()));
		}
		$this->req = isset($debug_data['HTTP Request']) ? $debug_data['HTTP Request'] : array();
	}

	private static function getMemoryLimit() {
		$currentmemorylimit = ini_get("memory_limit");
		if (preg_match('/\A([0-9\.]+)([K|M|G])\Z/', $currentmemorylimit, $matchparts)) {
			$multipliers = array('K' => 1024, 'M' => 1048576, 'G' => 1073741824);
			return $matchparts[1] * $multipliers[$matchparts[2]];
		}
		return false;
	}

	function addExtendedInformation() {

		// Increase memory limit and allow globals to be added to var dump
		if (!isset($this->debug_data['Globals']) && isset($GLOBALS)) {
			if (self::getMemoryLimit() < 128 * 1024 * 1024) {
				ini_set("memory_limit", "128M");
			}
			$g = array();
			$unwantedkeys = array("HTTP_COOKIE_VARS", "HTTP_POST_VARS", "HTTP_GET_VARS", "HTTP_SERVER_VARS", "HTTP_ENV_VARS", "HTTP_POST_FILES", "HTTP_SESSION_VARS", '_POST', '_GET', '_COOKIE', '_FILES', '_REQUEST', 'GLOBALS', '_SERVER');
			foreach($GLOBALS as $key  => $vals) if (!in_array($key, $unwantedkeys)) $g[$key] = $vals;
			$this->debug_data['Globals'] = $g;
		}
	}

	/**
	 * True if the log contains no new information (detailed information has been sent previously)
	 *
	 * @see removeExtendedInformation()
	 * @see ErrorAggregatorReportLogHandler
	 * @return boolean
	 */
	function isRedundant() {
		$tags = $this->getTags();
		if (!empty($tags['occurrence'])) return true; // will be logged only as an occurrence, not a full report

		if (isset($this->ed['Error handling']["ratelimitwarning"])) {
			$warning = $this->ed['Error handling']["ratelimitwarning"];
		} elseif (isset($this->ed['Error handling']["highloadwarning"])) {
			$warning = $this->ed['Error handling']["highloadwarning"];
		} else {
			return false;
		}
		return false !== strpos($warning, "full report of the same error has been made recently");
	}

	function removeExtendedInformation($reason) {
		unset($this->debug_data['Context'], $this->debug_data['Globals']);
		$this->debug_data['Error details']['Error handling']["highloadwarning"] = $reason;

		if (isset($this->debug_data['Backtrace'])) {
			foreach($this->debug_data['Backtrace'] as &$bt) {
				unset($bt['args'], $bt['object']);
			}
		}
	}

	/**
	 * Error message with minimum cleanup applied. Use getNiceTitle for human-friendly display.
	 *
	 * @see ErrorLog::getNiceTitle()
	 * @return string
	 */
	function getTitle() {
		return $this->ed['msg'];
	}

	function setTitle($msg) {
		$oldmsg = $this->ed['msg'];
		if ($msg === $oldmsg) return;

		if (!isset($this->ed['oldmsg'])) {
			$this->debug_data['Error details']['oldmsg'] = $oldmsg;
		}

		$this->debug_data['Error details']['msg'] = $msg;
		$this->debug_data['Error details']['Error handling']['shortmsg'] = self::shortenMessage($msg, $this->ed['server'], isset($this->debug_data["Backtrace"]) ? $this->debug_data["Backtrace"] : array());

		$this->ed = &$this->debug_data['Error details'];
	}

	public function getNiceTitle() {
		if (!empty($this->ed['Error handling']['shortmsg'])) {
			$title = $this->ed['Error handling']['shortmsg'];
		} else {
			$title = $this->getTitle();
		}

		$is_html = preg_match('/^<h1>/', $title); // WordPress errors

		$php_html_error_link = '/ \[<a href=\'([^\']+)\'>\1<\/a>\]: /';
		if (preg_match($php_html_error_link, $title)) {
			$title = preg_replace($php_html_error_link, ': ', $title);
			$is_html = true;
		}

		if ($is_html) {
			$title = html_entity_decode(strip_tags($title), ENT_QUOTES, 'UTF-8');
		}

		$title = preg_replace('/; check the manual that corresponds to your (?:MySQL|MariaDB) server version for the right syntax to use/', '', $title);

		if (mb_strlen($title) > 5100) {
			$title = mb_substr($title, 0, 2500).' … '.mb_substr($title, -2500);
		}
		return $title;
	}

	/**
	 * Get the title in a form which is safe for use as the reason phrase of a http status line
	 *
	 * @return string
	 */
	public function getHTTPSafeTitle() {
		$title = $this->getNiceTitle();

		// Control characters aren't allowed in the reason phrase of a http status line
		$title = preg_replace("/[[:cntrl:]]/", "", $title);

		$title = strtr($title, "\r\n","  ");
		$title = substr($title,0,100);

		return $title;
	}

	function getContext() {
		return isset($this->debug_data["Context"]) ? $this->debug_data["Context"] : NULL;
	}

	function getCodeContext() {
		return isset($this->ed["codecontext"]) ? $this->ed["codecontext"] : NULL;
	}

	function getBacktrace() {
		if (!isset($this->debug_data["Backtrace"])) return null;

		unset($this->debug_data["Backtrace"]['_errorhandler_objid']);
		unset($this->debug_data["Backtrace"]['_errorhandler_type']);
		return $this->debug_data["Backtrace"];
	}

	function getDescription() {

		$description = "";
		if (isset($this->ed['errline'], $this->ed["file"])) $description .= "Occured in ".$this->ed["file"].":".$this->ed['errline']."\n\n";
		if (isset($this->ed['calledfrom'])) $description .= "Called from: ".$this->ed["calledfrom"]."\n";
		if (isset($this->ed['levelname'])) $description .= "Error level: ".$this->ed['levelname']."\n";
		if (isset($this->ed["server"])) $description .= "Server: ".$this->getServerName()."\n";

		// If the error occurred in the context of an HTTP request, add those details
		if (isset($this->req["host"])) $description .= "HTTP Host: ".$this->req['host']."\n";
		if (isset($this->req["user"])) $description .= "HTTP auth user: ".$this->req["user"]."\n";
		if (isset($this->req["referrer"])) $description .= "Referrer: ".$this->req["referrer"]."\n";
		if ($this->getUserAgent()) $description .= "User agent: ".$this->getUserAgent()."\n";
		if (isset($this->req["visitor"])) $description .= "User IP address: ".$this->req["visitor"]."\n";

		// Add the summary file offset position so that the error can be found easily in the originating server's error log
		if (isset($this->ed['Error handling']["summaryfilepos"])) $description .= "Logged to local error summary file, offset: ".$this->ed['Error handling']["summaryfilepos"]." bytes\n";

		return $description;
	}

	function getServerName() {
		if (!empty($this->ed['server'])) {
			return $this->ed['server'];
		} elseif ($this->getServerVar('SERVER_NAME')) {
			return $this->getServerVar('SERVER_NAME');
		}
		return $this->getHostname();
	}

	function getReferrer() {
		return isset($this->req["referrer"]) ? $this->req["referrer"] : null;
	}

	function getHostname() {
		if (!empty($this->req['host'])) {
			return $this->req['host'];
		} elseif ($this->getServerVar('HOSTNAME')) {
			return $this->getServerVar('HOSTNAME');
		} elseif (!empty($this->ed['server'])) {
			return $this->ed['server'];
		}
		return $this->getServerVar('SERVER_ADDR');
	}

	function getUrl() {

		if ($this->getServerVar("HTTPS") || $this->getServerVar('HTTP_X_FORWARDED_SSL')) {
			$protocol = 'https://';
		} elseif ($this->req) {
			$protocol = 'http://';
		} else {
			$protocol = 'file://'; // presumably invoked from CLI
		}

		$hostname = $this->getHostName();

		if ($this->getRequestVar('requesturi')) {
			$path = $this->getRequestVar('requesturi');
		} elseif ($this->getServerVar('REQUEST_URI')) {
			$path = $this->getServerVar('REQUEST_URI');
		} else {
			$path = $this->getScriptName();
		}

		if (!$hostname) {
			if (!$path) return NULL;

			// JS puts entire URL in requesturi
			if (preg_match('#^https?://#', $path)) {
				return $path;
			}
			$hostname = '{unknown host}';
		}

		if ($path && $path[0] !== '/') $path = '/.../'.$path;

		return $protocol.$hostname.$path;
	}

	function getUserAgent() {
		return isset($this->req["useragent"]) ? $this->req["useragent"] : null;
	}

	/**
	 * rfc5424 severity as a string, capitalized, with Information shortened to Info
	 *
	 * @return string
	 */
	function getSeverity() {
		if ($this->getLoggerVersion() < 4 && isset($this->ed['level']) && $this->ed['level'] === E_NOTICE) {
			return 'notice';
		}

		if (isset($this->ed['severity'])) {
			return $this->ed['severity'];
		}
		if (isset($this->ed['level'])) {
			return Logger::severityForPhpError($this->ed['level']);
		}
		return "error";
	}

	/**
	 * Associative array with Error Tags: noreport, hashcode
	 *
	 * @return array
	 */
	function getTags() {
		$tags = isset($this->ed['Error handling']['tags']) ? $this->ed['Error handling']['tags'] : array();
		if (is_array($tags) && isset($tags['noreport'], $tags['tolerance'])) { // emulates behaviour of old logger which ignored eh:noreport
			unset($tags['noreport']);
		}
		return $tags;
	}

	/**
	 * Returns pretty unique hash for grouping of occurrences of the same error together.
	 * That's the primary way to find and identify this error log.
	 *
	 * @return string
	 */
	function getAggregationHash() {
		return isset($this->ed['Error handling']['hash']) ? $this->ed['Error handling']['hash'] : null;
	}

	function setAggregationHash($hash) {
		$oldhash = $this->getAggregationHash();
		if ($hash === $oldhash) {
			return;
		}
		if (!isset($this->ed['Error handling']['oldhash'])) {
			$this->debug_data['Error details']['Error handling']['oldhash'] = $oldhash;
		}
		$this->debug_data['Error details']['Error handling']['hash'] = $hash;

		$this->ed = &$this->debug_data['Error details'];
	}

	private static function readableHash($input) {
		return substr(md5($input),0,16) . substr(preg_replace('/[^a-zA-Z0-9_.:-]+/','', $input),0,16);
	}

	private static function truncatedFilePath($path) {
		return basename(dirname($path)).'/'.basename($path);
	}

	/**
	 * Aims to replace all variable parts of error messages with constants
	 *
	 * @param string $title message
	 * @return string
	 */
	private static function normalizedTitle($title) {
		$title = mb_strtolower(stripslashes($title));
		$title = preg_replace('# (?:"[^"]+"|\'[^\']+\') #','"text"', $title);
		$title = preg_replace('#(?:ftp|https?|file)://[^\s<>]+#','/url/', $title);
		$title = preg_replace('#/[^\s:\*\?<>]*/(?:[a-z]+|[\w]\.[a-z0-9]+)#','/filepath/', $title);
		$title = preg_replace('/\d+/', '0', $title);
		return substr($title, 0, 25);
	}

	/**
	 * Returns array of hash=>relevance pairs derived from different aspects of this error.
	 * These hashes are expected to collide with other similar reports.
	 *
	 * Hash lenghts are variable up to 32 ASCII chars. Relevance is float 0..1
	 *
	 * @return array array('foobar123'=>0.5)
	 */
	function getAlternativeAggregationHashes() {
		$hashes = array(
			$this->getISOTime() => 0.3, // If errors occur on the same second they may have same (sudden) cause
			self::readableHash(self::truncatedFilePath($this->getFile()).floor($this->getLine()/20)) => 0.6, // Approx location
			self::readableHash(self::truncatedFilePath($this->getFile()).$this->getLine()) => 1,
			self::readableHash($this->getUrl()) => 0.4,
			self::readableHash($this->getNiceTitle()) => 0.8,
			self::readableHash(self::normalizedTitle($this->getNiceTitle())) => 0.2,
		);
		$backtrace = $this->getBacktrace();
		if ($backtrace) {
			$max = ceil(count($backtrace)/2); // Use only half of backtrace, since all scripts have similar top-level entry points
			$level = 0;
			foreach ($backtrace as $bt) {
				if ($level++ > $max) break;
				if (isset($bt['file'], $bt['line'])) {
					$hash = self::readableHash(self::truncatedFilePath($bt['file']).$bt['line']);
					if (!isset($hashes[$hash])) $hashes[$hash] = 0.37/$level;
				}
			}
		}

		// These are meant to catch errors triggered by crawlers
		if (isset($this->req["referrer"])) $hashes[self::readableHash($this->req["referrer"])] = 0.2;
		if (isset($this->req["visitor"])) $hashes[self::readableHash($this->req["visitor"])] = 0.11;
		if ($this->getUserAgent()) $hashes[self::readableHash($this->getUserAgent())] = 0.1;

		return $hashes;
	}

	/**
	 * Time as ISO 8601 string in UTC
	 *
	 * @return string
	 */
	function getIsoTime() {

		// Old versions of logger used current timezone rather than UTC
		if (!isset($this->ed['localtime']) && $this->getLoggerVersion() < 2) {
			$this->ed['localtime'] = $this->ed['time'];
			$this->ed['time'] = gmdate('Y-m-d H:i:s', strtotime($this->ed['time'])).'Z';
		}
		return $this->ed['time'];
	}

	/**
	 * Time as ISO 8601 string in reporter's (PHP server or JS client) local time
	 *
	 * @return string
	 */
	function getIsoLocalTime() {
		return isset($this->ed['localtime']) ? $this->ed['localtime'] : $this->getIsoTime();
	}

	function getFile() {
		return isset($this->ed['file']) ? $this->ed['file'] : null;
	}

	function getLine() {
		return !empty($this->ed['errline']) && is_numeric($this->ed['errline']) ? $this->ed['errline'] : null;
	}

	function getSource() {
		if (isset($this->ed['source'])) {
			return $this->ed['source'];
		}
		if (isset($this->ed['Error handling']['source'])) {
			return $this->ed['Error handling']['source']; // JS client < 0.6
		}
		return null;
	}

	function getRequestVar($name) {
		if (!isset($this->req[$name])) return null;
		$val = $this->req[$name];
		if ($name == 'requesturi' && ($val === '-' || $val === 'null' || $val === '(null)')) return null;
		return $val;
	}

	function getServerVar($name) {
		return (isset($this->debug_data['Server variables'][$name])) ? $this->debug_data['Server variables'][$name] : null;
	}

	function isDev() {
		return $this->getServerVar('IS_DEV') || 0 === $this->getServerVar('IS_LIVE') || "0" === $this->getServerVar('IS_LIVE');
	}

	function getScriptName() {
		if (($name = $this->getServerVar('SCRIPT_NAME'))) return $name;
		if (!empty($this->ed['calledfrom'])) return $this->ed['calledfrom'];
		if (($name = $this->getServerVar('PHP_SELF'))) return $name;
	}

	function getDeploymentInformation() {
		return isset($this->ed['gitdeployed']) ? $this->ed['gitdeployed'] : null;
	}

	/**
	 * Returns array with 'Error details' and other fields in format backwards-compatible with Helpdesk and Error Aggregator
	 *
	 * @return array
	 */
	function getAsSerializableErrorTree() {

		if (!isset($this->debug_data['objstore'])) {

			if (!isset($this->debug_data['Globals']) && isset($this->debug_data['Context']) && isset($this->debug_data['Context']['GLOBALS'])) {
				$this->debug_data['Context']['GLOBALS'] = '/* GLOBALS */';
			}

			// Abbreviation and sending of potentially huge backtrace/context is expensive
			$load = sys_getloadavg();
			$this->ed['load'] = $load;

			$moderate_load = $load[0] > self::MODERATE_CPU_LOAD;
			if ($load[0] > self::HIGH_CPU_LOAD && mt_rand(0,1000) > 4000/$load[0]) {
				$this->removeExtendedInformation('Debug process curtailed by high load');
			}

			// abbreviate(), especially when inspecting arrays, may be memory hungry and cause process to hit the limit
			$max_mem = self::getMemoryLimit();
			$used_mem = memory_get_usage();
			$low_mem = $max_mem && ($max_mem - $used_mem < 15 * 1024 * 1024);
			$very_low_mem = $max_mem && ($max_mem - $used_mem < 3 * 1024 * 1024);

			if ($very_low_mem) {
				$this->removeExtendedInformation('Debug process curtailed by low memory');
			}

			// Arary inspection is destructive, so use only for serious errors
			// COMPLEX:KL:20140531 $inspectarrays must be enabled only when DisplayErrorLogHandler will run and stop execution
			// otherwise the app will continue with corrupted data (full of _errorhandler_arrref)
			$inspectarrays = !$low_mem && Logger::isSeverityGreaterOrEqual($this->getSeverity(), LogLevel::CRITICAL);

			// Array modifications shouldn't persist in the session
			$session_backup = $inspectarrays && !empty($_SESSION) ? unserialize(serialize($_SESSION)) : null;

			// Truncate data in sections of the log which might otherwise get very big, and index all referenced objects into the $indexedobjs array
			$indexedobjs = array(0 => 1);
			$recursion_level = $moderate_load ? self::MAX_TREE_DEPTH-3 : 0; // Keep context shallow if server is under load
			$this->debug_data = self::abbreviate($this->debug_data, $recursion_level, true, $inspectarrays, $indexedobjs);
			$this->debug_data['objstore'] = $indexedobjs;

			if ($session_backup !== null) $_SESSION = $session_backup;
		}

		return $this->debug_data;
	}

	/**
	 * Return ErrorLog in format accepted by the Error Aggregator
	 *
	 * @return string
	 */
	function getSerializedErrorTree() {

		// Serialise to JSON if possible
		$errtree = $this->getAsSerializableErrorTree();
		$json = @json_encode($errtree);
		if ($json) {
			return $json;
		}

		$serialised = serialize($errtree);
		$json = @json_encode(array(
			'Error details'=>array(
				'msg' => $this->getTitle(),
				"file" => $this->getFile(),
				"errline" => $this->getLine(),
				'Error handling' => array(
					'hash' => $this->getAggregationHash(),
				),
			),
			'_serialized'=>$serialised,
		));
		return $json ?: $serialised;
	}

	/**
	 * Get version of JS error aggregator client, or false if it's a server-side report
	 * @return mixed number or bool
	 */
	function getJsClientVersion() {
		if (isset($this->ed['client_version'])) {
			return $this->ed['client_version'];
		}
		return false;
	}

	function getLoggerVersion() {
		if (isset($this->ed['Error handling']['logger_version'])) {
			return $this->ed['Error handling']['logger_version'];
		}
		if (!isset($this->ed['Error handling']['summaryfilepos'])) {
			return 1;
		}
		return 0;
	}
}

