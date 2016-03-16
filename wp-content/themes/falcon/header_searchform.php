<form class="column-searchform" action="/searchdispatcher" method="get">
	<fieldset>
		<input type="hidden" value="<?php global $blog_id; echo $blog_id; ?>" name="blogid">
		<input type="text" name="s" value="<?php the_search_query(); ?>" class="text">
		<select name="scope" id="selscope" class="searchType">
			<option value="thisblog">This blog</option>
			<option value="quotes">Quotes</option>
			<option value="ftcom">All content</option>
		</select>
		<button type="submit" class="linkButton submit"><span><span>Search</span></span></button>  
	</fieldset>
</form>
