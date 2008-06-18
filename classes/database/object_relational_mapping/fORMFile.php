<?php
/**
 * Provides file manipulation functionality for {@link fActiveRecord} classes
 * 
 * @copyright  Copyright (c) 2008 William Bond
 * @author     William Bond [wb] <will@flourishlib.com>
 * @license    http://flourishlib.com/license
 * 
 * @link  http://flourishlib.com/fORMFile
 * 
 * @version  1.0.0
 * @changes  1.0.0    The initial implementation [wb, 2008-05-28]
 */
class fORMFile
{	
	/**
	 * The temporary directory to use for various tasks
	 * 
	 * @internal
	 * 
	 * @var string
	 */
	const TEMP_DIRECTORY = '__flourish_temp/';
	
	
	/**
	 * Defines how columns can inherit uploaded files
	 * 
	 * @var array
	 */
	static private $column_inheritence = array();
	
	/**
	 * Methods to be called on fUpload before the file is uploaded
	 * 
	 * @var array
	 */
	static private $fupload_method_calls = array();
	
	/**
	 * Columns that can be filled by file uploads
	 * 
	 * @var array
	 */
	static private $file_upload_columns = array();
	
	/**
	 * Methods to be called on the fImage instance
	 * 
	 * @var array
	 */
	static private $fimage_method_calls = array();
	
	/**
	 * Columns that can be filled by image uploads
	 * 
	 * @var array
	 */
	static private $image_upload_columns = array();
	
