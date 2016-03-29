<?php
/**
 * FT.com User session store
 *
 * Stores and provides access to memcache stored session data
 * for FT.com users.
 *
 * @codingstandard ftlabs-phpcs
 * @copyright The Financial Times Limited [All Rights Reserved]
 */

namespace FTLabs;

class FTSession {

	/**
	 * The Erights ID of the user to whom this session is attached in memcached
	 *
	 * @var int
	 * @visibility private
	 */
	private $eid;


	/**
	 * Record of the contents of the session as loaded from memcached, so that on destruct,
	 * the session is only written back to memcached if it has changed.
	 *
	 * @var array
	 * @visibility private
	 */
	private $fromMemcache;

	/**
	 * Record of the contents of the session ids as loaded from memcached, so that on destruct,
	 * the session ids are only written back to memcached if they have changed.
	 *
	 * @var array
	 * @visibility private
	 */
	private $IDsFromMemcache;

	/**
	 * Record of the contents of the session id blacklist as loaded from memcached, so that on destruct,
	 * the session id blacklist is only written back to memcached if it has changed.
	 *
	 * @var array
	 * @visibility private
	 */
	private $blacklistFromMemcache;

	/**
	 * Current session data
	 *
	 * @var array
	 * @visibility private
	 */
	private $data;

	/**
	 * Stores a list of PHP session IDs that reference this shared session
	 *
	 * @var array
	 * @visibility private
	 */
	private $localsessions = array();

	/**
	 * Stores a list of PHP session IDs that previously referenced this shared session and are now banned due to the concurrency limit
	 *
	 * @var array
	 * @visibility private
	 */
	private $sessionblacklist = array();

	/**
	 * Stores the maximum number of concurrent browser sessions to allow access to the shared session.  A browser session is created every time a browser with an independent cookie store logs in and creates a new PHP session ID.  A user logging in using two different browsers will end up with two different PHP sessions pointing at the same shared session.  Setting a maximum value ensures that no more than the specified number of PHP sessions may be referring to the same shared session at the same time, which avoids users sharing their credentials with a large number of other people.
	 *
	 * @var integer
	 * @visibility private
	 */
	private static $maxconcurrent = 0;


	/**
	 * A memcache object for storing data
	 *
	 * @var object
	 * @visibility private
	 */
	private static $memcache;


	/**
	 * Stores references for each user session instance and the anonymous session - ensures that there is only
	 * ever one instance of FTSession which accesses the anonymous session.
	 *
	 * @var array
	 * @visibility private
	 */
	private static $instances;

	const MEMCACHETTL = 86400;

	/**
	 * Constructor, retrieves the session from memcached
	 *
	 * @param int $eid Erights ID of the user whose session should be fetched
	 * @return FTSession
	 */
	private function __construct($eid=false) {
		if ($eid) {
			if (empty(self::$memcache)) {
				throw new AssankaException("If you provide an eRights ID you should have set a static memcache object in the FTsession class", 0, null, get_defined_vars());
			}
			$this->fromMemcache = self::$memcache->get('ftsession_' . $eid);
			if (!$this->fromMemcache) $this->fromMemcache = array();
			$this->data = $this->fromMemcache;
			$this->IDsFromMemcache = self::$memcache->get('ftsession_' . $eid.'_sessids');
			if (!$this->IDsFromMemcache) $this->IDsFromMemcache = array();
			$this->localsessions = $this->IDsFromMemcache;
			$this->blacklistFromMemcache = self::$memcache->get('ftsession_' . $eid.'_badsessids');
			if (!$this->blacklistFromMemcache) $this->blacklistFromMemcache = array();
			$this->sessionblacklist = $this->blacklistFromMemcache;
			$this->eid = $eid;
		} else {
			if (!isset($_SESSION)) session_start();
			$this->data = &$_SESSION['ftsession'];
		}
	}

	/**
	 * Destructor, saves the session back to memcache if appropriate
	 *
	 * @return void
	 */
	public function __destruct() {
		if (!empty($this->eid) and $this->data != $this->fromMemcache) {
			self::$memcache->set('ftsession_' . $this->eid, $this->data, self::MEMCACHETTL);
		}
		if (!empty($this->eid) and $this->localsessions != $this->IDsFromMemcache) {
			self::$memcache->set('ftsession_' . $this->eid . '_sessids', $this->localsessions, self::MEMCACHETTL);
		}
		if (!empty($this->eid) and $this->sessionblacklist != $this->blacklistFromMemcache) {
			self::$memcache->set('ftsession_' . $this->eid . '_badsessids', $this->sessionblacklist, self::MEMCACHETTL);
		}
		self::$instances[$this->eid] = null;
	}

