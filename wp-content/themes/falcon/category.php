<?php
/**
 * The template for displaying Category Archive pages.
 */


get_header(); ?>

<div class="master-row contentSection">
	<?php echo assanka_show_tabbed_menu(); // Display a tabbed page/category menu. ?>

	<div class="master-row editorialSection">
		<h1 class="page-title" id="tabbed-menu-page-title"><?php
			// Note: Do not change the ID to anything other than "tabbed-menu-page-title". It's used by assanka_show_tabbed_menu().
			printf( __( '%s', 'falcon' ), '<span>' . single_cat_title( '', false ) . '</span>' );
		?></h1>
		<?php
		/* Run the loop for the category page to output the posts.
		 * If you want to overload this in a child theme then include a file
		 * called loop-category.php and that will be used instead.
		 */
		get_template_part( 'loop', 'category' );
		?>
	</div><?php // master-row editorialSection ?>
</div><?php // master-row contentSection ?>

<?php get_sidebar(); ?>
<?php get_footer(); ?>
