<?php

namespace FTBlogs\Gtg\Check;

interface CheckableInterface
{
	/**
	 * @param array $params
	 * @return array
	 */
	public function check(array $params);
}