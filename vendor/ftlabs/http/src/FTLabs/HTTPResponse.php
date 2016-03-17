<?php
/**
 * Access the response to an HTTP Request made with the HTTPRequest class
 *
 * @codingstandard ftlabs-phpcs
 * @copyright The Financial Times Limited [All Rights Reserved]
 */

namespace FTLabs;

class HTTPResponse {
	private $headers;
	private $body;
	private $cookies;
	private $responsetext;
	private $reqtime;
	private $statuscode;


	/**
	 * Create an HTTPResponse
	 *
	 * Creates and HTTPResponse with data from the request session.  Normally created and populated by the HTTPRequest class
	 *
	 * @param array       $headers      Key / Value array containing the response headers (excluding any Set-cookie headers)
	 * @param array       $cookies      Key / Value array containing the response cookies
	 * @param string      $body         Text of the response body
	 * @param string      $responsetext Full text of the HTTP response
	 * @param int         $statuscode   HTTP status code of the response (e.g. 200 or 404)
	 * @param float       $time         Time taken to complete the HTTP request/response, in seconds
	 * @param HTTPRequest $req          The HTTPRequest that created this response (deprecated)
	 * @return HTTPResponse
	*/
	public function __construct($headers, $cookies, $body, $responsetext, $statuscode, $time, $req = false) {
		$this->headers = $headers;
		$this->body = $body;
		$this->cookies = $cookies;
		$this->responsetext = $responsetext;
		$this->statuscode = $statuscode;
		$this->reqtime = $time;
	}

	/**
	 * Returns all headers
	 *
	 * @return array
	 */
	public function getHeaders() {
		return $this->headers;
	}

	/**
	 * Returns the value of a specified header
	 *
	 * @param string $key the key to a corresponding header
	 * @return String the header value
	 */
	public function getHeader($key) {
		$key = strtolower($key);
		return (empty($this->headers[$key])) ? false : $this->headers[$key];
	}

	/**
	 * Returns all cookies set in the response
	 *
	 * @return array
	 */
	public function getCookies() {
		return $this->cookies;
	}

	/**
	 * Returns the body of the response.
	 *
	 * @return String The body of the return (no headers)
	*/
	public function getBody() {
		return $this->body;
	}

	/**
	 * Returns the body of the response, parsed according to the content-type header given by the server.
	 *
	 * Content types supported are:
	 *
	 * application/x-www-form-urlencoded
	 * application/php
	 * application/json
	 *
	 * If the content type is not supported or not specified, the raw content body is returned.
	 *
	 * @param string $type Forces the body to be interpreted as the specified type (choose from 'json', 'php', 'urlenc')
	 * @return mixed A structured representation of the data received in the response body
	*/
	public function getData($type=false) {
		if (empty($this->headers['content-type']) and empty($type)) return $this->body;
		if (empty($type)) $type = $this->headers['content-type'];
		$type = preg_replace("/;\s*charset\s*=\s*.*$/i", "", $type);
		$data = array();
		switch ($type) {
			case 'application/x-www-form-urlencoded':
			case 'urlenc':
				$resultparams = explode('&', $this->body);
				foreach ($resultparams as $param) {
					if (strpos($param, '=') === false) {
						$data[] = urldecode($param);
					} else {
						list($key, $val) = explode('=', $param, 2);
						if (strpos($key, '[]') == (strlen($key) - 2)) {
							$key = rtrim($key, '[]');
							if (empty($data[$key]) or !is_array($data[$key])) $data[$key] = array();
							$data[$key][] = urldecode($val);
						} else {
							$data[$key] = urldecode($val);
						}
					}
				}
				break;
			case 'application/php':
			case 'php':
				$data = unserialize($this->body);
				break;
			case 'application/json':
			case 'json':
				$data = json_decode($this->body, true);
				break;
			case 'application/atom+xml':
			case 'text/xml':
			case 'xml':
				$data = self::_xml2array($this->body);
				break;
			case 'xmlrpc':
				$data = xmlrpc_decode($this->body);
				break;
			default:
				$data = $this->body;
		}
		return $data;
	}

	/**
	 * Returns the whole of the response.
	 *
	 * @return String whole of the response (body and all headers)
	*/
	public function getResponse() {
		return $this->responsetext;
	}

	/**
	 * Returns the total time required for the request and response
	 *
	 * @return float Time in seconds
	*/
	public function getResponseTime() {
		return $this->reqtime;
	}

	/**
	 * Returns the response code given by the server.
	 *
	 * @return Integer HTTP response status code
	*/
	public function getResponseStatusCode() {
		return $this->statuscode;

	}

	/**
	 * Returns the W3C response status description text for the response status returned by the server.
	 *
	 * Note that if the server returned a non-standard response status description, this is ignored.
	 *
	 * @return Integer HTTP response status code
	*/
	public function getResponseStatusDesc() {
		$desc = array("100"=>"Continue", "101"=>"Switching Protocols", "200"=>"OK", "201"=>"Created", "202"=>"Accepted", "203"=>"Non-Authoritative Information", "204"=>"No Content", "205"=>"Reset Content", "206"=>"Partial Content", "300"=>"Multiple Choices", "301"=>"Moved Permanently", "302"=>"Found", "303"=>"See Other", "304"=>"Not Modified", "305"=>"Use Proxy", "307"=>"Temporary Redirect", "400"=>"Bad Request", "401"=>"Unauthorized", "402"=>"Payment Required", "403"=>"Forbidden", "404"=>"Not Found", "405"=>"Method Not Allowed", "406"=>"Not Acceptable", "407"=>"Proxy Authentication Required", "408"=>"Request Timeout", "409"=>"Conflict", "410"=>"Gone", "411"=>"Length Required", "412"=>"Precondition Failed", "413"=>"Request Entity Too Large", "414"=>"Request-URI Too Long", "415"=>"Unsupported Media Type", "416"=>"Requested Range Not Satisfiable", "417"=>"Expectation Failed", "500"=>"Internal Server Error", "501"=>"Not Implemented", "502"=>"Bad Gateway", "503"=>"Service Unavailable", "504"=>"Gateway Timeout", "505"=>"HTTP Version Not Supported");
		return isset($desc[$this->statuscode]) ? $desc[$this->statuscode] : null;

	}

