<?php
/*
Plugin Name: Assanka FT Wrappers
Plugin URI: http://blogs.ft.com
Description: Imports FT wrapper templates from a specified URL. Placeholders in the wrapper code are replaced with WordPress code and content.
Author: Assanka
Version: 1.0
Author URI: http://assanka.net/
*/

class Assanka_FTwrapper {

	private static $enabled = false;

	public static function enable($enable = true) {
		self::$enabled = $enable;
	}

	function __construct() {
		add_action('init', array($this, 'init'));
	}

	function init() {

		// Any theme that wishes to use this plugin should enable
		// it in the 'after_setup_theme' hook.  If the plugin is not
		// enabled, stop here.
		if (!self::$enabled) {
			return;
		}

		set_time_limit(10);

		// If the FT Wrapper HTML is set, hijack the WordPress theme.
		if ($ftwrapper_options = get_option('assanka_ftwrapper_options')and !empty($ftwrapper_options['ftwrapper_html'])) {
			add_action('wp', array($this, 'hijack_theme'));
		}

		// Styles
		add_action('wp_print_styles',                array($this, 'wp_print_styles'), 1);

		// Scripts
		add_action('wp_enqueue_scripts',             array($this, 'wp_enqueue_scripts'), 1);

		// Hooks
		add_action('assanka_import_ftwrapper_html',  array($this, 'import_ftwrapper_html'));
		add_action('admin_menu',                     array($this, 'create_options_menu_item'));
		add_action('wp_footer',                      array($this, 'output_footer_code'));
		add_action('plugin_action_links',            array($this, 'hook_plugin_action_links'), null, 2);


		// Disable some native WP functionality
		add_filter('show_admin_bar', '__return_false');
		remove_action('wp_head', 'rsd_link');
		remove_action('wp_head', 'wlwmanifest_link');
		remove_action('wp_head', 'wp_generator');

		// Run import when installing plugin; purge when uninstalling.
		register_activation_hook(__FILE__, array($this, 'plugin_install'));
		register_deactivation_hook(__FILE__, array($this, 'plugin_uninstall'));

		// Each placeholder in this array calls a class method which returns appropriate replacement content.
		$this->placeholders = array(
			'wp_head' => array(
				'title' => 'Head code',
				'pattern'=>'/\<!--ft.code:head--\>/i',
				'replacement'=>array(
					'object'=>$this,
					'function'=>'load_wordpress_function',
					'parameters'=>array('name'=>'wp_head')
				),
				'error'=>'The head code placeholder is missing.',
				'required' => false
			),
			'header' => array(
				'title' => 'Header section',
				'pattern'=>'/\<!--ft.content:header--\>/i',
				'replacement'=>array(
					'object'=>$this,
					'function'=>'load_wordpress_function',
					'parameters'=>array('name'=>'get_header')
				),
				'error'=>'The header section placeholder is missing.',
				'required' => false
			),
			'main' => array(
				'title' => 'Main content',
				'pattern'=>'/\<!--ft.content:contentWell--\>/i',
				'replacement'=>array(
					'object'=>$this,
					'function'=>'template_loader'
				),
				'error'=>'The main content placeholder is missing.',
				'required' => false
			),
			'rail' => array(
				'title' => 'Side rail',
				'pattern'=>'/\<!--ft.content:rightRailContentWell--\>/i',
				'replacement'=>array(
					'object'=>$this,
					'function'=>'load_wordpress_function',
					'parameters'=>array('name'=>'get_sidebar')
				),
				'error'=>'The side rail placeholder is missing.',
				'required' => false
			),
			'wp_footer' => array(
				'title' => 'Footer code',
				'pattern'=>'/\<!--ft.code:foot--\>/i', // Preferred: '/\<!--[\s]?ft[-:_]?foot[\s]?--\>/i'
				'replacement'=>array(
					'object'=>$this,
					'function'=>'load_wordpress_function',
					'parameters'=>array('name'=>'wp_footer')
				),
				'error'=>'The foot placeholder is missing.',
				'required' => false
			)
		);
	}

