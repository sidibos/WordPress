<?php
/**
 * Falcon-theme functions and definitions
 *
 * Originally based on the TwentyTen theme, but heavily modified by Assanka.
 *
 * Sets up the theme and provides some helper functions. Some helper functions
 * are used in the theme as custom template tags. Others are attached to action and
 * filter hooks in WordPress to change core functionality.
 *
 * The first function, falcon_setup(), sets up the theme by registering support
 * for various features in WordPress, such as post thumbnails, navigation menus, and the like.
 *
 * When using a child theme (see http://codex.wordpress.org/Theme_Development and
 * http://codex.wordpress.org/Child_Themes), you can override certain functions
 * (those wrapped in a function_exists() call) by defining them first in your child theme's
 * functions.php file. The child theme's functions.php file is included before the parent
 * theme's file, so the child theme functions would be used.
 *
 * Functions that are not pluggable (not wrapped in function_exists()) are instead attached
 * to a filter or action hook. The hook can be removed by using remove_action() or
 * remove_filter() and you can attach your own function to the hook.
 *
 * It is possible to remove the parent theme's hook only after it is attached, which means it
 * is necessary to wait until setting up the child theme:
 *
 * <code>
 * add_action( 'after_setup_theme', 'my_child_theme_setup' );
 * function my_child_theme_setup() {
 *     // We are providing our own filter for excerpt_length (or using the unfiltered value)
 *     remove_filter( 'excerpt_length', 'falcon_excerpt_length' );
 *     ...
 * }
 * </code>
 *
 * For more information on hooks, actions, and filters, see http://codex.wordpress.org/Plugin_API.
 */

// Don't cache stuff if the user is logged in.
require_once($_SERVER['COREFTCO'].'/helpers/cacheability/cacheability');
if ( is_user_logged_in() ) Cacheability::noCache();


/**
 * Add a stylesheet for the WP Admin section.
 */
add_action('admin_head', 'assanka_admin_head');
add_theme_support('post-thumbnails');
function assanka_admin_head() {
    echo "\n".'<link rel="stylesheet" href="' . get_bloginfo('stylesheet_directory') . '/wp-admin-style.css" type="text/css" media="screen">';
}
// Prevent the admin bar from showing
show_admin_bar(false);

// Remove other clutter from <head/>
remove_action('wp_head', 'rsd_link');
remove_action('wp_head', 'wlwmanifest_link');

function remove_generator() {
	return '';
}

add_filter('the_generator', 'remove_generator');

/**
 * Set the content width based on the theme's design and stylesheet.
 *
 * Used to set the width of images and content. Should be equal to the width the theme
 * is designed for, generally via the style.css stylesheet.
 */
if ( ! isset( $content_width ) )
	$content_width = 600;

/** Tell WordPress to run falcon_setup() when the 'after_setup_theme' hook is run. */
add_action( 'after_setup_theme', 'falcon_setup' );

if ( ! function_exists( 'falcon_setup' ) ):
/**
 * Sets up theme defaults and registers support for various WordPress features.
 *
 * Note that this function is hooked into the after_setup_theme hook, which runs
 * before the init hook. The init hook is too late for some features, such as indicating
 * support post thumbnails.
 *
 * To override falcon_setup() in a child theme, add your own falcon_setup to your child theme's
 * functions.php file.
 *
 * @uses add_theme_support() To add support for post thumbnails and automatic feed links.
 * @uses register_nav_menus() To add support for navigation menus.
 * @uses add_custom_background() To add support for a custom background.
 * @uses add_editor_style() To style the visual editor.
 * @uses load_theme_textdomain() For translation/localization support.
 * @uses add_custom_image_header() To add support for a custom header.
 * @uses register_default_headers() To register the default custom header images provided with the theme.
 * @uses set_post_thumbnail_size() To set a custom post thumbnail size.
 */
