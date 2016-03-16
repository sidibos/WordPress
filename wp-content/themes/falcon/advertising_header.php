<div class="header-advertising">
	<div id="banlb" class="advertising">
	    <script type="text/javascript">FT.ads.request("banlb");</script>
		<noscript>
			<?php 
			$theme_options = get_option('theme_options'); 
			$dfp_rand = str_pad(abs(mt_rand(1000000000000000, 9999999999999999)), 16, "0", STR_PAD_LEFT);
			?>
			<a href="http://ad.doubleclick.net/N5887/jump/<?php echo $theme_options['dfp_site']; ?>/<?php echo $theme_options['dfp_zone']; ?>;sz=468x60,728x90;pos=banlb;tile=1;nojs=1?">
			<img src="http://ad.doubleclick.net/N5887/ad/<?php echo $theme_options['dfp_site']; ?>/<?php echo $theme_options['dfp_zone']; ?>;sz=468x60,728x90;pos=banlb;tile=1;nojs=1?" border="none">
			</a>
		</noscript>
	</div>
	<div id="newssubs" class="advertising">
	    <script type="text/javascript">FT.ads.requestNewssubs();</script>
	</div> 
</div>