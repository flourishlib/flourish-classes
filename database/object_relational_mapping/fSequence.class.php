<?php
/**
 * A lightweight, iterable set of {@link fActiveRecord}-based objects
 * 
 * @copyright  Copyright (c) 2007-2008 William Bond
 * @author     William Bond [wb] <will@flourishlib.com>
 * @license    http://flourishlib.com/license
 * 
 * @link  http://flourishlib.com/fSequence
 * 
 * @uses  fCore
 * @uses  fEmptySequenceException
 * @uses  fInflection
 * @uses  fORM
 * @uses  fORMDatabase
 * @uses  fORMSchema
 * @uses  fProgrammerException
 * 
 * @todo  Add order by support of related data to preloading code
 * @todo  Add pagination (limit/offset) support to create()
 * 
 * @version  1.0.0
 * @changes  1.0.0    The initial implementation [wb, 2007-08-04]
 */
class fSequence implements Iterator
{
	/**
	 * Creates an fSequence by specifying the class to create plus the where conditions and order by rules
	 * 
	 * The where conditions array can contain key => value entries in any of the following formats (where VALUE/VALUE2 can be of any data type):
	 * <pre>
	 *  - '{column}='                     => VALUE,                    // column = VALUE
	 *  - '{column}!'                     => VALUE,                    // column <> VALUE
	 *  - '{column}~'                     => VALUE,                    // column LIKE '%VALUE%'
	 *  - '{column}='                     => array(VALUE, VALUE2,...), // column IN (VALUE, VALUE2, ...)
	 *  - '{column}!'                     => array(VALUE, VALUE2,...), // columnld NOT IN (VALUE, VALUE2, ...)
	 *  - '{column}~'                     => array(VALUE, VALUE2,...), // (column LIKE '%VALUE%' OR column LIKE '%VALUE2%' OR column ...)
	 *  - '{column}|{column2}|{column3}~' => VALUE,                    // (column LIKE '%VALUE%' OR column2 LIKE '%VALUE2%' OR column3 LIKE '%VALUE%')
	 *  - '{column}|{column2}|{column3}~' => array(VALUE, VALUE2,...), // ((column LIKE '%VALUE%' OR column2 LIKE '%VALUE%' OR column3 LIKE '%VALUE%') AND (column LIKE '%VALUE2%' OR column2 LIKE '%VALUE2%' OR column3 LIKE '%VALUE2%') AND ...)
	 * </pre>
	 * 
	 * The order bys array can contain key => value entries in any of the following formats:
	 * <pre>
	 *  - '{column}' => 'asc'           // 'first_name' => 'asc'
	 *  - '{column}' => 'desc'          // 'last_name'  => 'desc'
	 *  - '{expression}'  => 'asc'      // "CASE first_name WHEN 'smith' THEN 1 ELSE 2 END" => 'asc'
	 *  - '{expression}'  => 'desc'     // "CASE first_name WHEN 'smith' THEN 1 ELSE 2 END" => 'desc'
	 * </pre>
	 * 
	 * The {column} in both the where conditions and order bys can be in any of the formats:
	 * <pre>
	 *  - '{column}'                                                                 // e.g. 'first_name'
	 *  - '{current_table}.{column}'                                                 // e.g. 'users.first_name'
	 *  - '{related_table}.{column}'                                                 // e.g. 'user_groups.name'
	 *  - '{related_table}[{route}].{column}'                                        // e.g. 'user_groups[user_group_id].name'
	 *  - '{related_table}=>{once_removed_related_table}.{column}'                   // e.g. 'user_groups=>permissions.level'
	 *  - '{related_table}[{route}]=>{once_removed_related_table}.{column}'          // e.g. 'user_groups[user_group_id]=>permissions.level'
	 *  - '{related_table}=>{once_removed_related_table}[{route}].{column}'          // e.g. 'user_groups=>permissions[read].level'
	 *  - '{related_table}[{route}]=>{once_removed_related_table}[{route}].{column}' // e.g. 'user_groups[user_group_id]=>permissions[read].level'
	 * </pre>
	 * 
	 * @param  string $class_name        The class to create the fSequence of
	 * @param  array  $where_conditions  The column => value comparisons for the where clause
	 * @param  array  $order_bys         The column => direction values to use for sorting
	 * @return fSequence  A sequence of {@link fActiveRecord fActiveRecords}
	 */
	static public function create($class_name, $where_conditions=array(), $order_bys=array())
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
		
