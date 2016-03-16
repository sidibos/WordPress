<?php
/**
 * Add a Falcon theme settings menu to the WP 'Appearance' menu.
 */
class FalconThemeSettings {

	function __construct() {
		// Specify Hooks/Filters
		add_action('admin_init', array(&$this, 'options_init'));
		add_action('admin_menu', array(&$this, 'options_add_page'));

		// Insert values into the theme header using wp_head();
		add_action('wp_head', array(&$this, 'wp_head'));

		// Insert values into the theme footer using wp_footer();
		add_action('wp_footer', array(&$this, 'wp_footer'));
	}

	// Register our settings. Add the settings section, and settings fields
	function options_init() {
		register_setting('theme_options', 'theme_options', array(&$this,'theme_options_normalize'));

		// Site options
		add_settings_section('theme_site_options', 'Site options', array(&$this, 'description_site_options'),  __FILE__);
		add_settings_field('byline_pages', 'Author bylines: Pages', array(&$this, 'setting_byline_pages'),  __FILE__, 'theme_site_options');
		add_settings_field('byline_posts', 'Author bylines: Posts', array(&$this, 'setting_byline_posts'),  __FILE__, 'theme_site_options');
		add_settings_field('author_biographies', 'Author biographies', array($this, 'setting_author_biographies'),  __FILE__, 'theme_site_options');
		add_settings_field('tabbed_page_menu', 'Page navigation', array(&$this, 'setting_tabbed_page_menu'),  __FILE__, 'theme_site_options');
		add_settings_field('extra_css', 'Extra CSS styles', array(&$this, 'setting_extra_css'),  __FILE__, 'theme_site_options');
		add_settings_field('promotional_banner', 'Promotional banner', array(&$this, 'setting_promotional_banner'),  __FILE__, 'theme_site_options');
		add_settings_field('promoted_categories', 'Promoted categories', array(&$this, 'setting_promoted_categories'),  __FILE__, 'theme_site_options');
		add_settings_field('navigation_override', 'Primary navigation', array(&$this, 'setting_navigation_override'),  __FILE__, 'theme_site_options');
		add_settings_field('breadcrumb_override', 'Breadcrumbs', array(&$this, 'setting_breadcrumb_override'),  __FILE__, 'theme_site_options');

		// Social media options
		add_settings_section('theme_socialmedia_options', 'Social media buttons', array(&$this, 'description_socialmedia_options'),  __FILE__);
		add_settings_field('facebook_button_display_mode', 'Facebook "like" button', array(&$this, 'setting_facebook_button_display_mode'),  __FILE__, 'theme_socialmedia_options');
		add_settings_field('linkedin_button_display_mode', 'LinkedIn "Share" button', array(&$this, 'setting_linkedin_button_display_mode'),  __FILE__, 'theme_socialmedia_options');
		add_settings_field('google_button_display_mode', 'Google "+1" button', array(&$this, 'setting_google_button_display_mode'),  __FILE__, 'theme_socialmedia_options');
		add_settings_field('inferno_button_display_mode', 'FT "Comments" button', array(&$this, 'setting_inferno_button_display_mode'),  __FILE__, 'theme_socialmedia_options');
		add_settings_field('twitter_button_display_mode', 'Twitter "Tweet" button', array(&$this, 'setting_twitter_button_display_mode'),  __FILE__, 'theme_socialmedia_options');
		add_settings_field('twitter_display_mode', 'Twitter display mode', array(&$this, 'setting_twitter_display_mode'),  __FILE__, 'theme_socialmedia_options');
		add_settings_field('twitter_username', 'Twitter username', array(&$this, 'setting_twitter_username'),  __FILE__, 'theme_socialmedia_options');
		add_settings_field('twitter_related', 'Related Twitter accounts', array(&$this, 'setting_twitter_related'),  __FILE__, 'theme_socialmedia_options');

		// Statistics and meta-data
		add_settings_section('theme_metastats_options', 'Statistics and meta-data', array(&$this, 'description_metastats_options'),  __FILE__);
		add_settings_field('site_description', 'Site description', array(&$this, 'setting_site_description'),  __FILE__, 'theme_metastats_options');
		add_settings_field('meta_tags', 'Meta tags', array(&$this, 'setting_meta_tags'),  __FILE__, 'theme_metastats_options');
		add_settings_field('google_tracking_code', 'Google analytics tracking code', array(&$this, 'setting_google_tracking_code'),  __FILE__, 'theme_metastats_options');
		add_settings_field('ad_section_code', 'Ad section code', array(&$this, 'setting_ad_section_code'),  __FILE__, 'theme_metastats_options');
		add_settings_field('ad_page_code', 'Ad page code', array(&$this, 'setting_ad_page_code'),  __FILE__, 'theme_metastats_options');
		add_settings_field('dfp_site', 'DFP site', array(&$this, 'setting_dfp_site'),  __FILE__, 'theme_metastats_options');
		add_settings_field('dfp_zone', 'DFP zone', array(&$this, 'setting_dfp_zone'),  __FILE__, 'theme_metastats_options');
		add_settings_field('site_map_term', 'Site map term', array(&$this, 'setting_site_map_term'),  __FILE__, 'theme_metastats_options');
		add_settings_field('brand', 'Brand', array(&$this, 'setting_brand'),  __FILE__, 'theme_metastats_options');
		add_settings_field('search_query', 'Query string params for FT search', array(&$this, 'setting_search_query'),  __FILE__, 'theme_metastats_options');

		// The "beyond-brics" blog uses the "Three col blog + news" child theme.
		$parsed_url = parse_url(get_bloginfo('wpurl'));
		if ($parsed_url['path'] == '/beyond-brics') {
			add_settings_section('theme_beyondbrics_options', 'Options for the <em>Beyond brics</em> blog', array(&$this, 'description_beyondbrics_options'),  __FILE__);
			add_settings_field('category_filter', 'Category filter', array(&$this, 'setting_category_filter'),  __FILE__, 'theme_beyondbrics_options');
			add_settings_field('category_filter_label_text', 'Category filter label text', array(&$this, 'setting_category_filter_label_text'),  __FILE__, 'theme_beyondbrics_options');
		}
	}

