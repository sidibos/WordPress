<?php
/**
 * FT.com User
 *
 * Class to represent, manage and provide access to details
 * about an FT user.  Can be extended for specific projects
 * and local datastores to supplement the information stored
 * in the main dataset (OAC).  Implements ArrayAccess,
 * Iterator and Countable.  The internal methods required to
 * implement these interfaces are not documented, but do meet
 * the standard for the interface.
 *
 * @codingstandard ftlabs-phpcs
 * @copyright The Financial Times Limited [All Rights Reserved]
 */

namespace FTLabs;

use ArrayAccess;
use Countable;
use DateTime;
use DateTimeZone;
use Iterator;

use FTLabs\Exception;
use FTLabs\HTTPRequest;
use FTLabs\HTTPRequestException;
use FTLabs\MySqlConnectionException;

// COMPLEX:RB:20141006: Note that although importing \Exception
// without an alias does work as desired here, it will conflict
// with any project that makes use of FTLabs\Exception.
use Exception as BaseException;


class FTUser implements ArrayAccess, Countable, Iterator {

	protected $damerror;
	protected $data;
	protected $dbs;
	protected $tables;
	protected $session;
	protected $validity = 0;
	private $curlcmds = array();

	public static $enabledDAMSources = array('core', 'assanka');

	const VALIDITY_UNKNOWN = 0;
	const VALIDITY_VALID = 1;
	const VALIDITY_INVALID = -1;

	// The DAM endpoints return data based on a combination of the URL endpoint, third
	// party name, and the authentication key
	protected $damSourceConfigurations = array(
		'core' => array(
			'endpoint' => 'core',
			'thirdpartyname' => false,
			'authorization' => 'OTpDM2R1dkViRQ==',
		),
		'assanka' => array(
			'endpoint' => 'thirdparty',
			'thirdpartyname' => 'assanka',
			'authorization' => 'Mjo0NTU0bms0',
		),
		'mobile' => array(
			'endpoint' => 'thirdparty',
			'thirdpartyname' => 'assanka',
			'authorization' => 'OTpDM2R1dkViRQ==',
		)
	);

	protected $writableDamFields = array(
		'location' => 'assanka',
		'jobtitle' => 'assanka',
		'company' => 'assanka',
		'about' => 'assanka',
		'phone' => 'assanka',
		'Pseudonym' => 'assanka',
		'orgName' => 'assanka',
		'orgLocation' => 'assanka',
		'annotationsLevel' => 'assanka',
		'EMAIL' => 'assanka',
		'SUBSCRIPTIONS' => 'assanka',
		'html5devicetype' => 'mobile',
		'html5screenresolution' => 'mobile',
		'html5last5page' => 'mobile',
		'html5mainview' => 'mobile',
		'html5userlocation' => 'mobile',
		'html5adclicks' => 'mobile',
		'html5adview' => 'mobile',
		'html5appview' => 'mobile',
		'html5usetime' => 'mobile',
		'html5date' => 'mobile',
		'html5frequency' => 'mobile',
		'html5pushmarks' => 'mobile',
	);

	protected $fields = array();


	/* Note that the following fields are also present in DAM but ignored in this class
		* alertPreferences: A set of fields from WSOD to do with stock and portfolio alerts
		* lastVisit: Unknown
		* pageHits: Unknown
		* pageHitsLast10: Unknown
	*/

