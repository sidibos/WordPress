<?php
header("Cache-Control: must-revalidate");
header("Content-type: text/javascript");
$offset = 60 * 60 * 24 * 3;
header("Expires: " . gmdate("D, d M Y H:i:s", time() + $offset) . " GMT");

include("common.js");
?>