function falcon_setup() {

	// There are some admin-set options for this theme.
	$theme_options = get_option('theme_options');

	// This theme styles the visual editor with editor-style.css to match the theme style.
	add_editor_style();

	// Add default posts and comments RSS feed links to head
	add_theme_support( 'automatic-feed-links' );

	// Make theme available for translation
	// Translations can be filed in the /languages/ directory
	load_theme_textdomain( 'falcon', TEMPLATEPATH . '/languages' );
	$locale = get_locale();
	$locale_file = TEMPLATEPATH . "/languages/$locale.php";
	if ( is_readable( $locale_file ) )
		require_once( $locale_file );

	// All blogs with the falcon theme share a common Primary Navigation menu.
	$master_menu_blog_id = 1; // ID of the blog that has the master copy of the Primary Navigation menu (i.e. the root blog).

	// We only want to "enable" the Primary Navigation menu on the blog that has the master copy (i.e. the root blog).
	if (get_bloginfo('id') == 0) {
		// This theme uses wp_nav_menu() for the Primary Navigation in the header.
		$menus = array(
			'primary' => __( 'Primary Navigation', 'falcon' )
		);
		register_nav_menus( $menus );
	}

	/**
	 * Required plugins for this theme
	 */

	// Theme settings admin page
	$theme_settings_file = TEMPLATEPATH . '/theme_plugins/assanka_falcon_theme_settings/assanka_falcon_theme_settings.php';
	if ( is_readable( $theme_settings_file ) ) require_once( $theme_settings_file );

	// Author headshots
	$author_headshots_file = TEMPLATEPATH . '/theme_plugins/assanka_author_headshots/assanka_author_headshots.php';
	if ( is_readable( $author_headshots_file ) ) require_once( $author_headshots_file );

	// Author profiles widget
	$assanka_authors_widget_file = TEMPLATEPATH . '/theme_plugins/assanka_authors_widget/assanka_authors_widget.php';
	if ( is_readable( $assanka_authors_widget_file ) ) require_once( $assanka_authors_widget_file );

	// Tabbed menu
	$tabbed_menu_file = TEMPLATEPATH . '/theme_plugins/assanka_tabbed_menu/assanka_tabbed_menu.php';
	if ( is_readable( $tabbed_menu_file ) ) require_once( $tabbed_menu_file );

	// Promotional banner
	$promotional_banner_file = TEMPLATEPATH . '/theme_plugins/assanka_promotional_banner/assanka_promotional_banner.php';
	if ( is_readable( $promotional_banner_file ) ) require_once( $promotional_banner_file );

	/**
	 * Add linkedin share-button JS.
	 */
	if (!empty($theme_options['linkedin_button_display_mode']) && $theme_options['linkedin_button_display_mode'] == 1) {
		function assanka_linkedin_js() {
			wp_register_script( 'linkedin_js', 'http://platform.linkedin.com/in.js');
			wp_enqueue_script( 'linkedin_js' );
		}
		add_action('wp_enqueue_scripts', 'assanka_linkedin_js');
	}

	/**
	 * Add google +1 share-button JS.
	 */
	if (!empty($theme_options['google_button_display_mode']) && $theme_options['google_button_display_mode'] == 1) {
		function assanka_google_js() {
			wp_register_script( 'google_js', 'https://apis.google.com/js/plusone.js');
			wp_enqueue_script( 'google_js' );
		}
		add_action('wp_enqueue_scripts', 'assanka_google_js');
	}

	/**
	 * Add custom thumbnail size
	 */
	if (!current_theme_supports('post-thumbnails')) {
		add_theme_support('post-thumbnails');
	}
	add_image_size('top-story', 167, 96, true);

	//Add custom thumbnail size options in media uploader
	add_filter( 'image_size_names_choose', 'my_custom_sizes' );

}
endif;

/**
 * Add custom thumbnail size options in media uploader
 */
function my_custom_sizes( $sizes ) {
    return array_merge( $sizes, array(
        'top-story' => __('Top story thumbnail'),
    ) );
}

/**
 * Get our wp_nav_menu() fallback, wp_page_menu(), to show a home link.
 *
 * To override this in a child theme, remove the filter and optionally add
 * your own function tied to the wp_page_menu_args filter hook.
 */
function falcon_page_menu_args( $args ) {
	$args['show_home'] = true;
	return $args;
}
add_filter( 'wp_page_menu_args', 'falcon_page_menu_args' );

/**
 * Sets the post excerpt length to 40 characters.
 *
 * To override this length in a child theme, remove the filter and add your own
 * function tied to the excerpt_length filter hook.
 * @return int
 */
function falcon_excerpt_length( $length ) {
	return 40;
}
add_filter( 'excerpt_length', 'falcon_excerpt_length' );

/**
 * Returns a "Continue Reading" link for excerpts
 * @return string "Continue Reading" link
 */
if(!function_exists('falcon_continue_reading_link')):
function falcon_continue_reading_link() {
	/*
	 * Note: Some blogs use the "More Link Modifier" plugin, which makes this output redundant.
	 * So it checks if that plugin's active before going ahead.
	 */
	if (!has_action('the_content','modifyMoreLink')) {
		return '<div class="entry-meta"><a href="'. get_permalink() . '">' . __( 'Continue reading: <span class="meta-nav">"'.get_the_title().'"</span>', 'falcon' ) . '</a></div>';
	}
}
endif;

/**
 * Replaces "[...]" (appended to automatically generated excerpts) with an ellipsis and falcon_continue_reading_link().
 *
 * To override this in a child theme, remove the filter and add your own
 * function tied to the excerpt_more filter hook.
 * @return string An ellipsis
 */
