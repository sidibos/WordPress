<?php

namespace FTBlogs\Gtg\Check;

class DbReplicationLag extends AbstractCheck
{
	const DEFAULT_TRESHOLD = 5;
	protected $dbConfig = array();

	public function __construct(array $dbConfig = array()) {
		$this->dbConfig = $dbConfig;
	}

	/**
	 * @inheritdoc
	 */
	public function check(array $params) {

		$failures = $this->beat();

		$conn = @new \mysqli(
			$this->dbConfig['dbread']['host'],
			$this->dbConfig['dbread']['user'],
			$this->dbConfig['dbread']['pass'],
			$this->dbConfig['dbread']['name']
		);

		if ($conn->connect_errno) {
			$failures['dbread'] = array(
				'message' => 'Cannot connect to MySQL',
				'error' => $conn->connect_error,
				'code' => $conn->connect_errno
			);
			return $failures;
		}

		$res = @$conn->query("SELECT TIMESTAMPDIFF(SECOND, `value`, NOW()) FROM `heartbeat` WHERE `key`='gtg_beat_time'");
		if (!$res) {
			$failures['dbread'] = array(
				'message' => 'MySQL query error: could not select beat time',
				'error' => $conn->error,
				'code' => $conn->errno,
			);
			return $failures;
		}

		$row = @$res->fetch_row();
		if (!is_array($row) || !isset($row[0]) || !is_numeric($row[0])) {
			$failures['dbread'] = array(
				'message' => 'Unexpected result of DB query (expected number)',
				'result' => $row,
			);
		}

		$lag = (int)$row[0];
		$treshold = (int)((isset($params['maxdbreplicationlag']) && is_numeric($params['maxdbreplicationlag'])) ? $params['maxdbreplicationlag'] : self::DEFAULT_TRESHOLD);

		header('X-Gtg-DbReplicationLag-Value: ' . $lag);
		header('X-Gtg-DbReplicationLag-Treshold: ' . $treshold);

		if ($lag > $treshold) {
			$failures = array_merge($failures, array(
				'message' => 'DB Replication lag too high (in seconds)',
				'lag' => $lag,
				'treshold' => $treshold,
			));
		}

		return $failures;
	}

	protected function beat() {
		$conn = @new \mysqli(
			$this->dbConfig['dbmaster']['host'],
			$this->dbConfig['dbmaster']['user'],
			$this->dbConfig['dbmaster']['pass'],
			$this->dbConfig['dbmaster']['name']
		);

		if ($conn->connect_errno) {
			return array(
				'dbmaster' => array(
					'message' => 'Cannot connect to MySQL',
					'error' => $conn->connect_error,
					'code' => $conn->connect_errno
				),
			);
		}

		$res = @$conn->query("REPLACE INTO `heartbeat` SET `value`=NOW(), `key`='gtg_beat_time'");

		if (!$res) {
			return array(
				'dbmaster' => array(
					'message' => 'MySQL query error: could not write heartbeat',
					'error' => $conn->error,
					'code' => $conn->errno,
				),
			);
		}

		return array();
	}

}