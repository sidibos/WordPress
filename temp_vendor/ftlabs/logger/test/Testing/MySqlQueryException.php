<?php

namespace Testing;

if (!class_exists('AssankaException')) {
	class_alias('\FTLabs\Exception', 'AssankaException'); // This is a lame hack just for tests
}

class MySqlQueryException extends \AssankaException {}
