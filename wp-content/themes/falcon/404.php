<?php
/**
 * The template for displaying 404 pages (Not Found).
 *
 * @package WordPress
 * @subpackage Twenty_Ten
 * @since Falcon 1.0
 */

get_header(); ?>

<div class="master-row contentSection ">
	<?php echo assanka_show_tabbed_menu(); // Display a tabbed page/category menu. ?>

			<div id="post-0" class="post error404 not-found">
				<h2 class="entry-title"><?php _e( 'Sorry.', 'falcon' ); ?></h2>
				<div class="entry-content">
					<h4><?php _e( 'The page you requested could not be found.', 'falcon' ); ?></h4>
					
					<?php global $blog_id; if($blog_id > 1) : ?>
					<p><?php _e( 'Perhaps searching will help.', 'falcon' ); ?> <a href="/"><?php _e( 'Or, click here to return to the home page.', 'falcon' ); ?></a></p>
					<?php get_search_form(); ?>
					<?php else: ?>
					<p><a href="/"><?php _e( 'Click here to go to the FT Blogs home page.', 'falcon' ); ?></a></p>
					<?php endif; ?>

				</div><!-- .entry-content -->
			</div><!-- #post-0 -->
</div><?php // master-row contentSection ?>


	<script type="text/javascript">
		// focus on search field after it has loaded
		document.getElementById('s') && document.getElementById('s').focus();
	</script>

<?php get_footer(); ?>