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
 * @uses  fCore
 * @uses  fFilesystem
 * @uses  fHTML
 * @uses  fInflection
 * @uses  fNoResultsException
 * @uses  fNotFoundException
 * @uses  fORM
 * @uses  fORMDatabase
 * @uses  fORMRelatedData
 * @uses  fORMSchema
 * @uses  fORMValidation
 * @uses  fProgrammerException
 * @uses  fRequest
 * @uses  fResult
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
	 * The values for this record
	 * 
	 * @var array 
	 */
	protected $values = array();
	
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
	 * If debugging is turned on for the class
	 * 
	 * @var boolean 
	 */
	protected $debug = NULL;
	
	
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
		if (!fORM::checkFeaturesSet(get_class($this))) {
			$this->configure();
			fORM::flagFeaturesSet(get_class($this));
		}
		
		// Handle loading by a result object passed via the fRecordSet class
		if (is_object($primary_key) && $primary_key instanceof fResult) {
			if ($this->loadFromIdentityMap($primary_key)) {
				return;  
			}
			
			$this->loadByResult($primary_key);
			return;	
		}
		
		// Handle loading an object from the database
		if ($primary_key !== NULL) {
			
			if ($this->loadFromIdentityMap($primary_key)) {
				return;  
			}
			
			// Check the primary keys
			$primary_keys = fORMSchema::getInstance()->getKeys(fORM::tablize($this), 'primary');
			if ((sizeof($primary_keys) > 1 && array_keys($primary_key) != $primary_keys) || (sizeof($primary_keys) == 1 && !is_scalar($primary_key))) {
				fCore::toss('fProgrammerException', 'An invalidly formatted primary key was passed to ' . get_class($this));			
			}
			
			// Assign the primary key values
			if (sizeof($primary_keys) > 1) {
				for ($i=0; $i<sizeof($primary_keys); $i++) {
					$this->values[$primary_keys[0]] = $primary_key[$primary_keys[0]];
				}	
			} else {
				$this->values[$primary_keys[0]] = $primary_key;	
			}
			
			$this->load();
			
		// Create an empty array for new objects
		} else {
			$column_info = fORMSchema::getInstance()->getColumnInfo(fORM::tablize($this));
			foreach ($column_info as $column => $info) {
				$this->values[$column] = NULL;	
			}
		}	
	}
	
	
	/**
	 * Enabled debugging
	 * 
	 * @param  boolean $enable  If debugging should be enabled
	 * @return void
	 */
	public function setDebug($enable)
	{
		$this->debug = (boolean) $enable;
	}
	
	
	/**
	 * Allows the programmer to set features for the class
	 * 
	 * @return void
	 */
	protected function configure()
	{
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
		
		$primary_keys = fORMSchema::getInstance()->getKeys(fORM::tablize($this), 'primary');
		settype($primary_keys, 'array');
		
		// If we don't have a value for each primary key, we can't load
		if (is_array($row) && array_diff($primary_keys, array_keys($row)) !== array()) {
			return FALSE;
		}
		
		// Build an array of just the primary key data
		$pk_data = array();
		foreach ($primary_keys as $primary_key) {
			$pk_data[$primary_key] = (is_array($row)) ? $row[$primary_key] : $row;
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
	 * Loads a record from the database
	 * 
	 * @throws  fNotFoundException
	 * 
	 * @return void
	 */
	public function load()
	{
		$sql = 'SELECT * FROM ' . fORM::tablize($this) . ' WHERE ' . $this->getPrimaryKeyWhereClause();
		
		try {
			$result = fORMDatabase::getInstance()->translatedQuery($sql);
			$result->tossIfNoResults();
		} catch (fNoResultsException $e) {
			fCore::toss('fNotFoundException', 'The ' . fORM::getRecordName(get_class($this)) . ' requested could not be found');
		}
		
		$this->loadByResult($result);	
	}
	
	
	/**
	 * Loads a record from the database directly from a result object
	 * 
	 * @param  fResult $result  The result object to use for loading the current object
	 * @return void
	 */
	protected function loadByResult(fResult $result)
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
			} else {
				$this->values[$column] = $value;
			}
		}	
		
		
		// Save this object to the identity map
		$primary_keys = fORMSchema::getInstance()->getKeys(fORM::tablize($this), 'primary');
		settype($primary_keys, 'array');
		
		$pk_data = array();
		foreach ($primary_keys as $primary_key) {
			$pk_data[$primary_key] = $row[$primary_key];
		}
		
		fORM::saveToIdentityMap($this, $pk_data);
	}
	
	
	/**
	 * Dynamically creates setColumn() and getColumn() for columns in the database
	 * 
	 * @param  string $method_name  The name of the method called
	 * @param  string $parameters   The parameters passed
	 * @return void
	 */
	public function __call($method_name, $parameters)
	{
		list ($action, $subject) = explode('_', fInflection::underscorize($method_name), 2);
		
		// This will prevent quiet failure
		if (($action == 'set' || $action == 'associate' || $action == 'link') && !isset($parameters[0])) {
			fCore::toss('fProgrammerException', 'The method, ' . $method_name . '(), requires at least one parameter');
		}
		
		switch ($action) {
			
			// Value methods
			case 'get':
				if (isset($parameters[0])) {
					return $this->retrieveValue($subject, $parameters[0]);
				}
				return $this->retrieveValue($subject);

			case 'prepare':
				if (isset($parameters[0])) {
					return $this->formatValue($subject, $parameters[0]);
				}
				return $this->formatValue($subject);

			case 'set':
				return $this->assignValue($subject, $parameters[0]);
				
			// Related data methods
			case 'create':
				if (isset($parameters[0])) {
					return fORMRelatedData::constructRecord($this, $this->values, $subject, $parameters[0]);
				}
				return fORMRelatedData::constructRecord($this, $this->values, $subject);

			case 'build':
				if (isset($parameters[0])) {
					return fORMRelatedData::constructRecordSet($this, $this->values, $this->related_records, $subject, $parameters[0]);
				}
				return fORMRelatedData::constructRecordSet($this, $this->values, $this->related_records, $subject);

			case 'associate':
				if (isset($parameters[1])) {
					return fORMRelatedData::linkRecords($this, $this->related_records, $subject, $parameters[0], $parameters[1]);
				}
				return fORMRelatedData::linkRecords($this, $this->related_records, $subject, $parameters[0]);

			case 'inject':
				if (isset($parameters[1])) {
					return fORMRelatedData::setRecords($this, $this->related_records, $subject, $parameters[0], $parameters[1]);
				}
				return fORMRelatedData::setRecords($this, $this->related_records, $subject, $parameters[0]);
			
			// Error handler
			default:
				fCore::toss('fProgrammerException', 'Unknown method, ' . $method_name . '(), called');
		}			
	}
	
	
	/**
	 * Sets the values from this record via values from $_GET, $_POST and $_FILES
	 * 
	 * @return void
	 */
	public function populate()
	{
		$table = fORM::tablize($this);
		
		$column_info = fORMSchema::getInstance()->getColumnInfo($table);
		foreach ($column_info as $column => $info) {
			if (fRequest::check($column)) {
				$method = 'set' . fInflection::camelize($column, TRUE);
				$this->$method(fRequest::get($column));	
			}
		}
		
		// This handles associating many-to-many relationships
		$relationships = fORMSchema::getInstance()->getRelationships($table, 'many-to-many');
		foreach ($relationships as $rel) {
			$routes = fORMSchema::getRoutes($table, $rel['related_table']);
			$field  = fInflection::underscorize(fORM::classize($rel['related_table']));
			
			if (sizeof($routes) > 1 && fRequest::check($field)) {
				fCore::toss('fProgrammerException', 'The form submitted contains an ambiguous input field, ' . $field . '. The field name should contain the route to ' . $field . ' since there is more than one.');		
			}
			
			$route = NULL;
			if (sizeof($routes) > 1) {
				$route = $rel['join_table'];  
				$field .= '{' . $route . '}';	
			}
			
			if (fRequest::check($field)) {
				$method = 'associate' . fInflection::camelize(fORM::classize($rel['related_table']), TRUE);
				$this->$method(fRequest::get($field, 'array', array()), $route);	
			}
		}	
	}
	
	
	/**
	 * Sets the values for records in a one-to-many relationship with this record
	 * 
	 * @param  string $class_name  The related class to populate
	 * @param  string $route       The route to the related class
	 * @return void
	 */
	protected function populateOneToMany($class_name, $route=NULL)
	{
		$related_table = fORM::tablize($class_name);
		$primary_keys  = fORMSchema::getInstance()->getKeys($related_table, 'primary');		
		
		$table_with_route = $related_table;
		$table_with_route = ($route !== NULL) ? '{' . $route . '}' : '';
		
		$first_primary_key_column = $primary_keys[0];
		$primary_key_field        = $table_with_route . '::' . $first_primary_key_column;
		
		$total_records = sizeof(fRequest::get($primary_key_field, 'array', array()));
		$records       = array();
		
		for ($i = 0; $i < $total_records; $i++) {
			fRequest::filter($table_with_route . '::', $i);	
			
			// Existing record are loaded out of the database before populating
			if (fRequest::get($first_primary_key_column) !== NULL) {
				if (sizeof($primary_keys) == 1) {
					$primary_key_values = fRequest::get($first_primary_key_column);	
				} else {
					$primary_key_values = array();
					foreach ($primary_keys as $primary_key) {
						$primary_key_values[$primary_key] = fRequest::get($primary_key);
					}		
				}
				$record = new $class_name($primary_key_values);	
			
			// If we have a new record, created an empty object
			} else {
				$record = new $class_name();
			}
			
			$record->populate();
			$records[] = $record;
			
			fRequest::unfilter();
		}
		
		$record_set = fRecordSet::createFromObjects($records);
		
		fORMRelatedData::linkRecords($this, $this->related_records, $class_name, $record_set, $route);
	}
	
	
	/**
	 * Retrieves a value from the record.
	 * 
	 * @param  string $column      The name of the column to retrieve
	 * @return mixed  The value for the column specified
	 */
	protected function retrieveValue($column)
	{
		if (!isset($this->values[$column])) {
			fCore::toss('fProgrammerException', 'The column specified, ' . $column . ', does not exist'); 		
		}
		return $this->values[$column];	
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
	 *   - date, time, timestamp: these can not be formatted since they are represented by objects 
	 *   - objects: can not be formatted since they are objects and not scalar values
	 * 
	 * @param  string $column      The name of the column to retrieve
	 * @param  string $formatting  If php date() formatting string for date values
	 * @return mixed  The formatted value for the column specified
	 */
	protected function formatValue($column, $formatting=NULL)
	{
		if (!isset($this->values[$column])) {
			fCore::toss('fProgrammerException', 'The column specified, ' . $column . ', does not exist'); 		
		}
		
		$column_type = fORMSchema::getInstance()->getColumnInfo(fORM::tablize($this), $column, 'type');
		
		$email_formatted = fORMValidation::hasFormattingRule($this, $column, 'email');
		$link_formatted  = fORMValidation::hasFormattingRule($this, $column, 'link'); 
		
		// Ensure the programmer is calling the function properly
		if (in_array($column_type, array('blob'))) {
			fCore::toss('fProgrammerException', 'The column ' . $column . ' does not support formatting because it is a blob column');
		}
		
		if ($formatting !== NULL && !$email_formatted && !$link_formatted && $column_type != 'float') {
			fCore::toss('fProgrammerException', 'The column ' . $column . ' does not support any formatting options');
		}
		
		if ($formatting === NULL && $column_type == 'float') {
			fCore::toss('fProgrammerException', 'The column ' . $column . ' requires one parameter, the number of decimal places');	
		}
		
		// Grab the value for empty value checking
		$method_name = 'get' . fInflection::camelize($column, TRUE);
		$value       = $this->$method_name();
		
		// Values that are objects can not be formatted
		if (is_object($value) || in_array($column_type, array('date', 'time', 'timestamp'))) {
			fCore::toss('fProgrammerException', 'The column ' . $column . ' has a value that is represented by an object, and thus can not be formatted'); 		
		}
		
		// If we are formatting in a situation where empty values will produce incorrect results, exit early
		if (empty($value) && ($email_formatted || $link_formatted)) {
			return $value;	
		}
		
		// Handle the email and link formatting
		if ($email_formatted) {
			$css_class = ($formatting) ? ' class="' . $formatting . '"' : '';
			return '<a href="mailto:' . $value . '"' . $css_class . '>' . $value . '</a>';
		}
		if ($link_formatted) {
			$css_class = ($formatting) ? ' class="' . $formatting . '"' : '';
			return '<a href="' . $value . '"' . $css_class . '>' . $value . '</a>';
		}	
		
		// Handle the "standard" formatting
		switch ($column_type) {
			case 'varchar':
			case 'char':
			case 'text':
				return fHTML::prepare($value);

			case 'boolean':
				return ($value) ? 'Yes' : 'No';
				
			case 'integer':
				return number_format($value, 0, '', ',');
				
			case 'float':
				return number_format($value, $formatting, '.', ',');
		}
		
		// In case some other sort of column type gets added without this being updated
		return $value;
	}
	
	
	/**
	 * Sets a value to the record.
	 * 
	 * @param  string $column  The column to set the value to
	 * @param  mixed $value    The value to set
	 * @return void
	 */
	protected function assignValue($column, $value)
	{
		if (!isset($this->values[$column])) {
			fCore::toss('fProgrammerException', 'The column specified, ' . $column . ', does not exist'); 		
		}
		
		// We consider an empty string to be equivalent to NULL
		if ($value === '') {
			$value = NULL;	
		}
		
		// Turn date/time values into objects
		$column_type = fORMSchema::getInstance()->getColumnInfo(fORM::tablize($this), $column, 'type');
		if ($value !== NULL && in_array($column_type, array('date', 'time', 'timestamp'))) {
			try {
				$class = 'f' . fInflection::camelize($column_type, TRUE);
				$value = new $class($value);
			} catch (fValidationException $e) {
				// Validation exception result in the raw value being saved
			}	
		}
		
		$this->old_values[$column] = $this->values[$column];
		$this->values[$column]     = $value;
	}
	
	
	/**
	 * Validates the record against the database
	 * 
	 * @throws  fValidationException
	 * 
	 * @return void
	 */
	public function validate()
	{
		fORMValidation::validate(fORM::tablize($this), $this->values, $this->old_values);	
	}
	
	
	/**
	 * Stores a record in the database
	 * 
	 * @throws  fValidationException
	 * 
	 * @param  boolean $use_transaction  If a transaction should be wrapped around the delete
	 * @return void
	 */
	public function store($use_transaction=TRUE)
	{
		try {
			$table = fORM::tablize($this);
			
			// New auto-incrementing records require lots of special stuff, so we'll detect them here
			$new_autoincrementing_record = FALSE;
			if (!$this->checkIfExists()) {
				$primary_keys = fORMSchema::getInstance()->getKeys($table, 'primary');
				if (sizeof($primary_keys) == 1 && $column_info[$primary_keys[0]]['auto_increment']) {
					$new_autoincrementing_record = TRUE;
					$pk_field = $primary_keys[0];
				}
			}
			
			if ($use_transaction) {
				fORMDatabase::getInstance()->translatedQuery('BEGIN');
				fFilesystem::startTransaction();
			}
			
			$this->validate();
			
			
			// Storing main table 
			
			$column_info = fORMSchema::getInstance()->getColumnInfo($table);
			$sql_values = array();
			foreach ($column_info as $column => $info) {
				$value = fORM::scalarize($this->values[$column]);
				$sql_values[$column] = fORMDatabase::prepareBySchema($table, $column, $value);	
			}
			
			// Most databases don't like the auto incrementing primary key to be set to NULL
			if ($new_autoincrementing_record && $sql_values[$pk_field] == 'NULL') {
				unset($sql_values[$pk_field]);	
			}
			   
			$sql    = $this->prepareInsertSql($sql_values);
			$result = fORMDatabase::getInstance()->translatedQuery($sql);
			
			// If there is an auto-incrementing primary key, grab the value from the database
			if ($new_autoincrementing_record) {
				$this->old_values[$pk_field] = $this->values[$pk_field];
				$this->values[$pk_field]     = $result->getAutoIncrementedValue();	  
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
						$this->associateManyToManyRelatedRecords($relationship, $record_set);
					} else {
						$this->storeOneToManyRelatedRecords($relationship, $record_set);	
					}
				}  	
			}
			 
			
			if ($use_transaction) {
				fORMDatabase::getInstance()->translatedQuery('COMMIT');
				fFilesystem::commitTransaction(); 
			}
			
		} catch (fPrintableException $e) {

			if ($use_transaction) {
				fORMDatabase::getInstance()->translatedQuery('ROLLBACK');
				fFilesystem::rollbackTransaction();
			}
			
			if ($new_autoincrementing_record) {
				$this->values[$pk_field] = $this->old_values[$pk_field];	
				unset($this->old_values[$pk_field]);
			}
			
			throw $e;	
		}
	}
	
	
	/**
	 * Stores a set of one-to-many related records in the database
	 * 
	 * @param  array $relationship     The information about the relationship between this object and the records in the record set
	 * @param  fRecordSet $record_set  The set of records to store
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
	 * @param  array $relationship     The information about the relationship between this object and the records in the record set
	 * @param  fRecordSet $record_set  The set of records to associate
	 * @return void
	 */
	protected function associateManyToManyRelatedRecords($relationship, $record_set)
	{
		// First, we remove all existing relationships between the two tables
		$join_table        = $relationship['join_table'];
		$join_column       = $relationship['join_column'];
		
		$get_method_name   = fInflection::camelize($relationship['column'], TRUE);
		$join_column_value = fORMDatabase::prepareBySchema($join_table, $join_column, $this->$get_method_name());
		
		$delete_sql  = 'DELETE FROM ' . $join_table;
		$delete_sql .= ' WHERE ' . $join_column . ' = ' . $join_column_value;

		fORMDatabase::getInstance()->translatedQuery($delete_sql);
		
		// Then we add back the ones in the record set
		$join_related_column     = $relationship['join_related_column'];
		$get_related_method_name = fInflection::camelize($relationship['related_column'], TRUE);
		
		foreach ($record_set as $record) {
			$related_column_value = fORMDatabase::prepareBySchema($join_table, $join_related_column, $record->$get_related_method_name());
			
			$insert_sql  = 'INSERT INTO ' . $join_table . ' (' . $join_column . ', ' . $join_related_column . ') ';
			$insert_sql .= 'VALUES (' . $join_column_value . ', ' . $related_column_value . ')';
				
			fORMDatabase::getInstance()->translatedQuery($insert_sql);
		}
	}
	
	
	/**
	 * Delete a record from the database, does not destroy the object
	 * 
	 * @param  boolean $use_transaction  If a transaction should be wrapped around the delete
	 * @return void
	 */
	public function delete($use_transaction=TRUE)
	{
		if (!$this->checkIfExists()) {
			fCore::toss('fProgrammerException', 'The object does not yet exist in the database, and thus can not be deleted');	
		}
		
		try {
			if ($use_transaction) {
				fORMDatabase::getInstance()->translatedQuery('BEGIN');
				fFilesystem::startTransaction();
			}
			
			$table  = fORM::tablize($this);
			$sql    = 'DELETE FROM ' . $table . ' WHERE ' . $this->getPrimaryKeyWhereClause();
			$result = fORMDatabase::getInstance()->translatedQuery($sql);
			
			if ($use_transaction) {
				fORMDatabase::getInstance()->translatedQuery('COMMIT');
				fFilesystem::commitTransaction();
			}
			
			// If we just deleted an object that has an auto-incrementing primary key, lets delete that value from the object since it is no longer valid
			$column_info  = fORMSchema::getInstance()->getColumnInfo($table);
			$primary_keys = fORMSchema::getInstance()->getKeys($table, 'primary');
			if (sizeof($primary_keys) == 1 && $column_info[$primary_keys[0]]['auto_increment']) {
				$this->values[$primary_keys[0]] = NULL;	
				unset($this->old_values[$primary_keys[0]]); 
			}
			
		} catch (fPrintableException $e) {
			if ($use_transaction) {
				fORMDatabase::getInstance()->translatedQuery('ROLLBACK');
				fFilesystem::rollbackTransaction();
			}
			
			throw $e;	
		}
	}
	
	
	/**
	 * Checks to see if the record exists in the database
	 *
	 * @return boolean  If the record exists in the database
	 */
	protected function checkIfExists()
	{
		$primary_keys = fORMSchema::getInstance()->getKeys(fORM::tablize($this), 'primary');
		foreach ($primary_keys as $primary_key) {
			if ((array_key_exists($primary_key, $this->old_values) && $this->old_values[$primary_key] === NULL) || $this->values[$primary_key] === NULL) {
				return FALSE;	
			}
		}
		return TRUE;	
	}
	
	
	/**
	 * Creates the WHERE clause for the current primary key data
	 *
	 * @return string  The WHERE clause for the current primary key data
	 */
	protected function getPrimaryKeyWhereClause()
	{
		$sql = '';
		$primary_keys = fORMSchema::getInstance()->getKeys(fORM::tablize($this), 'primary');
		$key_num = 0;
		foreach ($primary_keys as $primary_key) {
			if ($key_num) { $sql .= " AND "; }
			
			if (!empty($this->old_values[$primary_key])) {
				$value = fORM::scalarize($this->old_values[$primary_key]);	
			} else {
				$value = fORM::scalarize($this->values[$primary_key]);	
			}
			
			$sql .= $primary_key . fORMDatabase::prepareBySchema(fORM::tablize($this), $primary_key, $value, '=');
			$key_num++;
		}
		return $sql;	
	}
	
	
	/**
	 * Creates the SQL to insert this record
	 *
	 * @param  array $sql_values    The sql-formatted values for this record
	 * @return string  The sql insert statement
	 */
	protected function prepareInsertSql($sql_values)
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
	 * @param  array $sql_values    The sql-formatted values for this record
	 * @return string  The sql update statement
	 */
	protected function prepareUpdateSql($sql_values)
	{
		$sql = 'UPDATE ' . fORM::tablize($this) . ' SET ';
		$column_num = 0;
		foreach ($sql_values as $column => $sql_value) {
			if ($column_num) { $sql .= ', '; }
			$sql .= $column . ' = ' . $sql_value;
			$column_num++;
		}
		$sql .= ' WHERE ' . $this->getPrimaryKeyWhereClause();
		return $sql;		
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
?>