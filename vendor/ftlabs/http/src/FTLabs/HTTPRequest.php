<?php
/**
 * Send an HTTP request to a remote server
 *
 * The HTTPRequest class allows you to make HTTP requests from PHP, including all RESTful operations.  Powered by libcurl.
 *
 * @codingstandard ftlabs-phpcs
 * @copyright The Financial Times Limited [All Rights Reserved]
 */

namespace FTLabs;

class HTTPRequest {

	private $useCookies = false;
	private $allowSslErrors = false;
	private $cookiePath;
	private $method = "GET"; // Defaults to get. cURL defaults to get even if this is not set here.
	private $postData;
	private $posttype = 'multipart';
	private $authmethod, $authUsername, $authPassword;
	private $header;
	protected $url;
	private $interface;
	private $timelimit = 30;
	private $resp;
	private $followlocation = false;
	private $receivedheaders = false;
	private $receivedcookies = false;
	private $receivedbody = "";
	private $receivedfullresponse = "";
	private $maxretries = 0;
	private $retryinterval = 3;
	private $usegzip = false;
	private $resolveto = false;
	private static $mockServer = null;

	/**
	 * Create an HTTPRequest object
	 *
	 * Optionally, you can provide the URL in the constructor, otherwise setURL must be called prior to send().
	 *
	 * @param string $url URL to fetch
	 * @return HTTPRequest
	 */
	public function __construct($url = false) {
		if ($url) $this->setURL($url);

		// Always specify a user-agent (this can be overriden later by calling setHeader again)
		$this->setHeader("User-Agent", "FT Labs HTTPRequest V1");

		// Always specify an Accepts header, which again can be overridden
		$this->setHeader("Accept", "*/*");
	}


	/**
	 * Enable or disable use of persistent cookies in the request
	 *
	 * Set to true to cause HTTPRequest to load cookies from a file, and save any new cookies returned in the response back to the file.  If you wish to specify the location of the cookie file, use setCookieJar().  You should use this feature if you wish to execute a series of requests that operate as part of a single cookied session.  However, if you wish to send a single request that contains a cookie header, you may prefer to simply use setHeader().
	 *
	 * @param Boolean $usecookies flag indicating if cookies should be used or not.
	 * @return void.
	 */
	public function useCookies($usecookies = true) {
		$this->useCookies = ($usecookies == true);

		// Use a random cookie jar filename if $usecookies is true and $setcookiejar hasn't been called
		if (empty($this->cookiePath)) $this->cookiePath = '/tmp/' . uniqid();
	}


	/**
	 * Sets the encoding type for POST requests
	 *
	 * By default, POST data is sent as multipart/form-data.  To send as url-encoded/form-data, pass 'form' as an argument.  To restore the multipart setting, pass 'multipart'.
	 *
	 * @param string $encoding 'form' or 'multipart'
	 * @return void.
	 */
	public function setPostEncoding($encoding) {
		$this->posttype = $encoding;
	}


	/**
	 * Enable or disable the ignoring of SSL errors
	 *
	 * @param Boolean $allowSslCertErrors Flag indicating if SSL certificate errors can be ignored
	 * @return void.
	 */
	public function allowSslCertErrors($allowSslCertErrors=true) {
		$this->allowSslErrors = ($allowSslCertErrors == true);
	}


	/**
	 * Override hostname resolution (typically for testing).
	 *
	 * If you do not use this method to set an alternative hostname or addres for resolution, then the URL used in the constructor will be used.  If this method is used, then the hostname is extacted from the URL in the constructor, and replaced with the alternative host supplied by this method.  The original hostname is then passed to the server via the 'Host: ' header.  This method is only applicable to HTTP(s) requests.
	 *
	 * @param string $host Host address or name
	 * @return void
	 */
	public function resolveTo($host) {
		$this->resolveto = $host;
	}


