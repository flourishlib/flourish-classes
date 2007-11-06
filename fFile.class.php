<?php
/**
 * Provides file-related methods
 * 
 * @copyright  Copyright (c) 2007 William Bond
 * @author     William Bond [wb] <will@flourishlib.com>
 * @license    http://flourishlib.com/license
 * 
 * @version  1.0.0 
 * @changes  1.0.0    The initial implementation [wb, 2007-06-14]
 */
class fFile
{
	/**
	 * If an exception was caused while uploading the file, this is it
	 * 
	 * @var object 
	 */
	private $exception;
	
	/**
	 * The full path to the file
	 * 
	 * @var string 
	 */
	private $file;
	
	/**
	 * The temporary directory to use for storing files
	 * 
	 * @var string 
	 */
	const TEMP_DIR = '__temp/';
	
	
	/**
	 * Takes a $_FILES array entry and parses it into private variables
	 * 
	 * @since  1.0.0
	 * 
	 * @param  string $file       The full path to the file
	 * @param  object $exception  An exception that was creating during the object creation process
	 * @return fFile
	 */
	public function __construct($file, Exception $exception=NULL)
	{
		if ($exception) {
			$this->exception;
			return;
		}    
		try {
			if (!file_exists($file)) {
				fCore::toss('fEnvironmentException', 'The file specified does not exist');   
			}
			$this->file = $file;
		} catch (Exception $e) {
			$this->exception = $e;   
		}
	}
	
	
	/**
	 * Gets the file's current path
	 * 
	 * @since  1.0.0
	 * 
	 * @param  boolean $from_doc_root  If the path should be returned relative to the document root
	 * @return void
	 */
	public function getPath($from_doc_root=FALSE)
	{
		if ($this->exception) { throw $this->exception; }
		
		if ($from_doc_root) {
			return str_replace($_SERVER['DOCUMENT_ROOT'], '', $this->file);    
		}
		return $this->file;    
	}
	
	
	/**
	 * Moves the file to the temp directory if it is not there already
	 * 
	 * @since  1.0.0
	 * 
	 * @return void
	 */
	public function moveToTemp()
	{
		if ($this->exception) { throw $this->exception; }
		
		$file_info = self::getInfo($this->file);
		if (!self::isTempDir($file_info['dirname'])) {
			$this->makeTempDir($file_info['dirname']);
			$new_file = $file_info['dirname'] . self::TEMP_DIR . $file_info['basename'];
			rename($this->file, $new_file);
			$this->file = $new_file;
		}    
	}
	
	
	/**
	 * Moves the file from the temp directory if it is not in the main directory already
	 * 
	 * @since  1.0.0
	 * 
	 * @return void
	 */
	public function moveFromTemp()
	{
		if ($this->exception) { throw $this->exception; }
		
		$file_info = self::getInfo($this->file);
		if (self::isTempDir($file_info['dirname'])) {
			$new_file = preg_replace('#' . self::TEMP_DIR . '$#', '', $file_info['dirname']) . $file_info['basename'];
			rename($this->file, $new_file);
			$this->file = $new_file;
		}    
	}
	
	
	/**
	 * Creates a new file object with a copy of the file in the new directory, will overwrite existing file
	 * 
	 * @since  1.0.0
	 * 
	 * @param  string $new_directory  The directory to duplicate the file into
	 * @return fFile  The new fFile object
	 */
	public function duplicate($new_directory)
	{
		if (substr($new_directory, -1) != '/' && substr($new_directory, -1) != '\\') {
			$new_directory .= '/';
		}
		
		if (!file_exists($new_directory)) {
			fCore::toss('fProgrammerException', 'The directory specified does not exist');   
		}
		if (!is_dir($new_directory)) {
			fCore::toss('fProgrammerException', 'The directory specified is not a directory');
		}
		if (!is_writable($new_directory)) {
			fCore::toss('fProgrammerException', 'The directory specified is not writable');
		}
		
		if (!$this->isTempDir($new_directory)) {
			$this->makeTempDir($new_directory);
		}       
		
		$file_info = self::getInfo($this->file);
		if (self::isTempDir($file_info['dir_name']) && !self::isTempDir($new_directory)) {
			$new_directory .= self::TEMP_DIR;	
		}
		@copy($this->file, $new_directory . $file_info['basename']);
		return new fFile($new_directory . $file_info['basename']);
	}
	
	
	/**
	 * Check to see if the current file is in the temp dir
	 * 
	 * @since  1.0.0
	 * 
	 * @param  string $dir  The directory to check for temp status 
	 * @return boolean  If the file is in the temp dir
	 */
	static private function isTempDir($dir)
	{
		return preg_match('#' . self::TEMP_DIR . '$#', $dir);    
	}
	
	
	/**
	 * Creates a temp dir for the directory specified
	 * 
	 * @since  1.0.0
	 * 
	 * @param  string $directory  The directory
	 * @return boolean  If the file is in the temp dir
	 */
	static private function makeTempDir($directory)
	{
		if (!file_exists($directory . self::TEMP_DIR)) {
			$old_umask = umask(0000);
			mkdir($directory . self::TEMP_DIR);
			umask($old_umask);
		}     
	}
	
	
	/**
	 * Returns a unique name for a file 
	 * 
	 * @since  1.0.0
	 * 
	 * @param  string $file   The filename to check
	 * @return string  The unique file name
	 */
	static public function createUniqueName($file) 
	{
		while (file_exists($file)) {
			$info = self::getFileInfo($file);
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
	 * @since  1.0.0
	 * 
	 * @param  string $file_path   The file to rename
	 * @param  string $element     The piece of information to return ('dirname', 'basename', 'extension', or 'filename')
	 * @return array  The file's dirname, basename, extension and filename
	 */
	static public function getInfo($file, $element=NULL) 
	{
		if ($element !== NULL && !in_array($element, array('dirname', 'basename', 'extension', 'filename'))) {
			fCore::toss('fProgrammerException', 'Invalid element requested');  
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
	 * Takes the size of a file in bytes and returns the  
	 * 
	 * @since 1.0.0
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
		return number_format($bytes/$sizes[$suffix], $decimal_places) . $names[$suffix];
	}
	
	
	/**
	 * Takes a file size and converts it to bytes 
	 * 
	 * @since 1.0.0
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