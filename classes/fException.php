<?php
/**
 * An exception that allows for easy l10n, printing, tracing and hooking
 * 
 * @copyright  Copyright (c) 2007-2008 William Bond
 * @author     William Bond [wb] <will@flourishlib.com>
 * @license    http://flourishlib.com/license
 * 
 * @package    Flourish
 * @link       http://flourishlib.com/fException
 * 
 * @version    1.0.0b
 * @changes    1.0.0b  The initial implementation [wb, 2007-06-14]
 */
abstract class fException extends Exception
{
	/**
	 * Callbacks for when exceptions are created
	 * 
	 * @var array
	 */
	static private $callbacks = array();
	
	
	/**
	 * Composes text using fText if loaded
	 * 
	 * @param  string  $message    The message to compose
	 * @param  mixed   $component  A string or number to insert into the message
	 * @param  mixed   ...
	 * @return string  The composed and possible translated message
	 */
	static protected function compose($message)
	{
		$args = array_slice(func_get_args(), 1);
		
		if (is_array($args) && sizeof($args) == 1) {
			$args = $args[0];	
		}
		
		if (class_exists('fText', FALSE)) {
			return call_user_func_array(
				array('fText', 'compose'),
				array($message, $args)
			);
		} else {
			return vsprintf($message, $args);
		}
	}
	
	
	/**
	 * Creates a string representation of any variable using predefined strings for booleans, `NULL` and empty strings
	 * 
	 * The string output format of this method is very similar to the output of
	 * [http://php.net/print_r print_r()] except that the following values
	 * are represented as special strings:
	 *   
	 *  - `TRUE`: `'{true}'`
	 *  - `FALSE`: `'{false}'`
	 *  - `NULL`: `'{null}'`
	 *  - `''`: `'{empty_string}'`
	 * 
	 * @param  mixed $data  The value to dump
	 * @return string  The string representation of the value
	 */
	static protected function dump($data)
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
			$output = preg_replace('#=> (&)?NULL#', '=> \1{null}', $output);
			$output = preg_replace('#=> (&)?bool\((false|true)\)#', '=> \1{\2}', $output);
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
	 * Adds a callback for when certain types of exceptions are created 
	 * 
	 * The callback will be called when any exception of this class, or any
	 * child class, specified is tossed. A single parameter will be passed
	 * to the callback, which will be the exception object.
	 * 
	 * @param  callback $callback        The callback
	 * @param  string   $exception_type  The type of exception to call the callback for
	 * @return void
	 */
	static public function registerCallback($callback, $exception_type=NULL)
	{
		if ($exception_type === NULL) {
			$exception_type = 'fException';	
		}
		
		if (!isset(self::$callbacks[$exception_type])) {
			self::$callbacks[$exception_type] = array();
		}
		
		if (is_string($callback) && strpos($callback, '::') !== FALSE) {
			$callback = explode('::', $callback);	
		}
		
		self::$callbacks[$exception_type][] = $callback;
	}
	
	
	/**
	 * Sets the message for the exception, allowing for string interpolation and internationalization
	 * 
	 * @param  string  $message    The message for the exception
	 * @param  mixed   $component  A string or number to insert into the message
	 * @param  mixed   ...
	 * @param  integer $code       The exception code to set
	 * @return fException
	 */
	public function __construct($message)
	{
		$args          = array_slice(func_get_args(), 1);
		$required_args = preg_match_all('#(?<!%)%(\d+\$)?[\-+]?( |0|\'.)?-?\d*(\.\d+)?[bcdeufFosxX]#', $message, $matches);
		
		$code = NULL;
		if ($required_args == sizeof($args) - 1) {
			$code = array_pop($args);		
		}
		
		if (sizeof($args) != $required_args) {
			$message = self::compose(
				'Only %1$d components were passed to the %2$s constructor, while %3$d were specified in the message',
				sizeof($args),
				get_class($this),
				$required_args
			);
			throw new Exception($message);	
		}
		
		$args = array_map(array('fException', 'dump'), $args);
		
		parent::__construct(
			self::compose($message, $args),
			$code
		);
		
		foreach (self::$callbacks as $class => $callbacks) {
			foreach ($callbacks as $callback) {
				if ($this instanceof $class) {
					call_user_func($callback, $this);
				}
			}
		}		
	}
	
	
	/**
	 * All requests that hit this method should be requests for callbacks
	 * 
	 * @param  string $method  The method to create a callback for
	 * @return callback  The callback for the method requested
	 */
	public function __get($method)
	{
		return array($this, $method);		
	}
	
	
	/**
	 * Gets the backtrace to currently called exception
	 * 
	 * @return string  A nicely formatted backtrace to this exception
	 */
	public function formatTrace()
	{
		$doc_root  = realpath($_SERVER['DOCUMENT_ROOT']);
		$doc_root .= (substr($doc_root, -1) != '/' && substr($doc_root, -1) != '\\') ? '/' : '';
		
		$backtrace = explode("\n", $this->getTraceAsString());
		$backtrace = preg_replace('/^#\d+\s+/', '', $backtrace);
		$backtrace = str_replace($doc_root, '{doc_root}/', $backtrace);
		$backtrace = array_diff($backtrace, array('{main}'));
		$backtrace = array_reverse($backtrace);
		
		return join("\n", $backtrace);
	}
	
	
	/**
	 * Returns the CSS class name for printing information about the exception
	 * 
	 * @return void
	 */
	protected function getCSSClass()
	{
		$string = preg_replace('#^f#', '', get_class($this));
		
		do {
			$old_string = $string;
			$string = preg_replace('/([a-zA-Z])([0-9])/', '\1_\2', $string);
			$string = preg_replace('/([a-z0-9A-Z])([A-Z])/', '\1_\2', $string);
		} while ($old_string != $string);
		
		return strtolower($string);
	}
	
	
	/**
	 * Prepares content for output into HTML
	 * 
	 * @return string  The prepared content
	 */
	protected function prepare($content)
	{
		// See if the message has newline characters but not br tags, extracted from fHTML to reduce dependencies
		static $inline_tags_minus_br = '<a><abbr><acronym><b><big><button><cite><code><del><dfn><em><font><i><img><input><ins><kbd><label><q><s><samp><select><small><span><strike><strong><sub><sup><textarea><tt><u><var>';
		$content_with_newlines = (strip_tags($content, $inline_tags_minus_br)) ? $content : nl2br($content);
		
		// Check to see if we have any block-level html, extracted from fHTML to reduce dependencies
		$inline_tags = $inline_tags_minus_br . '<br>';
		$no_block_html = strip_tags($content, $inline_tags) == $content;
		
		$content = html_entity_decode($content, ENT_QUOTES, 'UTF-8');
		
		// This code ensures the output is properly encoded for display in (X)HTML, extracted from fHTML to reduce dependencies
		$reg_exp = "/<\s*\/?\s*[\w:]+(?:\s+[\w:]+(?:\s*=\s*(?:\"[^\"]*?\"|'[^']*?'|[^'\">\s]+))?)*\s*\/?\s*>|&(?:#\d+|\w+);|<\!--.*?-->/";
		preg_match_all($reg_exp, $content, $html_matches, PREG_SET_ORDER);
		$text_matches = preg_split($reg_exp, $content_with_newlines);
		
		foreach($text_matches as $key => $value) {
			$value = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
		}
		
		for ($i = 0; $i < sizeof($html_matches); $i++) {
			$text_matches[$i] .= $html_matches[$i][0];
		}
		
		$content_with_newlines = implode($text_matches);
		
		$output  = ($no_block_html) ? '<p>' : '';
		$output .= $content_with_newlines;
		$output .= ($no_block_html) ? '</p>' : '';
		
		return $output;
	}
	
	
	/**
	 * Prints the message inside of a div with the class being 'exception %THIS_EXCEPTION_CLASS_NAME%'
	 * 
	 * @return void
	 */
	public function printMessage()
	{
		echo '<div class="exception ' . $this->getCSSClass() . '">';
		echo $this->prepare($this->message);
		echo '</div>';
	}
	
	
	/**
	 * Prints the backtrace to currently called exception inside of a pre tag with the class being 'exception %THIS_EXCEPTION_CLASS_NAME% trace'
	 * 
	 * @return void
	 */
	public function printTrace()
	{
		echo '<pre class="exception ' . $this->getCSSClass() . ' trace">';
		echo $this->formatTrace();
		echo '</pre>';
	}
	
	
	/**
	 * Allows the message to be overwriten
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