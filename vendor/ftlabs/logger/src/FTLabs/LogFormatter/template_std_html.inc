<!DOCTYPE html>
<html>
<head>
	<title>A problem has occurred</title>
	<meta name="robots" content="noindex, nofollow" />
	<style type="text/css">
	body { text-align: center; margin: 0; padding: 10px; font-family: "Helvetica Neue", Verdana, sans-serif; color: #555; font-size: 14px; line-height: 160%;}
	div.errcontentcontainer { width:720px; text-align:left; margin: 20px auto 10px auto; padding: 15px; }
	div.errortracking { background-color: #f0f0f0; margin-bottom: 40px; padding: 10px; border: 1px solid #999 }
	h1 { color: black; font-size: 35px; font-weight: normal; margin-bottom: 40px; }
	h2 { font-size: 19px; margin: 25px 0 7px 0; font-weight:normal }
	p { color: #333; margin-bottom: 12px; margin-top: 0px; }
	span.trackingcode { float:right; color: black; font-size: 20px; font-family: monospace; font-weight:bold; padding: 4px 0; margin-top: -4px; overflow:hidden; text-align:center; width: 250px }
	a.visited { color: blue }
	a.inline, a.inline:visited { color: #272361; text-decoration: none }
	a.inline:hover { color: blue; text-decoration: underline }
	.important { color: black }
	</style>
</head>
<body>
<div class="errcontentcontainer">
	<h1>Problem.</h1>
	<div class="errortracking">
		<span class="trackingcode"><?= htmlspecialchars($errorcode) ?></span> If you contact us about this problem, please <span class="important">quote this code</span>:
	</div>

	<h2>What should I do?</h2>
	<p>The problem may be fixed automatically, or if it's a simple problem an engineer may be able to fix it very quickly, so <a href="javascript:location.reload()" class="inline">try again</a> in a few minutes.  If you still encounter problems and want some help, <a href="mailto:help@ft.com" class="inline">contact us</a> and we'll doour best to assist you.  Or you can go back to <a href="<?= htmlspecialchars($url) ?>" class="inline">where you were before this problem occurred</a>.</p>
	<ul class="actions">
	<li><a href="javascript:location.reload()">Try again</a></li>
	<li><a href="mailto:help@ft.com">Contact us</a></li>
	<li><a href="<?= htmlspecialchars($url) ?>">Go back to where you were before</a></li>
	</ul>

	<h2>How did this happen?</h2>
	<p>Unexpected errors can occur for many reasons.  There might be a software problem, or a physical fault with our computer systems, or there may be an unexpectedly high level of traffic.  A complete report that should enable an engineer to fix the problem has been created and if someone isn't looking at it right now, they will be very shortly.</p>
</div>

</body>
</html>
