<?php
/**
 * The template for displaying Archive pages.
 */
?>

<?php get_header(); ?>

<div class="primary-content" role="main">
	<?php if (have_posts()) : the_post(); ?>

		<h1 class="page-title">
		<?php if ( is_day() ) : ?>
		<?php printf( __( 'Daily Archives: <span>%s</span>', 'falcon' ), get_the_date() ); ?>
		<?php elseif ( is_month() ) : ?>
		<?php printf( __( 'Monthly Archives: <span>%s</span>', 'falcon' ), get_the_date('F Y') ); ?>
		<?php elseif ( is_year() ) : ?>
		<?php printf( __( 'Yearly Archives: <span>%s</span>', 'falcon' ), get_the_date('Y') ); ?>
		<?php else : ?>
		<?php _e( 'Blog Archives', 'falcon' ); ?>
		<?php endif; ?>
		</h1>

		<?php
		// Since we called the_post() above, we need to rewind.
		rewind_posts();
		?>

		<?php get_template_part( 'loop', 'archive' ); ?>

	<?php endif; ?>
</div><!-- primary-content -->

<?php get_footer(); ?>