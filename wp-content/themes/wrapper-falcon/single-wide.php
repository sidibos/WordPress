<?php
/*
Template Name Posts: Wide Column Template
The Template for displaying single posts in wide-column format.
*/
?>

<?php get_header(); ?>

<?php do_action( 'show_top_stories' ); ?>

<div class="primary-content single single-wide" role="main" style="width: 972px; background: #FFF1E0;">
	<?php if (have_posts()) : the_post(); ?>

	<div id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
		<h2 class="entry-title">
			<?php assanka_show_post_byline(); // Displays an author headshot or category/country flag. ?>
			<?php the_title(); ?>
		</h2>

		<?php get_template_part('entry-header'); ?>

		<div class="entry-content">
			<?php the_content(); ?>
			<?php wp_link_pages( array( 'before' => '<div class="page-link">' . __( 'Pages:', 'falcon' ), 'after' => '</div>' ) ); ?>
		</div><!-- .entry-content -->

		<div class="entry-utility entry-meta clearfix">
			<div class="falcon-posted-in">
				<?php falcon_posted_in(); ?>
			</div>

			<div class="post-actions">
				<?php echo post_actions(); ?>
			</div>
		</div><!-- .entry-utility -->

		<!-- Placeholder div for "recommended-reads" -->
		<div id="ft-story-tools-bottom"></div>

	</div><!-- #post-## -->

	<?php comments_template( '', true ); ?>

	<?php endif; ?>
</div><!-- primary-content -->

<?php
// The wide-post template does not display the sidebar.

// TODO: Adam: 2012.11.01: Ensure this works.
unregister_sidebar( 'sidebar-1' );
?>

<?php get_footer(); ?>
