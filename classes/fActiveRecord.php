<?php
/**
 * An [http://en.wikipedia.org/wiki/Active_record_pattern active record pattern] base class
 * 
 * This class uses fORMSchema to inspect your database and provides an
 * OO interface to a single database table. The class dynamically handles
 * method calls for getting, setting and other operations on columns. It also
 * dynamically handles retrieving and storing related records.
 * 
 * @copyright  Copyright (c) 2007-2008 William Bond
 * @author     William Bond [wb] <will@flourishlib.com>
 * @license    http://flourishlib.com/license
 * 
 * @package    Flourish
 * @link       http://flourishlib.com/fActiveRecord
 * 
 * @version    1.0.0b
 * @changes    1.0.0b  The initial implementation [wb, 2007-08-04]
 */
abstract class fActiveRecord
{
	// The following constants allow for nice looking callbacks to static methods
	const assign         = 'fActiveRecord::assign';
	const changed        = 'fActiveRecord::changed';
	const forceConfigure = 'fActiveRecord::forceConfigure';
	const hasOld         = 'fActiveRecord::hasOld';
	const retrieveOld    = 'fActiveRecord::retrieveOld';
	
	
	/**
	 * An array of flags indicating a class has been configured
	 * 
	 * @var array
	 */
	static protected $configured = array();
	
	
	/**
	 * Maps objects via their primary key
	 * 
	 * @var array
	 */
	static protected $identity_map = array();
	
	
	/**
	 * Sets a value to the `$values` array, preserving the old value in `$old_values`
	 *
	 * @internal
	 * 
	 * @param  array  &$values      The current values
	 * @param  array  &$old_values  The old values
	 * @param  string $column       The column to set
	 * @param  mixed  $value        The value to set
	 * @return void
	 */
	static public function assign(&$values, &$old_values, $column, $value)
	{
		if (!isset($old_values[$column])) {
			$old_values[$column] = array();
		}
		
		$old_values[$column][] = $values[$column];
		$values[$column]       = $value;	
	}
	
	
	/**
	 * Checks to see if a value has changed
	 *
	 * @internal
	 * 
	 * @param  array  &$values      The current values
	 * @param  array  &$old_values  The old values
	 * @param  string $column       The column to check
	 * @return void
	 */
	static public function changed(&$values, &$old_values, $column)
	{
		if (!isset($old_values[$column])) {
			return FALSE;
		}
		
		return $old_values[$column][0] != $values[$column];	
	}
	
	
	/**
	 * Ensures that ::configure() has been called for the class
	 *
	 * @internal
	 * 
	 * @param  string $class  The class to configure
	 * @return void
	 */
	static public function forceConfigure($class)
	{
		if (isset(self::$configured[$class])) {
			return;	
		}
		new $class();
	}
	
	
	/**
	 * Checks to see if an old value exists for a column 
	 *
	 * @internal
	 * 
	 * @param  array  &$old_values  The old values
	 * @param  string $column       The column to set
	 * @return boolean  If an old value for that column exists
	 */
	static public function hasOld(&$old_values, $column)
	{
		return array_key_exists($column, $old_values);
	}
	
	
	/**
	 * Retrieves the oldest value for a column or all old values
	 *
	 * @internal
	 * 
	 * @param  array   &$old_values  The old values
	 * @param  string  $column       The column to get
	 * @param  mixed   $default      The default value to return if no value exists
	 * @param  boolean $return_all   Return the array of all old values for this column instead of just the oldest
	 * @return mixed  The old value for the column
	 */
	static public function retrieveOld(&$old_values, $column, $default=NULL, $return_all=FALSE)
	{
		if (!isset($old_values[$column])) {
			return $default;	
		}
		
		if ($return_all === TRUE) {
			return $old_values[$column];	
		}
		
		return $old_values[$column][0];
	}
	
	
	/**
	 * A data store for caching data related to a record, the structure of this is completely up to the developer using it
	 * 
	 * @var array
	 */
	protected $cache = array();
	
	/**
	 * The old values for this record
	 * 
	 * Column names are the keys, but a column key will only be present if a
	 * value has changed. The value associated with each key is an array of
	 * old values with the first entry being the oldest value. The static 
	 * methods ::assign(), ::changed(), ::has() and ::retrieve() are the best 
	 * way to interact with this array.
	 * 
	 * @var array
	 */
	protected $old_values = array();
	
	/**
	 * Records that are related to the current record via some relationship
	 * 
	 * This array is used to cache related records so that a database query
	 * is not required each time related records are accessed. The fORMRelated
	 * class handles most of the interaction with this array.
	 * 
	 * @var array
	 */
	protected $related_records = array();
	
