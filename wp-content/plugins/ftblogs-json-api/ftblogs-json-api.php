<?php
/*
Plugin Name: FT Blogs JSON API
Plugin URI: http://wordpress.org/plugins/json-api/
Description: A RESTful API for WordPress modified for FT Help purposes to expose pages like T&Cs, Cookie & Privacy policy, etc via a JSON API
Version: 1.1.1
Author: Dan Phiffer (modified by FT Blogs)
Author URI: http://phiffer.org/
*/

$dir = json_api_dir();
@include_once "$dir/singletons/api.php";
@include_once "$dir/singletons/query.php";
@include_once "$dir/singletons/introspector.php";
@include_once "$dir/singletons/response.php";
@include_once "$dir/models/post.php";
@include_once "$dir/models/comment.php";
@include_once "$dir/models/category.php";
@include_once "$dir/models/tag.php";
@include_once "$dir/models/author.php";
@include_once "$dir/models/attachment.php";

function json_api_init() {
  global $json_api;
  if (phpversion() < 5) {
    add_action('admin_notices', 'json_api_php_version_warning');
    return;
  }
  if (!class_exists('JSON_API')) {
    add_action('admin_notices', 'json_api_class_warning');
    return;
  }
  add_filter('rewrite_rules_array', 'json_api_rewrites');
  $json_api = new JSON_API();
}

function json_api_php_version_warning() {
  echo "<div id=\"json-api-warning\" class=\"updated fade\"><p>Sorry, JSON API requires PHP version 5.0 or greater.</p></div>";
}

function json_api_class_warning() {
  echo "<div id=\"json-api-warning\" class=\"updated fade\"><p>Oops, JSON_API class not found. If you've defined a JSON_API_DIR constant, double check that the path is correct.</p></div>";
}

function json_api_activation() {
  // Add the rewrite rule on activation
  global $wp_rewrite;
  add_filter('rewrite_rules_array', 'json_api_rewrites');
  $wp_rewrite->flush_rules();
}

function json_api_deactivation() {
  // Remove the rewrite rule on deactivation
  global $wp_rewrite;
  $wp_rewrite->flush_rules();
}

function json_api_rewrites($wp_rules) {
  $base = get_option('json_api_base', 'api');
  if (empty($base)) {
    return $wp_rules;
  }
  $json_api_rules = array(
    "$base\$" => 'index.php?json=info',
    "$base/(.+)\$" => 'index.php?json=$matches[1]'
  );
  return array_merge($json_api_rules, $wp_rules);
}

function json_api_dir() {
  if (defined('JSON_API_DIR') && file_exists(JSON_API_DIR)) {
    return JSON_API_DIR;
  } else {
    return dirname(__FILE__);
  }
}

function json_api_hide_network_plugin($all) {
	global $current_screen;

	if( $current_screen->is_network ) {
		unset($all['ftblogs-json-api/ftblogs-json-api.php']);
	}

	return $all;
}

// Add initialization and activation hooks
add_action('init', 'json_api_init');
register_activation_hook("$dir/ftblogs-json-api.php", 'json_api_activation');
register_deactivation_hook("$dir/ftblogs-json-api.php", 'json_api_deactivation');
// Hide the plugin from the Network activation; it needs to be enabled for each individual
// blog because it need to set correct rewrite rules
add_filter( 'all_plugins', 'json_api_hide_network_plugin' );