	/**
	 * Create an FTUser
	 *
	 * Creates a new instance from a specified erights ID, the primary key for FT users.
	 *
	 * @param int  $eid      The erights id of the user to instantiate
	 * @param bool $autoload Whether to populate the user from the database (and DAM as required) automatically.  If false, user object will be a shell.  If true, data will be loaded from the DB, and if not present, from DAM.
	 * @return FTUser
	 */
	public function __construct($eid, $autoload = false) {
		if (isset($_SERVER['HTTP_DAMENVIRONMENT']) and !isset($_SERVER['DAMENVIRONMENT'])) {
			$_SERVER['DAMENVIRONMENT'] = $_SERVER['HTTP_DAMENVIRONMENT'];
		}

		if (!is_numeric($eid) or $eid <= 0) {
			trigger_error('No erights ID supplied for this user', E_USER_ERROR);
		}

		$this->damerror = false;
		$this->dbs = array();
		$this->tables = array("oac" => "users");
		$this->data = array("eid" => $eid);
		$this->isloaded = false;
		$this->session = null;
		$this->validity = self::VALIDITY_UNKNOWN;

		if ($autoload) {
			$this->load();
		}
	}

	/**
	 * Updates the user object, first from the local database for the cached user record, otherwise makes a request to DAM
	 *
	 * @return bool Whether the user exists in either the OAC DB or DAM, and was loaded successfully
	 */
	public function load() {

		// Load the base DAM data, preferring to use cached data; if the cache is empty,
		// go back to the DAM endpoints; if those fail, fail the load.
		if (!$this->loadCachedDamData()) {
			if (!$this->updateFromDam()) {
				return false;
			}
		}

		// Load any fields configured to back onto a database
		if (!$this->loadDatabaseFields()) {
			return false;
		}

		// Mark the record as valid and return success
		$this->validity = self::VALIDITY_VALID;
		return true;
	}

	/**
	 * Load DAM data from a cache if possible.
	 *
	 * @return bool Whether the cached data was loaded successfully
	 */
	protected function loadCachedDamData() {

		// Load the base DAM data from memcache
		$cachedData = self::getNamespacedMemcache()->get($this->data['eid']);

		// If the data wasn't available in memcache, treat as a complete cache miss and fail
		if (!$cachedData) {
			return false;
		}

		$this->importDataFrom($cachedData);

		return true;
	}

	/**
	 * Makes a request to DAM for all the user's data and tries to update the local cache
	 *
	 * Queries DAM using each registered DAM source in turn.
	 *
	 * @return bool indicating whether data was retrieved from DAM.  Inability to cache results will not affect return value.
	 */
	public function updateFromDam() {
		$user = array();

		// Retrieve data from all DAM sources
		foreach (self::$enabledDAMSources as $sourceName => $source) {

			// Get the data
			$data = $this->makeDamRequest($source, false);
			if (!$data and $this->validity !== self::VALIDITY_VALID) {
				return false;
			}

			// If any DAM source fails to respond, abandon the update rather than download incomplete data
			if ($this->getDamError()) {
				return false;
			}

			// The "etag" field is used to synchronize data, and so needs to be source-specific.
			// Rename the field so it stays unique per-source
			if (isset($data['etag'])) {
				$data['etag_' . $sourceName] = $data['etag'];
				unset($data['etag']);
			}

			// Merge into the user data
			$user = array_merge($user, $data);
		}

		// Set the 'levelname' field from data in the products field
		$productData = explode(',', isset($user['products']) ? $user['products'] : '');
		if (in_array('P2', $productData) or in_array('P4', $productData)) {
			$user['levelname'] = 'premium';
		} elseif (in_array('P1', $productData)) {
			$user['levelname'] = 'subscribed';
		} elseif (in_array('P6', $productData)) {
			$user['levelname'] = 'weekend';
		} else {
			$user['levelname'] = 'registered';
		}

		// Error if no erightsId is present
		if (!isset($user['erightsId'])) {
			FTAuth::authLog(array('ftuser' => 'DAM', 'err' => 'NOEID'));
			return false;
		}

		// Error if wrong user returned by DAM
		if ($user['erightsId'] != $this->data['eid']) {
			FTAuth::authLog(array('ftuser' => 'DAM', 'err' => 'wronguser', 'requested' => $this->data['eid'], 'got' => $user['erightsId']));
			return false;
		}

		// Try to update the local cache
		$this->importDataFrom($user);
		$this->data['datelastdamdownload'] = time();
		self::getNamespacedMemcache()->set($this->data['eid'], $this->data, 60 * 60);

		return true;
	}

