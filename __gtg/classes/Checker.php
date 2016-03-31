<?php

namespace FTBlogs\Gtg;

class Checker
{
	protected $checks = array();

	public function registerGtgCheck(Check\CheckableInterface $gtgChecker) {
		$this->checks[] = $gtgChecker;
		return $this;
	}

	public function resetGtgChecks() {
		$this->checks = array();
		return $this;
	}

	public function isGoodToGo(array $params) {
		$failures = array();

		foreach ($this->checks as $check) {
			/* @var Check\CheckableInterface $check */
			$checkFailures = $check->check($params);
			if (count($checkFailures) > 0) {
				$checkId = (string)$check;
				if (!isset($failures[$checkId]) || !is_array($failures[$checkId])) {
					$failures[$checkId] = array();
				}

				$failures[$checkId] = array_merge(
					$failures[$checkId],
					$checkFailures
				);
			}
		}

		return $failures;
	}
}