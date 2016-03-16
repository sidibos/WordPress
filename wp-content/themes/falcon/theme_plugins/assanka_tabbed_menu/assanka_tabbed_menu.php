<?php
/**
 * Assanka tabbed menu
 * Displays a tabbed menu linking to pages (if enabled in Theme Settings) and promoted categories (if set in Theme Settings)
 */

class Assanka_TabbedMenu {
	var $name = 'Tabbed Menu';
	var $html = '';

	function __construct() {
		// Hooks
		add_action('init', array(&$this, 'generate_tabbed_menu'));
		add_action('show_tabbed_menu', array(&$this, 'show_tabbed_menu'));
	}

	/**
	 * Generate the tabbed menu HTML before the DOM loads so we can add JS and CSS inside <head></head>.
	 */
	function generate_tabbed_menu() {
		$options = get_option('theme_options');
		$blog_pages = array();
		$promoted_categories = array();

		// If page tabs are enabled, load up an array of top-level pages
		if (!empty($options['tabbed_page_menu'])) {
			$blog_pages = get_pages(array(
				'parent' => 0
			));
		}

		// If there are promoted categories, load them up in an array
		if (!empty($options['promoted_categories'])) {
			foreach ( $options['promoted_categories'] as $id ) {
				$promoted_categories[] = get_category($id);
			}
		}

		// If there are no promoted categories or pages to display, don't continue.
		if (empty($promoted_categories) && empty($blog_pages)) {
			return false;
		}

		// Get the blog slug (for the URLs)
		$url_slug = assanka_get_url_slug();

		// Make a fresh menu array from the $promoted_categories and $blog_pages arrays
		$menu_list = array();
		$active_match = false;
		
		// Pages
		$class = 'Home page';
		if ($_SERVER['REQUEST_URI'] == '/' . $url_slug  . '/') {
			$class .= ' active';
			$active_match = true;
		}
		// Static home page
		$menu_list[] = array(
			'href'           => '/' . $url_slug . '/',
			'class'          => $class,
			'title'          => get_bloginfo(),
			'display_string' => 'Home'
		);
		foreach ($blog_pages as $page) {
			$class = $page->post_name;
			$page_uri = '/'.$url_slug.'/'.get_page_uri($page->ID).'/';

			if ($_SERVER['REQUEST_URI'] == $page_uri) {
				$class .= ' active';
				$active_match = true;
			}
			$menu_list[] = array(
				'href'           => get_page_link($page->ID),
				'class'          => $class,
				'title'          => 'Page: ' . $page->post_title,
				'display_string' => $page->post_title
			);
		}

		// Promoted categories
		foreach ($promoted_categories as $category) {
			$class = $category->slug;

			if (stristr($_SERVER['REQUEST_URI'], '/'.$category->slug.'/')) {
				$class .= ' active';
				$active_match = true;
			}

			$href = get_category_link($category->cat_ID);
			if (is_string($href)) {
				$menu_list[] = array(
					'href'           => $href,
					'class'          => $class,
					'title'          => 'Category: ' . $category->name,
					'display_string' => $category->name
				);
			}
		}


		// Unless it's the home page, only display the tabbed-menu if one of the tabs is currently being viewed.
		if (!is_front_page() and $active_match != true) {
			return false;
		}

		// Generate the tabbed_menu HTML
		$html = '<div id="tabs" class="clearfix"><ul class="tabs">'."\n";
		foreach ($menu_list as $item) {
			$html .= '<li class="' . $item['class'] . '">';
			$html .= '<a href="' . $item['href'] . '" class="' . $item['class'] . '" title="' . $item['title'] . '"><span> </span>' . $item['display_string'] . '</a>';
			$html .= '</li>'."\n";
		}
		$html .= '</ul></div>'."\n";

		// If one of the tabs is currently being viewed, hide the page title.
		if ($active_match == true) {
			add_action('wp_head', array(&$this, 'output_head_code'), 1);
		}

		$this->html = $html;

		return !empty($html);
	}

	// Hide the page title with CSS and remove it from the DOM with javascript.
	function output_head_code() {
		echo PHP_EOL.'<style type="text/css">#tabbed-menu-page-title { display: none; } </style>';
		echo PHP_EOL.'<script language="javascript" type="text/javascript"> jQuery(document).ready(function($) { $("#tabbed-menu-page-title").remove(); }); </script>'.PHP_EOL;
	}

	/**
	 * Echo the HTML of the tabbed menu. Called in the template using do_action('show_tabbed_menu');
	 */
	function show_tabbed_menu() {
		echo $this->html;
	}
}
$assanka_tabbed_menu = new Assanka_TabbedMenu();

/**
 * Legacy: Allow for the old direct-function-call use case.
 */
function assanka_show_tabbed_menu() {
	do_action('show_tabbed_menu');
}

