<?php
/**
 * Provides validation routines for standalone forms, such as contact forms
 * 
 * @copyright  Copyright (c) 2007-2009 Will Bond
 * @author     Will Bond [wb] <will@flourishlib.com>
 * @license    http://flourishlib.com/license
 * 
 * @package    Flourish
 * @link       http://flourishlib.com/fValidation
 * 
 * @version    1.0.0b5
 * @changes    1.0.0b5  Added the `$return_messages` parameter to ::validate() and updated code for new fValidationException API [wb, 2009-09-17]
 * @changes    1.0.0b4  Changed date checking from `strtotime()` to fTimestamp for better localization support [wb, 2009-06-01]
 * @changes    1.0.0b3  Updated for new fCore API [wb, 2009-02-16]
 * @changes    1.0.0b2  Added support for validating date and URL fields [wb, 2009-01-23]
 * @changes    1.0.0b   The initial implementation [wb, 2007-06-14]
 */
class fValidation
{
	/**
	 * Composes text using fText if loaded
	 * 
	 * @param  string  $message    The message to compose
	 * @param  mixed   $component  A string or number to insert into the message
	 * @param  mixed   ...
	 * @return string  The composed and possible translated message
	 */
	static protected function compose($message)
	{
		$args = array_slice(func_get_args(), 1);
		
		if (class_exists('fText', FALSE)) {
			return call_user_func_array(
				array('fText', 'compose'),
				array($message, $args)
			);
		} else {
			return vsprintf($message, $args);
		}
	}
	
	
	/**
	 * Returns `TRUE` for non-empty strings, numbers, objects, empty numbers and string-like numbers (such as `0`, `0.0`, `'0'`)
	 * 
	 * @param  mixed $value  The value to check
	 * @return boolean  If the value is string-like
	 */
	static protected function stringlike($value)
	{
		if ((!is_string($value) && !is_object($value) && !is_numeric($value)) || !strlen(trim($value))) {
			return FALSE;	
		}
		
		return TRUE;
	}
	
	
	/**
	 * Fields that should be valid dates
	 * 
	 * @var array
	 */
	private $date_fields = array();
	
	/**
	 * Fields that should be formatted as email addresses
	 * 
	 * @var array
	 */
	private $email_fields = array();
	
	/**
	 * Fields that will be included in email headers and should be checked for email injection
	 * 
	 * @var array
	 */
	private $email_header_fields = array();
	
	/**
	 * The fields to be required
	 * 
	 * @var array
	 */
	private $required_fields = array();
	
