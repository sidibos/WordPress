<div class="entry-header">
	<h1 class="entry-title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h1>

	<?php if (!is_page()): ?>

		<div class="entry-meta">
				<?php 
				$authors = function_exists( 'get_coauthors' ) ? get_coauthors() : array(get_userdata(get_the_author_meta('ID')));
				foreach ($authors as $author) { 
					
					$class = '';
					$user_description = get_the_author_meta('user_description',$author->ID);
					if(!empty($user_description)){
						// Twitter 'follow' buttons do not display the @username if the parent div is not visible.
						// So they're positioned offscreen rather than hidden. https://dev.twitter.com/discussions/6785
						$class = "overlayButton";
					}

					echo '<strong><a class="'.$class.'" href="'.get_author_posts_url($author->ID).'" rel="author">'.$author->display_name.'</a>';
					echo ($author === end($authors)) ? '' :', '; //if not last one, add a comma
					echo '</strong>';

					if(!empty($user_description)) {	
						create_author_overlay($author->ID);
					}
				}
			?>
			<div class="inline-block">
				<!-- author alerts -->
				<?php  
			 	if (class_exists('Assanka_UID')){
					$blog_post_uid = Assanka_UID::get_the_post_uid();
				}
				?>
				<div class="o-author-alerts o-author-alerts--theme" data-o-component="o-author-alerts" data-o-version="0.1.0" data-o-author-alerts-article-id="<?php echo $blog_post_uid; ?>"></div>
				<span class="meta-divider"> | </span>
				<span class="entry-date"><?php echo assanka_get_the_time(); ?></span>
				<span class="meta-divider"> | </span>
				<?php if (comments_open()): ?>
					<a href="<?=get_permalink()?>#respond"<?=apply_filters('comments_popup_link_attributes', '')?>>Comment</a>
					<span class="meta-divider"> | </span>
				<?php endif;?>
				<?php echo alphaville_get_share_widget_html(); ?>
			</div> 
		</div><!-- .entry-meta -->

	<?php endif; // if (!is_page()) ?>

	<?php if(class_exists('Assanka_PromotedTags')) { Assanka_PromotedTags::display_widget(); } ?>

</div><!-- .entry-header -->
