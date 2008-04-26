<?php
/**
 * Handles related data tasks for (@link fActiveRecord} classes. Related data only works for single-field foreign keys.
 * 
 * @copyright  Copyright (c) 2007-2008 William Bond
 * @author     William Bond [wb] <will@flourishlib.com>
 * @license    http://flourishlib.com/license
 * 
 * @link  http://flourishlib.com/fORMRelatedData
 * 
 * @uses  fCore
 * @uses  fInflection
 * @uses  fORM
 * @uses  fORMDatabase
 * @uses  fORMSchema
 * @uses  fProgrammerException
 * @uses  fRecordSet
 * 
 * @todo  Finish rewriting retrieveValues()
 * 
 * @version  1.0.0
 * @changes  1.0.0    The initial implementation [wb, 2007-12-30]
 */
class fORMRelatedData
{
	/**
	 * Rules that control what order related data is returned in
	 * 
	 * @var array 
	 */
	static private $order_bys = array();
	
	
	/**
	 * Forces use as a static class
	 * 
	 * @return fORMRelatedData
	 */
	private function __construct() { }
	
	
	/**
	 * Sets the ordering to use when returning {@link fRecordSet fRecordSets} of related objects
	 *
	 * @param  mixed  $table           The database table (or {@link fActiveRecord} class) this ordering rule applies to
	 * @param  string $related_table   The related table we are getting info from
	 * @param  string $route           The route to the related table, this should be a column name in the current table or a join table name
	 * @param  array  $order_bys       An array of the order bys for this table.column combination (see {@link fRecordSet::create()} for format)
	 * @return void
	 */
	static public function setOrderBys($table, $related_table, $route, $order_bys)
	{
		if (is_object($table)) {
			$table = fORM::tablize($table);	
		}
		
		if (!isset(self::$order_bys[$table])) {
			self::$order_bys[$table] = array();		
		}
		
		if (!isset(self::$order_bys[$table][$related_table])) {
			self::$order_bys[$table][$related_table] = array();		
		}
		
		self::$order_bys[$table][$related_table][$route] = $order_bys;
	}
	
	
	/**
	 * Gets the ordering to use when returning {@link fRecordSet fRecordSets} of related objects
	 *
	 * @internal
	 * 
	 * @param  mixed  $table          The database table (or {@link fActiveRecord} class) this ordering rule applies to
	 * @param  string $related_table  The related table the ordering rules apply to
	 * @param  string $route          The route to the related table, should be a column name in the current table or a join table name
	 * @return array  An array of the order bys (see {@link fRecordSet::create()} for format)
	 */
	static public function getOrderBys($table, $related_table, $route)
	{
		if (is_object($table)) {
			$table = fORM::tablize($table);	
		}
		
		if (!isset(self::$order_bys[$table][$related_table]) || !isset(self::$order_bys[$table][$related_table][$route])) {
			return array();		
		}
		
		return self::$order_bys[$table][$related_table][$route];
	}
	
	
	/**
	 * Builds the object for the related class specified
	 * 
	 * @internal
	 * 
	 * @param  mixed  $table           The database table (or {@link fActiveRecord} class) to get the related values for
	 * @param  array  $values          The values existing in the {@link fActiveRecord} class
	 * @param  string $related_class   The related class name
	 * @param  string $route           The route to the related class
	 * @return fActiveRecord  An instace of the class specified
	 */
	static public function constructRecord($table, $values, $related_class, $route=NULL)
	{
		if (is_object($table)) {
			$table = fORM::tablize($table);	
		}
		
		$related_table = fORM::tablize($related_class);
		
		$relationship = fORMSchema::getToOneRoute($table, $related_table, $route);
		
		return new $class($values[$relationship['column']]);	
	}
	
	
	/**
	 * Builds a sequence of related records along a one-to-many or many-to-many relationship
	 * 
	 * @internal
	 * 
	 * @param  mixed  $table              The database table (or {@link fActiveRecord} class) to get the related values for
	 * @param  array  &$values            The values for the {@link fActiveRecord} class
	 * @param  array  &$related_records   The related records existing for the {@link fActiveRecord} class
	 * @param  string $related_class      The class that is related to the current record
	 * @param  string $route              The route to follow for the class specified
	 * @return array  An array of the related column values
	 */
	static public function constructSequence($table, &$values, &$related_records, $related_class, $route=NULL)
	{
		if (is_object($table)) {
			$table = fORM::tablize($table);	
		}
		
		$related_table = fORM::tablize($related_class);
		
		// If we already have the sequence, we can stop here
		if (isset($related_records[$related_table][$route])) {
			return $related_records[$related_table][$route];	
		}
		
		$route        = fORMSchema::getToManyRouteName($table, $related_table, $route);
		$relationship = fORMSchema::getToManyRoute($table, $related_table, $route);
		
		// Determine how we are going to build the sequence
		$where_conditions = array($table . '.' . $relationship['column'] . '=' => $values[$relationship['column']]);
		$order_bys = self::getOrderBys($table, $related_table, $route);
		
		$sequence = fRecordSet::create($related_class, $where_conditions, $order_bys);	
		
		// Cache the results for subsequent calls
		if (!isset($related_records[$related_table])) {
			$related_records[$related_table] = array();	
		}
		$related_records[$related_table][$route] = $sequence;
		
		return $sequence;
	}
	
	
	/**
	 * Creates associations for many-to-many relationships
	 * 
	 * @internal
	 * 
	 * @param  mixed  $table                   The database table (or {@link fActiveRecord} class) to get the related values for
	 * @param  array  &$related_records        The related records existing for the {@link fActiveRecord} class
	 * @param  string $related_class           The class we are associating with the current record
	 * @param  fRecordSet $records             The records are associating
	 * @param  string $route                   The route to use between the current class and the related class
	 * @return void
	 */
	static public function linkRecords($table, &$related_records, $related_class, fRecordSet $records, $route=NULL)
	{
		self::setRecords($table, $related_records, $related_class, $records, $route);
		$records->flag('associate');
	}
	
	
	/**
	 * Sets the related records for many-to-many relationships
	 * 
	 * @internal
	 * 
	 * @param  mixed  $table                   The database table (or {@link fActiveRecord} class) to get the related values for
	 * @param  array  &$related_records        The related records existing for the {@link fActiveRecord} class
	 * @param  string $related_class           The class we are associating with the current record
	 * @param  fRecordSet $records             The records are associating
	 * @param  string $route                   The route to use between the current class and the related class
	 * @return void
	 */
	static public function setRecords($table, &$related_records, $related_class, fRecordSet $records, $route=NULL)
	{
		if (is_object($table)) {
			$table = fORM::tablize($table);	
		}
		
		$related_table = fORM::tablize($related_class);	
		
		$route = fORMSchema::getToManyRouteName($table, $related_table, $route);	
		
		$related_records[$related_table][$route] = $records;
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
?>