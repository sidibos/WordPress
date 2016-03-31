<?php
/**
 * Posts ErrorLog to Error Aggregator using format backwards-compatible with Helpdesk
 *
 * @codingstandard ftlabs-phpcs
 * @copyright The Financial Times Limited [All rights reserved]
 */

namespace FTLabs;

class ErrorAggregatorReportLogHandler extends AbstractLogHandler {
	const FULL_LOG_INTERVAL = 14400;
	private $devmode;

	function __construct($devmode = false) {
		$this->devmode = $devmode;
	}

	function requiresErrorLog() {
		return true;
	}

	function handleLogMessage($severity, $message, array $context, ErrorLog $report = null) {

		$hash = $report->getAggregationHash();
		$logdir = "/tmp/errorhandler/".$hash;

		$load = sys_getloadavg();
		$highload = $load[0] > ErrorLog::HIGH_CPU_LOAD && mt_rand(0,1000) > 4000/$load[0]; // the higher the load the lower chance of reporting an error

		// If most recent mod time is more than FULL_LOG_INTERVAL seconds ago, include additional
		// context and full globals in debug log.  Always include globals if devmode is enabled
		if ($this->devmode || (!$highload && $this->getLatestReportTime($logdir) < time()-self::FULL_LOG_INTERVAL)) {
			$report->addExtendedInformation();
		} else {
			$report->removeExtendedInformation('Debug curtailed because a previous full report of the same error has been made recently');
		}

		$json = $report->getSerializedErrorTree();
		$jsonlength = strlen($json);
		$logfile = $this->writeLogFile($json, $logdir);

		if (!$logfile) {
			error_log("Error report could not be written to disk eh:tolerance=3/day eh:hashcode=$hash eh:occurrence");
			return;
		}

		$errortags = $report->getTags();
		if (!empty($errortags['noreport'])) return;

		if ($highload) {
			error_log("Not submitting due to high load ($highload) eh:noreport eh:hashcode=$hash eh:occurrence log_path=$logfile");
			return;
		}

		$helper_path = __DIR__.'/../scripts/postreport';
		$cmd = "nice -n 19 ".escapeshellarg($helper_path)." ".escapeshellarg($logfile)." ".escapeshellarg("jsonlength:$jsonlength")." 2>&1";
		if (empty($errortags['postsync'])) {
			$cmd = "nohup $cmd &";
		}

		$handle = @popen($cmd, "r");
		if ($handle) {
			@pclose($handle);
		} else {
			error_log("Error report could not be posted eh:tolerance=5/day eh:hashcode=$hash eh:occurrence log_path=$logfile");
		}
	}

	private function writeLogFile($json, $logdir) {

		if (!file_exists($logdir)) @mkdir($logdir, 0777, true);

		// Write the occurrence debug log file
		$logfile = @tempnam($logdir, "");
		if (!$logfile || !@file_put_contents($logfile, $json)) {
			return false;
		}
		@chmod($logfile, 0777);

		return $logfile;
	}

	private function getLatestReportTime($logdir) {
		if (!file_exists($logdir)) return 0;

		// There is no need to scan timestamps of all files, since the directory's timestamp gets updated automatically
		return filemtime($logdir);
	}
}
