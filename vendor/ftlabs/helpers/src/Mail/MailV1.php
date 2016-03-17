<?php
/**
 * Validates registration data and creates a user account
 *
 * @codingstandard ftlabs-phpcs
 * @copyright The Financial Times Limited [All Rights Reserved]
 */

namespace FTLabs\Mail;

use FTLabs\Logger;
use FTLabs\TemplateManager\TemplateManagerV4;

class MailV1 extends TemplateManagerV4 {

	const PRIORITY_NORMAL = 3;
	const PRIORITY_HIGH = 1;
	const PRIORITY_LOW = 5;
	const MODE_BUILTIN = 1;
	const MODE_TEST = 3;

	private $_subject = false;
	private $_recipients = array();
	private $_ccrecipients = array();
	private $_bccrecipients = array();
	private $_from = array();
	private $_bounceaddress = array('name' => 'Financial Times', 'email' => 'help@ft.com');
	private $_text = false;
	private $_html = false;
	private $_autocreatetext = true;
	private $_headers = array();
	private $_attachments = array();
	private $_priority = false;
	private $_mode = self::MODE_BUILTIN;
	private $_logger = null;
	private static $_overwriterecipients = null;

	function __construct($templateDirectory = null) {
		parent::__construct($templateDirectory);
		$this->_logger = new Logger('assanka-mail');
	}

