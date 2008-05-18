<?php
/**
 * Provides file upload functionality
 * 
 * @copyright  Copyright (c) 2007-2008 William Bond
 * @author     William Bond [wb] <will@flourishlib.com>
 * @license    http://flourishlib.com/license
 * 
 * @link  http://flourishlib.com/fUpload
 * 
 * @version  1.0.0
 * @changes  1.0.0    The initial implementation [wb, 2007-06-14]
 */
class fUpload
{
	/**
	 * The maximum file size in bytes
	 * 
	 * @var integer
	 */
	static private $max_file_size = 0;
	
	/**
	 * The mime types of files accepted
	 * 
	 * @var array
	 */
	static private $mime_types = '';
	
	/**
	 * The overwrite method
	 * 
	 * @var string
	 */
	static private $overwrite_mode = 'rename';
	
	/**
	 * The type of files accepted
	 * 
	 * @var string
	 */
	static private $type = 'non_php';
	
	
	/**
	 * Handles file upload checking, puts file data into an object. Will also pull file name from __temp_field_name field.
	 * 
	 * @throws  fValidationException
	 * 
	 * @param  string $file_name  The path to the file on the filesystem
	 * @return void
	 */
	static private function createObject($file_name)
	{
		try {
			fImage::verifyImageCompatible($file_name);
			return new fImage($file_name);
			
		} catch (fPrintableException $e) {
			return new fFile($file_name);
		}
	}
	
	
	/**
	 * Handles file upload checking, puts file data into an object. Will also pull file name from __temp_field_name field.
	 * 
	 * @throws  fValidationException
	 * 
	 * @param  array      $file_array  The array of information from the $_FILES array
	 * @param  string     $field       The field the file was uploaded through
	 * @param  fDirectory $directory   The directory the file is being uploaded into
	 * @return void
	 */
	static private function processFile($file_array, $field, $directory)
	{
		try {
			// Do some validation of the file provided
			if (empty($file_array['name']) || empty($file_array['tmp_name']) || empty($file_array['size'])) {
				fCore::toss('fValidationException', fInflection::humanize($field) . ': Please upload a file');
			}
			if (self::$max_file_size && $file_array['size'] > self::$max_file_size) {
				fCore::toss('fValidationException', fInflection::humanize($field) . ': The file uploaded is over the limit of ' . fFilesystem::formatFilesize(self::$max_file_size));
			}
			if (!empty(self::$mime_types) && !in_array($file_array['type'], self::$mime_types)) {
				if (self::$type != 'mime') {
					switch (self::$type) {
						case 'image':
							$message = 'The file uploaded is not an image';
							break;
						case 'zip':
							$message = 'The file uploaded is not a zip';
							break;
					}
					fCore::toss('fValidationException', fInflection::humanize($field) . ': ' . $message);
				} else {
					fCore::toss('fValidationException', fInflection::humanize($field) . ': The file uploaded is not one of the following mime types: ' . join(', ', self::$mime_types));
				}
			}
			if (self::$type == 'non_php') {
				$file_info = fFilesystem::getPathInfo($file_array['name']);
				if ($file_info['extension'] == 'php') {
					fCore::toss('fValidationException', fInflection::humanize($field) . ': You are not allowed to upload a PHP file');
				}
			}
			
			$file_name = fFilesystem::createUniqueName($directory->getPath() . $file_array['name']);
			if (!@move_uploaded_file($file_array['tmp_name'], $file_name)) {
				fCore::toss('fEnvironmentException', fInflection::humanize($field) . ': There was an error moving the uploaded file');
			}
			
			return self::createObject($file_name);
			
		} catch (Exception $e) {
			// If no file was uploaded, check to see if a temp file was referenced
			$temp_field = '__temp_' . $field;
			if ($e->getMessage() == fInflection::humanize($field) . ': Please upload a file' && !empty($_REQUEST[$temp_field]) && file_exists($directory->getTemp()->getPath() . $_REQUEST[$temp_field])) {
				$file_name = fFilesystem::createUniqueName($directory->getPath() . $_REQUEST[$temp_field]);
				rename($directory->getTemp()->getPath() . $_REQUEST[$temp_field], $file_name);
				return self::createObject($file_name);
			}
			
			return new fFile(NULL, $e);
		}
	}
	
	
	/**
	 * Sets the file mime types accepted, one per parameter
	 * 
	 * @param  string $size  The maximum file size (ex: 1MB, 200K, 10.5M), 0 for no limit
	 * @return void
	 */
	static public function setMaxFileSize($size)
	{
		self::$max_file_size = fFilesystem::convertToBytes($size);
	}
	
	
	/**
	 * Sets the file mime types accepted, one per parameter
	 * 
	 * @param  string $mime_type,...  The mime type accepted
	 * @return void
	 */
	static public function setMimeTypes()
	{
		self::$mime_types = func_get_args();
	}
	
	
	/**
	 * Set the overwrite mode, either 'rename' or 'overwrite'
	 * 
	 * @param  string $mode  Either 'rename' or 'overwrite'
	 * @return void
	 */
	static public function setOverwriteMode($mode)
	{
		if (!in_array($mode, array('rename', 'overwrite'))) {
			fCore::toss('fProgrammerException', 'Invalid mode specified');
		}
		self::$overwrite_mode = $mode;
	}
	
	
	/**
	 * Sets the file types accepted
	 * 
	 * @param  string $type  'image', 'zip', 'non_php', 'any'
	 * @return void
	 */
	static public function setType($type)
	{
		if (!in_array($mode, array('image', 'zip', 'non_php', 'any'))) {
			fCore::toss('fProgrammerException', 'Invalid type specified');
		}
		self::$type = $type;
		switch ($type) {
			case 'image':
				call_user_func_array(array('fUpload', 'setMimeTypes'), fImage::getCompatibleMimetypes());
				break;
			case 'zip':
				self::setMimeTypes('application/zip', 'application/gzip', 'application/x-zip-compressed');
				break;
			case 'non_php':
				self::setMimeTypes();
				break;
			case 'any':
				self::setMimeTypes();
				break;
		}
	}
	
	
	/**
	 * Handles a file upload
	 * 
	 * @throws  fValidationException
	 * 
	 * @param  string            $field      The file upload field to get the file(s) from
	 * @param  string|fDirectory $directory  The directory to upload the file to
	 * @return fFile|array  An fFile object or an array of fFile objects
	 */
	static public function uploadFile($field, $directory)
	{
		if (!is_object($directory)) {
			$directory = new fDirectory($directory);
		}
		
		if (!$directory->isWritable()) {
			fCore::toss('fProgrammerException', 'The directory specified is not writable');
		}
		if ($_SERVER['REQUEST_METHOD'] != 'POST') {
			fCore::toss('fProgrammerException', 'Missing method="POST" attribute in form tag');
		}
		if (!isset($_SERVER['CONTENT_TYPE']) || stripos($_SERVER['CONTENT_TYPE'], 'multipart/form-data') === FALSE) {
			fCore::toss('fProgrammerException', 'Missing enctype="multipart/form-data" attribute in form tag');
		}
		if (!isset($_FILES) || !isset($_FILES[$field]) || !is_array($_FILES[$field])) {
			fCore::toss('fProgrammerException', 'The field specified does not appear to be a file upload field');
		}
		
		// Remove old temp files
		$directory->getTemp()->clean();
		
		if (is_array($_FILES[$field]['name'])) {
			$output = array();
			for ($i=0; $i<sizeof($_FILES[$field]['name']); $i++) {
				$temp = array();
				$temp['name']     = (isset($_FILES[$field]['name'][$i]))     ? $_FILES[$field]['name'][$i]     : '';
				$temp['type']     = (isset($_FILES[$field]['type'][$i]))     ? $_FILES[$field]['type'][$i]     : '';
				$temp['tmp_name'] = (isset($_FILES[$field]['tmp_name'][$i])) ? $_FILES[$field]['tmp_name'][$i] : '';
				$temp['error']    = (isset($_FILES[$field]['error'][$i]))    ? $_FILES[$field]['error'][$i]    : '';
				$temp['size']     = (isset($_FILES[$field]['size'][$i]))     ? $_FILES[$field]['size'][$i]     : '';
				
				$output[] = self::processFile($temp, $field . '_#' . ($i+1), $directory);
			}
			return $output;
			
		} else {
			return self::processFile($_FILES[$field], $field, $directory);
		}
	}
	
	
	/**
	 * Forces use as a static class
	 * 
	 * @return fUpload
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