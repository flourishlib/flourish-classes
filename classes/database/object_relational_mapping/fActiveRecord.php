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
			$pk_columns = fORMSchema::getInstance()->getKeys(fORM::tablize($this), 'primary');
			if ((sizeof($pk_columns) > 1 && array_keys($primary_key) != $pk_columns) || (sizeof($pk_columns) == 1 && !is_scalar($primary_key))) {
				fCore::toss('fProgrammerException', 'An invalidly formatted primary key was passed to ' . get_class($this));			
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
	}
	
	
	/**
	 * Dynamically creates getColumn(), setColumn(), prepareColumn() for columns in the table.
	 * 
	 * @param  string $method_name  The name of the method called
	 * @param  string $parameters   The parameters passed
	 * @return void
	 */
	public function __call($method_name, $parameters)
	{
		list ($action, $subject) = explode('_', fInflection::underscorize($method_name), 2);
		
		// This will prevent quiet failure
		if (($action == 'set' || $action == 'associate' || $action == 'link') && sizeof($parameters) < 1) {
			fCore::toss('fProgrammerException', 'The method, ' . $method_name . '(), requires at least one parameter');
		}
		
		switch ($action) {
			
			// Value methods
			case 'encode':
				if (isset($parameters[0])) {
					return $this->entifyValue($subject, $parameters[0]);
				}
				return $this->entifyValue($subject);
			
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
				
			case 'populate':
				if (isset($parameters[0])) {
					return fORMRelatedData::populateRecords($this, $this->related_records, $subject, $parameters[0]);
				}
				return fORMRelatedData::populateRecords($this, $this->related_records, $subject);
			
			// Error handler
			default:
				fCore::toss('fProgrammerException', 'Unknown method, ' . $method_name . '(), called');
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
	 * Checks to see if the record exists in the database
	 *
	 * @return boolean  If the record exists in the database
	 */
	protected function checkIfExists()
	{
		$pk_columns = fORMSchema::getInstance()->getKeys(fORM::tablize($this), 'primary');
		foreach ($pk_columns as $pk_column) {
			if ((array_key_exists($pk_column, $this->old_values) && $this->old_values[$pk_column] === NULL) || $this->values[$pk_column] === NULL) {
				return FALSE;	
			}
		}
		return TRUE;	
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
			$column_info = fORMSchema::getInstance()->getColumnInfo($table);
			$pk_columns  = fORMSchema::getInstance()->getKeys($table, 'primary');
			if (sizeof($pk_columns) == 1 && $column_info[$pk_columns[0]]['auto_increment']) {
				$this->values[$pk_columns[0]] = NULL;	
				unset($this->old_values[$pk_columns[0]]); 
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
	 * Retrieves a value from the record and prepares it for output into an html form element.
	 * 
	 * Below are the transformations performed:
	 *   - float: takes 1 parameter to specify the number of decimal places
	 *   - date, time, timestamp: format() will be called on the fDate/fTime/fTimestamp object with the 1 parameter specified 
	 *   - objects: if a __toString() method exists, the output of that will be run throught fHTML::prepareFormValue()
	 *   - all other data types: the value will be run through fHTML::prepareFormValue()
	 * 
	 * @param  string $column      The name of the column to retrieve
	 * @param  string $formatting  If php date() formatting string for date values
	 * @return mixed  The formatted value for the column specified
	 */
	protected function entifyValue($column, $formatting=NULL)
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
		
		if ($formatting === NULL && $column_type == 'float') {
			fCore::toss('fProgrammerException', 'The column ' . $column . ' requires one formatting parameter, the number of decimal places');	
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
			return number_format($value, $formatting, '.', ''); 		
		}
		
		// Anything that has gotten to here is a string value or is not the proper data type for the column that contains it
		return fHTML::prepareFormValue($value);
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
	protected function formatValue($column, $formatting=NULL)
	{
		if (!array_key_exists($column, $this->values)) {
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
			fCore::toss('fProgrammerException', 'The column ' . $column . ' requires one formatting parameter, the number of decimal places');	
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
		
		// Ensure the value matches the data type specified to prevent mangling
		if ($column_type == 'boolean' && is_bool($value)) {
			return ($value) ? 'Yes' : 'No';	
		}
		
		if ($column_type == 'integer' && is_numeric($value)) {
			return number_format($value, 0, '', ',');	
		}
		
		if ($column_type == 'float' && is_numeric($value)) {
			return number_format($value, $formatting, '.', ',');	
		}
		
		// Anything that has gotten to here is a string value, or is not the
		// proper data type for the column, so we just make sure it is marked
		// up properly for display in HTML
		return fHTML::prepare($value);
	}
	
	
	/**
	 * Creates the WHERE clause for the current primary key data
	 *
	 * @return string  The WHERE clause for the current primary key data
	 */
	protected function getPrimaryKeyWhereClause()
	{
		$sql        = '';
		$pk_columns = fORMSchema::getInstance()->getKeys(fORM::tablize($this), 'primary');
		$key_num    = 0;
		
		foreach ($pk_columns as $pk_column) {
			if ($key_num) { $sql .= " AND "; }
			
			if (!empty($this->old_values[$pk_column])) {
				$value = fORM::scalarize($this->old_values[$pk_column]);	
			} else {
				$value = fORM::scalarize($this->values[$pk_column]);	
			}
			
			$sql .= $pk_column . fORMDatabase::prepareBySchema(fORM::tablize($this), $pk_column, $value, '=');
			$key_num++;
		}
		
		return $sql;	
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
		if (is_array($row) && array_diff($pk_columns, array_keys($row)) !== array()) {
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
	
	
	/**
	 * Retrieves a value from the record.
	 * 
	 * @param  string $column      The name of the column to retrieve
	 * @return mixed  The value for the column specified
	 */
	protected function retrieveValue($column)
	{
		if (!array_key_exists($column, $this->values)) {
			fCore::toss('fProgrammerException', 'The column specified, ' . $column . ', does not exist'); 		
		}
		return $this->values[$column];	
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
			$table       = fORM::tablize($this);
			$column_info = fORMSchema::getInstance()->getColumnInfo($table);
			
			// New auto-incrementing records require lots of special stuff, so we'll detect them here
			$new_autoincrementing_record = FALSE;
			if (!$this->checkIfExists()) {
				$pk_columns = fORMSchema::getInstance()->getKeys($table, 'primary');
				
				if (sizeof($pk_columns) == 1 && $column_info[$pk_columns[0]]['auto_increment']) {
					$new_autoincrementing_record = TRUE;
					$pk_column = $pk_columns[0];
				}
			}
			
			if ($use_transaction) {
				fORMDatabase::getInstance()->translatedQuery('BEGIN');
				fFilesystem::startTransaction();
			}
			
			$this->validate();
			
			
			// Storing main table 
			
			$sql_values = array();
			foreach ($column_info as $column => $info) {
				$value = fORM::scalarize($this->values[$column]);
				$sql_values[$column] = fORMDatabase::prepareBySchema($table, $column, $value);	
			}
			
			// Most databases don't like the auto incrementing primary key to be set to NULL
			if ($new_autoincrementing_record && $sql_values[$pk_column] == 'NULL') {
				unset($sql_values[$pk_column]);	
			}
			   
			$sql    = $this->prepareInsertSql($sql_values);
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
			 
			
			if ($use_transaction) {
				fORMDatabase::getInstance()->translatedQuery('COMMIT');
				fFilesystem::commitTransaction(); 
			}
			
		} catch (fPrintableException $e) {

			if ($use_transaction) {
				fORMDatabase::getInstance()->translatedQuery('ROLLBACK');
				fFilesystem::rollbackTransaction();
			}
			
			if ($new_autoincrementing_record && array_key_exists($pk_column, $this->old_values)) {
				$this->values[$pk_column] = $this->old_values[$pk_column];	
				unset($this->old_values[$pk_column]);
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
	protected function storeManyToManyAssociations($relationship, $record_set)
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