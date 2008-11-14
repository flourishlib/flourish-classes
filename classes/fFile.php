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
			throw new fValidationException(
				'No filename was specified'
			);
		}
		
		if (file_exists($file_path)) {
			throw new fValidationException(
				'The file specified, %s, already exists',
				$file_path
			);
		}
		
		$directory = fFilesystem::getPathInfo($file_path, 'dirname');
		if (!is_writable($directory)) {
			throw new fEnvironmentException(
				'The file path specified, %s, is inside of a directory that is not writable',
				$file_path
			);
		}
		
		file_put_contents($file_path, $contents);
		
		$file = new fFile($file_path);
		
		fFilesystem::recordCreate($file);
		
		return $file;
	}
	
	
	/**
	 * Determines the file's mime type by either looking at the file contents or matching the extension
	 * 
	 * Please see the ::getMimeType() description for details about how the
	 * mime type is determined and what mime types are detected.
	 * 
	 * @internal
	 * 
	 * @param  string $file  The file to check the mime type for
	 * @return string  The mime type of the file
	 */
	static public function determineMimeType($file)
	{
		if (!file_exists($file)) {
			throw new fValidationException(
				'The file specified, %s, does not exist',
				$file
			);
		}
		
		// The first 4k should be enough for content checking
		$handle  = fopen($file, 'r');
		$content = fread($handle, 4096);
		fclose($handle);
		
		// If there are no low ASCII chars and no easily distinguishable tokens, we need to detect by file extension
		if (!preg_match('#[\x00-\x08\x0B\x0C\x0E-\x1F]|<\?php|\%\!PS-Adobe-3|<\?xml|\{\\\\rtf|<\?=|<html|<\!doctype|<rss|\#\![/a-z0-9]+(python|ruby|perl|php)\b#i', $content)) {
			return self::determineMimeTypeByExtension(fFilesystem::getPathInfo($file, 'extension'));		
		}
		
		return self::determineMimeTypeByContents($content);
	}
	
	
	/**
	 * Looks for specific bytes in a file to determine the mime type of the file
	 * 
	 * @param  string $content  The first 4 bytes of the file content to use for byte checking
	 * @return string  The mime type of the file
	 */
	static private function determineMimeTypeByContents($content)
	{
		$_0_8 = substr($content, 0, 8);
		$_0_6 = substr($content, 0, 6);
		$_0_5 = substr($content, 0, 5);
		$_0_4 = substr($content, 0, 4);
		$_0_3 = substr($content, 0, 3);
		$_0_2 = substr($content, 0, 2);
		
		
		// Images
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
		
		if (preg_match('#^[^\n\r]*\%\!PS-Adobe-3#', $content)) {
			return 'application/postscript';			
		}
		
		if ($_0_4 == "\x00\x00\x01\x00") {
			return 'application/vnd.microsoft.icon';	
		}
		
		
		// Audio/Video
		if ($_0_4 == 'MOVI') {
			if (in_array($_4_4, array('moov', 'mdat'))) {
				return 'video/quicktime';
			}	
		}
		
		if (strlen($content) > 8 && substr($content, 4, 4) == 'ftyp') {
			
			$_8_4 = substr($content, 8, 4);
			$_8_3 = substr($content, 8, 3);
			$_8_2 = substr($content, 8, 2);
			
			if (in_array($_8_4, array('isom', 'iso2', 'mp41', 'mp42'))) {
				return 'video/mp4';
			}	
			
			if ($_8_3 == 'M4A') {
				return 'audio/mp4';
			}
			
			if ($_8_3 == 'M4V') {
				return 'video/mp4';
			}
			
			if ($_8_3 == 'M4P' || $_8_3 == 'M4B' || $_8_2 == 'qt') {
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
		
		if ($_0_8 == "\x30\x26\xB2\x75\x8E\x66\xCF\x11") {
			if ($content[24] == "\x07") {
				return 'audio/x-ms-wma';
			}
			if ($content[24] == "\x08") {
				return 'video/x-ms-wmv';
			}
			return 'video/x-ms-asf';	
		}
		
		if ($_0_4 == 'RIFF' && $_8_4 == 'AVI ') {
			return 'video/x-msvideo';	
		}
		
		if ($_0_4 == 'RIFF' && $_8_4 == 'WAVE') {
			return 'audio/x-wav';	
		}
		
		if ($_0_4 == 'OggS') {
			$_28_5 = substr($content, 28, 5);
			if ($_28_5 == "\x01\x76\x6F\x72\x62") {
				return 'audio/vorbis';	
			}
			if ($_28_5 == "\x07\x46\x4C\x41\x43") {
				return 'audio/x-flac';	
			}
			// Theora and OGM	
			if ($_28_5 == "\x80\x74\x68\x65\x6F" || $_28_5 == "\x76\x69\x64\x65") {
				return 'video/ogg';		
			}
		}
		
		if ($_0_3 == 'FWS' || $_0_3 == 'CWS') {
			return 'application/x-shockwave-flash';	
		}
		
		if ($_0_3 == 'FLV') {
			return 'video/x-flv';	
		}
		
		
		// Documents
		if ($_0_5 == '%PDF-') {
			return 'application/pdf'; 	
		}
		
		if ($_0_5 == '{\rtf') {
			return 'text/rtf';	
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
		
		if ($_0_6 == "\xDB\xA5\x2D\x00\x00\x00" || $_0_5 == "\x50\x4F\x5E\x51\x60" || $_0_4 == "\xFE\x37\x0\x23" || $_0_3 == "\x94\xA6\x2E") {
			return 'application/msword';	
		}
		
		// Office 2007 formats
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
		}
		
		
		// Archives
		if ($_0_4 == "PK\x03\x04") {
			return 'application/zip';	
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
		
		if ($_0_2 == "\x1F\x9D") {
			return 'application/x-compress';	
		}
		
		if ($_0_2 == "\x1F\x8B") {
			return 'application/x-gzip';	
		}
		
		if ($_0_3 == 'BZh') {
			return 'application/x-bzip2';	
		}
		
		if ($_0_4 == "SIT!" || $_0_4 == "SITD" || substr($content, 0, 7) == 'StuffIt') {
			return 'application/x-stuffit';	
		}	
		
		
		// Text files
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
		
		
		// Default
		return 'application/octet-stream';
	}
	
	
	/**
	 * Uses the extension of the all-text file to determine the mime type
	 * 
	 * @param  string $extension  The file extension
	 * @return string  The mime type of the file
	 */
	static private function determineMimeTypeByExtension($extension)
	{
		switch ($extension) {
			case 'css':
				return 'text/css';
			
			case 'csv':
				return 'text/csv';
			
			case 'htm':
			case 'html':
			case 'xhtml':
				return 'text/html';
				
			case 'ics':
				return 'text/calendar';
			
			case 'js':
				return 'application/javascript';
			
			case 'php':
			case 'php3':
			case 'php4':
			case 'php5':
			case 'inc':
				return 'application/x-httpd-php';
				
			case 'pl':
			case 'cgi':
				return 'application/x-perl';
			
			case 'py':
				return 'application/x-python';
			
			case 'rb':
			case 'rhtml':
				return 'application/x-ruby';
			
			case 'rss':
				return 'application/rss+xml';
				
			case 'tab':
				return 'text/tab-separated-values';
			
			case 'vcf':
				return 'text/x-vcard';
			
			case 'xml':
				return 'application/xml';
			
			default:
				return 'text/plain';	
		}
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
			throw new fValidationException(
				'No filename was specified'
			);
		}
		
		if (!file_exists($file)) {
			throw new fValidationException(
				'The file specified, %s, does not exist',
				$file
			);
		}
		if (!is_readable($file)) {
			throw new fEnvironmentException(
				'The file specified, %s, is not readable',
				$file
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
			throw new fEnvironmentException(
				'The file, %s, can not be deleted because the directory containing it is not writable',
				$this->file
			);
		}
		
		// Allow filesystem transactions
		if (fFilesystem::isInsideTransaction()) {
			return fFilesystem::recordDelete($this);
		}
		
		@unlink($this->file);
		
		$exception = new fProgrammerException(
			'The action requested can not be performed because the file has been deleted'
		);
		fFilesystem::updateExceptionMap($this->file, $exception);
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
			throw new fProgrammerException(
				"The new directory specified, %s, is the same as the current file's directory",
				$new_directory->getPath()
			);
		}
		
		$new_filename = $new_directory->getPath() . $this->getFilename();
		
		$check_dir_permissions = FALSE;
		
		if (file_exists($new_filename)) {
			if (!is_writable($new_filename)) {
				throw new fEnvironmentException(
					'The new directory specified, %1$s, already contains a file with the name %2$s, but it is not writable',
					$new_directory,
					$this->getFilename()
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
				throw new fEnvironmentException(
					'The new directory specified, %s, is not writable',
					$new_directory
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
	 * Gets the file's mime type
	 * 
	 * This method will attempt to look at the file contents and the file
	 * extension to determine the mime type. If the file contains binary
	 * information, the contents will be used for mime type verification,
	 * however if the contents appear to be plain text, the file extension
	 * will be used.
	 * 
	 * The following mime types are supported. All other binary file types
	 * will be returned as `application/octet-stream` and all other text files
	 * will be returned as `text/plain`.
	 * 
	 * **Archive:**
	 * 
	 *  - `application/x-bzip2` BZip2 file
	 *  - `application/x-compress` Compress (*nix) file
	 *  - `application/x-gzip` GZip file
	 *  - `application/x-rar-compressed` Rar file
	 *  - `application/x-stuffit` StuffIt file
	 *  - `application/x-tar` Tar file
	 *  - `application/zip` Zip file
	 * 
	 * **Audio:**
	 * 
	 *  - `audio/x-flac` FLAC audio
	 *  - `audio/mpeg` MP3 audio
	 *  - `audio/mp4` MP4 (AAC) audio
	 *  - `audio/vorbis` Ogg Vorbis audio
	 *  - `audio/x-wav` WAV audio
	 *  - `audio/x-ms-wma` Windows media audio
	 * 
	 * **Document:**
	 * 
	 *  - `application/vnd.ms-excel` Excel (2000, 2003 and 2007) file
	 *  - `application/pdf` PDF file
	 *  - `application/vnd.ms-powerpoint` Powerpoint (2000, 2003, 2007) file
	 *  - `text/rtf` RTF file
	 *  - `application/msword` Word (2000, 2003 and 2007) file
	 * 
	 * **Image:**
	 * 
	 *  - `image/x-ms-bmp` BMP file
	 *  - `application/postscript` EPS file
	 *  - `image/gif` GIF file
	 *  - `application/vnd.microsoft.icon` ICO file
	 *  - `image/jpeg` JPEG file
	 *  - `image/png` PNG file
	 *  - `image/tiff` TIFF file
	 *  - `image/svg+xml` SVG file
	 * 
	 * **Text:**
	 * 
	 *  - `text/css` CSS file
	 *  - `text/csv` CSV file
	 *  - `text/html` (X)HTML file
	 *  - `text/calendar` iCalendar file
	 *  - `application/javascript` Javascript file
	 *  - `application/x-perl` Perl file
	 *  - `application/x-httpd-php` PHP file
	 *  - `application/x-python` Python file
	 *  - `application/rss+xml` RSS feed
	 *  - `application/x-ruby` Ruby file
	 *  - `text/tab-separated-values` TAB file
	 *  - `text/x-vcard` VCard file
	 *  - `application/xhtml+xml` XHTML (Real) file
	 *  - `application/xml` XML file
	 * 
	 * **Video/Animation:**
	 * 
	 *  - `video/x-msvideo` AVI video
	 *  - `application/x-shockwave-flash` Flash movie
	 *  - `video/x-flv` Flash video
	 *  - `video/x-ms-asf` Microsoft ASF video
	 *  - `video/mp4` MP4 video
	 *  - `video/ogg` OGM and Ogg Theora video
	 *  - `video/quicktime` Quicktime video
	 *  - `video/x-ms-wmv` Windows media video
	 * 
	 * @return string  The mime type of the file
	 */
	public function getMimeType()
	{
		$this->tossIfException();
		
		return self::determineMimeType($this->file);	
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
			throw new fEnvironmentException(
				'The file, %s, can not be renamed because the directory containing it is not writable',
				$this->file
			);
		}
		
		$info = fFilesystem::getPathInfo($new_filename);
		
		if (!file_exists($info['dirname'])) {
			throw new fProgrammerException(
				'The new filename specified, %s, is inside of a directory that does not exist',
				$new_filename
			);
		}
		
		// Make the filename absolute
		$new_filename = fDirectory::makeCanonical(realpath($info['dirname'])) . $info['basename'];
		
		if (file_exists($new_filename)) {
			if (!is_writable($new_filename)) {
				throw new fEnvironmentException(
					'The new filename specified, %s, already exists, but is not writable',
					$new_filename
				);
			}
			if (!$overwrite) {
				$new_filename = fFilesystem::makeUniqueName($new_filename);
			}
		} else {
			$new_dir = new fDirectory($info['dirname']);
			if (!$new_dir->isWritable()) {
				throw new fEnvironmentException(
					'The new filename specified, %s, is inside of a directory that is not writable',
					$new_filename
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
			throw $this->exception;
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
			throw new fEnvironmentException(
				'This file, %s, can not be written to because it is not writable',
				$this->file
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