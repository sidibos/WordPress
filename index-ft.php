<?php
/**
 * FT Labs environment configuration
 *
 * @codingstandard ftlabs-phpcs
 * @copyright The Financial Times Limited [All rights reserved]
 */
//set CORE_PATH for heroku
if(!isset($_SERVER['CORE_PATH'])) $_SERVER['CORE_PATH'] = $_SERVER['DOCUMENT_ROOT'].'/assanka';

//require_once(__DIR__ . '/vendor/autoload.php');
//require_once $_SERVER['DOCUMENT_ROOT'] . '/vendor/autoload.php';

$dir = $_SERVER['DOCUMENT_ROOT'] . "/vendor";
if(!is_dir($dir)){
  echo 'is not a dir '.$dir;die;
} 
$dh  = opendir($dir);
$dir_list = array($dir);
while (false !== ($filename = readdir($dh))) {
    if($filename!="."&&$filename!=".."&&is_dir($dir.$filename))
        array_push($dir_list, $dir.$filename."/");
}
foreach ($dir_list as $dir) {
    foreach (glob($dir."*.php") as $filename)
        require_once $filename;
}


//include_all_php($_SERVER['DOCUMENT_ROOT'] . "/vendor");

if(!class_exists('FTLabs\Logger')){
die($_SERVER['DOCUMENT_ROOT'].'/vendor');
}
// Initialise the FTLabs logger for nicer dev errors, but disable reporting to the Labs system
$logger = FTLabs\Logger::init();
$logger->setHandlerMinSeverity('report', \Psr\Log\LogLevel::EMERGENCY);

require_once $_SERVER['CORE_PATH']."/helpers/common/v2/common";

error_reporting(error_reporting() ^ E_STRICT ^ E_DEPRECATED ^ E_NOTICE);

// Configure default caching of 10 mins
header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 600) . ' GMT');
