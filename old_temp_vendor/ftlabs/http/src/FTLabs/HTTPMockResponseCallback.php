<?php
/**
 * Intended to be implemented by unit tests, this callback method intercepts HTTP requests and may generate a 'mock' response
 *
 * @codingstandard ftlabs-phpcs
 * @copyright The Financial Times Limited [All Rights Reserved]
 */

namespace FTLabs;

interface HTTPMockResponseCallback {

	/**
	 * Intended to be implemented by unit tests, this callback method intercepts HTTP requests and may generate a 'mock' response
	 *
	 * The $statuscode, $headers and $totaltime parameters are passed into the callback by reference, enabling the callback to modify their values as desired
	 *
	 * @param HTTPRequest $request     The originating request object
	 * @param integer     &$statuscode The status code of the HTTP response (default 200)
	 * @param array       &$headers    Associative array HTTP response headers (default empty array)
	 * @param integer     &$totaltime  The total HTTP transaction time, in seconds (default 0)
	 * @return mixed                   The callback should return its HTTP response body as a string, but may also return NULL which will bypass the mock response and allow the request to be sent by cURL
	 */
	public function sendMockResponse(HTTPRequest $request, &$statuscode, array &$headers, &$totaltime);
}
