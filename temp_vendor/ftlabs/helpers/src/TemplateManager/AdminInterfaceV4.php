<?php
/**
 * AdminInterfaceV4
 *
 * @codingstandard ftlabs-phpcs
 * @copyright The Financial Times Limited [All rights reserved]
 */

namespace FTLabs\TemplateManager;

class AdminInterfaceV4 extends CoreInterfaceV4 {

	public function __construct($templatedir = false) {
		parent::__construct($templatedir);
	}

	public function addSection($label, $namespace, $data=false) {
		$namespace = trim($namespace, "/");
		if (!empty($data)) {
			if (!is_array($data)) trigger_error("Section data must be an array", E_USER_ERROR);
			$this->sectiondata[$namespace] = $data;
		}
		$this->apps[$namespace] = $label;
		$path = $this->siteroot."/".$namespace;
		$label = str_replace(" ", "&nbsp;", $label);
		if (rtrim($_SERVER["REQUEST_URI"], "/") == $path or strpos($_SERVER["REQUEST_URI"], $path."/") === 0) {
			$class = " class=\"current\"";
			$this->activesectionpath = $path;
			$this->activenamespace = $namespace;
		} else {
			$class = "";
		}
		$this->add("tabs", "<li".$class."><div><a href=\"".$path."/\">".$label."</a></div></li>");
	}

	public function getSections() {
		return $this->apps;
	}

	public function getSetting($key) {
		if (!$this->activenamespace or !isset($this->sectiondata[$this->activenamespace][$key])) return false;
		return $this->sectiondata[$this->activenamespace][$key];
	}

	public function getAppNamespace() {
		return $this->activenamespace;
	}

	public function getAppRootURI($includehost=true) {
		if ($approot = $this->getAppnamespace()) {
			if ($this->siteroot) {
				$ret = $this->siteroot."/".$approot."/";
			} else {
				$ret = "/".$approot."/";
			}
			if ($includehost) {
				$protocol = (!empty($_SERVER["HTTPS"])) ? "https":"http";
				return $protocol."://".$_SERVER["HTTP_HOST"].$ret;
			} else {
				return $ret;
			}
		}
		return "";
	}
	public function addSubsection($name, $path) {
		if (!$this->activesectionpath) {
			$this->alert("The requested sub-section is not available", "warning");
			header("Location: ".($this->siteroot?$this->siteroot:'/'));
			exit;
		}
		$path = $this->siteroot."/".$this->getAppNameSpace()."/".trim($path, "/");
		$depth = substr_count($path, "/") - substr_count($this->activesectionpath, "/");
		if ($depth != 1 and $depth != 2) trigger_error("Subsection links are only permitted up to two levels below the section level $depth", E_USER_ERROR);
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

	function addContent($type, $html) {
		if ($type == 'context') {
			$type = 'rightnav';
		}
		if (!isset($this->_assignedvars[$type])) {
			$this->_assignedvars[$type] = '';
		} elseif (is_array($this->_assignedvars[$type])) {
			$this->_assignedvars[$type][] = $html;
			return;
		}
		$this->_assignedvars[$type] .= $html;
	}

	function loadExternalContent($source, $additionaldata=false) {
		$str = file_get_contents($source);
		$data = $_SERVER;
		if (is_array($additionaldata)) $data = array_merge($data, $additionaldata);
		$tm = new self;
		$tm->add($data);
		return $tm->renderFromString($str);
	}
}
