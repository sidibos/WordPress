#!/usr/bin/php
<?php
/**
 * Atom Push queue
 *
 * Sends queued push jobs to ATOM endpoints.  To be run using standard cron job, because wp-cron runs per-blog
 *
 * @copyright The Financial Times Limited [All rights reserved]
 */

use FTLabs\MySqlConnection;
use FTLabs\Logger;

use FTBlogs\AtomPush\QueueProcessor;

require_once __DIR__ . '/../../../../vendor/autoload.php';
require_once __DIR__ . '/../../../wp-config-db.php';

require_once __DIR__ . '/classes/QueueProcessor.php';

// Prepare logger
FTLabs\Logger::init();
$logger = new Logger('blogs-atompush');
// Disable all reporting to FT Labs error aggregator (129 is one higher than the highest severity level)
$logger->setHandlerMinSeverity('report', 129);

// Prepare DB connections
$dbRead = new MySqlConnection(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME);
$dbRead->setReconnectOnFail(true);
$dbWrite = new MySqlConnection(WRITE_DB_HOST, WRITE_DB_USER, WRITE_DB_PASSWORD, WRITE_DB_NAME);
$dbWrite->setReconnectOnFail(true);

// Process the queue
$queueProcessor = new QueueProcessor($logger, $dbRead, $dbWrite);
$queueProcessor->processQueue();