	/**
	 * Set location in which to read and store cookies
	 *
	 * If this file already exists, cookies stored in the file will be appended to the request as a Cookie header.  Any Set-Cookie headers received in the response will be used to update the file.
	 *
	 * @param String $absoluteFilePath Location for the cookies to be written to. If this location does not exist or is not writable, cookies will not be included in the request, or accepted in the response.
	 * @return void.
	 */
	public function setCookieJar($absoluteFilePath) {
		$this->useCookies();
		$this->cookiePath = $absoluteFilePath;
	}

	/**
	 * Full HTTP URL to fetch in the request
	 *
	 * Should include the scheme and host, and where applicable the port, path, querystring, and fragment, eg:
	 *
	 * scheme://host.name/path?querystring#fragment
	 * http://www.example.com/users/andrew?startdate=2000-01-01#hobbies
	 *
	 * @param String $url The URL to load in the request
	 * @return void.
	 */
	public function setUrl($url) {
		$parts = parse_url($url);
		if (empty($parts['scheme']) or empty($parts['host'])) throw new InvalidCallException('Malformed URL passed to HTTPRequest', $url);
		$this->url = $url;
	}


	/**
	 * Get this instance's request URL
	 *
	 * @return string
	 */
	public function getUrl() {
		return $this->url;
	}


	/**
	 * Set HTTP request method (eg GET, POST, PUT, HEAD, DELETE)
	 *
	 * If you do not use this method to set the request verb, the request will default to GET.
	 *
	 * @param string $method HTTP method verb
	 * @return void
	 */
	public function setMethod($method) {
		$method = strtoupper($method);
		$validmethods = array("GET", "POST", "PUT", "DELETE", "HEAD", "PURGE");
		if (!in_array($method, $validmethods)) throw new InvalidCallException('Invalid HTTP method', $method);
		$this->method = $method;
	}


	/**
	 * Get this instance's HTTP request method
	 *
	 * @return string
	 */
	public function getMethod() {
		return $this->method;
	}


	/**
	 * Set Network interface to use (eg eth0).
	 *
	 * If you do not use this method to set the interface, the machine's default will be used.
	 *
	 * @param string $interface Name of network interface to use
	 * @return void
	 */
	public function setInterface($interface) {
		$this->interface = $interface;
	}


	/**
	 * Add some POST data to the request as multipart/form-data.  Overwrite any existing data with the same name.
	 *
	 * If setRequestBody is used, this setting will be ignored.
	 *
	 * @param mixed  $a If an array, keys and values to be set in the POST data.  If a string, the key.
	 * @param string $b If $a is a string, this is the value to set on key $a in the POST data
	 * @return void
	 */
	public function set($a=false, $b=null) {

		// PostData is an array of key / value pairs
		if (is_string($a) and is_scalar($b)) {
			$this->postData[$a] = $b;

		} elseif (is_string($a) and is_array($b) and !empty($b)) {

			// CURL cannot handle multi-dimensional arrays, (trying to SETOPT with one gives an Array to String conversion error)
			foreach (array_values($b) as $key=>$val) {

 				// Ignore the $key
 				$this->postData[$a.'['.$key.']'] = $val;
  			}

		} elseif (is_string($a) and !$b) {
			unset($this->postData[$a]);
		} elseif (is_array($a) and !$b) {
 			foreach ($a as $key=>$val) $this->set($key, $val);
		} else {
 			throw new InvalidCallException('Unable to interpret supplied POST data', get_defined_vars());
 		}
	}


