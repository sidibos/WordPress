<?php
/*
Plugin Name: Assanka get_the_excerpt
Plugin URI: http://assanka.net
Description: Provides custom logic for producing the excerpt of posts
Author: Assanka
Version: 1.0
Author URI: http://assanka.net
*/

class Assanka_GetTheExcerpt {
	/**
	 * We assume the post needs "Read more" link by default
	 */
	private static $has_read_more;

	function __construct() {
		add_filter('get_the_excerpt', array(&$this, 'hook_get_the_excerpt'));
		self::$has_read_more = true;
	}


	/**
	 * Get the description as appropriate, append a read-more link and return.
	 */
	function hook_get_the_excerpt() {
		$content = $this->get_the_description();

		// Append the read-more link to the end of the content if it's needed
		if(self::$has_read_more){
			$content .= '&nbsp;<a href="'. get_permalink() . '" rel="' . get_the_ID() . '" title="Continue reading: ' . get_the_title() . '" class="more-link">Read more</a>';
		}

		$content = trim(apply_filters('the_content', $content));
		$content = str_replace(']]>', ']]&gt;', $content);

	 	// Some blogs use the "More Link Modifier" plugin
		if (has_action('the_content', 'modifyMoreLink')) {
			$content = modifyMoreLink($content);
		}

		return $content;
	}

	/**
	 * (1) If there's a custom excerpt, use that;
	 * (2) Otherwise, if there's a <!--more--> tag, show the content preceeding that;
	 * (3) Otherwise, show the first two paragraphs of the normal content.
	 */
	function get_the_description(){
		if (has_excerpt(get_the_ID())) {
		 	global $post;
			self::$has_read_more = true;
			return $post->post_excerpt;
		}

		// If there is a <!--more--> tag, get_the_content() returns the content that precedes it,
		// and appends a "read more" anchor tag, which contains the ".more-link" class.
		$content = get_the_content();	
		if(empty($content)){
			global $post;
			$content = $post->post_content;
			//The post doesn't need "Read more" link if it's empty
			self::$has_read_more = false;
		}

		preg_match('/(.*)(?:<!--more--|<(?:a|span)(?:.*?)(?:class|id)="more-(?:.*?)(?:.*))/is', $content, $matches);
		if (!empty($matches[1])) {
			$content = force_balance_tags(trim($matches[1]));
			self::$has_read_more = true;
		} else {
			// If there was no ".more-link" anchor, then truncate to two paragraphs.
			$content = force_balance_tags(trim($content));
			$content = wpautop($content);
			$content = str_replace(['<blockquote><p>','</p></blockquote>'],['<p><blockquote>','</blockquote></p>'],$content);
			$paragraph_matches = null;
			preg_match_all('/\<p\>(.*?)\<\/p\>/is', $content, $paragraph_matches, PREG_PATTERN_ORDER);
			$paragraphs_count = count($paragraph_matches[1]);
			$paragraph_matches = array_slice($paragraph_matches[1], 0, 2);
			$content = implode(PHP_EOL.PHP_EOL, $paragraph_matches);
			//Add "Read more" link only if post has more than 2 paragraphs 
			self::$has_read_more = ($paragraphs_count > 2 ? true:false);
		}

		return $content;
	}
}
new Assanka_GetTheExcerpt;
