<?php
/**
 * A search resultset for a search of FT.com content.  An iterable list of FTItem objects.
 *
 * @codingstandard ftlabs-phpcs
 * @copyright The Financial Times Limited [All Rights Reserved]
 */

namespace FTLabs;

use Countable;
use Iterator;

class FTSearch implements Countable, Iterator {

	private $conn, $idx, $results, $resp, $total, $reqdata, $searchRun;

	/**
	 * Create a new FTSearch
	 *
	 * @param FTAPIConnection $conn   An API connection
	 * @param string          $query  The search query expression
	 * @param integer         $limit  Max results to return (default 10)
	 * @param integer         $offset The pagination offset (default 0)
	 * @return FTSearch
	 */
	public function __construct($conn, $query, $limit = 10, $offset = 0) {
		$this->conn = $conn;
		$this->reqdata = array(
			'queryString' => $query,
			'resultContext' => array(
				'offset' => $offset,
				'maxResults' => $limit,

				// These 5 aspects are available from an endpoint at /content/search/aspects/v1
				// To avoid doing an extra request for every search, they are hardcoded here
				'aspects' => array(
					"lifecycle",
					"location",
					"master",
					"summary",
					"title",
				),
			),
		);
		$this->searchRun = false;
	}

	public function setCurations(array $curations) {
		if ($this->searchRun) {
			trigger_error('FT Search API: curations set after query performed', E_USER_ERROR);
		}
		$this->reqdata['queryContext']['curations'] = $curations;
	}

	/**
	 * Set the sort field and order for the result set.
	 *
	 * @param string  $field         The field to sort by
	 * @param boolean $sortAscending Whether to sort the results in ascending order; set to false to sort in descending order
	 * @return void
	 */
	public function setSort($field, $sortAscending = true) {
		if ($this->searchRun) {
			trigger_error('FT Search API: sorting set after query performed', E_USER_ERROR);
		}
		$this->reqdata['resultContext']['sortField'] = $field;
		$this->reqdata['resultContext']['sortOrder'] = $sortAscending ? 'ASC' : 'DESC';
	}

	/**
	 * Returns the number of matching results in the Search API
	 *
	 * NB: This is different to simply calling count() on the search object as there may be many more matching results in the API which haven't been returned in this resultset
	 *
	 * @return int
	 */
	public function getTotalResults() {
		if (!$this->searchRun) $this->runSearch();
		return $this->total;
	}

	/**
	 * Returns a specified item from the resultset
	 *
	 * @param integer $idx Offset of the required item from the beginning of the resultset
	 * @return FTItem
	 */
	public function getItem($idx) {
		if (!$this->searchRun) $this->runSearch();
		if (!($this->results[$idx] instanceof FTItem)) {

			// Convert the search response into an item response (retaining same metadata such as lastchange and lastfetch dates)
			$this->resp->setData(array('item'=>$this->results[$idx]));

			// Ideally this would use FTItem::get() but there is no guarantee that
			// items returned by the search API will be known to the content API
			$item = new FTItem($this->resp);
			$this->results[$idx] = $item;
		}
		return $this->results[$idx];
	}



	/* Iterator Interface */

	public function current () { return $this->getItem($this->idx); }
	public function key () { return $this->idx; }
	public function next () { $this->idx++; }
	public function rewind () { $this->idx = 0; }
	public function valid () {
		if (!$this->searchRun) $this->runSearch();
		return isset($this->results[$this->idx]);
	}


	/* Countable Interface */

	public function count () {
		if (!$this->searchRun) $this->runSearch();
		return count($this->results);
	}

	private function runSearch () {
		$this->results = array();
		$this->total = 0;

		// Send the request, specifying that $reqdata is to be sent as a JSON-encoded request body, not as form-data
		$this->resp = $this->conn->post('/content/search/v1', $this->reqdata);
		if (isset($this->resp['results']) and is_array($this->resp['results'])) {
			$this->total = $this->resp['results'][0]['indexCount'];
			if ($this->total > 0) $this->results = $this->resp['results'][0]['results'];
			$this->rewind();
		} else {
			if (!$this->resp) {
				$this->conn->logWrite(array('action' => 'search', 'status' => 'error', 'msg' => 'Null response'));
			} elseif (isset($this->resp['errors'])) {
				foreach ($this->resp['errors'] as $error) {
					$this->conn->logWrite(array('action' => 'search', 'status' => 'error', 'msg' => $error['message']));
					trigger_error('FT Search API error: '.$error['message'], E_USER_NOTICE);
				}
			}
			throw new ContentAPIException('Search request failed', $this);
		}
		$this->searchRun = true;
	}
}