	/**
	 * Add some POST data to the request as multipart/form-data.  Convert any existing data with the same key into an array and add the new data as additional elements
	 *
	 * If setRequestBody is used, this setting will be ignored.
	 *
	 * @param mixed  $a If an array, keys and values to be added to the POST data.  If a string, the key.
	 * @param string $b If $a is a string, this is the value to add to key $a in the POST data
	 * @return void
	 */
	public function add($a=false, $b=false) {

		if (is_string($a) and is_scalar($b)) {
			if (isset($this->postData[$a])) {
				$this->postData[$a.'[0]'] = $this->postData[$a];
				$this->postData[$a.'[1]'] = $b;
				unset($this->postData[$a]);
			} elseif (isset($this->postData[$a.'[0]'])) {
				$i = 1;
				while (isset($this->postData[$a.'['.$i.']'])) ++$i;
				$this->postData[$a.'['.$i.']'] = $b;
			} else {
				$this->postData[$a] = $b;
			}


		} elseif (is_string($a) and is_array($b) and !empty($b)) {

			if (isset($this->postData[$a])) {
				$this->postData[$a.'[0]'] = $this->postData[$a];
				$offset = 1;
				unset($this->postData[$a]);
			} elseif (isset($this->postData[$a.'[0]'])) {
				$offset = 1;
				for ($offset = 1; isset($this->postData[$a.'['.$offset.']']); ++$offset) {
				}
			} else {
				$offset = 0;
			}
			foreach (array_values($b) as $idx=>$val) {
				$this->postData[$a.'['.($idx + $offset).']'] = $val;
			}

		} elseif (is_array($a) and !$b) {
			foreach ($a as $key=>$val) $this->add($key, $val);
		} else {
 			throw new InvalidCallException('Unable to interpret supplied POST data', get_defined_vars());
		}
	}


	/**
	 * Return the request's POST data
	 *
	 * @return array
	 */
	public function getPostData() {
		return $this->postData;
	}


	/**
	 * Remove all POST data previously set on the request
	 *
	 * @return void
	 */
	public function clearPostData() {
		unset($this->postData);
	}

	/**
	 * Specify that the request should be sent with a username and password formatted according to HTTP Basic Authentication
	 *
	 * @param string $u Username
	 * @param string $p Password
	 * @return void
	 */
	public function setBasicAuth($u, $p) {
		$this->authPassword = $p;
		$this->authUsername = $u;
		$this->authmethod = 'basic';
	}

	/**
	 * Set a raw request body.
	 *
	 * If a request body is supplied, no POST data will be sent, even if the set() and/or add() methods have been used to create some.
	 *
	 * @param string $body the request body
	 * @return boolean, true if set was successful, false on failure.
	 */
	public function setRequestBody($body) {
		if (is_scalar($body)) {
			$this->postData = (string)$body;
			return true;
		} else {
			return false;
		}

	}

	/**
	 * Add an HTTP header to the request
	 *
	 * @param string $key   HTTP header key
	 * @param string $value Header value
	 * @return void
	 */
	public function setHeader($key, $value) {
		$this->header[strtolower($key)] = $key.': '.$value;
	}

	/**
	 * Set multiple HTTP headers
	 *
	 * If any header already exists, it will be overwritten
	 *
	 * @param array $headers array of headers to add to the request.
	 * @return void
	 */
	public function setHeaders($headers) {
		if (!is_array($headers)) throw new InvalidCallException('Invalid headers: not an array', $headers);
		foreach ($headers as $k=>$v) $this->setHeader($k, $v);
	}


	/**
	 * Get this instance's HTTP request headers
	 *
	 * @return array
	 */
	public function getHeaders() {
		return $this->header;
	}


	/**
	 * Set time limit for the call about to be made.
	 *
	 * @param integer $seconds the value in seconds for the timeout of the call.
	 * @return boolean true if the change was successful, and false otherwise
	 */
	public function setTimelimit($seconds) {
		if (!is_numeric($seconds) or $seconds <= 0) throw new InvalidCallException('Invalid time limit', $seconds);
		$this->timelimit = $seconds;
	}

	/**
	 * Set the time to wait between retries.
	 *
	 * @param integer $seconds the number seconds to wait until retrying after a failed request.
	 * @return void
	 */
	public function setRetryInterval($seconds) {
		if (!is_numeric($seconds) or $seconds < 0) throw new InvalidCallException('Invalid time', $seconds);
		$this->retryinterval = $seconds;
	}

