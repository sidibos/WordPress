<?php
header('HTTP/1.1 200 OK');
header('Content-type: text/html');
die('');

set_error_handler(function($errno, $errstr, $errfile, $errline, $context) {
	header('HTTP/1.1 500 Internal Error');

	$logger = new FTLabs\Logger('blogs-gtg');

	// Disable all reporting to FT Labs error aggregator (129 is one higher than the highest severity level)
	$logger->setHandlerMinSeverity('report', 129);

	$logger->log(
		$logger->severityForPhpError($errno),
		$errstr,
		array(
			'errno'   => $errno,
			'file'    => $errfile,
			'line'    => $errline,
			'context' => @json_encode($context),
		)
	);
}, E_ALL | E_STRICT);

spl_autoload_register(function($class) {
	$prefix = 'FTBlogs\\Gtg\\';
	require_once __DIR__ . '/classes/' . str_replace('\\', DIRECTORY_SEPARATOR, (strpos($class, $prefix) === 0) ? substr($class, strlen($prefix)) : $class) . '.php';
});

require_once __DIR__ . '/../wp-config.php';
require_once __DIR__ . '/../temp_vendor/autoload.php';

use FTBlogs\Gtg;


$db = array(
	'dbread' => array(
		'host' => DB_HOST,
		'user' => DB_USER,
		'pass' => DB_PASSWORD,
		'name' => DB_NAME
	),
//	'dbmaster' => array(
//		'host' => WRITE_DB_HOST,
//		'user' => WRITE_DB_USER,
//		'pass' => WRITE_DB_PASSWORD,
//		'name' => WRITE_DB_NAME
//	),
);

$gtgChecker = new Gtg\Checker();
$failures = $gtgChecker
	->registerGtgCheck(new Gtg\Check\Load())
	->registerGtgCheck(new Gtg\Check\Db($db))
//	->registerGtgCheck(new Gtg\Check\DbReplicationLag($dbs))
	->isGoodToGo($_REQUEST)
;

if (count($failures)) {
	$response = json_encode(
		array (
			'status' => 'unhealthy',
			'failures' => $failures,
		)
	);

	$logger = new FTLabs\Logger('blogs-gtg');

	// Disable all reporting to FT Labs error aggregator (129 is one higher than the highest severity level)
	$logger->setHandlerMinSeverity('report', 129);

	$logger->alert('Node unhealthy', array('response' => $response));

	header('HTTP/1.1 503 Service unavailable');
	header('Content-type: application/json');
	die ($response);
}

