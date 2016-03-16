<?php

// Link this theme the 'FT Wrappers' plugin
add_action('after_setup_theme', function() {
	if (class_exists('Assanka_FTwrapper')) {
		Assanka_FTwrapper::enable();
	}
});


/**
 * Theme functions
 */
function falcon_add_theme_support(){
	add_theme_support( 'post-thumbnails' );
}
add_action( 'widgets_init', 'falcon_add_theme_support' );

function falcon_register_sidebar(){
	register_sidebar(array(
		'name' => 'Default sidebar',
		'id' => 'sidebar-1',
		'description' => 'Default sidebar that appears on all index, category, archive and blogpost pages.',
		'before_widget' => '<div id="%1$s" class="%2$s">',
		'before_title' => '<div class="comp-header"><h3 class="comp-header-title">',
		'after_title' => '</h3></div><div class="widgetcontent">',
		'after_widget' => '</div></div>',
	));
}
add_action( 'widgets_init', 'falcon_register_sidebar' );

// Generate and return HTML for the share button widget
function falcon_get_share_widget_html() {
	$sharelinks = array();

	// Twitter
	$sharelinks[] = array("label"=>"Twitter", "safename"=>"twitter", "href"=>get_sharelink_href_twitter(), "si_link"=>"social-media_twitter");

	// Facebook
	$sharelinks[] = array("label"=>"Facebook", "safename"=>"facebook", 	"href"=>get_sharelink_href_facebook(), "si_link"=>"social-media_facebook");

	// Google+
	$sharelinks[] = array("label"=>"Google+", "safename"=>"googleplus", "href"=>get_sharelink_href_googleplus(), "si_link"=>"social-media_googleplus");

	// Linkedin
	$sharelinks[] = array("label"=>"LinkedIn", "safename"=>"linkedin", "href"=>get_sharelink_href_linkedin(), "si_link"=>"social-media_linkedin");

	// Stumbleupon
	$sharelinks[] = array("label"=>"StumbleUpon", "safename"=>"stumbleupon", "href"=>get_sharelink_href_stumbleupon(), "si_link"=>"social-media_stumbleupon");

	// Reddit
	$sharelinks[] = array("label"=>"Reddit", "safename"=>"reddit", "href"=>get_sharelink_href_reddit(), "si_link"=>"social-media_reddit");

	// Share-widget button
	$html_share  = '<span class="social-links-popup linkButton small">';
	$html_share .= '	<a href="javascript:void(0);" class="white shareButton overlayButton"><span>Share</span></a>';
	$html_share .= '	<div class="shareList roundedCorners overlay">';
	$html_share .= '		<div class="overlayArrow overlayTopArrow"></div>';
	$html_share .= '		<div class="innerBox">';
	$html_share .= '			<div class="title">';
	$html_share .= '				Share this on';
	$html_share .= '				<a href="javascript:void(0)" class="close-icon" onclick="$(\'.overlay\').hide();"></a>';
	$html_share .= '			</div>';
	$html_share .= '			<ul class="clearfix">';

	foreach ($sharelinks as $sharelink) {
		$html_share .= '			<li class="sharelink ' . $sharelink['safename'] . '">';
		$html_share .= '				<a target="_blank" class="' . $sharelink['safename'] . '" href="' . $sharelink['href'] . '" si:link="' . $sharelink['si_link'] . '">' . $sharelink['label'] . '</a>';
		$html_share .= '			</li>';
	}

	$html_share .= '			</ul>';
	$html_share .= '		</div>';
	$html_share .= '	</div>';
	$html_share .= '</span>';

	return $html_share;
}

function get_sharelink_href_twitter(){
	$twitter_href  = 'https://twitter.com/intent/tweet?'; // Don't use www.twitter.com as it re-urlencodes the get parameters
	$twitter_href .= 'original_referer='	.rawurlencode(get_permalink());
	$twitter_href .= '&amp;text='			.rawurlencode(getTweetText());
	$twitter_href .= '&amp;url='			.rawurlencode(wp_get_shortlink());

	$twitter_related = 'financialtimes,';
	if (!empty($theme_options['twitter_related'])) $twitter_related .= $theme_options['twitter_related'];
	$twitter_href .= '&amp;related='		.rawurlencode($twitter_related);
	return $twitter_href;
}
function get_sharelink_href_facebook(){
	$facebook_href  = 'http://www.facebook.com/sharer/sharer.php?';
	$facebook_href .= 'u='					.rawurlencode(wp_get_shortlink());
	$facebook_href .= '&amp;t='				.rawurlencode(get_the_title().' | '.get_bloginfo('name').' | FT.com ');
	return $facebook_href;
}
function get_sharelink_href_googleplus(){
	$googleplus_href  = 'https://plus.google.com/share?';
	$googleplus_href .= 'url='					.rawurlencode(wp_get_shortlink());
	return $googleplus_href;
}
function get_sharelink_href_linkedin(){
	$linkedin_href  = 'http://www.linkedin.com/shareArticle?';
	$linkedin_href .= 'mini=true';
	$linkedin_href .= '&amp;url='			.rawurlencode(wp_get_shortlink());
	$linkedin_href .= '&amp;title='			.rawurlencode(get_the_title().' | '.get_bloginfo('name').' | FT.com ');
	$linkedin_href .= '&amp;summary='		.rawurlencode(get_the_excerpt());
	$linkedin_href .= '&amp;source='		.rawurlencode(get_bloginfo('name').' | FT.com ');
	return $linkedin_href;
}
function get_sharelink_href_stumbleupon(){
	return "http://www.stumbleupon.com/submit?url=".rawurlencode(wp_get_shortlink())."&amp;title=".rawurlencode(get_the_title());
}
function get_sharelink_href_reddit(){
	return "http://reddit.com/submit?url=".rawurlencode(wp_get_shortlink())."&amp;title=".rawurlencode(get_the_title());
}

