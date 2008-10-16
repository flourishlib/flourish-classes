<?php
/**
 * A lightweight, iterable set of {@link fActiveRecord}-based objects
 * 
 * @copyright  Copyright (c) 2007-2008 William Bond
 * @author     William Bond [wb] <will@flourishlib.com>
 * @license    http://flourishlib.com/license
 * 
 * @package    Flourish
 * @link       http://flourishlib.com/fRecordSet
 * 
 * @version    1.0.0b
 * @changes    1.0.0b  The initial implementation [wb, 2007-08-04]
 */
class fRecordSet implements Iterator
{
	// The following constants allow for nice looking callbacks to static methods
	const build                  = 'fRecordSet::build';
	const buildFromRecords       = 'fRecordSet::buildFromRecords';
	const buildFromSQL           = 'fRecordSet::buildFromSQL';
	const registerMethodCallback = 'fRecordSet::registerMethodCallback';
	const reset                  = 'fRecordSet::reset';
	
	
	/**
	 * Callbacks registered for the __call() handler
	 * 
	 * @var array
	 */
	static private $method_callbacks = array();
	
	
	/**
	 * Creates an {@link fRecordSet} by specifying the class to create plus the where conditions and order by rules
	 * 
	 * The where conditions array can contain key => value entries in any of the following formats (where VALUE/VALUE2 can be of any data type):
	 * <pre>
	 *  - '%column%='                       => VALUE,                        // column = VALUE
	 *  - '%column%!'                       => VALUE,                        // column <> VALUE
	 *  - '%column%~'                       => VALUE,                        // column LIKE '%VALUE%'
	 *  - '%column%<'                       => VALUE,                        // column < VALUE
	 *  - '%column%<='                      => VALUE,                        // column <= VALUE
	 *  - '%column%>'                       => VALUE,                        // column > VALUE
	 *  - '%column%>='                      => VALUE,                        // column >= VALUE
	 *  - '%column%='                       => array(VALUE, VALUE2,...),     // column IN (VALUE, VALUE2, ...)
	 *  - '%column%!'                       => array(VALUE, VALUE2,...),     // column NOT IN (VALUE, VALUE2, ...)
	 *  - '%column%~'                       => array(VALUE, VALUE2,...),     // (column LIKE '%VALUE%' OR column LIKE '%VALUE2%' OR column ...)
	 *  - '%column%!|%column2%<|%column3%=' => array(VALUE, VALUE2, VALUE3), // (column <> '%VALUE%' OR column2 < '%VALUE2%' OR column3 = '%VALUE3%')
	 *  - '%column%|%column2%|%column3%~'   => VALUE,                        // (column LIKE '%VALUE%' OR column2 LIKE '%VALUE2%' OR column3 LIKE '%VALUE%')
	 *  - '%column%|%column2%|%column3%~'   => array(VALUE, VALUE2,...)      // ((column LIKE '%VALUE%' OR column2 LIKE '%VALUE%' OR column3 LIKE '%VALUE%') AND (column LIKE '%VALUE2%' OR column2 LIKE '%VALUE2%' OR column3 LIKE '%VALUE2%') AND ...)
	 * </pre>
	 * 
	 * The order bys array can contain key => value entries in any of the following formats:
	 * <pre>
	 *  - '%column%'     => 'asc'      // 'first_name' => 'asc'
	 *  - '%column%'     => 'desc'     // 'last_name'  => 'desc'
	 *  - '%expression%' => 'asc'      // "CASE first_name WHEN 'smith' THEN 1 ELSE 2 END" => 'asc'
	 *  - '%expression%' => 'desc'     // "CASE first_name WHEN 'smith' THEN 1 ELSE 2 END" => 'desc'
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
	 * @param  string  $class             The class to create the {@link fRecordSet} of
	 * @param  array   $where_conditions  The column => value comparisons for the where clause
	 * @param  array   $order_bys         The column => direction values to use for sorting
	 * @param  integer $limit             The number of records to fetch
	 * @param  integer $page              The page offset to use when limiting records
	 * @return fRecordSet  A set of {@link fActiveRecord} objects
	 */
	static public function build($class, $where_conditions=array(), $order_bys=array(), $limit=NULL, $page=NULL)
	{
		// Ensure that the class has been configured
		if (!fORM::isConfigured($class)) {
			new $class();
		}
		
		$table = fORM::tablize($class);
		
		$sql = "SELECT " . $table . ".* FROM :from_clause";
		
		if ($where_conditions) {
			$sql .= ' WHERE ' . fORMDatabase::createWhereClause($table, $where_conditions);
		}
		
		$sql .= ' :group_by_clause ';
		
		if ($order_bys) {
			$sql .= 'ORDER BY ' . fORMDatabase::createOrderByClause($table, $order_bys);
		
		// If no ordering is specified, order by the primary key
		} else {
			$primary_keys = fORMSchema::retrieve()->getKeys($table, 'primary');
			$expressions = array();
			foreach ($primary_keys as $primary_key) {
				$expressions[] = $table . '.' . $primary_key . ' ASC';
			}
			$sql .= 'ORDER BY ' . join(', ', $expressions);
		}
		
		$sql = fORMDatabase::insertFromAndGroupByClauses($table, $sql);
		
		// Add the limit clause and create a query to get the non-limited total
		$non_limited_count_sql = NULL;
		if ($limit !== NULL) {
			$primary_key_fields = fORMSchema::retrieve()->getKeys($table, 'primary');
			$primary_key_fields = fORMDatabase::addTableToValues($table, $primary_key_fields);
			
			$non_limited_count_sql = str_replace('SELECT ' . $table . '.*', 'SELECT ' . join(', ', $primary_key_fields), $sql);
			$non_limited_count_sql = 'SELECT count(*) FROM (' . $non_limited_count_sql . ') AS sq';
			
			$sql .= ' LIMIT ' . $limit;
			
			if ($page !== NULL) {
				
				if (!is_numeric($page) || $page < 1) {
					fCore::toss(
						'fProgrammerException',
						fGrammar::compose(
							'The page specified, %s, is not a number or less than one',
							fCore::dump($page)
						)
					);
				}
				
				$sql .= ' OFFSET ' . (($page-1) * $limit);
			}
		}
		
		return new fRecordSet($class, fORMDatabase::retrieve()->translatedQuery($sql), $non_limited_count_sql);
	}
	
	
	/**
	 * Creates an {@link fRecordSet} from an array of records
	 * 
	 * @throws fValidationException
	 * @internal
	 * 
	 * @param  string $class    The type of object to create
	 * @param  array  $records  The records to create the set from, the order of the record set will be the same as the order of the array.
	 * @return fRecordSet  A set of {@link fActiveRecord} objects
	 */
	static public function buildFromRecords($class, $records)
	{
		$record_set = new fRecordSet($class);
		$record_set->records = $records;
		return $record_set;
	}
	
	
	/**
	 * Creates an {@link fRecordSet} from an SQL statement
	 * 
	 * @param  string $class                  The type of object to create
	 * @param  string $sql                    The SQL to create the set from
	 * @param  string $non_limited_count_sql  An SQL statement to get the total number of rows that would have been returned if a LIMIT clause had not been used. Should only be passed if a LIMIT clause is used.
	 * @return fRecordSet  A set of {@link fActiveRecord} objects
	 */
	static public function buildFromSQL($class, $sql, $non_limited_count_sql=NULL)
	{
		return new fRecordSet(
			$class,
			fORMDatabase::retrieve()->translatedQuery($sql),
			$non_limited_count_sql
		);
	}
	
	
	/**
	 * Registers a callback to be called when a specific method is handled by __call()
	 *  
	 * The callback should accept the following parameters:
	 *   - $record_set:  The actual record set
	 *   - $class:       The class of each record
	 *   - &$records:    The ordered array of fActiveRecords
	 *   - &$pointer:    The current array pointer for the records array
	 *   - &$associate:  If the record should be associated with an fActiveRecord holding it
	 * 
	 * @param  string   $method    The method to hook for
	 * @param  callback $callback  The callback to execute - see method description for parameter list
	 * @return void
	 */
	static public function registerMethodCallback($method, $callback)
	{
		self::$method_callbacks[$method] = $callback;
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
		self::$method_callbacks = array();
	}
	
	
	/**
	 * A flag to indicate this should record set should be associated to the parent {@link fActiveRecord} object
	 * 
	 * @var boolean
	 */
	private $associate = FALSE;
	
