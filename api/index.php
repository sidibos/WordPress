<?php
/**
 * Temporary redirect for Clamo Engine API for the migration of fastFT.
 * This file will redirect any requests to /api/ to www.ft.com/fastft/api/
 * This is to mitigate the potential problems with synchronising WebApp point release
 * and also potential delays in WebApp users updating the app version.
 *
 * This whole directory should be removed once WebApp and the Home Page Widget are using the fastft endpoint
 *
 * User: jan.majek
 * Date: 01/12/2015
 * Time: 16:49
 */

$baseUrl = 'www.ft.com/fastft/api/?';

// On lower environments, prefix the URL with environment name
if (!isset($_SERVER['IS_LIVE']) || $_SERVER['IS_LIVE'] != 1) {
	list ($env, $dummy) = explode('.', $_SERVER['SERVER_NAME'], 2);
	$baseUrl = $env . '.' . $baseUrl;
}

header('Location: http://' . $baseUrl . $_SERVER['QUERY_STRING'], true, 301);
exit;
