<?php
/*
Controller name: Core
Controller description: Basic introspection methods
*/

class JSON_API_Core_Controller {

	function __construct() {
		add_filter('json_api_import_wp_post', array($this, 'addGmtTimesToPostJson'), 10, 2);
		add_action('json_api-core-info', array($this, 'clamoApiHandler'), 10, 2);
	}

	public function get_recent_posts() {
		global $json_api;
		$posts = $json_api->introspector->get_posts();
		return $this->posts_result($posts);
	}

	public function get_post() {
		/**
		 * @var JSON_API
		 */
		global $json_api;
		global $post;

		// Only allow querying post_type "post" and webchat brands (if any)
		$_REQUEST['post_type'] = array('post');
		if (class_exists('Assanka_WebChatBrand')) {
			foreach (Assanka_WebChatBrand::getAll() as $webchat_brand) {
				if ($webchat_brand instanceof Assanka_WebChatBrand && isset($webchat_brand->post_type)) {
					$_REQUEST['post_type'][] = $webchat_brand->post_type;
				}
			}
		}

		$post = $json_api->introspector->get_current_post();
		if ($post) {
			$previous = get_adjacent_post(false, '', true);
			$next = get_adjacent_post(false, '', false);
			ob_start();
			$response = array(
				'post' => new JSON_API_Post($post)
			);

			ob_end_clean();
			if ($previous) {
				$response['previous_url'] = get_permalink($previous->ID);
			}
			if ($next) {
				$response['next_url'] = get_permalink($next->ID);
			}
			return $response;
		} else {
			$json_api->error("Not found.");
		}
	}

	/**
	 * Hook that adds custom fields during building the Post JSON
	 *
	 * @param JSON_API_Post $jsonApiPost
	 * @param $wp_post
	 * @hookedTo json_api_import_wp_post
	 */
	public function addGmtTimesToPostJson(JSON_API_Post $jsonApiPost, $wp_post) {
		// Add GMT fields to fix possible timezone mixups
		$date_format = $GLOBALS['json_api']->query->date_format;
		$jsonApiPost->set_value('date_gmt', date($date_format, strtotime($wp_post->post_date_gmt)));
		$jsonApiPost->set_value('modified_gmt', date($date_format, strtotime($wp_post->post_modified_gmt)));

		// Add FT Blogs specific information
		if (class_exists('Assanka_UID')){
			$jsonApiPost->set_value('uuid', Assanka_UID::get_the_post_uid($wp_post->ID));
			$jsonApiPost->set_value('blog_uid', Assanka_UID::get_the_blog_uid());
		}
		$jsonApiPost->set_value('blog_id', get_current_blog_id());

	}

	public function get_page() {
		global $json_api;
		extract($json_api->query->get(array('id', 'slug', 'page_id', 'page_slug', 'children')));
		if ($id || $page_id) {
			if (!$id) {
				$id = $page_id;
			}
			$posts = $json_api->introspector->get_posts(array(
															'page_id' => $id
														));
		} else if ($slug || $page_slug) {
			if (!$slug) {
				$slug = $page_slug;
			}
			$posts = $json_api->introspector->get_posts(array(
															'pagename' => $slug
														));
		} else {
			$json_api->error("Include 'id' or 'slug' var in your request.");
		}

		// Workaround for https://core.trac.wordpress.org/ticket/12647
		if (empty($posts)) {
			$url = $_SERVER['REQUEST_URI'];
			$parsed_url = parse_url($url);
			$path = $parsed_url['path'];
			if (preg_match('#^http://[^/]+(/.+)$#', get_bloginfo('url'), $matches)) {
				$blog_root = $matches[1];
				$path = preg_replace("#^$blog_root#", '', $path);
			}
			if (substr($path, 0, 1) == '/') {
				$path = substr($path, 1);
			}
			$posts = $json_api->introspector->get_posts(array('pagename' => $path));
		}

		if (count($posts) == 1) {
			if (!empty($children)) {
				$json_api->introspector->attach_child_posts($posts[0]);
			}
			return array(
				'page' => $posts[0]
			);
		} else {
			$json_api->error("Not found.");
		}
	}

