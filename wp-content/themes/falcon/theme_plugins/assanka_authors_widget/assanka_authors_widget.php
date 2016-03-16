<?php
/*
Plugin Name: Authors Widget Plugin
Plugin URI: http://blogs.ft.com
Description: Widget to display a list of authors by category in the sidebar, and link to author pages, which get an extra header with a large headshot and bio.
Author: Assanka
Version: 1.0
Author URI: http://assanka.net/
*/
class Assanka_AuthorsWidget extends WP_Widget{
	public function __construct() {
		$control_ops = array("width" => "450px");
		$widget_ops = array("classname"=>"assanka_authors_widget","description"=>"Displays a list of authors by category");
        parent::__construct("assanka_authors_widget", "Assanka Authors Widget", $widget_ops, $control_ops);
	}
    function update($new_instance, $old_instance) {
		$instance = $old_instance;
		$instance['title'] = strip_tags($new_instance['title']);
		$newoptions = explode(',', $new_instance["authors"]);
		$instance['authors'] = array();
		foreach($newoptions as $name) {
			if(username_exists(trim($name))) {
				$instance['authors'][] = trim($name);
			}
		}
        return $instance;
    }
    function form($instance) {
		?>
		<table>
		<tr>
			<td><label for="<?php echo $this->get_field_id('title'); ?>">Title</label></td>
			<td><input style="width: 350px;" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" type="text" value="<? echo esc_attr($instance["title"]); ?>" />
			</td>
		</tr>
		<tr><td colspan=2>Add login names as a comma separated list. Authors will be divided up according to the regions listed on their profile page</td></tr>
		<tr>
			<td><label for="<?php echo $this->get_field_id('authors'); ?>">Authors</label></td>
			<td><input style="width: 350px;" id="<?php echo $this->get_field_id('authors'); ?>" name="<?php echo $this->get_field_name('authors'); ?>" type="text" value="<? if(is_array($instance["authors"]))echo esc_attr(implode(', ', $instance["authors"])); ?>" />
			</td>
		</tr>
		</table>
		<?php
	}

