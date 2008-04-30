<?php
/**
 * Represents a directory on the filesystem, also provides static directory-related methods
 * 
 * @copyright  Copyright (c) 2007-2008 William Bond
 * @author     William Bond [wb] <will@flourishlib.com>
 * @license    http://flourishlib.com/license
 * 
 * @link  http://flourishlib.com/fDirectory
 * 
 * @uses  fCore
 * @uses  fEnvironmentException
 * @uses  fFile
 * @uses  fFilesystem
 * @uses  fProgrammerException
 * 
 * @version  1.0.0 
 * @changes  1.0.0    The initial implementation [wb, 2007-12-21]
 */
class fDirectory
{
	/**
	 * The full path to the directory
	 * 
	 * @var string 
	 */
	protected $directory;
	
	/**
	 * An exception to be thrown after a deletion has happened
	 * 
	 * @var object 
	 */
	protected $exception;
	
	/**
	 * The temporary directory to use for various tasks
	 * 
	 * @var string 
	 */
	const TEMP_DIRECTORY = '__temp/';
	
	
	/**
	 * Creates an object to represent a directory on the filesystem
	 * 
	 * @param  string $directory  The full path to the directory
	 * @return fDirectory
	 */
	public function __construct($directory)
	{
		if (empty($directory)) {
			fCore::toss('fProgrammerException', 'No directory was specified');	
		}
		
		if (!file_exists($directory)) {
			fCore::toss('fEnvironmentException', 'The directory specified, ' . $directory . ', does not exist');   
		}
		if (!is_dir($directory)) {
			fCore::toss('fEnvironmentException', 'The path specified, ' . $directory . ', is not a directory');   
		}
		
		$directory = self::makeCanonical(realpath($directory));
		
		$this->directory =& fFilesystem::hookFilenameMap($directory);
		$this->exception =& fFilesystem::hookExceptionMap($directory);
		
		
	}
	
	
	/**
	 * Makes sure a directory has a / or \ at the end
	 * 
	 * @param  string $directory  The directory to check
	 * @return string  The directory name in canonical form
	 */
	static public function makeCanonical($directory)
	{
		if (substr($directory, -1) != '/' && substr($directory, -1) != '\\') {
			$directory .= DIRECTORY_SEPARATOR;   
		}
		return $directory;
	}
	
	
	/**
	 * When used in a string context, represents the file as the filename
	 * 
	 * @return string  The filename of the file
	 */
	public function __toString()
	{
		return $this->getPath();
	}
	
	
	/**
	 * Throws the directory exception if exists
	 * 
	 * @return void
	 */
	protected function tossIfException()
	{
		if ($this->exception) {
			fCore::toss(get_class($this->exception), $this->exception->getMessage());
		}
	}
	
	
	/**
	 * Gets the directory's current path
	 * 
	 * @param  boolean $from_doc_root  If the path should be returned relative to the document root
	 * @return string  The path for the directory
	 */
	public function getPath($from_doc_root=FALSE)
	{
		$this->tossIfException();
		
		if ($from_doc_root) {
			return str_replace($_SERVER['DOCUMENT_ROOT'], '', $this->directory);    
		}
		return $this->directory;    
	}
	
	
	/**
	 * Gets the disk usage of the directory and all files and folders contained within. May be incorrect if files over 2GB exist.
	 * 
	 * @param  boolean $format          If the filesize should be formatted for human readability
	 * @param  integer $decimal_places  The number of decimal places to format to (if enabled)
	 * @return integer|string  If formatted a string with filesize in b/kb/mb/gb/tb, otherwise an integer
	 */
	public function getDiskUsage($format=FALSE, $decimal_places=1)
	{
		$this->tossIfException();
		
		if (fCore::getOS() == 'linux/unix') {
			$output = shell_exec('du -sb ' . escapeshellarg($this->directory));
			list($size, $trash) = explode("\t", $output);    		
		}
		
		if (fCore::getOS() == 'windows') {
			$process = popen('dir /S /A:-D /-C ' . escapeshellarg($this->directory), 'r');
			
			$line = '';
			while (!feof($process)) {
				$last_line = $line;
				$line = fgets($process);
				
				if (strpos($last_line, 'Total Files Listed:') !== FALSE) {
					$line_segments = preg_split('#\s+#', $line, 0, PREG_SPLIT_NO_EMPTY);  
					$size = $line_segments[2];  
					break;
				}
			}
			pclose($process);
		}
		
		if (!$format) {
			return $size;    
		}
		
		return fFilesystem::formatFilesize($size, $decimal_places);
	}
	
	
	/**
	 * Check to see if the current directory is a temporary directory
	 * 
	 * @internal
	 * 
	 * @return boolean  If the directory is a temp directory
	 */
	public function isTemp()
	{
		$this->tossIfException();
		
		return preg_match('#' . self::TEMP_DIRECTORY . '$#', $this->directory);    
	}
	
	
	/**
	 * Check to see if the current directory is writable
	 * 
	 * @return boolean  If the directory is writable
	 */
	public function isWritable()
	{
		$this->tossIfException();
		
		return is_writable($this->directory);   
	}
	
	
	/**
	 * Gets (and creates if necessary) a temp dir for the current directory
	 * 
	 * @internal
	 * 
	 * @return fDirectory  The object representing the temp dir
	 */
	public function getTemp()
	{
		$this->tossIfException();
		
		if ($this->isTemp()) {
			fCore::toss('fProgrammerException', 'The current directory is a temporary directory');   
		}
		$temp_dir = $this->directory . self::TEMP_DIRECTORY;
		if (!file_exists($temp_dir)) {
			$old_umask = umask(0000);
			mkdir($temp_dir);
			umask($old_umask);
		} 
		return new fDirectory($temp_dir);    
	}
	
	
	/**
	 * Gets the parent directory
	 * 
	 * @return fDirectory  The object representing the parent dir
	 */
	public function getParent()
	{
		$this->tossIfException();
		
		return new fDirectory(preg_replace('#[^/\\\\]+(/|\\\\)$#', '', $this->directory));    
	}
	
	
	/**
	 * Will clean out a temp directory of all files/directories. Removes all files over 6 hours old.
	 * 
	 * This operation is not part of the filesystem transaction model and will be executed immediately.
	 * 
	 * @internal
	 * 
	 * @return void
	 */
	public function clean() 
	{
		$this->tossIfException();
		
		if (!$this->isTemp()) {
			fCore::toss('fProgrammerException', 'Only temporary directories can be cleaned');   
		}
		
		// Delete the files
		$files = $this->recursiveScan();
		foreach ($files as $file) {
			if ($file instanceof fFile) {
				if (filemtime($file->getPath()) < strtotime('-6 hours')) {
					$file->delete();
				}
			}
		}
		
		// Delete the directories
		$dirs = $this->recursiveScan();
		foreach ($dirs as $dir) {
			if ($dir instanceof fDirectory) {    
				if (filemtime($dir->getPath()) < strtotime('-6 hours') && $dir->scan() == array()) {
					$dir->delete();
				}
			}
		}    
	}
	
	
	/**
	 * Performs a scandir on a directory, removing the . and .. folder references
	 * 
	 * @return array  The fFile and fDirectory objects for the files/folders in this directory
	 */
	public function scan()
	{
		$this->tossIfException();
		
		$files = array_diff(scandir($this->directory), array('.', '..')); 
		$objects = array();
		
		foreach ($files as $file) {
			if (is_dir($this->directory . $file)) {
				$objects[] = new fDirectory($this->directory . $file);   
			} else {
				$objects[] = new fFile($this->directory . $file); 
			}
		}  
		
		return $objects;
	}
	
	
	/**
	 * Performs a recursive scandir on a directory, removing the . and .. folder references
	 * 
	 * @return array  The fFile and fDirectory objects for the files/folders (listed recursively) in this directory
	 */
	public function scanRecursive()
	{
		$this->tossIfException();
		
		$files  = $this->scan();
		$objects = $files;
		
		$total_files = sizeof($files);
		for ($i=0; $i < $total_files; $i++) {
			if ($files[$i] instanceof fDirectory) {
				$objects = array_splice($objects, $i, 0, $files[$i]->scanRecursive());   
			}
		}  
		
		return $objects;
	}
	
	
	/**
	 * Renames the current directory, overwriting any existing file/directory
	 * 
	 * This operation will NOT be performed until the filesystem transaction has been committed, if a transaction is in progress. Any non-Flourish code (PHP or system) will still see this directory (and all contained files/dirs) as existing with the old paths until that point.
	 * 
	 * @param  string $new_dirname  The new full path to the directory
	 * @param  boolean $overwrite   If the new dirname already exists, TRUE will cause the file to be overwritten, FALSE will cause the new filename to change
	 * @return void
	 */
	public function rename($new_dirname, $overwrite) 
	{
		$this->tossIfException();
		
		if (!$this->getParent()->isWritable()) {
			fCore::toss('fProgrammerException', 'The directory, ' . $this->directory . ', can not be renamed because the directory containing it is not writable');	
		}
		
		$info = fFilesystem::getPathInfo($new_dirname);
		
		if (!file_exists($info['dirname'])) {
			fCore::toss('fProgrammerException', 'The new directory name specified, ' . $new_dirname . ', is inside of a directory that does not exist');
		}
		
		// Make the dirname absolute
		$new_dirname = fDirectory::makeCanonical(realpath($new_dirname));
		
		if (file_exists($new_dirname)) {
			if (!is_writable($new_dirname)) {
				fCore::toss('fProgrammerException', 'The new directory name specified, ' . $new_dirname . ', already exists, but is not writable'); 		
			}
			if (!$overwrite) {
				$new_dirname = fFilesystem::createUniqueName($new_dirname);	
			}
		} else {
			$parent_dir = new fDirectory($info['dirname']);
			if (!$parent_dir->isWritable()) {
				fCore::toss('fProgrammerException', 'The new directory name specified, ' . $new_dirname . ', is inside of a directory that is not writable');
			} 
		}
		
		
		@rename($this->directory, $new_dirname);
		
		// Allow filesystem transactions
		if (fFilesystem::isTransactionInProgress()) {
			fFilesystem::rename($this->directory, $new_dirname);	
		}
		
		fFilesystem::updateFilenameMapForDirectory($this->directory, $new_dirname);
	}
	
	
	/**
	 * Will delete a directory and all files and folders inside of it
	 * 
	 * This operation will not be performed until the filesystem transaction has been committed, if a transaction is in progress. Any non-Flourish code (PHP or system) will still see this directory and all contents as existing until that point.
	 * 
	 * @return void
	 */
	public function delete() 
	{
		$this->tossIfException();
		
		$files = $this->scan();
		
		foreach ($files as $file) {
			$file->delete();
		} 
		
		// Allow filesystem transactions
		if (fFilesystem::isTransactionInProgress()) {
			return fFilesystem::delete($this);	
		} 
		
		rmdir($this->directory);  
		
		$exception = new fProgrammerException('The action requested can not be performed because the directory has been deleted');
		fFilesystem::updateExceptionMap($this->directory, $exception);
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