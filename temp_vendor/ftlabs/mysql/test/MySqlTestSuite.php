<?php
/**
 * Sets up the test suite for the MySQL tests.
 *
 * @copyright The Financial Times Limited [All rights reserved]
 */
require_once __DIR__.'/../vendor/autoload.php';

class MySqlTestSuite extends PHPUnit_Framework_TestSuite {

	private $conn;

	public static function errorHandler($errno, $errstr, $errfile, $errline) {
		if ($errno === E_USER_WARNING && strpos($errstr, '(will try again)') !== false) {
			return true;
		} else {
			return call_user_func(array('PHPUnit_Util_ErrorHandler', 'handleError'), $errno, $errstr, $errfile, $errline);
		}
	}

	public static function suite() {
		$suite = new MySqlTestSuite();

		$suite->addTestFiles(array(
			__DIR__.'/connectionTest.php',
			__DIR__.'/queryLogTest.php',
			__DIR__.'/queryTest.php',
			__DIR__.'/regressions.php',
			__DIR__.'/resultTest.php',
			__DIR__.'/settersTest.php',
			__DIR__.'/shortcutMethodsTest.php',
		));

		return $suite;
	}

	protected function setUp() {
		if (empty($_SERVER['DB_HOST'])) $_SERVER['DB_HOST'] = 'dbmaster';
		if (empty($_SERVER['DB_USER'])) $_SERVER['DB_USER'] = 'test';
		if (empty($_SERVER['DB_PASS'])) $_SERVER['DB_PASS'] = 'test';
		$_SERVER['DB_NAME'] = 'test_core_mysqlv4';

		$this->conn = mysql_connect($_SERVER['DB_HOST'], $_SERVER['DB_USER'], $_SERVER['DB_PASS'], true, 128);
		if (!$this->conn) throw new Exception("Can't connect to test server");

		// REVIEW:LB:20130409: This should be updated to use mysqli as mysql_* is deprecated
		mysql_select_db($_SERVER['DB_NAME'], $this->conn);
		mysql_query("DROP DATABASE IF EXISTS {$_SERVER['DB_NAME']}", $this->conn);
		mysql_query("CREATE DATABASE {$_SERVER['DB_NAME']} CHARACTER SET 'utf8' COLLATE 'utf8_bin'", $this->conn);

		// Include a second db for database switching tests
		mysql_query("DROP DATABASE IF EXISTS {$_SERVER['DB_NAME']}_2", $this->conn);
		mysql_query("CREATE DATABASE {$_SERVER['DB_NAME']}_2 CHARACTER SET 'utf8' COLLATE 'utf8_bin'", $this->conn);
	}

	protected function tearDown() {

		// REVIEW:LB:20130409: This should be updated to use mysqli as mysql_* is deprecated
		mysql_query("DROP DATABASE {$_SERVER['DB_NAME']}", $this->conn);
		mysql_query("DROP DATABASE {$_SERVER['DB_NAME']}_2", $this->conn);
	}
}
