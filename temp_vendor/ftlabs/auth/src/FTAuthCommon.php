<?php
/**
 * Implements FTAuthV1 as an instantiable object.
 *
 * REVIEW:SG:20130207: This still requires some heavy refactoring as this 'Common' class is doing a whole lot of stuff that should probably be separated.
 *
 * @codingstandard ftlabs-phpcs
 * @copyright The Financial Times Limited [All Rights Reserved]
 */

namespace FTLabs;

use DateTime;

// COMPLEX:SH:20141211: Note that although importing \Exception
// without an alias does work as desired here, it will conflict
// with any project that makes use of FTLabs\Exception.
use Exception as BaseException;

use FTLabs\Common\CommonV3;
use FTLabs\HTTPRequest;
use FTLabs\KnownHost\KnownHostV1;
use FTLabs\Memcache;

/**
 * FTAuthCommon contains the logic for authentication with the FTs DAM.
 *
 * REVIEW:SG:20130207: This still requires some heavy refactoring as this 'Common' class is doing a whole lot of stuff that should probably be separated.
 *
 * @copyright The Financial Times Limited [All Rights Reserved]
 */
class FTAuthCommon {
	const MODE_COOKIE = 0;
	const MODE_PASSWORD = 1;

	const STATUS_APIERROR = -1;
	const STATUS_SUCCESS = 0;
	const STATUS_INVALIDCREDENTIALS = 1;
	const STATUS_INACTIVEACCOUNT = 2;
	const STATUS_USERNOTFOUND = 3;
	const STATUS_REGDOWN = 4;


	protected $isCached = false;
	protected $user = null;
	protected $authmethod = null;
	protected $loginmode = 0;
	protected $username = null;
	protected $password = null;
	protected $redirectsenabled = true;
	protected $userclass = 'FTLabs\\FTUser';
	protected $authstatus = null;
	protected $cookiesoverride = null;

	private $logger = false;

	protected $knownhost;

	protected $memcache = false;
	protected $oacDatabase = null;

	protected $useSession = true;

	// REVIEW:SG:20130207: This _could_ be globally overridden. I've left this here in case it is relied up on. Is it relied upon anywhere?
	public static $environments = array(
		'test' => array(
			'dam.ft.com' => '212.62.10.58',
			'registration.ft.com' => '212.62.10.59',
		),
		'int' => array(
			'dam.ft.com' => '213.216.149.96',
			'registration.ft.com' => '213.216.149.89',
		),
	);

	/**
	 * This method is to get the 'old' version number that was associated with these classes for logging purposes.
	 *
	 * @return integer The version number of this instance.
	 */
	public function getVersion() { return 2; }

	/**
	 * Sets the mode to use to authenticate the user
	 *
	 * Sets the mode to use to authenticate the user, if a fresh authentication is required (user will be pulled from cache regardless of this setting, but if they are not cached, then this setting determines how they will be authenticated).  Options are cookie based (FTAuth::MODE_COOKIE) which will read FT's SSO cookies and verify themm against the Assanka OAC DB and if necessary against DAM, or password based (FTAuth::MODE_PASSWORD) which will use supplied credentials (see FTAuth::setCredentials) to authenticate the user against the FT's remote login service.
	 *
	 * @param integer $loginmode The login mode desired - one of FTAuth::MODE_PASSWORD and FTAuth::MODE_COOKIE
	 * @return void
	 */
	public function setLoginMode($loginmode) {
		if ($loginmode != self::MODE_COOKIE and $loginmode != self::MODE_PASSWORD) {
			trigger_error('Invalid mode', E_USER_ERROR);
		}

		$this->loginmode = $loginmode;
	}

	/**
	 * Sets the credentials needed to log in a user via the password mode
	 *
	 * @param string $username The user's FT username or email address
	 * @param string $password The user's FT account password
	 * @return void
	 */
	public function setCredentials($username, $password) {
		$this->username = $username;
		$this->password = $password;
	}

