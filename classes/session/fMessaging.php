<?php
/**
 * Provides session-based messaging for page-to-page communication
 * 
 * @copyright  Copyright (c) 2007-2008 William Bond
 * @author     William Bond [wb] <will@flourishlib.com>
 * @license    http://flourishlib.com/license
 * 
 * @link  http://flourishlib.com/fMessaging
 * 
 * @uses  fHTML
 * @uses  fSession
 * 
 * @version  1.0.0
 * @changes  1.0.0    The initial implementation [wb, 2008-03-05]
 */
class fMessaging
{
	/**
	 * Creates a message that is stored in the session and retrieved by another page
	 * 
	 * @param  string $name       A name for the message
	 * @param  string $message    The message to send
	 * @param  string $recipient  The intended recipient
	 * @return void
	 */
	static public function create($name, $message, $recipient)
	{
		fSession::set($name, $message, 'fMessaging::' . $recipient . '::');
	}
	
	
	/**
	 * Retrieves and removes a message from the session
	 * 
	 * @param  string $name       The name of the message to retrieve
	 * @param  string $recipient  The intended recipient
	 * @return string  The message contents
	 */
	static public function retrieve($name, $recipient)
	{
		$prefix = 'fMessaging::' . $recipient . '::';
		$message = fSession::get($name, NULL, $prefix);
		fSession::clear($name, $prefix);
		return $message;
	}
	
	
	/**
	 * Prints a paragraph (or div if the content has block-level html) with the contents and the class specified. Will not print if no content.
	 * 
	 * @param  string $content    The content to display
	 * @param  string $css_class  The css class to apply
	 * @return void
	 */
	static public function show($content, $css_class)
	{
		if (empty($content)) {
			return;
		}
		
		if (fHTML::checkForBlockLevelHTML($content)) {
			echo '<div class="' . $css_class . '">' . fHTML::prepare($content) . '</div>';
		} else {
			echo '<p class="' . $css_class . '">' . fHTML::prepare($content) . '</p>';
		}
	}
	
	
	/**
	 * Forces use as a static class
	 * 
	 * @return fMessaging
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