	/**
	 * Loads any fields configured to load from a database.  If a row does not exist for
	 * the user in that table, the data corresponding to that table will not be loaded.
	 *
	 * @return bool Whether the user details were loaded from all configured databases
	 */
	protected function loadDatabaseFields() {
		$newdata = array();

		foreach ($this->dbs as $dbname => $db) {
			$qry = 'SELECT * FROM ' . $this->tables[$dbname] . ' WHERE eid=%s';
			$row = $db['read']->queryRow($qry, $this->data['eid']);
			if ($row) {
				$newdata = array_merge($newdata, $row);
			} else {
				return false;
			}
		}

		$this->importDataFrom($newdata);

		return true;
	}

	/**
	 * Checks that all databases relevent to this user class have been queried
	 *
	 * If not, calls load().  This is useful when users have been
	 * constructed from session data that may have been created by a
	 * different user class, eg a session is started by creating an FTLiveUser,
	 * and that session is resurrected into an FTDisussionsUser
	 *
	 * @return void
	 */
	public function checkData() {
		$dbs = array_flip(array_keys($this->dbs));
		foreach ($this->fields as $k => $fieldInfo) {
			if (isset($this->data[$k]) and isset($dbs[$fieldInfo['db']])) unset($dbs[$fieldInfo['db']]);
		}
		if (count($dbs)) $this->load();
	}


	/**
	 * Makes a request to DAM in the context of the user
	 *
	 * @param string $sourceName Name of DAM data group to read/write (core, assanka, mobile etc)
	 * @param array  $newdata    Data to send in a write operation.  If omitted, performs a read operation.
	 * @return mixed Returns true or a data array on success, false on failure (and sets damerror)
	 */
	protected function makeDamRequest($sourceName, $newdata = false) {
		$this->damerror = false;
		$log = array('type' => 'dam', 'eid' => $this->data['eid'], 'src' => $sourceName);
		$sourceConfiguration = $this->damSourceConfigurations[$sourceName];

		// Assemble the HTTP request
		$http = new HTTPRequest('https://dam.ft.com/dam/e/'.$sourceConfiguration['endpoint'].'/'.$this->data['eid']);
		if (isset($_SERVER['DAMENVIRONMENT']) && isset(FTAuth::$environments[$_SERVER['DAMENVIRONMENT']])) {
			$http->resolveTo(FTAuth::$environments[$_SERVER['DAMENVIRONMENT']]['dam.ft.com']);
		}
		$http->setHeader("Authorization", $sourceConfiguration['authorization']);
		$http->allowSslCertErrors();

		// Set a time limit, and allow retries if not uploading data
		$http->setTimeLimit(4);
		if (!$newdata) {
			$http->setMaxRetries(1);
		}

		// If data to upload has been provided, add it to the request
		if ($newdata) {
			$json = json_encode(array(
				"thirdparty" => $sourceConfiguration['thirdpartyname'],
				"data" => $newdata
			));
			$http->setRequestBody($json);
			$http->setMethod('POST');
		}

		// Perform request
		$log['cmd'] = $http->getCliEquiv();
		try {
			$resp = $http->send();
			$this->curlcmds[] = array($http->getCliEquiv(), $resp->getResponseStatusCode(), $sourceName);
		} catch (HTTPRequestException $e) {
			if ($e->isConnectionFailure()) {
				FTAuth::authLog($log + array('status' => 'timeout'));
				$this->damerror = "damdown";
				return false;
			}
			$this->curlcmds[] = array($http->getCliEquiv(), 'damdown', $sourceName);
			throw $e;
		}

		// Check for invalid HTTP status codes
		if ($resp->getResponseStatusCode() == 404) {
			FTAuth::authLog($log + array('status' => 'unknownuser'));
			$this->validity = self::VALIDITY_INVALID;
			return false;
		}

		// Check for invalid HTTP status codes
		if ($resp->getResponseStatusCode() != 200 and $resp->getResponseStatusCode() != 409) {
			FTAuth::authLog($log + array('status' => 'http_' . $resp->getResponseStatusCode()));
			$this->damerror = "damdown";
			return false;
		}

		$data = $resp->getData('json');
		if ($resp->getBody() and empty($data)) {
			FTAuth::authLog($log + array('status' => 'unparsable', 'data' => str_replace("\n", '', $resp->getBody())));
			$this->damerror = "unparsable";
			return false;
		}

		// Record updated etag
		if (isset($data["etag"])) {
			$this->importDataFrom(array("etag_".$sourceName => $data["etag"]));
		} elseif ($resp->getHeader('ETag')) {
			$this->importDataFrom(array("etag_".$sourceName => $resp->getHeader('Etag')));
		}

		// Check for conflict due to out of date ETag
		if ($resp->getResponseStatusCode() == 409) {
			if (isset($data["etag"])) {
				FTAuth::authLog($log + array('status' => 'conflict', 'etag_expected' => $data['etag']));
			} else {
				FTAuth::authLog($log + array('status' => 'conflict'));
			}
			$this->damerror = "conflict";
			return false;
		}
		unset($http, $resp);

		$this->validity = self::VALIDITY_VALID;
		FTAuth::authLog($log + array('status' => 'ok'));
		return empty($data) ? true : $data;
	}

