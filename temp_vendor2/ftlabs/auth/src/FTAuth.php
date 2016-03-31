<?php
/**
 * FT.com Authentication
 *
 * FTAuth is a static class that provides methods to authenticate
 * FT users based on their cookies or username and password.
 *
 * @codingstandard ftlabs-phpcs
 * @copyright The Financial Times Limited [All Rights Reserved]
 */

namespace FTLabs;

class FTAuth {
	const MODE_COOKIE = 0;
	const MODE_PASSWORD = 1;

	const STATUS_APIERROR = -1;
	const STATUS_SUCCESS = 0;
	const STATUS_INVALIDCREDENTIALS = 1;
	const STATUS_INACTIVEACCOUNT = 2;
	const STATUS_USERNOTFOUND = 3;
	const STATUS_REGDOWN = 4;

	/**
	 *
	 * @var FTAuthV1 An instance of the FTAuthV1 class.
	 */
	private static $instance = null;

	private static function getInstance() {

		if (self::$instance === null) {
			self::$instance = new FTAuthCommon();
		}

		return self::$instance;
	}

	public static $environments = array('test' => array('dam.ft.com' => '212.62.10.58', 'registration.ft.com' => '212.62.10.59'), 'int' => array('dam.ft.com' => '213.216.149.96', 'registration.ft.com' => '213.216.149.89'));

	/**
	 * Sets the mode to use to authenticate the user
	 *
	 * Sets the mode to use to authenticate the user, if a fresh authentication is required (user will be pulled from cache regardless of this setting, but if they are not cached, then this setting determines how they will be authenticated).  Options are cookie based (FTAuthV2::MODE_COOKIE) which will read FT's SSO cookies and verify themm against the Assanka OAC DB and if necessary against DAM, or password based (FTAuthV2::MODE_PASSWORD) which will use supplied credentials (see FTAuthV2::setCredentials) to authenticate the user against the FT's remote login service.
	 *
	 * @param integer $loginmode The login mode desired - one of FTAuthV2::MODE_PASSWORD and FTAuthV2::MODE_COOKIE
	 * @return void
	 */
	public static function setLoginMode($loginmode) {
		self::getInstance()->setLoginMode($loginmode);
	}

	/**
	 * Sets reconnectOnFail to true on the database connection.
	 *
	 * This must be called before using these helper methods.
	 *
	 * REVIEW:SG:20130207: This needs correct documentation to document use and maybe a potential use case.
	 *
	 * Only available in V2.
	 *
	 * @param boolean $islongprocess Set to true to initialise database connections that attempt to reconnect on failure.
	 * @return void
	 */
	public static function setIsLongProcess($islongprocess) {
		self::getInstance()->setIsLongProcess($islongprocess);
	}

	/**
	 * Sets the credentials needed to log in a user via the password mode
	 *
	 * @param string $username The user's FT username or email address
	 * @param string $password The user's FT account password
	 * @return void
	 */
	public static function setCredentials($username, $password) {
		self::getInstance()->setCredentials($username, $password);
	}

	/**
	 * Override the normal cookies (mainly for testing)
	 *
	 * @param array $cookies Array of cookie key/value pairs to override $_COOKIE (or null to cancel a previous override and return to reading $_COOKIE)
	 * @return void
	 */
	public static function setCookies($cookies) {
		self::getInstance()->setCookies($cookies);
	}

	/**
	 * Authenticates the current user.  Returns an FTUser (or the nominated user class which extends FTUser), or null if no user can be authenticated.
	 *
	 * @param bool $forcereauth Set to true to prevent the user being resurrected from session, and to force a reauthentication against cookies+DB/DAM or supplied credentials + mobile auth API.
	 * @return FTUser
	 */
	public static function authenticate($forcereauth = false) {
		return self::getInstance()->authenticate($forcereauth);
	}


	/**
	 * Retrieve data from authentication related FT cookies
	 *
	 * Reads the FT_Remember and FT_U cookies looking for EID and PID parameters, which map internally to eid and passportid keys.  Data is returned as an associative array with exactly three keys.  Null values are populated where the value cannot be determined from the cookie or the cookie is not present.
	 *
	 * @return array
	 */
	public static function getFTCookieData() {
		return self::getInstance()->getFTCookieData();
	}


