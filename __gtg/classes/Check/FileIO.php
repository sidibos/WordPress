<?php

namespace FTBlogs\Gtg\Check;

class FileIO extends AbstractCheck
{
	protected $filesToCheck = array();

	public function __construct(array $filesToCheck = array()) {
		$this->filesToCheck = $filesToCheck;
	}

	/**
	 * @inheritdoc
	 */
	public function check(array $params) {
		$failures = array();

		foreach ($this->filesToCheck as $file) {
			clearstatcache();

			if (!@touch($file)) {
				$failures[$file] = 'Failed to touch the file';
				continue;
			}
			if (!@is_readable($file)) {
				$failures[$file] = 'File is not readable or does not exist';
			}
			@unlink($file);
		}

		if (count($failures) === 0) {
			header('X-Gtg-FileIO: OK');
		}

		return $failures;
	}
}