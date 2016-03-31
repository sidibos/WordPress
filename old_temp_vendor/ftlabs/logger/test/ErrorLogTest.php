<?php
/**
 * Uh
 *
 * @codingstandard ftlabs-phpcs
 * @copyright The Financial Times Limited [All rights reserved]
 */

use \Psr\Log\LogLevel;

class ErrorLogTest extends PHPUnit_Framework_TestCase {

	function setUp() {
		$load = sys_getloadavg();
		if ($load[0] >= 5) $this->markTestSkipped("Load too high");
	}

	function testAddExtraInformation() {
		global $test_variable;
		$test_variable = 123;

		$err_log = FTLabs\ErrorLog::createFromEnvironment(Psr\Log\LogLevel::NOTICE, "blah", array("foo"=>"bar"));
		$err_log->addExtendedInformation();
		$data = $err_log->getAsSerializableErrorTree();

		$this->assertArrayHasKey("Globals", $data);
		$this->assertArrayHasKey("test_variable", $data["Globals"]);
		$this->assertArrayHasKey("Context", $data);
		$this->assertArrayHasKey("foo", $data["Context"]);
	}

	function testRemoveExtraInformation() {
		$err_log = FTLabs\ErrorLog::createFromEnvironment(Psr\Log\LogLevel::ERROR, "blah", array());
		$this->assertFalse($err_log->isRedundant());
		$err_log->removeExtendedInformation('Debug curtailed because a previous full report of the same error has been made recently');
		$data = $err_log->getAsSerializableErrorTree();

		$this->assertArrayNotHasKey("Globals", $data);
		$this->assertArrayNotHasKey("Context", $data);
		$this->assertArrayHasKey("Backtrace", $data);
		$this->assertTrue($err_log->isRedundant());
		$this->assertGreaterThanOrEqual(2, $err_log->getLoggerVersion());
	}

	function testSerializedRedundant() {
		$err_log = FTLabs\ErrorLog::createFromJson(file_get_contents(__DIR__."/redundant_report.json"));
		$this->assertTrue($err_log->isRedundant());
	}

	function testNoGlobalsByDefault() {
		global $test_variable;
		$GLOBALS["test_variable"] = 123;

		$err_log = FTLabs\ErrorLog::createFromEnvironment(Psr\Log\LogLevel::ALERT, "blah", get_defined_vars());
		$data = $err_log->getAsSerializableErrorTree();

		$this->assertArrayNotHasKey("Globals", $data);
		$this->assertArrayHasKey("Context", $data);
		$this->assertArrayNotHasKey("GLOBALS", $data["Context"]);
	}

	function testShowsCaller() {
		$err_log = FTLabs\ErrorLog::createFromEnvironment('error', "test", array('exception'=>new \FTLabs\InvalidCallException()));
		$this->assertArrayHasKey('caller', $err_log->getTags());

		$err_log = FTLabs\ErrorLog::createFromEnvironment('error', "test", array('exception'=>new \BadFunctionCallException()));
		$this->assertArrayHasKey('caller', $err_log->getTags());

		$err_log = FTLabs\ErrorLog::createFromEnvironment('error', "test", array('exception'=>new \FTLabs\Exception()));
		$this->assertArrayNotHasKey('caller', $err_log->getTags());
	}

	function testGetsTagsFromAssankaException() {
		$err_log = FTLabs\ErrorLog::createFromEnvironment('error', "test", array('exception'=>new Testing\MySqlQueryException("Something happened. eh:caller")));
		$this->assertNotContains('eh:caller', $err_log->getTitle());
		$this->assertArrayHasKey('caller', $err_log->getTags());
	}

	function testInfNan() {
		$log = FTLabs\ErrorLog::createFromErrorTree(array(
			'Error details' => array(),
			'Context'=>array(
				'inf' => INF,
				'nan' => NAN,
			),
		));
		$json = $log->getSerializedErrorTree();
		$this->assertInternalType('object', json_decode($json));
	}

	function testTime() {
		$err_log = FTLabs\ErrorLog::createFromEnvironment('error', "test", array('eh:timestamp' => 1400000000));
		$this->assertEquals("2014-05-13 16:53:20Z", $err_log->getIsoTime());
	}

	function testCustom() {
		$log = FTLabs\ErrorLog::createFromErrorTree(array(
			'Error details' => array(
				'msg'=>'<h1>AT&amp;T</h1>',
				'file' => 'foo.js',
				'errline' => '123',
				'Error handling' => array(
					'hashtrace' => 'customtrace',
					'hash' => 'customhash',
					'tags' => array( 'sometag' => 123, 'caller'=>true),
				),
			),
			'Backtrace' => array(
				array('file' => 'bar.js', 'line'=> 10, 'object'=>'o', 'args'=>array(1,2)),
				array('file' => '<script>', 'line'=> 1),
			),
			'Globals' => array(
				'window' => (object)array(
					'alert' => 'function alert() { [native code] }',
				),
			),
		));

		$this->assertEquals('<h1>AT&amp;T</h1>', $log->getTitle());
		$this->assertEquals('AT&T', $log->getNiceTitle());

		$this->assertEquals(array('sometag'=>123, 'caller'=>true), $log->getTags());
		$this->assertEquals('customhash', $log->getAggregationHash());
		$this->assertEquals('bar.js', $log->getFile());
		$this->assertEquals(10, $log->getLine());
		$this->assertNull($log->getServerName());
		$this->assertNull($log->getUrl());
		$this->assertEquals("error", $log->getSeverity());
		$this->assertInternalType('array', $log->getBacktrace());
		$bt = $log->getBacktrace();
		$this->assertArrayHasKey('args', $bt[1]);

		$log->removeExtendedInformation('test');

		$this->assertInternalType('array', $log->getBacktrace());
		$bt = $log->getBacktrace();
		$this->assertArrayHasKey('file', $bt[1]);
		$this->assertArrayNotHasKey('args', $bt[1]);
	}