	/**
	 * Keeps track of the nesting level of the filesystem transaction so we know when to start, commit, rollback, etc
	 * 
	 * @var integer
	 */
	static private $transaction_level = 0;
	
	
	/**
	 * Adds an fImage method call to the image manipulation for a column if an image file is uploaded
	 * 
	 * @param  mixed  $class       The class name or instance of the class
	 * @param  string $column      The column to call the method for
	 * @param  string $method      The fImage method to call
	 * @param  array  $parameters  The parameters to pass to the method
	 * @return void
	 */
	static public function addFImageMethodCall($class, $column, $method, $parameters=array())
	{
		$class = fORM::getClassName($class);
		
		if (!array_key_exists($column, self::$image_upload_columns[$class])) {
			fCore::toss('fProgrammerException', 'The column specified, ' . $column . ', has not been configured as an image upload column.');	
		}
		
		if (empty(self::$fimage_method_calls[$class])) {
			self::$fimage_method_calls[$class] = array(); 		
		}
		if (empty(self::$fimage_method_calls[$class][$column])) {
			self::$fimage_method_calls[$class][$column] = array(); 		
		}
		
		self::$fimage_method_calls[$class][$column][] = array(
			'method'     => $method,
			'parameters' => $parameters
		);
	}
	
	
	/**
	 * Adds an fUpload method call to the fUpload initialization for a column
	 * 
	 * @param  mixed  $class       The class name or instance of the class
	 * @param  string $column      The column to call the method for
	 * @param  string $method      The fUpload method to call
	 * @param  array  $parameters  The parameters to pass to the method
	 * @return void
	 */
	static public function addFUploadMethodCall($class, $column, $method, $parameters=array())
	{
		$class = fORM::getClassName($class);
		
		if (empty(self::$file_upload_columns[$class][$column])) {
			fCore::toss('fProgrammerException', 'The column specified, ' . $column . ', has not been configured as a file or image upload column.');	
		}
		
		if (empty(self::$fupload_method_calls[$class])) {
			self::$fupload_method_calls[$class] = array(); 		
		}
		if (empty(self::$fupload_method_calls[$class][$column])) {
			self::$fupload_method_calls[$class][$column] = array(); 		
		}
		
		self::$fupload_method_calls[$class][$column][] = array(
			'callback'   => array('fUpload', $method),
			'parameters' => $parameters
		);
	}
	
	
	/**
	 * Begins a transaction, or increases the level
	 * 
	 * @internal
	 * 
	 * @return void
	 */
	static public function begin()
	{
		// If the transaction was started by something else, don't even track it
		if (self::$transaction_level == 0 && fFilesystem::isInsideTransaction()) {
			return; 		
		}
		
		self::$transaction_level++;
		fFilesystem::begin();
	}
	
	
	/**
	 * Commits a transaction, or decreases the level
	 * 
	 * @internal
	 * 
	 * @return void
	 */
	static public function commit()
	{
		// If the transaction was started by something else, don't even track it
		if (self::$transaction_level == 0) {
			return; 		
		}
		
		self::$transaction_level--;
		
		if (!self::$transaction_level) {
			fFilesystem::commit();
		}
	}
	
	
	/**
	 * Sets a column to be a file upload column
	 * 
	 * @param  mixed             $class      The class name or instance of the class
	 * @param  string            $column     The column to set as a file upload column
	 * @param  fDirectory|string $directory  The directory to upload to
	 * @return void
	 */
	static public function configureFileUploadColumn($class, $column, $directory)
	{
		$class     = fORM::getClassName($class);
		$table     = fORM::tablize($class);
		$data_type = fORMSchema::getInstance()->getColumnInfo($table, $column, 'type');
		
		$valid_data_types = array('varchar', 'char', 'text');
		if (!in_array($data_type, $valid_data_types)) {
			fCore::toss('fProgrammerException', 'The column specified, ' . $column . ', is a ' . $data_type . ' column. Must be one of ' . join(', ', $valid_data_types) . ' to be set as a file upload column.');	
		}
		
		if (!is_object($directory)) {
			$directory = new fDirectory($directory);	
		}
		
		if (!$directory->isWritable()) {
			fCore::toss('fEnvironmentException', 'The file upload directory, ' . $directory->getPath() . ', is not writable');	
		}
		
		$camelized_column = fInflection::camelize($column, TRUE);
		
		$hook     = 'replace::upload' . $camelized_column . '()';
		$callback = array('fORMFile', 'upload');
		fORM::registerHookCallback($class, $hook, $callback);
		
		$hook     = 'replace::set' . $camelized_column . '()';
		$callback = array('fORMFile', 'set');
		fORM::registerHookCallback($class, $hook, $callback);
		
		$hook     = 'replace::encode' . $camelized_column . '()';
		$callback = array('fORMFile', 'encode');
		fORM::registerHookCallback($class, $hook, $callback);
		
		$hook     = 'replace::prepare' . $camelized_column . '()';
		$callback = array('fORMFile', 'prepare');
		fORM::registerHookCallback($class, $hook, $callback);
		
		$callback = array('fORMFile', 'objectify');
		fORM::registerObjectifyCallback($class, $column, $callback);
		
		$only_once_hooks = array(
			'post-begin::delete()'    => array('fORMFile', 'begin'),
			'pre-commit::delete()'    => array('fORMFile', 'delete'),
			'post-commit::delete()'   => array('fORMFile', 'commit'),
			'post-rollback::delete()' => array('fORMFile', 'rollback'),
			'post::populate()'        => array('fORMFile', 'populate'),
			'post-begin::store()'     => array('fORMFile', 'begin'),
			'post-validate::store()'  => array('fORMFile', 'moveFromTemp'),
			'pre-commit::store()'     => array('fORMFile', 'deleteOld'),
			'post-commit::store()'    => array('fORMFile', 'commit'),
			'post-rollback::store()'  => array('fORMFile', 'rollback'),
			'post::validate()'        => array('fORMFile', 'validate')
		);
		
		foreach ($only_once_hooks as $hook => $callback) {
			if (!fORM::checkHookCallback($class, $hook, $callback)) {
				fORM::registerHookCallback($class, $hook, $callback);	
			}
		}
		
		if (empty(self::$file_upload_columns[$class])) {
			self::$file_upload_columns[$class] = array();	
		}
		
		self::$file_upload_columns[$class][$column] = $directory;
	}
	
	
	/**
	 * Sets a column to be a date created column
	 * 
	 * @param  mixed             $class       The class name or instance of the class
	 * @param  string            $column      The column to set as a file upload column
	 * @param  fDirectory|string $directory   The directory to upload to
	 * @param  string            $image_type  The image type to save the image as. Valid: {null}, 'gif', 'jpg', 'png'
	 * @return void
	 */
	static public function configureImageUploadColumn($class, $column, $directory, $image_type=NULL)
	{
		$valid_image_types = array(NULL, 'gif', 'jpg', 'png');
		if (!in_array($image_type, $valid_image_types)) {
			$valid_image_types[0] = '{null}';
			fCore::toss('fProgrammerException', 'The image type specified, ' . $image_type . ', is not valid. Must be one of: ' . join(', ', $valid_image_types) . '.');	
		}
		
		self::configureFileUploadColumn($class, $column, $directory);
		
		$class = fORM::getClassName($class);
		
		if (empty(self::$image_upload_columns[$class])) {
			self::$image_upload_columns[$class] = array();	
		}
		
		self::$image_upload_columns[$class][$column] = $image_type;
		
		self::addFUploadMethodCall($class, $column, 'setType', array('image'));
	}
	
	
	/**
	 * Deletes the files for this record
	 * 
	 * @internal
	 * 
	 * @param  fActiveRecord $class                 The instance of the class
	 * @param  array         &$values               The current values
	 * @param  array         &$old_values           The old values
	 * @param  array         &$related_records      Any records related to this record
	 * @param  boolean       $debug                 If debug messages should be shown
	 * @return void
	 */
	static public function delete($class, &$values, &$old_values, &$related_records, $debug)
	{
		$class = fORM::getClassName($class);
		
		foreach (self::$file_upload_columns[$class] as $column => $directory) {
			
			// Remove the current file for the column
			if ($values[$column] instanceof fFile) {
				$values[$column]->delete();	
			}
			
			// Remove the old files for the column
			if (isset($old_values[$column])) {
				settype($old_values[$column], 'array');
				foreach ($old_values[$column] as $file) {
					if ($file instanceof fFile) {
						$file->delete();
					}
				}
			}
			
		}
	}
	
	
	/**
	 * Deletes old files for this record that have been replaced
	 * 
	 * @internal
	 * 
	 * @param  fActiveRecord $class                 The instance of the class
	 * @param  array         &$values               The current values
	 * @param  array         &$old_values           The old values
	 * @param  array         &$related_records      Any records related to this record
	 * @param  boolean       $debug                 If debug messages should be shown
	 * @return void
	 */
	static public function deleteOld($class, &$values, &$old_values, &$related_records, $debug)
	{
		$class = fORM::getClassName($class);
		
		foreach (self::$file_upload_columns[$class] as $column => $directory) {
			
			// Remove the old files for the column
			if (isset($old_values[$column])) {
				settype($old_values[$column], 'array');
				foreach ($old_values[$column] as $file) {
					if ($file instanceof fFile) {
						$file->delete();
					}
				}
			}
			
		}
	}
	
	
	/**
	 * Encodes a file for output into an HTML input tag
	 * 
	 * @internal
	 * 
	 * @param  fActiveRecord $class             The instance of the class
	 * @param  array         &$values           The current values
	 * @param  array         &$old_values       The old values
	 * @param  array         &$related_records  Any records related to this record
	 * @param  boolean       $debug             If debug messages should be shown
	 * @param  string        &$method_name      The method that was called
	 * @param  array         &$parameters       The parameters passed to the method
	 * @return void
	 */
	static public function encode($class, &$values, &$old_values, &$related_records, $debug, &$method_name, &$parameters)
	{
		list ($action, $column) = explode('_', fInflection::underscorize($method_name), 2);
		
		$filename = ($values[$column] instanceof fFile) ? $values[$column]->getFilename() : NULL;
		
		return fHTML::encode($filename);
	}
	
	
	/**
	 * Moves uploaded file from the temporary directory to the permanent directory
	 * 
	 * @internal
	 * 
	 * @param  fActiveRecord $class             The instance of the class
	 * @param  array         &$values           The current values
	 * @param  array         &$old_values       The old values
	 * @param  array         &$related_records  Any records related to this record
	 * @param  boolean       $debug             If debug messages should be shown
	 * @return void
	 */
	static public function moveFromTemp($class, &$values, &$old_values, &$related_records, $debug)
	{
		$class = fORM::getClassName($class);
		
		foreach ($values as $column => $value) {
			if (!$value instanceof fFile) {
				continue;
			}	
			
			// If the file is in a temp dir, move it out
			if (stripos($value->getDirectory()->getPath(), self::TEMP_DIRECTORY) !== FALSE) {
				$value->rename(str_replace(self::TEMP_DIRECTORY, '', $value->getPath()));	
			}
		}
	}
	
	
	/**
	 * Turns a filename into an fFile or fImage object
	 * 
	 * @internal
	 * 
	 * @param  string $class   The class this value is for
	 * @param  string $column  The column the value is in
	 * @param  mixed  $value   The value
	 * @return mixed  The fFile, fImage or raw value
	 */
	static public function objectify($class, $column, $value)
	{
		$class = fORM::getClassName($class);
		
		$path = self::$file_upload_columns[$class][$column]->getPath() . $value;
		
		try {
			
			if (fImage::isImageCompatible($path)) {
				return new fImage($path);	
			}
			
			return new fFile($path);
			 
		// If there was some error creating the file, just return the raw value
		} catch (fExpectedException $e) {
			return $value;	
		}
	}
	
	
	/**
	 * Performs the upload action for file uploads during populate() of fActiveRecord
	 * 
	 * @internal
	 * 
	 * @param  fActiveRecord $class             The instance of the class
	 * @param  array         &$values           The current values
	 * @param  array         &$old_values       The old values
	 * @param  array         &$related_records  Any records related to this record
	 * @param  boolean       $debug             If debug messages should be shown
	 * @return void
	 */
	static public function populate($class, &$values, &$old_values, &$related_records, $debug)
	{
		$class_name = fORM::getClassName($class);
		
		foreach (self::$file_upload_columns[$class_name] as $column => $directory) {
			if (fUpload::check($column)) {
				$method = 'upload' . fInflection::camelize($column, TRUE);
				$class->$method();
			}
		}
	}
	
	
	/**
	 * Prepares a file for output into HTML by returning the web server path to the file
	 * 
	 * @internal
	 * 
	 * @param  fActiveRecord $class             The instance of the class
	 * @param  array         &$values           The current values
	 * @param  array         &$old_values       The old values
	 * @param  array         &$related_records  Any records related to this record
	 * @param  boolean       $debug             If debug messages should be shown
	 * @param  string        &$method_name      The method that was called
	 * @param  array         &$parameters       The parameters passed to the method
	 * @return void
	 */
	static public function prepare($class, &$values, &$old_values, &$related_records, $debug, &$method_name, &$parameters)
	{
		list ($action, $column) = explode('_', fInflection::underscorize($method_name), 2);
		
		$doc_root    = realpath($_SERVER['DOCUMENT_ROOT']);
		$path        = ($values[$column] instanceof fFile) ? $values[$column]->getPath() : NULL;
		$server_path = preg_replace('#^' . preg_quote($doc_root, '#') . '#', '', $path);
		
		return fHTML::prepare($server_path);
	}
	
	
	/**
	 * Performs image manipulation on an uploaded image
	 * 
	 * @internal
	 * 
	 * @param  string $class   The name of the class we are manipulating the image for
	 * @param  string $column  The column the image is assigned to
	 * @param  fFile  $image   The image object to manipulate
	 * @return void
	 */
	static public function processImage($class, $column, $image)
	{
		// If we don't have an image or we haven't set it up to manipulate images, just exit
		if (!$image instanceof fImage || !array_key_exists($column, self::$fupload_method_calls[$class])) {
			return;	
		}
		
		// Manipulate the image
		if (!empty(self::$fimage_method_calls[$class][$column])) {
			foreach (self::$fimage_method_calls[$class][$column] as $method_call) {
				$callback   = array($image, $method_call['method']);
				$parameters = $method_call['parameters'];
				call_user_func_array($callback, $parameters);
			}	
		}
		
		// Save the changes
		$callback   = array($image, 'saveChanges');
		$parameters = array(self::$fimage_method_calls[$class][$column]);
		call_user_func_array($callback, $parameters);
	}
	
	
	/**
	 * Rolls back a transaction, or decreases the level
	 * 
	 * @internal
	 * 
	 * @return void
	 */
	static public function rollback()
	{
		// If the transaction was started by something else, don't even track it
		if (self::$transaction_level == 0) {
			return; 		
		}
		
		self::$transaction_level--;
		
		if (!self::$transaction_level) {
			fFilesystem::rollback();
		}
	}
	
	
	/**
	 * Copies a file from the filesystem to the file upload directory and sets it as the file for the specified column 
	 * 
	 * @internal
	 * 
	 * @param  fActiveRecord $class             The instance of the class
	 * @param  array         &$values           The current values
	 * @param  array         &$old_values       The old values
	 * @param  array         &$related_records  Any records related to this record
	 * @param  boolean       $debug             If debug messages should be shown
	 * @param  string        &$method_name      The method that was called
	 * @param  array         &$parameters       The parameters passed to the method
	 * @return void
	 */
	static public function set($class, &$values, &$old_values, &$related_records, $debug, &$method_name, &$parameters)
	{
		$class = fORM::getClassName($class);
		
		list ($action, $column) = explode('_', fInflection::underscorize($method_name), 2);
		
		$doc_root = realpath($_SERVER['DOCUMENT_ROOT']);
		
		if (!array_key_exists(0, $parameters)) {
			fCore::toss('fProgrammerException', 'The method ' . $method_name . '() requires exactly one parameter');	
		}
		
		$file_path = $parameters[0];
		$invalid_file = !$file_path && !is_numeric($file_path);
		
		if (!$file_path || (!file_exists($file_path) && !file_exists($doc_root . $file_path))) {
			fCore::toss('fEnvironmentException', 'The file specified, ' . fCore::dump($file_path) . ', does not exist. This may indicate a missing enctype="multipart/form-data" attribute in form tag.');	
		}
		
		if (!file_exists($file_path) && file_exists($doc_root . $file_path)) {
			$file_path = $doc_root . $file_path;	
		}
		
		$file     = new fFile($file_path);
		$new_file = $file->duplicate(self::$file_upload_columns[$class][$column]);
		
		settype($old_values[$column], 'array');
		
		$old_values[$column][] = $values[$column];
		$values[$column]       = $new_file;
	}
	
	
	/**
	 * Sets up the fUpload class for a specific column
	 * 
	 * @param  mixed  $class   The class name or an instance of the class to set up for
	 * @param  string $column  The column to set up for 
	 * @return void
	 */
	static private function setUpFUpload($class, $column)
	{
		fUpload::reset();
		
		// Set up the fUpload class
		if (!empty(self::$fupload_method_calls[$class][$column])) {
			foreach (self::$fupload_method_calls[$class][$column] as $method_call) {
				call_user_func_array($method_call['callback'], $method_call['parameters']);	
			}	
		}
	}
	
	
	/**
	 * Uploads a file
	 * 
	 * @internal
	 * 
	 * @param  fActiveRecord $class             The instance of the class
	 * @param  array         &$values           The current values
	 * @param  array         &$old_values       The old values
	 * @param  array         &$related_records  Any records related to this record
	 * @param  boolean       $debug             If debug messages should be shown
	 * @param  string        &$method_name      The method that was called
	 * @param  array         &$parameters       The parameters passed to the method
	 * @return void
	 */
	static public function upload($class, &$values, &$old_values, &$related_records, $debug, &$method_name, &$parameters)
	{
		$class = fORM::getClassName($class);
		
		list ($action, $column) = explode('_', fInflection::underscorize($method_name), 2);
		
		self::setUpFUpload($class, $column);
		
		$upload_dir = self::$file_upload_columns[$class][$column];
		
		// Let's clean out the upload temp dir
		try {
			$temp_dir = new fDirectory($upload_dir->getPath() . self::TEMP_DIRECTORY);
		} catch (fValidationException $e) {
			$temp_dir = fDirectory::create($upload_dir->getPath() . self::TEMP_DIRECTORY);			
		}
		
		$files    = $temp_dir->scan();
		foreach ($files as $file) {
			if (filemtime($file->getPath()) < strtotime('-6 hours')) {
				$file->delete();
			}
		}
		
		// Try to upload the file putting it in the temp dir incase there is a validation problem with the record
		try {
			$file = fUpload::upload($upload_dir . self::TEMP_DIRECTORY, $column);	
			fUpload::reset();
		
		// If there was an eror, check to see if we have an existing file
		} catch (fExpectedException $e) {
			fUpload::reset();
			
			// If there is an existing file and none was uploaded, substitute the existing file
			$existing_file = fRequest::get('__flourish_existing_' . $column);
			
			if ($existing_file && $e->getMessage() == 'Please upload a file') {
				
				// If the file is not in the database yet, look in the temp directory
				if ($existing_file != $values[$column] && file_exists($upload_dir->getPath() . self::TEMP_DIRECTORY . $existing_file)) {
					$existing_file = self::TEMP_DIRECTORY . $existing_file;	
				}
				
				$file = new fFile($upload_dir->getPath() . $existing_file);
				
			} else {
				return;	
			}	
		}
		
		settype($old_values[$column], 'array');
		
		// Assign the file
		$old_values[$column][] = $values[$column];
		$values[$column]       = $file;
		
		// Perform the file upload inheritance
		if (!empty(self::$column_inheritence[$class][$column])) {
			foreach (self::$column_inheritence[$class][$column] as $other_column) {
				$other_file = $file->duplicate(self::$file_upload_columns[$class][$column] . self::TEMP_DIRECTORY, FALSE);
				
				settype($old_values[$other_column], 'array');
				
				$old_values[$other_column][] = $values[$other_column];
				$values[$other_column]       = $other_file;
				
				self::processImage($class, $other_column, $other_file);	
			}	
		}
		
		// Process the file
		self::processImage($class, $column, $file);
	}
	
	
	/**
	 * Moves uploaded file from the temporary directory to the permanent directory
	 * 
	 * @internal
	 * 
	 * @param  fActiveRecord $class                 The instance of the class
	 * @param  array         &$values               The current values
	 * @param  array         &$old_values           The old values
	 * @param  array         &$related_records      Any records related to this record
	 * @param  boolean       $debug                 If debug messages should be shown
	 * @param  array         &$validation_messages  The existing validation messages
	 * @return void
	 */
	static public function validate($class, &$values, &$old_values, &$related_records, $debug, &$validation_messages)
	{
		$class = fORM::getClassName($class);
		
		foreach (self::$file_upload_columns[$class] as $column => $directory) {
			$column_name = fORM::getColumnName($class, $column);
			
			$search_message  = $column_name . ': Please enter a value';
			$replace_message = $column_name . ': Please upload a file';
			str_replace($search_message, $replace_message, $validation_messages);
			
			self::setUpFUpload($class, $column);
			
			// Grab the error that occured
			try {
				fUpload::validate($column); 		
			} catch (fValidationException $e) {
				if ($e->getMessage() != 'Please upload a file') {
					$validation_messages[] = $column_name . ': ' . $e->getMessage();	
				}
			}
		}
	}
	
	
	/**
	 * Forces use as a static class
	 * 
	 * @return fORMFile
	 */
	private function __construct() { }
}



/**
 * Copyright (c) 2008 William Bond <will@flourishlib.com>
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