	/**
	 * Set the maximum number of retries. (NB: this dosen't include the initial request)
	 *
	 * @param integer $retries the number of times to retry after a failed request.
	 * @return void
	 */
	public function setMaxRetries($retries) {
		if (!is_int($retries) or $retries < 0) throw new InvalidCallException('Number of retries must be a non-negative integer', $retries);
		$this->maxretries = $retries;
	}


	/**
	 * Set whether or not the request should follow HTTP redirects
	 *
	 * @param bool $followlocation whether or not to follow redirects.
	 * @return void
	 */
	public function setFollowLocation($followlocation) {
		if (!is_bool($followlocation)) throw new InvalidCallException('Invalid boolean value', $followlocation);
		$this->followlocation = $followlocation;
	}


	/**
	* Set whether or not to use treat the return value as gzip.
	* Use in conjunction with $req->setHeader('Accept-Encoding', 'gzip');
	*
	* @param bool $gzip whether or not to use gzip
	* @return void
	*/
	public function setUseGzip($gzip) {
		if ($gzip === true or $gzip === false) $this->usegzip = $gzip;
		else throw new InvalidCallException('Invalid argument. Only setGzip(true) or setGzip(false) accepted', $gzip);
	}


	/**
	 * Get whether or not the request is set to follow HTTP redirects
	 *
	 * @return bool
	 */
	public function getFollowLocation() {
		return $this->followlocation;
	}

	/**
	 * Return a string containing a cURL command line that would perform a request equivilent to the settings of the current request
	 *
	 * @return string
	 */
	public function getCliEquiv() {
		$cmd = "curl -v";
		$cmd .= ($this->followlocation) ? " -l" : "";
		$cmd .= ($this->timelimit) ? " -m ".$this->timelimit : "";

		// The -I option is the proper way to make a HEAD request
		if ($this->method == 'HEAD') $cmd .= " -I";
		else $cmd .= " -X ".$this->method;

		$cmd .= ($this->interface) ? " --interface ".$this->interface : "";
		if (!empty($this->header)) foreach($this->header as $h) $cmd .= " -H ".escapeshellarg($h);
		$thisurl = $this->url;
		if ($this->resolveto) {
			preg_match('/^https?:\/\/([^\/]*)/', $thisurl, $matches);
			if (isset($matches[1])) {
				$originalhost = $matches[1];
				$cmd .= " -H ".escapeshellarg('Host: '.$originalhost);
				$thisurl = preg_replace('/'.preg_quote($originalhost).'/', $this->resolveto, $thisurl, 1);
			}
		}
		if (!empty($this->postData)) {
			if ($this->method == 'GET') {
				$url = parse_url($this->url);
				if (!empty($this->postData) and is_array($this->postData)) {
					$data = http_build_query($this->postData);
					$qs = empty($url['query']) ? $data : $url['query']."&".$data;
					$newurl = $url['scheme']."://".(empty($url['user'])?'':$url['user'].":".$url['pass']."@") . $url['host'] . $url['path'] . "?" . $qs . (empty($url['fragment'])?'':'#'.$url['fragment']);
					$thisurl = $newurl;
				}
			} elseif ($this->method == 'POST' and !empty($this->postData) and is_array($this->postData)) {
				if ($this->posttype == 'form' or !is_array($this->postData)) {
					$data = http_build_query($this->postData);
					$cmd .= " -d ".escapeshellarg($data);
				} else {
					foreach ($this->postData as $k=>$v) {
						$cmd .= " -F ".escapeshellarg($k.'='.$v);
					}
				}
			} elseif (!empty($this->postData) and is_scalar($this->postData)) {
				$cmd .= " -d ".escapeshellarg($this->postData);
			}

		// RB:COMPLEX:20140701: Curl doesn't add a content-length by default if there is no
		// body to POST, which some servers and Akamai require, so add one, and enforce
		// HTTP version 1.0 for use with Expect: 100
		} elseif ($this->method == 'POST') {
			$cmd .= ' -H ' . escapeshellarg('Content-Length: 0');
			$cmd .= ' --http1.0';
		}

		if (!empty($this->authmethod) and $this->authmethod == 'basic') {
			$cmd .= " --basic -u ".escapeshellarg($this->authUsername.":".$this->authPassword);
		}
		if ($this->useCookies) {
			$cmd .= " -b ".escapeshellarg($this->cookiePath)." -c ".escapeshellarg($this->cookiePath);
		}
		if ($this->allowSslErrors) $cmd .= " -k";
		$cmd .= " --url ".escapeshellarg($thisurl);

		return $cmd;
	}