	/**
	 * Returns true if the user was authenticated from a cached session
	 *
	 * @return bool
	 */
	public static function isCached() {
		return self::getInstance()->isCached();
	}


	/**
	 * Sets the FTUser-compatible class to use to represent users authenticated using this class
	 *
	 * FTUser can be extended to encompass different profile fields for each application, eg FTLiveUser.  If you wish to have FTAuth create an instance of your extended class rather than the base class FTUser when it performs an authenticate(), use this method to set the name of the class you wish to use.
	 *
	 * @param string $class Name of an FTUser-compatible class
	 * @return void
	 */
	public static function setUserClass($class) {
		self::getInstance()->setUserClass($class);
	}


	/**
	 * Toggles the use of the PHP session handler to remember a user between script executions
	 *
	 * If the session is used (which is is by default), once a user is authenticated, their profile data will be stored in memcache against their eid, and their eid will be placed in the PHP session.  On the next call to authenticate(), the eid will be retrieved from the PHP session, and an FTUser object will be returned that has been populated from the memcache store for that EID.  If the session is not used, each authenticate() call will check the user's credentials from scratch, and will populate the FTUser from the database.
	 *
	 * @param bool $newval Whether to use the session
	 * @return void
	 */
	public static function useSession($newval = true) {
		self::getInstance()->useSession($newval);
	}

	public static function clear() {
		self::getInstance()->clear();
	}


	/**
	 * Factory method for creating FTUsers
	 * from this class
	 *
	 * @param integer $eid      the erights ID of the user
	 * @param boolean $autoload whether or not to automatically load the user's details from the database
	 *
	 * @return FTUserV2
	 */
	public static function createUser($eid, $autoload = false) {
		return self::getInstance()->createUser($eid, $autoload);
	}


	/**
	 * Write to log
	 *
	 * Writes a line to the authentication log, either a file (by default), or std output, depending on the prior use of setLogMethod and setLogFilename.
	 *
	 * @param array $details The details to write to the log
	 * @return void
	 */
	public static function authLog(array $details) {
		self::getInstance()->authLog($details);
	}

	public static function setLogMethod($method) {
		trigger_error('Logger method is always \'file\'', E_USER_DEPRECATED);
	}

	public static function setLogFilename($file) {
		trigger_error('File logging is handled by the core logger helper', E_USER_DEPRECATED);
	}

	/**
	 * Gets the status of the last authentication attempt
	 *
	 * A call to FTAuthV2::authenticate will cause the authentication status to be available.
	 * This is returned as an array with at least one key-value pair, with the key 'status'.
	 * The value is a constant such as FTAuthV2::STATUS_USERNOTFOUND.  Certain statuses return
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
	public static function getAuthStatus($key = false) {
		return self::getInstance()->getAuthStatus($key);
	}


	/**
	 * Disables automatic redirects
	 *
	 * By default, under certain circumstances, FTAuthV2::authenticate() will issue an HTTP Location header with a 302 response code, and halt script execution, (See FTAuthInternalV2#barrierRedirect()) specifically when the user has a 'remember me' cookie and needs to be redirected to FT reg servers for a new session.  If you do not want the class to issue the redirect, call this method.  If redirects are disabled and a user cannot be authenticated without one, null will be returned from the authenticate() call.
	 *
	 * @return	void
	 */
	public static function disableRedirects() {
		return self::getInstance()->disableRedirects();
	}


	/**
	 * Clears the current user
	 *
	 * Multiple calls to authenticate() within the same script execution will simply return the user object previously created.  If you wish to authenticate multiple users within the same script, you must clear the first user's credentials first before authenticating a second one.  Note: If you simpy want to access and manipulate multiple user's profiles, then just instantiate an FTUser object directly with the EID of your choice.  FTAuth tracks the CURRENTLY AUTHENTICATED user, so this method is only useful in situations where you are manipulating cookies mid request or wish to pass a second set of username/password credentials.
	 *
	 * @return void
	 */
	public static function clearCachedUser() {
		self::getInstance()->clearCachedUser();
	}


