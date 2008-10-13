<?php
/**
 * Provides request-related methods
 * 
 * This class is implemented to use the UTF-8 character encoding. Please see
 * {@link http://flourishlib.com/docs/UTF-8} for more information.
 * 
 * Please also note that using this class in a PUT or DELETE request will
 * cause the php://input stream to be consumed, and thus no longer available.
 * 
 * @copyright  Copyright (c) 2007-2008 William Bond
 * @author     William Bond [wb] <will@flourishlib.com>
 * @license    http://flourishlib.com/license
 * 
 * @package    Flourish
 * @link       http://flourishlib.com/fRequest
 * 
 * @version    1.0.0b
 * @changes    1.0.0b  The initial implementation [wb, 2007-06-14]
 */
class fRequest
{
	// The following constants allow for nice looking callbacks to static methods
	const check                 = 'fRequest::check';
	const filter                = 'fRequest::filter';
	const get                   = 'fRequest::get';
	const getAcceptLanguages    = 'fRequest::getAcceptLanguages';
	const getAcceptTypes        = 'fRequest::getAcceptTypes';
	const getBestAcceptLanguage = 'fRequest::getBestAcceptLanguage';
	const getBestAcceptType     = 'fRequest::getBestAcceptType';
	const getValid              = 'fRequest::getValid';
	const isDelete              = 'fRequest::isDelete';
	const isGet                 = 'fRequest::isGet';
	const isPost                = 'fRequest::isPost';
	const isPut                 = 'fRequest::isPut';
	const overrideAction        = 'fRequest::overrideAction';
	const reset                 = 'fRequest::reset';
	const set                   = 'fRequest::set';
	const unfilter              = 'fRequest::unfilter';
	
	
	/**
	 * A backup copy of $_FILES for unfilter()
	 * 
	 * @var array
	 */
	static private $backup_files = NULL;
	
	/**
	 * A backup copy of $_GET for unfilter()
	 * 
	 * @var array
	 */
	static private $backup_get = NULL;
	
	/**
	 * A backup copy of $_POST for unfilter()
	 * 
	 * @var array
	 */
	static private $backup_post = NULL;
	
	/**
	 * A backup copy of the local $_PUT for unfilter()
	 * 
	 * @var array
	 */
	static private $backup_put_delete = NULL;
	
