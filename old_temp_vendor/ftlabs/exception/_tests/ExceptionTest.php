<?php
/**
 * Test for FTLabs\Exception
 *
 * @codingstandard ftlabs-phpcs
 * @copyright The Financial Times Limited [All rights reserved]
 */


class ExceptionTest extends PHPUnit_Framework_TestCase {
	function testAllArgs() {
		$e = new FTLabs\Exception("Test Message", new Exception("previous"), array("foo"=>"bar"));

		$this->assertEquals($e->getMessage(), "Test Message");
		$this->assertEquals($e->getPrevious()->getMessage(), "previous");
		$this->assertEquals($e->getContext(), array("foo"=>"bar"));
	}

	function testCode() {
		$e = new FTLabs\Exception("Test Message", 5, array("foo"=>"bar"));

		$this->assertEquals(5, $e->getCode());
		$this->assertNull($e->getPrevious());
		$this->assertEquals($e->getContext(), array("foo"=>"bar"));
	}

	function testOnlyMessage() {
		$e = new FTLabs\Exception("Test Message");

		$this->assertEquals("Test Message", $e->getMessage());
	}

	function testOptionalContext() {
		$e = new FTLabs\Exception("Test Message", new Exception("previous"));

		$this->assertEquals($e->getMessage(), "Test Message");
		$this->assertNotNull($e->getPrevious());
		$this->assertEquals($e->getPrevious()->getMessage(), "previous");
		$this->assertNull($e->getContext());
	}

	function testOptionalException() {
		$e = new FTLabs\Exception("Test Message", array("foo"=>"bar"));

		$this->assertEquals($e->getMessage(), "Test Message");
		$this->assertNull($e->getPrevious());
		$this->assertEquals($e->getContext(), array("foo"=>"bar"));
	}

	function testOptionalMessage() {
		$e = new FTLabs\Exception(array("foo"=>"bar"));

		$this->assertEquals($e->getMessage(), "FTLabs\Exception");
		$this->assertNull($e->getPrevious());
		$this->assertEquals($e->getContext(), array("foo"=>"bar"));
	}

	function testTags() {
		$e = new FTLabs\Exception(array("eh:noreport"=>true));

		$this->assertEquals(array('noreport'=>true), $e->getTags());
	}

	function testObjectContext() {
		$e = new FTLabs\Exception((object)array("foo"=>"bar"));

		$this->assertEquals($e->getMessage(), "FTLabs\Exception");
		$this->assertNull($e->getPrevious());
		$this->assertEquals($e->getContext(), (object)array("foo"=>"bar"));
	}
}