	public function info()
	{
		// DO NOT DELETE, but keep empty. This method is hijacked by clamoApiHandler
		// but needs to stay present otherwise the Clamo API stops working.
	}

	/**
	 * Main method to handle Clamo API type requests from the WebApp and the Homepage Widget
	 *
	 * Supports 'getUserInfo', 'addQuerySubscription' and 'search' actions only, and only
	 * to a degree required by WebApp and the HP Widget.
	 *
	 * Clamo API requests will bypass any authentication set in the WP Admin settings
	 */
	public function clamoApiHandler() {
		$json_api = $GLOBALS['json_api']; /* @var JSON_API $json_api */

		/**
		 * Need to strip slashes because morons at WP HQ decided to stay
		 * in the middle ages and escape everything that comes in.
		 *
		 * @see wp_magic_quotes()
		 * @see https://core.trac.wordpress.org/ticket/18322
		 * @see http://i.imgur.com/iWKad22.jpg
		 */
		$request = json_decode(stripslashes($json_api->query->get('request')), true);

		if (!is_array($request)) {
			return $json_api->error("The 'request' supplied should be a string representing a valid JSON array of one action (note: this is not the same thing as raw JSON) [Invalid JSON]");
		}

		if (count($request) === 0) {
			return $json_api->error("The 'request' supplied should be a string representing a valid JSON array of one action (note: this is not the same thing as raw JSON) [Action array is empty]");
		}

		// Only one action is supported at the moment, ignore all the other ones:
		$request = $request[0];

		if (!isset($request['action'])) {
			return $json_api->error("The 'request' supplied should be a string representing a valid JSON array of one action (note: this is not the same thing as raw JSON) [No action specified]");
		}

		// Start buffering so that any output by plugins is buffered
		ob_start();

		switch ($request['action']) {
			case 'getUserInfo':
				$response = $this->clamoGetUserInfo();
				break;

			case 'addQuerySubscription':
				$response = $this->clamoAddQuerySubscription();
				break;

			case 'search':
				$response = $this->clamoSearch($request);
				break;

			default:
				return $json_api->error("Action not found: " . $request['action']);
		}

		// Discard any unwanted output from any hook/plugins
		ob_end_clean();

		// Send the response directly bypassing the controller as well as authentication
		$json_api->response->respond(
			[$response]
		);
		exit;
	}

	/**
	 * handles WebApp config call, returns configuration of the homepage tabs and
	 * corresponding search queries (the 'searchviews' field)
	 *
	 * @return array response
	 */
	protected function clamoGetUserInfo() {
		$homepageTabs = [
			'All'       => '',
			'US'        => 'tag:us',
			'Eurozone'  => 'tag:europe',
			'Asia'      => 'tag:asia',
			'Economy'   => 'tag:economy',
			'Companies' => 'tag:companies',
		];

		$searchviews = [];
		$id = 0;
		foreach ($homepageTabs as $title => $query) {
			$id++;
			$searchviews[] = [
				"id" => $id,
				"offsetlimit" => "0,50",
				"querydefid" => $id,
				"dateread" => [
				"date" => "2014-01-06 18:05:32.000000",
					"timezone_type" => 3,
					"timezone" => "Europe/London"
				],
				"islocked" => false,
				"isprimary" => false,
				"perpage" => 50,
				"query" => $query,
				"dispopts" => "",
				"sort" => "date",
				"title" => $title,
				"unreadcount" => 0,
				"classname" => null,
			];
		}

		return [
			"status" => "ok",
			"data" => [
				"id" => 1,
				"pseudonym" => "Anonymous User",
				"timezone" => "Europe/London",
				"avatar" => "https://secure.gravatar.com/avatar/beb21939f6a5a1e3b48faa2d5eed358a?d=mm",
				"role" => "anonymous",
				"searchviews" => $searchviews,
			]
		];
	}

