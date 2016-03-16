<?php
/**
 * The template for displaying Author Archive pages.
 */


get_header(); ?>

<div class="master-row contentSection ">
	<?php echo assanka_show_tabbed_menu(); // Display a tabbed page/category menu. ?>


<?php
	/* Queue the first post, that way we know who
	 * the author is when we try to get their name,
	 * URL, description, avatar, etc.
	 *
	 * We reset this later so we can run the loop
	 * properly with a call to rewind_posts().
	 */
	if ( have_posts() )
		the_post();

		// If a user has filled out their description, show a bio on their entries.
		if ( get_the_author_meta( 'description' ) ) : ?>
			<div id="entry-author-info" class="authordetails clearfix">
				<?php 
				$em_headshoturl_lrg = get_the_author_meta('_em_headshoturl_lrg');
				if (!empty($em_headshoturl_lrg)): 
				?>
				<div class="authorimage">
					<img src="<?php the_author_meta('_em_headshoturl_lrg'); ?>" alt="<?php printf( __( 'Image of %s', 'falcon' ), get_the_author() ); ?>" />
				</div>
				<?php endif; ?>
				<div id="author-description">
					<h2><?php printf( __( '%s', 'falcon' ), get_the_author() ); ?></h2>
					<?php the_author_meta( 'description' ); ?>
				</div><!-- #author-description	-->
			</div><!-- #entry-author-info -->
		<?php 
		endif; 
		
	/* Since we called the_post() above, we need to
	 * rewind the loop back to the beginning that way
	 * we can run the loop properly, in full.
	 */
	rewind_posts();

	/* Run the loop for the author archive page to output the authors posts
	 * If you want to overload this in a child theme then include a file
	 * called loop-author.php and that will be used instead.
	 */
	 get_template_part( 'loop', 'author' );
?>

</div><?php // master-row contentSection ?>

<?php get_sidebar(); ?>
<?php get_footer(); ?>
