<?php
/**
 * Parses PHP code for embedded phpdoc info
 * 
 * @copyright  Copyright (c) 2007 William Bond
 * @author     William Bond [wb] <will@flourishlib.com>
 * @license    http://flourishlib.com/license
 * 
 * @version  1.0.0
 * @changes  1.0.0    The initial implementation [wb, 2007-06-14]
 */
class fDocumentation
{

	/**
	 * Parses php code and returns an array of info
	 * 
	 * @since  1.0.0
	 * 
	 * @param  string $code  The code to parse
	 * @return array  Info about the code
	 */
	static public function getCodeInfo($code)
	{
		$code_tokens = token_get_all($code);
		$line = 1;
		$col  = 1;
		foreach ($code_tokens as &$token) {
			if (is_array($token)) {
				list ($token2, $text) = $token;
			} else if (is_string($token)) {
				$text = $token;
			}
			if (!is_string($token)) {
				$token[2] = $line;	
			}
			
			$num_lines = substr_count($text, "\n");
			if (1 <= $num_lines) {
				$line += $num_lines;
			}
		}
		
		$classes = array();
		$current_class = NULL;
		
		$functions = array();
		
		$abstract = FALSE;
		
		$static   = FALSE;
		$public     = FALSE;
		$private    = FALSE;
		$protected  = FALSE;
		
		$inside_parentheses = FALSE;
		
		$doc_block = '';
		
		$curly_stack = 0;
		
		for ($i=0; $i < sizeof($code_tokens); $i++) {
			
			if (!is_array($code_tokens[$i])) {
				$name = $code_tokens[$i];
			} else {
				$name = token_name($code_tokens[$i][0]);	
			}
			
			$temp = array();
			
			if (($curly_stack > 1 || $inside_parentheses) && !in_array($name, array('(', ')', '{', '}', 'T_CURLY_OPEN', 'T_DOLLAR_OPEN_CURLY_BRACES'))) {
				continue;
			}
			
			switch ($name) {
				case '{':
				case 'T_CURLY_OPEN':
				case 'T_DOLLAR_OPEN_CURLY_BRACES':
					$curly_stack++;
					break;
				
				case '}':
					$curly_stack--;
					if ($curly_stack == 0) {
						$current_class = NULL;	
					}
					break;
					
				case '(':
					$inside_parentheses = TRUE;
					break;
				
				case ')':
					$inside_parentheses = FALSE;
					break;
				
				case 'T_DOC_COMMENT':
					$doc_block = $code_tokens[$i][1];
					break;
				
				case 'T_ABSTRACT':
					$abstract = TRUE;
					break;
				
				case 'T_CLASS':
					$temp['name'] = $code_tokens[$i+2][1];
					$temp['line'] = $code_tokens[$i+2][2];
					if ($abstract) {
						$temp['abstract'] = $abstract;	
					}
					$temp['doc_block'] = $doc_block;
					
					if ($code_tokens[$i+4][0] == T_IMPLEMENTS) {
						$temp['implements'] = $code_tokens[$i+6][1];	
					}
					
					if ($code_tokens[$i+4][0] == T_EXTENDS) {
						$temp['extends'] = $code_tokens[$i+6][1];	
					}
					
					$temp['functions'] = array();
					$temp['variables'] = array();
					$temp['constants'] = array();
					
					$classes[$temp['name']] = $temp;
					$current_class = $temp['name'];
					
					$doc_block = '';
					$abstract = FALSE;
					break;
					
				case 'T_STATIC':
					$static = TRUE;
					break;
					
				case 'T_PUBLIC':
					$public = TRUE;
					break;
				
				case 'T_PRIVATE':
					$private = TRUE;
					break;
					
				case 'T_PROTECTED':
					$protected = TRUE;
					break;
				
				case 'T_VARIABLE':
					if ($curly_stack < 1 || $current_class == NULL) {
						continue;
					}
					$temp2 = array();
					$temp2['name'] = $code_tokens[$i][1];
					$temp2['line'] = $code_tokens[$i][2];
					
					if ($static) {
						$temp2['static'] = TRUE;	
					}
					if ($public) {
						$temp2['type'] = 'public';
					}	
					if ($private) {
						$temp2['type'] = 'private';
					}
					if ($protected) {
						$temp2['type'] = 'protected';
					}
					
					$temp2['doc_block'] = $doc_block;
					$classes[$current_class]['variables'][] = $temp2;
					$doc_block = '';
					$public = FALSE;
					$private = FALSE;
					$protected = FALSE;
					$static = FALSE;
					break;
				
				case 'T_CONST':
					if ($curly_stack < 1 || $current_class == NULL) {
						continue;
					}
					$temp2 = array();
					$temp2['name'] = $code_tokens[$i+2][1];
					$temp2['line'] = $code_tokens[$i+2][2];
					
					$temp2['doc_block'] = $doc_block;
					$classes[$current_class]['constants'][] = $temp2;
					$doc_block = '';
					break;
					
				case 'T_FUNCTION':
					$temp2 = array();
					$temp2['name'] = $code_tokens[$i+2][1];
					$temp2['line'] = $code_tokens[$i+2][2];

					if ($static) {
						$temp2['static'] = TRUE;	
					}
					if ($public) {
						$temp2['type'] = 'public';
					}	
					if ($private) {
						$temp2['type'] = 'private';
					}
					if ($protected) {
						$temp2['type'] = 'protected';
					}
					
					$temp2['doc_block'] = $doc_block;
					
					$parentheses_stack = 0;
					
					$parameters = array();
					$parameter = array();
					$after_equal = FALSE;
					
					for ($j=$i+3; $j<sizeof($code_tokens); $j++) {
						if (!is_array($code_tokens[$j])) {
							$name2 = $code_tokens[$j];
						} else {
							$name2 = token_name($code_tokens[$j][0]);	
						}	
						
						if ($name2 == '(') {
							$parentheses_stack++;
						} elseif ($name2 == ')') {
							$parentheses_stack--;
							if ($parentheses_stack == 0) {
								$parameters[] = $parameter;
								break;	
							}
						}
						
						if ($name2 == 'T_STRING' && !$after_equal) {
							$parameter['type'] = $code_tokens[$j][1];
						} elseif ($name2 == 'T_VARIABLE' && !$after_equal) {
							$parameter['name'] = $code_tokens[$j][1]; 
						} elseif ($name2 == '=') {
							$after_equal = TRUE;
						} elseif ($name2 ==',' && $parentheses_stack == 1) {
							$parameters[] = $parameter;
							$paramter = array();
						} elseif ($after_equal) {
							if (!isset($parameter['default'])) {
								$parameter['default'] = '';	
							}
							$parameter['default'] .= (isset($code_tokens[$j][1])) ? $code_tokens[$j][1] : $code_tokens[$j][0];
						}	
					}
					$temp2['parameters'] = $parameters;
					
					if ($current_class) {
						$classes[$current_class]['functions'][] = $temp2;
					} else {
						$functions[] = $temp2;	
					}
					
					$doc_block = '';
					$public = FALSE;
					$private = FALSE;
					$protected = FALSE;
					$static = FALSE;
					break;
					
				case 'T_INTERFACE':
					$doc_comment = $code_tokens[$i][1];
					break;
				
				
			}		
		}
		
		return array('classes' => $classes, 'functions' => $functions);	
	}

	
	/**
	 * Parses a php docblock pullout out the description and other elements
	 * 
	 * @since  1.0.0
	 * 
	 * @param  string $docblock  The docblock to parse
	 * @return array  Info about the code
	 */
	static public function parseDocBlock($docblock)
	{
		
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