	/**
	 * Reports the error message associated with the last DAM call.  If the last DAM call was successful, returns false.
	 *
	 * @return string
	 */
	public function getDamError() {
		return $this->damerror;
	}

	/**
	 * Returns references to the read databases used by this user object (only the OAC DB unless subclassed)
	 *
	 * @return array
	 */
	public function getDBs() {
		$dbs = array();
		foreach ($this->dbs as $k=>$db) $dbs[$k] = $db['read'];
		return $dbs;
	}

	public function getSources() {
		return $this->damSourceConfigurations;
	}

	/**
	 * Returns names of tables used by the databases in this user object (keys match those from FTUser::getDBs)
	 *
	 * @return array
	 */
	public function getTables() {
		return $this->tables;
	}

	/**
	 * Adds a new field to the set of data that makes up the user profile
	 *
	 * When the FTUser class is subclassed, the subclass should use this method to add fields stored in a local database.  The definition array should include the following keys:
	 *
	 * source - if the field is to be retrieved from DAM, the name of the DAM data group to which it belongs
	 * db - the name of the database connection that should be used to access the field
	 * sourceName - the name of the field in DAM, if different from the field name given in $fieldname
	 * modifier - FTLabs\MySQLConnection prepared statement modifier to add to the field when saving it to the database (eg 'date' to parse the value as a dae and save it in a MySQL date or datetime field.
	 *
	 * @param string $ref        Name to give to the new field
	 * @param array  $definition Associative array of properties as defined above
	 * @return  void
	 */
	protected function addField($ref, $definition) {
		$this->fields[$ref] = $definition;
	}

	/**
	 * Adds a new database connector
	 *
	 * When the FTUser class is subclassed, the subclass should call this method to set up any secondary databases that will store app-specific information.
	 *
	 * @param string                 $dbname Convenience name for the connection (referenced when adding fields, see addField)
	 * @param FTLabs\MySQLConnection &$db    Database connection object, or an associative array containing two database connection objects in keys 'read' and 'write'.  If just an object is specified, it will be used for both read and write connections.
	 * @param string                 $table  Name of table within the database to use for accessing the field (defaults to 'users')
	 *
	 * @return void
	 */
	protected function addDatabase($dbname, &$db, $table='users') {
		if (is_object($db)) {
			$database = array('read'=>&$db, 'write'=>&$db);
		} else {
			$database = $db;
		}

		$this->dbs[$dbname] = &$database;
		$this->tables[$dbname] = $table;
	}

