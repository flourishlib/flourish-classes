<?php
/**
 * A lightweight, iterable set of {@link fActiveRecord}-based objects
 * 
 * @copyright  Copyright (c) 2007 William Bond
 * @author     William Bond [wb] <will@flourishlib.com>
 * @license    http://flourishlib.com/license
 * 
 * @link  http://flourishlib.com/fSet
 * 
 * @uses  fCore
 * @uses  fEmptySetException
 * @uses  fInflection
 * @uses  fORM
 * @uses  fORMDatabase
 * @uses  fORMSchema
 * @uses  fProgrammerException
 * 
 * @version  1.0.0
 * @changes  1.0.0    The initial implementation [wb, 2007-08-04]
 */
class fSet implements Iterator
{
	/**
	 * Creates an fSet by specifying the class to create plus the where conditions and order by rules
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
	 *  - '{column}' => 'asc'
	 *  - '{column}' => 'desc'
	 *  - '{expression}'  => 'asc'
	 *  - '{expression}'  => 'desc'
	 * </pre>
	 * 
	 * The {column} in both the where conditions and order bys can be in any of the formats:
	 * <pre>
	 *  - '{column}'                                               // e.g. 'first_name'
	 *  - '{current_table}.{column}'                               // e.g. 'users.first_name'
	 *  - '{related_table}.{column}'                               // e.g. 'user_groups.name'
	 *  - '{related_table}=>{once_removed_related_table}.{column}' // e.g. 'user_groups=>permissions.level'
	 * </pre>
	 * 
	 * @param  string $class_name        The class to create the fSet of
	 * @param  array  $where_conditions  The column => value comparisons for the where clause
	 * @param  array  $order_bys         The column => direction values to use for sorting
	 * @return fSet  A set of {@link fActiveRecord fActiveRecords}
	 */
	static public function create($class_name, $where_conditions=array(), $order_bys=array())
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
		
		return new fSet($class_name, fORMDatabase::getInstance()->translatedQuery($sql));			
	}
	
	
	/**
	 * Creates an fSet from an array of primary keys
	 * 
	 * @param  string $class_name    The type of object to create
	 * @param  array  $primary_keys  The primary keys of the objects to create
	 * @param  array  $order_bys     The column => direction values to use for sorting (see {@link fSet::create()} for format)
	 * @return fSet  A Set of ActiveRecords
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
		
		return new fSet($class_name, fORMDatabase::getInstance()->translatedQuery($sql));	
	}
	
	
	/**
	 * Creates an fSet from an SQL statement
	 * 
	 * @param  string $class_name  The type of object to create
	 * @param  string $sql         The sql to get the primary keys to create a Set from
	 * @return fSet  A Set of ActiveRecords
	 */
	static public function createFromSql($class_name, $sql)
	{
		$result = fORMDatabase::getInstance()->translatedQuery($sql);
		return new fSet($class_name, $result);	
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
	 * An array of the records in the set, initially empty
	 * 
	 * @var array 
	 */
	private $records;
	
	
	/**
	 * Sets the contents of the set
	 * 
	 * @param  string $class_name     The type of records to create
	 * @param  object $result_object  The primary keys or fResult object of the records to create
	 * @return fSet
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
		$sort_method = str_replace('sortBy', $method_name);
		if ($sort_method == $method_name) {
			fCore::toss('fProgrammerException', 'Unknown method, ' . $method_name . '(), called');	
		}
		$sort_method[0] = strtolower($sort_method[0]);
		$this->sortCallback($parameter[0], $parameter[1], $sort_method);
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
	 * Returns all of the records in the set
	 * 
	 * @return array  The records in the set
	 */
	public function getRecords()
	{
		if (!isset($this->records)) {
			$this->createAllRecords();	
		}
		return $this->records;   
	}
	
	
	/**
	 * Returns the number of records in the set
	 * 
	 * @return integer  The number of records in the set
	 */
	public function getSizeOf()
	{
		return $this->result_object->getNumRows();	
	}
	
	
	/**
	 * Throws a fEmptySetException if the fSet is empty
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
	private function sortCallback($a, $b, $method)
	{
		return strnatcasecmp($a->$method(), $b->$method());  
	}
	
	
	/**
	 * Rewinds the set to the first record (used for iteration)
	 * 
	 * @return void
	 */
	public function rewind()
	{
		$this->result_object->rewind();
	}

	
	/**
	 * Returns the current record in the set (used for iteration)
	 * 
	 * @return object  The current record
	 */
	public function current()
	{
		if (!isset($this->records[$this->key()])) {
			$this->records[$this->key()] = new $this->class_name($this->result_object);
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
	 * Moves to the next record in the set (used for iteration)
	 * 
	 * @return void
	 */
	public function next()
	{
		$this->result_object->next();
	}

	
	/**
	 * Returns if the set has any records left (used for iteration)
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
 * An exception when an fSet does not contain any elements
 * 
 * @copyright  Copyright (c) 2007 William Bond
 * @author     William Bond [wb] <will@flourishlib.com>
 * @license    http://flourishlib.com/license
 * 
 * @link  http://flourishlib.com/fEmptySetException
 * 
 * @version  1.0.0 
 * @changes  1.0.0    The initial implementation [wb, 2007-06-14]
 */
class fEmptySetException extends fExpectedException
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