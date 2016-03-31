<?php
/**
 * Tests that modify global state
 *
 * @codingstandard ftlabs-phpcs
 * @copyright The Financial Times Limited [All rights reserved]
 */

use \Psr\Log\LogLevel;

class LoggerGlobalTest extends PHPUnit_Framework_TestCase
{
	protected $backupGlobals = false;
	protected $backupStaticAttributes = false;
	protected $preserveGlobalState = false;
	protected $runTestInSeparateProcess = false; // Disabled, since CodeCoverage doesn't support Jenkins' Xdebug configuration

	static function setUpBeforeClass() {
		// PHPUnit's process isolation tries to preserve global state in a fundamentally flawed way, hence hacks
		require_once __DIR__.'/bootstrap.php';
	}

	function setUp() {
		$original = set_exception_handler(function(Exception $e){});
		restore_exception_handler();

		if (is_array($original) && $original[0] instanceof FTLabs\Logger) {
			$this->markTestIncomplete("Global Logger has already been set :(");
		}
	}

	/*
	 * @runInSeparateProcess
	 * @requires PHPUnit 3.7.32
	 * @preserveGlobalState disabled
	 */
	public function testBuffersHTTP() {
		$level = ob_get_level();
		$_SERVER['REQUEST_URI'] = '/fake';
		FTLabs\Logger::init();
		$this->assertEquals($level+1, ob_get_level());
	}

	/*
	 * @runInSeparateProcess
	 * @requires PHPUnit 3.7.32
	 * @preserveGlobalState disabled
	 */
	public function testDisableBuffersHTTP() {
		$level = ob_get_level();
		$_SERVER['REQUEST_URI'] = '/fake';
		FTLabs\Logger::init(FTLabs\Logger::NO_BUFFERING | FTLabs\Logger::PAGE_TYPE_TEXT);
		$this->assertEquals($level, ob_get_level());
	}

	/*
	 * @runInSeparateProcess
	 * @requires PHPUnit 3.7.32
	 * @preserveGlobalState disabled
	 */
	public function testDoesNotBufferCLI() {
		$level = ob_get_level();
		FTLabs\Logger::init();
		$this->assertEquals($level, ob_get_level());
	}

	/*
	 * @runInSeparateProcess
	 * @requires PHPUnit 3.7.32
	 * @preserveGlobalState disabled
	 */
	public function testInitDoesNotSetUpErrorHandler() {
		$original = set_error_handler(function($e){});
		restore_error_handler();

		FTLabs\Logger::init(FTLabs\Logger::NO_ERROR_HANDLER | FTLabs\Logger::PAGE_TYPE_HTML);

		$set = set_error_handler(function($e){});
		restore_error_handler();

		$this->assertEquals($original, $set);
	}

	/*
	 * @runInSeparateProcess
	 * @requires PHPUnit 3.7.32
	 * @preserveGlobalState disabled
	 */
	public function testInitSetsUpErrorHandler() {

		// PHPUnit sets its own, so mere check for any error handler is not enough
		$original = set_error_handler(function($e){});
		restore_error_handler();

		FTLabs\Logger::init();

		$set = set_error_handler(function($e){});
		restore_error_handler();

		$this->assertNotNull($set);

		$this->assertNotEquals($original, $set, "Expected Logger:init() to change global error handler");
	}

	/*
	 * @runInSeparateProcess
	 * @requires PHPUnit 3.7.32
	 * @preserveGlobalState disabled
	 */
	public function testInitSetsUpExceptionHandler() {
		$original = set_exception_handler(function(Exception $e){});
		restore_exception_handler();

		FTLabs\Logger::init();

		$set = set_exception_handler(function(Exception $e){});
		restore_exception_handler();

		$this->assertNotEquals($original, $set, "Expected Logger::init() to change the default exception handler");
	}
}
