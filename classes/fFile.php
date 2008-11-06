<?php
/**
 * Represents a file on the filesystem, also provides static file-related methods
 * 
 * @copyright  Copyright (c) 2007-2008 William Bond
 * @author     William Bond [wb] <will@flourishlib.com>
 * @license    http://flourishlib.com/license
 * 
 * @package    Flourish
 * @link       http://flourishlib.com/fFile
 * 
 * @version    1.0.0b
 * @changes    1.0.0b  The initial implementation [wb, 2007-06-14]
 */
class fFile
{
	// The following constants allow for nice looking callbacks to static methods
	const create = 'fFile::create';
	
	
	/**
	 * Creates a file on the filesystem and returns an object representing it.
	 * 
	 * This operation will be reverted by a filesystem transaction being rolled back.
	 * 
	 * @throws fValidationException
	 * 
	 * @param  string $file_path  The path to the new file
	 * @param  string $contents   The contents to write to the file, must be a non-NULL value to be written
	 * @return fFile
	 */
	static public function create($file_path, $contents)
	{
		if (empty($file_path)) {
			fCore::toss(
				'fValidationException',
				fGrammar::compose('No filename was specified')
			);
		}
		
		if (file_exists($file_path)) {
			fCore::toss(
				'fValidationException',
				fGrammar::compose(
					'The file specified, %s, already exists',
					fCore::dump($file_path)
				)
			);
		}
		
		$directory = fFilesystem::getPathInfo($file_path, 'dirname');
		if (!is_writable($directory)) {
			fCore::toss(
				'fEnvironmentException',
				fGrammar::compose(
					'The file path specified, %s, is inside of a directory that is not writable',
					fCore::dump($file_path)
				)
			);
		}
		
		file_put_contents($file_path, $contents);
		
		$file = new fFile($file_path);
		
		fFilesystem::recordCreate($file);
		
		return $file;
	}
	
	
	/**
	 * The full path to the file
	 * 
	 * @var string
	 */
	protected $file;
	