	/**
	 * Callback function that is called for every header
	 * in the response.
	 *
	 * @param resource $handle the curl handle for the request
	 * @param string   $header the header
	 * @return integer length of header
	 */
	private function readHeaderCallback($handle, $header) {
		static $lastheader = false;

		// CURL passes $handle into this callback, but it is not required by this method.  State this for standards compliance
		$handle;

		// If this is the start of a new block of headers, flush
		// the array of received headers, so that the array ends
		// up containing only the last set of received headers.
		if ($this->receivedheaders === false or $lastheader === "") {
			$this->receivedheaders = array();
			$this->receivedcookies = array();
		}

		// Store the current header, for checking next iteration
		$trimmedheader = trim($header);
		$lastheader = $trimmedheader;

		// Split the header into key and value
		if (!empty($trimmedheader) and !preg_match('/^http\/\d\.\d\s+(\d+).*/i', $trimmedheader)) {
			if ($tmpAr = array_map("trim", explode(":", $header, 2))) {
				if (strtolower($tmpAr[0]) == 'set-cookie') {

					// Ignore badly formatted cookies
					if (strpos($tmpAr[1], '=') !== false) {
						$cookiekv = explode("=", $tmpAr[1], 2);
						$cookieparts = explode(";", $cookiekv[1]);
						$this->receivedcookies[$cookiekv[0]] = $cookieparts[0];
					}
				} elseif (isset($tmpAr[0]) and isset($tmpAr[1])) {
					$this->receivedheaders[strtolower($tmpAr[0])] = $tmpAr[1];
				}
			}
		}

		// Append the raw header to the full received response
		$this->receivedfullresponse .= $header;

		// CURL requires this callback to return the header length
		return strlen($header);
	}

	/**
	 * Callback function that is called for every line in the
	 * body of the response.  Must return the character length
	 * of the header for cURL to work properly.
	 *
	 * @param resource $handle     the curl handle for the request
	 * @param string   $lineofbody the line of the body
	 * @return integer length of line of body
	 */
	private function readBodyCallback($handle, $lineofbody) {

		// CURL passes $handle into this callback, but it is not required by this method.  State this for standards compliance
		$handle;

		// Append the line of the body to the received body
		// and full-response.
		$this->receivedbody .= $lineofbody;
		$this->receivedfullresponse .= $lineofbody;

		// CURL requires this callback to return the line length
		return strlen($lineofbody);
	}


	/**
	 * Register an object for handling requests and issuing 'mock' responses
	 *
	 * @param object $mockServer Instance of any object which implements the HTTPMockResponseCallback interface (ie. one which contains the ::sendMockResponse() method)
	 * @return void
	 */
	public static function setMockServer(HTTPMockResponseCallback $mockServer) {
		static::$mockServer = $mockServer;
	}


	/**
	 * Unregister any previously-set 'mock' request handler, allowing future requests to be sent for real through cURL
	 *
	 * @return void
	 */
	public static function unsetMockServer() {
		static::$mockServer = null;
	}