	/**
	 * The values for this record
	 * 
	 * This array always contains every column in the database table as a key
	 * with the value being the current value. 
	 * 
	 * @var array
	 */
	protected $values = array();
	
	
	/**
	 * Handles all method calls for columns, related records and hook callbacks
	 * 
	 * Dynamically handles `get`, `set`, `prepare`, `encode` and `inspect`
	 * methods for each column in this record. Method names are in the form
	 * `verbColumName()`.
	 * 
	 * This method also handles `associate`, `build`, `count` and `link` verbs
	 * for records in many-to-many relationships; `build`, `count` and
	 * `populate` verbs for all related records in one-to-many relationships
	 * and the `create` verb for all related records in *-to-one relationships.
	 * 
	 * Method callbacks registered through fORM::registerActiveRecordMethod()
	 * will be delegated via this method.
	 * 
	 * @param  string $method_name  The name of the method called
	 * @param  string $parameters   The parameters passed
	 * @return void
	 */
	public function __call($method_name, $parameters)
	{
		if ($callback = fORM::getActiveRecordMethod($this, $method_name)) {
			return fCore::call(
				$callback,
				array(
					$this,
					&$this->values,
					&$this->old_values,
					&$this->related_records,
					$method_name,
					$parameters
				)
			);
		}
		
		list ($action, $subject) = fORM::parseMethod($method_name);
		
		// This will prevent quiet failure
		if (in_array($action, array('set', 'associate', 'inject', 'tally')) && sizeof($parameters) < 1) {
			fCore::toss(
				'fProgrammerException',
				fGrammar::compose(
					'The method, %s, requires at least one parameter',
					$method_name . '()'
				)
			);
		}
		
		switch ($action) {
			
			// Value methods
			case 'encode':
				if (isset($parameters[0])) {
					return $this->encode($subject, $parameters[0]);
				}
				return $this->encode($subject);
			
			case 'get':
				if (isset($parameters[0])) {
					return $this->get($subject, $parameters[0]);
				}
				return $this->get($subject);
			
			case 'inspect':
				if (isset($parameters[0])) {
					return $this->inspect($subject, $parameters[0]);
				}
				return $this->inspect($subject);
			
			case 'prepare':
				if (isset($parameters[0])) {
					return $this->prepare($subject, $parameters[0]);
				}
				return $this->prepare($subject);
			
			case 'set':
				return $this->set($subject, $parameters[0]);
			
			// Related data methods
			case 'associate':
				$subject = fGrammar::singularize($subject);
				$subject = fGrammar::camelize($subject, TRUE);
				
				if (isset($parameters[1])) {
					return fORMRelated::associateRecords($this, $this->related_records, $subject, $parameters[0], $parameters[1]);
				}
				return fORMRelated::associateRecords($this, $this->related_records, $subject, $parameters[0]);
			
			case 'build':
				$subject = fGrammar::singularize($subject);
				$subject = fGrammar::camelize($subject, TRUE);
				
				if (isset($parameters[0])) {
					return fORMRelated::buildRecords($this, $this->values, $this->related_records, $subject, $parameters[0]);
				}
				return fORMRelated::buildRecords($this, $this->values, $this->related_records, $subject);
			
			case 'count':
				$subject = fGrammar::singularize($subject);
				$subject = fGrammar::camelize($subject, TRUE);
				
				if (isset($parameters[0])) {
					return fORMRelated::countRecords($this, $this->values, $this->related_records, $subject, $parameters[0]);
				}
				return fORMRelated::countRecords($this, $this->values, $this->related_records, $subject);
			
			case 'create':
				$subject = fGrammar::camelize($subject, TRUE);
				
				if (isset($parameters[0])) {
					return fORMRelated::createRecord($this, $this->values, $subject, $parameters[0]);
				}
				return fORMRelated::createRecord($this, $this->values, $subject);
			 
			case 'inject':
				$subject = fGrammar::singularize($subject);
				$subject = fGrammar::camelize($subject, TRUE);
				 
				if (isset($parameters[1])) {
					return fORMRelated::setRecords($this, $this->related_records, $subject, $parameters[0], $parameters[1]);
				}
				return fORMRelated::setRecords($this, $this->related_records, $subject, $parameters[0]);

			case 'link':
				$subject = fGrammar::singularize($subject);
				$subject = fGrammar::camelize($subject, TRUE);
				
				if (isset($parameters[0])) {
					return fORMRelated::linkRecords($this, $this->related_records, $subject, $parameters[0]);
				}
				return fORMRelated::linkRecords($this, $this->related_records, $subject);
			
			case 'populate':
				$subject = fGrammar::singularize($subject);
				$subject = fGrammar::camelize($subject, TRUE);
				
				if (isset($parameters[0])) {
					return fORMRelated::populateRecords($this, $this->related_records, $subject, $parameters[0]);
				}
				return fORMRelated::populateRecords($this, $this->related_records, $subject);
			
			case 'tally':
				$subject = fGrammar::singularize($subject);
				$subject = fGrammar::camelize($subject, TRUE);
				
				if (isset($parameters[1])) {
					return fORMRelated::tallyRecords($this, $this->related_records, $subject, $parameters[0], $parameters[1]);
				}
				return fORMRelated::tallyRecords($this, $this->related_records, $subject, $parameters[0]);
			
			// Error handler
			default:
				fCore::toss(
					'fProgrammerException',
					fGrammar::compose('Unknown method, %s, called', $method_name . '()')
				);
		}
	}
	
	
	/**
	 * Creates a new record or loads one from the database - if a primary key is provided the record will be loaded
	 * 
	 * @throws fNotFoundException
	 * 
	 * @param  mixed $primary_key  The primary key value(s). If multi-field, use an associative array of `(string) {field name} => (mixed) {value}`.
	 * @return fActiveRecord
	 */
	public function __construct($primary_key=NULL)
	{
		$class = get_class($this);
		
		// If the features of this class haven't been set yet, do it
		if (!isset(self::$configured[$class])) {
			$this->configure();
			self::$configured[$class] = TRUE;
		}
		
		if (fORM::getActiveRecordMethod($this, '__construct')) {
			return $this->__call('__construct', array($primary_key));
		}
		
		// Handle loading by a result object passed via the fRecordSet class
		if (is_object($primary_key) && $primary_key instanceof fResult) {
			if ($this->loadFromIdentityMap($primary_key)) {
				return;
			}
			
			$this->loadFromResult($primary_key);
			return;
		}
		
		// Handle loading an object from the database
		if ($primary_key !== NULL) {
			
			// Check the primary keys
			$pk_columns = fORMSchema::retrieve()->getKeys(fORM::tablize($this), 'primary');
			if ((sizeof($pk_columns) > 1 && array_keys($primary_key) != $pk_columns) || (sizeof($pk_columns) == 1 && !is_scalar($primary_key))) {
				fCore::toss(
					'fProgrammerException',
					fGrammar::compose(
						'An invalidly formatted primary key was passed to this %s object',
						fORM::getRecordName($this)
					)
				);
			}
			
			if ($this->loadFromIdentityMap($primary_key)) {
				return;
			}
			
			// Assign the primary key values
			if (sizeof($pk_columns) > 1) {
				foreach ($pk_columns as $pk_column) {
					$this->values[$pk_column] = $primary_key[$pk_column];
				}
			} else {
				$this->values[$pk_columns[0]] = $primary_key;
			}
			
			$this->load();
			
		// Create an empty array for new objects
		} else {
			$column_info = fORMSchema::retrieve()->getColumnInfo(fORM::tablize($this));
			foreach ($column_info as $column => $info) {
				$this->values[$column] = NULL;
			}
		}
		
		fORM::callHookCallbacks(
			$this,
			'post::__construct()',
			$this->values,
			$this->old_values,
			$this->related_records
		);
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
	 * Allows the programmer to set features for the class
	 * 
	 * This method is only called once per page load for each class.
	 * 
	 * @return void
	 */
	protected function configure()
	{
	}
	
	
	/**
	 * Creates the SQL to insert this record
	 *
	 * @param  array $sql_values  The SQL-formatted values for this record
	 * @return string  The SQL insert statement
	 */
	protected function constructInsertSQL($sql_values)
	{
		$sql = 'INSERT INTO ' . fORM::tablize($this) . ' (';
		
		$columns = '';
		$values  = '';
		
		$column_num = 0;
		foreach ($sql_values as $column => $sql_value) {
			if ($column_num) { $columns .= ', '; $values .= ', '; }
			$columns .= $column;
			$values  .= $sql_value;
			$column_num++;
		}
		$sql .= $columns . ') VALUES (' . $values . ')';
		return $sql;
	}
	
	
	/**
	 * Creates the SQL to update this record
	 *
	 * @param  array $sql_values  The SQL-formatted values for this record
	 * @return string  The SQL update statement
	 */
	protected function constructUpdateSQL($sql_values)
	{
		$table = fORM::tablize($this);
		
		$sql = 'UPDATE ' . $table . ' SET ';
		$column_num = 0;
		foreach ($sql_values as $column => $sql_value) {
			if ($column_num) { $sql .= ', '; }
			$sql .= $column . ' = ' . $sql_value;
			$column_num++;
		}
		
		$sql .= ' WHERE ' . fORMDatabase::createPrimaryKeyWhereClause($table, $table, $this->values, $this->old_values);
		
		return $sql;
	}
	
	
	/**
	 * Deletes a record from the database, but does not destroy the object
	 * 
	 * This method qill start a database transaction if one is not already active.
	 * 
	 * @return void
	 */
	public function delete()
	{
		if (fORM::getActiveRecordMethod($this, 'delete')) {
			return $this->__call('delete', array());
		}
		
		if (!$this->exists()) {
			fCore::toss(
				'fProgrammerException',
				fGrammar::compose(
					'This %s object does not yet exist in the database, and thus can not be deleted',
					fORM::getRecordName($this)
				)
			);
		}
		
		fORM::callHookCallbacks(
			$this, 'pre::delete()',
			$this->values,
			$this->old_values,
			$this->related_records
		);
		
		$table  = fORM::tablize($this);
		
		$inside_db_transaction = fORMDatabase::retrieve()->isInsideTransaction();
		
		try {
			
			if (!$inside_db_transaction) {
				fORMDatabase::retrieve()->translatedQuery('BEGIN');
			}
			
			fORM::callHookCallbacks(
				$this,
				'post-begin::delete()',
				$this->values,
				$this->old_values,
				$this->related_records
			);
			
			// Check to ensure no foreign dependencies prevent deletion
			$one_to_many_relationships  = fORMSchema::retrieve()->getRelationships($table, 'one-to-many');
			$many_to_many_relationships = fORMSchema::retrieve()->getRelationships($table, 'many-to-many');
			
			$relationships = array_merge($one_to_many_relationships, $many_to_many_relationships);
			$records_sets_to_delete = array();
			
			$restriction_messages = array();
			
			foreach ($relationships as $relationship) {
				
				// Figure out how to check for related records
				$type = (isset($relationship['join_table'])) ? 'many-to-many' : 'one-to-many';
				$route = fORMSchema::getRouteNameFromRelationship($type, $relationship);
				
				$related_class   = fORM::classize($relationship['related_table']);
				$related_objects = fGrammar::pluralize($related_class);
				$method          = 'build' . $related_objects;
				
				// Grab the related records
				$record_set = $this->$method($route);
				
				// If there are none, we can just move on
				if (!$record_set->count()) {
					continue;
				}
				
				if ($type == 'one-to-many' && $relationship['on_delete'] == 'cascade') {
					$records_sets_to_delete[] = $record_set;
				}
				
				if ($relationship['on_delete'] == 'restrict' || $relationship['on_delete'] == 'no_action') {
					
					// Otherwise we have a restriction
					$related_class_name  = fORM::classize($relationship['related_table']);
					$related_record_name = fORM::getRecordName($related_class_name);
					$related_record_name = fGrammar::pluralize($related_record_name);
					
					$restriction_messages[] = fGrammar::compose("One or more %s references it", $related_record_name);
				}
			}
			
			if ($restriction_messages) {
				fCore::toss(
					'fValidationException',
					sprintf(
						"<p>%1\$s</p>\n<ul>\n<li>%2\$s</li>\n</ul>",
						fGrammar::compose('This %s can not be deleted because:', fORM::getRecordName($this)),
						join("</li>\n<li>", $restriction_messages)
					)
				);
			}
			
			
			// Delete this record
			$sql    = 'DELETE FROM ' . $table . ' WHERE ' . fORMDatabase::createPrimaryKeyWhereClause($table, $table, $this->values, $this->old_values);
			$result = fORMDatabase::retrieve()->translatedQuery($sql);
			
			
			// Delete related records
			foreach ($records_sets_to_delete as $record_set) {
				foreach ($record_set as $record) {
					if ($record->exists()) {
						$record->delete();
					}
				}
			}
			
			fORM::callHookCallbacks(
				$this,
				'pre-commit::delete()',
				$this->values,
				$this->old_values,
				$this->related_records
			);
			
			if (!$inside_db_transaction) {
				fORMDatabase::retrieve()->translatedQuery('COMMIT');
			}
			
			fORM::callHookCallbacks(
				$this,
				'post-commit::delete()',
				$this->values,
				$this->old_values,
				$this->related_records
			);
			
			// If we just deleted an object that has an auto-incrementing primary key,
			// lets delete that value from the object since it is no longer valid
			$column_info = fORMSchema::retrieve()->getColumnInfo($table);
			$pk_columns  = fORMSchema::retrieve()->getKeys($table, 'primary');
			if (sizeof($pk_columns) == 1 && $column_info[$pk_columns[0]]['auto_increment']) {
				$this->values[$pk_columns[0]] = NULL;
				unset($this->old_values[$pk_columns[0]]);
			}
			
		} catch (fPrintableException $e) {
			if (!$inside_db_transaction) {
				fORMDatabase::retrieve()->translatedQuery('ROLLBACK');
			}
			
			fORM::callHookCallbacks(
				$this,
				'post-rollback::delete()',
				$this->values,
				$this->old_values,
				$this->related_records
			);
			
			// Check to see if the validation exception came from a related record, and fix the message
			if ($e instanceof fValidationException) {
				$message = $e->getMessage();
				$search  = fGrammar::compose('This %s can not be deleted because:', fORM::getRecordName($this));
				if (stripos($message, $search) === FALSE) {
					$regex       = fGrammar::compose('This %s can not be deleted because:', '__');
					$regex_parts = explode('__', $regex);
					$regex       = '#(' . preg_quote($regex_parts[0], '#') . ').*?(' . preg_quote($regex_parts[0], '#') . ')#';
					
					$message = preg_replace($regex, '\1' . fORM::getRecordName($this) . '\2', $message);
					
					$find          = fGrammar::compose("One or more %s references it", '__');
					$find_parts    = explode('__', $find);
					$find_regex    = '#' . preg_quote($find_parts[0], '#') . '(.*?)' . preg_quote($find_parts[1], '#') . '#';
					
					$replace       = fGrammar::compose("One or more %s indirectly references it", '__');
					$replace_parts = explode('__', $replace);
					$replace_regex = $replace_parts[0] . '\1' . $replace_parts[1];
					
					$message = preg_replace($find_regex, $replace_regex, $regex);
					fCore::toss('fValidationException', $message);
				}
			}
			
			throw $e;
		}
		
		fORM::callHookCallbacks(
			$this,
			'post::delete()',
			$this->values,
			$this->old_values,
			$this->related_records
		);
	}
	
	
	/**
	 * Retrieves a value from the record and prepares it for output into an HTML form element.
	 * 
	 * Below are the transformations performed:
	 *  
	 *  - **float**: takes 1 parameter to specify the number of decimal places
	 *  - **date, time, timestamp**: `format()` will be called on the fDate/fTime/fTimestamp object with the 1 parameter specified
	 *  - **objects**: the object will be converted to a string by `__toString()` or a `(string)` cast and then will be run through fHTML::encode()
	 *  - **all other data types**: the value will be run through fHTML::encode()
	 * 
	 * @param  string $column      The name of the column to retrieve
	 * @param  string $formatting  The formatting string
	 * @return string  The encoded value for the column specified
	 */
	protected function encode($column, $formatting=NULL)
	{
		if (!array_key_exists($column, $this->values)) {
			fCore::toss(
				'fProgrammerException',
				fGrammar::compose(
					'The column specified, %s, does not exist',
					fCore::dump($column)
				)
			);
		}
		
		$column_type = fORMSchema::retrieve()->getColumnInfo(fORM::tablize($this), $column, 'type');
		
		// Ensure the programmer is calling the function properly
		if (in_array($column_type, array('blob'))) {
			fCore::toss(
				'fProgrammerException',
				fGrammar::compose(
					'The column specified, %s, does not support forming because it is a blob column',
					fCore::dump($column)
				)
			);
		}
		
		if ($formatting !== NULL && in_array($column_type, array('varchar', 'char', 'text', 'boolean', 'integer'))) {
			fCore::toss(
				'fProgrammerException',
				fGrammar::compose(
					'The column specified, %s, does not support any formatting options',
					fCore::dump($column)
				)
			);
		}
		
		// Grab the value for empty value checking
		$method_name = 'get' . fGrammar::camelize($column, TRUE);
		$value       = $this->$method_name();
		
		// Date/time objects
		if (is_object($value) && in_array($column_type, array('date', 'time', 'timestamp'))) {
			if ($formatting === NULL) {
				fCore::toss(
					'fProgrammerException',
					fGrammar::compose(
						'The column specified, %s, requires one formatting parameter, a valid date() formatting string',
						fCore::dump($column)
					)
				);
			}
			$value = $value->format($formatting);
		}
		
		// Other objects
		if (is_object($value) && is_callable(array($value, '__toString'))) {
			$value = $value->__toString();
		} elseif (is_object($value)) {
			$value = (string) $value;	
		}
		
		// Make sure we don't mangle a non-float value
		if ($column_type == 'float' && is_numeric($value)) {
			$column_decimal_places = fORMSchema::retrieve()->getColumnInfo(fORM::tablize($this), $column, 'decimal_places');
			
			// If the user passed in a formatting value, use it
			if ($formatting !== NULL && is_numeric($formatting)) {
				$decimal_places = (int) $formatting;
				
			// If the column has a pre-defined number of decimal places, use that
			} elseif ($column_decimal_places !== NULL) {
				$decimal_places = $column_decimal_places;
			
			// This figures out how many decimal places are part of the current value
			} else {
				$value_parts    = explode('.', $value);
				$decimal_places = (!isset($value_parts[1])) ? 0 : strlen($value_parts[1]);
			}
			
			return number_format($value, $decimal_places, '.', ',');
		}
		
		// Anything that has gotten to here is a string value or is not the proper data type for the column that contains it
		return fHTML::encode($value);
	}
	
	
	/**
	 * Checks to see if the record exists in the database
	 * 
	 * @return boolean  If the record exists in the database
	 */
	public function exists()
	{
		if (fORM::getActiveRecordMethod($this, 'exists')) {
			return $this->__call('exists', array());
		}
		
		$pk_columns = fORMSchema::retrieve()->getKeys(fORM::tablize($this), 'primary');
		foreach ($pk_columns as $pk_column) {
			if ((self::hasOld($this->old_values, $pk_column) && self::retrieveOld($this->old_values, $pk_column) === NULL) || $this->values[$pk_column] === NULL) {
				return FALSE;
			}
		}
		return TRUE;
	}
	
	
	/**
	 * Retrieves a value from the record
	 * 
	 * @param  string $column  The name of the column to retrieve
	 * @return mixed  The value for the column specified
	 */
	protected function get($column)
	{
		if (!array_key_exists($column, $this->values)) {
			fCore::toss(
				'fProgrammerException',
				fGrammar::compose(
					'The column specified, %s, does not exist',
					fCore::dump($column)
				)
			);
		}
		return $this->values[$column];
	}
	
	
	/**
	 * Takes a row of data or a primary key and makes a hash from the primary key
	 * 
	 * @param  mixed $data   An array of the records data, an array of primary key data or a scalar primary key value
	 * @return string  A hash of the record's primary key value
	 */
	protected function hash($data)
	{
		$pk_columns = fORMSchema::retrieve()->getKeys(fORM::tablize($this), 'primary');
		
		// Build an array of just the primary key data
		$pk_data = array();
		foreach ($pk_columns as $pk_column) {
			$pk_data[$pk_column] = fORM::scalarize(
				$this,
				$pk_column,
				is_array($data) ? $data[$pk_column] : $data
			);
			if (fCore::stringlike($pk_data[$pk_column])) {
				$pk_data[$pk_column] = (string) $pk_data[$pk_column];	
			}
		}
		
		return md5(serialize($pk_data));
	}
	
	
	/**
	 * Retrieves information about a column
	 * 
	 * @param  string $column   The name of the column to inspect
	 * @param  string $element  The metadata element to retrieve
	 * @return mixed  The metadata array for the column, or the metadata element specified
	 */
	protected function inspect($column, $element=NULL)
	{
		if (!array_key_exists($column, $this->values)) {
			fCore::toss(
				'fProgrammerException',
				fGrammar::compose(
					'The column specified, %s, does not exist',
					fCore::dump($column)
				)
			);
		}
		
		$info = fORMSchema::retrieve()->getColumnInfo(fORM::tablize($this), $column);
		
		if (!in_array($info['type'], array('varchar', 'char', 'text'))) {
			unset($info['valid_values']);
			unset($info['max_length']);
		}
		
		if ($info['type'] != 'float') {
			unset($info['decimal_places']);
		}
		
		if ($info['type'] != 'integer') {
			unset($info['auto_increment']);
		}
		
		$info['feature'] = NULL;
		
		if ($element) {
			if (!isset($info[$element])) {
				fCore::toss(
					'fProgrammerException',
					fGrammar::compose(
						'The element specified, %1$s, is invalid. Must be one of: %2$s.',
						fCore::dump($element),
						join(', ', array_keys($info))
					)
				);
			}
			return $info[$element];
		}
		
		return $info;
	}
	
	
	/**
	 * Loads a record from the database
	 * 
	 * @throws fNotFoundException
	 * 
	 * @return void
	 */
	public function load()
	{
		if (fORM::getActiveRecordMethod($this, 'load')) {
			return $this->__call('load', array());
		}
		
		try {
			$table = fORM::tablize($this);
			$sql = 'SELECT * FROM ' . $table . ' WHERE ' . fORMDatabase::createPrimaryKeyWhereClause($table, $table, $this->values, $this->old_values);
		
			$result = fORMDatabase::retrieve()->translatedQuery($sql);
			$result->tossIfNoResults();
			
		} catch (fExpectedException $e) {
			fCore::toss(
				'fNotFoundException',
				fGrammar::compose(
					'The %s requested could not be found',
					fORM::getRecordName($this)
				)
			);
		}
		
		$this->loadFromResult($result);
	}
	
	
	/**
	 * Loads a record from the database directly from a result object
	 * 
	 * @param  fResult $result  The result object to use for loading the current object
	 * @return void
	 */
	protected function loadFromResult($result)
	{
		$row         = $result->current();
		$column_info = fORMSchema::retrieve()->getColumnInfo(fORM::tablize($this));
		
		foreach ($row as $column => $value) {
			if ($value === NULL) {
				$this->values[$column] = $value;
			} else {
				$this->values[$column] = fORMDatabase::retrieve()->unescape($column_info[$column]['type'], $value);
			}
			
			$this->values[$column] = fORM::objectify($this, $column, $this->values[$column]);
		}
		
		// Save this object to the identity map
		$class = get_class($this);
		$hash  = $this->hash($row);
		
		if (!isset(self::$identity_map[$class])) {
			self::$identity_map[$class] = array(); 		
		}
		self::$identity_map[$class][$hash] = $this;
		
		fORM::callHookCallbacks(
			$this,
			'post::loadFromResult()',
			$this->values,
			$this->old_values,
			$this->related_records
		);
	}
	
	
	/**
	 * Tries to load the object (via references to class vars) from the fORM identity map
	 * 
	 * @param  fResult|array $source  The data source for the primary key values
	 * @return boolean  If the load was successful
	 */
	protected function loadFromIdentityMap($source)
	{
		if ($source instanceof fResult) {
			$row = $source->current();
		} else {
			$row = $source;
		}
		
		$class = get_class($this);
		
		if (!isset(self::$identity_map[$class])) {
			return FALSE;
		}
		
		$hash = $this->hash($row);
		
		if (!isset(self::$identity_map[$class][$hash])) {
			return FALSE;
		}
		
		$object = self::$identity_map[$class][$hash];
		
		// If we got a result back, it is the object we are creating
		$this->cache           = &$object->cache;
		$this->values          = &$object->values;
		$this->old_values      = &$object->old_values;
		$this->related_records = &$object->related_records;
		return TRUE;
	}
	
	
	/**
	 * Sets the values for this record by getting values from the request through the fRequest class
	 * 
	 * @return void
	 */
	public function populate()
	{
		if (fORM::getActiveRecordMethod($this, 'populate')) {
			return $this->__call('populate', array());
		}
		
		fORM::callHookCallbacks(
			$this,
			'pre::populate()',
			$this->values,
			$this->old_values,
			$this->related_records
		);
		
		$table = fORM::tablize($this);
		
		$column_info = fORMSchema::retrieve()->getColumnInfo($table);
		foreach ($column_info as $column => $info) {
			if (fRequest::check($column)) {
				$method = 'set' . fGrammar::camelize($column, TRUE);
				$this->$method(fRequest::get($column));
			}
		}
		
		fORM::callHookCallbacks(
			$this,
			'post::populate()',
			$this->values,
			$this->old_values,
			$this->related_records
		);
	}
	
	
	/**
	 * Retrieves a value from the record and prepares it for output into html.
	 * 
	 * Below are the transformations performed:
	 * 
	 *  - **varchar, char, text**: will run through fHTML::prepare()
	 *  - **boolean**: will return `'Yes'` or `'No'`
	 *  - **integer**: will add thousands/millions/etc. separators
	 *  - **float**: will add thousands/millions/etc. separators and takes 1 parameter to specify the number of decimal places
	 *  - **date, time, timestamp**: `format()` will be called on the fDate/fTime/fTimestamp object with the 1 parameter specified
	 *  - **objects**: the object will be converted to a string by `__toString()` or a `(string)` cast and then will be run through fHTML::prepare()
	 * 
	 * @param  string $column      The name of the column to retrieve
	 * @param  mixed  $formatting  The formatting parameter, if applicable
	 * @return string  The formatted value for the column specified
	 */
	protected function prepare($column, $formatting=NULL)
	{
		if (!array_key_exists($column, $this->values)) {
			fCore::toss(
				'fProgrammerException',
				fGrammar::compose(
					'The column specified, %s, does not exist',
					fCore::dump($column)
				)
			);
		}
		
		$column_info = fORMSchema::retrieve()->getColumnInfo(fORM::tablize($this), $column);
		$column_type = $column_info['type'];
		
		// Ensure the programmer is calling the function properly
		if (in_array($column_type, array('blob'))) {
			fCore::toss(
				'fProgrammerException',
				fGrammar::compose(
					'The column specified, %s, can not be prepared because it is a blob column',
					fCore::dump($column)
				)
			);
		}
		
		if ($formatting !== NULL && in_array($column_type, array('integer', 'boolean'))) {
			fCore::toss(
				'fProgrammerException',
				fGrammar::compose(
					'The column specified, %s, does not support any formatting options',
					fCore::dump($column)
				)
			);
		}
		
		// Grab the value for empty value checking
		$method_name = 'get' . fGrammar::camelize($column, TRUE);
		$value       = $this->$method_name();
		
		// Date/time objects
		if (is_object($value) && in_array($column_type, array('date', 'time', 'timestamp'))) {
			if ($formatting === NULL) {
				fCore::toss(
					'fProgrammerException',
					fGrammar::compose(
						'The column specified, %s, requires one formatting parameter, a valid date() formatting string',
						fCore::dump($column)
					)
				);
			}
			return $value->format($formatting);
		}
		
		// Other objects
		if (is_object($value) && is_callable(array($value, '__toString'))) {
			$value = $value->__toString();
		} elseif (is_object($value)) {
			$value = (string) $value;	
		}
		
		// Ensure the value matches the data type specified to prevent mangling
		if ($column_type == 'boolean' && is_bool($value)) {
			return ($value) ? 'Yes' : 'No';
		}
		
		if ($column_type == 'integer' && is_numeric($value)) {
			return number_format($value, 0, '', ',');
		}
		
		if ($column_type == 'float' && is_numeric($value)) {
			// If the user passed in a formatting value, use it
			if ($formatting !== NULL && is_numeric($formatting)) {
				$decimal_places = (int) $formatting;
				
			// If the column has a pre-defined number of decimal places, use that
			} elseif ($column_info['decimal_places'] !== NULL) {
				$decimal_places = $column_info['decimal_places'];
			
			// This figures out how many decimal places are part of the current value
			} else {
				$value_parts    = explode('.', $value);
				$decimal_places = (!isset($value_parts[1])) ? 0 : strlen($value_parts[1]);
			}
			
			return number_format($value, $decimal_places, '.', ',');
		}
		
		// Turn like-breaks into breaks for text fields and add links
		if ($formatting === TRUE && in_array($column_type, array('varchar', 'char', 'text'))) {
			return fHTML::makeLinks(fHTML::convertNewlines(fHTML::prepare($value)));
		}
		
		// Anything that has gotten to here is a string value, or is not the
		// proper data type for the column, so we just make sure it is marked
		// up properly for display in HTML
		return fHTML::prepare($value);
	}
	
	
	/**
	 * Generates a preormatted block of text containing the method signatures for all methods (including dynamic ones)
	 * 
	 * @param  boolean $include_doc_comments  If the doc block comments for each method should be included
	 * @return string  A preformatted block of text with the method signatures and optionally the doc comment
	 */
	public function reflect($include_doc_comments=FALSE)
	{
		$signatures = array();
		
		$columns_info = fORMSchema::retrieve()->getColumnInfo(fORM::tablize($this));
		foreach ($columns_info as $column => $column_info) {
			$camelized_column = fGrammar::camelize($column, TRUE);
			
			// Get and set methods
			$signature = '';
			if ($include_doc_comments) {
				$fixed_type = $column_info['type'];
				if ($fixed_type == 'blob') {
					$fixed_type = 'string';
				}
				if ($fixed_type == 'date') {
					$fixed_type = 'fDate';
				}
				if ($fixed_type == 'timestamp') {
					$fixed_type = 'fTimestamp';
				}
				if ($fixed_type == 'time') {
					$fixed_type = 'fTime';
				}
				
				$signature .= "/**\n";
				$signature .= " * Gets the current value of " . $column . "\n";
				$signature .= " * \n";
				$signature .= " * @return " . $fixed_type . "  The current value\n";
				$signature .= " */\n";
			}
			$get_method = 'get' . $camelized_column;
			$signature .= 'public function ' . $get_method . '()';
			
			$signatures[$get_method] = $signature;
			
			
			$signature = '';
			if ($include_doc_comments) {
				$fixed_type = $column_info['type'];
				if ($fixed_type == 'blob') {
					$fixed_type = 'string';
				}
				if ($fixed_type == 'date') {
					$fixed_type = 'fDate|string';
				}
				if ($fixed_type == 'timestamp') {
					$fixed_type = 'fTimestamp|string';
				}
				if ($fixed_type == 'time') {
					$fixed_type = 'fTime|string';
				}
				
				$signature .= "/**\n";
				$signature .= " * Sets the value for " . $column . "\n";
				$signature .= " * \n";
				$signature .= " * @param  " . $fixed_type . " \$" . $column . "  The new value\n";
				$signature .= " * @return void\n";
				$signature .= " */\n";
			}
			$set_method = 'set' . $camelized_column;
			$signature .= 'public function ' . $set_method . '($' . $column . ')';
			
			$signatures[$set_method] = $signature;
			
			
			// The encode method
			$signature = '';
			if ($include_doc_comments) {
				$signature .= "/**\n";
				$signature .= " * Encodes the value of " . $column . " for output into an HTML form\n";
				$signature .= " * \n";
				
				if (in_array($column_info['type'], array('time', 'timestamp', 'date'))) {
					$signature .= " * @param  string \$date_formatting_string  A date() compatible formatting string\n";
				}
				if (in_array($column_info['type'], array('float'))) {
					$signature .= " * @param  integer \$decimal_places  The number of decimal places to include - if not specified will default to the precision of the column or the current value\n";
				}
				
				$signature .= " * @return string  The HTML form-ready value\n";
				$signature .= " */\n";
			}
			$encode_method = 'encode' . $camelized_column;
			$signature .= 'public function ' . $encode_method . '(';
			if (in_array($column_info['type'], array('time', 'timestamp', 'date'))) {
				$signature .= '$date_formatting_string';
			}
			if (in_array($column_info['type'], array('float'))) {
				$signature .= '$decimal_places=NULL';
			}
			$signature .= ')';
			
			$signatures[$encode_method] = $signature;
			
			
			// The prepare method
			$signature = '';
			if ($include_doc_comments) {
				$signature .= "/**\n";
				$signature .= " * Prepares the value of " . $column . " for output into HTML\n";
				$signature .= " * \n";
				
				if (in_array($column_info['type'], array('time', 'timestamp', 'date'))) {
					$signature .= " * @param  string \$date_formatting_string  A date() compatible formatting string\n";
				}
				if (in_array($column_info['type'], array('float'))) {
					$signature .= " * @param  integer \$decimal_places  The number of decimal places to include - if not specified will default to the precision of the column or the current value\n";
				}
				if (in_array($column_info['type'], array('varchar', 'char', 'text'))) {
					$signature .= " * @param  boolean \$create_links_and_line_breaks  Will cause links to be automatically converted into [a] tags and line breaks into [br] tags \n";;
				}
				
				$signature .= " * @return string  The HTML-ready value\n";
				$signature .= " */\n";
			}
			$prepare_method = 'prepare' . $camelized_column;
			$signature .= 'public function ' . $prepare_method . '(';
			if (in_array($column_info['type'], array('time', 'timestamp', 'date'))) {
				$signature .= '$date_formatting_string';
			}
			if (in_array($column_info['type'], array('float'))) {
				$signature .= '$decimal_places=NULL';
			}
			if (in_array($column_info['type'], array('varchar', 'char', 'text'))) {
				$signature .= '$create_links_and_line_breaks=FALSE';
			}
			$signature .= ')';
			
			$signatures[$prepare_method] = $signature;
			
			
			// The inspect method
			$signature = '';
			if ($include_doc_comments) {
				$signature .= "/**\n";
				$signature .= " * Returns metadata about " . $column . "\n";
				$signature .= " * \n";
				$elements = array('type', 'not_null', 'default');
				if (in_array($column_info['type'], array('varchar', 'char', 'text'))) {
					$elements[] = 'valid_values';
					$elements[] = 'max_length';
				}
				if ($column_info['type'] == 'float') {
					$elements[] = 'decimal_places';
				}
				if ($column_info['type'] == 'integer') {
					$elements[] = 'auto_increment';
				}
				$signature .= " * @param  string \$element  The element to return. Must be one of: '" . join("', '", $elements) . "'.\n";
				$signature .= " * @return mixed  The metadata array or a single element\n";
				$signature .= " */\n";
			}
			$inspect_method = 'inspect' . $camelized_column;
			$signature .= 'public function ' . $inspect_method . '($element=NULL)';
			
			$signatures[$inspect_method] = $signature;
		}
		
		fORMRelated::reflect($this, $signatures, $include_doc_comments);
		
		fORM::callReflectCallbacks($this, $signatures, $include_doc_comments);
		
		$reflection = new ReflectionClass(get_class($this));
		$methods    = $reflection->getMethods();
		
		foreach ($methods as $method) {
			$signature = '';
			
			if (!$method->isPublic() || $method->getName() == '__call') {
				continue;
			}
			
			if ($method->isFinal()) {
				$signature .= 'final ';
			}
			
			if ($method->isAbstract()) {
				$signature .= 'abstract ';
			}
			
			if ($method->isStatic()) {
				$signature .= 'static ';
			}
			
			$signature .= 'public function ';
			
			if ($method->returnsReference()) {
				$signature .= '&';
			}
			
			$signature .= $method->getName();
			$signature .= '(';
			
			$parameters = $method->getParameters();
			foreach ($parameters as $parameter) {
				if (substr($signature, -1) == '(') {
					$signature .= '';
				} else {
					$signature .= ', ';
				}
				
				$signature .= '$' . $parameter->getName();
				
				if ($parameter->isDefaultValueAvailable()) {
					$val = var_export($parameter->getDefaultValue(), TRUE);
					if ($val == 'true') {
						$val = 'TRUE';
					}
					if ($val == 'false') {
						$val = 'FALSE';
					}
					if (is_array($parameter->getDefaultValue())) {
						$val = preg_replace('#array\s+\(\s+#', 'array(', $val);
						$val = preg_replace('#,(\r)?\n  #', ', ', $val);
						$val = preg_replace('#,(\r)?\n\)#', ')', $val);
					}
					$signature .= '=' . $val;
				}
			}
			
			$signature .= ')';
			
			if ($include_doc_comments) {
				$comment = $method->getDocComment();
				$comment = preg_replace('#^\t+#m', '', $comment);
				$signature = $comment . "\n" . $signature;
			}
			$signatures[$method->getName()] = $signature;
		}
		
		ksort($signatures);
		
		return join("\n\n", $signatures);
	}
	
	
	/**
	 * Sets a value to the record
	 * 
	 * @param  string $column  The column to set the value to
	 * @param  mixed  $value   The value to set
	 * @return void
	 */
	protected function set($column, $value)
	{
		if (!array_key_exists($column, $this->values)) {
			fCore::toss(
				'fProgrammerException',
				fGrammar::compose(
					'The column specified, %s, does not exist',
					fCore::dump($column)
				)
			);
		}
		
		// We consider an empty string to be equivalent to NULL
		if ($value === '') {
			$value = NULL;
		}
		
		$value = fORM::objectify($this, $column, $value);
		
		self::assign($this->values, $this->old_values, $column, $value);
	}
	
	
	/**
	 * Stores a record in the database, whether existing or new
	 * 
	 * This method will start database and filesystem transactions if they have
	 * not already been started.
	 * 
	 * @throws fValidationException
	 * 
	 * @return void
	 */
	public function store()
	{
		if (fORM::getActiveRecordMethod($this, 'store')) {
			return $this->__call('store', array());
		}
		
		fORM::callHookCallbacks(
			$this,
			'pre::store()',
			$this->values,
			$this->old_values,
			$this->related_records
		);
		
		try {
			$table       = fORM::tablize($this);
			$column_info = fORMSchema::retrieve()->getColumnInfo($table);
			
			// New auto-incrementing records require lots of special stuff, so we'll detect them here
			$new_autoincrementing_record = FALSE;
			if (!$this->exists()) {
				$pk_columns = fORMSchema::retrieve()->getKeys($table, 'primary');
				
				if (sizeof($pk_columns) == 1 && $column_info[$pk_columns[0]]['auto_increment']) {
					$new_autoincrementing_record = TRUE;
					$pk_column = $pk_columns[0];
				}
			}
			
			$inside_db_transaction = fORMDatabase::retrieve()->isInsideTransaction();
			
			if (!$inside_db_transaction) {
				fORMDatabase::retrieve()->translatedQuery('BEGIN');
			}
			
			fORM::callHookCallbacks(
				$this,
				'post-begin::store()',
				$this->values,
				$this->old_values,
				$this->related_records
			);
			
			$this->validate();
			
			fORM::callHookCallbacks(
				$this,
				'post-validate::store()',
				$this->values,
				$this->old_values,
				$this->related_records
			);
			
			// Storing main table
			
			$sql_values = array();
			foreach ($column_info as $column => $info) {
				$value = fORM::scalarize($this, $column, $this->values[$column]);
				$sql_values[$column] = fORMDatabase::escapeBySchema($table, $column, $value);
			}
			
			// Most databases don't like the auto incrementing primary key to be set to NULL
			if ($new_autoincrementing_record && $sql_values[$pk_column] == 'NULL') {
				unset($sql_values[$pk_column]);
			}
			
			if (!$this->exists()) {
				$sql = $this->constructInsertSQL($sql_values);
			} else {
				$sql = $this->constructUpdateSQL($sql_values);
			}
			$result = fORMDatabase::retrieve()->translatedQuery($sql);
			
			
			// If there is an auto-incrementing primary key, grab the value from the database
			if ($new_autoincrementing_record) {
				$this->set($pk_column, $result->getAutoIncrementedValue());
			}
			
			
			// Storing *-to-many relationships
			
			$one_to_many_relationships  = fORMSchema::retrieve()->getRelationships($table, 'one-to-many');
			$many_to_many_relationships = fORMSchema::retrieve()->getRelationships($table, 'many-to-many');
			
			foreach ($this->related_records as $related_table => $relationship) {
				foreach ($relationship as $route => $info) {
					$record_set = $info['record_set'];
					if (!$record_set || !$record_set->isFlaggedForAssociation()) {
						continue;
					}
					
					$relationship = fORMSchema::getRoute($table, $related_table, $route);
					if (isset($relationship['join_table'])) {
						fORMRelated::storeManyToMany($this->values, $relationship, $record_set);
					} else {
						fORMRelated::storeOneToMany($this->values, $relationship, $record_set);
					}
				}
			}
			
			fORM::callHookCallbacks(
				$this,
				'pre-commit::store()',
				$this->values,
				$this->old_values,
				$this->related_records
			);
			
			if (!$inside_db_transaction) {
				fORMDatabase::retrieve()->translatedQuery('COMMIT');
			}
			
			fORM::callHookCallbacks(
				$this,
				'post-commit::store()',
				$this->values,
				$this->old_values,
				$this->related_records
			);
			
		} catch (fPrintableException $e) {
			
			if (!$inside_db_transaction) {
				fORMDatabase::retrieve()->translatedQuery('ROLLBACK');
			}
			
			fORM::callHookCallbacks(
				$this,
				'post-rollback::store()',
				$this->values,
				$this->old_values,
				$this->related_records
			);
			
			if ($new_autoincrementing_record && self::hasOld($this->old_values, $pk_column)) {
				$this->values[$pk_column] = self::retrieveOld($this->old_values, $pk_column);
				unset($this->old_values[$pk_column]);
			}
			
			throw $e;
		}
		
		fORM::callHookCallbacks(
			$this,
			'post::store()',
			$this->values,
			$this->old_values,
			$this->related_records
		);
		
		// If we got here we succefully stored, so update old values to make exists() work
		foreach ($this->values as $column => $value) {
			$this->old_values[$column] = array($value);
		}
	}
	
	
	/**
	 * Validates the values of the record against the database and any additional validation rules
	 * 
	 * @throws fValidationException
	 * 
	 * @param  boolean $return_messages  If an array of validation messages should be returned instead of an exception being thrown
	 * @return void|array  If $return_messages is TRUE, an array of validation messages will be returned
	 */
	public function validate($return_messages=FALSE)
	{
		if (fORM::getActiveRecordMethod($this, 'validate')) {
			return $this->__call('validate', array($return_messages));
		}
		
		$validation_messages = array();
		
		fORM::callHookCallbacks(
			$this,
			'pre::validate()',
			$this->values,
			$this->old_values,
			$this->related_records,
			$validation_messages
		);
		
		// Validate the local values
		$local_validation_messages = fORMValidation::validate($this, $this->values, $this->old_values);
		
		// Validate related records
		$related_validation_messages = fORMValidation::validateRelated($this, $this->related_records);
		
		$validation_messages = array_merge($validation_messages, $local_validation_messages, $related_validation_messages);
		
		fORM::callHookCallbacks(
			$this,
			'post::validate()',
			$this->values,
			$this->old_values,
			$this->related_records,
			$validation_messages
		);
		
		fORMValidation::reorderMessages($this, $validation_messages);
		
		if ($return_messages) {
			return $validation_messages;
		}
		
		if (!empty($validation_messages)) {
			fCore::toss(
				'fValidationException',
				sprintf(
					"<p>%1\$s</p>\n<ul>\n<li>%2\$s</li>\n</ul>",
					fGrammar::compose("The following problems were found:"),
					join("</li>\n<li>", $validation_messages)
				)
			);
		}
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