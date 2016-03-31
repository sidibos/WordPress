<?php
/**
 * Memcache access wrapper - stores Assanka's default memcache hostnames and provides per-request stats
 *
 * @codingstandard ftlabs-phpcs
 * @copyright The Financial Times Limited [All rights reserved]
 */

namespace FTLabs;

use Memcached;

class Memcache {

	private $mc;
	private $prefix;
	private $cntreadhits = 0, $cntreadmisses = 0, $cntwrites = 0, $logqueries = false, $queries = array();

	private static $readMethods = array(
		"get" => true,
		"getDelayed" => true,
		"fetch" => true,
		"fetchAll" => true,
		"getMulti" => true,
		"getMultiByKey" => true
	);
	private static $writeMethods = array(
		"set" => true,
		"setByKey" => true,
		"add" => true,
		"addByKey" => true,
		"append" => true,
		"appendByKey" => true,
		"decrement" => true,
		"delete" => true,
		"deleteByKey" => true,
		"flush" => true,
		"increment" => true,
		"prepend" => true,
		"prependByKey" => true,
		"replace" => true,
		"replaceByKey" => true,
		"setMulti" => true,
		"setMultiByKey" => true
	);
	private static $keyMethods = array(
		"get" => true,
		"set" => true,
		"add" => true,
		"append" => true,
		"decrement" => true,
		"delete" => true,
		"increment" => true,
		"prepend" => true,
		"replace" => true
	);
	private static $instances = array('default'=>null);

	const TIMEOUT = 50; // 50ms

	/**
	 * Create a memcache access object
	 *
	 * Creates a memcached object, setting OPT_COMPRESSION and Assanka's server addresses.  Not public, in order to enforce singleton pattern.
	 *
	 * @param string $prefix String to prefix to all get and set requests for the instance (calls to getMemcache with the same prefix will return the same object, whereas different prefixes will allow different instances of memcached to be created, ensuring that distincty parts of an application that use memcached with different prefixes do not clash). If not set, the instance returned will be the one with no prefix.
	 * @return MemcacheAccess
	 */
	private function __construct($prefix=false) {
		$this->prefix = $prefix;
	}

	private function init() {
		if (!class_exists('Memcached')) {
			throw new \Exception("Memcached not installed");
		}

		$this->mc = new Memcached;
		$this->mc->addServer('memcachea', 11211);
		$this->mc->addServer('memcacheb', 11211);
		$this->mc->setOption(Memcached::OPT_COMPRESSION, false);

		// Recv timeout is in microseconds, so multiply by 1000
		$this->mc->setOption(Memcached::OPT_RECV_TIMEOUT, self::TIMEOUT * 1000);
		$this->mc->setOption(Memcached::OPT_CONNECT_TIMEOUT, self::TIMEOUT);
		if ($this->prefix) {
			$this->mc->setOption(Memcached::OPT_PREFIX_KEY, $this->prefix);
		}
	}

	/**
	 * Magic method to wrap memcached functions
	 *
	 * Any unknown method of this class (including all valid memcached methods documented at http://php.net/memcached) when called will trigger the __call method if it exists.  $name is the name of the method actually being invoked, and $args is the array or arguments.  In this way, we can wrap all current (and future) memcached methods with one method in this wrapper class.  Therefore, this method allows MemcacheAccess to be used as a drop in replacement for memcached.  Where previously you had:
	 *
	 * $memcache = new Memcached;
	 * ... servers config ...
	 * $memcache->get('key');
	 *
	 * You can now do:
	 *
	 * $memcache = MemcacheAccess:getMemcache();
	 * $memcache->get('key');
	 *
	 * By detecting whether the specified method is generally a read or write operation, the number of reads and writes, and hits or misses is incremented as appropriate.  These do not detect whether memcache actually does a read or write, they are simply based on the name of the method called.  For the most commonly used methods: get() is a read, set() is a write.
	 *
	 * @param string $name The name of the method being called
	 * @param array  $args The arguments passed to the method in a numerically indexed array
	 * @return mixed The return value is passed through from the underlying memcached method
	 */
	public function __call($name, $args) {
		if ($this->logqueries) {
			$mcstarttime = microtime(true);
			$log = array();
		}

		if (isset($args[0])) {
			$key = $args[0];
			if ($name === 'setOption' and $key === Memcached::OPT_PREFIX_KEY) {
				trigger_error('Prefix should not be changed on a memcacheaccess instance.  Specify the prefix when calling MemcacheAccess::getMemcache()', E_USER_NOTICE);
			}
			if (!empty(self::$keyMethods[$name]) and strpos($key, ' ')) {
				trigger_error("Memcache keys cannot include space characters, replacing with underscore: '".$key."' eh:caller", E_USER_NOTICE);
				$args[0] = $key = str_replace(' ', '_', $key);
			}
		}

		if (!$this->mc) {
			$this->init();
		}

		$ret = @call_user_func_array(array($this->mc, $name), $args);
		$resultcode = $this->mc->getResultCode();

		// Count hits, misses and writes
		if (!empty(self::$readMethods[$name])) {
			if ($resultcode !== Memcached::RES_SUCCESS) {
				$this->cntreadmisses++;
				if ($this->logqueries) $log["type"] = "miss";
			} else {
				$this->cntreadhits++;
				if ($this->logqueries) $log["type"] = "hit";
			}
		} elseif (!empty(self::$writeMethods[$name])) {
			$this->cntwrites++;
			if ($this->logqueries and $name == 'set') {
				$log['type'] = "write";
				$log['ttl'] = (count($args) > 2) ? $args[2] : 0;
			}
			if (!empty($_SERVER['IS_DEV']) and $name == 'set' and count($args) > 2 and $args[2] > 2592000 and $args[2] < time()) {
				trigger_error("Memcache expiration times greater than a month are treated as a UNIX timestamp so the specified value will cause the item to expire at once.  If immediate expiration is desired, use 0", E_USER_NOTICE);
			}
		}

		// Log the call if required.
		if ($this->logqueries) {
			if (isset($args[0]) and !empty(self::$keyMethods[$name])) {
				$log['key'] = $args[0];
			}
			$log['call'] = $name;
			$log['time'] = microtime(true) - $mcstarttime;
			$this->queries[] = $log;
		}

		return $ret;
	}

