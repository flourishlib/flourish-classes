<?php
/**
 * Handles filesystem-level tasks
 * 
 * @copyright  Copyright (c) 2008 William Bond
 * @author     William Bond [wb] <will@flourishlib.com>
 * @license    http://flourishlib.com/license
 * 
 * @link  http://flourishlib.com/fFilesystem
 * 
 * @uses  fCore
 * @uses  fDirectory
 * @uses  fEnvironmentException
 * @uses  fProgrammerException
 * 
 * @version  1.0.0 
 * @changes  1.0.0    The initial implementation [wb, 2008-03-24]
 */
class fFilesystem
{
	/**
	 * Stores the pending transaction for this filesystem transaction
	 * 
	 * @var array 
	 */
	static private $pending_operations = NULL;
	
	
	/**
	 * Prevent instantiation
	 * 
	 * @return fFilesystem
	 */
	private function __construct() { }
	
	
	/**
	 * Starts a filesystem transaction, should only be called when no transaction is in progress
	 * 
	 * @return void
	 */
	static public function startTransaction()
	{
		if (self::$pending_operations !== NULL) {
			fCore::toss('fProgrammerException', 'There is already a filesystem transaction in progress');
		}
		self::$pending_operations = array();
	}
	
	
	/**
	 * Rolls back a filesystem transaction, it is safe to rollback when no transaction is in progress
	 * 
	 * @return void
	 */
	static public function rollbackTransaction()
	{
		self::$pending_operations = NULL;
	}
	
	
	/**
	 * Commits a filesystem transaction, should only be called when a transaction is in progress
	 * 
	 * @return void
	 */
	static public function commitTransaction()
	{
		if (self::$pending_operations === NULL) {
			fCore::toss('fProgrammerException', 'There is no filesystem transaction in progress to commit');
		}
		self::performOperations(self::$pending_operations);
	}
	
	
	/**
	 * Returns a unique name for a file 
	 * 
	 * @param  string $file           The filename to check
	 * @param  string $new_extension  The new extension for the filename, do not include .
	 * @return string  The unique file name
	 */
	static public function createUniqueName($file, $new_extension=NULL) 
	{
		$info = self::getInfo($file);
		
		// Change the file extension
		if ($new_extension !== NULL) {
			$new_extension = ($new_extension) ? '.' . $new_extension : $new_extension;
			$file = $info['dirname'] . $info['filename'] . $new_extension;
			$info = self::getInfo($file);
		}
		
		// Remove _copy# from the filename to start
		$file = preg_replace('#_copy(\d+)\.' . preg_quote($info['extension']) . '$#', '.' . $info['extension'], $file); 	
		
		// Look for a unique name by adding _copy# to the end of the file
		while (file_exists($file)) {
			$info = self::getInfo($file);
			if (preg_match('#_copy(\d+)\.' . preg_quote($info['extension']) . '$#', $file, $match)) {
				$file = preg_replace('#_copy(\d+)\.' . preg_quote($info['extension']) . '$#', '_copy' . ($match[1]+1) . '.' . $info['extension'], $file);
			} else {
				$file = $info['dirname'] . $info['filename'] . '_copy1.' . $info['extension'];    
			}    
		}
		
		return $file;
	}
	
	
	/**
	 * Returns info about a file including dirname, basename, extension and filename 
	 * 
	 * @param  string $file_path   The file to rename
	 * @param  string $element     The piece of information to return ('dirname', 'basename', 'extension', or 'filename')
	 * @return array  The file's dirname, basename, extension and filename
	 */
	static public function getInfo($file, $element=NULL) 
	{
		$valid_elements = array('dirname', 'basename', 'extension', 'filename');
		if ($element !== NULL && !in_array($element, $valid_elements)) {
			fCore::toss('fProgrammerException', 'Invalid element, ' . $element . ', requested. Must be one of: ' . join(', ', $valid_elements) . '.');  
		}
		
		$path_info = pathinfo($file);
		if (!isset($path_info['filename'])) {
			$path_info['filename'] = preg_replace('#\.' . preg_quote($path_info['extension']) . '$#', '', $path_info['basename']);   
		}
		$path_info['dirname'] .= '/';
		
		if ($element) {
			return $path_info[$element];   
		}
		
		return $path_info;
	} 
	
	
	/**
	 * Takes the size of a file in bytes and returns a friendly size in b/kb/mb/gb/tb 
	 * 
	 * @param  integer $bytes           The size of the file in bytes
	 * @param  integer $decimal_places  The number of decimal places to display
	 * @return string  
	 */
	static public function formatFilesize($bytes, $decimal_places=1) 
	{
		if ($bytes < 0) {
			$bytes = 0;        
		}
		$suffixes  = array('b', 'kb', 'mb', 'gb', 'tb');
		$sizes     = array(1, 1024, 1048576, 1073741824, 1099511627776);
		$suffix    = floor(log($bytes)/6.9314718);
		return number_format($bytes/$sizes[$suffix], $decimal_places) . $suffixes[$suffix];
	}
	
	
	/**
	 * Takes a file size and converts it to bytes 
	 * 
	 * @param  string $size  The size to convert to bytes
	 * @return integer  The number of bytes represented by the size  
	 */
	static public function convertToBytes($size) 
	{
		if (!preg_match('#^(\d+)\s*(k|m|g|t)?b?$#', strtolower(trim($size)), $matches)) {
			fCore::toss('fProgrammerException', 'The size specified does not appears to be a valid size');   
		}
		
		if ($matches[1] == '') {
			$matches[1] = 'b';   
		}
		
		$size_map = array('b' => 1,
						  'k' => 1024,
						  'm' => 1048576,
						  'g' => 1073741824,
						  't' => 1099511627776);
		return $matches[0] * $size_map[$matches[1]];
	}
}  


/**
 * Copyright (c) 2008 William Bond <will@flourishlib.com>
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