	/**
	 * Stylesheets
	 *
	 * First, the FT wrappers link to their base stylesheets.
	 * After that, the stylesheet for the wrapper plugin is linked;
	 * finally comes the theme's stylesheet.
	 */
	function wp_print_styles() {

		// Link the stylesheet for the plugin
		if (file_exists(plugin_dir_path(__FILE__).'style.css')) {
			wp_register_style('plugin_style',plugins_url('style.css', __FILE__),false,CACHEBUSTER);
			wp_enqueue_style( 'plugin_style' );
		}

		// Link the stylesheet for the theme
		if (file_exists(get_stylesheet_directory().'/style.css')) {
			wp_register_style('theme_style',get_stylesheet_directory_uri().'/style.css',false,CACHEBUSTER);
			wp_enqueue_style( 'theme_style' );
		}

		// Link the IE stylesheet for the plugin
		if (file_exists(plugin_dir_path(__FILE__).'styleie.css')) {
			wp_register_style('plugin_style_ie',plugins_url('styleie.css', __FILE__),false,CACHEBUSTER);
			$GLOBALS['wp_styles']->add_data( 'plugin_style_ie', 'conditional', 'IE' );
			wp_enqueue_style( 'plugin_style_ie' );
		}

		// Link the IE stylesheet for the theme
		if (file_exists(get_stylesheet_directory().'/styleie.css')) {
			wp_register_style('theme_style_ie',get_stylesheet_directory_uri().'/styleie.css',false,CACHEBUSTER);
			$GLOBALS['wp_styles']->add_data( 'theme_style_ie', 'conditional', 'IE' );
			wp_enqueue_style( 'theme_style_ie' );
		}

		// Link the print stylesheet for the theme
		if (file_exists(get_stylesheet_directory().'/print.css')) {
			wp_register_style('theme_style_print',get_stylesheet_directory_uri().'/print.css',false,CACHEBUSTER,'print');
			wp_enqueue_style( 'theme_style_print' );
		}
	}

	/**
	 * Scripts
	 *
	 * First, the FT wrappers link to their javascripts.
	 * After that, the javascript for the wrapper plugin is linked;
	 * finally comes the theme's javascript.
	 */
	function wp_enqueue_scripts() {
		// Link the javascript for the plugin
		if (file_exists(plugin_dir_path(__FILE__).'script.js')) {
			wp_enqueue_script('plugin_script',plugins_url('script.js', __FILE__),array('jquery'),CACHEBUSTER,true);
		}

		// Link the javascript for the theme
		if (file_exists(get_stylesheet_directory().'/script.js')) {
			wp_enqueue_script('theme_script',get_stylesheet_directory_uri().'/script.js',array('jquery'),CACHEBUSTER,true);
		}
	}

	// On install, queue a cron task for refreshing the wrapper HTML.
	function plugin_install() {

		// REVIEW: 2012.11.06: ADAM: Live wrappers are currently not stable enough to automatically update.
		// wp_schedule_event(time(), 'hourly', array($this, 'assanka_import_ftwrapper_html'));
	}

	// Tidy up on un-install.
	function plugin_uninstall() {
		delete_option('assanka_ftwrapper_options');
		remove_action('assanka_import_ftwrapper_html', array($this, 'import_ftwrapper_html'));

		// REVIEW: 2012.11.06: ADAM: Live wrappers are currently not stable enough to automatically update.
		// wp_clear_scheduled_hook(array($this, 'assanka_import_ftwrapper_html'));
	}

	function import_ftwrapper_html($ftwrapper_options = null) {
		$result = array();

		// $ftwrapper_options could be new values from $_POST; otherwise retrieve them from the DB.
		if (empty($ftwrapper_options)) {
			$ftwrapper_options = get_option('assanka_ftwrapper_options');
		}

		// Check ftwrapper_url
		if (empty($ftwrapper_options['ftwrapper_url'])) {
			$result['errors'][] = 'Error: Could not find the Wrapper URL value.';
			return $result;
		}
		$result['updates'][] = 'The wrapper URL value was found.';

		// Check the connection
		try {
			$opts = array('http' => array('method' => "GET", 'header' =>"Accept: */*\nUser-agent: FT Blogs wrapper download"));
			$context = stream_context_create($opts);
			$imported_html = file_get_contents($ftwrapper_options['ftwrapper_url'], false, $context);
			if (!$imported_html) {
				throw new Exception;
			}
		} catch(Exception $e) {
			$result['errors'][] = 'Error connecting to the wrapper URL. Please check the URL is valid.';
			return $result;
		}
		$result['updates'][] = 'Successfully connected to the wrapper URL.';

		// Check for content
		if (empty($imported_html)) {
			$result['errors'][] = 'Error loading the wrapper code. Please check that there is content at that URL.';
			return $result;
		}
		$result['updates'][] = 'The wrapper content was found.';

		// Check for placeholders
		foreach ($this->placeholders as $key => $placeholder) {
			// Force each 'use_placeholder' option to be either 1 or 'no'.
			if (empty($ftwrapper_options['use_placeholder_'.$key])) {
				$ftwrapper_options['use_placeholder_'.$key] = 'no';
			}

			$matches = null;
			preg_match($placeholder['pattern'], $imported_html, $matches);
			if (!empty($matches)) {
				$this->placeholders[$key]['found'] = 'match';
			} else {
				$this->placeholders[$key]['found'] = 'nomatch';

				if ($placeholder['required'] == true or $ftwrapper_options['use_placeholder_'.$key] == 1) {
					$result['errors'][] = $placeholder['error'].' <em>Pattern:</em> <code>'.htmlentities(trim(stripslashes($placeholder['pattern'])), ENT_QUOTES, 'UTF-8').'</code>';
				}
			}
		}

		// Only update HTML if there are no errors
		if (empty($result['errors'])) {
			if ($ftwrapper_options['override_resources'] == 1) $imported_html = $this->replace_wrapper_resources($imported_html);
			$ftwrapper_options['ftwrapper_html'] = $imported_html;
			$now = new DateTime(null, timezone_open('Europe/London'));
			$ftwrapper_options['lastSuccessfulImport'] = $now->format("Y-m-d H:i:s");
			$result['updates'][] = 'Wrapper saved.';
		}

		// If viewing the WP-Admin page, then this is not being called by the cron, so don't automatically save it.
		if(is_admin() and empty($_POST['info_update'])) return $result;

		// Save and return
		update_option('assanka_ftwrapper_options', $ftwrapper_options);
		return $result;
	}

