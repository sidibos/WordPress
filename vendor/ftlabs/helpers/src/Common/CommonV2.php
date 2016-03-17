<?php
/**
 * Library of very useful functions
 *
 * @copyright The Financial Times Limited [All Rights Reserved]
 */

namespace FTLabs\Common;

use DOMImplementation;

use FTLabs\CSV\CSVParserV1;

class CommonV2 {

	public static function getUrlParams($delim = "/", $scriptname = false) {
		if (!$delim) $delim = " ";
		if (!$scriptname) $scriptname = $_SERVER["SCRIPT_NAME"];
		$params = trim(substr($_SERVER["REQUEST_URI"], strlen($scriptname)), $delim);
		if (($qmarkpos = strpos($params, "?")) !== false) $params = substr($params, 0, $qmarkpos);
		$params = strlen($params) ? explode($delim, trim($params)) : array();
		for ($i=(sizeof($params)-1); $i>=0; --$i) $params[$i] = urldecode($params[$i]);
		return $params;
	}

	public static function uniqueFilename($path, $name) {
		if (!file_exists($path."/".$name)) return $name;
		if (preg_match("/^(.*)(\([0-9]+\))?(\.[^\.]+)?$/Ui", $name, $matches)) {
			$extension = ((isset($matches[3]))?$matches[3]:"");
			$newname = $matches[1].$extension;
			for ($i=1; file_exists($path."/".$newname); $i++) {
				$newname = $matches[1]."(".$i.")".$extension;
			}
			return $newname;
		} else {
			return false;
		}
	}

	public static function searchhighlight($str, $keywords, $startstr="<span class=\"srhhl\">", $endstr="</span>") {
		if (is_array($keywords)) {
			foreach($keywords as $keyword) {
				$str = str_replace($keyword, $startstr.$keyword.$endstr, $str);
			}
		} else {
			$str = str_replace($keywords, $startstr.$keywords.$endstr, $str);
		}
		return $str;
	}