function assanka_get_the_time($format = null) {
	if (empty($format)) {
		if (get_the_time('Y') == date('Y')) {
			$format = 'M d H:i';
		} else {
			$format = 'M d Y H:i';
		}
	}
	$dateTime = new DateTime(get_the_time('M d Y H:i'), new DateTimeZone('GMT'));
	return $dateTime->format($format);
}

function post_actions() {

	// Print
	$html_print = '<div class="linkButton small"><a class="white" href="#" onclick="window.print();"><span>Print</span></a></div>';

	$html_email  = apply_filters('get_mail_to_content', '');

	// Clip
	$newClipUrl = 'http://clippings.ft.com/?source=article-clip&uuid=' . Assanka_UID::get_the_post_uid();
	$newClipHtml = '<div id="clipthis'.get_the_ID().'" class="linkButton small"><a class="white" href="' . $newClipUrl . '"><span>Clip</span></a></div> ';

	// Share
	$html_share = falcon_get_share_widget_html();

	// Combine and return
	return $html_share . $newClipHtml . $html_print . $html_email;
}



/**
 * Action filter for navigation menus
 *
 * Sets 'current-menu-ancestor' class to menu items where appropriate;
 * For the primary navigation menu, does some cosmetic tweaks.
 */
function hook_wp_nav_menu_objects($sorted_menu_items, $args){

	// Flag whether any items in the top-level menu are the current ancestor
	$has_ancestor = false;
	foreach ($sorted_menu_items as &$item) {

		// Top-level items only
		if ($item->menu_item_parent == 0) {

			// Cosmetic tweak for primary navigation
			if ($args->theme_location == 'primary') {
				$item->title = '<strong>' . $item->title . '</strong> ' . esc_html($item->description);
			}

			// If the top-level item is the current menu item, it's also the current ancestor.
			if (in_array('current-menu-item', $item->classes)) {
				$item->current_item_ancestor = true;
				$item->classes[] = 'current-menu-ancestor';
				$has_ancestor = true;
				continue;
			}
		}
	}

	// If there are no current ancestors, make the "home" menu item the ancestor by default.
	if (!$has_ancestor) {
		foreach ($sorted_menu_items as &$item) {
			if ($item->url == '/') {
				$item->current_item_ancestor = true;
				$item->classes[] = 'current-menu-ancestor';
				continue;
			}
		}
	}

	return $sorted_menu_items;
}
add_filter('wp_nav_menu_objects' , 'hook_wp_nav_menu_objects', 5, 2);

/**
 * The "Categories to Tags Converter Importer" plugin takes a long time to process, which times out.
 */
function extend_admin_timeout(){
	set_time_limit(0);
	ini_set('memory_limit', '1024M');
}
add_action( 'admin_init', 'extend_admin_timeout' );


// Output <head></head> content, e.g: page title and description.
function hook_wp_head() {
	echo '<title>';
	// Print the title tag based on what is being viewed.
	global $page, $paged;
	wp_title( '|', true, 'right' );

	// Add the blog name.
	bloginfo( 'name' );

	// Add the blog description for the home/front page.
	$site_description = get_bloginfo( 'description', 'display' );
	if ( $site_description && ( is_home() || is_front_page() ) ) {
		echo ' | ' . $site_description;
	}
	echo '</title>'.PHP_EOL;

	$description = strip_tags(get_the_excerpt());
	if (empty($description) and !empty($site_description)){
		$description = $site_description;
	}
	if (!empty($description)){
		echo '<meta name="description" content="'.$description.'" />'.PHP_EOL;
	}
}
add_action('wp_head', 'hook_wp_head');

/**
 * Returns a "Continue Reading" link for excerpts; however, for wrapper falcon,
 * returns empty string as mu-plugin Assanka_GetTheExcerpt::hook_get_the_excerpt()
 * already handles this.
 *
 * @return string empty string
 */
if(!function_exists('falcon_continue_reading_link')):
	function falcon_continue_reading_link() {
		return '';
	}
endif;

if ( ! function_exists( 'falcon_social_buttons_and_counters' ) ) :
	/**
	 * Social counters
	 */
	function falcon_social_buttons_and_counters() {
		// Function does nothing, it serves only to override Falcon original function
	}
endif;
add_action('social_buttons_and_counters', 'falcon_social_buttons_and_counters');


if ( ! function_exists( 'falcon_webchat_byline' ) ) :
	/**
	 * Show byline, date and share button; a proxy for entry-header template part
	 *
	 * @see entry-header.php
	 */
	function falcon_webchat_byline() {
		get_template_part('entry-header');
	}
endif;
add_action('webchat_byline', 'falcon_webchat_byline');

// Custom "FT" feed specifically for Podcast (as the normal "feed" redirects to Acast)
function ftRssFeed() {
	add_feed('ft-feed', 'renderFtRssFeed');
}
function renderFtRssFeed() {
	do_feed_rss2( false );
}
add_action('init', 'ftRSSFeed');
