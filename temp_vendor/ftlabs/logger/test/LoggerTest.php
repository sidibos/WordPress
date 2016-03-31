<?php
/**
 * Uh
 *
 * @codingstandard ftlabs-phpcs
 * @copyright The Financial Times Limited [All rights reserved]
 */

use \Psr\Log\LogLevel;

class LoggerTest extends PHPUnit_Framework_TestCase
{
	public function testTagsInError() {
		list($msg, $tags) = FTLabs\Logger::extractErrorTags("Literal strings should not be included in queries.  Use a prepared statement. eh:caller");

		$this->assertNotContains('eh:caller', $msg);
		$this->assertEquals(array('caller'=>true), $tags);
	}

    /**
     *
     * @expectedException \FTLabs\LoggerException
     */
	public function testRequiresHandlers() {
		new FTLabs\Logger((object)array("nonsense"));
	}

    /**
     * @expectedException \FTLabs\Exception
     */
	public function testRequiresHandlers2() {
		new FTLabs\Logger(array(
			"bla"=>"bla"
		));
	}

	public function testReinitialises() {
		$mock = $this->getMock('FTLabs\StdoutLogHandler', array('reinitialise'));
		$mock->expects($this->once())
            ->method('reinitialise')
            ->will($this->returnValue(true));
		$logger = new FTLabs\Logger(array(
			'log'=>array('handler'=>$mock),
			'log2'=>array('handler'=>$mock),
		));
		$logger->reinitialise();
	}

	public function testLogException() {
		$mock = $this->getMock('FTLabs\StdoutLogHandler', array());
		$mock->expects($this->atLeastOnce())
			->method('requiresErrorLog')
			->will($this->returnValue(false));

		$mock->expects($this->once())
            ->method('handleLogMessage')
            ->with($this->equalTo(LogLevel::CRITICAL), $this->equalTo("Hello Worłd"))
            ->will($this->returnValue(true));

		$logger = new FTLabs\Logger($mock);
		$logger->logException(new \Exception("Hello Worłd"));
	}

	public function testLogErrorException() {
		$mock = $this->getMock('FTLabs\StdoutLogHandler', array('handleLogMessage'));
		$mock->expects($this->once())
            ->method('handleLogMessage')
            ->with($this->equalTo(LogLevel::CRITICAL), $this->equalTo("Hellö World"))
            ->will($this->returnValue(true));

		$logger = new FTLabs\Logger($mock);
		$logger->logException(new \ErrorException("Hellö World",0,E_USER_WARNING));
	}

	public function testShutdownCallback() {
		$mock = $this->getMock('FTLabs\StdoutLogHandler', array('handleLogMessage'));
		$mock->expects($this->once())
			->method('handleLogMessage')
			->with($this->equalTo(LogLevel::ERROR), $this->equalTo("Testing"))
			->will($this->returnValue(true));

		$logger = new FTLabs\Logger($mock);

		set_error_handler(function(){return false;});
		try {
			@trigger_error("Testing");
		} catch(\Exception $e) {}
		restore_error_handler();

		$this->assertInternalType('array', error_get_last());

		$logger->_phpShutdownCallback();
	}

	public function testSilenceOperatorWorks() {
		$mock = $this->getMock('FTLabs\StdoutLogHandler', array());
		$mock->expects($this->never())
			->method("handleLogMessage");

		$logger = new FTLabs\Logger($mock);

		error_reporting(error_reporting() | E_USER_WARNING);
		set_error_handler(array($logger, '_phpErrorHandlerCallback'));
		try {
			@trigger_error("Testing", E_USER_WARNING);
		} catch(\ErrorException $e) {}
		restore_error_handler();
	}

	public function testOldClassName() {
		new \FTLabs\RemoteErrorReportLogHandler();
	}

	public function testErrorLogingSettingWorks() {
		$mock = $this->getMock('FTLabs\StdoutLogHandler', array());
		$mock->expects($this->never())
			->method("handleLogMessage");

		$logger = new FTLabs\Logger($mock);

		set_error_handler(array($logger, '_phpErrorHandlerCallback'));
		error_reporting(($previous = error_reporting()) & ~E_USER_WARNING);
		try {
			trigger_error("Testing", E_USER_WARNING);
		} catch(\ErrorException $e) {}
		restore_error_handler();
		error_reporting($previous);
	}

	public function testSettingHandlers() {

		$logger = new FTLabs\Logger(array(
			'stdout1' => array('handler'=>new FTLabs\StdoutLogHandler(), 'min_severity'=>LogLevel::INFO),
			'stdout2' => array('handler'=>new FTLabs\StdoutLogHandler(), 'min_severity'=>LogLevel::NOTICE),
		));

		$logger->debug("Ignored");
		$logger->info("Goes to first");
		$logger->notice("Goes to both ∆");

		$this->assertEquals(LogLevel::NOTICE,
		$logger->setHandlerMinSeverity('stdout2', LogLevel::WARNING));

		$logger->notice("Goes to first one only");
		$logger->warning("Goes to both again");

		$this->expectOutputString("[info] Goes to first
[notice] Goes to both ∆
[notice] Goes to both ∆
[notice] Goes to first one only
[warning] Goes to both again
[warning] Goes to both again
");
	}

	function testGetSource() {
		$errLog = FTLabs\ErrorLog::createFromJson(file_get_contents(__DIR__."/example_js_report.json"));
		$this->assertEquals("JS:ft-app", $errLog->getSource());
	}
}
