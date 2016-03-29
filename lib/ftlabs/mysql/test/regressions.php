<?php

/**
 * Regression tests, ported from v3, but altered to work the new v4 interfaces
 *
 * @copyright The Financial Times Limited [All Rights Reserved]
 */

class mysqlconnectionRegressionTest extends PHPUnit_Framework_TestCase {

	private $db;

	protected function setUp() {
		if (set_error_handler(array('MySqlTestSuite', 'errorHandler')) === null) {
			throw new Exception('Unable to override error handler');
		}

		$this->db = new FTLabs\MySqlConnection($_SERVER["DB_HOST"], $_SERVER["DB_USER"], $_SERVER["DB_PASS"], $_SERVER["DB_NAME"]);
	}

	/**
	 * Quick check that the class exists and is in a position to operate
	 */
	public function testLoad() {

		// Ensure class is defined
		$this->assertTrue(class_exists("FTLabs\MySqlConnection"));

		$this->assertInstanceOf("FTLabs\MySqlConnection", $this->db);
	}

	/**
	 * Queries containing legitimate % signs should work
	 */
	public function testPercentSigns() {

		$mysqlstring = "%d %b %Y";
		$phpstring = "d M Y";

		// Check that the 'correct' way of inserting this string works:
		$mysqlop = $this->db->querySingle("select date_format(now(),%s)", $mysqlstring);
		$phpop = date($phpstring);
		$this->assertEquals($mysqlop, $phpop);

	}

	/**
	 * Ensure field names are escaped appropriately, so that restricted words can be used
	 */
	public function testRestrictedWordsAsFieldNames() {

		// This was fixed by revision 2185.

		// Create and populate a temporary table:
		$this->db->query('CREATE TEMPORARY TABLE restrictedfieldnames (`from` int)');
		$this->db->query('INSERT INTO restrictedfieldnames SET `from`=4');

		// Confirm that it can be queried:
		$numrows = $this->db->querySingle('SELECT COUNT(*) FROM restrictedfieldnames WHERE {from}', array('from'=>4));
		$this->assertEquals($numrows, 1);
	}

	/**
	 * Ensure %d style placeholders can be used after > or < comparisons
	 */
	public function testSprintfSyntaxAfterComparisons() {

		// This bug is fixed in 2196.

		// Create and populate a temporary table:
		$this->db->query('CREATE TEMPORARY TABLE comparisons (a int, b int)');
		$this->db->query('INSERT INTO comparisons VALUES (1,2), (3,4), (5,6), (7,8), (9,10)');

		// Confirm the expected number of rows are present:
		$numrows = $this->db->querySingle('SELECT COUNT(*) FROM comparisons');
		$this->assertEquals($numrows, 5);

		// Check the various comparisons work as required:
		$this->assertEquals(2, $this->db->querySingle('SELECT COUNT(*) FROM comparisons WHERE a<%d', 5));
		$this->assertEquals(2, $this->db->querySingle('SELECT COUNT(*) FROM comparisons WHERE a <%d', 5));
		$this->assertEquals(2, $this->db->querySingle('SELECT COUNT(*) FROM comparisons WHERE a< %d', 5));
		$this->assertEquals(2, $this->db->querySingle('SELECT COUNT(*) FROM comparisons WHERE a < %d', 5));
		$this->assertEquals(3, $this->db->querySingle('SELECT COUNT(*) FROM comparisons WHERE a <= %d', 5));
		$this->assertEquals(3, $this->db->querySingle('SELECT COUNT(*) FROM comparisons WHERE a <=%d', 5));
		$this->assertEquals(3, $this->db->querySingle('SELECT COUNT(*) FROM comparisons WHERE a<= %d', 5));
		$this->assertEquals(3, $this->db->querySingle('SELECT COUNT(*) FROM comparisons WHERE a<=%d', 5));

		$this->assertEquals(2, $this->db->querySingle('SELECT COUNT(*) FROM comparisons WHERE a>%d', 5));
		$this->assertEquals(2, $this->db->querySingle('SELECT COUNT(*) FROM comparisons WHERE a >%d', 5));
		$this->assertEquals(2, $this->db->querySingle('SELECT COUNT(*) FROM comparisons WHERE a> %d', 5));
		$this->assertEquals(2, $this->db->querySingle('SELECT COUNT(*) FROM comparisons WHERE a > %d', 5));
		$this->assertEquals(3, $this->db->querySingle('SELECT COUNT(*) FROM comparisons WHERE a >= %d', 5));
		$this->assertEquals(3, $this->db->querySingle('SELECT COUNT(*) FROM comparisons WHERE a >=%d', 5));
		$this->assertEquals(3, $this->db->querySingle('SELECT COUNT(*) FROM comparisons WHERE a>= %d', 5));
		$this->assertEquals(3, $this->db->querySingle('SELECT COUNT(*) FROM comparisons WHERE a>=%d', 5));
	}

	public function testSprintFSyntaxCorrectlyParsingIntegersAndStrings() {

		// Test parsing integers
		$hexval = $this->db->querySingle("SELECT HEX(%d)", 2);
		$this->assertEquals($hexval, 2);
		$hexval = $this->db->querySingle("SELECT HEX(%s)", 2);
		$this->assertEquals($hexval, 32);

		// Test parsing strings
		$hexval = $this->db->querySingle("SELECT HEX(%d)", '2');
		$this->assertEquals($hexval, 2);
		$hexval = $this->db->querySingle("SELECT HEX(%s)", '2');
		$this->assertEquals($hexval, 32);

		// Test inserting non-numeric string into a numeric placeholder
		$val = $this->db->querySingle("SELECT %d", "test");
		$this->assertTrue($val === "0");
	}

