<?php
/**
 * Provides functionality for XML files
 * 
 * This class is implemented to use the UTF-8 character encoding. Please see
 * {@link http://flourishlib.com/docs/UTF-8} for more information.
 * 
 * @copyright  Copyright (c) 2007-2008 William Bond
 * @author     William Bond [wb] <will@flourishlib.com>
 * @license    http://flourishlib.com/license
 * 
 * @package    Flourish
 * @link       http://flourishlib.com/fXML
 * 
 * @version    1.0.0b
 * @changes    1.0.0b  The initial implementation [wb, 2008-01-13]
 */
class fXML
{
	/**
	 * Encodes content for display in a UTF-8 encoded XML document
	 * 
	 * @param  string $content  The content to encode
	 * @return string  The encoded content
	 */
	static public function encode($content)
	{
		return htmlspecialchars(fHTML::decode($content));
	}
	
	
	/**
	 * Sets the proper Content-Type header for a UTF-8 XML file
	 * 
	 * @return void
	 */
	static public function sendHeader()
	{
		header('Content-Type: text/xml; charset=utf-8');
	}
	
	
	/**
	 * Forces use as a static class
	 * 
	 * @return fXML
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