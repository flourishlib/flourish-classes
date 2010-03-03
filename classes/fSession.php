<?php
/**
 * Wraps the session control functions and the `$_SESSION` superglobal for a more consistent and safer API
 * 
 * A `Cannot send session cache limiter` warning will be triggered if ::open(),
 * ::add(), ::clear(), ::delete(), ::get() or ::set() is called after output has
 * been sent to the browser. To prevent such a warning, explicitly call ::open()
 * before generating any output.
 * 
 * @copyright  Copyright (c) 2007-2010 Will Bond, others
 * @author     Will Bond [wb] <will@flourishlib.com>
 * @author     Alex Leeds [al] <alex@kingleeds.com>
 * @license    http://flourishlib.com/license
 * 
 * @package    Flourish
 * @link       http://flourishlib.com/fSession
 * 
 * @version    1.0.0b10
 * @changes    1.0.0b10  Fixed some documentation bugs [wb, 2010-03-03]
 * @changes    1.0.0b9   Fixed a bug in ::destroy() where sessions weren't always being properly destroyed [wb, 2009-12-08]
 * @changes    1.0.0b8   Fixed a bug that made the unit tests fail on PHP 5.1 [wb, 2009-10-27]
 * @changes    1.0.0b7   Backwards Compatibility Break - Removed the `$prefix` parameter from the methods ::delete(), ::get() and ::set() - added the methods ::add(), ::enablePersistence(), ::regenerateID() [wb+al, 2009-10-23]
 * @changes    1.0.0b6   Backwards Compatibility Break - the first parameter of ::clear() was removed, use ::delete() instead [wb, 2009-05-08] 
 * @changes    1.0.0b5   Added documentation about session cache limiter warnings [wb, 2009-05-04]
 * @changes    1.0.0b4   The class now works with existing sessions [wb, 2009-05-04]
 * @changes    1.0.0b3   Fixed ::clear() to properly handle when `$key` is `NULL` [wb, 2009-02-05]
 * @changes    1.0.0b2   Made ::open() public, fixed some consistency issues with setting session options through the class [wb, 2009-01-06]
 * @changes    1.0.0b    The initial implementation [wb, 2007-06-14]
 */
class fSession
{
	// The following constants allow for nice looking callbacks to static methods
	const add               = 'fSession::add';
	const clear             = 'fSession::clear';
	const close             = 'fSession::close';
	const delete            = 'fSession::delete';
	const destroy           = 'fSession::destroy';
	const enablePersistence = 'fSession::enablePersistence';
	const get               = 'fSession::get';
	const ignoreSubdomain   = 'fSession::ignoreSubdomain';
	const open              = 'fSession::open';
	const regenerateID      = 'fSession::regenerateID';
	const reset             = 'fSession::reset';
	const set               = 'fSession::set';
	const setLength         = 'fSession::setLength';
	const setPath           = 'fSession::setPath';
	
	
	/**
	 * The length for a normal session
	 * 
	 * @var integer
	 */
	static private $normal_timespan = NULL;
	
	/**
	 * If the session is open
	 * 
	 * @var boolean
	 */
	static private $open = FALSE;
	
	/**
	 * The length for a persistent session cookie - one that survives browser restarts
	 * 
	 * @var integer
	 */
	static private $persistent_timespan = NULL;
	
