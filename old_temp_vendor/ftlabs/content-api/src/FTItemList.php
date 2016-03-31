<?php
/**
 * A list of FTItem objects
 *
 * @codingstandard ftlabs-phpcs
 * @copyright The Financial Times Limited [All Rights Reserved]
 */

namespace FTLabs;

use Countable;
use Iterator;

class FTItemList implements Countable, Iterator {

	private $apiconnection, $items, $overrides, $keys, $idx, $listname;

	/**
	 * Creates an FTItemList
	 *
	 * @param FTAPIConnection $conn     An FT API connection object
	 * @param string          $listname The name of the item list for logging purposes
	 * @return FTItemList
	 */
	public function __construct($conn, $listname) {
		$this->apiconnection = $conn;
		$this->items = array();
		$this->overrides = array();
		$this->keys = array();
		$this->rewind();
		$this->listname = $listname;
	}

	/**
	 * Add an item to the list
	 *
	 * @param string $uuid         The UUID of the item to add
	 * @param array  $overridedata An array of data, grouped by aspect, to use to override the data provided by the item itself
	 * @return void
	 */
	public function addItem($uuid, $overridedata = array()) {
		$this->items[self::uuid($uuid)] = array();
		$this->keys[] = self::uuid($uuid);
		if ($overridedata) $this->overrides[self::uuid($uuid)] = $overridedata;
	}

	/**
	 * Gets one of the items in the list
	 *
	 * Returns null if the item is not present in the list
	 *
	 * @param string $index  The index of the item to get
	 * @param string $format Format in which to get the response (either 'plain' or 'structured', default plain).  Note that using the iterator interface will use the plain format.
	 * @return FTItem
	 */
	public function getItemByIndex($index, $format = 'plain') {

		// Get the UUID of the item, returning null if the index is out of bounds
		if (!isset($this->keys[$index])) return null;
		$uuid = $this->keys[$index];

		// If the item hasn't been cached within this list yet, retrieve it and add to the cache
		if (!isset($this->items[$uuid][$format])) {
			$ftitem = FTItem::get($this->apiconnection, $uuid, $format);

			if (!$ftitem) {
				$this->apiconnection->logWrite(array('action' => "itemlist->getItemByIndex", 'status' => "fail", 'errtext' => "item missing", "listname" => $this->listname, 'index' => $index, 'itemid' => $uuid));
				return null;
			}

			if (isset($this->overrides[$uuid])) {
				$ftitem->setOverrides($this->overrides[$uuid]);
			}

			$this->items[$uuid][$format] = $ftitem;
		}

		// Return the FT Item
		return $this->items[$uuid][$format];
	}


	/* Iterator interface */

	function rewind() { $this->idx = 0; }
	function current() { return $this->getItemByIndex($this->idx); }
	function key() { return $this->keys[$this->idx]; }
	function next() { ++$this->idx; }
	function valid() { return isset($this->keys[$this->idx]); }


	/* Countable interface */

	function count() { return count($this->keys); }


	/* Static utilities */

	private static function uuid($str) {
		return (preg_match("/[0-9a-f\-]{36}/i", $str, $m)) ? $m[0] : null;
	}
}
