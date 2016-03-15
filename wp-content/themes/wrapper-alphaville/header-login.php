<div id="ftLogin">
	<div class="ftLogin-loggedOut" style="">
		<a href="https://accounts.ft.com/login?location=http://<?php echo urlencode($_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI']); ?>" id="ftLogin-signIn-deprec">Sign in</a>
		<a href="http://media.ft.com/subs-guide">Site tour</a>
		<a href="http://registration.ft.com/registration/subscription-service/signuppsp?segid=70009">Register</a>
		<a href="http://registration.ft.com/registration/subscription-service/bpsp?segid=70152" class="subscriptionLink">Subscribe</a>
	</div>
	<div class="ftLogin-loggedIn" style="display: none; ">
		<span>
			Welcome
			<span id="ftLogin-user"></span>
			<span id="ftLogin-sub-link">
				<a href="http://www.ft.com/cms/782b3c3e-e239-11dd-b1dd-0000779fd2ac.html?pspId=0001&amp;segid=70152&amp;" class="subscriptionLink">Subscribe</a>
			</span>
			<a href="http://registration.ft.com/registration/selfcare/" id="ftLogin-yourAccount">Your account</a>
			<a href="http://media.ft.com/subs-guide">Site tour</a>
			<a href="https://accounts.ft.com/logout?location=http://<?php echo urlencode($_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI']); ?>" id="ftLogin-logout">Sign out</a>
		</span>
	</div>
	<div id="ftLogin-box" class="roundedCorners">
		<form action="https://accounts.ft.com/login?location=http://<?php echo urlencode($_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI']); ?>" method="post">
			<span class="closeButton" onclick="javascript: $('#ftLogin').removeClass('open');"></span>
			<input type="hidden" name="location" value="http://<?php echo urlencode($_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI']); ?>" />
			<fieldset>
				<label for="ftLogin-username">Email address</label>
				<input id="ftLogin-username" name="username" class="text" type="text"></fieldset>
			<fieldset>
				<label for="ftLogin-password">Password</label>
				<input id="ftLogin-password" name="password" class="text" type="password" autocomplete="off"></fieldset>
			<span class="ftLogin-forgotPassword">
				<a href="https://registration.ft.com/registration/login/forgottenpassword?forgottenPasswordUsername=&amp;location=http://<?php echo urlencode($_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI']); ?>">Forgot password?</a>
			</span>
			<fieldset>
				<p class="ftLogin-rememberMe">
					<span>
						<input id="ftLogin-remember" name="rememberme" class="checkbox" type="checkbox" checked="checked">
						<label for="ftLogin-remember">Remember me on this computer</label>
					</span>
					<button type="submit" class="ft-button ft-button-large">Sign in</button>
				</p>
				<p class="ftLogin-cookiePolicy">
					"Remember me" uses a cookie. View our <a href="http://www.ft.com/cookiepolicy">Cookie Policy</a>.
				</p>
			</fieldset>
		</form>
	</div>
</div>
<script>
FT.preInit.loginForm();
</script>
