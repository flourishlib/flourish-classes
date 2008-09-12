<?php
/**
 * Provides URL related functionality
 * 
 * @copyright  Copyright (c) 2007-2008 William Bond
 * @author     William Bond [wb] <will@flourishlib.com>
 * @license    http://flourishlib.com/license
 * 
 * @package    Flourish
 * @link       http://flourishlib.com/fURL
 * 
 * @version    1.0.0b
 * @changes    1.0.0b  The initial implementation [wb, 2007-06-14]
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
		return preg_replace('#\?.*$#', '', $_SERVER['REQUEST_URI']);
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
	 * Returns the current query string, does not include parameters added by a rewrite
	 * 
	 * @return string  The query string
	 */
	static public function getQueryString()
	{
		return preg_replace('#^[^?]*\??#', '', $_SERVER['REQUEST_URI']);
	}
	
	
	/**
	 * Returns the current url including query string, but without domain name - does not include query string parameters from a rewrite
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
		$string = fHTML::decode(fUTF8::ascii($string));
		$string = strtolower($string);
		$string = preg_replace("#[^a-zA-Z0-9 '-]#", ' ', $string);
		$string = str_replace("'", '', $string);
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
		} elseif (!preg_match('#^https?://#i', $url)) {
			$url = self::getDomain() . self::get() . $url;
		}
		
		// Strip the ? if there are no query string parameters
		if (substr($url, -1) == '?') {
			$url = substr($url, 0, -1);
		}
		
		header('Location: ' . $url);
		exit($url);
	}
	
	
	/**
	 * Removes one or more parameters from the query string
	 * 
	 * This method uses the query string from the original URL and will not
	 * contain any parameters that are rewritten by the web server (such as
	 * Apache's mod_rewrite).
	 * 
	 * @param  string $parameter,...  A parameter to remove from the query string
	 * @return string  The query string with the parameter(s) specified removed, first char is '?'
	 */
	static public function removeFromQueryString()
	{
		$parameters = func_get_args();
		
		parse_str(self::getQueryString(), $qs_array);
		if (get_magic_quotes_gpc()) {
			$qs_array = array_map('stripslashes', $qs_array);
		}
		
		foreach ($parameters as $parameter) {
			unset($qs_array[$parameter]);
		}
		
		return '?' . http_build_query($qs_array, '', '&');
	}
	
	
	/**
	 * Replaces a value in the querystring
	 * 
	 * This method uses the query string from the original URL and will not
	 * contain any parameters that are rewritten by the web server (such as
	 * Apache's mod_rewrite).
	 * 
	 * @param  string|array  $parameter  The query string parameter
	 * @param  string|array  $value      The value to set the parameter to
	 * @return string  The full query string with the parameter replaced, first char is '?'
	 */
	static public function replaceInQueryString($parameter, $value)
	{
		parse_str(self::getQueryString(), $qs_array);
		if (get_magic_quotes_gpc()) {
			$qs_array = array_map('stripslashes', $qs_array);
		}
		
		settype($parameter, 'array');
		settype($value, 'array');
		
		if (sizeof($parameter) != sizeof($value)) {
			fCore::toss(
				'fProgrammerException',
				fGrammar::compose(
					"There are a different number of parameters and values.\nParameters:\n%1\$s\nValues\n%2\$s",
					fCore::dump($parameter),
					fCore::dump($value)
				)
			);
		}
		
		for ($i=0; $i<sizeof($parameter); $i++) {
			$qs_array[$parameter[$i]] = $value[$i];
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