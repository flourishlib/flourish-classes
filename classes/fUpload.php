<?php
/**
 * Provides validation and movement of uploaded files
 * 
 * @copyright  Copyright (c) 2007-2009 Will Bond
 * @author     Will Bond [wb] <will@flourishlib.com>
 * @license    http://flourishlib.com/license
 * 
 * @package    Flourish
 * @link       http://flourishlib.com/fUpload
 * 
 * @version    1.0.0b7
 * @changes    1.0.0b7  Added ::filter() to allow for ignoring array file upload field entries that did not have a file uploaded [wb, 2009-10-06]
 * @changes    1.0.0b6  Updated ::move() to use the new fFilesystem::createObject() method [wb, 2009-01-21]
 * @changes    1.0.0b5  Removed some unnecessary error suppression operators from ::move() [wb, 2009-01-05]
 * @changes    1.0.0b4  Updated ::validate() so it properly handles upload max filesize specified in human-readable notation [wb, 2009-01-05]
 * @changes    1.0.0b3  Removed the dependency on fRequest [wb, 2009-01-05]
 * @changes    1.0.0b2  Fixed a bug with validating filesizes [wb, 2008-11-25]
 * @changes    1.0.0b   The initial implementation [wb, 2007-06-14]
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
		if (isset($_GET[$field]) && $_SERVER['REQUEST_METHOD'] != 'POST') {
			throw new fProgrammerException(
				'Missing method="post" attribute in form tag'
			);
		}
		
		if (isset($_POST[$field]) && (!isset($_SERVER['CONTENT_TYPE']) || stripos($_SERVER['CONTENT_TYPE'], 'multipart/form-data') === FALSE)) {
			throw new fProgrammerException(
				'Missing enctype="multipart/form-data" attribute in form tag'
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
			throw new fProgrammerException(
				'The field specified, %s, does not appear to be a file upload field',
				$field
			);
		}
		
		if (!is_array($_FILES[$field]['name'])) {
			throw new fProgrammerException(
				'The field specified, %s, does not appear to be an array file upload field',
				$field
			);
		}
		
		return sizeof($_FILES[$field]['name']);
	}
	
	
	/**
	 * Removes individual file upload entries from an array of file inputs in `$_FILES` when no file was uploaded
	 * 
	 * @param  string  $field  The field to filter
	 * @return array  The indexes of the files that were uploaded
	 */
	static public function filter($field)
	{
		$indexes = array();
		$columns = array('name', 'type', 'tmp_name', 'error', 'size');
		
		if (!self::count($field)) {
			return;	
		}
		
		foreach (array_keys($_FILES[$field]['name']) as $index) {
			if ($_FILES[$field]['error'][$index] == UPLOAD_ERR_NO_FILE) {
				foreach ($columns as $column) {
					unset($_FILES[$field][$column][$index]);
				}
			} else {
				$indexes[] = $index;
			}	
		}
		
		return $indexes;
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
	 * @internal
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
	 * @param  string $field  The field to get the file array for
	 * @param  mixed  $index  If the field is an array file upload field, use this to specify which array index to return
	 * @return array  The file info array from `$_FILES`
	 */
	private function extractFileUploadArray($field, $index=NULL)
	{
		if ($index === NULL) {
			return $_FILES[$field];
		}
		
		if (!is_array($_FILES[$field]['name'])) {
			throw new fProgrammerException(
				'The field specified, %s, does not appear to be an array file upload field',
				$field
			);
		}
		
		if (!isset($_FILES[$field]['name'][$index])) {
			throw new fProgrammerException(
				'The index specified, %1$s, is invalid for the field %2$s',
				$index,
				$field
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
	 * @throws fValidationException  When `$directory` is somehow invalid or ::validate() thows an exception
	 * 
	 * @param  string|fDirectory $directory  The directory to upload the file to
	 * @param  string            $field      The file upload field to get the file from
	 * @param  mixed             $index      If the field was an array file upload field, upload the file corresponding to this index
	 * @return fFile  An fFile (or fImage) object
	 */
	public function move($directory, $field, $index=NULL)
	{
		if (!is_object($directory)) {
			$directory = new fDirectory($directory);
		}
		
		if (!$directory->isWritable()) {
			throw new fProgrammerException(
				'The directory specified, %s, is not writable',
				$directory->getPath()
			);
		}
		
		if (!self::check($field)) {
			throw new fProgrammerException(
				'The field specified, %s, does not appear to be a file upload field',
				$field
			);
		}
		
		$file_array = $this->validate($field, $index);
		$file_name  = fFilesystem::makeURLSafe($file_array['name']);
		
		$file_name = $directory->getPath() . $file_name;
		if (!$this->enable_overwrite) {
			$file_name = fFilesystem::makeUniqueName($file_name);
		}
		
		if (!move_uploaded_file($file_array['tmp_name'], $file_name)) {
			throw new fEnvironmentException('There was an error moving the uploaded file');
		}
		
		if (!chmod($file_name, 0644)) {
			throw new fEnvironmentException('Unable to change permissions on the uploaded file');
		}
		
		return fFilesystem::createObject($file_name);
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
	 * @throws fValidationException  When no file is uploaded or the uploaded file violates the options set for this object
	 * 
	 * @param  string  $field  The field the file was uploaded through
	 * @param  mixed   $index  If the field was an array of file uploads, this specifies which one to validate
	 * @return void
	 */
	public function validate($field, $index=NULL)
	{
		if (!self::check($field)) {
			throw new fProgrammerException(
				'The field specified, %s, does not appear to be a file upload field',
				$field
			);
		}
		
		$file_array = $this->extractFileUploadArray($field, $index);
		
		// Do some validation of the file provided
		if (empty($file_array['name'])) {
			throw new fValidationException('Please upload a file');
		}
		
		if ($file_array['error'] == UPLOAD_ERR_FORM_SIZE || $file_array['error'] == UPLOAD_ERR_INI_SIZE) {
			$max_size = (!empty($_POST['MAX_FILE_SIZE'])) ? $_POST['MAX_FILE_SIZE'] : ini_get('upload_max_filesize');
			$max_size = (!is_numeric($max_size)) ? fFilesystem::convertToBytes($max_size) : $max_size;
			throw new fValidationException(
				'The file uploaded is over the limit of %s',
				fFilesystem::formatFilesize($max_size)
			);
		}
		if ($this->max_file_size && $file_array['size'] > $this->max_file_size) {
			throw new fValidationException(
				'The file uploaded is over the limit of %s',
				fFilesystem::formatFilesize($this->max_file_size)
			);
		}
		
		if (empty($file_array['tmp_name']) || empty($file_array['size'])) {
			throw new fValidationException('Please upload a file');	
		}
		
		if (!empty($this->mime_types) && file_exists($file_array['tmp_name']) && !in_array(fFile::determineMimeType($file_array['tmp_name']), $this->mime_types)) {
			throw new fValidationException($this->mime_type_message);
		}
		
		if (!$this->allow_php) {
			$file_info = fFilesystem::getPathInfo($file_array['name']);
			if (in_array(strtolower($file_info['extension']), array('php', 'php4', 'php5'))) {
				throw new fValidationException('The file uploaded is a PHP file, but those are not permitted');
			}
		}
		
		return $file_array;
	}
}



/**
 * Copyright (c) 2007-2009 Will Bond <will@flourishlib.com>
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