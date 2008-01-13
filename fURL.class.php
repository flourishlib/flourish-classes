<?php
/**
 * Provides url-related methods
 * 
 * @copyright  Copyright (c) 2007 William Bond
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
	 * If getScript() should strip the file extension
	 * 
	 * @var boolean 
	 */
	static private $no_extensions = FALSE;    
	
	
	/**
	 * Forces use as a static class
	 * 
	 * @return fURL
	 */
	private function __construct() { }
	
	
	/**
	 * Sets the no_extensions for fURL::getScript()
	 * 
	 * @param  boolean $no_extensions  If no extensions should be returned via fURL::getScript()
	 * @return void
	 */
	static public function setNoExtensions($no_extensions)
	{
		self::$no_extensions = (boolean) $no_extensions;
	}
	
	
	/**
	 * Returns the requested url
	 * 
	 * @return string  The requested URL
	 */
	static public function get()
	{
		$qs = '?' . $_SERVER['QUERY_STRING'];
		return str_replace($qs, '', $_SERVER['REQUEST_URI']);
	}
	
	
	/**
	 * Returns the current url including query string
	 * 
	 * @return string  The url with query string
	 */
	static public function getWithQueryString()
	{
		return $_SERVER['REQUEST_URI'];
	}
	
	
	/**
	 * Returns the current php script filename
	 * 
	 * @return string  The current php script
	 */
	static public function getScript()
	{
		return (self::$no_extensions) ? preg_replace('#\.php$#i', '', $_SERVER['SCRIPT_NAME']) : $_SERVER['SCRIPT_NAME'];
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
	 * Replaces a value in the querystring
	 * 
	 * @param  string|array  $parameter       The get parameter
	 * @param  string|array  $value           The value to set the parameter to
	 * @param  boolean       $html_entities   If &amp; should be used to seperate elements
	 * @return string  The full querystring with the parameter replaced, first char is '?'
	 */
	static public function replaceInQueryString($parameter, $value, $html_entities=TRUE)
	{
		$qs_array = $_GET;
		if (get_magic_quotes_gpc()) {
			$qs_array = array_map('stripslashes', $qs_array);	
		}
		
		settype($parameter, 'array');
		settype($value, 'array');
		
		if (sizeof($parameter) != sizeof($value)) {
			fCore::toss('fProgrammerException', 'There are a different number of parameters and values');	
		}
		
		for ($i=0; $i<sizeof($parameter); $i++) {
			$qs_array[$parameter[$i]] = $value[$i];		
		}
		
		return '?' . http_build_query($qs_array, '', ($html_entities) ? '&amp;' : '&');           
	}
	
	
	/**
	 * Removes one or more parameters from the query string, pass as many parameter names as you want
	 * 
	 * @param  string $parameter  A parameter to remove from the query string
	 * @return string  The query string with the parameter(s) specified removed, first char is '?'
	 */
	static public function removeFromQueryString()
	{
		$parameters = func_get_args();
		for ($i=0; $i < sizeof($parameters); $i++) {
			$parameters[$i] = '#\b' . $parameters[$i] . '=[^&]*&?#';    
		}
		return '?' . substr(preg_replace($parameters, '', $qs), 1);           
	}
		
	
	/**
	 * Changes a string into a nice-url style string
	 * 
	 * @param  string $string  The string to convert
	 * @return void
	 */
	static public function makeNice($string)
	{
		$string = fHTML::decodeEntities(fHTML::unaccent($string));
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
		} elseif (strpos($url, '?') === 0) {
			$url = self::getDomain() . self::get() . $url;	
		}
		header('Location: ' . $url);
		exit;
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