	public static function generatePassword() {
		$words = array('able', 'about', 'account', 'acid', 'across', 'after', 'again', 'against', 'almost', 'among', 'amount', 'angle', 'animal', 'answer', 'apple', 'arch', 'army', 'attempt', 'awake', 'back', 'balance', 'ball', 'band', 'base', 'basin', 'basket', 'bath', 'bear', 'because', 'before', 'belief', 'bell', 'berry', 'best', 'between', 'bird', 'birth', 'bite', 'bitter', 'black', 'blade', 'blood', 'blue', 'board', 'boat', 'body', 'boiling', 'book', 'bone', 'book', 'boot', 'bottle', 'brain', 'brake', 'branch', 'brass', 'bread', 'breath', 'brick', 'bridge', 'bright', 'broken', 'brother', 'brown', 'brush', 'bucket', 'bulb', 'burn', 'burst', 'butter', 'button', 'cake', 'camera', 'canvas', 'card', 'care', 'cart', 'cause', 'cave', 'certain', 'chain', 'chalk', 'chance', 'change', 'cheap', 'cheese', 'chest', 'chief', 'chin', 'church', 'circle', 'clean', 'clear', 'clock', 'cloth', 'cloud', 'coal', 'coat', 'cold', 'collar', 'comb', 'come', 'comfort', 'common', 'company', 'complex', 'control', 'cook', 'copper', 'copy', 'cord', 'cork', 'cotton', 'cough', 'country', 'cover', 'credit', 'crime', 'crush', 'current', 'curtain', 'curve', 'cushion', 'damage', 'danger', 'dark', 'dead', 'dear', 'deep', 'degree', 'demo', 'design', 'detail', 'door', 'doubt', 'down', 'drain', 'drawer', 'dress', 'drink', 'driving', 'drop', 'dust', 'early', 'earth', 'east', 'edge', 'effect', 'elastic', 'engine', 'enough', 'equal', 'error', 'even', 'event', 'ever', 'every', 'example', 'expert', 'fabric', 'face', 'fact', 'fall', 'false', 'family', 'farm', 'feather', 'feeble', 'feeling', 'fiction', 'field', 'fight', 'finger', 'fire', 'first', 'fish', 'fixed', 'flag', 'flame', 'flat', 'flight', 'floor', 'flower', 'fold', 'food', 'foolish', 'foot', 'force', 'fork', 'form', 'forward', 'fowl', 'frame', 'free', 'friend', 'from', 'front', 'fruit', 'full', 'future', 'garden', 'general', 'gift', 'girl', 'give', 'glass', 'glove', 'goat', 'gold', 'good', 'grain', 'grass', 'great', 'green', 'grey', 'grip', 'group', 'growth', 'guide', 'hair', 'hammer', 'hand', 'happy', 'harbour', 'hard', 'harmony', 'have', 'head', 'healthy', 'hear', 'hearing', 'heart', 'heat', 'help', 'high', 'history', 'hollow', 'hook', 'hope', 'horn', 'horse', 'hour', 'house', 'humour', 'idea', 'impulse', 'insect', 'iron', 'island', 'jazz', 'jelly', 'jewel', 'join', 'journey', 'judge', 'jump', 'just', 'keep', 'kettle', 'kick', 'kind', 'kiss', 'knee', 'knife', 'knot', 'land', 'last', 'late', 'laugh', 'layer', 'lead', 'leaf', 'leather', 'left', 'letter', 'level', 'library', 'light', 'lift', 'light', 'like', 'limit', 'line', 'linen', 'liquid', 'list', 'little', 'living', 'lock', 'long', 'look', 'loose', 'loss', 'loud', 'machine', 'make', 'male', 'manager', 'mark', 'market', 'married', 'mass', 'match', 'meal', 'measure', 'meat', 'medical', 'meeting', 'memory', 'metal', 'middle', 'milk', 'mind', 'mine', 'minute', 'mist', 'mixed', 'money', 'monkey', 'month', 'moon', 'moment', 'morning', 'mother', 'motion', 'move', 'much', 'muscle', 'music', 'nail', 'name', 'narrow', 'nation', 'natural', 'near', 'need', 'needle', 'nerve', 'news', 'night', 'noise', 'normal', 'north', 'nose', 'note', 'number', 'offer', 'office', 'only', 'open', 'opinion', 'orange', 'order', 'other', 'oven', 'over', 'owner', 'page', 'paint', 'paper', 'parcel', 'part', 'past', 'paste', 'payment', 'peace', 'pencil', 'penguin', 'person', 'picture', 'pipe', 'place', 'plane', 'plant', 'plate', 'play', 'please', 'plough', 'pocket', 'point', 'polish', 'poor', 'porter', 'potato', 'powder', 'power', 'present', 'price', 'print', 'prison', 'private', 'process', 'produce', 'profit', 'prose', 'public', 'pull', 'pump', 'purpose', 'push', 'quality', 'quick', 'quiet', 'quite', 'rail', 'rain', 'range', 'rate', 'reading', 'ready', 'reason', 'receipt', 'record', 'regret', 'regular', 'request', 'respect', 'rest', 'reward', 'rhythm', 'rice', 'right', 'ring', 'river', 'road', 'roll', 'roof', 'room', 'root', 'rough', 'round', 'rule', 'safe', 'sail', 'salt', 'same', 'sand', 'scale', 'school', 'science', 'seat', 'second', 'secret', 'seed', 'seem', 'self', 'send', 'sense', 'serious', 'servant', 'shade', 'shake', 'shame', 'sharp', 'sheep', 'shelf', 'ship', 'shirt', 'shock', 'shoe', 'short', 'shut', 'side', 'sign', 'silk', 'silver', 'simple', 'size', 'sleep', 'slip', 'slope', 'slow', 'small', 'smash', 'smell', 'smile', 'smoke', 'smooth', 'snake', 'sneeze', 'snow', 'soap', 'society', 'sock', 'soft', 'solid', 'some', 'song', 'sort', 'sound', 'soup', 'south', 'space', 'spade', 'special', 'sponge', 'spoon','spring', 'square', 'stage', 'stamp', 'star', 'start', 'station', 'steam', 'steel', 'stem', 'step', 'stick', 'sticky', 'still', 'stitch', 'stone', 'stop', 'store', 'story', 'strange', 'street', 'stretch', 'strong', 'such', 'sudden', 'sugar', 'summer', 'support', 'sweet', 'swim', 'system', 'table', 'tail', 'take', 'talk', 'tall', 'taste', 'test', 'than', 'that', 'then', 'theory', 'there', 'thick', 'thin', 'thing', 'this', 'thought', 'thread', 'thumb', 'thunder', 'ticket', 'till', 'time', 'tired', 'tongue', 'tooth', 'total', 'town', 'trade', 'train', 'tray', 'tree', 'trick', 'trouble', 'true', 'turn', 'twist', 'under', 'unit', 'value', 'verse', 'very', 'vessel', 'view', 'voice', 'waiting', 'walk', 'wall', 'warm', 'wash', 'waste', 'watch', 'water', 'wave', 'weather', 'week', 'weight', 'well', 'west', 'wheel', 'when', 'where', 'while', 'whip', 'whistle', 'white', 'wide', 'will', 'wind', 'window', 'wine', 'wing', 'winter', 'wire', 'wise', 'with', 'wood', 'wool', 'word', 'work', 'wound', 'writing', 'wrong', 'year', 'yellow', 'young', 'red', 'blue', 'yellow', 'green', 'purple', 'walnut', 'pebble', 'sky', 'cloud', 'laptop', 'mouse', 'keyboard', 'monitor', 'display', 'scroll', 'desktop', 'email');
		$word1 = $words[array_rand($words)];
		$word2 = $words[array_rand($words)];
		return (rand(0, 1)?$word1:ucfirst($word1)).rand(10,99).(rand(0, 1)?$word2:ucfirst($word2));
	}

