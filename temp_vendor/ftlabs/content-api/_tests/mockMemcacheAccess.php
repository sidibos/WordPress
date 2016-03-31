<?php
/**
 * Partially mocks the FTLabs\Memcache interface
 *
 * @codingstandard ftlabs-phpcs
 * @copyright The Financial Times Limited [All Rights Reserved]
 */

class mockMemcacheAccess {

	private $cached = array();
	private $getterkey = null, $setterkey = null;


	/* Mocked MemcacheAccess methods */

	public function get($key) {
		$this->getterkey = $key;
		return isset($this->cached[$key]) ? $this->cached[$key] : null;
	}

	public function set($key, $value) {
		$this->setterkey = $key;
		$this->cached[$key] = $value;
	}

	public function delete($key) {
		$this->setterkey = $key;
		unset($this->cached[$key]);
	}


	/* Extra methods for use in tests */

	public function getKeyForSetter() { return $this->setterkey; }
	public function getCached() { return $this->cached; }
}