		return new fSequence($class_name, fORMDatabase::getInstance()->translatedQuery($sql));			
	}
	
	
	/**
	 * Creates an fSequence from an array of primary keys
	 * 
	 * @param  string $class_name    The type of object to create
	 * @param  array  $primary_keys  The primary keys of the objects to create
	 * @param  array  $order_bys     The column => direction values to use for sorting (see {@link fSequence::create()} for format)
	 * @return fSequence  A sequence of ActiveRecords
	 */
	static public function createFromPrimaryKeys($class_name, $primary_keys, $order_bys=array())
	{
		$table_name   = fORM::tablize($class_name);
		
		settype($primary_keys, 'array');
		$primary_keys = array_merge($primary_keys);
		
		$sql  = 'SELECT DISTINCT ' . $table_name . '.* FROM :from_clause WHERE ';
		
		// Build the where clause
		$primary_key_fields = fORMSchema::getInstance()->getKeys($table_name, 'primary');
		$total_pk_fields = sizeof($primary_key_fields);
		
		// If it is a multi-field primary key, the sql is more complex
		if ($total_pk_fields > 1) {
			
			$total_primary_keys = sizeof($primary_keys);
			for ($i=0; $i < $total_primary_keys; $i++) {
				if ($i > 0) {
					$sql .= 'OR';
				}
				$sql .= ' (';
				for ($j=0; $j < $total_pk_fields; $j++) {
					if ($j > 0) {
						$sql .= ' AND ';	
					}
					$pkf = $primary_key_fields[$j];
					$sql .= $table_name . '.' . $pkf . fORMDatabase::prepareBySchema($table_name, $pkf, $primary_keys[$i][$pkf], '=');	
				}
				$sql .= ') ';	
			}
			
		// Single field primary keys are simple
		} else {
			$sql .= fORMDatabase::createWhereClause($table_name, array($primary_key_fields[0] . '=' => $primary_keys));
		}	
		
		if (!empty($order_bys)) {
			$sql .= ' ORDER BY ' . fORMDatabase::createOrderByClause($table_name, $order_bys);				
		}
		
		$sql = fORMDatabase::insertFromClause($table_name, $sql);
		
		return new fSequence($class_name, fORMDatabase::getInstance()->translatedQuery($sql));	
	}
	
	
	/**
	 * Creates an fSequence from an SQL statement
	 * 
	 * @param  string $class_name  The type of object to create
	 * @param  string $sql         The sql to create the sequence from
	 * @return fSequence  A sequence of ActiveRecords
	 */
	static public function createFromSql($class_name, $sql)
	{
		$result = fORMDatabase::getInstance()->translatedQuery($sql);
		return new fSequence($class_name, $result);	
	}
	
	
	/**
	 * The type of class to create from the primary keys provided
	 * 
	 * @var string 
	 */
	private $class_name;
	
	/**
	 * The result object that will act as the data source
	 * 
	 * @var object 
	 */
	private $result_object;
	
	/**
	 * The result objects for preloaded data
	 * 
	 * @var array 
	 */
	private $preloaded_result_objects = array();
	
	/**
	 * An array of the records in the sequence, initially empty
	 * 
	 * @var array 
	 */
	private $records = array();
	
	/**
	 * An array of the primary keys for the records in the sequence, initially empty
	 * 
	 * @var array 
	 */
	private $primary_keys = array();
	
	
	/**
	 * Sets the contents of the sequence
	 * 
	 * @param  string $class_name     The type of records to create
	 * @param  object $result_object  The primary keys or fResult object of the records to create
	 * @return fSequence
	 */
	protected function __construct($class_name, fResult $result_object)
	{
		if (!class_exists($class_name)) {
			fCore::toss('fProgrammerException', 'The class ' . $class_name . ' could not be loaded');	
		}
		$this->class_name = $class_name;
		
		$this->result_object = $result_object;
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
                $sort_method = substr($element, 3);
		        $sort_method = fInflection::camelize($sort_method, FALSE);
		        $this->performSort($parameters[0], $parameters[1], $sort_method);
                break;
                
            case 'preload':
                $this->performPreload($element, ($parameters != array()) ? $parameters[0] : NULL);
                break;
            
            default:
                fCore::toss('fProgrammerException', 'Unknown method, ' . $method_name . '(), called');      
                break;
        }
	}
	
	
	/**
	 * Returns the class name of the record being stored
	 * 
	 * @return string  The class name of the records in the sequence
	 */
	public function getClassName()
	{
		return $this->class_name;   
	}
	
	
	/**
	 * Returns all of the records in the sequence
	 * 
	 * @return array  The records in the sequence
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
	 * Returns the primary keys for all of the records in the sequence
	 * 
	 * @return array  The primary keys of all the records in the sequence
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
	 * Returns the number of records in the sequence
	 * 
	 * @return integer  The number of records in the sequence
	 */
	public function getSizeOf()
	{
		return $this->result_object->getReturnedRows();	
	}
	
	
	/**
	 * Throws a fEmptySequenceException if the fSequence is empty
	 * 
	 * @throws  fEmptySequenceException
	 * 
	 * @return void
	 */
	public function tossIfEmpty()
	{
		if (!$this->getSizeOf()) {
			fCore::toss('fEmptySequenceException', 'No ' . fInflection::humanize(fInflection::pluralize($this->class_name)) . ' could be found');	
		}
	}
	
	
	/**
	 * Sorts the sequence by the return value of a method from the class created
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
	 * Does the action of sorting records
	 * 
	 * @param  object $a       Record a
	 * @param  object $b       Record b
	 * @param  string $method  The method to sort by
	 * @return integer  < 0 if a is less than b; 0 if a = b; > 0 if a is greater than b
	 */
	private function performSort($a, $b, $method)
	{
		return strnatcasecmp($a->$method(), $b->$method());  
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
		$aliases = fSQLParsing::parseTableAliases($clauses['FROM']);
		
		if (!empty($clauses['GROUP BY'])) {
			fCore::toss('fProgrammerException', 'Preloading related data is not possible for queries that contain a GROUP BY clause');	
		}
		
		$this_table = fORM::tablize($this->class_name);
		
		$routes = fORMSchema::getToManyRoutes($this_table, $related_table);
		
		if (empty($route)) {
		 	try {
		 		$route = fORMSchema::getOnlyToManyRouteName($this_table, $related_table);	
			} catch (fValidationException $e) {
				fCore::toss('fProgrammerException', 'The related table being preloaded, ' . $related_table . ', is related to the ' . $this->class_name . ' object by more than one route and no route was specified');
			}
		}

		$related_table_info = $routes[$route];
		
		$related_table_alias = (isset($aliases[$related_table])) ? $aliases[$related_table] : $related_table;
		
		$new_sql  = 'SELECT ' . $related_table_alias . '.* ';
		$new_sql .= 'FROM ' . $clauses['FROM'];
		
		
		$this_column_name    = $aliases[$this_table] . '.' . $related_table_info['column'];
		$related_column_name = $related_table_alias . '.' . $related_table_info['related_column'];
		
		// Figure out if we have already joined and if not, do the joins the appropriate way
		if (isset($related_table_info['join_table'])) {
			
			if (!isset($aliases[$related_table])) {
				$join_table_alias         = (isset($aliases[$related_table_info['join_table']])) ? $aliases[$related_table_info['join_table']] : $related_table_info['join_table'];
				$join_column_name         = $join_table_alias . '.' . $related_table_info['join_column'];
				$join_related_column_name = $join_table_alias . '.' . $related_table_info['join_related_column'];
				
				$new_sql = ' INNER JOIN ' . $join_table_alias . ' ON ' . $this_column_name . ' = ' . $join_column_name . ' ';
				$new_sql = ' INNER JOIN ' . $related_table_alias . ' ON ' . $join_related_column_name . ' = ' . $related_column_name . ' ';
			}
			
		} else {
			
			if (!isset($aliases[$related_table])) {
				$new_sql = ' INNER JOIN ' . $related_table_alias . ' ON ' . $this_column_name . ' = ' . $related_column_name . ' ';
			}		
			
		}
		
		if (!empty($clauses['WHERE'])) {
			$new_sql .= 'WHERE (' . $clauses['WHERE'] . ') ';
		}
		
		// Limited queries require a slight bit of additional modification
		if (!empty($clauses['LIMIT'])) {
			if (!empty($clauses['WHERE'])) {
			 	$new_sql .= ' AND ';	
			} else {
			 	$new_sql .= 'WHERE ';	
			}
			
			$primary_keys = $this->getPrimaryKeys();
			$primary_key_fields = fORMSchema::getInstance()->getKeys($this_table, 'primary');
			
			// We have a multi-field primary key, making things kinda ugly
			if (is_array($primary_keys[0])) {
				$new_sql .= '((';
			 	$first = TRUE;
			 	foreach ($primary_keys as $primary_key) {
			 	 	if (!$first) {
			 	 	 	$new_sql .= ') OR (';	
					}
					for ($i=0; $i < sizeof($primary_key_fields); $i++) {
			 	 		if ($i) {
			 	 			$new_sql .= ' AND ';
						} 
			 	 		$new_sql .= $aliases[$this_table] . '.' . $primary_key_fields[$i];
			 	 		$new_sql .= fORMDatabase::prepareBySchema($this_table, $primary_key_fields[$i], $primary_key, '=');
					}
			 	 	$first = FALSE;
				}
			 	$new_sql .= ')) ';
			
			// We have a single primary key field, making things nice and easy
			} else {
			 	$new_sql .= $aliases[$this_table] . '.' . $primary_key_fields[0] . ' IN (';
			 	$first = TRUE;
			 	foreach ($primary_keys as $primary_key) {
			 	 	if (!$first) {
			 	 	 	$new_sql .= ', ';	
					}
			 	 	$new_sql .= fORMDatabase::prepareBySchema($this_table, $primary_key_fields[0], $primary_key);
			 	 	$first = FALSE;
				}
			 	$new_sql .= ') ';	
			}
		}
		
		if (!empty($clauses['ORDER BY'])) {
			$new_sql .= 'ORDER BY ' . $clauses['ORDER BY'];
		} 
		
		if (!isset($this->preloaded_result_objects[$related_table])) {
		 	$this->preloaded_result_objects[$related_table] = array();	
		}
		
		$this->preloaded_result_objects[$related_table][$route] = fORMDatabase::getInstance()->translatedQuery($new_sql);
	}
	
	
	/**
	 * Rewinds the sequence to the first record (used for iteration)
	 * 
	 * @return void
	 */
	public function rewind()
	{
		$this->result_object->rewind();
	}

	
	/**
	 * Returns the current record in the sequence (used for iteration)
	 * 
	 * @return object  The current record
	 */
	public function current()
	{
		if (!isset($this->records[$this->key()])) {
			$this->records[$this->key()] = new $this->class_name($this->result_object);
			
			// Pass the preloaded data to the object
			foreach ($this->preloaded_result_objects as $related_table => $result_objects) {
				foreach ($result_objects as $route => $result_object) {
					$method = 'preload' . fInflection::camelize($related_table, TRUE);
					$this->records[$this->key()]->$method($route, $result_object);
				}
			}
			
			// Fetch the primary keys for this object
			$primary_keys = fORMSchema::getInstance()->getKeys(fORM::tablize($this->class_name), 'primary');
			$keys = array();
			foreach ($primary_keys as $primary_key) {
				$method = 'get' . fInflection::camelize($primary_key, TRUE);
			 	$keys[$primary_key] = $this->records[$this->key()]->$method();	
			}
			$this->primary_keys[$this->key()] = (sizeof($primary_keys) == 1) ? $keys[$primary_keys[0]] : $keys;
		}
		return $this->records[$this->key()];
	}

	
	/**
	 * Returns the primary key for the current record (used for iteration)
	 * 
	 * @return mixed  The primay key of the current record
	 */
	public function key()
	{
		return $this->result_object->key();
	}

	
	/**
	 * Moves to the next record in the sequence (used for iteration)
	 * 
	 * @return void
	 */
	public function next()
	{
		$this->result_object->next();
	}

	
	/**
	 * Returns if the sequence has any records left (used for iteration)
	 * 
	 * @return boolean  If the iterator is still valid
	 */
	public function valid()
	{
		return $this->result_object->valid();
	}	  
}


// Handle dependency load order for extending exceptions
if (!class_exists('fCore')) { }


/**
 * An exception when an fSequence does not contain any elements
 * 
 * @copyright  Copyright (c) 2007-2008 William Bond
 * @author     William Bond [wb] <will@flourishlib.com>
 * @license    http://flourishlib.com/license
 * 
 * @link  http://flourishlib.com/fEmptySequenceException
 * 
 * @version  1.0.0 
 * @changes  1.0.0    The initial implementation [wb, 2007-06-14]
 */
class fEmptySequenceException extends fExpectedException
{
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