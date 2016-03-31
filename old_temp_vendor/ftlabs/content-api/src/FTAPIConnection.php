<?php
/**
 * Connect to the FT content API to retrieve data
 *
 * Sends HTTP requests to the FT's content API and
 * manages caching of results to minimise HTTP
 * overhead
 *
 * @codingstandard ftlabs-phpcs
 * @copyright The Financial Times Limited [All Rights Reserved]
 */

namespace FTLabs;

class FTAPIConnection {

	// Settings for Memcache-backed caching (hosts are configured in FTLabs\Memcache)
	const MEMCACHE_MAX_TTL = 1800;
	const MEMCACHE_PREFIX = 'content-api-helper';

	// Defaults for data TTLs (TTLs specified in response headers are respected, but subject to these defaults)
	const DEFAULT_DATA_TTL = 1800;
	const DEFAULT_ERROR_TTL = 10;

	// API environment to use.
	// Options are 'live', 'test' or 'int', which affect the hostname and API key
	private $apiEnvironment;

	private $logger;
	private $cacheEnabled = true;
	private $memcache;

	// Persist the most recent HTTP request and response, to enable deep inspection for unit testing
	private $lastHTTPRequest, $lastHTTPResponse;

	// Api override keys
	private $apiKey, $testApiKey;

	// Feature flags
	private $featureFlags;

	/**
	 * Create a new FTAPIConnection
	 *
	 * @return FTAPIConnection
	 */
	public function __construct() {
		$this->memcache = Memcache::getMemcache(self::MEMCACHE_PREFIX);
		$this->logger = new Logger('ftapicomponent');

		// Set up the API environment, using a value set on the server if present
		if (!empty($_SERVER['FT_API_ENV'])) {
			$this->apiEnvironment = $_SERVER['FT_API_ENV'];
		} else {
			$this->apiEnvironment = 'live';
		}

		// Set up feature flags for use on all requests made by this connection
		$this->featureFlags = array(
			'feature.usage' => 'on',
			'feature.textualBody' => 'on',
		);
	}

	/**
	 * Set cache behaviour; by default FTAPIConnection will use a Memcache cache
	 * to cache items up to the TTL specified on the response, but this can be
	 * disabled to always request items from source.
	 *
	 * @param boolean $newopt Whether to allow cache use
	 * @return void
	 */
	public function setCacheEnabled($newopt) {
		$this->cacheEnabled = $newopt;
	}

	/**
	 * Return whether the cache is currently enabled, as controlled by setCacheEnabled()
	 *
	 * @return integer Cache behaviour option as one of the class constants
	 */
	public function getCacheEnabled() {
		return $this->cacheEnabled;
	}

	/**
	 * Override the memcache accessor with an alternative class
	 *
	 * Allows replacement of the cache interface (mainly for testing purposes)
	 *
	 * @param object $mcobj An object implementing get, set and delete methods for cache access
	 * @return void
	 */
	public function setMemcacheAccess($mcobj) {
		$this->memcache = $mcobj;
	}

	/**
	 * Make a GET request to the FT API
	 *
	 * Get the data identified by the specified path and args from the FT API.
	 *
	 * @param string $path The REST resource to be loaded (from the universal API endpoint)
	 * @param array  $args An array of data to be sent with the request as arguments
	 * @see doRequest
	 * @return array The response from the API is assumed to be JSON and is decoded and returned as an array
	 */
	public function get($path, $args = array()) {
		return $this->doRequest($path, $args);
	}

	/**
	 * Make a POST request to the FT API
	 *
	 * Send the specifed request to the FT API and return the result
	 *
	 * @param string $path    The REST resource to be loaded (from the universal API endpoint)
	 * @param array  $args    An array of data to be sent with the request as arguments
	 * @see doRequest
	 * @return array The response from the API is assumed to be JSON and is decoded and returned as an array
	 */
	public function post($path, $args = array()) {
		return $this->doRequest($path, $args, 'POST');
	}

