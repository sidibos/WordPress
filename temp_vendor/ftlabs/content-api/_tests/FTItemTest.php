<?php
/**
 * PHPUnit tests for the content API
 *
 * @codingstandard ftlabs-phpcs
 * @copyright The Financial Times Limited [All Rights Reserved]
 */

// Dependencies of class under test
use FTLabs\FTAPIConnection;

// Class under test
use FTLabs\FTItem;

// Mocks
require_once 'mockMemcacheAccess.php';
require_once 'instrumentedFTAPIConnection.php';

class FTItemTest extends PHPUnit_Framework_TestCase {

	public function testItemCanBeLoadedFromAPI() {
		$conn = new FTAPIConnection();
		$conn->setCacheEnabled(false);
		$item = FTItem::get($conn, 'dd4725f6-06e4-11e1-90de-00144feabdc0');
		$this->assertNotNull($item);
		$this->assertEquals("US economy adds 80,000 jobs", $item->title['title'], "Item with known title returned different title from API");
		$this->assertEquals("dd4725f6-06e4-11e1-90de-00144feabdc0", $item->uuid, "UUID not found or incorrectly reported");
	}

	public function testArticleBodyContainsRealNewlines() {
		$conn = new FTAPIConnection();
		$item = FTItem::get($conn, '9366ce44-0605-11e1-ad0e-00144feabdc0');
		$this->assertNotNull($item, 'Known good item UUID could not be fetched as an FTItem');
		$this->assertNotContains("\\n", $item->body['body'], "Item body contains escaped newlines");
	}

	public function testCachingPreventsDuplicateRequests() {
		$conn = new FTAPIConnection();
		$mc = new mockMemcacheAccess();
		$conn->setMemcacheAccess($mc);
		$item = FTItem::get($conn, '940c78c8-b763-11de-9812-00144feab49a');
		$this->assertNotEmpty($item, 'Item was not found by API');
		$this->assertNotEquals('memcache', $item->getAPIResponse()->getCacheType());
		$cached = current($mc->getCached());
		$this->assertNotEmpty($cached, "Item expected to be cached in cache, but was not");
		$this->assertArrayHasKey('data', $cached, "Cached item has no data key");
		$this->assertNotEmpty($cached['data'], "Cached item has no data");
		$cacheddata = isset($cached['data']['item']) ? $cached['data']['item'] : $cached['data'];
		$this->assertArrayHasKey('id', $cacheddata);
		$this->assertEquals($item->uuid, $cacheddata['id'], "Item in cache does not match request item");
		$item2 = FTItem::get($conn, '940c78c8-b763-11de-9812-00144feab49a');
		$this->assertEquals('memcache', $item2->getAPIResponse()->getCacheType());

		// Same for an unknown item since 404s are also cached (though use connection directly since factory will return null for unknown items
		$mc = new mockMemcacheAccess();
		$conn = new FTAPIConnection();
		$conn->setMemcacheAccess($mc);

		$resp = $conn->get('content/items/v1/12345678-1234-1234-9812-00144feab49a');
		$this->assertTrue(empty($resp['item']), 'Request for an unknown item expected to return empty');
		$this->assertNotNull($mc->getKeyForSetter());
		$cached = current($mc->getCached());
		$this->assertNotEmpty($cached);
		$this->assertEmpty($cached['data']);
		$this->assertNotEquals('memcache', $resp->getCacheType());
		$resp2 = $conn->get('content/items/v1/12345678-1234-1234-9812-00144feab49a');
		$this->assertEquals('memcache', $resp2->getCacheType());
	}


	public function testSlideshowDataIsIncluded() {
		$conn = new FTAPIConnection();
		$conn->setCacheEnabled(false);
		$item = FTItem::get($conn, '9e21ed24-d084-11e1-99a8-00144feabdc0', 'structured');
		$this->assertNotNull($item);
		$this->assertNotNull($item->assets, "No assets aspect present on item known to contain a slideshow");
		$found = false;
		foreach ($item->assets as $asset) {
			if ($asset['type'] == 'slideshow') $found = true;
		}
		$this->assertTrue($found, "Asset list did not contain the expected slideshow data");
	}

	public function testDateMetadata() {
		$conn = new FTAPIConnection();
		$conn->setCacheEnabled(false);
		$item = FTItem::get($conn, '940c78c8-b763-11de-9812-00144feab49a');
		$this->assertNotEmpty($item, 'Item was not found by API');
		$this->assertInstanceOf('DateTime', $item->getLastFetchDate(), "Last fetch time missing");
	}
}
