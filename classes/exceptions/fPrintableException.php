<?php
/**
 * An exception that can easily be printed
 * 
 * @copyright  Copyright (c) 2007-2008 William Bond
 * @author     William Bond [wb] <will@flourishlib.com>
 * @license    http://flourishlib.com/license
 * 
 * @link  http://flourishlib.com/fPrintableException
 * 
 * @version  1.0.0
 * @changes  1.0.0    The initial implementation [wb, 2007-06-14]
 */
abstract class fPrintableException extends Exception
{
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
		// underscorize the current exception class name, extracted from fInflection::underscorize() to reduce dependencies
		return strtolower(preg_replace('/(?:([a-z0-9A-Z])([A-Z])|([a-zA-Z])([0-9]))/', '\1\3_\2\4', preg_replace('#^f#', '', get_class($this))));
	}
	
	
	/**
	 * Prepares content for output into HTML
	 * 
	 * @return string  The prepared content
	 */
	protected function prepare($content)
	{
		// See if the message has newline characters but not br tags, extracted from fHTML::convertNewlines() to reduce dependencies
		static $inline_tags_minus_br = '<a><abbr><acronym><b><big><button><cite><code><del><dfn><em><font><i><img><input><ins><kbd><label><q><s><samp><select><small><span><strike><strong><sub><sup><textarea><tt><u><var>';
		$content_with_newlines = (strip_tags($content, $inline_tags_minus_br)) ? $content : nl2br($content);
		
		// Check to see if we have any block-level html, extracted from fHTML::checkForBlockLevelHtml() to reduce dependencies
		$inline_tags = $inline_tags_minus_br . '<br>';
		$no_block_html = strip_tags($content, $inline_tags) == $content;
		
		// This code ensures the output is properly encoded for display in (X)HTML, extracted from fHTML::prepare() to reduce dependencies
		$reg_exp = "/<\s*\/?\s*[\w:]+(?:\s+[\w:]+(?:\s*=\s*(?:\"[^\"]*?\"|'[^']*?'|[^'\">\s]+))?)*\s*\/?\s*>|&(?:#\d+|\w+);|<\!--.*?-->/";
		preg_match_all($reg_exp, $content, $html_matches, PREG_SET_ORDER);
		$text_matches = preg_split($reg_exp, $content_with_newlines);
		foreach($text_matches as $key => $value) {
			$value = htmlentities($value, ENT_COMPAT, 'UTF-8');
			$windows_characters = array(
				chr(130) => '&lsquor;', chr(131) => '&fnof;',   chr(132) => '&ldquor;',
				chr(133) => '&hellip;', chr(134) => '&dagger;', chr(135) => '&Dagger;',
				chr(136) => '&#710;',   chr(137) => '&permil;', chr(138) => '&Scaron;',
				chr(139) => '&lsaquo;', chr(140) => '&OElig;',  chr(145) => '&lsquo;',
				chr(146) => '&rsquo;',  chr(147) => '&ldquo;',  chr(148) => '&rdquo;',
				chr(149) => '&bull;',   chr(150) => '&ndash;',  chr(151) => '&mdash;',
				chr(152) => '&tilde;',  chr(153) => '&trade;',  chr(154) => '&scaron;',
				chr(155) => '&rsaquo;', chr(156) => '&oelig;',  chr(159) => '&Yuml;'
			);
			$text_matches[$key] = strtr($value, $windows_characters);
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
	 * Prepares the message for output into HTML
	 * 
	 * @return string  The prepared message
	 */
	public function prepareMessage()
	{
		return $this->prepare($this->message);
	}
	
	
	/**
	 * Prints the message inside of a div with the class being 'exception %THIS_EXCEPTION_CLASS_NAME%'
	 * 
	 * @return void
	 */
	public function printMessage()
	{
		echo '<div class="exception ' . $this->getCSSClass() . '">';
		echo $this->prepareMessage();
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
		$trace = $this->formatTrace();
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