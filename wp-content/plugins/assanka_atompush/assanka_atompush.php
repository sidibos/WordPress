<?php
/*
Plugin Name: Assanka Atom Push
Plugin URI: http://www.assanka.net
Description: Implements an ATOM push notification, allowing updates to posts to be pushed out to any server that supports the ATOM publishing protocol.  Configure destination servers on the <a href="./options-general.php?page=assanka_atompush/assanka_atompush">ATOM push settings page</a>
Version: 1.0.0
Author: Assanka
Author Email: support@assanka.net
Author URI:
*/

//use FTBlogs\AtomPush\Message;
//require_once __DIR__ . '/classes/Message.php';

class Assanka_Atompush {
	private $logger;
	private $method;

	function __construct() {
		add_action('transition_post_status', array($this,'transition_post_status'), null, 3);
		add_action('add_meta_boxes',         array($this,'add_meta_boxes'));
		add_action('save_post',              array($this,'save_post'), 100, 2);
		add_action('admin_menu',             array($this,'include_options_page'));
		add_action('created_term',           array($this,'created_term'));

		register_activation_hook(__FILE__,   array($this,'install'));

		$this->logger = new FTLabs\Logger('blogs-atompush');

		// Disable all reporting to FT Labs error aggregator (129 is one higher than the highest severity level)
		$this->logger->setHandlerMinSeverity('report', 129);
	}

	/**
	 * Handle whenever a post (of any type) changes status.
	 * Possible statuses: inherit, auto-draft, draft, publish, or trash.
	 */
	public function transition_post_status($new_status=null, $old_status=null, $post=null) {

		// Reset the method, so that we can use it as a filter later on in save_post hook
		$this->method = false;

		// Only send atom-push messages if post-status is changing from or to 'publish'
		if ($new_status != 'publish' and $old_status != 'publish') return;
		// From [anything] to 'publish' = 'update'. From 'publish' to [anything not publish] = 'delete'
		// Save this to method property, so that it can be later used in save_post hook:
		$this->method = ($new_status == 'publish')? 'update' : 'delete';

		// Call queue_message here, because some status transitions do not trigger save_post
		// If save_post is triggered, it will override the result of this
		$this->queue_message($post, $this->method);
	}

	/**
	 * Each post that has queued messages has a single row in the assanka_atompush_content table.
	 * Posts can have multiple messages queued in the assanka_atompush_message table.
	 */
	private function queue_message($post=null, $method='delete') {
		if (empty($post)) return false; // REVIEW: Throw an error here?

		// No point in queueing anything if there's no destination.
		$destination_urls = get_option('wpatompush_destination_urls');
		if (empty($destination_urls)) return;

		if (!$this->okay_to_publish($post) or $method == 'delete') {
			$method = 'delete';
		} else {
			// If this is a new article, method is CREATE — otherwise it's UPDATE.
			$posted = get_post_meta($post->ID, '_atomsyndicationstate', true);
			$method = empty($posted)? 'create' : 'update';
		}
		update_post_meta($post->ID, '_atomsyndicationstate', 'posted');

		/**
		 * Database interactions
		 */
		global $wpdb;

		// For each destination, insert a row into atompush message table
		$message = array(
			'time'    => current_time('mysql'),
			'method'  => strtoupper($method),
			'body'    => $this->get_post_xml($post),
			'guid'    => get_permalink($post->ID),
			'post_id' => $post->ID,
			'blog_id' => get_current_blog_id(),
		);

		$destinations = explode("\n", trim($destination_urls));
		if (!is_array($destinations)) $destinations = array($destinations);
		foreach ($destinations as $url) {
			if (empty($url)) continue;

			$url = str_replace(
				array(
					'{{uuid}}',
					'{{rest_url}}',
				),
				array(
					(class_exists('Assanka_UID') ? Assanka_UID::get_the_post_uid($post->ID) : ''),
					rawurlencode(get_site_url() . '/' . get_option('json_api_base', 'api') . '/get_post/?id=' . $post->ID),
				),
				trim($url)
			);

			$message['destination']	= $url;

			// Write log entry
			$log = array(
				'act'     => 'queue',
				'post_id' => $post->ID,
				'blog_id' => get_current_blog_id(),
				'guid'    => get_permalink($post->ID),
				'method'  => strtoupper($method),
				'dest'    => $url,
			);
			if (class_exists('Assanka_UID')){
				$log['blog_uid'] = Assanka_UID::get_the_blog_uid();
				$log['blog_post_uid'] = Assanka_UID::get_the_post_uid($post->ID);
			}
			$this->logger->info('assanka_atompush_message',$log);

			//commenting this out as we don't want to push directly to CAPI 1 or 2
			/*try {
				$msg = new Message($message, $this->logger, $wpdb);
				$success = $msg->push();
			} catch (InvalidXmlException $e) {
				$success = false;
			}*/


			// Remove from the message table any existing messages (except CREATEs) that have the same post ID and destination.
			$wpdb->query($wpdb->prepare("DELETE FROM assanka_atompush_message WHERE method != %s AND destination = %s AND post_id = %d", 'CREATE', $url, $post->ID));

			// Insert fresh message row
			$wpdb->insert('assanka_atompush_message', $message);

			// Replace this post's row in the atompush content table
			$wpdb->query( $wpdb->prepare( "REPLACE INTO assanka_atompush_content SET guid = %s, destination = %s, erroremail = %s, attempts = 0, lastattempt = NULL", get_permalink($post->ID), $url, $this->get_error_email()));
		}
	}