	/**
	 * Handles WebApp subscription request. Assanka_RealTimePostUpdates plugin must be
	 * enabled, as it uses it to get the Pusher channel and appkey.
	 *
	 * @return array response (or die in error)
	 */
	protected function clamoAddQuerySubscription() {
		$json_api = $GLOBALS['json_api']; /* @var JSON_API $json_api */

		if (
			!class_exists('Assanka_RealTimePostUpdates') ||
			!isset($GLOBALS['assankaRealTimePostUpdates']) ||
			!$GLOBALS['assankaRealTimePostUpdates'] instanceof Assanka_RealTimePostUpdates
		){
			return $json_api->error('Real time posts are not enabled.');
		}

		/* @var Assanka_RealTimePostUpdates $assankaRealTimePostUpdates */
		$assankaRealTimePostUpdates = $GLOBALS['assankaRealTimePostUpdates'];

		return [
			"status" => "ok",
			"data" => [
				"queryid" => 1,
				"channel" => $assankaRealTimePostUpdates->getPusherChannelAllPosts(),
				"appkey" => $assankaRealTimePostUpdates->getPusherAppKey(),
			]
		];
	}

	/**
	 * Handles the WebApp and HP Widget search functionality. Supports very basic
	 * queries (@see clamoParseSearchQuery() method) and transforms the results
	 * into Clamo API like format (@see clamoMapSearchResultFields() method).
	 *
	 * @param array $request
	 * @return array
	 */
	protected function clamoSearch(array $request) {
		$json_api = $GLOBALS['json_api']; /* @var JSON_API $json_api */
		$json_api->query->date_format = 'U'; // get dates as unix timestamps

		// Parse the query
		$query = $this->clamoParseSearchQuery($request['arguments']['query']);

		// Set offset and limit
		$offset = isset($request['arguments']['offset']) ? $request['arguments']['offset'] : 0;
		$posts_per_page = isset($request['arguments']['limit']) ? $request['arguments']['limit'] : 10;

		$results = [];
		$queryStr = ''; // To be used in the "srh" part of the response

		// Set global $more variable to true so that WP returns full content of the posts
		// despite the fact they are in the loop (normaly it would only return excerpts)
		$GLOBALS['more'] = true;

		// If searching for tag
		if ($query['type'] === 'tag') {
			$queryStr = 'tag:' . (strpos($query['query'], ' ') ? '"' . $query['query'] . '"' : $query['query']);

			// Get tag by its name
			$wp_tag = get_term_by('name', $query['query'], 'post_tag');

			// If tag does not exist, search for the tag name instead
			if (!is_object($wp_tag) || !isset($wp_tag->slug)) {
				$query['type'] = 'search';
			} else {
				// Search for posts with given tag
				$tag = new JSON_API_Tag($wp_tag);
				$posts = $json_api->introspector->get_posts(array(
					'tag' => $tag->slug,
					'offset' => $offset,
					'posts_per_page' => $posts_per_page,
				));
				// Transform the results into array of JSON_API_Post objects
				$results = $this->posts_object_result($posts, $tag);
			}
		}

		// If doing a fulltext search:
		if ($query['type'] === 'search') {
			$queryStr = $query['query'];
			$posts = $json_api->introspector->get_posts(array(
				's' => $query['query'],
				'offset' => $offset,
				'posts_per_page' => $posts_per_page,
			));
			$results = $this->posts_result($posts);
		}

		// Get total number of results from the WP Query
		$totalResults = (int) $GLOBALS['wp_query']->found_posts;

		// Generate the response, the actual 'results' need to be mapped and transformed
		// to conform the Clamo Engine API style
		$response = [
			'status' => 'ok',
			'data' => [
				'results' => $this->clamoMapSearchResultFields($results['posts']),
				'srh' => [
					"query" => [
						"id" => 1,
						"str" => $queryStr,
						"url" => urlencode($queryStr),
						"obj" => new stdClass(),
						"hash" => md5($queryStr)
					],
					"sort" => "date",
					"limit" => $posts_per_page,
					"offset" => $offset,
					"dispopts" => [],
				],
				'resultsummarytext' => number_format($totalResults) . ' result' . ($totalResults !== 1 ? 's' : '' ),
				'total' => $totalResults,
				'count' => count($results['posts']),
			],
		];

		return $response;
	}