	/**
	 * An exception to be thrown if an action is performed on the file
	 * 
	 * @var Exception
	 */
	protected $exception;
	
	
	/**
	 * Creates an object to represent a file on the filesystem
	 * 
	 * If multiple fFile objects are created for a single file, they will
	 * reflect changes in each other including rename and delete actions.
	 * 
	 * @throws fValidationException
	 * 
	 * @param  string $file  The path to the file
	 * @return fFile
	 */
	public function __construct($file)
	{
		if (empty($file)) {
			fCore::toss(
				'fValidationException',
				fGrammar::compose('No filename was specified')
			);
		}
		
		if (!file_exists($file)) {
			fCore::toss(
				'fValidationException',
				fGrammar::compose(
					'The file specified, %s, does not exist',
					fCore::dump($file)
				)
			);
		}
		if (!is_readable($file)) {
			fCore::toss(
				'fEnvironmentException',
				fGrammar::compose(
					'The file specified, %s, is not readable',
					fCore::dump($file)
				)
			);
		}
		
		// Store the file as an absolute path
		$file = realpath($file);
		
		// Hook into the global file and exception maps
		$this->file      =& fFilesystem::hookFilenameMap($file);
		$this->exception =& fFilesystem::hookExceptionMap($file);
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
	 * Returns the filename of the file
	 * 
	 * @return string  The filename
	 */
	public function __toString()
	{
		return $this->getFilename();
	}
	
	
	/**
	 * Deletes the current file
	 * 
	 * This operation will NOT be performed until the filesystem transaction
	 * has been committed, if a transaction is in progress. Any non-Flourish
	 * code (PHP or system) will still see this file as existing until that
	 * point.
	 * 
	 * @return void
	 */
	public function delete()
	{
		// The only kind of stored exception is if the file has already
		// been deleted so in that case nothing needs to be done
		if ($this->exception) {
			return;
		}
		
		if (!$this->getDirectory()->isWritable()) {
			fCore::toss(
				'fEnvironmentException',
				fGrammar::compose(
					'The file, %s, can not be deleted because the directory containing it is not writable',
					fCore::dump($this->file)
				)
			);
		}
		
		// Allow filesystem transactions
		if (fFilesystem::isInsideTransaction()) {
			return fFilesystem::recordDelete($this);
		}
		
		@unlink($this->file);
		
		$exception = new fProgrammerException(
			fGrammar::compose(
				'The action requested can not be performed because the file has been deleted'
			)
		);
		fFilesystem::updateExceptionMap($this->file, $exception);
	}
	
	
	/**
	 * Looks for specific bytes in a file to determine the mime type of the file
	 * 
	 * @param  string $content  The first 4 bytes of the file content to use for byte checking
	 * @return string  The mime type of the file
	 */
	private function determineMimeTypeByContents($content)
	{
		$_0_8 = substr($content, 0, 8);
		$_0_6 = substr($content, 0, 6);
		$_0_5 = substr($content, 0, 5);
		$_0_4 = substr($content, 0, 4);
		$_0_3 = substr($content, 0, 3);
		$_0_2 = substr($content, 0, 2);
		
		if ($_0_5 == '%PDF-') {
			return 'application/pdf'; 	
		}
		
		if ($_0_5 == '{\rtf') {
			return 'text/rtf';	
		}
		
		# Quicktime and MP4 audio/video
		if ($_0_4 == 'MOVI') {
			if (in_array($_4_4, array('moov', 'mdat'))) {
				return 'video/quicktime';
			}	
		}
		
		if (strlen($content) > 8 && substr($content, 4, 4) == 'ftyp') {
			
			$_8_4 = substr($content, 8, 4);
			$_8_3 = substr($content, 8, 3);
			
			if (in_array($_8_4, array('isom', 'iso2', 'mp41', 'mp42'))) {
				return 'video/mp4';
			}	
			
			if ($_8_4 == 'avc1') {
				return 'video/3gpp';
			}
			
			if ($_8_3 == 'M4A') {
				return 'audio/mp4';
			}
			
			if ($_8_3 == 'M4V') {
				return 'video/mp4';
			}
			
			if ($_8_3 == 'M4P' || $_8_3 == 'M4B' || substr($content, 8, 2) == 'qt') {
				return 'video/quicktime';	
			}
		}
		
		// MP3
		if ($_0_2 & "\xFF\xFE" == "\xFF\xFA") {
			if (in_array($content[2] & "\xF0", array("\x10", "\x20", "\x30", "\x40", "\x50", "\x60", "\x70", "\x80", "\x90", "\xA0", "\xB0", "\xC0", "\xD0", "\xE0"))) {
				return 'audio/mpeg';
			}	
		}
		if ($_0_3 == 'ID3') {
			return 'audio/mpeg';	
		}
		
		if (strlen($content) > 257) {
			if (substr($content, 257, 6) == "ustar\x00") {
				return 'application/x-tar';	
			}
			if (substr($content, 257, 8) == "ustar\x40\x40\x00") {
				return 'application/x-tar';	
			}
		}
		
		if ($_0_4 == 'Rar!') {
			return 'application/x-rar-compressed';	
		}
		
		if ($_0_4 == "PK\x03\x04") {
			$_14_5 = substr($content, 14, 5);
			if ($_14_5 == "\xDD\xFC\x95\x37\x66") {
				return 'application/msword';	
			}
			if ($_14_5 == "\x58\x56\xC6\x8F\x60") {
				return 'application/vnd.ms-excel';
			}
			if ($_14_5 == "\x26\x1\xC0\xB1\xE2") {
				return 'application/vnd.ms-powerpoint';
			}	
			return 'application/zip';	
		}
		
		if ($_0_4 == 'RIFF' && $_8_4 == 'WAVE') {
			return 'audio/x-wav';	
		}
		
		if ($_0_2 == "\x1F\x9D") {
			return 'application/x-compress';	
		}
		
		if ($_0_2 == "\x1F\x8B") {
			return 'application/x-gzip';	
		}
		
		if ($_0_3 == 'BZh') {
			return 'application/x-bzip2';	
		}
		
		if ($_0_3 == 'FWS' || $_0_3 == 'CWS') {
			return 'application/x-shockwave-flash';	
		}
		
		if ($_0_3 == 'FLV') {
			return 'video/x-flv';	
		}
		
		if ($_0_4 == "MM\x00\x2A" || $_0_4 == "II\x2A\x00") {
			return 'image/tiff';	
		}
		
		if ($_0_8 == "\x89PNG\x0D\x0A\x1A\x0A") {
			return 'image/png';	
		}
		
		if ($_0_4 == 'GIF8') {
			return 'image/gif';	
		}
		
		if ($_0_2 == 'BM' && strlen($content) > 14 && array($content[14], array("\x0C", "\x28", "\x40", "\x80"))) {
			return 'image/x-ms-bmp';	
		}
		
		if (strlen($content) > 10 && substr($content, 6, 4) == 'JFIF') {
			return 'image/jpeg';	
		}
		
		if ($_0_4 == "SIT!" || $_0_4 == "SITD" || substr($content, 0, 7) == 'StuffIt') {
			return 'application/x-stuffit';	
		}
		
		if ($_0_8 == "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1") {
			$_513_8 = substr($content, 513, 8);
			if ($_513_8 == "\x09\x08\x10\x00\x00\x06\x05\x00") {
				return 'application/vnd.ms-excel';	
			}
			if ($_513_8 == "\xEC\xA5\xC1\x00\x3\x60\x9\x4") {
				return 'application/msword';
			}
			if ($_513_8 == "\x52\x00\x6F\x00\x6F\x00\x74\x00") {	
				return 'application/vnd.ms-powerpoint';
			}
		}
		
		if ($_0_8 == "\x09\x04\x06\x00\x00\x00\x10\x00") {
			return 'application/vnd.ms-excel';	
		}
		
		if ($_0_4 == "\x00\x00\x01\x00") {
			return 'application/vnd.microsoft.icon';	
		}
		
		if ($_0_6 == "\xDB\xA5\x2D\x00\x00\x00" || $_0_5 == "\x50\x4F\x5E\x51\x60" || $_0_4 == "\xFE\x37\x0\x23" || $_0_3 == "\x94\xA6\x2E") {
			return 'application/msword';	
		}	
		
		if (strpos($content, '<?xml') !== FALSE) {
			if (stripos($content, '<!DOCTYPE') !== FALSE) {
				return 'application/xhtml+xml';
			}
			if (strpos($content, '<svg') !== FALSE) {
				return 'image/svg+xml';
			}
			if (strpos($content, '<rss') !== FALSE) {
				return 'application/rss+xml';
			}
			return 'application/xml';	
		}
		
		if (strpos($content, '<?php') !== FALSE || strpos($content, '<?=') !== FALSE) {
			return 'application/x-httpd-php';	
		}
		
		if (preg_match('#^\#\![/a-z0-9]+(python|perl|php|ruby)$#mi', $content, $matches)) {
			switch (strtolower($matches[1])) {
				case 'php':
					return 'application/x-httpd-php';
				case 'python':
					return 'application/x-python';
				case 'perl':
					return 'application/x-perl';
				case 'ruby':
					return 'application/x-ruby';
			}	
		}
		
		return 'application/octet-stream';
	}
	
	
	/**
	 * Uses the extension of the all-text file to determine the mime type
	 * 
	 * @param  string $extension  The file extension
	 * @return string  The mime type of the file
	 */
	private function determineMimeTypeByExtension($extension)
	{
		switch ($extension) {
			case 'css':
				return 'text/css';
			
			case 'htm':
			case 'html':
				return 'text/html';
			
			case 'js':
				return 'application/javascript';
			
			case 'inc':
			case 'php':
			case 'php3':
			case 'php4':
			case 'php5':
				return 'application/x-httpd-php';
				
			case 'pl':
			case 'cgi':
				return 'application/x-perl';
			
			case 'rb':
			case 'rhtml':
				return 'application/x-ruby';
			
			case 'py':
				return 'application/x-python';
			
			case 'rss':
				return 'application/rss+xml';
			
			case 'xml':
				return 'application/xml';
			
			case 'csv':
				return 'text/csv';
				
			case 'vcf':
				return 'text/x-vcard';
				
			case 'ics':
				return 'text/calendar';
			
			case 'tab':
				return 'text/tab-separated-values';
			
			default:
				return 'text/plain';	
		}
	}
	
	
	/**
	 * Creates a new file object with a copy of this file
	 * 
	 * If no directory is specified, the file is created with a new name in
	 * the current directory. If a new directory is specified, you must also
	 * indicate if you wish to overwrite an existing file with the same name
	 * in the new directory or create a unique name.
	 * 
	 * This operation will be reverted by a filesystem transaction being rolled
	 * back.
	 * 
	 * @param  string|fDirectory $new_directory  The directory to duplicate the file into if different than the current directory
	 * @param  boolean           $overwrite      If a new directory is specified, this indicates if a file with the same name should be overwritten.
	 * @return fFile  The new fFile object
	 */
	public function duplicate($new_directory=NULL, $overwrite=NULL)
	{
		$this->tossIfException();
		
		if ($new_directory === NULL) {
			$new_directory = $this->getDirectory();
		}
		
		if (!is_object($new_directory)) {
			$new_directory = new fDirectory($new_directory);
		}
		
		if ($new_directory->getPath() == $this->getDirectory()->getPath()) {
			fCore::toss(
				'fProgrammerException',
				fGrammar::compose(
					"The new directory specified, %s, is the same as the current file's directory",
					fCore::dump($new_directory->getPath())
				)
			);
		}
		
		$new_filename = $new_directory->getPath() . $this->getFilename();
		
		$check_dir_permissions = FALSE;
		
		if (file_exists($new_filename)) {
			if (!is_writable($new_filename)) {
				fCore::toss(
					'fEnvironmentException',
					fGrammar::compose(
						'The new directory specified, %1$s, already contains a file with the name %2$s, but it is not writable',
						fCore::dump($new_directory),
						fCore::dump($this->getFilename())
					)
				);
			}
			if (!$overwrite) {
				$new_filename = fFilesystem::makeUniqueName($new_filename);
				$check_dir_permissions = TRUE;
			}
		} else {
			$check_dir_permissions = TRUE;
		}
		
		if ($check_dir_permissions) {
			if (!$new_directory->isWritable()) {
				fCore::toss(
					'fEnvironmentException',
					fGrammar::compose(
						'The new directory specified, %s, is not writable',
						fCore::dump($new_directory)
					)
				);
			}
		}
		
		@copy($this->getPath(), $new_filename);
		$class = get_class($this);
		$file  = new $class($new_filename);
		
		// Allow filesystem transactions
		if (fFilesystem::isInsideTransaction()) {
			fFilesystem::recordDuplicate($file);
		}
		
		return $file;
	}
	
	
	/**
	 * Gets the directory the file is located in
	 * 
	 * @return fDirectory  The directory containing the file
	 */
	public function getDirectory()
	{
		$this->tossIfException();
		
		return new fDirectory(fFilesystem::getPathInfo($this->file, 'dirname'));
	}
	
	
	/**
	 * Gets the filename (i.e. does not include the directory)
	 * 
	 * @return string  The filename of the file
	 */
	public function getFilename()
	{
		$this->tossIfException();
		
		// For some reason PHP calls the filename the basename, where filename is the filename minus the extension
		return fFilesystem::getPathInfo($this->file, 'basename');
	}
	
	
	/**
	 * Gets the size of the file
	 * 
	 * The return value may be incorrect for files over 2GB on 32-bit OSes.
	 * 
	 * @param  boolean $format          If the filesize should be formatted for human readability
	 * @param  integer $decimal_places  The number of decimal places to format to (if enabled)
	 * @return integer|string  If formatted a string with filesize in b/kb/mb/gb/tb, otherwise an integer
	 */
	public function getFilesize($format=FALSE, $decimal_places=1)
	{
		$this->tossIfException();
		
		// This technique can overcome signed integer limit
		$size = sprintf("%u", filesize($this->file));
		
		if (!$format) {
			return $size;
		}
		
		return fFilesystem::formatFilesize($size, $decimal_places);
	}
	
	
	/**
	 * Gets the file's mime type, or `application/octet-stream` if it can't be determined
	 * 
	 * @return string  The mime type of the file
	 */
	public function getMimeType()
	{
		$this->tossIfException();
		
		$handle  = fopen($this->file, 'r');
		$content = fread($handle, 4096);
		fclose($handle);
		
		// If there are no low ASCII chars and no easily distinguishable tokens, we need to detect by file extension
		if (!preg_match('#[\x00-\x08\x0B\x0C\x0E-\x1F]|<\?php|<\?xml|\{\\\\rtf|<\?=|<html|<\!doctype|<rss|\#\![/a-z0-9]+(python|ruby|perl|php)\b#i', $content)) {
			return $this->determineMimeTypeByExtension(fFilesystem::getPathInfo($this->file, 'extension'));		
		}
		
		return $this->determineMimeTypeByContents($content);	
	}
	
	
	/**
	 * Gets the file's current path (directory and filename)
	 * 
	 * If the web path is requested, uses translations set with
	 * fFilesystem::addWebPathTranslation()
	 * 
	 * @param  boolean $translate_to_web_path  If the path should be the web path
	 * @return string  The path (directory and filename) for the file
	 */
	public function getPath($translate_to_web_path=FALSE)
	{
		$this->tossIfException();
		
		if ($translate_to_web_path) {
			return fFilesystem::translateToWebPath($this->file);
		}
		return $this->file;
	}
	
	
	/**
	 * Check to see if the current file is writable
	 * 
	 * @return boolean  If the file is writable
	 */
	public function isWritable()
	{
		$this->tossIfException();
		
		return is_writable($this->file);
	}
	
	
	/**
	 * Reads the data from the file
	 * 
	 * Reads all file data into memory, use with caution on large files!
	 * 
	 * This operation will read the data that has been written during the
	 * current transaction if one is in progress.
	 * 
	 * @param  mixed $data  The data to write to the file
	 * @return string  The contents of the file
	 */
	public function read()
	{
		$this->tossIfException();
		
		return file_get_contents($this->file);
	}
	
	
	/**
	 * Renames the current file
	 * 
	 * If the filename already exists and the overwrite flag is set to false,
	 * a new filename will be created.
	 * 
	 * This operation will be reverted if a filesystem transaction is in
	 * progress and is later rolled back.
	 * 
	 * @param  string  $new_filename  The new full path to the file
	 * @param  boolean $overwrite     If the new filename already exists, `TRUE` will cause the file to be overwritten, `FALSE` will cause the new filename to change
	 * @return void
	 */
	public function rename($new_filename, $overwrite)
	{
		$this->tossIfException();
		
		if (!$this->getDirectory()->isWritable()) {
			fCore::toss(
				'fEnvironmentException',
				fGrammar::compose(
					'The file, %s, can not be renamed because the directory containing it is not writable',
					fCore::dump($this->file)
				)
			);
		}
		
		$info = fFilesystem::getPathInfo($new_filename);
		
		if (!file_exists($info['dirname'])) {
			fCore::toss(
				'fProgrammerException',
				fGrammar::compose(
					'The new filename specified, %s, is inside of a directory that does not exist',
					fCore::dump($new_filename)
				)
			);
		}
		
		// Make the filename absolute
		$new_filename = fDirectory::makeCanonical(realpath($info['dirname'])) . $info['basename'];
		
		if (file_exists($new_filename)) {
			if (!is_writable($new_filename)) {
				fCore::toss(
					'fEnvironmentException',
					fGrammar::compose(
						'The new filename specified, %s, already exists, but is not writable',
						fCore::dump($new_filename)
					)
				);
			}
			if (!$overwrite) {
				$new_filename = fFilesystem::makeUniqueName($new_filename);
			}
		} else {
			$new_dir = new fDirectory($info['dirname']);
			if (!$new_dir->isWritable()) {
				fCore::toss(
					'fEnvironmentException',
					fGrammar::compose(
						'The new filename specified, %s, is inside of a directory that is not writable',
						fCore::dump($new_filename)
					)
				);
			}
		}
		
		@rename($this->file, $new_filename);
		
		// Allow filesystem transactions
		if (fFilesystem::isInsideTransaction()) {
			fFilesystem::recordRename($this->file, $new_filename);
		}
		
		fFilesystem::updateFilenameMap($this->file, $new_filename);
	}
	
	
	/**
	 * Throws the file exception if exists
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
	 * Writes the provided data to the file
	 * 
	 * Requires all previous data to be stored in memory if inside a
	 * transaction, use with caution on large files!
	 * 
	 * If a filesystem transaction is in progress and is rolled back, the
	 * previous data will be restored.
	 * 
	 * @param  mixed $data  The data to write to the file
	 * @return void
	 */
	public function write($data)
	{
		$this->tossIfException();
		
		if (!$this->isWritable()) {
			fCore::toss(
				'fEnvironmentException',
				fGrammar::compose(
					'This file, %s, can not be written to because it is not writable',
					fCore::dump($this->file)
				)
			);
		}
		
		// Allow filesystem transactions
		if (fFilesystem::isInsideTransaction()) {
			fFilesystem::recordWrite($this);
		}
		
		file_put_contents($this->file, $data);
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