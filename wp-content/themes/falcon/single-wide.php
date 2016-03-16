<?php
/*
Template Name Posts: Wide Column Template
The Template for displaying all single posts in wide-column format.
*/
?>

<?php get_header(); ?>

<div class="master-row contentSection single wide-column" style="width: 100% !important;">

	<div class="fullstory">
		<div class="post-actions" id="falcon-story-tools-top">
			<?php global $blog_id; echo falcon_postActions($post->ID, $blog_id)?>
		</div>
	</div>

	<?php echo assanka_show_tabbed_menu(); // Display a tabbed page/category menu. ?>

	<?php if ( have_posts() ) while ( have_posts() ) : the_post(); ?>

	<div id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
		<h2 class="entry-title"><?php the_title(); ?></h2>

		<?php falcon_entry_meta(); ?>

		<div class="entry-content">
			<?php the_content(); ?>
			<?php wp_link_pages( array( 'before' => '<div class="page-link">' . __( 'Pages:', 'falcon' ), 'after' => '</div>' ) ); ?>
		</div><!-- .entry-content -->

		<div class="entry-utility entry-meta clearfix">
			<div class="falcon-posted-in">
				<?php falcon_posted_in(); ?>
				<?php edit_post_link( __( 'Edit', 'falcon' ), ' | <span class="edit-link">', '</span>' ); ?>
			</div>
			<div class="fullstory">
				<div class="post-actions" id="falcon-story-tools-bottom">
					<?php global $blog_id; echo falcon_postActions($post->ID, $blog_id)?>
				</div>
			</div>
		</div><!-- .entry-utility -->

	</div><!-- #post-## -->

	<?php comments_template( '', true ); ?>

	<?php endwhile; // end of the loop. ?>

</div><!-- .master-row contentSection -->

<?php
// The wide-post template does not display the sidebar.
unregister_sidebar( 'sidebar-1' );
get_footer();

