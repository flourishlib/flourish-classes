<?php
/**
 * Provides request-related methods
 * 
 * This class is implemented to use the UTF-8 character encoding. Please see
 * http://flourishlib.com/docs/UTF-8 for more information.
 * 
 * Please also note that using this class in a PUT or DELETE request will
 * cause the php://input stream to be consumed, and thus no longer available.
 * 
 * @copyright  Copyright (c) 2007-2010 Will Bond, others
 * @author     Will Bond [wb] <will@flourishlib.com>
 * @author     Alex Leeds [al] <alex@kingleeds.com>
 * @license    http://flourishlib.com/license
 * 
 * @package    Flourish
 * @link       http://flourishlib.com/fRequest
 * 
 * @version    1.0.0b11
 * @changes    1.0.0b11  Added ::isAjax() [al, 2010-03-15]
 * @changes    1.0.0b10  Fixed ::get() to not truncate integers to the 32bit integer limit [wb, 2010-03-05]
 * @changes    1.0.0b9   Updated class to use new fSession API [wb, 2009-10-23]
 * @changes    1.0.0b8   Casting to an integer or string in ::get() now properly casts when the `$key` isn't present in the request, added support for date, time, timestamp and `?` casts [wb, 2009-08-25] 
 * @changes    1.0.0b7   Fixed a bug with ::filter() not properly creating new `$_FILES` entries [wb, 2009-07-02]
 * @changes    1.0.0b6   ::filter() now works with empty prefixes and filtering the `$_FILES` superglobal has been fixed [wb, 2009-07-02]
 * @changes    1.0.0b5   Changed ::filter() so that it can be called multiple times for multi-level filtering [wb, 2009-06-02]
 * @changes    1.0.0b4   Added the HTML escaping functions ::encode() and ::prepare() [wb, 2009-05-27]
 * @changes    1.0.0b3   Updated class to use new fSession API [wb, 2009-05-08]
 * @changes    1.0.0b2   Added ::generateCSRFToken() from fCRUD::generateRequestToken() and ::validateCSRFToken() from fCRUD::validateRequestToken() [wb, 2009-05-08]
 * @changes    1.0.0b    The initial implementation [wb, 2007-06-14]
 */
class fRequest
{
	// The following constants allow for nice looking callbacks to static methods
	const check                 = 'fRequest::check';
	const encode                = 'fRequest::encode';
	const filter                = 'fRequest::filter';
	const generateCSRFToken     = 'fRequest::generateCSRFToken';
	const get                   = 'fRequest::get';
	const getAcceptLanguages    = 'fRequest::getAcceptLanguages';
	const getAcceptTypes        = 'fRequest::getAcceptTypes';
	const getBestAcceptLanguage = 'fRequest::getBestAcceptLanguage';
	const getBestAcceptType     = 'fRequest::getBestAcceptType';
	const getValid              = 'fRequest::getValid';
	const isAjax                = 'fRequest::isAjax';
	const isDelete              = 'fRequest::isDelete';
	const isGet                 = 'fRequest::isGet';
	const isPost                = 'fRequest::isPost';
	const isPut                 = 'fRequest::isPut';
	const overrideAction        = 'fRequest::overrideAction';
	const prepare               = 'fRequest::prepare';
	const reset                 = 'fRequest::reset';
	const set                   = 'fRequest::set';
	const unfilter              = 'fRequest::unfilter';
	const validateCSRFToken     = 'fRequest::validateCSRFToken';
	
	
	/**
	 * A backup copy of `$_FILES` for ::unfilter()
	 * 
	 * @var array
	 */
	static private $backup_files = array();
	
	/**
	 * A backup copy of `$_GET` for ::unfilter()
	 * 
	 * @var array
	 */
	static private $backup_get = array();
	
	/**
	 * A backup copy of `$_POST` for unfilter()
	 * 
	 * @var array
	 */
	static private $backup_post = array();
	
	/**
	 * A backup copy of the local `PUT`/`DELETE` post data for ::unfilter()
	 * 
	 * @var array
	 */
	static private $backup_put_delete = array();
	