	/**
	 * Removes a field from the set of data that makes up the user profile
	 *
	 * When the FTUser class is subclassed, the subclass should use this method to remove unneeded OAC fields, or other fields added by higher level subclasses (if the subclass of FTUser is itself subclassed)
	 *
	 * @param string $ref Name of field to remove
	 * @return void
	 */
	protected function removeField($ref) {
		unset($this->fields[$ref]);
	}

	/**
	 * Gets the session variable for this user
	 *
	 * @return  array
	 */
	public function &getSessionVar() {
		if (!$this->session) {
			try {
				$this->session = &FTSession::getSession($this->data['eid']);
			} catch (BaseException $e) {
				throw new Exception('There is not currently a memcache object in the FTSession class. The factory method FTAuth::createUser should add one; please always create users via this method, or set memcache manually first via FTSession::setMemcacheIfNotYetSet', get_defined_vars());
			}
		}
		return $this->session->getSessionVar();
	}

	/**
	 * Gets a single data item about the current user
	 *
	 * Note that since this class implements ArrayAccess, single fields can also be accessed using array syntax
	 *
	 * @param string $key Name of the key to return
	 * @return  mixed
	 */
	public function get($key) {
		$data = $this->getAll();
		return (isset($data[$key])) ? ($data[$key]) : false;
	}

	/**
	 * Gets all known data about the current user
	 *
	 * @return  array
	 */
	public function getAll() {
		return ($this->data);
	}


	/**
	 * Saves new data to the user record.  Detects changes in the user's DB record and saves changes to the database automatically.
	 *
	 * @param array $data       Array of key/value pairs to add to the user record.
	 * @param bool  $skiperrors Set to true to trigger an error if data is set which can't be saved.
	 * @return void
	 */
	public function setUserData($data, $skiperrors = false) {
		$damdata = array();
		foreach ($data as $key => $val) {

			// Reject the data if it's not writable DAM data or in the configured extra fields
			if (empty($this->fields[$key]) and empty($this->writableDamFields[$key])) {
				if (!$skiperrors) {
					trigger_error('Field "' . $key . '" cannot be written', E_USER_ERROR);
				}
				unset($data[$key]);
				continue;
			}

			// Separate out DAM data for upload
			if (!empty($this->writableDamFields[$key])) {
				$damdata[$this->writableDamFields[$key]][$key] = $val;
			}
		}

		if ($this->importDataFrom($data)) {
			$this->saveToDB();
			if (!empty($damdata)) {
				$this->syncDam('upload', $damdata);
			}
		}
	}

