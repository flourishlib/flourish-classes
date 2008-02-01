<?php
/**
 * Allows for quick and flexible html templating
 * 
 * @copyright  Copyright (c) 2007 William Bond
 * @author     William Bond [wb] <will@flourishlib.com>
 * @license    http://flourishlib.com/license
 * 
 * @link  http://flourishlib.com/fTemplating
 * 
 * @uses  fCore
 * @uses  fProgrammerException
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
	 * @param  string $root   The filesystem path to use when accessing relative files, defaults to $_SERVER['DOCUMENT_ROOT']
	 * @return fTemplating
	 */
	public function __construct($root=NULL)
	{
		if ($root === NULL) {
		 	$root = $_SERVER['DOCUMENT_ROOT'];	
		}
		
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
	 * @param  string $element        The element to get
	 * @param  mixed  $default_value  The value to return if the element has not been set
	 * @return mixed  The value of the element specified, or the default value if it has not been set
	 */
	public function get($element, $default_value=NULL)
	{
		return (isset($this->elements[$element])) ? $this->elements[$element] : $default_value;	
	}
	
	
	/**
	 * Includes the element specified (element must be set through setElement() first)
	 * 
	 * @param  string $element   The element to include
	 * @return void
	 */
	public function place($element)
	{
		if (!isset($this->elements[$element])) {
			fCore::toss('fProgrammerException', 'The element specified has not been set');       
		}
		
		$__values = $this->elements[$element];
		settype($__values, 'array');
		unset($element);
		
		extract($this->elements);
		foreach ($__values as $__value) {
			if (empty($__value)) {
				fCore::toss('fProgrammerException', 'The element specified is empty');	
			}
			
			// Check to see if the element is an absolute path
			if (!preg_match('#^(/|\\|[a-z]:(\\|/)|\\\\|//)#i', $__value)) {
				$__value = $this->root . $__value;		
			}
			
			if (!file_exists($__value)) {
				fCore::toss('fProgrammerException', 'The element specified does not exist on the filesystem');       
			}
			
			if (!is_readable($__value)) {
				fCore::toss('fProgrammerException', 'The element specified can not be read from');       
			}
			
			include($__value);	
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