<?php
/**
 * Allows a column in an {@link fActiveRecord} class to be a relative sort order column
 * 
 * @copyright  Copyright (c) 2008 William Bond
 * @author     William Bond [wb] <will@flourishlib.com>
 * @license    http://flourishlib.com/license
 * 
 * @package    Flourish
 * @link       http://flourishlib.com/fORMOrdering
 * 
 * @version    1.0.0b
 * @changes    1.0.0b  The initial implementation [wb, 2008-06-25]
 */
class fORMOrdering
{
	/**
	 * The columns configured as ordering columns
	 * 
	 * @var array
	 */
	static private $ordering_columns = array();
	
	
	/**
	 * Sets a column to be an ordering column
	 * 
	 * There can only be one ordering column per class/table and it must be
	 * part of a single or multi-column unique constraint.
	 * 
	 * @param  mixed  $class   The class name or instance of the class
	 * @param  string $column  The column to set as an ordering column
	 * @return void
	 */
	static public function configureOrderingColumn($class, $column)
	{
		$class       = fORM::getClassName($class);
		$table       = fORM::tablize($class);
		$data_type   = fORMSchema::getInstance()->getColumnInfo($table, $column, 'type');
		$unique_keys = fORMSchema::getInstance()->getKeys($table, 'unique');
		
		if ($data_type != 'integer') {
			fCore::toss(
				'fProgrammerException',
				fGrammar::compose(
					'The column specified, %s, is a %s column. It must be an integer column to be set as an ordering column.',
					fCore::dump($column),
					$data_type
				)
			);	
		}
		
		$found = FALSE;
		foreach ($unique_keys as $unique_key) {
			if (array_search($column, $unique_key) !== FALSE) {
				$other_columns = array_diff($unique_key, array($column));
				$found = TRUE;
				break;
			}		
		}
		
		if (!$found) {
			fCore::toss(
				'fProgrammerException',
				fGrammar::compose(
					'The column specified, %s, does not appear to be part of a unique key. It must be part of a unique key to be set as an ordering column.',
					fCore::dump($column)
				)
			);	
		}
		
		$camelized_column = fGrammar::camelize($column, TRUE);
		
		$hook     = 'replace::inspect' . $camelized_column . '()';
		$callback = array('fORMOrdering', 'inspect');
		fORM::registerHookCallback($class, $hook, $callback);
		
		$hook     = 'post::validate()';
		$callback = array('fORMOrdering', 'validate');
		fORM::registerHookCallback($class, $hook, $callback);
		
		$hook     = 'post-validate::store()';
		$callback = array('fORMOrdering', 'reorder');
		fORM::registerHookCallback($class, $hook, $callback);
		
		$hook     = 'pre-commit::delete()';
		$callback = array('fORMOrdering', 'delete');
		fORM::registerHookCallback($class, $hook, $callback);
		
		// Ensure we only ever have one ordering column by overwriting
		self::$ordering_columns[$class]['column']        = $column;	
		self::$ordering_columns[$class]['other_columns'] = $other_columns;
	}
	
	
	/**
	 * Creates a WHERE clause for the OLD multi-column set a record was part of
	 * 
	 * @param  string $table          The table the WHERE clause is for
	 * @param  array  $other_columns  The other columns in the multi-column unique constraint
	 * @param  array  &$values        The record's current values
	 * @param  array  &$old_values    The record's old values
	 * @return string  An SQL WHERE clause for the other columns in a multi-column unique constraint
	 */
	static private function createOldOtherFieldsWhereClause($table, $other_columns, &$values, &$old_values)
	{
		$conditions = array();
		foreach ($other_columns as $other_column) {
			$other_value  = (isset($old_values[$other_column])) ? $old_values[$other_column][0] : $values[$other_column];
			$conditions[] = $other_column . fORMDatabase::prepareBySchema($table, $other_column, $other_value, '=');
		}
		
		return join(' AND ', $conditions);
	}
	
	
	/**
	 * Creates a WHERE clause to ensure a database call is only selecting from rows that are part of the same set when an ordering field is in multi-column unique constraint.
	 * 
	 * @param  string $table          The table the WHERE clause is for
	 * @param  array  $other_columns  The other columns in the multi-column unique constraint
	 * @param  array  &$values        The values to match with
	 * @return string  An SQL WHERE clause for the other columns in a multi-column unique constraint
	 */
	static private function createOtherFieldsWhereClause($table, $other_columns, &$values)
	{
		$conditions = array();
		foreach ($other_columns as $other_column) {
			$conditions[] = $other_column . fORMDatabase::prepareBySchema($table, $other_column, $values[$other_column], '=');
		}		
		
		return join(' AND ', $conditions);
	}
	
	
	/**
	 * Re-orders other recrods in the set when the record specified is deleted
	 * 
	 * @internal
	 * 
	 * @param  fActiveRecord $object            The fActiveRecord instance
	 * @param  array         &$values           The current values
	 * @param  array         &$old_values       The old values
	 * @param  array         &$related_records  Any records related to this record
	 * @return string  The formatted link
	 */
	static public function delete($object, &$values, &$old_values, &$related_records)
	{              
		$class = get_class($object);
		$table = fORM::tablize($class);
		
		$column        = self::$ordering_columns[$class]['column'];
		$other_columns = self::$ordering_columns[$class]['other_columns'];
		
		$current_value = $values[$column];
		$old_value     = (isset($old_values[$column])) ? $old_values[$column][0] : NULL;
		
		// Figure out the range we are dealing with
		$sql = "SELECT max(" . $column . ") FROM " . $table;
		if ($other_columns) {
			$sql .= " WHERE " . self::createOtherFieldsWhereClause($table, $other_columns, $values);
		}
		
		$current_max_value = (integer) fORMDatabase::getInstance()->translatedQuery($sql)->fetchScalar();
		
		$shift_down = $current_max_value + 10;
		$shift_up   = $current_max_value + 9;
		
		// Close the gap for all records after this one in the set
		$sql  = "UPDATE " . $table . " SET " . $column . ' = ' . $column . ' - ' . $shift_down . ' ';
		$sql .= 'WHERE ' . $column . ' > ' . (($old_value) ? $old_value : $current_value);
		if ($other_columns) {
			$sql .= " AND " . self::createOldOtherFieldsWhereClause($table, $other_columns, $values, $old_values);
		}
		
		fORMDatabase::getInstance()->translatedQuery($sql);
		
		// Close the gap for all records after this one in the set
		$sql  = "UPDATE " . $table . " SET " . $column . ' = ' . $column . ' + ' . $shift_up . ' ';
		$sql .= 'WHERE ' . $column . ' < 0';
		if ($other_columns) {
			$sql .= " AND " . self::createOldOtherFieldsWhereClause($table, $other_columns, $values, $old_values);
		}
		
		fORMDatabase::getInstance()->translatedQuery($sql);
	}
	
	
	/**
	 * Returns the metadata about a column including features added by this class
	 * 
	 * @internal
	 * 
	 * @param  fActiveRecord $object            The fActiveRecord instance
	 * @param  array         &$values           The current values
	 * @param  array         &$old_values       The old values
	 * @param  array         &$related_records  Any records related to this record
	 * @param  string        &$method_name      The method that was called
	 * @param  array         &$parameters       The parameters passed to the method
	 * @return mixed  The metadata array or element specified
	 */
	static public function inspect($object, &$values, &$old_values, &$related_records, &$method_name, &$parameters)
	{
		list ($action, $column) = explode('_', fGrammar::underscorize($method_name), 2);
		
		$class = get_class($object);
		$table = fORM::tablize($class);
		
		$info       = fORMSchema::getInstance()->getColumnInfo($table, $column);
		$element    = (isset($parameters[0])) ? $parameters[0] : NULL;
		
		$column        = self::$ordering_columns[$class]['column'];
		$other_columns = self::$ordering_columns[$class]['other_columns'];
		
		// Retrieve the current max ordering index from the database
		$sql = "SELECT max(" . $column . ") FROM " . $table;
		if ($other_columns) {
			$sql .= " WHERE " . self::createOtherFieldsWhereClause($table, $other_columns, $values);
		}
		$max_index = (integer) fORMDatabase::getInstance()->translatedQuery($sql)->fetchScalar();
		
		// If this is a new record, or in a new set, we need one more space in the ordering index
		if (self::isInNewSet($column, $other_columns, $values, $old_values)) {
			$max_index += 1;	
		}
		
		$info['max_ordering_index'] = $max_index;
		$info['feature']            = 'ordering';
				
		if ($element) {
			return (isset($info[$element])) ? $info[$element] : NULL;	
		}
		
		return $info;
	}
	
	
	/**
	 * Checks to see if the values specified are part of a record that is new to its order set
	 * 
	 * @param  string $ordering_column  The column being ordered by
	 * @param  array  $other_columns    The other columns in the multi-column unique constraint
	 * @param  array  &$values          The values of the record
	 * @param  array  &$old_values      The old values of the record
	 * @return boolean  If the record is part of a new ordering set
	 */
	static private function isInNewSet($ordering_column, $other_columns, &$values, &$old_values)
	{
		$value_empty      = !$values[$ordering_column];
		$old_value_empty  = (isset($old_values[$ordering_column])) ? !$old_values[$ordering_column][0] : FALSE;
		$no_old_value_set = !isset($old_values[$ordering_column]);
		
		// If the value appears to be new, the record must be new to the order
		if ($old_value_empty || ($value_empty && $no_old_value_set)) {
			return TRUE;
		}
		
		// If there aren't any other columns to check, there is
		// only a single order, so it must have already existed
		if (!$other_columns) {
			return FALSE;
		}
		
		// Check through each of the other columns to see if the set could have
		// changed because of a new value in one of those columns
		foreach ($other_columns as $other_column) {
			if (isset($old_values[$other_column]) && $old_values[$other_column][0] != $values[$other_column]) {
				return TRUE;	
			}		
		}
		
		// If none of the multi-column values changed, the record must be part
		// of the same set it was
		return FALSE;		
	}
	
	
	/**
	 * Re-orders the object based on it's current state and new position
	 * 
	 * @internal
	 * 
	 * @param  fActiveRecord $object            The fActiveRecord instance
	 * @param  array         &$values           The current values
	 * @param  array         &$old_values       The old values
	 * @param  array         &$related_records  Any records related to this record
	 * @return string  The formatted link
	 */
	static public function reorder($object, &$values, &$old_values, &$related_records)
	{
		$class = get_class($object);
		$table = fORM::tablize($class);
		
		$column        = self::$ordering_columns[$class]['column'];
		$other_columns = self::$ordering_columns[$class]['other_columns'];
		
		$current_value = $values[$column];
		$old_value     = (isset($old_values[$column])) ? $old_values[$column][0] : NULL;
		
		// Figure out the range we are dealing with
		$sql = "SELECT max(" . $column . ") FROM " . $table;
		if ($other_columns) {
			$sql .= " WHERE " . self::createOtherFieldsWhereClause($table, $other_columns, $values);
		}
		
		$current_max_value = (integer) fORMDatabase::getInstance()->translatedQuery($sql)->fetchScalar();
		$new_max_value     = $current_max_value;
		
		if ($new_set = self::isInNewSet($column, $other_columns, $values, $old_values)) {
			$new_max_value = $current_max_value + 1;	
		}
		
		// If a blank value was set, correct it to the old value (if there
		// was one), or a new value at the end of the set
		if ($current_value === '' || $current_value === NULL) {
			if ($old_value) {
				$values[$column] = $current_value = $old_value;
			} else {
				$values[$column] = $current_value = $new_max_value;	
			}
		}
		
		// If the value didn't change, we can exit
		$value_didnt_change = ($old_value && $current_value == $old_value) || !$old_value;
		if (!$new_set && $value_didnt_change) {
			return;
		}
		
		// If we are entering a new record at the end of the set we don't need to shuffle anything either
		if (!$object->exists() && $new_set && $current_value == $new_max_value) {
			return;	
		}	
		
		
		// If the object already exists in the database, grab the ordering value
		// right now in case some other object reordered it since it was loaded
		if ($object->exists()) {
			$sql  = "SELECT " . $column . " FROM " . $table . " WHERE ";
			$sql .= fORMDatabase::createPrimaryKeyWhereClause($table, $table, $values, $old_values);
			$db_value = (integer) fORMDatabase::getInstance()->translatedQuery($sql)->fetchScalar();	
		}
		
		
		// We only need to move things in the new set around if we are inserting into the middle
		// of a new set, or if we are moving around in the current set
		if (!$new_set || ($new_set && $current_value != $new_max_value)) {
			$shift_down = $new_max_value + 10;
			
			// If we are moving into the middle of a new set we just push everything up one value
			if ($new_set) { 
				$shift_up       = $new_max_value + 11;
				$down_condition = $column . " >= " . $current_value;
			
			// If we are moving a value down in a set, we push values in the difference zone up one
			} elseif ($current_value < $db_value) {
				$shift_up       = $new_max_value + 11;
				$down_condition = $column . " < " . $db_value . " AND " . $column . " >= " . $current_value;
					
			// If we are moving a value up in a set, we push values in the difference zone down one
			} else {
				$shift_up       = $new_max_value + 9;
				$down_condition = $column . " > " . $db_value . " AND " . $column . " <= " . $current_value;
			}
			
			// To prevent issues with the unique constraint, we move everything below 0
			$sql  = "UPDATE " . $table . " SET " . $column . " = " . $column . " - " . $shift_down;
			$sql .= " WHERE " . $down_condition;
			if ($other_columns) {
				$sql .= " AND " . self::createOtherFieldsWhereClause($table, $other_columns, $values);
			} 		
			fORMDatabase::getInstance()->translatedQuery($sql);
			
			if ($object->exists()) {
				// Put the actual record we are changing in limbo to be updated when the actual update happens
				$sql  = "UPDATE " . $table . " SET " . $column . " = 0";
				$sql .= " WHERE " . $column . " = " . $db_value;
				if ($other_columns) {
					$sql .= " AND " . self::createOldOtherFieldsWhereClause($table, $other_columns, $values, $old_values);
				} 		
				fORMDatabase::getInstance()->translatedQuery($sql);
			}
			
			// Anything below zero needs to be moved back up into its new position
			$sql  = "UPDATE " . $table . " SET " . $column . " = " . $column . " + " . $shift_up;
			$sql .= " WHERE " . $column . " < 0";
			if ($other_columns) {
				$sql .= " AND " . self::createOtherFieldsWhereClause($table, $other_columns, $values);
			} 		
			fORMDatabase::getInstance()->translatedQuery($sql);
		}
		
		
		// If there was an old set, we need to close the gap
		if ($object->exists() && $new_set) {
			$sql  = "SELECT max(" . $column . ") FROM " . $table . " WHERE ";			
			$sql .= self::createOldOtherFieldsWhereClause($table, $other_columns, $values, $old_values);
			
			$old_set_max = (integer) fORMDatabase::getInstance()->translatedQuery($sql)->fetchScalar();
			
			// We only need to close the gap if the record was not at the end
			if ($db_value < $old_set_max) {
				$shift_down = $old_set_max + 10;
				$shift_up   = $old_set_max + 9;
				
				// To prevent issues with the unique constraint, we move everything below 0 and then back up above
				$sql  = "UPDATE " . $table . " SET " . $column . ' = ' . $column . ' - ' . $shift_down . " WHERE ";
				$sql .= self::createOldOtherFieldsWhereClause($table, $other_columns, $values, $old_values);
				$sql .= " AND " . $column . " > " . $db_value;
				fORMDatabase::getInstance()->translatedQuery($sql);
				
				$sql  = "UPDATE " . $table . " SET " . $column . ' = ' . $column . ' + ' . $shift_up . " WHERE ";
				$sql .= self::createOldOtherFieldsWhereClause($table, $other_columns, $values, $old_values);
				$sql .= " AND " . $column . " < 0";
				fORMDatabase::getInstance()->translatedQuery($sql);
			}
		}
	}
	
	
	/**
	 * Makes sure the ordering value is sane, removes error messages about missing values
	 * 
	 * @internal
	 * 
	 * @param  fActiveRecord $object                The fActiveRecord instance
	 * @param  array         &$values               The current values
	 * @param  array         &$old_values           The old values
	 * @param  array         &$related_records      Any records related to this record
	 * @param  array         &$validation_messages  An array of ordered validation messages
	 * @return void
	 */
	static public function validate($object, &$values, &$old_values, &$related_records, &$validation_messages)
	{
		$class = fORM::getClassName($object);
		$table = fORM::tablize($class);
		
		$column        = self::$ordering_columns[$class]['column'];
		$other_columns = self::$ordering_columns[$class]['other_columns'];
		
		$current_value = $values[$column];
		$old_value     = (isset($old_values[$column])) ? $old_values[$column][0] : NULL;
		
		$sql = "SELECT max(" . $column . ") FROM " . $table;
		if ($other_columns) {
			$sql .= " WHERE " . self::createOtherFieldsWhereClause($table, $other_columns, $values);
		}
		
		$current_max_value = (integer) fORMDatabase::getInstance()->translatedQuery($sql)->fetchScalar();
		$new_max_value     = $current_max_value;
		
		if (self::isInNewSet($column, $other_columns, $values, $old_values)) {
			$new_max_value = $current_max_value + 1;	
		}
		
		$column_name = fORM::getColumnName($class, $column);
		
		// Remove any previous validation warnings
		$filtered_messages = array();
		foreach ($validation_messages as $validation_message) {
			if (!preg_match('#^[^:]*\b' . preg_quote($column_name, '#') . '\b#', $validation_message)) {
				$filtered_messages[] = $validation_message;
			}	
		}
		$validation_messages = $filtered_messages;
		
		// If we have a completely empty value, we don't need to validate since a valid value will be generated
		if ($current_value === '' || $current_value === NULL) {
			return;	
		}
		
		if (!is_numeric($current_value) || strlen((int) $current_value) != strlen($current_value)) {
			$validation_messages[] = fGrammar::compose('%s: Please enter an integer', $column_name);
		
		} elseif ($current_value < 1) {
			$validation_messages[] = fGrammar::compose('%s: The value can not be less than 1', $column_name);
			
		} elseif ($current_value > $new_max_value) {
			$validation_messages[] = fGrammar::compose('%s: The value can not be greater than %s', $column_name, $new_max_value);
		}
	}
	
	
	/**
	 * Forces use as a static class
	 * 
	 * @return fORMOrdering
	 */
	private function __construct() { }
}



/**
 * Copyright (c) 2008 William Bond <will@flourishlib.com>
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