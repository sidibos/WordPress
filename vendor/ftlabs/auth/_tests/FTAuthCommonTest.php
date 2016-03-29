<?php

use FTLabs\FTAuthCommon;

require_once 'MockFTSession.php';
require_once 'MockFTAuthCommon.php';

/**
 * Tests the FTAuthCommon class.
 *
 * @copyright The Financial Times Limited [All Rights Reserved]
 */
class FTAuthCommonTest extends PHPUnit_Framework_TestCase {

	/**
	 * @var FTAuthCommon An instance of FTAuthCommon
	 */
	protected $auth;

	// TODO:SG:20130207: We really don't want to rely on these for testing _OUR_ code..
	protected $configset = array(
			'live' => array(
					'cookies' => array(
							'no_skey' => array(
									'FT_U'        =>'_EID=4071867_PID=4004071867_TIME=%5BWed%2C+09-Mar-2011+11%3A36%3A43+GMT%5D_RI=1_',
									'FT_Remember' =>'4071867:TK7323745493656747634:FNAME=:LNAME=:EMAIL=shilstonregister@assanka.com'
									),
							'with_skey' => array(
									'FT_U'        =>'_EID=4071867_PID=4004071867_TIME=%5BWed%2C+09-Mar-2011+11%3A36%3A43+GMT%5D_SKEY=nEv0WyEggRyHs%6DxgLBUdOA%3D%3D_RI=1_',
									'FT_Remember' =>'4071867:TK7323745493656747634:FNAME=:LNAME=:EMAIL=shilstonregister@assanka.com'
							)
						),
					'eid' => '4071867',
					'pid' => '4004071867',
					'username' => 'shilstonregister@assanka.com',
					'pseudonym' => 'TestUserRegisterPseudonym'
				),
			'test' => array(
					'cookies' => array(
							'no_skey' => array(
										'FT_U'=>'_EID=87737853_PID=4087737853_TIME=%5BMon%2C+06-Aug-2012+14%3A46%3A16+GMT%5D_RI=1_I=0_',
										'FT_Remember'=>'87737853:TK6496562230353019106:FNAME=Assanka:LNAME=Test:EMAIL=assanka_test_reg@jaysethi.com'
									),
							'with_skey' => array(
										'FT_U'=>'_EID=87737853_PID=4087737853_TIME=%5BMon%2C+06-Aug-2012+14%3A46%3A16+GMT%5D_SKEY=8t89XCuILkiAs1VOw58OtA%3D%3D_RI=1_I=0_',
										'FT_Remember'=>'87737853:TK6496562230353019106:FNAME=Assanka:LNAME=Test:EMAIL=assanka_test_reg@jaysethi.com'
									)
							),

					'eid' => '87737853',
					'pid' => '4087737853',
					'username' => 'assanka_test_reg@jaysethi.com',
					'pseudonym' => 'User87737853'
				)
		);


	private $cookiesNoSkey;
	private $cookiesWithSkey;
	private $templateVars;

	public function setUp() {
		$this->auth = $this->createMockFTAuth();

		$configSet = (isset($_SERVER['DAMENVIRONMENT']) and isset(FTAuthCommon::$environments[$_SERVER['DAMENVIRONMENT']])) ? $_SERVER['DAMENVIRONMENT'] : 'live';

		// Apply the specific configuration set:
		$this->cookiesNoSkey = $this->configset[$configSet]['cookies']['no_skey'];
		$this->cookiesWithSkey = $this->configset[$configSet]['cookies']['with_skey'];


		$this->templateVars = array(
			'eid' => $this->configset[$configSet]['eid'],
			'pid' => $this->configset[$configSet]['pid'],
			'username' => $this->configset[$configSet]['username'],
			'pseudonym' => $this->configset[$configSet]['pseudonym']
			);

	}

	public function createMockFTAuth() {
		return new MockFTAuthCommon();
	}

	public function buildApiResponse($template, array $values) {
		$response = $template;

		foreach($values as $key => $value) {
			$response = str_replace('{'.$key.'}', $value, $response);
		}

		return $response;
	}

