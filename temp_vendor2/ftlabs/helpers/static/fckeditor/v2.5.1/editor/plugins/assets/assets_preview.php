<?php
/*
########################################################
assanka_assets_preview.html

Provides a basic framework for the calling file to manipulate
via javascript.

Accepted Inputs:
none

20th February 2005
Rowan Beentje
Assanka Ltd (ASSK-2003-005)
########################################################
*/

require_once($_SERVER["DOCUMENT_ROOT"]."/lib/inc/global");

$cssFile = false;
if (file_exists($_SERVER["DOCUMENT_ROOT"]."/lib/css/cms")) $cssFile = "/lib/css/cms";
else if (file_exists($_SERVER["DOCUMENT_ROOT"]."/lib/css/cms.css")) $cssFile = "/lib/css/cms.css";
else if (file_exists($_SERVER["DOCUMENT_ROOT"]."/lib/css/screen")) $cssFile = "/lib/css/screen";
else if (file_exists($_SERVER["DOCUMENT_ROOT"]."/lib/css/screen.css")) $cssFile = "/lib/css/screen.css";

$htmlToEcho = "<html><head>";
if ($cssFile) $htmlToEcho .= "<link rel=\"stylesheet\" href=\"".$cssFile."\" type=\"text/css\" media=\"screen\" title=\"Screen style\" />";
$htmlToEcho .= "<title>Asset preview</title></head><body style=\"width: 230px; text-align: left; margin: 0; padding: 0;\"><div id=\"assettarget\" style=\"padding: 10px; overflow: auto;\">";
$htmlToEcho .= "Please select an asset to the left to proceed.";
$htmlToEcho .= "</div></body></html>";
echo $htmlToEcho;

?>