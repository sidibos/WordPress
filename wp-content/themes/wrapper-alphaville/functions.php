<?php

require_once 'theme_plugins/theme_options.php';

// Link this theme the 'FT Wrappers' plugin
add_action('after_setup_theme', function() {
	if (class_exists('Assanka_FTwrapper')) {
		Assanka_FTwrapper::enable();
	}
});


/**
 * Theme functions
 */
add_theme_support( 'post-thumbnails' );

function alphaville_widgets_init() {
	register_nav_menus( array(
		'primary' => 'Primary Navigation'
		));

	register_sidebar(array(
		'name' => 'Default sidebar',
		'id' => 'sidebar-1',
		'description' => 'Default sidebar that appears on all pages.',
		'before_widget' => '<div id="%1$s" class="%2$s widget">',
		'before_title' => '<h2 class="widgettitle">',
		'after_title' => '</h2><div class="widgetcontent">',
		'after_widget' => '</div></div>',
		));
}
add_action( 'widgets_init', 'alphaville_widgets_init' );

// Generate and return HTML for the share button widget
function alphaville_get_share_widget_html() {
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
	$twitter_href .= 'original_referer='	.urlencode(get_permalink());
	$twitter_href .= '&amp;text='			.urlencode(getTweetText());
	$twitter_href .= '&amp;url='			.urlencode(wp_get_shortlink());

	$twitter_related = 'financialtimes,';
	if (!empty($theme_options['twitter_related'])) $twitter_related .= $theme_options['twitter_related'];
	$twitter_href .= '&amp;related='		.urlencode($twitter_related);
	return $twitter_href;
}
function get_sharelink_href_facebook(){
	$facebook_href  = 'http://www.facebook.com/sharer/sharer.php?';
	$facebook_href .= 'u='					.urlencode(wp_get_shortlink());
	$facebook_href .= '&amp;t='				.urlencode(get_the_title().' | '.get_bloginfo('name').' | FT.com ');
	return $facebook_href;
}
function get_sharelink_href_googleplus(){
	$googleplus_href  = 'https://plus.google.com/share?';
	$googleplus_href .= 'url='					.urlencode(wp_get_shortlink());
	return $googleplus_href;
}
function get_sharelink_href_linkedin(){
	$linkedin_href  = 'http://www.linkedin.com/shareArticle?';
	$linkedin_href .= 'mini=true';
	$linkedin_href .= '&amp;url='			.urlencode(wp_get_shortlink());
	$linkedin_href .= '&amp;title='			.urlencode(get_the_title().' | '.get_bloginfo('name').' | FT.com ');
	$linkedin_href .= '&amp;summary='		.urlencode(get_the_excerpt());
	$linkedin_href .= '&amp;source='		.urlencode(get_bloginfo('name').' | FT.com ');
	return $linkedin_href;
}
function get_sharelink_href_stumbleupon(){
	return "http://www.stumbleupon.com/submit?url=".urlencode(wp_get_shortlink())."&amp;title=".urlencode(get_the_title());
}
function get_sharelink_href_reddit(){
	return "http://reddit.com/submit?url=".urlencode(wp_get_shortlink())."&amp;title=".urlencode(get_the_title());
}

/**
 * Returns text for a tweet about the current post
 */
function getTweetText($username = false) {
	$tweetcharsused = 20; // characters used by the link and space
	if ($username) {
		$tweetcharsused += 6 + mb_strlen($username);  // Twitter username won't be more than 15 chars
	}
	$blogname = html_entity_decode(get_bloginfo('name'), ENT_QUOTES, 'utf-8');
	if (!preg_match("/^ *FT/", $blogname)) $blogname .= ' | FT.com';
	$text = html_entity_decode(get_the_title(), ENT_QUOTES, 'utf-8') . ' | ' .$blogname;
	$tweetcharsleft = 140 - $tweetcharsused;

	if (mb_strlen($text) > $tweetcharsleft) $text = mb_substr($text, 0, $tweetcharsleft - 2) . " …";
	return $text;
}


/**
 * Returns HTML for a standard Twitter 'follow' link
 */
function generateTwitterFollowLink($username, $large = false) {
	$html  = '<div class="twidget' . ($large ? ' xl' : '') . '">';
	$html .= '<div class="btn-o">';
	$html .= '<a class="btn" target="_blank" title="Follow @' . $username . ' on Twitter" href="https://twitter.com/intent/follow?original_referer=http%3A%2F%2Fsh.sandboxes.ftalphaville.ft.com%2F&region=follow_link&screen_name=' . $username . '&tw_p=followbutton&variant=2.0"><i></i><span class="label">Follow @' . $username . '</span></a>';
	$html .= '</div>';
	$html .= '</div>';
	return $html;
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

	// Share
	$html_share = alphaville_get_share_widget_html();

	// Combine and return
	return $html_share . $html_print . $html_email;
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

	$description = strip_tags(Assanka_GetTheExcerpt::get_the_description());
	if (empty($description) and !empty($site_description)){
		$description = $site_description;
	}
	if (!empty($description)){
		echo '<meta name="description" content="' . esc_html($description) . '" />'.PHP_EOL;
	}
}
add_action('wp_head', 'hook_wp_head');

// Set default link type for media objects to link to 'file' by default, instead of linking to a attachement post
update_option('image_default_link_type', 'file' );

/**
 * Display a mini-biography popup box when an Author's link is clicked.
 * @param int $author_id the author id
 */
function create_author_overlay($author_id) {
	?>
	<div class="the-author-posts-link roundedCorners overlay">
		<div class="overlayArrow overlayTopArrow"></div>
		<div class="innerBox">
			<a href="javascript:void(0)" class="close-icon" onclick="$(this).closest('.the-author-posts-link').hide().parent('.entry-meta').removeAttr('style');"></a>
			<?php
			$ftalphaville_headshot_url = get_the_author_meta('ftalphaville_headshot_url', $author_id);
			if(!empty($ftalphaville_headshot_url)): ?>
			<div class="author-headshot" style="background-image: url(<?php echo $ftalphaville_headshot_url; ?>);"></div>
			<div class="author-biography">
			<?php endif; ?>
			<p><?php echo wpautop(get_the_author_meta('user_description', $author_id)); ?> <a href="<?php echo get_author_posts_url($author_id); ?>">Learn more</a></p>
			<div class="author-actions">
				<?php
				$ftalphaville_twitter_name = get_the_author_meta('ftalphaville_twitter_name', $author_id);
				if(!empty($ftalphaville_twitter_name)) {
					echo generateTwitterFollowLink($ftalphaville_twitter_name) . '<span class="meta-divider"> | </span>';
				}
				?>

				<a href="/author/<?php echo get_the_author_meta('user_nicename', $author_id); ?>/feed/" target="_blank">Subscribe to <?php the_author_meta('first_name', $author_id); ?>'s posts</a>
			</div>
			<?php if(!empty($ftalphaville_headshot_url)): ?>
			</div>
		<?php endif; ?>
		</div>
	</div>
	<?php
}