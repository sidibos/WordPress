<?php
/**
 * The main template file.
 *
 * This is the most generic template file in a WordPress theme
 * and one of the two required files for a theme (the other being style.css).
 * It is used to display a page when nothing more specific matches a query.
 * E.g., it puts together the home page when no home.php file exists.
 * Learn more: http://codex.wordpress.org/Template_Hierarchy
*/
?>

<?php get_header(); ?>

<div class="master-row contentSection ">
	<?php echo assanka_show_tabbed_menu(); // Display a tabbed page/category menu. ?>
	<?php echo falcon_showMoreRecentPostsBanner(); // Display a post from a promotional category in a banner-like format. ?>


	<?php if (have_posts()) : the_post(); ?>
	<div class="master-row editorialSection ">
		<?php
		/**
		 * Run the loop to output the posts.
		 * If you want to overload this in a child theme then include a file called loop-index.php and that will be used instead.
		 */
		 get_template_part( 'loop', 'index' );
		?>
	</div><?php // master-row editorialSection ?>
	<?php endif; ?>

</div><?php // master-row contentSection ?>

<?php get_footer(); ?>
