<?php
/**
 * A lightweight, iterable set of {@link fActiveRecord}-based objects
 * 
 * @copyright  Copyright (c) 2007-2008 William Bond
 * @author     William Bond [wb] <will@flourishlib.com>
 * @license    http://flourishlib.com/license
 * 
 * @link  http://flourishlib.com/fRecordSet
 * 
 * @version  1.0.0
 * @changes  1.0.0    The initial implementation [wb, 2007-08-04]
 */
class fRecordSet implements Iterator
{
	/**
	 * Creates an fRecordSet by specifying the class to create plus the where conditions and order by rules
	 * 
	 * The where conditions array can contain key => value entries in any of the following formats (where VALUE/VALUE2 can be of any data type):
	 * <pre>
	 *  - '%column%='                     => VALUE,                    // column = VALUE
	 *  - '%column%!'                     => VALUE,                    // column <> VALUE
	 *  - '%column%~'                     => VALUE,                    // column LIKE '%VALUE%'
	 *  - '%column%='                     => array(VALUE, VALUE2,...), // column IN (VALUE, VALUE2, ...)
	 *  - '%column%!'                     => array(VALUE, VALUE2,...), // column NOT IN (VALUE, VALUE2, ...)
	 *  - '%column%~'                     => array(VALUE, VALUE2,...), // (column LIKE '%VALUE%' OR column LIKE '%VALUE2%' OR column ...)
	 *  - '%column%|%column2%|%column3%~' => VALUE,                    // (column LIKE '%VALUE%' OR column2 LIKE '%VALUE2%' OR column3 LIKE '%VALUE%')
	 *  - '%column%|%column2%|%column3%~' => array(VALUE, VALUE2,...), // ((column LIKE '%VALUE%' OR column2 LIKE '%VALUE%' OR column3 LIKE '%VALUE%') AND (column LIKE '%VALUE2%' OR column2 LIKE '%VALUE2%' OR column3 LIKE '%VALUE2%') AND ...)
	 * </pre>
	 * 
	 * The order bys array can contain key => value entries in any of the following formats:
	 * <pre>
	 *  - '%column%' => 'asc'           // 'first_name' => 'asc'
	 *  - '%column%' => 'desc'          // 'last_name'  => 'desc'
	 *  - '%expression%'  => 'asc'      // "CASE first_name WHEN 'smith' THEN 1 ELSE 2 END" => 'asc'
	 *  - '%expression%'  => 'desc'     // "CASE first_name WHEN 'smith' THEN 1 ELSE 2 END" => 'desc'
	 * </pre>
	 * 
	 * The %column% in both the where conditions and order bys can be in any of the formats:
	 * <pre>
	 *  - '%column%'                                                                 // e.g. 'first_name'
	 *  - '%current_table%.%column%'                                                 // e.g. 'users.first_name'
	 *  - '%related_table%.%column%'                                                 // e.g. 'user_groups.name'
	 *  - '%related_table%{%route%}.%column%'                                        // e.g. 'user_groups{user_group_id}.name'
	 *  - '%related_table%=>%once_removed_related_table%.%column%'                   // e.g. 'user_groups=>permissions.level'
	 *  - '%related_table%{%route%}=>%once_removed_related_table%.%column%'          // e.g. 'user_groups{user_group_id}=>permissions.level'
	 *  - '%related_table%=>%once_removed_related_table%{%route%}.%column%'          // e.g. 'user_groups=>permissions{read}.level'
	 *  - '%related_table%{%route%}=>%once_removed_related_table%{%route%}.%column%' // e.g. 'user_groups{user_group_id}=>permissions{read}.level'
	 * </pre>
	 * 
	 * @param  string  $class_name        The class to create the fRecordSet of
	 * @param  array   $where_conditions  The column => value comparisons for the where clause
	 * @param  array   $order_bys         The column => direction values to use for sorting
	 * @param  integer $limit             The number of records to fetch
	 * @param  integer $offset            The offset to use before limiting
	 * @return fRecordSet  A set of {@link fActiveRecord fActiveRecord objects}
	 */
	static public function create($class_name, $where_conditions=array(), $order_bys=array(), $limit=NULL, $offset=NULL)
	{
		$table_name   = fORM::tablize($class_name);
		
		$sql  = "SELECT " . $table_name . ".* FROM :from_clause";
		
		if (!empty($where_conditions)) {
			$sql .= ' WHERE ' . fORMDatabase::createWhereClause($table_name, $where_conditions);
		}
		
		if (!empty($order_bys)) {
			$sql .= ' ORDER BY ' . fORMDatabase::createOrderByClause($table_name, $order_bys);
		}
		
		$sql = fORMDatabase::insertFromClause($table_name, $sql);
		
		// Add the limit clause and create a query to get the non-limited total
		$non_limited_count_sql = NULL;
		if ($limit !== NULL) {
			$primary_key_fields = fORMSchema::getInstance()->getKeys($table_name, 'primary');
			$primary_key_fields = fORMDatabase::addTableToValues($table_name, $primary_key_fields);
			
			$non_limited_count_sql = str_replace('SELECT ' . $table_name . '.*', 'SELECT ' . join(', ', $primary_key_fields), $sql);
			$non_limited_count_sql = 'SELECT count(*) FROM (' . $non_limited_count_sql . ') AS sq';
			
			$sql .= ' LIMIT ' . $limit;
			
			if ($offset !== NULL) {
				$sql .= ' OFFSET ' . $offset;
			}
		}
		
		return new fRecordSet($class_name, fORMDatabase::getInstance()->translatedQuery($sql), $non_limited_count_sql);
	}
	
	
	/**
	 * Creates an empty fRecordSet
	 * 
	 * @throws fValidationException
	 * @internal
	 * 
	 * @param  string $class_name  The type of object to create
	 * @return fRecordSet  A set of {@link fActiveRecord fActiveRecord objects}
	 */
	static public function createEmpty($class_name)
	{
		$table_name   = fORM::tablize($class_name);
		
		settype($primary_keys, 'array');
		$primary_keys = array_merge($primary_keys);
		
		$sql  = 'SELECT ' . $table_name . '.* FROM ' . $table_name . ' WHERE ';
		$sql .= fORMDatabase::getInstance()->escapeBoolean(TRUE) . ' = ' . fORMDatabase::getInstance()->escapeBoolean(FALSE);
		
		return new fRecordSet($class_name, fORMDatabase::getInstance()->translatedQuery($sql));
	}
	
	
	/**
	 * Creates an fRecordSet from an array of records
	 * 
	 * @throws fValidationException
	 * @internal
	 * 
	 * @param  array $records  The records to create the set from, the order of the record set will be the same as the order of the array.
	 * @return fRecordSet  A set of {@link fActiveRecord fActiveRecord objects}
	 */
	static public function createFromObjects($records)
	{
		if (empty($records)) {
			fCore::toss('fProgrammerException', 'You can not build a record set from an empty array of objects');	
		}
		
		$class_name = get_class($records[0]);
		$table_name = fORM::tablize($class_name);
		
		$sql  = 'SELECT ' . $table_name . '.* FROM ' . $table_name . ' WHERE ';
		
		// Build the where clause
		$primary_key_fields = fORMSchema::getInstance()->getKeys($table_name, 'primary');
		$total_pk_fields = sizeof($primary_key_fields);
		
		$primary_keys = array();	
		
		$i = 0;
		foreach ($records as $record) {
			$sql .= ($i > 0) ? ' OR ' : '';
			$sql .= ($total_pk_fields > 1) ? ' (' : '';
			
			for ($j=0; $j < $total_pk_fields; $j++) {
				$pk_field      = $primary_key_fields[$j];
				$pk_get_method = 'get' . fInflection::camelize($pk_field, TRUE);
				
				$pk_value = $record->$pk_get_method();
				if ($j == 0 && $total_pk_fields == 1) {
					$primary_keys[$i] = $pk_value;
				} elseif ($j == 0) {
					$primary_keys[$i] = array();
				}
				if ($total_pk_fields > 1) {
					$primary_keys[$i][$pk_field] = $pk_value;
				}
				
				$sql .= ($j > 0) ? ' AND ' : '';
				$sql .= $table_name . '.' . $pk_field . fORMDatabase::prepareBySchema($table_name, $pk_field, $pk_value, '=');
			}
			
			$sql .= ($total_pk_fields > 1) ? ') ' : '';
			$i++;
		}
		
		$result = new fResult('array');
		$result->setResult(array());
		$result->setReturnedRows(sizeof($records));
		$result->setSQL($sql);
		
		$record_set = new fRecordSet($class_name, $result);
		$record_set->records      = $records;
		$record_set->primary_keys = $primary_keys;
		return $record_set;
	}
	
	
	/**
	 * Creates an fRecordSet from an array of primary keys
	 * 
	 * @throws fValidationException
	 * @internal
	 * 
	 * @param  string  $class_name    The type of object to create
	 * @param  array   $primary_keys  The primary keys of the objects to create
	 * @param  array   $order_bys     The column => direction values to use for sorting (see {@link fRecordSet::create()} for format)
	 * @return fRecordSet  A set of {@link fActiveRecord fActiveRecord objects}
	 */
	static public function createFromPrimaryKeys($class_name, $primary_keys, $order_bys=array())
	{
		$table_name   = fORM::tablize($class_name);
		
		settype($primary_keys, 'array');
		$primary_keys = array_merge($primary_keys);
		
		$sql  = 'SELECT ' . $table_name . '.* FROM :from_clause WHERE ';
		
		// Build the where clause
		$primary_key_fields = fORMSchema::getInstance()->getKeys($table_name, 'primary');
		$total_pk_fields = sizeof($primary_key_fields);
		
		$empty_records = 0;
		
		$total_primary_keys = sizeof($primary_keys);
		for ($i=0; $i < $total_primary_keys; $i++) {
			if ($total_pk_fields > 1) {
				$sql .= ($i > 0) ? ' OR ' : '';
			
				$sql .= ' (';
				for ($j=0; $j < $total_pk_fields; $j++) {
					$pkf = $primary_key_fields[$j];
					
					$sql .= ($j > 0) ? ' AND ' : '';
					$sql .= $table_name . '.' . $pkf . fORMDatabase::prepareBySchema($table_name, $pkf, $primary_keys[$i][$pkf], '=');
				}
			} else {
				if (empty($primary_keys[$i])) {
					$empty_records++;
					continue;	
				}
				$sql .= ($i > 0) ? ' OR ' : '';
				$pkf  = $primary_key_fields[0];
				$sql .= $table_name . '.' . $pkf . fORMDatabase::prepareBySchema($table_name, $pkf, $primary_keys[$i], '=');	
			}
		}
		
		// If we don't have any real records to pull out, create an unequal where condition
		if ($empty_records == sizeof($primary_keys)) {
			$sql .= fORMDatabase::getInstance()->escapeBoolean(TRUE) . ' = ' . fORMDatabase::getInstance()->escapeBoolean(FALSE);	
		}
		
		if (!empty($order_bys)) {
			$sql .= ' ORDER BY ' . fORMDatabase::createOrderByClause($table_name, $order_bys);
		}
		
		$sql = fORMDatabase::insertFromClause($table_name, $sql);
		
		$result = fORMDatabase::getInstance()->translatedQuery($sql);
		
		// If we have empty records we need to splice in some new records with results from the database
		if ($empty_records) {
			$fake_result = new fResult('array');
			
			// Create a blank row for the empty results
			$column_info = fORMSchema::getInstance()->getColumnInfo($table_name);
			$blank_row = array(); 
			foreach ($column_info as $column => $info) {
				$blank_row[$column] = NULL;	
			}
			
			$result_array = array();
			for ($j=0; $j < $total_primary_keys; $j++) {
				if(empty($primary_keys[$j])) {
					$result_array[] = $blank_row;	
				} else {
					try {
						$result_array[] = $result->fetchRow();
					} catch (fExpectedException $e) {
						$result_array[] = $blank_row;
					}
				}	
			}
			
			$fake_result->setResult($result_array);
			$fake_result->setReturnedRows(sizeof($result_array));
			$fake_result->setSQL($sql);
			
			unset($result);
			$result = $fake_result;
		}
		
		return new fRecordSet($class_name, $result);
	}
	
	
	/**
	 * Creates an fRecordSet from an SQL statement
	 * 
	 * @param  string $class_name             The type of object to create
	 * @param  string $sql                    The SQL to create the set from
	 * @param  string $non_limited_count_sql  An SQL statement to get the total number of rows that would have been returned if a LIMIT clause had not been used. Should only be passed if a LIMIT clause is used.
	 * @return fRecordSet  A set of ActiveRecords
	 */
	static public function createFromSql($class_name, $sql, $grand_total_sql=NULL)
	{
		$result = fORMDatabase::getInstance()->translatedQuery($sql);
		return new fRecordSet($class_name, $result, $non_limited_count_sql);
	}
	
	
	/**
	 * A flag to indicate this should record set should be associated to the parent fActiveRecord object
	 * 
	 * @var boolean
	 */
	private $associate = FALSE;
	