	/**
	 * The key/value pairs from the post data of a PUT/DELETE request
	 * 
	 * @var array
	 */
	static private $put_delete = NULL;
	
	
	/**
	 * Indicated if the parameter specified is set in the $_GET or $_POST superglobals or in the post data of a PUT or DELETE request
	 * 
	 * @param  string $key  The key to check
	 * @return boolean  If the parameter is set
	 */
	static public function check($key)
	{
		self::initPutDelete();
		
		return isset($_GET[$key]) || isset($_POST[$key]) || isset(self::$put_delete[$key]);
	}
	
	
	/**
	 * Parses through $_FILES, $_GET, $_POST and the PUT/DELETE post data and filters out everything that doesn't match the prefix and key, also removes the prefix from the field name
	 * 
	 * @internal
	 * 
	 * @param  string $prefix  The prefix to filter by
	 * @param  mixed  $key     If the field is an array, get the value corresponding to this key
	 * @return void
	 */
	static public function filter($prefix, $key)
	{
		self::initPutDelete();
		
		$regex = '#^' . preg_quote($prefix, '#') . '#';
		
		self::$backup_files = $_FILES;
		
		$_FILES = array();
		foreach (self::$backup_files as $field => $value) {
			if (strpos($field, $prefix) === 0 && is_array($value) && isset($value['name'][$key])) {
				$new_field = preg_replace($regex, '', $field);
				$_FILES[$new_field]['name']     = $value['name'][$key];
				$_FILES[$new_field]['type']     = $value['type'][$key];
				$_FILES[$new_field]['tmp_name'] = $value['tmp_name'][$key];
				$_FILES[$new_field]['error']    = $value['error'][$key];
				$_FILES[$new_field]['size']     = $value['size'][$key];
			}
		}
		
		$globals = array(
			'get'        => array('array' => &$_GET,             'backup' => &self::$backup_get),
			'post'       => array('array' => &$_POST,            'backup' => &self::$backup_post),
			'put/delete' => array('array' => &self::$put_delete, 'backup' => &self::$backup_put_delete)
		);
		
		foreach ($globals as $refs) {
			$refs['backup'] = $refs['array'];
			$refs['array']  = array();	
			foreach ($refs['backup'] as $field => $value) {
				if (strpos($field, $prefix) === 0 && is_array($value) && isset($value[$key])) {
					$new_field = preg_replace($regex, '', $field);
					$refs['array'][$new_field] = $value[$key];
				}
			}
		}
	}
	
	
	/**
	 * Gets a value from the DELETE/PUT post data, $_POST or $_GET superglobals (in that order)
	 * 
	 * A value that === '' and is not cast to a specific type will become NULL.
	 *  
	 * All text values are interpreted as UTF-8 string and appropriately
	 * cleaned.
	 * 
	 * @param  string $key            The key to get the value of
	 * @param  string $cast_to        Cast the value to this data type
	 * @param  mixed  $default_value  If the parameter is not set in $_POST or $_GET, use this value instead
	 * @return mixed  The value
	 */
	static public function get($key, $cast_to=NULL, $default_value=NULL)
	{
		self::initPutDelete();
		
		$value = $default_value;
		if (isset(self::$put_delete[$key])) {
			$value = self::$put_delete[$key];
		} elseif (isset($_POST[$key])) {
			$value = $_POST[$key];
		} elseif (isset($_GET[$key])) {
			$value = $_GET[$key];
		}
		
		if (get_magic_quotes_gpc() && (self::isPost() || self::isGet())) {
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
		
		// Clean values coming in to ensure we don't have invalid UTF-8
		if (($cast_to === NULL || $cast_to == 'string' || $cast_to == 'array') && $value !== NULL) {
			$value = fUTF8::clean($value);
		}
		
		return $value;
	}
	
	
	/**
	 * Returns the HTTP accept languages sorted by their q values (from high to low)
	 * 
	 * @return array  An associative array of {language} => {q value} sorted (in a stable-fashion) from highest to lowest q
	 */
	static public function getAcceptLanguages()
	{
		return self::processAcceptHeader($_SERVER['HTTP_ACCEPT_LANGUAGE']);
	}
	
	
	/**
	 * Returns the HTTP accept types sorted by their q values (from high to low)
	 * 
	 * @return array  An associative array of {type} => {q value} sorted (in a stable-fashion) from highest to lowest q
	 */
	static public function getAcceptTypes()
	{
		return self::processAcceptHeader($_SERVER['HTTP_ACCEPT']);
	}
	
	
	/**
	 * Returns the best HTTP accept language (based on q value) - can be filtered to only allow certain languages
	 * 
	 * @param  array $filter  An array of languages that are valid to return
	 * @return string  The best language listed in the accept header
	 */
	static public function getBestAcceptLanguage($filter=array())
	{
		return self::pickBestAcceptItem($_SERVER['HTTP_ACCEPT_LANGUAGE'], $filter);
	}
	
	
	/**
	 * Returns the best HTTP accept type (based on q value) - can be filtered to only allow certain types
	 * 
	 * @param  array $filter  An array of types that are valid to return
	 * @return string  The best type listed in the accept header
	 */
	static public function getBestAcceptType($filter=array())
	{
		return self::pickBestAcceptItem($_SERVER['HTTP_ACCEPT'], $filter);
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
	 * Parses post data for PUT and DELETE HTTP methods
	 * 
	 * @return void
	 */
	static private function initPutDelete()
	{
		if (is_array(self::$put_delete)) {
			return;	
		}
		
		if (self::isPut() || self::isDelete()) {
			parse_str(file_get_contents('php://input'), self::$put_delete);
		} else {
			self::$put_delete = array();
		}
	}
	
	
	/**
	 * Indicates if the URL was accessed via the DELETE HTTP method
	 * 
	 * @return boolean  If the URL was accessed via the DELETE HTTP method
	 */
	static public function isDelete()
	{
		return strtolower($_SERVER['REQUEST_METHOD']) == 'delete';
	}
	
	
	/**
	 * Indicates if the URL was accessed via the GET HTTP method
	 * 
	 * @return boolean  If the URL was accessed via the GET HTTP method
	 */
	static public function isGet()
	{
		return strtolower($_SERVER['REQUEST_METHOD']) == 'get';
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
	 * Indicates if the URL was accessed via the PUT HTTP method
	 * 
	 * @return boolean  If the URL was accessed via the PUT HTTP method
	 */
	static public function isPut()
	{
		return strtolower($_SERVER['REQUEST_METHOD']) == 'put';
	}
	
	
	/**
	 * Overrides the value of 'action' in $_GET and $_POST based on the 'action::ACTION_NAME' value in $_GET and $_POST
	 * 
	 * This method is primarily intended to be used for hanlding multiple
	 * submit buttons.
	 * 
	 * @param  string $redirect  The url to redirect to if the action is overriden. %action% will be replaced with the overridden action.
	 * @return void
	 */
	static public function overrideAction($redirect=NULL)
	{
		self::initPutDelete();
		
		$found = FALSE;
		
		$globals = array(&$_GET, &$_POST, &self::$put_delete);
		
		foreach ($globals as &$global) {
			foreach ($global as $key => $value) {
				if (substr($key, 0, 8) == 'action::') {
					$found = (boolean) $global['action'] = substr($key, 8);
					unset($global[$key]);
				}
			}
		}
		
		if ($redirect && $found) {
			fURL::redirect(str_replace('%action%', $found, $redirect));
		}
	}
	
	
	/**
	 * Returns the best HTTP accept header item match (based on q value), optionally filtered by an array of options
	 * 
	 * @param  string $header   The accept header to pick the best item from
	 * @param  array  $options  A list of supported options to pick the best from
	 * @return string  The best accept item, NULL if an options array is specified and none are valid
	 */
	static private function pickBestAcceptItem($header, $options)
	{
		settype($options, 'array');
		
		$items = self::processAcceptHeader($header);
		
		if (!$options) {
			return key($items);		
		}
		
		$top_q    = 0;
		$top_item = NULL;
		
		foreach ($items as $item => $q) {
			if ($q < $top_q) {
				continue;	
			}
			
			// Type matches have /s
			if (strpos($item, '/') !== FALSE) {
				$regex = '#^' . str_replace('*', '.*', $item) . '$#i';
			
			// Language matches that don't have a - are a wildcard
			} elseif (strpos($item, '-') === FALSE) {
				$regex = '#^' . str_replace('*', '.*', $item) . '(-.*)?$#i';	
				
			// Non-wildcard languages are straight-up matches
			} else {
				$regex = '#^' . str_replace('*', '.*', $item) . '$#i';	
			}
			foreach ($options as $option) {
				if (preg_match($regex, $option) && $top_q < $q) {
					$top_q = $q;
					$top_item = $option;
					continue 2;
				}	
			}
		}
		
		return $top_item;
	}
	
	
	/**
	 * Returns an array of values from one of the HTTP Accept-* headers
	 * 
	 * @return array  An associative array of {value} => {quality} sorted (in a stable-fashion) from highest to lowest quality
	 */
	static private function processAcceptHeader($header)
	{
		$types  = explode(',', $header);
		$output = array();
		
		// We use this suffix to force stable sorting with the built-in sort function
		$suffix = sizeof($types);
		
		foreach ($types as $type) {
			$parts = explode(';', $type);
			
			if (!empty($parts[1]) && preg_match('#^q=(\d(?:\.\d)?)#', $parts[1], $match)) {
				$q = number_format((float)$match[1], 5);
			} else {
				$q = number_format(1.0, 5);	
			}
			$q .= $suffix--;
			
			$output[$parts[0]] = $q;	
		}
		
		arsort($output, SORT_NUMERIC);
		
		foreach ($output as $type => $q) {
			$output[$type] = (float) substr($q, 0, -1);	
		}
		
		return $output;
	}
	
	
	/**
	 * Resets the configuration and data of the class
	 * 
	 * @internal
	 * 
	 * @return void
	 */
	static public function reset()
	{
		self::$backup_files      = NULL;
		self::$backup_get        = NULL;
		self::$backup_post       = NULL;
		self::$backup_put_delete = NULL;
		self::$put_delete        = NULL;
	}
	
	
	/**
	 * Sets a value into the appropriate superglobal ($_GET or $_POST) based on what HTTP method was used for the request
	 * 
	 * @param  string $key    The key to set the value to
	 * @param  mixed  $value  The value to set
	 * @return void
	 */
	static public function set($key, $value)
	{		
		if (self::isPost()) {
			$_POST[$key] = $value;	
			return;
		}
		
		if (self::isGet()) {
			$_GET[$key] = $value;	
			return;
		}
		
		if (self::isDelete() || self::isDelete()) {
			self::initPutDelete();
			self::$put_delete[$key] = $value;	
			return;
		}
	}
	
	
	/**
	 * Returns $_GET, $_POST and $_FILES and the PUT/DELTE post data to the state they were at before filter() was called
	 * 
	 * @internal
	 * 
	 * @return void
	 */
	static public function unfilter()
	{
		if (self::$backup_get === NULL) {
			fCore::toss(
				'fProgrammerException',
				fGrammar::compose(
					'%1$s can only be called after %2$s',
					__CLASS__ . '::unfilter()',
					__CLASS__ . '::filter()'
				)
			);
		}
		
		$_FILES           = self::$backup_files;
		$_GET             = self::$backup_get;
		$_POST            = self::$backup_post;
		self::$put_delete = self::$backup_put_delete;
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