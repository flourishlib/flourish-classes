<?php
/**
 * Provides english words pluralization, singularization, camelCase, undercore_notation, etc
 * 
 * @copyright  Copyright (c) 2007-2008 William Bond
 * @author     William Bond [wb] <will@flourishlib.com>
 * @license    http://flourishlib.com/license
 * 
 * @link  http://flourishlib.com/fInflection
 * 
 * @version  1.0.0
 * @changes  1.0.0    The initial implementation [wb, 2007-09-25]
 */
class fInflection
{
	/**
	 * A listing of words that should be converted to all capital letters, instead of just the first letter
	 * 
	 * @var array
	 */
	static private $all_capitals_words = array(
		'api',
		'css',
		'gif',
		'html',
		'id',
		'jpg',
		'mp3',
		'pdf',
		'php',
		'png',
		'sql',
		'swf',
		'url',
		'xhtml',
		'xml'
	);
	
	/**
	 * Rules for plural to singular inflection of nouns
	 * 
	 * @var array
	 */
	static private $plural_to_singular_rules = array(
		'^(p)hotos$'     => '\1hoto',
		'^(l)ogos$'      => '\1ogo',
		'^(n)ews$'       => '\1ews',
		'^(q)uizzes$'    => '\1uiz',
		'^(c)hildren$'   => '\1hild',
		'^(p)eople$'     => '\1erson',
		'(m)en$'         => '\1an',
		'(phase)s'       => '\1',
		'([csx])es$'     => '\1is',
		'([cs]h)es$'     => '\1',
		'(ss)es$'        => '\1',
		'([aeo]l)ves$'   => '\1f',
		'([^d]ea)ves$'   => '\1f',
		'(ar)ves$'       => '\1f',
		'([nlw]i)ves$'   => '\1fe',
		'([aeiou]y)s$'   => '\1',
		'([^aeiou])ies$' => '\1y',
		'(x)es$'         => '\1',
		'(s)es$'         => '\1',
		'(.)s$'          => '\1'
	);
	
