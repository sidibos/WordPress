<?php
/**
 * Initialise
 * Does some checks and sets some template variables for pages in the "Falcon" theme.
 */

/**
 * Guardians
 */

// Exit on bad request
if (!mb_check_encoding($_SERVER['REQUEST_URI'], 'UTF-8')) {
	header("HTTP/1.1 400 Bad Request");
	header("X-Reason: Supplied request URI was not valid UTF-8.");
	exit;
}

/**
 * Setting variables
 */

// Let's store our template variables in a global array.
global $tpl_variables;
$tpl_variables=array();

// Set time zone
date_default_timezone_set("Europe/London");
define("DFP_RAND", str_pad(abs(mt_rand(1000000000000000, 9999999999999999)), 16, "0", STR_PAD_LEFT));

// Determine actual slug (for the URL)
$url = str_replace(array("http://","https://"),'',get_bloginfo('siteurl'));
if (($slugstart = strrpos($url,"/")) !== false) {
	$url_slug = substr($url,$slugstart+1);
}
else{
	$bits = explode('.',$url);
	$url_slug = $bits[0];
}
$tpl_variables['url_slug'] = $url_slug;

// Load static content from the current host on dev, or the CDN on live
$tpl_variables['static_content_host'] = ($_SERVER['IS_LIVE'] and !isset($_GET["assktest"]))?"blogs.r.ftdata.co.uk":$_SERVER["HTTP_HOST"];

/**
 * Dynamic CSS stylesheets
 */
global $dynamic_stylesheets_array;
$link_html = '';

// If on any page in the contact us section, add the contact us stylesheet
if (preg_match("/^\/?contact\-?us(\/.*)?$/", $_SERVER['REQUEST_URI'])) {
	$dynamic_stylesheets_array[] = 'contact.css';
}

if(!empty($dynamic_stylesheets_array)){
	foreach($dynamic_stylesheets_array as $stylesheet_filename){
		$link_html .= '<link rel="stylesheet" type="text/css" media="all" href="http://' . $tpl_variables['static_content_host'] . '/wp-content/themes/aboutus/stylesheets/' . $stylesheet_filename . '?v=' . CACHEBUSTER .'" />' . "\n";
	}
}
$tpl_variables['dynamic_stylesheets'] = $link_html;

/**
 * Custom meta tags
 * Output description meta-tag based on the post excerpt, if available, and the default blog description if not.
 */
$descriptionmeta = "";
if (is_single()) {
	$descriptionmeta = trim(get_the_excerpt());
}
if (empty($descriptionmeta)) {
	$descriptionmeta = get_option("_ftblogs_descriptionmetatag");
}
if (!empty($descriptionmeta)) {
	$descriptionmeta = htmlspecialchars(preg_replace("/[\n\r]+/", "  ", $descriptionmeta), ENT_QUOTES, "UTF-8");
}
else{
	$descriptionmeta = 'The Financial Times brings you the latest economic and political news from the UK';
}
$tpl_variables['descriptionmeta'] = $descriptionmeta;

// Other meta tags (set in the FTblogs WP plugin.)
$ftblogs_metatags = balancetags(get_option('_ftblogs_metatags'));

// Set up the page title
$pagetitle = '';
if(!is_front_page() && !is_home()){
	$pagetitle  = get_the_title();
	$pagetitle .= !empty($pagetitle)? ' | ' : NULL;
}
$pagetitle .= get_the_title() != get_bloginfo('name')? get_bloginfo('name') : NULL;
$tpl_variables['pagetitle'] = $pagetitle;

$tpl_variables['display_name'] = get_bloginfo( 'name' );