	// Return whether or not it's okay to publish (CREATE or UPDATE) this post
	private function okay_to_publish($post){

		// Syndicated posts mustn't be atom pushed, regardless of user selection.
		if (get_post_meta($post->ID, 'syndicate', true)) return false;

		// Posts that are in non-pushable categories musn't be atom pushed, regardless of user selection.
		if ($this->in_nonpushable_category($post)) return false;

		// Posts that are not set to "yes_atompush" musn't be atom pushed.
		$atompush_selected =  get_post_meta($post->ID, 'assanka_atompush', true);
		return ($atompush_selected == 'yes_atompush');

		return true;
	}

	// Return whether or not the post is in a non-atom-pushable category
	private function in_nonpushable_category($post) {

		// The admin can select which categories should have their posts atom-pushed by default.
		$pushable_categories = get_option('wpatompush_selected_categories');

		// If they haven't created this setting, then pretend they've selected all categories to be atom-pushed.
		if (empty($pushable_categories) or !is_array($pushable_categories)){
			$pushable_categories = array();
			$all_categories = get_terms( 'category' );
			foreach ($all_categories as $category){
				$pushable_categories[] = $category->term_id;
			}
		}

		$in_nonpushable_category = false;
		$post_categories = get_the_category($post->ID);
		foreach ($post_categories as $category){
			if (!in_array($category->term_id, $pushable_categories)){
				$in_nonpushable_category = true;
			}
		}
		return $in_nonpushable_category;
	}