	/**
	 * Fields that should be formatted as URLs
	 * 
	 * @var array
	 */
	private $url_fields = array();
	
	
	/**
	 * All requests that hit this method should be requests for callbacks
	 * 
	 * @internal
	 * 
	 * @param  string $method  The method to create a callback for
	 * @return callback  The callback for the method requested
	 */
	public function __get($method)
	{
		return array($this, $method);		
	}
	
	
	/**
	 * Adds form fields to the list of fields to be blank or a valid date
	 * 
	 * Use ::addRequiredFields() disallow blank values.
	 * 
	 * @param  string $field  Any number of fields that should contain a valid date
	 * @param  string ...
	 * @return void
	 */
	public function addDateFields()
	{
		$args = func_get_args();
		foreach ($args as $arg) {
			if (!self::stringlike($arg)) {
				throw new fProgrammerException(
					'The field specified, %s, does not appear to be a valid field name',
					$arg
				);
			}
		}
		$this->date_fields = array_merge($this->date_fields, $args);
	}
	
	
	/**
	 * Adds form fields to the list of fields to be blank or a valid email address
	 * 
	 * Use ::addRequiredFields() disallow blank values.
	 * 
	 * @param  string $field  Any number of fields that should contain a valid email address
	 * @param  string ...
	 * @return void
	 */
	public function addEmailFields()
	{
		$args = func_get_args();
		foreach ($args as $arg) {
			if (!self::stringlike($arg)) {
				throw new fProgrammerException(
					'The field specified, %s, does not appear to be a valid field name',
					$arg
				);
			}
		}
		$this->email_fields = array_merge($this->email_fields, $args);
	}
	
	
	/**
	 * Adds form fields to be checked for email injection
	 * 
	 * Every field that is included in email headers should be passed to this
	 * method.
	 * 
	 * @param  string $field  Any number of fields to be checked for email injection
	 * @param  string ...
	 * @return void
	 */
	public function addEmailHeaderFields()
	{
		$args = func_get_args();
		foreach ($args as $arg) {
			if (!self::stringlike($arg)) {
				throw new fProgrammerException(
					'The field specified, %s, does not appear to be a valid field name',
					$arg
				);
			}
		}
		$this->email_header_fields = array_merge($this->email_header_fields, $args);
	}
	
	
	/**
	 * Adds form fields to be required, taking each parameter as a field name
	 * 
	 * To require one of multiple fields, pass an array of fields as the parameter.
	 * 
	 * To conditionally require fields, pass an associative array of with the
	 * key being the field that will trigger the other fields to be required:
	 * 
	 * {{{
	 * #!php
	 * array(
	 *     'trigger_field' => array(
	 *         'conditionally_required_field',
	 *         'second_conditionally_required_field'
	 *     )
	 * );
	 * }}}
	 * 
	 * @param  mixed $field  Any number of fields to check
	 * @param  mixed ...
	 * @return void
	 */
	public function addRequiredFields()
	{
		$args       = func_get_args();
		$fixed_args = array();
		
		foreach ($args as $arg) {
			// This handles normal field validation
			if (self::stringlike($arg)) {
				$fixed_args[] = $arg;
			
			// This allows for 'or' validation
			} elseif (is_array($arg) && sizeof($arg) > 1) {
				$fixed_args[] = $arg;
			
			// This handles conditional validation
			} elseif (is_array($arg) && sizeof($arg) == 1 && self::stringlike(key($arg)) && is_array(reset($arg))) {
				$fixed_args[key($arg)] = reset($arg);
				
			} else {
				throw new fProgrammerException(
					'The field specified, %s, does not appear to be a valid required field definition',
					$arg
				);
			}
		}
		
		$this->required_fields = array_merge($this->required_fields, $fixed_args);
	}
	
	
	/**
	 * Adds form fields to the list of fields to be blank or a valid URL
	 * 
	 * Use ::addRequiredFields() disallow blank values.
	 * 
	 * @param  string $field  Any number of fields that should contain a valid URL
	 * @param  string ...
	 * @return void
	 */
	public function addURLFields()
	{
		$args = func_get_args();
		foreach ($args as $arg) {
			if (!self::stringlike($arg)) {
				throw new fProgrammerException(
					'The field specified, %s, does not appear to be a valid field name',
					$arg
				);
			}
		}
		$this->url_fields = array_merge($this->url_fields, $args);
	}
	
	
	/**
	 * Validates the date fields, requiring that any date fields that have a value that can be interpreted as a date
	 * 
	 * @param  array &$messages  The messages to display to the user
	 * @return void
	 */
	private function checkDateFields(&$messages)
	{
		foreach ($this->date_fields as $date_field) {
			$value = trim(fRequest::get($date_field));
			if (self::stringlike($value)) {
				try {
					new fTimestamp($value);	
				} catch (fValidationException $e) {
					$messages[] = self::compose(
						'%sPlease enter a date',
						fValidationException::formatField(fGrammar::humanize($date_field))
					);
				}
			}
		}
	}
	
	
	/**
	 * Validates the email fields, requiring that any email fields that have a value are formatted like an email address
	 * 
	 * @param  array &$messages  The messages to display to the user
	 * @return void
	 */
	private function checkEmailFields(&$messages)
	{
		foreach ($this->email_fields as $email_field) {
			$value = trim(fRequest::get($email_field));
			if (self::stringlike($value) && !preg_match(fEmail::EMAIL_REGEX, $value)) {
				$messages[] = self::compose(
					'%sPlease enter an email address in the form name@example.com',
					fValidationException::formatField(fGrammar::humanize($email_field))
				);
			}
		}
	}
	
	
	/**
	 * Validates email header fields to ensure they don't have newline characters (which allow for email header injection)
	 * 
	 * @param  array &$messages  The messages to display to the user
	 * @return void
	 */
	private function checkEmailHeaderFields(&$messages)
	{
		foreach ($this->email_header_fields as $email_header_field) {
			if (preg_match('#\r|\n#', fRequest::get($email_header_field))) {
				$messages[] = self::compose(
					'%sLine breaks are not allowed',
					fValidationException::formatField(fGrammar::humanize($email_header_field))
				);
			}
		}
	}
	
	
	/**
	 * Validates the required fields, adding any missing fields to the messages array
	 * 
	 * @param  array &$messages  The messages to display to the user
	 * @return void
	 */
	private function checkRequiredFields(&$messages)
	{
		foreach ($this->required_fields as $key => $required_field) {
			// Handle single fields
			if (is_numeric($key) && is_string($required_field)) {
				if (!self::hasValue($required_field)) {
					$messages[] = self::compose(
						'%sPlease enter a value',
						fValidationException::formatField(fGrammar::humanize($required_field))
					);
				}
				
			// Handle one of multiple fields
			} elseif (is_numeric($key) && is_array($required_field)) {
				$found = FALSE;
				foreach ($required_field as $individual_field) {
					if (self::hasValue($individual_field)) {
						$found = TRUE;
					}
				}
				
				if (!$found) {
					$required_field = array_map(array('fGrammar', 'humanize'), $required_field);
					$messages[] = self::compose(
						'%sPlease enter at least one',
						fValidationException::formatField(join(', ', $required_field))
					);
				}
				
			// Handle conditional fields
			} else {
				if (!self::hasValue($key)) {
					continue;
				}
				foreach ($required_field as $individual_field) {
					if (!self::hasValue($individual_field)) {
						$messages[] = self::compose(
							'%sPlease enter a value',
							fValidationException::formatField(fGrammar::humanize($individual_field))
						);
					}
				}
			}
		}
	}
	
	
	/**
	 * Validates the URL fields, requiring that any URL fields that have a value are valid URLs
	 * 
	 * @param  array &$messages  The messages to display to the user
	 * @return void
	 */
	private function checkURLFields(&$messages)
	{
		foreach ($this->url_fields as $url_field) {
			$value = trim(fRequest::get($url_field));
			if (self::stringlike($value) && !preg_match('#^https?://[^ ]+$#iD', $value)) {
				$messages[] = self::compose(
					'%sPlease enter a URL in the form http://www.example.com/page',
					fValidationException::formatField(fGrammar::humanize($url_field))
				);
			}
		}
	}
	
	
	/**
	 * Check if a field has a value
	 * 
	 * @param  string $key  The key to check for a value
	 * @return boolean  If the key has a value
	 */
	static private function hasValue($key)
	{
		$value = fRequest::get($key);
		if (self::stringlike($value)) {
			return TRUE;
		}	
		if (is_array($value)) {
			foreach ($value as $individual_value) {
				if (self::stringlike($individual_value)) {
					return TRUE;	
				}
			}	
		}
		return FALSE;
	}
	
	
	/**
	 * Checks for required fields, email field formatting and email header injection using values previously set
	 * 
	 * @throws fValidationException  When one of the options set for the object is violated
	 * 
	 * @param  boolean $return_messages  If an array of validation messages should be returned instead of an exception being thrown
	 * @return void|array  If $return_messages is TRUE, an array of validation messages will be returned
	 */
	public function validate($return_messages=FALSE)
	{
		if (!$this->email_header_fields &&
			  !$this->required_fields &&
			  !$this->email_fields &&
			  !$this->date_fields &&
			  !$this->url_fields) {
			throw new fProgrammerException(
				'No fields have been set to be validated'
			);
		}
		
		$messages = array();
		
		$this->checkRequiredFields($messages);
		$this->checkDateFields($messages);
		$this->checkEmailFields($messages);
		$this->checkEmailHeaderFields($messages);
		$this->checkURLFields($messages);
		
		if ($return_messages) {
			return $messages;
		}
		
		if ($messages) {
			throw new fValidationException(
				'The following problems were found:',
				$messages
			);
		}
	}
}



/**
 * Copyright (c) 2007-2009 Will Bond <will@flourishlib.com>
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