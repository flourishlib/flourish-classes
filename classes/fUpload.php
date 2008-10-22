<?php
/**
 * Provides validation and movement of uploaded files
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
	// The following constants allow for nice looking callbacks to static methods
	const check = 'fUpload::check';
	const count = 'fUpload::count';
	
	
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
	 * The type of files accepted
	 * 
	 * @var string
	 */
	private $allow_php = FALSE;
	
	/**
	 * If existing files of the same name should be overwritten
	 * 
	 * @var boolean
	 */
	private $enable_overwrite = FALSE;
	
	/**
	 * The maximum file size in bytes
	 * 
	 * @var integer
	 */
	private $max_file_size = 0;
	
	/**
	 * The error message to display if the mime types do not match
	 * 
	 * @var string
	 */
	private $mime_type_message = NULL;
	
	/**
	 * The mime types of files accepted
	 * 
	 * @var array
	 */
	private $mime_types = array();
	
	
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
	 * Sets the upload class to allow PHP files
	 * 
	 * @return void
	 */
	public function allowPHP()
	{
		$this->allow_php = TRUE;
	}
	
	
	/**
	 * Set the class to overwrite existing files in the destination directory instead of renaming the file
	 * 
	 * @return void
	 */
	public function enableOverwrite()
	{
		$this->enable_overwrite = TRUE;
	}
	
	
	/**
	 * Returns the `$_FILES` array for the field specified.
	 * 
	 * @param  string  $field  The field to get the file array for
	 * @param  integer $index  If the field is an array file upload field, use this to specify which array index to return
	 * @return array  The file info array from `$_FILES`
	 */
	private function extractFileUploadArray($field, $index=NULL)
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
				fGrammar::compose('The index specified, %1$s, is invalid for the field %2$s',
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
	 * Moves an uploaded file from the temp directory to a permanent location
	 * 
	 * @throws fValidationException
	 * 
	 * @param  string|fDirectory $directory  The directory to upload the file to
	 * @param  string            $field      The file upload field to get the file from
	 * @param  integer           $index      If the field was an array file upload field, upload the file corresponding to this index
	 * @return fFile  An fFile (or fImage) object
	 */
	public function move($directory, $field, $index=NULL)
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
		
		$file_array = $this->validate($field, $index);
		$file_name  = strtolower($file_array['name']);
		$file_name  = preg_replace('#\s+#', '_', $file_name);
		$file_name  = preg_replace('#[^a-z0-9_\.-]#', '', $file_name);
		
		$file_name = $directory->getPath() . $file_name;
		if (!$this->enable_overwrite) {
			$file_name = fFilesystem::makeUniqueName($file_name);
		}
		
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
	 * Sets the file mime types accepted, one per parameter
	 * 
	 * @param  string $size  The maximum file size (e.g. `1MB`, `200K`, `10.5M`) - `0` for no limit
	 * @return void
	 */
	public function setMaxFileSize($size)
	{
		$this->max_file_size = fFilesystem::convertToBytes($size);
	}
	
	
	/**
	 * Sets the file mime types accepted
	 * 
	 * @param  array  $mime_types  The mime types to accept
	 * @param  string $message     The message to display if the uploaded file is not one of the mime type specified
	 * @return void
	 */
	public function setMIMETypes($mime_types, $message)
	{
		$this->mime_types        = $mime_types;
		$this->mime_type_message = $message;
	}
	
	
	/**
	 * Validates the uploaded file, ensuring a file was actually uploaded and that is matched the restrictions put in place
	 * 
	 * @throws fValidationException
	 * 
	 * @param  string  $field  The field the file was uploaded through
	 * @param  integer $index  If the field was an array of file uploads, this specifies which one to validate
	 * @return void
	 */
	public function validate($field, $index=NULL)
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
		
		$file_array = $this->extractFileUploadArray($field, $index);
		
		// Do some validation of the file provided
		if (empty($file_array['name']) || empty($file_array['tmp_name']) || empty($file_array['size'])) {
			fCore::toss(
				'fValidationException',
				fGrammar::compose('Please upload a file')
			);
		}
		
		if ($file_array['error'] == UPLOAD_ERR_FORM_SIZE) {
			$max_size = (fRequest::get('MAX_FILE_SIZE')) ? fRequest::get('MAX_FILE_SIZE') : ini_get('upload_max_filesize');
			fCore::toss(
				'fValidationException',
				fGrammar::compose('The file uploaded is over the limit of ' . fFilesystem::formatFilesize($max_size))
			);
		}
		if ($this->max_file_size && $file_array['size'] > $this->max_file_size) {
			fCore::toss(
				'fValidationException',
				fGrammar::compose('The file uploaded is over the limit of ' . fFilesystem::formatFilesize($this->max_file_size))
			);
		}
		
		if (!empty($this->mime_types) && !in_array($file_array['type'], $this->mime_types)) {
			fCore::toss('fValidationException', $this->mime_type_message);
		}
		
		if (!$this->allow_php) {
			$file_info = fFilesystem::getPathInfo($file_array['name']);
			if (in_array(strtolower($file_info['extension']), array('php', 'php4', 'php5'))) {
				fCore::toss(
					'fValidationException',
					fGrammar::compose('The file uploaded is a PHP file, but those are not permitted')
				);
			}
		}
		
		return $file_array;
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