	/**
	 * Function to compose and send the email.  Will fail if there is not at least a recipient and
	 * some text to send, although some details can be provided as part of this call.
	 *
	 * @param string $to      Email address - optionally including name - that this email should be send to.  Setting this is equivalent to calling clearRecipients and addRecipient with the email address.
	 * @param string $from    Email address - optionally including name - to send this email from.  This will overwrite any email address provided via setFrom if valid.
	 * @param string $text    The plain-text content of the email to send.  This will clear any HTML part and replace any text part if provided, and will be parsed as a template before sending.
	 * @param string $subject The plain text subject string.  Will overwrite any previously provided subject if provided.
	 *
	 * @return integer Returns the number of emails sent.
	 */
	function send($to = null, $from = null, $text = null, $subject = null) {
        if (class_exists('PHPUnit_Framework_TestCase')) $this->_mode = self::MODE_TEST;

		// Initially process the supplied variables if set
		if ($to !== null) {
			$this->clearRecipients();
			if (!$this->addRecipient($to)) return false;
		}
		if ($from !== null) {
			$this->_from = array();
			if (!$this->setFrom($from)) return false;
		}
		if ($text !== null) {
			$this->_text = false;
			$this->_html = false;
			if (!$this->setText($text, false)) return false;
		}
		if ($subject !== null) {
			$this->_subject = false;
			if (!$this->setSubject($subject)) return false;
		}

		// Basic error catching - require to and text.
		if (!count($this->_recipients) or (!$this->_text and !$this->_html)) return false;

		// Set up mime headers...
		$mime1 = '==MultipartBoundaryOne_'.md5(time() + rand());
		$mime2 = '==MultipartBoundaryTwo_'.md5(time() + rand());

		// Put together additional headers for this message
		$messageheaders = $this->_headers;

		// Add all the BCC recipients
		if (empty($messageheaders['CC'])) $messageheaders['CC'] = array();
		foreach ($this->_ccrecipients as $cc) {
			if (!empty($cc['name'])) {
				if (preg_match('/[^\x01-\x21\x23-\x7E]/', $cc['name'])) {
					$cc['name'] = self::encodeQuotedPrintable($cc['name'], true);
				} else {
					$cc['name'] = '"'.$cc['name'].'"';
				}
				$messageheaders['CC'][] = $cc['name'].' <'.$cc['email'].'>';
			} else {
				$messageheaders['CC'][] = $cc['email'];
			}
		}

		// Add any BCC recipients
		if (empty($messageheaders['BCC'])) $messageheaders['BCC'] = array();
		foreach ($this->_bccrecipients as $bcc) {
			if (!empty($bcc['name'])) {
				if (preg_match('/[^\x01-\x21\x23-\x7E]/', $bcc['name'])) {
					$bcc['name'] = self::encodeQuotedPrintable($bcc['name'], true);
				} else {
					$bcc['name'] = '"'.$bcc['name'].'"';
				}
				$messageheaders['BCC'][] = $bcc['name'].' <'.$bcc['email'].'>';
			} else {
				$messageheaders['BCC'][] = $bcc['email'];
			}
		}

		// Add the From details and address
		if ($this->_from) {
			if (empty($messageheaders['From'])) $messageheaders['From'] = array();
			$from = $this->_from;
			if (!empty($from['name'])) {
				if (preg_match('/[^\x01-\x21\x23-\x7E]/', $from['name'])) {
					$from['name'] = self::encodeQuotedPrintable($from['name'], true);
				} else {
					$from['name'] = '"'.$from['name'].'"';
				}
				$messageheaders['From'][] = $from['name'].' <'.$from['email'].'>';
			} else {
				$messageheaders['From'][] = $from['email'];
			}
		}

		// Add a priority if set
		if ($this->_priority) {
			switch ($this->_priority) {
				case self::PRIORITY_HIGH:
					$messageheaders['Importance'] = array('High');
					$messageheaders['X-MSMail-Priority'] = array('High');
					$messageheaders['X-Priority'] = array('1 (Highest)');
					break;
				case self::PRIORITY_LOW:
					$messageheaders['Importance'] = array('Low');
					$messageheaders['X-MSMail-Priority'] = array('Low');
					$messageheaders['X-Priority'] = array('5 (Lowest)');
					break;
			}
		}

		// As a special case for text-only emails, construct the emails in simple form:
		if ($this->_text and !$this->_html and !count($this->_attachments)) {
			$output = $this->renderFromString($this->_text);
			if (preg_match('/[^\x01-\x7E]/', $output)) {
				$messageheaders['Content-Type'] = array('text/plain; charset=UTF-8');
				$messageheaders['Content-Transfer-Encoding'] = array('base64');
				$output = chunk_split(base64_encode($output), 76, "\n");
			} else {
				$messageheaders['Content-Type'] = array('text/plain; charset=iso-8859-1; format=flowed');
				if ($this->_mode == self::MODE_TEST) $output = htmlspecialchars($output);
				$this->_wrapTextForEmailing($output);
			}
		} else {

			// Construct the HTML part of this message
			$htmloutput = $textoutput = false;
			if ($this->_html) {
				$htmloutput = $this->renderFromString($this->_html);

				// Derive the text version if none has been provided
				if ($this->_autocreatetext and !$this->_text) {
					$textoutput = str_replace(array("\r", "\n"), '', $htmloutput);
					$textoutput = preg_replace('/\<\/p\>/i', "\n\n", $textoutput);
					$textoutput = preg_replace('/\<br\s*\\/?>/i', "\n", $textoutput);
					$textoutput = preg_replace('/\<h[0-9]\>/i', "\n\n", $textoutput);
					$textoutput = preg_replace('/\<\/h[0-9]\>/i', "\n".str_pad('', 40, '*')."\n\n", $textoutput);
					$textoutput = html_entity_decode($textoutput, ENT_QUOTES, 'UTF-8');
					$textoutput = strip_tags($textoutput);
				}
			}

			// Construct the text part of this message
			if ($this->_text) {
				$textoutput = $this->renderFromString($this->_text);
			}

			// Put together the message body
			$output = 'This is a multipart message in MIME format.'."\n\n";
			if (count($this->_attachments)) {
				$output .= '--'.$mime2."\n";
				$output .= 'Content-Type: multipart/alternative; boundary="'.$mime1.'"'."\n\n";
			}

			// Add the text section, encoded appropriately
			if ($textoutput) {
				$output .= '--'.$mime1."\n";
				if (preg_match('/[^\x01-\x7E]/', $textoutput)) {
					$output .= 'Content-Type: text/plain; charset="UTF-8"'."\n";
					$output .= 'Content-Transfer-Encoding: base64'."\n\n";
					$output .= chunk_split(base64_encode($textoutput), 76, "\n");
				} else {
					$output .= 'Content-Type: text/plain; charset="iso-8859-1"; format=flowed'."\n\n";
					$this->_wrapTextForEmailing($textoutput);
					if ($this->_mode == self::MODE_TEST) $textoutput = htmlspecialchars($textoutput);
					$output .= $textoutput;
				}
				if (substr($textoutput, -1) != "\n") $output .= "\n";
			}

			// Add the html section, encoded appropriately
			if ($htmloutput) {
				$output .= '--'.$mime1."\n";
				if (preg_match('/[^\x01-\x7E]/', $htmloutput)) {
					$output .= 'Content-Type: text/html; charset="UTF-8"'."\n";
					$output .= 'Content-Transfer-Encoding: base64'."\n\n";
					$output .= chunk_split(base64_encode($htmloutput), 76, "\n");
				} else {
					$output .= 'Content-Type: text/html; charset="iso-8859-1"; format=flowed'."\n\n";
					$this->_wrapTextForEmailing($htmloutput);
					$output .= $htmloutput;
				}
				if (substr($htmloutput, -1) != "\n") $output .= "\n";
			}
			$output .= '--'.$mime1."--\n";

			// Add any attachments previously specified
			if (count($this->_attachments)) {
				foreach ($this->_attachments as $attachment) {
					$output .= '--'.$mime2."\n";
					$output .= 'Content-Type: '.$attachment['mime'].';'."\n";
					$output .= ' name="'.$attachment['name'].'"'."\n";
					$output .= 'Content-Transfer-Encoding: base64'."\n\n";
					$output .= chunk_split(base64_encode($attachment['data']), 76, "\n")."\n\n";
				}
				$output .= '--'.$mime2.'--'."\n";
			}

			// Finally set the MIME headers for this message
			$messageheaders['MIME-Version'] = array('1.0');
			if (count($this->_attachments)) {
				$messageheaders['Content-Type'] = array('multipart/mixed;'."\n".' boundary="'.$mime2.'"');
			} else {
				$messageheaders['Content-Type'] = array('multipart/alternative;'."\n".' boundary="'.$mime1.'"');
			}
		}

		// Collapse the header arrays into a header string
		$messageheaderstring = array();
		foreach ($messageheaders as $name => $value) {
			foreach ($value as $header) $messageheaderstring[] = $name.': '.$header;
		}
		$messageheaderstring = implode("\n", $messageheaderstring);

		$successcount = 0;

		// Send the email[s]
		foreach ($this->_recipients as $recipient) {

			// Encode the To: address and subject line appropriately
			if (!empty($recipient['name'])) {
				if (preg_match('/[^\x01-\x21\x23-\x7E]/', $recipient['name'])) {
					$recipient['name'] = self::encodeQuotedPrintable($recipient['name'], true);
				} else {
					$recipient['name'] = '"'.$recipient['name'].'"';
				}
				$recipient['to'] = $recipient['name'].' <'.$recipient['email'].'>';
			} else {
				$recipient['to'] = $recipient['email'];
			}
			if (preg_match('/[^\x20-\x7E]/', $this->_subject)) {
				$subject = self::encodeQuotedPrintable($this->_subject);
			} else {
				$subject = $this->_subject;
			}

			// Send!
			switch ($this->_mode) {
				case self::MODE_BUILTIN:
					if (mail($recipient['to'], $subject, $output, $messageheaderstring, '-f '.$this->_bounceaddress['email'])) $successcount++;
				break;
				case self::MODE_TEST:
					$op = array('to' => $recipient['to'], 'subject' => $subject, 'headers' => $messageheaderstring, 'body' => $output);

					// COMPLEX:SG:201207071606: Instead of returning 'SuccessCount' When in test mode (it will never fail anyway).  Return an array of Json objects for each "success"
					if (is_array($successcount)) {
						$successcount[] = $op;
					} else {
						$successcount = array(0 => $op);
					}
				break;
			}
			$this->_logger->info('', array(
				'to' => $recipient['to'],
				'fr' => $from['email'],
				'subj' => $subject,
				'mode' => $this->_mode,
			));
		}

		return $successcount;
	}