	/**
	 * Given a valid response is returned from the FTs API,
	 * A corresponding FTUser object should be created, and the FTAuth status set to STATUS_SUCCESS.
	 */
	public function testSuccessfulAuthenticateAgainstApi() {

		// This uses a real eid as FTUser is tightly coupled to the database :(
		$apiResponse = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<ft-login version="1.0">
		<response>success</response>
		<reason>ok</reason>
		<message>Request OK</message>
		<username>{username}</username>
	<account>
		<active-products>
			<product>P0</product>
			<product>Tools</product>
		</active-products>
		<username>{username}</username>
		<eid>{eid}</eid>
		<pid>{pid}</pid>
	</account>
</ft-login>
XML;

		$response = $this->buildApiResponse($apiResponse, $this->templateVars);

		// Set the mock response.
		$this->auth->setNextAuthResponse($response, 200);

		// Set up auth preconditions.
		$this->auth->setCredentials($this->templateVars['username'], 'password');
		$this->auth->useSession(false);
		$this->auth->setLoginMode(FTAuthCommon::MODE_PASSWORD);

		// TODO:SG:20130207: This may fail if the memcache state key 'ftco/registration/state' suggests that registration is down.
		$user = $this->auth->authenticate(true);

		$this->assertEquals(FTAuthCommon::STATUS_SUCCESS, $this->auth->getAuthStatus('status'));
		$this->assertInstanceOf($this->auth->getUserClass(), $user);
		$this->assertEquals($this->templateVars['eid'], $user->get('eid'));
	}

	/**
	 * Given an invalid response is received from the FTs API,
	 * The authStatus should be set to STATUS_APIERROR and the user returned by FTAuthCommon->authenticate should be null.
	 */
	public function testNoAuthenticationOnInvalidApiResponse() {

		// Set the mock response.
		$this->auth->setNextAuthResponse("An Internal Error Occurred (Mock Response)", 500);

		// Set auth preconditions.
		$this->auth->setCredentials($this->templateVars['username'], 'password');
		$this->auth->useSession(false);
		$this->auth->setLoginMode(FTAuthCommon::MODE_PASSWORD);

		$user = $this->auth->authenticate(true);

		$this->assertEquals(FTAuthCommon::STATUS_APIERROR, $this->auth->getAuthStatus('status'));
		$this->assertEquals(null, $user);
	}

	/**
	 * Given a successful API response, with an out of date, non-existant erights ID -
	 * The authStatus should be set to STATUS_USERNOTFOUND and the returned user from authenticate should be null.
	 */
	public function testUserNotFoundStatus() {

		// This response contains an extinct erights id
		$apiResponse = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<ft-login version="1.0">
		<response>success</response>
		<reason>ok</reason>
		<message>Request OK</message>
		<username>shilstonregister@assanka.com</username>
	<account>
		<active-products>
			<product>P0</product>
			<product>Tools</product>
		</active-products>
		<username>shilstonregister@assanka.com</username>
		<eid>1122334455</eid>
		<pid>88776655</pid>
	</account>
</ft-login>
XML;

		// Set mock response.
		$this->auth->setNextAuthResponse($apiResponse, 200);

		// Set preconditions.
		$this->auth->setCredentials($this->templateVars['username'], 'password');
		$this->auth->useSession(false);
		$this->auth->setLoginMode(FTAuthCommon::MODE_PASSWORD);

		$user = $this->auth->authenticate(true);

		$this->assertEquals(FTAuthCommon::STATUS_USERNOTFOUND, $this->auth->getAuthStatus('status'));
		$this->assertEquals(null, $user);
	}

	/**
	 * Given an 'invalid-credentials' response from the FTs API -
	 * The authStatus should be set to STATUS_INVALIDCREDENTIALS.
	 *
	 * REVIEW:SG:20130207: The method that gets the eid from the XML is also setting the authStatus. This completely violates single responsibility principle.
	 */
	public function testAuthFailsInvalidCredentials() {

		// Required internally by getEidFromXml :( (Just for logging).
		$this->auth->setCredentials('user@local.com', 'password');

		// This response contains an extinct erights id
		$apiResponse = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<ft-login version="1.0">
		<response>failure</response>
		<reason>invalid-credentials</reason>
		<message>account is null for given credentials</message>
	<username>user@local.com</username>
</ft-login>
XML;

		$eid = $this->auth->getEidFromXML($apiResponse);
		$this->assertFalse($eid);
		$this->assertEquals(FTAuthCommon::STATUS_INVALIDCREDENTIALS, $this->auth->getAuthStatus('status'));
	}

