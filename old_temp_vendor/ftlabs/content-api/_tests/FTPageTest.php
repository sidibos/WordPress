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
use FTLabs\FTPage;

class FTPageTest extends PHPUnit_Framework_TestCase {

	public function testItemCanBeLoadedFromAPI() {
		$conn = new FTAPIConnection();
		$conn->setCacheEnabled(false);
		$page = FTPage::get($conn, 'cec106aa-cd25-11de-a748-00144feabdc0');
		$this->assertNotNull($page);
	}

	public function testMainContentCanBeLoaded() {
		$conn = new FTAPIConnection();
		$page = FTPage::get($conn, 'cec106aa-cd25-11de-a748-00144feabdc0');
		$list = $page->getMainContent();
		$this->assertTrue($list->count() > 0, "API should return list");

		foreach ($list as $item) {
			if ($item === null) continue;
			$this->assertNotNull($item->location['uri']);
		}
	}

	public function testContentWithParamSameAsMainContent() {
		$conn = new FTAPIConnection();
		$page = FTPage::get($conn, 'cec106aa-cd25-11de-a748-00144feabdc0');
		$listOldSyntax = $page->getMainContent();
		$listNewSyntax = $page->getContent('main-content');
		$this->assertEquals($listOldSyntax, $listNewSyntax);
	}

	public function testSkylineContentCanBeLoaded() {
		$conn = new FTAPIConnection();
		$page = FTPage::get($conn, '67a77e8a-276d-11e3-8feb-00144feab7de');
		$list = $page->getContent('skyline-content');
		$this->assertTrue($list->count() > 0, "API should return list");

		foreach ($list as $item) {
			if ($item === null) continue;
			$this->assertNotNull($item->location['uri']);
		}
	}

	public function testUnknownPageReturnsNull() {
		$conn = new FTAPIConnection();
		$conn->setCacheEnabled(false);
		$page = FTPage::get($conn, 'aec106aa-cd25-11de-a748-00144feabdc0');
		$this->assertNull($page);
	}

	public function testMalformatedUUIDReturnsNull() {
		$conn = new FTAPIConnection();
		$conn->setCacheEnabled(false);
		$page = FTPage::get($conn, 'too short');
		$this->assertNull($page);
	}

	public function testDateMetadata() {
		$conn = new FTAPIConnection();
		$page = FTPage::get($conn, 'cec106aa-cd25-11de-a748-00144feabdc0');
		$this->assertNotEmpty($page, 'Page was not found by API');
		$this->assertInstanceOf('DateTime', $page->getLastFetchDate(), "Last fetch time missing");
	}

	public function testGetUUIDFromWebURL() {
		$conn = new FTAPIConnection();
		$uuid = FTPage::getUUIDFromWebURL($conn, "http://www.app.ft.com/business-education/innovative-law-schools/");
		$this->assertEquals("1ddab786-10c7-11e1-8298-00144feabdc0", $uuid);
	}
}