	/**
	 * Make a request aimed at the FT API, utilitising cacheing as appropriate.
	 *
	 * Get the data identified by the specified path and args from the FT API.  Where the response is already in local memory or memcache, that saved response may be returned instead, depending on the setting of setCacheMode.
	 *
	 * @param string $path    The REST resource to be loaded (from the universal API endpoint)
	 * @param array  $args    An array of data to be sent with the request as arguments
	 * @param string $method  HTTP method name
	 * @return array The response from the API is assumed to be JSON and is decoded and returned as an array
	 */
	private function doRequest($path, $args = array(), $method = 'GET') {

		// Remove any host element of path, since API host is determined by the connection object
		$path = preg_replace('/^((https?\:\/\/)?([a-z0-9]+\.)*ft\.com)?\/(.*)$/i', '$4', $path);

		// Cache key is either args serialised as querystring, or MD5 sum of data
		// if args is to be encoded into JSON (it might be deep, and large)
		$cacheargs = $args;
		$cacheargs['apienv'] = $this->apiEnvironment;

		$basekey = $path . '?' . md5(json_encode($cacheargs));

		// Use MD5 of base cache key to avoid exceeding memcache max key length
		$cachekey = md5($basekey);



		/* If a cached response is permitted, check memcache for the value and return if present */

		if ($this->cacheEnabled) {
			$cache = $this->memcache->get($cachekey);
			if ($cache) {
				$meta = $cache['meta'];
				return new FTAPIResponse($cache['data'], $meta['ttl'], $meta['lastfetch'], $meta['apitime'], 'memcache');
			}
		}


		/* Cache disabled or response not found in cache.  Fetch from origin */

		$item = $this->doHTTPRequest($path, $args, $method, $basekey);

		// If the item wasn't available, return null
		if ($item === null) {
			return null;
		}

		// Store response in memcache if the item has a positive TTL
		if (!empty($item['meta']['ttl']) and $item['meta']['ttl'] > 0) {
			$memcacheCacheTime = min(self::MEMCACHE_MAX_TTL, $item['meta']['ttl']);
			$this->memcache->set($cachekey, $item, $memcacheCacheTime);
		}

		$meta = $item['meta'];
		return new FTAPIResponse($item['data'], $meta['ttl'], $meta['lastfetch'], $meta['apitime'], 'none');
	}

	/**
	 * Make a request to the FT API over HTTP
	 *
	 * Get the data identified by the specified path and args from the FT API HTTP endpoint.
	 *
	 * @param string $path       The REST resource to be loaded (from the universal API endpoint)
	 * @param array  $args       An array of data to be sent with the request as arguments
	 * @param string $method     HTTP method name
	 * @param string $basekey    The string key used to uniquely identify this request
	 *
	 * @return array The response from the API is assumed to be JSON and is decoded and returned as an array
	 */
	private function doHTTPRequest($path, $args, $method, $basekey) {

		// Set up a memcache key to avoid exceeding memcache max key length
		$memcachekey = md5($basekey);

		// Determine the endpoint base path to differentiate services (path up to version number)
		$service = preg_replace('/^(.*?)\/v\d+\/.*$/si', "$1", $path);

		// Set up the basic details to log for this request
		$log = array(
			'action' => 'fetch',
			'method' => $method,
			'key' => $basekey
		);

		// Set up the HTTP request
		if ($method == 'POST') {

			// HACK:MA:20140620 Currently there is no way to provide query string params to post requests on an FT Labs request object - redmine #40262
			// so the only way to do this is by 'hacking' them on to the path instead.  There is a danger though by doing this that FTAPIConnection#post
			// method might be called with query parameters already (and then the URL would become blah?something=true?feature.usage=on).
			// So rather than implementing logic to detect whether the path passed in has a query parameter or not, throw an error here to strictly
			// enforce $path being a path (not a path + encoded query string)
			if (strpos($path, '?') !== false) {
				trigger_error('API post request path must not contain query parameters', E_USER_NOTICE);
			}

			// HACK:MA:20140620 As the only FT API method that supports post requests is search, and currently that doesn't get called with
			// any query parameters, this doesn't actually do anything, but it's important for this to be consistent with #get.
			$path .= '?' . http_build_query($this->featureFlags);

			$http = new HTTPRequest(self::getApiMeta('host') . $path);
			$http->setHeader('X-Api-Key', $this->getApiKey());
			$http->setMethod('POST');
			$http->setRequestBody(json_encode($args));
		} else {

			// Add any feature flags to the request arguments, preferring request arguments
			$args = array_merge($this->featureFlags, $args);

			$http = new HTTPRequest(self::getApiMeta('host') . $path);
			$args['apiKey'] = $this->getApiKey();
			$http->set($args);
		}

		// Allow a single retry but with a short request timeout
		$http->setMaxRetries(1);
		$http->setTimeLimit(5);

		// Send the request
		try {
			$resp = $http->send();
			$this->lastHTTPRequest = $http;
			$this->lastHTTPResponse = $resp;

			// If response is a 500 (or greater) or 429 error (ie error or rate limit encountered), treat as an exception to avoid overwriting cache (other errors such as 404 should flush cache)
			if ($resp->getResponseStatusCode() >= 500 or $resp->getResponseStatusCode() == 429) {
				throw new \Exception('HTTP ' . $resp->getResponseStatusCode());
			}

		// If the request failed with an exception, log and return
		} catch (\Exception $e) {
			$log['status'] = 'fail_exception';
			$log['errtext'] = $e->getMessage();
			$this->logWrite($log);

			return null;
		}

		// Set up the data to return with details from the response
		$item = array(
			'meta' => array(),
			'data' => array()
		);

		// Observe TTLs specified by server, but anchor to minimum and maximum limits
		if ($resp->getHeader('Expires') and $expires = strtotime($resp->getHeader('Expires'))) {
			$item['meta']['ttl'] = $expires - time();
		} else {
			$item['meta']['ttl'] = ($resp->getResponseStatusCode() == 200) ? self::DEFAULT_DATA_TTL : self::DEFAULT_ERROR_TTL;
		}

		// Set metadata on the data, such as last fetch timestamp, to allow
		// cache age tracking
		$item['meta']['apitime'] = round($resp->getResponseTime(), 2);
		$item['meta']['lastfetch'] = time();
		if ($resp->getResponseStatusCode() == 200) {
			$item['data'] = $resp->getData();
			$log['status'] = 'success';
			$log['resptime'] = round($resp->getResponseTime(), 2);
		} else {
			$item['meta']['lastchange'] = null;
			$log['status'] = 'fail_' . $resp->getResponseStatusCode();
		}
		$log['newttl'] = $item['meta']['ttl'];

		// Write out the final request status
		$this->logWrite($log);

		return $item;
	}

