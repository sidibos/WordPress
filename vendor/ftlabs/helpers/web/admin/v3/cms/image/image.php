<?php
header("Cache-Control: must-revalidate");
header("Content-type: text/css");
$offset = 60 * 60 * 24 * 3;
header("Expires: " . gmdate("D, d M Y H:i:s", time() + $offset) . " GMT");
?>
/*
########################################################
/assanka/web/admin/v2/cms/image/image.css

Image editor styles - used to provide styles for the Outline
Image Editor.

Accepted Inputs:
none

28th January 2005
Rowan Beentje
Assanka Ltd
########################################################
*/

/* Generic styles */
html, body {
	height: 100%;
	width: 100%;
}body {	background-color: rgb(160,160,160);	margin: 0;	padding: 0;
	font-family: sans-serif;	font-size: 12px;
	overflow: hidden;}
p, td, th, li {	font-family: sans-serif;	font-size: 12px;	vertical-align: top;}img {	border: none;}
h4 {
	background: rgb(176,0,3);
	font-weight: bold;
	font-size: 15px;
	color: white;
	margin: 0px 0px 10px 0px;
	padding: 4px 10px 3px 12px;
}
acronym {
	cursor: help;
	border-bottom: 1px dotted;
	font-style: normal;
}
#toolpane {
	border-left: 1px solid rgb(110,110,110);
	position: absolute;
	z-index: 100;
	right: 0px;
	top: 0px;
	margin: 0px;
	padding: 0px;
	background-color: rgb(240,240,240);
	width: 200px;
	height: 100%;
}
#infopane {
	display: none;
	text-align: left;
	position: absolute;
	z-index: 20;
	left: 0px;
	bottom: 0px;
	margin: 0px;
	padding: 0px;	
	height: 32px;
	width: 100%;
}
#infobar {
	background-color: rgb(240,240,240);
	background-image: url(info.gif);
	background-repeat: no-repeat;
	font-size: 14px;
	font-weight: bold;
	line-height: 32px;
	margin: 0px 200px 0px 0px;
	padding: 0px 0px 0px 39px;
	height: 100%;
	border-top: 1px solid rgb(110,110,110);
}
#theimageframe {
	display: none;
	overflow: hidden;
	position: absolute;
}
.formlabel {
	margin: 5px 10px 0px 0px;
	float: left;
	display: block;
	width: 60px;
	text-align: right;
}
.textinput {
	float: left;
	width: 35px;
	margin: 1px;
	height: 14px;
}
.infotext {
	margin: 5px 0px 0px 10px;
	float: left;
	display: block;
	color: rgb(150,150,150);
}
.warninginfotext {
	font-weight: bold;
	color: rgb(200,90,90);
}
.clear {
	clear: both;
}
.inset {
	margin: 0px 0px 0px 10px;
}
.inputaligned {
	display: block;
	float: left;
	margin: 5px 0px 0px 0px;
}
.vbuffer {
	margin: 4px 0px 0px 0px;
}
.button {
	float: right;
	display: inline;
	margin: 5px 5px 0px 0px;
	width: 80px;
}