	// Add sub page to the Settings Menu
	function options_add_page() {
		add_menu_page('Theme Settings: '.get_current_theme(), 'Theme Settings', 'administrator', __FILE__, array(&$this, 'options_page'), null, '999');
	}

	/**
	 * Section: Site options
	 */
	function description_site_options() {
		echo '<p>These settings change the display of the website.</p>';
	}
	function setting_promotional_banner() {
		$options = get_option('theme_options');
		$options_promotional_banner = array(
			'Promotional banner on' => 1,
			'Promotional banner off' => 0
		);
		if (empty($options['promotional_banner'])) {
			$options['promotional_banner'] = 0;
		}
		echo'The promotional banner is displayed on the blog home page. It is only displayed if you have a <em>promoted category</em> and there is new content in it.<br />';
		foreach ( $options_promotional_banner as $label => $value ) {
			echo "\t<label title='" . esc_attr($label) . "'><input type='radio' name='theme_options[promotional_banner]' value='" . esc_attr($value) . "'";
			if ( $options['promotional_banner'] == $value ) {
				echo " checked='checked'";
			}
			echo ' /> ' . $label . "</label><br />\n";
		}
	}
	function setting_byline_pages() {
		$options = get_option('theme_options');
		$options_byline_pages = array(
			'Show author bylines on pages' => 1,
			'Show author bylines and head-shots on pages' => 2,
			'Do not show author bylines or head-shots on pages' => 0
		);
		if (empty($options['byline_pages'])) {
			$options['byline_pages'] = 0;
		}
		echo'Author headshots can be set via the <a href="users.php" target="_blank">user admin page</a>.<br />';
		foreach ( $options_byline_pages as $label => $value ) {
			echo "\t<label title='" . esc_attr($label) . "'><input type='radio' name='theme_options[byline_pages]' value='" . esc_attr($value) . "'";
			if ( $options['byline_pages'] == $value ) {
				echo " checked='checked'";
			}
			echo ' /> ' . $label . "</label><br />\n";
		}
	}
	function setting_byline_posts() {
		$options = get_option('theme_options');
		$options_byline_posts = array(
			'Show author bylines on posts' => 1,
			'Show author bylines and head-shots on posts' => 2,
			'Show author bylines and country flags on posts' => 3,
			'Do not show author bylines or head-shots on posts' => 0,
		);

		if (empty($options['byline_posts'])) {
			$options['byline_posts'] = 0;
		}
		echo'Author headshots can be set via the <a href="users.php" target="_blank">user admin page</a>.<br />To display country flags, posts\' categories need to have appropriate country names.<br />';
		foreach ( $options_byline_posts as $label => $value ) {
			echo "\t<label title='" . esc_attr($label) . "'><input type='radio' name='theme_options[byline_posts]' value='" . esc_attr($value) . "'";
			if ( $options['byline_posts'] == $value ) {
				echo " checked='checked'";
			}
			echo ' /> ' . $label . "</label><br />\n";
		}
	}
	function setting_author_biographies() {
		$options = get_option('theme_options');
		$options_author_biographies = array(
			'Show author biographies on author pages' => 1,
			'Do not show author biographies on author pages' => 0,
		);

		if (empty($options['author_biographies'])) {
			$options['author_biographies'] = 0;
		}
		echo 'Author biographies can be set via the <a href="users.php" target="_blank">user admin page</a>.<br/>';
		foreach ( $options_author_biographies as $label => $value ) {
			echo "\t<label title='" . esc_attr($label) . "'><input type='radio' name='theme_options[author_biographies]' value='" . esc_attr($value) . "'";
			if ( $options['author_biographies'] == $value ) {
				echo " checked='checked'";
			}
			echo ' /> ' . $label . "</label><br />\n";
		}
	}
	function setting_tabbed_page_menu() {
		$options = get_option('theme_options');
		$options_tabbed_page_menu = array(
			'Show page links in the tabbed menu' => 1,
			'Do not show page links in the tabbed menu' => 0
		);
		if (empty($options['tabbed_page_menu'])) {
			$options['tabbed_page_menu'] = 0;
		}
		echo'The tabbed menu can automatically display links to blog <em>pages</em> (rather than blog <em>posts</em> or <em>categories</em>.) <br />';
		foreach ( $options_tabbed_page_menu as $label => $value ) {
			echo "\t<label title='" . esc_attr($label) . "'><input type='radio' name='theme_options[tabbed_page_menu]' value='" . esc_attr($value) . "'";
			if ( $options['tabbed_page_menu'] == $value ) {
				echo " checked='checked'";
			}
			echo ' /> ' . $label . "</label><br />\n";
		}
	}
	function setting_promoted_categories() {
		$options = get_option('theme_options');
		$options['promoted_categories'] = !empty($options['promoted_categories'])?$options['promoted_categories']:array();
		$all_categories = get_categories();
		$options_promoted_categories = array();
		foreach ($all_categories as $row) {
			$options_promoted_categories[$row->name] = $row->term_id;
		}
		echo'The tabbed menu automatically displays links to any promoted categories. <br />';
		foreach ( $options_promoted_categories as $label => $value ) {
			echo "\t<label title='" . esc_attr($label) . "'><input type='checkbox' name='theme_options[promoted_categories][]' value='" . esc_attr($value) . "'";
			if ( in_array($value, $options['promoted_categories'])) {
				echo " checked='checked'";
			}
			echo ' /> ' . $label . "</label><br />\n";
		}
	}
	function setting_navigation_override() {
		$options = get_option('theme_options');
		echo "<textarea cols='36' rows='6' id='navigation_override' class='large-text code' name='theme_options[navigation_override]'>".$options['navigation_override']."</textarea><br/><span class='description'>If left blank, the <a href='".get_option('siteurl')."/wp-admin/network/settings.php?page=assanka_navigation'>site-wide primary navigation</a> will be used.</span>";
	}
	function setting_breadcrumb_override() {
		$options = get_option('theme_options');
		echo "<textarea cols='36' rows='6' id='breadcrumb_override' class='large-text code' name='theme_options[breadcrumb_override]'>".htmlspecialchars($options['breadcrumb_override'])."</textarea><br/><span class='description'>If left blank, the <a href='".get_option('siteurl')."/wp-admin/network/settings.php?page=assanka_navigation'>site-wide breadcrumbs</a> will be used.</span>";
	}
	function setting_extra_css() {
		$options = get_option('theme_options');
		echo "<textarea cols='36' rows='6' class='large-text code' id='extra_css' name='theme_options[extra_css]'>".$options['extra_css']."</textarea><br/><span class='description'>Any CSS code you enter here will be inserted into the header template &lt;head&gt;.</span>";
	}