	/**
	 * Get default connection settings based on current environment
	 *
	 * @param string $entry The setting to fetch (host or key)
	 * @return string The setting
	 */
	private function getApiMeta($entry) {
		$apis = array(
			'test-live' => array('host'=>'http://ft-eu.dn.apigee.net/', 'key'=>'3a8ff92699a30b7d53c96325f0616151'),
			'live-live' => array('host'=>'http://api.ft.com/', 'key'=>'2ff50f876aab083a7f1490bcc523f121'),
			'test-test' => array('host'=>'http://test.api.ft.com/', 'key'=>'ePSQfnkpLOtqFZ4GIRPx8b8gGmT3R78L'),
			'live-test' => array('host'=>'http://test.api.ft.com/', 'key'=>'ePSQfnkpLOtqFZ4GIRPx8b8gGmT3R78L'),
			'test-int' => array('host'=>'http://int.api.ft.com/', 'key'=>'ePSQfnkpLOtqFZ4GIRPx8b8gGmT3R78L'),
			'test-mashery' => array('host'=>'http://financialtimes.api.mashery.com/', 'key'=>'r8rxKSVKN5y2VLH2EWpAlF1kV6Xqlz1A')
		);
		$endpoint = (!empty($_SERVER['IS_LIVE']) ? 'live' : 'test') . '-' . $this->apiEnvironment;
		return $apis[$endpoint][$entry];
	}

	/**
	 * Get the API key for a given environment
	 *
	 * @param string $entry The endpoint to use (either test or live)
	 * @return string        The api key to use
	 */
	private function getApiKey() {
		if ($this->apiEnvironment === 'live' and isset($this->apiKey)) {
			return $this->apiKey;
		} elseif ($this->apiEnvironment !== 'live' and isset($this->testApiKey)) {
			return $this->testApiKey;
		}
		return $this->getApiMeta('key');
	}

	/**
	 * Get the active environment
	 *
	 * @return string The active environment - "test", "live" or "int".
	 */
	public function getEnvironment() {
		return $this->apiEnvironment;
	}

	/**
	 * Write a line to the API log
	 *
	 * @param array $vars Array to write
	 * @return void
	 */
	public function logWrite($vars) {
		$this->logger->info('', $vars);
	}

	/**
	 * Allow deep inspection for the purposes of automated testing
	 *
	 * @return HTTPRequest object
	 */
	public function getLastHTTPResponse() {
		return $this->lastHTTPResponse;
	}

	/**
	 * Overrides the default API key used when the API environment is live
	 *
	 * @param string $key Key
	 * @return void
	 */
	public function setApiKey($key) {
		$this->apiKey = $key;
	}

	/**
	 * Overrides the default API key used when the API environment is test
	 *
	 * @param string $key Key
	 * @return void
	 */
	public function setTestApiKey($key) {
		$this->testApiKey = $key;
	}

}
