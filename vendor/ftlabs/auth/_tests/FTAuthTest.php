<?php
/*
######################################################################

PHPUnit test for the FTAuth class

(c) Copyright Assanka Limited [All rights reserved]
######################################################################
*/

use FTLabs\HTTPRequest;

// Class under test
use FTLabs\FTAuth;

class FTAuthTest extends PHPUnit_Framework_TestCase {


	private $configsets = array(


	    /*  Credentials for testing against LIVE */

		'live' => array(
			'eid' => '4071867', /* shilstonregistered@assanka.com */
			'eid_fake' => '1',
			'cookies' => array(
				'FT_U'=>'_EID=4071867_PID=4004071867_TIME=%5BWed%2C+09-Mar-2011+11%3A36%3A43+GMT%5D_SKEY=nEv0WyEggRyHs%6DxgLBUdOA%3D%3D_RI=1_',
				'FT_Remember'=>'4071867:TK7323745493656747634:FNAME=:LNAME=:EMAIL=shilstonregister@assanka.com'
			),
			'skey' => 'nEv0WyEggRyHs%256DxgLBUdOA%253D%253D', /* Note that the skey needs to be the url encoded version of that in the cookie's skey value. */
			'creds' => array(
				'username'=>'shilstonregister@assanka.com',
				'password'=>'register1'
			)
		),


	    /*  Credentials for testing against TEST - use by running "DAMENVIRONMENT=test phpunit -c phpunit.xml" */

		'test' => array(
			'eid' => '87737853',
			'eid_fake' => '1',
			'cookies' => array(
				'FT_U'=>'_EID=87737853_PID=4087737853_TIME=%5BMon%2C+06-Aug-2012+14%3A46%3A16+GMT%5D_SKEY=8t89XCuILkiAs1VOw58OtA%3D%3D_RI=1_I=0_',
				'FT_Remember'=>'87737853:TK6496562230353019106:FNAME=Assanka:LNAME=Test:EMAIL=assanka_test_reg@jaysethi.com'
			),
			'skey' => '8t89XCuILkiAs1VOw58OtA%253D%253D', /* Note that the skey needs to be the url encoded version of that in the cookie's skey value. */
			'creds' => array(
				'username'=>'assanka_test_reg@jaysethi.com',
				'password'=>'password'
			)
		),
		'int' => array(
		)
	);

	private $eid;
	private $eid_fake;
	private $cookies;
	private $cookies_new = array();
	private $creds;
	private $config;

	private $mc = false; // Memcache instance for tests

	public function setUp() {

		// Apply the specific configuration set:
		if (isset($_SERVER['DAMENVIRONMENT']) and isset(FTAuth::$environments[$_SERVER['DAMENVIRONMENT']])) {
			$this->config = $this->configsets[$_SERVER['DAMENVIRONMENT']];
		} else {
			$this->config = $this->configsets['live'];
		}
		$this->eid = $this->config['eid'];
		$this->eid_fake = $this->config['eid_fake'];
		$this->cookies = $this->config['cookies'];
		$this->creds = $this->config['creds'];

		// Sign in using registration.ft.com
		$cookies_new = $this->cookies;
		$http = new HTTPRequest("https://registration.ft.com/registration/barrier/login");
		if (isset($_SERVER['DAMENVIRONMENT']) and isset(FTAuth::$environments[$_SERVER['DAMENVIRONMENT']])) {
			$http->resolveTo(FTAuth::$environments[$_SERVER['DAMENVIRONMENT']]['registration.ft.com']);
		}
		$http->set($this->creds);
		$http->set(array("location"=>"http://www.ft.com/home/uk", "rememberme"=>"on"));
		$http->setMethod('POST');
		$http->allowSslCertErrors();
		$http->setPostEncoding('form');
		$resp = $http->send();
		$this->cookies_new = $resp->getCookies();

		if (!array_key_exists('FT_U', $this->cookies_new)) {
			// NOTE:SG:20120704: FT Notified on 20120702 that Cookies are not being set by the test environment.  CC Jonathan Furse and Aday Bujeda-Ateca @ft.com RESOLVED:20120712: The test account had been removed.
			$this->fail("FT_U is not set in returned cookies from: https://registration.ft.com/registration/barrier/login Last time this issue occured was due to the FT removing the test account from the environment");
		}

		if (!$this->mc) {
			$this->mc = FTLabs\FTUser::getNamespacedMemcache();
		}
		$this->mc->set('4071867', array(
			'eid' => 4071867,
			'levelname' => 'registered',
			'products' => 'Tools,P0',
			'passportId' => 4004071867,
			'emailAddress' => 'shilstonregister@assanka.com',
			'firstName' => null,
			'lastName' => null,
			'groups' => 'UK',
			'userName' => 'shilstonregister@assanka.com',
			'dametag' => null,
			'etag_assanka' => 10440,
			'etag_mobile' => 39350,
			'Pseudonym' => 'TestUserRegisterPseudonym',
			'orgName' => null,
			'orgLocation' => null,
			'annotationsLevel' => null,
			'skeyexpires' => '2013-02-08 13:14:34',
			'skey' => 'nEv0WyEggRyHs%256DxgLBUdOA%253D%253D',
			'biog' => null,
			'avatar' => null,
			'location' => null,
			'jobtitle' => null,
			'about' => '1360208167-changed',
			'phone' => null,
			'company' => null,
			'datecreated' => '2013-02-07',
			'datemodified' => '2013-02-07 13:14:34',
			'datelastdamdownload' => '2013-02-07 13:14:34',
			'datelastdamupload' => '2013-02-07 03:36:13',
			'format' => null,
			'latesthandset' => null,
			'latestnetwork' => null,
			'region' => null,
			'stock1' => null,
			'stock2' => null,
			'stock3' => null,
			'stock4' => null,
			'stock5' => null,
			'EMAIL' => null,
			'SUBSCRIPTIONS' => null,
			'html5devicetype' => '1360208162',
			'html5screenresolution' => '1360201965.3804',
			'html5last5page' => '1360201965.3804',
			'html5mainview' => '1360201965.3804',
			'html5userlocation' => '1360201965.3804',
			'html5adclicks' => '1360201965.3804',
			'html5adview' => '1360201965.3804',
			'html5appview' => '20130207',
			'html5usetime' => '1360201965.3804',
			'html5date' => '1360201965.3804',
			'html5frequency' => '1360201965.3804',
			'html5pushmarks' => '1360170243.6183',
		), 60 * 60);
		$this->mc->set('87737853', array(
			'eid' => 87737853,
			'levelname' => 'registered',
			'products' => 'Tools,P0',
			'passportId' => 4087737853,
			'emailAddress' => 'assanka_test_reg@jaysethi.com',
			'firstName' => 'Assanka',
			'lastName' => 'Test',
			'groups' => 'UK',
			'userName' => 'assanka_test_reg@jaysethi.com',
			'dametag' => NULL,
			'etag_assanka' => 0,
			'etag_mobile' => 0,
			'Pseudonym' => 'User87737853',
			'orgName' => NULL,
			'orgLocation' => NULL,
			'annotationsLevel' => NULL,
			'skeyexpires' => '2013-02-08 20:36:59',
			'skey' => '8t89XCuILkiAs1VOw58OtA%253D%253D',
			'biog' => NULL,
			'avatar' => NULL,
			'location' => NULL,
			'jobtitle' => NULL,
			'about' => NULL,
			'phone' => NULL,
			'company' => NULL,
			'datecreated' => '2013-02-07',
			'datemodified' => NULL,
			'datelastdamdownload' => '2013-02-07 20:36:59',
			'datelastdamupload' => NULL,
			'format' => NULL,
			'latesthandset' => NULL,
			'latestnetwork' => NULL,
			'region' => NULL,
			'stock1' => NULL,
			'stock2' => NULL,
			'stock3' => NULL,
			'stock4' => NULL,
			'stock5' => NULL,
			'EMAIL' => NULL,
			'SUBSCRIPTIONS' => NULL,
			'html5devicetype' => NULL,
			'html5screenresolution' => NULL,
			'html5last5page' => NULL,
			'html5mainview' => NULL,
			'html5userlocation' => NULL,
			'html5adclicks' => NULL,
			'html5adview' => NULL,
			'html5appview' => NULL,
			'html5usetime' => NULL,
			'html5date' => NULL,
			'html5frequency' => NULL,
			'html5pushmarks' => NULL,
		), 60 * 60);
	}

