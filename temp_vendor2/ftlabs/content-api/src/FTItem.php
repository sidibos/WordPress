<?php
/**
 * A single piece of FT content
 *
 * // REVIEW:AB:20120507: Consider changing the keys to be single level strings, eg editorial/subheading, and implement ArrayAccess.  This would allow setting of overrides more elegantly, avoid passing around unstructured arrays and allow must safer existence checking on parameters.  eg:
 *
 * $item = FTItem::get('...');
 * echo $item['editorial/subheading'];    // 'Original subheading'
 * echo $item->getDataView();             // VIEW_ORIGINAL
 * $item['editorial/subheading'] = 'New subheading';
 * echo $item['editorial/subheading'];    // 'New subheading'
 * echo $item->getDataView();             // VIEW_OVERRIDDEN
 * $item->setDataView(FTItem::VIEW_ORIGINAL);
 * echo $item['editorial/subheading'];    // 'Original subheading'
 * echo $item->getDataView();             // VIEW_ORIGINAL
 *
 * But that would be a breaking change, so in the meantime this is done like this:
 *
 * $item = FTItem::get('...');
 * echo $item->editorial['subheading'];   // 'Original subheading'
 * echo $item->getDataView();             // VIEW_ORIGINAL
 * $item->setOverrides(array('editorial'=>array('subheading' => 'New subheading')));
 * echo $item->editorial['subheading'];   // 'New subheading'
 * echo $item->getDataView();             // VIEW_OVERRIDDEN
 * $item->setDataView(FTItem::VIEW_ORIGINAL);
 * echo $item->editorial['subheading'];   // 'Original subheading'
 * echo $item->getDataView();             // VIEW_ORIGINAL
 *
 * @codingstandard ftlabs-phpcs
 * @copyright The Financial Times Limited [All Rights Reserved]
 */

namespace FTLabs;

use DateTime;
use DateTimeZone;

class FTItem {
	private static $setupTasksCompleted = false;

	// Comprehensive list of aspects delivered by the FT API (in alphabetical order to canonicalise request format)
	private static $aspects = array(
		'assets',
		'body',
		'editorial',
		'images',
		'lifecycle',
		'location',
		'master',
		'mediaAssets',
		'metadata',
		'package',
		'packaging',
		'provenance',
		'summary',
		'textualBody',
		'title',
		'usage'
	);
	private static $aspectsLookup;

	// API supports two body formats, which also affect the presence of other metadata in the response
	private static $formats = array(
		'plain',
		'structured'
	);

	// List of fields that should be cast to non-string types for use in PHP.  Any fields not listed will be returned as strings.
	private static $typecasts = array(
		'lifecycle' => array(
			'initialPublishDateTime' => 'datetime',
			'lastPublishDateTime' => 'datetime'
		),
		'body' => array(
			'body' => 'newlinefix'
		)
	);

	// Track the item data
	private $data = array(), $resp = null, $id = null, $overridedata = array(), $dataview;

	// Allow all dates to be set up with the default timezone, which is Europe/London
	private static $defaultTimezone = null;

	// Set descriptive constants to refer to whether the current view of data is the original, or overridden, dataset.
	const VIEW_ORIGINAL = 1, VIEW_OVERRIDDEN = 2;

	/**
	 * Perform one-off class setup tasks
	 *
	 * @return void
	 */
	private static function setUp() {
		self::$setupTasksCompleted = true;
		self::$aspectsLookup = array();
		foreach (self::$aspects as $aspect) {
			self::$aspectsLookup[$aspect] = true;
		}
		self::$defaultTimezone = new DateTimeZone(date_default_timezone_get());
	}

	/**
	 * Create an FTItem
	 *
	 * Normally, FTItem objects should be created via the factory FTItem::get().  Only other ftapi classes are expected to be using this constructor publicly, so the interface should be considered subject to change at any time.
	 *
	 * @param FTAPIResponse $resp An FT API Response object
	 * @return FTItem
	 */
	public function __construct($resp) {
		if (!self::$setupTasksCompleted) {
			self::setUp();
		}

		$this->resp = $resp;
		$data = $resp['item'];
		if (isset($data['id'])) $this->id = $data['id'];
		$this->data = self::loadData($data);
		$this->dataview = self::VIEW_ORIGINAL;
	}

	/**
	 * Add data to override some or all of the item's aspects
	 *
	 * Intended to be used to override a content-item with data from a page-item, ie when an item appears on an index page, it's headline may be different (page-item) than when its own article page is displayed (content-item).  This function is typically used internally by the API classes, and should not be needed elsewhere.
	 *
	 * @param array $data Array of override data, by aspect, then key
	 * @return void
	 */
	public function setOverrides($data) {

		// Load the API response and perform required data conversions
		$this->overridedata = self::loadData($data);
	}

