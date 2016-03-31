<?php

namespace FTBlogs\Gtg\Check;

class Db extends AbstractCheck
{
	protected $dbsToCheck = array();

	public function __construct(array $dbsToCheck = array()) {
		$this->dbsToCheck = $dbsToCheck;
	}

	/**
	 * @inheritdoc
	 */
	public function check(array $params) {
		$failures = array();

		foreach ($this->dbsToCheck as $dbId => $db) {
			if (!is_array($db) || !isset($db['host']) || !isset($db['user']) || !isset($db['pass']) || !isset($db['name'])) {
				continue;
			}

			$conn = @new \mysqli($db['host'], $db['user'], $db['pass'], $db['name']);
			if ($conn->connect_errno) {
				$failures[$dbId] = array(
					'message' => 'Cannot connect to MySQL',
					'error' => $conn->connect_error,
					'code' => $conn->connect_errno
				);
				continue;
			}
			$res = @$conn->query('SELECT COUNT(*) FROM wp_blogs');
			if (!$res) {
				$failures[$dbId] = array(
					'message' => 'MySQL query error: could not select blog count',
					'error' => $conn->error,
					'code' => $conn->errno,
				);
				continue;
			}

			$row = @$res->fetch_row();
			if (!is_array($row) || !isset($row[0]) || (int)$row[0] <= 0) {
				$failures[$dbId] = array(
					'message' => 'Unexpected result of DB query (expected number > 0)',
					'result' => $row,
				);
				continue;
			}

			header('X-Gtg-Db-' . $dbId . ': OK');
		}

		return $failures;
	}

}