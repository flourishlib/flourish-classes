<?php
/**
 * Provides validation routines for forms
 * 
 * @copyright  Copyright (c) 2007 William Bond
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
	 * Forces use as a static class
	 * 
	 * @return fValidation
	 */
	private function __construct() { }
	
	
	/**
	 * Validates form fields specified and throws an exception when values are missing, takes each argument as a field name.
	 * 
	 * Will throw an exception with a field name on each line
	 *
	 * @param  string $field,...  Any number of fields to check
	 * @return void
	 */
	static public function requireRequestValue()
	{
		$fields = func_get_args();
		$missing_fields = array();
		
		// Check the fields
		foreach ($fields as $field) {
			if (!fRequest::get($field)) {
				$missing_fields[] = fInflection::humanize($field);
			}	
		}
		
		// Generate the error message
		if (!empty($missing_fields)) {
			fCore::toss('fValidationException', '<p>Please enter a value for the following:</p><ul><li>' . join('</li><li>', $missing_fields) . '</li></ul>');
		}		
	}
	
	
	/**
	 * Validates form fields specified to make sure no new lines are contained, thus preventing email injection attacks
	 *
	 * @param  string $field,...  Any number of fields to check
	 * @return void
	 */
	static public function stopRequestEmailInjection()
	{
		$fields = func_get_args();
		
		$value = '';
		$field_names = array();
		foreach ($fields as $field) {
			$value .= fRequest::get($field);
			$field_names[] = fInflection::humanize($field);
		}
		
		if (preg_match("/\r|\n/", $value)) {
			fCore::toss('fValidationException', '<p>Please remove any line breaks from the following:</p><ul><li>' . join('</li><li>', $field_names) . '</li></ul>');
		}		
	}
}


/**
 * Copyright (c) 2007 William Bond <will@flourishlib.com>
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