	/**
	 * Override the normal cookies (mainly for testing)
	 *
	 * @param array $cookies Array of cookie key/value pairs to override $_COOKIE (or null to cancel a previous override and return to reading $_COOKIE)
	 * @return void
	 */
	public function setCookies($cookies) {
		$this->cookiesoverride = ($cookies and is_array($cookies)) ? $cookies : null;
	}

	/**
	 * Gets a handle for a session.
	 *
	 * If an Erights ID is supplied, returns an FTSession instance
	 * for that EID.  Otherwise reads the PHP session for the active
	 * user, and if it contains a flag linking it to an EID session,
	 * returns the FTSession instance for that EID.  Otherwise
	 * returns an instance for the anonymous PHP Session.
	 *
	 * @param int $eid Erights ID of user session required
	 * @return FTSession FTSession object instance
	 */
	public function &getFTSession($eid = false) {
		return FTSession::getSession($eid);
	}

	/**
	 * Detach this user's session from the PHP session, so there is no active session
	 *
	 * Session data is retained, and the shared session still exists, but the PHP session is no longer attached to it.
	 *
	 * @return void
	 */
	public function cancelActiveSession() {
		FTSession::cancelActive();
	}

	/**
	 * Attempt to authenticate a user against the FTs DAM given: username and password
	 *
	 * @param FTUser &$user Reference to an FTUser object.  If authenticated, this will be populated with an authenticated user object.
	 * @return boolean Returns true on successful authentication, false if unsuccessful.
	 */
	protected function tryAuthenticate(&$user) {

		try {

			// Make authentication request.
			$response = $this->authRequest();
		} catch (BaseException $e) {

			// Authentication request failed.
			return false;
		}

		$status = $response['status'];
		$body = $response['body'];

		if ($status != '200') {
			$this->authstatus = array("status" => self::STATUS_APIERROR);
			$this->authLog(array('username' => $this->username, 'status' => 'http_'.$status));
			return false;
		}

		$eid = $this->getEidFromResponse($body, $status);

		if ($eid === false) {
			return false;
		}

		$user = $this->createUser($eid);

		if (!$user->load()) {
			$this->authLog(array('eid' => $eid, 'username' => $this->username, 'method' => 'password', 'status' => 'ok'));
			$this->authstatus = array("status" => self::STATUS_USERNOTFOUND);
			$user = false;

			return false;
		}


		$this->authLog(array('eid' => $eid, 'username' => $this->username, 'method' => 'password', 'status' => 'ok'));
		$this->authmethod = 'password';
		$this->authstatus = array("status" => self::STATUS_SUCCESS);

		return true;
	}