	/**
	 * Execute the request
	 *
	 * @return HTTPResponse
	 */
	public function send() {

		if (empty($this->url)) throw new InvalidCallException("URL not set", get_defined_vars());
		$thisurl = $this->url;

		// Check for a registered mock server
		if (self::$mockServer) {

			// Set default callback parameters
			$statuscode = 200;
			$headers = array();
			$totaltime = 0;

			// Execute the callback
			if (!is_null($body = self::$mockServer->sendMockResponse($this, $statuscode, $headers, $totaltime))) {

				// Generate an HTTPResponse based on the values returned by the callback
				return new HTTPResponse($headers, array(), $body, 'HTTP/1.0 ' . $statuscode . "\r\n" . implode(array_map(function ($key, $value) {
					return $key . ': ' . $value . "\r\n";
				}, array_keys($headers), $headers)) . "\r\n" . $body, $statuscode, $totaltime);
			}
		}

		if ($this->resolveto) {
			preg_match('/^https?:\/\/([^\/]*)/', $thisurl, $matches);
			if (isset($matches[1])) {
				$originalhost = $matches[1];
				$resolvetoheader = 'Host: '.$originalhost;
				$thisurl = preg_replace('/'.preg_quote($originalhost).'/', $this->resolveto, $thisurl, 1);
			}
		}
		$ch = curl_init($thisurl);

		curl_setopt_array($ch, array (
			CURLOPT_RETURNTRANSFER => false,
			CURLOPT_FOLLOWLOCATION => $this->followlocation,
			CURLOPT_HEADER => false,
			CURLOPT_TIMEOUT => $this->timelimit,
			CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
			CURLOPT_HEADERFUNCTION => array($this, "readHeaderCallback"),
			CURLOPT_WRITEFUNCTION => array($this, "readBodyCallback")
 		));
		if (!empty($this->allowSslErrors)) curl_setopt_array($ch, array (
			CURLOPT_SSL_VERIFYHOST => false,
			CURLOPT_SSL_VERIFYPEER => false,
 		));

 		// COMPLEX:MCG:20120713: With CURLOPT_CUSTOMREQUEST set to 'HEAD' curl will wait for the number of bytes stated in the Content-Length header before closing the connection. This is a problem with requests to ft.com because on receiving a HEAD request, the FT's web servers will send a Content-Length header but never send the body or close the connection, hanging until the timeout is exceeded then failing with a timeout error. In any case the proper way to make a HEAD request is to specify CURLOPT_NOBODY, which implicitly sets the method to 'HEAD' and closes the connection forcibly as soon as the headers are received.
 		if ($this->method == 'HEAD') curl_setopt($ch, CURLOPT_NOBODY, true);
 		else curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $this->method);

