<?php
/**
 * A global file for all tests
 *
 * @codingstandard ftlabs-phpcs
 * @copyright The Financial Times Limited [All rights reserved]
 */

define('PROJROOT', realpath(__DIR__ . '/../'));

require_once PROJROOT . '/vendor/autoload.php';

require_once 'MockFTAuthCommon.php';
require_once 'MockFTSession.php';