function falcon_auto_excerpt_more( $more ) {
	return ' &hellip;' . falcon_continue_reading_link();
}
add_filter( 'excerpt_more', 'falcon_auto_excerpt_more' );

/**
 * Adds a pretty "Continue Reading" link to custom post excerpts.
 *
 * To override this link in a child theme, remove the filter and add your own
 * function tied to the get_the_excerpt filter hook.
 * @return string Excerpt with a pretty "Continue Reading" link
 */
function falcon_custom_excerpt_more( $output ) {
	if ( has_excerpt() && ! is_attachment() ) {
		$output .= falcon_continue_reading_link();
	}
	return $output;
}
add_filter( 'get_the_excerpt', 'falcon_custom_excerpt_more' );

/**
 * Remove inline styles printed when the gallery shortcode is used.
 *
 * Galleries are styled by the theme in Falcon's style.css.
 * @return string The gallery style filter, with the styles themselves removed.
 */
function falcon_remove_gallery_css( $css ) {
	return preg_replace( "#<style type='text/css'>(.*?)</style>#s", '', $css );
}
add_filter( 'gallery_style', 'falcon_remove_gallery_css' );

if ( ! function_exists( 'falcon_comment' ) ) :
/**
 * Template for comments and pingbacks.
 *
 * To override this walker in a child theme without modifying the comments template
 * simply create your own falcon_comment(), and that function will be used instead.
 *
 * Used as a callback by wp_list_comments() for displaying the comments.
 */
function falcon_comment( $comment, $args, $depth ) {
	$GLOBALS['comment'] = $comment;
	switch ( $comment->comment_type ) :
		case '' :
	?>
	<li <?php comment_class(); ?> id="li-comment-<?php comment_ID(); ?>">
		<div id="comment-<?php comment_ID(); ?>">
		<div class="comment-author vcard">
			<?php echo get_avatar( $comment, 40 ); ?>
			<?php printf( __( '%s <span class="says">says:</span>', 'falcon' ), sprintf( '<cite class="fn">%s</cite>', get_comment_author_link() ) ); ?>
		</div><!-- .comment-author .vcard -->
		<?php if ( $comment->comment_approved == '0' ) : ?>
			<em><?php _e( 'Your comment is awaiting moderation.', 'falcon' ); ?></em>
			<br />
		<?php endif; ?>

		<div class="comment-meta commentmetadata"><a href="<?php echo esc_url( get_comment_link( $comment->comment_ID ) ); ?>">
			<?php
				/* translators: 1: date, 2: time */
				printf( __( '%1$s at %2$s', 'falcon' ), get_comment_date(),  get_comment_time() ); ?></a><?php edit_comment_link( __( '(Edit)', 'falcon' ), ' ' );
			?>
		</div><!-- .comment-meta .commentmetadata -->

		<div class="comment-body"><?php comment_text(); ?></div>

		<div class="reply">
			<?php comment_reply_link( array_merge( $args, array( 'depth' => $depth, 'max_depth' => $args['max_depth'] ) ) ); ?>
		</div><!-- .reply -->
	</div><!-- #comment-##  -->

	<?php
			break;
		case 'pingback'  :
		case 'trackback' :
	?>
	<li class="post pingback">
		<p><?php _e( 'Pingback:', 'falcon' ); ?> <?php comment_author_link(); ?><?php edit_comment_link( __('(Edit)', 'falcon'), ' ' ); ?></p>
	<?php
			break;
	endswitch;
}
endif;

/**
 * Removes the default styles that are packaged with the Recent Comments widget.
 *
 * To override this in a child theme, remove the filter and optionally add your own
 * function tied to the widgets_init action hook.
 */
function falcon_remove_recent_comments_style() {
	global $wp_widget_factory;
	remove_action( 'wp_head', array( $wp_widget_factory->widgets['WP_Widget_Recent_Comments'], 'recent_comments_style' ) );
}
add_action( 'widgets_init', 'falcon_remove_recent_comments_style' );

if (!function_exists("falcon_entry_meta")) :
function falcon_entry_meta() {
	?>
	<div class="entry-meta byline clearfix">
		<span class="posted-on entry-date"><?php echo get_the_date().' '.esc_attr(get_the_time());?></span>
		<?php
		falcon_entry_meta_theme_specific_content();
		?>
	</div>
	<?php

	falcon_entry_meta_social_buttons_and_counters();
}
endif;


/* The following two functions 'falcon_entry_meta_xyz' are designed to be overridden in child classes */

if (!function_exists('falcon_entry_meta_theme_specific_content')) {
function falcon_entry_meta_theme_specific_content() {
	falcon_author_byline();
}
}