	/**
	 * Updates the given user data in the local databases
	 *
	 * @return bool
	 */
	protected function saveToDB() {
		foreach ($this->dbs as $dbname => $db) {

			// Fetch current DB data (if user exists in the db)
			$current = $db['read']->queryRow('SELECT * FROM '.$this->tables[$dbname].' WHERE eid = ' . $this->data['eid']);
			$updates = $updatedata = array();

			// Check fields for changes
			foreach ($this->fields as $k => $fieldInfo) {

				// Skip fields that are not found in this database
				if (!empty($fieldInfo['db']) and $fieldInfo['db'] != $dbname) continue;

				// If this field has changed, store it for update
				if (isset($current[$k]) and isset($fieldInfo['modifier']) and $fieldInfo['modifier'] == 'utcdate') {
					$current[$k] = self::parseDate($current[$k]);
				}
				if (isset($this->data[$k]) and $this->data[$k] !== "" and $this->data[$k] != $current[$k]) {

					// Queue update
					$updates[$k] = "{".$k.((!empty($fieldInfo['modifier']))?'|'.$fieldInfo['modifier']:'')."}";
					$updatedata[$k] = $this->data[$k];
				}
			}

			// If there are any changes, apply them
			if (count($updates) or !$current) {

				// Ensure eid is included in query - EID must be included in every database
				$updates['eid'] = '{eid}';
				$updatedata['eid'] = $this->data['eid'];


				/* Prepare query  */

				// Build basic 'fields' part of the 'insert' query
				$insertfields = join(', ', $updates);

				// Build basic 'fields' part of the 'update' query
				unset($updates['eid']);
				$updatefields = join(', ', $updates);

				// If the data contains fields that need to be updated if already present, build one query
				if ($updatefields) {
					$query = 'INSERT INTO '.$this->tables[$dbname].' SET '.$insertfields.' ON DUPLICATE KEY UPDATE ' . $updatefields;

				// If the data contains no fields that may need updating, build a different query
				} else {
					$query = 'INSERT IGNORE INTO '.$this->tables[$dbname].' SET '.$insertfields;
				}

				// Log the varnish ID in the query if present to help correlate log information
				if (isset($_SERVER['HTTP_X_VARNISH'])) $query .= ' /* vid:'.$_SERVER['HTTP_X_VARNISH'].' */';

				try {
					$db['write']->query($query, $updatedata);
				} catch (MySqlConnectionException $e) {
					trigger_error('Unable to save FTUser data to cache database eh:tolerance=100/day', E_USER_NOTICE);
					return false;
				}
			}
		}
		return true;
	}

	/**
	 * performs syncronous upload/download to/from DAM
	 *
	 * @param string $action Either 'upload' or 'download'
	 * @param array  $data   An array of data to be used (not used for download)
	 * @return void
	 */
	public function syncDam($action, array $data=array()) {
		switch ($action) {
			case "download":
				$this->updateFromDam();
				break;
			case "upload":
				if (empty($data)) return "No data to upload";
				$this->uploadToDam($data);
				break;
			default:
				return "Couldn't find action ".$action;
		}
		return false;
	}

	/**
	 * Checks whether the user supports the given field
	 *
	 * @param string $fieldName Field name to check
	 * @return boolean
	 */
	public function hasField($fieldName) {
		return isset($this->fields[$fieldName]);
	}

	/**
	 * Sends third party data to DAM
	 *
	 * @param array $data The new data to write to DAM
	 * @return bool
	 */
	public function uploadToDam(array $data) {
		$hadconflicts = false;
		foreach ($data as $source => $uploaddata) {

			if (isset($this->data["etag_".$source])) {
				$uploaddata['etag'] = $this->data["etag_".$source];
			}

			// Execute upload.  If a conflict results, makeDamRequest will have recorded the new ETag, so just recreate the upload with the new data.
			if (!$this->makeDamRequest($source, $uploaddata) and $this->getDamError() == 'conflict') {
				$hadconflicts = true;
			}
		}

		if ($hadconflicts) {

			// If a conflict occurred write it again with the newer version number (which has been recorded by makeDAMRequest)
			FTAuth::authLog(array('ftuser' => $this->get('eid'), 'err' => 'conflict', 'winner' => 'local'));
			$this->uploadToDAM($data);
		}

		// Clear the memcache record for this user; the next read will update the data from DAM
		self::getNamespacedMemcache()->delete($this->data['eid']);
	}