	public static function generateRandomText($length=8, $type="any") {
		$op = "";
		$chars = "0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ";
		for ($i=1; $i<=$length; $i++) {
			if ($type=="hex") $op .= chr(rand(0,14));
			elseif ($type=="lcalnum") $op .= $chars[rand(0,35)];
			elseif ($type=="lcalpha") $op .= $chars[rand(10,35)];
			elseif ($type=="alpha") $op .= $chars[rand(10,61)];
			else $op .= $chars[rand(0,61)];
		}
		return $op;
	}


	public static function xmlentities($str, $quote_style = ENT_QUOTES) {

		// Encode built-in entities
		$str = htmlspecialchars($str, $quote_style, "UTF-8");

		// Remove invalid unicode characters as per
		// http://www.w3.org/TR/xml/#charsets
		$str = preg_replace("/[^\x{9}\x{A}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u", "", $str);

		return $str;
	}

	// Converts "special" characters to their ord equivalent to prevent quoting issues, or </script> tags
	// within a javascript variable breaking executing, or one vector of XSS attacks
	public static function jsentities($string) {
		static $dechexcache = array();
		$l = strlen($string);
		$returnstring = "";
		for ($i=0; $i<$l; ++$i) {
			$char = $string[$i];
			switch($char) {
				case "\r":
					$returnstring .= "\\r";
					break;
				case "\n":
					$returnstring .= "\\n";
					break;
				case "\"":
				case "'":
				case "<":
				case ">":
				case "&":
					if (empty($dechexcache[$char])) $dechexcache[$char] = '\x'.dechex(ord($char));
					$returnstring .= $dechexcache[$char];
				break;
				default:
					$returnstring .= $char;
					break;
			}
		}
		return $returnstring;
	}

	public static function generateCSV($data, $delim=",", $escape="\"", $lineend="\n", $enclose="\"") {

		// Check the supplied parameters
		if (empty($enclose) and !empty($escape)) {
			trigger_error("The escape character is used to escape the enclose character, so if you do not specify an enclose character you cannot specify an escape character", E_USER_NOTICE);
		}

		// Build an return the CSV
		$op = "";
		foreach($data as $row) {
			foreach($row as $colkey=>$col) {
				$val = (empty($escape)?$col:str_replace($enclose, $escape.$enclose, $col));
				$row[$colkey] = $enclose.$val.$enclose;
			}
			$op .= join($delim, $row).$lineend;
		}
		return $op;
	}

	/**
	 * Parse a supplied CSV string using the Assanka CSV parser class.
	 */
	public static function parseCSV($str, $f_delim = ',', $r_delim = "\n", $qual = '"') {
		$parser = new CSVParserV1();
		$parser->setFieldDelimiter($f_delim);
		$parser->setRowDelimiter($r_delim);
		$parser->setFieldQuoteString($qual);
		$parser->setCSVString($str);
		return $parser->getRows();
	}

