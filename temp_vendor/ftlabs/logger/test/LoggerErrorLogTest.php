<?php
/**
 * Uh
 *
 * @codingstandard ftlabs-phpcs
 * @copyright The Financial Times Limited [All rights reserved]
 */

use \Psr\Log\LogLevel;

class CustomTestExceptionClass extends \Exception {}

class LoggerErrorLogTest extends PHPUnit_Framework_TestCase {

	private function createLoggerReturningErrorLog(&$err_log) {
		$mock = $this->getMock('FTLabs\StdoutLogHandler', array());

		$mock->expects($this->atLeastOnce())
			->method('requiresErrorLog')
			->will($this->returnValue(true));

		$mock->expects($this->once())
			->method('handleLogMessage')
			->will($this->returnCallback(function($s,$m,array $c, FTLabs\ErrorLog $err_log_in)use(&$err_log){
				$err_log = $err_log_in;
			}));

		$logger = new FTLabs\Logger($mock);

		return $logger;
	}

	public function testErrorLoging() {
		$logger = $this->createLoggerReturningErrorLog($err_log);

		$logger->error("Booh!"); /* test:should be present in context • */
		$this->assertInstanceOf('\FTLabs\ErrorLog', $err_log);

		$bt = $err_log->getBacktrace();
		$this->assertInternalType('array', $bt);
		$this->assertEquals(__FILE__, $bt[0]['file']);
		$this->assertArrayHasKey('function', $bt[0]);
		$this->assertEquals('error', $bt[0]['function']);

		$this->assertContains('test:should be present in context • ', implode(' ',$err_log->getCodeContext()));
	}

	public function testErrorLogingFromCallback() {
		$logger = $this->createLoggerReturningErrorLog($err_log);

		set_error_handler(array($logger, '_phpErrorHandlerCallback'));
		error_reporting(error_reporting() | E_USER_WARNING);

		try {
			$line = __LINE__; trigger_error("Testīng eh:noreport eh:tolerance=1/hour eh:projcode=1234", E_USER_WARNING);
		} catch(\ErrorException $e) {}
		restore_error_handler();

		$this->assertInstanceOf('\FTLabs\ErrorLog', $err_log);

		$bt = $err_log->getBacktrace();
		$this->assertInternalType('array', $bt);
		$this->assertEquals("Testīng", $err_log->getTitle());
		$this->assertEquals("Testīng", $err_log->getNiceTitle());
		$this->assertEquals(__FILE__, $bt[0]['file']);
		$this->assertEquals($line, $bt[0]['line']);
		$this->assertEquals(array('projcode'=>"1234", 'tolerance'=>'1/hour', 'noreport'=>true), $err_log->getTags());

		$errtree = $err_log->getAsSerializableErrorTree();
		$this->assertTrue(isset($errtree['Error details']['Error handling']['hash']));
	}


	public function testErrorWithTolerance() {
		$logger = $this->createLoggerReturningErrorLog($err_log);
		$logger->error("Vitamins!", array('eh:tolerance'=>'5/day'));

		$this->assertNotNull($err_log);
		$this->assertEquals(array('tolerance'=>'5/day'), $err_log->getTags());
	}

	public function testParentException() {
		$logger1 = $this->createLoggerReturningErrorLog($err_log1);
		$logger2 = $this->createLoggerReturningErrorLog($err_log2);

		$parentException = new \FTLabs\Exception("Vitamins!");

		$logger1->logException(new \FTLabs\Exception($parentException, array('eh:tolerance'=>'15/day')), LogLevel::ERROR);

		$this->assertNotNull($err_log1);

		$logger2->logException(new \FTLabs\Exception("new exc", $parentException), LogLevel::CRITICAL);

		$this->assertContains("Vitamins!", $err_log1->getTitle());
		$this->assertEquals(LogLevel::ERROR, $err_log1->getSeverity());
		$this->assertEquals(array('tolerance'=>'15/day'), $err_log1->getTags());


		$this->assertNotNull($err_log2);
		$this->assertEquals("new exc", $err_log2->getTitle());
		$this->assertEquals(LogLevel::CRITICAL, $err_log2->getSeverity());
		$this->assertEquals(array(), $err_log2->getTags());
	}

	public function testLogFTLabsException() {
		$logger = $this->createLoggerReturningErrorLog($err_log);

        $e = new FTLabs\Exception(array("foo"=>"bar", 'eh:hashcode'=>"customhash"));
        $this->assertEquals(array("foo"=>"bar"), $e->getContext());

		$logger->setInstanceVariables(array("unwanted"=>"fail"));
		$logger->setInstanceVariables(null);
		$logger->setInstanceVariables(array("instance"=>"old"));
		$logger->setInstanceVariables(array("instance2"=>"var2"));
		$logger->setInstanceVariables(array("instance"=>"var"));
		$logger->logException($e, LogLevel::ALERT);

		$this->assertInstanceOf('\FTLabs\ErrorLog', $err_log);
		$this->assertEquals(LogLevel::ALERT, $err_log->getSeverity());
		$this->assertEquals("FTLabs\Exception", $err_log->getTitle());
		$this->assertEquals(array("foo"=>"bar", "instance"=>"var", "instance2"=>"var2"), $err_log->getContext());

		$this->assertEquals(array('hashcode'=>'customhash'), $err_log->getTags());
		$this->assertEquals('customhash', $err_log->getAggregationHash());

		$err_log->setAggregationHash("customhash2");
		$this->assertEquals('customhash2', $err_log->getAggregationHash());
	}

	public function testExceptionNameAsMessage() {
		$logger = $this->createLoggerReturningErrorLog($err_log);
		$logger->logException(new CustomTestExceptionClass());

		$this->assertNotNull($err_log);
		$this->assertContains("CustomTestExceptionClass",$err_log->getTitle());

		$err_log->setTitle("foo");
		$this->assertContains("foo", $err_log->getTitle());
	}

	public function testExceptionDataIsPreserved() {
		$logger = $this->createLoggerReturningErrorLog($err_log);
		$logger->logException(new CustomTestExceptionClass("test", 1234));

		$this->assertNotNull($err_log);
		$errtree = $err_log->getAsSerializableErrorTree();
		$this->assertContains("CustomTestExceptionClass", $errtree['Error details']['exception']);
		$this->assertEquals(1234, $errtree['Error details']['exception_code']);
	}
}