	/**
	 * The type of class to create from the primary keys provided
	 * 
	 * @var string
	 */
	private $class_name;
	
	/**
	 * The number of rows that would have been returned if a LIMIT clause had not been used
	 * 
	 * @var integer
	 */
	private $non_limited_count;
	
	/**
	 * The SQL to get the total number of rows that would have been returned if a LIMIT clause had not been used
	 * 
	 * @var string
	 */
	private $non_limited_count_sql;
	
	/**
	 * The index of the current record
	 * 
	 * @var integer
	 */
	private $pointer = 0;
	
	/**
	 * The result objects for preloaded data
	 * 
	 * @var array
	 */
	private $preloaded_result_objects = array();
	
	/**
	 * An array of the primary keys for the records in the set, initially empty
	 * 
	 * @var array
	 */
	private $primary_keys = array();
	
	/**
	 * An array of the records in the set, initially empty
	 * 
	 * @var array
	 */
	private $records = array();
	
	/**
	 * The result object that will act as the data source
	 * 
	 * @var object
	 */
	private $result_object;
	
	
	/**
	 * Calls sortCallback with the appropriate method
	 * 
	 * @throws fValidationException
	 * 
	 * @param  string $method_name  The name of the method called
	 * @param  string $parameters   The parameters passed
	 * @return void
	 */
	public function __call($method_name, $parameters)
	{
		list($action, $element) = explode('_', fInflection::underscorize($method_name), 2);
		
		switch ($action) {
			case 'preload':
				$element = fInflection::camelize($element, TRUE);
				$element = fInflection::singularize($element);
				return $this->performPreload($element, ($parameters != array()) ? $parameters[0] : NULL);
		}
		
		fCore::toss('fProgrammerException', 'Unknown method, ' . $method_name . '(), called');
	}
	
	
	/**
	 * Sets the contents of the set
	 * 
	 * @param  string $class_name             The type of records to create
	 * @param  object $result_object          The primary keys or fResult object of the records to create
	 * @param  string $non_limited_count_sql  An SQL statement to get the total number of rows that would have been returned if a LIMIT clause had not been used. Should only be passed if a LIMIT clause is used.
	 * @return fRecordSet
	 */
	protected function __construct($class_name, fResult $result_object, $non_limited_count_sql=NULL)
	{
		if (!class_exists($class_name)) {
			fCore::toss('fProgrammerException', 'The class specified, ' . $class_name . ', could not be loaded');
		}
		
		if (!is_subclass_of($class_name, 'fActiveRecord')) {
			fCore::toss('fProgrammerException', 'The class specified, ' . $class_name . ', does not extend fActiveRecord. All classes used with fRecordSet must extend fActiveRecord.');
		}
		
		$this->class_name            = $class_name;
		$this->result_object         = $result_object;
		$this->non_limited_count_sql = $non_limited_count_sql;
	}
	
	
	/**
	 * Creates all records for the primary keys provided
	 * 
	 * @throws fValidationException
	 * 
	 * @return void
	 */
	private function createAllRecords()
	{
		while ($this->valid()) {
			$this->current();
			$this->next();
		}
	}
	
	
	/**
	 * Returns the current record in the set (used for iteration)
	 * 
	 * @throws fValidationException
	 * @internal
	 * 
	 * @return object  The current record
	 */
	public function current()
	{
		if (!$this->valid()) {
			fCore::toss('fProgrammerException', 'There are no remaining records');
		}
		
		if (!isset($this->records[$this->pointer])) {
			$this->records[$this->pointer] = new $this->class_name($this->result_object);
			$this->extractPrimaryKeys($this->pointer);
		}
		
		return $this->records[$this->pointer];
	}
	
	
	/**
	 * Extracts the primary key(s) from a record or all records
	 * 
	 * @param  integer $record_number  The record number to extract the primary key(s) for. If not provided all records will be extracted.
	 * @return void
	 */
	private function extractPrimaryKeys($record_number=NULL)
	{
		$table           = fORM::tablize($this->class_name);
		$pk_columns      = fORMSchema::getInstance()->getKeys($table, 'primary');
		$first_pk_column = $pk_columns[0];
		
		if ($record_number === NULL) {
			$records = $this->records;
		} else {
			$records = array($this->records[$record_number]);
		}
		
		foreach ($records as $number => $record) {
			$keys = array();
			
			foreach ($pk_columns as $pk_column) {
				$method = 'get' . fInflection::camelize($pk_column, TRUE);
				$keys[$pk_column] = $record->$method();
			}
			
			$this->primary_keys[$number] = (sizeof($pk_columns) == 1) ? $keys[$first_pk_column] : $keys;
		}	
	}
	
	
	/**
	 * Flags this record set for association with the fActiveRecord object that references it
	 * 
	 * @internal
	 * 
	 * @return void
	 */
	public function flagForAssociation()
	{
		$this->associate = TRUE;
	}
	
	
	/**
	 * Returns the current record in the set and moves the pointer to the next
	 * 
	 * @throws fValidationException
	 * 
	 * @return object|false  The current record or FALSE if no remaining records
	 */
	public function fetchRecord()
	{
		try {
			$record = $this->current();
			$this->next();
			return $record;
		} catch (fValidationException $e) {
			throw $e;
		} catch (fExpectedException $e) {
			fCore::toss('fNoRemainingException', 'There are no remaining records');
		}
	}
	
	
	/**
	 * Returns the class name of the record being stored
	 * 
	 * @return string  The class name of the records in the set
	 */
	public function getClassName()
	{
		return $this->class_name;
	}
	
	
	/**
	 * Returns the number of records in the set
	 * 
	 * @return integer  The number of records in the set
	 */
	public function getCount()
	{
		return $this->result_object->getReturnedRows();
	}
	
	
	/**
	 * Returns the number of records that would have been returned if the SQL statement had not used a LIMIT clause.
	 * 
	 * @return integer  The number of records that would have been returned if there was no LIMIT clause, or the number of records in the set if there was no LIMIT clause.
	 */
	public function getNonLimitedCount()
	{
		// A query that does not use a LIMIT clause just returns the number of returned rows
		if ($this->non_limited_count_sql === NULL) {
			return $this->getCount();
		}
		
		if ($this->non_limited_count !== NULL) {
			try {
				$this->non_limited_count = fORMDatabase::getInstance()->translatedQuery($this->non_limited_count_sql)->fetchScalar();
			} catch (fExpectedException $e) {
				$this->non_limited_count = $this->getCount();	
			}
		}
		return $this->non_limited_count;
	}
	
	
	/**
	 * Returns all of the records in the set
	 * 
	 * @throws fValidationException
	 * 
	 * @return array  The records in the set
	 */
	public function getRecords()
	{
		if (sizeof($this->records) != $this->result_object->getReturnedRows()) {
			$pointer = $this->pointer;
			$this->createAllRecords();
			$this->pointer = $pointer;
		}
		return $this->records;
	}
	
	
	/**
	 * Returns the primary keys for all of the records in the set
	 * 
	 * @throws fValidationException
	 * 
	 * @return array  The primary keys of all the records in the set
	 */
	public function getPrimaryKeys()
	{
		if (sizeof($this->primary_keys) != $this->result_object->getReturnedRows()) {
			$pointer = $this->pointer;
			$this->createAllRecords();
			$this->pointer = $pointer;
		}
		return $this->primary_keys;
	}
	
	
	/**
	 * Returns if this record set is flagged for association with the fActiveRecord object that references it
	 * 
	 * @internal
	 * 
	 * @return boolean  If this record set is flagged for association
	 */
	public function isFlaggedForAssociation()
	{
		return $this->associate;
	}
	
	
	/**
	 * Returns the primary key for the current record (used for iteration)
	 * 
	 * @internal
	 * 
	 * @return mixed  The primay key of the current record
	 */
	public function key()
	{
		return $this->pointer;
	}
	
	
	/**
	 * Moves to the next record in the set (used for iteration)
	 * 
	 * @internal
	 * 
	 * @return void
	 */
	public function next()
	{
		if (sizeof($this->records) < $this->result_object->getReturnedRows()) {
			$this->result_object->next();	
		}
		$this->pointer++;
	}
	
	
	/**
	 * Preloads a result object for related data
	 * 
	 * @throws fValidationException
	 * 
	 * @param  string $related_class  This should be the name of a related class
	 * @param  string $route          This should be a column name or a join table name and is only required when there are multiple routes to a related table. If there are multiple routes and this is not specified, an fProgrammerException will be thrown.
	 * @return void
	 */
	private function performPreload($related_class, $route=NULL)
	{
		$related_table = fORM::tablize($related_class);
		
		$sql = ($this->result_object->getUntranslatedSQL()) ? $this->result_object->getUntranslatedSQL() : $this->result_object->getSQL();
		
		$clauses = fSQLParsing::parseSelectSQL($sql);
		
		if (!empty($clauses['GROUP BY'])) {
			fCore::toss('fProgrammerException', 'Preloading related data is not possible for queries that contain a GROUP BY clause');
		}
		
		$table = fORM::tablize($this->class_name);
		
		$route        = fORMSchema::getRouteName($table, $related_table, $route, '*-to-many');
		$relationship = fORMSchema::getRoute($table, $related_table, $route, '*-to-many');
		
		// Get the existing joins and add any necessary ones
		$joins = fSQLParsing::parseJoins($clauses['FROM'], fORMSchema::getInstance());
		$joins = fORMDatabase::addJoin($joins, $table, $route, $relationship);
		
		// Find the aliases we are gonna need
		$table_alias         = fORMDatabase::findTableAlias($table, $joins);
		$join_name           = $table . '_' . $related_table . '{' . $route . '}';
		$related_table_alias = $joins[$join_name]['table_alias'];
		
		// Build the query out
		$new_sql  = 'SELECT ' . $related_table_alias . '.* ';
		$new_sql .= 'FROM :from_clause ';
		
		if (!empty($clauses['WHERE'])) {
			$new_sql .= 'WHERE (' . $clauses['WHERE'] . ') ';
		}
		
		// Limited queries require a slight bit of additional modification so that we only load the related data for the elements returned
		if (!empty($clauses['LIMIT'])) {
			if (!empty($clauses['WHERE'])) {
				$new_sql .= ' AND ';
			} else {
				$new_sql .= 'WHERE ';
			}
			$new_sql .= fORMDatabase::createPrimaryKeyWhereCondition($table, $table_alias, $this->getPrimaryKeys());
		}
		
		// If the original query did not have an order by, we need to create one
		if (empty($clauses['ORDER BY'])) {
			
			$clauses['ORDER BY'] = 'CASE ';
			
			$pk_columns      = fORMSchema::getInstance()->getKeys($table, 'primary');
			$first_pk_column = $pk_columns[0];
			
			$number = 0;
			foreach ($this->primary_keys as $primary_keys) {
				$clauses['ORDER BY'] .= 'WHEN ';
				
				if (is_array($primary_keys)) {
					$conditions = array();
					foreach ($pk_columns as $pk_column) {
						$conditions[] = $table . '.' . $pk_column . fORMDatabase::prepareBySchema($table, $pk_column, $primary_keys[$pk_column], '=');			 		
					}
					$clauses['ORDER BY'] .= join(' AND ', $conditions);
				} else {
					$clauses['ORDER BY'] .= $table . '.' . $first_pk_column . fORMDatabase::prepareBySchema($table, $first_pk_column, $primary_keys, '=');
				}	
				
				$clauses['ORDER BY'] .= ' THEN ' . $number . ' ';	
				
				$number++;
			}	
			
			$clauses['ORDER BY'] .= 'END ASC';	
		}
			
		$new_sql .= 'ORDER BY ' . $clauses['ORDER BY'];
		
		$order_bys = fORMRelated::getOrderBys($this->class_name, $related_class, $route);
			
		if ($clauses['ORDER BY'] && $order_bys != array()) {
			$new_sql .= ', ';
		}
		
		if ($order_bys) {
			$new_sql .= fORMDatabase::createOrderByClause($related_table, $order_bys);
		}
		
		$new_sql = fORMDatabase::insertFromClause($table, $new_sql, $joins);
		
		if (!isset($this->preloaded_result_objects[$related_table])) {
			$this->preloaded_result_objects[$related_table] = array();
		}
		
		$result = fORMDatabase::getInstance()->translatedQuery($new_sql);
		
		$total_records = sizeof($this->records);
		for ($i=0; $i < $total_records; $i++) {
			
			$record = $this->records[$i];
			
			$pk_columns = fORMSchema::getInstance()->getKeys($table, 'primary');
			$keys       = array();
			
			foreach ($pk_columns as $pk_column) {
				$method = 'get' . fInflection::camelize($pk_column, TRUE);
				$keys[$pk_column] = $record->$method();
			}
			
			$rows = array();
						
			try {
				while (!array_diff($keys, $result->current())) {
					$rows[] = $result->fetchRow();
				}
			} catch (fExpectedException $e) { }
			
			$method = 'get' . fInflection::camelize($relationship['column'], TRUE);
			
			$sql = 'SELECT *
						FROM ' . $related_table . '
						WHERE ' . $relationship['related_column'] . fORMDatabase::prepareBySchema($related_table, $relationship['related_column'], $record->$method(), '=');
			
			$injected_result = new fResult('array');
			$injected_result->setSQL($sql);
			$injected_result->setResult($rows);
			$injected_result->setReturnedRows(sizeof($rows));
			$injected_result->setAffectedRows(0);
			$injected_result->setAutoIncrementedValue(NULL);
			
			$set = new fRecordSet($related_class, $injected_result);
			
			$method = 'inject' . fInflection::pluralize($related_class);
			$record->$method($set);		
		}
	}
	
	
	/**
	 * Rewinds the set to the first record (used for iteration)
	 * 
	 * @internal
	 * 
	 * @return void
	 */
	public function rewind()
	{
		$this->pointer = 0;
	}
	
	
	/**
	 * Sorts the set by the return value of a method from the class created
	 * 
	 * @throws fValidationException
	 * 
	 * @param  string $method     The method to call on each object to get the value to sort by
	 * @param  string $direction  Either 'asc' or 'desc'
	 * @return void
	 */
	public function sort($method, $direction)
	{
		if (!in_array($direction, array('asc', 'desc'))) {
			fCore::toss('fProgrammerException', 'Sort direction ' . $direction . ' should be either asc or desc');
		}
		
		// We will create an anonymous function here to handle the sort
		$lambda_params = '$a,$b';
		$lambda_funcs  = array(
			'asc'  => 'return strnatcasecmp($a->' . $method . '(), $b->' . $method . '());',
			'desc' => 'return strnatcasecmp($b->' . $method . '(), $a->' . $method . '());'
		);
		
		$this->sortByCallback(create_function($lambda_params, $lambda_funcs[$direction]));
	}
	
	
	/**
	 * Sorts the set by passing the callback to {@link http://php.net/usort usort()}
	 * 
	 * @throws fValidationException
	 * 
	 * @param  mixed $callback  The function/method to pass to usort()
	 * @return void
	 */
	public function sortByCallback($callback)
	{
		$this->createAllRecords();
		usort($this->records, $callback);
		$this->extractPrimaryKeys();	
	}
	
	
	/**
	 * Throws a fEmptySetException if the fRecordSet is empty
	 * 
	 * @throws  fEmptySetException
	 * 
	 * @return void
	 */
	public function tossIfEmpty()
	{
		if (!$this->getCount()) {
			fCore::toss('fEmptySetException', 'No ' . fInflection::humanize(fInflection::pluralize($this->class_name)) . ' could be found');
		}
	}
	
	
	/**
	 * Returns if the set has any records left (used for iteration)
	 * 
	 * @internal
	 * 
	 * @return boolean  If the iterator is still valid
	 */
	public function valid()
	{
		return $this->pointer < $this->result_object->getReturnedRows();
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