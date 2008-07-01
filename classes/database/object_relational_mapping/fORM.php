<?php
/**
 * Dynamically handles many centralized object-relational mapping tasks
 * 
 * @copyright  Copyright (c) 2007-2008 William Bond
 * @author     William Bond [wb] <will@flourishlib.com>
 * @license    http://flourishlib.com/license
 * 
 * @package    Flourish
 * @link       http://flourishlib.com/fORM
 * 
 * @version    1.0.0b
 * @changes    1.0.0b  The initial implementation [wb, 2007-08-04]
 */
class fORM
{
	/**
	 * Custom column names for columns in fActiveRecord classes
	 * 
	 * @var array
	 */
	static private $column_names = array();
	
	/**
	 * An array of flags indicating a class has been configured
	 * 
	 * @var array
	 */
	static private $configured = array();
	
	/**
	 * Tracks callbacks registered for various fActiveRecord hooks
	 * 
	 * @var array
	 */
	static private $hook_callbacks = array();
	
	/**
	 * Maps objects via their primary key
	 * 
	 * @var array
	 */
	static private $identity_map = array();
	
	/**
	 * Callbacks for objectify()
	 * 
	 * @var array
	 */
	static private $objectify_callbacks = array();
	
	/**
	 * Custom record names for {@link fActiveRecord} classes
	 * 
	 * @var array
	 */
	static private $record_names = array();
	
	/**
	 * Callbacks for scalarize()
	 * 
	 * @var array
	 */
	static private $scalarize_callbacks = array();
	
