<?php
/**
 * FT Labs environment configuration
 *
 * @codingstandard ftlabs-phpcs
 * @copyright The Financial Times Limited [All rights reserved]
 */

require_once(__DIR__ . '/vendor/autoload.php');
//require_once $_SERVER['DOCUMENT_ROOT'] . '/vendor/autoload.php';



// Initialise the FTLabs logger for nicer dev errors, but disable reporting to the Labs system
$logger = FTLabs\Logger::init();
$logger->setHandlerMinSeverity('report', \Psr\Log\LogLevel::EMERGENCY);


error_reporting(error_reporting() ^ E_STRICT ^ E_DEPRECATED ^ E_NOTICE);

// Configure default caching of 10 mins
header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 600) . ' GMT');
