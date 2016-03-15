<?php rewind_posts(); while ( have_posts() ) : the_post(); ?>
	<div id="post-<?php the_ID(); ?>" <?php post_class('clearfix'); ?>>
		<?php get_template_part('entry-header'); ?>
		<div class="entry-content entry-excerpt">
			<?php the_excerpt(); // Modified in mu-plugins/assanka_get_the_excerpt.php ?>
		</div><!-- .entry-excerpt -->
	</div>
<?php endwhile; // End the loop. ?>

<?php /* Display navigation to next/previous pages when applicable */ ?>
<?php if (  $wp_query->max_num_pages > 1 ) : ?>
<div class="backnforth bottom">
	<div class="back">
		<?php next_posts_link('<img class="go_button" alt="Back" src="/wp-content/themes/falcon/img/back_button.png">Older entries', 0); ?>
	</div>
	<div class="forth">
		 <?php previous_posts_link('Newer entries <img \="" src="/wp-content/themes/falcon/img/next_button.png" class="go_button">', 0); ?>
	</div>
</div>
<?php endif; ?>