	/**
	 * Returns true if the current remote host is a trusted IP, false otherwise
	 *
	 * @return bool Whether the current remote host can be trusted
	 */
	public static function isTrustedHost() {
		return self::getInstance()->isTrustedHost();
	}

	/**
	 * Loads multiple users' data from the databases
	 *
	 * @param array $eids     Array of erights IDs
	 * @param bool  $autoload Whether to automatically populate each user from the database (this is done efficiently, but will still increase the database overhead by one query per database, not per user)
	 * @return array  Array of user objects (FTUser or $userclass), keyed by erights ID
	 */
	public static function getBatchUsers($eids, $autoload=true) {
		return self::getInstance()->getBatchUsers($eids, $autoload);
	}


	/**
	 * Gets ERights IDs from usernames
	 *
	 * Accepts either a single username (as a string) or an array of usernames, and returns either a single Erights ID or an array of Erights IDs, to match the format of the input.  If an array is returned, it is keyed on the username.
	 *
	 * Note: FT usernames can CHANGE, and usernames can be reallocated to existing user accounts.  This means that at any given moment, the Assanka database may consider there to be more than one user with the same username.  In reality there is not - this results from only one of the two users authenticating since the reassignment took place.  Where we have multiple users with the same username, the Erights ID of one of the users will be returned (which should be considered a random choice).
	 *
	 * @param mixed $username A single username or an array of usernames
	 * @return mixed A single EID or an array of EIDs
	 */
	public static function getEidFromUsername($username) {
		return self::getInstance()->getEidFromUsername($username);
	}


	/**
	 * Gets EIDs from Email addresses
	 *
	 * Accepts either a single email address (as a string) or an array of email addresses, and returns either a single Erights ID or an array of Erights IDs, to match the format of the input.  If an array is returned, it is keyed on the email address.<br/><br/>
	 * Note: FT users can change their email address to an address previously used by another user.  This means that at any given moment, the Assanka database may consider there to be more than one user with the same email address.  In reality there is not - this results from only one of the two users authenticating since the reassignment took place.  Where we have multiple users with the same email address, the Erights ID of one of the users will be returned (which should be considered a random choice).
	 *
	 * @param mixed $email A single email addresss or an array of email addresses
	 * @return mixed A single EID or an array of EIDs
	 */
	public static function getEIDFromEmail($email) {
		return self::getInstance()->getEidFromEmail($email);
	}

	/**
	 * Gets FT usernames from EIDs
	 *
	 * Accepts either a single EID (as an integer or string) or an array of EIDs, and returns either a single username or an array of usernames, to match the format of the input.  If an array is returned, it is keyed on the eid.
	 *
	 * @param mixed $eid A single EID or an array of EIDs
	 * @return mixed A single username or an array of usernames
	 */
	public static function getUsernameFromEid($eid) {
		return self::getInstance()->getUsernameFromEid($eid);
	}

	/**
	 * Gets pseudonyms from EIDs
	 *
	 * Accepts either a single EID (as an integer or string) or an array of EIDs, and returns either a single pseudonym or an array of pseudonyms, to match the format of the input.  If an array is returned, it is keyed on the eid.
	 *
	 * Note: a user may not have a pseudonym.  It is an optional field.
	 *
	 * @param mixed $eid A single EID or an array of EIDs
	 * @return mixed A single pseudonym or an array of pseudonyms
	 */
	public static function getPseudonymFromEid($eid) {
		return self::getInstance()->getPseudonymFromEid($eid);
	}


	/**
	 * Returns an array of users matching a given term.  Use with extreme care, since the result set is not paginated and the dataset returned may be very large.  It will also be slower if you set autoload to true.
	 *
	 * @param string $term     Term to search for
	 * @param bool   $autoload Whether to auto-populate result users (default false)
	 *
	 * @return array Array of FTUser (or $userclass) objects
	 */
	public static function search($term, $autoload=false) {
		return self::getInstance()->search($term, $autoload);
	}
}
