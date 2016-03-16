<div class="entry-meta">
	<?php
		$theme_options = get_option('theme_options');
		$show_byline = true;
		if (is_page() && (!isset($theme_options['byline_pages']) || $theme_options['byline_pages'] == 0)) {
			$show_byline = false;
		}
		if (!is_page() && (!isset($theme_options['byline_posts']) || $theme_options['byline_posts'] == 0)) {
			$show_byline = false;
		}
	?>

	<?php if ($show_byline): ?>
	<strong>
		<?php 
		if ( function_exists( 'coauthors_posts_links' ) ) {
		    coauthors_posts_links();
		} else {
		    the_author_posts_link();
		};
		?>
	</strong>
	<?php endif; ?>
	<div class="inline-block">
		<!-- author alerts -->
		<?php  
	 	if (class_exists('Assanka_UID')){
			$blog_post_uid = Assanka_UID::get_the_post_uid();
		}
		?>
		<?php if ($show_byline): ?>
			<div class="o-author-alerts o-author-alerts--theme" data-o-component="o-author-alerts" data-o-version="0.1.0" data-o-author-alerts-article-id="<?php echo $blog_post_uid; ?>"></div>
			<span class="meta-divider"> | </span>
		<?php endif; ?>
		<span class="entry-date"><?php echo assanka_get_the_time(); ?></span>
		<span class="meta-divider"> | </span>
		<?php do_action('falcon_byline_before_comments'); ?>
		<?php if (comments_open()): ?>
			<a href="<?=get_permalink()?>#respond"<?php echo apply_filters('comments_popup_link_attributes', ''); ?>>Comments</a>
			<span class="meta-divider"> | </span>
		<?php endif;?>
		<?php echo falcon_get_share_widget_html(); ?>
	</div>

</div><!-- .entry-meta -->
