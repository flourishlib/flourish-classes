<?php
/**
 * Allows for quick and flexible html templating
 * 
 * @copyright  Copyright (c) 2007 William Bond
 * @author     William Bond [wb] <will@flourishlib.com>
 * @license    http://flourishlib.com/license
 * 
 * @version  1.0.0
 * @changes  1.0.0    The initial implementation [wb, 2007-06-14]
 */
class fTemplating
{
	/**
	 * A data store for templating
	 * 
	 * @var array 
	 */
	private $elements;

	/**
	 * The directory to look for files
	 * 
	 * @var array 
	 */
	private $root;
	
	
	/**
	 * Initializes this templating engine
	 * 
	 * @since 1.0.0
	 * 
	 * @param  string $root   The filesystem path to use when accessing relative files
	 * @return fTemplating
	 */
	public function __construct($root)
	{
		if (!file_exists($root)) {
			fCore::toss('fProgrammerException', 'The root specified does not exist on the filesystem');       
		}
		
		if (!is_readable($root)) {
			fCore::toss('fProgrammerException', 'The root specified can not be read from');       
		}
		
		if (substr($root, -1) != '/' && substr($root, -1) != '\\') {
			$root .= (strpos($root, '\\') !== FALSE) ? '\\' : '/';	
		}
		
		$this->root = $root;	
	}
	
	
	/**
	 * Set a value for an element
	 * 
	 * @since 1.0.0
	 * 
	 * @param  string $element   The element to set
	 * @param  mixed  $value     The value for the element
	 * @return void
	 */
	public function set($element, $value)
	{
		$this->elements[$element] = $value;	
	}
	
	
	/**
	 * Gets the value of an element
	 * 
	 * @since 1.0.0
	 * 
	 * @param  string $element   The element to get
	 * @return mixed  The value of the element specified, or NULL if it has not been set
	 */
	public function get($element)
	{
		return (isset($this->elements[$element])) ? $this->elements[$element] : NULL;	
	}
	
	
	/**
	 * Includes the element specified (element must be set through setElement() first)
	 * 
	 * @since 1.0.0
	 * 
	 * @param  string $element   The element to include
	 * @return void
	 */
	public function place($element)
	{
		if (!isset($this->elements[$element])) {
			fCore::toss('fProgrammerException', 'The element specified has not been set');       
		}
		
		$values = $this->elements[$element];
		settype($values, 'array');
		
		foreach ($values as $value) {
			if (empty($value)) {
				fCore::toss('fProgrammerException', 'The element specified is empty');	
			}
			
			// Check to see if the element is an absolute path
			if (!preg_match('#^(/|\\|[a-z]:(\\|/)|\\\\|//)#i', $value)) {
				$value = $this->root . $value;		
			}
			
			if (!file_exists($value)) {
				fCore::toss('fProgrammerException', 'The element specified does not exist on the filesystem');       
			}
			
			if (!is_readable($value)) {
				fCore::toss('fProgrammerException', 'The element specified can not be read from');       
			}
			
			include($value);	
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