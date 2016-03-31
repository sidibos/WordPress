<?php
/**
 * Test for connection setter methods
 *
 * @codingstandard ftlabs-phpcs
 * @copyright The Financial Times Limited [All Rights Reserved]
 */


class settersTest extends PHPUnit_Framework_TestCase {

	private $db;

	protected function setUp() {
		if (set_error_handler(array('MySqlTestSuite', 'errorHandler')) === null) {
			throw new Exception('Unable to override error handler');
		}

		$this->db = new FTLabs\MySqlConnection($_SERVER["DB_HOST"], $_SERVER["DB_USER"], $_SERVER["DB_PASS"], $_SERVER["DB_NAME"]);
	}

	public function testChangeDatabase() {

		// Do a query to ensure a connection to the old database has been made
		$this->db->query('SELECT 1');
		$otherdb = $_SERVER["DB_NAME"]."_2";
		$this->db->changeDatabase($otherdb);
		$result = $this->db->querySingle("SELECT DATABASE();");
		$this->assertEquals($otherdb, $result);
	}
	public function testChangeDatabaseWithoutPriorConnection() {
		$otherdb = $_SERVER["DB_NAME"]."_2";
		$this->db->changeDatabase($otherdb);
		$result = $this->db->querySingle("SELECT DATABASE();");
		$this->assertEquals($otherdb, $result);
	}
	public function testChangeToUnkownDatabase() {

		// Do a query to ensure a connection to the old database has been made
		$this->db->query('SELECT 1');
		$otherdb = $_SERVER["DB_NAME"]."_giraffe";
		$this->setExpectedException('FTLabs\\Exception');
		$this->db->changeDatabase($otherdb);
	}

	public function testReconnectOnFail() {
		$this->db->setReconnectOnFail(true);

		// It's hard to make a failure, so for now, just make sure the function doesn't error
		$this->assertTrue(true);
	}

	public function testWithoutErrorSuppression() {
		$this->db->setErrorSuppression(false);
		$this->setExpectedException('FTLabs\\Exception');
		$this->db->query('CREATE TEMPORARY TABLE testdata (`string` varchar(255))');
		$this->db->query('INSERT INTO testdata VALUES %s|values', array('apple', 'appleanna', 'bannana', 'banapple'));
		$result = $this->db->query("SELECT * FROM testdata WHERE this is invalid sql", array('string' => array("apple", "banapple", "peach", "bannana")));
	}

	public function testWithErrorSuppression() {
		$this->db->setErrorSuppression(true);
		$this->db->query('CREATE TEMPORARY TABLE testdata (`string` varchar(255))');
		$this->db->query('INSERT INTO testdata VALUES %s|values', array('apple', 'appleanna', 'bannana', 'banapple'));
		$result = $this->db->query("SELECT * FROM testdata WHERE this is invalid sql", array('string' => array("apple", "banapple", "peach", "bannana")));
		$this->assertEquals(0, count($result));
	}

	protected function tearDown() {
		restore_error_handler();
	}
}