	/**
	 * The key/value pairs from the post data of a `PUT`/`DELETE` request
	 * 
	 * @var array
	 */
	static private $put_delete = NULL;
	
	
	/**
	 * Indicated if the parameter specified is set in the `$_GET` or `$_POST` superglobals or in the post data of a `PUT` or `DELETE` request
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
	 * Gets a value from ::get() and passes it through fHTML::encode()
	 * 
	 * @param  string $key            The key to get the value of
	 * @param  string $cast_to        Cast the value to this data type
	 * @param  mixed  $default_value  If the parameter is not set in the `DELETE`/`PUT` post data, `$_POST` or `$_GET`, use this value instead
	 * @return string  The encoded value
	 */
	static public function encode($key, $cast_to=NULL, $default_value=NULL)
	{
		return fHTML::encode(self::get($key, $cast_to, $default_value));
	}
	
	
	/**
	 * Parses through `$_FILES`, `$_GET`, `$_POST` and the `PUT`/`DELETE` post data and filters out everything that doesn't match the prefix and key, also removes the prefix from the field name
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
		
		$current_backup       = sizeof(self::$backup_files);
		self::$backup_files[] = $_FILES;
		
		$_FILES = array();
		foreach (self::$backup_files[$current_backup] as $field => $value) {
			$matches_prefix = !$prefix || ($prefix && strpos($field, $prefix) === 0);
			if ($matches_prefix && is_array($value) && isset($value['name'][$key])) {
				$new_field = preg_replace($regex, '', $field);
				$_FILES[$new_field]             = array();
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
			$current_backup   = sizeof($refs['backup']);
			$refs['backup'][] = $refs['array'];
			$refs['array']    = array();	
			foreach ($refs['backup'][$current_backup] as $field => $value) {
				$matches_prefix = !$prefix || ($prefix && strpos($field, $prefix) === 0);
				if ($matches_prefix && is_array($value) && isset($value[$key])) {
					$new_field = preg_replace($regex, '', $field);
					$refs['array'][$new_field] = $value[$key];
				}
			}
		}
	}
	
	
	/**
	 * Returns a request token that should be placed in each HTML form to prevent [http://en.wikipedia.org/wiki/Cross-site_request_forgery cross-site request forgery]
	 * 
	 * This method will return a random 15 character string that should be
	 * placed in a hidden `input` element on every HTML form. When the form
	 * contents are being processed, the token should be retrieved and passed
	 * into ::validateCSRFToken().
	 * 
	 * The value returned by this method is stored in the session and then
	 * checked by the validate method, which helps prevent cross site request
	 * forgeries and (naive) automated form submissions.
	 * 
	 * Tokens generated by this method are single use, so a user must request
	 * the page that generates the token at least once per submission.
	 * 
	 * @param  string $url  The URL to generate a token for, default to the current page
	 * @return string  The token to be submitted with the form
	 */
	static public function generateCSRFToken($url=NULL)
	{
		if ($url === NULL) {
			$url = fURL::get();	
		}
		
		$token  = fCryptography::randomString(16);
		
		fSession::add(__CLASS__ . '::' . $url . '::csrf_tokens', $token);
		
		return $token;
	}
	
	
	/**
	 * Gets a value from the `DELETE`/`PUT` post data, `$_POST` or `$_GET` superglobals (in that order)
	 * 
	 * A value that exactly equals `''` and is not cast to a specific type will
	 * become `NULL`.
	 * 
	 * Valid `$cast_to` types include:
	 *  - `'string'`,
	 *  - `'int'`
	 *  - `'integer'`
	 *  - `'bool'`
	 *  - `'boolean'`
	 *  - `'array'`
	 *  - `'date'`
	 *  - `'time'`
	 *  - `'timestamp'`
	 * 
	 * It is also possible to append a `?` to a data type to return `NULL`
	 * whenever the `$key` was not specified in the request, or if the value
	 * was a blank string.
	 *  
	 * All text values are interpreted as UTF-8 string and appropriately
	 * cleaned.
	 * 
	 * @param  string $key            The key to get the value of
	 * @param  string $cast_to        Cast the value to this data type - see method description for details
	 * @param  mixed  $default_value  If the parameter is not set in the `DELETE`/`PUT` post data, `$_POST` or `$_GET`, use this value instead. This value will get cast if a `$cast_to` is specified.
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
		
		// This allows for data_type? casts to allow NULL through
		if ($cast_to !== NULL && substr($cast_to, -1) == '?') {
			if ($value === NULL || $value === '') {
				return $value;
			}	
			$cast_to = substr($cast_to, 0, -1);
		}
		
		if (get_magic_quotes_gpc() && (self::isPost() || self::isGet())) {
			if (is_array($value)) {
				$value = array_map('stripslashes', $value);
			} else {
				$value = stripslashes($value);
			}
		}
		
		// This normalizes an empty element to NULL
		if ($cast_to === NULL && $value === '') {
			$value = NULL;
			
		} elseif ($cast_to == 'date') {
			try {
				$value = new fDate($value);
			} catch (fValidationException $e) {
				$value = new fDate();	
			}
			
		} elseif ($cast_to == 'time') {
			try {
				$value = new fTime($value);
			} catch (fValidationException $e) {
				$value = new fTime();	
			}
			
		} elseif ($cast_to == 'timestamp') {
			try {
				$value = new fTimestamp($value);
			} catch (fValidationException $e) {
				$value = new fTimestamp();	
			}
			
		} elseif ($cast_to == 'array' && is_string($value) && strpos($value, ',') !== FALSE) {
			$value = explode(',', $value);
		
		} elseif ($cast_to == 'array' && ($value === NULL || $value === '')) {
			$value = array();
				
		} elseif ($cast_to == 'bool' || $cast_to == 'boolean') {
			if (strtolower($value) == 'f' || strtolower($value) == 'false' || strtolower($value) == 'no' || !$value) {
				$value = FALSE;
			} else {
				$value = TRUE;
			}
			
		} elseif (($cast_to == 'int' || $cast_to == 'integer') && preg_match('#^-?\d+$#D', $value)) {
			// If the cast is an integer and the value is digits, don't cast to prevent
			// truncation due to 32 bit integer limits
			
		} elseif ($cast_to) {
			settype($value, $cast_to);
		}
		
		// Clean values coming in to ensure we don't have invalid UTF-8
		if (($cast_to === NULL || $cast_to == 'string' || $cast_to == 'array') && $value !== NULL) {
			$value = fUTF8::clean($value);
		}
		
		return $value;
	}
	
	
	/**
	 * Returns the HTTP `Accept-Language`s sorted by their `q` values (from high to low)
	 * 
	 * @return array  An associative array of `{language} => {q value}` sorted (in a stable-fashion) from highest to lowest `q`
	 */
	static public function getAcceptLanguages()
	{
		return self::processAcceptHeader($_SERVER['HTTP_ACCEPT_LANGUAGE']);
	}
	
	
	/**
	 * Returns the HTTP `Accept` types sorted by their `q` values (from high to low)
	 * 
	 * @return array  An associative array of `{type} => {q value}` sorted (in a stable-fashion) from highest to lowest `q`
	 */
	static public function getAcceptTypes()
	{
		return self::processAcceptHeader($_SERVER['HTTP_ACCEPT']);
	}
	
	
	/**
	 * Returns the best HTTP `Accept-Language` (based on `q` value) - can be filtered to only allow certain languages
	 * 
	 * @param  array $filter  An array of languages that are valid to return
	 * @return string  The best language listed in the `Accept-Language` header
	 */
	static public function getBestAcceptLanguage($filter=array())
	{
		return self::pickBestAcceptItem($_SERVER['HTTP_ACCEPT_LANGUAGE'], $filter);
	}
	
	
	/**
	 * Returns the best HTTP `Accept` type (based on `q` value) - can be filtered to only allow certain types
	 * 
	 * @param  array $filter  An array of types that are valid to return
	 * @return string  The best type listed in the `Accept` header
	 */
	static public function getBestAcceptType($filter=array())
	{
		return self::pickBestAcceptItem($_SERVER['HTTP_ACCEPT'], $filter);
	}
	
	
	/**
	 * Gets a value from the `DELETE`/`PUT` post data, `$_POST` or `$_GET` superglobals (in that order), restricting to a specific set of values
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
	 * Parses post data for `PUT` and `DELETE` HTTP methods
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
	 * Indicates if the URL was accessed via an XMLHttpRequest
	 * 
	 * @return boolean  If the URL was accessed via an XMLHttpRequest
	 */
	static public function isAjax()
	{
		return isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
	}
	
	
	/**
	 * Indicates if the URL was accessed via the `DELETE` HTTP method
	 * 
	 * @return boolean  If the URL was accessed via the `DELETE` HTTP method
	 */
	static public function isDelete()
	{
		return strtolower($_SERVER['REQUEST_METHOD']) == 'delete';
	}
	
	
	/**
	 * Indicates if the URL was accessed via the `GET` HTTP method
	 * 
	 * @return boolean  If the URL was accessed via the `GET` HTTP method
	 */
	static public function isGet()
	{
		return strtolower($_SERVER['REQUEST_METHOD']) == 'get';
	}
	
	
	/**
	 * Indicates if the URL was accessed via the `POST` HTTP method
	 * 
	 * @return boolean  If the URL was accessed via the `POST` HTTP method
	 */
	static public function isPost()
	{
		return strtolower($_SERVER['REQUEST_METHOD']) == 'post';
	}
	
	
	/**
	 * Indicates if the URL was accessed via the `PUT` HTTP method
	 * 
	 * @return boolean  If the URL was accessed via the `PUT` HTTP method
	 */
	static public function isPut()
	{
		return strtolower($_SERVER['REQUEST_METHOD']) == 'put';
	}
	
	
	/**
	 * Overrides the value of `'action'` in the `DELETE`/`PUT` post data, `$_POST` or `$_GET` superglobals based on the `'action::{action_name}'` value
	 * 
	 * This method is primarily intended to be used for hanlding multiple
	 * submit buttons.
	 * 
	 * @param  string $redirect  The url to redirect to if the action is overriden. `%action%` will be replaced with the overridden action.
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
	 * Returns the best HTTP `Accept-*` header item match (based on `q` value), optionally filtered by an array of options
	 * 
	 * @param  string $header   The `Accept-*` header to pick the best item from
	 * @param  array  $options  A list of supported options to pick the best from
	 * @return string  The best accept item, `NULL` if an options array is specified and none are valid
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
				$regex = '#^' . str_replace('*', '.*', $item) . '$#iD';
			
			// Language matches that don't have a - are a wildcard
			} elseif (strpos($item, '-') === FALSE) {
				$regex = '#^' . str_replace('*', '.*', $item) . '(-.*)?$#iD';	
				
			// Non-wildcard languages are straight-up matches
			} else {
				$regex = '#^' . str_replace('*', '.*', $item) . '$#iD';	
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
	 * Gets a value from ::get() and passes it through fHTML::prepare()
	 * 
	 * @param  string $key            The key to get the value of
	 * @param  string $cast_to        Cast the value to this data type
	 * @param  mixed  $default_value  If the parameter is not set in the `DELETE`/`PUT` post data, `$_POST` or `$_GET`, use this value instead
	 * @return string  The prepared value
	 */
	static public function prepare($key, $cast_to=NULL, $default_value=NULL)
	{
		return fHTML::prepare(self::get($key, $cast_to, $default_value));
	}
	
	
	/**
	 * Returns an array of values from one of the HTTP `Accept-*` headers
	 * 
	 * @return array  An associative array of `{value} => {quality}` sorted (in a stable-fashion) from highest to lowest `q`
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
		fSession::clear(__CLASS__ . '::');
		
		self::$backup_files      = NULL;
		self::$backup_get        = NULL;
		self::$backup_post       = NULL;
		self::$backup_put_delete = NULL;
		self::$put_delete        = NULL;
	}
	
	
	/**
	 * Sets a value into the appropriate `$_GET` or `$_POST` superglobal, or the local `PUT`/`DELETE` post data based on what HTTP method was used for the request
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
	 * Returns `$_GET`, `$_POST` and `$_FILES` and the `PUT`/`DELTE` post data to the state they were at before ::filter() was called
	 * 
	 * @internal
	 * 
	 * @return void
	 */
	static public function unfilter()
	{
		if (self::$backup_get === array()) {
			throw new fProgrammerException(
				'%1$s can only be called after %2$s',
				__CLASS__ . '::unfilter()',
				__CLASS__ . '::filter()'
			);
		}
		
		$_FILES           = array_pop(self::$backup_files);
		$_GET             = array_pop(self::$backup_get);
		$_POST            = array_pop(self::$backup_post);
		self::$put_delete = array_pop(self::$backup_put_delete);
	}
	
	
	/**
	 * Validates a request token generated by ::generateCSRFToken()
	 * 
	 * This method takes a request token and ensures it is valid, otherwise
	 * it will throw an fValidationException.
	 * 
	 * @throws fValidationException  When the CSRF token specified is invalid
	 * 
	 * @param  string $token  The request token to validate
	 * @param  string $url    The URL to validate the token for, default to the current page
	 * @return void
	 */
	static public function validateCSRFToken($token, $url=NULL)
	{
		if ($url === NULL) {
			$url = fURL::get();	
		}
		
		$key    = __CLASS__ . '::' . $url . '::csrf_tokens';
		$tokens = fSession::get($key, array());
		
		if (!in_array($token, $tokens)) {
			throw new fValidationException(
				'The form submitted could not be validated as authentic, please try submitting it again'
			);	
		}
		
		$tokens = array_diff($tokens, array($token));;
		fSession::set($key, $tokens);
	}
	
	
	/**
	 * Forces use as a static class
	 * 
	 * @return fRequest
	 */
	private function __construct() { }
}



/**
 * Copyright (c) 2007-2010 Will Bond <will@flourishlib.com>, others
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