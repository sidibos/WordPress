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
use FTLabs\FTSearch;

class FTSearchTest extends PHPUnit_Framework_TestCase {

	public function testSearchAndIterateResultset() {
		$conn = new FTAPIConnection();
		$conn->setCacheEnabled(false);
		$srh = new FTSearch($conn, 'obama');
		$this->assertGreaterThan(0, $srh->getTotalResults(), 'No results found for common search term');
		$this->assertGreaterThan(0, count($srh), 'No results found for common search term');
		$i = 0;
		foreach ($srh as $result) {
			$i++;
			$this->assertInstanceOf('FTLabs\\FTItem', $result, 'Result items not of expected type');
			$this->assertNotEmpty($result->uuid, 'Result returned an empty uuid');
		}
		$this->assertEquals($i, count($srh), 'Number of results iterated does not match reported count');
	}

	public function testSearchIsNotCaseSensitive() {
		$conn = new FTAPIConnection();
		$conn->setCacheEnabled(false);
		$srh = new FTSearch($conn, 'transcript');
		$srh2 = new FTSearch($conn, 'Transcript');
		$this->assertGreaterThan(0, count($srh), 'No results found for common search term');
		$this->assertEquals($srh->getTotalResults(), $srh2->getTotalResults(), 'Different capitalisations of the same term return different numbers of results');
	}
}