	/**
	 * Function to parse a supplied XML string and return it as an array.  Supports namespaces, and easy
	 * to use as it is an array, but for large XML files expect parsing the full XML string to take longer
	 * than accessing individual elements via SimpleXML!
	 *
	 * The array returned will use a fixed format for each XML node, including the parent, with the following
	 * array items:
	 *  - "type" - the node name
	 *  - "attributes" - an associative array of all attributes on the node.  Not present if empty.
	 *  - "children" - a numerically indexed array of all child nodes.  Not present if no child nodes.
	 *  - "value" - a string containing the text or data within the node, if any.  This is not trimmed,
	 *              but not set if the node is empty.
	 *
	 * Child nodes are also made available directly from the parent node as a shortcut; always via their
	 * numeric indices, so $array["parentnode"][0] links directly to the first child, and where the child
	 * node name doesn't conflict with the four reserved variables above, it will also be available via
	 * that key. Similarly, attributes are available via keys of that name if they do not conflict.
	 *
	 * So for the XML: <parent><child foo="bar">text </child></parent>
	 * the text can be accessed consistently via $array["parent"]["children"][0]["value"], or via
	 * the shortcuts $array["parent"][0]["value"] and $array["parent"]["child"]["value"]; the attribute
	 * can be accessed consistently via $array["parent"]["children"][0]["attributes"]["foo"], or
	 * via the shortcut $array["parent"]["child"]["foo"].
	 *
	 * @param string $xmlstring A string containing the XML to parse.
	 *
	 * @return array An array containing the parsed XML, or false if unable to parse the XML.
	 */
	private static function _xml2array($xmlstring) {

		// Load in the XML file via SimpleXML.
		$xml = @simplexml_load_string($xmlstring, 'SimpleXMLElement', LIBXML_NOCDATA);
		if (!$xml) return false;

		// Load any namespaces present within the document
		$namespaces = $xml->getNamespaces(true);
		foreach ($namespaces as $nskey=>$ns) {
			if ($nskey == '') unset($namespaces[$nskey]);
		}

		// Process the master node recusively
		$dataArray = self::_processXMLNode($xml, $namespaces);

		unset($xml);
		return $dataArray;
	}

	private static function _processXMLNode($xml, &$namespaces, $currentns = false) {

		// Set up node, including name.
		$node = array('type'=>(($currentns)?$currentns.':':'').$xml->getName());

		// Add attributes
		$nodeatts = $xml->attributes();
		if (count($nodeatts)) {
			$node['attributes'] = array();
			foreach ($nodeatts as $name=>$nodeatt) $node['attributes'][$name] = (string)$nodeatt;
		}

		// Cope with namespaced attributes
		foreach ($namespaces as $nskey=>$ns) {
			$nodeatts = $xml->attributes($ns);
			if (count($nodeatts)) {
				if (!isset($node['attributes'])) $node['attributes'] = array();
				foreach ($nodeatts as $nodeatt) $node['attributes'][] = (string)$nodeatt;
			}
		}

		// Add any children into a children array for constant iteration
		$nodechildren = $xml->children();
		$numnodechildren = count($nodechildren);
		if ($numnodechildren) {
			$node['children'] = array();

			// COMPLEX:RB:20100702: Up to Bzr revision 2133, we were using a foreach here; however the
			// foreach sometimes resulted into partial repetition (!) so a for() muse be used instead
			for ($nodecounter = 0; $nodecounter < $numnodechildren; $nodecounter++) {
				$node['children'][] = self::_processXMLNode($nodechildren[$nodecounter], $namespaces);
			}
		}

		// Cope with namespaced children
		foreach ($namespaces as $nskey=>$ns) {
			$nodechildren = $xml->children($ns);
			if (count($nodechildren)) {
				if (!isset($node['children'])) $node['children'] = array();
				foreach ($nodechildren as $childnode) $node['children'][] = self::_processXMLNode($childnode, $namespaces, $nskey);
			}
		}

		// Get the text/data value of the node, if any.
		$nodevalue = (string)$xml[0];
		if ($nodevalue !== '' and (empty($node['children']) or trim($nodevalue) !== '')) $node['value'] = $nodevalue;

		// Remap children by reference where it seems possible to do so
		$childCount = count($node['children']);
		if (!empty($node['children'])) for ($i = 0; $i < $childCount; $i++) {
			$node[$i] = &$node['children'][$i];
			$nodename = $node[$i]['type'];
			if (!isset($node[$nodename])) $node[$nodename] = &$node['children'][$i];
		}

		// Remap attributes by reference where it seems possible to do so
		if (!empty($node['attributes'])) foreach ($node['attributes'] as $key=>$value) {
			if (!isset($node[$key])) $node[$key] = &$node['attributes'][$key];
		}

		// Clean up and return.
		unset($nodechildren, $nodeatts, $nodevalue, $xml);
		return $node;
	}
}