	// Generate and return XML for the atompush-message body
	private function get_post_xml($post){
		$o = '<?xml version="1.0" encoding="UTF-8"?>'."\n";
		$o .= '<entry xmlns="http://www.w3.org/2005/Atom" xmlns:ft="http://feeds.ft.com/2010">'."\n";
		$o .= '  <title>'.htmlspecialchars($post->post_title).'</title>'."\n";
		$o .= '  <link href="'.htmlspecialchars(get_permalink($post->ID)).'" />'."\n";
		$o .= '  <id>'.$post->guid.'</id>'."\n";

		$date = DateTime::createFromFormat('Y-m-d H:i:s', $post->post_modified_gmt, new DateTimeZone('GMT')) ;
		$o .= '  <updated>'.$date->format('c').'</updated>'."\n";

		$date = DateTime::createFromFormat('Y-m-d H:i:s', $post->post_date_gmt, new DateTimeZone('GMT')) ;
		$o .= '  <published>'.$date->format('c').'</published>'."\n";

		// The A-List blog: Redmine Feature #3498: Make the FT.com search use the 'Guest Author' field instead of the WP Author.
		$output_names = array();
		if (function_exists('assanka_the_guest_author') and function_exists('p2p_get_connected')) {
			$guest_author_ids = p2p_get_connected( $post->ID, $direction = 'any' );
			if (!empty($guest_author_ids)) {
				$guest_author_names = array();
				foreach($guest_author_ids as $guest_author_id){
					$guest_author = get_post( $guest_author_id );
					$guest_author_names[] = $guest_author->post_title;
				}
				$output_names[] = implode(', ', $guest_author_names);
			}
		} else {
			$authors = function_exists( 'get_coauthors' ) ? get_coauthors($post->ID) : array(get_userdata($post->post_author));
			foreach ($authors as $author) {
				$output_names[] = htmlspecialchars($author->display_name);
			}
		}

		if (!empty($output_names)) {
			$o.= '  <author><name>' . implode('</name></author>' . PHP_EOL . '  <author><name>', $output_names) . '</name></author>' . PHP_EOL;
		}

		$content = $post->post_content;
		if (trim($content) == '') $content = $post->post_excerpt;

		$clean_content = str_replace(']]>', ']'.']]><![CDATA['.']>', $content);
		$clean_content = htmlspecialchars($clean_content);
		$o .= '  <content><![CDATA[' . $clean_content . ']]></content>'."\n";

		$parsed_content = trim(apply_filters('the_content', $content));
		$o .= '  <ft:blog_html><![CDATA[' . $parsed_content . ']]></ft:blog_html>'."\n";

		$cats = get_the_category($post->ID);
		if (is_array($cats) and count($cats)) {
			foreach ($cats as $cat) $o .= '  <category term="'.htmlspecialchars($cat->name).'" />'."\n";
		}

		$tags = get_the_tags($post->ID);
		if (is_array($tags) and count($tags)) {
			foreach ($tags as $tag) $o .= '  <ft:blog_tag value="'.htmlspecialchars($tag->name).'" />'."\n";
		}

		$o .='  <ft:blog_name value="'.get_bloginfo('name').'" />'."\n";

		if (class_exists('Assanka_UID')){
			$o .='  <ft:blog_uid value="'.Assanka_UID::get_the_blog_uid().'" />'."\n";
			$o .='  <ft:blog_post_uid value="'.Assanka_UID::get_the_post_uid($post->ID).'" />'."\n";
		}

		$o .='  <post_id>' . $post->ID . '</post_id>'."\n";
		$o .='  <blog_id>' . get_current_blog_id() . '</blog_id>'."\n";
		$o .='</entry>';

		return $o;
	}

