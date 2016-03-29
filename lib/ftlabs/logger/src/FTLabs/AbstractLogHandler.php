<?php
/**
 * Common interface for log handlers
 *
 * @codingstandard ftlabs-phpcs
 * @copyright The Financial Times Limited [All rights reserved]
 */

namespace FTLabs;

use Psr\Log\LogLevel;

abstract class AbstractLogHandler {

	/**
	 * Called whenever logger is told to reinitialize, e.g. should re-open log files and flush output.
	 *
	 * @codeCoverageIgnore
	 * @return bool success
	 */
	public function reinitialise() {
		return true;
	}

	/**
	 * Return true if handleLogMessage() must receive ErrorLog argument, otherwise it may be null.
	 *
	 * @return bool
	 */
	public function requiresErrorLog() {
		return false;
	}

	/**
	 * Process error report in any way (log, display, send, etc.). @see requiresErrorLog()
	 * The report must not be modified by this function, @see preprocessErrorLog()
	 *
	 * @param string $severity PSR-3
	 * @param string $message  Text
	 * @param array  $context  Arbitrary context of the event
	 * @param ErrorLog $errlog (optional) Detailed data of the error that occurred
	 * @return mixed           return Logger::ABORT_EXECUTION if error is not recoverable
	 */
	abstract function handleLogMessage($severity, $message, array $context, ErrorLog $errlog = null);
}