	/**
	 * Merges data into the user record in memory.  Behaves as array_merge, except that empty strings will not overwrite known values.  False will.  To edit a user's data, use setUserData.
	 *
	 * @param array $vars Key/value pairs to add to the user
	 * @return bool
	 */
	public function importDataFrom($vars) {
		$newdata = array();

		foreach ($vars as $key=>$value) {
			if ($key == 'eid' and $value != $this->data['eid']) {
				trigger_error("Cannot change EID on user instance.  Create new instance instead", E_USER_ERROR);
			}

			// Canonicalise dates
			if (isset($this->fields[$key]['modifier']) and $this->fields[$key]['modifier'] == 'utcdate') {
				$value = self::parseDate($value);
			}

			// If value hasn't changed, don't bother importing it
			if (array_key_exists($key, $this->data) and (string)$value == (string)$this->data[$key]) {
				continue;
			}

			// Prevent setting existing values to an empty string
			if ($value === "" and isset($newdata[$key])) {
				continue;
			}

			$newdata[$key] = $value;
		}

		// Give the user a default pseudonym if they don't have one already.
		// Don't use firstname, lastname, username or email as this is used for public attribution
		if (empty($this->data['Pseudonym']) and empty($newdata['Pseudonym'])) {
			$newdata['Pseudonym'] = 'User' . $this->data['eid'];
		}

		if ($newdata) {
			$this->data = array_merge($this->data, $newdata);

			// Update session
			$session = &$this->getSessionVar();
			$session['user'] = empty($session['user']) ? $this->data : array_merge($session['user'], $this->data);

			return true;
		} else {
			return false;
		}
	}

	/**
	 * Returns Javascript that sets a description of the user into the Assanka.auth.user object in the format described in http://wiki.assanka.com/wiki/The_FT_platform#Javascript_namespacing
	 *
	 * @return	string
	 */
	public function getPublicUserState() {

		// Determine user's display name (to display to the user themselves, or to use in emailing the user or sending email on behalf of the user.  Not to be used to attribute actions to the user publicly - use the pseudonym only.)
		$dispname = $this->get('Pseudonym');
		if (!trim($dispname)) $dispname = $this->get('firstname').' '.$this->get('lastname');
		if (!trim($dispname)) $dispname = $this->get('email');

		// Add user details to output array
		$op = array();
		$op['pseudonym'] = $this->get('Pseudonym');
		$op['dispname'] = $dispname;
		$op['email'] = $this->get('email');
		$op['eid'] = $this->get('eid');

		return $op;
	}

	public function getPublicUserStateJS() {
		$ret = "";
		$ret .= "if (typeof Assanka == \"undefined\") Assanka = {};";
		$ret .= "Assanka.auth = Assanka.auth || {};";
		$ret .= "Assanka.auth.user = ".$this->getPublicUserStateJSON().";";
		return $ret;
	}

	public function getPublicUserStateJSON() {
		return json_encode($this->getPublicUserState());
	}

	public function getDamCurlCmds() {
		return $this->curlcmds;
	}

	public function getValidity() {
		return $this->validity;
	}

	private static function parseDate($value) {
		if (is_numeric($value)) return $value;
		$date = new DateTime($value, new DateTimeZone('UTC'));
		return $date->format('U');
	}

	public static function getNamespacedMemcache() {
		$prefix = 'ftauth';
		$damSourceNames = array_keys(self::$enabledDAMSources);
		sort($damSourceNames);
		$prefix .= md5(implode($damSourceNames));

		return Memcache::getMemcache($prefix);
	}


	/* Implementation of ArrayAccess */

	public function offsetExists($offset) { return isset($this->data[$offset]); }
	public function offsetGet($offset) { return (isset($this->data[$offset])) ? $this->data[$offset] : null; }
	public function offsetSet($offset, $value) { $this->setUserData(array($offset => $value)); }
	public function offsetUnset($offset) { $this->setUserData(array($offset => null)); }


	/* Implementation of Iterator */

	protected $currentkey;
	public function current() { return ($this->valid()) ? $this->data[$this->currentkey] : null; }
	public function key() { return $this->currentkey; }
	public function valid() { return array_key_exists($this->currentkey, $this->data); }
	public function next() {
		next($this->data);
		$this->currentkey = key($this->data);
		return $this->current();
	}
	public function rewind() {
		reset($this->data);
		$this->currentkey = key($this->data);
	}


	/* Implementation of Countable */

	public function count() { return count($this->data); }
}
