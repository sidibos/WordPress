<?php
class ThemeSettings {

	function __construct() {
		// Specify Hooks/Filters
		add_action('admin_init', array(&$this, 'options_init'));
		add_action('admin_menu', array(&$this, 'options_add_page'));
	}

	// Register our settings. Add the settings section, and settings fields
	function options_init() {
		register_setting('theme_options', 'theme_options', array(&$this,'theme_options_normalize'));

		// Statistics and meta-data
		add_settings_section('theme_metastats_options', 'Statistics and meta-data', array(&$this, 'description_metastats_options'),  __FILE__);
		add_settings_field('search_query', 'Query string params for FT search', array(&$this, 'setting_search_query'),  __FILE__, 'theme_metastats_options');
	}

	// Add sub page to the Settings Menu
	function options_add_page() {
		add_menu_page('Theme Settings: '.get_current_theme(), 'Theme Settings', 'administrator', __FILE__, array(&$this, 'options_page'), null, '999');
	}

	function description_metastats_options() {
		echo '<p>These settings are for statistical tracking and search-engine meta data.</p>';
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
		return $input; // return validated input
	}

}
new ThemeSettings();
