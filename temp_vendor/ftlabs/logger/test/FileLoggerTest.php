<?php
/**
 * Test for FileLogHandler - logging to file
 *
 * @codingstandard ftlabs-phpcs
 * @copyright The Financial Times Limited [All rights reserved]
 */


class FileLoggerTest extends PHPUnit_Framework_TestCase {
	const LOG_PATH = '/var/log/apps/testlogger.log';
	protected $logger;

	protected function setUp() {
		if (!is_dir('/var/log/apps/') && !is_writeable('/var/log')) $this->markTestSkipped("Missing /var/log/apps/");

		$this->logger = new FTLabs\FileLogHandler("testlogger");
		if (file_exists(self::LOG_PATH)) unlink(self::LOG_PATH);
	}
	protected function tearDown() {
		if (file_exists(self::LOG_PATH)) {
			chmod(self::LOG_PATH, 644);
			unlink(self::LOG_PATH);
		}
	}
	public function testCanLogStrings() {
		$input1 = " 𐌼𐌰𐌲 𐌲𐌻𐌴𐍃 𐌹̈𐍄𐌰𐌽, 𐌽𐌹 𐌼𐌹𐍃 𐍅𐌿 𐌽𐌳𐌰𐌽 𐌱𐍂𐌹𐌲𐌲𐌹𐌸.";
		$input2 = "aɪ kæn iːt glɑːs ænd ɪt dɐz nɒt hɜːt miː";
		$this->assertTrue($this->logger->write($input1));
		$output1 = file_get_contents(self::LOG_PATH);

		// Ignore exact datetime as that may have changed between writing log and reading from it
		$this->assertRegExp('/^[\d- :\.T\Z]+ '.preg_quote($input1)."\n$/", $output1, "Log contents don't match input");

		$this->assertTrue($this->logger->write($input2));
		$output2 = file_get_contents(self::LOG_PATH);

		// Ignore exact datetime as that may have changed between writing log and reading from it
		$this->assertRegExp('/^[\d- :\.T\Z]+ '.preg_quote($input1)."\n[\\d- :\.T\Z]+ ".preg_quote($input2)."\n$/", $output2, "Log contents don't match input");
	}
	public function testCanCopeWithDeletedLog() {
		$input1 = "Ég get etið gler án þess að meiða mig.";
		$input2 = "Minä voin syvvä st'oklua dai minule ei ole kibie. ";
		$this->assertTrue($this->logger->write($input1));
		$output1 = file_get_contents(self::LOG_PATH);

		// Ignore exact datetime as that may have changed between writing log and reading from it
		$this->assertRegExp('/^[\d- :\.T\Z]+ '.preg_quote($input1, '/')."\n$/", $output1, "Initial log contents don't match input");

		unlink(self::LOG_PATH);

		$this->assertTrue($this->logger->write($input2));
		$output2 = file_get_contents(self::LOG_PATH);

		// Ignore exact datetime as that may have changed between writing log and reading from it
		$this->assertRegExp('/^[\d- :\.T\Z]+ '.preg_quote($input2, '/')."\n$/", $output2, "Post delete log contents don't match input");

	}
	public function testCanCopeWithMovedLog() {
		$input1 = "Unë mund të ha qelq dhe nuk më gjen gjë.";
		$input2 = "Կրնամ ապակի ուտել և ինծի անհանգիստ չըներ։";
		$this->assertTrue($this->logger->write($input1));
		$output1 = file_get_contents(self::LOG_PATH);

		// Ignore exact datetime as that may have changed between writing log and reading from it
		$this->assertRegExp('/^[\d- :\.T\Z]+ '.preg_quote($input1)."\n$/", $output1, "Initial log contents don't match input");

		rename(self::LOG_PATH, '/var/log/apps/oldtestlogger.log');

		$this->logger->write($input2);
		$output2 = file_get_contents(self::LOG_PATH);

		// Ignore exact datetime as that may have changed between writing log and reading from it
		$this->assertRegExp('/^[\d- :\.T\Z]+ '.preg_quote($input2, '/')."\n$/", $output2, "Post move log contents don't match input");

	}
	public function testMovingLogFollowedByReintialiseWorks () {
		$input1 = "Мога да ям стъкло, то не ми вреди.";
		$input2 = " मी काच खाऊ शकतो, मला ते दुखत नाही.";
		$this->logger->write($input1);
		$output1 = file_get_contents(self::LOG_PATH);

		// Ignore exact datetime as that may have changed between writing log and reading from it
		$this->assertRegExp('/^[\d- :\.T\Z]+ '.preg_quote($input1)."\n$/", $output1, "Initial log contents don't match input");

		rename(self::LOG_PATH, '/var/log/apps/oldtestlogger.log');

		$this->logger->reinitialise();

		$this->logger->write($input2);
		$output2 = file_get_contents(self::LOG_PATH);

		// Ignore exact datetime as that may have changed between writing log and reading from it
		$this->assertRegExp('/^[\d- :\.T\Z]+ '.preg_quote($input2)."\n$/", $output2, "Post move log contents don't match input");

	}

	public function testDateFormatIsIso8601() {
		$this->assertTrue($this->logger->write('foo'));
		$output1 = file_get_contents(self::LOG_PATH);
		$this->assertRegExp('/^\d{4}-\d\d-\d\dT\d\d\:\d\d:\d\d\.\d{1,6}Z /', $output1, "Date is not in ISO8601 format");
	}

	public function testErrorWhenWritingDoesntCauseHardError() {
		touch(self::LOG_PATH);
		chmod(self::LOG_PATH, 000);

		// The error here is reported to the helpdesk via trigger_error (which phpunit can detect).
		try {
			$this->assertFalse($this->logger->write('foo'));
		} catch(PHPUnit_Framework_Error_Notice $e) {}
	}
}
