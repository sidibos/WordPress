<?php
/**
 * The template for displaying Author Archive pages.
 */
?>

<?php get_header(); ?>

<div class="primary-content" role="main">
	<?php if (have_posts()) : the_post(); ?>

		<?php
		// If biographies are enabled in theme settings, and
		// the user has filled out their description, show a bio on their entries:
		$theme_options = get_option('theme_options');
		if ( $theme_options['author_biographies'] == 1 && get_the_author_meta( 'description' ) ) :
		?>
		<div id="entry-author-info" class="authordetails clearfix">
			<?php
			$em_headshoturl_lrg = get_the_author_meta('_em_headshoturl_lrg');
			if (!empty($em_headshoturl_lrg)):
			?>
			<div class="authorimage">
				<img src="<?php the_author_meta('_em_headshoturl_lrg'); ?>" alt="<?php printf( __( 'Image of %s', 'falcon' ), get_the_author() ); ?>" height="120" width="120" />
			</div>
			<?php endif; ?>
			<div id="author-description">
				<h2><?php printf( __( '%s', 'falcon' ), get_the_author() ); ?></h2>
				<?php the_author_meta( 'description' ); ?>
			</div><!-- #author-description	-->
		</div><!-- #entry-author-info -->
		<?php endif; ?>

		<?php
		// Since we called the_post() above, we need to rewind.
		rewind_posts();
		?>

		<?php get_template_part( 'loop', 'author' ); ?>

	<?php endif; ?>
</div><!-- primary-content -->

<?php get_footer(); ?>