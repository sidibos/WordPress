<?php
/**
 * A single index page view, which may comprise a number of components and a primary list of content
 *
 * @codingstandard ftlabs-phpcs
 * @copyright The Financial Times Limited [All Rights Reserved]
 */

namespace FTLabs;

class FTPage {

	private $conn = null, $data = array(), $links = array(), $lastfetch, $lastchange;

	/**
	 * Creates an FTPage
	 *
	 * Private constructor.  Use factory method FTPage::get().
	 *
	 * @param FTAPIConnection $conn An FT API connection object
	 * @param FTAPIResponse   $resp An API response
	 * @return FTPage
	 */
	private function __construct(FTAPIConnection $conn, $resp) {
		$this->conn = $conn;
		$this->resp = $resp;

		foreach ($resp['page'] as $k=>$v) {
			if ($k == 'links') {
				foreach ($v as $linkdata) {
					$this->links[$linkdata['rel']] = $linkdata['href'];
				}
			} else {
				$this->data[$k] = $v;
			}
		}
	}

	/**
	 * Returns a list of items in the main content of the page
	 *
	 * @param String $rel The rel to use
	 * @return FTItemList
	 */
	public function getContent($rel) {
		$list = new FTItemList($this->conn, 'Page:' . $this->data['id']);
		if (empty($this->links[$rel])) {
			$this->conn->logWrite(array('action' => "page->getContent($rel)", 'status' => "fail", 'errtext' => "$rel link missing", 'id' => $this->data['id']));
			return $list;
		}
		$data = $this->conn->get($this->links[$rel]);
		if (empty($data['pageItems'])) {
			$this->conn->logWrite(array('action' => "page->getContent($rel)", 'status' => "empty", 'errtext' => "no pageItems", 'id' => $this->data['id']));
			return $list;
		}
		foreach ($data['pageItems'] as $item) {
			foreach ($item['links'] as $lnk) {
				if ($lnk['rel'] == 'content-item') {
					$list->addItem($lnk['href'], $item);
					break;
				}
			}
		}
		return $list;
	}

	/**
	 * Returns a list of items in the specified rel of the page
	 *
	 * @deprecated
	 * @return FTItemList
	 */
	public function getMainContent() {
		return $this->getContent('main-content');
	}

	/**
	 * Strips http(s), www., app. sub domain, also strips trailing slashes to make matches more likely to be exact.
	 *
	 * @param string $string input url.
	 * @return string
	 */
	private static function stripURLCruft($string) {
		return preg_replace('/(http(s)?:\/\/)?(www\\.)?(app\\.)?|\/$/', '', $string);
	}

	/**
	 * Returns a UUID from a WebURL
	 * Throws an exception if no match found.
	 *
	 * @param FTAPIConnection $conn   An API connection object
	 * @param string          $webURL WebURL url.
	 * @return string
	 */
	public static function getUUIDFromWebURL(FTAPIConnection $conn, $webURL) {
		$uuid = self::uuid($webURL);
		if ($uuid) {
			return $uuid;
		}
		$webURL = self::stripURLCruft($webURL);
		$mc = Memcache::getMemcache();
		$mckey = 'ft-app-WebURL_UUID_store';
		$responsebody = $mc->get($mckey);
		if ($responsebody == null) {

			// No Cached data so read the JSON and turn it into a key value pair of
			// stripURLCruft(WebURL) -> UUID
			if (($resp = $conn->get('site/v1/pages.json')) === null) {
				throw new ContentAPIException('Error executing request on FTAPIConnection');
			}
			$prebody = $resp->toArray();
			if (empty($prebody) or empty($prebody['pages'])) {
				throw new ContentAPIException('Invalid UUID list read from FTAPIConnection');
			}
			$responsebody = array();
			foreach ($prebody['pages'] as $value) {
				$responsebody[self::stripURLCruft($value['webUrl'])] = $value['id'];
			};
			$mc->set($mckey, $responsebody, 600);
		}

		// Check to see if there is an exact match in the list.
		if (isset($responsebody[$webURL])) {
			return $responsebody[$webURL];
		}
		throw new ContentAPIException('No match found.');
	}

	/**
	 * Gets the date on which the page was last fetched from the API
	 *
	 * @return DateTime The date/time
	 */
	public function getLastFetchDate() {
		return $this->getAPIResponse()->getLastFetchDate();
	}

	/**
	 * Gets the API response from which the page was constructed
	 *
	 * @return FTAPIResponse
	 */
	public function getAPIResponse() {
		return $this->resp;
	}

	/**
	 * Returns one of the page's data aspects as an array
	 *
	 * @param string $key Aspect name (magic getter, so this parameter is passed by reading it as an object property)
	 * @return array The aspect data
	 */
	public function __get($key) {
		return isset($this->data[$key]) ? $this->data[$key] : null;
	}


	/**
	 * Creates an FTPage from an identifier, using the provided FTAPIConnection
	 *
	 * @param FTAPIConnection $conn An API connection object
	 * @param string          $ref  A string containing a UUID (either on it's own, or part of a larger string, normally a URL)
	 * @return FTPage
	 */
	public static function get(FTAPIConnection $conn, $ref) {
		$uuid = self::uuid($ref);
		if (!$uuid) return null;
		$resp = $conn->get('site/v1/pages/'.$uuid);
		if (empty($resp['page'])) {
			$conn->logWrite(array('action' => "page::get", 'status' => "fail", 'errtext' => "empty response", 'id' => $uuid));
			return null;
		}
		$page = new FTPage($conn, $resp);
		return $page;
	}

	/**
	 * Extracts a UUID from a longer string (normally a URL)
	 *
	 * @param string $str A string containing a UUID
	 * @return string A UUID
	 */
	public static function uuid($str) {
		return (preg_match("/[0-9a-f\-]{36}/i", $str, $m)) ? $m[0] : null;
	}
}
