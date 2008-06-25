<?php
/**
 * Provides special column functionality for {@link fActiveRecord} classes
 * 
 * @copyright  Copyright (c) 2008 William Bond
 * @author     William Bond [wb] <will@flourishlib.com>
 * @license    http://flourishlib.com/license
 * 
 * @link  http://flourishlib.com/fORMColumn
 * 
 * @version  1.0.0
 * @changes  1.0.0    The initial implementation [wb, 2008-05-27]
 */
class fORMColumn
{
	/**
	 * Columns that should be filled with the date created for new objects
	 * 
	 * @var array
	 */
	static private $date_created_columns = array();
	
	/**
	 * Columns that should be filled with the date updated
	 * 
	 * @var array
	 */
	static private $date_updated_columns = array();
	
	/**
	 * Columns that should be formatted as email addresses
	 * 
	 * @var array
	 */
	static private $email_columns = array();
	
	/**
	 * Columns that should be formatted as links
	 * 
	 * @var array
	 */
	static private $link_columns = array();
	
	/**
	 * Columns that should be formatted as a random string
	 * 
	 * @var array
	 */
	static private $random_columns = array();
	
	
	/**
	 * Sets a column to be a date created column
	 * 
	 * @param  mixed  $class   The class name or instance of the class
	 * @param  string $column  The column to set as a date created column
	 * @return void
	 */
	static public function configureDateCreatedColumn($class, $column)
	{
		$class     = fORM::getClassName($class);
		$table     = fORM::tablize($class);
		$data_type = fORMSchema::getInstance()->getColumnInfo($table, $column, 'type');
		
		$valid_data_types = array('date', 'time', 'timestamp');
		if (!in_array($data_type, $valid_data_types)) {
			fCore::toss('fProgrammerException', 'The column specified, ' . $column . ', is a ' . $data_type . ' column. Must be one of ' . join(', ', $valid_data_types) . ' to be set as a date created column.');	
		}
		
		$hook     = 'post-begin::store()';
		$callback = array('fORMColumn', 'setDateCreated');
		fORM::registerHookCallback($class, $hook, $callback);
		
		if (empty(self::$date_created_columns[$class])) {
			self::$date_created_columns[$class] = array();	
		}
		
		self::$date_created_columns[$class][$column] = TRUE;
	}
	
	
	/**
	 * Sets a column to be a date updated column
	 * 
	 * @param  mixed  $class   The class name or instance of the class
	 * @param  string $column  The column to set as a date updated column
	 * @return void
	 */
	static public function configureDateUpdatedColumn($class, $column)
	{
		$class     = fORM::getClassName($class);
		$table     = fORM::tablize($class);
		$data_type = fORMSchema::getInstance()->getColumnInfo($table, $column, 'type');
		
		$valid_data_types = array('date', 'time', 'timestamp');
		if (!in_array($data_type, $valid_data_types)) {
			fCore::toss('fProgrammerException', 'The column specified, ' . $column . ', is a ' . $data_type . ' column. Must be one of ' . join(', ', $valid_data_types) . ' to be set as a date updated column.');	
		}
		
		$hook     = 'post-begin::store()';
		$callback = array('fORMColumn', 'setDateUpdated');
		fORM::registerHookCallback($class, $hook, $callback);
		
		if (empty(self::$date_updated_columns[$class])) {
			self::$date_updated_columns[$class] = array();	
		}
		
		self::$date_updated_columns[$class][$column] = TRUE;
	}
	
	
	/**
	 * Sets a column to be formatted as an email address
	 * 
	 * @param  mixed  $class   The class name or instance of the class to set the column format
	 * @param  string $column  The column to format as an email address
	 * @return void
	 */
	static public function configureEmailColumn($class, $column)
	{
		$class     = fORM::getClassName($class);
		$table     = fORM::tablize($class);
		$data_type = fORMSchema::getInstance()->getColumnInfo($table, $column, 'type');
		
		$valid_data_types = array('varchar', 'char', 'text');
		if (!in_array($data_type, $valid_data_types)) {
			fCore::toss('fProgrammerException', 'The column specified, ' . $column . ', is a ' . $data_type . ' column. Must be one of ' . join(', ', $valid_data_types) . ' to be set as an email column.');	
		}
		
		$hook     = 'post::validate()';
		$callback = array('fORMColumn', 'validateEmailColumns');
		if (!fORM::checkHookCallback($class, $hook, $callback)) {
			fORM::registerHookCallback($class, $hook, $callback);
		}
		
		if (empty(self::$email_columns[$class])) {
			self::$email_columns[$class] = array();	
		}
		
		self::$email_columns[$class][$column] = TRUE;
	}
	
	
	/**
	 * Sets a column to be formatted as a link
	 * 
	 * @param  mixed  $class   The class name or instance of the class to set the column format
	 * @param  string $column  The column to format as an email address
	 * @return void
	 */
	static public function configureLinkColumn($class, $column)
	{
		$class     = fORM::getClassName($class);
		$table     = fORM::tablize($class);
		$data_type = fORMSchema::getInstance()->getColumnInfo($table, $column, 'type');
		
		$valid_data_types = array('varchar', 'char', 'text');
		if (!in_array($data_type, $valid_data_types)) {
			fCore::toss('fProgrammerException', 'The column specified, ' . $column . ', is a ' . $data_type . ' column. Must be one of ' . join(', ', $valid_data_types) . ' to be set as a link column.');	
		}
		
		$cameled_column = fInflection::camelize($column, TRUE);
		
		$hook     = 'replace::prepare' . $cameled_column . '()';
		$callback = array('fORMColumn', 'prepareLinkColumn');
		fORM::registerHookCallback($class, $hook, $callback);
		
		$hook     = 'post::validate()';
		$callback = array('fORMColumn', 'validateLinkColumns');
		if (!fORM::checkHookCallback($class, $hook, $callback)) {
			fORM::registerHookCallback($class, $hook, $callback);
		}
		
		if (empty(self::$link_columns[$class])) {
			self::$link_columns[$class] = array();	
		}
		
		self::$link_columns[$class][$column] = TRUE;
	}
	
	
	/**
	 * Sets a column to be a random string column
	 * 
	 * @param  mixed   $class   The class name or instance of the class
	 * @param  string  $column  The column to set as a random column
	 * @param  string  $type    The type of random string, must be one of: 'alphanumeric', 'alpha', 'numeric', 'hexadecimal'
	 * @param  integer $length  The length of the random string
	 * @return void
	 */
	static public function configureRandomColumn($class, $column, $type, $length)
	{
		$class     = fORM::getClassName($class);
		$table     = fORM::tablize($class);
		$data_type = fORMSchema::getInstance()->getColumnInfo($table, $column, 'type');
		
		$valid_data_types = array('varchar', 'char', 'text');
		if (!in_array($data_type, $valid_data_types)) {                                                                                                                       
			fCore::toss('fProgrammerException', 'The column specified, ' . $column . ', is a ' . $data_type . ' column. Must be one of ' . join(', ', $valid_data_types) . ' to be set as a random string column.');	
		}
		
		$valid_types = array('alphanumeric', 'alpha', 'numeric', 'hexadecimal');
		if (!in_array($type, $valid_types)) {
			fCore::toss('fProgrammerException', 'The type, ' . $type . ', must be one of ' . join(', ', $valid_types) . '.');	
		}
		
		if (!is_numeric($length) || $length < 1) {
			fCore::toss('fProgrammerException', 'The length specified, ' . $length . ', needs to be an integer greater than zero.');	
		}
		
		$hook     = 'pre::validate()';
		$callback = array('fORMColumn', 'setRandomStrings');
		fORM::registerHookCallback($class, $hook, $callback);
		
		if (empty(self::$random_columns[$class])) {
			self::$random_columns[$class] = array();	
		}
		
		self::$random_columns[$class][$column] = array('type' => $type, 'length' => (int) $length);
	}
	
	
	/**
	 * Prepares a link column so that the link will work properly in an A tag
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
	 * @return string  The formatted link
	 */
	static public function prepareLinkColumn($class, &$values, &$old_values, &$related_records, $debug, &$method_name, &$parameters)
	{
		list ($action, $column) = explode('_', fInflection::underscorize($method_name), 2);
		
		if (empty($values[$column])) {
			return $values[$column];
		}	
		$value = $values[$column];
		
		// Fix domains that don't have the protocol to start
		if (preg_match('#^([a-z0-9\\-]+\.)+[a-z]{2,}(/|$)#i', $value)) {
			$value = 'http://' . $value;
		}
		
		return fHTML::prepare($value);
	}
	
	
	/**
	 * Sets the appropriate column values to the date the object was created (for new records)
	 * 
	 * @internal
	 * 
	 * @param  fActiveRecord $class             The instance of the class
	 * @param  array         &$values           The current values
	 * @param  array         &$old_values       The old values
	 * @param  array         &$related_records  Any records related to this record
	 * @param  boolean       $debug             If debug messages should be shown
	 * @return string  The formatted link
	 */
	static public function setDateCreated($class, &$values, &$old_values, &$related_records, $debug)
	{
		if ($class->exists()) {
			return;	
		}
		
		$class = fORM::getClassName($class);
		
		foreach (self::$date_created_columns[$class] as $column => $enabled) {
			$old_values[$column] = $values[$column];
			$values[$column] = fORM::objectify($class, $column, date('Y-m-d H:i:s'));		
		}
	}
	
	
	/**
	 * Sets the appropriate column values to the date the object was updated
	 * 
	 * @internal
	 * 
	 * @param  fActiveRecord $class             The instance of the class
	 * @param  array         &$values           The current values
	 * @param  array         &$old_values       The old values
	 * @param  array         &$related_records  Any records related to this record
	 * @param  boolean       $debug             If debug messages should be shown
	 * @return string  The formatted link
	 */
	static public function setDateUpdated($class, &$values, &$old_values, &$related_records, $debug)
	{
		$class = fORM::getClassName($class);
		
		foreach (self::$date_updated_columns[$class] as $column => $enabled) {
			$old_values[$column] = $values[$column];
			$values[$column] = fORM::objectify($class, $column, date('Y-m-d H:i:s'));		
		}
	}
	
	
	/**
	 * Sets the appropriate column values to a random string if the object is new
	 * 
	 * @internal
	 * 
	 * @param  fActiveRecord $class             The instance of the class
	 * @param  array         &$values           The current values
	 * @param  array         &$old_values       The old values
	 * @param  array         &$related_records  Any records related to this record
	 * @param  boolean       $debug             If debug messages should be shown
	 * @return string  The formatted link
	 */
	static public function setRandomStrings($class, &$values, &$old_values, &$related_records, $debug)
	{
		if ($class->exists()) {
			return;	
		}
		$table = fORM::tablize($class);
		
		$class = fORM::getClassName($class);
		
		foreach (self::$random_columns[$class] as $column => $settings) {
			$old_values[$column] = $values[$column];
			
			// Check to see if this is a unique column
			$unique_keys      = fORMSchema::getInstance()->getKeys($table, 'unique');
			$is_unique_column = FALSE;
			foreach ($unique_keys as $unique_key) {
				if ($unique_key == array($column)) {
					$is_unique_column = TRUE;
					do {
						$values[$column] = fCryptography::generateRandomString($settings['length'], $settings['type']);
						
						// See if this is unique
						$sql = "SELECT " . $column . " FROM " . $table . " WHERE " . $column . " = '" . fORMDatabase::getInstance()->escapeString($values[$column]) . "'";
					
					} while (fORMDatabase::getInstance()->query($sql)->getReturnedRows());
				}
			}
			
			// If is is not a unique column, just generate a value
			if (!$is_unique_column) {
				$values[$column] = fCryptography::generateRandomString($settings['length'], $settings['type']);	
			}
		}
	}
	
	
	/**
	 * Validates all email columns
	 * 
	 * @internal
	 * 
	 * @param  fActiveRecord $class                 The name of the class
	 * @param  array         &$values               The current values
	 * @param  array         &$old_values           The old values
	 * @param  array         &$related_records      Any records related to this record
	 * @param  boolean       $debug                 If debug messages should be shown
	 * @param  array         &$validation_messages  An array of ordered validation messages
	 * @return void
	 */
	static public function validateEmailColumns($class, &$values, &$old_values, &$related_records, $debug, &$validation_messages)
	{
		$class = fORM::getClassName($class);
		
		if (empty(self::$email_columns[$class])) {
			return;
		}	
		
		foreach (self::$email_columns[$class] as $column => $enabled) {
			if (!fCore::stringlike($values[$column])) {
				continue;	
			}
			if (!preg_match('#^[a-z0-9\\.\'_\\-\\+]+@(?:[a-z0-9\\-]+\.)+[a-z]{2,}$#i', $values[$column])) {
				$validation_messages[] = fORM::getColumnName($class_name, $column) . ': Please enter an email address in the form name@example.com';
			}	
		}
	}
	
	
	/**
	 * Validates all link columns
	 * 
	 * @internal
	 * 
	 * @param  fActiveRecord $class                 The name of the class
	 * @param  array         &$values               The current values
	 * @param  array         &$old_values           The old values
	 * @param  array         &$related_records      Any records related to this record
	 * @param  boolean       $debug                 If debug messages should be shown
	 * @param  array         &$validation_messages  An array of ordered validation messages
	 * @return void
	 */
	static public function validateLinkColumns($class, &$values, &$old_values, &$related_records, $debug, &$validation_messages)
	{
		$class = fORM::getClassName($class);
		
		if (empty(self::$link_columns[$class])) {
			return;
		}	
		
		foreach (self::$link_columns[$class] as $column => $enabled) {
			if (!fCore::stringlike($values[$column])) {
				continue;	
			}
			if (!preg_match('#^(http(s)?://|/|([a-z0-9\\-]+\.)+[a-z]{2,})#i', $values[$column])) {
				$validation_messages[] = fORM::getColumnName($class, $column) . ': Please enter a link in the form http://www.example.com';
			}	
		}
	}
	
	
	/**
	 * Forces use as a static class
	 * 
	 * @return fORMColumn
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