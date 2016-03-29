<?php
/**
 * Assanka SMS class
 *
 * Provides a wrapper class for sending SMS messages via
 * the Clickatell HTTP API (http://www.clickatell.com).
 *
 * @codingstandard ftlabs-phpcs
 * @copyright The Financial Times Limited [All Rights Reserved]
 */

namespace FTLabs\SMS;

class SMSV1 {

	private $_apiID = "223020";
	private $_username = "assanka";
	private $_password = "curtain65part";
	private $_fromname = "Assanka";
	private $_concat = false;
	private $_msgtype = false;
	private $_priority = 3;
	private $_recipients;

	public $lasterror;
	public $lastresponse;

	/**
	 * Constructor method.  Accepts three parameters
	 * that can be used to override the default login details
	 *
	 * @param string $apiID    Value to override the default API key used by the class
	 * @param string $username Value to override the authentication username used by the class
	 * @param string $password Value to override the authentication password used by the class
	 *
	 */
	function __construct($apiID = false,$username = false,$password = false) {
		if ($apiID) $this->_apiID = $apiID;
		if ($username) $this->_username = $username;
		if ($password) $this->_password = $password;
		$this->_recipients = array();
	}

	/**
	 * Private function to make an API call via file_get_contents.
	 *
	 * @param string $command Command to send to API.  Can be 'checkbal' or 'sendmsg'
	 * @param array  $params  Key/Value pairs to send to the API.
	 *
	 * @return mixed Response body on success, false on failure.
	 */
	private function _apiCall($command,$params = false) {
		switch ($command) {
			case "checkbal":
				$url = "https://api.clickatell.com/http/getbalance";
				break;
			case "sendmsg":
				$url = "https://api.clickatell.com/http/sendmsg";
				break;
			default:
				trigger_error("Unknown command",E_USER_ERROR);
				break;
		}
		$url .= "?api_id=".$this->_apiID."&user=".$this->_username."&password=".$this->_password;
		if (is_array($params) and sizeOf($params)) foreach ($params as $key => $value) {
			$url .= "&".$key."=".$value;
		}
		$response = @file_get_contents($url);
		$this->lastresponse = $response;
		if (substr($response,0,4) == "ERR:") {
			trigger_error("Clickatell error",E_USER_NOTICE);
			$this->lasterror = $response;
			return false;
		} else {
			return $response;
		}
	}

	/**
	 * Private function to actually send the message
	 *
	 * @param string $text The text to send
	 *
	 * @return int Message ID on success, false on failure
	 */
	private function _dispatch($text) {

		// Validate length, taking concat mode into account
		$msglen = strlen($text);
		$msgsrequired = 1;
		if ($msglen > 160) {
			if (!$this->_concat) {
				trigger_error("Message too long (length: ".$msglen." chars)",E_USER_ERROR);
			} elseif ($msglen < 307) {
				$msgsrequired = 2;
			} elseif ($msglen < 460) {
				$msgsrequired = 3;
			} else {
				trigger_error("Message too long",E_USER_ERROR);
			}
		}


		/* Build parameter array */

		$params = array();

		// From name
		$params["from"] = $this->_fromname;

		// Text
		$params["text"] = rawurlencode($text);

		// Recipients
		$params["to"] = "";
		foreach ($this->_recipients as $number => $v) $params["to"] .= ",".rawurlencode($number);
		$params["to"] = substr($params["to"],1);

		// Other parameters
		if (($msgsrequired > 1)) $params["concat"] = $msgsrequired;
		if ($this->_msgtype) $params["msg_type"] = $this->_msgtype;
		if ($this->_priority) $params["priority"] = $this->_priority;

		// Make API call.
		$response = $this->_apiCall("sendmsg",$params);

		// Return message ID or false
		if (preg_match("/^ID\:\s*(.*)/i",$response,$m)) return $m[1];
		else return false;
	 }

	/**
	 * Returns encoded lines for use in vCard files.
	 *
	 * @param string $string to encode
	 * @return string Encoded version of input
	 */
	function ultraLong($string) {
		$string = str_replace("\r\n","\n",$string);
		$string = str_replace("\n","\r\n",$string);
		$string = str_replace("\n","=0A",$string);
		$string = str_replace("\r","=0D",$string);
		return chunk_split($string,74,"=\r\n");
	}

	/**
	 * Returns the balance of the clickatell account associated with the current session
	 *
	 * @return mixed current balance on success, false on failure
	 */
	public function checkBal() {
		$response = $this->_apiCall("checkbal");
		if (preg_match("/^Credit\:\s*(.*)?$/i",$response,$m)) {
			return $m[1];
		} else {
			trigger_error("Unexpected response from Clickatell",E_USER_NOTICE);
			return false;
		}
	}


