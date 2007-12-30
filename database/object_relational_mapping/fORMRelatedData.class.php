<?php
/**
 * Handles related data tasks for (@link fActiveRecord} classes. Related data only works for single-field foreign keys.
 * 
 * @copyright  Copyright (c) 2007 William Bond
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
 * @uses  fSet
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
	 * Sets the ordering to use when returning {@link fSet fSets} of related objects
	 *
	 * @param  mixed  $table                  The database table (or {@link fActiveRecord} class) this ordering rule applies to
	 * @param  string $plural_related_column  The plural form of the related column this ordering rule applies to
	 * @param  array  $order_bys              An array of the order bys for this table.column combination (see {@link fSet::create()} for format)
	 * @return void
	 */
	static public function setOrderBys($table, $plural_related_column, $order_bys)
	{
		if (is_object($table)) {
			$table = fORM::tablize($table);	
		}
		
		if (!isset(self::$order_bys[$table])) {
			self::$order_bys[$table] = array();		
		}
		
		self::$order_bys[$table][$plural_related_column] = $order_bys;
	}
	
	
	/**
	 * Gets the ordering to use when returning {@link fSet fSets} of related objects
	 *
	 * @param  mixed  $table                  The database table (or {@link fActiveRecord} class) this ordering rule applies to
	 * @param  string $plural_related_column  The plural form of the related column this ordering rule applies to
	 * @return array  An array of the order bys for this table.column combination (see {@link fSet::create()} for format)
	 */
	static public function getOrderBys($table, $plural_related_column)
	{
		if (is_object($table)) {
			$table = fORM::tablize($table);	
		}
		
		if (!isset(self::$order_bys[$table][$plural_related_column])) {
			return array();		
		}
		
		return self::$order_bys[$table][$plural_related_column];
	}
	
	
	/**
	 * Retrieves an array of values from one-to-many and many-to-many relationships
	 * 
	 * @param  mixed  $table                   The database table (or {@link fActiveRecord} class) to get the related values for
	 * @param  array  &$values                 The values existing in the {@link fActiveRecord} class
	 * @param  string $plural_related_column   The plural form of the related column name
	 * @return array  An array of the related column values
	 */
	public function retrieveValues($table, &$values, $plural_related_column)
	{
		if (is_object($table)) {
			$table = fORM::tablize($table);	
		}
		
		$related_column = fInflection::singularize($plural_related_column);
		$relationships = fORMSchema::getInstance()->getRelationships($table);
		
		// Handle one-to-many values
		foreach ($relationships['one-to-many'] as $rel) {
			$rel_primary_keys = fORMSchema::getInstance()->getKeys($rel['related_table'], 'primary');
			
			// This enforces the restriction that related data only currently work for single field foreign keys
			if ($rel_primary_keys != array($related_column)) {
				continue;	
			}
			
			$rel_primary_key = fORMDatabase::addTableToValues($rel['related_table'], $rel_primary_keys[0]);
			
			$sql  = "SELECT " . $rel_primary_key . " FROM :from_clause";
			$sql .= " WHERE " . $rel['related_table'] . '.' . $rel['related_column'] . ' = ' . fORMDatabase::prepareBySchema($table, $rel['column'], $values[$rel['column']]);
			
			$order_bys = self::getOrderBys($table, fInflection::pluralize($rel_primary_keys[0]));
			if (!empty($order_bys)) {
				$sql .= ' ORDER BY ' . fORMDatabase::createOrderByClause($table, $order_bys);				
			}
			
			$sql = fORMDatabase::insertFromClause($rel['related_table'], $sql);
			
			$result = fORMDatabase::getInstance()->translatedQuery($sql);
			return fORMDatabase::condensePrimaryKeyArray($result->fetchAllRows());	
		}
		
		
		// Handle many-to-many values
		if (isset($values[$plural_related_column])) {
			return $values[$plural_related_column];	
		}
		
		foreach ($relationships['many-to-many'] as $rel) {
			$rel_primary_keys = fORMSchema::getInstance()->getKeys($rel['related_table'], 'primary');
			
			// This enforces the restriction that related data only currently work for single field foreign keys
			if ($rel_primary_keys != array($related_column)) {
				continue;	
			}
			
			$rel_primary_key = fORMDatabase::addTableToValues($rel['related_table'], $rel_primary_keys[0]);
			
			$sql  = "SELECT " . $rel_primary_key . " FROM :from_clause";
			$sql .= " WHERE " . $table . '.' . $rel['column'] . ' = ' . fORMDatabase::prepareBySchema($table, $rel['column'], $values[$rel['column']]);
			
			$order_bys = self::getOrderBys($table, fInflection::pluralize($rel_primary_keys[0]));
			if (!empty($order_bys)) {
				$sql .= ' ORDER BY ' . fORMDatabase::createOrderByClause($table, $order_bys);				
			}
			
			$sql = fORMDatabase::insertFromClause($rel['related_table'], $sql);
			
			$result = fORMDatabase::getInstance()->translatedQuery($sql);
			$values[$plural_related_column] = fORMDatabase::condensePrimaryKeyArray($result->fetchAllRows());
			return $values[$plural_related_column];
		}
		
		fCore::toss('fProgrammerException', 'The related column name you specified, ' . $plural_related_column . ', could not be found');	
	}
	
	
	/**
	 * Sets values for many-to-many relationships
	 * 
	 * @param  mixed  $table                   The database table (or {@link fActiveRecord} class) to get the related values for
	 * @param  array  &$values                 The values existing in the {@link fActiveRecord} class
	 * @param  string $plural_related_column   The plural form of the related column name
	 * @param  array  $column_values           The values for the column specified
	 * @return void
	 */
	public function assignValues($table, &$values, $plural_related_column, $column_values)
	{
		if (is_object($table)) {
			$table = fORM::tablize($table);	
		}
		
		settype($values, 'array');
		$related_column = fInflection::singularize($plural_related_column);
		$relationships  = fORMSchema::getInstance()->getRelationships($table, 'many-to-many');
		
		foreach ($relationships as $rel) {
			$rel_primary_keys = fORMSchema::getInstance()->getKeys($rel['related_table'], 'primary');
			
			if ($rel_primary_keys != array($related_column)) {
				continue;	
			}
			
			$values[$plural_related_column] = $values;
		}
		
		fCore::toss('fProgrammerException', 'The related column name you specified, ' . $plural_related_column . ', is not in a many-to-many relationship with the current table, ' . $table);	
	}
	
	
	/**
	 * Builds the object for the related class specified
	 * 
	 * @param  mixed  $table           The database table (or {@link fActiveRecord} class) to get the related values for
	 * @param  array  $values          The values existing in the {@link fActiveRecord} class
	 * @param  string $related_class   The related class name
	 * @return fActiveRecord  An instace of the class specified
	 */
	public function buildObject($table, $values, $related_class)
	{
		if (is_object($table)) {
			$table = fORM::tablize($table);	
		}
		
		$relationships = fORMSchema::getInstance()->getRelationships($table);
		
		$search_relationships = array_merge($relationships['one-to-one'], $relationships['many-to-one']);
		foreach ($search_relationships as $rel) {
			if ($related_class == fORM::classize($rel['related_table'])) {
				$class = fORM::classize($rel['related_table']);	
				break;
			}
		}
		
		if (empty($class)) {
			fCore::toss('fProgrammerException', 'The related class name you specified, ' . $related_class . ', could not be found');
		}
		
		return new $class($values[$rel['column']]);	
	}
	
	
	/**
	 * Retrieves a set of values from one-to-many and many-to-many relationships
	 * 
	 * @param  object $object                  The {@link fActiveRecord} object to create the {@link fSet} from
	 * @param  string $plural_related_column   The plural form of the related column name
	 * @return fSet  The set of objects from the specified column
	 */
	public function buildSet($object, $plural_related_column)
	{
		$related_column = fInflection::singularize($plural_related_column);
		$relationships = fORMSchema::getInstance()->getRelationships(fORM::tablize($object));
		
		$search_relationships = array_merge($relationships['one-to-many'], $relationships['many-to-many']);
		foreach ($search_relationships as $rel) {
			$rel_primary_keys = fORMSchema::getInstance()->getKeys($rel['related_table'], 'primary');
			
			if ($rel_primary_keys == array($related_column)) {
				$class = fORM::classize($rel['related_table']);	
				break;
			}
		}
		
		if (empty($class)) {
			fCore::toss('fProgrammerException', 'The related column name you specified, ' . $plural_related_column . ', could not be found');
		}
		
		$find_method = 'find' . fInflection::camelize($plural_related_column);
		
		return fSet::createFromPrimaryKeys($class, $object->$find_method());	
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