	/**
	 * Section: Social media options
	 */
	function description_socialmedia_options() {
		echo '<p>Social media buttons (also called "counters") are displayed for every blog post. They can be turned on or off.</p>';
	}

	// Facebook
	function setting_facebook_button_display_mode() {
		$options = get_option('theme_options');
		$options_facebook_button_display_mode = array( 'Display the facebook button' => 1, 'Do not display the facebook button' => 0 );
		if(empty($options['facebook_button_display_mode'])) $options['facebook_button_display_mode'] = 0;
		foreach ( $options_facebook_button_display_mode as $label => $value ) {
			echo "\t<label title='" . esc_attr($label) . "'><input type='radio' name='theme_options[facebook_button_display_mode]' value='" . esc_attr($value) . "'";
			if ( $options['facebook_button_display_mode'] == $value ) {
				echo " checked='checked'";
			}
			echo ' /> ' . $label . "</label><br />\n";
		}
	}

	// Linkedin
	function setting_linkedin_button_display_mode() {
		$options = get_option('theme_options');
		$options_linkedin_button_display_mode = array( 'Display the linkedin button' => 1, 'Do not display the linkedin button' => 0 );
		if(empty($options['linkedin_button_display_mode'])) $options['linkedin_button_display_mode'] = 0;
		foreach ( $options_linkedin_button_display_mode as $label => $value ) {
			echo "\t<label title='" . esc_attr($label) . "'><input type='radio' name='theme_options[linkedin_button_display_mode]' value='" . esc_attr($value) . "'";
			if ( $options['linkedin_button_display_mode'] == $value ) {
				echo " checked='checked'";
			}
			echo ' /> ' . $label . "</label><br />\n";
		}
	}

