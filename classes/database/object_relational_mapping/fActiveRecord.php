<?php
/**
 * An active record pattern base class
 * 
 * @copyright  Copyright (c) 2007-2008 William Bond
 * @author     William Bond [wb] <will@flourishlib.com>
 * @license    http://flourishlib.com/license
 * 
 * @link  http://flourishlib.com/fActiveRecord
 * 
 * @todo  Add fFile support
 * @todo  Add fImage support
 * @todo  Add various hooks
 * @todo  Add reordering support
 * 
 * @version  1.0.0
 * @changes  1.0.0    The initial implementation [wb, 2007-08-04]
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
	 * Dynamically creates getColumn(), setColumn(), prepareColumn() for columns in the table.
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
			return fORM::callHookCallback($this, 'replace::' . $method_name . '()', $this->values, $this->old_values, $this->related_records, $this->debug, $method_name, $parameters);	
		}
		
		// This will prevent quiet failure
		if (($action == 'set' || $action == 'associate' || $action == 'inject') && sizeof($parameters) < 1) {
			fCore::toss('fProgrammerException', 'The method, ' . $method_name . '(), requires at least one parameter');
		}
		
		switch ($action) {
			
			// Value methods
			case 'encode':
				if (isset($parameters[0])) {
					return $this->entify($subject, $parameters[0]);
				}
				return $this->entify($subject);
			
			case 'get':
				if (isset($parameters[0])) {
					return $this->retrieve($subject, $parameters[0]);
				}
				return $this->retrieve($subject);
			
			case 'prepare':
				if (isset($parameters[0])) {
					return $this->format($subject, $parameters[0]);
				}
				return $this->format($subject);
			
			case 'set':
				return $this->assign($subject, $parameters[0]);
			
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
				fCore::toss('fProgrammerException', 'Unknown method, ' . $method_name . '(), called');
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
		if (!fORM::checkFeaturesSet($this)) {
			$this->configure();
			fORM::flagFeaturesSet($this);
		}
		
		if (fORM::checkHookCallback($this, 'replace::__construct()')) {
			return fORM::callHookCallback($this, 'replace::__construct()', $this->values, $this->old_values, $this->related_records, $this->debug, $primary_key);	
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
				fCore::toss('fProgrammerException', 'An invalidly formatted primary key was passed to this ' . fORM::getRecordName($this) . ' object');
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
		
		fORM::callHookCallback($this, 'post::__construct()', $this->values, $this->old_values, $this->related_records, $this->debug);
	}
	
	
	/**
	 * Sets a value to the record.
	 * 
	 * @param  string $column  The column to set the value to
	 * @param  mixed $value    The value to set
	 * @return void
	 */
	protected function assign($column, $value)
	{
		if (!array_key_exists($column, $this->values)) {
			fCore::toss('fProgrammerException', 'The column specified, ' . $column . ', does not exist');
		}
		
		// We consider an empty string to be equivalent to NULL
		if ($value === '') {
			$value = NULL;
		}
		
		$value = fORM::objectify($this, $column, $value);
		
		$this->old_values[$column] = $this->values[$column];
		$this->values[$column]     = $value;
	}
	
	
	/**
	 * Allows the programmer to set features for the class. Only called once per
	 * page load for each class.
	 * 
	 * @return void
	 */
	protected function configure()
	{
	}
	
	
	/**
	 * Creates the SQL to insert this record
	 *
	 * @param  array $sql_values  The sql-formatted values for this record
	 * @return string  The sql insert statement
	 */
	protected function constructInsertSql($sql_values)
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
	 * Creates the WHERE clause for the current primary key data
	 *
	 * @throws fValidationException
	 * 
	 * @return string  The WHERE clause for the current primary key data
	 */
	protected function constructPrimaryKeyWhereClause()
	{
		$sql        = '';
		$pk_columns = fORMSchema::getInstance()->getKeys(fORM::tablize($this), 'primary');
		$key_num    = 0;
		
		foreach ($pk_columns as $pk_column) {
			if ($key_num) { $sql .= " AND "; }
			
			if (!empty($this->old_values[$pk_column])) {
				$value = fORM::scalarize($this, $pk_column, $this->old_values[$pk_column]);
			} else {
				$value = fORM::scalarize($this, $pk_column, $this->values[$pk_column]);
			}
			
			$sql .= $pk_column . fORMDatabase::prepareBySchema(fORM::tablize($this), $pk_column, $value, '=');
			$key_num++;
		}
		
		return $sql;
	}
	
	
	/**
	 * Creates the SQL to update this record
	 *
	 * @param  array $sql_values  The sql-formatted values for this record
	 * @return string  The sql update statement
	 */
	protected function constructUpdateSql($sql_values)
	{
		$sql = 'UPDATE ' . fORM::tablize($this) . ' SET ';
		$column_num = 0;
		foreach ($sql_values as $column => $sql_value) {
			if ($column_num) { $sql .= ', '; }
			$sql .= $column . ' = ' . $sql_value;
			$column_num++;
		}
		$sql .= ' WHERE ' . $this->constructPrimaryKeyWhereClause();
		return $sql;
	}
	
	
	/**
	 * Delete a record from the database, does not destroy the object. Will
	 * start database and filesystem transactions if not already inside them
	 * 
	 * @return void
	 */
	public function delete()
	{
		if (fORM::checkHookCallback($this, 'replace::delete()')) {
			return fORM::callHookCallback($this, 'replace::delete()', $this->values, $this->old_values, $this->related_records, $this->debug);	
		}	
		
		if (!$this->exists()) {
			fCore::toss('fProgrammerException', 'The object does not yet exist in the database, and thus can not be deleted');
		}
		
		fORM::callHookCallback($this, 'pre::delete()', $this->values, $this->old_values, $this->related_records, $this->debug);
		
		$table  = fORM::tablize($this);
		
		$inside_db_transaction = fORMDatabase::getInstance()->isInsideTransaction();
		$inside_fs_transaction = fFilesystem::isInsideTransaction();
		
		try {
			
			if (!$inside_db_transaction) {
				fORMDatabase::getInstance()->translatedQuery('BEGIN');
			}
			if (!$inside_fs_transaction) {
				fFilesystem::startTransaction();
			}
			
			fORM::callHookCallback($this, 'post-begin::delete()', $this->values, $this->old_values, $this->related_records, $this->debug);
			
			// Check to ensure no foreign dependencies prevent deletion
			$one_to_many_relationships  = fORMSchema::getInstance()->getRelationships($table, 'one-to-many');
			$many_to_many_relationships = fORMSchema::getInstance()->getRelationships($table, 'many-to-many');
			
			$relationships = array_merge($one_to_many_relationships, $many_to_many_relationships);
			$records_sets_to_delete = array();
			
			$restriction_message = '';
			
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
					
					$restriction_message .= "<li>One or more " . $related_record_name . " references it</li>\n";
				}
			}
			
			if (!empty($restriction_message)) {
				fCore::toss('fValidationException', '<p>This ' . fORM::getRecordName($this) . ' can not be deleted because:</p><ul>' . $restriction_message . '</ul>');
			}
			
			
			// Delete this record
			$sql    = 'DELETE FROM ' . $table . ' WHERE ' . $this->constructPrimaryKeyWhereClause();
			$result = fORMDatabase::getInstance()->translatedQuery($sql);
			
			
			// Delete related records
			foreach ($records_sets_to_delete as $record_set) {
				foreach ($record_set as $record) {
					if ($record->exists()) {
						$record->delete();
					}
				}
			}
			
			fORM::callHookCallback($this, 'pre-commit::delete()', $this->values, $this->old_values, $this->related_records, $this->debug);
			
			if (!$inside_db_transaction) {
				fORMDatabase::getInstance()->translatedQuery('COMMIT');
			}
			if (!$inside_fs_transaction) {
				fFilesystem::commitTransaction();
			}
			
			
			// If we just deleted an object that has an auto-incrementing primary key, lets delete that value from the object since it is no longer valid
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
			if (!$inside_fs_transaction) {
				fFilesystem::rollbackTransaction();
			}
			
			fORM::callHookCallback($this, 'post-rollback::delete()', $this->values, $this->old_values, $this->related_records, $this->debug);
			
			// Check to see if the validation exception came from a related record, and fix the message
			if ($e instanceof fValidationException) {
				$message     = $e->getMessage();
				$record_name = fORM::getRecordName($this);
				if (stripos($message, 'This ' . $record_name) === FALSE) {
					$message = preg_replace('#(This ).*?( can not be deleted because)#is', '\1' . $record_name . '\2');
					$message = str_replace(' references it', ' indirectly refereces it');
					fCore::toss('fValidationException', $message);
				}
			}
			
			throw $e;
		}
		
		fORM::callHookCallback($this, 'post::delete()', $this->values, $this->old_values, $this->related_records, $this->debug);
	}
	
	
	/**
	 * Retrieves a value from the record and prepares it for output into an HTML form element.
	 * 
	 * Below are the transformations performed:
	 *   - float: takes 1 parameter to specify the number of decimal places
	 *   - date, time, timestamp: format() will be called on the fDate/fTime/fTimestamp object with the 1 parameter specified
	 *   - objects: if a __toString() method exists, the output of that will be run throught fHTML::encode()
	 *   - all other data types: the value will be run through fHTML::encode()
	 * 
	 * @param  string $column      The name of the column to retrieve
	 * @param  string $formatting  If php date() formatting string for date values
	 * @return mixed  The formatted value for the column specified
	 */
	protected function entify($column, $formatting=NULL)
	{
		if (!array_key_exists($column, $this->values)) {
			fCore::toss('fProgrammerException', 'The column specified, ' . $column . ', does not exist');
		}
		
		$column_type = fORMSchema::getInstance()->getColumnInfo(fORM::tablize($this), $column, 'type');
		
		// Ensure the programmer is calling the function properly
		if (in_array($column_type, array('blob'))) {
			fCore::toss('fProgrammerException', 'The column ' . $column . ' does not support forming because it is a blob column');
		}
		
		if ($formatting !== NULL && in_array($column_type, array('varchar', 'char', 'text', 'boolean', 'integer'))) {
			fCore::toss('fProgrammerException', 'The column ' . $column . ' does not support any formatting options');
		}
		
		// Grab the value for empty value checking
		$method_name = 'get' . fInflection::camelize($column, TRUE);
		$value       = $this->$method_name();
		
		// Date/time objects
		if (is_object($value) && in_array($column_type, array('date', 'time', 'timestamp'))) {
			if ($formatting === NULL) {
				fCore::toss('fProgrammerException', 'The column ' . $column . ' requires one formatting parameter, a valid date() formatting string');
			}
			$value = $value->format($formatting);
		}
		
		// Other objects
		if (is_object($value) && is_callable(array($value, '__toString'))) {
			$value = $value->__toString();
		}
		
		// If we are left with an object at this point then we don't know what to do with it
		if (is_object($value)) {
			fCore::toss('fProgrammerException', 'The column ' . $column . ' contains an object that does not have a __toString() method - unsure how to get object value');
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
			return fORM::callHookCallback($this, 'replace::exists()', $this->values, $this->old_values, $this->related_records, $this->debug);	
		}
		
		$pk_columns = fORMSchema::getInstance()->getKeys(fORM::tablize($this), 'primary');
		foreach ($pk_columns as $pk_column) {
			if ((array_key_exists($pk_column, $this->old_values) && $this->old_values[$pk_column] === NULL) || $this->values[$pk_column] === NULL) {
				return FALSE;
			}
		}
		return TRUE;
	}
	
	
	/**
	 * Retrieves a value from the record and prepares it for output into html.
	 * 
	 * Below are the transformations performed:
	 *   - varchar, char, text columns with email formatting rule: an <a> html tag with a mailto link to the email address - empty() values will return a blank string
	 *   - varchar, char, text columns with link formatting rule: an <a> html tag for the link - empty() values will return a blank string
	 *   - varchar, char, text columns: will run through fHTML::prepare()
	 *   - boolean: will return 'Yes' or 'No'
	 *   - integer: will add thousands/millions/etc. separators
	 *   - float: will add thousands/millions/etc. separators and takes 1 parameter to specify the number of decimal places
	 *   - date, time, timestamp: format() will be called on the fDate/fTime/fTimestamp object with the 1 parameter specified
	 *   - objects: if a __toString() method exists, the output of that will be run throught fHTML::prepare()
	 * 
	 * @param  string $column      The name of the column to retrieve
	 * @param  string $formatting  If php date() formatting string for date values
	 * @return mixed  The formatted value for the column specified
	 */
	protected function format($column, $formatting=NULL)
	{
		if (!array_key_exists($column, $this->values)) {
			fCore::toss('fProgrammerException', 'The column specified, ' . $column . ', does not exist');
		}
		
		$column_info = fORMSchema::getInstance()->getColumnInfo(fORM::tablize($this), $column);
		$column_type = $column_info['type'];
		
		// Ensure the programmer is calling the function properly
		if (in_array($column_type, array('blob'))) {
			fCore::toss('fProgrammerException', 'The column ' . $column . ' does not support formatting because it is a blob column');
		}
		
		if ($formatting !== NULL && !in_array($column_type, array('date', 'time', 'timestamp', 'float'))) {
			fCore::toss('fProgrammerException', 'The column ' . $column . ' does not support any formatting options');
		}
		
		// Grab the value for empty value checking
		$method_name = 'get' . fInflection::camelize($column, TRUE);
		$value       = $this->$method_name();
		
		// Date/time objects
		if (is_object($value) && in_array($column_type, array('date', 'time', 'timestamp'))) {
			if ($formatting === NULL) {
				fCore::toss('fProgrammerException', 'The column ' . $column . ' requires one formatting parameter, a valid date() formatting string');
			}
			return $value->format($formatting);
		}
		
		// Other objects
		if (is_object($value) && is_callable(array($value, '__toString'))) {
			return fHTML::prepare($value->__toString());
		}
		
		// If we are left with an object at this point then we don't know what to do with it
		if (is_object($value)) {
			fCore::toss('fProgrammerException', 'The column ' . $column . ' contains an object that does not have a __toString() method - unsure how to get object value');
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
		
		// Anything that has gotten to here is a string value, or is not the
		// proper data type for the column, so we just make sure it is marked
		// up properly for display in HTML
		return fHTML::prepare($value);
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
			return fORM::callHookCallback($this, 'replace::load()', $this->values, $this->old_values, $this->related_records, $this->debug);	
		}
		
		try {
			$sql = 'SELECT * FROM ' . fORM::tablize($this) . ' WHERE ' . $this->constructPrimaryKeyWhereClause();
		
			$result = fORMDatabase::getInstance()->translatedQuery($sql);
			$result->tossIfNoResults();
			
		} catch (fExpectedException $e) {
			fCore::toss('fNotFoundException', 'The ' . fORM::getRecordName($this) . ' requested could not be found');
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
		
		fORM::callHookCallback($this, 'post::loadFromResult()', $this->values, $this->old_values, $this->related_records, $this->debug);
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
			return fORM::callHookCallback($this, 'replace::populate()', $this->values, $this->old_values, $this->related_records, $this->debug);	
		}
		
		fORM::callHookCallback($this, 'pre::populate()', $this->values, $this->old_values, $this->related_records, $this->debug);
		
		$table = fORM::tablize($this);
		
		$column_info = fORMSchema::getInstance()->getColumnInfo($table);
		foreach ($column_info as $column => $info) {
			if (fRequest::check($column)) {
				$method = 'set' . fInflection::camelize($column, TRUE);
				$this->$method(fRequest::get($column));
			}
		}
		
		fORM::callHookCallback($this, 'post::populate()', $this->values, $this->old_values, $this->related_records, $this->debug);
	}
	
	
	/**
	 * Retrieves a value from the record.
	 * 
	 * @param  string $column  The name of the column to retrieve
	 * @return mixed  The value for the column specified
	 */
	protected function retrieve($column)
	{
		if (!array_key_exists($column, $this->values)) {
			fCore::toss('fProgrammerException', 'The column specified, ' . $column . ', does not exist');
		}
		return $this->values[$column];
	}
	
	
	/**
	 * Sets if debug messages should be shown
	 * 
	 * @param  boolean $flag  If debugging messages should be shown
	 * @return void
	 */
	public function showDebug($flag)
	{
		$this->debug = (boolean) $flag;
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
			return fORM::callHookCallback($this, 'replace::store()', $this->values, $this->old_values, $this->related_records, $this->debug);	
		}
		
		fORM::callHookCallback($this, 'pre::store()', $this->values, $this->old_values, $this->related_records, $this->debug);
		
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
			
			fORM::callHookCallback($this, 'post-begin::store()', $this->values, $this->old_values, $this->related_records, $this->debug);
			
			$this->validate();
			
			fORM::callHookCallback($this, 'post-validate::store()', $this->values, $this->old_values, $this->related_records, $this->debug);
			
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
				$sql = $this->constructInsertSql($sql_values);
			} else {
				$sql = $this->constructUpdateSql($sql_values);
			}
			$result = fORMDatabase::getInstance()->translatedQuery($sql);
			
			
			// If there is an auto-incrementing primary key, grab the value from the database
			if ($new_autoincrementing_record) {
				$this->old_values[$pk_column] = $this->values[$pk_column];
				$this->values[$pk_column]     = $result->getAutoIncrementedValue();
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
						$this->storeManyToManyAssociations($relationship, $record_set);
					} else {
						$this->storeOneToManyRelatedRecords($relationship, $record_set);
					}
				}
			}
			
			fORM::callHookCallback($this, 'pre-commit::store()', $this->values, $this->old_values, $this->related_records, $this->debug);
			
			if (!$inside_db_transaction) {
				fORMDatabase::getInstance()->translatedQuery('COMMIT');
			}
			
		} catch (fPrintableException $e) {
			
			if (!$inside_db_transaction) {
				fORMDatabase::getInstance()->translatedQuery('ROLLBACK');
			}
			
			fORM::callHookCallback($this, 'post-rollback::store()', $this->values, $this->old_values, $this->related_records, $this->debug);
			
			if ($new_autoincrementing_record && array_key_exists($pk_column, $this->old_values)) {
				$this->values[$pk_column] = $this->old_values[$pk_column];
				unset($this->old_values[$pk_column]);
			}
			
			throw $e;
		}
		
		fORM::callHookCallback($this, 'post::store()', $this->values, $this->old_values, $this->related_records, $this->debug);
		
		// If we got here we succefully stored, so update old values to make exists() work 
		$this->old_values = $this->values;
	}
	
	
	/**
	 * Stores a set of one-to-many related records in the database
	 * 
	 * @throws fValidationException
	 * 
	 * @param  array      $relationship  The information about the relationship between this object and the records in the record set
	 * @param  fRecordSet $record_set    The set of records to store
	 * @return void
	 */
	protected function storeOneToManyRelatedRecords($relationship, $record_set)
	{
		$get_method_name = 'get' . fInflection::camelize($relationship['column'], TRUE);
		
		$where_conditions = array(
			$relationship['related_column'] . '=' => $this->$get_method_name()
		);
		
		$class_name = $record_set->getClassName();
		$existing_records = fRecordSet::create($class_name, $where_conditions);
		
		$existing_primary_keys  = $existing_records->getPrimaryKeys();
		$new_primary_keys       = $record_set->getPrimaryKeys();
		
		$primary_keys_to_delete = array_diff($existing_primary_keys, $new_primary_keys);
		
		foreach ($primary_keys_to_delete as $primary_key_to_delete) {
			$object_to_delete = new $class_name();
			$object_to_delete->delete(FALSE);
		}
		
		$set_method_name = 'set' . fInflection::camelize($relationship['related_column'], TRUE);
		foreach ($record_set as $record) {
			$record->$set_method_name($this->$get_method_name());
			$record->store(FALSE);
		}
	}
	
	
	/**
	 * Associates a set of many-to-many related records with the current record
	 * 
	 * @throws fValidationException
	 * 
	 * @param  array      $relationship  The information about the relationship between this object and the records in the record set
	 * @param  fRecordSet $record_set    The set of records to associate
	 * @return void
	 */
	protected function storeManyToManyAssociations($relationship, $record_set)
	{
		// First, we remove all existing relationships between the two tables
		$join_table        = $relationship['join_table'];
		$join_column       = $relationship['join_column'];
		
		$get_method_name   = 'get' . fInflection::camelize($relationship['column'], TRUE);
		$join_column_value = fORMDatabase::prepareBySchema($join_table, $join_column, $this->$get_method_name());
		
		$delete_sql  = 'DELETE FROM ' . $join_table;
		$delete_sql .= ' WHERE ' . $join_column . ' = ' . $join_column_value;
		
		fORMDatabase::getInstance()->translatedQuery($delete_sql);
		
		// Then we add back the ones in the record set
		$join_related_column     = $relationship['join_related_column'];
		$get_related_method_name = 'get' . fInflection::camelize($relationship['related_column'], TRUE);
		
		foreach ($record_set as $record) {
			$related_column_value = fORMDatabase::prepareBySchema($join_table, $join_related_column, $record->$get_related_method_name());
			
			$insert_sql  = 'INSERT INTO ' . $join_table . ' (' . $join_column . ', ' . $join_related_column . ') ';
			$insert_sql .= 'VALUES (' . $join_column_value . ', ' . $related_column_value . ')';
			
			fORMDatabase::getInstance()->translatedQuery($insert_sql);
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
		if (fORM::checkHookCallback($this, 'replace::populate()')) {
			return fORM::callHookCallback($this, 'replace::populate()', $this->values, $this->old_values, $this->related_records, $this->debug, $return_messages);	
		}
		
		$validation_messages = array();
		
		fORM::callHookCallback($this, 'pre::validate()', $this->values, $this->old_values, $this->related_records, $this->debug, $validation_messages);
		
		$table = fORM::tablize($this);
		
		// Validate the local values
		$local_validation_messages = fORMValidation::validate($this, $this->values, $this->old_values);
		
		// Validate related records
		$related_validation_messages = fORMValidation::validateRelated($this, $this->related_records);	
		
		$validation_messages = array_merge($validation_messages, $local_validation_messages, $related_validation_messages);
		
		fORM::callHookCallback($this, 'post::validate()', $this->values, $this->old_values, $this->related_records, $this->debug, $validation_messages);
		
		fORMValidation::reorderMessages($table, $validation_messages);
		
		if ($return_messages) {
			return $validation_messages;	
		}
		
		if (!empty($validation_messages)) {
			$message = '<p>The following problems were found:</p><ul><li>' . join('</li><li>', $validation_messages) . '</li></ul>';
			fCore::toss('fValidationException', $message);
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