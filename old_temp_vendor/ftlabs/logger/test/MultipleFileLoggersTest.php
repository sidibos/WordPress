<?php
/**
 * Test for AssankaLoggerV1 - opening lots of file loggers
 *
 * @codingstandard ftlabs-phpcs
 * @copyright The Financial Times Limited [All rights reserved]
 */

class multipleFileLoggersTest extends PHPUnit_Framework_TestCase {
	const LOG_BASE_PATH = '/var/log/apps/';
	protected $loggerpaths = array();
	protected function setUp() {
	}
	protected function tearDown() {
		foreach ($this->loggerpaths as $path) {
			if (file_exists($path)) {
				chmod($path, 644);
				unlink($path);
			}
		}
	}
	public function testLotsOfFileLoggers() {
		$numloggers = 2000;
		$loggers = array();
		for ($ii = 0; $ii < $numloggers; $ii++) {
			$loggername = "testlogger".uniqid();
			$path = self::LOG_BASE_PATH.$loggername.".log";
			$this->loggerpaths[] = $path;

			$logger = new FTLabs\Logger(new FTLabs\FileLogHandler($loggername));
			$loggers[] = $logger;
		}
	}
}