	/**
	 * Setter function to amend the subject line
	 *
	 * @param string $subject the subject to apply for emails.
	 *
	 * @return bool Whether the set was successful.
	 */
	public function setSubject($subject) {
		if (strlen($subject) == 0) return false;

		$this->_subject = $this->_injectProtect($subject);

		return true;
	}

	/**
	 * Getter function to access the current subject line
	 *
	 * @return string The current subject line, or false if none has been set
	 */
	public function getSubject() {
		return $this->_subject;
	}

	/**
	 * Clears the current recipient list.
	 *
	 * @return void
	 */
	public function clearRecipients() {
		$this->_recipients = array();
	}

	/**
	 * Add a recipient to the list of those to be mailed.
	 *
	 * @param string $email The email address to be added.  This may be a fully RFC-formatted email address including the name, such as "Rowan" <r@r.r>, in which case the name will be extracted and used.
	 * @param string $name  (Optional) The name to use in the headers when constructing the email to send - this will overwrite any extracted from the email param if supplied
	 *
	 * @return boolean indicator of success; a false indicates an email failed to validate.
	 */
	public function addRecipient($email, $name = false) {
		return $this->_addEmailToList($email, $name, $this->_recipients);
	}

	/**
	 * Add multiple recipients to the list of those to be emailed.
	 *
	 * @param array $recipients The email addresses to be added.  These may be an array of RFC-formatted email addresses, in which case the name will also be extracted.  If the key of an array item is not numeric, it will be treated as a name and later used to construct the email address in the headers.
	 *
	 * @return integer Returns the number of email addresses that passed validation.
	 */
	public function addRecipients($recipients) {
		$added = 0;

		foreach ($recipients as $name => $email) {
			if (is_numeric($name)) $name = false;
			if ($this->_addEmailToList($email, $name, $this->_recipients)) $added++;
		}

		return $added;
	}

