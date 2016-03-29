<?php

/**
 * Use PDO to Mock an Assanka MySQL Database
 *
 * @copyright The Financial Times Limited [All Rights Reserved]
 */

namespace FTLabs;

use FTLabs\Exception;

class MockMySQLConnection extends MySqlConnection {
	private $_db;

	public function __construct(\PDO $pdo = null) {
		if ($pdo === null) {
			$this->_db = new \PDO('sqlite::memory:');

		} else {
			$this->_db = $pdo;
		}

		// Throw Exception
		$this->_db->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
	}

	protected function isConnected() {
		return true;
	}

	protected function sqlenc($val) {
		return $val;
	}

	public function rawQuery($query) {
		$output = $this->_db->query($query);

		if ($this->_db->errorCode() != '00000') {
			throw new Exception($query . print_r($output, 1) . print_r($this->_db->errorInfo(), true));
		}
		return $output;
	}

	/**
	 * Mirrors the internal runQuery, the internal runQuery is private, therefore it is not overridden.
	 */
	protected function runQuery($queryExpr) {
		assert('$queryExpr instanceof PDOStatement');
		$queryExpr->execute();

		if (stristr($queryExpr->queryString, "insert") === FALSE) {
			return $queryExpr->fetchAll(\PDO::FETCH_ASSOC);
		}

		return $this->_db->lastInsertId();
	}

	public function query() {

		$queryExpr = call_user_func_array(array($this, 'parse'), func_get_args());

		if (empty($queryExpr)) {

			// TODO:SG:20130206: This is directly copied from the connection class.  Really this should be abstracted?
			// Attempt to determine why the query is empty.
			switch (preg_last_error()) {
				case PREG_NO_ERROR:
					$error = "No PREG error.";
					break;
				case PREG_INTERNAL_ERROR:
					$error = "Internal PREG error.";
					break;
				case PREG_BACKTRACK_LIMIT_ERROR:
					$error = "Backtrack limit exhasuted.";
					break;
				case PREG_RECURSION_LIMIT_ERROR:
					$error = "Too much recursion.";
					break;
				case PREG_BAD_UTF8_ERROR:
					$error = "Bad UTF8.";
					break;
				case PREG_BAD_UTF8_OFFSET_ERROR:
					$error = "Bad UTF8 offset.";
					break;
				default:
					$error = "Unknown PREG error.";
					break;
			}
			throw new MySqlQueryException("Query is empty: ".$error, get_defined_vars());
		}

		// Prepare a PDOStatement using the $queryExpr. Prepare is basically doing the same thing parse does above.  Normally you pass in an SQL statement with tokens.  When you execute the statement you pass in the values that should be replaced. Then, internally, the data is escaped and coalesced to the correct type.  However here we're not doing this.  However using a PDOStatement gives us better control over the data returned rather than using the PDO object itself for error messages etc.
		$statement = $this->_db->prepare($queryExpr);

		$start = microtime(true);
		$resultObject = $this->runQuery($statement);
		$end = microtime(true);

		$errorInfo = $statement->errorInfo();
		try {
			$lastInsertId = $this->_db->lastInsertId();
		} catch (\PDOException $e){
			$lastInsertId = NULL;
		}

		$resultDetails = array(
			'queryExpr' => $statement->queryString,
			'insertId' => $lastInsertId,
			'timeTaken' => $end - $start,
			'dateExecuted' => $start,
			'errorNo' => $errorInfo[0],
			'errorMsg' => print_r($errorInfo, true),
			'affectedRows' => $statement->rowCount()
		);

		return new MockMySQLResult($resultObject, $resultDetails);
	}
}
