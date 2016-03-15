<div id="ft-search" class="clearfix wideSearchInput">
	<form id="wsod-symbolSearch" method="get" action="/searchdispatcher" _lpchecked="1">
		<input type="hidden" value="<?php global $blog_id; echo $blog_id; ?>" name="blogid">
		<fieldset>
			<div class="searchContainer">
				<input class="text ft-search-auto-completable ft-search-autocomplete-placeholder" type="text" value="" name="s" id="simpleSearchField" autocomplete="off">
			</div>
			<select id="search-scope" name='scope'>
				<option value="thisblog">This blog</option>
				<option value="quotes">Quotes</option>
				<option value="ftcom">All content</option>
			</select>

			<button type="submit" class="ft-button ft-button-large">Search</button>

		</fieldset>
		<div class="ft-autocomplete">
			<div class="ft-autocomplete-quotes" style="display: none; ">
				<h6 class="ft-autocomplete-heading">Quotes</h6>
			</div>
			<div class="ft-autocomplete-news" style="display: none; ">
				<h6 class="ft-autocomplete-heading">All content</h6>
			</div>
		</div>
	</form>
	<div class="symbolSearch symbolSearchHidden"></div>
</div><!-- /#ft-search -->
