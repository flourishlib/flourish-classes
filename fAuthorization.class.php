<?php
/**
 * Simplifies user permission checking
 * 
 * @copyright  Copyright (c) 2007 William Bond
 * @author     William Bond [wb] <will@flourishlib.com>
 * @license    http://flourishlib.com/license
 * 
 * @version  1.0.0 
 * @changes  1.0.0    The initial implementation [wb, 2007-06-14]
 */
class fAuthorization
{
	/**
	 * The prefix for session variables
	 */
	const SESSION_PREFIX = 'fAuthorization::';
	
	/**
	 * The valid auth levels
	 * 
	 * @var array 
	 */
	static private $levels = NULL;
	
	/**
	 * The login page
	 * 
	 * @var string 
	 */
	static private $login_page = NULL;
	
	
	
	/**
	 * Prevent instantiation
	 * 
	 * @since  1.0.0
	 * 
	 * @return fAuthorization
	 */
	private function __construct() { }
	
	
	/**
	 * Sets the authorization levels to use for level checking
	 * 
	 * @since  1.0.0
	 * 
	 * @param  array $levels  An associative array of (string) {level} => (integer), for each level
	 * @return void
	 */
	static public function setAuthLevels($levels)
	{
		self::$levels = $levels;
	}
	
	
	/**
	 * Sets the login page to redirect users to
	 * 
	 * @since  1.0.0
	 * 
	 * @param  string $url  The URL of the login page
	 * @return void
	 */
	static public function setLoginPage($url)
	{
		self::$login_page = $url;
	}
	
	
	/**
	 * Sets the authorization level for the logged in user
	 * 
	 * @since  1.0.0
	 * 
	 * @param  string $level  The logged in user's auth level
	 * @return void
	 */
	static public function setUserAuthLevel($level)
	{
		self::validateAuthLevel($level);
		fSession::set('user_auth_level', $level, self::SESSION_PREFIX);
	}
	
	
	/**
	 * Gets the authorization level for the logged in user
	 * 
	 * @since  1.0.0
	 * 
	 * @return string  The logged in user's auth level
	 */
	static public function getUserAuthLevel()
	{
		return fSession::get('user_auth_level', NULL, self::SESSION_PREFIX);
	}
	
	
	/**
	 * Checks to see if the logged in user has the specified auth level
	 * 
	 * @since  1.0.0
	 * 
	 * @param  string $level  The level to check against the logged in user's level
	 * @return boolean  If the user has the required auth level
	 */
	static public function checkAuthLevel($level)
	{
		if (self::getUserAuthLevel()) {
			
			self::validateAuthLevel(self::getUserAuthLevel());
			self::validateAuthLevel($level);
			
			$user_number = self::$levels[self::getUserAuthLevel()];
			$required_number = self::$levels[$level];
			
			if ($user_number >= $required_number) {
				return TRUE;	
			}		
		}
		
		return FALSE;	
	}
	
	
	/**
	 * Redirect the user to the login page if they do not have the auth level required
	 * 
	 * @since  1.0.0
	 * 
	 * @param  string $level  The level to check against the logged in user's level
	 * @return void
	 */
	static public function requireAuthLevel($level)
	{
		self::validateLoginPage();
		
		if (self::checkAuthLevel($level)) {
			return;	
		}
		
		self::redirect();	
	}
	
	
	/**
	 * Sets the ACLs for the logged in user.
	 * 
	 * Array should be formatted like:
	 * 
	 * <pre>
	 * array (
	 *     (string) {resource name}  => array((mixed) {permission},...),...
	 * )
	 * </pre>
	 * 
	 * The resource name or the permission may be the single character '*'
	 * which acts as a wildcard.
	 * 
	 * @since  1.0.0
	 * 
	 * @param  array $acls  The logged in user's ACLs (see method description for format)
	 * @return void
	 */
	static public function setUserACLs($acls)
	{
		fSession::set('user_acls', $acls, self::SESSION_PREFIX);
	}
	
	
	/**
	 * Gets the ACLs for the logged in user
	 * 
	 * @since  1.0.0
	 * 
	 * @return array  The logged in user's ACLs
	 */
	static public function getUserACLs()
	{
		return fSession::get('user_acls', NULL, self::SESSION_PREFIX);
	}
	
	
	/**
	 * Checks to see if the logged in user meets the requirements of the ACL specified
	 * 
	 * @since  1.0.0
	 * 
	 * @param  string $resource    The resource we are checking permissions for
	 * @param  string $permission  The permission to require from the user
	 * @return boolean  If the user has the required permissions
	 */
	static public function checkACL($resource, $permission)
	{
		if (self::getUserACLs() === NULL) {
			return FALSE;
		}
			
		$acls = self::getUserACLs();
		
		if (!isset($acls[$resource]) && !isset($acls['*'])) {
			return FALSE;	
		}
		
		if (isset($acls[$resource])) {
			if (in_array($permission, $acls[$resource]) || in_array('*', $acls[$resource])) {
				return TRUE;
			}	
		}
		
		if (isset($acls['*'])) {
			if (in_array($permission, $acls['*']) || in_array('*', $acls['*'])) {
				return TRUE;
			}	
		}
		
		return FALSE;	
	}
	
	
	/**
	 * Redirect the user to the login page if they do not have the permissions required
	 * 
	 * @since  1.0.0
	 * 
	 * @param  string $resource    The resource we are checking permissions for
	 * @param  string $permission  The permission to require from the user
	 * @return void
	 */
	static public function requireACL($resource, $permission)
	{
		self::validateLoginPage();
		
		if (self::checkACL($resource, $permission)) {
			return;	
		}
		
		self::redirect();	
	}
	
	
	/**
	 * Checks to see if the user has an auth level or ACLs defined
	 * 
	 * @since  1.0.0
	 * 
	 * @return boolean  If the user is logged in
	 */
	static public function checkLoggedIn()
	{
		if (fSession::get('user_auth_level', NULL, self::SESSION_PREFIX) !== NULL ||
			fSession::get('user_acls', NULL, self::SESSION_PREFIX) !== NULL) {
			return TRUE;	
		}	
		return FALSE;
	}
	
	
	/**
	 * Redirect the user to the login page if they do not have an auth level or ACLs
	 * 
	 * @since  1.0.0
	 * 
	 * @return void
	 */
	static public function requireLoggedIn()
	{
		self::validateLoginPage();
		
		if (self::checkLoggedIn()) {
			return;	
		}
		
		self::redirect();	
	}
	
	
	/**
	 * Destroys the user's auth level and/or ACLs
	 * 
	 * @since  1.0.0
	 * 
	 * @return void
	 */
	static public function destroyUserInfo()
	{
		fSession::set('user_auth_level', NULL, self::SESSION_PREFIX);
		fSession::set('user_acls', NULL, self::SESSION_PREFIX);	
	}
	
	
	/**
	 * Redirects the user to the login page
	 * 
	 * @since  1.0.0
	 * 
	 * @return void
	 */
	static private function redirect()
	{
		$qs_append  = (strpos(self::$login_page, '?') === FALSE) ? '?' : '&';
		$qs_append .= 'requested_url=' . urlencode(fURL::getWithQueryString());
		
		fURL::redirect(self::$login_page . $qs_append);
	}	
	
	
	/**
	 * Makes sure a login page has been defined
	 * 
	 * @since  1.0.0
	 * 
	 * @return void
	 */
	static private function validateLoginPage()
	{
		if (self::$login_page === NULL) {
			fCore::toss('fProgrammerException', 'No login page has been set, please call fAuthorization::setLoginPage()');	
		}	
	}
	
	
	/**
	 * Makes sure auth levels have been set, and that the specified auth level is valid
	 * 
	 * @since  1.0.0
	 * 
	 * @param  string $level  The level to validate
	 * @return void
	 */
	static private function validateAuthLevel($level=NULL)
	{
		if (self::$levels === NULL) {
			fCore::toss('fProgrammerException', 'No authorization levels have been set, please call fAuthorization::setAuthLevels()');	
		}
		if ($level !== NULL && !isset(self::$levels[$level])) {
			fCore::toss('fProgrammerException', 'The authorization level specified, ' . $level . ', is not a valid authorization level');	
		}		
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