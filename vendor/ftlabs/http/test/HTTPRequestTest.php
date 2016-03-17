<?php

use FTLabs\HTTPRequest;

class HTTPRequestTest extends PHPUnit_Framework_TestCase {

	public function testMethods() {

		// BadAPI doesn't support PUT and DELETE
		$methods = array('GET', 'POST');
		foreach ($methods as $method) {
			$req = new HTTPRequest("http://badapi.trib.tv/req");
			$req->setMethod($method);
			$req->set('op', 'request');
			$resp = $req->send();
			$this->assertContains(" ".$method." http", $resp->getBody());
		}
	}

	public function testReqHeaders() {
		$req = new HTTPRequest('http://badapi.trib.tv/req?op=headers');
		$req->setHeader('User-Agent', 'iPhone');
		$resp = $req->send();
		$this->assertContains('iPhone', $resp->getBody());
	}

	public function testRequestTimeout() {
		try {
			$req = new HTTPRequest('http://badapi.trib.tv/req?wait=10');
			$req->setTimeLimit(5);
			$req->send();
		} catch (Exception $e) {
			$this->assertEquals('HTTP request timed out', $e->getMessage());
			return;
		}
		$this->fail('Exception was expected but not thrown');
	}

	public function testResolveToCLIEquiv() {
		$req = new HTTPRequest('http://badapi.trib.tv/req?wait=0');
		$req->resolveTo('127.0.0.1');
		$equiv = $req->getCliEquiv();
		$this->assertContains('Host: badapi.trib.tv', $equiv);
		$this->assertContains('http://127.0.0.1/req?wait=0', $equiv);
		$this->assertNotContains('http://badapi.trib.tv/req?wait=0', $equiv);
	}

	public function testResponseTime() {
		$req = new HTTPRequest('http://badapi.trib.tv/req?wait=10');
		$req->setTimeLimit(30);
		$resp = $req->send();
		$responsetimeint = floor($resp->getResponseTime());
		$this->assertTrue($responsetimeint > 9 and $responsetimeint < 16);
	}

	public function testJsonDecode() {
		$req = new HTTPRequest("http://badapi.trib.tv/req?op=json1&ct=application/json");
		$resp = $req->send();
		$data = $resp->getData();
		$this->assertFalse(empty($data['key2']['456']), 'Response was not identified or parsed as JSON');
	}

	public function testFormVarsDecode() {
		$req = new HTTPRequest("http://badapi.trib.tv/req?op=formvars&ct=application/x-www-form-urlencoded");
		$resp = $req->send();
		$data = $resp->getData();
		$this->assertTrue(!empty($data['breed']) and is_array($data['breed']), 'Response was not identified or parsed as form vars');
	}

	public function testPHPDecode() {
		$req = new HTTPRequest("http://badapi.trib.tv/req?op=phpserial&ct=application/php");
		$resp = $req->send();
		$data = $resp->getData();
		$this->assertFalse(empty($data['first']->owner), 'Response was not identified or parsed as php serialised data');
	}

	public function testResponseStatusCode() {
		$req = new HTTPRequest("http://badapi.trib.tv/req?resp=503");
		$resp = $req->send();
		$this->assertEquals($resp->getResponseStatusCode(), 503);
	}

	public function testResponse() {
		$req = new HTTPRequest("http://badapi.trib.tv/req");
		$resp = $req->send();
		$this->assertContains("HTTP/1.1 200 OK", $resp->getResponse());
	}

	public function testRespHeader() {
		$req = new HTTPRequest("http://badapi.trib.tv/req?ct=css");
		$resp = $req->send();
		$this->assertEquals("text/css", $resp->getHeader('content-type'));
	}

	public function testFollowLocation() {

		// Example.com redirects to www.iana.org/domains/example
		$req = new HTTPRequest("http://www.ft.com/");

		// Test that followlocation == true works correctly
		$req->setFollowLocation(true);
		$resp = $req->send();
		$this->assertEquals("200", $resp->getResponseStatusCode());

		// Test that followlocation == false works correctly
		$req->setFollowLocation(false);
		$resp = $req->send();
		$this->assertEquals("302", $resp->getResponseStatusCode());
	}
}
