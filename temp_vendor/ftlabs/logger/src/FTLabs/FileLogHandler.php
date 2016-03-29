<?php
/**
 * Logger helper
 *
 * @codingstandard ftlabs-phpcs
 * @copyright The Financial Times Limited [All rights reserved]
 */

namespace FTLabs;

class FileLogHandler extends AbstractLogHandler {
	private $logpath;
	private $formatter;

	/**
	 * Creates a new Logger instance
	 *
	 * @param string $logname   The name of this log, should be unique within the project (Used in the path of log files)
	 * @param object $formatter Instance of log formatter used to convert context array to text
	 */
	public function __construct($logname, LogFormatterInterface $formatter = null) {
		$this->formatter = $formatter ? $formatter : new SplunkLogFormatter();
		$this->logpath = "/var/log/apps/{$logname}.log";
	}

	private function writeString($string) {
		$old_mask = umask(0);
		$result = @file_put_contents($this->logpath, $string."\n", FILE_APPEND);
		if (!$result) {
			@mkdir(dirname($this->logpath), 0777, true);
			$result = @file_put_contents($this->logpath, $string."\n", FILE_APPEND);
			if (!$result) {
				error_log("[{$this->logpath} write failed] $string");
			}
		}
		umask($old_mask);
		return $result !== false; // file_put_contents returns length on success
	}

	public function reinitialise() {
		return true;
	}

	public function handleLogMessage($severity, $message, array $context, ErrorLog $e = null) {
		if ($e) {
			list(,$body) = $this->formatter->formattedErrorLog($e);
		} else {
			if (isset($context['eh:timestamp'])) {
				$timestamp = $context['eh:timestamp'];
				unset($context['eh:timestamp']);
			} else {
				$timestamp = null;
			}
			$body = $this->formatter->formattedLogMessage($severity, $message, $context, $timestamp);
		}
		return $this->writeString($body);
	}

	/**
	 * Writes some variables to the log.  The format of the variabels should be a key=>value array.  This is not a structure of any kind and can be used to simply log variables to the logger.  This will also include any instance variables that have been set by calling setInstanceVariables unless  they are overridden here.
	 *
	 * @param mixed $vars Ideally an array of variables to write to the log.  For backwards compatibility, this can also be a string.  However, using a string will result in instance variables not being added to the log.
	 * @return void
	 */
	public function write($vars) {
		return $this->writeString($this->formatter->formattedLogMessage(null, null, $vars));
	}
}
