<?php
/**
 * Provides validation routines for standalone forms, such as contact forms
 * 
 * @copyright  Copyright (c) 2007-2008 William Bond
 * @author     William Bond [wb] <will@flourishlib.com>
 * @license    http://flourishlib.com/license
 * 
 * @link  http://flourishlib.com/fValidation
 * 
 * @uses  fCore
 * @uses  fInflection
 * @uses  fRequest
 * @uses  fValidationException
 * 
 * @version  1.0.0
 * @changes  1.0.0    The initial implementation [wb, 2007-06-14]
 */
class fValidation
{	
	/**
	 * The fields to be required
	 * 
	 * @var array 
	 */
	private $required_fields;
	
	/**
	 * Fields that should be formatted as email addresses
	 * 
	 * @var array 
	 */
	private $email_fields;
	
	/**
	 * Fields that will be included in email headers and should be checked for email injection
	 * 
	 * @var array 
	 */
	private $email_header_fields;
	
	
	/**
	 * Sets form fields to be required, taking each parameter as a field name.
	 * 
	 * To require one of multiple fields, pass an array of fields as the parameter.
	 * 
	 * To conditionally require fields, pass an associative array of with the key being the field that will trigger the
	 * other fields to be required:
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
	public function setRequiredFields()
	{
		$this->required_fields = func_get_args();		
	}
	
	
	/**
	 * Sets form fields to be required to be blank or a valid email address. Use {@link fValidation::setRequiredFields} to not allow blank values.
	 * 
	 * @param  string $field,...  Any number of fields to required valid email addresses for
	 * @return void
	 */
	public function setEmailFields()
	{
		$this->email_fields = func_get_args();		
	}
	
	
	/**
	 * Sets form fields to be checked for email injection. Every field that is included in email headers should be passed to this method.
	 * 
	 * @param  string $field,...  Any number of fields to be checked for email injection
	 * @return void
	 */
	public function setEmailHeaderFields()
	{
		$this->email_header_fields = func_get_args();		
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
		if (empty($this->email_header_fields) && empty($this->required_fields) && empty($this->email_fields)) {
			fCore::toss('fProgrammerException', 'No fields have been set to be validated');
		}		
		
		$messages = array();
		
		$this->checkRequiredFields($messages);
		$this->checkEmailFields($messages);
		$this->checkEmailHeaderFields($messages);
		
		if (!empty($messages)) {
		 	$errors  = '<p>The following errors were found in your submission:</p><ul><li>';
		 	$errors .= join('</li><li>', $messages);
		 	$errors .= '</li></ul>';	
		 	
		 	fCore::toss('fValidationException', $errors);
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
					$messages[] = fInflection::humanize($required_field) . ' needs to have a value';
				}
				
			// Handle one of multiple fields
			} elseif (is_numeric($key) && is_array($required_field)) {
				$found = FALSE;
				foreach ($required_field as $individual_field) {
					if (fRequest::get($individual_field) !== '' && fRequest::get($individual_field) !== NULL) {
						$found = TRUE;
					}
				}
				
				if (!$found) {
					$required_field = array_map(array('fInflection', 'humanize'), $required_field);
					$messages[] = join(' or ', $required_field) . ' needs to have a value';	
				}
				
			// Handle conditional fields
			} elseif (is_string($key) && (is_string($required_field) || is_array($required_field))) {
				if (fRequest::get($key) !== '' && fRequest::get($key) !== NULL) {
					foreach ($required_field as $individual_field) {
						if (fRequest::get($individual_field) === '' || fRequest::get($individual_field) === NULL) {
							$messages[] = fInflection::humanize($individual_field) . ' needs to have a value';
						}
					}		
				}	
				
			} else {
				fCore::toss('fProgrammerException', 'Unrecognized required field structure');
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
			if (is_string($email_field)) {
				if (fRequest::get($email_field) !== '' && fRequest::get($email_field) !== NULL && !preg_match('#^[a-z0-9\\.\'_\\-\\+]+@(?:[a-z0-9\\-]+\.)+[a-z]{2,}$#i', trim(fRequest::get($email_field)))) {
					$messages[] = fInflection::humanize($email_field) . ' should be in the form name@example.com';	
				}
				
			} else {
				fCore::toss('fProgrammerException', 'Unrecognized email field structure');
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
			if (is_string($email_header_field)) {
				if (preg_match('#\r|\n', fRequest::get($email_header_field))) {
					$messages[] = fInflection::humanize($email_header_field) . ' can not contain line breaks';	
				}
				
			} else {
				fCore::toss('fProgrammerException', 'Unrecognized email header field structure');
			}	
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
?>