	protected function getEidFromResponse($responseBody, $responseStatus) {
		$xml = CommonV3::xml2array($responseBody);


		/*

		Response format:

		See Unit Tests for examples.

		Possible values for new XML nodes:
		response: success | failure
		reason: invalid-credentials | inactive-account | ok | error
		message: String with a debug message
		username: the parameter name sent with the request.
		account: XML node with the account data for the user.
		active-products: list of active products for the user. This list could be empty
		product: An individual product name
		eid: Same as before
		pid: Same as before

		Jon Furse will update Merlin to have a record of the agreed functionality.


		Some examples:
		Example URL:
		/registration/login/mobile/login, with the body containing name=user@local.com&password=password sent via POST

		Failure: missing parameter password
		< ?xml version="1.0" encoding="UTF-8"? >
		<ft-login version="1.0">
		  <response>failure</response>
		   <reason>error</reason>
		   <message>password is empty or null</message>
		  <username>user@local.com</username>
		</ft-login>

		Failure: invalid Username/password.
		<?xml version="1.0" encoding="UTF-8"? >
		<ft-login version="1.0">
		  <response>failure</response>
		  <reason>invalid-credentials</reason>
		  <message>account is null for given credentials</message>
		  <username>user@local.com</username>
		</ft-login>

		Failure: inactive account.
		<?xml version="1.0" encoding="UTF-8"? >
		<ft-login version="1.0">
		  <response>failure</response>
		  <reason>inactive-account</reason>
		  <message>account belongs to holding group</message>
		  <username>user@local.com</username>
		</ft-login>


		Successful login.
		<?xml version="1.0" encoding="UTF-8"? >
		<ft-login version="1.0">
		  <response>success</response>
		  <reason>ok</reason>
		  <message>Request OK</message>
		  <username>user@local.com</username>
		  <account>
			 <active-products>
				<product>P0</product>
				<product>Tools</product>
			 </active-products>
			 <username>user@local.com</username>
			 <eid>11223344</eid>
			 <pid>88776655</pid>
		   </account>
		</ft-login>

		Successful login, no free clicks available. Active product P0 will disappear.
		<?xml version="1.0" encoding="UTF-8"? >
		<ft-login version="1.0">
		  <response>success</response>
		  <reason>ok</reason>
		  <message>Request OK</message>
		  <username>user@local.com</username>
		  <account>
			 <active-products>
				<product>Tools</product>
			 </active-products>
			 <username>user@local.com</username>
			 <eid>11223344</eid>
			 <pid>88776655</pid>
		   </account>
		</ft-login>
		*/

		if (!$xml) {
			$this->authstatus = array("status" => self::STATUS_APIERROR);
			$this->authLog(array('username' => $this->username, 'status' => 'http_'.$responseStatus));

			return false;
		}

		if ($xml['response']['value'] == 'failure') {
			switch ($xml['reason']['value']) {
				case "invalid-credentials":
					$this->authstatus = array("status" => self::STATUS_INVALIDCREDENTIALS);
					break;
				case "inactive-account":
					$this->authstatus = array("status" => self::STATUS_INACTIVEACCOUNT, "username" => $xml['username']['value']);
					break;
				default:
					$this->authstatus = array("status" => self::STATUS_APIERROR);
					break;
			}

			$this->authLog(array('username' => $this->username, 'status' => 'wrongpassword'));

			return false;
		}

		if ($xml['response']['value'] != 'success' or empty($xml['account']['eid']['value'])) {
			$this->authstatus = array("status" => self::STATUS_APIERROR);
			$this->authLog(array('username' => $this->username, 'respbody' => $responseBody));

			return false;
		}


		if ($xml['reason']['value'] != "ok") {
			trigger_error("Unexpected success reason: ".$xml['reason']['value'], E_USER_NOTICE);
			return false;
		}

		return $xml['account']['eid']['value'];
	}

	/**
	 * Make an authentication request to registration.ft.com.
	 *
	 * @return mixed Returns an array containing the response body, and response status if successful.
	 * @throws Exception If The http request fails in any way.
	 */
	protected function authRequest() {

		// Assemble the HTTP request
		$http = new HTTPRequest('https://registration.ft.com/registration/login/mobile/login');
		$http->setMethod('POST');
		$http->setRequestBody('name='.rawurlencode($this->username).'&password='. rawurlencode($this->password));

		// REVIEW:SG:20130206: Ideally we don't want to leave environments globally static..
		if (isset($_SERVER['DAMENVIRONMENT']) and isset(self::$environments[$_SERVER['DAMENVIRONMENT']])) {
			$http->resolveTo(self::$environments[$_SERVER['DAMENVIRONMENT']]['registration.ft.com']);
		}

		$http->setTimeLimit(30);
		$http->allowSslCertErrors();

		// Perform request
		// $this->authLog("FTUSER:PASSWORDLOGIN:CURL:".$http->getCliEquiv())
		try {
			$resp = $http->send();
		} catch (BaseException $e) {

			if ($e->getMessage() == "HTTP request timed out" or $e->getMessage() == "Empty reply from server") {
				$this->authLog(array('username' => $this->username, 'status' => 'timeout'));
			} elseif (strpos($e->getMessage(), "Failure executing cURL command") === 0) {
				$this->authLog(array('username' => $this->username, 'status' => 'curlerror'));
			}

			throw $e;
		}

		$body = $resp->getBody();
		$status = $resp->getResponseStatusCode();

		return array('body' => $body, 'status' => $status);
	}

