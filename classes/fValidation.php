<?php
/**
 * Provides validation routines for standalone forms, such as contact forms
 * 
 * @copyright  Copyright (c) 2007-2008 William Bond
 * @author     William Bond [wb] <will@flourishlib.com>
 * @license    http://flourishlib.com/license
 * 
 * @package    Flourish
 * @link       http://flourishlib.com/fValidation
 * 
 * @version    1.0.0b
 * @changes    1.0.0b  The initial implementation [wb, 2007-06-14]
 */
class fValidation
{
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
	 * Adds form fields to be required to be blank or a valid email address
	 * 
	 * Use {@link fValidation::addRequiredFields()} to not allow blank values.
	 * 
	 * @param  string $field,...  Any number of fields to required valid email addresses for
	 * @return void
	 */
	public function addEmailFields()
	{
		$args = func_get_args();
		foreach ($args as $arg) {
			if (!fCore::stringlike($arg)) {
				fCore::toss(
					'fProgrammerException',
					fGrammar::compose(
						'The field specified, %s, does not appear to be a valid field name',
						fCore::dump($arg)
					)
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
	 * @param  string $field,...  Any number of fields to be checked for email injection
	 * @return void
	 */
	public function addEmailHeaderFields()
	{
		$args = func_get_args();
		foreach ($args as $arg) {
			if (!fCore::stringlike($arg)) {
				fCore::toss(
					'fProgrammerException',
					fGrammar::compose(
						'The field specified, %s, does not appear to be a valid field name',
						fCore::dump($arg)
					)
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
	 * <pre>
	 * array(
	 *     'trigger_field' => array(
	 *         'conditionally_required_field',
	 *         'second_conditionally_required_field'
	 *     )
	 * )
	 * </pre>
	 * 
	 * @param  mixed $field,...  Any number of fields to check
	 * @return void
	 */
	public function addRequiredFields()
	{
		$args       = func_get_args();
		$fixed_args = array();
		
		foreach ($args as $arg) {
			// This handles normal field validation
			if (fCore::stringlike($arg)) {
				$fixed_args[] = $arg;
			
			// This allows for 'or' validation
			} elseif (is_array($arg) && sizeof($arg) > 1) {
				$fixed_args[] = $arg;
			
			// This handles conditional validation
			} elseif (is_array($arg) && sizeof($arg) == 1 && fCore::stringlike(key($arg)) && is_array(reset($arg))) {
				$fixed_args[key($arg)] = reset($arg);
				
			} else {
				fCore::toss(
					'fProgrammerException',
					fGrammar::compose(
						'The field specified, %s, does not appear to be a valid required field definition',
						fCore::dump($arg)
					)
				);
			}
		}
		
		$this->required_fields = array_merge($this->required_fields, $fixed_args);
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
			$value = fRequest::get($email_field);
			if (fCore::stringlike($value) && !preg_match(fEmail::EMAIL_REGEX, $value)) {
				$messages[] = fGrammar::compose(
					'%s: Please enter an email address in the form name@example.com',
					fGrammar::humanize($email_field)
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
				$messages[] = fGrammar::compose(
					'%s: Line breaks are not allowed',
					fGrammar::humanize($email_header_field)
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
				if (fRequest::get($required_field) === '' || fRequest::get($required_field) === NULL) {
					$messages[] = fGrammar::compose(
						'%s: Please enter a value',
						fGrammar::humanize($required_field)
					);
				}
				
			// Handle one of multiple fields
			} elseif (is_numeric($key) && is_array($required_field)) {
				$found = FALSE;
				foreach ($required_field as $individual_field) {
					if (fCore::stringlike(fRequest::get($individual_field))) {
						$found = TRUE;
					}
				}
				
				if (!$found) {
					$required_field = array_map(array('fGrammar', 'humanize'), $required_field);
					$messages[] = fGrammar::compose(
						'%s: Please enter at least one',
						join(', ', $required_field)
					);
				}
				
			// Handle conditional fields
			} else {
				if (!fCore::stringlike(fRequest::get($key))) {
					continue;
				}
				foreach ($required_field as $individual_field) {
					if (!fCore::stringlike(fRequest::get($individual_field))) {
						$messages[] = fGrammar::compose(
							'%s: Please enter a value',
							fGrammar::humanize($individual_field)
						);
					}
				}
			}
		}
	}
	
	
	/**
	 * Checks for required fields, email field formatting and email header injection using values previously set
	 * 
	 * @throws fValidationException
	 * 
	 * @return void
	 */
	public function validate()
	{
		if (!$this->email_header_fields && !$this->required_fields && !$this->email_fields) {
			fCore::toss(
				'fProgrammerException',
				fGrammar::compose(
					'No fields have been set to be validated'
				)
			);
		}
		
		$messages = array();
		
		$this->checkRequiredFields($messages);
		$this->checkEmailFields($messages);
		$this->checkEmailHeaderFields($messages);
		
		if ($messages) {
			fCore::toss(
				'fValidationException',
				sprintf(
					"<p>%1\$s</p>\n<ul>\n<li>%2\$s</li>\n</ul>",
					fGrammar::compose("The following problems were found:"),
					join("</li>\n<li>", $messages)
				)
			);
		}
	}
}



/**
 * Copyright (c) 2007-2008 William Bond <will@flourishlib.com>
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