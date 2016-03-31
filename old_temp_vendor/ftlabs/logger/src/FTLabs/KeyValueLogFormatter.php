<?php

namespace FTLabs;

class KeyValueLogFormatter implements LogFormatterInterface {

	function formattedErrorLog(ErrorLog $error) {
		return array('text/plain', $this->formattedLogMessage($error->getSeverity(), $error->getTitle(), $error->getContext(), strtotime($error->getIsoTime())));
	}

	function formattedLogMessage($level, $msg, $vars, $timestamp = null) {
		$string = ($level !== null || $msg !== null) ? "[$level] $msg" : "";
		if (is_array($vars)) {
			if (isset($vars['exception']) && $vars['exception'] instanceof \FTLabs\Exception) {
				$ctx = $vars['exception']->getContext();
				if (is_array($ctx)) $vars = array_merge($vars, $ctx);
			} elseif (isset($vars['error']) && is_array($vars['error']) && isset($vars['error']['context']) && is_array($vars['error']['context'])) {
				$vars = array_merge($vars, $vars['error']['context']);
			}
			foreach ($vars as $key => $val) {
				if (is_bool($val)) $val = ($val)?"true":"false";
				elseif (is_array($val)) $val = "Array(".count($val).")";
				elseif (is_object($val)) $val = get_class($val);
				elseif (!is_scalar($val)) $val = gettype($val);

				// Make sure neither the key or val contains any spaces or equals signs as they are used as delimiters
				$key = strtr($key, " =", "__");
				$val = strtr($val, " =", "__");

				if ($string) $string .= " ";
				$string .= "{$key}={$val}";
			}
		} elseif (is_scalar($vars) or (is_object($vars) and method_exists($vars, '__toString'))) {
			$string .= " " . $vars;
		} elseif ($vars !== NULL) {
			throw new LoggerException("Can only write strings or arrays to log, got ".gettype($vars), get_defined_vars());
		}

		return $string;
	}
}