	protected function authenticateDirectly(&$user) {
		if (!$this->username or !$this->password) {
			return false;
		}

		// Check memcache for registration.ft.com status - if the key is set and suggests
		// registration is down, leave the user unauthenticated.
		$memcache = $this->getMemcache();
		$statekey = 'ftco/registration/state';
		$statearray = $memcache->getMulti(array($statekey));

		if (isset($statearray[$statekey]) and empty($statearray[$statekey])) {
			$this->authLog(array('username' => $this->username, 'status' => 'regdown'));
			$this->authstatus = array("status" => self::STATUS_REGDOWN);
			return false;
		}

		return $this->tryAuthenticate($user);
	}

	protected function authenticateWithCookies(&$user, $cookieData) {

		// Has eid: look up user
		if (!$user and $cookieData['eid']) {

			$user = $this->createUser($cookieData['eid']);
			$user->importDataFrom($cookieData);

			if ($user->load()) {
				$this->authLog(array('eid' => $cookieData['eid'], 'status' => 'ok', 'method' => 'standard'));
				$this->authmethod = 'standard';
			} else {
				$this->authLog(array('eid' => $cookieData['eid'], 'status' => 'unknownuser'));
				$this->authstatus = array("status" => self::STATUS_USERNOTFOUND);
				$user = false;
			}
		}
	}

	/**
	 * Redirects the user to the FT Reg barrier page.
	 *
	 * Warning: This method calls 'exit'
	 *
	 * @return undefined This method will never return and will exit the PHP process.
	 */
	public function barrierRedirect() {
		header("Location: http://registration.ft.com/registration/barrier?location=" . rawurlencode("http://".$_SERVER["HTTP_HOST"].$_SERVER["REQUEST_URI"]));
		exit;
	}

	/**
	 * This method is called before authentication begins.
	 *
	 * @param boolean $forcereauth The force reauth parameter that was passed in from the authenticate method. @see FTAuthCommon::authenticate();
	 * @return void
	 */
	public function preAuthentication($forcereauth = false) {
		if (isset($_SERVER['HTTP_DAMENVIRONMENT']) and !isset($_SERVER['DAMENVIRONMENT'])) {
			$_SERVER['DAMENVIRONMENT'] = $_SERVER['HTTP_DAMENVIRONMENT'];
		}

		FTSession::setMemcacheIfNotYetSet($this->getMemcache());
	}

