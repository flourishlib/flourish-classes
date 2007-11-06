<?php
/**
 * Provides low-level debugging, error and exception functionality
 * 
 * @copyright  Copyright (c) 2007 William Bond
 * @author     William Bond [wb] <will@flourishlib.com>
 * @license    http://flourishlib.com/license
 * 
 * @version  1.0.0
 * @changes  1.0.0    The initial implementation [wb, 2007-09-25]
 */
class fCore
{	
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
	static private $error_destination = NULL;
	
	/**
	 * Exception destination
	 * 
	 * @var string 
	 */
	static private $exception_destination = NULL;
	
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
	 * Forces use as a static class
	 * 
	 * @since 1.0.0
	 * 
	 * @return fCore
	 */
	private function __construct() { }
	
	
	/**
	 * Turns on or off global debugging
	 * 
	 * @since  1.0.0
	 * 
	 * @param  boolean $enable  If debugging should be enabled
	 * @return void
	 */
	static public function setDebug($enable)
	{
		self::$debug = (boolean) $enable;
	}
	
	
	/**
	 * Throws the exception type specified (if the class exists), otherwise throws a normal exception
	 * 
	 * @since  1.0.0
	 * 
	 * @param  string $exception_type  The type of exception to throw
	 * @param  string $message         The exception message
	 * @return void
	 */
	static public function toss($exception_type, $message)
	{
		$exception = new $exception_type($message);
		foreach (self::$toss_callbacks as $class => $callback) {
			if ($exception instanceof $class) {
				call_user_func($callback);
			}	
		}
		throw $exception;
	}
	
	
	/**
	 * Adds a callback for when certain types of exceptions are tossed
	 * 
	 * @since  1.0.0
	 * 
	 * @param  string   $exception_type  The type of exception to call the callback on
	 * @param  callback $callback        This callback
	 * @return void
	 */
	static public function addTossCallback($exception_type, $callback)
	{
		self::$toss_callbacks[$exception_type] = $callback;		 
	}
	
	
	/**
	 * Prints a debugging message if global or code-specific debugging is enabled 
	 * 
	 * @since  1.0.0
	 * 
	 * @param  string  $message   The debug message
	 * @param  boolean $force     If debugging should be forced even when global debug is off
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
	 * @since  1.0.0
	 * 
	 * @param  mixed $data   The variable to dump
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
	 * Prints the contents of a variable
	 * 
	 * @since  1.0.0
	 * 
	 * @param  mixed $data   The data to show
	 * @param  mixed $dump   If a dump of the variable should be shown
	 * @return void
	 */
	static public function expose($data)
	{
		$data = self::dump($data);	
		echo '<pre class="exposed">' . htmlentities((string) $data, ENT_COMPAT, 'UTF-8') . '</pre>';     
	}
	
	
	/**
	 * Turns on special error handling. All errors that match the current error_reporting() level will be redirected to the destination.
	 * 
	 * @since  1.0.0
	 * 
	 * @param  string $destination   The destination for the errors. An email or file.
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
	 * Turns on special exception handling. Any uncaught exception will be redirected to the destination specified, and the page will execute the $closing_code callback before exiting.
	 * 
	 * @since  1.0.0
	 * 
	 * @param  string   $destination    The destination for the exception. An email or file.
	 * @param  callback $closing_code   This callback will happen after the exception is handled and before page execution stops. Good for printing a footer.
	 * @param  array    $parameters     The parameters to send to $closing_code
	 * @return void
	 */
	static public function enableExceptionHandling($destination, $closing_code, $parameters=array())
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
	 * Handles an error
	 * 
	 * @since  1.0.0
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
		
		$doc_root  = $_SERVER['DOCUMENT_ROOT'];
		$doc_root .= (substr($_SERVER['DOCUMENT_ROOT'], -1) != '/' && substr($_SERVER['DOCUMENT_ROOT'], -1) != '\\') ? '/' : '';
		
		$error_file = str_replace($doc_root, '{doc_root}/', $error_file);

