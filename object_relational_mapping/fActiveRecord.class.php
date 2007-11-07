<?php
/**
 * An active record pattern base class
 * 
 * @copyright  Copyright (c) 2007 William Bond
 * @author     William Bond [wb] <will@flourishlib.com>
 * @license    http://flourishlib.com/license
 * 
 * @todo  Add code to handle association via multiple columns
 * @todo  Add fFile support
 * @todo  Add fImage support
 * @todo  Add various hooks
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
	 * If debugging is turned on for the class
	 * 
	 * @var boolean 
	 */
	protected $debug = NULL;
	
	
	/**
	 * Creates a record
	 * 
	 * @since  1.0.0
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
		
		if (is_object($primary_key) && $primary_key instanceof fResult) {
			$this->loadByResult($primary_key);
			return;	
		}
		
		// Handle loading an object from the database
		if ($primary_key !== NULL) {
			
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
	 * @since  1.0.0
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
	 * @since  1.0.0
	 * 
	 * @return void
	 */
	protected function configure()
	{
	}
	
	
	/**
	 * Loads a record from the database
	 * 
	 * @since  1.0.0
	 * 
	 * @return void
	 */
	public function load()
	{
		$sql = "SELECT * FROM " . fORM::tablize($this) . " WHERE " . $this->getPrimaryKeyWhereClause();
		
		try {
			$result = fORMDatabase::getInstance()->translatedQuery($sql, TRUE);
		} catch (fNoResultsException $e) {
			fCore::toss('fNotFoundException', 'The ' . fORM::getRecordName(get_class($this)) . ' requested could not be found');
		}
		
		$this->loadByResult($result);	
	}
	
	
	/**
	 * Loads a record from the database directly from a result object
	 * 
	 * @since  1.0.0
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
	}
	
	
	/**
	 * Dynamically creates setColumn() and getColumn() for columns in the database
	 * 
	 * @since  1.0.0
	 * 
	 * @param  string $method_name  The name of the method called
	 * @param  string $parameters   The parameters passed
	 * @return void
	 */
	public function __call($method_name, $parameters)
	{
		list ($action, $column) = explode('_', fInflection::underscorize($method_name), 2);
		
		switch ($action) {
			case 'get':
				if (isset($parameters[0])) {
					return $this->retrieveValue($column, $parameters[0]);
				}
				return $this->retrieveValue($column);
				break;
			case 'format':
				if (isset($parameters[0])) {
					return $this->prepareValue($column, $parameters[0]);
				}
				return $this->prepareValue($column);
				break;
			case 'set':
				return $this->assignValue($column, $parameters[0]);
				break;
			case 'find':
				return $this->retrieveValues($column);
				break;
			case 'link':
				return $this->assignValues($column, $parameters[0]);
				break;
			case 'create':
				return $this->buildObject($column);
				break;
			case 'form':
				return $this->buildSet($column);
				break;
			default:
				fCore::toss('fProgrammerException', 'Unknown method, ' . $method_name . '(), called');
				break;
		}			
	}
	
	
	/**
	 * Sets the values from this record via values from $_GET, $_POST and $_FILES
	 * 
	 * @since  1.0.0
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
				$method = 'link' . fInflection::camelize($column, TRUE);
				$this->$method(fRequest::get($column, 'array', array()));	
			}
		}	
	}
	
	
	/**
	 * Retrieves a value from the record.
	 * 
	 * @since  1.0.0
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
	 * Retrieves an array of values from one-to-many and many-to-many relationships
	 * 
	 * @since  1.0.0
	 * 
	 * @param  string $plural_related_column   The plural form of the related column name
	 * @return array  An array of the related column values
	 */
	protected function retrieveValues($plural_related_column)
	{
		$related_column = fInflection::singularize($plural_related_column);
		$relationships = fORMSchema::getInstance()->getRelationships(fORM::tablize($this));
		
		// Handle one-to-many values
		foreach ($relationships['one-to-many'] as $rel) {
			$rel_primary_keys = fORMSchema::getInstance()->getKeys($rel['related_table'], 'primary');
			
			if ($rel_primary_keys != array($related_column)) {
				continue;	
			}
			
			$sql  = "SELECT " . join(', ', $rel_primary_keys) . " FROM " . $rel['related_table'];
			$sql .= " WHERE " . $rel['related_column'] . ' = ' . fORMDatabase::prepareBySchema(fORM::tablize($this), $rel['column'], $this->values[$rel['column']]);
			
			$rows = fORMDatabase::getInstance()->translatedQuery($sql, FALSE);
			return fORMDatabase::condensePrimaryKeyArray($rows);	
		}
		
		
		// Handle many-to-many values
		if (isset($this->values[$plural_related_column])) {
			return $this->values[$plural_related_column];	
		}
		
		foreach ($relationships['many-to-many'] as $rel) {
			$rel_primary_keys = fORMSchema::getInstance()->getKeys($rel['related_table'], 'primary');
			
			if ($rel_primary_keys != array($related_column)) {
				continue;	
			}
			
			$rel_primary_keys = fORMDatabase::addTableToValues($rel['related_table'], $rel_primary_keys);
			
			$sql  = "SELECT " . join(', ', $rel_primary_keys) . " FROM ";
			$sql .= fORMDatabase::createFromClause(fORM::tablize($this), $sql);
			$sql .= " WHERE " . fORM::tablize($this) . '.' . $rel['column'] . ' = ' . fORMDatabase::prepareBySchema(fORM::tablize($this), $rel['column'], $this->values[$rel['column']]);
			
			$rows = fORMDatabase::getInstance()->translatedQuery($sql, FALSE);
			$this->values[$plural_related_column] = fORMDatabase::condensePrimaryKeyArray($rows);
			return $this->values[$plural_related_column];
		}
		
		fCore::toss('fProgrammerException', 'The related column name you specified, ' . $plural_related_column . ', could not be found');	
	}
	
	
	/**
	 * Builds the object for the related class specified
	 * 
	 * @since  1.0.0
	 * 
	 * @param  string $related_class   The related class name
	 * @return fActiveRecord  An instace of the class specified
	 */
	protected function buildObject($related_class)
	{
		$relationships = fORMSchema::getInstance()->getRelationships(fORM::tablize($this));
		
		$search_relationships = array_merge($relationships['one-to-one'], $relationships['many-to-one']);
		foreach ($search_relationships as $rel) {
			if ($related_class == fORM::classize($rel['related_table'])) {
				$class = fORM::classize($rel['related_table']);	
				break;
			}
		}
		
		if (empty($class)) {
			fCore::toss('fProgrammerException', 'The related class name you specified, ' . $related_class . ', could not be found');
		}
		
		return new $class($this->values[$rel['column']]);	
	}
	
	
	/**
	 * Retrieves a set of values from one-to-many and many-to-many relationships
	 * 
	 * @since  1.0.0
	 * 
	 * @param  string $plural_related_column   The plural form of the related column name
	 * @return fSet  The set of objects from the specified column
	 */
	protected function buildSet($plural_related_column)
	{
		$related_column = fInflection::singularize($plural_related_column);
		$relationships = fORMSchema::getInstance()->getRelationships(fORM::tablize($this));
		
		$search_relationships = array_merge($relationships['one-to-many'], $relationships['many-to-many']);
		foreach ($search_relationships as $rel) {
			$rel_primary_keys = fORMSchema::getInstance()->getKeys($rel['related_table'], 'primary');
			
			if ($rel_primary_keys == array($related_column)) {
				$class = fORM::classize($rel['related_table']);	
				break;
			}
		}
		
		if (empty($class)) {
			fCore::toss('fProgrammerException', 'The related column name you specified, ' . $plural_related_column . ', could not be found');
		}
		
		$find_method = 'find' . fInflection::camelize($plural_related_column);
		
		return fSet::createFromPrimaryKeys($class, $this->$find_method());	
	}
	
	
	/**
	 * Retrieves a value from the record and formats it for output into html.
	 * 
	 * Below are the transformations performed:
	 *   - varchar, char, text columns: will run through fHTML::encodeHtml()
	 *   - date, time, timestamp: takes 1 parameter, php date() formatting string
	 *   - boolean: will return 'Yes' or 'No'
	 * 
	 * @since  1.0.0
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
				return fHTML::encodeHtml($this->$method_name());

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
	 * @since  1.0.0
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
	 * Sets values for many-to-many relationships
	 * 
	 * @since  1.0.0
	 * 
	 * @param  string $plural_related_column   The plural form of the related column name
	 * @param  array $values                   The values for the column specified
	 * @return void
	 */
	protected function assignValues($plural_related_column, $values)
	{
		settype($values, 'array');
		$related_column = fInflection::singularize($plural_related_column);
		$relationships  = fORMSchema::getInstance()->getRelationships(fORM::tablize($this), 'many-to-many');
		
		foreach ($relationships as $rel) {
			$rel_primary_keys = fORMSchema::getInstance()->getKeys($rel['related_table'], 'primary');
			
			if ($rel_primary_keys != array($related_column)) {
				continue;	
			}
			
			$this->values[$plural_related_column] = $values;
		}
		
		fCore::toss('fProgrammerException', 'The related column name you specified, ' . $plural_related_column . ', is not in a many-to-many relationship with the current table, ' . fORM::tablize($this));	
	}
	
	
	/**
	 * Validates the record against the database
	 * 
	 * @since  1.0.0
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
	 * @since  1.0.0
	 * 
	 * @param  boolean $use_transaction  If a transaction should be wrapped around the delete
	 * @return void
	 */
	public function store($use_transaction=TRUE)
	{
		try {
			if ($use_transaction) {
				fORMDatabase::getInstance()->translatedQuery("BEGIN", FALSE);
			}
			
			$this->validate();
			
			
			/********************
			 * Storing main table
			 */
			
			$column_info = fORMSchema::getInstance()->getColumnInfo(fORM::tablize($this));
			$sql_values = array();
			foreach ($column_info as $column => $info) {
				$sql_values[$column] = fORMDatabase::prepareBySchema(fORM::tablize($this), $column, $this->values[$column]);	
			}
			
			if (!$this->checkIfExists()) {
				// Most database don't like the auto incrementing primary key to be set to NULL
				$primary_keys = fORMSchema::getInstance()->getKeys(fORM::tablize($this), 'primary');
				if (sizeof($primary_keys) == 1 && $column_info[$primary_keys[0]]['auto_increment'] && $sql_values[$primary_keys[0]] == 'NULL') {
					unset($sql_values[$primary_keys[0]]);	
				}
				
				$sql = $this->prepareInsertSql($sql_values);
			} else {
				$sql = $this->prepareUpdateSql($sql_values);
			}
			
			$result = fORMDatabase::getInstance()->translatedQuery($sql, FALSE);
			
			
			/************************************
			 * Storing many-to-many relationships
			 */
			
			if ($use_transaction) {
				fORMDatabase::getInstance()->translatedQuery("COMMIT", FALSE);
			}
			
			if (!$this->checkIfExists()) {
				$primary_keys = fORMSchema::getInstance()->getKeys(fORM::tablize($this), 'primary');
				if (sizeof($primary_keys) == 1 && $column_info[$primary_keys[0]]['auto_increment']) {
					$this->old_values[$primary_keys[0]] = $this->values[$primary_keys[0]];
					$this->values[$primary_keys[0]] = $result->getAutoIncrementedValue();	
				}
			}
			
		} catch (fPrintableException $e) {

			if ($use_transaction) {
				fORMDatabase::getInstance()->translatedQuery("ROLLBACK", FALSE);
			}
			
			throw $e;	
		}
	}
	
	
	/**
	 * Delete a record from the database, does not destroy the object
	 * 
	 * @since  1.0.0
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
				fORMDatabase::getInstance()->translatedQuery("BEGIN");
			}
			
			$sql = "DELETE FROM " . fORM::tablize($this) . " WHERE " . $this->getPrimaryKeyWhereClause();
			
			$result = fORMDatabase::getInstance()->translatedQuery($sql, FALSE);
			
			if ($use_transaction) {
				fORMDatabase::getInstance()->translatedQuery("COMMIT");
			}
			
		} catch (fPrintableException $e) {
			if ($use_transaction) {
				fORMDatabase::getInstance()->translatedQuery("ROLLBACK");
			}
			
			throw $e;	
		}
	}
	
	
	/**
	 * Checks to see if the record exists in the database
	 *
	 * @since 1.0.0
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
	 * @since 1.0.0
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
	 * @since 1.0.0
	 * 
	 * @param  array $sql_values    The sql-formatted values for this record
	 * @return string  The sql insert statement
	 */
	protected function prepareInsertSql($sql_values)
	{
		$sql = "INSERT INTO " . fORM::tablize($this) . " (";
		
		$columns = '';
		$values  = '';
		
		$column_num = 0;
		foreach ($sql_values as $column => $sql_value) {
			if ($column_num) { $columns .= ", "; $values .= ", "; }
			$columns .= $column;
			$values  .= $sql_value;
			$column_num++;
		}
		$sql .= $columns . ") VALUES (" . $values . ")";
		return $sql;		
	}
	
	
	/**
	 * Creates the SQL to update this record
	 *
	 * @since 1.0.0
	 * 
	 * @param  array $sql_values    The sql-formatted values for this record
	 * @return string  The sql update statement
	 */
	protected function prepareUpdateSql($sql_values)
	{
		$sql = "UPDATE " . fORM::tablize($this) . " SET ";
		$column_num = 0;
		foreach ($sql_values as $column => $sql_value) {
			if ($column_num) { $sql .= ", "; }
			$sql .= $column . " = " . $sql_value;
			$column_num++;
		}
		$sql .= " WHERE " . $this->getPrimaryKeyWhereClause();
		return $sql;		
	}
}  


// Handle dependency load order for extending exceptions
if (!class_exists('fCore')) { }


/**
 * An exception when an fActiveRecord is not found in the database
 * 
 * @copyright  Copyright (c) 2007 William Bond
 * @author     William Bond [wb] <will@flourishlib.com>
 * @license    http://flourishlib.com/license
 * 
 * @version  1.0.0
 * @changes  1.0.0    The initial implementation [wb, 2007-06-14]
 */
class fNotFoundException extends fExpectedException
{
}



/**
 * Copyright (c) 2007 William Bond <will@flourishlib.com>
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