	/**
	 * Prints the contents of the current session to stdout
	 *
	 * @return void
	 */
	public function showdebug() {
		echo "SESSION CONTENTS FOR '".$this->eid."':\n";
		var_dump($this->data);
		echo "LOCAL SESSIONS ATTACHED: \n";
		var_dump($this->localsessions);
		echo "LOCAL SESSIONS BLACKLISTED: \n";
		var_dump($this->sessionblacklist);
		echo "CURRENT LOCAL SESSION: \n";
		var_dump(session_id());
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
		if (!isset($_SESSION)) session_start();

		// If this is the anonymous session, copy it back into the PHP session container
		if (!$this->eid) {
			$_SESSION['ftsession'] = $this->data;

		// Otherwise, use the PHP session to store a pointer to this user session
		} else {
			$_SESSION['ftsession'] = array("FTSession_EID" => $this->eid);

			// Add the PHP session ID to the local sessions list, invalidating all existing sessions if necessary
			if (!in_array(session_id(), $this->localsessions)) {
				if (self::$maxconcurrent and count($this->localsessions) == self::$maxconcurrent) {
					$this->sessionblacklist += $this->localsessions;
					$this->sessionblacklist = array_unique($this->sessionblacklist);
					$this->localsessions = array();
				}
				$this->localsessions[] = session_id();
			}
		}
	}

	/**
	 * Detach this user's session from the PHP session, so there is no active session
	 *
	 * Opposite of setActive.  Session data is retained, and the shared session still exists, but the
	 * PHP session is no longer attached to it.
	 *
	 * @return void
	 */
	public static function cancelActive() {
		if (isset($_SESSION['ftsession']["FTSession_EID"])) {
			$s = self::getSession($_SESSION['ftsession']["FTSession_EID"]);
			if ($idx = array_search(session_id(), $s->localsessions)) unset($s->localsessions[$idx]);
		}
		if (!isset($_SESSION)) session_start();
		unset($_SESSION['ftsession']);
	}

	public static function setMemcacheIfNotYetSet($memcache) {
		if (empty(self::$memcache)) {
			self::$memcache = $memcache;
		}
	}


	/**
	 * Set the maximum number of concurrent sessions permitted to access a single shared session
	 *
	 * @param integer $max New maximum number of sessions
	 * @return void
	 */
	public static function setConcurrencyLimit($max) {
		self::$maxconcurrent = $max;
	}

	/**
	 * Gets a handle for a session.

	 * If an Erights ID is supplied, returns an FTSession instance
	 * for that EID.  Otherwise reads the PHP session for the active
	 * user, and if it contains a flag linking it to an EID session,
	 * returns the FTSession instance for that EID.  Otherwise
	 * returns an instance for the anonymous PHP Session.
	 *
	 * @param int $eid Erights ID of user session required
	 * @return FTSession FTSession object instance
	 */
	public static function &getSession($eid = false) {
		if (!$eid) {
			if (!isset($_SESSION)) session_start();
			if (isset($_SESSION['ftsession']["FTSession_EID"])) $eid = $_SESSION['ftsession']["FTSession_EID"];
		}
		if ($eid) {
			if (empty(self::$memcache)) {
				throw new AssankaException("Please supply a memcache object before calling getSession", 0, null, get_defined_vars());
			}
			if (empty(self::$instances[$eid])) {
				self::$instances[$eid] = new FTSession($eid, self::$memcache);
			}
			if (!(isset($_SESSION['ftsession']["FTSession_EID"]) and self::$instances[$eid]->isBanned(session_id()))) {
				return self::$instances[$eid];
			}
		}
		if (empty(self::$instances['_anon'])) {
			self::$instances['_anon'] = new FTSession();
		}
		return self::$instances['_anon'];
	}

	/**
	 * Clears records of session instances from memory
	 * 
	 * Any long running script should call this method regularly to
	 * avoid leaking memory.  Clearing the cache means that any existing
	 * session references will be unlinked from this class, and duplicate
	 * objects may then be possible.
	 *
	 * @return void
	 */
	public static function clearObjectCache() {
		self::$instances = array();
	}
}