	/**
	 * Authenticates the current user.  Returns an FTUser (or the nominated user class which extends FTUser), or null if no user can be authenticated.
	 *
	 * @param bool $forcereauth Set to true to prevent the user being resurrected from session, and to force a reauthentication against cookies+DB/DAM or supplied credentials + mobile auth API.
	 * @return FTUser
	 */
	public function authenticate($forcereauth = false) {


		/*
		// REVIEW:AB:20100309: Enable for additional debug
		$bt = debug_backtrace();
		$this->authLog('Authenticate called from '.$bt[0]['file'].":".$bt[0]['line']." (".$bt[0]['function'].") ".$_SERVER['REQUEST_URI']); */

		if ($this->user) {
			return $this->user;
		}

		$this->preAuthentication($forcereauth);

		$user = false;
		$this->authstatus = false;
		$ftc = $this->getFTCookieData();
		$usingCookies = ($this->loginmode === self::MODE_COOKIE);



		// REVIEW:AB:20090720: This is the only point where the anonymous PHP session is needed - specifically in the event that a trusted user has a session and loses all their FT cookies.  If we could do without this, then we could potentially do without the PHP session handler entirely (by passing the EID into the getSession call below, and getting that from the FT_U or FT_Remember)
		if ($this->useSession) {
			$SESSION_OBJ = &$this->getFTSession();
			$SESSION_DATA = &$SESSION_OBJ->getSessionVar();

			// Allow authentication to be performed from existing session if it exists and not overridden
			if (!$forcereauth and !empty($SESSION_DATA["user"]["eid"])) {

				// User has session. If not using cookies, this can be considered valid.  If cookies are being used, check that the cookies sent with the current request match the identity of the user in the session
				if (!$usingCookies or ($ftc['eid'] == $SESSION_DATA["user"]["eid"])) {
					$this->isCached = true;
					$this->authLog(array('eid' => $ftc['eid'], 'status' => 'ok', 'method' => $this->isTrustedHost() ? 'trusted' : ''));
					$user = $this->createUser($SESSION_DATA['user']['eid']);
					$user->importDataFrom($SESSION_DATA['user']);
					$this->authmethod = 'existingsession';
				}
			}
		} else {
			$SESSION_OBJ = null;
			$SESSION_DATA = array();
		}


		/* All cases beyond this point ignore any existing session and reauthenticate the user */

		// Using cookie based login
		if ($usingCookies) {

			$this->authenticateWithCookies($user, $ftc);

		// Using direct authentication using instance variables: username and password
		} else {
			if (!$user) {
				$this->authenticateDirectly($user);
			}
		}

		// If user was authenticated, set session store correctly and return user object
		if ($user) {
			$this->user = &$user;
			if ($this->useSession and $SESSION_OBJ->getEID() != $user->get('eid')) {
				$USER_SESSION_OBJ = &$this->getFTSession($user->get('eid'));
				$USER_SESSION_OBJ->setActive();
				$USER_SESSION_DATA = &$USER_SESSION_OBJ->getSessionVar();
				$USER_SESSION_DATA['user'] = $user->getAll();
			}

			// Check that the user object has all data required for its class (Scenario: User has been authenticated using the JS auth service, as a standard FTUser, and is now accessing a service that extends their profile, eg FTLiveUser.  Because they already have a session, this authenticate method will have resurrected the user object from the session, and it will be incomplete because it has not populated itself from the database, which will now include the extended profile database as well).  Therefore, the FTUser must check that it is fully populated and if not, call its own load() method.
			$user->checkData();
			$this->authstatus = array("status" => self::STATUS_SUCCESS);
			return $user;
		}

		// If user was not authenticated, write to log if user has just logged out (they had an existing session but no longer have an FT_U)
		if (!empty($SESSION_DATA["user"]["eid"])) {
			return $this->clearSession("eid:".$SESSION_DATA["user"]["eid"]." method:logout status:signedout");
		}

		// Also write to log if user had an EID, but could not be authenticated
		if ($ftc['eid']) {
			return $this->clearSession("FTAUTH:FAIL:".$ftc['eid']);
		}

		return false;
	}

	/**
	 * Factory method for creating FTUserV2s
	 * from this class
	 *
	 * This override instantiates an FTUserV2.  Additionally it calls: FTSessionV2::setMemcacheIfNotYetSet(1);
	 *
	 * @param integer $eid      the erights ID of the user
	 * @param boolean $autoload whether or not to automatically load the user's details from the database
	 * @return FTUserV2
	 */
	public function createUser($eid, $autoload = false) {

		FTSession::setMemcacheIfNotYetSet($this->getMemcache());
		return new $this->userclass($eid, $autoload);
	}