	// Google +1
	function setting_google_button_display_mode() {
		$options = get_option('theme_options');
		$options_google_button_display_mode = array( 'Display the google+ button' => 1, 'Do not display the google+ button' => 0 );
		if(empty($options['google_button_display_mode'])) $options['google_button_display_mode'] = 0;
		foreach ( $options_google_button_display_mode as $label => $value ) {
			echo "\t<label title='" . esc_attr($label) . "'><input type='radio' name='theme_options[google_button_display_mode]' value='" . esc_attr($value) . "'";
			if ( $options['google_button_display_mode'] == $value ) {
				echo " checked='checked'";
			}
			echo ' /> ' . $label . "</label><br />\n";
		}
	}

	// Inferno (comments)
	function setting_inferno_button_display_mode() {
		$options = get_option('theme_options');
		$options_inferno_button_display_mode = array( 'Display the comments button' => 1, 'Do not display the comments button' => 0 );
		if(empty($options['inferno_button_display_mode'])) $options['inferno_button_display_mode'] = 0;
		foreach ( $options_inferno_button_display_mode as $label => $value ) {
			echo "\t<label title='" . esc_attr($label) . "'><input type='radio' name='theme_options[inferno_button_display_mode]' value='" . esc_attr($value) . "'";
			if ( $options['inferno_button_display_mode'] == $value ) {
				echo " checked='checked'";
			}
			echo ' /> ' . $label . "</label><br />\n";
		}
	}

