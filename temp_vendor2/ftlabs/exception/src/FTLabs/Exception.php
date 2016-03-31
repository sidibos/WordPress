<?php
/**
 * An exception with context support
 *
 * @copyright The Financial Times Limited [All Rights Reserved]
 */
namespace FTLabs;

class Exception extends \Exception {
	private $tags = array();
	private $context;

	/**
	 * Throw an exception with context (use get_defined_vars() for context).
	 *
	 * All arguments are optional, and message and previous exception can be omitted entirely. Context must be the last argument.
	 * All of these are valid:
	 *   new Exception("message");
	 *   new Exception(get_defined_vars()); // for use with exception subclasses
	 *   new Exception("message", get_defined_vars());
	 *   new Exception("message", $previous, get_defined_vars());
	 *   new Exception("message", -1, get_defined_vars());
	 *
	 * @param mixed $message_or_context           If a string: Description of the exception (avoid including dynamic data in your description)
	 * @param mixed $exception_or_code_or_context If Exception instance: Exception that has already been thrown, and caught, and which has in turn triggered this exception. If integer: it's used as a code.
	 * @param mixed $context                      Any data (normally an array) which should be attached to this exception as debug.
	 * @return  FTLabs\Exception
	 */
	public function __construct($message_or_context = null, $exception_or_code_or_context = null, $context = null) {
		$num_args = func_num_args();

		$code = 0;
		$previous = null;

		if ($num_args >= 2) {
			$message = $message_or_context;

			if ($exception_or_code_or_context instanceof \Exception) {
				$previous = $exception_or_code_or_context;
			} else if ($num_args == 2) {
				$context = $exception_or_code_or_context;
			} else if (is_numeric($exception_or_code_or_context)) {
				$code = $exception_or_code_or_context;
			}
		} else if (is_string($message_or_context)) {
			$message = $message_or_context;
		} else {
			$message = get_class($this);
			$context = $message_or_context;
		}

		if (is_array($context)) {
			foreach ($context as $k => $v) {
				if ('eh:' === substr($k, 0, 3)) {
					$this->tags[substr($k,3)] = $v;
					unset($context[$k]);
				}
			}
		}
		$this->context = $context;

		parent::__construct($message, $code, $previous);
	}

	public function getContext() {
		return $this->context;
	}

	/**
	 * Error Tags. Set them with array('eh:tag'=>'value') in context
	 *
	 * @see ErrorHandler
	 * @return array
	 */
	public function getTags() {
		return $this->tags;
	}
}


/* defining exception subclasses here is against PSR-0 */

