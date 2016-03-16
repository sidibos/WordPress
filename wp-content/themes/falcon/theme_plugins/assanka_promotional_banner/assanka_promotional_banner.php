<?php 
/*
 * Promoted categories is enabled/disabled (in WP admin-> Theme Settings).
 * If there is a new post in a 'promoted category', and it's newer than the latest post on the home page, display it in a banner-like format.
 */
 
add_filter('pre_get_posts', '_ftblogs_promotedcategories'); // Filter the posts
function _ftblogs_promotedcategories($query) {
	// On the home page, exclude posts from promotional categories
	$options = get_option('theme_options');
	if(!empty($options['promoted_categories'])){
		if ( $query->is_home ) {
			$excludes = Array();
			foreach($options['promoted_categories'] as $key){
				$excludes[] = '-'.$key;
			}
			$query->query_vars['cat'] = implode(" , ", $excludes);
		}
		$query->parsed_tax_query = false;
		return $query;
	}
}
 
function falcon_showMoreRecentPostsBanner() {
	global $wpdb;

	$options = get_option('theme_options');
	if(empty($options['promotional_banner'])){ return false; } 
	if(empty($options['promoted_categories'])){ return false; }

	// What is the most recent post?
	$sql = "SELECT * FROM $wpdb->posts WHERE post_status='publish' ORDER BY post_date DESC LIMIT 1";
	$mostRecent = $wpdb->get_row($sql, ARRAY_A);

	// Use the ID from wp_get_recent_posts to get the most recent post's categories
	$postCats = get_the_category($mostRecent['ID']);

	// Is the most recent post in a category? If not, don't continue.
	if(empty($postCats)){ return false; } 

	$html = '';	
	// Look through all of the categories that the post is in; see if any of them are promotional categories.
	foreach ($postCats as $cat => $catData){
		if(in_array($catData->term_id,$options['promoted_categories'])){

			$extract = strip_tags($mostRecent['post_content']);
			if (strlen($extract) > 180) $extract = preg_replace("/^(.{180}[^\s]*?)(\s.*)?$/", "$1", $extract)."...";
			if (strlen($extract) > 203) $extract = substr($extract, 0, 200)."...";
			$headerLink = "category/".$catData->category_nicename;
			
			preg_match('/(<a.*?><img .*? \/>.*?<\/a>)/', $mostRecent['post_content'], $img);
			$img = preg_replace('/height="[0-9].*?"/', 'height="100"' ,$img[1]);
			$img = preg_replace('/width="[0-9].*?"/', '' ,$img);
			
			$html = "<div class='newerpostsbanner'>".$img."<p class='entry strapline'><span class='strapline'>Latest from <strong><a href=".$headerLink.">".strtoupper($catData->cat_name)."</a></strong></span></p>";
			
			$html .= "<h4 class='entry-title'><a href=".$mostRecent['guid'].">".$mostRecent['post_title']."</a></h4>";
			$html .= "<p class='entry'>".$extract.'</p>';
			$html .= "<div class='heightlessclearer'></div>";
			$html .= "</div>";

			break; // Once we have found one category match, we don't need to look at the rest
		}
	}
	return($html);
}
