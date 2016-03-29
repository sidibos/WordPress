<?php
/**
 * Represent a response from the FT API Connection
 *
 * @codingstandard ftlabs-phpcs
 * @copyright The Financial Times Limited [All Rights Reserved]
 */

namespace FTLabs;

use ArrayAccess;
use Countable;
use DateTime;
use Iterator;

class FTAPIResponse implements ArrayAccess, Countable, Iterator {

	// The data returned from the API call
	private $data;

	// Metadata concerning the request
	private $ttl, $lastfetch, $apitime, $cachetype;

	/**
	 * Create a new FTAPIResponse
	 *
	 * @param array   $data       Array of data, expected to be nested, may contain other data types
	 * @param integer $ttl        Number of seconds after $lastfetch for which the respose is fresh
	 * @param integer $lastfetch  Date the response was received from the API, unix timestamp
	 * @param float   $apitime    Time taken in seconds for the request to be processed by the API
	 * @param string  $cachetype  Name of the cache from which data was loaded, eg 'memcache', 'internal' or 'none'
	 * @return FTAPIResponse
	 */
	public function __construct($data, $ttl, $lastfetch, $apitime, $cachetype) {
		 $this->data = $data;
		 $this->ttl = $ttl;
		 $this->lastfetch = $lastfetch;
		 $this->apitime = $apitime;
		 $this->cachetype = $cachetype;
	}

	/**
	 * Replaces the data already in the response with that supplied (eg to reduce a search response to one of its constituent items)
	 *
	 * @param array $data Array of data, expected to be nested, may contain other data types
	 * @return integer
	 */
	public function setData($data) {
		$this->data = $data;
	}

	/**
	 * Returns remaining freshness time in seconds
	 *
	 * @return integer
	 */
	public function getRemainingTTL() {
		return $this->ttl - (time() - $this->lastfetch);
	}

	/**
	 * Returns true if the response is fresh (has a positive remaining TTL)
	 *
	 * @return boolean
	 */
	public function isFresh() {
		return $this->getRemainingTTL() > 0;
	}

	/**
	 * Returns time spent on the API roundtrip
	 *
	 * Note that the response may have been retrieved from cache (to check that, see FTAPIResponse::getCacheType), and if so the value returned will be the time taken when the request was originally made.  Time in seconds.
	 *
	 * @return float
	 */
	public function getApiTime() {
		return $this->apitime;
	}

	/**
	 * Returns date and time this response was fetched from the API
	 *
	 * @return DateTime
	 */
	public function getLastFetchDate() {
		return new DateTime('@'.$this->lastfetch);
	}

	/**
	 * Returns the source of the current response
	 *
	 * A value of 'none' indicates that the response is was retrieved syncronously from the API.  Any other value describes the cache from which the response originated, eg 'memcache', 'internal'.
	 *
	 * @return string
	 */
	public function getCacheType() {
		return $this->cachetype;
	}

	/**
	 * Returns the API response data as a primitive array
	 *
	 * The FTAPIResponse can be treated as an array and when accessed as an array interface, exposes the response data.  However, some applications may require the data as a primitive array type (eg in order to test that it is_array()).
	 *
	 * @return array
	 */
	public function toArray() {
		return $this->data;
	}


	/* ArrayAccess Interface */

	public function offsetExists($key) { return isset($this->data[$key]); }
	public function offsetGet($key) { return $this->data[$key]; }
	public function offsetSet($key, $value) { trigger_error('API Response is read-only', E_USER_NOTICE); }
	public function offsetUnset($key) { trigger_error('API Response is read-only', E_USER_NOTICE); }


	/* Iterator Interface */

	public function current() { return current($this->data); }
	public function key() { return key($this->data); }
	public function next() { next($this->data); }
	public function rewind() { reset($this->data); }
	public function valid() { return !!key($this->data); }


	/* Countable Interface */

	public function count() { return count($this->data); }
}