	public static function xmlvalidatestring(&$xmlstring) {
		trigger_error("This function is deprecated.  Use Validator::isXML()", E_USER_NOTICE);
		$impl = new DOMImplementation();
		$doc = $impl->createDocument();
		@$doc->loadXML($xmlstring, LIBXML_DTDLOAD);
		return @$doc->validate();
	}

	public static function xmlvalidatehtmlfragment($xmlstring, $doctype='<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN""http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd" >') {
		trigger_error("This function is deprecated.  Use Validator::isXHTMLFragment()", E_USER_NOTICE);
		$contents = $doctype."<html><head><title>Test fragment</title></head><body>".$xmlstring."</body></html>";
		return self::xmlvalidatestring($contents);
	}


	/**
	 * Function to parse a supplied XML string and return it as an array.  Supports namespaces, and easy
	 * to use as it is an array, but for large XML files expect parsing the full XML string to take longer
	 * than accessing individual elements via SimpleXML!
	 *
	 * The array returned will use a fixed format for each XML node, including the parent, with the following
	 * array items:
	 *  - "type" - the node name
	 *  - "attributes" - an associative array of all attributes on the node.  Not present if empty.
	 *  - "children" - a numerically indexed array of all child nodes.  Not present if no child nodes.
	 *  - "value" - a string containing the text or data within the node, if any.  This is not trimmed,
	 *              but not set if the node is empty.
	 *
	 * Child nodes are also made available directly from the parent node as a shortcut; always via their
	 * numeric indices, so $array["parentnode"][0] links directly to the first child, and where the child
	 * node name doesn't conflict with the four reserved variables above, it will also be available via
	 * that key. Similarly, attributes are available via keys of that name if they do not conflict.
	 *
	 * So for the XML: <parent><child foo="bar">text </child></parent>
	 * the text can be accessed consistently via $array["parent"]["children"][0]["value"], or via
	 * the shortcuts $array["parent"][0]["value"] and $array["parent"]["child"]["value"]; the attribute
	 * can be accessed consistently via $array["parent"]["children"][0]["attributes"]["foo"], or
	 * via the shortcut $array["parent"]["child"]["foo"].
	 *
	 * @param string $xmlstring A string containing the XML to parse.
	 *
	 * @return array An array containing the parsed XML, or false if unable to parse the XML.
	 */
	public static function xml2array(&$xmlstring) {

		// Load in the XML file via SimpleXML.
		$xml = @simplexml_load_string($xmlstring, 'SimpleXMLElement', LIBXML_NOCDATA);
		if (!$xml) return false;
		
		// Load any namespaces present within the document
		$namespaces = $xml->getNamespaces(true);
		foreach ($namespaces as $nskey=>$ns) {
			if ($nskey == '') unset($namespaces[$nskey]);
		}

		// Process the master node recusively
		$dataArray = self::_processXMLNode($xml, $namespaces);
		
		unset($xml);
        return $dataArray;
	}

	/**
	 * Private function to parse a node, as used by xml2array.
	 */
	private static function _processXMLNode($xml, &$namespaces, $currentns = false) {

		// Set up node, including name.
		$node = array('type'=>(($currentns)?$currentns.':':'').$xml->getName());

		// Add attributes
		$nodeatts = $xml->attributes();
		if (count($nodeatts)) {
			$node['attributes'] = array();
			foreach ($nodeatts as $name=>$nodeatt) $node['attributes'][$name] = (string)$nodeatt;
		}

		// Cope with namespaced attributes
		foreach ($namespaces as $nskey=>$ns) {
			$nodeatts = $xml->attributes($ns);
			if (count($nodeatts)) {
				if (!isset($node['attributes'])) $node['attributes'] = array();
				foreach ($nodeatts as $nodeatt) $node['attributes'][] = (string)$nodeatt;
			}
		}

		// Add any children into a children array for constant iteration
		$nodechildren = $xml->children();
		$numnodechildren = count($nodechildren);
		if ($numnodechildren) {
			$node['children'] = array();

			// COMPLEX:RB:20100702: Up to Bzr revision 2133, we were using a foreach here; however the
			// foreach sometimes resulted into partial repetition (!) so a for() muse be used instead 
			for ($nodecounter = 0; $nodecounter < $numnodechildren; $nodecounter++) {
			   $node['children'][] = self::_processXMLNode($nodechildren[$nodecounter], $namespaces);
			}
		}

		// Cope with namespaced children
		foreach ($namespaces as $nskey=>$ns) {
			$nodechildren = $xml->children($ns);
			if (count($nodechildren)) {
				if (!isset($node['children'])) $node['children'] = array();
				foreach ($nodechildren as $childnode) $node['children'][] = self::_processXMLNode($childnode, $namespaces, $nskey);
			}
		}

		// Get the text/data value of the node, if any.
		$nodevalue = (string)$xml[0];
		if ($nodevalue !== '' and (empty($node['children']) or trim($nodevalue) !== '')) $node['value'] = $nodevalue;
					
		// Remap children by reference where it seems possible to do so
		if (!empty($node['children'])) for ($i = 0; $i < count($node['children']); $i++) {
			$node[$i] = &$node['children'][$i];
			$nodename = $node[$i]['type'];
			if (!isset($node[$nodename])) $node[$nodename] = &$node['children'][$i];
		}

		// Remap attributes by reference where it seems possible to do so
		if (!empty($node['attributes'])) foreach ($node['attributes'] as $key=>$value) {
			if (!isset($node[$key])) $node[$key] = &$node['attributes'][$key];
		}

		// Clean up and return.
		unset($nodechildren, $nodeatts, $nodevalue, $xml);	
		return $node;
	}