	// Delete FT css/script resources from wrapper html
	function replace_wrapper_resources($html){

		// Prevent Warnings from badly formated HTML; preserve initial state
		$libxml_previous_state = libxml_use_internal_errors(true);
		$dom = new DOMDocument();
		$dom->loadHTML($html);

		// Clear warnings
		libxml_clear_errors();

		// Restore state
		libxml_use_internal_errors($libxml_previous_state);

		$scripts = $dom->getElementsByTagName('script');
		$styles = $dom->getElementsByTagName('link');

		// List of script srcs to be replaced
		$scriptsToReplace = array('/bundles/coreFoot.js','/navigation/ft/js/script.min.js' ,
		                        '/bundles/nonArticleFoot.js');

		// List of css srcs to be replaced
		$stylesToReplace = array('/bundles/core.css', '/navigation/ft/css/style.min.css', '/bundles/nonArticle.css');

		foreach($scripts as $item) {
			foreach ($scriptsToReplace as $needle){
				if(strpos($item->getAttribute('src'), $needle) !== false) {
					$filename = basename($needle);

					// Check if we have the file in the theme and replace the object
					if (file_exists(get_stylesheet_directory().'/wrapper-res/'.$filename)) {
						$newItem = $dom->createElement('script');
						$newItem->setAttribute('src', get_stylesheet_directory_uri().'/wrapper-res/'.$filename.'?ver='.CACHEBUSTER);
						if(is_object($item->parentNode)) $item->parentNode->replaceChild($newItem, $item);
					}
				}
			}
		}
		foreach($styles as $item) {
			foreach ($stylesToReplace as $needle){

				if(strpos($item->getAttribute('href'), $needle) !== false) {
					$filename = basename($needle);

					// Check if we have the file in the theme and replace the object
					if (file_exists(get_stylesheet_directory().'/wrapper-res/'.$filename)) {
						$newItem = $dom->createElement('link');
						$newItem->setAttribute('href', get_stylesheet_directory_uri().'/wrapper-res/'.$filename.'?ver='.CACHEBUSTER);
						$newItem->setAttribute('rel', 'stylesheet');
						$newItem->setAttribute('media', 'all');
						if(is_object($item->parentNode)) $item->parentNode->replaceChild($newItem, $item);
					}
				}
			}
		}

		$html = $dom->saveHTML();

		return $html;
	}

	// Display a link to the plugin's settings page in WP-Admin
	function create_options_menu_item() {
		if (function_exists('add_options_page')) {
			add_options_page('FT Wrappers', 'FT Wrappers', 9, __FILE__, array($this, 'options_page'));
		}
	}