	/**
	 * Sets whether to use original (normally content-item) or overridden (normally page-item, if available) data
	 *
	 * @param integer $viewmode View mode, one of VIEW_ORIGINAL or VIEW_OVERRIDDEN
	 * @return integer
	 */
	public function setDataView($viewmode) {
		$this->dataview = $viewmode;
	}

	/**
	 * Returns the currently selected view mode
	 *
	 * @return integer Constant which maps to VIEW_ORIGINAL or VIEW_OVERRIDDEN
	 */
	public function getDataView() {
		return $this->dataview;
	}

	/**
	 * Gets the date on which the item was last fetched from the API
	 *
	 * @return DateTime The date/time
	 */
	public function getLastFetchDate() {
		return $this->getAPIResponse()->getLastFetchDate();
	}

	/**
	 * Gets the date on which the item content last changed
	 *
	 * @return DateTime The date/time
	 */
	public function getLastChangeDate() {
		return $this->getAPIResponse()->getLastChangeDate();
	}

	/**
	 * Gets the API response from which the item was constructed
	 *
	 * @return FTAPIResponse
	 */
	public function getAPIResponse() {
		return $this->resp;
	}

	/**
	 * Returns one of the item's data aspects as an array
	 *
	 * @param string $key Aspect name (magic getter, so this parameter is passed by reading it as an object property)
	 * @return array The aspect data
	 */
	public function __get($key) {
		if ($key === 'uuid') return $this->id;

		// TODO:AB:20120509: if it has a UUID, and we don't have the aspect already, retrieve it on demand?

		// If the override view is set, and override data is present, return it
		if ($this->dataview == self::VIEW_OVERRIDDEN and isset($this->overridedata[$key])) {
			return $this->overridedata[$key];
		}

		return isset($this->data[$key]) ? $this->data[$key] : null;
	}


	/**
	 * Checks whether a specified aspect exists on the item
	 *
	 * @param string $key The aspect name
	 * @return boolean
	 */
	public function __isset($key) {
		if ($key === 'uuid') return isset($this->id);
		return isset($this->data[$key]);
	}




	/**
	 * Creates an FTItem from an identifier, using the provided FTAPIConnection
	 *
	 * @param FTAPIConnection $conn   An API connection object
	 * @param string          $uuid   A string containing a UUID (either on it's own, or part of a larger string, normally a URL)
	 * @param string          $format Format in which to get the response (either 'plain' or 'structured', default plain)
	 * @return FTItem
	 */
	public static function get($conn, $uuid, $format = 'plain') {
		if (!self::uuid($uuid)) return null;
		if (!is_object($conn) || !($conn instanceof FTAPIConnection)) {
			throw new ContentAPIException('FTAPIConnection object required');
		}
		if (!in_array($format, self::$formats)) {
			throw new ContentAPIException('Invalid article format: ' . $format);
		}
		$resp = $conn->get('content/items/v1/'.self::uuid($uuid), array('aspects'=>join(',', self::$aspects), 'publishedWithin' => 24, 'bodyFormat' => $format));
		if (empty($resp['item'])) {
			$conn->logWrite(array('action' => "item::get", 'status' => "fail", 'errtext' => "empty response", 'id' => $uuid));
			return null;
		}
		$item = new FTItem($resp);
		return $item;
	}

	/**
	 * Converts an API response into an array of data, with data conversions applied where needed.
	 *
	 * @param mixed $data The array of data, or the FTAPIResponse for that data
	 * @return array The converted data
	 */
	private static function loadData($data) {
		if (!is_array($data)) {
			return array();
		}

		// Remove all keys which were not requested aspects
		$data = array_intersect_key($data, self::$aspectsLookup);

		// Apply any typecast conversions
		foreach (self::$typecasts as $key => $conversions) {
			foreach ($conversions as $datakey => $conversion) {
				if (!isset($data[$key][$datakey])) {
					continue;
				}

				$val = $data[$key][$datakey];
				switch ($conversion) {
					case 'datetime':
						$val = new DateTime($val, self::$defaultTimezone);
					break;
					case 'newlinefix':
						$val = str_replace('\n', "\n", $val);
					break;
				}
				$data[$key][$datakey] = $val;
			}
		}

		return $data;
	}

	/**
	 * Extracts a UUID from a longer string (normally a URL)
	 *
	 * @param string $str A string containing a UUID
	 * @return string A UUID
	 */
	public static function uuid($str) {
		return (preg_match("/[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}/i", $str, $m)) ? $m[0] : null;
	}

}