	public static function convertHumanTime($date) {
		if (is_numeric($date)) return $date;
		$days = '0?[1-9]|[12][0-9]|3[01]';
		$months = '0?[1-9]|1[0-2]';
		$year = '\d\d|\d\d\d\d';
		$sep = '[\/\-\.\\\,]';
		if (preg_match("/\b($year)\-($months)\-($days)(\s*(\d\d):(\d\d):(\d\d))?\b/", $date, $d)) {
			if (!isset($d[5]) or !is_numeric($d[5])) $d[5] = 0;
			if (!isset($d[6]) or !is_numeric($d[6])) $d[6] = 0;
			if (!isset($d[7]) or !is_numeric($d[7])) $d[7] = 0;
			$date = mktime($d[5], $d[6], $d[7], $d[2], $d[3], $d[1]);
		} elseif (preg_match("/\b(($days)($sep))?($months)($sep)($year)\b/", $date, $d)) {
			if (!preg_match("/\b(\d{1,2})(\:(\d\d)(\:(\d\d))?)\s*(am|pm)?\b/i", $date, $t)) $t = array(0,0,0,0,0,0,0);
			if (!isset($d[2]) or !is_numeric($d[2])) $d[2] = 0;
			if (!isset($t[3]) or !is_numeric($t[3])) $t[3] = 0;
			if (!isset($t[5]) or !is_numeric($t[5])) $t[5] = 0;
			if (!empty($t[6]) and  $t[6]==="pm") $t[1] += 12;
			$date = mktime($t[1], $t[3], $t[5], $d[4], $d[2], $d[6]);
		} elseif (trim(strtolower($date)) == 'now') {
			$date = time();
		} elseif (strlen($date)>4) {
			$date = strtotime($date);
		} else {
			$date = false;
		}

		// Note that dates earlier than 1970 are returned as negative numbers.  Because versions of PHP earlier than
		// 5.1.0 return -1 as a failure code from strtotime(), it is impossible to represent the date 31 Dec 1969 23:59:59,
		// except during the summer, when there is a DST gap between 31 Dec and the current date, when it becomes
		// impossible to represent 31 Dec 1969 22:59:59.
		return (is_integer($date) and $date != -1) ? $date : false; 
	}
	
	// Function to detect whether UTF-8 is present in a string - from comment by chris AT w3style.co DOT uk
	// on http://www.php.net/mb_detect_encoding
	public static function detectUTF8($string) {
		return preg_match('%(?:
		[\xC2-\xDF][\x80-\xBF]        # non-overlong 2-byte
		|\xE0[\xA0-\xBF][\x80-\xBF]               # excluding overlongs
		|[\xE1-\xEC\xEE\xEF][\x80-\xBF]{2}      # straight 3-byte
		|\xED[\x80-\x9F][\x80-\xBF]               # excluding surrogates
		|\xF0[\x90-\xBF][\x80-\xBF]{2}    # planes 1-3
		|[\xF1-\xF3][\x80-\xBF]{3}                  # planes 4-15
		|\xF4[\x80-\x8F][\x80-\xBF]{2}    # plane 16
		)+%xs', $string);
	}
	
