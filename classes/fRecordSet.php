<?php
/**
 * A lightweight, iterable set of fActiveRecord-based objects
 * 
 * @copyright  Copyright (c) 2007-2009 Will Bond
 * @author     Will Bond [wb] <will@flourishlib.com>
 * @license    http://flourishlib.com/license
 * 
 * @package    Flourish
 * @link       http://flourishlib.com/fRecordSet
 * 
 * @version    1.0.0b11
 * @changes    1.0.0b11  Added documentation to ::build() about the `NOT LIKE` operator `!~` [wb, 2009-07-08]
 * @changes    1.0.0b10  Moved the private method ::checkConditions() to fActiveRecord::checkConditions() [wb, 2009-07-08]
 * @changes    1.0.0b9   Changed ::build() to only fall back to ordering by primary keys if one exists [wb, 2009-06-26]
 * @changes    1.0.0b8   Updated ::merge() to accept arrays of fActiveRecords or a single fActiveRecord in addition to an fRecordSet [wb, 2009-06-02]
 * @changes    1.0.0b7   Backwards Compatibility Break - Removed ::flagAssociate() and ::isFlaggedForAssociation(), callbacks registered via fORM::registerRecordSetMethod() no longer receive the `$associate` parameter [wb, 2009-06-02]
 * @changes    1.0.0b6   Changed ::tossIfEmpty() to return the record set to allow for method chaining [wb, 2009-05-18]
 * @changes    1.0.0b5   ::build() now allows NULL for `$where_conditions` and `$order_bys`, added a check to the SQL passed to ::buildFromSQL() [wb, 2009-05-03]
 * @changes    1.0.0b4   ::__call() was changed to prevent exceptions coming from fGrammar when an unknown method is called [wb, 2009-03-27]
 * @changes    1.0.0b3   ::sort() and ::sortByCallback() now return the record set to allow for method chaining [wb, 2009-03-23]
 * @changes    1.0.0b2   Added support for != and <> to ::build() and ::filter() [wb, 2008-12-04]
 * @changes    1.0.0b    The initial implementation [wb, 2007-08-04]
 */
