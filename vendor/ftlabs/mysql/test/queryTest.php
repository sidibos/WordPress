<?php
/**
 * Test for connection::query
 *
 * @codingstandard ftlabs-phpcs
 * @copyright The Financial Times Limited [All Rights Reserved]
 */


class queryTest extends PHPUnit_Framework_TestCase {

	private $db;

	protected function setUp() {
		if (set_error_handler(array('MySqlTestSuite', 'errorHandler')) === null) {
			throw new Exception('Unable to override error handler');
		}

		$this->db = new FTLabs\MySqlConnection($_SERVER["DB_HOST"], $_SERVER["DB_USER"], $_SERVER["DB_PASS"], $_SERVER["DB_NAME"]);
	}

	/**
	 * @expectedException FTLabs\MySqlConnectionException
	 * @expectedExceptionMessage server 'badhostname.invalid'
	 */
	public function testFailedConnection() {
		$prev = error_reporting(0);
		try {
			$db = new FTLabs\MySqlConnection("badhostname.invalid", "nope", "nope", "nope");
			$db->query("SELECT 1");
		} catch(\Exception $e) {
			error_reporting($prev);
			throw $e;
		}
		error_reporting($prev);
	}

	public function testSimpleQuery() {
		$result = $this->db->query("SELECT 1");
		$this->assertEquals(1, count($result));
	}