	/**
	 * Retrieve data from authentication related FT cookies
	 *
	 * Reads the FT_Remember and FT_U cookies looking for EID and PID parameters, which map internally to eid and passportid keys.  Data is returned as an associative array with exactly three keys.  Null values are populated where the value cannot be determined from the cookie or the cookie is not present.
	 *
	 * @return array
	 */
	public function getFTCookieData() {
		$ftu = array("eid" => null, "passportid" => null);
		$cookies = ($this->cookiesoverride) ? $this->cookiesoverride : $_COOKIE;

		if (!empty($cookies["FT_U"])) {
			$ftcookie = utf8_encode($cookies["FT_U"]);
			$bits = explode("_", trim($ftcookie, "_"));
			foreach ($bits as $bit) {
				$kv = explode("=", $bit, 2);
				if (count($kv) == 2) {
					if ($kv[0] == 'EID') $ftu['eid'] = $kv[1];
					if ($kv[0] == 'PID') $ftu['passportid'] = $kv[1];
				}
			}
		}
		if (!empty($cookies["FT_Remember"])) {
			$bits = explode(":", trim(utf8_encode($cookies["FT_Remember"]), ":"));
			if (is_numeric($bits[0])) $ftu['eid'] = $bits[0];
		}
		return $ftu;
	}

	/**
	 * Returns true if the user was authenticated from a cached session
	 *
	 * @return bool
	 */
	public function isCached() {
		return $this->isCached;
	}

	/**
	 * Sets the FTUser subtype to use to represent users authenticated using this class
	 *
	 * FTUser can be extended to encompass different profile fields for each application, eg FTLiveUser.  If you wish to have FTAuth create an instance of your extended class rather than the base class FTUser when it performs an authenticate(), use this method to set the name of the class you wish to use.
	 *
	 * @param string $class Name of an FTUser-compatible class
	 * @return void
	 */
	public function setUserClass($class) {
		$this->userclass = $class;
	}

	/**
	 * Toggles the use of the PHP session handler to remember a user between script executions
	 *
	 * If the session is used (which is is by default), once a user is authenticated, their profile data will be stored in memcache against their eid, and their eid will be placed in the PHP session.  On the next call to authenticate(), the eid will be retrieved from the PHP session, and an FTUser object will be returned that has been populated from the memcache store for that EID.  If the session is not used, each authenticate() call will check the user's credentials from scratch, and will populate the FTUser from the database.
	 *
	 * @param bool $newval Whether to use the session
	 * @return void
	 */
	public function useSession($newval = true) {
		$this->useSession = $newval;
	}

	/**
	 * Clear the cached session data for the current user
	 *
	 * Clears the memcache session data store for the current EID.  Does not clear the PHP session, and probably should. (REVIEW:AB:20091215)
	 *
	 * This override clears session for FTSessionV2
	 *
	 * @param string $reason The reason for clearing the session, to be written to the authentication log
	 * @return void
	 */
	public function clearSession($reason = false) {
		if ($reason) {
			$this->authLog(array('clearsession' => $reason));
		}

		if ($this->useSession) {
			FTSession::setMemcacheIfNotYetSet($this->getMemcache());
			FTSession::cancelActive();
			$SESSION_OBJ = &FTSession::getSession();
			$SESSION_DATA = &$SESSION_OBJ->getSessionVar();
			$SESSION_DATA["user"] = null;
			unset($SESSION_DATA["user"]);
		}

		// REVIEW:SG:20130206: Is anything depending on this, it is undocumented in DocBlock
		return false;
	}

	/**
	 * Clear the user and the session.
	 *
	 * @return void
	 */
	public function clear() {
		$this->user = null;
		$this->clearSession();
	}

	/**
	 * Write to log
	 *
	 * Writes a line to the authentication log
	 *
	 * @param array $details The details to write to the log
	 * @return void
	 */
	public function authLog($details) {

		// Catch any old style FTAuthV1 logs (just in case).
		if (!is_array($details)) {
			$details = array('v1' => true, 'message' => $details);
		}

		if (!$this->logger) {
			$this->logger = new Logger('ftauth');
			$logDetails = $this->getLogDetails();
			$this->logger->setInstanceVariables(array(
				'v' =>  $this->getVersion(),
				'remote addr' => $logDetails["ip"],
				'varnish id'  => $logDetails["vid"]
			));
		}

		$this->logger->info('', $details);
	}

