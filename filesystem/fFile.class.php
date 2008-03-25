<?php
/**
 * Represents a file on the filesystem, also provides static file-related methods
 * 
 * @copyright  Copyright (c) 2007-2008 William Bond
 * @author     William Bond [wb] <will@flourishlib.com>
 * @license    http://flourishlib.com/license
 * 
 * @link  http://flourishlib.com/fFile
 * 
 * @uses  fCore
 * @uses  fDirectory
 * @uses  fEnvironmentException
 * @uses  fFilesystem
 * @uses  fProgrammerException
 * 
 * @version  1.0.0 
 * @changes  1.0.0    The initial implementation [wb, 2007-06-14]
 */
class fFile
{
	/**
	 * If an exception was caused while uploading the file or creating the object, this is it
	 * 
	 * @var object 
	 */
	protected $exception;
	
	/**
	 * The full path to the file
	 * 
	 * @var string 
	 */
	protected $file;
	
	
	/**
	 * Creates an object to represent a file on the filesystem
	 * 
	 * @param  string $file       The full path to the file
	 * @param  object $exception  An exception that was tossed during the object creation process
	 * @return fFile
	 */
	public function __construct($file, Exception $exception=NULL)
	{
		if ($exception) {
			$this->exception = $exception;
			return;
		}    
		try {
			if (!file_exists($file)) {
				fCore::toss('fEnvironmentException', 'The file specified does not exist');   
			}
			if (!is_readable($file)) {
				fCore::toss('fEnvironmentException', 'The file specified is not readable');   
			}
			$this->file = $file;
		} catch (Exception $e) {
			$this->exception = $e;   
		}
	}
	
	
	/**
	 * When used in a string context, represents the file as the filename
	 * 
	 * @return string  The filename of the file
	 */
	public function __toString()
	{
		return $this->getFilename();
	}
	
	
	/**
	 * Gets the filename (i.e. does not include the directory)
	 * 
	 * @return string  The filename of the file
	 */
	public function getFilename()
	{
		if ($this->exception) { throw $this->exception; }
		// For some reason PHP calls the filename the basename, where filename is the filename minus the extension
		return fFilesystem::getInfo($this->file, 'basename');    
	}
	
	
	/**
	 * Gets the directory the file is located in
	 * 
	 * @return fDirectory  The directory containing the file
	 */
	public function getDirectory()
	{
		if ($this->exception) { throw $this->exception; }
		return new fDirectory(fFilesystem::getInfo($this->file, 'dirname'));    
	}
	
	
	/**
	 * Gets the file's current path (directory and filename)
	 * 
	 * @param  boolean $from_doc_root  If the path should be returned relative to the document root
	 * @return string  The path (directory and filename) for the file
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
	 * Gets the size of the file. May be incorrect for files over 2GB on certain operating systems.
	 * 
	 * @param  boolean $format          If the filesize should be formatted for human readability
	 * @param  integer $decimal_places  The number of decimal places to format to (if enabled)
	 * @return integer|string  If formatted a string with filesize in b/kb/mb/gb/tb, otherwise an integer
	 */
	public function getFilesize($format=FALSE, $decimal_places=1)
	{
		// This technique can overcome signed integer limit
		$size = sprintf("%u", filesize($this->file));    
		
		if (!$format) {
			return $size;	
		}
		
		return fFilesystem::formatFilesize($size, $decimal_places);
	}
	
	
	/**
	 * Moves the file to the temp directory if it is not there already
	 * 
	 * @return void
	 */
	public function moveToTemp()
	{
		if ($this->exception) { throw $this->exception; }
		
		$file_info = fFilesystem::getInfo($this->file);
		$directory = $this->getDirectory();
		if (!$directory->isTemp()) {
			$temp_dir = $directory->getTemp();
			$new_file = $temp_dir->getPath() . $this->getFilename();
			rename($this->file, $new_file);
			$this->file = $new_file;
		}    
	}
	
	
	/**
	 * Moves the file from the temp directory if it is not in the main directory already
	 * 
	 * @return void
	 */
	public function moveFromTemp()
	{
		if ($this->exception) { throw $this->exception; }
		
		$directory = $this->getDirectory();
		if ($directory->isTemp()) {
			$new_file = $directory->getParent() . $this->getFilename();
			rename($this->file, $new_file);
			$this->file = $new_file;
		}    
	}
	
	
	/**
	 * Creates a new file object with a copy of the file in the new directory, will overwrite an existing file of the same name. Will also put the file into the temp dir if it is currently in a temp dir.
	 * 
	 * @param  string|fDirectory $new_directory  The directory to duplicate the file into
	 * @return fFile  The new fFile object
	 */
	public function duplicate($new_directory)
	{
		if ($this->exception) { throw $this->exception; }
		
		if (!is_object($new_directory)) {
			$new_directory = fDirectory($new_directory);
		}   
		
		if (!$new_directory->isWritable()) {
			fCore::toss('fProgrammerException', 'The directory specified is not writable');
		}
		
		if ($this->getDirectory()->isTemp()) {
			$new_directory = $new_directory->getTemp();
		}       
		
		@copy($this->getPath(), $new_directory->getPath() . $this->getFilename());
		return new fFile($new_directory->getPath() . $this->getFilename());
	}
	
	
	/**
	 * Check to see if the current file is writable
	 * 
	 * @return boolean  If the file is writable
	 */
	public function isWritable()
	{
		if ($this->exception) { throw $this->exception; }
		return is_writable($this->file);   
	}
	
	
	/**
	 * Deletes the current file
	 * 
	 * @return void
	 */
	public function delete() 
	{
		if ($this->exception) { throw $this->exception; }
		
		$dir = $this->getDirectory();
		
		if (!$dir->isWritable()) {
			fCore::toss('fProgrammerException', 'The file can not be deleted because the directory containing it is not writable');
		} 
		
		@unlink($this->file);
		$this->file = NULL;
		
		try {
			fCore::toss('fProgrammerException', 'The action requested can not be performed because the file has been deleted');   
		} catch (fPrintableException $e) {
			$this->exception = $e;
		}
	}
	
	
	/**
	 * Writes the provided data to the file
	 * 
	 * @param  mixed $data  The data to write to the file
	 * @return void
	 */
	public function write($data) 
	{
		if (!$this->isWritable()) {
			fCore::toss('fProgrammerException', 'This file can not be written to because it is not writable');
		} 
		
		file_put_contents($this->file, $data);
	}
	
	
	/**
	 * Reads the data from the file
	 * 
	 * @param  mixed $data  The data to write to the file
	 * @return string  The contents of the file
	 */
	public function read() 
	{
		return file_get_contents($this->file);
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