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
 * @uses  fCore
 * @uses  fEmptySetException
 * @uses  fInflection
 * @uses  fORM
 * @uses  fORMDatabase
 * @uses  fORMSchema
 * @uses  fProgrammerException
 * @uses  fSQLParsing
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
	 * @param  string $class_name        The class to create the fRecordSet of
	 * @param  array  $where_conditions  The column => value comparisons for the where clause
	 * @param  array  $order_bys         The column => direction values to use for sorting
	 * @param  integer $limit            The number of records to fetch
	 * @param  integer $offset           The offset to use before limiting
	 * @return fRecordSet  A set of {@link fActiveRecord fActiveRecord objects}
	 */
	static public function create($class_name, $where_conditions=array(), $order_bys=array(), $limit=NULL, $offset=NULL)
	{
		$table_name   = fORM::tablize($class_name);
		
		$sql  = "SELECT DISTINCT " . $table_name . ".* FROM :from_clause";

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
			
			$non_limited_count_sql = str_replace('SELECT DISTINCT ' . $table_name . '.*', 'SELECT DISTINCT ' . join(', ', $primary_key_fields), $sql);
			$non_limited_count_sql = 'SELECT count(*) FROM (' . $non_limited_count_sql . ') AS sq';
			
			$sql .= ' LIMIT ' . $limit;	
			
			if ($offset !== NULL) {
				$sql .= ' OFFSET ' . $offset;	
			}
		}
		
		return new fRecordSet($class_name, fORMDatabase::getInstance()->translatedQuery($sql), $non_limited_count_sql);			
	}
	
	
	/**
	 * Creates an fRecordSet from an array of records
	 * 
	 * @internal
	 * 
	 * @param  array  $records  The records to create the set from, the order of the record set will be the same as the order of the array.
	 * @return fRecordSet  A set of {@link fActiveRecord fActiveRecord objects}
	 */
	static public function createFromObjects($records)
	{
		$class_name = get_class($records[0]);
		$table_name = fORM::tablize($class_name);
		
		$sql  = 'SELECT DISTINCT ' . $table_name . '.* FROM ' . $table_name . ' WHERE ';
		
		// Build the where clause
		$primary_key_fields = fORMSchema::getInstance()->getKeys($table_name, 'primary');
		$total_pk_fields = sizeof($primary_key_fields);
		
		$primary_keys = array();
		
		$i = 0;
		foreach ($records as $record) {
			$sql .= ($i > 0) ? 'OR' : '';
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
	 * @internal
	 * 
	 * @param  string $class_name    The type of object to create
	 * @param  array  $primary_keys  The primary keys of the objects to create
	 * @param  array  $order_bys     The column => direction values to use for sorting (see {@link fRecordSet::create()} for format)
	 * @param  integer $limit        The number of records to fetch
	 * @param  integer $offset       The offset to use before limiting
	 * @return fRecordSet  A set of {@link fActiveRecord fActiveRecord objects}
	 */
	static public function createFromPrimaryKeys($class_name, $primary_keys, $order_bys=array(), $limit=NULL, $offset=NULL)
	{
		$table_name   = fORM::tablize($class_name);
		
		settype($primary_keys, 'array');
		$primary_keys = array_merge($primary_keys);
		
		$sql  = 'SELECT DISTINCT ' . $table_name . '.* FROM :from_clause WHERE ';
		
		// Build the where clause
		$primary_key_fields = fORMSchema::getInstance()->getKeys($table_name, 'primary');
		$total_pk_fields = sizeof($primary_key_fields);
		
		$total_primary_keys = sizeof($primary_keys);
		for ($i=0; $i < $total_primary_keys; $i++) {
			$sql .= ($i > 0) ? 'OR' : '';
			$sql .= ($total_pk_fields > 1) ? ' (' : '';     
			
			for ($j=0; $j < $total_pk_fields; $j++) {
				$pkf = $primary_key_fields[$j];
				
				$sql .= ($j > 0) ? ' AND ' : '';
				$sql .= $table_name . '.' . $pkf . fORMDatabase::prepareBySchema($table_name, $pkf, $primary_keys[$i][$pkf], '=');	
			}
			
			$sql .= ($total_pk_fields > 1) ? ' (' : '';
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
			
			$non_limited_count_sql = str_replace('SELECT DISTINCT ' . $table_name . '.*', 'SELECT DISTINCT ' . join(', ', $primary_key_fields), $sql);
			$non_limited_count_sql = 'SELECT count(*) FROM (' . $non_limited_count_sql . ') AS sq';
			
			$sql .= ' LIMIT ' . $limit;	
			
			if ($offset !== NULL) {
				$sql .= ' OFFSET ' . $offset;	
			}
		}
		
		return new fRecordSet($class_name, fORMDatabase::getInstance()->translatedQuery($sql), $non_limited_count_sql);	
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
	 * Calls sortCallback with the appropriate method
	 * 
	 * @param  string $method_name  The name of the method called
	 * @param  string $parameters   The parameters passed
	 * @return void
	 */
	public function __call($method_name, $parameters)
	{
		list($action, $element) = explode('_', fInflection::underscorize($method_name), 2);
		
		switch ($action) {
			case 'sort':                                           
				// Trim the "by_" off of the beginning
				$sort_method = substr($element, 3);
				$sort_method = fInflection::camelize($sort_method, FALSE); 
				return $this->performSort($parameters[0], $parameters[1], $sort_method);      
				
			case 'preload':
				return $this->performPreload($element, ($parameters != array()) ? $parameters[0] : NULL);
		}     
		
		fCore::toss('fProgrammerException', 'Unknown method, ' . $method_name . '(), called');      
	}
	
	
	/**
	 * Creates all records for the primary keys provided
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
	 * @internal
	 * 
	 * @return object  The current record
	 */
	public function current()
	{
		if (!isset($this->records[$this->key()])) {
			$this->records[$this->key()] = new $this->class_name($this->result_object);
			
			// Fetch the primary keys for this object
			$primary_keys = fORMSchema::getInstance()->getKeys(fORM::tablize($this->class_name), 'primary');
			$keys = array();
			foreach ($primary_keys as $primary_key) {
				$method = 'get' . fInflection::camelize($primary_key, TRUE);
				$keys[$primary_key] = $this->records[$this->key()]->$method();	
			}
			$this->primary_keys[$this->key()] = (sizeof($primary_keys) == 1) ? $keys[array_unshift($primary_keys)] : $keys;
			
			// Pass the preloaded data to the object
			foreach ($this->preloaded_result_objects as $related_table => $result_objects) {
				foreach ($result_objects as $route => $result_object) {
					$this->injectSubSet($related_table, $route, $result_object);
				}
			}
		}
		return $this->records[$this->key()];
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
			$this->non_limited_count = fORMDatabase::getInstance()->translatedQuery($this->non_limited_count_sql)->fetchScalar();
		}
		return $this->non_limited_count;
	}
	
	
	/**
	 * Returns all of the records in the set
	 * 
	 * @return array  The records in the set
	 */
	public function getRecords()
	{
		if (sizeof($this->records) != $this->result_object->getReturnedRows()) {
			$pointer = $this->result_object->getPointer();
			$this->createAllRecords();	
			$this->result_object->seek($pointer);
		}
		return $this->records;   
	}
	
	
	/**
	 * Returns the primary keys for all of the records in the set
	 * 
	 * @return array  The primary keys of all the records in the set
	 */
	public function getPrimaryKeys()
	{
		if (sizeof($this->primary_keys) != $this->result_object->getReturnedRows()) {
			$pointer = $this->result_object->getPointer();
			$this->createAllRecords();	
			$this->result_object->seek($pointer);	
		}
		return $this->primary_keys;   
	}
	
	
	/**
	 * Injects a set of related information into the current record
	 * 
	 * @param string $related_table   The table we are injecting the values for
	 * @param string $route           The route to the related table
	 * @param fResult $result_object  The pre-loaded result object that we are extracting the sequence from
	 * @return void
	 */
	private function injectSubSet($related_table, $route, $result_object) 
	{
		$rows = array();
		
		$keys = $this->primary_keys[$this->key()];
		settype($keys, 'array');
					
		try {
			while (array_diff($keys, $result_object->current()) == array()) {
				$rows[] = $result_object->fetchRow();	
			}	
		} catch (fPrintableException $e) { }		
		
		$table = fORM::tablize($this->class_name);
		$relationship = fORMSchema::getRoute($table, $related_table, $route);
		
		$record = $this->records[$this->key()];
		$method = 'get' . fInflection::camelize($relationship['column'], TRUE);
		
		$sql = 'SELECT *
					FROM ' . $related_table . '
					WHERE ' . $relationship['related_column'] . fORMDatabase::prepareBySchema($related_table, $relationship['related_column'], $record->$method(), '=');
		
		$result = new fResult('array');
		$result->setSQL($sql);
		$result->setResult($rows);
		$result->setReturnedRows(sizeof($rows));
		$result->setAffectedRows(0);
		$result->setAutoIncrementedValue(NULL);
		
		$class_name = fORM::classize($related_table);
		
		$set = new fRecordSet($class_name, $result);
		
		$method = 'inject' . fInflection::pluralize($class_name);
		$record->$method($set);	
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
		return $this->result_object->key();
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
		$this->result_object->next();
	}
	
	
	/**
	 * Preloads a result object for related data
	 * 
	 * @param  string $related_table  This should be the of a related table
	 * @param  string $route          This should be a column name or a join table name and is only required when there are multiple routes to a related table. If there are multiple routes and this is not specified, an fProgrammerException will be thrown.
	 * @return void
	 */
	private function performPreload($related_table, $route=NULL)
	{
		$sql = ($this->result_object->getUntranslatedSQL()) ? $this->result_object->getUntranslatedSQL() : $this->result_object->getSQL();
		
		$clauses = fSQLParsing::parseSelectSQL($sql);
		
		if (!empty($clauses['GROUP BY'])) {
			fCore::toss('fProgrammerException', 'Preloading related data is not possible for queries that contain a GROUP BY clause');	
		}
		
		$table = fORM::tablize($this->class_name);
		
		$route         = fORMSchema::getToManyRouteName($table, $related_table, $route);
		$relationship  = fORMSchema::getToManyRoute($table, $related_table, $route);
		$related_table = $relationship['related_table'];
		
		// Get the existing joins and add any necessary ones
		$joins = fSQLParsing::parseJoins($clauses['FROM'], fORMSchema::getInstance());
		$joins = fORMDatabase::addJoin($joins, $route, $relationship);
		
		// Find the aliases we are gonna need
		$table_alias         = fORMDatabase::findTableAlias($table, $joins);
		$join_name           = $table . '_' . $related_table . '[' . $route . ']';
		$related_table_alias = $joins[$join_name]['table_alias'];
		
		// Build the query out
		$new_sql  = 'SELECT DISTINCT ' . $related_table_alias . '.* ';
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
		
		// We need to add the related data order bys to the existing order bys
		$order_bys = fORMRelatedData::getOrderBys($table, $related_table, $route);
		if (!empty($clauses['ORDER BY']) || $order_bys != array()) {
			$new_sql .= 'ORDER BY ' . $clauses['ORDER BY'];
				
			if ($clauses['ORDER BY'] && $order_bys != array()) {
				$new_sql .= ', ';
			}
			
			if ($order_bys != array()) {
				$new_sql .= fORMDatabase::createOrderByClause($related_table, $order_bys);	
			}
		} 
		
		$new_sql = fORMDatabase::insertFromClause($this_table, $new_sql, $joins);
		
		if (!isset($this->preloaded_result_objects[$related_table])) {
			$this->preloaded_result_objects[$related_table] = array();	
		}
		
		$this->preloaded_result_objects[$related_table][$route] = fORMDatabase::getInstance()->translatedQuery($new_sql);
	}
	
	
	/**
	 * Does the action of sorting records
	 * 
	 * @param  object $a       Record a
	 * @param  object $b       Record b
	 * @param  string $method  The method to sort by
	 * @return integer  < 0 if a is less than b; 0 if a = b; > 0 if a is greater than b
	 */
	protected function performSort($a, $b, $method)
	{
		return strnatcasecmp($a->$method(), $b->$method());  
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
		$this->result_object->rewind();
	}
	
	
	/**
	 * Sorts the set by the return value of a method from the class created
	 * 
	 * @param  string $method_name  The method to call on each object to get the value to sort
	 * @param  string $direction    Either 'asc' or 'desc'
	 * @return void
	 */
	public function sort($method_name, $direction)
	{
		if (!in_array($direction, array('asc', 'desc'))) {
			fCore::toss('fProgrammerException', 'Sort direction ' . $direction . ' should be either asc or desc');
		}
		
		$this->createAllRecords(); 
		
		if (!method_exists($this->records[0], $method_name)) {
			fCore::toss('fProgrammerException', 'The method specified for sorting, ' . $method_name . '(), does not exist');
		}
		
		// Use __call to pass the desired method name through to the sort callback
		usort($this->records, array($this, 'sortBy' . fInflection::camelize($method_name, TRUE)));
		if ($direction == 'desc') {
			array_reverse($this->records);	
		}
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
		if (!$this->getSizeOf()) {
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
		return $this->result_object->valid();
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