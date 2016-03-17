<?php

namespace FTBlogs\AtomPush;

use FTLabs\MySqlConnection;
use FTLabs\Logger;

use FTBlogs\AtomPush\InvalidXmlException;

require_once __DIR__ . '/Message.php';
require_once __DIR__ . '/InvalidXmlException.php';


class QueueProcessor
{
	const MAX_RUNTIME = 300; // Maximum run time no longer than 5 minutes

	/**
	 * @var Logger
	 */
	private $logger;

	/**
	 * @var MySqlConnection
	 */
	private $dbRead;

	/**
	 * @var MySqlConnection
	 */
	private $dbWrite;

	public function __construct(Logger $logger, MySqlConnection $dbRead, MySqlConnection $dbWrite) {
		$this->logger = $logger;
		$this->dbRead = $dbRead;
		$this->dbWrite = $dbWrite;
	}

	public function processQueue() {
		// Only push queue on LIVE environment:
		if (!isset($_SERVER['IS_LIVE']) || $_SERVER['IS_LIVE'] != 1) {
			echo PHP_EOL . 'Cannot push queue in non-production environment; aborting' . PHP_EOL;
			$this->logger->notice('Cannot push queue in non-production environment');
			return;
		}

		// Enforce maximum runtime
		$startTime = time();
		$maxTime = $startTime + self::MAX_RUNTIME;

		$this->cleanUpQueue();

		/* Process posts, and messages in order for each post with queued messages */

		/* @var FTLabs\MySqlResult $posts */
		$posts = $this->dbRead->query("SELECT * FROM assanka_atompush_content WHERE 1");
		$numPostsProcessed = $numPostsFailed = 0;

		$this->logger->info(
			'Pushqueue starting',
			array(
				'posts_count' => $posts->count(),
			)
		);

		$log = null;
		while ($this_post = $posts->getRow()) {

			$sentcount = $skippedcount = 0;
			$success = false;
			$messages = $this->dbRead->query("SELECT * FROM assanka_atompush_message WHERE {guid} and {destination} ORDER BY `time` ASC", $this_post);
			while ($this_message = $messages->getRow()) {

				$log = array(
					'act'           => 'push',
					'guid' 			=> $this_message['guid'],
					'dest' 	        => $this_message['destination'],
					'method' 		=> $this_message['method']
				);

				if ($this_post['attempts'] > 5) {
					$log['result'] = "maxattempts";
					$this->dbWrite->query("DELETE FROM assanka_atompush_message WHERE {id}", $this_message);
					$this->dbWrite->query("UPDATE assanka_atompush_content SET attempts=0 WHERE {guid}", $this_post);
					$skippedcount++;

					$this->logger->info('', $log);
					continue;
				}

				try {
					$message = new Message($this_message, $this->logger);
					$success = $message->push();
				} catch (InvalidXmlException $e) {
					$this->dbWrite->query("DELETE FROM assanka_atompush_message WHERE {id}", $this_message);
					$skippedcount++;
					$success = false;
				}

				// If unsuccessful, don't send any other queued messages for this post in case of continuity errors.  Move on to the next post.
				if (!$success) {
					// Increment the attempts count.
					$this->dbWrite->query("UPDATE assanka_atompush_content SET `attempts` = `attempts` + 1, `lastattempt` = NOW() WHERE id = %d", $this_post['id']);

					$this->logger->notice('', array(
						'act'=>'stopcontentqueue',
						'id'=>$this_post['id'],
						'guid'=>$this_post['guid'],
						'sent'=>$sentcount,
						'skipped'=>$skippedcount,
					));
					$numPostsFailed++;
					continue 2;
				} else {
					// If the request was successful, remove the message from the queue
					$this->dbWrite->query("DELETE FROM assanka_atompush_message WHERE {id}", $this_message);
					$sentcount++;
				}
			}

			// All requests for this post completed, so remove it from the content queue.
			$this->dbWrite->query("DELETE FROM assanka_atompush_content WHERE {id}", $this_post);
			$this->logger->info('', array(
				'act'=>'contentcomplete',
				'id'=>$this_post['id'],
				'guid'=>$this_post['guid'],
				'sent'=>$sentcount,
				'skipped'=>$skippedcount,
			));

			$numPostsProcessed++;

			// Check runtime and do next post
			if (time() > $maxTime) break;
			sleep(1);
		}

		$this->logger->info(
			'Pushqueue finished',
			array(
				'posts_processed' => $numPostsProcessed,
				'posts_failed' => $numPostsFailed
			)
		);
	}

	public function cleanUpQueue() {
		// Posts with no GUID
		$this->dbWrite->query("DELETE FROM assanka_atompush_content WHERE `guid` IS NULL OR `guid` =%s", '');

		// Messages with no parent post are deleted from the messages table.
		$this->dbWrite->query("DELETE m FROM assanka_atompush_message m LEFT JOIN assanka_atompush_content c ON m.guid=c.guid WHERE c.guid IS NULL");

		// Posts with no messages
		$this->dbWrite->query("DELETE c FROM assanka_atompush_content c LEFT JOIN assanka_atompush_message m ON m.guid=c.guid WHERE c.guid IS NULL");

	}

} 