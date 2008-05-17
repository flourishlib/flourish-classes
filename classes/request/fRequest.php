<?php
/**
 * Provides request-related methods
 * 
 * @copyright  Copyright (c) 2007-2008 William Bond
 * @author     William Bond [wb] <will@flourishlib.com>
 * @license    http://flourishlib.com/license
 * 
 * @link  http://flourishlib.com/fRequest
 * 
 * @uses  fCore
 * @uses  fProgrammerException
 * @uses  fURL
 * 
 * @version  1.0.0
 * @changes  1.0.0    The initial implementation [wb, 2007-06-14]
 */
class fRequest
{
	/**
	 * A backup copy of $_FILES for unfilter()
	 * 
	 * @var array
	 */
	static private $_files = NULL;
	
	/**
	 * A backup copy of $_GET for unfilter()
	 * 
	 * @var array
	 */
	static private $_get = NULL;
	
	/**
	 * A backup copy of $_POST for unfilter()
	 * 
	 * @var array
	 */
	static private $_post = NULL;
	
	
	/**
	 * Indicated if the parameter specified is set in the $_GET or $_POST or  superglobals
	 * 
	 * @param  string $key  The key to check
	 * @return boolean  If the parameter is set
	 */
	static public function check($key)
	{
		return isset($_GET[$key]) || isset($_POST[$key]);
	}
	
	
	/**
	 * Parses through $_FILES, $_GET and $_POST and filters out everything that doesn't match the prefix and key, also removes the prefix from the field name
	 * 
	 * @internal
	 * 
	 * @param  string $prefix  The prefix to filter by
	 * @param  mixed  $key     If the field is an array, get the value corresponding to this key
	 * @return void
	 */
	static public function filter($prefix, $key)
	{
		self::$_files   = $_FILES;
		self::$_get     = $_GET;
		self::$_post    = $_POST;
		
		$_FILES = array();
		foreach (self::$_files as $field => $value) {
			if (strpos($field, $prefix) === 0) {
				$new_field = preg_replace('#^' . preg_quote($prefix, '#') . '#', '', $field);
				if (is_array($value)) {
					if (isset($value['name'][$key])) {
						$_FILES[$new_field]['name']     = $value['name'][$key];
						$_FILES[$new_field]['type']     = $value['type'][$key];
						$_FILES[$new_field]['tmp_name'] = $value['tmp_name'][$key];
						$_FILES[$new_field]['error']    = $value['error'][$key];
						$_FILES[$new_field]['size']     = $value['size'][$key];
					}
				}
			}
		}
			
		$_GET = array();
		foreach (self::$_get as $field => $value) {
			if (strpos($field, $prefix) === 0) {
				$new_field = preg_replace('#^' . preg_quote($prefix, '#') . '#', '', $field);
				if (is_array($value)) {
					if (isset($value[$key])) {
						$_GET[$new_field] = $value[$key];
					}
				}
			}
		}
		
		$_POST = array();
		foreach (self::$_post as $field => $value) {
			if (strpos($field, $prefix) === 0) {
				$new_field = preg_replace('#^' . preg_quote($prefix, '#') . '#', '', $field);
				if (is_array($value)) {
					if (isset($value[$key])) {
						$_POST[$new_field] = $value[$key];
					}
				}
			}
		}
	}
	
	
	/**
	 * Gets a value from the $_POST or $_GET superglobals (in that order). A
	 * value that === '' and is not cast to a specific type will become NULL.
	 * 
	 * @param  string $key            The key to get the value of
	 * @param  string $cast_to        Cast the value to this data type
	 * @param  mixed  $default_value  If the parameter is not set in $_POST or $_GET, use this value instead
	 * @return mixed  The value
	 */
	static public function get($key, $cast_to=NULL, $default_value=NULL)
	{
		$value = $default_value;
		if (isset($_POST[$key])) {
			$value = $_POST[$key];
		} elseif (isset($_GET[$key])) {
			$value = $_GET[$key];
		}
		
		if (get_magic_quotes_gpc()) {
			if (is_array($value)) {
				$value = array_map('stripslashes', $value);
			} else {
				$value = stripslashes($value);
			}
		}
		
		if ($cast_to == 'array' && is_string($value) && strpos($value, ',') !== FALSE) {
			$value = explode(',', $value);
		}
		
		if ($cast_to == 'bool' || $cast_to == 'boolean') {
			if (strtolower($value) == 'f' || strtolower($value) == 'false' || strtolower($value) == 'no' || !$value) {
				$value = FALSE;
			} else {
				$value = TRUE;
			}
		}
		
		if ($cast_to == 'array' && ($value === NULL || $value === '')) {
			$value = array();
		} elseif ($cast_to != 'string' && $value === '') {
			$value = NULL;
		} elseif ($cast_to && $value !== NULL) {
			settype($value, $cast_to);
		}
		
		return $value;
	}
	
	
	/**
	 * Gets a value from the $_POST or $_GET superglobals (in that order), restricting to a specific set of values
	 * 
	 * @param  string $key           The key to get the value of
	 * @param  array  $valid_values  The array of values that are permissible, if one is not selected, picks first
	 * @return mixed  The value
	 */
	static public function getValid($key, $valid_values)
	{
		settype($valid_values, 'array');
		$valid_values = array_merge(array_unique($valid_values));
		$value = self::get($key);
		if (!in_array($value, $valid_values)) {
			return $valid_values[0];
		}
		return $value;
	}
	
	
	/**
	 * Indicates if the URL was accessed via the POST HTTP method
	 * 
	 * @return boolean  If the URL was accessed via the POST HTTP method
	 */
	static public function isPost()
	{
		return strtolower($_SERVER['REQUEST_METHOD']) == 'post';
	}
	
	
	/**
	 * Overrides the value of 'action' in $_GET and $_POST based on the 'action::ACTION_NAME' value in $_GET and $_POST. Used for multiple submit buttons.
	 * 
	 * @param  string $redirect  The url to redirect to if the action is overriden. %%action%% will be replaced with the overridden action.
	 * @return void
	 */
	static public function overrideAction($redirect=NULL)
	{
		$found = FALSE;
		
		foreach ($_GET as $key => $value) {
			if (substr($key, 0, 8) == 'action::') {
				$found = $_GET['action'] = substr($key, 8);
				unset($_GET[$key]);
			}
		}
		
		foreach ($_POST as $key => $value) {
			if (substr($key, 0, 8) == 'action::') {
				$found = $_POST['action'] = substr($key, 8);
				unset($_POST[$key]);
			}
		}
		
		if ($redirect && $found) {
			fURL::redirect(str_replace('%%action%%', $found, $redirect));
		}
	}
	
	
	/**
	 * Returns $_REQUEST and $_FILES to the state they were at before filter() was called
	 * 
	 * @internal
	 * 
	 * @return void
	 */
	static public function unfilter()
	{
		if (self::$_files === NULL || self::$_get === NULL || self::$_post === NULL) {
			fCore::toss('fProgrammerException', 'fRequest::unfilter() can only be called after fRequest::filter()');
		}
		$_FILES   = self::$_files;
		$_GET     = self::$_get;
		$_POST    = self::$_post;
	}
	
	
	/**
	 * Forces use as a static class
	 * 
	 * @return fRequest
	 */
	private function __construct() { }
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