if (!function_exists('falcon_entry_meta_social_buttons_and_counters')) {
function falcon_entry_meta_social_buttons_and_counters() {
	falcon_social_buttons_and_counters();
}
}



if (!function_exists('falcon_author_byline')) :
function falcon_author_byline() {
	$theme_options = get_option('theme_options');

	$author_byline = '';
	if ((!is_page() && !empty($theme_options['byline_posts'])) || (is_page() && !empty($theme_options['byline_pages']))) {
		global $authordata;
		$the_author_posts_link = '<a href="'.get_author_posts_url( $authordata->ID, $authordata->user_nicename ).'"> by '.get_the_author().'</a>';
		if (!empty($authordata->ID)) {
			$author_byline  = '<span class="author_byline">'. $the_author_posts_link .'</span>';
		}
	}

	echo $author_byline;
}
endif;


if ( ! function_exists( 'falcon_social_buttons_and_counters' ) ) :
/**
 * Social counters
 */
function falcon_social_buttons_and_counters() {
	global $post;

	$theme_options = get_option('theme_options');

	echo '<div class="entry-meta byline clearfix">';

	if (!is_page()) {

		// Twitter
		if (!empty($theme_options['twitter_button_display_mode']) && $theme_options['twitter_button_display_mode'] == 1) {
			$iframewidth = 102;
			$twittercountposition = 'horizontal';
			if (!empty($theme_options['twitter_display_mode']) && $theme_options['twitter_display_mode'] == 1) {
				$iframewidth = 65;
				$twittercountposition = 'none';
			}

			$twitter_href  = 'http://platform.twitter.com/widgets/tweet_button.html?'; // Don't use www.twitter.com as it re-urlencodes the get parameters
			$twitter_href .= 'original_referer='	.rawurlencode(get_permalink());
			$twitter_href .= '&amp;url='			.rawurlencode(wp_get_shortlink());
			$twitter_href .= '&amp;counturl='		.rawurlencode(get_permalink());
			$twitter_href .= '&amp;count='			.rawurlencode($twittercountposition);

			$twitter_related = 'financialtimes,';
			if (!empty($theme_options['twitter_related'])) $twitter_related .= $theme_options['twitter_related'];
			$twitter_href .= '&amp;related='		.urlencode($twitter_related);

			$twitter_username = null;
			if (!empty($theme_options['twitter_username'])) {
				$twitter_username = rawurlencode($theme_options['twitter_username']);
				$twitter_href .= "&amp;via=".$twitter_username;
				$tweetcharsused += 6 + mb_strlen($theme_options['twitter_username']);  // Twitter username wont more than 15 chars
			}
			$twitter_href .= '&amp;text='			.rawurlencode(getTweetText($twitter_username));

			$twitter_social_counter = '<span class="twitter-counter social-counter"><iframe allowtransparency="true" frameborder="0" scrolling="no" src="'.$twitter_href.'" style="width:'.$iframewidth.'px; height:20px;"></iframe></span>';
		}

		// Facebook
		if (!empty($theme_options['facebook_button_display_mode']) && $theme_options['facebook_button_display_mode'] == 1) {
			$permalink = get_permalink($post->ID);
			$facebook_href = 'http://www.facebook.com/dialog/feed?'.http_build_query(array(
				'app_id' => '240084812701288',
				'link' => $permalink,
				'redirect_uri' => $permalink,
				'name' => get_bloginfo('name').' | FT.com',
				'caption' => $post->post_title,
				'description' => $post->post_excerpt,
			));
			$facebook_social_counter = '<span class="facebook-counter social-counter"><a target="_blank" class="facebook-counter" id="facebook-counter-' . $post->ID . '" href="'.$facebook_href.'">&nbsp;</a></span>';
		}

		// Linkedin
		if (!empty($theme_options['linkedin_button_display_mode']) && $theme_options['linkedin_button_display_mode'] == 1) {

			$linkedin_shareurl  = 'http://www.linkedin.com/shareArticle?'.http_build_query(array(
				'mini' => 'true',
				'url' => wp_get_shortlink($post->ID),
				'title' => ($post->post_title.' | '.get_bloginfo('name').' | FT.com '),
				'summary' => assanka_the_excerpt(),
				'source' => get_bloginfo('name').' | FT.com ',
			));

			$linkedin_social_counter = '<span class="linkedin-counter social-counter" data-shareurl="'.htmlspecialchars($linkedin_shareurl, ENT_QUOTES, 'UTF-8').'"><span class="clicktarget"><script type="IN/Share" data-url="'.htmlspecialchars(get_permalink(), ENT_QUOTES, 'UTF-8').'" data-counter="right" data-showzero="true"></script></span></span>';
		}

		// Google
		if (!empty($theme_options['google_button_display_mode']) && $theme_options['google_button_display_mode'] == 1) {
			$google_social_counter = '<span class="google-counter social-counter"><div class="g-plus" data-action="share" data-annotation="bubble" data-href="'.get_permalink().'"></div></span>';
		}
	}

	echo $facebook_social_counter;
	echo $twitter_social_counter;
	echo $linkedin_social_counter;
	echo $google_social_counter;


	if (!is_page() && comments_open() ) {
		// Inferno (comments)
		if (!empty($theme_options['inferno_button_display_mode']) && $theme_options['inferno_button_display_mode'] == 1) {
			echo '<span class="inferno-counter social-counter">';
			comments_popup_link( '0', __( '1 Comment', 'falcon' ), __( '0 Comments', 'falcon' ) );
			echo '</span>';
		}
	}

	echo '</div><!-- .entry-meta -->';
}
endif;
add_action('social_buttons_and_counters', 'falcon_social_buttons_and_counters');

