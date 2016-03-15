<?php if (empty($_POST['naked'])) get_template_part('entry-header'); ?>

<div class="entry-content">
	<?php the_content(); ?>
	<?php wp_link_pages( array( 'before' => '<div class="page-link">' . __( 'Pages:', 'twentyten' ), 'after' => '</div>' ) ); ?>
</div><!-- .entry-content -->

<?php if(class_exists('Assanka_PromotedTags')) { Assanka_PromotedTags::display_widget('footer'); } ?>

<?php if (!is_page()): ?>
<div class="entry-utility entry-meta">
	This entry was posted by <?php the_author_posts_link(); ?> 
	on <time class="entry-date" pubdate><?php echo assanka_get_the_time('l F jS, Y H:i'); ?></time>.
	<?php the_tags('Tagged with ', ', ', '.'); ?>
</div><!-- .entry-utility -->
<?php else: ?>
<hr />
<?php endif; ?>

<div class="entry-sharelinks">
	<ul>
		<li class="icon sharelink"><a class="icon twitter" target="_blank" href="<?php echo get_sharelink_href_twitter(); ?>"><span class="hidden">Twitter</span></a></li>
		<li class="icon sharelink"><a class="icon facebook" target="_blank" href="<?php echo get_sharelink_href_facebook(); ?>"><span class="hidden">Facebook</span></a></li>
		<li class="icon sharelink"><a class="icon googleplus" target="_blank" href="<?php echo get_sharelink_href_googleplus(); ?>"><span class="hidden">Google+</span></a></li>
		<li class="icon sharelink"><a class="icon linkedin" target="_blank" href="<?php echo get_sharelink_href_linkedin(); ?>"><span class="hidden">Linkedin</span></a></li>
	</ul>
</div><!-- .entry-sharelinks -->

<div class="post-actions">
	<?php echo post_actions(); ?>
</div>
