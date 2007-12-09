<?php
/**
 * Provides request-related methods
 * 
 * @copyright  Copyright (c) 2007 William Bond
 * @author     William Bond [wb] <will@flourishlib.com>
 * @license    http://flourishlib.com/license
 * 
 * @version  1.0.0
 * @changes  1.0.0    The initial implementation [wb, 2007-06-14]
 */
class fRequest
{
    /**
     * A backup copy of $_REQUEST for unfilter()
     * 
     * @var array 
     */
    static private $_request = NULL;

    /**
     * A backup copy of $_FILES for unfilter()
     * 
     * @var array 
	 */
	static private $_files = NULL;
	
	
	/**
	 * Prevent instantiation
	 * 
	 * @since  1.0.0
	 * 
	 * @return fRequest
	 */
	private function __construct() { }
	
	
	/**
	 * Gets a value from the $_POST or $_GET superglobals (in that order)
	 * 
	 * @since  1.0.0
	 * 
	 * @param  string $parameter     The parameter to get the value of
	 * @param  string $cast_to       Cast the value to this data type
     * @param  mixed $default_value  If the parameter is not set in $_POST or $_GET, use this value instead
	 * @return mixed  The value
     */
	static public function get($parameter, $cast_to=NULL, $default_value=NULL)
	{
        $value = $default_value;
		if (isset($_POST[$parameter])) {
            $value = $_POST[$parameter];   
		} elseif (isset($_GET[$parameter])) {
			$value = $_GET[$parameter];
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
			if (strtolower($value) == 'f' || strtolower($value) == 'false' || !$value) {
				$value = FALSE;
			} else {
				$value = TRUE;
			}   
        }
		if ($cast_to) {
			settype($value, $cast_to);   
		}
		return $value;
	}
	
	
	/**
	 * Indicated if the parameter specified is set in the $_POST or $_GET superglobals
	 * 
	 * @since  1.0.0
	 * 
	 * @param  string $parameter     The parameter to check
	 * @return boolean  If the parameter is set
	 */
	static public function check($parameter)
	{
		return isset($_POST[$parameter]) || isset($_GET[$parameter]);
	}
	
	
	/**
	 * Gets a value from the $_POST or $_GET superglobals (in that order), restricting to a specific set of values
	 * 
	 * @since  1.0.0
	 * 
	 * @param  string $parameter      The parameter to get the value of
	 * @param  array  $valid_values   The array of values that are permissible, if one is not selected, picks first
	 * @return mixed  The value
	 */
	static public function getFromArray($parameter, $valid_values)
	{
		settype($valid_values, 'array');
		$valid_values = array_merge(array_unique($valid_values));
		$value = self::get($parameter);
		if (!in_array($value, $valid_values)) {
			return $valid_values[0];	
		}
		return $value;
	}
	
    
    /**
     * Parses through $_REQUEST and $_FILES and filters out everything that doesn't match the prefix and key, also removes the prefix from the field name
     * 
     * @since  1.0.0
     * 
     * @param  string $prefix   The prefix to filter by
     * @param  mixed  $key      If the field is an array, get the value corresponding to this key
     * @return void
     */
    static public function filter($prefix, $key)
    {
        self::$_request = $_REQUEST;
        self::$_files   = $_FILES;
            
        $_REQUEST = array();
        foreach (self::$_request as $field => $value) {
            if (strpos($field, $prefix) === 0) {
                $new_field = preg_replace('#^' . preg_quote($field) . '#', '', $field);
                if (is_array($value)) {
                    if (isset($value[$key])) {
                        $_REQUEST[$new_field] = $value[$key];    
                    }
                }               
            } 
        }
        
        $_FILES = array();
        foreach (self::$_files as $field => $value) {
            if (strpos($field, $prefix) === 0) {
                $new_field = preg_replace('#^' . preg_quote($field) . '#', '', $field);
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
    }
    
    
    /**
     * Returns $_REQUEST and $_FILES to the state they were at before filter() was called
     * 
     * @since  1.0.0
     * 
     * @return void
     */
    static public function unfilter()
    {
        if (self::$_request === NULL || self::$_files === NULL) {
            fCore::toss('fProgrammerException', 'fRequest::unfilter() can only be called after fRequest::filter()');   
        }
        $_REQUEST = self::$_request;
		$_FILES   = self::$_files;
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