<?php
/**
 * The Template for displaying all single posts.
 */
?>

<div class="primary-container">
	<div class="page-columns-container clearfix">
		<div class="primary-content page-content single" role="main">
			<div class="inner">
				<?php if (have_posts()) : the_post(); ?>
					<div id="post-<?php the_ID(); ?>" <?php post_class('clearfix'); ?>>
						<?php get_template_part('content-post'); ?>
					</div>
				<?php endif; ?>
			</div>
		</div><!-- primary-content -->
		<?php get_footer(); ?>
	</div>
</div><!-- primary-container -->
