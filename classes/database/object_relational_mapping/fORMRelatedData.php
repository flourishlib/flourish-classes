<?php
/**
 * Handles related data tasks for (@link fActiveRecord} classes. Related data
 * only works for single-field foreign keys.
 * 
 * @copyright  Copyright (c) 2007-2008 William Bond
 * @author     William Bond [wb] <will@flourishlib.com>
 * @license    http://flourishlib.com/license
 * 
 * @link  http://flourishlib.com/fORMRelatedData
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
	 * Names for related records
	 * 
	 * @var array
	 */
	static private $related_record_names = array();
	
	
	/**
	 * Creates associations for many-to-many relationships
	 * 
	 * @internal
	 * 
	 * @param  mixed  $class             The class name or instance of the class to get the related values for
	 * @param  array  &$related_records  The related records existing for the {@link fActiveRecord} class
	 * @param  string $related_class     The class we are associating with the current record
	 * @param  array  $primary_keys      The primary keys of the records to be associated
	 * @param  string $route             The route to use between the current class and the related class
	 * @return void
	 */
	static public function associateRecords($class, &$related_records, $related_class, $primary_keys, $route=NULL)
	{
		// Remove blank values from the related records primary keys
		$new_primary_keys = array();
		foreach ($primary_keys as $primary_key) {
			if (empty($primary_key)) {
				continue;
			}	
			$new_primary_keys[] = $primary_key;
		}
		
		$records = fRecordSet::createFromPrimaryKeys($related_class, $primary_keys);
		self::setRecords($class, $related_records, $related_class, $records, $route);
		$records->flagForAssociation();
	}
	
	
	/**
	 * Builds the object for the related class specified
	 * 
	 * @internal
	 * 
	 * @param  mixed  $class          The class name or instance of the class to get the related values for
	 * @param  array  $values         The values existing in the {@link fActiveRecord} class
	 * @param  string $related_class  The related class name
	 * @param  string $route          The route to the related class
	 * @return fActiveRecord  An instace of the class specified
	 */
	static public function constructRecord($class, $values, $related_class, $route=NULL)
	{
		$table = fORM::tablize($class);
		
		$related_table = fORM::tablize($related_class);
		
		$relationship = fORMSchema::getRoute($table, $related_table, $route, '*-to-one');
		
		return new $related_class($values[$relationship['column']]);
	}
	
	
	/**
	 * Builds a sequence of related records along a one-to-many or many-to-many relationship
	 * 
	 * @internal
	 * 
	 * @param  mixed  $class             The class name or instance of the class to get the related values for
	 * @param  array  &$values           The values for the {@link fActiveRecord} class
	 * @param  array  &$related_records  The related records existing for the {@link fActiveRecord} class
	 * @param  string $related_class     The class that is related to the current record
	 * @param  string $route             The route to follow for the class specified
	 * @return array  An array of the related column values
	 */
	static public function constructRecordSet($class, &$values, &$related_records, $related_class, $route=NULL)
	{
		$table         = fORM::tablize($class);
		$related_table = fORM::tablize($related_class);
		
		$route = fORMSchema::getRouteName($table, $related_table, $route, '*-to-many');
		
		// If we already have the sequence, we can stop here
		if (isset($related_records[$related_table][$route])) {
			return $related_records[$related_table][$route];
		}
		
		$relationship = fORMSchema::getRoute($table, $related_table, $route, '*-to-many');
		
		// Determine how we are going to build the sequence
		if ($values[$relationship['column']] === NULL) {
			$record_set = fRecordSet::createEmpty($related_class);
		} else {
			$where_conditions = array($table . '.' . $relationship['column'] . '=' => $values[$relationship['column']]);
			$order_bys        = self::getOrderBys($class, $related_class, $route);
			$record_set       = fRecordSet::create($related_class, $where_conditions, $order_bys);
		}
		
		// Cache the results for subsequent calls
		if (!isset($related_records[$related_table])) {
			$related_records[$related_table] = array();
		}
		$related_records[$related_table][$route] = $record_set;
		
		return $record_set;
	}
	
	
	/**
	 * Gets the ordering to use when returning {@link fRecordSet fRecordSets} of related objects
	 *
	 * @internal
	 * 
	 * @param  mixed  $class          The class name or instance of the class this ordering rule applies to
	 * @param  string $related_class  The related class the ordering rules apply to
	 * @param  string $route          The route to the related table, should be a column name in the current table or a join table name
	 * @return array  An array of the order bys (see {@link fRecordSet::create()} for format)
	 */
	static public function getOrderBys($class, $related_class, $route)
	{
		$table         = fORM::tablize($class);
		$related_table = fORM::tablize($related_class);
		
		$route = fORMSchema::getRouteName($table, $related_table, $route);
		
		if (!isset(self::$order_bys[$table][$related_table]) || !isset(self::$order_bys[$table][$related_table][$route])) {
			return array();
		}
		
		return self::$order_bys[$table][$related_table][$route];
	}
	
	
	/**
	 * Returns the record name for a related class. The default record name is a
	 * humanized version of the class name.
	 * 
	 * @internal
	 * 
	 * @param  mixed $class          The class name or instance of the class to get the related class name for
	 * @param  mixed $related_class  The related class/class name to get the record name of
	 * @return string  The record name for the related class specified
	 */
	static public function getRelatedRecordName($class, $related_class, $route=NULL)
	{
		$table = fORM::tablize($class);
		
		if (is_object($related_class)) {
			$related_class = get_class($related_class);	
		}
		
		$related_table = fORM::tablize($related_class);
		
		$route = fORMSchema::getRouteName($table, $related_table, $route, '*-to-many');
		
		if (!isset(self::$related_record_names[$table]) ||
			  !isset(self::$related_record_names[$table][$related_class]) ||
			  !isset(self::$related_record_names[$table][$related_class][$route])) {
			return fORM::getRecordName($related_class);	
		}
		
		return self::$related_record_names[$table][$related_class][$route];
	}
	
	
	/**
	 * Parses associations for many-to-many relationships from the page request
	 * 
	 * @internal
	 * 
	 * @param  mixed  $class             The class name or instance of the class to get the related values for
	 * @param  array  &$related_records  The related records existing for the {@link fActiveRecord} class
	 * @param  string $related_class     The related class to populate
	 * @param  string $route             The route to the related class
	 * @return void
	 */
	static public function linkRecords($class, &$related_records, $related_class, $route=NULL)
	{
		$table         = fORM::tablize($class);
		$related_table = fORM::tablize($related_class);
		
		$route_name   = fORMSchema::getRouteName($table, $related_table, $route, 'many-to-many');
		$relationship = fORMSchema::getRoute($table, $related_table, $route, 'many-to-many');
		
		$field_table      = $relationship['related_table'];
		$field_column     = '::' . $relationship['related_column'];
		
		$field            = $field_table . $field_column;
		$field_with_route = $field_table . '{' . $route_name . '}' . $field_column;
		
		// If there is only one route and they specified the route instead of leaving it off, use that
		if ($route === NULL && !fRequest::check($field) && fRequest::check($field_with_route)) {
			$field = $field_with_route;	
		}
		
		$primary_keys = fRequest::get($field, 'array', array());
		self::associateRecords($class, $related_records, $related_class, $primary_keys, $route);
	}
	
	
	/**
	 * Allows overriding of default (humanize-d class name) record names or related records
	 * 
	 * @param  mixed  $class          The class name or instance of the class to set the related record name for
	 * @param  mixed  $related_class  The name of the related class, or an instance of it
	 * @param  string $record_name    The human version of the related record
	 * @param  string $route          The route to the related class
	 * @return void
	 */
	static public function overrideRelatedRecordName($class, $related_class, $record_name, $route=NULL)
	{
		$table = fORM::tablize($class);
		
		if (is_object($related_class)) {
			$related_class = get_class($related_class);	
		}
		
		$related_table = fORM::tablize($related_class);
		
		$route = fORMSchema::getRouteName($table, $related_table, $route, '*-to-many');
		
		if (!isset(self::$related_record_names[$table])) {
			self::$related_record_names[$table] = array();	
		}
		
		if (!isset(self::$related_record_names[$table][$related_class])) {
			self::$related_record_names[$table][$related_class] = array();	
		}
		
		self::$related_record_names[$table][$related_class][$route] = $record_name;
	}
	
	
	/**
	 * Sets the values for records in a one-to-many relationship with this record
	 * 
	 * @throws fValidationException
	 * @internal
	 * 
	 * @param  mixed  $class             The class name or instance of the class to get the related values for
	 * @param  array  &$related_records  The related records existing for the {@link fActiveRecord} class
	 * @param  string $related_class     The related class to populate
	 * @param  string $route             The route to the related class
	 * @return void
	 */
	static public function populateRecords($class, &$related_records, $related_class, $route=NULL)
	{
		$table = fORM::tablize($class);
		
		$related_table = fORM::tablize($related_class);
		
		$route_name   = fORMSchema::getRouteName($table, $related_table, $route);
		$relationship = fORMSchema::getRoute($table, $related_table, $route);
		
		$pk_columns   = fORMSchema::getInstance()->getKeys($related_table, 'primary');
		
		$filter_table            = $related_table;
		$filter_table_with_route = $related_table . '{' . $route_name . '}';
		
		$first_pk_column     = $pk_columns[0];
		$pk_field            = $filter_table . '::' . $first_pk_column;
		$pk_field_with_route = $filter_table_with_route . '::' . $first_pk_column;
		
		// If there is only a single relationship, but the user specified the route, use that
		if ($route === NULL && !fRequest::check($pk_field) && fRequest::check($pk_field_with_route)) {
			$pk_field     = $pk_field_with_route;
			$filter_table = $filter_table_with_route;	
		}
		
		$total_records = sizeof(fRequest::get($pk_field, 'array', array()));
		$records       = array();
		
		for ($i = 0; $i < $total_records; $i++) {
			fRequest::filter($filter_table . '::', $i);
			
			// Existing record are loaded out of the database before populating
			if (fRequest::get($first_pk_column) !== NULL) {
				if (sizeof($pk_columns) == 1) {
					$primary_key_values = fRequest::get($first_pk_column);
				} else {
					$primary_key_values = array();
					foreach ($pk_columns as $pk_column) {
						$primary_key_values[$pk_column] = fRequest::get($pk_column);
					}
				}
				$record = new $related_class($primary_key_values);
			
			// If we have a new record, created an empty object
			} else {
				$record = new $related_class();
			}
			
			$record->populate();
			$records[] = $record;
			
			fRequest::unfilter();
		}
		
		if (empty($records)) {
			$record_set = fRecordSet::createEmpty($related_class);	
		} else {
			$record_set = fRecordSet::createFromObjects($records);
		}
		
		$record_set->flagForAssociation();
		self::setRecords($class, $related_records, $related_class, $record_set, $route);
	}
	
	
	/**
	 * Sets the ordering to use when returning {@link fRecordSet fRecordSets} of related objects
	 *
	 * @param  mixed  $class           The class name or instance of the class this ordering rule applies to
	 * @param  string $related_class   The related class we are getting info from
	 * @param  string $route           The route to the related table, this should be a column name in the current table or a join table name
	 * @param  array  $order_bys       An array of the order bys for this table.column combination (see {@link fRecordSet::create()} for format)
	 * @return void
	 */
	static public function setOrderBys($class, $related_class, $route, $order_bys)
	{
		$table         = fORM::tablize($class);
		$related_table = fORM::tablize($related_class);
		
		$route = fORMSchema::getRouteName($table, $related_table, $route);
		
		if (!isset(self::$order_bys[$table])) {
			self::$order_bys[$table] = array();
		}
		
		if (!isset(self::$order_bys[$table][$related_table])) {
			self::$order_bys[$table][$related_table] = array();
		}
		
		self::$order_bys[$table][$related_table][$route] = $order_bys;
	}
	
	
	/**
	 * Sets the related records for many-to-many relationships
	 * 
	 * @internal
	 * 
	 * @param  mixed  $class             The class name or instance of the class to get the related values for
	 * @param  array  &$related_records  The related records existing for the {@link fActiveRecord} class
	 * @param  string $related_class     The class we are associating with the current record
	 * @param  fRecordSet $records       The records are associating
	 * @param  string $route             The route to use between the current class and the related class
	 * @return void
	 */
	static public function setRecords($class, &$related_records, $related_class, fRecordSet $records, $route=NULL)
	{
		$table         = fORM::tablize($class);
		$related_table = fORM::tablize($related_class);
		
		$route = fORMSchema::getRouteName($table, $related_table, $route, '*-to-many');
		
		$related_records[$related_table][$route] = $records;
	}
	
	
	/**
	 * Forces use as a static class
	 * 
	 * @return fORMRelatedData
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