	protected final function getLogDetails() {
		$ip = (isset($_SERVER["REMOTE_ADDR"])) ? $_SERVER["REMOTE_ADDR"] : "Local";
		$varnish = (isset($_SERVER["HTTP_X_VARNISH"])) ? $_SERVER["HTTP_X_VARNISH"] : "-";

		return array('ip' => $ip, 'vid' => $varnish);
	}

	/**
	 * Controls where log output is directed
	 *
	 * By default log output is written to a file.  You can use this method to explicity dictate that this is the case, or switch to display log output on the screen, in which case it will simply be echoed.
	 *
	 * @param string $method One of 'file' and 'screen'
	 * @return void
	 * @deprecated
	 */
	public function setLogMethod($method) {
		trigger_error('Logger method is always \'file\'', E_USER_DEPRECATED);
	}

	/**
	 * Controls where log output is written
	 *
	 * If the log method is 'file', this method controls the file location of the file to which the output should be written.  Path should be absolute.
	 *
	 * @param string $file Filesystem path, including filename, of file to which to write log output
	 * @return void
	 * @deprecated
	 */
	public function setLogFilename($file) {
		trigger_error('File logging is handled by the logger helper', E_USER_DEPRECATED);
	}

	/**
	 * Gets the status of the last authentication attempt
	 *
	 * A call to FTAuth::authenticate will cause the authentication status to be available.
	 * This is returned as an array with at least one key-value pair, with the key 'status'.
	 * The value is a constant such as FTAuth::STATUS_USERNOTFOUND.  Certain statuses return
	 * additional information in further key-value pairs.
	 *
	 * This approach has been adopted so that logic is held centrally, and doesn't need
	 * repeating in all apps calling Authenticate.  For example, STATUS_INACTIVEACCOUNT returns
	 * an erights ID which can be used to request an activation email be re-sent to the user.
	 * We could, in this instance, return a user from Authenticate(), but would then depend on
	 * each app calling a method such as $user->hasActivatedAccount(), then de-authenticating
	 * the user if they're not
	 *
	 * @param string $key Optional key to return from the auth status (if omitted, an array of key value pairs is returned)
	 * @return mixed Either an array or a scalar value
	 */
	public function getAuthStatus($key = false) {
		return ($key) ? $this->authstatus[$key] : $this->authstatus;
	}

	/**
	 * Disables automatic redirects
	 *
	 * By default, under certain circumstances, FTAuth::authenticate() will issue an HTTP Location header with a 302 response code, and halt script execution, specifically when the user has a 'remember me' cookie and needs to be redirected to FT reg servers for a new session.  If you do not want the class to issue the redirect, call this method.  If redirects are disabled and a user cannot be authenticated without one, null will be returned from the authenticate() call.
	 *
	 * @return	void
	 */
	public function disableRedirects() {
		$this->redirectsenabled = false;
	}

	/**
	 * Clears the current user
	 *
	 * Multiple calls to authenticate() within the same script execution will simply return the user object previously created.  If you wish to authenticate multiple users within the same script, you must clear the first user's credentials first before authenticating a second one.  Note: If you simpy want to access and manipulate multiple user's profiles, then just instantiate an FTUser object directly with the EID of your choice.  FTAuth tracks the CURRENTLY AUTHENTICATED user, so this method is only useful in situations where you are manipulating cookies mid request or wish to pass a second set of username/password credentials.
	 *
	 * @return void
	 */
	public function clearCachedUser() {
		$this->user = null;
	}

	/**
	 * Returns true if the current remote host is a trusted IP, false otherwise
	 *
	 * @return bool Whether the current remote host can be trusted
	 */
	public function isTrustedHost() {
		$this->knownhost = new KnownHostV1(array("FTCO", "ASSK"));
		return $this->knownhost->isKnownHost();
	}

	/**
	 * Gets the active memcached pool
	 *
	 * @return Memcached
	 */
	public function getMemcache() {
		if (!$this->memcache) {
			$this->memcache = Memcache::getMemcache();
		}

		return $this->memcache;
	}

	public function setDatabase($database) {
		$this->oacDatabase = $database;
	}
}
