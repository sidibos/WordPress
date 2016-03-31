<?php

namespace FTBlogs\Gtg\Check;

abstract class AbstractCheck implements CheckableInterface
{
	function __toString() {
		return get_class($this);
	}
}