<?php
/**
 * Testing memcache timeouts
 *
 * @codingstandard ftlabs-phpcs
 * @copyright The Financial Times Limited [All rights reserved]
 */

use FTLabs\Memcache;

class Timeout_TestCase extends PHPUnit_Framework_TestCase {

	public function testTimeout() {
		$cachekey = 'testcachekey';
		$mc = Memcache::getMemcache();
		$mc->set($cachekey, 'true', 1);
		$this->assertEquals('true', $mc->get($cachekey), 'key not stored in memcache at all');
		sleep(2);
		$this->assertEmpty($mc->get($cachekey), 'key not removed from memcache after ttl');
	}
}
