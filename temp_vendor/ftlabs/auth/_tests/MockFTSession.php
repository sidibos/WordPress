<?php

class MockFTSession {

	private $eid;
	private $data;
	private $sessionblacklist;

	private $SESSION;

	/**
	 * Constructor, retrieves the session from memcached
	 *
	 * @param int $eid Erights ID of the user whose session should be fetched
	 * @return FTSession
	 */
	public function __construct($eid, $data, $blacklist) {
		$this->eid = $eid;
		$this->data = &$data;
		$this->sessionblacklist = $blacklist;
	}

	/**
	 * Destructor, saves the session back to memcache if appropriate
	 *
	 * @return void
	 */
	public function __destruct() {
	}

	/**
	 * In mock does nothing.
	 *
	 * @return void
	 */
	public function showdebug() {
	}

	/**
	 * Returns the Erights ID of the active user
	 *
	 * @return int
	 */
	public function getEID() {
		return $this->eid;
	}

	/**
	 * Returns a reference to the contents of the session
	 *
	 * @return array
	 */
	public function &getSessionVar() {
		return $this->data;
	}

	/**
	 * Returns true if the specified local session ID is banned from using this shared session as it's active session
	 *
	 * @param string $sid Session identifier
	 * @return boolean
	 */
	public function isBanned($sid) {
		return in_array($sid, $this->sessionblacklist);
	}

	/**
	 * Sets this session to be the active session
	 *
	 * An FTSession is not necessarily attached to the current user's request.  setActive is used to bind an FTSession instance to a specific PHP session (ie a browser instance).
	 *
	 * If FTSession::getSession() is called with no parameter, the active session will be returned.
	 *
	 * @return void
	 */
	public function setActive() {
	}

}