	// Function to prevent cross-site scripting attacks by adding <span></span> tags to dangerous tags
	// and attributes; also prevents non-printable characters, linebreaks, and character encodings
	// from being used to attempt to get around similar sets.
	// This function is adapted from http://quickwired.com/smallprojects/php_xss_filter_function.php
	// (which is in the public domain).
	public static function removeXSS($string) {
		
		// Remove all non-printable characters. CR(0a) and LF(0b) and TAB(9) are allowed;
		// this prevents some character re-spacing such as <java\0script>.  Note that splits
		// with \n, \r, and \t are handled later since they *are* allowed in some inputs.
		$string = preg_replace('/([\x00-\x08,\x0b-\x0c,\x0e-\x19])/', '', $string);

		// Replace encoded "normal" characters with the normal characters themselves.  Standard
		// text shouldn't be using encoded text for all characters anyway, and this prevents tags
		// like <IMG SRC=&#X40&#X61&#X76&#X61&#X73&#X63&#X72&#X69&#X70&#X74&#X3A&#X61
		// &#X6C&#X65&#X72&#X74&#X28&#X27&#X58&#X53&#X53&#X27&#X29 />
		$search = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ'; 
		$search .= '1234567890!@#$%^&*()~`";:?+/={}[]-_|\'\\'; 
		for ($i = 0; $i < strlen($search); $i++) {
		
			// ;? at the end matches the ;, which is optional.
			// 0{0,8} matches any padded zeros, which are optional and go up to 8 chars

			// Replace hex-encoded characters with their text equivalent
			$string = preg_replace('/(&#[xX]0{0,8}'.dechex(ord($search[$i])).';?)/i', $search[$i], $string);

			// Replace ord/long-encoded ASCII characters with their text equivalent
			$string = preg_replace('/(&#0{0,8}'.ord($search[$i]).';?)/', $search[$i], $string);
		}

		// By now the only remaining whitespace attacks are \t, \n, and \r - dealt with in $pattern.
		$ra1 = Array('javascript', 'vbscript', 'expression', 'applet', 'meta', 'xml', 'blink', 'link', 'style', 'script', 'embed', 'object', 'iframe', 'frame', 'frameset', 'ilayer', 'layer', 'bgsound', 'title', 'base');
		$ra2 = Array('onabort', 'onactivate', 'onafterprint', 'onafterupdate', 'onbeforeactivate', 'onbeforecopy', 'onbeforecut', 'onbeforedeactivate', 'onbeforeeditfocus', 'onbeforepaste', 'onbeforeprint', 'onbeforeunload', 'onbeforeupdate', 'onblur', 'onbounce', 'oncellchange', 'onchange', 'onclick', 'oncontextmenu', 'oncontrolselect', 'oncopy', 'oncut', 'ondataavailable', 'ondatasetchanged', 'ondatasetcomplete', 'ondblclick', 'ondeactivate', 'ondrag', 'ondragend', 'ondragenter', 'ondragleave', 'ondragover', 'ondragstart', 'ondrop', 'onerror', 'onerrorupdate', 'onfilterchange', 'onfinish', 'onfocus', 'onfocusin', 'onfocusout', 'onhelp', 'onkeydown', 'onkeypress', 'onkeyup', 'onlayoutcomplete', 'onload', 'onlosecapture', 'onmousedown', 'onmouseenter', 'onmouseleave', 'onmousemove', 'onmouseout', 'onmouseover', 'onmouseup', 'onmousewheel', 'onmove', 'onmoveend', 'onmovestart', 'onpaste', 'onpropertychange', 'onreadystatechange', 'onreset', 'onresize', 'onresizeend', 'onresizestart', 'onrowenter', 'onrowexit', 'onrowsdelete', 'onrowsinserted', 'onscroll', 'onselect', 'onselectionchange', 'onselectstart', 'onstart', 'onstop', 'onsubmit', 'onunload');
		$ra = array_merge($ra1, $ra2);

		// Keep replacing as long as the previous round replaced something
		$found = true; 
		while ($found == true) {
			$string_before = $string;
			for ($i = 0; $i < sizeof($ra); $i++) {
			
				// Build up a pattern which includes optional \t, \n, and \r between each word.
				$pattern = '/';
				for ($j = 0; $j < strlen($ra[$i]); $j++) {
					if ($j > 0) {
						$pattern .= '((&#[xX]0{0,8}([9ab]);)|(&#0{0,8}([9|10|13]);))*';
					}
					$pattern .= $ra[$i][$j];
				}
				$pattern .= '/i';
				
				// Replace any found instances with <span></span> - this is invisible if plain text
				// has been mistakenly identified, but breaks up tags or attributes.
				$replacement = substr($ra[$i], 0, 2).'<span></span>'.substr($ra[$i], 2);
				$string = preg_replace($pattern, $replacement, $string); 

				// If no replacements were made, exit the loop 
				if ($string_before == $string) {
					$found = false;
				}
			}
		}
		return $string;
	}
	