	/**
	 * Given an 'inactive-account' response from the FTs API -
	 * The authStatus should be set to STATUS_INACTIVEACCOUNT.
	 *
	 * REVIEW:SG:20130207: The method that gets the eid from the XML is also setting the authStatus. This completely violates single responsibility principle.
	 */
	public function testAuthFailsWithInactiveAccount() {

		// Required internally by getEidFromXml :(
		$this->auth->setCredentials('user@local.com', 'password');

		// This response contains an extinct erights id
		$apiResponse = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<ft-login version="1.0">
	<response>failure</response>
	<reason>inactive-account</reason>
	<message>account belongs to holding group</message>
	<username>user@local.com</username>
</ft-login>
XML;

		$eid = $this->auth->getEidFromXML($apiResponse);
		$this->assertFalse($eid);
		$this->assertEquals(FTAuthCommon::STATUS_INACTIVEACCOUNT, $this->auth->getAuthStatus('status'));
	}

	/**
	 * Given an 'error' response from the FTs API -
	 * The authStatus should be set to STATUS_APIERROR.
	 *
	 * REVIEW:SG:20130207: The method that gets the eid from the XML is also setting the authStatus. This completely violates single responsibility principle.
	 */
	public function testAuthFailsMissingPassword() {

		// Required internally by getEidFromXml :(
		$this->auth->setCredentials('user@local.com', 'password');

		// This response contains an extinct erights id
		$apiResponse = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<ft-login version="1.0">
	<response>failure</response>
	<reason>error</reason>
	<message>password is empty or null</message>
	<username>user@local.com</username>
</ft-login>
XML;

		$eid = $this->auth->getEidFromXML($apiResponse);
		$this->assertFalse($eid);
		$this->assertEquals(FTAuthCommon::STATUS_APIERROR, $this->auth->getAuthStatus('status'));
	}

	/**
	 * Given a success response from the FTs API -
	 * 	Which contains an EMPTY EID FIELD
	 * The authStatus should be STATUS_APIERROR
	 */
	public function testAuthFailsMissingEid() {

		// Required internally by getEidFromXml :(
		$this->auth->setCredentials('user@local.com', 'password');

		// This response contains an extinct erights id
		$apiResponse = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<ft-login version="1.0">
		<response>success</response>
		<reason>ok</reason>
		<message>Request OK</message>
		<username>shilstonregister@assanka.com</username>
	<account>
		<active-products>
			<product>P0</product>
			<product>Tools</product>
		</active-products>
		<username>shilstonregister@assanka.com</username>
		<eid></eid>
		<pid>88776655</pid>
	</account>
</ft-login>
XML;

		$eid = $this->auth->getEidFromXML($apiResponse);
		$this->assertFalse($eid);
		$this->assertEquals(FTAuthCommon::STATUS_APIERROR, $this->auth->getAuthStatus('status'));
	}


	/**
	 * Given valid cookies -
	 *  An FTUser should be created by authenticateWithCookies
	 */
	public function testAuthUsingCookiesUsingSKey() {

		$this->auth->setCookies($this->cookiesWithSkey);
		$user = null;
		$this->auth->cookieAuth($user, $this->auth->getFTCookieData());

		$this->assertInstanceOf($this->auth->getUserClass(), $user);
	}

	/**
	 * Given Cookies with an EID, but no SKEY,
	 * and the host is trusted -
	 * 	Authenticate the EID if it is valid.
	 */
	public function testAuthUsingCookiesTrusted() {

		$this->auth->setCookies($this->cookiesNoSkey);

		$this->auth->isTrusted(true);

		$user = null;
		$this->auth->cookieAuth($user, $this->auth->getFTCookieData());

		$this->assertInstanceOf($this->auth->getUserClass(), $user);
	}


	public function testTryAuthenticateFailsIfauthRequestThrowsException() {
		$this->auth->throwExceptionInAuthRequest(true);

		// Set auth preconditions.
		$this->auth->setCredentials($this->templateVars['username'], 'password');
		$this->auth->useSession(false);
		$this->auth->setLoginMode(FTAuthCommon::MODE_PASSWORD);

		$user = $this->auth->authenticate(true);

		$this->assertFalse($user);
	}
}