if ( ! function_exists( 'falcon_posted_in' ) ) :
/**
 * Prints HTML with meta information for the current post (category, tags and permalink).
 */
function falcon_posted_in() {
	$posted_in = '';
	$tag_list = get_the_tag_list( '', ', ' );
	if ($tag_list) {
		$posted_in .= __( 'Tags: %1$s <br/>', 'falcon' );
	}
	$categories = get_the_category();
	if ( is_object_in_taxonomy( get_post_type(), 'category' ) and !empty( $categories ) ) {
		$posted_in .= __( 'Posted in %2$s | <a class="permalink" href="%3$s" rel="bookmark">Permalink</a>', 'falcon' );
	} else {
		$posted_in .= __( '<a href="%3$s" class="permalink" rel="bookmark">Permalink</a>', 'falcon' );
	}
	// Prints the string, replacing the placeholders.
	printf(
		$posted_in,
		$tag_list,
		get_the_category_list( ', ' ),
		get_permalink()
	);
}
endif;

/**
 * Assanka: Falcon theme functions
 */

if(!has_action('widgets_init','falcon_register_sidebar')){
	function falcon_register_sidebar(){
		register_sidebar(array(
			'name' => 'Sidebar',
			'before_widget' => '<div id="%1$s" class="%2$s">',
			'before_title' => '<div class="comp-header"><h3 class="comp-header-title">',
			'after_title' => '</h3></div><div class="widgetcontent">',
			'after_widget' => '</div></div>',

	    ));
	}
	add_action( 'widgets_init', 'falcon_register_sidebar' );
}

/**
 * Assanka: returns text for a tweet about the current post
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

function falcon_postActions($id, $blogid) {
	global $post;

	static $instance;
	$instance = (!$instance) ? 1 : $instance + 1;

	/**
	 * Clip
	 */
	$html_clip = '';

	if (!empty($_SERVER['IS_DEV'])) 		$clip_href  = $_SERVER['CLIPPINGS_HOST'] .'/clipthis/js';
	elseif (!empty($_SERVER['IS_LIVE'])) 	$clip_href  = 'http://clippings.ft.com/clipthis/js';
	else 									$clip_href  = 'http://staging.clippings.ft.com/clipthis/js';

	$post_tags = wp_get_post_tags($id);
	$post_tags_list = array();
	if ($post_tags) {
		foreach ($post_tags as $tag) {
			$post_tags_list[] = urlencode($tag->name);
		}
	}

	$clip_href .= '?url='                 .urlencode(get_permalink($post->ID));
	$clip_href .= '&title='               .urlencode($post->post_title);
	$clip_href .= '&tags='                .implode(',', $post_tags_list);
	$clip_href .= '&output=text';
	$clip_href .= '&note='                .urlencode(assanka_the_excerpt());
	$clip_href .= '&datepublished='       .urlencode($thepost->post_date);
	$clip_href .= '&containerid=clipthis' .$id.'_'.$instance;
	$clip_href .= '&clipthisdisplay=<span>Clip this</span>';
	$clip_href .= '&clippeddisplay=<span>Clipped</span>';

	$html_clip .= '<div id="clipthis'.$id.'_'.$instance.'" class="linkButton small">';
	$html_clip .= '	<a class="white" href="javascript:void(0)"><span>Clip this</span></a>';
	$html_clip .= '</div>';
	$html_clip .= '<script type="text/javascript">clipthishrefs.push("'.$clip_href.'");</script>';

	/**
	 * Print
	 */
	$html_print = '';

	$html_print .= '<div class="linkButton small">';
	$html_print .= '<a class="white" href="#" onclick="window.print();"><span>Print</span></a>';
	$html_print .= '</div>';

	$html_email  = apply_filters('get_mail_to_content', '');

	/**
	 * Share
	 */
	$html_share = falcon_get_share_widget_html();

	/**
	 * Combine and return
	 */
	$html_post_actions = $html_share . $html_clip . $html_print . $html_email;
	return $html_post_actions;
}