	/**
	 * Clears the current carbon-copy user list.
	 *
	 * @return void
	 */
	public function clearCCs() {
		$this->_ccrecipients = array();
	}

	/**
	 * Add a carbon copy user to the list of those to be mailed.  Each carbon copy user will be mailed
	 * whenever each user is mailed, so a list of five recipients and two carbon copy addresses will result
	 * in each carbon copy recipient being emailed five times.
	 *
	 * @param string $email The email address to be added.  This may be a fully RFC-formatted email address including the name, such as "Rowan" <r@r.r>, in which case the name will be extracted and used.
	 * @param string $name  (Optional) The name to use in the headers when constructing the email to send - this will overwrite any extracted from the email param if supplied
	 *
	 * @return boolean indicator of success; a false indicates an email failed to validate.
	 */
	public function addCC($email, $name = false) {
		return $this->_addEmailToList($email, $name, $this->_ccrecipients);
	}

	/**
	 * Add multiple carbon copy users to the list of those to be emailed.  Each carbon copy user will be
	 * mailed whenever each user is mailed, so a list of five recipients and two carbon copy addresses will
	 * result in each carbon copy recipient being emailed five times.
	 *
	 * @param array $recipients The email addresses to be added.  These may be an array of RFC-formatted email addresses, in which case the name will also be extracted.  If the key of an array item is not numeric, it will be treated as a name and later used to construct the email address in the headers.
	 *
	 * @return integer Returns the number of email addresses that passed validation.
	 */
	public function addCCs($recipients) {
		$added = 0;

		foreach ($recipients as $name => $email) {
			if (is_numeric($name)) $name = false;
			if ($this->_addEmailToList($email, $name, $this->_ccrecipients)) $added++;
		}

		return $added;
	}

	/**
	 * Clears the current blind carbon-copy user list.
	 *
	 * @return void
	 */
	public function clearBCCs() {
		$this->_bccrecipients = array();
	}

	/**
	 * Add a blind carbon copy user to the list of those to be mailed.  Each blind carbon copy user will be
	 * mailed whenever each user is mailed, so a list of five recipients and two blind carbon copy addresses
	 * will result in each blind carbon copy recipient being emailed five times.
	 *
	 * @param string $email The email address to be added.  This may be a fully RFC-formatted email address including the name, such as "Rowan" <r@r.r>, in which case the name will be extracted and used.
	 * @param string $name  (Optional) The name to use in the headers when constructing the email to send - this will overwrite any extracted from the email param if supplied
	 *
	 * @return boolean indicator of success; a false indicates an email failed to validate.
	 */
	public function addBCC($email, $name = false) {
		return $this->_addEmailToList($email, $name, $this->_bccrecipients);
	}

	/**
	 * Add multiple blind carbon copy users to the list of those to be emailed.  Each blind carbon
	 * copy user will be  mailed whenever each user is mailed, so a list of five recipients and two
	 * blind carbon copy addresses will result in each blind carbon copy recipient being emailed five times.
	 *
	 * @param array $recipients The email addresses to be added.  These may be an array of RFC-formatted email addresses, in which case the name will also be extracted.  If the key of an array item is not numeric, it will be treated as a name and later used to construct the email address in the headers.
	 *
	 * @return integer Returns the number of email addresses that passed validation.
	 */
	public function addBCCs($recipients) {
		$added = 0;

		foreach ($recipients as $name => $email) {
			if (is_numeric($name)) $name = false;
			if ($this->_addEmailToList($email, $name, $this->_bccrecipients)) $added++;
		}

		return $added;
	}

