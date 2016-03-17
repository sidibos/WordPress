<?php
/**
 * A Shim to give a PSR-3 compatible interface to the old logging class in the Assanka Core.
 *
 * @codingstandard ftlabs-phpcs
 * @copyright The Financial Times Limited [All Rights Reserved]
 */
namespace FTLabs;

use \Psr\Log\LogLevel;

final class Logger extends \Psr\Log\AbstractLogger {
	private $instance_variables = array(), $extended_report;
	private $handlers, $handlers_for_severity;
	private $raven_client;

	private static $global_logger;
	private static $last_error_handled;

	// Allows correct numeric comparisons and ORing
	private static $severity_to_bitmask = array(
		LogLevel::EMERGENCY => 128,
		LogLevel::ALERT => 64,
		LogLevel::CRITICAL => 32,
		LogLevel::ERROR => 16,
		LogLevel::WARNING => 8,
		LogLevel::NOTICE => 4,
		LogLevel::INFO => 2,
		LogLevel::DEBUG => 1,
	);

	private static $php_error_to_severity = array(
		E_ERROR => LogLevel::ALERT,
		E_CORE_ERROR => LogLevel::ALERT,
		E_PARSE => LogLevel::ALERT,
		E_COMPILE_ERROR => LogLevel::ALERT,
		E_RECOVERABLE_ERROR => LogLevel::CRITICAL,
		E_USER_ERROR => LogLevel::ALERT,
		E_COMPILE_WARNING => LogLevel::CRITICAL,
		E_CORE_WARNING => LogLevel::CRITICAL,
		E_USER_WARNING => LogLevel::CRITICAL,
		E_WARNING => LogLevel::ERROR,
		E_USER_NOTICE => LogLevel::ERROR,
		E_NOTICE => LogLevel::WARNING,
		E_STRICT => LogLevel::NOTICE,
		E_DEPRECATED => LogLevel::NOTICE,
		E_USER_DEPRECATED => LogLevel::NOTICE,
	);

	/**
	 * Options that can be ORed together
	 */
	const PAGE_TYPE_HTML = 4;
	const PAGE_TYPE_TEXT = 8;
	const NO_BUFFERING = 16;
	const NO_ERROR_HANDLER = 32;

	/**
	 * @var ABORT_EXECUTION return from handler to stop execution after all current handlers finish
	 */
	const ABORT_EXECUTION = ':abort';

	/**
	 * Set is_dev=DEV_ACCESS_PASSWORD cookie or query string argument to show debug info in any environment
	 */
	const DEV_ACCESS_PASSWORD = '9108c15623472d21';

	/**
	 * Set (by merging) instance variables or clear all if NULL is passed
	 *
	 * @param mixed $vars array or NULL
	 * @return void
	 */
	public function setInstanceVariables(array $vars = null) {
		$this->instance_variables = $vars === null ? array() : array_merge($this->instance_variables, $vars);
	}

	/**
	 * Creates new logger instance. @see Logger::init()
	 *
	 * You can define multiple log handlers for different severities:
	 * array(
	 * 	'name_of_the_handler' => array('handler' => LogHandler instance,
	 *                                 'min_severity' => LogLevel::DEBUG,
	 *                                 'max_severity' => LogLevel::EMERGENCY)
	 * )
	 * min/max severity is optional.
	 *
	 * @param mixed $handlers log name, AbstractLogHandler or array describing handlers
	 */
	public function __construct($handlers) {
		if (is_string($handlers)) $handlers = new \FTLabs\FileLogHandler($handlers);
		if ($handlers instanceof \FTLabs\AbstractLogHandler) {
			$handlers = array(
				'log'=> array('handler'=>$handlers),
			);
		}
		if (!is_array($handlers)) {
			throw new LoggerException("Logger expects array of log handler definitions or \FTLabs\AbstractLogHandler");
		}
		$this->setLogHandlers($handlers);
	}