    function widget($args, $instance) {
		if (!isset($instance["title"]) or !is_array($instance["authors"])) return;
		extract($args);
		$title = apply_filters('widget_title', $instance['title']);
		// If there is no title, do not add classes that trigger display of the top border and toggle button.
		// Regions are defined by the parent categories
		$regions = get_categories(Array('parent'=>'0', 'hide_empty'=>0));

		// Authorsinregions is how we will represent the team: [category][user_id][userdata]
		$authorsinregions = array();

		// Put authors into groups.
		foreach($instance['authors'] as $a) {
			// Does not return enough ifo, so we need to use the ID to make a second call...
			$dat = get_userdatabylogin($a);

			// Get user data - returns basic info and usermeta
			$userdata = get_userdata($dat->ID);

			$thumb = $userdata->_em_headshoturl_thumb;
			$cats = $userdata->_em_interest_regions;

			// Only users who have written posts are to be included
			if (get_posts(Array('post_author'=> $dat->ID))) {

				// If the user has interest categotries put them in the relevant position in the array
				if (!empty($cats)) {
					foreach($cats as $cat) {
						$authorsinregions[get_cat_name($cat)][$dat->ID] = Array('name'=>$dat->display_name,'thumb_src'=>$thumb, 'urlname'=>$dat->user_nicename);
					}
				} else {
					// If the user is not in any categories, this means they are part of the 'Global team', so should be put in this group
					$authorsinregions['global'][$dat->ID] = Array('name'=>$dat->display_name,'thumb_src'=>$thumb, 'urlname'=>$dat->user_nicename);
				}
			}
		}


		/* Generate area specific teams */

		$regionlisthtml = Array();

		// Initialise with global panel
		$authorpanelshtml = '<div id="panelregion_global" class="author_panel first"><ul>';

		$region = $authorsinregions['global'];
		$needspad = true;
		if (!empty($region)){
			$needspad = false;
			foreach($region as $key => $value) {

				$authorpanelshtml .= '<li><a href="'.get_bloginfo('url').'/author/'.$value['urlname'].'/">';

				// If a thumbnail value is found then put it in, otherwise add a transparent filler
				if($value['thumb_src'] !='') {
					$authorpanelshtml .= '<img src="'.$value['thumb_src'].'" />';
				} else {
					$authorpanelshtml .= '<img src="/wp-content/themes/threecolblognews/img/transparentspacer.gif" height="20"/>';
				}

				$authorpanelshtml .= $value['name'];
				$authorpanelshtml .= '</a></li>';
				$needspad = !$needspad;
			}
			if($needspad)$authorpanelshtml .= '<li class="_em_pad"></li>';
		}
		$authorpanelshtml .= '</ul></div>';

		foreach($regions as $region => $regionobj) {
			// Ignore the Uncategorized category
			if ($regionobj->name != 'Uncategorized') {

				$region = $authorsinregions[$regionobj->name];

				// Only add regions for which there are posts and authors
				if (!empty($region)){

					$authorpanelshtml .= '<div id="panelregion_'.$regionobj->term_id.'" class="author_panel hidden"><ul>';

					// Padding for the event that there is an odd number of authors
					$needspad = false;
					foreach($region as $key => $value) {
						$authorpanelshtml .= '<li><a href="'.get_bloginfo('url').'/author/'.$value['urlname'].'/"';
						$authorpanelshtml .= '">';
						if($value['thumb_src'] !='') {
							$authorpanelshtml .= '<img src="'.$value['thumb_src'].'" />';
						} else {
							$authorpanelshtml .= '<img src="/wp-content/themes/threecolblognews/img/transparentspacer.gif" height="20"/>';
						}
						$authorpanelshtml .= $value['name'];
						$authorpanelshtml .= '</a></li>';
						$needspad = !$needspad;
					}
					if($needspad)$authorpanelshtml .= '<li class="_em_pad"></li>';

				// Add link to region panel
				if ($regionobj->name !='Other') $regionlisthtml[] = '<li class="region" id="region_'.$regionobj->term_id.'"><a>'.	$regionobj->name.'</a></li>';
				$authorpanelshtml .= '</ul><div class="heightlessclearer"></div></div>';

				}
			}
		}

		$regionlisthtml = implode('<li>|</li>', $regionlisthtml);
		$regionlisthtml = '<ul class="clearfix"><li class="region selected" id="region_global"><a>bb team</a></li> <li>|</li> '.$regionlisthtml;
		$regionlisthtml .= '</ul>';
		echo !empty($title)?$before_widget:NULL;
		echo !empty($title)?$before_title.$title.$after_title:NULL;
		?>
			<div id="author_regions">
					<h4>Regional teams:</h4>
					<?php echo $regionlisthtml ?>
				</div>
			<script type="text/javascript" src="<?php echo get_option("siteurl").str_replace($_SERVER['DOCUMENT_ROOT'], "", dirname(__FILE__)); ?>/authorswidget.js"></script>
				<?php echo $authorpanelshtml; ?>
			<div class="heightlessclearer"></div>
		<?php

		echo !empty($title)?$after_widget:NULL;
	}
}

class Assanka_Authors {

	function __construct(){

		// Add actions to the user profile to include author images
		add_action('edit_user_profile', array(&$this, 'authors'), 20);
		add_action('show_user_profile', array(&$this, 'authors') ,20);

		add_action('user_profile_update_errors', array(&$this, 'checkuseredit'),1);
		add_filter('edit_user_profile_save', array(&$this, 'saveuseredit'));

		// Register hook for adding all of the country categories
		register_activation_hook(__FILE__, array(&$this, '_em_add_cats'));
		add_action('widgets_init', create_function('', 'return register_widget("Assanka_AuthorsWidget");'));
	}

