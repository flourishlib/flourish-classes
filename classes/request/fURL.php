<?php
/**
 * Provides url-related methods
 * 
 * @copyright  Copyright (c) 2007-2008 William Bond
 * @author     William Bond [wb] <will@flourishlib.com>
 * @license    http://flourishlib.com/license
 * 
 * @link  http://flourishlib.com/fURL
 * 
 * @uses  fCore
 * @uses  fHTML
 * @uses  fProgrammerException
 * 
 * @version  1.0.0 
 * @changes  1.0.0    The initial implementation [wb, 2007-06-14]
 */
class fURL
{	
	/**
	 * Returns the requested url, does no include the domain name or query string
	 * 
	 * @return string  The requested URL without the query string
	 */
	static public function get()
	{
		return str_replace('?' . $_SERVER['QUERY_STRING'], '', $_SERVER['REQUEST_URI']);
	}
	
	
	/**
	 * Returns the current domain name, with protcol prefix
	 * 
	 * @return string  The current domain name (with protocol prefix)
	 */
	static public function getDomain()
	{
		return ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on') ? 'https://' : 'http://') . $_SERVER['SERVER_NAME'];    
	}
	
	
	/**
	 * Returns the current query string
	 * 
	 * @return string  The query string
	 */
	static public function getQueryString()
	{
		return $_SERVER['QUERY_STRING'];
	}
	
	
	/**
	 * Returns the current url including query string, but without domain name
	 * 
	 * @return string  The url with query string
	 */
	static public function getWithQueryString()
	{
		return $_SERVER['REQUEST_URI'];
	}
	
	
	/**
	 * Changes a string into a URL-friendly string
	 * 
	 * @param  string $string  The string to convert
	 * @return void
	 */
	static public function makeFriendly($string)
	{
		$string = fHTML::decodeEntities(fHTML::unaccent($string));
		$string = strtolower($string);
		$string = preg_replace('#[^a-zA-Z -]#', ' ', $string);
		return preg_replace('#\s+#', '_', trim($string));
	}
	
	
	/**
	 * Redirects to the url specified, if the url does not start with http:// or https://, redirects to current site
	 * 
	 * @param  string $url  The url to redirect to
	 * @return void
	 */
	static public function redirect($url)
	{
		if (strpos($url, '/') === 0) {
			$url = self::getDomain() . $url;   
		} elseif (!preg_match('#^https?://#i')) {
			$url = self::getDomain() . self::get() . $url;	
		}
		header('Location: ' . $url);
		exit($url);
	}
	
	
	/**
	 * Removes one or more key/fields from the query string, pass as many key names as you want
	 * 
	 * @param  string $key,...  A key/field to remove from the query string
	 * @return string  The query string with the parameter(s) specified removed, first char is '?'
	 */
	static public function removeFromQueryString()
	{
		$keys = func_get_args();
		for ($i=0; $i < sizeof($keys); $i++) {
			$keys[$i] = '#\b' . $keys[$i] . '=[^&]*&?#';    
		}
		return '?' . substr(preg_replace($keys, '', $qs), 1);           
	}
	
	
	/**
	 * Replaces a value in the querystring
	 * 
	 * @param  string|array  $key             The get key/field
	 * @param  string|array  $value           The value to set the key to
	 * @return string  The full query string with the key replaced, first char is '?'
	 */
	static public function replaceInQueryString($key, $value)
	{
		$qs_array = $_GET;
		if (get_magic_quotes_gpc()) {
			$qs_array = array_map('stripslashes', $qs_array);	
		}
		
		settype($key, 'array');
		settype($value, 'array');
		
		if (sizeof($key) != sizeof($value)) {
			fCore::toss('fProgrammerException', 'There are a different number of parameters and values');	
		}
		
		for ($i=0; $i<sizeof($key); $i++) {
			$qs_array[$key[$i]] = $value[$i];		
		}
		
		return '?' . http_build_query($qs_array, '', '&');           
	}
	
	
	/**
	 * Forces use as a static class
	 * 
	 * @return fURL
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