<?php
/**
 * The template for displaying Tag Archive pages.
 */
?>

<?php if (is_tag()) { $tag = get_queried_object(); $promoted_tag = get_option('promoted_tag_' . $tag->term_id); } ?>

<div class="primary-container">
	<div class="page-columns-container clearfix">
		<div class="primary-content" role="main">
			<div class="inner">
				<?php if(tag_description()): ?>
				<div class="promoted-tag-container clearfix">
					<?php if(!empty($promoted_tag['image_src'])): ?>
					<img class="promoted-tag-image" src="<?php echo trim(esc_attr($promoted_tag['image_src'])); ?>" />
					<?php endif; ?>
					<?php echo wpautop(tag_description()); ?>
				</div>
				<?php else: ?>
				<h3>Posts tagged '<?=$tag->name?>'</h3>
				<?php endif; ?>

				<?php get_template_part( 'loop', 'index' ); ?>
			</div>
		</div><!-- primary-content -->
		<?php get_footer(); ?>
	</div>
</div>
