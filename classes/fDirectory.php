<?php
/**
 * Represents a directory on the filesystem, also provides static directory-related methods
 * 
 * @copyright  Copyright (c) 2007-2008 William Bond
 * @author     William Bond [wb] <will@flourishlib.com>
 * @license    http://flourishlib.com/license
 * 
 * @package    Flourish
 * @link       http://flourishlib.com/fDirectory
 * 
 * @version    1.0.0b
 * @changes    1.0.0b  The initial implementation [wb, 2007-12-21]
 */
class fDirectory
{
	// The following constants allow for nice looking callbacks to static methods
	const create        = 'fDirectory::create';
	const makeCanonical = 'fDirectory::makeCanonical';
	
	
	/**
	 * Creates a directory on the filesystem and returns an object representing it
	 * 
	 * The directory creation is done recursively, so if any of the parent
	 * directories do not exist, they will be created.
	 * 
	 * This operation will be reverted by a filesystem transaction being rolled back.
	 * 
	 * @throws fValidationException
	 * 
	 * @param  string  $directory  The path to the new directory
	 * @param  numeric $mode       The mode (permissions) to use when creating the directory. This should be an octal number (requires a leading zero). This has no effect on the Windows platform.
	 * @return fDirectory
	 */
	static public function create($directory, $mode=0777)
	{
		if (empty($directory)) {
			throw new fValidationException('No directory name was specified');
		}
		
		if (file_exists($directory)) {
			throw new fValidationException(
				'The directory specified, %s, already exists',
				$directory
			);
		}
		
		$parent_directory = fFilesystem::getPathInfo($directory, 'dirname');
		if (!file_exists($parent_directory)) {
			fDirectory::create($parent_directory, $mode);
		}
		
		if (!is_writable($parent_directory)) {
			throw new fEnvironmentException(
				'The directory specified, %s, is inside of a directory that is not writable',
				$directory
			);
		}
		
		mkdir($directory, $mode);
		
		$directory = new fDirectory($directory);
		
		fFilesystem::recordCreate($directory);
		
		return $directory;
	}
	
	
	/**
	 * Makes sure a directory has a `/` or `\` at the end
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
	 * Creates an object to represent a directory on the filesystem
	 * 
	 * If multiple fDirectory objects are created for a single directory,
	 * they will reflect changes in each other including rename and delete
	 * actions.
	 * 
	 * @throws fValidationException
	 * 
	 * @param  string $directory  The path to the directory
	 * @return fDirectory
	 */
	public function __construct($directory)
	{
		if (empty($directory)) {
			throw new fValidationException('No directory was specified');
		}
		
		if (!file_exists($directory)) {
			throw new fValidationException(
				'The directory specified, %s, does not exist',
				$directory
			);
		}
		if (!is_dir($directory)) {
			throw new fValidationException(
				'The directory specified, %s, is not a directory',
				$directory
			);
		}
		if (!is_readable($directory)) {
			throw new fEnvironmentException(
				'The directory specified, %s, is not readable',
				$directory
			);
		}
		
		$directory = self::makeCanonical(realpath($directory));
		
		$this->directory =& fFilesystem::hookFilenameMap($directory);
		$this->exception =& fFilesystem::hookExceptionMap($directory);
	}
	
	
	/**
	 * All requests that hit this method should be requests for callbacks
	 * 
	 * @param  string $method  The method to create a callback for
	 * @return callback  The callback for the method requested
	 */
	public function __get($method)
	{
		return array($this, $method);		
	}
	
	
	/**
	 * Returns the full filesystem path for the directory
	 * 
	 * @return string  The full filesystem path
	 */
	public function __toString()
	{
		return $this->getPath();
	}
	
	
	/**
	 * Will delete a directory and all files and directories inside of it
	 * 
	 * This operation will not be performed until the filesystem transaction
	 * has been committed, if a transaction is in progress. Any non-Flourish
	 * code (PHP or system) will still see this directory and all contents as
	 * existing until that point.
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
		if (fFilesystem::isInsideTransaction()) {
			return fFilesystem::delete($this);
		}
		
		rmdir($this->directory);
		
		$exception = new fProgrammerException(
			'The action requested can not be performed because the directory has been deleted'
		);
		fFilesystem::updateExceptionMap($this->directory, $exception);
	}
	
	
	/**
	 * Gets the disk usage of the directory and all files and folders contained within
	 * 
	 * This method may return incorrect results if files over 2GB exist and the
	 * server uses a 32 bit operating system
	 * 
	 * @param  boolean $format          If the filesize should be formatted for human readability
	 * @param  integer $decimal_places  The number of decimal places to format to (if enabled)
	 * @return integer|string  If formatted, a string with filesize in b/kb/mb/gb/tb, otherwise an integer
	 */
	public function getFilesize($format=FALSE, $decimal_places=1)
	{
		$this->tossIfException();
		
		$size = 0;
		
		$children = $this->scan();
		foreach ($children as $child) {
			$size += $child->getFilesize();
		}
		
		if (!$format) {
			return $size;
		}
		
		return fFilesystem::formatFilesize($size, $decimal_places);
	}
	
	
	/**
	 * Gets the parent directory
	 * 
	 * @return fDirectory  The object representing the parent directory
	 */
	public function getParent()
	{
		$this->tossIfException();
		
		$dirname = fFilesystem::getPathInfo($this->directory, 'dirname');
		
		if ($dirname == $this->directory) {
			throw new fEnvironmentException(
				'The current directory does not have a parent directory'
			);
		}
		
		return new fDirectory();
	}
	
	
	/**
	 * Gets the directory's current path
	 * 
	 * If the web path is requested, uses translations set with
	 * fFilesystem::addWebPathTranslation()
	 * 
	 * @param  boolean $translate_to_web_path  If the path should be the web path
	 * @return string  The path for the directory
	 */
	public function getPath($translate_to_web_path=FALSE)
	{
		$this->tossIfException();
		
		if ($translate_to_web_path) {
			return fFilesystem::translateToWebPath($this->directory);
		}
		return $this->directory;
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
	 * Renames the current directory, overwriting any existing file/directory
	 * 
	 * This operation will NOT be performed until the filesystem transaction
	 * has been committed, if a transaction is in progress. Any non-Flourish
	 * code (PHP or system) will still see this directory (and all contained
	 * files/dirs) as existing with the old paths until that point.
	 * 
	 * @param  string  $new_dirname  The new full path to the directory
	 * @param  boolean $overwrite    If the new dirname already exists, TRUE will cause the file to be overwritten, FALSE will cause the new filename to change
	 * @return void
	 */
	public function rename($new_dirname, $overwrite)
	{
		$this->tossIfException();
		
		if (!$this->getParent()->isWritable()) {
			throw new fEnvironmentException(
				'The directory, %s, can not be renamed because the directory containing it is not writable',
				$this->directory
			);
		}
		
		$info = fFilesystem::getPathInfo($new_dirname);
		
		if (!file_exists($info['dirname'])) {
			throw new fProgrammerException(
				'The new directory name specified, %s, is inside of a directory that does not exist',
				$new_dirname
			);
		}
		
		// Make the dirname absolute
		$new_dirname = fDirectory::makeCanonical(realpath($new_dirname));
		
		if (file_exists($new_dirname)) {
			if (!is_writable($new_dirname)) {
				throw new fEnvironmentException(
					'The new directory name specified, %s, already exists, but is not writable',
					$new_dirname
				);
			}
			if (!$overwrite) {
				$new_dirname = fFilesystem::makeUniqueName($new_dirname);
			}
		} else {
			$parent_dir = new fDirectory($info['dirname']);
			if (!$parent_dir->isWritable()) {
				throw new fEnvironmentException(
					'The new directory name specified, %s, is inside of a directory that is not writable',
					$new_dirname
				);
			}
		}
		
		rename($this->directory, $new_dirname);
		
		// Allow filesystem transactions
		if (fFilesystem::isInsideTransaction()) {
			fFilesystem::rename($this->directory, $new_dirname);
		}
		
		fFilesystem::updateFilenameMapForDirectory($this->directory, $new_dirname);
	}
	
	
	/**
	 * Performs a [http://php.net/scandir scandir()] on a directory, removing the `.` and `..` entries
	 * 
	 * @return array  The fFile (or fImage) and fDirectory objects for the files/directories in this directory
	 */
	public function scan()
	{
		$this->tossIfException();
		
		$files = array_diff(scandir($this->directory), array('.', '..'));
		$objects = array();
		
		foreach ($files as $file) {
			$file = $this->directory . $file;
			if (is_dir($file)) {
				$objects[] = new fDirectory($file);
			} elseif (fImage::isImageCompatible($file)) {
				$objects[] = new fImage($file);
			} else {
				$objects[] = new fFile($file);
			}
		}
		
		return $objects;
	}
	
	
	/**
	 * Performs a **recursive** [http://php.net/scandir scandir()] on a directory, removing the `.` and `..` entries
	 * 
	 * @return array  The fFile and fDirectory objects for the files/directory (listed recursively) in this directory
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
	 * Throws the directory exception if exists
	 * 
	 * @return void
	 */
	protected function tossIfException()
	{
		if ($this->exception) {
			throw $this->exception;
		}
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