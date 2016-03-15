
<div id="primary-navigation">
	<?php
	// See: hook_wp_nav_menu_objects() in functions.php
	wp_nav_menu( array('theme_location' => 'primary'));
	?>
</div>

<?php if(class_exists('Assanka_TagIndexOverlay')){ Assanka_TagIndexOverlay::show_tab_overlay(); } ?>

<div id="primary-navigation-tools" class="sharelink-icons">
	<ul>
		<li><a class="back-to-ft" href="http://ft.com/">Back to FT.com</a></li>
		<li class="icon"><a target="_blank" class="icon twitter overlayButton" href="https://twitter.com/ftalphaville"><span class="hidden">Twitter</span></a></li>
		<li class="icon"><a target="_blank" class="icon tumblr" href="http://ftalphaville.tumblr.com/"><span class="hidden">Tumblr</span></a></li>
		<li class="icon"><a target="_blank" class="icon rss" href="/feed/"><span class="hidden">RSS</span></a></li>
	</ul>

	<div class="roundedCorners overlay" id="header-tools_twitter-overlay">
		<div class="overlayArrow overlayTopArrow"></div>
		<div class="innerBox">
			<a href="javascript:void(0)" class="close-icon" onclick="$(this).closest('.overlay').hide();"></a>
			<p><strong>FT Alphaville on Twitter</strong></p>
			<p>For new posts:</p>
			<?php echo generateTwitterFollowLink('FTAlphaville', true); ?>

			<p>For additional musings and announcements:</p>
			<?php echo generateTwitterFollowLink('ftalpha', true); ?>

		</div>
	</div>
</div>