	// Twitter
	function setting_twitter_button_display_mode() {
		$options = get_option('theme_options');
		$options_twitter_button_display_mode = array( 'Display the twitter+ button' => 1, 'Do not display the twitter+ button' => 0 );
		if(empty($options['twitter_button_display_mode'])) $options['twitter_button_display_mode'] = 0;
		foreach ( $options_twitter_button_display_mode as $label => $value ) {
			echo "\t<label title='" . esc_attr($label) . "'><input type='radio' name='theme_options[twitter_button_display_mode]' value='" . esc_attr($value) . "'";
			if ( $options['twitter_button_display_mode'] == $value ) {
				echo " checked='checked'";
			}
			echo ' /> ' . $label . "</label><br />\n";
		}
	}

	// Additional Twitter settings
	function setting_twitter_display_mode() {
		$options = get_option('theme_options');
		$options_twitter_display_mode = array(
			'Display tweet counter' => 0,
			'Do not display tweet counter' => 1
		);
		if (empty($options['twitter_display_mode'])) {
			$options['twitter_display_mode'] = 0;
		}
		echo'Twitter ("Tweet") buttons can be shown with or without tweet counters.<br />';
		foreach ( $options_twitter_display_mode as $label => $value ) {
			echo "\t<label title='" . esc_attr($label) . "'><input type='radio' name='theme_options[twitter_display_mode]' value='" . esc_attr($value) . "'";
			if ( $options['twitter_display_mode'] == $value ) {
				echo " checked='checked'";
			}
			echo ' /> ' . $label . "</label><br />\n";
		}
	}
	function setting_twitter_username() {
		$options = get_option('theme_options');
		if (empty($options['twitter_username'])) {
			$options['twitter_username'] = '';
		}
		echo "\t<input type='text' name='theme_options[twitter_username]' value=\"" . esc_attr($options['twitter_username']) . "\" /><br />";
		echo '<span class="description">If set, this will append "via @username" to the end of each tweet sent using the tweet button.</span>';
	}
	function setting_twitter_related() {
		$options = get_option('theme_options');
		if (empty($options['twitter_related'])) {
			$options['twitter_related'] = '';
		}
		echo "\tfinancialtimes,<input type='text' name='theme_options[twitter_related]' value=\"" . esc_attr($options['twitter_related']) . "\" /><br />";
		echo '<span class="description">Comma-seperated list of related accounts for a user to follow once they have sent a Tweet using the Tweet Button.</span>';
	}


	/**
	 * Section: Three col blog + news
	 */
	function description_beyondbrics_options() {
		// Echo '<p>These settings are for the "Three col blog + news" theme.</p>';
	}
	function setting_category_filter() {
		$options = get_option('theme_options');
		$options_category_filter = array(
			'Show the category filter' => 1,
			'Do not show the category filter' => 0
		);
		if (empty($options['category_filter'])) {
			$options['category_filter'] = 0;
		}
		echo'The category filter appears at the top of the home page. <br />';
		foreach ( $options_category_filter as $label => $value ) {
			echo "\t<label title='" . esc_attr($label) . "'><input type='radio' name='theme_options[category_filter]' value='" . esc_attr($value) . "'";
			if ( $options['category_filter'] == $value ) {
				echo " checked='checked'";
			}
			echo ' /> ' . $label . "</label><br />\n";
		}
	}
	function setting_category_filter_label_text() {
		$options = get_option('theme_options');
		$options['category_filter_label_text'] = !empty($options['category_filter_label_text'])? $options['category_filter_label_text'] : 'Filter by specific countries or regions';
		echo "<input id='category_filter_label_text' name='theme_options[category_filter_label_text]' size='40' type='text' value='{$options['category_filter_label_text']}' />";
	}