	// Display the plugin's settings page in WP-Admin
	function options_page() {

		// Automatically import_ftwrapper_html on page load.
		$feedback = $this->import_ftwrapper_html($_POST['ftwrapper_options']);

		// Load the ftwrapper options_page
		$ftwrapper_options = get_option('assanka_ftwrapper_options');
		?>
		<div class="wrap">
			<form method="post">
				<?php
				// Echo feedback if appropriate
				if (!empty($_POST) and !empty($_POST ['ftwrapper_options'])) {
					if (empty($feedback['errors']) and !empty($feedback['updates'])and is_array($feedback['updates'])) {
						echo '<div id="setting-error-settings_updated" class="updated settings-error">';
						echo '<p><strong>Update succeeded</strong></p>';
						echo '<ul>';
						foreach ($feedback['updates'] as $update) {
							echo '<li>'.$update.'</li>';
						}
						echo '</ul>';
						echo '</div>';
					}
					if (!empty($feedback['errors']) and is_array($feedback['errors'])) {
						echo '<div id="setting-error-settings_update_failed" class="error settings-error">';
						echo '<p><strong>Update failed.</strong></p>';
						echo '<ul>';
						foreach ($feedback['errors'] as $error) {
							echo '<li>'.$error.'</li>';
						}
						echo '</ul>';
						echo '</div>';
					}
				}
				?>
				<h2>FT Wrappers</h2>

				<h3>Wrapper URL options</h3>
				<p>This plugin imports the HTML code from a page created outside of WordPress. You need to know the URL of the wrapper.</p>
				<table class="form-table">
					<tbody>
						<tr valign="top">
							<th scope="row">Wrapper URL</th>
							<td>
								<input style="width:500px;" type="text" name="ftwrapper_options[ftwrapper_url]" id="ftwrapper_url" value="<?php echo $ftwrapper_options['ftwrapper_url']; ?>" /><br>
								<span class="description">(e.g. http://www.ft.com/thirdpartywrapper/<?php echo str_replace('/','',$GLOBALS['path']); ?>)</span>
							</td>
						</tr>
						<tr valign="top">
							<th scope="row">Last successful import</th>
							<td>
								<?php echo empty($ftwrapper_options['lastSuccessfulImport'])? 'Never' : date("l F j, Y  H:i:s", strtotime($ftwrapper_options['lastSuccessfulImport'])); ?>
								<br>
								<span class="description">The wrapper code is automatically imported when you click "Update".</span>
							</td>
						</tr>
					</tbody>
				</table>

				<h3>Placeholders</h3>
				<p>
					The wrapper needs specific placeholder tags for WordPress to insert appropriate code. <br />
					<span class="description">Some page templates automatically ignore specific placeholders; for example, full-width pages don't include the side rail placeholder.</span>
				</p>
				<table class="form-table">
					<tbody>
						<?php foreach($this->placeholders as $key => $placeholder): ?>
						<tr valign="top" <?php if ($placeholder['found'] == 'match'): ?>style="color:green;"<?php elseif ($placeholder['found'] == 'nomatch'): ?>style="color:red;"<?php endif; ?>>
							<th scope="row"><strong><?php echo $placeholder['title'] ?></strong></th>
							<td>
								<?php if ($placeholder['required'] == true): ?>
									This placeholder will be replaced with appropriate content.
								<?php else: ?>
									<?php $selected =($ftwrapper_options['use_placeholder_'.$key] != 'no')? 'checked="checked"' : null; ?>
									<label for="use_placeholder_<?php echo $key; ?>"><input name="ftwrapper_options[use_placeholder_<?php echo $key; ?>]" type="checkbox" id="use_placeholder_<?php echo $key; ?>" value="1" <?php echo $selected; ?>> Replace this placeholder with appropriate content. (Optional)</label>
								<?php endif; ?>
							</td>
							<td>
								<?php echo($placeholder['found'] == 'match')? 'Placeholder match found.' : 'Placeholder not found in wrapper code.'; ?>
							</td>
							<td>
								<em>Placeholder pattern:</em> <code><?php echo htmlentities(trim(stripslashes($placeholder['pattern'])), ENT_QUOTES, 'UTF-8')?></code>
							</td>
						</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
				<script >
					function toggleOverrideOptions() {
						jQuery('.overrideResourcesOptions').toggle();
					}
				</script>
				<p class="showOverrideOptions">
					<a href="#" class="" onclick="toggleOverrideOptions(); return false;">More &raquo;</a>
				</p>
				<div class="overrideResourcesOptions" style="display: none;">
				<h3>Override FT wrapper styles & scripts</h3>
				<p style="color:red;">Do not use this option unless you have checked and confirmed
						that the desired resources have been downloaded into the theme's folder for this blog</p>
				<p>
					If you choose to override FT Wrapper resources, certain script and style imports will be removed
					from the Wrapper HTML and in place we will try loading local copies of them,
					which are expected to be manually downloaded in the blog's theme folder
					<strong>"theme-folder/wrapper-res/"</strong></p>
				<table class="form-table">
					<tbody>
					<tr valign="top">
						<th scope="row">Override wrapper styles and scripts</th>
						<td>
							<label for="override_resources"><input name="ftwrapper_options[override_resources]" type="checkbox" id="override_resources" value="1" <?php echo ($ftwrapper_options['override_resources'] == 1) ? 'checked="checked"':''; ?>></label>
						</td>
					</tr>
					</tbody>
				</table>
				</div>

				<div class="submit"><input type="submit" name="info_update" value="<?php _e('Update')?> &raquo;" /></div>
			</form>
		</div>
		<?php
	}

	/**
	 * Take the static FT Wrapper HTML template and replace its placeholders with dynamic WordPress content.
	 */
	function hijack_theme($wp) {
		if (is_admin() or $wp->request=='feed' or !empty($wp->query_vars['feed']) or !empty($wp->query_vars['robots'])) return false;
		if (!$ftwrapper_options = get_option('assanka_ftwrapper_options')or empty($ftwrapper_options['ftwrapper_html'])) return false;

		$patterns = array();
		$replacements = array();
		$expected_match_count = 0;

		// Let plugins dynamically alter the placeholders
		$this->placeholders = apply_filters('ftwrapper_placeholders', $this->placeholders);

		foreach ($this->placeholders as $key => $placeholder) {

			// If the placeholder is not required and has been deselected in settings, don't replace it.
			if ($placeholder['required'] == false and $ftwrapper_options['use_placeholder_'.$key] == 'no') continue;
			$patterns[] = $placeholder['pattern'];
			$template_code = null;

			// The replacement can be a string, or it can be a function call.
			if (is_array($placeholder['replacement'])) {
				if (!is_array($placeholder['replacement']['parameters'])) $placeholder['replacement']['parameters'] = array();

				// If it's a function call, it might be an object method.
				if (!empty($placeholder['replacement']['object']) and is_callable(array($placeholder['replacement']['object'], $placeholder['replacement']['function']))) {
					$template_code = call_user_func_array(array($placeholder['replacement']['object'], $placeholder['replacement']['function']), $placeholder['replacement']['parameters']);
				}

				// Or it might be a global function.
				elseif (is_callable($placeholder['replacement']['function'])) {
					$template_code = call_user_func_array($placeholder['replacement']['function'], $placeholder['replacement']['parameters']);
				}

				$replacement_code  = PHP_EOL.PHP_EOL.'<!-- Replaced: '.$placeholder['title'].' -->'.PHP_EOL;
				$replacement_code .= $template_code;
				$replacement_code .= PHP_EOL .'<!-- End of replaced: '.$placeholder['title'].' -->' .PHP_EOL.PHP_EOL;
				$replacements[] = preg_replace('/(?<!\\\\)\\$/', '\\\\$', $replacement_code);
			}

			// In this case the replacement content's not coming from a function call, so it must be a string.
			else {
				$replacements[] = preg_replace('/(?<!\\\\)\\$/', '\\\\$', $placeholder['replacement']);
			}

			$expected_match_count++;
		}

		$output = preg_replace($patterns, $replacements, $ftwrapper_options['ftwrapper_html'], 1, $returned_match_count);
		if ($returned_match_count == $expected_match_count) {
			ob_end_clean();
			die($output);
		}
		return false;
	}

	function hook_plugin_action_links( $links, $file ) {
        if (basename($file) == basename(__FILE__)) {
			$settings_link = '<a href="options-general.php?page=assanka_ftwrappers/assanka_ftwrappers.php">Settings</a>';
			array_unshift( $links, $settings_link );
		}
		return $links;
	}

	/**
	 * The following methods return replacement content for placeholders.
	 */

	// Output some code in the document footer
	function output_footer_code() {
		echo '<!-- Revision @@deploy_version@@ -->';
	}

	// WordPress functions like wp_head()and wp_footer()echo content when executed, but we want the content as a variable instead.
	function load_wordpress_function($name = null, $arguments = null) {
		$buffer = ob_get_contents();
		ob_end_clean();
		ob_start();
		call_user_func($name, $arguments);
		$result = ob_get_contents();
		ob_end_clean();
		echo $buffer;
		return $result;
	}

	// WordPress templates echo content when executed, but we want the content as a variable instead.
	function template_loader() {
		$template_loader_file = $_SERVER['DOCUMENT_ROOT'].'/wp-includes/template-loader.php';
		if (!file_exists($template_loader_file)) {
			return false;
		}
		$buffer = ob_get_contents();
		ob_end_clean();
		ob_start();
		require_once $template_loader_file;
		$result = ob_get_contents();
		ob_end_clean();
		echo $buffer;
		return $result;
	}
}
$assanka_ftwrapper = new Assanka_FTwrapper();


