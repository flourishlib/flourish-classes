<?php
/**
 * An active record pattern base class
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
	/**
	 * If debugging is turned on for the class
	 * 
	 * @var boolean
	 */
	protected $debug = NULL;
	
	/**
	 * The old values for this record
	 * 
	 * @var array
	 */
	protected $old_values = array();
	
	/**
	 * Related that are related to the current record via some relationship
	 * 
	 * @var array
	 */
	protected $related_records = array();
	
	/**
	 * The values for this record
	 * 
	 * @var array
	 */
	protected $values = array();
	
	
	/**
	 * Dynamically creates get{Column}(), set{Column}(), prepare{Column}(), encode{Column}() and inspect{Column}() methods for columns of this record
	 * 
	 * @throws fValidationException
	 * 
	 * @param  string $method_name  The name of the method called
	 * @param  string $parameters   The parameters passed
	 * @return void
	 */
	public function __call($method_name, $parameters)
	{
		list ($action, $subject) = explode('_', fInflection::underscorize($method_name), 2);
		
		if (fORM::checkHookCallback($this, 'replace::' . $method_name . '()')) {
			return fORM::callHookCallback(
				$this,
				'replace::' . $method_name . '()',
				$this->values,
				$this->old_values,
				$this->related_records,
				$this->debug,
				$method_name,
				$parameters
			);	
		}
		
		// This will prevent quiet failure
		if (($action == 'set' || $action == 'associate' || $action == 'inject') && sizeof($parameters) < 1) {
			fCore::toss(
				'fProgrammerException',
				fCore::compose(
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
				$subject = fInflection::singularize($subject);
				$subject = fInflection::camelize($subject, TRUE);
				
				if (isset($parameters[1])) {
					return fORMRelated::associateRecords($this, $this->related_records, $subject, $parameters[0], $parameters[1]);
				}
				return fORMRelated::associateRecords($this, $this->related_records, $subject, $parameters[0]);
			
			case 'build':
				$subject = fInflection::singularize($subject);
				$subject = fInflection::camelize($subject, TRUE);
				
				if (isset($parameters[0])) {
					return fORMRelated::constructRecordSet($this, $this->values, $this->related_records, $subject, $parameters[0]);
				}
				return fORMRelated::constructRecordSet($this, $this->values, $this->related_records, $subject);
			
			case 'create':
				$subject = fInflection::camelize($subject, TRUE);
				
				if (isset($parameters[0])) {
					return fORMRelated::constructRecord($this, $this->values, $subject, $parameters[0]);
				}
				return fORMRelated::constructRecord($this, $this->values, $subject);
			
			case 'inject':
				$subject = fInflection::singularize($subject);
				$subject = fInflection::camelize($subject, TRUE);
				
				if (isset($parameters[1])) {
					return fORMRelated::setRecords($this, $this->related_records, $subject, $parameters[0], $parameters[1]);
				}
				return fORMRelated::setRecords($this, $this->related_records, $subject, $parameters[0]);
			
			case 'link':
				$subject = fInflection::singularize($subject);
				$subject = fInflection::camelize($subject, TRUE);
				
				if (isset($parameters[0])) {
					return fORMRelated::linkRecords($this, $this->related_records, $subject, $parameters[0]);
				}
				return fORMRelated::linkRecords($this, $this->related_records, $subject);
			
			case 'populate':
				$subject = fInflection::singularize($subject);
				$subject = fInflection::camelize($subject, TRUE);
				
				if (isset($parameters[0])) {
					return fORMRelated::populateRecords($this, $this->related_records, $subject, $parameters[0]);
				}
				return fORMRelated::populateRecords($this, $this->related_records, $subject);
			
			// Error handler
			default:
				fCore::toss(
					'fProgrammerException',
					fCore::compose('Unknown method, %s, called', $method_name . '()')
				);
		}
	}
	
	
	/**
	 * Creates a record
	 * 
	 * @throws  fNotFoundException
	 * 
	 * @param  mixed $primary_key  The primary key value(s). If multi-field, use an associative array of (string) {field name} => (mixed) {value}.
	 * @return fActiveRecord
	 */
	public function __construct($primary_key=NULL)
	{
		// If the features of this class haven't been set yet, do it
		if (!fORM::isConfigured($this)) {
			$this->configure();
			fORM::flagConfigured($this);
		}
		
		if (fORM::checkHookCallback($this, 'replace::__construct()')) {
			return fORM::callHookCallback(
				$this,
				'replace::__construct()',
				$this->values,
				$this->old_values,
				$this->related_records,
				$this->debug,
				$primary_key
			);	
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
			
			if ($this->loadFromIdentityMap($primary_key)) {
				return;
			}
			
			// Check the primary keys
			$pk_columns = fORMSchema::getInstance()->getKeys(fORM::tablize($this), 'primary');
			if ((sizeof($pk_columns) > 1 && array_keys($primary_key) != $pk_columns) || (sizeof($pk_columns) == 1 && !is_scalar($primary_key))) {
				fCore::toss(
					'fProgrammerException',
					fCore::compose(
						'An invalidly formatted primary key was passed to this %s object',
						fORM::getRecordName($this)
					)
				);
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
			$column_info = fORMSchema::getInstance()->getColumnInfo(fORM::tablize($this));
			foreach ($column_info as $column => $info) {
				$this->values[$column] = NULL;
			}
		}
		
		fORM::callHookCallback(
			$this,
			'post::__construct()',
			$this->values,
			$this->old_values,
			$this->related_records,
			$this->debug
		);
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
	 * Delete a record from the database - does not destroy the object
	 * 
	 * This method qill start a database transaction if one is not already active.
	 * 
	 * @return void
	 */
	public function delete()
	{
		if (fORM::checkHookCallback($this, 'replace::delete()')) {
			return fORM::callHookCallback(
				$this,
				'replace::delete()',
				$this->values,
				$this->old_values,
				$this->related_records,
				$this->debug
			);	
		}	
		
		if (!$this->exists()) {
			fCore::toss(
				'fProgrammerException',
				fCore::compose(
					'This %s object does not yet exist in the database, and thus can not be deleted',
					fORM::getRecordName($this)
				)
			);
		}
		
		fORM::callHookCallback(
			$this, 'pre::delete()',
			$this->values,
			$this->old_values,
			$this->related_records,
			$this->debug
		);
		
		$table  = fORM::tablize($this);
		
		$inside_db_transaction = fORMDatabase::getInstance()->isInsideTransaction();
		
		try {
			
			if (!$inside_db_transaction) {
				fORMDatabase::getInstance()->translatedQuery('BEGIN');
			}
			
			fORM::callHookCallback(
				$this,
				'post-begin::delete()',
				$this->values,
				$this->old_values,
				$this->related_records,
				$this->debug
			);
			
			// Check to ensure no foreign dependencies prevent deletion
			$one_to_many_relationships  = fORMSchema::getInstance()->getRelationships($table, 'one-to-many');
			$many_to_many_relationships = fORMSchema::getInstance()->getRelationships($table, 'many-to-many');
			
			$relationships = array_merge($one_to_many_relationships, $many_to_many_relationships);
			$records_sets_to_delete = array();
			
			$restriction_messages = array();
			
			foreach ($relationships as $relationship) {
				
				// Figure out how to check for related records
				$type = (isset($relationship['join_table'])) ? 'many-to-many' : 'one-to-many';
				$route = fORMSchema::getRouteNameFromRelationship($type, $relationship);
				
				$related_class   = fORM::classize($relationship['related_table']);
				$related_objects = fInflection::pluralize($related_class);
				$method          = 'build' . $related_objects;
				
				// Grab the related records
				$record_set = $this->$method($route);
				
				// If there are none, we can just move on
				if (!$record_set->getCount()) {
					continue;
				}
				
				if ($type == 'one-to-many' && $relationship['on_delete'] == 'cascade') {
					$records_sets_to_delete[] = $record_set;
				}
				
				if ($relationship['on_delete'] == 'restrict' || $relationship['on_delete'] == 'no_action') {
					
					// Otherwise we have a restriction
					$related_class_name  = fORM::classize($relationship['foreign_table']);
					$related_record_name = fORM::getRecordName($related_class_name);
					$related_record_name = fInflection::pluralize($related_record_name);
					
					$restriction_message[] = fCore::compose("One or more %s references it", $related_record_name);
				}
			}
			
			if ($restriction_messages) {
				fCore::toss(
					'fValidationException',
					sprintf(
						"<p>%s</p>\n<ul>\n<li>%s</li>\n</ul>",
						fCore::compose('This %s can not be deleted because:', fORM::getRecordName($this)),
						join("</li>\n<li>", $restriction_messages)
					)
				);
			}
			
			
			// Delete this record
			$sql    = 'DELETE FROM ' . $table . ' WHERE ' . fORMDatabase::createPrimaryKeyWhereClause($table, $table, $this->values, $this->old_values);
			$result = fORMDatabase::getInstance()->translatedQuery($sql);
			
			
			// Delete related records
			foreach ($records_sets_to_delete as $record_set) {
				foreach ($record_set as $record) {
					if ($record->exists()) {
						$record->delete();
					}
				}
			}
			
			fORM::callHookCallback(
				$this,
				'pre-commit::delete()',
				$this->values,
				$this->old_values,
				$this->related_records,
				$this->debug
			);
			
			if (!$inside_db_transaction) {
				fORMDatabase::getInstance()->translatedQuery('COMMIT');
			}
			
			fORM::callHookCallback(
				$this,
				'post-commit::delete()',
				$this->values,
				$this->old_values,
				$this->related_records,
				$this->debug
			);
			
			// If we just deleted an object that has an auto-incrementing primary key,
			// lets delete that value from the object since it is no longer valid
			$column_info = fORMSchema::getInstance()->getColumnInfo($table);
			$pk_columns  = fORMSchema::getInstance()->getKeys($table, 'primary');
			if (sizeof($pk_columns) == 1 && $column_info[$pk_columns[0]]['auto_increment']) {
				$this->values[$pk_columns[0]] = NULL;
				unset($this->old_values[$pk_columns[0]]);
			}
			
		} catch (fPrintableException $e) {
			if (!$inside_db_transaction) {
				fORMDatabase::getInstance()->translatedQuery('ROLLBACK');
			}
			
			fORM::callHookCallback(
				$this,
				'post-rollback::delete()',
				$this->values,
				$this->old_values,
				$this->related_records,
				$this->debug
			);
			
			// Check to see if the validation exception came from a related record, and fix the message
			if ($e instanceof fValidationException) {
				$message = $e->getMessage();
				$search  = fCore::compose('This %s can not be deleted because:', fORM::getRecordName($this));
				if (stripos($message, $search) === FALSE) {
					$regex       = fCore::compose('This %s can not be deleted because:', '__');
					$regex_parts = explode('__', $regex);
					$regex       = '#(' . preg_quote($regex_parts[0], '#') . ').*?(' . preg_quote($regex_parts[0], '#') . ')#';
					
					$message = preg_replace($regex, '\1' . fORM::getRecordName($this) . '\2', $message);
					
					$find          = fCore::compose("One or more %s references it", '__');
					$find_parts    = explode('__', $find);
					$find_regex    = '#' . preg_quote($find_parts[0], '#') . '(.*?)' . preg_quote($find_parts[1], '#') . '#';
					
					$replace       = fCore::compose("One or more %s indirectly references it", '__');
					$replace_parts = explode('__', $replace);
					$replace_regex = $replace_parts[0] . '\1' . $replace_parts[1];
					
					$message = preg_replace($find_regex, $replace_regex, $regex);
					fCore::toss('fValidationException', $message);
				}
			}
			
			throw $e;
		}
		
		fORM::callHookCallback(
			$this,
			'post::delete()',
			$this->values,
			$this->old_values,
			$this->related_records,
			$this->debug
		);
	}
	
	
	/**
	 * Sets if debug messages should be shown
	 * 
	 * @param  boolean $flag  If debugging messages should be shown
	 * @return void
	 */
	public function enableDebugging($flag)
	{
		$this->debug = (boolean) $flag;
	}
	
	
	/**
	 * Retrieves a value from the record and prepares it for output into an HTML form element.
	 * 
	 * Below are the transformations performed:
	 *   - float: takes 1 parameter to specify the number of decimal places
	 *   - date, time, timestamp: format() will be called on the {@link fDate}/{@link fTime}/{@link fTimestamp} object with the 1 parameter specified
	 *   - objects: if a __toString() method exists, the output of that will be run throught {@link fHTML::encode()}
	 *   - all other data types: the value will be run through {@link fHTML::encode()}
	 * 
	 * @param  string $column      The name of the column to retrieve
	 * @param  string $formatting  The formatting string
	 * @return mixed  The encoded value for the column specified
	 */
	protected function encode($column, $formatting=NULL)
	{
		if (!array_key_exists($column, $this->values)) {
			fCore::toss(
				'fProgrammerException',
				fCore::compose(
					'The column specified, %s, does not exist',
					fCore::dump($column)
				)
			);
		}
		
		$column_type = fORMSchema::getInstance()->getColumnInfo(fORM::tablize($this), $column, 'type');
		
		// Ensure the programmer is calling the function properly
		if (in_array($column_type, array('blob'))) {
			fCore::toss(
				'fProgrammerException',
				fCore::compose(
					'The column specified, %s, does not support forming because it is a blob column',
					fCore::dump($column)
				)
			);
		}
		
		if ($formatting !== NULL && in_array($column_type, array('varchar', 'char', 'text', 'boolean', 'integer'))) {
			fCore::toss(
				'fProgrammerException',
				fCore::compose(
					'The column specified, %s, does not support any formatting options',
					fCore::dump($column)
				)
			);
		}
		
		// Grab the value for empty value checking
		$method_name = 'get' . fInflection::camelize($column, TRUE);
		$value       = $this->$method_name();
		
		// Date/time objects
		if (is_object($value) && in_array($column_type, array('date', 'time', 'timestamp'))) {
			if ($formatting === NULL) {
				fCore::toss(
					'fProgrammerException',
					fCore::compose(
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
		}
		
		// If we are left with an object at this point then we don't know what to do with it
		if (is_object($value)) {
			fCore::toss(
				'fProgrammerException',
				fCore::compose(
					'The column specified, %s, contains an object that does not have a __toString() method - unsure how to get object value',
					fCore::dump($column)
				)
			);
		}
		
		// Make sure we don't mangle a non-float value
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
		if (fORM::checkHookCallback($this, 'replace::exists()')) {
			return fORM::callHookCallback(
				$this,
				'replace::exists()',
				$this->values,
				$this->old_values,
				$this->related_records,
				$this->debug
			);	
		}
		
		$pk_columns = fORMSchema::getInstance()->getKeys(fORM::tablize($this), 'primary');
		foreach ($pk_columns as $pk_column) {
			if ((array_key_exists($pk_column, $this->old_values) && $this->old_values[$pk_column][0] === NULL) || $this->values[$pk_column] === NULL) {
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
				fCore::compose(
					'The column specified, %s, does not exist',
					fCore::dump($column)
				)
			);
		}
		return $this->values[$column];
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
				fCore::compose(
					'The column specified, %s, does not exist',
					fCore::dump($column)
				)
			);
		}
		
		$info = fORMSchema::getInstance()->getColumnInfo(fORM::tablize($this), $column);
		
		if ($element) {
			return (isset($info[$element])) ? $info[$element] : NULL;	
		}
		
		return $info;
	}
	
	
	/**
	 * Loads a record from the database
	 * 
	 * @throws  fNotFoundException
	 * 
	 * @return void
	 */
	public function load()
	{
		if (fORM::checkHookCallback($this, 'replace::load()')) {
			return fORM::callHookCallback(
				$this,
				'replace::load()',
				$this->values,
				$this->old_values,
				$this->related_records,
				$this->debug
			);	
		}
		
		try {
			$table = fORM::tablize($this);
			$sql = 'SELECT * FROM ' . $table . ' WHERE ' . fORMDatabase::createPrimaryKeyWhereClause($table, $table, $this->values, $this->old_values);
		
			$result = fORMDatabase::getInstance()->translatedQuery($sql);
			$result->tossIfNoResults();
			
		} catch (fExpectedException $e) {
			fCore::toss(
				'fNotFoundException',
				fCore::compose(
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
	protected function loadFromResult(fResult $result)
	{
		$row = $result->current();
		$column_info = fORMSchema::getInstance()->getColumnInfo(fORM::tablize($this));
		
		foreach ($row as $column => $value) {
			if ($value === NULL) {
				$this->values[$column] = $value;
			} elseif ($column_info[$column]['type'] == 'boolean') {
				$this->values[$column] = fORMDatabase::getInstance()->unescapeBoolean($value);
			} elseif ($column_info[$column]['type'] == 'blob') {
				$this->values[$column] = fORMDatabase::getInstance()->unescapeBlob($value);
			} elseif ($column_info[$column]['type'] == 'float' && $column_info[$column]['decimal_places'] !== NULL) {
				$this->values[$column] = number_format($value, $column_info[$column]['decimal_places'], '.', '');
			} else {
				$this->values[$column] = fORM::objectify($this, $column, $value);
			}
		}
		
		// Save this object to the identity map
		$pk_columns = fORMSchema::getInstance()->getKeys(fORM::tablize($this), 'primary');
		
		$pk_data = array();
		foreach ($pk_columns as $pk_column) {
			$pk_data[$pk_column] = $row[$pk_column];
		}
		
		fORM::saveToIdentityMap($this, $pk_data);
		
		fORM::callHookCallback(
			$this,
			'post::loadFromResult()',
			$this->values,
			$this->old_values,
			$this->related_records,
			$this->debug
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
		
		$pk_columns = fORMSchema::getInstance()->getKeys(fORM::tablize($this), 'primary');
		
		// If we don't have a value for each primary key, we can't load
		if (is_array($row) && !array_diff($pk_columns, array_keys($row))) {
			return FALSE;
		}
		
		// Build an array of just the primary key data
		$pk_data = array();
		foreach ($pk_columns as $pk_column) {
			$pk_data[$pk_column] = (is_array($row)) ? $row[$pk_column] : $row;
		}
		
		$object = fORM::checkIdentityMap($this, $pk_data);
		
		// A negative result implies this object has not been added to the indentity map yet
		if(!$object) {
			return FALSE;
		}
		
		// If we got a result back, it is the object we are creating
		$this->values          = &$object->values;
		$this->old_values      = &$object->old_values;
		$this->related_records = &$object->related_records;
		$this->debug           = &$object->debug;
		return TRUE;
	}
	
	
	/**
	 * Sets the values from this record via values from $_GET, $_POST and $_FILES
	 * 
	 * @return void
	 */
	public function populate()
	{
		if (fORM::checkHookCallback($this, 'replace::populate()')) {
			return fORM::callHookCallback(
				$this,
				'replace::populate()',
				$this->values,
				$this->old_values,
				$this->related_records,
				$this->debug
			);	
		}
		
		fORM::callHookCallback(
			$this,
			'pre::populate()',
			$this->values,
			$this->old_values,
			$this->related_records,
			$this->debug
		);
		
		$table = fORM::tablize($this);
		
		$column_info = fORMSchema::getInstance()->getColumnInfo($table);
		foreach ($column_info as $column => $info) {
			if (fRequest::check($column)) {
				$method = 'set' . fInflection::camelize($column, TRUE);
				$this->$method(fRequest::get($column));
			}
		}
		
		fORM::callHookCallback(
			$this,
			'post::populate()',
			$this->values,
			$this->old_values,
			$this->related_records,
			$this->debug
		);
	}
	
	
	/**
	 * Retrieves a value from the record and prepares it for output into html.
	 * 
	 * Below are the transformations performed:
	 *   - varchar, char, text columns: will run through {@link fHTML::prepare()}
	 *   - boolean: will return 'Yes' or 'No'
	 *   - integer: will add thousands/millions/etc. separators
	 *   - float: will add thousands/millions/etc. separators and takes 1 parameter to specify the number of decimal places
	 *   - date, time, timestamp: format() will be called on the {@link fDate}/{@link fTime}/{@link fTimestamp} object with the 1 parameter specified
	 *   - objects: if a __toString() method exists, the output of that will be run throught {@link fHTML::prepare()}
	 * 
	 * @param  string $column      The name of the column to retrieve
	 * @param  string $formatting  If php date() formatting string for date values
	 * @return mixed  The formatted value for the column specified
	 */
	protected function prepare($column, $formatting=NULL)
	{
		if (!array_key_exists($column, $this->values)) {
			fCore::toss(
				'fProgrammerException',
				fCore::compose(
					'The column specified, %s, does not exist',
					fCore::dump($column)
				)
			);
		}
		
		$column_info = fORMSchema::getInstance()->getColumnInfo(fORM::tablize($this), $column);
		$column_type = $column_info['type'];
		
		// Ensure the programmer is calling the function properly
		if (in_array($column_type, array('blob'))) {
			fCore::toss(
				'fProgrammerException',
				fCore::compose(
					'The column specified, %s, can not be prepared because it is a blob column',
					fCore::dump($column)
				)
			);
		}
		
		if ($formatting !== NULL && in_array($column_type, array('integer', 'boolean'))) {
			fCore::toss(
				'fProgrammerException',
				fCore::compose(
					'The column specified, %s, does not support any formatting options',
					fCore::dump($column)
				)
			);
		}
		
		// Grab the value for empty value checking
		$method_name = 'get' . fInflection::camelize($column, TRUE);
		$value       = $this->$method_name();
		
		// Date/time objects
		if (is_object($value) && in_array($column_type, array('date', 'time', 'timestamp'))) {
			if ($formatting === NULL) {
				fCore::toss(
					'fProgrammerException',
					fCore::compose(
						'The column specified, %s, requires one formatting parameter, a valid date() formatting string',
						fCore::dump($column)
					)
				);
			}
			return $value->format($formatting);
		}
		
		// Other objects
		if (is_object($value) && is_callable(array($value, '__toString'))) {
			return fHTML::prepare($value->__toString());
		}
		
		// If we are left with an object at this point then we don't know what to do with it
		if (is_object($value)) {
			fCore::toss(
				'fProgrammerException',
				fCore::compose(
					'The column specified, %s, contains an object that does not have a __toString() method - unsure how to get object value',
					fCore::dump($column)
				)
			);
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
			return fHTML::createLinks(fHTML::convertNewlines(fHTML::prepare($value)));		
		}
		
		// Anything that has gotten to here is a string value, or is not the
		// proper data type for the column, so we just make sure it is marked
		// up properly for display in HTML
		return fHTML::prepare($value);
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
				fCore::compose(
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
		
		if (!isset($this->old_values[$column])) {
			$this->old_values[$column] = array();	
		}
		
		$this->old_values[$column][] = $this->values[$column];
		$this->values[$column]       = $value;
	}
	
	
	/**
	 * Stores a record in the database. Will start database and filesystem
	 * transactions if not already inside them.
	 * 
	 * @throws  fValidationException
	 * 
	 * @return void
	 */
	public function store()
	{
		if (fORM::checkHookCallback($this, 'replace::store()')) {
			return fORM::callHookCallback(
				$this,
				'replace::store()',
				$this->values,
				$this->old_values,
				$this->related_records,
				$this->debug
			);	
		}
		
		fORM::callHookCallback(
			$this,
			'pre::store()',
			$this->values,
			$this->old_values,
			$this->related_records,
			$this->debug
		);
		
		try {
			$table       = fORM::tablize($this);
			$column_info = fORMSchema::getInstance()->getColumnInfo($table);
			
			// New auto-incrementing records require lots of special stuff, so we'll detect them here
			$new_autoincrementing_record = FALSE;
			if (!$this->exists()) {
				$pk_columns = fORMSchema::getInstance()->getKeys($table, 'primary');
				
				if (sizeof($pk_columns) == 1 && $column_info[$pk_columns[0]]['auto_increment']) {
					$new_autoincrementing_record = TRUE;
					$pk_column = $pk_columns[0];
				}
			}
			
			$inside_db_transaction = fORMDatabase::getInstance()->isInsideTransaction();
			
			if (!$inside_db_transaction) {
				fORMDatabase::getInstance()->translatedQuery('BEGIN');
			}
			
			fORM::callHookCallback(
				$this,
				'post-begin::store()',
				$this->values,
				$this->old_values,
				$this->related_records,
				$this->debug
			);
			
			$this->validate();
			
			fORM::callHookCallback(
				$this,
				'post-validate::store()',
				$this->values,
				$this->old_values,
				$this->related_records,
				$this->debug
			);
			
			// Storing main table
			
			$sql_values = array();
			foreach ($column_info as $column => $info) {
				$value = fORM::scalarize($this, $column, $this->values[$column]);
				$sql_values[$column] = fORMDatabase::prepareBySchema($table, $column, $value);
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
			$result = fORMDatabase::getInstance()->translatedQuery($sql);
			
			
			// If there is an auto-incrementing primary key, grab the value from the database
			if ($new_autoincrementing_record) {
				$this->set($pk_column, $result->getAutoIncrementedValue());
			}
			
			
			// Storing *-to-many relationships
			
			$one_to_many_relationships  = fORMSchema::getInstance()->getRelationships($table, 'one-to-many');
			$many_to_many_relationships = fORMSchema::getInstance()->getRelationships($table, 'many-to-many');
			
			foreach ($this->related_records as $related_table => $relationship) {
				foreach ($relationship as $route => $record_set) {
					if (!$record_set->isFlaggedForAssociation()) {
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
			
			fORM::callHookCallback(
				$this,
				'pre-commit::store()',
				$this->values,
				$this->old_values,
				$this->related_records,
				$this->debug
			);
			
			if (!$inside_db_transaction) {
				fORMDatabase::getInstance()->translatedQuery('COMMIT');
			}
			
			fORM::callHookCallback(
				$this,
				'post-commit::store()',
				$this->values,
				$this->old_values,
				$this->related_records,
				$this->debug
			);
			
		} catch (fPrintableException $e) {
			
			if (!$inside_db_transaction) {
				fORMDatabase::getInstance()->translatedQuery('ROLLBACK');
			}
			
			fORM::callHookCallback(
				$this,
				'post-rollback::store()',
				$this->values,
				$this->old_values,
				$this->related_records,
				$this->debug
			);
			
			if ($new_autoincrementing_record && array_key_exists($pk_column, $this->old_values)) {
				$this->values[$pk_column] = $this->old_values[$pk_column][0];
				unset($this->old_values[$pk_column]);
			}
			
			throw $e;
		}
		
		fORM::callHookCallback(
			$this,
			'post::store()',
			$this->values,
			$this->old_values,
			$this->related_records,
			$this->debug
		);
		
		// If we got here we succefully stored, so update old values to make exists() work 
		foreach ($this->values as $column => $value) {
			$this->old_values[$column] = array($value);
		}
	}
	
	
	/**
	 * Validates the contents of the record
	 * 
	 * @throws  fValidationException
	 * 
	 * @param  boolean $return_messages  If an array of validation messages should be returned instead of an exception being thrown
	 * @return void|array  If $return_messages is TRUE, an array of validation messages will be returned
	 */
	public function validate($return_messages=FALSE)
	{
		if (fORM::checkHookCallback($this, 'replace::validate()')) {
			return fORM::callHookCallback(
				$this,
				'replace::validate()',
				$this->values,
				$this->old_values,
				$this->related_records,
				$this->debug,
				$return_messages
			);	
		}
		
		$validation_messages = array();
		
		fORM::callHookCallback(
			$this,
			'pre::validate()',
			$this->values,
			$this->old_values,
			$this->related_records,
			$this->debug,
			$validation_messages
		);
		
		$table = fORM::tablize($this);
		
		// Validate the local values
		$local_validation_messages = fORMValidation::validate($this, $this->values, $this->old_values);
		
		// Validate related records
		$related_validation_messages = fORMValidation::validateRelated($this, $this->related_records);	
		
		$validation_messages = array_merge($validation_messages, $local_validation_messages, $related_validation_messages);
		
		fORM::callHookCallback(
			$this,
			'post::validate()',
			$this->values,
			$this->old_values,
			$this->related_records,
			$this->debug,
			$validation_messages
		);
		
		fORMValidation::reorderMessages($table, $validation_messages);
		
		if ($return_messages) {
			return $validation_messages;	
		}
		
		if (!empty($validation_messages)) {
			fCore::toss(
				'fValidationException',
				sprintf(
					"<p>%s</p>\n<ul>\n<li>%s</li>\n</ul>",
					fCore::compose("The following problems we found:"),
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