<?php
/**
 * Allows creating and sending a single email containing plaintext, HTML, attachments and S/MIME encryption
 * 
 * Please note that this class uses the {@link http://php.net/function.mail mail()}
 * function, and thus would have poor performance if used for mass mailing.
 * 
 * This class is implemented to use the UTF-8 character encoding. Please see
 * {@link http://flourishlib.com/docs/UTF-8} for more information.
 * 
 * @copyright  Copyright (c) 2008 William Bond
 * @author     William Bond [wb] <will@flourishlib.com>
 * @license    http://flourishlib.com/license
 * 
 * @link  http://flourishlib.com/fEmail
 * 
 * @version  1.0.0
 * @changes  1.0.0    The initial implementation [wb, 2008-06-23]
 */
class fEmail
{
	/**
	 * A regular expression to match an email address, exluding those with comments and folding whitespace
	 * 
	 * The matches will be:
	 * 
	 *   - [0]: The whole email address
	 *   - [1]: The name before the @
	 *   - [2]: The domain/ip after the @
	 * 
	 * @internal
	 * 
	 * @var string
	 */
	const EMAIL_REGEX = '#^[ \t]*((?:[^\x00-\x20\(\)<>@,;:\\\\"\.\[\]]+|"[^"\\\\\n\r]+")(?:\.(?:[^\x00-\x20\(\)<>@,;:\\\\"\.\[\]]+|"[^"\\\\\n\r]+"))*)@((?:[a-z0-9\\-]+\.)+[a-z]{2,}|(?:(?:[01]?\d?\d|2[0-4]\d|25[0-5])\.){3}(?:[01]?\d?\d|2[0-4]\d|25[0-5]))[ \t]*$#i';
	
	/**
	 * A regular expression to match a 'name <email>' string, exluding those with comments and folding whitespace
	 * 
	 * The matches will be:
	 * 
	 *   - [0]: The whole name and email address
	 *   - [1]: The name
	 *   - [2]: The whole email address
	 *   - [3]: The email username before the @
	 *   - [4]: The email domain/ip after the @
	 * 
	 * @internal
	 * 
	 * @var string
	 */
	const NAME_EMAIL_REGEX = '#^[ \t]*((?:[^\x00-\x20\(\)<>@,;:\\\\"\.\[\]]+[ \t]*|"[^"\\\\\n\r]+"[ \t]*)(?:\.?[ \t]*(?:[^\x00-\x20\(\)<>@,;:\\\\"\.\[\]]+[ \t]*|"[^"\\\\\n\r]+"[ \t]*))*)[ \t]*<[ \t]*(((?:[^\x00-\x20\(\)<>@,;:\\\\"\.\[\]]+|"[^"\\\\\n\r]+")(?:\.(?:[^\x00-\x20\(\)<>@,;:\\\\"\.\[\]]+|"[^"\\\\\n\r]+"))*)@((?:[a-z0-9\\-]+\.)+[a-z]{2,}|(?:(?:[01]?\d?\d|2[0-4]\d|25[0-5])\.){3}(?:[01]?\d?\d|2[0-4]\d|25[0-5])))[ \t]*>[ \t]*$#i';
	
	
	/**
	 * The file contents to attach
	 * 
	 * @var array
	 */
	private $attachments = array();
	
	/**
	 * The email address(es) to BCC to
	 * 
	 * @var array
	 */
	private $bcc_emails = array();
	
	/**
	 * The email address to bounce to
	 * 
	 * @var string
	 */
	private $bounce_to_email = NULL;
	
	/**
	 * The email address(es) to CC to
	 * 
	 * @var array
	 */
	private $cc_emails = array();
	
	/**
	 * The email address being sent from
	 * 
	 * @var string
	 */
	private $from_email = NULL;
	
	/**
	 * The HTML body of the email
	 * 
	 * @var string
	 */
	private $html_body = NULL;
	
	/**
	 * The plaintext body of the email
	 * 
	 * @var string
	 */
	private $plaintext_body = NULL;
	
	/**
	 * The recipient's S/MIME PEM certificate filename, used for encryption of the message
	 * 
	 * @var string
	 */
	private $recipients_smime_cert_file = NULL;
	