	/**
	 * Section: Statistics and meta-data
	 */
	function description_metastats_options() {
		echo '<p>These settings are for statistical tracking and search-engine meta data.</p>';
	}
	function setting_meta_tags() {
		$options = get_option('theme_options');
		echo "<textarea cols='36' rows='6' id='meta_tags' class='large-text code' name='theme_options[meta_tags]'>".$options['meta_tags']."</textarea><br/><span class='description'>Do not include a description meta tag</span>";
	}
	function setting_site_description() {
		$options = get_option('theme_options');
		echo "<textarea cols='36' rows='6' id='site_description' class='large-text' name='theme_options[site_description]'>".$options['site_description']."</textarea>";
	}
	function setting_google_tracking_code() {
		$options = get_option('theme_options');
		echo "<input id='google_tracking_code' name='theme_options[google_tracking_code]' size='40' type='text' value='{$options['google_tracking_code']}' />";
	}
	function setting_ad_section_code() {
		$options = get_option('theme_options');
		echo "<input id='ad_section_code' name='theme_options[ad_section_code]' size='40' type='text' value='{$options['ad_section_code']}' />";
	}
	function setting_ad_page_code() {
		$options = get_option('theme_options');
		echo "<input id='ad_page_code' name='theme_options[ad_page_code]' size='40' type='text' value='{$options['ad_page_code']}' />";
	}
	function setting_dfp_site() {
		$options = get_option('theme_options');
		echo "<input id='dfp_site' name='theme_options[dfp_site]' size='40' type='text' value='{$options['dfp_site']}' />";
	}
	function setting_dfp_zone() {
		$options = get_option('theme_options');
		echo "<input id='dfp_zone' name='theme_options[dfp_zone]' size='40' type='text' value='{$options['dfp_zone']}' />";
	}
	function setting_site_map_term() {
		$options = get_option('theme_options');
		echo "<input id='site_map_term' name='theme_options[site_map_term]' size='40' type='text' value='{$options['site_map_term']}' />";
	}
	function setting_brand() {
		$options = get_option('theme_options');
		echo "<input id='brand' name='theme_options[brand]' size='40' type='text' value='{$options['brand']}' />";
	}
	function setting_search_query() {
		$options = get_option('theme_options');

		// Delete legacy versions
		delete_option('assanka_ftintegration_blogIdentifierForFTSearch');
		delete_option('search_id');

		echo "<input id='search_query' name='theme_options[search_query]' size='40' type='text' value='{$options['search_query']}' /><br/><span class='description'>A valid HTTP URI query expression to add to the query string when making a search request to <a href='http://search.ft.com/search?queryText=blogs' target='_blank'>http://search.ft.com/</a>, in order that the search can be restricted to just this blog's posts. (eg <code>a=foo&b=bar</code>)</span>";
	}

	// Display the admin options page
	function options_page() {
	?>
		<div class="wrap">
			<div class="icon32" id="icon-options-general"><br></div>
			<h2>Theme Settings: <?php echo(get_current_theme()); ?> </h2>
			<h3>Header options</h3>
			<p>These settings can be accessed in different areas.</p>
			<table class="form-table">
				<tbody>
					<tr valign="top">
						<th scope="row">Vanity slug / Tagline</th>
						<td>The tagline appears in the site header between the breadcrumbs and primary navigation. <br/>
						<a href="options-general.php" target="_blank">Edit the tagline here.</a></td>
					</tr>
				</tbody>
			</table>
			<form action="options.php" method="post">
				<?php settings_fields('theme_options'); ?>
				<?php do_settings_sections(__FILE__); ?>
				<p class="submit">
					<input name="Submit" type="submit" class="button-primary" value="<?php esc_attr_e('Save Changes'); ?>" />
				</p>
			</form>
		</div>
	<?php
	}