if ( ! function_exists( 'falcon_get_share_widget_html' ) ) :
// Generate and return HTML for the share button widget
function falcon_get_share_widget_html() {
	if(empty($post)) global $post;

	$sharelinks = array();

	// Twitter
	$twitter_href  = 'https://twitter.com/intent/tweet?'; // Don't use www.twitter.com as it re-urlencodes the get parameters
	$twitter_href .= 'original_referer='	.urlencode(get_permalink($post->ID));
	$twitter_href .= '&amp;text='			.urlencode(getTweetText());
	$twitter_href .= '&amp;url='			.urlencode(wp_get_shortlink($post->ID));

	$twitter_related = 'financialtimes,';
	if (!empty($theme_options['twitter_related'])) $twitter_related .= $theme_options['twitter_related'];
	$twitter_href .= '&amp;related='		.urlencode($twitter_related);
	$sharelinks[] = array("label"=>"Twitter", "safename"=>"twitter", "href"=>$twitter_href, "si_link"=>"social-media_twitter");

	// Facebook
	$facebook_href  = 'http://www.facebook.com/sharer/sharer.php?';
	$facebook_href .= 'u='					.urlencode(wp_get_shortlink($post->ID));
	$facebook_href .= '&amp;t='				.urlencode($post->post_title.' | '.get_bloginfo('name').' | FT.com ');
	$sharelinks[] = array("label"=>"Facebook", "safename"=>"facebook", 	"href"=>$facebook_href, "si_link"=>"social-media_facebook");

	// Google+
	$googleplus_href  = 'https://plus.google.com/share?';
	$googleplus_href .= 'url='					.urlencode(wp_get_shortlink($post->ID));
	$sharelinks[] = array("label"=>"Google+", "safename"=>"googleplus", "href"=>$googleplus_href, "si_link"=>"social-media_googleplus");

	// Linkedin
	$linkedin_href  = 'http://www.linkedin.com/shareArticle?';
	$linkedin_href .= 'mini=true';
	$linkedin_href .= '&amp;url='			.urlencode(wp_get_shortlink($post->ID));
	$linkedin_href .= '&amp;title='			.urlencode($post->post_title.' | '.get_bloginfo('name').' | FT.com ');
	$linkedin_href .= '&amp;summary='		.urlencode(assanka_the_excerpt());
	$linkedin_href .= '&amp;source='		.urlencode(get_bloginfo('name').' | FT.com ');
	$sharelinks[] = array("label"=>"LinkedIn", "safename"=>"linkedin", "href"=>$linkedin_href, "si_link"=>"social-media_linkedin");

	// Stumbleupon
	$sharelinks[] = array("label"=>"StumbleUpon", "safename"=>"stumbleupon", "href"=>"http://www.stumbleupon.com/submit?url=".urlencode(wp_get_shortlink($post->ID))."&amp;title=".urlencode($post->post_title), "si_link"=>"social-media_stumbleupon");

	// Reddit
	$sharelinks[] = array("label"=>"Reddit", "safename"=>"reddit", "href"=>"http://reddit.com/submit?url=".urlencode(wp_get_shortlink($post->ID))."&amp;title=".urlencode($post->post_title), "si_link"=>"social-media_reddit");

	// Share-widget button
	$html_share  = '';
	$html_share .= '<div class="linkButton small">';
	$html_share .= '	<a class="white" href="javascript:void(0)" onclick="$(this).parent().find(\'.shareList\').show();"><span>Share</span></a>';
	$html_share .= '	<div class="shareList roundedCorners overlay" style="padding: 4px;">';
	$html_share .= '		<div class="overlayArrow overlayTopArrow" style="margin: auto; margin-top: -21px;"></div>';
	$html_share .= '		<div class="innerBox">';
	$html_share .= '			<div class="title">';
	$html_share .= '				Share this on';
	$html_share .= '				<a href="javascript:void(0)" class="close-icon" onclick="$(this).closest(\'.shareList\').hide();"></a>';
	$html_share .= '			</div>';

	$html_share .= '			<ul class="clearfix">';
	foreach ($sharelinks as $sharelink) {
		$html_share .= '			<li class="sharelink ' . $sharelink['safename'] . '">';
		$html_share .= '				<a target="_blank" class="' . $sharelink['safename'] . '" href="' . $sharelink['href'] . '" si:link="' . $sharelink['si_link'] . '">' . $sharelink['label'] . '</a>';
		$html_share .= '			</li>';
	}
	$html_share .= '			</ul>';

	$html_share .= '		</div>';
	$html_share .= '		<div class="overlayArrow overlayBottomArrow" style="display: none;"></div>';
	$html_share .= '	</div>';
	$html_share .= '</div>';

	return $html_share;
}
endif;