	public function testSprintf() {
		$this->db->query('CREATE TEMPORARY TABLE testdata (`string` varchar(255), `num` int(21))');
		$this->db->query('INSERT INTO testdata VALUES %s|values', array(array('string', 7), array('more', '8'), array('string', 'apple'),  array('string', 6.3), ));
		$result = $this->db->query("SELECT * FROM testdata WHERE `string` = %s AND `num` > %d", 'string', 5);
		$this->assertEquals(2, count($result));
	}
	public function testByKey() {
		$this->db->query('CREATE TEMPORARY TABLE testdata (`string` varchar(255), `num` int(21))');
		$this->db->query('INSERT INTO testdata VALUES %s|values', array(array('string', 7), array('more', '8'), array('string', 'apple'),  array('string', 6.3), ));
		$result = $this->db->query("SELECT * FROM testdata WHERE {string} AND {num|>}", array('string' => "string", 'num' => 5));
		$this->assertEquals(2, count($result));
	}
	public function testDateModifier() {
		$this->db->query('CREATE TEMPORARY TABLE testdata (a datetime)');
		$this->db->query('INSERT INTO testdata VALUES %s|values_date', array('today', 'tomorrow', 'last wednesday', '1984'));
		$result = $this->db->query("SELECT * FROM testdata WHERE {a|date}", array('a' => date('Y-m-d')));
		$this->assertEquals(1, count($result));
	}
	public function testDateModifierWithInvalidDateInsertsNull() {
		$datetime = 'apples and pears';
		$this->db->query('CREATE TEMPORARY TABLE testdata (a datetime)');
		$this->db->query('INSERT INTO testdata SET a = %s|date', $datetime);
		$result = $this->db->query("SELECT * FROM testdata WHERE {a|isnull}", array('a' => null));
		$this->assertEquals(1, count($result));
	}
	public function testUTCDateModifier() {
		$datetime = new DateTime("17-Mar-2009 16:43:13", new DateTimezone('Pacific/Tongatapu'));
		$this->db->query('CREATE TEMPORARY TABLE testdata (a datetime)');
		$this->db->query('INSERT INTO testdata SET a = %s|utcdate', $datetime);
		$datetime->setTimezone(new DateTimezone('UTC'));
		$result = $this->db->querySingle("SELECT a FROM testdata LIMIT 1");
		$this->assertEquals($datetime->format("Y-m-d H:i:s"), $result);
	}
	public function testLikeModifier() {
		$this->db->query('CREATE TEMPORARY TABLE testdata (`string` varchar(255))');
		$this->db->query('INSERT INTO testdata VALUES %s|values', array('app_le', 'app_leanna', 'bannana', 'banapp_le', 'appple'));
		$result = $this->db->query("SELECT * FROM testdata WHERE {string|like}", array('string' => "app_le"));
		$this->assertEquals(3, count($result));
	}
	public function test01Modifier() {
		$this->db->query('CREATE TEMPORARY TABLE testdata (a tinyint)');
		$this->db->query('INSERT INTO testdata VALUES %d|values_01', array(0, 1, 0.5, 2, 3, false, true, 'apple'));
		$result = $this->db->query("SELECT * FROM testdata WHERE {a|01}", array('a' => true));
		$this->assertEquals(6, count($result));
		$result = $this->db->query("SELECT * FROM testdata WHERE {a|01}", array('a' => false));
		$this->assertEquals(2, count($result));
	}
	public function testValuesModifier() {
		$this->db->query('CREATE TEMPORARY TABLE testdata (a varchar(255), b int)');
		$values = array(array("apple", 1), array("orange", '3'), array("peach", '70'));
		$this->db->query('INSERT INTO testdata VALUES %s|values', $values);
		$result = $this->db->query("SELECT * FROM testdata");
		$this->assertEquals(count($values), count($result));
	}
	public function testNoKeyModifier() {
		$this->db->query('CREATE TEMPORARY TABLE testdata (a int)');
		$this->db->query('INSERT INTO testdata VALUES %d|values', array(0, 1, 5.5, 2, 3, false, true, 'apple'));
		$result = $this->db->query("SELECT * FROM testdata WHERE a > {bee|nokey}", array('bee' => 1));
		$this->assertEquals(3, count($result));
	}
	public function testIsNullModifier() {
		$this->db->query('CREATE TEMPORARY TABLE testdata (`string` varchar(255))');
		$this->db->query('INSERT INTO testdata VALUES %s|values', array('apple', array(null), array(null), 'banapple'));
		foreach (array(1 => 'apple', 2 => null) as $count => $val) {
			$result = $this->db->query("SELECT * FROM testdata WHERE {string|isnull}", array('string' => $val));
			$this->assertEquals($count, count($result));
		}
	}
	public function testListModifier() {
		$this->db->query('CREATE TEMPORARY TABLE testdata (`string` varchar(255))');
		$this->db->query('INSERT INTO testdata VALUES %s|values', array('apple', 'appleanna', 'bannana', 'banapple'));
		$result = $this->db->query("SELECT * FROM testdata WHERE {string|list}", array('string' => array("apple", "banapple", "peach", "bannana")));
		$this->assertEquals(3, count($result));
	}
	public function testToStringModifier() {
		$hexval = $this->db->querySingle("SELECT HEX({h|nokey})", array('h' => 2));
		$this->assertEquals(2, $hexval);
		$hexval = $this->db->querySingle("SELECT HEX({h|nokey_tostring})", array('h' => 2));
		$this->assertEquals(32, $hexval);
		$value = $this->db->querySingle("SELECT {h|nokey_tostring}", array('h' => null));
		$this->assertNull($value);
	}
	public function testOperatorChangeModifiers() {
		$this->db->query('CREATE TEMPORARY TABLE testdata (a int)');
		$this->db->query('INSERT INTO testdata VALUES %d|values', array(0, 1, 2, 3, 4, 5, 6, 7, 8, 9));
		foreach (array('not' => 9, '<' => 5, '>' => 4, '<=' => 6, '>=' => 5) as $val => $count) {
			$result = $this->db->query("SELECT * FROM testdata WHERE {a|$val}", array('a' => 5));
			$this->assertEquals($count, count($result));
		}
	}
	public function testMultipleModifiers() {
		$this->db->query('CREATE TEMPORARY TABLE testdata (a datetime)');
		$this->db->query('INSERT INTO testdata VALUES %s|values_date', array('today', 'tomorrow', 'last wednesday', '1984', array(null), 'pear'));
		foreach (array(1 => date('Y-m-d'), 2 => null) as $count => $val) {
			$result = $this->db->query("SELECT * FROM testdata WHERE {a|date_isnull}", array('a' => $val));
			$this->assertEquals($count, count($result));
		}
	}
	public function testModifiersAreCaseInsensitive() {
		$this->db->query('CREATE TEMPORARY TABLE testdata (a int)');
		$this->db->query('INSERT INTO testdata VALUES %d|values', array(0, 1, 5.5, 2, 3, false, true, array(null), 'apple'));
		$result = $this->db->query("SELECT * FROM testdata WHERE a > {bee|noKey}", array('bee' => 1));
		$this->assertEquals(3, count($result));
		$result = $this->db->query("SELECT * FROM testdata WHERE {a|isNull}", array('a' => null));
		$this->assertEquals(1, count($result));
	}
	public function testInvalidModifierThrowsException() {
		$this->setExpectedException('PHPUnit_Framework_Error_Notice');
		$result = $this->db->query("SELECT * FROM testdata WHERE %s|unknownmodifier", 'seven');
	}
	public function testQuoteCharThrowsException() {

		// This actually triggers an E_USER_DEPRECATED error, but phpunit 3.5.13 doesn't handle these separately so just converts it to a PHPUnit_Framework_Error Exception, future verisons may change.
		$this->setExpectedException('PHPUnit_Framework_Error');
		$result = $this->db->query("SELECT * FROM testdata WHERE a = 'seven'");
	}

	protected function tearDown() {
		restore_error_handler();
	}
}
