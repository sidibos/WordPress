<?php
/*
Plugin Name: Assanka Real-time post updates
Description: Integrates Wordpress with <a href="http://www.pusherapp.com">Pusher</a>. Updates index pages automatically to receive new posts and have deleted posts removed without refreshing the page.
Author: Assanka
Version: 1.0
Author URI: http://assanka.net
*/

class Assanka_RealTimePostUpdates {

	public function __construct() {
		// whenever post is created or updated
		add_action('wp_insert_post', array($this, 'publish_post'));

		// after post is untrashed
		add_action('untrashed_post', array($this, 'publish_post'), 100);
		
		// before post is trashed
		add_action('wp_trash_post', array($this, 'delete_post'));

		add_action('wp_footer', array($this, 'foot'), 20);
		add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));

		// check post for search page, AJAX
		add_action( 'wp_ajax_check_post_search', array($this, 'check_post_search') ); // Use this action for logged in users
		add_action( 'wp_ajax_nopriv_check_post_search', array($this, 'check_post_search') ); // Use this action for visitors
	}

	/**
	 * check if we need to publish the post
	 * @param  int $post_id 
	 */
	public function publish_post($post_id) {
		$event = (get_post_status($post_id) == 'publish') ? 'post-update' : 'post-delete';	
		$this->broadcast($post_id, $event);
	}

	/**
	 * check if we need to remove the post
	 * @param  int $post_id 
	 */
	public function delete_post($post_id) {
		if (get_post_status($post_id) == 'publish') {
			$this->broadcast($post_id,'post-delete');
		}
	}

	/**
	 * publish or remove post
	 * @param  int $post_id 
	 * @param  boolean $event 
	 */
	public function broadcast($post_id, $event = false) {
		if (!event) return; //safety check
		$post = get_post($post_id);
		$eventdata = array('id'=>$post->ID, 'pubdate'=>$post->post_date_gmt);
		$channels = array($this->getPusherChannelAllPosts());

		// Only process publishes
		if ($event == 'post-update') {

			// The post time is returned GMT and not blog time
			define('GMT_TIME', true);

			// Only process recent posts
			$fivehoursago = time() - (5 * 3600);
			if (strtotime($post->post_date) < $fivehoursago) return;

			// Construct the post url - add update time to invalidate cache
			$posturl = get_permalink($post->ID);
			if (strpos($posturl, '?') === false) $posturl .= '?updated='.time();
			else $posturl .= '&amp;updated='.time();

			// Create a new wp_query for this post so it can be read in The Loop
			$args = array(
				'p'         => $post->ID,
				'post_type' => $post->post_type,
			);
			query_posts( $args );

			global $wp_query;
			$wp_query->is_singular = false;

			global $more;
			$more = true;

			// Create the post HTML.  This assumes that the currently active theme uses get_template_part('loop') in the main loop to produce the markup for the posts list.
			ob_start();
			get_template_part('loop', 'index');
			$eventdata['html'] = ob_get_contents();
			$eventdata['url'] = $posturl;
			ob_end_clean();
			wp_reset_query();
			wp_reset_postdata();

		} elseif ($event == 'post-delete') {
			$channels[] = $this->getPusherChannelPostDeletions();
		}

		// Add category and tag specific channels
		if ($postcategories = get_the_category()) {
			foreach ($postcategories as $category) {
				$channels[] = $this->getPusherChannelCategory($category->cat_ID);
			}
		}
		if ($posttags = get_the_tags()) {
			foreach ($posttags as $tag) {
				$channels[] = $this->getPusherChannelTag($tag->term_id);
			}
		}

		// Broadcast the action to the all-posts channel
		if (!class_exists('Pusher')) require_once "pusher.php";
		$pusher = new Pusher($_SERVER['PUSHER_KEY'], $_SERVER['PUSHER_SECRET'], $_SERVER['PUSHER_APPID'], true, 'http://api.pusherapp.com', '80', 3);
		foreach ($channels as $ch) {
			$pusher->trigger($ch, $event, $eventdata);
		}
	}

	/**
	 * 
	 * @return ajax wp search in added post
	 */
	public function check_post_search() {

	    check_ajax_referer( 'assanka_realtimeposts', 'security' );

	    $post_id = $_POST['post_id'];
	    $search_terms = explode(" ", $_POST['search_terms']);

	    global $wpdb;
	    $search = "SELECT $wpdb->posts.ID FROM $wpdb->posts WHERE $wpdb->posts.ID = $post_id";

	    //wp search
	    foreach( $search_terms as $term ) {
	    	$term = esc_sql( like_escape( $term ) );
	    	$search .= "  AND (($wpdb->posts.post_title LIKE '%{$term}%') OR ($wpdb->posts.post_content LIKE '%{$term}%'))";
	    }

	    echo $wpdb->get_var($search);
	    die();
	}

	public function enqueue_scripts() {
		if (!empty($_GET['sockjs'])) {
			wp_enqueue_script('pusherflags', "/wp-content/plugins/assanka_web_chat/pusher-flags.js", array(), false, false);
		}
		wp_enqueue_script('pusher', 'http://js.pusher.com/2.0.5/pusher.min.js', array(), false, true);
	}

	public function foot() {
		$channel = null;
		if (is_home() or is_front_page() or is_search()) {
			$channel = $this->getPusherChannelAllPosts();
		} elseif (is_tag()) {
			$t = get_queried_object();
			$channel = $this->getPusherChannelTag($t->term_id);
		}
		?>
		<script type='text/javascript'>
		jQuery(function($) {
			setTimeout(function() {
				if (typeof Pusher === 'function') {
					if (!window.pusher) window.pusher = new Pusher('<?=$_SERVER['PUSHER_KEY']?>');
					<?php if ($channel): ?>
					var realtime_post_updates = window.pusher.subscribe('<?=$channel?>');
					realtime_post_updates.bind('post-update', function(data) {

						//search page, AJAX check if post matches search
						<?php if (is_search()) {  ?>
							jQuery.ajax({
								url : '<?php echo admin_url( 'admin-ajax.php' ); ?>',
								type : 'post',
								data : {
									action : 'check_post_search',
									security: '<?php echo wp_create_nonce( "assanka_realtimeposts" ); ?>',
									search_terms: '<?php echo get_search_query(); ?>',
									post_id : data.id
								},
								success : function( response ) { //returns post_id if terms found or NULL if not
									if (response == data.id) {	

								<?php } //if is_search() ?> 

								if (!$('#post-'+data.id).length) {
									$(data.html).insertBefore('.primary-content .hentry:eq(0)').hide().slideDown();
									if (typeof o_initialize == 'function') o_initialize('post-' + data.id, false);
									if (typeof ftvideo_initialize == 'function') ftvideo_initialize('post-' + data.id);
								}
							
							<?php if (is_search()) {  ?>
									}	
								} // function(response)
							}); // jQuery.ajax
							<?php } // if is_search() ?> 	
					});
					<?php endif; ?>
					var realtime_post_deletes = window.pusher.subscribe('<?php echo $this->getPusherChannelPostDeletions(); ?>');
					realtime_post_deletes.bind('post-delete', function(data) {
						$('#post-'+data.id).remove();
					});
				}
			}, 5000);
		});
		</script>
		<?php
	}

	public function getPusherChannelAllPosts() {
		return $this->getPusherChannelPrefix() . '.all-posts';
	}

	public function getPusherChannelPostDeletions() {
		return $this->getPusherChannelPrefix() . '.post-deletions';
	}

	public function getPusherChannelCategory($categoryId) {
		return $this->getPusherChannelPrefix() . '.posts-in-cat-' . $categoryId;
	}

	public function getPusherChannelTag($tagId) {
		return $this->getPusherChannelPrefix() . '.posts-with-tag-' . $tagId;
	}

	public function getPusherAppKey() {
		return isset($_SERVER['PUSHER_KEY']) ? $_SERVER['PUSHER_KEY'] : '';
	}

	protected function getPusherChannelPrefix() {
		return 'blogs.'.(empty($_SERVER['IS_LIVE'])?'staging-':'').'blog-'.get_current_blog_id();
	}
}

$assankaRealTimePostUpdates = new Assanka_RealTimePostUpdates();
