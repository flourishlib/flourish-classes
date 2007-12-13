<?php
/**
 * Provides file upload functionality
 * 
 * @copyright  Copyright (c) 2007 William Bond
 * @author     William Bond [wb] <will@flourishlib.com>
 * @license    http://flourishlib.com/license
 * 
 * @link  http://flourishlib.com/fUpload
 * 
 * @uses  fCore
 * @uses  fEnvironmentException
 * @uses  fFile
 * @uses  fInflection
 * @uses  fProgrammerException
 * @uses  fValidationException
 * 
 * @todo  Test this class
 * 
 * @version  1.0.0
 * @changes  1.0.0    The initial implementation [wb, 2007-06-14]
 */
class fUpload
{
	/**
	 * The type of files accepted
	 * 
	 * @var string 
	 */
	private $type = 'non_php';
	
	/**
	 * The mime types of files accepted
	 * 
	 * @var array 
	 */
	private $mime_types = '';
	
	/**
	 * The maximum file size in bytes
	 * 
	 * @var integer 
	 */
	private $max_file_size = 0;
	
	/**
	 * The overwrite method
	 * 
	 * @var string 
	 */
	private $overwrite_mode = 'rename';
	
	
	/**
	 * Set the overwrite mode, either 'rename' or 'overwrite'
	 * 
	 * @since  1.0.0
	 * 
	 * @param  string $mode   Either 'rename' or 'overwrite'
	 * @return void
	 */
	public function setOverwriteMode($mode) 
	{
		if (!in_array($mode, array('rename', 'overwrite'))) {
			fCore::toss('fProgrammerException', 'Invalid mode specified');       
		}
		$this->overwrite_mode = $mode;
	}
	
	
	/**
	 * Sets the file types accepted
	 * 
	 * @since  1.0.0
	 * 
	 * @param  string $type   'image', 'zip', 'non_php', 'any'
	 * @return void
	 */
	public function setType($type) 
	{
		if (!in_array($mode, array('image', 'zip', 'non_php', 'any'))) {
			fCore::toss('fProgrammerException', 'Invalid type specified');       
		}
		$this->type = $type;
		switch ($type) {
			case 'image':
				$this->setMimeTypes('image/pjpeg', 'image/jpeg', 'image/gif', 'image/png'); 
				break;
			case 'zip':
				$this->setMimeTypes('application/zip', 'application/x-zip-compressed'); 
				break;
			case 'non_php':
				$this->setMimeTypes(); 
				break;
			case 'any':
				$this->setMimeTypes(); 
				break;  
		}
	}
	
	
	/**
	 * Sets the file mime types accepted, one per parameter
	 * 
	 * @since  1.0.0
	 * 
	 * @param  string $mime_type   The mime type accepted 
	 * @return void
	 */
	public function setMimeTypes($mime_type) 
	{
		$mime_types = func_get_args();
		$this->mime_types = $mime_types;
	}
	
	
	/**
	 * Sets the file mime types accepted, one per parameter
	 * 
	 * @since  1.0.0
	 * 
	 * @param  string $size   The maximum file size (ex: 1MB, 200K, 10.5M), 0 for no limit 
	 * @return void
	 */
	public function setMaxFileSize($size) 
	{
		$this->max_file_size = fFile::convertToBytes($size);
	}
	
	
	/**
	 * Handles a file upload
	 * 
	 * @since  1.0.0
	 * 
	 * @throws  fValidationException
	 * 
	 * @param  string $mime_type   The mime type accepted 
	 * @return fFile|array  An fFile object or an array of fFile objects
	 */
	public function uploadFile($field, $directory) 
	{
		if (substr($directory, -1) != '/') {
			$directory .= '/';   
		}
		
		if (!file_exists($directory)) {
			fCore::toss('fProgrammerException', 'The directory specified does not exist');   
		}
		if (!is_dir($directory)) {
			fCore::toss('fProgrammerException', 'The directory specified is not a directory');
		}
		if (!is_writable($directory)) {
			fCore::toss('fProgrammerException', 'The directory specified is not writable');
		}
		
		if (stripos($_SERVER['CONTENT_TYPE'], 'multipart/form-data') === FALSE) {
			fCore::toss('fProgrammerException', 'Missing enctype="multipart/form-data" attribute in form tag');
		}
		if ($_SERVER['REQUEST_METHOD'] != 'POST') {
			fCore::toss('fProgrammerException', 'Missing method="POST" attribute in form tag');
		}
		if (!isset($_FILES) || !isset($_FILES[$field]) || !is_array($_FILES[$field])) {
			fCore::toss('fProgrammerException', 'The field specified does not appear to be a file upload field');
		}
		
		// Remove old temp files
		$this->cleanTempDirectory($directory);
		
		if (is_array($_FILES[$field]['name'])) {
			$output = array();
			for ($i=0; $i<sizeof($_FILES[$field]['name']); $i++) {
				$temp = array();
				$temp['name']     = (isset($_FILES[$field]['name'][$i]))     ? $_FILES[$field]['name'][$i]     : '';
				$temp['type']     = (isset($_FILES[$field]['type'][$i]))     ? $_FILES[$field]['type'][$i]     : '';
				$temp['tmp_name'] = (isset($_FILES[$field]['tmp_name'][$i])) ? $_FILES[$field]['tmp_name'][$i] : '';
				$temp['error']    = (isset($_FILES[$field]['error'][$i]))    ? $_FILES[$field]['error'][$i]    : '';
				$temp['size']     = (isset($_FILES[$field]['size'][$i]))     ? $_FILES[$field]['size'][$i]     : '';
				
				$output[] = $this->processFile($temp, $field . '_#' . ($i+1), $directory);
			}
			return $output;
			
		} else {
			return $this->processFile($_FILES[$field], $field, $directory);
		}
	}
	
	
	/**
	 * Handles file upload checking, puts file data into an object. Will also pull file name from __temp_field_name field.
	 * 
	 * @since  1.0.0
	 * 
	 * @param  array $file_array   The array of information from the $_FILES array 
	 * @param  string $field       The field the file was uploaded through
	 * @param  string $directory   The directory the file is being uploaded into
	 * @return void
	 */
	private function processFile($file_array, $field, $directory) 
	{
		try {
			if (empty($file_array['name']) || empty($file_array['tmp_name']) || empty($file_array['size'])) {
				fCore::toss('fValidationException', fInflection::humanize($field) . ': Please upload a file'); 
			}
			if ($this->max_file_size && $file_array['size'] > $this->max_file_size) {
				fCore::toss('fValidationException', fInflection::humanize($field) . ': The file uploaded is over the limit of ' . fFile::formatFilesize($this->max_file_size));   
			}
			if (!empty($this->mime_types) && !in_array($file_array['type'], $this->mime_types)) {
				if ($this->type != 'mime') {
					switch ($this->type) {
						case 'image':
							$message = 'The file uploaded is not an image';
							break;
						case 'zip':
							$message = 'The file uploaded is not a zip';
							break;   
					}
					fCore::toss('fValidationException', fInflection::humanize($field) . ': ' . $message);
				} else {
					fCore::toss('fValidationException', fInflection::humanize($field) . ': The file uploaded is not one of the following mime types: ' . join(', ', $this->mime_types));
				}    
			}
			if ($this->type == 'non_php') {
				$file_info = fFile::getInfo($file_array['name']);
				if ($file_info['extension'] == 'php') {
					fCore::toss('fValidationException', fInflection::humanize($field) . ': You are not allowed to upload a PHP file'); 
				}   
			}
			
			$file_name = fFile::createUniqueName($directory . $file_array['name']);
			if (!@move_uploaded_file($file_array['tmp_name'], $file_name)) {
				fCore::toss('fEnvironmentException', fInflection::humanize($field) . ': There was an error moving the uploaded file');    
			}
			
			return new fFile($file_name); 
			
		} catch (Exception $e) {
			// If no file was uploaded, check to see if a temp file was referenced
			if ($e->getCode() == 608 && !empty($_REQUEST['__temp_' . $field]) && file_exists($directory . fFile::TEMP_DIR . $_REQUEST['__temp_' . $field])) {
				$file_name = fFile::createUniqueName($directory . $_REQUEST['__temp_' . $field]);
				return new fFile($file_name);	
			}
			
			return new fFile(NULL, $e);
		}    
	}
	
	
	/**
	 * Handles cleaning out the temp directory for the directory specified. Removed all files over 6 hours old.
	 * 
	 * @since  1.0.0
	 * 
	 * @param  string $directory   The directory to clean the temp dir for
	 * @return void
	 */
	private function cleanTempDirectory($directory) 
	{
		$temp_directory = $directory . fFile::TEMP_DIR;
		$files = array_diff(scandir($temp_directory), array('.','..'));
		
		foreach ($files as $file) {
			if (filemtime($temp_directory . $file) < strtotime('-6 hours')) {
				unlink($temp_directory . $file);
			}
		}	
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