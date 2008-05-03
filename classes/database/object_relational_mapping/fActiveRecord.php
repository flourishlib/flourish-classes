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
 * @todo  Add populateRelatedClass()
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
		if (($action == 'set' || $action == 'associate') && !isset($parameters[0])) {
			fCore::toss('fProgrammerException', 'The method, ' . $method_name . '(), requires a single parameter');
		}
		
		switch ($action) {
			
			// Value methods
			case 'get':
				if (isset($parameters[0])) {
					return $this->retrieveValue($subject, $parameters[0]);
				}
				return $this->retrieveValue($subject);

			case 'format':
				if (isset($parameters[0])) {
					return $this->prepareValue($subject, $parameters[0]);
				}
				return $this->prepareValue($subject);

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
		$column_info = fORMSchema::getInstance()->getColumnInfo(fORM::tablize($this));
		foreach ($column_info as $column => $info) {
			if (fRequest::check($column)) {
				$method = 'set' . fInflection::camelize($column, TRUE);
				$this->$method(fRequest::get($column));	
			}
		}
		
		$relationships = fORMSchema::getInstance()->getRelationships(fORM::tablize($this), 'many-to-many');
		foreach ($relationships as $rel) {
			$column = fInflection::pluralize($rel['related_column']);
			if (fRequest::check($column)) {
				$method = 'associate' . fInflection::camelize($column, TRUE);
				$this->$method(fRequest::get($column, 'array', array()));	
			}
		}	
	}
	
	
	/**
	 * Retrieves a value from the record.
	 * 
	 * @param  string $column      The name of the column to retrieve
	 * @return mixed  The value for the column specified
	 */
	protected function retrieveValue($column)
	{
		$column_type = fORMSchema::getInstance()->getColumnInfo(fORM::tablize($this), $column, 'type');
		return $this->values[$column];	
	}
	
	
	/**
	 * Retrieves a value from the record and formats it for output into html.
	 * 
	 * Below are the transformations performed:
	 *   - varchar, char, text columns: will run through fHTML::prepare()
	 *   - date, time, timestamp: takes 1 parameter, php date() formatting string
	 *   - boolean: will return 'Yes' or 'No'
	 * 
	 * @param  string $column      The name of the column to retrieve
	 * @param  string $formatting  If php date() formatting string for date values
	 * @return mixed  The formatted value for the column specified
	 */
	protected function prepareValue($column, $formatting=NULL)
	{
		$column_type = fORMSchema::getInstance()->getColumnInfo(fORM::tablize($this), $column, 'type');
		
		if (in_array($column_type, array('integer', 'float', 'blob'))) {
			fCore::toss('fProgrammerException', 'The column ' . $column . ' does not support formatting because it is of the type ' . $column_type);
		}
		
		if ($formatting !== NULL && in_array($column_type, array('varchar', 'char', 'text'))) {
			fCore::toss('fProgrammerException', 'The column ' . $column . ' does not support a formatting string');
		}
		
		$method_name = 'get' . fInflection::camelize($column, TRUE);
		switch ($column_type) {
			case 'varchar':
			case 'char':
			case 'text':
				return fHTML::prepare($this->$method_name());

			case 'date':
			case 'time':
			case 'timestamp':
				return date($formatting, strtotime($this->$method_name()));

			case 'boolean':
				return ($this->$method_name()) ? 'Yes' : 'No';
		}
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
		// We consider an empty string to be equivalent to NULL
		if ($value === '') {
			$value = NULL;	
		}
		
		$this->old_values[$column] = $this->values[$column];
		$this->values[$column] = $value;
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
				$sql_values[$column] = fORMDatabase::prepareBySchema($table, $column, $this->values[$column]);	
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
			$sql .= $primary_key . fORMDatabase::prepareBySchema(fORM::tablize($this), $primary_key, (!empty($this->old_values[$primary_key])) ? $this->old_values[$primary_key] : $this->values[$primary_key], '=');
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