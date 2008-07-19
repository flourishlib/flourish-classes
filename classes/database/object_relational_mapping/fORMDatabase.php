<?php
/**
 * Performs database manipulations for ORM-related code
 * 
 * @copyright  Copyright (c) 2007-2008 William Bond
 * @author     William Bond [wb] <will@flourishlib.com>
 * @license    http://flourishlib.com/license
 * 
 * @package    Flourish
 * @link       http://flourishlib.com/fORMDatabase
 * 
 * @version    1.0.0b
 * @changes    1.0.0b  The initial implementation [wb, 2007-08-04]
 */
class fORMDatabase
{
	/**
	 * The instance of fDatabase
	 * 
	 * @var fDatabase
	 */
	static private $database_object;
	
	
	/**
	 * Adds a join to an existing array of joins
	 * 
	 * @internal
	 * 
	 * @param  array  $joins         The existing joins
	 * @param  string $table         The table the relationship extends from
	 * @param  string $route         The route to the related table
	 * @param  array  $relationship  The relationship info for the route specified
	 * @return array  The joins array with the new join added
	 */
	static public function addJoin($joins, $table, $route, $relationship)
	{
		$related_table = $relationship['related_table'];
		
		if (isset($joins[$table . '_' . $related_table . '{' . $route . '}'])) {
			return $joins;
		}
		
		$table_alias = self::findTableAlias($table, $joins);
		
		if (!$table_alias) {
			fCore::toss(
				'fProgrammerException',
				fCore::compsoe(
					'The table %s has not been joined to yet, so it can not be joined from',
					fCore::dump($table)
				)
			);
		}
		
		$aliases = array();
		foreach ($joins as $join) {
			$aliases[] = $join['table_alias'];
		}
		
		self::createJoin($table, $table_alias, $related_table, $route, $joins, $aliases);
		
		return $joins;
	}
	
	
	/**
	 * Prepends the table_name. to the keys of the array
	 * 
	 * @internal
	 * 
	 * @param  string $table  The table to prepend
	 * @param  array  $array  The array to modify
	 * @return array  The modified array
	 */
	static public function addTableToKeys($table, $array)
	{
		$modified_array = array();
		foreach ($array as $key => $value) {
			if (preg_match('#^\w+$#', $key)) {
				$modified_array[$table . '.' . $key] = $value;
			} else {
				$modified_array[$key] = $value;
			}
		}
		return $modified_array;
	}
	
	
	/**
	 * Prepends the table_name. to the values of the array
	 * 
	 * @internal
	 * 
	 * @param  string $table  The table to prepend
	 * @param  array  $array  The array to modify
	 * @return array  The modified array
	 */
	static public function addTableToValues($table, $array)
	{
		$modified_array = array();
		foreach ($array as $key => $value) {
			if (preg_match('#^\w+$#', $value)) {
				$modified_array[$key] = $table . '.' . $value;
			} else {
				$modified_array[$key] = $value;
			}
		}
		return $modified_array;
	}
	
	
	/**
	 * Allows attaching a class that is or extends {@link fDatabase} instead of just using the provided implementation
	 * 
	 * @param  fDatabase $database  An object that is or extends the fDatabase class
	 * @return void
	 */
	static public function attach(fDatabase $database)
	{
		self::$database_object = $database;
	}
	
	
	/**
	 * Turns a single field primary key list from an array into a string
	 * 
	 * @internal
	 * 
	 * @param  array $rows  The rows of primary keys
	 * @return array  A possibly condensed array of primary keys
	 */
	static public function condensePrimaryKeyArray($rows)
	{
		if (empty($rows)) {
			return $rows;
		}
		
		$test_row = $rows[0];
		if (sizeof($test_row) == 1) {
			$new_rows = array();
			$row_keys = array_keys($test_row);
			foreach ($rows as $row) {
				$new_rows[] = $row[$row_keys[0]];
			}
			$rows = $new_rows;
		}
		
		return $rows;
	}
	
	
	/**
	 * Creates a FROM clause from a join array
	 * 
	 * @internal
	 * 
	 * @param  array $joins  The joins to create the FROM clause out of
	 * @return string  The from clause (does not include the word 'FROM')
	 */
	static public function createFromClauseFromJoins($joins)
	{
		$sql = '';
		
		foreach ($joins as $join) {
			// Here we handle the first table in a join
			if ($join['join_type'] == 'none') {
				$sql .= $join['table_name'];
				if ($join['table_alias'] != $join['table_name']) {
					$sql .= ' AS ' . $join['table_alias'];
				}
			
			// Here we handle all other joins
			} else {
				$sql .= ' ' . strtoupper($join['join_type']) . ' ' . $join['table_name'];
				if ($join['table_alias'] != $join['table_name']) {
					$sql .= ' AS ' . $join['table_alias'];
				}
				if (isset($join['on_clause_type'])) {
					if ($join['on_clause_type'] == 'simple_equation') {
						$sql .= ' ON ' . $join['on_clause_fields'][0] . ' = ' . $join['on_clause_fields'][1];
						
					} else {
						$sql .= ' ON ' . $join['on_clause'];
					}
				}
			}
		}
		
		return $sql;
	}
	
	
	/**
	 * Creates join information for the table shortcut provided
	 * 
	 * @internal
	 * 
	 * @param  string $table          The primary table
	 * @param  string $table_alias    The primary table alias
	 * @param  string $related_table  The related table
	 * @param  string $route          The route to the related table
	 * @param  array  &$joins         The names of the joins that have been created
	 * @param  array  &$used_aliases  The aliases that have been used
	 * @return string  The name of the significant join created
	 */
	static private function createJoin($table, $table_alias, $related_table, $route, &$joins, &$used_aliases)
	{
		$routes = fORMSchema::getRoutes($table, $related_table);
						
		if (!isset($routes[$route])) {
			fCore::toss(
				'fProgrammerException',
				fGrammar::compose(
					'An invalid route, %s, was specified for the relationship from %s to %s',
					fCore::dump($route),
					fCore::dump($table),
					fCore::dump($related_table)
				)
			);
		}
		
		if (isset($joins[$table . '_' . $related_table . '{' . $route . '}'])) {
			return  $table . '_' . $related_table . '{' . $route . '}';
		}
		
		// If the route uses a join table
		if (isset($routes[$route]['join_table'])) {
			$join = array(
				'join_type' => 'INNER JOIN',
				'table_name' => $routes[$route]['join_table'],
				'table_alias' => self::createNewAlias($routes[$route]['join_table'], $used_aliases), // Fix this
				'on_clause_type' => 'simple_equation',
				'on_clause_fields' => array()
			);
			
			$join['on_clause_fields'][] = $table_alias . '.' . $routes[$route]['column'];
			$join['on_clause_fields'][] = $join['table_alias'] . '.' . $routes[$route]['join_column'];
			
			$join2 = array(
				'join_type' => 'INNER JOIN',
				'table_name' => $related_table,
				'table_alias' => self::createNewAlias($related_table, $used_aliases),
				'on_clause_type' => 'simple_equation',
				'on_clause_fields' => array()
			);
			
			$join2['on_clause_fields'][] = $join['table_alias'] . '.' . $routes[$route]['join_related_column'];
			$join2['on_clause_fields'][] = $join2['table_alias'] . '.' . $routes[$route]['related_column'];
			
			$joins[$table . '_' . $related_table . '{' . $route . '}_join'] = $join;
			$joins[$table . '_' . $related_table . '{' . $route . '}'] = $join2;
				
		// If the route is a direct join
		} else {
			
			$join = array(
				'join_type' => 'INNER JOIN',
				'table_name' => $related_table,
				'table_alias' => self::createNewAlias($related_table, $used_aliases), // Fix this
				'on_clause_type' => 'simple_equation',
				'on_clause_fields' => array()
			);
			
			$join['on_clause_fields'][] = $table_alias . '.' . $routes[$route]['column'];
			$join['on_clause_fields'][] = $join['table_alias'] . '.' . $routes[$route]['related_column'];
		
			$joins[$table . '_' . $related_table . '{' . $route . '}'] = $join;
		
		}
		
		return $table . '_' . $related_table . '{' . $route . '}';
	}
	
	
	/**
	 * Creates a new table alias
	 * 
	 * @internal
	 * 
	 * @param  string $table          The table to create an alias for
	 * @param  array  &$used_aliases  The aliases that have been used
	 * @return string  The alias to use for the table
	 */
	static private function createNewAlias($table, &$used_aliases)
	{
		if (!in_array($table, $used_aliases)) {
			$used_aliases[] = $table;
			return $table;
		}
		$i = 1;
		while(in_array($table . $i, $used_aliases)) {
			$i++;
		}
		$used_aliases[] = $table . $i;
		return $table . $i;
	}
	
	
	/**
	 * Creates an order by clause from an array of columns/expressions and directions
	 * 
	 * @internal
	 * 
	 * @param  string $table      The table any ambigious column references will refer to
	 * @param  array  $order_bys  The array of order bys to use (see {@link fRecordSet::create()} for format)
	 * @return string  The SQL ORDER BY clause
	 */
	static public function createOrderByClause($table, $order_bys)
	{
		$order_bys = self::addTableToKeys($table, $order_bys);
		$sql = array();
		
		foreach ($order_bys as $column => $direction) {
			if (!fCore::stringlike($column)) {
				fCore::toss(
					'fProgrammerException',
					fGrammar::compose(
						'An invalid sort column, %s, was specified',
						fCore::dump($column)
					)
				); 		
			}
			
			$direction = strtoupper($direction);
			if (!in_array($direction, array('ASC', 'DESC'))) {
				fCore::toss(
					'fProgrammerException',
					fGrammar::compose(
						'An invalid direction, %s, was specified',
						fCore::dump($direction)
					)
				);
			}
			
			if (preg_match('#^(?:\w+(?:\{\w+\})?=>)?(\w+)(?:\{\w+\})?\.(\w+)$#', $column, $matches)) {
				$column_type = fORMSchema::getInstance()->getColumnInfo($matches[1], $matches[2], 'type');
				if (in_array($column_type, array('varchar', 'char', 'text'))) {
					$sql[] = 'LOWER(' . $column . ') ' . $direction;
				} else {
					$sql[] = $column . ' ' . $direction;
				}
			} else {
				$sql[] = $column . ' ' . $direction;
			}
		}
		
		return join(', ', $sql);
	}
	
	
	/**
	 * Creates a where clause condition for primary keys of the table specified
	 * 
	 * This method requires the $primary_keys parameter to be one of:
	 * 
	 *  - A scalar value for a single-column primary key
	 *  - An array of values for a single-column primary key
	 *  - An associative array of values for a multi-column primary key (column => value)
	 *  - An array of associative arrays of values for a multi-column primary key (key => array(column => value)
	 * 
	 * If you are looking to build a primary key where clause from the $values
	 * and $old_values arrays, please see {@link createPrimaryKeyWhereClause()}
	 * 
	 * @throws fValidationException
	 * @internal
	 * 
	 * @param  string $table         The table to build the where clause for
	 * @param  string $table_alias   The alias for the table
	 * @param  array  &$values       The values array for the {@link fActiveRecord} object
	 * @param  array  &$old_values   The values array for the {@link fActiveRecord} object
	 * @return string  The WHERE clause that will specify the {@link fActiveRecord} as it currently exists in the database
	 */
	static public function createPrimaryKeyWhereClause($table, $table_alias, &$values, &$old_values)
	{
		$primary_keys = fORMSchema::getInstance()->getKeys($table, 'primary');
		
		$sql = '';
		foreach ($primary_keys as $primary_key) {
			if ($sql) { $sql .= " AND "; }
			
			$value = (!empty($old_values[$primary_key])) ? $old_values[$primary_key][0] : $values[$primary_key];
			
			$sql  .= $table . '.' . $primary_key . fORMDatabase::prepareBySchema($table, $primary_key, $value, '=');
		}
		
		return $sql;			
	}
	
	
	/**
	 * Creates a where clause from an array of conditions
	 * 
	 * @internal
	 * 
	 * @param  string $table       The table any ambigious column references will refer to
	 * @param  array  $conditions  The array of conditions  (see {@link fRecordSet::create()} for format)
	 * @return string  The SQL WHERE clause
	 */
	static public function createWhereClause($table, $conditions)
	{
		$sql = array();
		foreach ($conditions as $column => $values) {
			$type   = substr($column, -1);
			$column = substr($column, 0, -1);
			settype($values, 'array');
			if (!$values) { $values = array(NULL); }
			
			
			// Multi-column condition
			if (strpos($column, '|') !== FALSE) {
				if ($type != '~') {
					fCore::toss(
						'fProgrammerException',
						fGrammar::compose(
							'An invalid matching type, %s, was specified',
							fCore::dump($type)
						)
					);
				}
				$columns = self::addTableToValues($table, explode('|', $column));
				
				$condition = array();
				foreach ($values as $value) {
					$sub_condition = array();
					foreach ($columns as $column) {
						$sub_condition[] = $column . ' LIKE ' . self::getInstance()->escapeString('%' . $value . '%');
					}
					$condition[] = '(' . join(' OR ', $sub_condition) . ')';
				}
				$sql[] = ' (' . join(' AND ', $condition) . ') ';
				
				
			// Single column condition
			} else {
				
				$columns = self::addTableToValues($table, array($column));
				$column  = $columns[0];
				
				// More than one value
				if (sizeof($values) > 1) {
					switch ($type) {
						case '=':
							$condition = array();
							foreach ($values as $value) {
								$condition[] = self::prepareByType($value);
							}
							$sql[] = $column . ' IN (' . join(', ', $condition) . ')';
							break;
						case '!':
							$condition = array();
							foreach ($values as $value) {
								$condition[] = self::prepareByType($value);
							}
							$sql[] = $column . ' NOT IN (' . join(', ', $condition) . ')';
							break;
						case '~':
							$condition = array();
							foreach ($values as $value) {
								$condition[] = $column . ' LIKE ' . self::getInstance()->escapeString('%' . $value . '%');
							}
							$sql[] = '(' . join(' OR ', $condition) . ')';
							break;
						default:
							fCore::toss(
								'fProgrammerException',
								fGrammar::compose(
									'An invalid matching type, %s, was specified',
									fCore::dump($type)
								)
							);
							break;
					}
					
				// A single value
				} else {
					switch ($type) {
						case '=':
							$sql[] = $column . self::prepareByType($values[0], '=');
							break;
						case '!':
							if ($values[0] !== NULL) {
								$sql[] = '(' . $column . self::prepareByType($values[0], '<>') . ' OR ' . $column . ' IS NULL)';
							} else {
								$sql[] = $column . self::prepareByType($values[0], '<>');
							}
							break;
						case '~':
							$sql[] = $column . ' LIKE ' . self::getInstance()->escapeString('%' . $values[0] . '%');
							break;
						default:
							fCore::toss(
								'fProgrammerException',
								fGrammar::compose(
									'An invalid matching type, %s, was specified',
									fCore::dump($type)
								)
							);
							break;
					}
				}
			}
		}
		
		return join(' AND ', $sql);
	}
	
	
	/**
	 * Finds the first table alias for the table specified in the list of joins provided
	 * 
	 * @internal
	 * 
	 * @param  string $table  The table to find the alias for
	 * @param  array  $joins  The joins to look through
	 * @return string  The alias to use for the table
	 */
	static public function findTableAlias($table, $joins)
	{
		foreach ($joins as $join) {
			if ($join['table_name'] == $table) {
				return $join['table_alias'];
			}
		}
		return NULL;
	}
	
	
	/**
	 * Return the instance of the {@link fDatabase} class
	 * 
	 * @return fDatabase  The database instance
	 */
	static public function getInstance()
	{
		if (!self::$database_object) {
			fCore::toss(
				'fProgrammerException',
				fGrammar::compose(
					'The method %s needs to be called before %s',
					'initialize()',
					'getInstance()'
				)
			);
		}
		return self::$database_object;
	}
	
	
	/**
	 * Initializes a singleton instance of the {@link fDatabase} class
	 * 
	 * @param  string  $type      The type of the database: 'mssql', 'mysql', 'postgresql', 'sqlite'
	 * @param  string  $database  Name of the database. If an ODBC connection 'dsn:' concatenated with the DSN, if SQLite the path to the database file.
	 * @param  string  $username  Database username, required for all databases except SQLite
	 * @param  string  $password  The password for the username specified
	 * @param  string  $host      Database server host or ip, defaults to localhost for all databases except SQLite
	 * @param  integer $port      The port to connect to, defaults to the standard port for the database type specified
	 * @return void
	 */
	static public function initialize($type, $database, $username=NULL, $password=NULL, $host=NULL, $port=NULL)
	{
		self::$database_object = new fDatabase($type, $database, $username, $password, $host, $port);
	}
	
	
	/**
	 * Finds all of the table names in the SQL and creates a from clause
	 * 
	 * @internal
	 * 
	 * @param  string $table  The main table to be queried
	 * @param  string $sql    The SQL to insert the from clause into
	 * @param  array $joins   Optional: The existing joins that have been parsed
	 * @return string  The from SQL clause
	 */
	static public function insertFromAndGroupByClauses($table, $sql, $joins=array())
	{
		if (strpos($sql, ':from_clause') === FALSE) {
			fCore::toss(
				'fProgrammerException',
				fGrammar::compose(
					"No %s placeholder was found in:\n%s",
					'getInstance()',
					$sql
				)
			);
		}
		
		// Separate the SQL from quoted values
		preg_match_all("#(?:'(?:''|\\\\'|\\\\[^']|[^'\\\\])*')|(?:[^']+)#", $sql, $matches);
		
		$table_alias = $table;
		
		$used_aliases  = array();
		$table_map     = array();
		
		// If we are not passing in existing joins, start with the specified table
		if (!$joins) {
			$joins[] = array(
				'join_type'   => 'none',
				'table_name'  => $table,
				'table_alias' => $table_alias
			);
		}
		
		foreach ($matches[0] as $match) {
			if ($match[0] != "'") {
				preg_match_all('#\b((?:(\w+)(?:\{(\w+)\})?=>)?(\w+)(?:\{(\w+)\})?)\.\w+\b#m', $match, $table_matches, PREG_SET_ORDER);
				foreach ($table_matches as $table_match) {
					
					if (!isset($table_match[5])) {
						$table_match[5] = NULL;
					}
					
					// This is a related table that is going to join to a once-removed table
					if (!empty($table_match[2])) {
						
						$related_table = $table_match[2];
						$route = fORMSchema::getRouteName($table, $related_table, $table_match[3]);
						
						$join_name = $table . '_' . $related_table . '{' . $route . '}';
						
						self::createJoin($table, $table_alias, $related_table, $route, $joins, $used_aliases);
						
						$once_removed_table = $table_match[4];
						$route = fORMSchema::getRouteName($related_table, $once_removed_table, $table_match[5]);
						
						$join_name = self::createJoin($related_table, $joins[$join_name]['table_alias'], $once_removed_table, $route, $joins, $used_aliases);
						
						$table_map[$table_match[1]] = $joins[$join_name]['table_alias'];
					
					// This is a related table
					} elseif ($table_match[4] != $table) {
					
						$related_table = $table_match[4];
						$route = fORMSchema::getRouteName($table, $related_table, $table_match[5]);
						
						$join_name = self::createJoin($table, $table_alias, $related_table, $route, $joins, $used_aliases);
						
						$table_map[$table_match[1]] = $joins[$join_name]['table_alias'];
					}
				}
			}
		}
		
		// Determine if we joined a *-to-many relationship
		$joined_to_many = FALSE;
		foreach ($joins as $name => $join) {
			if (is_numeric($name)) {
				continue;	
			}
			
			if (substr($name, -5) == '_join') {
				$joined_to_many = TRUE;
				break;	
			}
			
			$main_table   = preg_replace('#_' . $join['table_name'] . '{\w+}$#i', '', $name);
			$second_table = $join['table_name'];
			$route        = preg_replace('#^[^{]+{(\w+)}$#', '\1', $name);
			$routes       = fORMSchema::getRoutes($main_table, $second_table, '*-to-many');
			if (isset($routes[$route])) {
				$joined_to_many = TRUE;
				break;
			}	
		}
		$found_order_by = FALSE;
		
		$from_clause     = self::createFromClauseFromJoins($joins);
		
		// If we are joining on a *-to-many relationship we need to group by the
		// columns in the main table to prevent duplicate entries
		if ($joined_to_many) {
			$column_info     = fORMSchema::getInstance()->getColumnInfo($table);
			$group_by_clause = ' GROUP BY ';
			$columns         = array();
			foreach ($column_info as $column => $info) {
				$columns[] = $table . '.' . $column;
			}
			$group_by_clause .= join(', ', $columns) . ' ';
		} else {
			$group_by_clause = ' ';	
		}
		
		// Put the SQL back together
		$new_sql = '';
		foreach ($matches[0] as $match) {
			$temp_sql = $match;
			
			// Get rid of the => notation and the :from_clause placeholder
			if ($match[0] !== "'") {
				foreach ($table_map as $arrow_table => $alias) {
					$temp_sql = str_replace($arrow_table, $alias, $temp_sql);
				}
				
				// In the ORDER BY clause we need to wrap columns in
				if ($found_order_by && $joined_to_many) {
					$temp_sql = preg_replace('#(?<!avg\(|count\(|max\(|min\(|sum\()\b((?!' . preg_quote($table, '#') . '\.)\w+\.\w+)\b#', 'max(\1)', $temp_sql);	
				}
				
				if ($joined_to_many && preg_match('#order\s+by#i', $temp_sql)) {
					$order_by_found = TRUE;	
					
					$parts = preg_split('#(order\s+by)#i', $temp_sql, -1, PREG_SPLIT_DELIM_CAPTURE);
					$parts[2] = $temp_sql = preg_replace('#(?<!avg\(|count\(|max\(|min\(|sum\()\b((?!' . preg_quote($table, '#') . '\.)\w+\.\w+)\b#', 'max(\1)', $parts[2]);
					
					$temp_sql = join('', $parts);
				}
				
				$temp_sql = str_replace(':from_clause',     $from_clause,     $temp_sql);
				$temp_sql = str_replace(' :group_by_clause ', $group_by_clause, $temp_sql);
			}
			
			$new_sql .= $temp_sql;
		}
			
		return $new_sql;
	}
	
	
	/**
	 * Prepares a value for a DB call based on database schema
	 * 
	 * @throws fValidationException
	 * @internal
	 * 
	 * @param  string $table                The table to store the value
	 * @param  string $column               The column to store the value in
	 * @param  mixed  $value                The value to prepare
	 * @param  string $comparison_operator  Optional: should be '=', '<>', '<', '<=', '>', '>=', 'IN', 'NOT IN'
	 * @return string  The SQL-ready representation of the value
	 */
	static public function prepareBySchema($table, $column, $value, $comparison_operator=NULL)
	{
		$class = fORM::classize($table);
		$value = fORM::scalarize($class, $column, $value);
		
		$valid_comparison_operators = array('=', '<>', '<=', '<', '>=', '>', 'IN', 'NOT IN');
		if ($comparison_operator !== NULL && !in_array(strtoupper($comparison_operator), $valid_comparison_operators)) {
			fCore::toss(
				'fProgrammerException',
				fGrammar::compose(
					'The comparison operator specified, %s, is invalid. Must be one of: %s.',
					fCore::dump($comparison_operator),
					join(', ', $valid_comparison_operators)
				)
			);
		}
		
		$co = (is_null($comparison_operator)) ? '' : ' ' . strtoupper($comparison_operator) . ' ';
		
		$column_info = fORMSchema::getInstance()->getColumnInfo($table, $column);
		if ($column_info['not_null'] && $value === NULL && $column_info['default'] !== NULL) {
			$value = $column_info['default'];
		}
		
		if (is_null($value)) {
			if ($co) {
				if (in_array(trim($co), array('=', 'IN'))) {
					$co = ' IS ';
				} elseif (in_array(trim($co), array('<>', 'NOT IN'))) {
					$co = ' IS NOT ';
				}
			}
			$prepared = $co . 'NULL';
		} elseif (in_array($column_info['type'], array('varchar', 'char', 'text'))) {
			$prepared = $co . self::getInstance()->escapeString($value);
		} elseif ($column_info['type'] == 'timestamp') {
			$prepared = $co . self::getInstance()->escapeTimestamp($value);
		} elseif ($column_info['type'] == 'date') {
			$prepared = $co . self::getInstance()->escapeDate($value);
		} elseif ($column_info['type'] == 'time') {
			$prepared = $co . self::getInstance()->escapeTime($value);
		} elseif ($column_info['type'] == 'blob') {
			$prepared = $co . self::getInstance()->escapeBlob($value);
		} elseif ($column_info['type'] == 'boolean') {
			$prepared = $co . self::getInstance()->escapeBoolean($value);
		} elseif ($column_info['type'] == 'integer') {
			$prepared = $co . self::getInstance()->escapeInteger($value);
		} elseif ($column_info['type'] == 'float') {
			$prepared = $co . self::getInstance()->escapeFloat($value);
		}
		
		return $prepared;
	}
	
	
	/**
	 * Prepares a value for a DB call based on variable type
	 * 
	 * @internal
	 * 
	 * @param  mixed  $value                The value to prepare
	 * @param  string $comparison_operator  Optional: should be '=', '<>', '<', '<=', '>', '>=', 'IN', 'NOT IN'
	 * @return string  The SQL-ready representation of the value
	 */
	static public function prepareByType($value, $comparison_operator=NULL)
	{
		$valid_comparison_operators = array('=', '<>', '<=', '<', '>=', '>', 'IN', 'NOT IN');
		if ($comparison_operator !== NULL && !in_array(strtoupper($comparison_operator), $valid_comparison_operators)) {
			fCore::toss(
				'fProgrammerException',
				fGrammar::compose(
					'The comparison operator specified, %s, is invalid. Must be one of: %s.',
					fCore::dump($comparison_operator),
					join(', ', $valid_comparison_operators)
				)
			);
		}
		
		$co = (is_null($comparison_operator)) ? '' : ' ' . strtoupper($comparison_operator) . ' ';
		
		if (is_int($value) || is_float($value)) {
			$prepared = $co . $value;
		} elseif (is_bool($value)) {
			$prepared = $co . self::getInstance()->escapeBoolean($value);
		} elseif (is_null($value)) {
			if ($co) {
				if (in_array(trim($co), array('=', 'IN'))) {
					$co = ' IS ';
				} elseif (in_array(trim($co), array('<>', 'NOT IN'))) {
					$co = ' IS NOT ';
				}
			}
			$prepared = $co . "NULL";
		} else {
			$prepared = $co . self::getInstance()->escapeString($value);
		}
		
		return $prepared;
	}
	
	
	/**
	 * Forces use as a static class
	 * 
	 * @return fORMDatabase
	 */
	private function __construct() { }
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