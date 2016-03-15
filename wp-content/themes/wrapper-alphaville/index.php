<?php
/**
 * The main template file.
 */
?>

<div class="primary-container">
	<div class="page-columns-container">
		<div class="primary-content" role="main">
			<div class="inner">
				<?php if ( is_day() ) : ?>
					<h3>Posts from <?=get_the_date()?></h3>
				<?php elseif ( is_month() ) : ?>
					<h3>Posts from <?=get_the_date('F Y')?></h3>
				<?php elseif ( is_year() ) : ?>
					<h3>Posts from <?=get_the_date('Y')?></h3>
				<?php endif; ?>
				<?php if (have_posts()) : the_post(); ?>
					<?php get_template_part( 'loop', 'index' ); ?>
				<?php else: ?>
				<div class="hentry clearfix">
					<div class="entry-header">
						<h1 class="entry-title"><a href="/">Sorry</a></h1>
					</div><!-- .entry-header -->
				 	<div class="entry-content entry-excerpt">
							<h4>The post or page you were looking for could not be found.</h4>
							<p>Either the page has been removed or was never here in the first place. To see an index of posts in each section of Alphaville, click the section name above, or use the search facility at the top of the page.</p>
					</div><!-- .entry-excerpt -->
				</div>
				<?php endif; ?>
			</div>
		</div><!-- primary-content -->
		<?php get_footer(); ?>
	</div>
</div><!-- primary-container -->