	/**
	 * Set the address, and optionally name, of the user the email should appear to be sent by.
	 *
	 * @param string $email The email address to be added.  This may be a fully RFC-formatted email address including the name, such as "Rowan" <r@r.r>, in which case the name will be extracted and used.
	 * @param string $name  (Optional) The name to use in the headers when constructing the email to send - this will overwrite any extracted from the email param if supplied.
	 *
	 * @return boolean indicator of success; a false indicates an email failed to validate.
	 */
	public function setFrom($email, $name = false) {
		if (!$details = $this->validateEmail($email)) {
			return false;
		}

		$this->_from['email'] = $details['email'];
		$this->_from['name'] = false;
		if ($details['name']) $this->_from['name'] = $details['name'];
		if ($name) $this->_from['name'] = $name;

		return true;
	}

	/**
	 * Set the address, and optionally name, that the email should be returned to if it cannot be delivered.
	 *
	 * @param string $email The email address to be added.  This may be a fully RFC-formatted email address including the name, such as "Rowan" <r@r.r>, in which case the name will be extracted and used.
	 * @param string $name  (Optional) The name to use in the headers when constructing the email to send - this will overwrite any extracted from the email param if supplied.
	 *
	 * @return boolean indicator of success; a false indicates an email failed to validate.
	 */
	public function setBounceTo($email, $name = false) {
		if (!$details = $this->validateEmail($email)) {
			return false;
		}

		$this->_bounceaddress['email'] = $details['email'];
		$this->_bounceaddress['name'] = false;
		if ($details['name']) $this->_bounceaddress['name'] = $details['name'];
		if ($name) $this->_bounceaddress['name'] = $name;

		return true;
	}

	/**
	 * Set the HTML to use for the body of this email. Takes either a string or a path to a file.
	 * The resulting HTML is run through the template manager before sending.  If setAutocreateTextFromHTML
	 * has not been called with false, the resulting HTML will also be copied across to a text version where
	 * appropriate.
	 *
	 * Calling this function with a blank string is treated as clearing the html.
	 *
	 * @param string  $html   String containing the html text to use for the body, or a path to the HTML template
	 * @param boolean $ispath Whether or not the string provided is a path to a template - defaults to false.
	 *
	 * @return boolean Return whether or not the html was set, or whether the file was successfully read
	 */
	public function setHTML($html, $ispath = false) {
		if (!$ispath) {
			if (!$html or !trim($html)) {
				$this->_html = false;
			} else {
				$this->_html = $html;
			}
			return true;
		} else {
			if (!@file_exists($html)) $html = dirname($_SERVER['SCRIPT_FILENAME']).'/'.$html.'.html';
			if (!@file_exists($html) or !@is_readable($html)) return false;
			if (!$html = @file_get_contents($html)) return false;
			$this->_html = $html;
			return true;
		}
	}

	/**
	 * Set the text to use for the body of this email. Takes either a string or a path to a file.
	 * The resulting text is run through the template manager before sending.
	 *
	 * Calling thisfunction with a blank string is treated as clearing the text.
	 *
	 * @param string  $text   String containing the text to use for the body, or a path to the template file
	 * @param boolean $ispath Whether or not the string provided is a path to a file - defaults to false.
	 *
	 * @return boolean Return whether or not the text was set, or whether the file was successfully read
	 */
	public function setText($text, $ispath = false) {
		if (!$ispath) {
			if (!$text or !trim($text)) {
				$this->_text = false;
			} else {
				$this->_text = $text;
			}
			return true;
		} else {
			if (!@file_exists($text) or !@is_readable($text)) return false;
			if (!$text = @file_get_contents($text)) return false;
			$this->_text = $text;
			return true;
		}
	}

	/**
	 * Setter function controlling whether or not text versions should be automatically generated from
	 * HTML emails if no text version is already present.  Defaults to true, which is also the initial
	 * setting for the class.
	 *
	 * @param boolean $value Controls whether or not text versions should be automatically generated if not set.
	 *
	 * @return void
	 */
	public function setAutocreateTextFromHTML($value) {
		$this->_autocreatetext = $value?true:false;
	}

