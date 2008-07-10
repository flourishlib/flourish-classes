<?php
/**
 * Provides file upload functionality
 * 
 * @copyright  Copyright (c) 2007-2008 William Bond
 * @author     William Bond [wb] <will@flourishlib.com>
 * @license    http://flourishlib.com/license
 * 
 * @package    Flourish
 * @link       http://flourishlib.com/fUpload
 * 
 * @version    1.0.0b
 * @changes    1.0.0b  The initial implementation [wb, 2007-06-14]
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
	static private $mime_types = array();
	
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
	 * Checks to see if the field specified is a valid file upload field
	 * 
	 * @param  string $field  The field to check
	 * @return boolean  If the field is a valid file upload field
	 */
	static public function check($field)
	{
		if (fRequest::check($field) && $_SERVER['REQUEST_METHOD'] != 'POST') {
			fCore::toss(
				'fProgrammerException',
				fGrammar::compose(
					'Missing method="post" attribute in form tag'
				)
			);
		}
		if (fRequest::check($field) && (!isset($_SERVER['CONTENT_TYPE']) || stripos($_SERVER['CONTENT_TYPE'], 'multipart/form-data') === FALSE)) {
			fCore::toss(
				'fProgrammerException',
				fGrammar::compose(
					'Missing enctype="multipart/form-data" attribute in form tag'
				)
			);
		}
		return isset($_FILES) && isset($_FILES[$field]) && is_array($_FILES[$field]);
	}
	
	
	/**
	 * Returns the number of files uploaded to a file upload array field 
	 * 
	 * @param  string  $field  The field to get the number of files for
	 * @return integer  The number of uploaded files
	 */
	static public function count($field)
	{
		if (!self::check($field)) {
			fCore::toss(
				'fProgrammerException',
				fGrammar::compose(
					'The field specified, %s, does not appear to be a file upload field',
					fCore::dump($field)
				)
			);
		}
		if (!is_array($_FILES[$field]['name'])) {
			fCore::toss(
				'fProgrammerException',
				fGrammar::compose(
					'The field specified, %s, does not appear to be an array file upload field',
					fCore::dump($field)
				)
			);	
		}
		
		return sizeof($_FILES[$field]['name']);
	}
	
	
	/**
	 * Returns the $_FILES array for the field specified. 
	 * 
	 * @param  string  $field  The field to get the file array for
	 * @param  integer $index  If the field is an array file upload field, use this to specify which array index to return
	 * @return array  The file info array from $_FILES
	 */
	static private function extractFileUploadArray($field, $index=NULL)
	{
		if ($index === NULL) {
			return $_FILES[$field];	
		}
		
		if (!is_array($_FILES[$field]['name'])) {
			fCore::toss(
				'fProgrammerException',
				fGrammar::compose(
					'The field specified, %s, does not appear to be an array file upload field',
					fCore::dump($field)
				)
			);	
		}
		if (!isset($_FILES[$field]['name'][$index])) {
			fCore::toss(
				'fProgrammerException',
				fGrammar::compose('The index specified, %s, is invalid for the field %s',
					fCore::dump($index),
					fCore::dump($field)
				)
			);
		}
		$file_array = array();
		$file_array['name']     = $_FILES[$field]['name'][$index];
		$file_array['type']     = $_FILES[$field]['type'][$index];
		$file_array['tmp_name'] = $_FILES[$field]['tmp_name'][$index];
		$file_array['error']    = $_FILES[$field]['error'][$index];
		$file_array['size']     = $_FILES[$field]['size'][$index];	
		
		return $file_array;
	}
	
	
	/**
	 * Resets the max file size, mime types, overwrite mode and type to default values
	 * 
	 * @return void
	 */
	static public function reset()
	{
		self::$max_file_size  = 0;
		self::$mime_types     = array();
		self::$overwrite_mode = 'rename';
		self::$type           = 'non_php';
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
		$valid_modes = array('rename', 'overwrite');
		if (!in_array($mode, $valid_modes)) {
			fCore::toss(
				'fProgrammerException',
				fGrammar::compose(
					'The mode specified, %s, is invalid. Must be one of: %s.',
					fCore::dump($mode),
					join(', ', $valid_modes)
				)
			);
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
		$valid_types = array('image', 'zip', 'non_php', 'any');
		if (!in_array($type, $valid_types)) {
			fCore::toss(
				'fProgrammerException',
				fGrammar::compose(
					'The type specified, %s, is invalid. Must be one of: %s.',
					fCore::dump($type),
					join(', ', $valid_types)
				)
			);
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
	 * @param  string|fDirectory $directory  The directory to upload the file to
	 * @param  string            $field      The file upload field to get the file from
	 * @param  integer           $index      If the field was an array file upload field, upload the file corresponding to this index
	 * @return fFile  An fFile object
	 */
	static public function upload($directory, $field, $index=NULL)
	{
		if (!is_object($directory)) {
			$directory = new fDirectory($directory);
		}
		
		if (!$directory->isWritable()) {
			fCore::toss(
				'fProgrammerException',
				fGrammar::compose(
					'The directory specified, %s, is not writable',
					fCore::dump($directory->getPath())
				)
			);
		}
		
		if (!self::check($field)) {
			fCore::toss(
				'fProgrammerException',
				fGrammar::compose(
					'The field specified, %s, does not appear to be a file upload field',
					fCore::dump($field)
				)
			);
		}
		
		$file_array = self::validate($field, $index);
		$file_name  = strtolower($file_array['name']);
		$file_name  = preg_replace('#\s+#', '_', $file_name);
		$file_name  = preg_replace('#[^a-z0-9_\.-]#', '', $file_name);
		$file_name  = fFilesystem::createUniqueName($directory->getPath() . $file_name);
		
		if (!@move_uploaded_file($file_array['tmp_name'], $file_name)) {
			fCore::toss(
				'fEnvironmentException',
				fGrammar::compose('There was an error moving the uploaded file')
			);
		}
		
		if (!@chmod($file_name, 0644)) {
			fCore::toss(
				'fEnvironmentException',
				fGrammar::compose('Unable to change permissions on the uploaded file')
			);
		}
		
		if (fImage::isImageCompatible($file_name)) {
			return new fImage($file_name);
		}
		return new fFile($file_name);
	}
	
	
	/**
	 * Validates the uploaded file, ensuring a file was actually uploaded and that is matched the restrictions put in place
	 * 
	 * @throws  fValidationException
	 * 
	 * @param  string  $field  The field the file was uploaded through
	 * @param  integer $index  If the field was an array of file uploads, this specifies which one to validate
	 * @return array  The $_FILES array for the field and index specified
	 */
	static public function validate($field, $index=NULL)
	{
		if (!self::check($field)) {
			fCore::toss(
				'fProgrammerException',
				fGrammar::compose(
					'The field specified, %s, does not appear to be a file upload field',
					fCore::dump($field)
				)
			); 		
		}
		
		$file_array = self::extractFileUploadArray($field, $index);
		
		// Do some validation of the file provided
		if (empty($file_array['name']) || empty($file_array['tmp_name']) || empty($file_array['size'])) {
			fCore::toss(
				'fValidationException',
				fGrammar::compose('Please upload a file')
			);
		}
		
		if (self::$max_file_size && $file_array['size'] > self::$max_file_size) {
			fCore::toss(
				'fValidationException',
				fGrammar::compose('The file uploaded is over the limit of ' . fFilesystem::formatFilesize(self::$max_file_size))
			);
		}
		
		if (!empty(self::$mime_types) && !in_array($file_array['type'], self::$mime_types)) {
			if (self::$type != 'mime') {
				$messages = array(
					'image' => fGrammar::compose('The file uploaded is not an image'),
					'zip'   => fGrammar::compose('The file uploaded is not a zip')
				);
				fCore::toss('fValidationException', $messages[$type]);
			} else {
				fCore::toss(
					'fValidationException',
					fGrammar::compose(
						'The file uploaded is an invalid type. It is a %s file, but must be one of %s.',
						fCore::dump($file_array['type']),
						join(', ', self::$mime_types)
					)
				);
			}
		}
		
		if (self::$type == 'non_php') {
			$file_info = fFilesystem::getPathInfo($file_array['name']);
			if ($file_info['extension'] == 'php') {
				fCore::toss(
					'fValidationException',
					fGrammar::compose('The file uploaded is a PHP file, but those are not permitted')
				);
			}
		}
		
		return $file_array;
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