	public function testUserCanAuthenticateWithUsernameAndPassword() {
		$this->mc->delete($this->eid);
		FTAuth::useSession(false);
		FTAuth::clear();
		FTAuth::setLoginMode(FTAuth::MODE_PASSWORD);
		FTAuth::setCredentials($this->creds['username'], $this->creds['password']);
		$user = FTAuth::authenticate(true);
		$this->assertEquals(FTAuth::STATUS_SUCCESS, FTAuth::getAuthStatus('status'), "Correct username and password failed to authenticate the user correctly");
		$this->assertEquals($this->eid, $user->get('eid'), "Incorrect EID returned for authenticated user");
		$this->assertEquals($this->creds['username'], $user->get('userName'), "Incorrect username returned for authenticated user");
		FTAuth::clear();
		FTAuth::setCredentials('assankatest', 'invalidpassword');
		$user = FTAuth::authenticate(true);
		$this->assertEquals(FTAuth::STATUS_INVALIDCREDENTIALS, FTAuth::getAuthStatus('status'), "Invalid credentials not correctly recognised as such");
	}

	public function testUserCanAuthenticateWithCookie() {
		$cachedUser = $this->mc->get($this->eid);
		$time = new DateTime('+2 days', new DateTimeZone('UTC'));
		$cachedUser['skeyexpires'] = $time->format('Y-m-d H:i:s');
		$this->mc->set($this->eid, $cachedUser, 60 * 60);
		FTAuth::useSession(false);
		FTAuth::clear();
		FTAuth::setLoginMode(FTAuth::MODE_COOKIE);
		FTAuth::setCookies($this->cookies);
		$user = FTAuth::authenticate(true);
		$this->assertEquals(FTAuth::STATUS_SUCCESS, FTAuth::getAuthStatus('status'), "User wasn't authenticated as expected");
		$this->assertEquals($this->creds['username'], $user->get('userName'), "Incorrect username returned for authenticated user");
		$this->assertEquals(0, count($user->getDamCurlCmds()), "DAM API requests made where the user should have just been loaded from local DB");
	}

	public function testFtUsersCanAuthenticateWithJustRememberMeCookie() {
		// Verify no DAM Request
	}

	public function testInvalidPasswordContainingAngleBracketsIsRejected() {
		FTAuth::useSession(false);
		FTAuth::clear();
		FTAuth::setLoginMode(FTAuth::MODE_PASSWORD);
		FTAuth::setCredentials($this->creds['username'], 'xxx>xxx<xxx');
		$user = FTAuth::authenticate(true);
		$this->assertEquals(FTAuth::STATUS_INVALIDCREDENTIALS, FTAuth::getAuthStatus('status'), "Invalid credentials containing < and > not correctly recognised as such");
	}
}
