<?php
/**
 * Extends the FTAPIConnection in order to expose the queueAsync method publicly
 *
 * @codingstandard ftlabs-phpcs
 * @copyright The Financial Times Limited [All Rights Reserved]
 */

use FTLabs\FTAPIConnection;

class instrumentedFTAPIConnection extends FTAPIConnection {

	/**
	 * Queue an asyncronous request
	 *
	 * Save a request to the message queue for async processing by a worker daemon.
	 *
	 * @param string $mckey   The cache key for this request
	 * @param array  $request An array of request data
	 * @return void
	 */
	protected function runQueueAsync($mckey, $request) {
		$this->queueAsync($mckey, $request);
	}
}
