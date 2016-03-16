<div id="ftLogin">
	<div class="ftLogin-loggedOut">
		<a href="https://registration.ft.com/registration/barrier/login" id="ftLogin-signIn">Sign in</a>
		<a href="http://media.ft.com/subs-guide">Site tour</a>
		<a href="http://www.ft.com/cms/275bc334-3063-11dc-9a81-0000779fd2ac.html?segid=70009&amp;segsrc=fthome">Register</a>
		<a id="subscriptionLink" href="https://registration.ft.com/registration/subscription-service/bpsp?segid=70152">Subscribe</a>
	</div>
	<div class="ftLogin-loggedIn">
		<span>
			Welcome <span id="ftLogin-user"></span>
			<a href="http://registration.ft.com/registration/selfcare/" id="ftLogin-yourAccount">Your account</a>
			<a href="http://media.ft.com/subs-guide">Site tour</a>
			<a href="https://registration.ft.com/registration/login/logout?location=http://<?php echo urlencode($_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI']); ?>" id="ftLogin-logout">Sign out</a>
		</span>
	</div>
	<div id="ftLogin-box" class="roundedCorners">
		<form action="https://registration.ft.com/registration/barrier/login" method="post">
			<input type="hidden" name="location" value="http://<?php echo urlencode($_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI']); ?>" />
			<fieldset>
				<label for="ftLogin-username">Email address</label>
				<input id="ftLogin-username" name="username" class="text" type="text"/>
			</fieldset>
			<fieldset>
				<label for="ftLogin-password">Password</label>
				<input id="ftLogin-password" name="password" class="text" type="password"/>
			</fieldset>
			<span class="ftLogin-forgotPassword">
				<a href="https://registration.ft.com/registration/login/forgottenpassword?forgottenPasswordUsername=&amp;location=http://<?php echo urlencode($_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI']); ?>">Forgot password?</a>
			</span>
			<fieldset>
				<p class="ftLogin-rememberMe">
					<span>
						<input id="ftLogin-remember" name="rememberme" class="checkbox" type="checkbox" checked="checked" />
						<label for="ftLogin-remember">Remember me on this computer</label>
					</span>
					<button type="submit" class="linkButton submit"><span><span>Sign in</span></span></button>  
				</p>
			</fieldset>
		</form>
	</div>
</div>
