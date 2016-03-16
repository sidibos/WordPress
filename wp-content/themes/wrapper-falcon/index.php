<?php
/**
 * The main template file.
 */
?>

<?php get_header(); ?>

<?php do_action( 'show_top_stories' ); ?>

<div class="primary-content" role="main">

	<?php do_action( 'show_tabbed_menu' ); ?>

	<?php if (function_exists('falcon_showMoreRecentPostsBanner')) : ?>
		<?php echo falcon_showMoreRecentPostsBanner(); ?>
	<?php endif; ?>

	<?php
		// Hook to display Category filter if ftblogs-category-filter plugin is enabled (used on Beyond brics)
		do_action( 'show_category_filter' );
	?>

	<?php if (have_posts()) : the_post(); ?>
		<?php get_template_part( 'loop', 'index' ); ?>
	<?php endif; ?>

</div><!-- primary-content -->

<?php get_footer(); ?>
