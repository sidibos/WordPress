<?php
/**
 * The template for displaying all pages.
 *
 * This is the template that displays all pages by default.
 * Please note that this is the WordPress construct of pages
 * and that other 'pages' on your WordPress site will use a
 * different template.
 */

// There are some admin-set options for this theme.
$theme_options = get_option('theme_options'); 
get_header(); 
?>

<div class="master-row contentSection ">
	<?php echo assanka_show_tabbed_menu(); // Display a tabbed page/category menu. ?>
	<?php if ( have_posts() ) while ( have_posts() ) : the_post(); ?>
	<?php
	// Src for the post's author's headshot
	$user_id = get_the_author_meta('id');
	$byline_author_headshot_src = get_user_meta($user_id, '_ftblogs_headshoturl', true); 
	?>
	<div id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
		<h2 class="entry-title">
			<?php if(!empty($theme_options['byline_pages']) && $theme_options['byline_pages'] == 2 && !empty($byline_author_headshot_src)): // Display author head shot. ?>
			<img alt="<?php the_author(); ?>" height="45" width="35" src="<?php echo get_usermeta($user_id, "_ftblogs_headshoturl"); ?>" class="headshot">
			<?php endif; ?>
			<?php the_title(); ?>
		</h2>
		<?php falcon_entry_meta(); ?>
		<div class="entry-content">
			<?php the_content(); ?>
			<?php wp_link_pages( array( 'before' => '<div class="page-link">' . __( 'Pages:', 'falcon' ), 'after' => '</div>' ) ); ?>
			<?php edit_post_link( __( 'Edit', 'falcon' ), '<span class="edit-link">', '</span>' ); ?>
		</div><!-- .entry-content -->
	</div><!-- #post-## -->

	<?php comments_template( '', true ); ?>
	<?php endwhile; ?>
</div><!-- .master-row contentSection -->

<?php get_sidebar(); ?>
<?php get_footer(); ?>
