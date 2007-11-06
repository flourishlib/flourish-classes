<?php
/**
 * Performs database manipulations for ORM-related code
 * 
 * @copyright  Copyright (c) 2007 William Bond
 * @author     William Bond [wb] <will@flourishlib.com>
 * @license    http://flourishlib.com/license
 * 
 * @version  1.0.0
 * @changes  1.0.0    The initial implementation [wb, 2007-08-04]
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
	 * Private class constructor to prevent instantiating the class
	 * 
	 * @since  1.0.0
	 * 
	 * @return fORMDatabase
	 */
	private function __construct() { }
	
	
	/**
	 * Initializes a singleton instance of the fDatabase class
	 * 
	 * @since  1.0.0
	 * 
	 * @param  string  $type      The type of the database
	 * @param  string  $database  Name of database
	 * @param  string  $username  Database username
	 * @param  string  $password  User's password
	 * @param  string  $host      Database server host or ip with optional port
	 * @param  integer $port      The port number of the database server
	 * @return void
	 */
	static public function initialize($type, $database, $username=NULL, $password=NULL, $host=NULL, $port=NULL)
	{
		self::$database_object = new fDatabase($type, $database, $username, $password, $host, $port);
	}
	
	
	/**
	 * Return the instance of the fDatabase class
	 * 
	 * @since  1.0.0
	 * 
	 * @return fDatabase  The database instance
	 */
	static public function getInstance()
	{
		if (!self::$database_object) {
			fCore::toss('fProgrammerException', 'The method initialize() needs to be called before getInstance()');
		}
		return self::$database_object;
	}
	
	
	/**
	 * Prepares a value for a DB call based on database schema
	 * 
	 * @since  1.0.0
	 * 
	 * @param  string $table                The table to store the value
	 * @param  string $column               The column to store the value in
	 * @param  mixed  $value                The value to prepare
	 * @param  string $comparison_operator  Optional: should be '=', '<>', '<', '<=', '>', '>=', 'IN', 'NOT IN'
	 * @return string  The SQL-ready representation of the value
	 */
	static public function prepareBySchema($table, $column, $value, $comparison_operator=NULL)
	{
		if ($comparison_operator !== NULL && !in_array(strtoupper($comparison_operator), array('=', '<>', '<=', '<', '>=', '>', 'IN', 'NOT IN'))) {
			fCore::toss('fProgrammerException', 'Invalid comparison operator specified');	
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
			$prepared = $co . "'" . date('Y-m-d H:i:s', strtotime($value)) . "'";
		} elseif ($column_info['type'] == 'date') {
			$prepared = $co . "'" . date('Y-m-d', strtotime($value)) . "'";
		} elseif ($column_info['type'] == 'time') {
			$prepared = $co . "'" . date('H:i:s', strtotime($value)) . "'";
		} elseif ($column_info['type'] == 'blob') {
			$prepared = $co . self::getInstance()->escapeBlob($value);
		} elseif ($column_info['type'] == 'boolean') {
			$prepared = $co . self::getInstance()->escapeBoolean($value);
		} else {
			$prepared = $co . $value;	
		}
		
		return $prepared;
	}
	
	
	/**
	 * Prepares a value for a DB call based on variable type
	 * 
	 * @since  1.0.0
	 * 
	 * @param  mixed  $value                The value to prepare
	 * @param  string $comparison_operator  Optional: should be '=', '<>', '<', '<=', '>', '>=', 'IN', 'NOT IN'
	 * @return string  The SQL-ready representation of the value
	 */
	static public function prepareByType($value, $comparison_operator=NULL)
	{
		if ($comparison_operator !== NULL && !in_array(strtoupper($comparison_operator), array('=', '<>', '<=', '<', '>=', '>', 'IN', 'NOT IN'))) {
			fCore::toss('fProgrammerException', 'Invalid comparison operator specified');	
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
	 * Prepends the table_name. to the values of the array
	 * 
	 * @since  1.0.0
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
	 * Prepends the table_name. to the keys of the array
	 * 
	 * @since  1.0.0
	 * 
	 * @param  string $table  The table to prepend
	 * @param  array  $array  The array to modify
	 * @return array  The modified array
	 */
	static public function addTableToKeys($table, $array)
	{
		$modified_array = array();
		foreach ($array as $key => $value) {
			if (preg_match('#^\w+$#', $value)) {
				$modified_array[$table . '.' . $key] = $value;
			} else {
				$modified_array[$key] = $value;
			}
		}	
		return $modified_array;
	}
	
	
	/**
	 * Finds all of the table names in the sql and creates a from clause
	 * 
	 * @since  1.0.0
	 * 
	 * @param  string $table  The main table to be queried
	 * @param  string $sql    The SQL to insert the from clause into
	 * @return string  The from SQL clause
	 */
	static public function insertFromClause($table, $sql)
	{
		if (strpos($sql, ':from_clause') === FALSE) {
			fCore::toss('fProgrammerException', "No :from_clause placeholder was found in:\n" . $sql);	
		}
		
		// Separate the SQL from quoted values
		preg_match_all("#(?:'(?:''|\\\\'|\\\\[^']|[^'\\\\])*')|(?:[^']+)#", $sql, $matches);
		
		// Look through all of the non-string values for database table names
		$second_tier_tables = array();
		$third_tier_tables = array();
		
		foreach ($matches[0] as $match) {
			if ($match != "'") {
				preg_match_all('#\b((?:(\w+)=>)?\w+)\.\w+\b#m', $match, $table_matches, PREG_SET_ORDER);		
				foreach ($table_matches as $table_match) {
					if (!isset($table_match[1])) {
						continue;	
					}
					if (!empty($table_match[2])) {
						$third_tier_tables[] = $table_match[1];
						$second_tier_tables[] = $table_match[2];
						continue;	
					}
					$second_tier_tables[] = $table_match[1];
				}
			}
		}
		$second_tier_tables = array_diff($second_tier_tables, array($table));
		$second_tier_tables = array_unique($second_tier_tables);
		sort($second_tier_tables);
		$used_relationships = array();
		
		
		// Find all of the related tables for this table
		$relationships = fORMSchema::getInstance()->getRelationships($table);
		$related_tables = array();
		foreach ($relationships as $type => $entries) {
			foreach ($entries as $relationship) {
				$related_tables[] = $relationship['related_table'];
				if (in_array($relationship['related_table'], $second_tier_tables)) {
					$used_relationships[$relationship['related_table']] = $relationship;
				}	
			}
		}
		$related_tables = array_unique($related_tables);
		sort($related_tables);
		
		if (array_diff($second_tier_tables, $related_tables) != array()) {
			fCore::toss('fProgrammerException', 'One of the tables specified in the following SQL is not related to the table ' . $table . ":\n" . $sql);	
		}
		
		
		// Make the second tier joins
		$from_clause = $table;
		foreach ($second_tier_tables as $joined_table) {		
			$rel = $used_relationships[$joined_table];
			if (isset($rel['join_table'])) {
				$from_clause .= ' LEFT JOIN ' . $rel['join_table'];
				$from_clause .= ' ON ' . $table . '.' . $rel['column'];
				$from_clause .= ' = ' . $rel['join_table'] . '.' . $rel['join_column'];	
				$from_clause .= ' LEFT JOIN ' . $rel['related_table'];
				$from_clause .= ' ON ' . $rel['join_table'] . '.' . $rel['join_related_column'];
				$from_clause .= ' = ' . $rel['related_table'] . '.' . $rel['related_column'];
			} else {
				$from_clause .= ' LEFT JOIN ' . $rel['related_table'];
				$from_clause .= ' ON ' . $table . '.' . $rel['column'];
				$from_clause .= ' = ' . $rel['related_table'] . '.' . $rel['related_column'];
			}
		}
		
		
		
		// Make the third tier joins
		static $table_number = 0;
		$table_map = array();
		
		foreach ($third_tier_tables as $chained_table) {		
			
			$st_table = preg_replace('#=>\w+#', '', $chained_table);
			$tt_table = preg_replace('#\w+=>#', '', $chained_table);
			$relationships = fORMSchema::getInstance()->getRelationships($st_table); 
			
			foreach ($relationships as $type => $rels) {
				foreach ($rels as $rel) {
					if ($rel['related_table'] != $tt_table) {
						continue;	
					}
					
					if (isset($rel['join_table'])) {
						$jt = 'table' . $table_number;
						$table_number++;
						$rt = 'table' . $table_number;
						$table_number++;
						
						$table_map[$chained_table] = $rt;
						
						$from_clause .= ' LEFT JOIN ' . $rel['join_table'] . ' AS ' . $jt;
						$from_clause .= ' ON ' . $st_table . '.' . $rel['column'];
						$from_clause .= ' = ' . $jt . '.' . $rel['join_column'];	
						$from_clause .= ' LEFT JOIN ' . $rel['related_table'] . ' AS ' . $rt;
						$from_clause .= ' ON ' . $jt . '.' . $rel['join_related_column'];
						$from_clause .= ' = ' . $rt . '.' . $rel['related_column'];
						
					} else {
						$rt = 'table' . $table_number;
						$table_number++;
						
						$table_map[$chained_table] = $rt;
						
						$from_clause .= ' LEFT JOIN ' . $rel['related_table'] . ' AS ' . $rt;
						$from_clause .= ' ON ' . $st_table . '.' . $rel['column'];
						$from_clause .= ' = ' . $rt . '.' . $rel['related_column'];
					}
				}
			}
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
				$temp_sql = str_replace(':from_clause', $from_clause, $temp_sql);
			}
			
			$new_sql .= $temp_sql;
		}	
			
		
		return $new_sql;
	}
	
	
	/**
	 * Creates a where clause from an array of conditions
	 * 
	 * The conditions array can contain entries in any of the following formats (where VALUE/VALUE2 can be of any data type):
	 *   - 'column='                 => VALUE,                    // column = VALUE
	 *   - 'column!'                 => VALUE,                    // column <> VALUE
	 *   - 'column~'                 => VALUE,                    // column LIKE '%VALUE%'
	 *   - 'column='                 => array(VALUE, VALUE2,...), // column IN (VALUE, VALUE2, ...)
	 *   - 'column!'                 => array(VALUE, VALUE2,...), // columnld NOT IN (VALUE, VALUE2, ...)
	 *   - 'column~'                 => array(VALUE, VALUE2,...), // (column LIKE '%VALUE%' OR column LIKE '%VALUE2%' OR column ...)
	 *   - 'column|column2|column3~' => VALUE,                    // (column LIKE '%VALUE%' OR column2 LIKE '%VALUE2%' OR column3 LIKE '%VALUE%')
	 *   - 'column|column2|column3~' => array(VALUE, VALUE2,...), // ((column LIKE '%VALUE%' OR column2 LIKE '%VALUE%' OR column3 LIKE '%VALUE%') AND (column LIKE '%VALUE2%' OR column2 LIKE '%VALUE2%' OR column3 LIKE '%VALUE2%') AND ...)
	 * 
	 * @since  1.0.0
	 * 
	 * @param  string $table       The table any ambigious column references will refer to
	 * @param  array  $conditions  The array of conditions (see method description for format)
	 * @return string  The SQL WHERE clause
	 */
	static public function createWhereClause($table, $conditions)
	{
		$sql = array();
		foreach ($conditions as $column => $values) {
			$type   = substr($column, -1);
			$column = substr($column, 0, -1);
			settype($values, 'array');
			if ($values === array()) { $values = array(NULL); }
			
			
			// Multi-column condition
			if (strpos($column, '|') !== FALSE) {
				if ($type != '~') {
					fCore::toss('fProgrammerException', 'Invalid matching type, ' . $type . ', specified');
				}
				$columns = self::addTableToValues($table, explode('|', $column));
				
				$condition = array();
				foreach ($values as $value) {
					$sub_condition = array();
					foreach ($columns as $column) {
						$sub_condition[] = $column . ' ' . self::getInstance()->getLikeOperator() . ' ' . self::getInstance()->escapeString('%' . $value . '%');	
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
								$condition[] = $column . ' ' . self::getInstance()->getLikeOperator() . ' ' . self::getInstance()->escapeString('%' . $value . '%');	
							}
							$sql[] = '(' . join(' OR ', $condition) . ')';
							break;
						default:
							fCore::toss('fProgrammerException', 'Invalid matching type, ' . $type . ', specified');
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
							$sql[] = $column . ' ' . self::getInstance()->getLikeOperator() . ' ' . self::getInstance()->escapeString('%' . $values[0] . '%');	
							break;
						default:
							fCore::toss('fProgrammerException', 'Invalid matching type, ' . $type . ', specified');
							break;	
					}	
				}		
			}
		}
		
		return join(' AND ', $sql);				
	}
	
	
	/**
	 * Creates an order by clause from an array of columns/expressions and directions
	 * 
	 * The order bys array can contain entries formatting in any combination of:
	 *   - (string) {column name} => 'asc'
	 *   - (string) {column name} => 'desc'
	 *   - (string) {expression}  => 'asc'
	 *   - (string) {expression}  => 'desc'
	 * 
	 * @since  1.0.0
	 * 
	 * @param  string $table      The table any ambigious column references will refer to
	 * @param  array  $order_bys  The array of order bys to use
	 * @return string  The SQL ORDER BY clause
	 */
	static public function createOrderByClause($table, $order_bys)
	{
		$order_bys = self::addTableToKeys($table, $order_bys);
		$sql = array();
		
		foreach ($order_bys as $column => $direction) {
			$direction = strtoupper($direction);
			if (!in_array($direction, array('ASC', 'DESC'))) {
				fCore::toss('fProgrammerException', 'Invalid direction, ' . $direction . ', specified');
			}
			
			if (preg_match('#^(\w+)\.(\w+)$#', $column, $matches)) {
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
	 * Takes an array of rows containing primary keys for a table. If there is only a single primary key, condense the array of rows into single-dimensional array
	 * 
	 * @since  1.0.0
	 * 
	 * @param  array $rows   The rows of primary keys
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