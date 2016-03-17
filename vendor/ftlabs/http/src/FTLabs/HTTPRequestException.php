<?php
/**
 * HTTP errors
 *
 * @codingstandard ftlabs-phpcs
 * @copyright The Financial Times Limited [All Rights Reserved]
 */

namespace FTLabs;

class HTTPRequestException extends \FTLabs\Exception {

	// These aren't used any more, just here for reference.
	const TIMEOUT  = 1;
	const EMPTYRES = 2;
	const CURLFAIL = 3;
}
