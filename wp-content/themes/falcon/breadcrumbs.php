<?php
/*
Assanka Breaducrumbs
Author: Assanka
Version: 1.0

This code uses the "assanka_breadcrumbs" site option. 
The option is set via admin, using the "Assanka Primary Navigation" plugin (location: wp-content/mu-plugins/assanka_primary_navigation.php).
*/ 
$assanka_breadcrumbs = get_site_option( 'assanka_breadcrumbs' );
$thmopts = get_option('theme_options');
if (!empty($thmopts['breadcrumb_override'])) $assanka_breadcrumbs = $thmopts['breadcrumb_override'];
echo($assanka_breadcrumbs);
