<?php
/**
 * The template for displaying 404 pages (Not Found).
 */
?>

<?php get_header(); ?>

<div class="primary-content" role="main">
	<div class="hentry">
		<h1 class="entry-title"><?php _e( 'Sorry.', 'falcon' ); ?></h1>
		<div class="entry-content">
			<h3><?php _e( 'The page you requested could not be found.', 'falcon' ); ?></h3>
			<p><a href="/"><?php _e( 'Click here to go to the FT Blogs home page.', 'falcon' ); ?></a></p>
		</div><!-- .entry-content -->
	</div><!-- #post-## -->
</div><!-- primary-content -->

<?php get_footer(); ?>