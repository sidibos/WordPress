<?php
/**
 * Test for connection querylogging functions
 *
 * @codingstandard ftlabs-phpcs
 * @copyright The Financial Times Limited [All Rights Reserved]
 */


class queryLogTest extends PHPUnit_Framework_TestCase {

	private $db;

	protected function setUp() {
		if (set_error_handler(array('MySqlTestSuite', 'errorHandler')) === null) {
			throw new Exception('Unable to override error handler');
		}

		$this->db = new FTLabs\MySqlConnection($_SERVER["DB_HOST"], $_SERVER["DB_USER"], $_SERVER["DB_PASS"], $_SERVER["DB_NAME"]);
	}

	public function testQueryLogging() {

		// Counter for counting number of queries
		$ii = 0;

		$this->db->setQueryLogging(true);
		$this->db->query("SELECT $ii");
		$ii++;
		$this->db->query("SELECT $ii");
		$ii++;
		$this->db->setQueryLogging(false);
		$this->db->query("SELECT $ii");
		$ii++;
		$this->assertEquals(2, count($this->db->getQueryLog()));
		$this->db->clearQueryLog();
		$this->assertEquals(0, count($this->db->getQueryLog()));
		$this->db->setQueryLogging(true);
		$queryExpr = "SELECT {$ii}";
		$this->db->query($queryExpr);
		$ii++;
		$log = $this->db->getQueryLog();
		$this->assertEquals($queryExpr, $log[0]['queryExpr']);

		// The Query should count all  queries, irrespective of logging
		$this->assertEquals($ii, $this->db->getQueryCount());
	}

	protected function tearDown() {
		restore_error_handler();
	}
}
