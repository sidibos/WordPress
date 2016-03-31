<?php

namespace FTBlogs\Gtg\Check;

class Load extends AbstractCheck
{
	const DEFAULT_TRESHOLD = 120;

	/**
	 * @inheritdoc
	 */
	public function check(array $params) {

		$loadavg = explode(' ', shell_exec('cat /proc/loadavg 2>/dev/null'));
		if (!is_numeric($loadavg[0])) {
			return array(
				'message' => "Cannot retrieve loadavg"
			);
		}

		$treshold = (isset($params['maxload']) && is_numeric($params['maxload'])) ? $params['maxload'] : self::DEFAULT_TRESHOLD;
		if ($loadavg[0] > $treshold) {
			return array(
				'message' => 'Load too high',
				'load' => $loadavg[0],
				'treshold' => $treshold,
			);
		}

		header('X-Gtg-Load-Value: ' . $loadavg[0]);
		header('X-Gtg-Load-Treshold: ' . $treshold);

		return array();
	}
}