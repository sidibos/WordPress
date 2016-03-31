<?php
/**
 * PHPUnit tests for the content API
 *
 * @codingstandard ftlabs-phpcs
 * @copyright The Financial Times Limited [All Rights Reserved]
 */

// Class under test
use FTLabs\FTAPIConnection;

class FTAPIConnectionTest extends PHPUnit_Framework_TestCase {

	public function testResponseWithZeroTTLIsNotCached() {
		$conn = new FTAPIConnection();


		/* Note - this test is a bit fragile as it is not possible to tell whether an end point is caching or not.
		   However, at the time of writing, searches are served with no-cache and max-age=0 */

		$reqdata = array(
			'queryString' => 'transcript'.microtime(true),
			'resultContext' => array(
				'offset' => 0,
				'maxResults' => 1,
				'aspects' => array(
					"lifecycle",
					"location",
					"master",
					"summary",
					"title",
				),
			),
		);

		$resp = $conn->post('/content/search/v1', $reqdata);
		$firstfetchdate = $resp->getLastFetchDate();

		// Check that items with "no-cache", "no-store" or "max-age=0" in their response have a zero TTL:
		$rawresponse = $conn->getLastHTTPResponse();
		$responseheaders = $rawresponse->getHeaders();
		$uncached = false;
		if (isset($responseheaders['cache-control'])) {
			$pieces = explode(",", $responseheaders['cache-control']);
			foreach ($pieces as $piece) {
				if (in_array(trim($piece), array('no-cache', 'no-store', 'max-age=0'))) $uncached = true;
			}
		}

		if ($uncached) {
			$this->assertEquals($resp->getRemainingTTL(), 0, "Items with a cache-control header specifying no caching should have a zero TTL");
		} else {
			$this->markTestSkipped('This test requires an upstream end-point to serve with a no-cache header, which did not happen');
		}

		// Check items are cached based on TTL:
		if ($resp->getRemainingTTL() > 2) {

			// This item is cacheable, so repeating the request shouldn't cause a change in the fetch date.
			sleep(2);
			$resp = $conn->post('/content/search/v1', $reqdata);
			$secondfetchdate = $resp->getLastFetchDate();
			$this->assertEquals($firstfetchdate, $secondfetchdate, "Items that are cacheable should return the same fetch date when re-requested within the TTL");
		} else {

			// This item should not be cached, so repeating the request should cause a new fetch date.
			sleep(2);
			$resp = $conn->post('/content/search/v1', $reqdata);
			$secondfetchdate = $resp->getLastFetchDate();
			$this->assertNotEquals($firstfetchdate, $secondfetchdate, "Items that are not-cacheable should return the different fetch dates when re-requested.");
		}
	}
}