	// Add a meta box to the compose page for posts, pages and custom post types.
	function add_meta_boxes(){
		$atompush_compatible = array('post','page');
		foreach(get_post_types($args=array('_builtin'=>false)) as $post_type) {
			$atompush_compatible[] = $post_type;
		}
		foreach($atompush_compatible as $compatible_post_type){
			add_meta_box('assanka_atompushmetabox', 'Atom push: Options', array(&$this, 'add_atompushmetabox'), $compatible_post_type, 'side', 'low');
		}
	}
	function add_atompushmetabox() {
		global $post;

		// See if this post's atom-push preference is already set
		$atompush_selected = get_post_meta($post->ID, 'assanka_atompush', true);
		$yes_atompush_selected = null;
		$no_atompush_selected = null;
		if (empty($atompush_selected) || $atompush_selected == 'yes_atompush') {
			$yes_atompush_selected = 'checked = "checked"';
		} else {
			$no_atompush_selected = 'checked = "checked"';
		}

		/**
		 * The admin can select which categories should have their posts atom-pushed by default.
		 * If they haven't created this setting, then pretend they've selected all categories to be atom-pushed.
		 */
		$pushable_categories = get_option('wpatompush_selected_categories');

		// If the admin has never selected any categories, then pre-select all by default.
		if(empty($pushable_categories)){
			$all_categories = get_terms( 'category' );
			foreach($all_categories as $category){
				$pushable_categories[] = $category->term_id;
			}
		}

		// If this post is in a category that's not in the list of categories selected by the admin, then don't atom push it.
		$post_categories = get_the_category($post->ID);
		$alert_html = null;
		foreach($post_categories as $category){
			if (empty($pushable_categories) or !in_array($category->term_id,$pushable_categories)) {
				$yes_atompush_disabled = 'disabled="disabled"';
				$yes_atompush_selected =  null;
				$no_atompush_selected = 'checked = "checked"';

				// User feedback
				$alert_html = '<p style="background: #EAF2FA; padding: 2px 4px;"><strong>Note: This post can\'t be atom-pushed, </strong> because it\'s in the <strong>' . $category->name . '</strong> category.</p>';
			}
		}

		// Syndicated posts mustn't be atom pushed, regardless of user selection
		$syndicated_state = get_post_meta($post->ID, 'syndicate', true);
		if(!empty($syndicated_state)) {
			$yes_atompush_disabled = 'disabled="disabled"';
			$yes_atompush_selected =  null;
			$no_atompush_selected = 'checked = "checked"';

			// User feedback
			$alert_html = '<p style="background: #EAF2FA; padding: 2px 4px;"><strong>Note: </strong> This post has been <strong>syndicated</strong> from an external source, so it can\'t be atom-pushed.</p>';
		}

		// Output user instructions and alerts
		echo '<p>When your post is published, "atom push" sends it to the FT.com search engine so it can be included in their search results.</p>';
		echo $alert_html;

		// Output radio buttons
		echo "<p><input $yes_atompush_selected $yes_atompush_disabled type='radio' name='assanka_atompush' id='yes_atompush' value='yes_atompush'> <label for='yes_atompush'>Atom-push this post</label></p>";
		echo "<p><input $no_atompush_selected type='radio' name='assanka_atompush' id='no_atompush' value='no_atompush'> <label for='no_atompush'>Do not atom-push this post</label></p>";

		wp_nonce_field('assanka_atompush_option','assanka_atompush_nonce');

		// Link to Atom Push Settings page
		echo '<p align="right"><a href="options-general.php?page=assanka_atompush%2Fassanka_atompush.php" target="_blank">Atom Push Settings</a></p>';
	}

	// When post is saved (excluding autosaves), update a custom post metadata variable to save the 'assanka_atompush' state
	function save_post($post_ID, $post) {

		// Nonce check to make sure the field is changed only when the post is saved manually
		if (wp_verify_nonce($_REQUEST['assanka_atompush_nonce'],'assanka_atompush_option')
			&& isset($_POST['assanka_atompush'])) {
			$val = $_POST['assanka_atompush'];

			// Do not atom push if it's in a category that's not pushable
			if($this->in_nonpushable_category($post)) $val = 'no_atompush';
			update_post_meta($post_ID, 'assanka_atompush', $val);

			/**
			 * If the status transition requires queueing of the message, queue it:
			 * @see transition_post_status()
			 */
			if ($this->method !== false) {
				$this->queue_message($post, $this->method);
			}

		}
	}

	// Return email address for error notifications
	function get_error_email() {
		$email = get_option('wpatompush_error_email');
		if (!$email) {
			return "dotcomsupport@ft.com";
		} else {
			return $email;
		}
	}