/**
 * Return (as a string) the content generated from calling dynamic_sidebar()
 */
function get_dynamic_sidebar($index = 1) {
	$sidebar_contents = "";
	ob_start();
	dynamic_sidebar($index);
	$sidebar_contents = ob_get_clean();
	return $sidebar_contents;
}

/**
 *  Adds a filter to append the default stylesheet to the tinymce editor.
 */
if ( ! function_exists('tdav_css') ) {
	function tdav_css($wp) {
		$wp .= ',' . get_bloginfo('stylesheet_directory').'/style.css';
		// $wp .= ',http://s1.media.ft.com/m/style/N1042427199/bundles/user.css';
		$wp .= ',' . get_bloginfo('stylesheet_directory').'/style_tiny_mce.css';
		return $wp;
	}
}
add_filter( 'mce_css', 'tdav_css' );


/**
 *  Some custom excerpt handling.
 */


// If the except exists use that; otherwise if there's a more tag, use that; otherwise trim the content/teaser to the first two paragraphs.
function assanka_the_excerpt($return_html = FALSE) {
	global $post;

	// WordPress will look for the More tag and create a teaser from the content that precedes the More tag
	$content = $post->post_content;
	$content = apply_filters('the_content', $content);
	$content = str_replace(']]>', ']]&gt;', $content);

	// Match an empty More span which was inserted via get_the_content().
	$matches = null;
	preg_match('/(.*?)(<p>)?<span id="more(.*)/s', $content, $matches);
	if (!empty($matches[1])) {
		$content = $matches[1];
	} else {
		// Match all of the paragraphs of the post, and return them as an array.
		preg_match_all('/\<p\>(.*?)\<\/p\>/i', $content, $matches);

		// Join the first two paragraphs in the content back into a string
		$content = implode(' ', array_slice($matches[0], 0, 2));
	}

	if ($return_html == TRUE) {
		return force_balance_tags($content);
	} else {
		return strip_tags($content);
	}
}

/**
 * Generate the blog's url slug.
 * Returns the first slug in the request URI; e.g., The URI 'http://blogs.ft.com/brusselsblog/secondslug/thirdslug/' would return 'brusselsblog'.
 *
 * @dependency ftblogs-category-filter/category-filter.phtml
 */
function assanka_get_url_slug() {
	$bits = explode('/',$_SERVER['REQUEST_URI']);
	foreach ($bits as $key => $slug) {
		if (empty($slug)) {
			unset($bits[$key]);
		}
	}
	$url_slug = array_shift($bits);
	return $url_slug;
}
if ( ! function_exists( 'assanka_show_post_byline' ) ) :
/**
 * Displays an author headshot or country flag for posts.
 *
 * @prints HTML
 */
function assanka_show_post_byline() {
	// Src for the post's author's headshot
	$html = '';
	$theme_options = get_option('theme_options');


	if (is_page()) {
		// Display headshots for pages

		if (isset($theme_options['byline_pages']) && $theme_options['byline_pages'] == 2) {
			// Display author head shot.
			$author_id = get_the_author_meta('id');
			$byline_author_headshot_src = get_user_meta($author_id, '_ftblogs_headshoturl', true);
			if (!empty($byline_author_headshot_src)) {
				$html = '<img alt="'.get_the_author().'" height="45" width="35" src="' . $byline_author_headshot_src . '" class="headshot">';
			}
		}

	} else {
		// Display headshots for posts (and other types other than Pages)

		if (isset($theme_options['byline_posts']) && $theme_options['byline_posts'] == 2 && (!is_author() || $theme_options['author_biographies'] != 1)) {
			// Display author head shot (unless on author pages when biographies are enabled).
			$author_id = get_the_author_meta('id');
			$byline_author_headshot_src = get_user_meta($author_id, '_ftblogs_headshoturl', true);
			if (!empty($byline_author_headshot_src)) {
				$html = '<img alt="'.get_the_author().'" height="45" width="35" src="' . $byline_author_headshot_src . '" class="headshot">';
			}
		} elseif (isset($theme_options['byline_posts']) && $theme_options['byline_posts'] == 3) {
			// Display flag
			$category_id = get_post_meta(get_the_ID(), 'primary_category', true);
			if (!empty($category_id)) {
				$categories[] = $category_id;
			}
			else{
				$categories = get_the_category();
			}

			if (!empty($categories)) {
				foreach ($categories as $category) {
					$category_name = is_object($category) ? $category->name : $category;
					$country_name = str_replace(' ', '_', strtolower($category_name));
					$country_name = htmlspecialchars_decode($country_name, ENT_NOQUOTES);

					if (file_exists($_SERVER['DOCUMENT_ROOT'].'/wp-content/themes/threecolblognews/img/flags/24/flag_'.$country_name.'.png')) {
						$flag_src = $staticcontenthost.'/wp-content/themes/threecolblognews/img/flags/24/flag_'.urlencode($country_name).'.png';
						break;
					}
				}
				if (!empty($flag_src)) {
					$html = '<span class="entry_flag"><img src="' . $flag_src . '"></span>';
				}
			}
		}
	}

	if (!empty($html)) {
		return print($html);
	}
}
endif;