	function authors() {
		global $user_id;

		/* Generate form */

		/* Headshots rows */
		$fullheadrow = '<tr>
						<th scope="row">Large Headshot URL</th>
						<td>
							<input id="_em_headshoturl_lrg" type="text" name="_em_headshoturl_lrg" value="'.get_usermeta($user_id, "_em_headshoturl_lrg").'" style="width:500px" /><br />If a valid URL is provided, the image will be displayed on the author page. Images should be 120px wide by 120px high.
						</td>';

		if(get_usermeta($user_id, "_em_headshoturl_lrg")) {
			$fullheadrow .= '<td><img src="'.get_usermeta($user_id, "_em_headshoturl_lrg").'"/></td>';
		} else {
			$fullheadrow .= '<td></td>';
		}
		$fullheadrow .=	'</tr>';

		$thumbheadrow = '<tr>
						<th scope="row">Thumbnail Headshot URL</th>
						<td>
							<input id="_em_headshoturl_thumb" type="text" name="_em_headshoturl_thumb" value="'.get_usermeta($user_id, "_em_headshoturl_thumb").'" style="width:500px" /><br />If a valid URL is provided, the image will be displayed on the author widget on the index page. Images should be 20px wide by 20px high.
						</td>';
		if(get_usermeta($user_id, "_em_headshoturl_lrg")) {
			$thumbheadrow .= '<td><img src="'.get_usermeta($user_id, "_em_headshoturl_thumb").'"/></td>';
		} else {
			$thumbheadrow .= '<td></td>';
		}

		$thumbheadrow .= '</tr>';

		// Get categories for form
		$cats = get_categories(Array('parent'=>'0', 'hide_empty'=>0));

		$regionsrow ='<tr><th scope="row">Regions</th><td>';
		foreach ($cats as $catid =>$catobj) {
			if ($catobj->name != 'Uncategorized') {
				$interest_regions = get_usermeta( $user_id, '_em_interest_regions');
				if(!empty($interest_regions) and in_array($catobj->term_id, $interest_regions)) {
					$checked = 'checked';
					$checkedtf = 'true';
				} else {
					$checked = 'unchecked';
					$checkedtf = 'false';
				}

				$regionsrow .= '<label for="_em_interest_regions'. $catobj->term_id .'">';
				$regionsrow .= '<input name="_em_interest_regions'.$catobj->term_id .'" id="_em_interest_regions'.$catobj->term_id .'" type="checkbox" value="'.$checkedtf.'" check="'.$checked.'" '.$checked.' />';
				$regionsrow .= '&nbsp;'. $catobj->name .'</label>';
				$regionsrow .= '<br/>';
			}

		}
		$regionsrow .= '</td><td></td></tr>';

		// Output form
		echo('<h3>Author data</h3>
			  <table class="form-table">');
		echo($fullheadrow);
		echo($thumbheadrow);
		echo($regionsrow);
		echo('</table>');
	}

	// Check a submitted headshot
	function checkuseredit($errors) {
		$urlhead = trim($_POST["_em_headshoturl_lrg"]);
		$urlthumb = trim($_POST["_em_headshoturl_thumb"]);

		if($urlhead) $errors =  $this->checkurl($urlhead, '_em_headshoturl_lrg', $errors, 120, 120);
		if($urlthumb)$errors = $this->checkurl($urlthumb, '_em_headshoturl_thumb', $errors, 20, 20);

		return ($errors);
	}


	function checkurl($url, $name, $errors, $height, $width)	{
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

		return $errors;
	}

	// Save a user edit
	function saveuseredit() {
		global $user_id;

		$urlhead = trim($_POST["_em_headshoturl_lrg"]);
		$urlthumb = trim($_POST["_em_headshoturl_thumb"]);

		$cats = get_categories(Array('parent'=>'0', 'hide_empty'=>0));

		$foundcats = array();

		foreach($cats as $cat => $catobj) {
			if ($catobj->name != 'Uncategorized') {
				if ($_POST['_em_interest_regions'.$catobj->term_id]) {
					$foundcats[] = $catobj->term_id;
				}
			}
		}

		if ( empty ($foundcats))
			delete_usermeta($user_id, '_em_interest_regions');
		else {
			update_usermeta($user_id, '_em_interest_regions', $foundcats);
		}

		// Only save headshot URL if one was actually supplied
		if ($urlhead) {

			// Add http:// to URL if it is missing
			$urlparts = parse_url($urlhead);
			if (!$urlparts["scheme"]) {
				$urlhead = "http://".$urlhead;
			}

			// Save URL to database
			update_usermeta($user_id, "_em_headshoturl_lrg", $urlhead);

		// If no headshot URL was supplied, remove any existing URL from the database
		} else {
			delete_usermeta($user_id, "_em_headshoturl_lrg");
		}

		// Only save headshot URL if one was actually supplied
		if ($urlthumb) {
			// Add http:// to URL if it is missing
			$urlparts = parse_url($urlthumb);
			if (!$urlparts["scheme"]) {
				$urlthumb = "http://".$urlthumb;
			}

			// Save URL to database
			update_usermeta($user_id, "_em_headshoturl_thumb", $urlthumb);

		// If no headshot URL was supplied, remove any existing URL from the database
		} else {
			delete_usermeta($user_id, "_em_headshoturl_thumb");
		}
	}
}
new Assanka_Authors;
