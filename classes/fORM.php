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
	// The following constants allow for nice looking callbacks to static methods
	const addCustomTableClassMapping = 'fORM::addCustomTableClassMapping';
	const callHookCallbacks          = 'fORM::callHookCallbacks';
	const callReflectCallbacks       = 'fORM::callReflectCallbacks';
	const checkHookCallback          = 'fORM::checkHookCallback';
	const classize                   = 'fORM::classize';
	const defineActiveRecordClass    = 'fORM::defineActiveRecordClass';
	const getActiveRecordMethod      = 'fORM::getActiveRecordMethod';
	const getClass                   = 'fORM::getClass';
	const getColumnName              = 'fORM::getColumnName';
	const getRecordName              = 'fORM::getRecordName';
	const getRecordSetMethod         = 'fORM::getRecordSetMethod';
	const objectify                  = 'fORM::objectify';
	const overrideColumnName         = 'fORM::overrideColumnName';
	const overrideRecordName         = 'fORM::overrideRecordName';
	const parseMethod                = 'fORM::parseMethod';
	const registerActiveRecordMethod = 'fORM::registerActiveRecordMethod';
	const registerHookCallback       = 'fORM::registerHookCallback';
	const registerObjectifyCallback  = 'fORM::registerObjectifyCallback';
	const registerRecordSetMethod    = 'fORM::registerRecordSetMethod';
	const registerReflectCallback    = 'fORM::registerReflectCallback';
	const registerScalarizeCallback  = 'fORM::registerScalarizeCallback';
	const reset                      = 'fORM::reset';
	const scalarize                  = 'fORM::scalarize';
	const tablize                    = 'fORM::tablize';
	
	
	/**
	 * An array of `{method} => {callback}` mappings for fActiveRecord
	 * 
	 * @var array
	 */
	static private $active_record_method_callbacks = array();
	
	/**
	 * Custom column names for columns in fActiveRecord classes
	 * 
	 * @var array
	 */
	static private $column_names = array();
	
	/**
	 * Tracks callbacks registered for various fActiveRecord hooks
	 * 
	 * @var array
	 */
	static private $hook_callbacks = array();
	
	/**
	 * Callbacks for ::objectify()
	 * 
	 * @var array
	 */
	static private $objectify_callbacks = array();
	
	/**
	 * Custom record names for fActiveRecord classes
	 * 
	 * @var array
	 */
	static private $record_names = array();
	
	/**
	 * An array of `{method} => {callback}` mappings for fRecordSet
	 * 
	 * @var array
	 */
	static private $record_set_method_callbacks = array();
	
	/**
	 * Callbacks for ::reflect()
	 * 
	 * @var array
	 */
	static private $reflect_callbacks = array();
	
	/**
	 * Callbacks for ::scalarize()
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
	 * Allows non-standard table to class mapping
	 * 
	 * By default, all database tables are assumed to be plural nouns in
	 * `underscore_notation` and all class names are assumed to be singular
	 * nouns in `UpperCamelCase`. This method allows arbitrary table to 
	 * class mapping.
	 * 
	 * @param  string $table  The name of the database table
	 * @param  string $class  The name of the class
	 * @return void
	 */
	static public function addCustomTableClassMapping($table, $class)
	{
		self::$table_class_map[$table] = $class;
	}
	
	
	/**
	 * Calls the hook callbacks for the class and hook specified
	 * 
	 * @internal
	 * 
	 * @param  fActiveRecord $object             The instance of the class to call the hook for
	 * @param  string        $hook               The hook to call
	 * @param  array         &$values            The current values of the record
	 * @param  array         &$old_values        The old values of the record
	 * @param  array         &$related_records   Records related to the current record
	 * @param  mixed         &$first_parameter   The first parameter to send the callback
	 * @return void
	 */
	static public function callHookCallbacks(fActiveRecord $object, $hook, &$values, &$old_values, &$related_records, &$first_parameter=NULL)
	{
		$class = self::getClass($object);
		
		if (empty(self::$hook_callbacks[$class][$hook]) && empty(self::$hook_callbacks['*'][$hook])) {
			return;
		}
		
		// Get all of the callbacks for this hook, both for this class or all classes
		$callbacks = array();
		
		if (isset(self::$hook_callbacks[$class][$hook])) {
			$callbacks = array_merge($callbacks, self::$hook_callbacks[$class][$hook]);
		}
		
		if (isset(self::$hook_callbacks['*'][$hook])) {
			$callbacks = array_merge($callbacks, self::$hook_callbacks['*'][$hook]);
		}
		
		foreach ($callbacks as $callback) {
			fCore::call(
				$callback,
				// This is the only way to pass by reference
				array(
					$object,
					&$values,
					&$old_values,
					&$related_records,
					&$first_parameter
				)
			);
		}
	}
	
	
	/**
	 * Calls all reflect callbacks for the object passed
	 * 
	 * @internal
	 * 
	 * @param  fActiveRecord $object                The instance of the class to call the hook for
	 * @param  array         &$signatures           The associative array of `{method_name} => {signature}`
	 * @param  boolean       $include_doc_comments  If the doc comments should be included in the signature
	 * @return void
	 */
	static public function callReflectCallbacks(fActiveRecord $object, &$signatures, $include_doc_comments)
	{
		$class = self::getClass($object);
		
		if (!isset(self::$reflect_callbacks[$class]) && !isset(self::$reflect_callbacks['*'])) {
			return;
		}
		
		if (!empty(self::$reflect_callbacks['*'])) {
			foreach (self::$reflect_callbacks['*'] as $callback) {
				// This is the only way to pass by reference
				$parameters = array(
					$class,
					&$signatures,
					$include_doc_comments
				);
				fCore::call($callback, $parameters);
			}	
		}
		
		if (!empty(self::$reflect_callbacks[$class])) {
			foreach (self::$reflect_callbacks[$class] as $callback) {
				// This is the only way to pass by reference
				$parameters = array(
					$class,
					&$signatures,
					$include_doc_comments
				);
				fCore::call($callback, $parameters);
			}
		}
	}
	
	
	/**
	 * Checks to see if any (or a specific) callback has been registered for a specific hook
	 *
	 * @internal
	 * 
	 * @param  mixed  $class     The name of the class, or an instance of it
	 * @param  string $hook      The hook to check
	 * @param  array  $callback  The specific callback to check for
	 * @return boolean  If the specified callback exists
	 */
	static public function checkHookCallback($class, $hook, $callback=NULL)
	{
		$class = self::getClass($class);
		
		if (empty(self::$hook_callbacks[$class][$hook]) && empty(self::$hook_callbacks['*'][$hook])) {
			return FALSE;
		}
		
		if (!$callback) {
			return TRUE;
		}
		
		if (!empty(self::$hook_callbacks[$class][$hook]) && in_array($callback, self::$hook_callbacks[$class][$hook])) {
			return TRUE;	
		}
		
		if (!empty(self::$hook_callbacks['*'][$hook]) && in_array($callback, self::$hook_callbacks['*'][$hook])) {
			return TRUE;	
		}
		
		return FALSE;
	}
	
	
	/**
	 * Takes a table and turns it into a class name - uses custom mapping if set
	 * 
	 * @param  string $table  The table name
	 * @return string  The class name
	 */
	static public function classize($table)
	{
		if (!isset(self::$table_class_map[$table])) {
			self::$table_class_map[$table] = fGrammar::camelize(fGrammar::singularize($table), TRUE);
		}
		return self::$table_class_map[$table];
	}
	
	
	/**
	 * Will dynamically create an fActiveRecord-based class for a database table
	 * 
	 * Normally this would be called from an `__autoload()` function
	 * 
	 * @param  string $class  The name of the class to create
	 * @return void
	 */
	static public function defineActiveRecordClass($class)
	{
		if (class_exists($class, FALSE)) {
			return;
		}
		$tables = fORMSchema::retrieve()->getTables();
		$table = self::tablize($class);
		if (in_array($table, $tables)) {
			eval('class ' . $class . ' extends fActiveRecord { };');
			return;
		}
		
		fCore::toss(
			'fProgrammerException',
			fGrammar::compose(
				'The class specified, %s, does not correspond to a database table',
				fCore::dump($class)
			)
		);
	}
	
	
	/**
	 * Returns a matching callback for the class and method specified
	 * 
	 * The callback returned will be determined by the following logic:
	 * 
	 *  1. If an exact callback has been defined for the method, it will be returned
	 *  2. If a callback in the form `{action}*` has been defined that matches the method, it will be returned
	 *  3. `NULL` will be returned
	 * 
	 * @internal
	 * 
	 * @param  mixed  $class   The name of the class, or an instance of it
	 * @param  string $method  The method to get the callback for
	 * @return string|null  The callback for the method or `NULL` if none exists - see method description for details
	 */
	static public function getActiveRecordMethod($class, $method)
	{
		$class = self::getClass($class);
		
		if (isset(self::$active_record_method_callbacks[$class][$method])) {
			return self::$active_record_method_callbacks[$class][$method];	
		}
		
		if (preg_match('#[A-Z0-9]#', $method)) {
			list($action, $subject) = self::parseMethod($method);
			if (isset(self::$active_record_method_callbacks[$class][$action . '*'])) {
				return self::$active_record_method_callbacks[$class][$action . '*'];	
			}	
		}
		
		return NULL;	
	}
	
	
	/**
	 * Takes a class name or class and returns the class name
	 *
	 * @internal
	 * 
	 * @param  mixed $class  The object to get the name of, or possibly a string already containing the class
	 * @return string  The class name
	 */
	static public function getClass($class)
	{
		if (is_object($class)) { return get_class($class); }
		return $class;
	}
	
	
	/**
	 * Returns the column name
	 * 
	 * The default column name is the result of calling fGrammar::humanize()
	 * on the column.
	 * 
	 * @internal
	 * 
	 * @param  mixed  $class   The class name or instance of the class the column is part of
	 * @param  string $column  The database column
	 * @return string  The column name for the column specified
	 */
	static public function getColumnName($class, $column)
	{
		$class = self::getClass($class);
		
		if (!isset(self::$column_names[$class])) {
			self::$column_names[$class] = array();
		}
		
		if (!isset(self::$column_names[$class][$column])) {
			self::$column_names[$class][$column] = fGrammar::humanize($column);
		}
		
		return self::$column_names[$class][$column];
	}
	
	
	/**
	 * Returns the record name for a class
	 * 
	 * The default record name is the result of calling fGrammar::humanize()
	 * on the class.
	 * 
	 * @internal
	 * 
	 * @param  mixed $class  The class name or instance of the class to get the record name of
	 * @return string  The record name for the class specified
	 */
	static public function getRecordName($class)
	{
		$class = self::getClass($class);
		
		if (!isset(self::$record_names[$class])) {
			self::$record_names[$class] = fGrammar::humanize($class);
		}
		
		return self::$record_names[$class];
	}
	
	
	/**
	 * Returns a matching callback for the method specified
	 * 
	 * The callback returned will be determined by the following logic:
	 * 
	 *  1. If an exact callback has been defined for the method, it will be returned
	 *  2. If a callback in the form `{action}*` has been defined that matches the method, it will be returned
	 *  3. `NULL` will be returned
	 * 
	 * @internal
	 * 
	 * @param  string $method  The method to get the callback for
	 * @return string|null  The callback for the method or `NULL` if none exists - see method description for details
	 */
	static public function getRecordSetMethod($method)
	{
		if (isset(self::$record_set_method_callbacks[$method])) {
			return self::$record_set_method_callbacks[$method];	
		}
		
		if (preg_match('#[A-Z0-9]#', $method)) {
			list($action, $subject) = self::parseMethod($method);
			if (isset(self::$record_set_method_callbacks[$action . '*'])) {
				return self::$record_set_method_callbacks[$action . '*'];	
			}	
		}
		
		return NULL;	
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
		$class = self::getClass($class);
		
		if (!empty(self::$objectify_callbacks[$class][$column])) {
			return fCore::call(self::$objectify_callbacks[$class][$column], $class, $column, $value);
		}
		
		$table = self::tablize($class);
		
		// Turn date/time values into objects
		$column_type = fORMSchema::retrieve()->getColumnInfo($table, $column, 'type');
		
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
	 * Allows overriding of default column names
	 * 
	 * By default a column name is the result of fGrammar::humanize() called
	 * on the column.
	 * 
	 * @param  mixed  $class        The class name or instance of the class the column is located in
	 * @param  string $column       The database column
	 * @param  string $column_name  The name for the column
	 * @return void
	 */
	static public function overrideColumnName($class, $column, $column_name)
	{
		$class = self::getClass($class);
		
		if (!isset(self::$column_names[$class])) {
			self::$column_names[$class] = array();
		}
		
		self::$column_names[$class][$column] = $column_name;
	}
	
	
	/**
	 * Allows overriding of default record names
	 * 
	 * By default a record name is the result of fGrammar::humanize() called
	 * on the class.
	 * 
	 * @param  mixed  $class        The class name or instance of the class to override the name of
	 * @param  string $record_name  The human version of the record
	 * @return void
	 */
	static public function overrideRecordName($class, $record_name)
	{
		$class = self::getClass($class);
		self::$record_names[$class] = $record_name;
	}
	
	
	/**
	 * Parses a `camelCase` method name for an action and subject in the form `actionSubject()`
	 *
	 * @internal
	 * 
	 * @param  string $method  The method name to parse
	 * @return array  An array of `0 => {action}, 1 => {subject}`
	 */
	static public function parseMethod($method)
	{
		if (!preg_match('#^([a-z]+)(.*)$#', $method, $matches)) {
			fCore::toss(
				'fProgrammerException',
				fGrammar::compose(
					'Invalid method, %s(), called',
					$method
				)
			);	
		}
		return array($matches[1], fGrammar::underscorize($matches[2]));
	}
	
	
	/**
	 * Registers a callback for an fActiveRecord method that falls through to fActiveRecord::__call() or hits a predefined method hook
	 *  
	 * The callback should accept the following parameters:
	 * 
	 *  - **`$object`**:           The fActiveRecord instance
	 *  - **`&$values`**:          The values array for the record
	 *  - **`&$old_values`**:      The old values array for the record
	 *  - **`&$related_records`**: The related records array for the record
	 *  - **`$method_name`**:      The method that was called
	 *  - **`&$parameters`**:      The parameters passed to the method
	 * 
	 * @param  mixed    $class     The class name or instance of the class to register for, `'*'` will register for all classes
	 * @param  string   $method    The method to hook for
	 * @param  callback $callback  The callback to execute - see method description for parameter list
	 * @return void
	 */
	static public function registerActiveRecordMethod($class, $method, $callback)
	{
		$class = self::getClass($class);
		
		if (!isset(self::$active_record_method_callbacks[$class])) {
			self::$active_record_method_callbacks[$class] = array();	
		}
		
		self::$active_record_method_callbacks[$class][$method] = $callback;
	}
	
	
	/**
	 * Registers a callback for one of the various fActiveRecord hooks - multiple callbacks can be registered for each hook
	 * 
	 * The method signature should include the follow parameters:
	 * 
	 *  - **`$object`**:           The fActiveRecord instance
	 *  - **`&$values`**:          The values array for the record
	 *  - **`&$old_values`**:      The old values array for the record
	 *  - **`&$related_records`**: The related records array for the record
	 * 
	 * The `'pre::validate()'` and `'post::validate()'` hooks have an extra parameter:
	 * 
	 *  - **`&$validation_messages`**: An ordered array of validation errors that will be returned or tossed as an fValidationException
	 *  
	 * Below is a list of all valid hooks:
	 * 
	 *  - `'post::__construct()'`
	 *  - `'pre::delete()'`
	 *  - `'post-begin::delete()'`
	 *  - `'pre-commit::delete()'`
	 *  - `'post-commit::delete()'`
	 *  - `'post-rollback::delete()'`
	 *  - `'post::delete()'`
	 *  - `'post::inspect()'`
	 *  - `'post::loadFromResult()'`
	 *  - `'pre::populate()'`
	 *  - `'post::populate()'`
	 *  - `'pre::store()'`
	 *  - `'post-begin::store()'`
	 *  - `'post-validate::store()'`
	 *  - `'pre-commit::store()'`
	 *  - `'post-commit::store()'`
	 *  - `'post-rollback::store()'`
	 *  - `'post::store()'`
	 *  - `'pre::validate()'`
	 *  - `'post::validate()'`
	 * 
	 * @param  mixed    $class     The class name or instance of the class to hook, `'*'` will hook all classes
	 * @param  string   $hook      The hook to register for
	 * @param  callback $callback  The callback to register - see the method description for details about the method signature
	 * @return void
	 */
	static public function registerHookCallback($class, $hook, $callback)
	{
		$class = self::getClass($class);
		
		static $valid_hooks = array(
			'post::__construct()',
			'pre::delete()',
			'post-begin::delete()',
			'pre-commit::delete()',
			'post-commit::delete()',
			'post-rollback::delete()',
			'post::delete()',
			'post::inspect()',
			'post::loadFromResult()',
			'pre::populate()',
			'post::populate()',
			'pre::store()',
			'post-begin::store()',
			'post-validate::store()',
			'pre-commit::store()',
			'post-commit::store()',
			'post-rollback::store()',
			'post::store()',
			'pre::validate()',
			'post::validate()'
		);
		
		if (!in_array($hook, $valid_hooks)) {
			fCore::toss(
				'fProgrammerException',
				fGrammar::compose(
					'The hook specified, %1$s, should be one of: %2$s.',
					fCore::dump($hook),
					join(', ', $valid_hooks)
				)
			);
		}
		
		if (!isset(self::$hook_callbacks[$class])) {
			self::$hook_callbacks[$class] = array();
		}
		
		if (!isset(self::$hook_callbacks[$class][$hook])) {
			self::$hook_callbacks[$class][$hook] = array();
		}
		
		self::$hook_callbacks[$class][$hook][] = $callback;
	}
	
	
	/**
	 * Registers a callback for when ::objectify() is called on a specific column
	 * 
	 * @param  mixed    $class     The class name or instance of the class to register for
	 * @param  string   $column    The column to register for
	 * @param  callback $callback  The callback to register. Callback should accept a single parameter, the value to objectify and should return the objectified value.
	 * @return void
	 */
	static public function registerObjectifyCallback($class, $column, $callback)
	{
		$class = self::getClass($class);
		
		if (!isset(self::$objectify_callbacks[$class])) {
			self::$objectify_callbacks[$class] = array();
		}
		
		self::$objectify_callbacks[$class][$column] = $callback;
	}
	
	
	/**
	 * Registers a callback for an fRecordSet method that fall through to fRecordSet::__call()
	 *  
	 * The callback should accept the following parameters:
	 * 
	 *  - **`$object`**:      The actual record set
	 *  - **`$class`**:       The class of each record
	 *  - **`&$records`**:    The ordered array of fActiveRecords
	 *  - **`&$pointer`**:    The current array pointer for the records array
	 *  - **`&$associate`**:  If the record should be associated with an fActiveRecord holding it
	 * 
	 * @param  string   $method    The method to hook for
	 * @param  callback $callback  The callback to execute - see method description for parameter list
	 * @return void
	 */
	static public function registerRecordSetMethod($method, $callback)
	{
		self::$record_set_method_callbacks[$method] = $callback;
	}
	
	
	/**
	 * Registers a callback to modify the results of fActiveRecord::reflect()
	 * 
	 * Callbacks registered here can override default method signatures and add
	 * method signatures, however any methods that are defined in the actual class
	 * will override these signatures.
	 * 
	 * The callback should accept three parameters:
	 * 
	 *  - **`$class`**: the class name
	 *  - **`&$signatures`**: an associative array of `{method_name} => {signature}`
	 *  - **`$include_doc_comments`**: a boolean indicating if the signature should include the doc comment for the method, or just the signature
	 * 
	 * @param  mixed    $class     The class name or instance of the class to register for, `'*'` will register for all classes
	 * @param  callback $callback  The callback to register. Callback should accept a three parameters - see method description for details.
	 * @return void
	 */
	static public function registerReflectCallback($class, $callback)
	{
		$class = self::getClass($class);
		
		if (!isset(self::$reflect_callbacks[$class])) {
			self::$reflect_callbacks[$class] = array();
		} elseif (in_array($callback, self::$reflect_callbacks[$class])) {
			return;
		}
		
		self::$reflect_callbacks[$class][] = $callback;
	}
	
	
	/**
	 * Registers a callback for when ::scalarize() is called on a specific column
	 * 
	 * @param  mixed    $class     The class name or instance of the class to register for
	 * @param  string   $column    The column to register for
	 * @param  callback $callback  The callback to register. Callback should accept a single parameter, the value to scalarize and should return the scalarized value.
	 * @return void
	 */
	static public function registerScalarizeCallback($class, $column, $callback)
	{
		$class = self::getClass($class);
		
		if (!isset(self::$scalarize_callbacks[$class])) {
			self::$scalarize_callbacks[$class] = array();
		}
		
		self::$scalarize_callbacks[$class][$column] = $callback;
	}
	
	
	/**
	 * Resets the configuration of the class
	 * 
	 * @internal
	 * 
	 * @return void
	 */
	static public function reset()
	{
		self::$active_record_method_callbacks = array();
		self::$column_names                   = array();
		self::$configured                     = array();
		self::$hook_callbacks                 = array();
		self::$identity_map                   = array();
		self::$objectify_callbacks            = array();
		self::$record_names                   = array();
		self::$record_set_method_callbacks    = array();
		self::$reflect_callbacks              = array();
		self::$scalarize_callbacks            = array();
		self::$table_class_map                = array();
	}
	
	
	/**
	 * If the value passed is an object, calls `__toString()` on it
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
		$class = self::getClass($class);
		
		if (!empty(self::$scalarize_callbacks[$class][$column])) {
			return fCore::call(self::$scalarize_callbacks[$class][$column], $class, $column, $value);
		}
		
		if (is_object($value) && is_callable(array($value, '__toString'))) {
			return $value->__toString();
		} elseif (is_object($value)) {
			return (string) $value;
		}
		
		return $value;
	}
	
	
	/**
	 * Takes a class name (or class) and turns it into a table name - Uses custom mapping if set
	 * 
	 * @param  mixed $class  he class name or instance of the class
	 * @return string  The table name
	 */
	static public function tablize($class)
	{
		$class = self::getClass($class);
		
		if (!$table = array_search($class, self::$table_class_map)) {
			$table = fGrammar::underscorize(fGrammar::pluralize($class));
			self::$table_class_map[$table] = $class;
		}
		
		return $table;
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