	/**
	 * If the session ID was regenerated during this script
	 * 
	 * @var boolean
	 */
	static private $regenerated = FALSE;
	
	
	/**
	 * Adds a value to an already-existing array value, or to a new array value
	 *
	 * @param  string $key     The name to access the array under
	 * @param  mixed  $value   The value to add to the array
	 * @return void
	 */
	static public function add($key, $value)
	{
		self::open();
		if (!isset($_SESSION[$key])) {
			$_SESSION[$key] = array();
		}
		if (!is_array($_SESSION[$key])) {
			throw new fProgrammerException(
				'%1$s was called for the key, %2$s, which is not an array',
				__CLASS__ . '::add()',
				$key
			);
		}
		$_SESSION[$key][] = $value;
	}	
	
	
	/**
	 * Removes all session values with the provided prefix
	 * 
	 * This method will not remove session variables used by this class, which
	 * are prefixed with `fSession::`.
	 * 
	 * @param  string $prefix  The prefix to clear all session values for
	 * @return void
	 */
	static public function clear($prefix=NULL)
	{
		self::open();
		
		$session_type    = $_SESSION['fSession::type'];
		$session_expires = $_SESSION['fSession::expires'];
		
		if ($prefix) {
			foreach ($_SESSION as $key => $value) {
				if (strpos($key, $prefix) === 0) {
					unset($_SESSION[$key]);
				}
			}
		} else {
			$_SESSION = array();		
		}
		
		$_SESSION['fSession::type']    = $session_type;
		$_SESSION['fSession::expires'] = $session_expires;
	}
	
	
	/**
	 * Closes the session for writing, allowing other pages to open the session
	 * 
	 * @return void
	 */
	static public function close()
	{
		if (!self::$open) { return; }
		
		session_write_close();
		unset($_SESSION);
		self::$open = FALSE;
	}
	
	
	/**
	 * Deletes a value from the session
	 * 
	 * @param  string $key  The key of the value to delete
	 * @return void
	 */
	static public function delete($key)
	{
		self::open();
		
		unset($_SESSION[$key]);
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
			setcookie(session_name(), '', time()-43200, $params['path'], $params['domain'], $params['secure']);
		}
		session_destroy();
		self::regenerateID();
	}
	
	
	/**
	 * Changed the session to use a time-based cookie instead of a session-based cookie
	 * 
	 * The length of the time-based cookie is controlled by ::setLength(). When
	 * this method is called, a time-based cookie is used to store the session
	 * ID. This means the session can persist browser restarts. Normally, a
	 * session-based cookie is used, which is wiped when a browser restart
	 * occurs.
	 * 
	 * This method should be called during the login process and will normally
	 * be controlled by a checkbox or similar where the user can indicate if
	 * they want to stay logged in for an extended period of time.
	 * 
	 * @return void
	 */
	static public function enablePersistence()
	{
		if (self::$persistent_timespan === NULL) {
			throw new fProgrammerException(
				'The method %1$s must be called with the %2$s parameter before calling %3$s',
				__CLASS__ . '::setLength()',
				'$persistent_timespan',
				__CLASS__ . '::enablePersistence()'
			);	
		}
		
		$current_params = session_get_cookie_params();
		
		$params = array(
			self::$persistent_timespan,
			$current_params['path'],
			$current_params['domain'],
			$current_params['secure']
		);
		
		call_user_func_array('session_set_cookie_params', $params);
		
		$_SESSION['fSession::type'] = 'persistent';
		
		if (isset($_COOKIE[session_name()])) {
			self::regenerateID();
		}
	}
	
	
	/**
	 * Gets data from the `$_SESSION` superglobal
	 * 
	 * @param  string $key            The name to get the value for
	 * @param  mixed  $default_value  The default value to use if the requested key is not set
	 * @return mixed  The data element requested
	 */
	static public function get($key, $default_value=NULL)
	{
		self::open();
		return (isset($_SESSION[$key])) ? $_SESSION[$key] : $default_value;
	}
	
	
	/**
	 * Sets the session to run on the main domain, not just the specific subdomain currently being accessed
	 * 
	 * This method should be called after any calls to
	 * [http://php.net/session_set_cookie_params `session_set_cookie_params()`].
	 * 
	 * @return void
	 */
	static public function ignoreSubdomain()
	{
		if (self::$open || isset($_SESSION)) {
			throw new fProgrammerException(
				'%1$s must be called before any of %2$s, %3$s, %4$s, %5$s, %6$s or %7$s',
				__CLASS__ . '::ignoreSubdomain()',
				__CLASS__ . '::add()',
				__CLASS__ . '::clear()',
				__CLASS__ . '::get()',
				__CLASS__ . '::open()',
				__CLASS__ . '::set()',
				'session_start()'
			);
		}
		
		$current_params = session_get_cookie_params();
		
		$params = array(
			$current_params['lifetime'],
			$current_params['path'],
			preg_replace('#.*?([a-z0-9\\-]+\.[a-z]+)$#iD', '.\1', $_SERVER['SERVER_NAME']),
			$current_params['secure']
		);
		
		call_user_func_array('session_set_cookie_params', $params);
	}
	
	
	/**
	 * Opens the session for writing, is automatically called by ::clear(), ::get() and ::set()
	 * 
	 * A `Cannot send session cache limiter` warning will be triggered if this,
	 * ::add(), ::clear(), ::delete(), ::get() or ::set() is called after output
	 * has been sent to the browser. To prevent such a warning, explicitly call
	 * this method before generating any output.
	 * 
	 * @param  boolean $cookie_only_session_id  If the session id should only be allowed via cookie - this is a security issue and should only be set to `FALSE` when absolutely necessary 
	 * @return void
	 */
	static public function open($cookie_only_session_id=TRUE)
	{
		if (self::$open) { return; }
		
		self::$open = TRUE;
		
		if (self::$normal_timespan === NULL) {
			self::$normal_timespan = ini_get('session.gc_maxlifetime');	
		}
		
		// If the session is already open, we just piggy-back without setting options
		if (!isset($_SESSION)) {
			if ($cookie_only_session_id) {
				ini_set('session.use_cookies', 1);
				ini_set('session.use_only_cookies', 1);
			}
			session_start();
		}
		
		// If the session has existed for too long, reset it
		if (!isset($_SESSION['fSession::expires']) || $_SESSION['fSession::expires'] < $_SERVER['REQUEST_TIME']) {
			$_SESSION = array();
			if (isset($_SESSION['fSession::expires'])) {
				self::regenerateID();
			}
		}
		
		if (!isset($_SESSION['fSession::type'])) {
			$_SESSION['fSession::type'] = 'normal';	
		}
		
		// We store the expiration time for a session to allow for both normal and persistent sessions
		if ($_SESSION['fSession::type'] == 'persistent' && self::$persistent_timespan) {
			$_SESSION['fSession::expires'] = $_SERVER['REQUEST_TIME'] + self::$persistent_timespan;
			
		} else {
			$_SESSION['fSession::expires'] = $_SERVER['REQUEST_TIME'] + self::$normal_timespan;	
		}
	}
	
	
	/**
	 * Regenerates the session ID, but only once per script execution
	 * 
	 * @internal
	 * 
	 * @return void
	 */
	static public function regenerateID()
	{
		if (!self::$regenerated){
			session_regenerate_id();
			self::$regenerated = TRUE;
		}
	}
	
	
	/**
	 * Resets the configuration of the class
	 * 
	 * @internal
	 * 
	 * @return void
	 */
	static public function reset()
	{
		self::$normal_timespan     = NULL;
		self::$persistent_timespan = NULL;
		self::$regenerated         = FALSE;
		self::destroy();
		self::close();	
	}
	
	
	/**
	 * Sets data to the `$_SESSION` superglobal
	 * 
	 * @param  string $key     The name to save the value under
	 * @param  mixed  $value   The value to store
	 * @return void
	 */
	static public function set($key, $value)
	{
		self::open();
		$_SESSION[$key] = $value;
	}
	
	
	/**
	 * Sets the minimum length of a session - PHP might not clean up the session data right away once this timespan has elapsed
	 * 
	 * Please be sure to set a custom session path via ::setPath() to ensure
	 * another site on the server does not garbage collect the session files
	 * from this site!
	 * 
	 * Both of the timespan can accept either a integer timespan in seconds,
	 * or an english description of a timespan (e.g. `'30 minutes'`, `'1 hour'`,
	 * `'1 day 2 hours'`).
	 * 
	 * @param  string|integer $normal_timespan      The normal, session-based cookie, length for the session
	 * @param  string|integer $persistent_timespan  The persistent, timed-based cookie, length for the session - this is enabled by calling ::enabledPersistence() during login
	 * @return void
	 */
	static public function setLength($normal_timespan, $persistent_timespan=NULL)
	{
		if (self::$open || isset($_SESSION)) {
			throw new fProgrammerException(
				'%1$s must be called before any of %2$s, %3$s, %4$s, %5$s, %6$s or %7$s',
				__CLASS__ . '::setLength()',
				__CLASS__ . '::add()',
				__CLASS__ . '::clear()',
				__CLASS__ . '::get()',
				__CLASS__ . '::open()',
				__CLASS__ . '::set()',
				'session_start()'
			);
		}
		
		$seconds = (!is_numeric($normal_timespan)) ? strtotime($normal_timespan) - time() : $normal_timespan;
		self::$normal_timespan = $seconds;
		
		if ($persistent_timespan) {
			$seconds = (!is_numeric($persistent_timespan)) ? strtotime($persistent_timespan) - time() : $persistent_timespan;	
			self::$persistent_timespan = $seconds;
		}
		
		ini_set('session.gc_maxlifetime', $seconds);
	}
	
	
	/**
	 * Sets the path to store session files in
	 * 
	 * This method should always be called with a non-standard directory
	 * whenever ::setLength() is called to ensure that another site on the
	 * server does not garbage collect the session files for this site.
	 * 
	 * Standard session directories usually include `/tmp` and `/var/tmp`. 
	 * 
	 * @param  string|fDirectory $directory  The directory to store session files in
	 * @return void
	 */
	static public function setPath($directory)
	{
		if (self::$open || isset($_SESSION)) {
			throw new fProgrammerException(
				'%1$s must be called before any of %2$s, %3$s, %4$s, %5$s, %6$s or %7$s',
				__CLASS__ . '::setPath()',
				__CLASS__ . '::add()',
				__CLASS__ . '::clear()',
				__CLASS__ . '::get()',
				__CLASS__ . '::open()',
				__CLASS__ . '::set()',
				'session_start()'
			);
		}
		
		if (!$directory instanceof fDirectory) {
			$directory = new fDirectory($directory);	
		}
		
		if (!$directory->isWritable()) {
			throw new fEnvironmentException(
				'The directory specified, %s, is not writable',
				$directory->getPath()
			);	
		}
		
		session_save_path($directory->getPath());
	}
	
	
	/**
	 * Forces use as a static class
	 * 
	 * @return fSession
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