	/**
	 * Custom mappings for table <-> class
	 * 
	 * @var array
	 */
	static private $table_class_map = array();
	
	
	/**
	 * Allows non-standard (plural, underscore notation table name <-> singular, upper-camel case class name) table to class mapping
	 * 
	 * @param  string $table_name  The name of the database table
	 * @param  string $class_name  The name of the class
	 * @return void
	 */
	static public function addCustomTableClassMapping($table_name, $class_name)
	{
		self::$table_class_map[$table_name] = $class_name;
	}
	
	
	/**
	 * Calls a hook callback and returns the result
	 * 
	 * The return value should only be used by replace:: callbacks.
	 * 
	 * @internal
	 * 
	 * @param  fActiveRecord $class              The instance of the class to call the hook for
	 * @param  string        $hook               The hook to call
	 * @param  array         &$values            The current values of the record
	 * @param  array         &$old_values        The old values of the record
	 * @param  array         &$related_records   Records related to the current record
	 * @param  boolean       $debug              If debugging is turned on for this record
	 * @param  mixed         &$first_parameter   The first parameter to send the callback
	 * @param  mixed         &$second_parameter  The second parameter to send the callback
	 * @return mixed  The return value from the callback. Will be NULL unless it is a replace:: callback.
	 */
	static public function callHookCallback(fActiveRecord $class, $hook, &$values, &$old_values, &$related_records, $debug, &$first_parameter=NULL, &$second_parameter=NULL)
	{
		$class_name = self::getClassName($class);
		
		if (!isset(self::$hook_callbacks[$class_name]) || empty(self::$hook_callbacks[$class_name][$hook])) {
			return;
		}
		
		// replace:: hooks are always singular and return a value
		if (preg_match('#^replace::[\w_]+\(\)$#', $hook)) {
			// This is the only way to pass by reference
			$parameters = array(
				$class,
				&$values,
				&$old_values,
				&$related_records,
				$debug,
				&$first_parameter,
				&$second_parameter
			);
			return call_user_func_array(self::$hook_callbacks[$class_name][$hook], $parameters);
		}
		
		// There can be more than one non-replace:: hook so we can't return a value
		foreach (self::$hook_callbacks[$class_name][$hook] as $callback) {
			// This is the only way to pass by reference
			$parameters = array(
				$class,
				&$values,
				&$old_values,
				&$related_records,
				$debug,
				&$first_parameter,
				&$second_parameter
			);
			call_user_func_array($callback, $parameters);
		}
	}
	
	
	/**
	 * Checks to see if any (or a specific) callback has been registered for a specific hook
	 *
	 * @internal
	 * 
	 * @param  mixed  $class     The name of the class to check the hook of
	 * @param  string $hook      The hook to check
	 * @param  array  $callback  The specific callback to check for
	 * @return boolean  If the specified callback exists
	 */
	static public function checkHookCallback($class, $hook, $callback=NULL)
	{
		$class = self::getClassName($class);
		
		if (!isset(self::$hook_callbacks[$class]) || empty(self::$hook_callbacks[$class][$hook])) {
			return FALSE;
		}
		
		if (!$callback) {
			return TRUE;	
		}
		
		foreach (self::$hook_callbacks[$class][$hook] as $_callback) {
			if ($_callback == $callback) {
				return TRUE;
			}	
		}
		
		return FALSE;
	}
	
	
	/**
	 * Checks to see if an object has been saved to the identity map
	 * 
	 * @internal
	 * 
	 * @param  mixed $class             The name of the class, or an instance of it
	 * @param  array $primary_key_data  The primary key(s) for the instance
	 * @return mixed  Will return FALSE if no match, or the instance of the object if a match occurs
	 */
	static public function checkIdentityMap($class, $primary_key_data)
	{
		$class = self::getClassName($class);
		
		if (!isset(self::$identity_map[$class])) {
			return FALSE;
		}
		
		$hash_key = self::createPrimaryKeyHash($primary_key_data);
		
		if (!isset(self::$identity_map[$class][$hash_key])) {
			return FALSE;
		}
		
		return self::$identity_map[$class][$hash_key];
	}
	
	
	/**
	 * Takes a table name and turns it into a class name - uses custom mapping if set
	 * 
	 * @param  string $table_name  The table name
	 * @return string  The class name
	 */
	static public function classize($table_name)
	{
		if (!isset(self::$table_class_map[$table_name])) {
			self::$table_class_map[$table_name] = fInflection::camelize(fInflection::singularize($table_name), TRUE);
		}
		return self::$table_class_map[$table_name];
	}
	
	
	/**
	 * Will dynamically create an {@link fActiveRecord}-based class for a database table
	 * 
	 * Normally this would be called from an __autoload() function
	 * 
	 * @param  string $class_name  The name of the class to create
	 * @return void
	 */
	static public function createActiveRecordClass($class_name)
	{
		if (class_exists($class_name, FALSE)) {
			return;
		}
		$tables = fORMSchema::getInstance()->getTables();
		$table_name = self::tablize($class_name);
		if (in_array($table_name, $tables)) {
			eval('class ' . $class_name . ' extends fActiveRecord { };');
			return;
		}
		fCore::toss('fProgrammerException', 'The class specified does not correspond to a database table');
	}
	
	
	/**
	 * Turns a primary key array into a hash key using md5
	 * 
	 * @param  array $primary_key_data  The primary key data to hash
	 * @return string  An md5 of the sorted, serialized primary key data
	 */
	static private function createPrimaryKeyHash($primary_key_data)
	{
		sort($primary_key_data);
		foreach ($primary_key_data as $primary_key => $data) {
			if (is_object($data) && is_callable(array($data, '__toString'))) {
				$data = $data->__toString();	
			}
			$primary_key_data[$primary_key] = (string) $data;
		}
		return md5(serialize($primary_key_data));
	}
	
	
	/**
	 * Sets a flag indicating a class has been configured
	 *
	 * @internal
	 * 
	 * @param  mixed $class  The class name or an instance of the class to flag
	 * @return void
	 */
	static public function flagConfigured($class)
	{
		$class = self::getClassName($class);
		self::$configured[$class] = TRUE;
	}
	
	
	/**
	 * Takes a class name or class and returns the class name
	 *
	 * @internal
	 * 
	 * @param  mixed $class  The class to get the name of
	 * @return string  The class name
	 */
	static public function getClassName($class)
	{
		if (is_object($class)) { return get_class($class); }
		return $class;
	}
	
	
	/**
	 * Returns the column name. The default column name is a humanize-d version of the column.
	 * 
	 * @internal
	 * 
	 * @param  mixed  $class   The class name or instance of the class the column is part of
	 * @param  string $column  The database column
	 * @return string  The column name for the column specified
	 */
	static public function getColumnName($class, $column)
	{
		$class = self::getClassName($class);
		
		if (!isset(self::$column_names[$class])) {
			self::$column_names[$class] = array();
		}
		
		if (!isset(self::$column_names[$class][$column])) {
			self::$column_names[$class][$column] = fInflection::humanize($column);
		}
		
		return self::$column_names[$class][$column];
	}
	
	
	/**
	 * Returns the record name for a class. The default record name is a humanized version of the class name.
	 * 
	 * @internal
	 * 
	 * @param  mixed $class  The class name or instance of the class to get the record name of
	 * @return string  The record name for the class specified
	 */
	static public function getRecordName($class)
	{
		$class = self::getClassName($class);
		
		if (!isset(self::$record_names[$class])) {
			self::$record_names[$class] = fInflection::humanize($class);
		}
		
		return self::$record_names[$class];
	}
	
	
	/**
	 * Checks to see if the class has been configured yet
	 *
	 * @internal
	 * 
	 * @param  mixed $class  The class name or an instance of the class to check
	 * @return boolean
	 */
	static public function isConfigured($class)
	{
		$class = self::getClassName($class);
		return !empty(self::$configured[$class]);
	}
	
	
	/**
	 * Takes a scalar value and turns it into an object if applicable
	 *
	 * @internal
	 * 
	 * @param  mixed  $class   The class name or instance of the class the column is part of
	 * @param  string $column  The database column
	 * @param  mixed  $value   The value to possibly objectify
	 * @return mixed  The scalar or object version of the value, depending on the column type and column options
	 */
	static public function objectify($class, $column, $value)
	{
		$class = self::getClassName($class);
		
		if (!empty(self::$objectify_callbacks[$class][$column])) {
			return call_user_func(self::$objectify_callbacks[$class][$column], $class, $column, $value);	
		}
		
		$table = self::tablize($class);
		
		// Turn date/time values into objects
		$column_type = fORMSchema::getInstance()->getColumnInfo($table, $column, 'type');
		
		if ($value !== NULL && in_array($column_type, array('date', 'time', 'timestamp'))) {
			try {
				
				// Explicit calls to the constructors are used for dependency detection
				switch ($column_type) {
					case 'date':      $value = new fDate($value);      break;
					case 'time':      $value = new fTime($value);      break;
					case 'timestamp': $value = new fTimestamp($value); break;
				}
				
			} catch (fValidationException $e) {
				// Validation exception results in the raw value being saved
			}
		}
		
		return $value;
	}
	
	
	/**
	 * Allows overriding of default (humanize-d column) column names
	 * 
	 * @param  mixed  $class        The class name or instance of the class the column is located in
	 * @param  string $column       The database column
	 * @param  string $column_name  The name for the column
	 * @return void
	 */
	static public function overrideColumnName($class, $column, $column_name)
	{
		$class = self::getClassName($class);
		
		if (!isset(self::$column_names[$class])) {
			self::$column_names[$class] = array();
		}
		
		self::$column_names[$class][$column] = $column_name;
	}
	
	
	/**
	 * Allows overriding of default (humanize-d class name) record names
	 * 
	 * @param  mixed  $class        The class name or instance of the class to override the name of
	 * @param  string $record_name  The human version of the record
	 * @return void
	 */
	static public function overrideRecordName($class, $record_name)
	{
		$class = self::getClassName($class);
		self::$record_names[$class] = $record_name;
	}
	
	
	/**
	 * Registers a callback for one of the various {@link fActiveRecord} hooks
	 * 
	 * Any hook that does not begin with replace:: can have multiple callbacks. 
	 * replace:: hooks can only have one, the most recently registered.
	 * 
	 * The method signature should include the follow parameters:
	 * 
	 *  1. $class
	 *  2. &$values
	 *  3. &$old_values
	 *  4. &$related_records
	 *  5. $debug
	 * 
	 * Below is a list of other parameters passed to specific hooks:
	 *   - 'replace::validate()': $return messages - a boolean flag indicating if the validation messages should be returned as an array instead of thrown as an exception
	 *   - 'pre::validate()' and 'post::validate()': &$validation_messages - an ordered array of validation errors that will be returned or tossed as an fValidationException
	 *   - 'replace::{someMethod}()' (where {someMethod} is anything routed to __call()): &$method_name - the name of the method called, &$parameters - the parameters the method was called with  
	 * 
	 * @param  mixed    $class     The class name or instance of the class to hook
	 * @param  string   $hook      The hook to register for
	 * @param  callback $callback  The callback to register. See the method description for details about the method signature.
	 * @return void
	 */
	static public function registerHookCallback($class, $hook, $callback)
	{
		$class = self::getClassName($class);
		$replace_hook = preg_match('#^replace::[\w_]+\(\)$#', $hook);
		
		static $valid_hooks = array(
			'post::__construct()',
			'replace::delete()',
			'pre::delete()',
			'post-begin::delete()',
			'pre-commit::delete()',
			'post-commit::delete()',
			'post-rollback::delete()',
			'post::delete()',
			'post::inspect()',
			'post::loadFromResult()',
			'replace::populate()',
			'pre::populate()',
			'post::populate()',
			'replace::store()',
			'pre::store()',
			'post-begin::store()',
			'post-validate::store()',
			'pre-commit::store()',
			'post-commit::store()',
			'post-rollback::store()',
			'post::store()',
			'replace::validate()',
			'pre::validate()',
			'post::validate()'
		);
		
		static $invalid_replace_hooks = array(
			'replace::enableDebugging()',
			'replace::assign()',
			'replace::configure()',
			'replace::constructInsertSQL()',
			'replace::constructPrimaryKeyWhereClause()',
			'replace::constructUpdateSQL()',
			'replace::entify()',
			'replace::format()',
			'replace::loadFromIdentityMap()',
			'replace::loadFromResult()',
			'replace::retrieve()',
			'replace::storeManyToManyAssociations()',
			'replace::storeOneToManyRelatedRecords()'
		);
		
		if (!in_array($hook, $valid_hooks) && !$replace_hook) {
			fCore::toss('fProgrammerException', 'The hook specified, ' . $hook . ', should be one of: ' . join(', ', $valid_hooks) . ' or replace::{someMethod}().');	
		}
		
		if ($replace_hook && in_array($hook, $invalid_replace_hooks)) {
			fCore::toss('fProgrammerException', 'The hook specified, ' . $hook . ', is an invalid replace:: hook. Can not be one of: ' . join(', ', $invalid_replace_hooks) . '.');	
		}
		
		if (!isset(self::$hook_callbacks[$class])) {
			self::$hook_callbacks[$class] = array();
		}
		
		// We only allow a single replace:: callback
		if ($replace_hook) {
			self::$hook_callbacks[$class][$hook] = $callback;
		
		// If it is not a replace:: callback, we can have unlimited callbacks
		} else {
			if (!isset(self::$hook_callbacks[$class][$hook])) {
				self::$hook_callbacks[$class][$hook] = array();
			}	
			self::$hook_callbacks[$class][$hook][] = $callback;
		}
	}
	
	
	/**
	 * Registers a callback for when objectify() is called on a specific column
	 * 
	 * @param  mixed    $class     The class name or instance of the class to register for
	 * @param  string   $column    The column to register for
	 * @param  callback $callback  The callback to register. Callback should accept a single parameter, the value to objectify and should return the objectified value.
	 * @return void
	 */
	static public function registerObjectifyCallback($class, $column, $callback)
	{
		$class = self::getClassName($class);
		
		if (!isset(self::$objectify_callbacks[$class])) {
			self::$objectify_callbacks[$class] = array();
		}
		
		self::$objectify_callbacks[$class][$column] = $callback;
	}
	
	
	/**
	 * Registers a callback for when scalarize() is called on a specific column
	 * 
	 * @param  mixed    $class     The class name or instance of the class to register for
	 * @param  string   $column    The column to register for
	 * @param  callback $callback  The callback to register. Callback should accept a single parameter, the value to scalarize and should return the scalarized value.
	 * @return void
	 */
	static public function registerScalarizeCallback($class, $column, $callback)
	{
		$class = self::getClassName($class);
		
		if (!isset(self::$scalarize_callbacks[$class])) {
			self::$scalarize_callbacks[$class] = array();
		}
		
		self::$scalarize_callbacks[$class][$column] = $callback;
	}
	
	
	/**
	 * Saves an object to the identity map
	 * 
	 * @internal
	 * 
	 * @param  mixed $object            An instance of an fActiveRecord class
	 * @param  array $primary_key_data  The primary key(s) for the instance
	 * @return void
	 */
	static public function saveToIdentityMap($object, $primary_key_data)
	{
		$class = self::getClassName($object);
		
		if (!isset(self::$identity_map[$class])) {
			self::$identity_map[$class] = array();
		}
		
		$hash_key = self::createPrimaryKeyHash($primary_key_data);
		
		self::$identity_map[$class][$hash_key] = $object;
	}
	
	
	/**
	 * If the value passed is an object, calls __toString() on it
	 *
	 * @internal
	 * 
	 * @param  mixed  $class   The class name or instance of the class the column is part of
	 * @param  string $column  The database column
	 * @param  mixed  $value   The value to get the scalar value of
	 * @return mixed  The scalar value of the value
	 */
	static public function scalarize($class, $column, $value)
	{
		$class = self::getClassName($class);
		
		if (!empty(self::$scalarize_callbacks[$class][$column])) {
			return call_user_func(self::$scalarize_callbacks[$class][$column], $class, $column, $value);	
		}
		
		if (is_object($value)) {
			return $value->__toString();
		}
		return $value;
	}
	
	
	/**
	 * Takes a class name (or class) and turns it into a table name. Uses custom mapping if set.
	 * 
	 * @param  mixed $class  The name of the class or the class to extract the name from
	 * @return string  The table name
	 */
	static public function tablize($class)
	{
		$class = self::getClassName($class);
		
		if (!$table_name = array_search($class, self::$table_class_map)) {
			$table_name = fInflection::underscorize(fInflection::pluralize($class));
			self::$table_class_map[$table_name] = $class;
		}
		
		return $table_name;
	}
	
	
	/**
	 * Forces use as a static class
	 * 
	 * @return fORM
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