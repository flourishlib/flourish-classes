<?php
/**
 * Provides low-level debugging, error and exception functionality
 * 
 * @copyright  Copyright (c) 2007-2008 William Bond
 * @author     William Bond [wb] <will@flourishlib.com>
 * @license    http://flourishlib.com/license
 * 
 * @package    Flourish
 * @link       http://flourishlib.com/fCore
 * 
 * @version    1.0.0b
 * @changes    1.0.0b  The initial implementation [wb, 2007-09-25]
 */
class fCore
{
	/**
	 * Callbacks for when messages are composed
	 * 
	 * @var array
	 */
	static private $compose_callbacks = array(
		'pre'  => array(),
		'post' => array()
	);
	
	/**
	 * If global debugging is enabled
	 * 
	 * @var boolean
	 */
	static private $debug = NULL;
	
	/**
	 * Error destination
	 * 
	 * @var string
	 */
	static private $error_destination = 'html';
	
	/**
	 * Exception destination
	 * 
	 * @var string
	 */
	static private $exception_destination = 'html';
	
	/**
	 * Exceptation handler callback
	 * 
	 * @var mixed
	 */
	static private $exception_handler_callback = NULL;
	
	/**
	 * Exceptation handler callback parameters
	 * 
	 * @var array
	 */
	static private $exception_handler_parameters = array();
	
