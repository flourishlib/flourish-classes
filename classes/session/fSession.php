<?php
/**
 * Handles session-related data
 * 
 * @copyright  Copyright (c) 2007-2008 William Bond
 * @author     William Bond [wb] <will@flourishlib.com>
 * @license    http://flourishlib.com/license
 * 
 * @package    Flourish
 * @link       http://flourishlib.com/fSession
 * 
 * @version    1.0.0b
 * @changes    1.0.0b  The initial implementation [wb, 2007-06-14]
 */
class fSession
{
	/**
	 * If the session is open
	 * 
	 * @var boolean
	 */
	static private $open = FALSE;
	
	
	/**
	 * Unsets a key from the session superglobal using the prefix provided
	 * 
	 * @param  string $key     The name to unset
	 * @param  string $prefix  The prefix to stick before the key
	 * @return void
	 */
	static public function clear($key, $prefix='fSession::')
	{
		self::open();
		unset($_SESSION[$prefix . $key]);
	}
	
	
	/**
	 * Closes the session for writing, allowing other pages to open the session
	 * 
	 * @return void
	 */
	static public function close()
	{
		if (self::$open) {
			session_write_close();
			self::$open = FALSE;
		}
	}
	
	
	/**
	 * Destroys the session, removing all values
	 * 
	 * @return void
	 */
	static public function destroy()
	{
		self::open();
		$_SESSION = array();
		if (isset($_COOKIE[session_name()])) {
			$params = session_get_cookie_params();
			setcookie(session_name(), '', time()-43200, $params['path'], $params['domain']);
		}
		session_destroy();
	}
	
	
	/**
	 * Gets data from the session superglobal, prefixing it with fSession:: to prevent issues with $_REQUEST
	 * 
	 * @param  string $key            The name to get the value for
	 * @param  mixed  $default_value  The default value to use if the requested key is not set
	 * @param  string $prefix         The prefix to stick before the key
	 * @return mixed  The data element requested
	 */
	static public function get($key, $default_value=NULL, $prefix='fSession::')
	{
		self::open();
		return (isset($_SESSION[$prefix . $key])) ? $_SESSION[$prefix . $key] : $default_value;
	}
	
	
	/**
	 * Sets the session to run on the main domain, not just the specific subdomain currently being accessed
	 * 
	 * @return void
	 */
	static public function ignoreSubdomain()
	{
		if (self::$open) {
			fCore::toss(
				'fProgrammerException',
				fGrammar::compose(
					'%1$s must be called before any of %2$s, %3$s or %4$s',
					__CLASS__ . '::ignoreSubdomain()',
					__CLASS__ . '::clear()',
					__CLASS__ . '::get()',
					__CLASS__ . '::set()'
				)
			);
		}
		session_set_cookie_params(0, '/', preg_replace('#.*?([a-z0-9\\-]+\.[a-z]+)$#i', '.\1', $_SERVER['SERVER_NAME']));
	}
	
	
	/**
	 * Opens the session for writing
	 * 
	 * @return void
	 */
	static private function open()
	{
		if (!self::$open) {
			session_start();
			self::$open = TRUE;
		}
	}
	
	
	/**
	 * Sets data to the session superglobal, prefixing it with fSession:: to prevent issues with $_REQUEST
	 * 
	 * @param  string $key     The name to save the value under
	 * @param  mixed  $value   The value to store
	 * @param  string $prefix  The prefix to stick before the key
	 * @return void
	 */
	static public function set($key, $value, $prefix='fSession::')
	{
		self::open();
		$_SESSION[$prefix . $key] = $value;
	}
	
	
	/**
	 * Sets the minimum length of a session - PHP might not clean up the session data right away once this timespan has elapsed
	 * 
	 * @param  string $timespan  An english description of a timespan (e.g. '30 minutes', '1 hour', '1 day 2 hours')
	 * @return void
	 */
	static public function setLength($timespan)
	{
		if (self::$open) {
			fCore::toss(
				'fProgrammerException',
				fGrammar::compose(
					'%1$s must be called before any of %2$s, %3$s or %4$s',
					__CLASS__ . '::setLength()',
					__CLASS__ . '::clear()',
					__CLASS__ . '::get()',
					__CLASS__ . '::set()'
				)	
			);
		}
		$seconds = strtotime($timespan) - time();
		ini_set('session.gc_maxlifetime', $seconds);
		ini_set('session.cookie_lifetime', 0);
	}
	
	
	/**
	 * Prevent instantiation
	 * 
	 * @return fSession
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