/**
 * Insert some meta tags for Facebook's spiders to use.
 */
add_action('wp_head', 'assanka_facebook_head');
function assanka_facebook_head() {
	// Only required on single posts.
	if ( !is_singular() ) return;

	global $wp_the_query;
	if ( !$id = $wp_the_query->get_queried_object_id() ) return;

	global $post;

	// URL of the author headshot.
	// Only used if there is an author headshot for the page; i.e. it's a post on a blog that has author headshots enabled - including the A list (which does it in a different way)
	$em_headshoturl_lrg = get_the_author_meta('_em_headshoturl_lrg', $post->post_author);
	if (!empty($em_headshoturl_lrg)) {
		echo '<meta property="og:image" content="'.$em_headshoturl_lrg.'"/>';
	} elseif (function_exists('assanka_get_the_guest_author_headshot')) {
		do_p2p_each_connected($wp_the_query);
		echo '<meta property="og:image" content="'.assanka_get_the_guest_author_headshot(true).'"/>';
	}

	// The title and URL of the article
	echo '<meta property="og:title" content="'.get_the_title( $id ).'"/>';
	echo '<meta property="og:url" 	content="'.get_permalink( $id ).'"/>';

	// There is no 'article description', so use the auto-generated excerpt
	echo '<meta property="og:description" content="'.htmlentities(assanka_the_excerpt(), ENT_QUOTES, "UTF-8").'"/>';

	// The final three meta tags are constant across all blogs
	echo '<meta property="og:type" 		content="article"/>';
	echo '<meta property="og:site_name" content="Financial Times"/>';
	echo '<meta property="fb:page_id" 	content="8860325749" />';
}

/**
 * Insert some meta tags for Google+ to see when sharing posts
 */
add_action('wp_head', 'assanka_googleplus_head');
function assanka_googleplus_head() {
	global $post;

	echo '<meta itemprop="name" content="'.htmlspecialchars($post->post_title, ENT_QUOTES, 'UTF-8').'">'.PHP_EOL;
	echo '<meta itemprop="description" content="'.htmlspecialchars($post->post_excerpt, ENT_QUOTES, 'UTF-8').'">'.PHP_EOL;
}


/**
 * Include posts from authors in the search results where
 * either their display name or user login matches the query string
 *
 * @author danielbachhuber
 */
add_filter( 'posts_search', 'db_filter_authors_search' );
function db_filter_authors_search( $posts_search ) {

	// Don't modify the query at all if we're not on the search template
	// or if the LIKE is empty
	if ( !is_search() || empty( $posts_search ) )
		return $posts_search;

	global $wpdb;
	// Get all of the users of the blog and see if the search query matches either
	// the display name or the user login
	add_filter( 'pre_user_query', 'db_filter_user_query' );
	$search = sanitize_text_field( get_query_var( 's' ) );
	$args = array(
		'count_total' => false,
		'search' => sprintf( '*%s*', $search ),
		'search_fields' => array(
			'display_name',
			'user_login',
		),
		'fields' => 'ID',
	);
	$matching_users = get_users( $args );
	remove_filter( 'pre_user_query', 'db_filter_user_query' );
	// Don't modify the query if there aren't any matching users
	if ( empty( $matching_users ) )
		return $posts_search;
	// Take a slightly different approach than core where we want all of the posts from these authors
	$posts_search = str_replace( ')))', ")) OR ( {$wpdb->posts}.post_author IN (" . implode( ',', array_map( 'absint', $matching_users ) ) . ")))", $posts_search );
	error_log( $posts_search );
	return $posts_search;
}
/**
 * Modify get_users() to search display_name instead of user_nicename
 */
function db_filter_user_query( &$user_query ) {

	if ( is_object( $user_query ) )
		$user_query->query_where = str_replace( "user_nicename LIKE", "display_name LIKE", $user_query->query_where );
	return $user_query;
}

// Set default link type for media objects to link to 'file' by default, instead of linking to a attachement post
update_option('image_default_link_type', 'file' );
