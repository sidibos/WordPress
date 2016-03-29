<?php
/**
 * Arguments given to a function/service are invalid. Caller should fix their code.
 * Makes error stack trace highlight the caller.
 *
 * @codingstandard ftlabs-phpcs
 * @copyright The Financial Times Limited [All rights reserved]
 */
namespace FTLabs;

class InvalidCallException extends Exception {}
