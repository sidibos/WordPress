<?php
/**
 * Test for mysql results methods
 *
 * @codingstandard ftlabs-phpcs
 * @copyright The Financial Times Limited [All Rights Reserved]
 */

class RowObject {
	private $id;
	public $string;

	public function getArray() {
		return array($this->id, $this->string, $this->data);
	}
}

class resultTest extends PHPUnit_Framework_TestCase {

	private $db;
	private $testdata;

	protected function setUp() {
		if (set_error_handler(array('MySqlTestSuite', 'errorHandler')) === null) {
			throw new Exception('Unable to override error handler');
		}

		$this->db = new FTLabs\MySqlConnection($_SERVER["DB_HOST"], $_SERVER["DB_USER"], $_SERVER["DB_PASS"], $_SERVER["DB_NAME"]);
		$this->db->query('CREATE TEMPORARY TABLE testdata (`id` int AUTO_INCREMENT, `string` varchar(255), data text, PRIMARY KEY (id))');
		$this->testdata = array(array(1, 'apple', "איך קען עסן גלאָז און עס טוט מיר נישט װײ."), array(2, 'appleanna', "Ég get etið gler án þess að meiða mig."), array(3, 'bannana', "aɪ kæn iːt glɑːs ænd ɪt dɐz nɒt hɜːt miː"), array(4, 'banapple', " ⠊⠀⠉⠁⠝⠀⠑⠁⠞⠀⠛⠇⠁⠎⠎⠀⠁⠝⠙⠀⠊⠞⠀⠙⠕⠑⠎⠝⠞⠀⠓⠥⠗⠞⠀⠍⠑"));
		$this->db->query('INSERT INTO testdata VALUES %s|values', $this->testdata);
	}

	public function testGetSingle() {
		$result = $this->db->query("SELECT * FROM testdata ORDER BY id");
		$value = $result->getSingle(2);

		$this->assertEquals($this->testdata[0][2], $value);
	}

	public function testInsertId() {
		$inc_offset = $this->db->queryRow("SHOW VARIABLES LIKE %s", "auto_increment_offset");
		$auto_increment_increment = $this->db->querySingle("SELECT @@auto_increment_increment;");
		$result = $this->db->query("INSERT INTO testdata SET string = %s, data = %s", 'pomegranette', '私はガラスを食べられます。それは私を傷つけません。');
		$this->assertEquals($auto_increment_increment + $inc_offset['Value'], $result->getInsertId());
	}


	public function testAffectedRows() {
		$result = $this->db->query("UPDATE testdata SET string = %s WHERE id < %d", "gone off", "3");
		$this->assertEquals(2, $result->getAffectedRows());
	}

	public function testError() {
		$this->db->setErrorSuppression(true);
		$result = $this->db->query("SELECT * FROM testdata WHERE id is greater than %d", "3");

		$this->assertEquals(1064, $result->getErrorNo());
		$this->assertContains("You have an error in your SQL syntax", $result->getError());
		$this->assertContains("the right syntax to use near 'greater than 3' at line 1", $result->getError());
	}

	public function testIterator() {
		$result = $this->db->query("SELECT * FROM testdata ORDER BY id");
		foreach ($result as $key => $val) {
			$val = array_values($val);
			$this->assertEquals($this->testdata[$key], $val);
		}
	}

	public function testObjectCreation() {

		$result = $this->db->query("SELECT * FROM testdata ORDER BY id");
		$result->setReturnObject('RowObject');
		foreach ($result as $key => $val) {
			$this->assertEquals($this->testdata[$key], $val->getArray());
		}

	}

	protected function tearDown() {
		restore_error_handler();
	}
}
