<?php
/**
 * An exception that should probably not be handled by the display code, fCore::enableExceptionHandler() is recommended
 * 
 * @copyright  Copyright (c) 2007-2008 William Bond
 * @author     William Bond [wb] <will@flourishlib.com>
 * @license    http://flourishlib.com/license
 * 
 * @link  http://flourishlib.com/fUnexpectedException
 * 
 * @version  1.0.0
 * @changes  1.0.0    The initial implementation [wb, 2007-06-14]
 */
abstract class fUnexpectedException extends fPrintableException
{
	/**
	 * Prints out a generic error message inside of a div with the class being 'exception THIS_EXCEPTION_CLASS_NAME'
	 * 
	 * @return void
	 */
	public function printMessage() 
	{
		// underscorize the current exception class name, extracted from fInflection::underscorize() to reduce dependencies
		$exception_class = strtolower(preg_replace('/(?:([a-z0-9A-Z])([A-Z])|([a-zA-Z])([0-9]))/', '\1\3_\2\4', preg_replace('#^f#', '', get_class($this))));
		$css_class       = 'exception ' . $exception_class;

		echo '<div class="' . $css_class . '">';
		echo '<p>It appears an error has occured &mdash; we apologize for the inconvenience. The problem may be resolved if you try again.</p>';
		echo '</div>';
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
?>