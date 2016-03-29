<?php
/*
######################################################################

PHPUnit test for the FTUser class

(c) Copyright Assanka Limited [All rights reserved]
######################################################################
*/

use FTLabs\HTTPRequest;
use FTLabs\Memcache;

// Class under test
use FTLabs\FTAuth;
use FTLabs\FTUser;

class FTUserTest extends PHPUnit_Framework_TestCase{

	private $eid = '4071867'; // shilstonregister@assanka.com
	private $eid_fake = '1'; // Unknown user
	private $mc = false; // Memcache instance for tests

	public function setUp() {

		// Enable all sources for tests by default
		FTUser::$enabledDAMSources = array('core', 'assanka', 'mobile');

		if (!$this->mc) {
			$this->mc = FTUser::getNamespacedMemcache();
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
			'briefingsemail' => null,
			'subscriptions' => null,
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
			'username' => 'assanka_test_reg@jaysethi.com',
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
			'briefingsemail' => NULL,
			'subscriptions' => NULL,
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

	public function testKnownUserCanBeInstantiated() {
		$this->assertInternalType('object', FTAuth::createUser($this->eid));
	}

	public function testInvalidEidCannotBeInstantiated() {
		try {
			FTAuth::createUser("-1");
			$e = null;
		} catch (Exception $e) { }
		$this->assertTrue(is_object($e));
		$this->assertContains('No erights ID supplied for this user', $e->getMessage());
	}

	public function testUnknownEidIsNotValid() {
		$user = FTAuth::createUser($this->eid_fake);
		$this->assertEquals(FTUser::VALIDITY_UNKNOWN, $user->getValidity(), "User validity was not UNKNOWN despite autoload being false");
	}

	public function testUnknownEidIsInvalid() {
		$this->markTestSkipped('This test was investigated on 10th April 2012 and found to be failing since approx 1st Feb.  FT have been notified.  In short, DAM emits a 500 rather than a 404 when doing a GET for an invalid EID.');
		$user = FTAuth::createUser($this->eid_fake, true);
		$this->assertEquals(FTUser::VALIDITY_INVALID, $user->getValidity(), "Unknown user ID was not flagged as invalid despite autoload being enabled");
	}

	public function testCoreDataFetchReturnsCorrectDataForKnownUser() {
		$this->mc->delete($this->eid);

		// Instantiating a user that does not exist should query DAM
		$user = FTAuth::createUser($this->eid, true);
		$this->assertEquals(FTUser::VALIDITY_VALID, $user->getValidity(), "User validity was not VALID despite autoload being true");
		foreach ($user->getDamCurlCmds() as $cmd) {
			$this->assertEquals(200, $cmd[1], "API request for ".$cmd[2]." source returned non-200 HTTP response code: ".print_r($cmd, true));
		}
		$this->assertEquals(count(FTUser::$enabledDAMSources), count($user->getDamCurlCmds()), "Number of API requests made does not match number of enabled sources");
		$this->assertEquals('shilstonregister@assanka.com', $user->get('userName'), 'Known data was not correct for known user');

		// Instantiating a user that does exist should not query DAM
		$user = FTAuth::createUser($this->eid, true);
		$this->assertEquals(FTUser::VALIDITY_VALID, $user->getValidity(), "User validity was not VALID despite autoload being true");
		$this->assertEquals(0, count($user->getDamCurlCmds()), "Instantiating a known user queried DAM syncronously despite data being available locally");
		$this->assertEquals('shilstonregister@assanka.com', $user->get('userName'), 'Known data was not correct for known user');
	}

	public function testFTUserCanBeInstantiatedWithVariableDAMSources() {

		// Enable all DAM sources
		FTUser::$enabledDAMSources = array('core', 'assanka', 'mobile');
		$mc = FTUser::getNamespacedMemcache();
		$mc->delete($this->eid);
		$user = FTAuth::createUser($this->eid, true);
		$this->assertEquals(3, count($user->getDamCurlCmds()), "Number of API requests made does not match number of specified sources");

		// Enable two sources
		FTUser::$enabledDAMSources = array('core', 'assanka');
		$mc = FTUser::getNamespacedMemcache();
		$mc->delete($this->eid);
		$user = FTAuth::createUser($this->eid, true);
		$this->assertEquals(2, count($user->getDamCurlCmds()), "Number of API requests made does not match number of specified sources");

		// Enable only the core source
		FTUser::$enabledDAMSources = array('core');
		$mc = FTUser::getNamespacedMemcache();
		$mc->delete($this->eid);
		$user = FTAuth::createUser($this->eid, true);
		$this->assertEquals(1, count($user->getDamCurlCmds()), "Number of API requests made does not match the single source specified");
		$this->assertEquals('shilstonregister@assanka.com', $user->get('userName'), 'Known data was not correct for known user');

		// Enable only a third-party source
		FTUser::$enabledDAMSources = array('mobile');
		$mc = FTUser::getNamespacedMemcache();
		$mc->delete($this->eid);
		$user = FTAuth::createUser($this->eid, true);
		$this->assertEquals(1, count($user->getDamCurlCmds()), "Number of API requests made does not match the single source specified");
		$this->assertEquals(false, $user->get('userName'), 'Data that should not be present on a user using a third-party source was present');
	}

	public function testNewEtagReturnedFromUpload() {
		$user = FTAuth::createUser($this->eid, true);
		$user->updateFromDAM();
		$newdata = time().__FUNCTION__;
		$currentetag_assanka = $user->get('etag_assanka');
		$currentetag_mobile = $user->get('etag_mobile');
		$user->setUserData(array("html5devicetype"=>$newdata, 'about'=>$newdata));
		$this->assertNotEquals($user->get("etag_mobile"), $currentetag_mobile, "Updating a user did not increment the etag");
		$this->assertNotEquals($user->get("etag_assanka"), $currentetag_assanka, "Updating a user did not increment the etag");
	}

	public function testUserObjectReflectsUnderlyingData() {
		$user = FTAuth::createUser($this->eid, true);
		$cachedUser = $this->mc->get($this->eid);
		$cachedUser['etag_assanka'] = 1;
		$this->mc->set($this->eid, $cachedUser, 60 * 60);
		$user->load();
		$this->assertEquals($user->get("etag_assanka"), 1, "DB change in etag not reflected in user object");
	}

	public function testUploadSucceedsDespiteOutOfDateEtag() {
		$cachedUser = $this->mc->get($this->eid);
		$cachedUser['etag_assanka'] = 1;
		$cachedUser['etag_mobile'] = 1;
		$this->mc->set($this->eid, $cachedUser, 60 * 60);
		$user = FTAuth::createUser($this->eid, true);
		$this->assertEquals($user->get('etag_assanka'), 1, 'ETag could not be initialised to the value required for this test');
		$this->assertEquals($user->get('etag_mobile'), 1, 'ETag could not be initialised to the value required for this test');
		$newdata = time();
		$user->setUserData(array("html5devicetype"=>$newdata, "about"=>$newdata));
		$this->assertNotEquals($user->get("etag_assanka"), 1, "Intentionally wrong Etag not replaced by corrected version when expected");
		$this->assertNotEquals($user->get("etag_mobile"), 1, "Intentionally wrong Etag not replaced by corrected version when expected");
	}

	public function testReadOnlyKeysCannotBeSet() {
		$user = FTAuth::createUser($this->eid, true);

		// Attempt to set value of a read only key
		try {
			$user->setUserData(array('subscriptions'=>'xxxxxx'));
			$e = null;
		} catch (Exception $e) { }
		$this->assertTrue(is_object($e), "Able to write to a read-only key");
		$this->assertContains('cannot be written', $e->getMessage());

		// Attempt to set value of a read only key
		try {
			$user->setUserData(array('datemodified'=>'xxxxxx'));
			$e = null;
		} catch (Exception $e) { }
		$this->assertTrue(is_object($e), "Able to write to a read-only key");
		$this->assertContains('cannot be written', $e->getMessage());
	}

	public function testReadOnlyFieldsAreNotIncludedInUpload() {
		$user = FTAuth::createUser($this->eid, true);

		$oldvalue = $user->get('html5date');
		$newdata = time().__FUNCTION__;
		$user->setUserData(array('html5date'=>$newdata));

		// Check that upload does not try to write read-only keys back to DAM
		foreach ($user->getDamCurlCmds() as $cmd) {
			$this->assertTrue((strpos($cmd[0], '"SUBSCRIPTIONS"') === false), "Attempt to write read-only key to DAM");
		}

		// Restore old data
		$user->setUserData(array('html5date'=>$oldvalue), true);
		$this->assertEquals($oldvalue, $user->get('html5date'));
	}

	public function testUpdatedDataCanBeUploaded() {

		$user = FTAuth::createUser($this->eid, true);

		$keys = array('html5devicetype', 'html5screenresolution', 'html5last5page', 'html5mainview', 'html5userlocation', 'html5adclicks', 'html5adview', 'html5usetime', 'html5date', 'html5frequency', 'html5pushmarks', 'about');
		$oldvalues = $user->getAll();
		$newdata = $newdata = substr(microtime(true),-15);

		// Set keys individually
		foreach ($keys as $key) $user->setUserData(array($key=>$newdata));

		// Set several keys at once
		$user->setUserData(array('html5devicetype'=>$newdata, 'html5frequency'=>$newdata));

		// Skip the test if DAM is down
		if ($user->getDamError() == 'damdown') {
			$this->markTestSkipped('DAM is down (received error "damdown")');
		}

		// Check data was stored locally
		$this->assertEquals($newdata, $user->get('html5date'), 'New data failed to save locally');
		$this->assertEquals($newdata, $user->get('html5frequency'), 'New data failed to save locally');

		// Upload and then fetch
		$this->assertEquals(null, $user->getDamError(), 'Error saving data to DAM');
		$user->updateFromDAM();
		$this->assertEquals(null, $user->getDamError(), 'Error retrieving data from DAM');

		// Check new data was stored
		$this->assertEquals($newdata, $user->get('html5date'), 'New data failed to save in DAM');
		$this->assertEquals($newdata, $user->get('about'), 'New data failed to save in DAM');

		// Restore old data
		$user->setUserData($oldvalues, true);
		$this->assertEquals($oldvalues['html5date'], $user->get('html5date'));
	}

	public function testOutlierValuesCanBeStoredAndRetrieved() {
		$this->markTestSkipped('The DAM spec stats that the service accepts valid JSON, however, it appears to actually accept only a single level of key-value pairs where the values are strings.  So it is not true JSON, but JSON-like. (KNOWN ISSUE FT NOTIFIED AB:20110428)');

		$user = FTAuth::createUser($this->eid, true);

		$keys = array('html5devicetype', 'html5screenresolution', 'html5last5page', 'html5mainview', 'html5userlocation', 'html5adclicks', 'html5adview', 'html5usetime', 'html5date', 'html5frequency', 'html5pushmarks', 'about');
		$oldvalues = $user->getAll();

		// Set several keys at once
		$user->setUserData(array(
			'html5devicetype'=>'TEST DATA \u1F51\u043F &lt; & \'"abcdef,[] ὑпііϚϙბĔ',
			'html5frequency'=>'null',
			'html5screenresolution'=>'NULL',
			'html5userlocation'=>null,
			'about'=>'false',
			'html5adclicks'=>true,
			'html5last5page'=>45,
			'html5mainview'=>'456',
		));


		// Upload and then fetch
		$this->assertEquals(null, $user->getDamError(), 'Error saving multibyte and non-string data to DAM');
		$user->updateFromDAM();
		$this->assertEquals(null, $user->getDamError(), 'Error retrieving data from DAM');

		// Check new data was stored
		$this->assertEquals('TEST DATA \u1F51\u043F &lt; & \'"abcdef,[] ὑпііϚϙბĔ', $user->get('html5devicetype'), 'Failed to store multibyte character data');
		$this->assertEquals('null', $user->get('html5frequency'), 'Failed to store the string \'null\'');
		$this->assertEquals('NULL', $user->get('html5screenresolution'), 'Failed to store the string \'NULL\'');
		$this->assertEquals(null, $user->get('html5userlocation'), 'Failed to store literal null');
		$this->assertEquals('false', $user->get('about'), 'Failed to store the string \'false\'');
		$this->assertEquals(true, $user->get('html5adclicks'), 'Failed to store boolean true');
		$this->assertEquals(45, $user->get('html5last5page'), 'Failed to store numeric 45');
		$this->assertEquals('456', $user->get('html5mainview'), 'Failed to store string \'456\'');

		// Restore old data
		$user->setUserData($oldvalues, true);
		$this->assertEquals($oldvalues['html5date'], $user->get('html5date'));
	}

	public function testModificationDateUpdatesWhenDataIsSaved() {
		$this->markTestSkipped('Having switched from a MySQL-backed cache to Memcache, this behaviour is no longer supported.');

		$user = FTAuth::createUser($this->eid, true);

		$oldvalues = $user->getAll();
		$newdata = new DateTime('now', new DateTimeZone('UTC'));
		$newdata = $newdata->getTimestamp();
		$olddatemodified = $user->get('datemodified');
		sleep(2);
		$user->setUserData(array('about'=>$newdata));
		$moddate2 = $user->get('datemodified');
		$this->assertNotEquals($olddatemodified, $moddate2, 'Modification date did not change when new data was saved');
		$this->assertEquals($newdata, $user->get('about'), 'Data was not successfully saved');
		sleep(2);
		$user->setUserData(array('about'=>$newdata));
		$moddate3 = $user->get('datemodified');
		$this->assertEquals($moddate2, $moddate3, 'When setting data containing no changes, modification date unexpectedly changed');
		sleep(2);
		$user->setUserData(array('about'=>$newdata.'-changed'));
		$moddate4 = $user->get('datemodified');
		$this->assertNotEquals($olddatemodified, $moddate4, 'Modification date did not change when new data was saved');
		$this->assertNotEquals($moddate3, $moddate4, 'Modification date did not change when new data was saved');

		// Restore old data
		$user->setUserData($oldvalues, true);
	}
}
