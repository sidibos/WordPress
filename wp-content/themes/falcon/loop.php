<?php
/**
 * The loop that displays posts.
 *
 * The loop displays the posts and the post content.  See
 * http://codex.wordpress.org/The_Loop to understand it and
 * http://codex.wordpress.org/Template_Tags to understand
 * the tags used in it.
 *
 * This can be overridden in child themes with loop.php or
 * loop-template.php, where 'template' is the loop context
 * requested by a template. For example, loop-index.php would
 * be used if it exists and we ask for the loop with:
 * <code>get_template_part( 'loop', 'index' );</code>
 */
?>

<?php /* If there are no posts to display, such as an empty archive page */ ?>
<?php rewind_posts(); if ( ! have_posts() ) : ?>
	<div id="post-0" class="post error404 not-found">
		<h2 class="entry-title"><?php _e( 'Not Found', 'falcon' ); ?></h2>
		<div class="entry-content">
			<p><?php _e( 'Sorry. No results were found.', 'falcon' ); ?></p>
			<?php get_search_form(); ?>
		</div><!-- .entry-content -->
	</div><!-- #post-0 -->
<?php endif; ?>

<?php $first_syndicated_post = true; ?>
<?php rewind_posts(); while ( have_posts() ) : the_post(); ?>
	<?php
	/**
	 * Display syndicated posts differently. These are set up on the post->edit page and require the Syndicated Posts plugin.
	 */
	$syndicate_href              = get_post_meta(get_the_ID(),'syndicate',true);
	$syndicate_blog_name         = get_post_meta(get_the_ID(),'syndicateBlogName',true);
	$syndicate_blog_url          = get_post_meta(get_the_ID(),'syndicateBlogURL',true);
	$syndicate_date              = get_post_meta(get_the_ID(),'syndicateDate',true);
	$class_first_syndicated_post = null;

	// If the first post is a syndicated post, give it a 'syndicated-first' class so it can have a top-border.
	$first_post = false;
	if ($post == $posts[0]) {
		$first_post = true;
	}
	if (	$first_post == true && $first_syndicated_post == true && !empty($syndicate_href) && !empty($syndicate_blog_name) && !empty($syndicate_blog_url) && !empty($syndicate_date)) {
		$class_first_syndicated_post = 'syndicated-first';
		$first_syndicated_post = false;
	}
?>

<?php if (is_singular()): ?>
<div class="fullstory">
	<div class="post-actions" id="falcon-story-tools-top">
		<?php global $blog_id; echo falcon_postActions($post->ID, $blog_id)?>
	</div>
</div>
<?php endif; ?>

<div id="post-<?php the_ID(); ?>" <?php post_class($class_first_syndicated_post); ?>>

	<?php if(!empty($syndicate_href) && !empty($syndicate_blog_name) && !empty($syndicate_blog_url) && !empty($syndicate_date)) : // This is a syndicated post. ?>

	<div class="entry-meta">
		From <a class="syndicated-posted-from" href="<?php echo $syndicate_blog_url; ?>"><?php echo $syndicate_blog_name; ?></a>
		<span class="syndicated-posted-on"><?php echo date('F j, Y',strtotime($syndicate_date)); ?></span>
	</div><!-- .entry-meta -->
	<h2 class="entry-title"><a class="permalink" href="<?php echo $syndicate_href; ?>" rel="bookmark"><?php the_title(); ?></a></h2>

	<?php else: // This is not a syndicated post. ?>

	<h2 class="entry-title">
		<?php assanka_show_post_byline(); // Displays an author headshot or category/country flag. ?>
		<a class="permalink-byline" href="<?php the_permalink(); ?>" rel="bookmark"><?php the_title(); ?></a>
	</h2>

	<?php falcon_entry_meta(); ?>

	<?php endif; ?>

	<div class="entry-content">
		<?php the_content( __( falcon_continue_reading_link() ) ); ?>
		<?php wp_link_pages( array( 'before' => '<div class="page-link">' . __( 'Pages:', 'falcon' ), 'after' => '</div>' ) ); ?>
	</div><!-- .entry-content -->

	<div class="entry-utility entry-meta">
		<?php falcon_posted_in(); ?>
	</div><!-- .entry-utility -->

	<?php if (is_singular()): ?>
	<div class="fullstory">
		<div class="post-actions" id="falcon-story-tools-bottom">
			<?php global $blog_id; echo falcon_postActions($post->ID, $blog_id)?>
		</div>
	</div>
	<?php endif; ?>

</div><!-- #post-## -->

<?php comments_template( '', true ); ?>

<?php endwhile; // End the loop. Whew. ?>

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
