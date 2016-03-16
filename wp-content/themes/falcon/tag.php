<?php
/**
 * The template for displaying Tag Archive pages.
 */


get_header(); ?>

<div class="master-row contentSection ">


				<h1 class="page-title"><?php
					printf( __( 'Tag: %s', 'falcon' ), '<span>' . single_tag_title( '', false ) . '</span>' );
				?></h1>

<?php
/* Run the loop for the tag archive to output the posts
 * If you want to overload this in a child theme then include a file
 * called loop-tag.php and that will be used instead.
 */
 get_template_part( 'loop', 'tag' );
?>

</div><?php // master-row contentSection ?>


<?php get_sidebar(); ?>
<?php get_footer(); ?>
