<?php

namespace FTBlogs\AtomPush;

use FTLabs\HTTPRequest;
use FTLabs\MySqlConnection;
use FTLabs\Logger;

use FTBlogs\AtomPush\InvalidXmlException;

require_once __DIR__ . '/InvalidXmlException.php';


class Message
{
	private $data = array();

	/**
	 * @var Logger
	 */
	private $logger;

	public function __construct(array $data, Logger $logger) {
		$this->data = $data;
		$this->logger = $logger;
	}

	/**
	 * @return bool
	 * @throws InvalidXmlException if the body is not a valid XML
	 */
	public function push() {
		// Only push queue on LIVE environment:
		if (!isset($_SERVER['IS_LIVE']) || $_SERVER['IS_LIVE'] != 1) {
			echo PHP_EOL . 'Cannot push message in non-production environment; aborting' . PHP_EOL;
			$this->logger->notice('Cannot push message in non-production environment');
			return false;
		}

		// Init log entry for this message
		$log = array(
			'act'           => 'push',
			'guid' 			=> $this->data['guid'],
			'dest' 	        => $this->data['destination'],
			'method' 		=> $this->data['method']
		);

		// Skip if the body of the queued ATOM message is invalid XML
		if ($this->data['method'] != 'DELETE' and !@simplexml_load_string($this->data['body'])) {
			$log['result'] = "invalidxml";
			$this->logger->info('', $log);
			throw new InvalidXmlException($log['result']);
		}

		// Make the atom-push http request
		$request = new HTTPRequest();
		$request->setTimelimit(30);
		$request->setHeader('Content-Type', 'application/atom+xml; charset=utf-8');
		$request->setRequestBody($this->data['body']);
		switch ($this->data['method']) {
			case "DELETE":
				$request->setMethod('DELETE');
				$request->setUrl($this->data['destination'].rawurlencode($this->data['guid']));
				break;

			case "UPDATE":
				$request->setMethod('PUT');
				$request->setUrl($this->data['destination'].rawurlencode($this->data['guid']));
				break;

			case "CREATE":
				$request->setMethod('POST');
				$request->setUrl($this->data['destination']);
				break;
		}

		$success = false;
		try {
			$request->send();
			$response = $request->getResponse();

			// Add to the log for this message
			$log['response'] = $response->getBody();
			$log['response_time'] = $response->getResponseTime();
			$log['response_code'] = $response->getResponseStatusCode();

			if ($response->getResponseStatusCode() == '200' or $response->getResponseStatusCode() == '201') {
				$success = true;
			}
		} catch (Exception $e) { }

		if (!$success) {
			$log['result'] = isset($e) ? $e->getMessage() : $response->getResponseStatusCode();
		} else {
			$log['result'] = "success";
		}

		$this->logger->info('', $log);

		return $success;
	}

} 