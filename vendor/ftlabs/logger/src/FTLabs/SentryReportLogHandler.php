<?php
/**
 * Posts ErrorLog to Sentry using Raven
 *
 * @codingstandard ftlabs-phpcs
 * @copyright The Financial Times Limited [All rights reserved]
 */

namespace FTLabs;
use \Psr\Log\LogLevel;

class SentryReportLogHandler extends AbstractLogHandler {
	const FULL_LOG_INTERVAL = 14400;
	private $devmode;
	private $client;

	function __construct(\Raven_Client $client, $devmode = false) {
		$this->devmode = $devmode;
		$this->client = $client;
	}

	function requiresErrorLog() {
		return true;
	}

	function handleLogMessage($severity, $message, array $context, ErrorLog $report = null) {

		$errortags = $report->getTags();
		if (!empty($errortags['noreport'])) return;

		$hash = $report->getAggregationHash();

		$load = sys_getloadavg();
		$highload = $load[0] > ErrorLog::HIGH_CPU_LOAD && mt_rand(0,1000) > 4000/$load[0]; // the higher the load the lower chance of reporting an error

		if ($highload) {
			error_log("Not submitting due to high load ($highload) eh:noreport eh:hashcode=$hash eh:occurrence");
			return;
		}

		$logdir = "/tmp/errorhandler/".$hash;

		// If most recent mod time is more than FULL_LOG_INTERVAL seconds ago, include additional
		// context and full globals in debug log.  Always include globals if devmode is enabled
		if ($this->devmode || (!$highload && $this->getLatestReportTime($logdir) < time()-self::FULL_LOG_INTERVAL)) {
			$report->addExtendedInformation();
		} else {
			$report->removeExtendedInformation('Debug curtailed because a previous full report of the same error has been made recently');
		}

		$severityToSentry = array(
			LogLevel::EMERGENCY => \Raven_Client::FATAL,
			LogLevel::ALERT => \Raven_Client::FATAL,
			LogLevel::CRITICAL => \Raven_Client::ERROR,
			LogLevel::ERROR => \Raven_Client::ERROR,
			LogLevel::WARNING => \Raven_Client::WARNING,
			LogLevel::NOTICE => \Raven_Client::WARNING,
			LogLevel::INFO => \Raven_Client::INFO,
		    LogLevel::DEBUG => \Raven_Client::DEBUG,
		);

		$stack = $report->getBacktrace();

		$data = array(
			'timestamp' => strtr($report->getIsoTime(), ' ','T'),
			'level' => $severityToSentry[$report->getSeverity()],
			'tags' => $errortags,
			'message' => $report->getTitle(),
			'extra' => $report->getAsSerializableErrorTree(),
		);

		$this->client->capture($data, $stack);

		$this->updateLatestReportTime($logdir);
	}

	/**
	 * Uses the same dir as Error Aggregator handler
	 */
	private function updateLatestReportTime($logdir) {
		if (!file_exists($logdir)) {
			@mkdir($logdir, 0777, true);
		} else {
			touch($logdir);
		}
	}

	private function getLatestReportTime($logdir) {
		if (!file_exists($logdir)) return 0;

		// There is no need to scan timestamps of all files, since the directory's timestamp gets updated automatically
		return filemtime($logdir);
	}
}
