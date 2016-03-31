<?php
/**
 * Displays error report as text or HTML and stops execution
 *
 * @codingstandard ftlabs-phpcs
 * @copyright The Financial Times Limited [All rights reserved]
 */

namespace FTLabs;

use FTLabs\ErrorLog;

class DisplayErrorLogHandler extends AbstractLogHandler {
	function __construct(LogFormatterInterface $formatter) {
		$this->formatter = $formatter;
	}

	public function requiresErrorLog() {

		// Always wants ErrorLog to be able to display detailed information.
		// Whether anything is displayed at all is controlled by assigning this handler
		// to desired severities in the Logger (which varies for dev/prod, etc.)
		return true;
	}

	function handleLogMessage($severity, $message, array $context, ErrorLog $errlog = null) {
		$errortags = $errlog->getTags();

		if (!empty($errortags['nostop'])) return;

		foreach (array_reverse(ob_list_handlers()) as $handler) {
			if ($handler === 'ob_gzhandler' || $handler === 'zlib output compression') break;
			if (!@ob_end_clean()) break;
		}

		list($mime, $body) = $this->formatter->formattedErrorLog($errlog);

		if (ErrorLog::isHTTP() && !headers_sent()) {
			$code = !empty($errortags['httpresponse']) ? $errortags['httpresponse'] : 500;
			header("HTTP/1.1 $code ".$errlog->getHTTPSafeTitle());
			header("Content-type: $mime");
			header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");
			header("Cache-Control: no-cache, must-revalidate");
			header("X-No-Cache: 1");
			header("X-ErrorHash: ".$errlog->getAggregationHash());
		}

		echo $body;

		flush();

		return Logger::ABORT_EXECUTION;
	}
}
