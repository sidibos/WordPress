<?php
/**
 * Test for StdoutLogHandler - logging to STDOUT
 *
 * @codingstandard ftlabs-phpcs
 * @copyright The Financial Times Limited [All rights reserved]
 */


class StdoutLoggerTest extends PHPUnit_Framework_TestCase {
	protected $logger;

	protected function setUp() {
		$this->logger = new FTLabs\Logger(array(
			'log'=>array('handler'=>new FTLabs\StdoutLogHandler()),
		));
	}

	public function testCanLogString() {
		$outputstring = "나는 유리를 먹을 수 있어요. 그래도 아프지 않아요";

		$this->expectOutputString("[info] ".$outputstring."\n");
		$this->logger->info($outputstring);
	}

	public function testExtractsExceptionContext() {

		$this->expectOutputRegex("/exceptionmessage.*contextkey=contextvalue/s");
		$this->logger->logException(new FTLabs\Exception("exceptionmessage", array('contextkey'=>'contextvalue')));
	}

	public function testExtractsErrorContext() {

		$this->expectOutputRegex("/errormessage.*contextkey=contextvalue/s");
		$this->logger->error("errormessage", array('error'=>array('context'=>array('contextkey'=>'contextvalue'))));
	}

	public function testCanLogArray() {
		$arraytolog = array(
			'boolean' => true,
			'unicode' => "მინას",
			'has spaces' => "yes I have spaces",
			'has=equals' => "got=equals",
		);
		$this->expectOutputString("[info] msg boolean=true unicode=მინას has_spaces=yes_I_have_spaces has_equals=got_equals\n");
		$this->logger->info("msg", $arraytolog);
	}

	public function testCanLogArrayWithInstanceVariables() {
		$this->logger->setInstanceVariables(array(
			'boolean' => false,
			'ip address' => "127.0.0.1",
		));
		$arraytolog = array(
			'boolean' => true,
			'unicode' => "მინას",
			'has spaces' => "yes I have spaces",
			'has=equals' => "got=equals",
		);
		$this->expectOutputString("[info] msg boolean=true ip_address=127.0.0.1 unicode=მინას has_spaces=yes_I_have_spaces has_equals=got_equals\n");
		$this->logger->info("msg", $arraytolog);
	}
}
