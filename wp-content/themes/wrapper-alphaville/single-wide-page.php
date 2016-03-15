<?php
/**
 * Template Name: One column, no sidebar
 *
 * A custom page template without sidebar. Note: This is for pages, not posts.
 */
?>
<div class="primary-container">
	<div class="page-columns-container clearfix">
		<div class="primary-content page-content single" role="main">
			<?php if (have_posts()) : the_post(); ?>
				<div id="post-<?php the_ID(); ?>" <?php post_class('clearfix'); ?>>
					<?php get_template_part('content-post'); ?>
				</div>
			<?php endif; ?>
		</div><!-- primary-content -->
		<?php
		// The wide-page template does not display the sidebar.
		unregister_sidebar( 'sidebar-1' );
		?>
		<?php get_footer(); ?>
	</div>
</div><!-- primary-container -->