class fRecordSet implements Iterator
{
	// The following constants allow for nice looking callbacks to static methods
	const build                  = 'fRecordSet::build';
	const buildFromRecords       = 'fRecordSet::buildFromRecords';
	const buildFromSQL           = 'fRecordSet::buildFromSQL';
	
	
	/**
	 * Creates an fRecordSet by specifying the class to create plus the where conditions and order by rules
	 * 
	 * The where conditions array can contain `key => value` entries in any of
	 * the following formats:
	 * 
	 * {{{
	 * 'column='                    => VALUE,                       // column = VALUE
	 * 'column!'                    => VALUE                        // column <> VALUE
	 * 'column!='                   => VALUE                        // column <> VALUE
	 * 'column<>'                   => VALUE                        // column <> VALUE
	 * 'column~'                    => VALUE                        // column LIKE '%VALUE%'
	 * 'column!~'                   => VALUE                        // column NOT LIKE '%VALUE%'
	 * 'column<'                    => VALUE                        // column < VALUE
	 * 'column<='                   => VALUE                        // column <= VALUE
	 * 'column>'                    => VALUE                        // column > VALUE
	 * 'column>='                   => VALUE                        // column >= VALUE
	 * 'column='                    => array(VALUE, VALUE2, ... )   // column IN (VALUE, VALUE2, ... )
	 * 'column!'                    => array(VALUE, VALUE2, ... )   // column NOT IN (VALUE, VALUE2, ... )
	 * 'column!='                   => array(VALUE, VALUE2, ... )   // column NOT IN (VALUE, VALUE2, ... )
	 * 'column<>'                   => array(VALUE, VALUE2, ... )   // column NOT IN (VALUE, VALUE2, ... )
	 * 'column~'                    => array(VALUE, VALUE2, ... )   // (column LIKE '%VALUE%' OR column LIKE '%VALUE2%' OR column ... )
	 * 'column!~'                   => array(VALUE, VALUE2, ... )   // (column NOT LIKE '%VALUE%' AND column NOT LIKE '%VALUE2%' AND column ... )
	 * 'column!|column2<|column3='  => array(VALUE, VALUE2, VALUE3) // (column <> '%VALUE%' OR column2 < '%VALUE2%' OR column3 = '%VALUE3%')
	 * 'column|column2|column3~'    => VALUE                        // (column LIKE '%VALUE%' OR column2 LIKE '%VALUE%' OR column3 LIKE '%VALUE%')
	 * 'column|column2|column3~'    => array(VALUE, VALUE2, ... )   // ((column LIKE '%VALUE%' OR column2 LIKE '%VALUE%' OR column3 LIKE '%VALUE%') AND (column LIKE '%VALUE2%' OR column2 LIKE '%VALUE2%' OR column3 LIKE '%VALUE2%') AND ... )
	 * }}}
	 * 
	 * When creating a condition in the form `column|column2|column3~`, if the
	 * value for the condition is a single string that contains spaces, the
	 * string will be parsed for search terms. The search term parsing will
	 * handle quoted phrases and normal words and will strip punctuation and
	 * stop words (such as "the" and "a").
	 * 
	 * The order bys array can contain `key => value` entries in any of the
	 * following formats:
	 * 
	 * {{{
	 * 'column'     => 'asc'      // 'first_name' => 'asc'
	 * 'column'     => 'desc'     // 'last_name'  => 'desc'
	 * 'expression' => 'asc'      // "CASE first_name WHEN 'smith' THEN 1 ELSE 2 END" => 'asc'
	 * 'expression' => 'desc'     // "CASE first_name WHEN 'smith' THEN 1 ELSE 2 END" => 'desc'
	 * }}}
	 * 
	 * The column in both the where conditions and order bys can be in any of
	 * the formats:
	 * 
	 * {{{
	 * 'column'                                                         // e.g. 'first_name'
	 * 'current_table.column'                                           // e.g. 'users.first_name'
	 * 'related_table.column'                                           // e.g. 'user_groups.name'
	 * 'related_table{route}.column'                                    // e.g. 'user_groups{user_group_id}.name'
	 * 'related_table=>once_removed_related_table.column'               // e.g. 'user_groups=>permissions.level'
	 * 'related_table{route}=>once_removed_related_table.column'        // e.g. 'user_groups{user_group_id}=>permissions.level'
	 * 'related_table=>once_removed_related_table{route}.column'        // e.g. 'user_groups=>permissions{read}.level'
	 * 'related_table{route}=>once_removed_related_table{route}.column' // e.g. 'user_groups{user_group_id}=>permissions{read}.level'
	 * 'column||other_column'                                           // e.g. 'first_name||last_name' - this concatenates the column values
	 * }}}
	 * 
	 * In addition to using plain column names for where conditions, it is also
	 * possible to pass an aggregate function wrapped around a column in place
	 * of a column name, but only for certain comparison types:
	 * 
	 * {{{
	 * 'function(column)='   => VALUE,                       // function(column) = VALUE
	 * 'function(column)!'   => VALUE                        // function(column) <> VALUE
	 * 'function(column)!=   => VALUE                        // function(column) <> VALUE
	 * 'function(column)<>'  => VALUE                        // function(column) <> VALUE
	 * 'function(column)~'   => VALUE                        // function(column) LIKE '%VALUE%'
	 * 'function(column)!~'  => VALUE                        // function(column) NOT LIKE '%VALUE%'
	 * 'function(column)<'   => VALUE                        // function(column) < VALUE
	 * 'function(column)<='  => VALUE                        // function(column) <= VALUE
	 * 'function(column)>'   => VALUE                        // function(column) > VALUE
	 * 'function(column)>='  => VALUE                        // function(column) >= VALUE
	 * 'function(column)='   => array(VALUE, VALUE2, ... )   // function(column) IN (VALUE, VALUE2, ... )
	 * 'function(column)!'   => array(VALUE, VALUE2, ... )   // function(column) NOT IN (VALUE, VALUE2, ... )
	 * 'function(column)!='  => array(VALUE, VALUE2, ... )   // function(column) NOT IN (VALUE, VALUE2, ... )
	 * 'function(column)<>'  => array(VALUE, VALUE2, ... )   // function(column) NOT IN (VALUE, VALUE2, ... )
	 * }}}
	 * 
	 * The aggregate functions `AVG()`, `COUNT()`, `MAX()`, `MIN()` and
	 * `SUM()` are supported across all database types.
	 * 
	 * Below is an example of using where conditions and order bys. Please note
	 * that values should **not** be escaped for the database, but should just
	 * be normal PHP values.
	 * 
	 * {{{
	 * #!php
	 * return fRecordSet::build(
	 *     'User',
	 *     array(
	 *         'first_name='      => 'John',
	 *         'status!'          => 'Inactive',
	 *         'groups.group_id=' => 2
	 *     ),
	 *     array(
	 *         'last_name'   => 'asc',
	 *         'date_joined' => 'desc'
	 *     )
	 * );
	 * }}}
	 * 
	 * @param  string  $class             The class to create the fRecordSet of
	 * @param  array   $where_conditions  The `column => value` comparisons for the `WHERE` clause
	 * @param  array   $order_bys         The `column => direction` values to use for the `ORDER BY` clause
	 * @param  integer $limit             The number of records to fetch
	 * @param  integer $page              The page offset to use when limiting records
	 * @return fRecordSet  A set of fActiveRecord objects
	 */
	static public function build($class, $where_conditions=array(), $order_bys=array(), $limit=NULL, $page=NULL)
	{
		self::validateClass($class);
		
		// Ensure that the class has been configured
		fActiveRecord::forceConfigure($class);
		
		$table = fORM::tablize($class);
		
		$sql = "SELECT " . $table . ".* FROM :from_clause";
		
		if ($where_conditions) {
			$having_conditions = fORMDatabase::splitHavingConditions($where_conditions);
		
			$sql .= ' WHERE ' . fORMDatabase::createWhereClause($table, $where_conditions);
		}
		
		$sql .= ' :group_by_clause ';
		
		if ($where_conditions && $having_conditions) {
			$sql .= ' HAVING ' . fORMDatabase::createHavingClause($having_conditions);	
		}
		
		if ($order_bys) {
			$sql .= ' ORDER BY ' . fORMDatabase::createOrderByClause($table, $order_bys);
		
		// If no ordering is specified, order by the primary key
		} elseif ($primary_keys = fORMSchema::retrieve()->getKeys($table, 'primary')) {
			$expressions = array();
			foreach ($primary_keys as $primary_key) {
				$expressions[] = $table . '.' . $primary_key . ' ASC';
			}
			$sql .= ' ORDER BY ' . join(', ', $expressions);
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
					throw new fProgrammerException(
						'The page specified, %s, is not a number or less than one',
						$page
					);
				}
				
				$sql .= ' OFFSET ' . (($page-1) * $limit);
			}
		}
		
		return new fRecordSet($class, fORMDatabase::retrieve()->translatedQuery($sql), $non_limited_count_sql);
	}
	
	
	/**
	 * Creates an fRecordSet from an array of records
	 * 
	 * @internal
	 * 
	 * @param  string|array $class    The class or classes of the records
	 * @param  array        $records  The records to create the set from, the order of the record set will be the same as the order of the array.
	 * @return fRecordSet  A set of fActiveRecord objects
	 */
	static public function buildFromRecords($class, $records)
	{
		if (is_array($class)) {
			foreach ($class as $_class) {
				self::validateClass($_class);	
			}
		} else {
			self::validateClass($class);	
		}
		
		$record_set = new fRecordSet($class);
		$record_set->records = $records;
		return $record_set;
	}
	
	
	/**
	 * Creates an fRecordSet from an SQL statement
	 * 
	 * The SQL statement should select all columns from a single table with a *
	 * pattern since that is what an fActiveRecord models. If any columns are
	 * left out or added, strange error may happen when loading or saving
	 * records.
	 * 
	 * Here is an example of an appropriate SQL statement:
	 * 
	 * {{{
	 * #!sql
	 * SELECT users.* FROM users INNER JOIN groups ON users.group_id = groups.group_id WHERE groups.name = 'Public'
	 * }}}
	 * 
	 * Here is an example of a SQL statement that will cause errors:
	 * 
	 * {{{
	 * #!sql
	 * SELECT users.*, groups.name FROM users INNER JOIN groups ON users.group_id = groups.group_id WHERE groups.group_id = 2
	 * }}}
	 * 
	 * The `$non_limited_count_sql` should only be passed when the `$sql`
	 * contains a `LIMIT` clause and should contain a count of the records when
	 * a `LIMIT` is not imposed.
	 * 
	 * Here is an example of a `$sql` statement with a `LIMIT` clause and a
	 * corresponding `$non_limited_count_sql`:
	 * 
	 * {{{
	 * #!php
	 * fRecordSet::buildFromSQL('User', 'SELECT * FROM users LIMIT 5', 'SELECT count(*) FROM users');
	 * }}}
	 * 
	 * The `$non_limited_count_sql` is used when ::count() is called with `TRUE`
	 * passed as the parameter.
	 * 
	 * @param  string $class                  The class to create the fRecordSet of
	 * @param  string $sql                    The SQL to create the set from
	 * @param  string $non_limited_count_sql  An SQL statement to get the total number of rows that would have been returned if a `LIMIT` clause had not been used. Should only be passed if a `LIMIT` clause is used.
	 * @return fRecordSet  A set of fActiveRecord objects
	 */
	static public function buildFromSQL($class, $sql, $non_limited_count_sql=NULL)
	{
		self::validateClass($class);
		
		if (!preg_match('#^\s*SELECT\s*(DISTINCT|ALL)?\s*(\w+\.)?\*\s*FROM#i', $sql)) {
			throw new fProgrammerException(
				'The SQL statement specified, %s, does not appear to be in the form SELECT * FROM table',
				$sql
			);	
		}
		
		return new fRecordSet(
			$class,
			fORMDatabase::retrieve()->translatedQuery($sql),
			$non_limited_count_sql
		);
	}
	
	
	/**
	 * Composes text using fText if loaded
	 * 
	 * @param  string  $message    The message to compose
	 * @param  mixed   $component  A string or number to insert into the message
	 * @param  mixed   ...
	 * @return string  The composed and possible translated message
	 */
	static protected function compose($message)
	{
		$args = array_slice(func_get_args(), 1);
		
		if (class_exists('fText', FALSE)) {
			return call_user_func_array(
				array('fText', 'compose'),
				array($message, $args)
			);
		} else {
			return vsprintf($message, $args);
		}
	}
	
	
	/**
	 * Ensures a class extends fActiveRecord
	 * 
	 * @param  string $class  The class to verify
	 * @return void
	 */
	static private function validateClass($class)
	{
		if (!is_string($class) || !$class || !class_exists($class) || !is_subclass_of($class, 'fActiveRecord')) {
			throw new fProgrammerException(
				'The class specified, %1$s, does not appear to be a valid %2$s class',
				$class,
				'fActiveRecord'
			);	
		}	
	}
	
	
	/**
	 * The type of class to create from the primary keys provided
	 * 
	 * @var string
	 */
	private $class = NULL;
	
	/**
	 * The number of rows that would have been returned if a `LIMIT` clause had not been used
	 * 
	 * @var integer
	 */
	private $non_limited_count = NULL;
	
	/**
	 * The SQL to get the total number of rows that would have been returned if a `LIMIT` clause had not been used
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
	 * Allows for preloading various data related to the record set in single database queries, as opposed to one query per record
	 * 
	 * This method will handle methods in the format `verbRelatedRecords()` for
	 * the verbs `prebuild`, `precount` and `precreate`.
	 * 
	 * `prebuild` builds *-to-many record sets for all records in the record
	 * set. `precount` will count records in *-to-many record sets for every
	 * record in the record set. `precreate` will create a *-to-one record
	 * for every record in the record set.
	 *  
	 * @param  string $method_name  The name of the method called
	 * @param  string $parameters   The parameters passed
	 * @return void
	 */
	public function __call($method_name, $parameters)
	{
		if ($callback = fORM::getRecordSetMethod($method_name)) {
			return call_user_func_array(
				$callback,
				array(
					$this,
					$this->class,
					&$this->records,
					&$this->pointer
				)
			);	
		}
		
		list($action, $element) = fORM::parseMethod($method_name);
		
		$route = ($parameters) ? $parameters[0] : NULL;
		
		// This check prevents fGrammar exceptions being thrown when an unknown method is called
		if (in_array($action, array('prebuild', 'precount', 'precreate'))) {
			$related_class = fGrammar::singularize(fGrammar::camelize($element, TRUE));
		}
		 
		switch ($action) {
			case 'prebuild':
				return $this->prebuild($related_class, $route);
			
			case 'precount':
				return $this->precount($related_class, $route);
				
			case 'precreate':
				return $this->precreate($related_class, $route);
		}
		 
		throw new fProgrammerException(
			'Unknown method, %s(), called',
			$method_name
		);
	}
	 
	 
	/** 
	 * Sets the contents of the set
	 * 
	 * @param  string  $class                  The type of records to create
	 * @param  fResult $result_object          The fResult object of the records to create
	 * @param  string  $non_limited_count_sql  An SQL statement to get the total number of rows that would have been returned if a `LIMIT` clause had not been used. Should only be passed if a `LIMIT` clause is used.
	 * @return fRecordSet
	 */
	protected function __construct($class, fResult $result_object=NULL, $non_limited_count_sql=NULL)
	{
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
	 * @internal
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
	 * @param  string $method     The method to call
	 * @param  mixed  $parameter  A parameter to pass for each call to the method
	 * @param  mixed  ...
	 * @return array  An array the size of the record set with one result from each record/method
	 */
	public function call($method)
	{
		$parameters = array_slice(func_get_args(), 1);
		
		$output = array();
		foreach ($this->records as $record) {
			$output[] = call_user_func_array(
				$record->$method,
				$parameters
			);
		}
		return $output;
	}
	
	
	/**
	 * Creates an `ORDER BY` clause for the primary keys of this record set
	 * 
	 * @param  string $route  The route to this table from another table
	 * @return string  The `ORDER BY` clause
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
	 * Creates a `WHERE` clause for the primary keys of this record set
	 * 
	 * @param  string $route  The route to this table from another table
	 * @return string  The `WHERE` clause
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
	 * @param  boolean $ignore_limit  If set to `TRUE`, this method will return the number of records that would be in the set if there was no `LIMIT` clause
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
	 * @throws fNoRemainingException  When there are no remaining records in the set
	 * @internal
	 * 
	 * @return fActiveRecord  The current record
	 */
	public function current()
	{
		if (!$this->valid()) {
			throw new fNoRemainingException(
				'There are no remaining records'
			);
		}
		
		return $this->records[$this->pointer];
	}
	
	
	/**
	 * Filters the records in the record set via a callback
	 * 
	 * The `$callback` parameter can be one of three different forms to filter
	 * the records in the set:
	 * 
	 *  - A callback that accepts a single record and returns `FALSE` if it should be removed
	 *  - A psuedo-callback in the form `'{record}::methodName'` to filter out any records where the output of `$record->methodName()` is equivalent to `FALSE`
	 *  - A conditions array that will remove any records that don't meet all of the conditions
	 * 
	 * The conditions array can use one or more of the following `key => value`
	 * syntaxes to perform various comparisons. The array keys are method
	 * names followed by a comparison operator.
	 * 
	 * {{{
	 * // The following forms work for any $value that is not an array
	 * 'methodName='                         => $value  // If the output is equal to $value
	 * 'methodName!'                         => $value  // If the output is not equal to $value
	 * 'methodName!='                        => $value  // If the output is not equal to $value
	 * 'methodName<>'                        => $value  // If the output is not equal to $value
	 * 'methodName<'                         => $value  // If the output is less than $value
	 * 'methodName<='                        => $value  // If the output is less than or equal to $value
	 * 'methodName>'                         => $value  // If the output is greater than $value
	 * 'methodName>='                        => $value  // If the output is greater than or equal to $value
	 * 'methodName~'                         => $value  // If the output contains the $value (case insensitive)
	 * 'methodName|methodName2|methodName3~' => $value  // Parses $value as a search string and make sure each term is present in at least one output (case insensitive)
	 * 
	 * // The following forms work for any $array that is an array
	 * 'methodName='                         => $array  // If the output is equal to at least one value in $array
	 * 'methodName!'                         => $array  // If the output is not equal to any value in $array
	 * 'methodName!='                        => $array  // If the output is not equal to any value in $array
	 * 'methodName<>'                        => $array  // If the output is not equal to any value in $array
	 * 'methodName~'                         => $array  // If the output contains one of the strings in $array (case insensitive)
	 * 'methodName|methodName2|methodName3~' => $array  // If each value in the array is present in the output of at least one method (case insensitive)
	 * }}} 
	 * 
	 * @param  callback|string|array $procedure  The way in which to filter the records - see method description for possible forms
	 * @return fRecordSet  A new fRecordSet with the filtered records
	 */
	public function filter($procedure)
	{
		if (!$this->records) {
			return clone $this;
		}
		
		if (is_array($procedure) && is_string(key($procedure))) {
			$type       = 'conditions';
			$conditions = $procedure;
			
		} elseif (is_string($procedure) && preg_match('#^\{record\}::([a-z0-9_\-]+)$#iD', $procedure, $matches)) {
			$type   = 'psuedo-callback';
			$method = $matches[1];
			
		} else {
			$type     = 'callback';
			$callback = $procedure;
			if (is_string($callback) && strpos($callback, '::') !== FALSE) {
				$callback = explode('::', $callback);	
			}
		}
			
		$new_records = array();
		$classes     = (!is_array($this->class)) ? array($this->class) : array();
		
		foreach ($this->records as $record) {
			switch ($type) {
				case 'conditions':
					$value = fActiveRecord::checkConditions($record, $conditions);
					break;
					
				case 'psuedo-callback':
					$value = $record->$method();
					break;
					
				case 'callback':
					$value = call_user_func($callback, $record);
					break;
			}
			
			if ($value) {
				// If we are filtering a multi-class set, only grab classes for records that are being copied
				if (is_array($this->class) && !in_array(get_class($record), $classes)) {
					$classes[] = get_class($record); 		
				}
				
				$new_records[] = $record;
			}
		}
		
		if (sizeof($classes) == 1) {
			$classes = $classes[0];	
		}
		
		return self::buildFromRecords($classes, $new_records);
	}
	
	
	/**
	 * Returns the current record in the set and moves the pointer to the next
	 * 
	 * @throws fNoRemainingException  When there are no remaining records in the set
	 * 
	 * @return fActiveRecord  The current record
	 */
	public function fetchRecord()
	{
		$record = $this->current();
		$this->next();
		return $record;
	}
	
	
	/**
	 * Returns the class name of the record being stored
	 * 
	 * @return string|array  The class name(s) of the records in the set
	 */
	public function getClass()
	{
		return $this->class;
	}
	
	
	/**
	 * Returns all of the records in the set
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
		$this->validateSingleClass('get primary key');
		
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
	 * Performs an [http://php.net/array_map array_map()] on the record in the set
	 * 
	 * The record will be passed to the callback as the first parameter unless
	 * it's position is specified by the placeholder string `'{record}'`.
	 * 
	 * Additional parameters can be passed to the callback in one of two
	 * different ways:
	 * 
	 *  - Passing a non-array value will cause it to be passed to the callback
	 *  - Passing an array value will cause the array values to be passed to the callback with their corresponding record
	 *  
	 * If an array parameter is too long (more items than records in the set)
	 * it will be truncated. If an array parameter is too short (less items
	 * than records in the set) it will be padded with `NULL` values.
	 * 
	 * To allow passing the record as a specific parameter to the callback, a
	 * placeholder string `'{record}'` will be replaced with a the record. It
	 * is also possible to specify `'{record}::methodName'` to cause the output
	 * of a method from the record to be passed instead of the whole record.
	 * 
	 * It is also possible to pass the zero-based record index to the callback
	 * by passing a parameter that contains `'{index}'`.
	 * 
	 * @param  callback $callback   The callback to pass the values to
	 * @param  mixed    $parameter  The parameter to pass to the callback - see method description for details
	 * @param  mixed    ...
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
				if (preg_match('#^\{record\}::([a-z0-9_\-]+)$#iD', $parameter, $matches)) {
					$parameters_array[] = $this->call($matches[1]);
					$found_record = TRUE;
				} elseif ($parameter === '{record}') {
					$parameters_array[] = $this->records;
					$found_record = TRUE;
				} elseif ($parameter === '{index}') {
					$parameters_array[] = array_keys($this->records);
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
	 * Merges the record set with more records
	 * 
	 * @param  fRecordSet|array|fActiveRecord $records  The record set, array of records, or record to merge with the current record set, duplicates will **not** be removed
	 * @return fRecordSet  The merged record sets
	 */
	public function merge($records)
	{
		if ($records instanceof fRecordSet) {
			$new_records = $records->records;
			$new_class   = $records->class;	
		
		} elseif (is_array($records)) {
			$new_records = array();
			$new_class   = array();
			foreach ($records as $record) {
				if (!$record instanceof fActiveRecord) {
					throw new fProgrammerException(
						'One of the records specified is not an instance of %s',
						'fActiveRecord'
					);	
				}
				$new_records[] = $record;
				if (!in_array(get_class($record), $new_class)) {
					$new_class[] = get_class($record);
				}	
			}
			if (sizeof($new_class) == 1) {
				$new_class = $new_class[0];
			}	
		
		} elseif ($records instanceof fActiveRecord) {
			$new_records = array($records);
			$new_class   = get_class($records);
			
		} else {
			throw new fProgrammerException(
				'The records specified, %1$s, are invalid. Must be an %2$s, %3$s or an array of %4$s.',
				$records,
				'fRecordSet',
				'fActiveRecord',
				'fActiveRecords'
			);	
		}
		
		if (!$new_records) {
			return $this;	
		}
		
		if ($this->class != $new_class) {
			$class = array_unique(array_merge(
				(is_array($this->class)) ? $this->class : array($this->class),
				(is_array($new_class))   ? $new_class   : array($new_class)	
			));
		} else {
			$class = $this->class;	
		}
		
		return self::buildFromRecords(
			$class,
			array_merge(
				$this->records,
				$new_records
			)
		);
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
		$this->validateSingleClass('prebuild');
		
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
		$this->validateSingleClass('precount');
		
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
		$this->validateSingleClass('precreate');
		
		// If there are no primary keys we can just exit
		if (!array_merge($this->getPrimaryKeys())) {
			return;
		}
		
		$relationship = fORMSchema::getRoute(
			fORM::tablize($this->class),
			fORM::tablize($related_class),
			$route,
			'*-to-one'
		);
		
		self::build(
			$related_class,
			array(
				$relationship['related_column'] . '=' => $this->call(
					'get' . fGrammar::camelize($relationship['column'], TRUE)
				)
			)
		);
	}
	
	
	/**
	 * Reduces the record set to a single value via a callback
	 * 
	 * The callback should take two parameters and return a single value:
	 * 
	 *  - The initial value and the first record for the first call
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
		
		$result = $inital_value;
		
		if (is_string($callback) && strpos($callback, '::') !== FALSE) {
			$callback = explode('::', $callback);	
		}
		
		foreach($this->records as $record) {
			$result = call_user_func($callback, $result, $record);
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
	 * Slices a section of records from the set and returns a new set containing those
	 * 
	 * @param  integer $offset  The index to start at, negative indexes will slice that many records from the end
	 * @param  integer $length  The number of records to return, negative values will stop that many records before the end, `NULL` will return all records to the end of the set - if there are not enough records, less than `$length` will be returned
	 * @return fRecordSet  The new slice of records
	 */
	public function slice($offset, $length=NULL)
	{
		if ($length === NULL) {
			if ($offset >= 0) {
				$length = sizeof($this->records) - $offset;	
			} else {
				$length = abs($offset);	
			}
		}
		return self::buildFromRecords($this->class, array_slice($this->records, $offset, $length));
	}
	
	
	/**
	 * Sorts the set by the return value of a method from the class created and rewind the interator
	 * 
	 * This methods uses fUTF8::inatcmp() to perform comparisons.
	 * 
	 * @param  string $method     The method to call on each object to get the value to sort by
	 * @param  string $direction  Either `'asc'` or `'desc'`
	 * @return fRecordSet  The record set object, to allow for method chaining
	 */
	public function sort($method, $direction)
	{
		if (!in_array($direction, array('asc', 'desc'))) {
			throw new fProgrammerException(
				'The sort direction specified, %1$s, is invalid. Must be one of: %2$s or %3$s.',
				$direction,
				'asc',
				'desc'
			);
		}
		
		// We will create an anonymous function here to handle the sort
		$lambda_params = '$a,$b';
		$lambda_funcs  = array(
			'asc'  => 'return fUTF8::inatcmp($a->' . $method . '(), $b->' . $method . '());',
			'desc' => 'return fUTF8::inatcmp($b->' . $method . '(), $a->' . $method . '());'
		);
		
		$this->sortByCallback(create_function($lambda_params, $lambda_funcs[$direction]));
		
		return $this;
	}
	
	
	/**
	 * Sorts the set by passing the callback to [http://php.net/usort `usort()`] and rewinds the interator
	 * 
	 * @param  mixed $callback  The function/method to pass to `usort()`
	 * @return fRecordSet  The record set object, to allow for method chaining 
	 */
	public function sortByCallback($callback)
	{
		usort($this->records, $callback);
		$this->rewind();
		
		return $this;
	}
	
	
	/**
	 * Throws an fEmptySetException if the record set is empty
	 * 
	 * @throws fEmptySetException  When there are no record in the set
	 * 
	 * @param  string $message  The message to use for the exception if there are no records in this set
	 * @return fRecordSet  The record set object, to allow for method chaining
	 */
	public function tossIfEmpty($message=NULL)
	{
		if ($this->count()) {
			return $this;	
		}
		
		if ($message === NULL) {
			if (is_array($this->class)) {
				$names = array_map(array('fORM', 'getRecordName'), $this->class);
				$names = array_map(array('fGrammar', 'pluralize'), $names);
				$name  = join(', ', $names);	
			} else {
				$name = fGrammar::pluralize(fORM::getRecordName($this->class));
			}
			
			$message = self::compose(
				'No %s could be found',
				$name
			);	
		}
		
		throw new fEmptySetException($message);
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
	
	
	/**
	 * Ensures the record set only contains a single kind of record to prevent issues with certain operations
	 * 
	 * @param  string $operation  The operation being performed - used in the exception thrown
	 * @return void
	 */
	private function validateSingleClass($operation)
	{
		if (!is_array($this->class)) {
			return;
		}			
		
		throw new fProgrammerException(
			'The %1$s operation can not be performed on a record set with multiple types (%2$s) of records',
			$operation,
			join(', ', $this->class)	
		);
	}
}



/**
 * Copyright (c) 2007-2009 Will Bond <will@flourishlib.com>
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