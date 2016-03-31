	<style type="text/css">

	.error-report * { box-sizing: border-box; }
	.error-report { font-family: sans-serif; margin: 0;  color: rgb(70,70,70); }
	.error-report pre { display: block; margin-top: 8px; background-color: #f0f0f0; white-space:pre; overflow: auto; padding: 3px; }
	.error-report div.errcontentcontainer { background-color:white; text-align:left; margin:10px auto; overflow: hidden; }


	/* Images for tree view */

	/* ULs get a basic vertical line background */
	.error-report .tree ul { background-image: url(data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABIAAAASCAQAAAD8x0bcAAAACXZwQWcAAAASAAAAEgAjOG5KAAAAFklEQVQoz2NgQAX/GagFRk0aNYkSkwBo3Aj4y8e/NAAAAABJRU5ErkJggg==);}

	/* LIs get a T-junction image, which sits on top of the UL image */
	.error-report ul.tree ul li { background-image: url(data:image/gif;base64,R0lGODlhEgASAPAAAP///wAAACH5BAEAAAAALAAAAAASABIAQAIbhA+By6afomKz2Wppvrvh7oDLJ5bmiabqypYFADs=);}
	.error-report ul.tree ul li:last-child { background-image: url(data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABIAAAASCAQAAAD8x0bcAAAACXZwQWcAAAASAAAAEgAjOG5KAAAAGElEQVQoz2NgQAX/GagFhr9J2PAooBYAAG8OCfdNbwxwAAAAAElFTkSuQmCC);}

	/* Keys get a page icon */
	.error-report .key { background-image: url(data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABIAAAASCAYAAABWzo5XAAAAGUlEQVR42mNgGAWDAvzHgUcNGswGjYJhDwAWqiPd1K8GVQAAAABJRU5ErkJggg==);}

	/* LIs with a subtree get a PLUS icon and a closed folder */
	.error-report .tree ul li.has-subtree { background-image:url(data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABIAAAASCAQAAAD8x0bcAAAACXZwQWcAAAASAAAAEgAjOG5KAAAANElEQVQoz2NgQAEN/xmoBCg3qeE/OsSqCBXgVcRAFUUMcAhRhIxpYB0RviMinOgcd1QwCQBIB5MBjIRwiAAAAABJRU5ErkJggg==);}
	.error-report .tree ul li.has-subtree:last-child { background-image: url(data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABIAAAASCAQAAAD8x0bcAAAACXZwQWcAAAASAAAAEgAjOG5KAAAAMUlEQVQoz2NgQAEN/xmoBCg3qeE/OsSqCBXgVcRAFUUMcAhRhIxpYB0RviMinIYeAAAqtpADEaAVZgAAAABJRU5ErkJggg==);}
	.error-report .tree ul li.has-subtree > .key { background-image: url(data:image/gif;base64,R0lGODlhEgASANUAAPv7++/v79u3UsyZNOTk5MHBwaNxC8KPKre3t55sBrqHIpxqBMmWMb2KJbOBG5lnAdu3cbWCHaBuCMuYM///urB+GMWSLad1D8eUL6ampqVzDbeEH6t5E8iVMMCNKMbGxq58FppoAqh2EKx6FP/Ub//4k+vr6///nP/bdf/kf//viba2tv//////mQAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAACH5BAEAAC4ALAAAAAASABIAAAaRQJdwSCwaj8ik0jUYTBidAEA5YFkplANhehxABGAwpKHYRByVwHBibbvbo8+Q0TrZ7/jWBTHEtP6AgX8GK0MWLSWJiostEoVCBy0qk5SVLQmPLh4tKZ2eny0LmQ0tKKanqC0hmQotJK+wsS0PfEIBZxUgHCIaBhIJCw8ZBUMABAUrycrLBQREAAEm0tPUUktKQQA7);}

	/* LIs with a subtree which are open, get a MINUS icon and an open folder */
	.error-report .tree ul li.has-subtree.open { background-image: url(data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABIAAAASCAQAAAD8x0bcAAAACXZwQWcAAAASAAAAEgAjOG5KAAAAL0lEQVQoz2NgQAEN/xmoBCg3qeE/OsSqCBXQQREDHEIUIeOBcRMR4UQSaBoMqQAAz9CW/9oxawEAAAAASUVORK5CYII=);}
	.error-report .tree ul li.has-subtree.open:last-child { background-image: url(data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABIAAAASCAQAAAD8x0bcAAAACXZwQWcAAAASAAAAEgAjOG5KAAAAK0lEQVQoz2NgQAEN/xmoBCg3qeE/OsSqCBXQQREDHEIUIeOBcRMR4TT0AACxdZP/0fEmDQAAAABJRU5ErkJggg==);}
	.error-report .tree ul li.has-subtree.open > .key { background-image: url(data:image/gif;base64,R0lGODlhEgASANUAAO/v76VzDfv7+8yZNMHBweTk5JpoAqBuCMuYM8mWMZ5sBpxqBPr7/Le3t///pcaaGvDker2KJc+iJqd1D7B+GOKzQ8KPKqJwCrOBG7WCHbeEH9e4QNq/bP/rhJlnAffwiaampuLBUMmgIf3VcKRyDP/XhLqHIqNxC8iVMMbGxqx6FP/kf//bdf/vievr67a2tv/4k8aaGf//nP//mf///wAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAACH5BAEAADUALAAAAAASABIAAAaVwJpwSCwaj8ikUjgYIBIogEA5oFkZDEtheqzKvl9axKTJYCiAIYIGblutqtQwQYPZ73jZpCGM+f+AfiEdJy99M21tMxwxJQeGNTGIeHcyHzEjCpAAki2en54OIhULkAKSMiuqqysOGxIGkDWcMyy2t7YQDx58QqcBwMAkFwcKCwYgBEQFBC/Oz9AEBUUALtbX2FJLSUEAOw==);}

	@-webkit-keyframes highlight {
		from { background-color: transparent; }
		to { background-color: transparent; }
		20% { background-color: yellow; }
	}
	@-moz-keyframes highlight {
		from { background-color: transparent; }
		to { background-color: transparent; }
		20% { background-color: yellow; }
	}
	@keyframes highlight {
		from { background-color: transparent; }
		to { background-color: transparent; }
		20% { background-color: yellow; }
	}


	.error-report ul.tree { font-size: 12px; line-height: 16px; margin: 0; padding: 0; }
	.error-report .tree ul { margin: 0 0 0 18px; padding: 0; background-repeat: repeat-y; display: none; }
	.error-report .tree li.open > ul { display:block; }

	.error-report .tree li { margin: 0; padding: 0; list-style-type: none; background-repeat: no-repeat; word-wrap: break-word;}
	.error-report .tree li:last-child { background-color: white; }
	.error-report .tree li.highlighted { -webkit-animation-name: highlight; -webkit-animation-duration: 2s; animation-name: highlight; animation-duration: 2s; }

	.error-report .tree li .key { display: block; background-position: 18px 0; background-repeat: no-repeat; padding: 1px 0 1px 36px; cursor: default; font-weight: normal }
	.error-report .tree li .val { font-weight: bold; white-space: pre-wrap; }
	.error-report .tree li .val i { color: #aaa; font-weight: normal; font-style: normal; }
	.error-report .tree li .interp { font-style: italic; color: #CF0AE0; margin-left: 10px;}
	.error-report .tree li .type { display: inline; color: #080; font-size: 10px; line-height: 10px; }
	</style>

<div class="error-report">
<div class="errcontentcontainer">
occurred on line <b><?= $errline ?></b> of <b id="filepath"><?= $errfile ?></b>: <input value="Open" type=button onclick="return sublime('<?= $errfile ?>','<?= $errline ?>')"><br/>
<pre><?= htmlspecialchars($line, ENT_QUOTES, 'UTF-8') ?></pre>
</div>
<div class="errcontentcontainer hideforjs">
<ul id='debugtree' class="tree"><li class="open"><span>Error report</span>
<?= isset($debug) ? $debug : '<i>Debug information missing</i>' ?>
</li></ul>
</div>
</div>
<script>
<?php include __DIR__."/template_dev_html_script.js"; ?>
</script>
<script src="//ajax.googleapis.com/ajax/libs/jquery/2.1.1/jquery.min.js" async onload="initErrorTree()"></script>