	/**
	 * Transforms WP JSON API results into Clamo API like format
	 *
	 * @param array $posts objects of JSON_API_Post
	 * @return array
	 */
	protected function clamoMapSearchResultFields($posts) {

		if (!is_array($posts)) {
			return [];
		}

		$clamoPosts = [];
		foreach ($posts as $post) { /* @var JSON_API_Post $post */

			$tags = [];
			if (is_array($post->tags)) {
				foreach ( $post->tags as $tag )
				{
					/* @var JSON_API_Tag $tag */
					$tags[] = [
						'tag' => $tag->title,
						'id' => $tag->id,
						'query' => 'tag:' . ( strpos( $tag->title, ' ' ) ? '"' . $tag->title . '"' : $tag->title ),
						'classname' => null,
					];
				}
			}

			$isSticky =  is_sticky($post->id);
			if(
				isset($post->custom_fields) &&
				is_object($post->custom_fields) &&
				isset($post->custom_fields->primary_tag) &&
				is_array($post->custom_fields->primary_tag) &&
				isset($post->custom_fields->primary_tag[0])
			) {
				$primaryTag = $post->custom_fields->primary_tag[0];
			} else {
				$primaryTag = 0;
			}

			$clamoPosts[] = [
				'id' => $post->id,
				'title' => html_entity_decode($post->title),
				'type' => ($post->type === 'post' ? 'article' : $post->type),
				'status' => ($post->status === 'publish' ? 'live' : $post->status),
				'url' => $post->url,
				'shorturl' => wp_get_shortlink($post->id),
				'content' => $post->content,
				'abstract' => $post->excerpt,
				'datepublished' => $post->date,
				'currentversion' => 1,
				'attachments' => [],
				'metadata' => [
					'primarytagid' => $primaryTag,
				],
				'issticky' => $isSticky,
				'tags' => $tags,
				'uuidv3' => $post->uuid,
				'sortval' => ($isSticky ? '0' : '1') . '-0-' . str_pad($post->date, 11, '0', STR_PAD_LEFT),
			];

			// Optimise memory usage
			unset($tags);
		}

		return $clamoPosts;
	}

