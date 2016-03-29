<?php
/**
 * Test for FileLogHandler
 *
 * @codingstandard ftlabs-phpcs
 * @copyright The Financial Times Limited [All rights reserved]
 */


class LogWriterTest extends PHPUnit_Framework_TestCase {
	public function testConstructor() {
		$logger = new FTLabs\FileLogHandler("testlogger");
		$this->assertNotNull($logger);
	}

	public function testCantWriteObjectToLog() {
		$logger = new FTLabs\FileLogHandler("testlogger");
		$this->setExpectedException('\FTLabs\Exception', "Can only write strings or arrays to log");
		$logger->write(new stdClass());
	}
}
