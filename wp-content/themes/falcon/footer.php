

			<!-- continued from content -->
			<?php get_sidebar(); ?>

			<!-- continued from header -->
		</div><?php // master-column middleSection ?>

		<!-- footer -->
		<div class="master-row bottomSection ">
			<div class="freestyle" >
				<div id="footer">
					<div id="content">
						<ul class="gen-freestyle-ul">
							<li><a href="http://www.ft.com/help">Help</a></li>
							<li>&bull;<a href="http://aboutus.ft.com/contact-us">Contact us</a></li>
							<li>&bull;<a href="http://www.ft.com/aboutus">About us</a></li>
							<li>&bull;<a href="http://www.ft.com/advertising">Advertise with the FT</a></li>
							<li>&bull;<a href="http://www.ft.com/servicestools/help/terms">Terms &amp; Conditions</a></li>
							<li>&bull;<a href="http://www.ft.com/servicestools/help/privacy">Privacy Policy</a></li>
							<li>&bull;<a href="http://www.ft.com/servicestools/help/copyright">Copyright</a></li>
							<li id="footerCookiePolicy">&bull;<a href="http://www.ft.com/cookiepolicy">Cookie policy</a></li>
						</ul>
						<p><span class="copyright"><strong>&copy; The Financial Times Ltd <?php echo date('Y'); ?></strong></span> <span>FT and 'Financial Times' are trademarks of The Financial Times Ltd.</span> </p>
					</div>
				</div>
			</div>
		</div>
	</div>
	<script type="text/javascript">FTSection = ""; pageUUID = "";</script>
	<script type="text/javascript" src="http://s2.media.ft.com/scripts/2029995712/bundles/foot.js"></script>
	<script type="text/javascript">FT.preInit.loginForm();</script>
	<!--ft:footWrapperJs-->
</div><?php // end: <div class="clearfix container" id="page-container"> ?>
<?php include TEMPLATEPATH . '/advertising_footer.php'; ?>
<script type="text/javascript">
for (var i in clipthishrefs) {
	jQuery.getScript(clipthishrefs[i]);
}
</script>
<?php wp_footer(); ?>
<!--[if IE]></div><![endif]-->
<!-- Revision <?= CACHEBUSTER ?> -->
</body></html>