	/**
	 * Setter function to add a header to the list of current headers.  Supplied with a header and value,
	 * the header will be sent out with all emails sent with this instance of the class, with options
	 * to quoted-printable encode the content if necessary (defaults to true) and whether to overwrite the
	 * current setting for this heading or add to it if it already exists (defaults to add).
	 *
	 * @param string  $headername The name of the header to set, for example 'X-Mailer'
	 * @param string  $value      The value to send for the header, for example 'The sales website'.  Header injection attempts will automatically be removed from the provided text.
	 * @param boolean $encode     (Optional) Whether to quoted-printable encode the value if it contains non-US-ASCII chars, as per the RFC - defaults to true.
	 * @param boolean $overwrite  (Optional) Whether to overwrite an existing setting for this header if it already has been set.  Defaults to false.
	 *
	 * @return boolean Success or failure.
	 */
	function addHeader($headername, $value, $encode = true, $overwrite = false) {
		if (!trim($headername)) return false;

		// Encode the header value if necessary
		$value = $this->_injectProtect($value);
		if ($encode and preg_match('/[^\x20-\x7E]/', $value)) {
			if ($parts = $this->validateEmail($value)) {
				$value = self::encodeQuotedPrintable($parts['name'], true).' <'.$parts['email'].'>';
			} else {
				$value = self::encodeQuotedPrintable($value);
			}
		}

		if (empty($this->_headers[$headername]) or $overwrite) {
			$this->_headers[$headername] = array();
		}
		$this->_headers[$headername][] = $value;

		return true;
	}


	/**
	 * Clears the current attachments array.
	 *
	 * @return void
	 */
	public function clearAttachments() {
		$this->_attachments = array();
	}

	/**
	 * Function to add an attachment to future emails.  Accepts a file path by default, but can also
	 * be passed a direct data string, along with name and mime type arguments.
	 *
	 * @param string  $string The path (default/if $isfile is true) to the attachment to send, or a string which should be sent as an attachment
	 * @param boolean $isfile Whether to treat $string as a file path (true) or a data string (false).
	 * @param string  $name   The name to use for the attachment.  Required if not passing a file.
	 * @param string  $mime   The mimetype (and optionally, encoding) for this attachment. Required if not passing a file.
	 *
	 * @return boolean An indicator of success.
	 */
	public function addAttachment($string, $isfile = true, $name = false, $mime = false) {
		if (!$isfile and (!$name or !$mime)) return false;

		if ($isfile) {
			if (!@file_exists($string) or !@is_readable($string)) return false;
			if (!$contents = @file_get_contents($string)) return false;
			if (!$name) $name = basename($string);
			if (!$mime) {
				$mime = trim(shell_exec('/usr/bin/file --mime '.escapeshellarg($string)));
				if (strpos($mime, $string) === 0) {
					$mime = substr($mime, strlen($string) + 2);
				} else {
					$mime = 'application/octet-stream';
				}
			}
			$string = $contents;
		}

		$this->_attachments[] = array('name' => $name, 'mime' => $mime, 'data' => $string);
		return true;
	}

	/**
	 * Sets the priority of the email.  Must be an integer as defined in PRIORITY_n within this class -
	 * 1 being high priority, 5 being low priority.
	 *
	 * @param integer $priority The priority to use when sending the email.
	 *
	 * @return boolean Whether the priority setting was accepted.
	 */
	public function setPriority($priority) {
		if ($priority !== self::PRIORITY_NORMAL and $priority !== self::PRIORITY_HIGH and $priority !== self::PRIORITY_LOW) return false;

		$this->_priority = $priority;
		return true;
	}

	/**
	 * Set the email mode to use - PHP built-in or test.
	 *
	 * @param integer $mode The mode to select - see the MODE_ class constants
	 *
	 * @return boolean Whether the mode setting was accepted.
	 */
	public function setMode($mode) {
		if ($mode !== self::MODE_BUILTIN and $mode !== self::MODE_TEST) return false;

		$this->_mode = $mode;
		return true;
	}

