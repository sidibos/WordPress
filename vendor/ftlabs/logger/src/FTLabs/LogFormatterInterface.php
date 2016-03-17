<?php

namespace FTLabs;

interface LogFormatterInterface {
	/**
	 * Returns log message as a string. Exact format and escaping is implementation-dependent.
	 *
	 * @param string $level   PSR level
	 * @param string $msg     Description
	 * @param mixed  $context any context object
	 * @return string
	 */
	function formattedLogMessage($level, $msg, $context, $timestamp = null);

	/**
	 * Returns MIME type and formatted log message
	 *
	 * @param  ErrorLog $errlog what it says on the tin
	 * @return array            array('text/html', '<h1>error</h1>')
	 */
	function formattedErrorLog(ErrorLog $errlog);
}
