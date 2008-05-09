<?php
/**
 * Provides HTML-related methods
 * 
 * @copyright  Copyright (c) 2007-2008 William Bond
 * @author     William Bond [wb] <will@flourishlib.com>
 * @license    http://flourishlib.com/license
 * 
 * @link  http://flourishlib.com/fHTML
 * 
 * @version  1.0.0
 * @changes  1.0.0    The initial implementation [wb, 2007-09-25]
 */
class fHTML
{	
	/**
	 * Checks a string of HTML for block level elements 
	 * 
	 * @param  string $content   The HTML content to check
	 * @return boolean  If the content contains a block level tag
	 */
	static public function checkForBlockLevelHTML($content)
	{
		static $inline_tags = '<a><abbr><acronym><b><big><br><button><cite><code><del><dfn><em><font><i><img><input><ins><kbd><label><q><s><samp><select><small><span><strike><strong><sub><sup><textarea><tt><u><var>';
		return strip_tags($content, $inline_tags) != $content;
	}  
	
	
	/**
	 * Converts alphabetical entities to their numeric counterparts 
	 * 
	 * @internal
	 * 
	 * @param  string $content   The content to convert
	 * @return string  The converted content
	 */
	static public function convertEntitiesToNumeric($content)
	{
		static $entity_map = array(
			'&Aacute;'   => '&#193;',    '&aacute;'   => '&#225;',    '&Acirc;'    => '&#194;',    
			'&acirc;'    => '&#226;',    '&acute;'    => '&#180;',    '&AElig;'    => '&#198;',    
			'&aelig;'    => '&#230;',    '&Agrave;'   => '&#192;',    '&agrave;'   => '&#224;',    
			'&alefsym;'  => '&#8501;',   '&Alpha;'    => '&#913;',    '&alpha;'    => '&#945;',    
			'&and;'      => '&#8743;',   '&ang;'      => '&#8736;',   '&Aring;'    => '&#197;',    
			'&aring;'    => '&#229;',    '&asymp;'    => '&#8776;',   '&Atilde;'   => '&#195;',    
			'&atilde;'   => '&#227;',    '&Auml;'     => '&#196;',    '&auml;'     => '&#228;',    
			'&bdquo;'    => '&#8222;',   '&Beta;'     => '&#914;',    '&beta;'     => '&#946;',    
			'&brvbar;'   => '&#166;',    '&bull;'     => '&#8226;',   '&cap;'      => '&#8745;',   
			'&Ccedil;'   => '&#199;',    '&ccedil;'   => '&#231;',    '&cedil;'    => '&#184;',    
			'&cent;'     => '&#162;',    '&Chi;'      => '&#935;',    '&chi;'      => '&#967;',    
			'&circ;'     => '&#94;',     '&clubs;'    => '&#9827;',   '&cong;'     => '&#8773;',   
			'&copy;'     => '&#169;',    '&crarr;'    => '&#8629;',   '&cup;'      => '&#8746;',   
			'&curren;'   => '&#164;',    '&dagger;'   => '&#8224;',   '&Dagger;'   => '&#8225;',   
			'&darr;'     => '&#8595;',   '&dArr;'     => '&#8659;',   '&deg;'      => '&#176;',    
			'&Delta;'    => '&#916;',    '&delta;'    => '&#948;',    '&diams;'    => '&#9830;',   
			'&divide;'   => '&#247;',    '&Eacute;'   => '&#201;',    '&eacute;'   => '&#233;',    
			'&Ecirc;'    => '&#202;',    '&ecirc;'    => '&#234;',    '&Egrave;'   => '&#200;',    
			'&egrave;'   => '&#232;',    '&empty;'    => '&#8709;',   '&emsp;'     => '&#8195;',   
			'&ensp;'     => '&#8194;',   '&Epsilon;'  => '&#917;',    '&epsilon;'  => '&#949;',    
			'&equiv;'    => '&#8801;',   '&Eta;'      => '&#919;',    '&eta;'      => '&#951;',    
			'&ETH;'      => '&#208;',    '&eth;'      => '&#240;',    '&Euml;'     => '&#203;',    
			'&euml;'     => '&#235;',    '&euro;'     => '&#8364;',   '&exist;'    => '&#8707;',   
			'&fnof;'     => '&#402;',    '&forall;'   => '&#8704;',   '&frac12;'   => '&#189;',    
			'&frac14;'   => '&#188;',    '&frac34;'   => '&#190;',    '&frasl;'    => '&#8260;',   
			'&Gamma;'    => '&#915;',    '&gamma;'    => '&#947;',    '&ge;'       => '&#8805;',   
			'&harr;'     => '&#8596;',   '&hArr;'     => '&#8660;',   '&hearts;'   => '&#9829;',   
			'&hellip;'   => '&#8230;',   '&Iacute;'   => '&#205;',    '&iacute;'   => '&#237;',    
			'&Icirc;'    => '&#206;',    '&icirc;'    => '&#238;',    '&iexcl;'    => '&#161;',    
			'&Igrave;'   => '&#204;',    '&igrave;'   => '&#236;',    '&image;'    => '&#8465;',   
			'&infin;'    => '&#8734;',   '&int;'      => '&#8747;',   '&Iota;'     => '&#921;',    
			'&iota;'     => '&#953;',    '&iquest;'   => '&#191;',    '&isin;'     => '&#8712;',   
			'&Iuml;'     => '&#207;',    '&iuml;'     => '&#239;',    '&Kappa;'    => '&#922;',    
			'&kappa;'    => '&#954;',    '&Lambda;'   => '&#923;',    '&lambda;'   => '&#955;',    
			'&lang;'     => '&#9001;',   '&laquo;'    => '&#171;',    '&larr;'     => '&#8592;',   
			'&lArr;'     => '&#8656;',   '&lceil;'    => '&#8968;',   '&ldquo;'    => '&#8220;',   
			'&le;'       => '&#8804;',   '&lfloor;'   => '&#8970;',   '&lowast;'   => '&#8727;',   
			'&loz;'      => '&#9674;',   '&lrm;'      => '&#8206;',   '&lsaquo;'   => '&#8249;',   
			'&lsquo;'    => '&#8216;',   '&macr;'     => '&#175;',    '&mdash;'    => '&#8212;',   
			'&micro;'    => '&#181;',    '&middot;'   => '&#183;',    '&minus;'    => '&#8722;',   
			'&Mu;'       => '&#924;',    '&mu;'       => '&#956;',    '&nabla;'    => '&#8711;',   
			'&nbsp;'     => '&#160;',    '&ndash;'    => '&#8211;',   '&ne;'       => '&#8800;',   
			'&ni;'       => '&#8715;',   '&not;'      => '&#172;',    '&notin;'    => '&#8713;',   
			'&nsub;'     => '&#8836;',   '&Ntilde;'   => '&#209;',    '&ntilde;'   => '&#241;',    
			'&Nu;'       => '&#925;',    '&nu;'       => '&#957;',    '&Oacute;'   => '&#211;',    
			'&oacute;'   => '&#243;',    '&Ocirc;'    => '&#212;',    '&ocirc;'    => '&#244;',    
			'&OElig;'    => '&#338;',    '&oelig;'    => '&#339;',    '&Ograve;'   => '&#210;',    
			'&ograve;'   => '&#242;',    '&oline;'    => '&#8254;',   '&Omega;'    => '&#937;',    
			'&omega;'    => '&#969;',    '&Omicron;'  => '&#927;',    '&omicron;'  => '&#959;',    
			'&oplus;'    => '&#8853;',   '&or;'       => '&#8744;',   '&ordf;'     => '&#170;',    
			'&ordm;'     => '&#186;',    '&Oslash;'   => '&#216;',    '&oslash;'   => '&#248;',    
			'&Otilde;'   => '&#213;',    '&otilde;'   => '&#245;',    '&otimes;'   => '&#8855;',   
			'&Ouml;'     => '&#214;',    '&ouml;'     => '&#246;',    '&para;'     => '&#182;',    
			'&part;'     => '&#8706;',   '&permil;'   => '&#8240;',   '&perp;'     => '&#8869;',   
			'&Phi;'      => '&#934;',    '&phi;'      => '&#966;',    '&Pi;'       => '&#928;',    
			'&pi;'       => '&#960;',    '&piv;'      => '&#982;',    '&plusmn;'   => '&#177;',    
			'&pound;'    => '&#163;',    '&prime;'    => '&#8242;',   '&Prime;'    => '&#8243;',   
			'&prod;'     => '&#8719;',   '&prop;'     => '&#8733;',   '&Psi;'      => '&#936;',    
			'&psi;'      => '&#968;',    '&radic;'    => '&#8730;',   '&rang;'     => '&#9002;',   
			'&raquo;'    => '&#187;',    '&rarr;'     => '&#8594;',   '&rArr;'     => '&#8658;',   
			'&rceil;'    => '&#8969;',   '&rdquo;'    => '&#8221;',   '&real;'     => '&#8476;',   
			'&reg;'      => '&#174;',    '&rfloor;'   => '&#8971;',   '&Rho;'      => '&#929;',    
			'&rho;'      => '&#961;',    '&rlm;'      => '&#8207;',   '&rsaquo;'   => '&#8250;',   
			'&rsquo;'    => '&#8217;',   '&sbquo;'    => '&#8218;',   '&Scaron;'   => '&#352;',    
			'&scaron;'   => '&#353;',    '&sdot;'     => '&#8901;',   '&sect;'     => '&#167;',    
			'&shy;'      => '&#173;',    '&Sigma;'    => '&#931;',    '&sigma;'    => '&#963;',    
			'&sigmaf;'   => '&#962;',    '&sim;'      => '&#8764;',   '&spades;'   => '&#9824;',   
			'&sub;'      => '&#8834;',   '&sube;'     => '&#8838;',   '&sum;'      => '&#8721;',   
			'&sup1;'     => '&#185;',    '&sup2;'     => '&#178;',    '&sup3;'     => '&#179;',    
			'&sup;'      => '&#8835;',   '&supe;'     => '&#8839;',   '&szlig;'    => '&#223;',    
			'&Tau;'      => '&#932;',    '&tau;'      => '&#964;',    '&there4;'   => '&#8756;',   
			'&Theta;'    => '&#920;',    '&theta;'    => '&#952;',    '&thetasym;' => '&#977;',    
			'&thinsp;'   => '&#8201;',   '&THORN;'    => '&#222;',    '&thorn;'    => '&#254;',    
			'&tilde;'    => '&#732;',    '&times;'    => '&#215;',    '&trade;'    => '&#8482;',   
			'&Uacute;'   => '&#218;',    '&uacute;'   => '&#250;',    '&uarr;'     => '&#8593;',   
			'&uArr;'     => '&#8657;',   '&Ucirc;'    => '&#219;',    '&ucirc;'    => '&#251;',    
			'&Ugrave;'   => '&#217;',    '&ugrave;'   => '&#249;',    '&uml;'      => '&#168;',    
			'&upsih;'    => '&#978;',    '&Upsilon;'  => '&#933;',    '&upsilon;'  => '&#965;',    
			'&Uuml;'     => '&#220;',    '&uuml;'     => '&#252;',    '&weierp;'   => '&#8472;',   
			'&Xi;'       => '&#926;',    '&xi;'       => '&#958;',    '&Yacute;'   => '&#221;',    
			'&yacute;'   => '&#253;',    '&yen;'      => '&#165;',    '&Yuml;'     => '&#376;',    
			'&yuml;'     => '&#255;',    '&Zeta;'     => '&#918;',    '&zeta;'     => '&#950;',    
			'&zwj;'      => '&#8205;',   '&zwnj;'     => '&#8204;'
		);
		
		return strtr($content, $entity_map);
	} 
	
	
	/**
	 * Prints text, turning newlines into breaks as long as there aren't any block-level html tags
	 * 
	 * @param  string $content  The content to display
	 * @return void
	 */
	static public function convertNewlines($content)
	{
		static $inline_tags_minus_br = '<a><abbr><acronym><b><big><button><cite><code><del><dfn><em><font><i><img><input><ins><kbd><label><q><s><samp><select><small><span><strike><strong><sub><sup><textarea><tt><u><var>';
		return (strip_tags($content, $inline_tags_minus_br) != $content) ? $content : nl2br($content);
	}
	
	
	/**
	 * Takes a block of text and converts all URLs into HTML links
	 * 
	 * @param  string $content            The content to parse for links
	 * @param  integer $link_text_length  If non-zero, all link text will be truncated to this many characters
	 * @return string  The content with all URLs converted to HTML link
	 */
	static public function createLinks($content, $link_text_length=0)
	{
		// Determine what replacement to perform
		if ($link_text_length) {
			$replacement = '((strlen("\1") > ' . $link_text_length . ') ? substr("\1", 0, ' . $link_text_length . ') . "..." : "\1")';	
		} else {
			$replacement = '"\1"';
		}
		
		
		// Handle fully qualified urls with protocol
		$full_url_regex       = '#\b([a-z]{3,}://[a-z0-9%\$\-_.+!*;/?:@=&\'\#,]+[a-z0-9\$\-_+!*;/?:@=&\'\#,])\b#ie';
		$full_url_replacement = '"<a href=\"\1\">" . ' . $replacement . ' . "</a>"';
		
		// Handle domains names that start with www
		$www_url_regex       = '#\b(www\.([a-z0-9\-]+\.)+[a-z]{2,}(?:/[a-z0-9%\$\-_.+!*;/?:@=&\'\#,]+[a-z0-9\$\-_+!*;/?:@=&\'\#,])?)\b#ie';
		$www_url_replacement = '"<a href=\"http://\1\">" . ' . $replacement . ' . "</a>"';
		
		// Handle email addresses
		$email_regex       = '#\b([a-z0-9\\.\'_\\-]+@(?:[a-z0-9\\-]+\.)+[a-z]{2,})\b#ie';
		$email_replacement = '"<a href=\"mailto:\1\">" . ' . $replacement . ' . "</a>"';
		
		$searches = array(
			$full_url_regex => $full_url_replacement,
			$www_url_regex  => $www_url_replacement,
			$email_regex    => $email_replacement	
		);
		
		
		// Loop through and do each kind of replacement, by doing a pass for each replacement, we prevent nested links
		foreach ($searches as $regex => $replacement) {
			
			// Find all a tags
			$reg_exp = "#<\s*a(?:\s+[\w:]+(?:\s*=\s*(?:\"[^\"]*?\"|'[^']*?'|[^'\">\s]+))?)*\s*>.*?<\s*/\s*a\s*>#";
			preg_match_all($reg_exp, $content, $a_tag_matches, PREG_SET_ORDER);

			// Find all text
			$text_matches = preg_split($reg_exp, $content);
			
			// For each chunk of text, convert all URLs to links
			foreach($text_matches as $key => $text) {
				$text = preg_replace($regex, $replacement, $text);
				$text_matches[$key] = str_replace("\\'", "'", $text);
			}

			// Merge the text and a tags back together  
			for ($i = 0; $i < sizeof($a_tag_matches); $i++) {
				$text_matches[$i] .= $a_tag_matches[$i][0];
			}

			$content = implode($text_matches);  
		}
		
		return $content;
	}
	
	
	/**
	 * Converts all html entities to normal characters, using utf-8
	 * 
	 * @param  string $content   The content to decode
	 * @return string  The decoded content
	 */
	static public function decodeEntities($content)
	{
		return html_entity_decode($content, ENT_COMPAT, 'UTF-8');
	}
	
	
	/**
	 * Converts all special characters to entites, using utf-8. Handles Windows-specific characters.
	 * 
	 * @param  string $content   The content to encode
	 * @return string  The encoded content
	 */
	static public function encodeEntities($content)
	{
		$content = htmlentities($content, ENT_COMPAT, 'UTF-8');    
		
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
		
		return strtr($content, $windows_characters);      
	}
	
	
	/**
	 * Prepares content for display in HTML, allows HTML tags. Converts all special characters that are not part of html tags or existing entites to entities 
	 * 
	 * @param  string $content   The content to prepare
	 * @return string  The encoded html
	 */
	static public function prepare($content)
	{
		// Find all html tags, entities and comments
		$reg_exp = "/<\s*\/?\s*[\w:]+(?:\s+[\w:]+(?:\s*=\s*(?:\"[^\"]*?\"|'[^']*?'|[^'\">\s]+))?)*\s*\/?\s*>|&(?:#\d+|\w+);|<\!--.*?-->/";
		preg_match_all($reg_exp, $content, $html_matches, PREG_SET_ORDER);

		// Find all text
		$text_matches = preg_split($reg_exp, $content);

		// For each chunk of text, make sure it is converted to entities
		foreach($text_matches as $key => $value) {
			$text_matches[$key] = self::encodeEntities($value);
		}

		// Merge the text and html back together  
		for ($i = 0; $i < sizeof($html_matches); $i++) {
			$text_matches[$i] .= $html_matches[$i][0];
		}

		return implode($text_matches);         
	}
	
	
	/**
	 * Prepares content for display in an HTML form element
	 * 
	 * @param  string $content   The content to prepare
	 * @return string  The encoded html
	 */
	static public function prepareFormValue($content)
	{
		return self::encodeEntities($content);        
	}
	
	
	/**
	 * Prepares content for display in HTML, does not allow HTML tags. Converts all special characters that are not already entites to entities 
	 * 
	 * @param  string $content   The content to prepare
	 * @return string  The encoded html
	 */
	static public function preparePlainText($content)
	{
		// Remove existing entities to prevent double-encoding
		$content = self::decodeEntities($content);
		
		return self::encodeEntities($content);        
	}
	
	
	/**
	 * Converts accented characters to their non-accented counterparts 
	 * 
	 * @internal
	 * 
	 * @param  string $content   The content to convert
	 * @return string  The converted content
	 */
	static public function unaccent($content)
	{
		static $character_map = array (
			'&#192;' => 'A', '&#193;' => 'A', '&#194;' => 'A', '&#195;' => 'A', '&#196;' => 'A', 
			'&#197;' => 'A', '&#199;' => 'C', '&#200;' => 'E', '&#201;' => 'E', '&#202;' => 'E', 
			'&#203;' => 'E', '&#204;' => 'I', '&#205;' => 'I', '&#206;' => 'I', '&#207;' => 'I', 
			'&#208;' => 'D', '&#209;' => 'N', '&#210;' => 'O', '&#211;' => 'O', '&#212;' => 'O', 
			'&#213;' => 'O', '&#214;' => 'O', '&#216;' => 'O', '&#217;' => 'U', '&#218;' => 'U', 
			'&#219;' => 'U', '&#220;' => 'U', '&#221;' => 'Y', '&#224;' => 'a', '&#225;' => 'a', 
			'&#226;' => 'a', '&#227;' => 'a', '&#228;' => 'a', '&#229;' => 'a', '&#231;' => 'c', 
			'&#232;' => 'e', '&#233;' => 'e', '&#234;' => 'e', '&#235;' => 'e', '&#236;' => 'i', 
			'&#237;' => 'i', '&#238;' => 'i', '&#239;' => 'i', '&#241;' => 'n', '&#242;' => 'o', 
			'&#243;' => 'o', '&#244;' => 'o', '&#245;' => 'o', '&#246;' => 'o', '&#248;' => 'o', 
			'&#249;' => 'u', '&#250;' => 'u', '&#251;' => 'u', '&#252;' => 'u', '&#253;' => 'y', 
			'&#255;' => 'y'    
		);
		
		// Handle any existing entities
		$content = self::convertEntitiesToNumeric($content);
		$content = strtr($content, $character_map);
		
		// Make entities out of extended characters and revert the rest back to how it was
		$content = self::encodeEntities($content);
		$content = self::convertEntitiesToNumeric($content);
		$content = strtr($content, $character_map);
		return self::decodeEntities($content); 
	}   
	
	
	/**
	 * Forces use as a static class
	 * 
	 * @return fHTML
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