	function testAbbreviatedTitles() {
		$longtitle = "file_get_contents(/var/long/path/lorem/ipsum/dolor/sit/amet/consectetur/adipisicing/elit/sed/do/eiusmod/) error";
		$log = FTLabs\ErrorLog::createFromEnvironment('error', $longtitle, array());
		$this->assertEquals("file_get_contents(/var/long/path/lorem/ipsum/dolor/sit/amet/consectetur/adipisicing/elit/sed/do/eiusmod/) error", $log->getTitle());
		$this->assertEquals("file_get_contents(/var/long/path/lore…ing/elit/sed/do/eiusmod/) error", $log->getNiceTitle());
	}

	function testAbbreviatedUnicodeTitles() {
		$longtitle = "日本の書　file_get_contents(/var/long/path/lorem/ipsum/dolor/sit/amet/consectetur/adipisicing/elit/sed/do/eiusmod/) error";
		$log = FTLabs\ErrorLog::createFromEnvironment('error', $longtitle, array());
		$this->assertEquals("日本の書　file_get_contents(/var/long/path/lorem/ipsum/dolor/sit/amet/consectetur/adipisicing/elit/sed/do/eiusmod/) error", $log->getTitle());
		$this->assertEquals("日本の書　file_get_contents(/var/long/path/lore…ing/elit/sed/do/eiusmod/) error", $log->getNiceTitle());
	}

	/*
	 * This test is run in a child process, because it kills PHP on failure.
	 *
	 * @runInSeparateProcess
	 * @requires PHPUnit 3.7.32
	 * @preserveGlobalState disabled
	 */
	/*
	function testMemory() {

		// PHPUnit's process isolation tries to preserve global state in a fundamentally flawed way, hence hacks
		require_once __DIR__.'/bootstrap.php';

		$bigstuff = array();
		$ref = range(1,100);
		for ($i = 0; $i < 15000; $i++) {
			$bigstuff[] = array($i, &$ref);
		}

		$mem = memory_get_usage();
		$this->iniSet('memory_limit', ceil($mem / 1024 + 1000).'K');

		$err_log = FTLabs\ErrorLog::createFromEnvironment(Psr\Log\LogLevel::ERROR, "blah", array('big'=>$bigstuff));

		$data = $err_log->getAsSerializableErrorTree();
	}
	*/

	function testGetsDeploymentInfo() {
		$err_log = FTLabs\ErrorLog::createFromEnvironment('error', "blah", array());

		$deployed = $err_log->getDeploymentInformation();
		$this->assertInternalType('array', $deployed);
		$this->assertArrayHasKey('module.name', $deployed);
	}

	function testNoDeploymentInfo() {
		if (file_exists('/tmp/gitdeployed')) $this->markTestSkipped("Somebody deployed to /tmp/?!");

		$err_log = FTLabs\ErrorLog::createFromEnvironment('error', "blah", array(
			'error' => array(
				'file'=>'/tmp/foo',
				'line'=>1,
				'errno'=>E_ERROR,
			),
		));
		$this->assertEquals('/tmp/foo', $err_log->getFile());
		$this->assertNull($err_log->getDeploymentInformation());
	}

	function testHTTPSafeTitle() {
		$bel = chr(7);
		$esc = chr(27);

		$log = FTLabs\ErrorLog::createFromErrorTree(array(
			'Error details' => array(
				'msg'=>"Javascript resource compilation via grunt failed{$bel}: {$esc}[4m\r\nRunning \"concurrent:build-all\" () task{$esc}[24m      {$esc}[4mRunning \"chalkboard\" task{$esc}[24m      {$esc}[32mDone, without errors.{$esc}[39m         {$esc}[33mWarning: {$esc}[4mRunning \"browserify:build\" () task{$esc}[24m     {$esc}[31m>> {$esc}[39mSyntaxError: Line 16: Unexpected token ) while parsing /home/Example.User/sandboxes/ft-app/lib/javascript/mftcomponents/home.js     {$esc}[33mWarning: Error running grunt-browserify.{$bel} Use --force to continue.{$esc}[39m      {$esc}[31mAborted due to warnings.{$esc}[39m{$bel} Use --force to continue.{$esc}[39m          {$esc}[31mAborted due to warnings.{$esc}[39m",
			),
		));

		$actual = $log->getHTTPSafeTitle();
		$this->assertNotContains($esc, $actual, "Title contains ESC control character");
		$this->assertNotContains($bel, $actual, "Title contains BEL control character");
		$this->assertNotContains("\r\n", $actual, "Title contains newline character");
		$this->assertEquals(100, strlen($actual), "Title not truncated to 100 characters");
	}

	function testSimplifiedHashtrace() {
		$one = FTLabs\ErrorLog::createFromErrorTree(array(
			'Error details' => array(
				'msg' => "928798375985732982" . str_repeat("Blah", 40) . "suffix that should be cut off",
			),
		));
		$two = FTLabs\ErrorLog::createFromErrorTree(array(
			'Error details' => array(
				'msg' => "167527632423" . str_repeat("Blah", 40) . "different suffix",
			),
		));
		$three = FTLabs\ErrorLog::createFromErrorTree(array(
			'Error details' => array(
				'msg' => "167527632423" . str_repeat("Meh", 40) . "different suffix",
			),
		));

		$this->assertEquals($one->getAggregationHash(), $two->getAggregationHash(), "First two should be the same - ignore numbers and text after 150 chars");
		$this->assertNotEquals($one->getAggregationHash(), $three->getAggregationHash(), "Third has substancialy different message");
	}
}