	/**
	 * The type of class to create from the primary keys provided
	 * 
	 * @var string
	 */
	private $class = NULL;
	
	/**
	 * The number of rows that would have been returned if a LIMIT clause had not been used
	 * 
	 * @var integer
	 */
	private $non_limited_count = NULL;
	
	/**
	 * The SQL to get the total number of rows that would have been returned if a LIMIT clause had not been used
	 * 
	 * @var string
	 */
	private $non_limited_count_sql = NULL;
	
	/**
	 * The index of the current record
	 * 
	 * @var integer
	 */
	private $pointer = 0;
	
	/**
	 * An array of the records in the set, initially empty
	 * 
	 * @var array
	 */
	private $records = array();
	
	
	/**
	 * Allows for preloading of related records by dynamically creating preload{related plural class}() methods
	 *  
	 * @throws fValidationException
	 * 
	 * @param  string $method_name  The name of the method called
	 * @param  string $parameters   The parameters passed
	 * @return void
	 */
	public function __call($method_name, $parameters)
	{
		list($action, $element) = fORM::parseMethod($method_name);
		
		if (isset(self::$method_callbacks[$method_name])) {
			return fCore::call(
				self::$method_callbacks[$method_name],
				array(
					$this,
					$this->class,
					&$this->records,
					&$this->pointer,
					&$this->associate
				)
			);	
		}
		
		$route         = ($parameters) ? $parameters[0] : NULL;
		$related_class = fGrammar::singularize(fGrammar::camelize($element, TRUE));
		 
		switch ($action) {
			case 'prebuild':
				return $this->prebuild($related_class, $route);
			
			case 'precount':
				return $this->precount($related_class, $route);
				
			case 'precreate':
				return $this->precreate($related_class, $route);
		}
		 
		fCore::toss('fProgrammerException', 'Unknown method, ' . $method_name . '(), called');
	}
	 
	 
	/** 
	 * Sets the contents of the set
	 * 
	 * @param  string  $class                  The type of records to create
	 * @param  fResult $result_object          The {@link fResult} object of the records to create
	 * @param  string  $non_limited_count_sql  An SQL statement to get the total number of rows that would have been returned if a LIMIT clause had not been used. Should only be passed if a LIMIT clause is used.
	 * @return fRecordSet
	 */
	protected function __construct($class, fResult $result_object=NULL, $non_limited_count_sql=NULL)
	{
		if (!class_exists($class)) {
			fCore::toss(
				'fProgrammerException',
				fGrammar::compose(
					'The class specified, %s, could not be loaded',
					fCore::dump($class)
				)
			);
		}
		
		if (!is_subclass_of($class, 'fActiveRecord')) {
			fCore::toss(
				'fProgrammerException',
				fGrammar::compose(
					'The class specified, %1$s, does not extend %2$s. All classes used with %3$s must extend %4$s.',
					fCore::dump($class),
					'fActiveRecord',
					'fRecordSet',
					'fActiveRecord'
				)
			);
		}
		
		$this->class                 = $class;
		$this->non_limited_count_sql = $non_limited_count_sql;
		
		while ($result_object && $result_object->valid()) {
			$this->records[] = new $class($result_object);
			$result_object->next();
		}
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
	 * Calls a specific method on each object, returning an array of the results
	 * 
	 * @return array  An array the size of the record set with one result from each record/method
	 */
	public function call($method)
	{
		$output = array();
		foreach ($this->records as $record) {
			$output[] = $record->$method();
		}
		return $output;
	}
	
	
	/**
	 * Creates an order by clause for the primary keys of this record set
	 * 
	 * @param  string $route  The route to this table from another table
	 * @return string  The order by clause
	 */
	private function constructOrderByClause($route=NULL)
	{
		$table = fORM::tablize($this->class);
		$table_with_route = ($route) ? $table . '{' . $route . '}' : $table;
		
		$pk_columns      = fORMSchema::retrieve()->getKeys($table, 'primary');
		$first_pk_column = $pk_columns[0];
		
		$sql = '';
		
		$number = 0;
		foreach ($this->getPrimaryKeys() as $primary_key) {
			$sql .= 'WHEN ';
			 
			if (is_array($primary_key)) {
				$conditions = array();
				foreach ($pk_columns as $pk_column) {
					$conditions[] = $table_with_route . '.' . $pk_column . fORMDatabase::escapeBySchema($table, $pk_column, $primary_key[$pk_column], '=');
				}
				$sql .= join(' AND ', $conditions);
			} else {
				$sql .= $table_with_route . '.' . $first_pk_column . fORMDatabase::escapeBySchema($table, $first_pk_column, $primary_key, '=');
			}
			 
			$sql .= ' THEN ' . $number . ' ';
			 
			$number++;
		}
		
		return 'CASE ' . $sql . 'END ASC';
	}
	
	
	/**
	 * Creates a where clause for the primary keys of this record set
	 * 
	 * @param  string $route  The route to this table from another table
	 * @return string  The where clause
	 */
	private function constructWhereClause($route=NULL)
	{
		$table = fORM::tablize($this->class);
		$table_with_route = ($route) ? $table . '{' . $route . '}' : $table;
		
		$pk_columns = fORMSchema::retrieve()->getKeys($table, 'primary');
		
		$sql = '';
		
		// We have a multi-field primary key, making things kinda ugly
		if (sizeof($pk_columns) > 1) {
			
			$conditions = array();
			 
			foreach ($this->getPrimaryKeys() as $primary_key) {
				$sub_conditions = array();
				foreach ($pk_columns as $pk_column) {
					$sub_conditions[] = $table_with_route . '.' . $pk_column . fORMDatabase::escapeBySchema($table, $pk_column, $primary_key[$pk_column], '=');
				}
				$conditions[] = join(' AND ', $sub_conditions);
			}
			$sql .= '(' . join(') OR (', $conditions) . ')';
		 
		// We have a single primary key field, making things nice and easy
		} else {
			$first_pk_column = $pk_columns[0];
		 
			$values = array();
			foreach ($this->getPrimaryKeys() as $primary_key) {
				$values[] = fORMDatabase::escapeBySchema($table, $first_pk_column, $primary_key);
			}
			$sql .= $table_with_route . '.' . $first_pk_column . ' IN (' . join(', ', $values) . ')';
		}
		
		return $sql;
	}
	
	
	/**
	 * Returns the number of records in the set
	 * 
	 * @param  boolean $ignore_limit  If set to TRUE, this method will return the number of records that would be in the set if there was no LIMIT clause
	 * @return integer  The number of records in the set
	 */
	public function count($ignore_limit=FALSE)
	{
		if ($ignore_limit !== TRUE || $this->non_limited_count_sql === NULL) {
			return sizeof($this->records);
		}
		
		if ($this->non_limited_count === NULL) {
			try {
				$this->non_limited_count = fORMDatabase::retrieve()->translatedQuery($this->non_limited_count_sql)->fetchScalar();
			} catch (fExpectedException $e) {
				$this->non_limited_count = $this->count();
			}
		}
		return $this->non_limited_count;
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
			fCore::toss(
				'fProgrammerException',
				fGrammar::compose('There are no remaining records')
			);
		}
		
		return $this->records[$this->pointer];
	}
	
	
	/**
	 * Filters the record set via a callback
	 * 
	 * @param  callback $callback  The callback can be either a callback that accepts a single parameter and returns a boolean, or a string like '{record}::methodName' to filter based on the output of $record->methodName()
	 * @return fRecordSet  A new fRecordSet with the filtered records
	 */
	public function filter($callback)
	{
		if (!$this->records) {
			return clone $this;
		}
		
		$call_filter = FALSE;
		if (preg_match('#^\{record\}::([a-z0-9_\-]+)$#i', $callback, $matches)) {
			$call_filter = TRUE;
			$method      = $matches[1];
		}
			
		$new_records = array();
		foreach ($this->records as $record) {
			if ($call_filter) {
				$value = $record->$method();
			} else {
				$value = fCore::call($callback, $record);
			}
			if ($value) {
				$new_records[] = $record;
			}
		}
		
		return self::buildFromRecords($this->class, $new_records);
	}
	
	
	/**
	 * Flags this record set for association with the {@link fActiveRecord} object that references it
	 * 
	 * @internal
	 * 
	 * @return void
	 */
	public function flagAssociate()
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
			fCore::toss(
				'fNoRemainingException',
				fGrammar::compose('There are no remaining records')
			);
		}
	}
	
	
	/**
	 * Returns the class name of the record being stored
	 * 
	 * @return string  The class name of the records in the set
	 */
	public function getClass()
	{
		return $this->class;
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
		return $this->records;
	}
	
	
	/**
	 * Returns the primary keys for all of the records in the set
	 * 
	 * @return array  The primary keys of all the records in the set
	 */
	public function getPrimaryKeys()
	{
		$table           = fORM::tablize($this->class);
		$pk_columns      = fORMSchema::retrieve()->getKeys($table, 'primary');
		$first_pk_column = $pk_columns[0];
		
		$primary_keys = array();
		
		foreach ($this->records as $number => $record) {
			$keys = array();
			
			foreach ($pk_columns as $pk_column) {
				$method = 'get' . fGrammar::camelize($pk_column, TRUE);
				$keys[$pk_column] = $record->$method();
			}
			
			$primary_keys[$number] = (sizeof($pk_columns) == 1) ? $keys[$first_pk_column] : $keys;
		}
		
		return $primary_keys;
	}
	
	
	/**
	 * Returns if this record set is flagged for association with the {@link fActiveRecord} object that references it
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
	 * Performs an array_map on the record in the set
	 * 
	 * The record will be passed to the callback as the first parameter unless
	 * it's position is specified by the placeholder string '{record}'. More
	 * details further down.
	 * 
	 * Additional parameters can be passed to the callback in one of two
	 * different ways:
	 *  - Passing a non-array value will cause it to be passed to the callback
	 *  - Passing an array value will cause the array values to be passed to the callback with their corresponding record
	 *  
	 * If an array parameter is too long (more items than records in the set)
	 * it will be truncated. If an array parameter is too short (less items
	 * than records in the set) it will be padded with NULL values.
	 * 
	 * To allow passing the record as a specific parameter to the callback, a
	 * placeholder string '{record}' will be replaced with a the record. You
	 * can also specify '{record}::methodName' to cause the output of a method
	 * from the record to be passed instead of the whole record.
	 * 
	 * @param  callback $callback       The callback to pass the values to
	 * @param  mixed    $parameter,...  The parameter to pass to the callback - see method description for details
	 * @return array  An array of the results from the callback
	 */
	public function map($callback)
	{
		$parameters = array_slice(func_get_args(), 1);
		
		if (!$this->records) {
			return array();
		}
		
		$parameters_array = array();
		$found_record     = FALSE;
		$total_records    = sizeof($this->records);
		
		foreach ($parameters as $parameter) {
			if (!is_array($parameter)) {
				if (preg_match('#^\{record\}::([a-z0-9_\-]+)$#i', $parameter, $matches)) {
					$parameters_array[] = $this->call($matches[1]);
					$found_record = TRUE;
				} elseif ($parameter == '{record}') {
					$parameters_array[] = $this->records;
					$found_record = TRUE;
				} else {
					$parameters_array[] = array_pad(array(), $total_records, $parameter);
				}
				
			} elseif (sizeof($parameter) > $total_records) {
				$parameters_array[] = array_slice($parameter, 0, $total_records);
			} elseif (sizeof($parameter) < $total_records) {
				$parameters_array[] = array_pad($parameter, $total_records, NULL);
			} else {
				$parameters_array[] = $parameter;
			}
		}
		
		if (!$found_record) {
			array_unshift($parameters_array, $this->records);
		}
		
		array_unshift($parameters_array, $callback);
		
		return call_user_func_array('array_map', $parameters_array);
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
		$this->pointer++;
	}
	
	
	/** 
	 * Builds the related records for all records in this set in one DB query
	 *  
	 * @param  string $related_class  This should be the name of a related class
	 * @param  string $route          This should be a column name or a join table name and is only required when there are multiple routes to a related table. If there are multiple routes and this is not specified, an fProgrammerException will be thrown.
	 * @return void
	 */
	private function prebuild($related_class, $route=NULL)
	{
		// If there are no primary keys we can just exit
		if (!array_merge($this->getPrimaryKeys())) {
			return;
		}
		
		$related_table = fORM::tablize($related_class);
		$table         = fORM::tablize($this->class);
		 
		$route        = fORMSchema::getRouteName($table, $related_table, $route, '*-to-many');
		$relationship = fORMSchema::getRoute($table, $related_table, $route, '*-to-many');
		
		$table_with_route = ($route) ? $table . '{' . $route . '}' : $table;
		
		// Build the query out
		$where_sql    = $this->constructWhereClause($route);
		
		$order_by_sql = $this->constructOrderByClause($route);
		if ($related_order_bys = fORMRelated::getOrderBys($this->class, $related_class, $route)) {
			$order_by_sql .= ', ' . fORMDatabase::createOrderByClause($related_table, $related_order_bys);
		}
		
		$new_sql  = 'SELECT ' . $related_table . '.*';
		
		// If we are going through a join table we need the related primary key for matching
		if (isset($relationship['join_table'])) {
			$new_sql .= ", " . $table_with_route . '.' . $relationship['column'];
		}
		
		$new_sql .= ' FROM :from_clause ';
		$new_sql .= ' WHERE ' . $where_sql;
		$new_sql .= ' :group_by_clause ';
		$new_sql .= ' ORDER BY ' . $order_by_sql;
		 
		$new_sql = fORMDatabase::insertFromAndGroupByClauses($related_table, $new_sql);
		
		// Add the joining column to the group by
		if (strpos($new_sql, 'GROUP BY') !== FALSE) {
			$new_sql = str_replace(' ORDER BY', ', ' . $table . '.' . $relationship['column'] . ' ORDER BY', $new_sql);
		}
		 
		 
		// Run the query and inject the results into the records
		$result = fORMDatabase::retrieve()->translatedQuery($new_sql);
		 
		$total_records = sizeof($this->records);
		for ($i=0; $i < $total_records; $i++) {
			 
			
			// Get the record we are injecting into
			$record = $this->records[$i];
			$keys   = array();
			
			 
			// If we are going through a join table, keep track of the record by the value in the join table
			if (isset($relationship['join_table'])) {
				try {
					$current_row = $result->current();
					$keys[$relationship['column']] = $current_row[$relationship['column']];
				} catch (fExpectedException $e) { }
			
			// If it is a straight join, keep track of the value by the related column value
			} else {
				$method = 'get' . fGrammar::camelize($relationship['related_column'], TRUE);
				$keys[$relationship['related_column']] = $record->$method();
			}
			 
			
			// Loop through and find each row for the current record
			$rows = array();
						 
			try {
				while (!array_diff_assoc($keys, $result->current())) {
					$row = $result->fetchRow();
					 
					// If we are going through a join table we need to remove the related primary key that was used for matching
					if (isset($relationship['join_table'])) {
						unset($row[$relationship['column']]);
					}
					 
					$rows[] = $row;
				}
			} catch (fExpectedException $e) { }
			 
			 
			// Build the SQL for the record set we are injecting
			$method = 'get' . fGrammar::camelize($relationship['column'], TRUE);
			 
			$sql  = "SELECT " . $related_table . ".* FROM :from_clause";
			 
			$where_conditions = array(
				$table_with_route . '.' . $relationship['column'] . '=' => $record->$method()
			);
			$sql .= ' WHERE ' . fORMDatabase::createWhereClause($related_table, $where_conditions);
			
			$sql .= ' :group_by_clause ';
			 
			if ($order_bys = fORMRelated::getOrderBys($this->class, $related_class, $route)) {
				$sql .= ' ORDER BY ' . fORMDatabase::createOrderByClause($related_table, $order_bys);
			}
			 
			$sql = fORMDatabase::insertFromAndGroupByClauses($related_table, $sql);
			 
			
			// Set up the result object for the new record set
			$injected_result = new fResult(fORMDatabase::retrieve()->getType(), 'array');
			$injected_result->setSQL($sql);
			$injected_result->setResult($rows);
			$injected_result->setReturnedRows(sizeof($rows));
			$injected_result->setAffectedRows(0);
			$injected_result->setAutoIncrementedValue(NULL);
			 
			$set = new fRecordSet($related_class, $injected_result);
			 
			 
			// Inject the new record set into the record
			$method = 'inject' . fGrammar::pluralize($related_class);
			$record->$method($set, $route);
		}
	}
	
	
	/** 
	 * Counts the related records for all records in this set in one DB query
	 *  
	 * @param  string $related_class  This should be the name of a related class
	 * @param  string $route          This should be a column name or a join table name and is only required when there are multiple routes to a related table. If there are multiple routes and this is not specified, an fProgrammerException will be thrown.
	 * @return void
	 */
	private function precount($related_class, $route=NULL)
	{
		// If there are no primary keys we can just exit
		if (!array_merge($this->getPrimaryKeys())) {
			return;
		}
		
		$related_table = fORM::tablize($related_class);
		$table         = fORM::tablize($this->class);
		 
		$route        = fORMSchema::getRouteName($table, $related_table, $route, '*-to-many');
		$relationship = fORMSchema::getRoute($table, $related_table, $route, '*-to-many');
		
		$table_with_route = ($route) ? $table . '{' . $route . '}' : $table;
		
		// Build the query out
		$where_sql    = $this->constructWhereClause($route);
		$order_by_sql = $this->constructOrderByClause($route);
		
		$related_table_keys = fORMSchema::retrieve()->getKeys($related_table, 'primary');
		$related_table_keys = fORMDatabase::addTableToValues($related_table, $related_table_keys);
		$related_table_keys = join(', ', $related_table_keys);
		
		$column = $table_with_route . '.' . $relationship['column'];
		
		$new_sql  = 'SELECT count(' . $related_table_keys . ') AS __flourish_count, ' . $column . ' AS __flourish_column ';
		$new_sql .= ' FROM :from_clause ';
		$new_sql .= ' WHERE ' . $where_sql;
		$new_sql .= ' GROUP BY ' . $column;
		$new_sql .= ' ORDER BY ' . $column . ' ASC';
		 
		$new_sql = fORMDatabase::insertFromAndGroupByClauses($related_table, $new_sql);
		 
		// Run the query and inject the results into the records
		$result = fORMDatabase::retrieve()->translatedQuery($new_sql);
		
		$counts = array();
		foreach ($result as $row) {
			$counts[$row['__flourish_column']] = (int) $row['__flourish_count'];
		}
		
		unset($result);
		 
		$total_records = sizeof($this->records);
		$get_method   = 'get' . fGrammar::camelize($relationship['column'], TRUE);
		$tally_method = 'tally' . fGrammar::pluralize($related_class);
		
		for ($i=0; $i < $total_records; $i++) {
			$record = $this->records[$i];
			$count  = (isset($counts[$record->$get_method()])) ? $counts[$record->$get_method()] : 0;
			$record->$tally_method($count, $route);
		}
	}
	
	
	/** 
	 * Creates the objects for related records that are in a one-to-one or many-to-one relationship with the current class in a single DB query
	 *  
	 * @param  string $related_class  This should be the name of a related class
	 * @param  string $route          This should be the column name of the foreign key and is only required when there are multiple routes to a related table. If there are multiple routes and this is not specified, an fProgrammerException will be thrown.
	 * @return void
	 */
	private function precreate($related_class, $route=NULL)
	{
		// If there are no primary keys we can just exit
		if (!array_merge($this->getPrimaryKeys())) {
			return;
		}
		
		$relationship = fORMSchema::getRoute(
			fORM::tablize($this->class),
			fORM::tablize($related_class),
			$route,
			'*-to-many'
		);
		
		self::build(
			$related_class,
			array(
				$relationship['related_column'] . '=' => $this->call(
					fGrammar::camelize(
						$relatioship['column'],
						TRUE
					)
				)
			)
		);
	}
	
	
	/**
	 * Reduces the record set to a single value via a callback
	 * 
	 * The callback should take two parameters:
	 *  - The first two records on the first call if no $inital_value is specified
	 *  - The initial value and the first record for the first call if an $initial_value is specified
	 *  - The result of the last call plus the next record for the second and subsequent calls
	 * 
	 * @param  callback $callback      The callback to pass the records to - see method description for details
	 * @param  mixed    $inital_value  The initial value to seed reduce with
	 * @return mixed  The result of the reduce operation
	 */
	public function reduce($callback, $inital_value=NULL)
	{
		if (!$this->records) {
			return $initial_value;
		}
		
		$values = $this->records;
		if ($inital_value === NULL) {
			$result = $values[0];
			$values = array_slice($values, 1);
		} else {
			$result = $inital_value;
		}
		
		foreach($values as $value) {
			$result = fCore::call($callback, $result, $value);
		}
		
		return $result;
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
	 * Sorts the set by the return value of a method from the class created and rewind the interator
	 * 
	 * This methods uses {@link fUTF8::inatcmp()} to perform comparisons.
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
			fCore::toss(
				'fProgrammerException',
				fGrammar::compose(
					'The sort direction specified, %1$s, is invalid. Must be one of: %2$s or %3$s.',
					fCore::dump($direction),
					'asc',
					'desc'
				)
			);
		}
		
		// We will create an anonymous function here to handle the sort
		$lambda_params = '$a,$b';
		$lambda_funcs  = array(
			'asc'  => 'return fUTF8::inatcmp($a->' . $method . '(), $b->' . $method . '());',
			'desc' => 'return fUTF8::inatcmp($b->' . $method . '(), $a->' . $method . '());'
		);
		
		$this->sortByCallback(create_function($lambda_params, $lambda_funcs[$direction]));
	}
	
	
	/**
	 * Sorts the set by passing the callback to {@link http://php.net/usort usort()} and rewinds the interator
	 * 
	 * @throws fValidationException
	 * 
	 * @param  mixed $callback  The function/method to pass to usort()
	 * @return void
	 */
	public function sortByCallback($callback)
	{
		usort($this->records, $callback);
		$this->rewind();
	}
	
	
	/**
	 * Throws a {@link fEmptySetException} if the {@link fRecordSet} is empty
	 * 
	 * @throws fEmptySetException
	 * 
	 * @return void
	 */
	public function tossIfEmpty()
	{
		if (!$this->count()) {
			fCore::toss(
				'fEmptySetException',
				fGrammar::compose(
					'No %s could be found',
					fGrammar::pluralize(fORM::getRecordName($this->class))
				)
			);
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
		return $this->pointer < $this->count();
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