	public function testListModifier() {

		// This bug arose in revision 2195 and resolved in ....

		// Create and populate a temporary table:
		$this->db->query('CREATE TEMPORARY TABLE listmodifierdata (a int, b int)');
		$this->db->query('INSERT INTO listmodifierdata VALUES (1,2), (3,4), (5,6), (7,8), (9,10)');

		// Confirm the expected number of rows are present:
		$numrows = $this->db->querySingle('SELECT COUNT(*) FROM listmodifierdata');
		$this->assertEquals($numrows, 5);

		// Check the list modifier works as required:
		$numrows = $this->db->querySingle('SELECT COUNT(*) FROM listmodifierdata WHERE a IN %d|list AND b > %d', array(1, 2, '3', '4'), 0);
		$this->assertEquals($numrows, 2);
		$numrows = $this->db->querySingle('SELECT COUNT(*) FROM listmodifierdata WHERE a IN {firstcolumn|nokey_list} AND b > {secondcolumn|nokey}', array("firstcolumn" => array(1, 2, '3', '4'), "secondcolumn" => 0));
		$this->assertEquals($numrows, 2);


		/* Test error triggered when an array is used as as parameter, when the
		   placeholder does not have the list modifier */

		// By-key mode
		$e = false;
		try {
			$this->db->query('CREATE TEMPORARY TABLE test (a char(3))');
			$this->db->query('INSERT INTO test SET {a}', array('a' => array(1, 2, 3)));
		} catch (Exception $e) {}
		$this->assertFalse($e === false);

		// Sprintf mode
		$e = false;
		try {
			$this->db->query('INSERT INTO test SET a=%d, b=%d', 1, array(1, 2, 3, "foo", "blah"));
		} catch (Exception $e) {}
		$this->assertFalse($e === false);
	}

	public function testDateModifiers() {

		// This bug appears in revision 2185 and is fixed in 2193.

		// Create and populate a temporary table.  Store that the values were correctly stored.
		$this->db->query('CREATE TEMPORARY TABLE datemodifiers (`id` int, `datecol` datetime)');
		$id = 1;
		$this->db->query('INSERT INTO datemodifiers SET id='.$id.', {datecol|date}', array('datecol'=>'today'));
		$storeddate = $this->db->querySingle('SELECT datecol FROM datemodifiers WHERE id='.$id++);
		$this->assertEquals($storeddate, date('Y-m-d 00:00:00'));
		$this->db->query('INSERT INTO datemodifiers SET id='.$id.', datecol={testkey|nokey_date}', array('testkey'=>'today'));
		$storeddate = $this->db->querySingle('SELECT datecol FROM datemodifiers WHERE id='.$id++);
		$this->assertEquals($storeddate, date('Y-m-d 00:00:00'));

		// Repeat with sprintf syntax
		$this->db->query('INSERT INTO datemodifiers SET id='.$id.', datecol=%s|date', 'today');
		$storeddate = $this->db->querySingle('SELECT datecol FROM datemodifiers WHERE id='.$id++);
		$this->assertEquals($storeddate, date('Y-m-d 00:00:00'));

		// Repeat with null date
		$this->db->query('INSERT INTO datemodifiers SET id='.$id.', datecol=%s|date', null);
		$storeddate = $this->db->querySingle('SELECT datecol FROM datemodifiers WHERE id='.$id++);
		$this->assertNull($storeddate);
		$this->db->query('INSERT INTO datemodifiers SET id='.$id.', {datecol|date}', array('datecol' => 'today'));
		$storeddate = $this->db->querySingle('SELECT datecol FROM datemodifiers WHERE id='.$id++);
		$this->db->query('INSERT INTO datemodifiers SET id='.$id.', datecol={testkey|nokey_date}', array('testkey' => 'today'));
		$storeddate = $this->db->querySingle('SELECT datecol FROM datemodifiers WHERE id='.$id++);
	}

	public function testOutstandingFaultWhenParsingSprintf() {

		// Create and populate a temporary table:
		$this->db->query('CREATE TEMPORARY TABLE listmodifierdata (a int, b int)');
		$this->db->query('INSERT INTO listmodifierdata VALUES (1,2), (3,4), (5,6), (7,8), (9,10)');

		// Confirm the expected number of rows are present:
		$numrows = $this->db->querySingle('SELECT COUNT(*) FROM listmodifierdata');
		$this->assertEquals($numrows, 5);

		// Ensure that the DB connection class interprets arguments correctly
		$numrows = $this->db->querySingle('SELECT COUNT(*) FROM listmodifierdata WHERE a IN %d|list', array(1, 2, '3', '4'));
		$this->assertEquals($numrows, 2);
	}

	public function testOutstandingFaultUserEnteredPercentInLikeQuery() {

		// If a user-entered input is "aa%bb", then piped to |like, the mysqlconnectionclass would match aa.*bb not aa\%bb.  This isn't a major
		// problem, as it also matches aa%bb, it's just the user will see additional (unexpected) rows.

		// Create and populate a temporary table:
		$this->db->query('CREATE TEMPORARY TABLE percentinlikedata (`string` varchar(255))');
		$this->db->query('INSERT INTO percentinlikedata VALUES %s|values', array('bb%aa', 'aa%bb', 'aa%%bb', 'aacbb', 'aa%bb%cc'));
		$numrows = $this->db->querySingle('SELECT COUNT(*) FROM percentinlikedata WHERE `string` LIKE %s|like', 'aa%bb');
		$this->assertEquals(2, $numrows);
	}

	protected function tearDown() {
		restore_error_handler();
	}
}
