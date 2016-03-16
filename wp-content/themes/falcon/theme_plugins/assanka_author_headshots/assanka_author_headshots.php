<?php
/*
Plugin Name: Assanka Emerging markets headshots widget
Description: When editing a user, this plugin displays extra form fields for author headshots.
Plugin URI: http://blogs.ft.com
Author: Assanka
Version: 1.0
Author URI: http://assanka.net/
*/

class Assanka_Headshots {

	function __construct() {
		add_action('show_user_profile',          array(&$this, 'outputuseredit'));
		add_action('edit_user_profile',          array(&$this, 'outputuseredit'));
		add_action('user_profile_update_errors', array(&$this, 'checkuseredit'),1);
		add_filter('edit_user_profile_save',     array(&$this, 'saveuseredit'),1);
	}

	// Output additional form elements to allow picture byline support, by adding a URL to link to a user picture
	function outputuseredit() {
		global $user_id;
		?>
		<h3>Author Byline</h3>
		<table class="form-table">
			<tbody>
				<tr>
					<th>
						<label for="_ftblogs_headshoturl">Headshot URL</label>
					</th>
					<td>
						<input id="_ftblogs_headshoturl" type="text" name="_ftblogs_headshoturl" value="<?php echo get_user_meta($user_id, '_ftblogs_headshoturl', true); ?>" style="width:500px" /><br />
						Enter a valid URL for the author headshot image. It is displayed as part of the author byline, next to post titles. Images should be 35px wide by 45px high.
						<?php
						// If author headshots are not enabled, highlight the notice.
						$theme_options = get_option('theme_options');
						if ( (empty($theme_options['byline_posts']) || $theme_options['byline_posts'] != 2) && (empty($theme_options['byline_pages']) || $theme_options['byline_pages'] != 2)) {
							$class = 'error';
						}
						?>
						<br/><span class="<?php echo $class; ?>">You can enable/disable author bylines in the <strong>Theme Settings</strong> admin page.</span>
					</td>
				</tr>
			</tbody>
		</table>
		<?php
	}


	// Check a submitted headshot
	function checkuseredit($errors) {

		$name = "_ftblogs_headshoturl";
		$url = trim($_POST[$name]);

		// Only check the URL if one was actually supplied
		if ($url) {

			// Add http:// to the URL if it is missing
			$urlparts = parse_url($url);
			if (!$urlparts["scheme"]) {
				$url = "http://".$url;
			}


			// Check file exists:
			require_once $_SERVER["CORE_PATH"]."/helpers/http/HTTPRequest";
			$http = new HTTPRequest($url);
			$http->setTimelimit(30);
			$success = false;
			try {
				$resp = $http->send();
				if ($resp->getResponseStatusCode() !== 200) {
					$log = "Can't download (HTTP ".$resp->getResponseStatusCode().")\n";
				} else {
					$imstring = $resp->getBody();
					$success = true;
				}
			} catch (Exception $e) {
				$log = "Can't download (".$e->getMessage().")\n";
			}

			if ($success) {

				$width = 35;
				$height = 45;

				// Check image dimensions:
				$im = @imagecreatefromstring($imstring);
				if ($im) $imagesize = array(imagesx($im), imagesy($im));

				// Error: could not get image size
				if ($imagesize == false) {
					$errors->add($name, "<strong>ERROR</strong>: The headshot URL you supplied does not point to a valid image", array("form-field" => $name));

				// Error: incorrect image dimensions
				} elseif ($imagesize[0] != $width or $imagesize[1] != $height) {
					$errors->add($name, "<strong>ERROR</strong>: The headshot URL you supplied points to an image that has the wrong dimensions.  Headshot images should be precisely ".$width." pixels wide by ".$height." pixels tall.", array("form-field" => $name));

				// No error: success!
				} else {

					// Do nothing
				}

			// Error: could not load image
 			} else {
				$errors->add($name, "<strong>ERROR</strong>: The headshot URL you supplied could not be loaded (".$url." - ".$log.")", array("form-field" => $name));
 			}

		}
		return $errors;
	}

	// Save a user edit
	function saveuseredit($userid) {

		$url = trim($_POST["_ftblogs_headshoturl"]);

		// Only save headshot URL if one was actually supplied
		if ($url) {

			// Add http:// to URL if it is missing
			$urlparts = parse_url($url);
			if (!$urlparts["scheme"]) {
				$url = "http://".$url;
			}

			// Save URL to database
			update_usermeta($userid, "_ftblogs_headshoturl", $url);

		// If no headshot URL was supplied, remove any existing URL from the database
		} else {
			delete_usermeta($userid, "_ftblogs_headshoturl");
		}
	}

}
new Assanka_Headshots;
