<?php
/**
 * An exception caused by a data not matching a rule or set of rules
 * 
 * @copyright  Copyright (c) 2007-2008 Will Bond
 * @author     Will Bond [wb] <will@flourishlib.com>
 * @license    http://flourishlib.com/license
 * 
 * @package    Flourish
 * @link       http://flourishlib.com/fValidationException
 * 
 * @version    1.0.0b
 * @changes    1.0.0b  The initial implementation [wb, 2007-06-14]
 */
class fValidationException extends fExpectedException
{
	/**
	 * The formatting string to use for field names
	 * 
	 * @var string
	 */
	static protected $field_format = '%s: ';
	
	
	/**
	 * Accepts a field name and formats it based on the formatting string set via ::setFieldFormat()
	 * 
	 * @param string $field  The name of the field to format
	 * @return string  The formatted field name
	 */
	static public function formatField($field)
	{
		return sprintf(self::$field_format, $field);	
	}
	
	
	/**
	* Set the format to be applied to all field names used in fValidationExceptions
	* 
	* The format should contain exactly one `%s`
	* [http://php.net/sprintf sprintf()] conversion specification, which will
	* be replaced with the field name. Any literal `%` characters should be
	* written as `%%`.
	* 
	* The default format is just `%s: `, which simply inserts a `:` and space
	* after the field name.
	* 
	* @param string $format  A string to format the field name with - `%s` will be replaced with the field name
	* @return void
	*/
	static public function setFieldFormat($format)
	{
		if (substr_count(str_replace('%%', '', $format), '%') != 1 || strpos($format, '%s') === FALSE) {
			throw new fProgrammerException(
				'The format, %s, has more or less than exactly one %%s sprintf() conversion specification',
				$format
			);	
		}
		self::$field_format = $format;		
	}
}



/**
 * Copyright (c) 2007-2008 Will Bond <will@flourishlib.com>
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