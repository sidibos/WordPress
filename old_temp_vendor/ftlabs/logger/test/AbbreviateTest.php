<?php
/**
 * Uh
 *
 * @codingstandard ftlabs-phpcs
 * @copyright The Financial Times Limited [All rights reserved]
 */

use \Psr\Log\LogLevel;

class AbbreviateTest extends PHPUnit_Framework_TestCase
{
	function setUp() {
		$load = sys_getloadavg();
		if ($load[0] >= 5) $this->markTestSkipped("Load too high");
	}

	function testCircularObject() {
		$a = new stdClass;
		$a->self = $a;
		$a->array = array($a);
		$a->sub = (object)array('ref'=>&$a);

		$log = \FTLabs\ErrorLog::createFromEnvironment('notice', 'message', array('error'=>array('errno'=>1), 'object'=>$a));
		$f = new \FTLabs\HtmlLogFormatter("template_dev_html");

		list(,$body) = $f->formattedErrorLog($log);

		$this->assertNotContains('Too many items', $body);
		$this->assertNotContains('** invalid input **', $body);
		$this->assertContains('see other reference', $body);
	}
}