	public function reinitialise() {

		// Avoid calling same handler twice
		$all_handlers = array();
		foreach ($this->handlers as $info) {
			$all_handlers[spl_object_hash($info['handler'])] = $info['handler'];
		}
		foreach ($all_handlers as $handler) {
			$handler->reinitialise();
		}
	}

	private static function isAjaxRequest() {
		if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] == 'XMLHttpRequest') return true;
		if (isset($_SERVER["REQUEST_URI"]) && preg_match("/\/aja?x\//i", $_SERVER["REQUEST_URI"])) return true;
		if (empty($_SERVER['HTTP_USER_AGENT']) || preg_match('/\bcurl\//', $_SERVER['HTTP_USER_AGENT'])) return true;
		return false;
	}

	/**
	 * Configures logger to intercept PHP errors and uncaught exceptions.
	 *
	 * It's safe to call multiple times, but please don't abuse this as a singleton
	 * - store logger instance on first init or create new instances using constructor.
	 *
	 * @param string $options PAGE_TYPE_TEXT or PAGE_TYPE_HTML
	 * @return Logger
	 */
	public static function init($options = null) {
		if (self::$global_logger) return self::$global_logger;

		self::ensureErrorLogClassIsCompatible();

		$page_type = null;
		$raven_client = null;
		$error_handler = true;
		$buffering = true;

		if (is_int($options)) {
			if ($options & self::PAGE_TYPE_TEXT) {
				$page_type = self::PAGE_TYPE_TEXT;
			} elseif ($options & self::PAGE_TYPE_HTML) {
				$page_type = self::PAGE_TYPE_HTML;
			}
			$error_handler = !($options & self::NO_ERROR_HANDLER);
			$buffering = !($options & self::NO_BUFFERING);
		} else if (is_array($options)) {
			if (isset($options['page_type'])) {
				$page_type = $options['page_type'];
			}
			if (isset($options['raven_client'])) {
				$raven_client = $options['raven_client'];
			} elseif (isset($options['sentry_dsn'])) {
				$raven_client = new \Raven_Client($options['sentry_dsn']);
			}
			$error_handler = !isset($options['error_handler']) || $options['error_handler'];
			$buffering = !isset($options['buffering']) || $options['buffering'];
		}

		if (!$page_type) {
			$page_type = PHP_SAPI == 'cli' || self::isAjaxRequest() ? self::PAGE_TYPE_TEXT : self::PAGE_TYPE_HTML;
		}

		if ($raven_client && defined('PROJECT_NAMESPACE')) {
			$raven_client->extra_context(array('PROJECT_NAMESPACE' => PROJECT_NAMESPACE));
		}

		$logger = new self(self::getDefaultHandlers($page_type, $raven_client));
		$logger->raven_client = $raven_client;

		if ($error_handler) {
			$logger->setAsGlobalErrorHandler();
		}

		// Enable output buffering if running in web server to ensure error pages replace any output already sent to the browser
		if ($buffering && isset($_SERVER["REQUEST_URI"])) {
			ob_start(array($logger, '_phpOutputBufferCallback'));
		}

		return self::$global_logger = $logger;
	}

	private static function getDefaultHandlers($page_type, $raven_client) {

		$devmode = PHP_SAPI == 'cli-server' ||
			!empty($_SERVER["IS_DEV"]) ||
			(isset($_REQUEST["is_dev"]) && $_REQUEST["is_dev"] === self::DEV_ACCESS_PASSWORD);

		if ($page_type === self::PAGE_TYPE_TEXT) {
			$format = new TextLogFormatter($devmode ? 'template_dev_text' : 'template_std_text');
		} else {
			$format = new HtmlLogFormatter($devmode ? 'template_dev_html' : 'template_std_html');
		}

		if ($devmode) {
			return array(
				'log' => array(
					'handler' => new FileLogHandler(defined('PROJECT_NAMESPACE') ? PROJECT_NAMESPACE : 'php-dev'),
					'min_severity' => LogLevel::INFO,
				),
				'stop' => array(
					'handler'=> new DisplayErrorLogHandler($format),
					'min_severity' => LogLevel::NOTICE,
					'extended_report' => true,
				),
			);
		} else {
			return array(
				'log' => defined('PROJECT_NAMESPACE') ? array(
						'handler' => new FileLogHandler(PROJECT_NAMESPACE),
						'min_severity' => LogLevel::INFO,
					) : array(
						'handler' => new FileLogHandler('php-errors'),
						'min_severity' => LogLevel::NOTICE,
					),
				'report' => array(
					'handler' => $raven_client ? new SentryReportLogHandler($raven_client, $devmode) : new ErrorAggregatorReportLogHandler($devmode),
					'min_severity' => LogLevel::WARNING,
				),
				'session' => array(
					'handler' => new SessionIdHackHandler(),
					'min_severity' => LogLevel::WARNING,
				),
				'stop' => array(
					'handler'=> new DisplayErrorLogHandler($format),
					'min_severity' => LogLevel::CRITICAL,
				),
			);
		}
	}

	/**
	 * Re-sets logger configuration for text or html
	 *
	 * @param string $page_type PAGE_TYPE_HTML or PAGE_TYPE_TEXT
	 * @return void
	 */
	public function setOutputType($page_type) {
		$this->setLogHandlers(self::getDefaultHandlers($page_type, $this->raven_client));
	}

	public function log($severity, $message, array $context = array()) {
		if (!isset(self::$severity_to_bitmask[$severity])) {
			throw new \Psr\Log\InvalidArgumentException("Invalid severity $severity");
		}

		if (empty($this->handlers_for_severity[$severity])) {
			return;
		}

		if (is_array($message)) throw new \Psr\Log\InvalidArgumentException("Array passed as message");

		// Allow message argument to be an ErrorLog for custom reports (e.g. forwarded from splunk)
		if ($message instanceof ErrorLog) {
			$error_log = $message;
			$message = $error_log->getTitle();
		} else {
			$error_log = null;
			$context = array_merge($this->instance_variables, $context);
		}

		$abort = false;
		foreach ($this->handlers_for_severity[$severity] as $handler) {
			try {

				// Creation of ErrorLog is expensive, so it's created lazily and only for handlers that require it
				if (!$error_log && $handler->requiresErrorLog()) {
					self::ensureErrorLogClassIsCompatible();
					self::extendTimeLimit(5); // gathering of debug data may take some time
					$error_log = ErrorLog::createFromEnvironment($severity, $message, $context);
					if ($this->extended_report) $error_log->addExtendedInformation();
				}

				$result = $handler->handleLogMessage($severity, $message, $context, $error_log);
				if ($result === self::ABORT_EXECUTION) $abort = true;
			}
			catch(\Exception $e) {
				error_log($e->getMessage()." in ".$e->getFile().":".$e->getLine());
			}
		}

		// The @ operator in error handler callback doesn't prevent error_get_last() from being set
		// Workaround for https://redmine.labs.ft.com/issues/28677#note-6
		if ($error_log && $error_log->getSource() === 'error') {
			self::$last_error_handled = error_get_last();
		}

		if ($abort) exit(70); // EX_SOFTWARE /usr/include/sysexits.h
	}

	private static function ensureErrorLogClassIsCompatible() {
		static $checked_once = false;
		if ($checked_once) return;
		$checked_once = true;

		if (!property_exists('\\FTLabs\\ErrorLog', 'version')) $version = 0;
		else $version = ErrorLog::$version;

		if ($version < 5) {
			$r = new \ReflectionClass('\\FTLabs\\ErrorLog');
			error_log("Invalid version of ErrorLog found. Logger loaded from: ".__FILE__." ErrorLog $version loaded from: ".$r->getFileName());
		}
	}

	/**
	 * Give the script extra time to run, be careful not to shorten existing timeout
	 *
	 * @param int $extraSeconds seconds
	 * @return void
	 */
	private static function extendTimeLimit($extraSeconds) {
		$timeElapsed = !empty($_SERVER['REQUEST_TIME']) ? time() - $_SERVER['REQUEST_TIME'] : 1;
		$maxTimeAllowed = intval(ini_get('max_execution_time'));

		// If the maximum execution time is a zero or falsey value, no time limit applies
		// so don't alter the time limit.
		if (!$maxTimeAllowed) {
			return;
		}

		set_time_limit(max($maxTimeAllowed - $timeElapsed, $extraSeconds));
	}

	/**
	 * Adds handlers (e.g writing to log file, reporting to bug tracker) to the logger.
	 * Handlers are assigned to certain range of severities, executed in order they were added.
	 * Each handler is given a name (to later disable it by name)
	 *
	 * @param array $handlers array('name'=>array('handler'=>$instance, 'min_severity'=>$severity));
	 * @return void
	 */
	public function setLogHandlers(array $handlers) {

		// Definition of handlers is rewritten to be easy to access when logging
		$handlers_for_severity = array();
		foreach ($handlers as $handler_name => $info) {
			if (!is_array($info) || !$info['handler'] instanceof \FTLabs\AbstractLogHandler) {
				throw new LoggerException("Handler '$handler_name' must be an instance of \FTLabs\AbstractLogHandler", $info);
			}

			if (!empty($info['extended_report'])) $this->extended_report = true;

			$min_severity_num = self::$severity_to_bitmask[isset($info['min_severity']) ? $info['min_severity'] : LogLevel::DEBUG];
			$max_severity_num = self::$severity_to_bitmask[isset($info['max_severity']) ? $info['max_severity'] : LogLevel::EMERGENCY];
			foreach (self::$severity_to_bitmask as $severity => $num) {
				if ($num >= $min_severity_num and $num <= $max_severity_num) {
					if (!isset($handlers_for_severity[$severity])) {
						$handlers_for_severity[$severity] = array();
					}
					$handlers_for_severity[$severity][$handler_name] = $info['handler'];
				}
			}
		}

		if (!getenv("TRACE")) {
			unset($handlers_for_severity[LogLevel::DEBUG]);
		}

		$this->handlers = $handlers;
		$this->handlers_for_severity = $handlers_for_severity;
	}

	/**
	 * Sets severity of handler given by name. If there is no such handler NULL is returned.
	 *
	 * @param string $name         Name of the handler given in setLogHandlers
	 * @param string $min_severity new minimum severity
	 * @return string previous secerity or NULL
	 */
	public function setHandlerMinSeverity($name, $min_severity) {
		if (!isset($this->handlers[$name])) return null;

		$info = &$this->handlers[$name];
		$previous = isset($info['min_severity']) ? $info['min_severity'] : LogLevel::DEBUG;

		$info['min_severity'] = $min_severity;
		$this->setLogHandlers($this->handlers);

		return $previous;
	}

	public function setAsGlobalErrorHandler() {
		set_error_handler(array($this, '_phpErrorHandlerCallback'));
		set_exception_handler(array($this, '_phpExceptionHandlerCallback'));
		register_shutdown_function(array($this, '_phpShutdownCallback'));
	}

	/**
	 * Log and report exception
	 *
	 * @param Exception $e        The exception
	 * @param string    $severity exception severity level
	 * @return void
	 */
	public function logException(\Exception $e, $severity = null) {
		$this->logExceptionFrom($e, $severity, 'logger');
	}

	public function logErrorLog(ErrorLog $log) {
		$this->log($log->getSeverity(), $log);
	}

	public function _phpExceptionHandlerCallback(\Exception $e) {

		// All uncaught exceptions have alert severity, because that stops execution
		// & needs to be higher than level for regular PHP errors
		$this->logExceptionFrom($e, LogLevel::ALERT, 'uncaught exception');
	}

	private function logExceptionFrom(\Exception $e, $severity, $source) {
		if (null === $severity) {
			if ($e instanceof \ErrorException) {
				$severity = self::severityForPhpError($e->getSeverity()); // ErrorException's severity is not PSR
			} else {
				$severity = LogLevel::CRITICAL;
			}
		}

		$errstr = $e->getMessage();
		if (!$errstr) $errstr = get_class($e);

		$context = array(
			'exception'=>$e,  // PSR-3
			'source'=>$source,
		);

		return $this->log($severity, $errstr, $context);
	}

	/**
	 * Should not be called directly.  Use trigger_error instead,
	 * which will call this as the method registered as PHP's
	 * error handler.  Depending on the configuration of the error
	 * handler instance, reportError <b>may</b> cause the script to
	 * exit before returning.
	 *
	 * @param integer $errno   Bitwise integer indicating the error tye
	 * @param string  $errstr  Error message
	 * @param string  $errfile File path of file in which error occurred
	 * @param integer $errline Line number on which error occurred
	 * @param array   $context Variables within the context of the error
	 * @return void
	 *
	 * @see setAsGlobalErrorHandler()
	 */
	public function _phpErrorHandlerCallback($errno, $errstr, $errfile, $errline, $context) {

		// Respects error_reporting and @ operator
		if (!(error_reporting() & $errno)) return;

		list($errstr, $tags) = $this->extractErrorTags($errstr);

		$backtrace = debug_backtrace();
		array_shift($backtrace); // hide call to the handler itself

		$this->log(self::severityForPhpError($errno), $errstr, array('error'=>array(
			'errno'=>$errno,
			'file'=>$errfile,
			'line'=>$errline,
			'tags'=>$tags,
			'context'=>$context,
			'backtrace'=>$backtrace,
			),
			'source'=>'error'
		));
	}

	public static function extractErrorTags($errstr) {

		// Detect and read machine tags in error string
		$errortags = array();
		preg_match_all('/\beh\:(httpresponse|hashcode|tolerance|projcode|report|noreport|occurrence|caller)(=([^\s]*))?\b\s?/i', $errstr, $m, PREG_SET_ORDER);
		if ($m) {
			foreach ($m as $match) {
				$errstr = str_replace($match[0], "", $errstr);
				$errortags[$match[1]] = isset($match[3]) ? $match[3] : true;
			}
		}
		return array(trim($errstr), $errortags);
	}

	public static function severityForPhpError($errno) {
		if (isset(self::$php_error_to_severity[$errno])) {
			return self::$php_error_to_severity[$errno];
		}
		return LogLevel::ERROR;
	}

	/**
	 * Compares to LogLevel:XXX PSR-3 severities (arg1 >= arg2)
	 *
	 * @param string $severity arg1
	 * @param string $than     arg2
	 * @return boolean
	 */
	public static function isSeverityGreaterOrEqual($severity, $than) {
		return self::$severity_to_bitmask[$severity] >= self::$severity_to_bitmask[$than];
	}

	public function _phpOutputBufferCallback($output) {
		if (false !== strpos($output, 'Fatal error')) {

			// On fatal error the shutdown handler won't run, so this is the last chance to handle fatal errors.
			// Not all errors are handled here, because inside an output buffer callback
			// other buffer-related functions cause problems.
			$this->logLastPHPError('output buffer');
		}
		return $output;
	}

	public function _phpShutdownCallback() {
		$this->logLastPHPError('shutdown handler');
	}

	private function logLastPHPError($source) {

		// Returning false from PHP error handler prevents error_get_last() from being set,
		// so this will only see unhandled errors. $last_error_handled is just a backup in case
		// both _phpOutputBufferCallback and _phpShutdownCallback are run.
		$error = error_get_last();
		if ($error && $error !== self::$last_error_handled) {
			self::$last_error_handled = $error;
			$this->log(self::severityForPhpError($error['type']), $error['message'], array(
				'error' => array(
					'errno' => $error['type'],
					'file' => $error['file'],
					'line' => $error['line'],
					'tags' => array('nostop' => true), // it doesn't make sense to stop execution in the shutdown handler
					'backtrace' => array(),
				),
			  	'source' => $source,
			));
		}
	}
}

// Backwards compatibility
class_alias('FTLabs\ErrorAggregatorReportLogHandler', 'FTLabs\RemoteErrorReportLogHandler');