		// RB:COMPLEX:20110712: Currently the live (and developer) servers appear to favour
		// IPv6 over IPv4 when resolving hosts, but can't connect.  This means that various services
		// which advertise IPv6 hostnames - eg bit.ly - will have all their IPv6 hostnames tried
		// before falling back to IPv4, causing slowness.  To prevent this we currently require
		// IPv4.  This will probably require review in future.
		curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);

		if ($this->usegzip === true) curl_setopt($ch, CURLOPT_ENCODING, "gzip");
		if (!empty($this->interface)) curl_setopt($ch, CURLOPT_INTERFACE, $this->interface);
		$headers = array();
		if (!empty($this->header)) {
			$headers = $this->header;
		}
		if (!empty($resolvetoheader)) {
			$headers[] = $resolvetoheader;
		}
		if (!empty($this->postData)) {
			if ($this->method == 'GET') {
				if (!is_array($this->postData)) throw new InvalidCallException('GET request with a content body is not allowed');
				$url = parse_url($this->url);
				$data = http_build_query($this->postData, '', '&');
				$qs = empty($url['query']) ? $data : $url['query']."&".$data;
				$newurl = $url['scheme']."://".(empty($url['user'])?'':$url['user'].":".$url['pass']."@") . $url['host'] . $url['path'] . "?" . $qs . (empty($url['fragment'])?'':'#'.$url['fragment']);
				$this->url = $newurl;
				curl_setopt($ch, CURLOPT_URL, $newurl);

			} elseif ($this->method == 'POST') {
				$data = ($this->posttype == 'form') ? http_build_query($this->postData) : $this->postData;
				curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
			} else {
				if (!is_scalar($this->postData)) throw new InvalidCallException('Only GET and POST requests can be made with array parameters - data must be a single item in the request body for all other HTTP verbs');
				curl_setopt($ch, CURLOPT_POSTFIELDS, $this->postData);
			}

		// RB:COMPLEX:20140701: Curl doesn't add a content-length by default if there is no
		// body to POST, which some servers and Akamai require, so add one, and enforce
		// HTTP version 1.0 for use with Expect: 100
		} elseif ($this->method == 'POST') {
			$headers[] = 'Content-Length: 0';
			curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_0);
		}

		if (count($headers)) curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

		if (!empty($this->authmethod)) {
			if ($this->authmethod == 'basic') {
				curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
				curl_setopt($ch, CURLOPT_USERPWD, $this->authUsername.":".$this->authPassword);
			}
		}
		if ($this->useCookies) {
			curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookiePath);
			curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookiePath);
		}

		// Clear previous request data
		$this->resp = null;
		$this->receivedheaders = false;
		$this->receivedbody = '';
		$this->receivedfullresponse = '';

		// Make the request
		for ($ii = 0; $ii <= $this->maxretries; $ii++) {
			$resp = curl_exec($ch);
			if ($resp) {
				$statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

				// Retry if proxy reports gateway error (presumably they're temporary and/or load-balancing will reach a working back-end next time)
				if ($statusCode != 502 && $statusCode != 504) {
					break;
				}
			}
			if ($ii < $this->maxretries) sleep($this->retryinterval);
		}
		if (!$resp) {

			// Get the most recent curl error for debug purposes
			// (it will be captured by get_defined_vars() when we throw
			// an exception)
			$curlerror = curl_error($ch);

			// Use the error number for comparison, comparing the error message output may be unsuitable if they change in future versions.
			$errno = curl_errno($ch);

			// If it's a timeout, throw a specific exception
			if ($errno === 28) {
				throw new HTTPRequestTimeoutException('HTTP request timed out', array('eh:caller'=>1, 'context'=>get_defined_vars()));
			}

			// If it's an empty reply, throw a specific exception
			if ($errno === 52) {
				throw new HTTPRequestException('Empty reply from server', array('eh:caller'=>1, 'context'=>get_defined_vars()));
			}

			// Otherwise throw a generic exception
			throw new HTTPRequestException($curlerror, array('eh:caller'=>1, 'context'=>get_defined_vars()));
		}

		// Build the response:
		$this->resp = new HTTPResponse($this->receivedheaders, $this->receivedcookies, $this->receivedbody, $this->receivedfullresponse, $statusCode, curl_getinfo($ch, CURLINFO_TOTAL_TIME), $this);
		curl_close($ch);
		return $this->resp;
	}

	/**
	 * Get the response from the last request
	 *
	 * @return HTTPresponse Returned response from the cURL or null if a send has not yet been made
	 */
	public function getResponse() {
		return $this->resp;
	}

	/**
	 * Destructor: clean up by deleting cookie files used in the request
	 */
	function __destruct() {
		if (preg_match("/^((\/tmp\/)([a-f0-9]{13}))$/", $this->cookiePath)) {
			if (is_writable($this->cookiePath)) unlink($this->cookiePath);
		}
	}


	/* Convenience methods */

	public function doXmlRpc() {
		$args = func_get_args();
		$method = array_shift($args);
		$this->setRequestBody(xmlrpc_encode_request($method, $args));
		$this->setMethod("POST");
		$this->setHeader("Content-Type", "text/xml");
		$resp = $this->send();
		return $resp->getData('xmlrpc');
	}



	/* Deprecated functions */

	public function setAuthDetails() { trigger_error('Deprecated', E_USER_DEPRECATED); }
}
