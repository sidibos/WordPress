<?php
/**
 * Test for StdoutLogHandler - using the debug method
 *
 * @codingstandard ftlabs-phpcs
 * @copyright The Financial Times Limited [All rights reserved]
 */


class TraceTest extends PHPUnit_Framework_TestCase {

	public function testNothingIsLoggedWithNoTraceEnvVar() {
		$outputstring = "나는 유리를 먹을 수 있어요. 그래도 아프지 않아요";

		// Clear any TRACE enviroment variable
		putenv("TRACE");

		$logger = new FTLabs\Logger(new FTLabs\StdoutLogHandler());
		$this->expectOutputString("");
		$logger->debug($outputstring);
	}

	public function testSomethingIsLoggedWithTraceEnvVar() {
		$outputstring = "나는 유리를 먹을 수 있어요. 그래도 아프지 않아요";

		// Set the TRACE enviroment variable
		putenv("TRACE=1");

		$logger = new FTLabs\Logger(new FTLabs\StdoutLogHandler());
		$this->expectOutputString('[debug] '.$outputstring."\n");
		$logger->debug($outputstring);
	}
}