		$backtrace = debug_backtrace();
		array_shift($backtrace);
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
							if (strlen($arg) > 18) {
								$arg = substr($arg, 0, 15) . '...';	
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
		$bt_string .= "\n";		
		$bt_string .= str_replace($doc_root, '{doc_root}/', $error_file) . '(' . $error_line . '):'; 
		
		$error_string = preg_replace('# \[<a href=\'.*?</a>\]: #', ': ', $error_string);
		
		$error  = "Error\n-----\n" . $bt_string . "\n" . $error_string;
		
		self::sendMessageToDestination(self::$error_destination, $error);			 
	}
	
	
	/**
	 * Handles an uncaught exception
	 * 
	 * @since  1.0.0
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
		$message = "Uncaught Exception\n------------------\n" . $message;
		
		if (self::$exception_destination != 'html' && $exception instanceof fPrintableException) {
			$exception->printMessage();	
		}
				
		self::sendMessageToDestination(self::$exception_destination, $message);
				
		try {
			call_user_func_array(self::$exception_handler_callback, self::$exception_handler_parameters);
		} catch (Exception $e) {
			trigger_error('An exception was thrown in the setExceptionHandling() $closing_code callback', E_USER_ERROR);
		}		 
	}
	
	
	/**
	 * Checks an error/exception destination
	 * 
	 * @since  1.0.0
	 * 
	 * @param  string $destination     The destination for the exception. An email or file.
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
	 * Handles sending a message to a destination
	 * 
	 * @since  1.0.0
	 * 
	 * @param  string $destination     The destination for the error/exception. An email or file.
	 * @param  string $message         The message to send to the destination
	 * @return void
	 */
	static private function sendMessageToDestination($destination, $message)
	{
		$subject = '[' . $_SERVER['SERVER_NAME'] . '] An error/exception occured at ' . date('Y-m-d H:i:s');
		
		// Add variable information
		$context  = "\n\nContext\n-------";
		if ($destination != 'html') {
			$content .= "\n" . '$_SERVER[\'REQUEST_URI\']' . "\n" . self::dump($_SERVER['REQUEST_URI']) . "\n";
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
}  



/**
 * An exception that can easily be printed
 * 
 * @copyright  Copyright (c) 2007 William Bond
 * @author     William Bond [wb] <will@flourishlib.com>
 * @license    http://flourishlib.com/license
 * 
 * @version  1.0.0
 * @changes  1.0.0    The initial implementation [wb, 2007-06-14]
 */
class fPrintableException extends Exception
{
	/**
	 * Prints the message inside of a div with the class being 'exception THIS_EXCEPTION_CLASS_NAME'
	 * 
	 * @since  1.0.0
	 * 
	 * @return void
	 */
	public function printMessage() 
	{
		$css_class     = 'exception ' . fInflection::underscorize(get_class($this));
		$no_block_html = strip_tags($this->getMessage(), '<a><abbr><acronym><b><code><em><i><span><strong>') == $this->getMessage();
		
		echo '<div class="' . $css_class . '">';
		echo ($no_block_html) ? '<p>' : '';
		echo fHTML::encodeHtml(fHTML::convertNewlines($this->getMessage()));
		echo ($no_block_html) ? '</p>' : '';
		echo '</div>';
	}
	
	
	/**
	 * Gets the backtrace to currently called exception
	 * 
	 * @since  1.0.0
	 * 
	 * @return string  A nicely formatted backtrace to this exception
	 */
	public function formatTrace()
	{
		$doc_root  = $_SERVER['DOCUMENT_ROOT'];
		$doc_root .= (substr($_SERVER['DOCUMENT_ROOT'], -1) != '/' && substr($_SERVER['DOCUMENT_ROOT'], -1) != '\\') ? '/' : '';
		
		$backtrace = explode("\n", $this->getTraceAsString());
		$backtrace = preg_replace('/^#\d+\s+/', '', $backtrace);
		$backtrace = str_replace($doc_root, '{doc_root}/', $backtrace);
		$backtrace = array_diff($backtrace, array('{main}'));     
		$backtrace = array_reverse($backtrace);

		return join("\n", $backtrace);
	}
	
	
	/**
	 * Allows the message to be overwriten
	 * 
	 * @since  1.0.0
	 * 
	 * @param  string $new_message  The new message for the exception
	 * @return void
	 */
	public function setMessage($new_message) 
	{
		$this->message = $new_message;
	}
}



/**
 * An exception that should be handled by the display code
 * 
 * @copyright  Copyright (c) 2007 William Bond
 * @author     William Bond [wb] <will@flourishlib.com>
 * @license    http://flourishlib.com/license
 * 
 * @version  1.0.0
 * @changes  1.0.0    The initial implementation [wb, 2007-06-14]
 */
class fExpectedException extends fPrintableException
{
}



/**
 * An exception caused by a data not matching a rule or set of rules
 * 
 * @copyright  Copyright (c) 2007 William Bond
 * @author     William Bond [wb] <will@flourishlib.com>
 * @license    http://flourishlib.com/license
 * 
 * @version  1.0.0
 * @changes  1.0.0    The initial implementation [wb, 2007-06-14]
 */
class fValidationException extends fExpectedException
{
}



/**
 * An exception that should probably not be handled by the display code, fCore::enableExceptionHandler() is recommended
 * 
 * @copyright  Copyright (c) 2007 William Bond
 * @author     William Bond [wb] <will@flourishlib.com>
 * @license    http://flourishlib.com/license
 * 
 * @version  1.0.0
 * @changes  1.0.0    The initial implementation [wb, 2007-06-14]
 */
class fUnexpectedException extends fPrintableException
{
	/**
	 * Prints out a generic error message inside of a div with the class being 'exception f_printable_exception'
	 * 
	 * @since  1.0.0
	 * 
	 * @return void
	 */
	public function printMessage() 
	{
		$css_class     = 'exception ' . fInflection::underscorize(get_class($this));

		echo '<div class="' . $css_class . '">';
		echo '<p>It appears an error has occured &mdash; we apologize for the inconvenience. The problem may be resolved if you try again.</p>';
		echo '</div>';
	}
}



/**
 * An exception caused by an environment error
 * 
 * @copyright  Copyright (c) 2007 William Bond
 * @author     William Bond [wb] <will@flourishlib.com>
 * @license    http://flourishlib.com/license
 * 
 * @version  1.0.0
 * @changes  1.0.0    The initial implementation [wb, 2007-06-14]
 */
class fEnvironmentException extends fUnexpectedException
{
}



/**
 * An exception caused by a connectivity error
 * 
 * @copyright  Copyright (c) 2007 William Bond
 * @author     William Bond [wb] <will@flourishlib.com>
 * @license    http://flourishlib.com/license
 * 
 * @version  1.0.0
 * @changes  1.0.0    The initial implementation [wb, 2007-06-14]
 */
class fConnectivityException extends fUnexpectedException
{
} 



/**
 * An exception caused by programmer error
 * 
 * @copyright  Copyright (c) 2007 William Bond
 * @author     William Bond [wb] <will@flourishlib.com>
 * @license    http://flourishlib.com/license
 * 
 * @version  1.0.0
 * @changes  1.0.0    The initial implementation [wb, 2007-06-14]
 */
class fProgrammerException extends fUnexpectedException
{
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