	/**
	 * Callbacks for when exceptions are tossed
	 * 
	 * @var array
	 */
	static private $toss_callbacks = array();
	
	
	/**
	 * Creates a nicely formatted backtrace
	 * 
	 * @param  integer $remove_lines  The number of trailing lines to remove from the backtrace
	 * @return string  The formatted backtrace
	 */
	static public function backtrace($remove_lines=0)
	{
		if ($remove_lines !== NULL && !is_numeric($remove_lines)) {
			$remove_lines = 0;
		}
		
		settype($remove_lines, 'integer');
		
		$doc_root  = realpath($_SERVER['DOCUMENT_ROOT']);
		$doc_root .= (substr($doc_root, -1) != '/' && substr($doc_root, -1) != '\\') ? '/' : '';
		
		$backtrace = debug_backtrace();
		
		while ($remove_lines > 0) {
			array_shift($backtrace);
			$remove_lines--;
		}
		
		$backtrace = array_reverse($backtrace);
		
		$bt_string = '';
		$i = 0;
		foreach ($backtrace as $call) {
			if ($i) {
				$bt_string .= "\n";
			}
			if (isset($call['file'])) {
				$bt_string .= str_replace($doc_root, '{doc_root}/', $call['file']) . '(' . $call['line'] . '): ';
			} else {
				$bt_string .= '[internal function]: ';
			}
			if (isset($call['class'])) {
				$bt_string .= $call['class'] . $call['type'];
			}
			if (isset($call['class']) || isset($call['function'])) {
				$bt_string .= $call['function'] . '(';
					$j = 0;
					if (!isset($call['args'])) {
						$call['args'] = array();	
					}
					foreach ($call['args'] as $arg) {
						if ($j) {
							$bt_string .= ', ';
						}
						if (is_bool($arg)) {
							$bt_string .= ($arg) ? 'true' : 'false';
						} elseif (is_null($arg)) {
							$bt_string .= 'NULL';
						} elseif (is_array($arg)) {
							$bt_string .= 'Array';
						} elseif (is_object($arg)) {
							$bt_string .= 'Object(' . get_class($arg) . ')';
						} elseif (is_string($arg)) {
							// Shorten the UTF-8 string if it is too long
							if (strlen(utf8_decode($arg)) > 18) {
								preg_match('#^(.{0,15})#us', $arg, $short_arg);
								$arg = $short_arg[1] . '...';
							}
							$bt_string .= "'" . $arg . "'";
						} else {
							$bt_string .= (string) $arg;
						}
						$j++;
					}
				$bt_string .= ')';
			}
			$i++;
		}
		
		return $bt_string;
	}
	
	
	/**
	 * Checks an error/exception destination
	 * 
	 * @param  string $destination  The destination for the exception. An email or file.
	 * @return string|boolean  'email', 'file' or FALSE
	 */
	static private function checkDestination($destination)
	{
		if ($destination == 'html') {
			return 'html';
		}
		
		if (preg_match('#[a-z0-9_.\-\']+@([a-z0-9\-]+\.){1,}([a-z]{2,})#i', $destination)) {
			return 'email';
		}
		
		$path_info     = pathinfo($destination);
		$dir_exists    = file_exists($path_info['dirname']);
		$dir_writable  = ($dir_exists) ? is_writable($path_info['dirnam']) : FALSE;
		$file_exists   = file_exists($destination);
		$file_writable = ($file_exists) ? is_writable($destination) : FALSE;
		
		if (!$dir_exists || ($dir_exists && ((!$file_exists && !$dir_writable) || ($file_exists && !$file_writable)))) {
			return FALSE;
		}
			
		return 'file';
	}
	
	
	/**
	 * Performs an {@link http://php.net/sprintf sprintf()} on a string and provides a hook for modifications such as internationalization
	 * 
	 * @param  string  $message        A message to compose
	 * @param  mixed   $component,...  A string or number to insert into the message
	 * @return void
	 */
	static public function compose($message, $component)
	{
		if (self::$compose_callbacks) {
			foreach (self::$compose_callbacks['pre'] as $callback) {
				$message = call_user_func($callback, $message);	
			}
		}
		
		$components = array_slice(func_get_args(), 1);
		$message    = vsprintf($message, $components);	
		
		if (self::$compose_callbacks) {
			foreach (self::$compose_callbacks['post'] as $callback) {
				$message = call_user_func($callback, $message);	
			}
		}
		
		return $message;
	}
	
	
	/**
	 * Prints a debugging message if global or code-specific debugging is enabled
	 * 
	 * @param  string  $message  The debug message
	 * @param  boolean $force    If debugging should be forced even when global debug is off
	 * @return void
	 */
	static public function debug($message, $force)
	{
		if ($force || self::$debug) {
			self::expose($message, FALSE);
		}
	}
	
	
	/**
	 * Returns a string representation of any variable
	 * 
	 * @param  mixed $data  The variable to dump
	 * @return string  The string representation of the value
	 */
	static public function dump($data)
	{
		if (is_bool($data)) {
			return ($data) ? '{true}' : '{false}';
		
		} elseif (is_null($data)) {
			return '{null}';
		
		} elseif ($data === '') {
			return '{empty_string}';
		
		} elseif (is_array($data) || is_object($data)) {
			
			ob_start();
			var_dump($data);
			$output = ob_get_contents();
			ob_end_clean();
			
			// Make the var dump more like a print_r
			$output = preg_replace('#=>\n(  )+(?=[a-zA-Z]|&)#m', ' => ', $output);
			$output = str_replace('string(0) ""', '{empty_string}', $output);
			$output = str_replace('=> bool(false)', '=> {false}', $output);
			$output = str_replace('=> bool(true)', '=> {true}', $output);
			$output = str_replace('=> NULL', '=> {null}', $output);
			$output = preg_replace('#string\(\d+\) "#', '', $output);
			$output = preg_replace('#"(\n(  )*)(?=\[|\})#', '\1', $output);
			$output = preg_replace('#(?:float|int)\((-?\d+(?:.\d+)?)\)#', '\1', $output);
			$output = preg_replace('#((?:  )+)\["(.*?)"\]#', '\1[\2]', $output);
			$output = preg_replace('#(?:&)?array\(\d+\) \{\n((?:  )*)((?:  )(?=\[)|(?=\}))#', "Array\n\\1(\n\\1\\2", $output);
			$output = preg_replace('/object\((\w+)\)#\d+ \(\d+\) {\n((?:  )*)((?:  )(?=\[)|(?=\}))/', "\\1 Object\n\\2(\n\\2\\3", $output);
			$output = preg_replace('#^((?:  )+)}(?=\n|$)#m', "\\1)\n", $output);
			$output = substr($output, 0, -2) . ')';
			
			// Fix indenting issues with the var dump output
			$output_lines = explode("\n", $output);
			$new_output = array();
			$stack = 0;
			foreach ($output_lines as $line) {
				if (preg_match('#^((?:  )*)([^ ])#', $line, $match)) {
					$spaces = strlen($match[1]);
					if ($spaces && $match[2] == '(') {
						$stack += 1;
					}
					$new_output[] = str_pad('', ($spaces)+(4*$stack)) . $line;
					if ($spaces && $match[2] == ')') {
						$stack -= 1;
					}
				} else {
					$new_output[] = str_pad('', ($spaces)+(4*$stack)) . $line;
				}
			}
			
			return join("\n", $new_output);
			
		} else {
			return (string) $data;
		}
	}
	
	
	/**
	 * Sets if debug messages should be shown globally (for every object)
	 * 
	 * @param  boolean $flag  If debugging messages should be shown
	 * @return void
	 */
	static public function enableDebugging($flag)
	{
		self::$debug = (boolean) $flag;
	}
	
	
	/**
	 * Turns on special error handling
	 * 
	 * All errors that match the current error_reporting() level will be
	 * redirected to the destination.
	 * 
	 * @param  string $destination  The destination for the errors. An email or file.
	 * @return void
	 */
	static public function enableErrorHandling($destination)
	{
		if (!self::checkDestination($destination)) {
			return;
		}
		self::$error_destination = $destination;
		set_error_handler(array('fCore', 'handleError'));
	}
	
	
	/**
	 * Turns on special exception handling
	 * 
	 * Any uncaught exception will be redirected to the destination specified,
	 * and the page will execute the $closing_code callback before exiting.
	 * 
	 * @param  string   $destination   The destination for the exception. An email or file.
	 * @param  callback $closing_code  This callback will happen after the exception is handled and before page execution stops. Good for printing a footer.
	 * @param  array    $parameters    The parameters to send to $closing_code
	 * @return void
	 */
	static public function enableExceptionHandling($destination, $closing_code=NULL, $parameters=array())
	{
		if (!self::checkDestination($destination)) {
			return;
		}
		self::$exception_destination        = $destination;
		self::$exception_handler_callback   = $closing_code;
		settype($parameters, 'array');
		self::$exception_handler_parameters = $parameters;
		set_exception_handler(array('fCore', 'handleException'));
	}
	
	
	/**
	 * Prints the contents of a variable
	 * 
	 * @param  mixed $data  The data to show
	 * @return void
	 */
	static public function expose($data)
	{
		echo '<pre class="exposed">' . htmlspecialchars((string) self::dump($data)) . '</pre>';
	}
	
	
	/**
	 * Returns the (generalized) operating system the code is currently running on
	 * 
	 * @return string  Either 'windows', 'solaris' or 'linux/unix' (linux, *BSD)
	 */
	static public function getOS()
	{
		$uname = php_uname('s');
		
		if (stripos($uname, 'linux') !== FALSE) {
			return 'linux/unix';
		}
		if (stripos($uname, 'bsd') !== FALSE) {
			return 'linux/unix';
		}
		if (stripos($uname, 'solaris') !== FALSE || stripos($uname, 'sunos') !== FALSE) {
			return 'solaris';
		}
		if (stripos($uname, 'windows') !== FALSE) {
			return 'windows';
		}
		
		self::trigger(
			'warning',
			self::compose(
				"Unable to reliably determine the server OS. Defaulting to 'linux/unix'."
			)
		);
		
		return 'linux/unix';
	}
	
	
	/**
	 * Returns the version of PHP running, ignoring any information about the OS
	 * 
	 * @return string  The PHP version in the format major.minor.version
	 */
	static public function getPHPVersion()
	{
		static $version = NULL;
		
		if ($version === NULL) {
			$version = phpversion();
			$version = preg_replace('#^(\d+\.\d+\.\d+).*$#', '\1', $version);
		}
		
		return $version;
	}
	
	
	/**
	 * Handles an error
	 * 
	 * @internal
	 * 
	 * @param  integer $error_number   The error type
	 * @param  string  $error_string   The message for the error
	 * @param  string  $error_file     The file the error occured in
	 * @param  integer $error_line     The line the error occured on
	 * @param  array   $error_context  A references to all variables in scope at the occurence of the error
	 * @return void
	 */
	static public function handleError($error_number, $error_string, $error_file=NULL, $error_line=NULL, $error_context=NULL)
	{
		if ((error_reporting() & $error_number) == 0) {
			return;
		}
		
		$doc_root  = realpath($_SERVER['DOCUMENT_ROOT']);
		$doc_root .= (substr($doc_root, -1) != '/' && substr($doc_root, -1) != '\\') ? '/' : '';
		
		$error_file = str_replace($doc_root, '{doc_root}/', $error_file);
		
		$backtrace = self::backtrace(1);
		
		$error_string = preg_replace('# \[<a href=\'.*?</a>\]: #', ': ', $error_string);
		
		$error   = self::compose('Error') . "\n-----\n" . $backtrace . "\n" . $error_string;
		
		self::sendMessageToDestination(self::$error_destination, $error);
	}
	
	
	/**
	 * Handles an uncaught exception
	 * 
	 * @internal
	 * 
	 * @param  object $exception  The uncaught exception to handle
	 * @return void
	 */
	static public function handleException($exception)
	{
		if ($exception instanceof fPrintableException) {
			$message = $exception->formatTrace() . "\n" . $exception->getMessage();
		} else {
			$message = $exception->getTraceAsString() . "\n" . $exception->getMessage();
		}
		$message = self::compose("Uncaught Exception") . "\n------------------\n" . $message;
		
		if (self::$exception_destination != 'html' && $exception instanceof fPrintableException) {
			$exception->printMessage();
		}
				
		self::sendMessageToDestination(self::$exception_destination, $message);
		
		if (self::$exception_handler_callback === NULL) {
			return;
		}
				
		try {
			call_user_func_array(self::$exception_handler_callback, self::$exception_handler_parameters);
		} catch (Exception $e) {
			self::trigger(
				'error',
				self::compose(
					'An exception was thrown in the %s closing code callback',
					'setExceptionHandling()'
				)
			);
		}
	}
	
	
	/**
	 * Adds a callback for when a message is created using {@link compose()}
	 * 
	 * The primary purpose of these callbacks is for internationalization of
	 * error messaging in Flourish. The callback should accept a single
	 * parameter, the message being composed and should return the message
	 * with any modifications.
	 * 
	 * The timing parameter controls if the callback happens before or after
	 * the actual composition takes place, which is simply a call to
	 * {@link http://php.net/sprintf sprintf()}. Thus the message passed 'pre'
	 * will always be exactly the same, while the message 'post' will include
	 * the interpolated variables. Because of this, most of the time the 'pre'
	 * timing should be chosen. 
	 * 
	 * @param  string   $timing    When the callback should be executed, 'pre' or 'post' performing the actual composition
	 * @param  callback $callback  The callback
	 * @return void
	 */
	static public function registerComposeCallback($timing, $callback)
	{
		$valid_timings = array('pre', 'post');
		if (!in_array($timing, $valid_timings)) {
			self::toss(
				'fProgrammerException',
				self::compose(
					'The timing specified, %s, is not a valid timing. Must be one of: %s.',
					self::dump($timing),
					join(', ', $valid_timings)
				)	
			);	
		}
		
		self::$compose_callbacks[$timing][] = $callback;
	}
	
	
	/**
	 * Adds a callback for when certain types of exceptions are tossed
	 * 
	 * The callback will be called when any exception of the class, or any
	 * child class, specified is tossed. A single parameter will be passed
	 * to the callback, which will be the exception object.
	 * 
	 * @param  string   $exception_type  The type of exception to call the callback on
	 * @param  callback $callback        The callback
	 * @return void
	 */
	static public function registerTossCallback($exception_type, $callback)
	{
		if (!isset(self::$toss_callbacks[$exception_type])) {
			self::$toss_callbacks[$exception_type] = array();	
		}
		
		self::$toss_callbacks[$exception_type][] = $callback;
	}
	
	
	/**
	 * Handles sending a message to a destination
	 * 
	 * @param  string $destination  The destination for the error/exception. An email or file.
	 * @param  string $message      The message to send to the destination
	 * @return void
	 */
	static private function sendMessageToDestination($destination, $message)
	{
		$subject = self::compose(
			'[%s] An error/exception occured at %s',
			$_SERVER['SERVER_NAME'],
			date('Y-m-d H:i:s')
		);
		
		// Add variable information
		$context  = "\n\n" . self::compose('Context') . "\n-------";
		if ($destination != 'html') {
			$content .= "\n\$_SERVER['REQUEST_URI']\n" . self::dump($_SERVER['REQUEST_URI']) . "\n";
		}
		$context .= "\n" . '$_REQUEST' . "\n" . self::dump($_REQUEST);
		$context .= "\n\n" . '$_FILES' . "\n" . self::dump($_FILES);
		$context .= "\n\n" . '$_SESSION' . "\n" . self::dump((isset($_SESSION)) ? $_SESSION : NULL);
		
		switch (self::checkDestination($destination)) {
			case 'html':
				static $shown_context = FALSE;
				if (!$shown_context) {
					self::expose(trim($context), FALSE);
					$shown_context = TRUE;
				}
				self::expose($message, FALSE);
				break;
			
			case 'email':
				mail($destination, $subject, $message);
				break;
				
			case 'file':
				$handle = fopen($destination, 'a');
				fwrite($handle, $subject . "\n");
				fwrite($handle, $message . "\n");
				fclose($handle);
				break;
		}
	}
	
	
	/**
	 * Returns TRUE for non-empty strings, numbers and objects and for empty numbers and string-like numbers (such as 0, 0.0, '0')
	 * 
	 * @param  mixed $value  The value to check
	 * @return boolean  If the value is string-like
	 */
	static public function stringlike($value)
	{
		if (!$value && !is_numeric($value)) {
			return FALSE;
		} 	
		
		if (is_resource($value) || is_array($value) || $value === TRUE) {
			return FALSE;
		}
		
		return TRUE;	
	}
	
	
	/**
	 * Throws the exception class specified (if the class exists), otherwise throws a normal exception
	 * 
	 * @param  string $exception_class  The class of exception to throw
	 * @param  string $message          The exception message
	 * @return void
	 */
	static public function toss($exception_class, $message)
	{
		$exception = new $exception_class($message);
		foreach (self::$toss_callbacks as $class => $callbacks) {
			foreach ($callbacks as $callback) {
				if ($exception instanceof $class) {
					call_user_func($callback, $exception);
				}
			}
		}
		throw $exception;
	}
	
	
	/**
	 * Triggers a user-level error
	 * 
	 * The default error handler in PHP will show the line number of this
	 * method as the triggering code. To get a full backtrace, use
	 * {@link enableErrorHandling()}.
	 * 
	 * @param  string $error_type  The type of error to trigger ('error', 'warning' or 'notice')
	 * @param  string $message     The error message
	 * @return void
	 */
	static public function trigger($error_type, $message)
	{
		$valid_error_types = array('error', 'warning', 'notice');
		if (!in_array($error_type, $valid_error_types)) {
			self::toss(
				'fProgrammerException',
				self::compose(
					'Invalid error type, %s, specified. Must be one of: %s.',
					self::dump($error_type),
					join(', ', $valid_error_types)
				)
			);
		}
		
		static $error_type_map = array(
			'error'   => E_USER_ERROR,
			'warning' => E_USER_WARNING,
			'notice'  => E_USER_NOTICE
		);
		
		trigger_error($message, $error_type_map[$error_type]);
	}
	
	
	/**
	 * Forces use as a static class
	 * 
	 * @return fCore
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