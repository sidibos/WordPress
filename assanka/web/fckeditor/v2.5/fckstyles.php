<?php
	header("Content-type: text/xml");
	echo("<?xml version=\"1.0\" encoding=\"utf-8\" ?>")
?>
<!--
 * FCKeditor - The text editor for internet
 * Copyright (C) 2003-2005 Frederico Caldeira Knabben
 * 
 * Licensed under the terms of the GNU Lesser General Public License:
 * 		http://www.opensource.org/licenses/lgpl-license.php
 * 
 * For further information visit:
 * 		http://www.fckeditor.net/
 * 
 * File Name: fckstyles.xml
 * 	This is the sample style definitions file. It makes the styles combo
 * 	completely customizable.
 * 	See FCKConfig.StylesXmlPath in the configuration file.
 * 
 * File Authors:
 * 		Frederico Caldeira Knabben (fredck@fckeditor.net)
-->
<Styles>
	<Style name="Title H1" element="h1" />
	<Style name="Title H2" element="h2" />
	<Style name="Title H3" element="h3" />
	<Style name="Title H4" element="h4" />
	<Style name="Float to left" element="span">
		<Attribute name="style" value="float: left;" />
	</Style>
	<Style name="Float to left (Image)" element="img">
		<Attribute name="style" value="float: left;" />
	</Style>
	<Style name="Float to right" element="span">
		<Attribute name="style" value="float: right;" />
	</Style>
	<Style name="Float to right (Image)" element="img">
		<Attribute name="style" value="float: right;" />
	</Style>
	<?php
		// If there are any project-specific files, this is where to insert them
		if (file_exists($_SERVER["DOCUMENT_ROOT"]."/lib/inc/addfckstyles")) include($_SERVER["DOCUMENT_ROOT"]."/lib/inc/addfckstyles");
	?>
</Styles>
