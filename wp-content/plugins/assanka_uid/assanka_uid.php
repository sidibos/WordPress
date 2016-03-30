<?php
/*
Plugin Name: Assanka UID
Description: Generate Type 3 UUIDs for blog posts and custom-format UIDs for blogs.
Plugin URI: http://assanka.net/
Author: Assanka
Version: 1.0
Author URI: http://assanka.net/
*/

/**
 * README
 *
 * Example code to get the blog and post UIDs in different plugins:
 *
 * 	if (class_exists('Assanka_UID')){
 *
 * 		// $blog_id is optional; it defaults to the ID of the current blog.
 *		$blog_uid = Assanka_UID::get_the_blog_uid($blog_id);
 *
 *		// $post_id is optional; it defaults to the ID of the current post.
 *		$blog_post_uid = Assanka_UID::get_the_post_uid($post_id);
 *	}
 */

class Assanka_UID  {

	function __construct() {
		// Scripts
		add_action('wp_print_scripts',array(&$this, 'get_uuid_var'));
	}

	/**
	 * Return a blog_post_uid
	 */
	public static function get_the_blog_uid($blog_id=null){
		$blog_uid = get_option('blog_uid');
		if (!empty($blog_uid)) return $blog_uid;

		if (empty($blog_id)) $blog_id = get_current_blog_id();
		$blog_uid_parts = array(
			'namespace'  => 'FT',
			'ownership'  => 'LABS',
			'associated' => 'WP',
			'instance'   => 1,
			'blog_id'    => $blog_id,
		);
		$blog_uid_parts = apply_filters('blog_uid_parts', $blog_uid_parts);
		$blog_uid = implode('-', $blog_uid_parts);

		add_option('blog_uid',$blog_uid);
		return $blog_uid;
	}

	/**
	 * Return a blog_post_uid
	 */
	public static function get_the_post_uid($post_id=null){
		if (empty($post_id)) $post_id = get_the_ID();

		// Note the underscore prepending '_blog_post_uid'.
		$blog_post_uid = get_post_meta('_blog_post_uid', $post_id, true);
		if (!empty($blog_post_uid)) return $blog_post_uid;

		/**
		 * Using the "Name string is a URL" namespace from RFC 4211
		 * http://www.ietf.org/rfc/rfc4122.txt (Appendix C)
		 */
		$post_uid_namespace = '6ba7b811-9dad-11d1-80b4-00c04fd430c8';
		$post_uid_namespace = apply_filters('post_uid_namespace', $post_uid_namespace);

		$blog_post_uid = self::get_v3_uuid($post_uid_namespace, get_the_guid($post_id));
		add_post_meta($post_id, '_blog_post_uid', $blog_post_uid, true);
		return $blog_post_uid;
	}

	/**
	 * Return a blog_post_uid in javascript variable
	 */
	function get_uuid_var() {
		if(is_single()){
 		?>
	<script type="text/javascript">
		/* <![CDATA[ */
		var FT = FT || {};
		FT.page = FT.page || {};
		FT.page.metadata = FT.page.metadata || {};
		FT.page.metadata.articleUuid = '<?php echo self::get_the_post_uid(); ?>';

		FT.isPage = FT.isPage || {};
		FT.isPage.Story = 1;
		window.articleUUID = '<?php echo self::get_the_post_uid(); ?>';
		window.pageUUID = '<?php echo self::get_the_post_uid(); ?>';
		/* ]]> */
	</script>
		<?php
		}
	}

	/**
	 * UUID generator
	 *
	 * The following function generates VALID RFC 4211 COMPLIANT
	 * Universally Unique IDentifiers (UUID version 3).
	 *
	 * @author Andrew Moore
	 * @link http://www.php.net/manual/en/function.uniqid.php#94959
	 */
	public static function get_v3_uuid($namespace, $name) {
		if(!self::is_valid($namespace)) return false;

		// Get hexadecimal components of namespace
		$nhex = str_replace(array('-','{','}'), '', $namespace);

		// Binary Value
		$nstr = '';

		// Convert Namespace UUID to bits
		for($i = 0; $i < strlen($nhex); $i+=2) {
			$nstr .= chr(hexdec($nhex[$i].$nhex[$i+1]));
		}

		// Calculate hash value
		$hash = md5($nstr . $name);

		return sprintf('%08s-%04s-%04x-%04x-%12s',

			// 32 bits for "time_low"
			substr($hash, 0, 8),

			// 16 bits for "time_mid"
			substr($hash, 8, 4),

			// 16 bits for "time_hi_and_version",
			// four most significant bits holds version number 3
			(hexdec(substr($hash, 12, 4)) & 0x0fff) | 0x3000,

			// 16 bits, 8 bits for "clk_seq_hi_res",
			// 8 bits for "clk_seq_low",
			// two most significant bits holds zero and one for variant DCE1.1
			(hexdec(substr($hash, 16, 4)) & 0x3fff) | 0x8000,

			// 48 bits for "node"
			substr($hash, 20, 12)
		);
	}

	public static function is_valid($uuid) {
		return preg_match('/^\{?[0-9a-f]{8}\-?[0-9a-f]{4}\-?[0-9a-f]{4}\-?'.
		'[0-9a-f]{4}\-?[0-9a-f]{12}\}?$/i', $uuid) === 1;
	}
}
new Assanka_UID;
