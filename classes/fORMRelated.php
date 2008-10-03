<?php
/**
 * Handles related record tasks for (@link fActiveRecord} classes
 * 
 * The functionality only works with single-field foreign keys.
 * 
 * @copyright  Copyright (c) 2007-2008 William Bond
 * @author     William Bond [wb] <will@flourishlib.com>
 * @license    http://flourishlib.com/license
 * 
 * @package    Flourish
 * @link       http://flourishlib.com/fORMRelated
 * 
 * @version    1.0.0b
 * @changes    1.0.0b  The initial implementation [wb, 2007-12-30]
 */
class fORMRelated
{
	const associateRecords          = 'fORMRelated::associateRecords';
	const buildRecords              = 'fORMRelated::buildRecords';
	const countRecords              = 'fORMRelated::countRecords';
	const createRecord              = 'fORMRelated::createRecord';
	const determineRequestFilter    = 'fORMRelated::determineRequestFilter';
	const getOrderBys               = 'fORMRelated::getOrderBys';
	const getRelatedRecordName      = 'fORMRelated::getRelatedRecordName';
	const linkRecords               = 'fORMRelated::linkRecords';
	const overrideRelatedRecordName = 'fORMRelated::overrideRelatedRecordName';
	const populateRecords           = 'fORMRelated::populateRecords';
	const reflect                   = 'fORMRelated::reflect';
	const setOrderBys               = 'fORMRelated::setOrderBys';
	const setRecords                = 'fORMRelated::setRecords';
	const storeManyToMany           = 'fORMRelated::storeManyToMany';
	const storeOneToMany            = 'fORMRelated::storeOneToMany';
	const tallyRecords              = 'fORMRelated::tallyRecords';
	
	
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
	 * @param  mixed       $class                 The class name or instance of the class to get the related values for
	 * @param  array       &$related_records      The related records existing for the {@link fActiveRecord} class
	 * @param  string      $related_class         The class we are associating with the current record
	 * @param  fRecordSet  $records_to_associate  An fRecordSet of the records to be associated
	 * @param  string      $route                 The route to use between the current class and the related class
	 * @return void
	 */
	static public function associateRecords($class, &$related_records, $related_class, $records_to_associate, $route=NULL)
	{
		$records = clone $records_to_associate;
		$records->flagAssociate();
		self::setRecords($class, $related_records, $related_class, $records, $route);
	}
	
	
	/**
	 * Builds a set of related records along a one-to-many or many-to-many relationship
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
	static public function buildRecords($class, &$values, &$related_records, $related_class, $route=NULL)
	{
		$table         = fORM::tablize($class);
		$related_table = fORM::tablize($related_class);
		
		$route = fORMSchema::getRouteName($table, $related_table, $route, '*-to-many');
		
		// If we already have the sequence, we can stop here
		if (isset($related_records[$related_table][$route]['record_set'])) {
			return $related_records[$related_table][$route]['record_set'];
		}
		
		$relationship = fORMSchema::getRoute($table, $related_table, $route, '*-to-many');
		
		// Determine how we are going to build the sequence
		if ($values[$relationship['column']] === NULL) {
			$record_set = fRecordSet::buildFromRecords($related_class, array());
		
		} else {
			// When joining to the same table, we have to use a different column
			$same_class = $related_class == fORM::getClass($class);
			if ($same_class && isset($relationship['join_table'])) {
				$column = $table . '{' . $relationship['join_table'] . '}.' . $relationship['column'];
			} elseif ($same_class) {
				$column = $table . '{' . $route . '}.' . $relationship['related_column'];
			} else {
				$column = $table . '{' . $route . '}.' . $relationship['column'];
			}
			
			$where_conditions = array($column . '=' => $values[$relationship['column']]);
			$order_bys        = self::getOrderBys($class, $related_class, $route);
			$record_set       = fRecordSet::build($related_class, $where_conditions, $order_bys);
		}
		
		self::setRecords($class, $related_records, $related_class, $record_set, $route);
		
		return $record_set;
	}
	
	
	/**
	 * Counts the number of related one-to-many or many-to-many records
	 * 
	 * @internal
	 * 
	 * @param  mixed  $class             The class name or instance of the class to get the related values for
	 * @param  array  &$values           The values for the {@link fActiveRecord} class
	 * @param  array  &$related_records  The related records existing for the {@link fActiveRecord} class
	 * @param  string $related_class     The class that is related to the current record
	 * @param  string $route             The route to follow for the class specified
	 * @return integer  The number of related records
	 */
	static public function countRecords($class, &$values, &$related_records, $related_class, $route=NULL)
	{
		$table         = fORM::tablize($class);
		$related_table = fORM::tablize($related_class);
		
		$route = fORMSchema::getRouteName($table, $related_table, $route, '*-to-many');
		
		// If we already have the sequence, we can stop here
		if (isset($related_records[$related_table][$route]['count'])) {
			return $related_records[$related_table][$route]['count'];
		}
		
		$relationship = fORMSchema::getRoute($table, $related_table, $route, '*-to-many');
		
		// Determine how we are going to build the sequence
		if ($values[$relationship['column']] === NULL) {
			$count = 0;
		} else {
			$column = $table . '.' . $relationship['column'];
			$value  = fORMDatabase::escapeBySchema($table, $relationship['column'], $values[$relationship['column']], '=');
			
			$primary_keys = fORMSchema::getInstance()->getKeys($related_table, 'primary');
			$primary_keys = fORMDatabase::addTableToValues($related_table, $primary_keys);
			$primary_keys = join(', ', $primary_keys);
			
			$sql  = "SELECT count(" . $primary_keys . ") AS __flourish_count ";
			$sql .= "FROM :from_clause ";
			$sql .= "WHERE " . $column . $value;
			$sql .= ' :group_by_clause ';
			$sql .= 'ORDER BY ' . $column . ' ASC';
			
			$sql = fORMDatabase::insertFromAndGroupByClauses($table, $sql);
			
			$result = fORMDatabase::getInstance()->translatedQuery($sql);
			
			$count = ($result->getReturnedRows()) ? (int) $result->fetchScalar() : 0;
		}
		
		self::tallyRecords($class, $related_records, $related_class, $count, $route);
		
		return $count;
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
	static public function createRecord($class, $values, $related_class, $route=NULL)
	{
		$table = fORM::tablize($class);
		
		$related_table = fORM::tablize($related_class);
		
		$relationship = fORMSchema::getRoute($table, $related_table, $route, '*-to-one');
		
		return new $related_class($values[$relationship['column']]);
	}
	
	
	/**
	 * Figures out what filter to pass to {@link fRequest::filter()} for the specified related class
	 *
	 * @internal
	 * 
	 * @param  mixed  $class          The class name or instance of the main class
	 * @param  string $related_class  The related class being filtered for
	 * @param  string $route          The route to the related table
	 * @return string  The prefix to filter the request fields by
	 */
	static public function determineRequestFilter($class, $related_class, $route)
	{
		$table           = fORM::tablize($class);
		$related_table   = fORM::tablize($related_class);
		$relationship    = fORMSchema::getRoute($table, $related_table, $route);
		
		$route_name    	 = fORMSchema::getRouteNameFromRelationship('one-to-many', $relationship);
		
		$primary_keys    = fORMSchema::getInstance()->getKeys($related_table, 'primary');
		$first_pk_column = $primary_keys[0];
		
		$filter_table            = $related_table;
		$filter_table_with_route = $related_table . '{' . $route_name . '}';
		
		$pk_field            = $filter_table . '.' . $first_pk_column;
		$pk_field_with_route = $filter_table_with_route . '.' . $first_pk_column;
		
		if (!fRequest::check($pk_field) && fRequest::check($pk_field_with_route)) {
			$filter_table = $filter_table_with_route;
		}
		
		return $filter_table . '.';
	}
	
	
	/**
	 * Gets the ordering to use when returning {@link fRecordSet fRecordSets} of related objects
	 *
	 * @internal
	 * 
	 * @param  mixed  $class          The class name or instance of the class this ordering rule applies to
	 * @param  string $related_class  The related class the ordering rules apply to
	 * @param  string $route          The route to the related table, should be a column name in the current table or a join table name
	 * @return array  An array of the order bys (see {@link fRecordSet::build()} for format)
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
	 * Returns the record name for a related class - default is a humanized version of the class name
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
		
		$related_class = fORM::getClass($related_class);
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
		
		$route_name   = fORMSchema::getRouteName($table, $related_table, $route, '*-to-many');
		$relationship = fORMSchema::getRoute($table, $related_table, $route, 'many-to-many');
		
		$field_table      = $relationship['related_table'];
		$field_column     = '.' . $relationship['related_column'];
		
		$field            = $field_table . $field_column;
		$field_with_route = $field_table . '{' . $route_name . '}' . $field_column;
		
		// If there is only one route and they specified the route instead of leaving it off, use that
		if ($route === NULL && !fRequest::check($field) && fRequest::check($field_with_route)) {
			$field = $field_with_route;
		}
		
		$record_set = fRecordSet::build(
			$related_class,
			array(
				$field_with_route . '=' => fRequest::get($field, 'array', array())
			)
		);
		
		self::associateRecords($class, $related_records, $related_class, $record_set, $route);
	}
	
	
	/**
	 * Does an array_diff() for two arrays that have arrays as values
	 * 
	 * @param  array $array1  The array to remove items from
	 * @param  array $array2  The array of items to remove
	 * @return array  The items in $array1 that were not also in $array2
	 */
	static private function multidimensionArrayDiff($array1, $array2)
	{
		$output = array();
		foreach ($array1 as $sub_array1) {
			$remove = FALSE;
			foreach ($array2 as $sub_array2) {
				if ($sub_array1 == $sub_array2) {
					$remove = TRUE;
				}
			}
			if (!$remove) {
				$output[] = $sub_array1;
			}
		}
		return $output;
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
		
		$related_class = fORM::getClass($related_class);
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
		$related_table   = fORM::tablize($related_class);
		$pk_columns      = fORMSchema::getInstance()->getKeys($related_table, 'primary');
		
		// If there is a multi-fiend primary key we want to populate based on any field BUT the foreign key to the current class
		if (sizeof($pk_columns) > 1) {
		
			$first_pk_column = NULL;
			$relationships   = fORMSchema::getRoutes($related_table, fORM::tablize($class), '*-to-one');
			foreach ($pk_columns as $pk_column) {
				foreach ($relationships as $relationship) {
					if ($pk_column == $relationship['column']) {
						continue;
					}
					$first_pk_column = $pk_column;
					break 2;
				}	
			}
			
			if (!$first_pk_column) {
				$first_pk_column = $pk_columns[0];
			}
			
		} else {
			$first_pk_column = $pk_columns[0];
		}
		
		$filter          = self::determineRequestFilter($class, $related_class, $route);
		$pk_field        = $filter . $first_pk_column;
		
		$total_records = sizeof(fRequest::get($pk_field, 'array', array()));
		$records       = array();
		
		for ($i = 0; $i < $total_records; $i++) {
			fRequest::filter($filter, $i);
			
			// Try to load the value from the database first
			try {
				if (sizeof($pk_columns) == 1) {
					$primary_key_values = fRequest::get($first_pk_column);
				} else {
					$primary_key_values = array();
					foreach ($pk_columns as $pk_column) {
						$primary_key_values[$pk_column] = fRequest::get($pk_column);
					}
				}
				
				$record = new $related_class($primary_key_values);
				
			} catch (fNotFoundException $e) {
				$record = new $related_class();
			}
			
			$record->populate();
			$records[] = $record;
			
			fRequest::unfilter();
		}
		
		$record_set = fRecordSet::buildFromRecords($related_class, $records);
		$record_set->flagAssociate();
		self::setRecords($class, $related_records, $related_class, $record_set, $route);
	}
	
	
	/**
	 * Adds information about methods provided by this class to {@link fActiveRecord}
	 * 
	 * @internal
	 * 
	 * @param  mixed   $class                 The class name or instance of the class this ordering rule applies to
	 * @param  array   &$signatures           The associative array of {method_name} => {signature}
	 * @param  boolean $include_doc_comments  If the doc block comments for each method should be included
	 * @return void
	 */
	static public function reflect($class, &$signatures, $include_doc_comments)
	{
		$table = fORM::tablize($class);
		
		$one_to_one_relationships   = fORMSchema::getInstance()->getRelationships($table, 'one-to-one');
		$one_to_many_relationships  = fORMSchema::getInstance()->getRelationships($table, 'one-to-many');
		$many_to_one_relationships  = fORMSchema::getInstance()->getRelationships($table, 'many-to-one');
		$many_to_many_relationships = fORMSchema::getInstance()->getRelationships($table, 'many-to-many');
		
		$to_one_relationships  = array_merge($one_to_one_relationships, $many_to_one_relationships);
		$to_many_relationships = array_merge($one_to_many_relationships, $many_to_many_relationships);
		
		$to_one_created = array();
		
		foreach ($to_one_relationships as $relationship) {
			$related_class = fORM::classize($relationship['related_table']);
			
			if (isset($to_one_created[$related_class])) {
				continue;
			}
			
			$routes = fORMSchema::getRoutes($table, $relationship['related_table'], '*-to-one');
			$route_names = array();
			
			foreach ($routes as $route) {
				$route_names[] = fORMSchema::getRouteNameFromRelationship('one-to-one', $route);
			}
			
			$signature = '';
			if ($include_doc_comments) {
				$signature .= "/**\n";
				$signature .= " * Creates the related " . $related_class . "\n";
				$signature .= " * \n";
				if (sizeof($route_names) > 1) {
					$signature .= " * @param  string \$route  The route to the related class. Must be one of: '" . join("', '", $route_names) . "'.\n";
				}
				$signature .= " * @return " . $related_class . "  The related object\n";
				$signature .= " */\n";
			}
			$create_method = 'create' . $related_class;
			$signature .= 'public function ' . $create_method . '(';
			if (sizeof($route_names) > 1) {
				$signature .= '$route';
			}
			$signature .= ')';
			
			$signatures[$create_method] = $signature;
			
			$to_one_created[$related_class] = TRUE;
		}
		
		$to_many_created = array();
		
		foreach ($to_many_relationships as $relationship) {
			$related_class = fORM::classize($relationship['related_table']);
			
			if (isset($to_many_created[$related_class])) {
				continue;
			}
			
			$routes = fORMSchema::getRoutes($table, $relationship['related_table'], '*-to-many');
			$route_names = array();
			
			foreach ($routes as $route) {
				if (isset($relationship['join_table'])) {
					$route_names[] = fORMSchema::getRouteNameFromRelationship('many-to-many', $route);
				} else {
					$route_names[] = fORMSchema::getRouteNameFromRelationship('one-to-many', $route);
				}
			}
			
			$signature = '';
			if ($include_doc_comments) {
				$signature .= "/**\n";
				$signature .= " * Builds an fRecordSet of the related " . $related_class . " objects\n";
				$signature .= " * \n";
				if (sizeof($route_names) > 1) {
					$signature .= " * @param  string \$route  The route to the related class. Must be one of: '" . join("', '", $route_names) . "'.\n";
				}
				$signature .= " * @return fRecordSet  A record set of the related " . $related_class . " objects\n";
				$signature .= " */\n";
			}
			$build_method = 'build' . fGrammar::pluralize($related_class);
			$signature .= 'public function ' . $build_method . '(';
			if (sizeof($route_names) > 1) {
				$signature .= '$route';
			}
			$signature .= ')';
			
			$signatures[$build_method] = $signature;
			
			
			$signature = '';
			if ($include_doc_comments) {
				$signature .= "/**\n";
				$signature .= " * Counts the number of related " . $related_class . " objects\n";
				$signature .= " * \n";
				if (sizeof($route_names) > 1) {
					$signature .= " * @param  string \$route  The route to the related class. Must be one of: '" . join("', '", $route_names) . "'.\n";
				}
				$signature .= " * @return integer  The number related " . $related_class . " objects\n";
				$signature .= " */\n";
			}
			$count_method = 'count' . fGrammar::pluralize($related_class);
			$signature .= 'public function ' . $count_method . '(';
			if (sizeof($route_names) > 1) {
				$signature .= '$route';
			}
			$signature .= ')';
			
			$signatures[$count_method] = $signature;
			
			
			$to_many_created[$related_class] = TRUE;
		}
	}
	
	
	/**
	 * Sets the ordering to use when returning an {@link fRecordSet} of related objects
	 *
	 * @param  mixed  $class           The class name or instance of the class this ordering rule applies to
	 * @param  string $related_class   The related class we are getting info from
	 * @param  string $route           The route to the related table, this should be a column name in the current table or a join table name
	 * @param  array  $order_bys       An array of the order bys for this table.column combination (see {@link fRecordSet::build()} for format)
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
		
		if (!isset($related_records[$related_table])) {
			$related_records[$related_table] = array();
		}
		if (!isset($related_records[$related_table][$route])) {
			$related_records[$related_table][$route] = array();
		}
		
		$related_records[$related_table][$route]['record_set'] = $records;
		$related_records[$related_table][$route]['count']      = $records->count();
	}
	
	
	/**
	 * Associates a set of many-to-many related records with the current record
	 * 
	 * @throws fValidationException
	 * @internal
	 * 
	 * @param  array      &$values       The current values for the main record being stored
	 * @param  array      $relationship  The information about the relationship between this object and the records in the record set
	 * @param  fRecordSet $record_set    The set of records to associate
	 * @return void
	 */
	static public function storeManyToMany(&$values, $relationship, $record_set)
	{
		$column_value      = $values[$relationship['column']];
		
		// First, we remove all existing relationships between the two tables
		$join_table        = $relationship['join_table'];
		$join_column       = $relationship['join_column'];
		
		$join_column_value = fORMDatabase::escapeBySchema($join_table, $join_column, $column_value);
		
		$delete_sql  = 'DELETE FROM ' . $join_table;
		$delete_sql .= ' WHERE ' . $join_column . ' = ' . $join_column_value;
		
		fORMDatabase::getInstance()->translatedQuery($delete_sql);
		
		// Then we add back the ones in the record set
		$join_related_column     = $relationship['join_related_column'];
		$get_related_method_name = 'get' . fGrammar::camelize($relationship['related_column'], TRUE);
		
		foreach ($record_set as $record) {
			$related_column_value = fORMDatabase::escapeBySchema($join_table, $join_related_column, $record->$get_related_method_name());
			
			$insert_sql  = 'INSERT INTO ' . $join_table . ' (' . $join_column . ', ' . $join_related_column . ') ';
			$insert_sql .= 'VALUES (' . $join_column_value . ', ' . $related_column_value . ')';
			
			fORMDatabase::getInstance()->translatedQuery($insert_sql);
		}
	}
	
	
	/**
	 * Stores a set of one-to-many related records in the database
	 * 
	 * @throws fValidationException
	 * @internal
	 * 
	 * @param  array      &$values       The current values for the main record being stored
	 * @param  array      $relationship  The information about the relationship between this object and the records in the record set
	 * @param  fRecordSet $record_set    The set of records to store
	 * @return void
	 */
	static public function storeOneToMany(&$values, $relationship, $record_set)
	{
		$column_value = $values[$relationship['column']];
		
		$where_conditions = array(
			$relationship['related_column'] . '=' => $column_value
		);
		
		$related_class    = $record_set->getClass();
		$existing_records = fRecordSet::build($related_class, $where_conditions);
		
		$existing_primary_keys  = $existing_records->getPrimaryKeys();
		$new_primary_keys       = $record_set->getPrimaryKeys();
		
		$primary_keys_to_delete = self::multidimensionArrayDiff($existing_primary_keys, $new_primary_keys);
		
		foreach ($primary_keys_to_delete as $primary_key_to_delete) {
			$object_to_delete = new $related_class($primary_key_to_delete);
			$object_to_delete->delete();
		}
		
		$set_method_name = 'set' . fGrammar::camelize($relationship['related_column'], TRUE);
		
		$record_number = 0;
		$filter        = self::determineRequestFilter(fORM::classize($relationship['table']), $related_class, $relationship['related_column']);
		
		foreach ($record_set as $record) {
			fRequest::filter($filter, $record_number);
			$record->$set_method_name($column_value);
			$record->store();
			fRequest::unfilter();
			$record_number++;
		}
	}
	
	
	/**
	 * Records the number of related one-to-many or many-to-many records
	 * 
	 * @internal
	 * 
	 * @param  mixed   $class             The class name or instance of the class to get the related values for
	 * @param  array   &$values           The values for the {@link fActiveRecord} class
	 * @param  array   &$related_records  The related records existing for the {@link fActiveRecord} class
	 * @param  string  $related_class     The class that is related to the current record
	 * @param  integer $count             The number of records
	 * @param  string  $route             The route to follow for the class specified
	 * @return void
	 */
	static public function tallyRecords($class, &$related_records, $related_class, $count, $route=NULL)
	{
		$table         = fORM::tablize($class);
		$related_table = fORM::tablize($related_class);
		
		$route = fORMSchema::getRouteName($table, $related_table, $route, '*-to-many');
		
		// Cache the results for subsequent calls
		if (!isset($related_records[$related_table])) {
			$related_records[$related_table] = array();
		}
		if (!isset($related_records[$related_table][$route])) {
			$related_records[$related_table][$route] = array();
		}
		
		if (!isset($related_records[$related_table][$route]['record_set'])) {
			$related_records[$related_table][$route]['record_set'] = NULL;
		}
		$related_records[$related_table][$route]['count'] = $count;
		
	}
	
	
	/**
	 * Forces use as a static class
	 * 
	 * @return fORMRelated
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