<?php
/**
 * CoreInterfaceV4
 *
 * @codingstandard ftlabs-phpcs
 * @copyright The Financial Times Limited [All rights reserved]
 */

namespace FTLabs\TemplateManager;

class CoreInterfaceV4 extends TemplateManagerV4 {

	protected $activesectionpath, $activesubsectionpath, $activenamespace, $dbref, $siteroot, $sentheaders, $apps, $actcount, $sectiondata, $pagetemplate, $showheader, $showfooter, $showgatepass, $maxnavdepth;

	public function __construct($templatedir = false) {
		if ($templatedir != false and !is_string($templatedir) and !is_array($templatedir)) {
			trigger_error("AdminInterface instantiated with invalid parameter", E_USER_NOTICE);
			$templatedir = false;
		}
		$adminTemplates = __DIR__ . '/admintemplates';
		if ($templatedir == false) {
			parent::__construct($adminTemplates);
		} else {
			if (is_array($templatedir)) {
				$templatedir[] = $adminTemplates;
			} else {
				$templatedir = array($templatedir, $adminTemplates);
			}
			parent::__construct($templatedir);
		}
		$this->sections = array();
		$this->sentheaders = false;
		$this->actcount = 0;
		$this->showheader = 1;
		$this->showfooter = 1;
		$this->maxnavdepth = 1;
		$this->showgatepass = 0;
		$this->siteroot = "";
		$this->db = false;
		$this->pagetemplate = "adminpage";
	}

	public function showHeader($bool = true) {
		$this->showheader = $bool;
	}

	public function showFooter($bool = true) {
		$this->showfooter = $bool;
	}

	public function showGatepass($bool = true) {

		// Deprecated
	}

	public function setRoot($path) {
		$this->siteroot = rtrim($path, "/");
	}

	public function getRoot() {
		return $this->siteroot;
	}

	public function countQueriesForDB($db) {
		$this->dbref = $db;
	}

	public function addAction($label, $options) {
		$this->actcount++;
		if (is_string($options)) $options = array("href"=>$options);
		if (!empty($options["data"]) and is_array($options["data"])) {
			$op = "<form name='actionform".$this->actcount."' method='";
			$op .= (!empty($options["method"])) ? $options["method"] : "POST";
			$op .= "' action='";
			$op .= (!empty($options["href"])) ? $options["href"] : $_SERVER["PHP_SELF"];
			$op .= "' onsubmit=\"";
			$op .= (empty($options["javascript"])) ? "return true" : $options["javascript"];
			$op .= "\"";
			if (!empty($options["domid"])) $op .= " id=\"action_".$options["domid"]."\"";
			if (!empty($options["hidden"])) $op .= " style=\"visibility:hidden\"";
			$op .= ">";
			foreach ($options["data"] as $key=>$val) {
				$op .= "<input type='hidden' name=\"".$key."\" value=\"".$val."\" />";
			}
			$op .= "<a href='javascript:if (document.actionform".$this->actcount.".onsubmit()) document.actionform".$this->actcount.".submit();'>".$label."</a></form>";
		} else {
			$op = "<a href=\"".((!empty($options["href"]))?$options["href"]:"javascript:void(0)")."\"";
			if (!empty($options["domid"])) $op .= " id=\"action_".$options["domid"]."\"";
			if (!empty($options["hidden"])) $op .= " style=\"display:none\"";
			if (!empty($options["javascript"])) {
				if (empty($options["href"])) {
					if (substr($options["javascript"], strlen($options["javascript"]) - 1, 1) != ";") $options["javascript"] .= ";";
					$options["javascript"] .= " return false;";
				}
				$op .= " onclick=\"".$options["javascript"]."\"";
			}
			$op .= ">".$label."</a>";
		}
		$this->add("actions", $op);
	}

	public function showWaitMsg($title, $subtitle) {
		if ($approot = $this->getAppRootURI()) $this->set("approot", $approot);
		$this->set("waitmsg_title", $title);
		$this->set("waitmsg_subtitle", $subtitle);
		if (!$this->sentheaders) $this->sendHeaders();
		echo $this->render("waitmsg");
		ob_flush();
		flush();

		// Prevent rendering issues in Firefox (support request #16683)
		usleep(500000);
	}

	public function reportProgress($percent, $msg='') {
		$this->set("waitmsg_progress", $percent);
		$this->set("waitmsg_update", $msg);
		if (!$this->sentheaders) $this->sendHeaders();
		echo $this->render("waitmsg_progress");
		ob_flush();
		flush();
	}

