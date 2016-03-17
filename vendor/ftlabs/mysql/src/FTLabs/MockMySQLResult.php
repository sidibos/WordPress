<?php

/**
 * Use PDO to Mock an Assanka MySQL Database
 *
 * @copyright The Financial Times Limited [All Rights Reserved]
 */

namespace FTLabs;

/**
 * A complete Mock of the FTLabs\MySqlResult type.  This can be used directly with the connection class, however, it makes more sense to pass results to consumers than have an object make a query, and then process it.  This means we can mock query results and pass the results to the object that 'does stuff' with the data.
 *
 * But for those who don't like separation of concerns @see MockMySqlConnectionV4.
 *
 * To use:
 *
 * ```PHP
 *
 * $mysqlResult = new MockMySqlResultV4(array(
 *          array(1, "field_1_data", "field_2_data", "field_3_data"),
 *          array(2, "field_1_data", "field_2_data", "field_3_data"),
 *          array(3, "field_1_data", "field_2_data", "field_3_data")
 *      ), array(
 *          "affectedRows" => integer_number_of_rows_query_affected,
 *          "insertId"	   => integer_id_of_inserted_value_if_insert,
 *          "errorNo"	   => integer_mysql_error_number,
 *          "errorMsg"	   => string_mysql_error_message,
 *          "queryExpr"	   => string_query_expression,
 *          "timeTaken"    => 0.7731,				// Microseconds
 *          "dateExecuted" => 1360167008.7731   // PHP microtime(true)
 *      ));
 *
 * ```
 *
 * The first element should be an array of arrays.  The arrays that are contained in the array should represent records.
 *
 * If you need to use MySqlResultV4#setReturnObject you need to specify the names of the columns as well as a numeric index:
 *
 * ```PHP
 * $mysqlResult = new MockMySqlResultV4(array(
 *          array(
 *               0 => 1, 1 => "field_1_data", 2 => "field_2_data", 3 => "field_3_data",
 *               "id" => 1, "field_1" => "field_1_data", "field_2" => "field_2_data", "field_3" => "field_3_data"
 *          	),
 *          array(
 *               0 => 2, 1 => "field_1_data", 2 => "field_2_data", 3 => "field_3_data",
 *               "id" => 2, "field_1" => "field_1_data", "field_2" => "field_2_data", "field_3" => "field_3_data"
 *          	),
 *          array(
 *               0 => 3, 1 => "field_1_data", 2 => "field_2_data", 3 => "field_3_data",
 *               "id" => 3, "field_1" => "field_1_data", "field_2" => "field_2_data", "field_3" => "field_3_data"
 *          	)
 *      ), array(
 *          "affectedRows" => 3,              // Integer_number_of_rows_query_affected
 *          "insertId"	   => 0,              // Integer_id_of_inserted_value_if_insert (0 if none: http://www.php.net/manual/en/mysqli.insert-id.php)
 *          "errorNo"	   => 0,              // Integer_mysql_error_number,
 *          "errorMsg"	   => "",             // string_mysql_error_message,
 *          "queryExpr"	   => "SELECT * FROM test_db LIMIT 3;", //string_query_expression
 *          "timeTaken"    => 0.7731,				// Microseconds
 *          "dateExecuted" => 1360167008.7731   // PHP microtime(true)
 *      ));
 *
 * ```
 */
class MockMySqlResult extends MySqlResult {

	/**
	 * @var array The Result data.
	 */
	private $_data;

	public function __construct($resultObject, array $resultDetails) {

		// The resultobject that the real MySqlResult expects is a 'mysqli_result' type.  (Which is also an Iterator).
		// COMPLEX:SG:20130206: We are only mocking the Result object in cases where it interacts directly with the resultobject.  Unfortunately the MySqlResult relies heavily on the fact that $resultObject is a 'mysqli_result' type.  Here we pass in null to the parent constructor so that an error is thrown if we try and access the results from the parent type here.
		parent::__construct(null, $resultDetails);

		$this->_data = $resultObject;
	}

	function rewind() {
	 	$this->currentKey = -1;
	 	$this->next();
	}

	function current() {
		return $this->current;
	}

	function key() {
		return $this->currentKey;
	}

	function next() {
		$this->currentKey++;

		if (isset($this->_data[$this->currentKey])) {
			$this->current = $this->_data[$this->currentKey];
		} else {
			$this->current = false;
		}
	}

	function valid() {
		return isset($this->_data[$this->currentKey]);
	}

	function count() {
		return count($this->_data);
	}

	/**
	 * Fetch a single 'cell' of data from the first row returned from a query
	 *
	 * @param integer $columnoffset Index of column to fetch, where 0 is the leftmost column. Optional, defaults to 0.
	 * @return array A row of data as key/value pairs
	 */
	public function getSingle($columnoffset = 0) {
		if (!count($this)) return null;

		// The method getSingle has some possibly unexpected side effects that should be investigated.  In the implemetation.  The internal pointers are not advanced.
		$this->next();
		$values = array_values($this->current());
		return $values[$columnoffset];
	}

	/**
	 * Return all results as a numeric array of rows, each row an associative array
	 *
	 * Entirre resultset is loaded into memory - use only on small resultsets
	 *
	 * @return array Array containing one element per row in the resultset
	 */
	public function getAllRows() {
		return $this->_data;
	}


}
