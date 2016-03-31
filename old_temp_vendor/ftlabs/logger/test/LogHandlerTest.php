<?php
/**
 * duh
 *
 * @codingstandard ftlabs-phpcs
 * @copyright The Financial Times Limited [All rights reserved]
 */


class LogHandlerTest extends PHPUnit_Framework_TestCase {
	public function testDisplayErrorLogHandler() {
		$logger = new FTLabs\Logger(new FTLabs\DisplayErrorLogHandler(new FTLabs\TextLogFormatter("sometemplate")));
		$logger->error("yo", array('eh:nostop'=>true));
	}
}
