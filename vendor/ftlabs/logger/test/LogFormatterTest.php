<?php
/**
 * Blah
 *
 * @codingstandard ftlabs-phpcs
 * @copyright The Financial Times Limited [All rights reserved]
 */

use Psr\Log\LogLevel;

class LogFormatterTest extends PHPUnit_Framework_TestCase
{
	private function getErrorLog() {
		return FTLabs\ErrorLog::createFromJson(file_get_contents(__DIR__."/example_error_report.json"));
	}

	function testHTML() {
		$html = new FTLabs\HtmlLogFormatter("template_dev_html");
		list($type, $rendered) = $html->formattedErrorLog($this->getErrorLog());
		$this->assertContains('text/html', $type);
		$this->assertContains('charset=', $type);
		$this->assertContains("########## TEXT VERSION OF THIS ERROR ##########", $rendered);
		$this->assertContains('ERROR: Test 180913', $rendered);
		$this->assertContains('trigger_error("Test 180913",E_USER_ERROR);', $rendered);
		$this->assertContains("<title>Test 180913 (Error)</title>", $rendered);
		$this->assertContains('<div class="errcontentcontainer hideforjs">', $rendered);
		$this->assertContains("<span class='val'>dev02-shell01-uk1.ak.ft.com</span>", $rendered);
	}

	function testText() {
		$html = new FTLabs\TextLogFormatter("template_dev_text");
		list(,$rendered) = $html->formattedErrorLog($this->getErrorLog());
		$this->assertNotContains("<title>", $rendered);
		$this->assertNotContains('<div class', $rendered);
		$this->assertContains('ERROR: Test 180913', $rendered);
		$this->assertContains('trigger_error("Test 180913",E_USER_ERROR);', $rendered);
		$this->assertContains('trigger_error called at', $rendered);
	}

	function testTextStd() {
		$html = new FTLabs\TextLogFormatter("template_std_text");
		list($type, $rendered) = $html->formattedErrorLog($this->getErrorLog());
		$this->assertContains('text/plain', $type);
		$this->assertContains('A problem has occurred.  Please try again', $rendered);

		$this->assertNotContains("<title>", $rendered);
		$this->assertNotContains('<div class', $rendered);
		$this->assertNotContains('ERROR: Test 180913', $rendered);
		$this->assertNotContains('trigger_error("Test 180913",E_USER_ERROR);', $rendered);
		$this->assertNotContains('trigger_error called at', $rendered);
	}

	function testSplunk() {
		$splunk = new FTLabs\SplunkLogFormatter();
		$log = $splunk->formattedLogMessage("error", "hello world", array("foo"=>"bar"), 1400495124.84);

		$this->assertEquals('2014-05-19T10:25:24.840Z [error] hello world foo=bar', $log);
	}

	function testSplunkStruct() {
		$splunk = new FTLabs\SplunkLogFormatter();

		$a = array("level1"=>array("level2"=>array("level3"=>3, "obj"=>(object)array("foo"=>"bar"))));

		$log = $splunk->formattedLogMessage("error", "hello world", $a, 1400495124.84);

		$this->assertEquals('2014-05-19T10:25:24.840Z [error] hello world level1_level2={"level3":3,"obj":{"foo":"bar"}}', $log);

		$a['loop'] = &$a;
		$log = $splunk->formattedLogMessage("error", "hello world", $a, 1400495124.84); // Don't test output, just shouldn't loop forever
		$this->assertLessThan(1000, strlen($log));
	}

	function testSplunkViaLogger() {
		$log = new FTLabs\Logger(new FTLabs\StdoutLogHandler(new FTLabs\SplunkLogFormatter()));

		$this->expectOutputString("2014-05-19T10:25:24.850Z [info] Hello\n");
		$log->info("Hello", array('eh:timestamp'=>1400495124.85));
	}
}