	// Detect whether two variables are referencing the same thing
	public static function is_ref(&$a, &$b) {
		if (gettype($a) !== gettype($b)) return false;

		$same = false;
		if (is_array($a)){

			// Look for an unused index in $a
			$key = uniqid("is_ref_", true);
			while(isset($a[$key]))$key = uniqid("is_ref_", true);

			// The two variables differ in content ... They can't be the same
			if(isset($b[$key])) return false;

			// The arrays point to the same data if changes are reflected in $b
			$data = uniqid("is_ref_data_", true);
			$a[$key] =& $data;
			// There seems to be a modification ...
			$same = ((isset($b[$key])) and ($b[$key] === $data));

			// Undo our changes ...
			unset($a[$key]);

		} elseif (is_object($a)) {

			// The same objects are required to have equal class names ;-)
			if(get_class($a) !== get_class($b)) return false;

			// Look for an unused property in $a
			$key = uniqid("is_ref_", true);
			while(isset($a->$key))$key = uniqid("is_ref_", true);

			// The two variables differ in content ... They can't be the same
			if(isset($b->$key)) return false;

			// The arrays point to the same data if changes are reflected in $b
			$data = uniqid("is_ref_data_", true);
			$a->$key =& $data;
			// There seems to be a modification ...
			$same = ((isset($b->$key)) and ($b->$key === $data));

			// Undo our changes ...
			unset($a->$key);

		} elseif (is_resource($a)){

			if (get_resource_type($a) !== get_resource_type($b))return false;
			$same = ((string) $var1) === ((string) $var2);

		} else {

			if($a !== $b) return false;

			// To check for a reference of a variable with simple type
			// simply store its old value and check against modifications of the second variable ;-)

			$data = uniqid("is_ref_", true);
			while($data === $a) $data = uniqid("is_ref_", true);

			$save = $a;             //WE NEED A COPY HERE!!!
			$a    = $data;          //Set a to the value of $data (copy)
			$same = ($a === $b);    //Check if $var2 was modified too ...
			$a    = $save;          //Undo our changes ...

		}
		return $same;
	}

	public static function mimeType($path) {
		return shell_exec("file -ib ".escapeshellarg($path));
	}

