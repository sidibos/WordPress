<?php
/**
 * SingleAppInterfaceV5
 *
 * @codingstandard ftlabs-phpcs
 * @copyright The Financial Times Limited [All rights reserved]
 */

namespace FTLabs\TemplateManager;

class SingleAppInterfaceV5 extends CoreInterfaceV5 {

	public function __construct($templatedir = false) {
		parent::__construct($templatedir);
		$this->showHeader(false);
	}

	public function getAppNamespace() {
		return $this->activesubsectionpath;
	}

	public function getAppRootURI($includehost=true) {

		if ($this->maxnavdepth < 2 ) {
			$ret = $this->siteroot."/";
		} else {
			$approot = trim($this->getAppnamespace(), '/');
			if ($this->siteroot) {
				$ret = $this->siteroot."/".$approot."/";
			} else {
				$ret = "/".$approot."/";
			}
		}
		if ($includehost) {
			$protocol = (!empty($_SERVER["HTTPS"])) ? "https":"http";
			return $protocol."://".$_SERVER["HTTP_HOST"].$ret;
		} else {
			return $ret;
		}
		return "";
	}

	public function addSubsection($name, $path) {
		$path = $this->siteroot."/".trim($path, "/");
		$depth = substr_count($path, "/") - substr_count($this->siteroot, "/");
		if ($depth != 1 and $depth != 2) trigger_error("Subsection links are only permitted up to two levels below the root of the domain", E_USER_ERROR);
		if ($depth == 1 or (!empty($this->activesubsectionpath) and strpos($path, $this->activesubsectionpath."/") !== false)) {
			if ($depth == 1 and strpos($_SERVER["REQUEST_URI"], $path."/") !== false) {
				$this->activesubsectionpath = $path;
			}
			if ($name == "-") {
				$op = "&nbsp;";
			} else {
				$class = (rtrim($_SERVER["REQUEST_URI"], "/") == $path or strpos($_SERVER["REQUEST_URI"], $path."/") === 0) ? " class=\"sel\"" : "";
				$op = "<li".$class."><a href=\"".$path."/\">".$name."</a></li>";
			}
			$tag = ($depth == 1) ? "subtabs" : "sections";
			$this->add($tag, $op);
		}
		if ($depth == 2) $this->maxnavdepth = 2;
	}

}
