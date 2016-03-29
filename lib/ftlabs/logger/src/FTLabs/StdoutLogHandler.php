<?php
/**
 * Prints log to stdout, duh.
 *
 * @codingstandard ftlabs-phpcs
 * @copyright The Financial Times Limited [All rights reserved]
 */

namespace FTLabs;

class StdoutLogHandler extends AbstractLogHandler {

	function __construct(LogFormatterInterface $formatter = null) {
		$this->formatter = $formatter ? $formatter : new KeyValueLogFormatter();
	}

	public function reinitialise() {
		flush();
	}

	public function handleLogMessage($severity, $message, array $context, ErrorLog $e = NULL) {
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
		echo $body,"\n";
	}
}
