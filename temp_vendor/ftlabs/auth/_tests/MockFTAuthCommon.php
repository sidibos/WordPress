<?php

use FTLabs\FTAuthCommon;

/**
 * Mocks the FTAuthCommonV2 subtype, which is in turn a subtype of FTAuthCommon.
 *
 * Many of these methods override functionality in the supertypes.  This means we are able to mock out actual HTTPRequests and replace getDatabase with a method that returns a Connection to a mocked database etc.
 *
 * @copyright The Financial Times Limited [All Rights Reserved]
 */
class MockFTAuthCommon extends FTAuthCommon {
	private $nextBody;
	private $mockDbConn = false;
	private $nextStatus;
	private $session = false;
	private $trusted = false;
	private $hasRedirected = false;
	private $throwAuthException = false;

	/**
	 * Get the string representation of the class name that is used to instantiate a 'User'
	 */
	public function getUserClass() {
		return $this->userclass;
	}

	/* The following two methods are ideal candidates for separating into some sort of authentication object instead of being mashed into Common */

	/**
	 * Internally invokes getEidFromResponse This will also set the 'authStatus' variable as a side effect.
	 *
	 * @param  string   $xml The XML 'response' to get the EID from.
	 * @return mixed The erights id.
	 */
	public function getEidFromXML($xml) {
		return $this->getEidFromResponse($xml, 200);
	}

	/**
	 * Try authenticate a user via Cookie Data.
	 *
	 * Internally invokes authenticateWithCookies.
	 */
	public function cookieAuth(&$user, $cookieData) {
		$this->authenticateWithCookies($user, $cookieData);
	}

	/**
	 * Set the response status and XML of the next authentication request.
	 */
	public function setNextAuthResponse($body, $status) {
		$this->nextBody = $body;
		$this->nextStatus = $status;
	}

	/**
	 * Overrides default auth request.  The default makes an actual request.  This simply returns the mock response set using 'setNextAuthResponse'
	 */
	protected function authRequest() {
		if ($this->throwAuthException) {
			throw new Exception("HTTP request timed out.");
		}

		return array('body' => $this->nextBody, 'status' => $this->nextStatus);
	}

	public function throwExceptionInAuthRequest($value) {
		$this->throwAuthException = !!$value;
	}

	/**
	 * Override getFTSession.  This is so we aren't dealing with any real sessions in the UNIT test.
	 */
	public function &getFTSession($eid = false) {
		if (!$this->session) {

			//TODO
			$this->session = new MockFTSession($eid, array(), array());
		}

		return $this->session;
	}

	/**
	 * Set the host as trusted or not trusted.
	 *
	 * @param boolean $value The call to isTrustedHost will return this value.
	 */
	public function isTrusted($value) {
		$this->trusted = $value;
	}

	/**
	 * Override the defaul isTrustedHost, this will instead return a boolean value set by isTrusted (or false by default)
	 */
	public function isTrustedHost() {
		return $this->trusted;
	}

	/**
	 * Override the default barrierRedirect.  The default emits a redirect header and then 'exit'.
	 * This method allows us to record if the barrierRedirect was invoked and not actually exit, as that wouldn't be testable.
	 */
	public function barrierRedirect() {
		$this->hasRedirected = true;
	}

	/**
	 * Gets if the barrier redirect has been invoked.
	 *
	 * @return boolean True if the barrierRedirect was invoked.
	 */
	public function getHasRedirected() {
		return $this->hasRedirected;
	}

	/**
	 * Override default.
	 */
	public function cancelActiveSession() {
	}

	public function clearSession($reason = false) {}
}