	/**
	 * Rules for singular to plural inflection of nouns
	 * 
	 * @var array
	 */
	static private $singular_to_plural_rules = array(
		'^(p)hoto$'    => '\1hotos',
		'^(l)ogo$'     => '\1ogos',
		'^(n)ews$'     => '\1ews',
		'^(q)uiz$'     => '\1uizzes',
		'^(c)hild$'    => '\1hildren',
		'^(p)erson$'   => '\1eople',
		'(m)an$'       => '\1en',
		'([csx])is$'   => '\1es',
		'([cs]h)$'     => '\1es',
		'(ss)$'        => '\1es',
		'([aeo]l)f$'   => '\1ves',
		'([^d]ea)f$'   => '\1ves',
		'(ar)f$'       => '\1ves',
		'([nlw]i)fe$'  => '\1ves',
		'([aeiou]y)$'  => '\1s',
		'([^aeiou])y$' => '\1ies',
		'(x)$'         => '\1es',
		'(s)$'         => '\1es',
		'(.)$'         => '\1s'
	);
	
	
	/**
	 * Adds a word to the list of all capital letters words, which is used by {@link fInflection::humanize()} to produce more gramatically correct results
	 * 
	 * @param  string $word  The word that should be in all caps when printed
	 * @return void
	 */
	static public function addAllCapitalsWord($word)
	{
		self::$all_capitals_words[] = strtolower($word);
	}
	
	
	/**
	 * Adds a custom singular->plural and plural->singular rule
	 * 
	 * @param  string $singular  The singular version of the noun
	 * @param  string $plural    The plural version of the noun
	 * @return void
	 */
	static public function addSingularPluralRule($singular, $plural)
	{
		self::$singular_to_plural_rules = array_merge(array('^(' . $singular[0] . ')' . substr($singular, 1) . '$' => '\1' . substr($plural, 1)),
													  self::$singular_to_plural_rules);
		self::$plural_to_singular_rules = array_merge(array('^(' . $plural[0] . ')' . substr($plural, 1) . '$' => '\1' . substr($singular, 1)),
													  self::$plural_to_singular_rules);
	}
	
	
	/**
	 * Converts an underscore notation string to camelCase
	 * 
	 * @param  string  $string  The string to convert
	 * @param  boolean $upper   If the camel case should be upper camel case
	 * @return string  The converted string
	 */
	static public function camelize($string, $upper)
	{
		// Check to make sure this is not already camel case
		if (strpos($string, '_') === FALSE) {
			if ($upper) {
				$string = strtoupper($string[0]) . substr($string, 1);
			}
			return $string;	
		}  
		
		// Handle underscore notation
		$string = strtolower($string);
		if ($upper) {
			$string = strtoupper($string[0]) . substr($string, 1);
		}
		return preg_replace('/(_([a-z0-9]))/e', 'strtoupper("\2")', $string);
	}
	
	
	/**
	 * Makes an underscore notation string into a human-friendly string
	 * 
	 * @param  string $string  The string to humanize
	 * @return string  The converted string
	 */
	static public function humanize($string)
	{
		return preg_replace('/(\b(' . join('|', self::$all_capitals_words) . ')\b|\b\w)/e', 'strtoupper("\1")', str_replace('_', ' ', $string));
	}
	
	
	/**
	 * Returns the singular or plural form of the word or based on the quantity specified
	 * 
	 * @param  mixed   $quantity                     The quantity (integer) or an array of objects to count
	 * @param  string  $singular_form                The string to be returned for when $quantity = 1
	 * @param  string  $plural_form                  The string to be returned for when $quantity != 1, use %d to place the quantity in the string
	 * @param  boolean $use_words_for_single_digits  If the numbers 0 to 9 should be written out as words
	 * @return string
	 */
	static public function inflectOnQuantity($quantity, $singular_form, $plural_form=NULL, $use_words_for_single_digits=FALSE)
	{
		if ($plural_form === NULL) {
			$plural_form = self::pluralize($singular_form);	
		}
		
		if (is_array($quantity)) {
			$quantity = sizeof($quantity);
		}
		
		if ($quantity == 1) {
			return $singular_form;
			
		} else {
			$output = $plural_form;
			
			// Handle placement of the quantity into the output
			if (strpos($output, '%d') !== FALSE) {
				
				if ($use_words_for_single_digits && $quantity < 10) {
					$replacements = array(
						0 => 'zero',
						1 => 'one',
						2 => 'two',
						3 => 'three',
						4 => 'four',
						5 => 'five',
						6 => 'six',
						7 => 'seven',
						8 => 'eight',
						9 => 'nine'
					);
					$quantity = $replacements[$quantity];
				}
				
				$output = str_replace('%d', $quantity, $output);
			}
			
			return $output;
		}
	}
	
	
	/**
	 * Returns the passed terms joined together using rule 2 from Strunk & White's 'The Elements of Style'
	 * 
	 * @param  array $terms  An array of terms to be join together
	 * @return string  The terms joined together
	 */
	static public function joinTerms($terms)
	{
		settype($terms, 'array');
		$terms = array_values($terms);
		
		switch (sizeof($terms)) {
			case 0:
				return '';
				break;
			
			case 1:
				return $terms[0];
				break;
			
			case 2:
				return $terms[0] . ' and ' . $terms[1];
				break;
				
			default:
				$last_term = array_pop($terms);
				return join(', ', $terms) . ', and ' . $last_term;
				break;
		}
	}
	
	
	/**
	 * Returns the plural version of the singular noun
	 * 
	 * @param  string $singular_noun  The singular noun to pluralize
	 * @return string  The pluralized noun
	 */
	static public function pluralize($singular_noun)
	{
		list ($beginning, $singular_noun) = self::splitLastWord($singular_noun);
		foreach (self::$singular_to_plural_rules as $from => $to) {
			if (preg_match('#' . $from . '#i', $singular_noun)) {
				return $beginning . preg_replace('#' . $from . '#i', $to, $singular_noun);
			}
		}
		fCore::toss('fProgrammerException', 'The noun specified could not be pluralized');
	}
	
	
	/**
	 * Returns the singular version of the plural noun
	 * 
	 * @param  string $plural_noun  The plural noun to singularize
	 * @return string  The singularized noun
	 */
	static public function singularize($plural_noun)
	{
		list ($beginning, $plural_noun) = self::splitLastWord($plural_noun);
		foreach (self::$plural_to_singular_rules as $from => $to) {
			if (preg_match('#' . $from . '#i', $plural_noun)) {
				return $beginning . preg_replace('#' . $from . '#i', $to, $plural_noun);
			}
		}
		fCore::toss('fProgrammerException', 'The noun specified could not be singularized');
	}
	
	
	/**
	 * Splits the last word off of a camel case or unscore notation string
	 * 
	 * @param  string $string  The string to split the word from
	 * @return array  The first element is the beginning part of the string, the second element is the last word
	 */
	static private function splitLastWord($string)
	{
		// Handle strings with spaces in them
		if (strpos($string, ' ') !== FALSE) {
			return array(substr($string, 0, strrpos($string, ' ')+1), substr($string, strrpos($string, ' ')+1));
		}
		
		// Handle underscore notation
		if ($string == self::underscorize($string)) {
			if (strpos($string, '_') === FALSE) { return array('', $string); }
			return array(substr($string, 0, strrpos($string, '_')+1), substr($string, strrpos($string, '_')+1));
		}
		
		// Handle camel case
		if (preg_match('#(.*)((?<=[a-zA-Z]|^)(?:[0-9]+|[A-Z][a-z]*)|(?<=[0-9A-Z]|^)(?:[A-Z][a-z]*))$#', $string, $match)) {
			return array($match[1], $match[2]);
		}
		
		return array('', $string);
	}
	
	
	/**
	 * Converts a camelCase string to underscore notation
	 * 
	 * @param  string $string  The string to convert
	 * @return string  The converted string
	 */
	static public function underscorize($string)
	{
		return strtolower(preg_replace('/(?:([a-z0-9A-Z])([A-Z])|([a-zA-Z])([0-9]))/', '\1\3_\2\4', $string));
	}
	
	
	/**
	 * Forces use as a static class
	 * 
	 * @return fInflection
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