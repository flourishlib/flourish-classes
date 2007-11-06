<?php
/**
 * Provides english words pluralization, singularization, camelCase, undercore_notation, etc
 * 
 * @copyright  Copyright (c) 2007 William Bond
 * @author     William Bond [wb] <will@flourishlib.com>
 * @license    http://flourishlib.com/license
 * 
 * @version  1.0.0 
 * @changes  1.0.0    The initial implementation [wb, 2007-09-25]
 */
class fInflection
{
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
	 * Prevent instantiation
	 * 
	 * @since  1.0.0
	 * 
	 * @return fInflection
	 */
	private function __construct() { }
	
    
    /**
     * Returns the plural version of the singular noun
     * 
     * @since  1.0.0
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
     * @since  1.0.0
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
	 * @since  1.0.0
	 * 
	 * @param  string $string  The string to split the word from
	 * @return array  The first element is the beginning part of the string, the second element is the last word
	 */
	static private function splitLastWord($string)
	{
		// Handle strings with spaces in them
		if (strpos($string, ' ') !== FALSE) {
			return array(substr($string, 0, strrpos($string, ' ')), substr($string, strrpos($string, ' ')+1));	
		}
		
		// Handle underscore notation
		if ($string == self::underscorize($string)) {
			if (strpos($string, '_') === FALSE) { return array('', $string); }
			return array(substr($string, 0, strrpos($string, '_')), substr($string, strrpos($string, '_')+1));	
		}
		
		// Handle camel case
		if (preg_match('#(.*)((?<=[a-zA-Z]|^)(?:[0-9]+|[A-Z][a-z]*)|(?<=[0-9A-Z]|^)(?:[A-Z][a-z]*))$#', $string, $match)) {
			return array($match[1], $match[2]);	
		}
		
		return array('', $string); 
	}
	
	
	/**
	 * Adds a custom singular->plural and plural->singular rule
	 * 
	 * @since  1.0.0
	 * 
	 * @param  string $singular  The singular version of the noun
	 * @param  string $plural    The plural version of the noun
	 * @return void
	 */
    static public function addCustomRule($singular, $plural)
	{
		self::$singular_to_plural_rules = array_merge(array('^(' . $singular[0] . ')' . substr($singular, 1) . '$' => '\1' . substr($plural, 1)),
													  self::$singular_to_plural_rules);
		self::$plural_to_singular_rules = array_merge(array('^(' . $plural[0] . ')' . substr($plural, 1) . '$' => '\1' . substr($singular, 1)),
													  self::$plural_to_singular_rules);
	}  
    
    
    /**
     * Converts a camelCase string to underscore notation
     * 
     * @since  1.0.0
     * 
     * @param  string $string   The string to convert
     * @return string  The converted string
     */
	static public function underscorize($string)
	{
		return strtolower(preg_replace('/(?:([a-z0-9A-Z])([A-Z])|([a-zA-Z])([0-9]))/', '\1\3_\2\4', $string));
    }
    
    
    /**
     * Converts an underscore notation string to camelCase
     * 
     * @since  1.0.0
     * 
	 * @param  string $string   The string to convert          
     * @param  boolean $upper   If the camel case should be upper camel case
     * @return string  The converted string
     */
    static public function camelize($string, $upper)
    {
        $string = strtolower($string);
        if ($upper) {
            $string = strtoupper($string[0]) . substr($string, 1);    
        }
        return preg_replace('/(_([a-z0-9]))/e', 'strtoupper("\2")', $string);
    } 
    
    
	/**
	 * Makes an underscore notation string into a human-friendly string
	 * 
     * @since  1.0.0
	 * 
     * @param  string $string   The string to humanize
     * @return string  The converted string
	 */
	static public function humanize($string)
    {
		return preg_replace('/(\bid\b|\burl\b|\b\w)/e', 'strtoupper("\1")', str_replace('_', ' ', $string));
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