	/**
	 * Function to validate and parse a supplied email address string.
	 *
	 * @param string $email The email address to validate, either solely an email or with an RFC-compliant name.
	 *
	 * @return array an array containing the email address (and name, if any), or false on failure.
	 */
	static function validateEmailAddress($email) {
		$name = "a-z0-9\!\#\$\%\&\'\*\+\/\=\?\^\_\`\{\|\}\~\-";
		$quotedname = "^\t\n\r\"\\\\";
		$quoteddomain = "^\t\n\r\[\]\\\\";
		$escapedquoted = "^\n\r";
		$domainchunk = "[a-z0-9](?:[a-z0-9-]*[a-z0-9])?";
		$octet = "25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?";

		$validuser = "[$name]+(?:\.[$name]+)*";
		$validquoteduser = "\"[\x01-\x08\x0b\x0c\x0e-\x1f\x21\x23-\x5b\x5d-\x7e]?\"";
		$validquoteduser = "\"(?:[$quotedname]|\\\\[".$escapedquoted."])*\"";

		$validsimpledomain = "(?:$domainchunk\.)+$domainchunk";
		$validgroupeddomain = "\[(?:(?:(?:$octet)\.){4}|(?:[$quoteddomain]|\\\\[$escapedquoted])+)\]";

		$fullemail = "(?:$validuser|$validquoteduser)@(?:$validsimpledomain|$validgroupeddomain)";
		$emailregex = "/^\s*(?:\"?(.*)?\s*<)?($fullemail)>?\s*$/i";

		if (preg_match($emailregex, $email, $matches)) {
			$name = preg_replace("/([^\\\\])\"\s*$/", "$1", $matches[1]);
			$email = $matches[2];
			return array(0 => $email, 1 => $name, "email" => $email, "name" => $name);
		} else {
			return false;
		}
	}
	public function validateEmail($email) {
		return self::validateEmailAddress($email);
	}

	/**
	 * Static method to quoted-printable encode the input, as used in emails for header lines. Differs from the standard PHP function in that it's highly targetted towards emails (eg regarding linewrap, trailing spaces), and can also optionally encode extra characters for use in to/from/etc headers.
	 *
	 * @param string $input       The text to encode as quoted-printable
	 * @param bool   $extraencode (Optional, default false) Whether to encode all characters except alphanumerics, eg for name headers
	 *
	 * @return string The encoded text
	 */
	static function encodeQuotedPrintable($input, $extraencode = false) {
		static $charmaps = false;
		$linestoreturn = array();

		// Build up an array of safe chars on first user
		if (!$charmaps) {

			// For non-extra encoded, allow basic safe characters and also explicitly allow spaces
			$charmaps[0] = array(' ' => ' ');
			for ($i = 33; $i <= 60; $i++) $charmaps[0][chr($i)] = chr($i);
			for ($i = 62; $i <= 126; $i++) $charmap[0][chr($i)] = chr($i);

			// For extra-encoded, only explicitly allow alphanumeric characters
			$charmaps[1] = array();
			for ($i = 48; $i <= 57; $i++) $charmaps[1][chr($i)] = chr($i);
			for ($i = 65; $i <= 90; $i++) $charmaps[1][chr($i)] = chr($i);
			for ($i = 97; $i <= 122; $i++) $charmaps[1][chr($i)] = chr($i);
		}

		// Select the active charmap array
		$charmap =& $charmaps[$extraencode?1:0];

		// Split the original input into individual lines, and process each separately
		$originallines = explode("\r\n", $input);
		foreach ($originallines as $theline) {

			// Skip empty lines
			if (!strlen($theline)) {
				$linestoreturn[] = '';
				continue;
			}

			$lineparts = array();
			$line = '';
			$linelength = 0;
			$strlen = mb_strlen($theline, 'UTF-8');

			// The first line can only be 51 characters long - to account for UTF8 markup and a header -
			// whereas subsequent lines can be 61 characters long. This keeps lines below 76.
			$maxlinelength = 51;

			// Walk through the string and build up output strings, encoding non-standard chars.
			for ($i = 0; $i < $strlen; $i++) {

				// Pull out the character, and (via cache if possible), convert to safe/encoded
				$char = mb_substr($theline, $i, 1, 'UTF-8');
				if (empty($charmap[$char])) {
					$charrep = '';
					$charlen = strlen($char);
					for ($j = 0; $j < $charlen; $j++) {
						$charrep .= sprintf('=%02X', ord($char[$j]));
					}
					$charmap[$char] = $charrep;
				}
				$char = $charmap[$char];
				$charlength = strlen($char);

				// If this would bring the line length to too long, store and reset the line
				if ($charlength + $linelength > $maxlinelength) {
					$finalcharord = ord($line[$linelength - 1]);
					if ($finalcharord == 0x09 or $finalcharord == 0x20) {
						$line[$linelength - 1] = '=';
						$line .= ($finalcharord == 0x09)?'09':'20';
					}
					$lineparts[] = $line;
					$line = '';
					$linelength = 0;
					$maxlinelength = 61;
				}

				// Add to the current line
				$line .= $char;
				$linelength += $charlength;

				// Store the line if at the end of the string
				if ($i + 1 == $strlen) {
					$lineparts[] = $line;
				}
			}

			// Collapse back into an encoded line
			$encodedline = "=?utf-8?Q?".implode("?=\r\n =?utf-8?Q?", $lineparts)."?=";

			// Encode spaces before linebreaks, if any are left
			$encodedline = str_replace(' =0D', '=20=0D', $encodedline);

			$linestoreturn[] = $encodedline;
		}

		return implode("\r\n", $linestoreturn);
	}

