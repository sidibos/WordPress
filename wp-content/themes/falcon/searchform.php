<form method="get" id="searchform" action="<?php bloginfo('url'); ?>/">
	<label class="hidden" for="s"><?php _e('Search for:'); ?></label>
	<div>
		<input type="text" value="<?php the_search_query(); ?>" name="s" id="s" />
		<button type="submit" class="linkButton submit"><span><span>Search</span></span></button>  
	</div>
</form>
