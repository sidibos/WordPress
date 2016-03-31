<?php
/**
 * Hack inherited from ErrorHandlerV5
 *
 * @codingstandard ftlabs-phpcs
 * @copyright The Financial Times Limited [All Rights Reserved]
 */

namespace FTLabs;

class SessionIdHackHandler extends AbstractLogHandler {

	public function requiresErrorLog() {
		return false;
	}

	function handleLogMessage($severity, $errstr, array $context, ErrorLog $errlog = null) {

		// Special case for corrupted session IDs - assign a new session (support request #2809)
		if (strpos($errstr, "The session id contains invalid characters, valid characters are") !== false or strpos($errstr, "The session id contains illegal characters, valid characters are") !== false) {

			// Clear the current session
			unset($_COOKIE["PHPSESSID"]);
			@session_unset();
			@session_destroy();

			// Start a new one using the current microsecond timestamp as an ID to replace the corrupted one
			session_id(str_replace('.', '', microtime(true)));
			session_start();


			/* Allow error page to be displayed because the script may depend on session data that has now been wiped.  On next pageload the new session will be used. */

		}
	}
}
