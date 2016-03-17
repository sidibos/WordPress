<?php
require('makefont.php');

// yum install ttf2pt1
// First:  ttf2pt1 -a zurich.ttf zurich

//$font[]="couriernew";
//$font[]="humanistlt";
//$font[]="humanistrg";
//$font[]="novarese";
//$font[]="zurich";
//$font[]="verdanab";
//$font[]="verdanai";
//$font[]="verdanaz";
$font[]="HouschkaAltPro-BoldItalic";
$font[]="HouschkaAltPro-Bold";
$font[]="HouschkaAltPro-DemiBoldItalic";
$font[]="HouschkaAltPro-DemiBold";
$font[]="HouschkaAltPro-LightItalic";
$font[]="HouschkaAltPro-Light";

foreach ($font as $f) {
	$cmd = "ttf2pt1 -a ".$f.".ttf ".$f;
	`$cmd`;
	MakeFont($f.'.ttf',$f.'.afm');
}