	/**
	 * The email address to reply to
	 * 
	 * @var string
	 */
	private $reply_to_email = NULL;
	
	/**
	 * The email address actually sending the email
	 * 
	 * @var string
	 */
	private $sender_email = NULL;
	
	/**
	 * The senders's S/MIME PEM certificate filename, used for singing the message
	 * 
	 * @var string
	 */
	private $senders_smime_cert_file = NULL;
	
	/**
	 * The senders's S/MIME private key filename, used for singing the message
	 * 
	 * @var string
	 */
	private $senders_smime_pk_file = NULL;
	
	/**
	 * The senders's S/MIME private key password, used for singing the message
	 * 
	 * @var string
	 */
	private $senders_smime_pk_password = NULL;
	
	/**
	 * If the message should be encrypted using the recipient's S/MIME certificate
	 * 
	 * @var boolean
	 */
	private $smime_encrypt = FALSE;
	
	/**
	 * If the message should be signed using the senders's S/MIME private key
	 * 
	 * @var boolean
	 */
	private $smime_sign = FALSE;
	
	/**
	 * The subject of the email
	 * 
	 * @var string
	 */
	private $subject = NULL;
	
	/**
	 * The email address(es) to send to
	 * 
	 * @var array
	 */
	private $to_emails = array();
	
	
	/**
	 * Adds an attachment to the email
	 * 
	 * Duplicate filenames will be changed to be unique.
	 * 
	 * @param  string $filename   The name of the file to attach
	 * @param  string $mime_type  The mime type of the file
	 * @param  string $contents   The contents of the file
	 * @return void
	 */
	public function addAttachment($filename, $mime_type, $contents)
	{
		if (!fCore::stringlike($filename)) {
			fCore::toss('fProgrammerException', 'The filename specified, ' . fCore::dump($filename) . ', does not appear to be a valid filename');	
		}
		
		$filename = (string) $filename;
		
		$i = 1;
		while (isset($this->attachments[$filename])) {
			$filename_info = fFilesystem::getPathInfo($filename);
			$extension     = ($filename_info['extension']) ? '.' . $filename_info['extension'] : '';
			$filename      = preg_replace('#_copy\d+$#', '', $filename_info['filename']) . '_copy' . $i . $extension;
			$i++;	
		}
		
		$this->attachments[$filename] = array(
			'mime-type' => $mime_type,
			'contents'  => $contents
		);
	}
	
	
	/**
	 * Adds a blind carbon copy (BCC) email recipient
	 * 
	 * @param  string $email  The email address to BCC
	 * @param  string $name   The recipient's name
	 * @return void
	 */
	public function addBCCRecipient($email, $name=NULL)
	{
		if (!$email) {
			return;	
		}
		
		$this->bcc_emails[] = $this->combineNameEmail($name, $email);
	}
	
	
	/**
	 * Adds a carbon copy (CC) email recipient
	 * 
	 * @param  string $email  The email address to BCC
	 * @param  string $name   The recipient's name
	 * @return void
	 */
	public function addCCRecipient($email, $name=NULL)
	{
		if (!$email) {
			return;	
		}
		
		$this->cc_emails[] = $this->combineNameEmail($name, $email);
	}
	
	
	/**
	 * Adds an email recipient
	 * 
	 * @param  string $email  The email address to send to
	 * @param  string $name   The recipient's name
	 * @return void
	 */
	public function addRecipient($email, $name=NULL)
	{
		if (!$email) {
			return;	
		}
		
		$this->to_emails[] = $this->combineNameEmail($name, $email);
	}
	
	
	/**
	 * Takes a multi-address email header and builds it out using an array of emails 
	 * 
	 * @param  string $header  The header name without ': ', the header is non-blank, ': ' will be added
	 * @param  array  $emails  The email addresses for the header
	 * @return string  The email header with a trailing "\r\n"
	 */
	private function buildMultiAddressHeader($header, $emails)
	{
		if ($header) {
			$header .= ': ';
		}
		
		$first = TRUE;
		$line = 0;
		foreach ($emails as $email) {
			if ($first) { $first = FALSE; } else { $header .= ', '; }
			
			// Make sure we don't go past the 978 char limit for email headers
			if (strlen($header . $email) / 950 > $line) {
				$header .= "\r\n ";
				$line++;
			}	
			
			$header .= trim($email);	
		}	
		
		return $header . "\r\n";
	}
	
	
	/**
	 * Creates a 32-character boundary for a multipart message 
	 * 
	 * @return string  A multipart boundary
	 */
	private function createBoundary()
	{
		// We use characters that are not part of base-64 encoded string
		$chars      = 'ghijklmnopqrstuvwxyzGHIJKLMNOPQRSTUVWXYZ:-_';
		$last_index = strlen($chars) - 1;
		$output     = '';
		
		for ($i = 0; $i < 32; $i++) {
			$output .= $chars[rand(0, $last_index)];
		}
		return $output;	
	}
	
	
	/**
	 * Turns a name and email into a '"name" <email>' string, or just 'email' if no name is provided
	 * 
	 * This method will remove newline characters from the name and email, and
	 * will remove any backslash (\) and double quote (") characters from
	 * the name. 
	 * 
	 * @param  string $name   The name associated with the email address
	 * @param  string $email  The email address
	 * @return string  The '"name" <email>' or 'email' string
	 */
	private function combineNameEmail($name, $email)
	{
		$email = str_replace(array("\r", "\n"), '', $email);
		$name  = str_replace(array('\\', '"', "\r", "\n"), '', $name);
		
		if (!$name) {
			return $email;	
		}
		
		return '"' . $name . '" <' . $email . '>';	
	}
	
	
	/**
	 * Sets the email to be encrypted with S/MIME
	 * 
	 * @param  string $recipients_smime_cert_file  The file path to the PEM-encoded S/MIME certificate for the recipient
	 * @return void
	 */
	public function encrypt($recipients_smime_cert_file)
	{
		if (!fCore::stringlike($recipients_smime_cert_file)) {
			fCore::toss('fProgrammerException', "The recipient's S/MIME certificate filename specified, " . fCore::dump($recipients_smime_cert_file) . ', does not appear to be a valid filename');	
		}
		
		$this->smime_encrypt              = TRUE;
		$this->recipients_smime_cert_file = $recipients_smime_cert_file;
	}
	
	
	/**
	 * Encodes a string to base64
	 * 
	 * @param  string  $content  The content to encode
	 * @return string  The encoded string
	 */
	private function makeBase64($content)
	{
		return chunk_split(base64_encode($content));	
	}
	
	
	/**
	 * Encodes a string to UTF-8 encoded-word
	 * 
	 * @param  string  $content  The content to encode
	 * @return string  The encoded string
	 */
	private function makeEncodedWord($content)
	{
		// Homogenize the line-endings to CRLF
		$content = str_replace("\r\n", "\n", $content);
		$content = str_replace("\r", "\n", $content);
		$content = str_replace("\n", "\r\n", $content);
		
		// A quick a dirty hex encoding
		$content = rawurlencode($content);
		$content = str_replace('=', '%3D', $content);
		$content = str_replace('%', '=', $content);
		
		// Decode characters that don't have to be coded
		$decodings = array(
			'=20' => ' ', '=21' => '!', '=22' => '"',  '=23' => '#',
			'=24' => '$', '=25' => '%', '=26' => '&',  '=27' => "'",
			'=28' => '(', '=29' => ')', '=2A' => '*',  '=2B' => '+',
			'=2C' => ',', '=2D' => '-', '=2E' => '.',  '=2F' => '/',
			'=3A' => ':', '=3B' => ';', '=3C' => '<',  '=3E' => '>',
			'=40' => '@', '=5B' => '[', '=5C' => '\\', '=5D' => ']',
			'=5E' => '^', '=60' => '`', '=7B' => '{',  '=7C' => '|',
			'=7D' => '}', '=7E' => '~', ' '   => '_'
		);
		
		$content = strtr($content, $decodings);
		
		$length = strlen($content);
		
		$prefix = '=?utf-8?Q?';
		$suffix = '?=';
		
		$prefix_length = 10;
		$suffix_length = 2;
		
		// This loop goes through and ensures we are wrapping by 75 chars
		// including the encoded word delimiters
		$output = $prefix;
		$line_length = $prefix_length;
		
		for ($i=0; $i<$length; $i++) {
			
			// Get info about the next character
			$char_length = ($content[$i] == '=') ? 3 : 1;
			$char        = $content[$i];
			if ($char_length == 3) {
				$char .= $content[$i+1] . $content[$i+2]; 
			}
			
			// If we have too long a line, wrap it
			if ($line_length + $suffix_length + $char_length > 75) {
				$output .= $suffix . "\r\n " . $prefix;
				$line_length = $prefix_length + 1;
			} 		
			
			// Add the character
			$output .= $char;
			
			// Figure out how much longer the line is
			$line_length += $char_length;
			
			// Skip characters if we have an encoded character
			$i += $char_length-1;	
		}
		
		if (substr($output, -2) != $suffix) {
			$output .= $suffix;
		}
		
		return $output;	
	}
	
	
	/**
	 * Encodes a string to quoted-printable, properly handles UTF-8
	 * 
	 * @param  string  $content  The content to encode
	 * @return string  The encoded string
	 */
	private function makeQuotedPrintable($content)
	{
		// Homogenize the line-endings to CRLF
		$content = str_replace("\r\n", "\n", $content);
		$content = str_replace("\r", "\n", $content);
		$content = str_replace("\n", "\r\n", $content);
		
		// A quick a dirty hex encoding
		$content = rawurlencode($content);
		$content = str_replace('=', '%3D', $content);
		$content = str_replace('%', '=', $content);
		
		// Decode characters that don't have to be coded
		$decodings = array(
			'=20' => ' ', '=21' => '!', '=22' => '"', '=23' => '#',
			'=24' => '$', '=25' => '%', '=26' => '&', '=27' => "'",
			'=28' => '(', '=29' => ')', '=2A' => '*', '=2B' => '+',
			'=2C' => ',', '=2D' => '-', '=2E' => '.', '=2F' => '/',
			'=3A' => ':', '=3B' => ';', '=3C' => '<', '=3E' => '>',
			'=3F' => '?', '=40' => '@', '=5B' => '[', '=5C' => '\\',
			'=5D' => ']', '=5E' => '^', '=5F' => '_', '=60' => '`',
			'=7B' => '{', '=7C' => '|', '=7D' => '}', '=7E' => '~'
		);
		
		$content = strtr($content, $decodings);
		
		$output = '';
		
		$length = strlen($content);
		
		// This loop goes through and ensures we are wrapping by 76 chars
		$line_length = 0;
		for ($i=0; $i<$length; $i++) {
			
			// Get info about the next character
			$char_length = ($content[$i] == '=') ? 3 : 1;
			$char        = $content[$i];
			if ($char_length == 3) {
				$char .= $content[$i+1] . $content[$i+2]; 
			}
			
			// Spaces and tabs at the beginning and ending of lines have to be encoded
			$begining_or_end = $line_length > 69 || $line_length == 0;
			$tab_or_space    = $char == ' ' || $char == "\t";
			if ($begining_or_end && $tab_or_space) {	
				$char_length = 3;
				$char        = ($char == ' ') ? '=20' : '=09';	
			}
			
			// If we have too long a line, wrap it
			if ($char != "\r" && $char != "\n" && $line_length + $char_length > 76) {
				$output .= "=\r\n";
				$line_length = 0;
			} 		
			
			// Add the character
			$output .= $char;
			
			// Figure out how much longer the line is now
			if ($char == "\r" || $char == "\n") {
				$line_length = 0;	
			} else {
				$line_length += $char_length;
			}
			
			// Skip characters if we have an encoded character
			$i += $char_length-1;
		}
		
		return $output;	
	}
	
	
	/**
	 * Sends the email
	 * 
	 * @throws fValidationException
	 * 
	 * @return void
	 */
	public function send()
	{
		$this->validate();
		
		$to = trim($this->buildMultiAddressHeader("", $this->to_emails));
		
		$headers = '';
		
		if ($this->cc_emails) {
			$headers .= $this->buildMultiAddressHeader("Cc", $this->cc_emails);
		}
		if ($this->bcc_emails) {
			$headers .= $this->buildMultiAddressHeader("Bcc", $this->bcc_emails);
		}
		
		$headers .= "From: " . trim($this->from_email) . "\r\n";
		
		if ($this->reply_to_email) {
			$headers .= "Reply-To: " . trim($this->reply_to_email) . "\r\n";	
		}
		if ($this->sender_email) {
			$headers .= "Sender: " . trim($this->sender_email) . "\r\n";	
		}
		if ($this->bounce_to_email) {
			// QMail might allow setting this? Most other MTAs do not.
			$headers .= "Return-Path: " . trim($this->bounce_to_email) . "\r\n";
			// Postfix may use this to send bounces
			$headers .= "Errors-To: " . trim($this->bounce_to_email) . "\r\n";	
		}
		
		if ($this->html_body || $this->attachments) {
			$headers .= "MIME-Version: 1.0\r\n";	  
		}
		
		$subject = str_replace(array("\r", "\n"), '', $this->subject);
		$subject = $this->makeEncodedWord($subject);
		
		$body = '';
		
		// Build the multi-part/alternative section for the plaintext/HTML combo
		if ($this->html_body) {
			
			$boundary = $this->createBoundary();
			
			// Depending on the other content, these headers may be inline or in the real headers
			if ($this->attachments) {
				$body .= 'Content-Type: multipart/alternative; boundary="' . $boundary . "\"\r\n\r\n";
			} else {
				$headers .= 'Content-Type: multipart/alternative; boundary="' . $boundary . "\"\r\n\r\n";
			}
			
			$body .= "This message has been formatted using MIME. It does not appear that your email client supports MIME.";
			$body .= '--' . $boundary . "\r\n";
			$body .= "Content-type: text/plain; charset=utf-8\r\n";
			$body .= "Content-transfer-encoding: quoted-printable\r\n\r\n";
			$body .= $this->makeQuotedPrintable($this->plaintext_body) . "\r\n\r\n";
			$body .= '--' . $boundary . "\r\n";
			$body .= "Content-type: text/html; charset=utf-8\r\n";
			$body .= "Content-transfer-encoding: quoted-printable\r\n\r\n";
			$body .= $this->makeQuotedPrintable($this->html_body) . "\r\n\r\n";
			$body .= '--' . $boundary . "\r\n";
		
		// If there is no HTML, just encode the body
		} else {
			
			// Depending on the other content, these headers may be inline or in the real headers
			if (!$this->attachments) {
				$headers .= "Content-type: text/plain\r\n";
				$headers .= "Content-transfer-encoding: quoted-printable\r\n";
			} else {
				$body .= "Content-type: text/plain\r\n";
				$body .= "Content-transfer-encoding: quoted-printable\r\n\r\n";
			}
			
			$body .= $this->makeQuotedPrintable($this->plaintext_body) . "\r\n\r\n";	
		}
		
		// If we have attachments, we need to wrap a multipart/mixed around the current body
		if ($this->attachments) {
			
			$boundary = $this->createBoundary();
			
			$headers .= 'Content-Type: multipart/mixed; boundary="' . $boundary . "\"\r\n\r\n";
			
			$multipart .= "This message has been formatted using MIME. It does not appear that your email client supports MIME.";
			$multipart .= '--' . $boundary . "\r\n";
			$multipart .= $body . "\r\n";
			$multipart .= '--' . $boundary . "\r\n";
			
			foreach ($this->attachments as $filename => $file_info) {
				$multipart .= 'Content-type: ' . $file_info['mime-type'] . "\r\n";
				$multipart .= "Content-transfer-encoding: base64\r\n";
				$multipart .= 'Content-Disposition: attachment; filename="' . $filename . "\";\r\n\r\n";
				$multipart .= $this->makeBase64($file_info['content']) . "\r\n\r\n";
				$multipart .= '--' . $boundary . "\r\n";
			}	
			
			$message = $multipart;
		} else {
			$message = $body;	
		}

		// Sendmail when not in safe mode will allow you to set the envelope from address via the -f parameter
		$parameters = NULL;
		if (fCore::getOS() != 'windows' && $this->bounce_to_email && !ini_get('safe_mode')) {
			preg_match(self::NAME_EMAIL_REGEX, $this->bounce_to_email, $matches);
			$parameters = '-f ' . $matches[2];		
		}
		
		// Windows takes the Return-Path email from the sendmail_from ini setting
		if (fCore::getOS() == 'windows' && $this->bounce_to_email) {
			$old_sendmail_from = ini_get('sendmail_from');
			preg_match(self::NAME_EMAIL_REGEX, $this->bounce_to_email, $matches);
			ini_set('sendmail_from', $matches[2]);	
		}
		
		// Apparently SMTP server strip a leading . from lines
		if (fCore::getOS() == 'windows') {
			$message = str_replace("\r\n.", "\r\n..", $message);	
		}
		
		$error = !mail($to, $subject, $message, $headers, $parameters);
		
		if (fCore::getOS() == 'windows' && $this->bounce_to_email) {
			ini_set('sendmail_from', $old_sendmail_from);	
		}
		
		if ($error) {
			fCore::toss('fConnectivityException', 'An error occured while trying to send the email entitled: ' . fCore::dump($this->subject));
		}
	}
	
	
	/**
	 * Adds the email address the email will be bounced to
	 * 
	 * This email address will be set to the Return-Path and Errors-To headers.
	 * 
	 * @param  string $email  The email address to bounce to
	 * @param  string $name   The bounce-to email user's name
	 * @return void
	 */
	public function setBounceToEmail($email, $name=NULL)
	{
		if (!$email) {
			return;	
		}
		
		$this->bounce_to_email = $this->combineNameEmail($name, $email);
	}
	
	
	/**
	 * Adds the From: email address to the email
	 * 
	 * @param  string $email  The email address being sent from
	 * @param  string $name   The from email user's name
	 * @return void
	 */
	public function setFromEmail($email, $name=NULL)
	{
		if (!$email) {
			return;	
		}
		
		$this->from_email = $this->combineNameEmail($name, $email);
	}
	
	
	/**
	 * Sets the HTML version of the email body
	 * 
	 * This method accepts either ASCII or UTF-8 encoded text. Please see
	 * {@link http://flourishlib.com/docs/UTF-8} for more information.
	 * 
	 * @param  string $html  The HTML version of the email body
	 * @return void
	 */
	public function setHTMLBody($html)
	{
		$this->html_body = $html;
	}
	
	
	/**
	 * Sets the plaintext version of the email body
	 * 
	 * This method accepts either ASCII or UTF-8 encoded text. Please see
	 * {@link http://flourishlib.com/docs/UTF-8} for more information.
	 * 
	 * @param  string $plaintext  The plaintext version of the email body
	 * @return void
	 */
	public function setBody($plaintext)
	{
		$this->plaintext_body = $plaintext;
	}
	
	
	/**
	 * Adds the Reply-To: email address to the email
	 * 
	 * @param  string $email  The email address to reply to
	 * @param  string $name   The reply-to email user's name
	 * @return void
	 */
	public function setReplyToEmail($email, $name=NULL)
	{
		if (!$email) {
			return;	
		}
		
		$this->reply_to_email = $this->combineNameEmail($name, $email);
	}
	
	
	/**
	 * Adds the Sender: email address to the email
	 * 
	 * The Sender: header is used to indicate someone other than the From:
	 * address is actually submitting the message to the network.
	 * 
	 * @param  string $email  The email address the message is actually being sent from
	 * @param  string $name   The sender email user's name
	 * @return void
	 */
	public function setSenderEmail($email, $name=NULL)
	{
		if (!$email) {
			return;	
		}
		
		$this->sender_email = $this->combineNameEmail($name, $email);
	}
	
	
	/**
	 * Sets the subject of the email
	 * 
	 * This method accepts either ASCII or UTF-8 encoded text. Please see
	 * {@link http://flourishlib.com/docs/UTF-8} for more information.
	 * 
	 * @param  string $subject  The subject of the email
	 * @return void
	 */
	public function setSubject($subject)
	{
		$this->subject = $subject;
	}
	
	
	/**
	 * Sets the email to be signed with S/MIME
	 * 
	 * @param  string $senders_smime_cert_file    The file path to the sender's PEM-encoded S/MIME certificate
	 * @param  string $senders_smime_pk_file      The file path to the sender's S/MIME private key
	 * @param  string $senders_smime_pk_password  The password for the sender's S/MIME private key
	 * @return void
	 */
	public function sign($senders_smime_cert_file, $senders_smime_pk_file, $senders_smime_pk_password)
	{
		if (!fCore::stringlike($senders_smime_cert_file)) {
			fCore::toss('fProgrammerException', "The sender's S/MIME certificate file specified, " . fCore::dump($senders_smime_cert_file) . ', does not appear to be a valid filename');	
		}
		if (!file_exists($senders_smime_cert_file) || !is_readable($senders_smime_cert_file)) {
			fCore::toss('fEnvironmentException', "The sender's S/MIME certificate file specified, " . fCore::dump($senders_smime_cert_file) . ', does not exist or could not be read');
		}
		
		if (!fCore::stringlike($senders_smime_pk_file)) {
			fCore::toss('fProgrammerException', "The sender's S/MIME primary key file specified, " . fCore::dump($senders_smime_pk_file) . ', does not appear to be a valid filename');	
		}
		if (!file_exists($senders_smime_pk_file) || !is_readable($senders_smime_pk_file)) {
			fCore::toss('fEnvironmentException', "The sender's S/MIME primary key file specified, " . fCore::dump($senders_smime_pk_file) . ', does not exist or could not be read');
		}
		
		$this->smime_sign                = TRUE;
		$this->senders_smime_cert_file   = $senders_smime_cert_file;
		$this->senders_smime_pk_file     = $senders_smime_pk_file;
		$this->senders_smime_pk_password = $senders_smime_pk_password;
	}
	
	
	/**
	 * Validates that all of the parts of the email are valid
	 * 
	 * @throws fValidationException
	 * 
	 * @return void
	 */
	private function validate()
	{
		$validation_messages = array();
		
		// Check all multi-address email field
		$multi_address_field_list = array(
			'to_emails'  => 'recipient',
			'cc_emails'  => 'CC recipient',
			'bcc_emails' => 'BCC recipient'
		);
		
		foreach ($multi_address_field_list as $field => $name) {
			foreach ($this->$field as $email) {
				if ($email && !preg_match(self::NAME_EMAIL_REGEX, $email) && !preg_match(self::EMAIL_REGEX, $email)) {
					$validation_messages[] = "The " . $name . " " . fCore::dump($email) . ' is not a valid email address. Should be like "John Smith" <name@example.com>" or name@example.com.';
				}	
			}	
		}
		
		// Check all single-address email fields
		$single_address_field_list = array(
			'from_email'      => 'From email address',
			'reply_to_email'  => 'Reply-To email address',
			'sender_email'    => 'Sender email address',
			'bounce_to_email' => 'Bounce-To email address'	
		);
		
		foreach ($single_address_field_list as $field => $name) {
			if ($this->$field && !preg_match(self::NAME_EMAIL_REGEX, $this->$field) && !preg_match(self::EMAIL_REGEX, $this->$field)) {
				$validation_messages[] = "The " . $name . " " . fCore::dump($this->$field) . ' is not a valid email address. Should be like "John Smith" <name@example.com>" or name@example.com.';
			} 		
		}
		
		// Make sure the required fields are all set
		if (!$this->to_emails) {
			$validation_messages[] = "Please provide at least on recipient";	
		}
		
		if (!$this->from_email) {
			$validation_messages[] = "Please provide the from email address";	
		}
		
		if (!fCore::stringlike($this->subject)) {
			$validation_messages[] = "Please provide an email subject";	
		}
		
		if (!fCore::stringlike($this->plaintext_body)) {
			$validation_messages[] = "Please provide a plaintext email body";	
		}
		
		// Make sure the attachments look good
		foreach ($this->attachments as $filename => $file_info) {
			if (!fCore::stringlike($file_info['mime-type'])) {
				$validation_messages[] = "No mime-type was specified for the attachment " . $filename;	
			}
			if (!fCore::stringlike($file_info['content'])) {
				$validation_messages[] = "The attachment " . $filename . " appears to be a blank file";	
			}
		}		
	}
}



/**
 * Copyright (c) 2008 William Bond <will@flourishlib.com>
 * 
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 * 
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 * 
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */