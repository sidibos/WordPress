<?php
/**
 * It's for compatibility/consistency. Instead you should create exception subclass specific to the "internals" that are throwing it.
 *
 * @codingstandard ftlabs-phpcs
 * @copyright The Financial Times Limited [All rights reserved]
 */
namespace FTLabs;

class InternalException extends Exception {}