	/**
	 * Sends all emails to the email address given, irrespective of intended recipients
	 *
	 * Useful for dev or staging servers which use end users' email address
	 *
	 * @param string $email The address to overwrite all recipients' addresses with
	 * @return void
	 */
	public static function overwriteRecipients($email) {
		self::$_overwriterecipients = $email;
	}

	/**
	 * Header injection protection function.  Replaces linebreaks and nasty chars with a space.
	 *
	 * @param string $string The input string.
	 *
	 * @return string the cleaned string
	 */
	private function _injectProtect($string) {
		$string = preg_replace("#(<CR>|<LF>|0x0A/%0A|0x0D/%0D|\n|\r)+#i", " ", $string);
		return $string;
	}

	/**
	 * Function to handle adding an email address to one of the three recipient lists.
	 * Used by the recipient, cc and bcc recipient functions.
	 *
	 * @param string $email        The email address to be added.  This may be a fully RFC-formatted email address including the name, such as "Rowan" <r@r.r>, in which case the name will be extracted and used.
	 * @param string $name         The name to use in the headers when constructing the email to send - this will overwrite any extracted from the email param if supplied
	 * @param array  &$contactlist The reference to the array to add the contact to
	 *
	 * @return boolean indicator of success; a false indicates an email failed to validate.
	 */
	private function _addEmailToList($email, $name, &$contactlist) {
		$contact = false;
		if (!empty(self::$_overwriterecipients)) $email = self::$_overwriterecipients;
		if ($details = $this->validateEmail($email)) {
			if ($details['name']) $contact['name'] = $details['name'];
			$contact['email'] = $details['email'];
		} else {
			return false;
		}
		if ($name) $contact['name'] = $name;
		if (empty($contact['name'])) $contact['name'] = false;

		$contactlist[] = $contact;
		return true;
	}


	/**
	 * Function to wrap long lines within emails to comply with RFCs and MTAs - RFC2822 specifies that:
	 *   "There are two limits that this standard places on the number of
	 *    characters in a line. Each line of characters MUST be no more than
	 *    998 characters, and SHOULD be no more than 78 characters, excluding
	 *    the CRLF."
	 * Many old and rubbish MTAs don't cope well with lines above 90 let alone 900, so this function
	 * word wraps to 78, but allows single-word lines to get up to 200 characters before they are forcibly
	 * wrapped to attempt to leave URLs functional in Outlook and old clients.
	 *
	 * Supports format=flowed to leave messages visually unaffected in modern mail clients.  In future it
	 * would be ideal to use delsp=Yes in the headers and force everything to wrap to 78, but support for
	 * delsp is currently narrow enough that this would have a greater negative effect than dodgy MTAs.
	 *
	 * @param string &$text A reference of the text to wrap.  Any existing linebreaks will be used and preserved.
	 *
	 * @return void
	 */
	private function _wrapTextForEmailing(&$text) {

		// Split the text into an array of lines - necessary because wordwrap only treats the *specified*
		// linebreak character as a linebreak, and we later use " \n" to support format=flowed.
		$lines = explode("\n", $text);

		// Step through the lines and wrap each one as necessary
		foreach ($lines as &$line) {
			$line = rtrim($line, ' ');
			$line = wordwrap($line, 78, " \n");
			$line = wordwrap($line, 200, "\n", true);
		}

		// Implode the lines array again.
		$text = implode("\n", $lines);
	}
}