	// Validate user data for some/all of your input fields
	function theme_options_normalize($input) {
		$input['byline_pages']          = wp_filter_nohtml_kses($input['byline_pages']);
		$input['byline_posts']          = wp_filter_nohtml_kses($input['byline_posts']);
		$input['author_biographies']    = wp_filter_nohtml_kses($input['author_biographies']);
		$input['tabbed_page_menu']      = wp_filter_nohtml_kses($input['tabbed_page_menu']);
		$input['extra_css']             = wp_filter_nohtml_kses($input['extra_css']);
		$input['promotional_banner']    = wp_filter_nohtml_kses($input['promotional_banner']);
		$input['promoted_categories']   = $input['promoted_categories'];
		$input['site_description']      = wp_filter_nohtml_kses($input['site_description']);
		$input['meta_tags']             = $input['meta_tags'];
		$input['google_display_mode']   = wp_filter_nohtml_kses($input['google_display_mode']);
		$input['google_tracking_code']  = wp_filter_nohtml_kses($input['google_tracking_code']);
		$input['ad_section_code']       = wp_filter_nohtml_kses($input['ad_section_code']);
		$input['ad_page_code']          = wp_filter_nohtml_kses($input['ad_page_code']);
		$input['dfp_site']              = wp_filter_nohtml_kses($input['dfp_site']);
		$input['dfp_zone']              = wp_filter_nohtml_kses($input['dfp_zone']);
		$input['site_map_term']         = wp_filter_nohtml_kses($input['site_map_term']);
		$input['brand']                 = wp_filter_nohtml_kses($input['brand']);
		$input['twitter_username']		= preg_replace("/[@# ]/", '', $input['twitter_username']);
		if (strlen($input['twitter_username']) > 15) $input['twitter_username'] = substr($input['twitter_username'], 0, 15); // Twitter usernames can't be more than 15 chars

		return $input; // return validated input
	}

	function wp_head() {
		$options 	= get_option('theme_options');
		//get dfp value for this category
		$dfp_category_site 		= apply_filters('get_category_dfp_site','');
		$dfp_category_zone   	= apply_filters('get_category_dfp_zone','');
		$dfp_site 				= (strlen($dfp_category_site)) ? $dfp_category_site:$options['dfp_site'];
		$dfp_zone 				= (strlen($dfp_category_zone)) ? $dfp_category_zone:$options['dfp_zone'];

		if (!empty($options['extra_css'])) {
			echo "\n".'<style type="text/css">' . $options['extra_css'] . '</style>'."\n";
		}
		if (!empty($options['site_description']) && !is_single()) {
			echo "\n".'<meta name="description" content="'. $options['site_description'] .'" />'."\n";
		}
		if (!empty($options['meta_tags'])) {
			echo "\n" . $options['meta_tags'] ."\n";
		}
		if (!empty($dfp_site) && !empty($dfp_zone)) {?>

	<script type="text/javascript">
	// <![CDATA[
	var FT = FT || {};
	FT.env = {
		"dfp_site" : "<?php echo $dfp_site; ?>",
		"dfp_zone" : "<?php echo $dfp_zone; ?>",
		"dfp_targeting" : "",

		// Legacy Settings
		"site"     : "ftcom",
		"sec"      : "<?php echo $options['ad_section_code']; ?>",
		"page"     : "<?php echo $options['ad_page_code']; ?>",
		"artid"    : "",
		"server"   : "",
		"asset"    : "page"
	};
	// These global vars are required for FTTrack2.js
	FTSection 	= FT.env.sec;
	FTPage 		= FT.env.page;
	FTSite 		= FT.env.site;
	AssetType 	= FT.env.asset;

	var siteMapTerm = "<?php echo $options['site_map_term']; ?>";
	var brandName = "<?php echo $options['brand']; ?>";
	// ]]>
	</script>

			<?php
		}
	}

	function wp_footer() {
		$options = get_option('theme_options');

		if (!empty($options['google_tracking_code'])) {
			
			?>
			<!-- Google analytics -->
			<script type="text/javascript">
			// <![CDATA[
			var _gaq = _gaq || [];
			_gaq.push(['_setAccount', '<?php echo $options['google_tracking_code']; ?>']);
			_gaq.push(['_setDomainName', '.ft.com']);
			_gaq.push(['_trackPageview']);

			(function() {
				var ga = document.createElement('script'); ga.type = 'text/javascript'; ga.async = true;
				ga.src = ('https:' == document.location.protocol ? 'https://ssl' : 'http://www') + '.google-analytics.com/ga.js';
				var s = document.getElementsByTagName('script')[0]; s.parentNode.insertBefore(ga, s);
			})();
			// ]]>
			</script>

			<?php
		}
	}
}
new FalconThemeSettings();