	/**
	 * Return number of cache hits on read operations in current request
	 *
	 * Returns an integer count of the number of read operations that have been passed through the object which resulted in a hit on the memcache array.  This number is per-instance (but this is a singleton so you can only have one instance anyway), and per-request (counts do not persist between script executions, eg in the session).
	 *
	 * @return integer The number of read hits
	 */
	public function getReadHits() {
		return $this->cntreadhits;
	}

	/**
	 * Return number of cache misses on read operations in current request
	 *
	 * Returns an integer count of the number of read operations that have been passed through the object which resulted in a miss on the memcache array.  This number is per-instance (but this is a singleton so you can only have one instance anyway), and per-request (counts do not persist between script executions, eg in the session).
	 *
	 * @return integer The number of read misses
	 */
	public function getReadMisses() {
		return $this->cntreadmisses;
	}

	/**
	 * Return number of write operations in current request
	 *
	 * Returns an integer count of the number of write operations that have been passed through the object.  This number is per-instance (but this is a singleton so you can only have one instance anyway), and per-request (counts do not persist between script executions, eg in the session).  Write operations are not considered to be 'hits' and 'misses' - although some of memcached's write methods are (or in the future may be) conditional, any call to a write method will be counted as a write, whether or not it results in a change to the state of the cache.
	 *
	 * @return integer The number of read hits
	 */
	public function getWrites() {
		return $this->cntwrites;
	}

	/**
	 * Enable or disable Memcache query logging
	 *
	 * By default, MemcacheAccess will count the read hits, read misses and write queries made through it during a request, but will not remember an itemised list of queries.  This leaks negligible memory.  If you turn on query logging, every query made will be remembered and the entire query history can be retrieved with getQueries().  Exercise caution if considering using this in production envrionments (consider turning it on conditionally by checking $_SERVER['IS_DEV']), and particularly if the script is long running or likely to make a very significant number of memcache queries.
	 *
	 * @param boolean $newval Whether to enable query logging (default true)
	 * @return void
	 */
	public function enableQueryLogging($newval=true) {
		$this->logqueries = ($newval == true);
	}

	/**
	 * Retrieve query history
	 *
	 * Returns a numerically indexed array listing all the calls to memcached, and the result for each one.  Each element in the list is an associative array containing the following elements:
	 *
	 * - key: The memcached key being manipulated
	 * - type: miss, hit, or write
	 * - ttl: If the call was a write operation, this is the TTL specified in the write (if one was specified)
	 * - time: Execution time of the operation
	 *
	 * @return array The complete history of calls to memcached, in the described format
	 */
	public function getQueries() {
		return $this->queries;
	}


	/* Static methods */

	/**
	 * Return the object instance
	 *
	 * MemcacheAccess is a singleton class, so you must call this method to get an instance of the class.  The class will internally ensure that only one instance exists in each request.
	 *
	 * @param string $prefix String to prefix to all get and set requests for the instance (calls to getMemcache with the same prefix will return the same object, whereas different prefixes will allow different instances of memcached to be created, ensuring that distincty parts of an application that use memcached with different prefixes do not clash). If not set, the instance returned will be the one with no prefix.
	 * @return MemcacheAccess The working instance
	 */
	public static function getMemcache($prefix=false) {
		if (empty($prefix) or !is_string($prefix)) $prefix = 'default';
		if (empty(self::$instances[$prefix])) self::$instances[$prefix] = new self($prefix);
		return self::$instances[$prefix];
	}
}