	function include_options_page() {
		add_options_page('Atom push settings', 'Atom push', 8, __FILE__, array(&$this, 'do_atompush_options'));
	}
	public function do_atompush_options() {
		if (!empty($_POST) and !empty($_POST['atom-push-all-posts'])){
			$offset = 0;
			if (!empty($_POST['atom-push-all-posts-offset']) and is_numeric($_POST['atom-push-all-posts-offset'])){
				$offset = $_POST['atom-push-all-posts-offset'];
			}

			$limit = 0;
			if (!empty($_POST['atom-push-all-posts-limit']) and is_numeric($_POST['atom-push-all-posts-limit'])){
				$limit = $_POST['atom-push-all-posts-limit'];
			}

			// Queue an update message for every post in the blog.
			set_time_limit(0);
			$postcount = 0;
			$numberposts = 50;
			while ($posts_array = get_posts(array('post_status' => 'publish', 'numberposts' => $numberposts, 'offset' => $offset))) {
				foreach ($posts_array as $post) {
					$this->queue_message($post, 'update');
					$postcount++;
				}
				$offset += $numberposts;

				if ($limit > 0){
					if ($postcount + $numberposts > $limit) {
						$numberposts = $limit - $postcount;
						if ($numberposts <= 0) break;
					}
				}
			}

			// Feedback here
			?>
			<div id="setting-error-settings_updated" class="updated settings-error">
			<p><strong>Atom-Push all posts:</strong> <?= $postcount; ?> posts were added to the atom-push queue.</p></div>
			<?php
		}

		// Display options.
		include dirname(__FILE__) . '/options.phtml';
	}

	// Create atom push tables in database (if not there already)
	function install() {
		global $wpdb;

		if($wpdb->get_var("SHOW TABLES LIKE 'assanka_atompush_content'") != 'assanka_atompush_content') {
			$sql = "CREATE TABLE assanka_atompush_content (
					id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
					guid VARCHAR(127) NOT NULL,
					destination TEXT COLLATE utf8_unicode_ci NOT NULL,
					erroremail VARCHAR(127) NOT NULL,
					attempts int(9) unsigned DEFAULT 0 NOT NULL,
					lastattempt datetime DEFAULT NULL,
					UNIQUE KEY id (id)
				);";
			require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

			dbDelta($sql);
		}
		if($wpdb->get_var("SHOW TABLES LIKE 'assanka_atompush_message'") != 'assanka_atompush_message') {
			$sql = "CREATE TABLE assanka_atompush_message (
					`id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
					`time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
					`method` enum('CREATE','UPDATE','DELETE') COLLATE utf8_unicode_ci NOT NULL,
					`body` mediumtext COLLATE utf8_unicode_ci NOT NULL,
					`guid` varchar(127) COLLATE utf8_unicode_ci NOT NULL,
					`post_id` int(11) NOT NULL,
					`blog_id` int(11) NOT NULL,
					`destination` TEXT COLLATE utf8_unicode_ci NOT NULL,
					UNIQUE KEY `id` (`id`)
				);";
			require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

			dbDelta($sql);
		}
	}

	// Return list of categories that are selected as atom-pushable
	static function get_category_defaults() {
		$category_list = get_terms( 'category', array('hide_empty'=>false) );
		$pushable_categories = get_option('wpatompush_selected_categories');

		// If the admin has never selected any categories, then pre-select all by default.
		if(empty($pushable_categories)){
			foreach($category_list as $category){
				$pushable_categories[] = $category->term_id;
			}
		}

		// Match the list of all categories against the list of categories selected by the admin
		foreach($category_list as &$category){
			if(in_array($category->term_id, $pushable_categories)){
				$category->atom_push_selected = true;
			}
		}
		return $category_list;
	}

	// Add newly-created categories to the list of categories to atom-push.
	function created_term($term_id){
		$pushable_categories   = get_option('wpatompush_selected_categories');
		$pushable_categories[] = $term_id;
		update_option('wpatompush_selected_categories', $pushable_categories);
	}
}
new Assanka_Atompush();
