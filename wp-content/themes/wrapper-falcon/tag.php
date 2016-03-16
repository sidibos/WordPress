<?php
/**
 * The template for displaying Tag Archive pages.
 */
?>

<?php get_header(); ?>

<div class="primary-content" role="main">
	<?php if (have_posts()) : the_post(); ?>
		<h1 class="page-title"><?php printf( __( '%s', 'falcon' ), '<span>' . single_tag_title( '', false ) . '</span>' ); ?></h1>
		<?php get_template_part( 'loop', 'tag' ); ?>
	<?php endif; ?>
</div><!-- primary-content -->

<?php get_footer(); ?>