	// Finds a unique slug for a word or group of words
	public static function createSlug($origstring, $spacechar = ".", $db = false, $table=false, $slugfield = "slug") {

		// First utf8_decode the string to ISO-8859-1, and proceed by replacing diacritics
		// with their closest equivalent ASCII longhand and western accented characters
		// with their closest ASCII equivalents.
		$string = utf8_decode($origstring);
		$translatechars = array("\xA9"=>"c", "\xAA"=>"a", "\xAE"=>"r", "\xB5"=>"u", "\xBA"=>"o", "\xC0"=>"A", "\xC1"=>"A", "\xC2"=>"A", "\xC3"=>"A", "\xC4"=>"Ae", "\xC5"=>"A", "\xC6"=>"AE", "\xC7"=>"C", "\xC8"=>"E", "\xC9"=>"E", "\xCA"=>"E", "\xCB"=>"E", "\xCC"=>"I", "\xCD"=>"I", "\xCE"=>"I", "\xCF"=>"I", "\xD0"=>"D", "\xD1"=>"N", "\xD2"=>"O", "\xD3"=>"O", "\xD4"=>"O", "\xD5"=>"O", "\xD6"=>"Oe", "\xD8"=>"O", "\xD9"=>"U", "\xDA"=>"U", "\xDB"=>"U", "\xDC"=>"Ue", "\xDD"=>"Y", "\xDE"=>"TH", "\xDF"=>"ss", "\xE0"=>"a", "\xE1"=>"a", "\xE2"=>"a", "\xE3"=>"a", "\xE4"=>"ae", "\xE5"=>"a", "\xE6"=>"ae", "\xE7"=>"c", "\xE8"=>"e", "\xE9"=>"e", "\xEA"=>"e", "\xEB"=>"e", "\xEC"=>"i", "\xED"=>"i", "\xEE"=>"i", "\xEF"=>"i", "\xF0"=>"d", "\xF1"=>"n", "\xF2"=>"o", "\xF3"=>"o", "\xF4"=>"o", "\xF5"=>"o", "\xF6"=>"oe", "\xF8"=>"o", "\xF9"=>"u", "\xFA"=>"u", "\xFB"=>"u", "\xFC"=>"ue", "\xFD"=>"y", "\xFE"=>"th", "\xFF"=>"y", "\x80"=>" ", "\xA0"=>" ");
		$string = strtr($string, $translatechars);
		
		// Replace ampersands with the word 'and'
		$string = str_replace("&", "and", $string);
	
		// Replace spaces with the supplied space character and convert the string to lowercase
		$string = trim($string);
		$string = preg_replace("/\s+/", $spacechar, $string);
		$string = strtolower($string);
		
		// Strip any remaining characters which could cause problems in a URL
		// This will limit the range of $spacechar a bit and may require updating.
		$string = preg_replace("/[^a-z0-9\.\-\_]+/", "", $string);
		
		// Remove the space character from start or end of the string, along with any full stops or dashes.
		$string = trim($string, ".-".$spacechar);

		// Collapse multiple space characters to a single one
		$string = preg_replace("/(".preg_quote($spacechar).")+/i", $spacechar, $string);

		// Truncate to 95 characters
		if (strlen($string) > 95) $string = substr($string, 0, 95);

		// If resulting slug is less than 10 characters long and less than half the length of the original slug, due to removal of non-ASCII characters, generate an alternative slug by URLencoding the first 30 chars of the original.
		if (strlen($string) < 10 and strlen($origstring) > (strlen($string)*2)) {
			$string = rawurlencode(substr($origstring, 0, 30));
		}

		$baseslug = $slug = $string;

		// If a database and table has been specified, make the slug unique within that table
		if ($db and $table) {
			for ($i = 2; $db->querySingle("SELECT 1 FROM `$table` WHERE `$slugfield` = %s", $slug); $i++) {
				$slug = $baseslug.$spacechar.$i;
			}
		}
		return $slug;
	}

	public static function dispBytes($num) {
		if ($num > 1099511627776) {
			if ($num/1099511627776 >= 100) {
				return number_format($num/1099511627776)." TB";
			} else {
				return number_format($num/1099511627776, 1)." TB";
			}
		} elseif ($num > 1073741824) {
			if ($num/1073741824 >= 100) {
				return number_format($num/1073741824)." GB";
			} else {
				return number_format($num/1073741824, 1)." GB";
			}
		} elseif ($num > 1048576) {
			if ($num/1048576 >= 100) {
				return number_format($num/1048576)." MB";
			} else {
				return number_format($num/1048576, 1)." MB";
			}
		} elseif ($num > 1024) {
			return number_format($num/1024)." KB";
		} else {
			return $num." B";
		}
	}

	/**
	 * Formats a number of seconds into a human friendly string
	 *
	 * @param    int       $in        The number of seconds to format
	 * @param    int       $limit     Number of units to limit the output to (0 for no limit)
	 * @param    string    $sep       String to separate units by
	 * @return   string               e.g. "4 days, 13 hours"
	 */
	public static function secondsh($in, $limit = 0, $sep = ', ') {
		$units = array('second', 'minute', 'hour', 'day', 'week', 'month', 'year');
		$factors = array(1, 60, 3600, 86400, 604800, 2629746, 31556952);
		for ($i = 7, $bal = 0; $i--;) {
			$cur = floor(($in - $bal) / $factors[$i]);
			if (!$cur && ($i || $in)) {
				continue;
			}
			$bal += $cur * $factors[$i];
			$ret[] = $cur . ' ' . $units[$i] . ($cur != 1 ? 's' : '');
		}
		if ($limit) {
			$ret = array_slice($ret, 0, $limit);
		}
		return implode($sep, $ret);
	}
}
