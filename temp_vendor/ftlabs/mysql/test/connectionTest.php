<?php
/**
 * Test for connection
 *
 * @codingstandard ftlabs-phpcs
 * @copyright The Financial Times Limited [All Rights Reserved]
 */

use FTLabs\MySqlConnection;

class connectionTest extends PHPUnit_Framework_TestCase {

	private $db;

	public function test4ArgConnect() {
		$this->db = new MySqlConnection($_SERVER["DB_HOST"], $_SERVER["DB_USER"], $_SERVER["DB_PASS"], $_SERVER["DB_NAME"]);
		$result = $this->db->query("SELECT 1");
		$this->assertEquals(1, count($result));
	}

	public function test1ArgConnect() {
		$this->db = new MySqlConnection(array(
			MySqlConnection::PARAM_SERVER => $_SERVER['DB_HOST'],
		));
		$result = $this->db->query("SELECT 1");
		$this->assertEquals(1, count($result));
	}
}