	/**
	 * Add recipient(s) to the message
	 *
	 * @param mixed $tel telephone number(s) of recipient(s) to add.  Should be a string if one recipient is being added, or a unidimensional array if several.
	 *
	 * @return mixed True on success, false on failure.  If array is passed, containing invalid phone numbers, invalid numbers will be skipped and the function will return false;
	 */
	public function addRecipient($tel) {
		if (is_array($tel)) {
			$allvalid = true;
			foreach ($tel as $val) {
				$outcome = $this->addRecipient($val);
				if ($allvalid) $allvalid = $outcome;
			}
			return $allvalid;
		} elseif (preg_match("/^\s*(?:\+\+)?([0-9]+)\s*$/",$tel,$m)) {
			$this->_recipients[$m[1]] = true;
			return true;
		} else {
			return false;
		}
	}

	/**
	 * Getter function to obtain an array containing all the message recipients currently stored in the SMS object
	 *
	 * @return array The keys of the array are all telephonephone numbers - values are all boolean 'true'
	 */
	public function getRecipients() {
		return $this->_recipients();
	}

	/**
	 * The clickatell API can automatically conjoin more than one message to
	 * form a long message, if the text to be sent is longer than the maximum
	 * for a single message.  The number of concatenated messages is limited
	 * to three.
	 *
	 * @param bool $mode True for 'allow conjoining', false for otherwise.
	 *
	 * @return null
	 */
	public function setConcatMode($mode=true) {
		if (!is_bool($mode)) return false;
		$this->_concat = $mode;
	}


	/**
	 * Specify whether the SMS should be displayed on the recipient's
	 * phone immediately on receipt, or if they should have to open
	 * it from their inbox in the usual manner.
	 *
	 * @param bool $mode True for 'immediate', false for 'inbox'.
	 *
	 * @return null
	 */
	public function setFlashMode($mode=true) {
		if (!is_bool($mode)) return false;
		$this->_msgtype = ($mode) ? "SMS_FLASH" : false;
	}


	/**
	 * Specify how important this message is.  If there is a message queue,
	 * more important messages are sent before less important messages
	 *
	 * @param int $priority valid values are 1,2 and 3.  1 is most important, 3 is least (and default)
	 *
	 * @return null
	 */
	public function setPriority($priority) {
		if (!is_integer($priority) or $priority < 1 or $priority > 3) return false;
		$this->_priority = $priority;
	}


	/**
	 * Send the message (as VCard)
	 *
	 * @param array $data Data to be sent.  Should be a keyed array, with keys for names of fields, and values for the data in the field.  Valid keys are: {FIRSTNAME, LASTNAME, TITLE, ORG, BDAY, NOTE, EMAIL, WORKTEL, HOMETEL, MOBILETEL, HOMEADR, WORKADR } .  FIRSTNAME and LASTNAME are required.
	 *
	 * @return mixed The ID of the message on success.
	 */
	public function sendVCard($data) {

		// Uppercase all keys in data
		$data = array_change_key_case($data,CASE_UPPER);


		/* Validate data */

		if (!isset($data["FIRSTNAME"]) or !isset($data["LASTNAME"])) return false;


		/* Build string */

		$vcard = array();

		// Add name
		$vcard["FN"] = $data["FIRSTNAME"]." ".$data["LASTNAME"];
		$vcard["N"] = $data["LASTNAME"].";".$data["FIRSTNAME"].";;;";

		// Add company, job title, birthday, misc. note, and email address
		if (isset($data["TITLE"])) $vcard["TITLE"] = $data["TITLE"];
		if (isset($data["ORG"])) $vcard["ORG"] = $data["ORG"];
		if (isset($data["BDAY"])) $vcard["BDAY"] = $data["BDAY"];
		if (isset($data["NOTE"])) $vcard["NOTE;ENCODING=QUOTED-PRINTABLE"] = $this->ultraLong($data["NOTE"]);
		if (isset($data["EMAIL"])) $vcard["EMAIL;PREF;INTERNET"] = $data["EMAIL"];

		// Add supplied phone numbers.
		if (isset($data["WORKTEL"])) $vcard["TEL;WORK;VOICE"] = $data["WORKTEL"];
		if (isset($data["HOMETEL"])) $vcard["TEL;HOME;VOICE"] = $data["HOMETEL"];
		if (isset($data["MOBILETEL"])) $vcard["TEL;WORK;CELL"] = $data["MOBILETEL"];

		// Add supplied addresses
		if (isset($data["HOMEADR"])) $vcard["ADR;HOME;ENCODING=QUOTED-PRINTABLE"] = ";;".$this->ultraLong($data["HOMEADR"]);
		if (isset($data["WORKADR"])) $vcard["ADR;WORK;ENCODING=QUOTED-PRINTABLE"] = ";;".$this->ultraLong($data["WORKADR"]);

		$text = "BEGIN:VCARD\r\nVERSION:2.1\r\n";
		foreach ($vcard as $key => $value) $text .= $key.":".$value."\r\n";
		$text .= "END:VCARD";


		/* Send message */

		$this->_msgtype = "SMS_NOKIA_VCARD";
		return $this->_dispatch($text);
	}

	/**
	 * Send the message (normal text SMS)
	 *
	 * @param string $text The message to send
	 * @return mixed The ID of the message on success, for tracking callbacks, logging, etc.  False on failure
	 */
	public function send($text) {
		return $this->_dispatch($text);
	}
}
