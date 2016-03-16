<?php
/**
 * The template for displaying Category Archive pages.
 */
?>

<?php get_header(); ?>

<div class="primary-content" role="main">
	<?php if (have_posts()) : the_post(); ?>

		<?php
			// Hook to display Category filter if ftblogs-category-filter plugin is enabled (used on Beyond brics)
			do_action( 'show_category_filter' );
		?>

		<?php // Note: Do not change the ID to anything other than "tabbed-menu-page-title". It's used by assanka_show_tabbed_menu(). ?>
		<h1 class="page-title" id="tabbed-menu-page-title"><?php printf( __( '%s', 'falcon' ), '<span>' . single_cat_title( '', false ) . '</span>' ); ?></h1>

		<?php $category_description = category_description(); ?>
		<?php if (!empty($category_description)): ?>
		<div class="archive-meta"><?php echo $category_description ?></div>
		<?php endif; ?>

		<?php get_template_part( 'loop', 'category' ); ?>

	<?php endif; ?>
</div><!-- primary-content -->

<?php get_footer(); ?>