	/**
	 * Parses and simplifies Clamo-like search query.
	 * Note: WP search adaptor only supports simple queries, see below.
	 *
	 * If it's a tag in form "taxonomy:tag",
	 *   If it's using "status" taxonomy,
	 *     (0) Then ignore it completely
	 *         And continue with next term in the loop
	 *   If it's using valid taxonomy,
	 *     If it's the first thing in the search query,
	 *       (1) Then simply show posts with that tag and ignore
	 *           the rest of the search query
	 *           And break out of the loop
	 *     Else (if is not first thing in the search query),
	 *       (2) Then add just the "tag" part (i.e. not the taxonomy) to the search
	 *           And continue with next query term in the loop
	 *   Else (if it's not using valid taxonomy),
	 *      (3) Then treat it just like any full text search term and add the
	 *          whole "taxonomy:tag" to the search
	 * Else (if it isn't in "taxonomy:tag" form,
	 *   (4) Then add the term to the search
	 *
	 * Examples:
	 * (0)
	 *   "status:live" ==> search("")
	 *   "status:live test" ==> search("test")
	 *   "test status:live test2" ==> search("test test2")
	 * (1)
	 *   "location:us" ==> tag("us")
	 *   "location:us test" ==> tag("us")
	 *   "location:us institution:fed" ==> tag("us")
	 *   "location:us test test2" ==> tag("us")
	 *   "status:live location:us" ==> tag("us")
	 * (2)
	 *   "test and location:us" ==> search("test and us")
	 *   "test and location:us and status:live" ==> search("test and us")
	 * (3)
	 *   "invalid_taxonomy:us" ==> search("invalid_taxonomy:us")
	 * (4)
	 *   "test" ==> search("test")
	 *
	 * @param string $searchQuery
	 * @return array ["type": "tag"|"search", "query": "<tag>"|"<search_term>"]
	 */
	protected function clamoParseSearchQuery($searchQuery) {
		$allowedTaxonomies = [
			'spotlight',
			'topic',
			'sector',
			'person',
			'location',
			'institution',
			'company',
			'tag',
		];

		$specialCharMap = [
			'"' => '{{{quote}}}',
			' ' => '{{{space}}}',
			'(' => '{{{openpar}}}',
			')' => '{{{closepar}}}',
			':' => '{{{colon}}}',
		];

		// Replace spaces in quoted strings with special sequence to
		// avoid exploding on them later
		$searchQuery = preg_replace_callback(
			'/("[^"]+"|"[^"]+)/',
			function ($matches) use ($specialCharMap) {
				return strtr($matches[0], $specialCharMap);
			},
			$searchQuery
		);

		// Explode search query by space
		$queryArr = explode(' ', trim(strtolower($searchQuery)));

		$searchArr = [];
		$searchTag = false;
		foreach ($queryArr as $queryTerm) {
			// Trim space characters as well as parentheses:
			$queryTerm = trim($queryTerm, "\t\n\r\0\x0B()");

			// Ignore empty terms (i.e. remove multiple spaces)
			if ($queryTerm === '') continue;

			// If term is in "taxonomy:tag" form:
			if (strpos($queryTerm, ':') !== false) {
				list ($taxonomy, $tag) = explode(':', $queryTerm);
				$taxonomy = trim($taxonomy, "\t\n\r\0\x0B()");
				$tag      = trim($tag, "\t\n\r\0\x0B()");

				// Ignore "status" taxonomy, always return published only
				if ($taxonomy == 'status') continue;

				// If valid taxonomy:
				if (in_array($taxonomy, $allowedTaxonomies)) {
					// If this is the first term in the search query, search tags
					if (count($searchArr) === 0) {
						$searchTag = $tag;
						break;
					} else {
						// Otherwise add the tag to the full-text search query
						$searchArr[] = $tag;
						continue;
					}
				}
			}

			// Ignore "and" and "or" at the beginning of the query
			if (count($searchArr) === 0 && in_array($queryTerm, ['and', 'or'])) {
				continue;
			}

			// Add term to the full-text search query
			$searchArr[] = $queryTerm;
		}

		// Remove any trialing "and" and "or"
		if (in_array($searchArr[count($searchArr) - 1], ['and', 'or'])) {
			array_pop($searchArr);
		}

		// Return appropriate type and query (with special characters converted back)
		if ($searchTag !== false) {
			$query = trim(strtr($searchTag, array_flip($specialCharMap)), '"');
			return [
				'type' => 'tag',
				'query' => $query,
			];
		} else {
			$query = strtr(implode(' ', $searchArr), array_flip($specialCharMap));
			return [
				'type' => 'search',
				'query' => $query,
			];
		}
	}

	protected function get_object_posts($object, $id_var, $slug_var) {
		global $json_api;
		$object_id = "{$type}_id";
		$object_slug = "{$type}_slug";
		extract($json_api->query->get(array('id', 'slug', $object_id, $object_slug)));
		if ($id || $$object_id) {
			if (!$id) {
				$id = $$object_id;
			}
			$posts = $json_api->introspector->get_posts(array(
															$id_var => $id
														));
		} else if ($slug || $$object_slug) {
			if (!$slug) {
				$slug = $$object_slug;
			}
			$posts = $json_api->introspector->get_posts(array(
															$slug_var => $slug
														));
		} else {
			$json_api->error("No $type specified. Include 'id' or 'slug' var in your request.");
		}
		return $posts;
	}

	protected function posts_result($posts) {
		global $wp_query;
		return array(
			'count' => count($posts),
			'count_total' => (int) $wp_query->found_posts,
			'pages' => $wp_query->max_num_pages,
			'posts' => $posts
		);
	}

	protected function posts_object_result($posts, $object) {
		global $wp_query;
		// Convert something like "JSON_API_Category" into "category"
		$object_key = strtolower(substr(get_class($object), 9));
		return array(
			'count' => count($posts),
			'pages' => (int) $wp_query->max_num_pages,
			$object_key => $object,
			'posts' => $posts
		);
	}

}
