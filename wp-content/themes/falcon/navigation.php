<?php
/*
Assanka primary navigation
Author: Assanka
Version: 1.0

This code uses the "assanka_primary_navigation" site option. 
The option is set via admin, using the "Assanka Primary Navigation" plugin (location: wp-content/mu-plugins/assanka_primary_navigation.php).
*/ 
$assanka_primary_navigation = get_site_option( 'assanka_primary_navigation' );
$thmopts = get_option('theme_options');
if (!empty($thmopts['navigation_override'])) $assanka_primary_navigation = $thmopts['navigation_override'];
$rss_href = get_bloginfo('rss2_url');
if(!empty($rss_href)){
	$assanka_primary_navigation = str_replace('{{rss-feed-href}}',$rss_href,$assanka_primary_navigation);
}
echo($assanka_primary_navigation);