	public function alert($msg, $type = "info") {
		$types = array("warning", "error", "wait", "done", "info");
		if (isset($_SESSION["infomsgs"])) {
			$newindex = sizeof($_SESSION["infomsgs"]);
		} else {
			$newindex = 0;
			$_SESSION["infomsgs"] = array();
		}
		$type = (in_array($type, $types)) ? $type : "info";
		$_SESSION["infomsgs"][$newindex] = array("msg"=>$msg, "type"=>$type);
	}

	function goBackAndAlert($msg) {

		// Deprecated.
		$this->redirectAndAlert($msg);
	}

	public function redirectAndAlert($msg=false, $msgtype='error', $url=false) {
		if ($msg) $this->alert($msg, $msgtype);
		if (!$url and !empty($_SERVER["HTTP_REFERER"])) $url = $_SERVER["HTTP_REFERER"];
		if (!$url and preg_match("/^(\/(\w+\/)+)/i", $_SERVER["REQUEST_URI"], $m)) $url = $m[1];

		$apprelativeurl = preg_match("/^\w(\w+\/)+/i", $url);
		if ($apprelativeurl) $url = $this->getAppRootUri().$url;

		if ($url) {

			// Redirect via headers if headers haven't already been sent...
			if (!headers_sent()) {
				header("Location: ".$url);

			// Otherwise, redirect via javascript/noscript fallback.
			} else {
				if (!empty($apprelativeurl) and $url[0] != "/") {
					$url = preg_replace("/\/[^\/]+$/", "", $_SERVER["SCRIPT_NAME"])."/".$url;
				}
				echo "<script type=\"text/javascript\">window.location.href = '".$url."';</script>";
				echo "<noscript><meta http-equiv=\"refresh\" content=\"1;url=".$url."\" /></noscript>";
			}
		} else {
			if (!$msg) trigger_error("No message or redirect location defined", E_USER_ERROR);
			echo $msg;
		}
		exit;
	}

	public function redirect($url=false) {
		$this->redirectAndAlert(false, false, $url);
	}

	public function outputHTML() {
		if (!empty($_SERVER['HTTP_USER_AGENT']) and preg_match('/; MSIE 6.[01]b?;/', $_SERVER['HTTP_USER_AGENT'])) {
			$this->alert('You are using an out of date web browser that is no longer supported by Assanka. Please upgrade to a modern web browser. Assanka recommends <a href="http://www.google.com/chrome">Google Chrome</a> for the fastest and easiest browsing experience.<ul><li><b><a href="http://www.google.com/chrome">Install Google Chrome</a></b></li></ul>Other supported browsers (latest version): <a href="http://www.mozilla.com/firefox/">Mozilla Firefox</a>, <a href="http://www.apple.com/safari/">Apple Safari</a>, <a href="http://www.microsoft.com/windows/internet-explorer/default.aspx">Microsoft Internet Explorer</a>.', 'warning');
		}

		$this->set('showheader', $this->showheader);
		$this->set('showfooter', $this->showfooter);
		$this->set('showgatepass', $this->showgatepass);
		if (isset($this->_assignedvars["actions"]) or isset($this->_assignedvars["rightnav"])) {
			$this->set("showactionsandfilters", 1);
		}
		if (isset($_SESSION["infomsgs"])) {
			foreach ($_SESSION["infomsgs"] as $msg) {
				if (!$msg["type"]) $msg["type"] = "info";
				$this->add("pinnedevents", "<div class=\"infobar_".$msg["type"]."\"><img src=\"/corestatic/adminmedia/img/close.png\" width=\"11\" height=\"10\" class=\"evtbutton evtclose\" title=\"Close this message\" /><span>".$msg["msg"]."</span></div>");
			}
			unset($_SESSION["infomsgs"]);
		}
		if ($this->dbref) $this->set("querycount", $this->dbref->getQueryCount());
		if ($this->maxnavdepth == 1 and !empty($this->_assignedvars['subtabs'])) {
			$this->set("sections", $this->_assignedvars["subtabs"]);
			$this->clearAssigned("subtabs");
		}

		if ($approot = $this->getAppRootURI()) $this->set("approot", $approot);

		// Output
		if (!$this->sentheaders) {
			$this->sendHeaders();
		} else {
			echo "<style type=\"text/css\">#waitmsg { display: none }</style>\n";
			ob_flush();
			flush();
		}
		echo $this->render($this->pagetemplate);
	}

	protected function sendHeaders() {
		header("Expires: Mon, 04 Oct 2004 09:00:00 GMT");
		header("Cache-Control: max-age=0, public");
		header("Cache-Control: no-store, no-cache, must-revalidate", false);
		header("Cache-Control: post-check=0, pre-check=0", false);
		header("Pragma: no-cache");
		$this->sentheaders = true;
	}
}
