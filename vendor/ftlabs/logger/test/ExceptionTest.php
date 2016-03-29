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

	function testOptionalContext() {
		$e = new FTLabs\AuthException("Test Message", new Exception("previous"));

		$this->assertEquals($e->getMessage(), "Test Message");
		$this->assertEquals($e->getPrevious()->getMessage(), "previous");
		$this->assertNull($e->getContext());
	}

	function testOptionalException() {
		$e = new FTLabs\UserInputException("Test Message", array("foo"=>"bar"));

		$this->assertEquals($e->getMessage(), "Test Message");
		$this->assertNull($e->getPrevious());
		$this->assertEquals($e->getContext(), array("foo"=>"bar"));
	}

	function testOptionalMessage() {
		$e = new FTLabs\InvalidCallException(array("foo"=>"bar"));

		$this->assertEquals($e->getMessage(), "FTLabs\InvalidCallException");
		$this->assertNull($e->getPrevious());
		$this->assertEquals($e->getContext(), array("foo"=>"bar"));
	}

	function testTags() {
		$e = new FTLabs\InternalException(array("eh:noreport"=>true));

		$this->assertEquals(array('noreport'=>true), $e->getTags());
	}

	function testObjectContext() {
		$e = new FTLabs\InternalException((object)array("foo"=>"bar"));

		$this->assertEquals($e->getMessage(), "FTLabs\InternalException");
		$this->assertNull($e->getPrevious());
		$this->assertEquals($e->getContext(), (object)array("foo"=>"bar"));
	}
}
