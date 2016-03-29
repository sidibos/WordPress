<?php
/**
 * Test for query* methods on the connection class
 *
 * @codingstandard ftlabs-phpcs
 * @copyright The Financial Times Limited [All Rights Reserved]
 */

class shortcutMethodsTest extends PHPUnit_Framework_TestCase {

	private $db;
	private $testdata;

	protected function setUp() {
		if (set_error_handler(array('MySqlTestSuite', 'errorHandler')) === null) {
			throw new Exception('Unable to override error handler');
		}

		$this->db = new FTLabs\MySqlConnection($_SERVER["DB_HOST"], $_SERVER["DB_USER"], $_SERVER["DB_PASS"], $_SERVER["DB_NAME"]);
		$this->db->query('CREATE TEMPORARY TABLE testdata (`id` int, `string` varchar(255), data text)');
		$this->testdata = array(
			array(0, 'peach', "Mý a yl dybry gwéder hag éf ny wra ow ankenya."),
			array(1, 'apple', "איך קען עסן גלאָז און עס טוט מיר נישט װײ."),
			array(2, 'appleanna', "Ég get etið gler án þess að meiða mig."),
			array(3, 'bannana', "aɪ kæn iːt glɑːs ænd ɪt dɐz nɒt hɜːt miː"),
			array(4, 'banapple', " ⠊⠀⠉⠁⠝⠀⠑⠁⠞⠀⠛⠇⠁⠎⠎⠀⠁⠝⠙⠀⠊⠞⠀⠙⠕⠑⠎⠝⠞⠀⠓⠥⠗⠞⠀⠍⠑"),
		);
		$this->testdatacsv = <<<CSV
"id","string","data"
"0","peach","Mý a yl dybry gwéder hag éf ny wra ow ankenya."
"1","apple","איך קען עסן גלאָז און עס טוט מיר נישט װײ."
"2","appleanna","Ég get etið gler án þess að meiða mig."
"3","bannana","aɪ kæn iːt glɑːs ænd ɪt dɐz nɒt hɜːt miː"
"4","banapple"," ⠊⠀⠉⠁⠝⠀⠑⠁⠞⠀⠛⠇⠁⠎⠎⠀⠁⠝⠙⠀⠊⠞⠀⠙⠕⠑⠎⠝⠞⠀⠓⠥⠗⠞⠀⠍⠑"

CSV;
		$this->db->query('INSERT INTO testdata VALUES %s|values', $this->testdata);
	}

	public function testQueryCrosstab() {
		$result = $this->db->queryCrosstab("SELECT `id` AS y, string AS x, data FROM testdata");
		foreach ($this->testdata as $row) {
			$this->assertEquals($row[2], $result[$row[0]][$row[1]]);
		}
	}
	public function testQueryRow() {
		foreach ($this->testdata as $row) {
			$result = $this->db->queryRow("SELECT * FROM testdata WHERE id = %d", $row[0]);
			$this->assertEquals($row, array_values($result));
		}

		$nullresult = $this->db->queryRow("SELECT * FROM testdata WHERE id = %d", count($this->testdata));
		$this->assertNull($nullresult, "Result didn't return null");
	}
	public function testQuerySingle() {
		foreach ($this->testdata as $row) {
			$result = $this->db->querySingle("SELECT string FROM testdata WHERE id = %d", $row[0]);
			$this->assertEquals($row[1], $result);
		}
	}
	public function testQueryAllRows() {
		$result = $this->db->queryAllRows("SELECT * FROM testdata ORDER BY id");
		foreach ($result as &$row) $row = array_values($row);
		$this->assertEquals($this->testdata, $result);

	}
	public function testQueryLookupTable() {
		$result = $this->db->queryLookupTable("SELECT `id` AS k, `string` as v FROM testdata");
		foreach ($this->testdata as $row) {
			$this->assertEquals($row[1], $result[$row[0]]);
		}
	}
	public function testQueryList() {
		$result = $this->db->queryList("SELECT id FROM testdata ORDER BY id");
		foreach ($this->testdata as $row) $exp[] = $row[0];
		$this->assertEquals($exp, array_keys($result));
	}
	public function testQueryCSV() {
		$csv = $this->db->queryCSV("SELECT * FROM testdata ORDER BY id");
		$this->assertEquals($this->testdatacsv, $csv);
	}

	protected function tearDown() {
		restore_error_handler();
	}
}
