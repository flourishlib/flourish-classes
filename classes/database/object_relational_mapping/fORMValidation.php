<?php
/**
 * Handles validation for (@link fActiveRecord} classes
 * 
 * @copyright  Copyright (c) 2007-2008 William Bond
 * @author     William Bond [wb] <will@flourishlib.com>
 * @license    http://flourishlib.com/license
 * 
 * @link  http://flourishlib.com/fORMValidation
 * 
 * @version  1.0.0
 * @changes  1.0.0    The initial implementation [wb, 2007-08-04]
 */
class fORMValidation
{	
	/**
	 * Conditional validation rules
	 * 
	 * @var array
	 */
	static private $conditional_validation_rules = array();
	
	/**
	 * Formatting rules
	 * 
	 * @var array
	 */
	static private $formatting_rules = array();
	
	/**
	 * Many-to-many validation rules
	 * 
	 * @var array
	 */
	static private $many_to_many_validation_rules = array();
	
	/**
	 * Ordering rules for validation messages
	 * 
	 * @var array
	 */
	static private $message_orders = array();
	
	/**
	 * One or more validation rules
	 * 
	 * @var array
	 */
	static private $one_or_more_validation_rules = array();
	
	/**
	 * Only one validation rules
	 * 
	 * @var array
	 */
	static private $only_one_validation_rules = array();
	
	
	/**
	 * Adds a conditional validation rule
	 *
	 * @param  mixed  $class                The class name or instance of the class this validation rule applies to
	 * @param  string $main_column          The column to check for a value
	 * @param  array  $conditional_values   If empty, any value in the main column will trigger the conditional columns, otherwise the value must match one of these
	 * @param  array  $conditional_columns  The columns that are to be required
	 * @return void
	 */
	static public function addConditionalValidationRule($class, $main_column, $conditional_values, $conditional_columns)
	{
		$table = fORM::tablize($class);
		
		if (!isset(self::$conditional_validation_rules[$table])) {
			self::$conditional_validation_rules[$table] = array();
		}
		
		$rule = array();
		$rule['main_column']         = $main_column;
		$rule['conditional_values']  = $conditional_values;
		$rule['conditional_columns'] = $conditional_columns;
		
		self::$conditional_validation_rules[$table][] = $rule;
	}
	
	
	/**
	 * Adds a column format rule
	 *
	 * @param  mixed  $class        The class name or instance of the class this validation rule applies to
	 * @param  string $column       The column to check the format of
	 * @param  string $format_type  The format for the column: email, link
	 * @return void
	 */
	static public function addFormattingRule($class, $column, $format_type)
	{
		$table = fORM::tablize($class);
		
		if (!isset(self::$formatting_rules[$table])) {
			self::$formatting_rules[$table] = array();
		}
		
		$valid_formats = array('email', 'link');
		if (!in_array($format_type, $valid_formats)) {
			fCore::toss('fProgrammerException', 'The format type specified, ' . $format_type . ', should be one of: ' . join(', ', $valid_formats));
		}
		
		self::$formatting_rules[$table][$column] = $format_type;
	}
	
	
	/**
	 * Add a many-to-many validation rule
	 *
	 * @param  mixed  $class                  The class name or instance of the class to add the rule for
	 * @param  string $plural_related_column  The plural form of the related column
	 * @return void
	 */
	static public function addManyToManyValidationRule($class, $plural_related_column)
	{
		$table = fORM::tablize($class);
		
		if (!isset(self::$many_to_many_validation_rules[$table])) {
			self::$many_to_many_validation_rules[$table] = array();
		}
		
		$rule = array();
		$rule['plural_related_column'] = $plural_related_column;
		
		self::$many_to_many_validation_rules[$table][] = $rule;
	}
	
	
	/**
	 * Adds a one-or-more validation rule
	 *
	 * @param  mixed $class    The class name or instance of the class the columns exists in
	 * @param  array $columns  The columns to check
	 * @return void
	 */
	static public function addOneOrMoreValidationRule($class, $columns)
	{
		$table = fORM::tablize($class);
		
		settype($columns, 'array');
		
		if (!isset(self::$one_or_more_validation_rules[$table])) {
			self::$one_or_more_validation_rules[$table] = array();
		}
		
		$rule = array();
		$rule['columns'] = $columns;
		
		self::$one_or_more_validation_rules[$table][] = $rule;
	}
	
	
	/**
	 * Add an only-one validation rule
	 *
	 * @param  mixed $class    The class name or instance of the class the columns exists in
	 * @param  array $columns  The columns to check
	 * @return void
	 */
	static public function addOnlyOneValidationRule($class, $columns)
	{
		$table = fORM::tablize($class);
		
		settype($columns, 'array');
		
		if (!isset(self::$only_one_validation_rules[$table])) {
			self::$only_one_validation_rules[$table] = array();
		}
		
		$rule = array();
		$rule['columns'] = $columns;
		
		self::$only_one_validation_rules[$table][] = $rule;
	}
	
	
	/**
	 * Validates a value against the database schema
	 *
	 * @throws  fValidationException
	 * 
	 * @param  string $table        The database table the column exists in
	 * @param  string $column       The column to check
	 * @param  array  &$values      An associative array of all values going into the row (needs all for multi-field unique constraint checking)
	 * @param  array  &$old_values  The old values from the record
	 * @return void
	 */
	static private function checkAgainstSchema($table, $column, &$values, &$old_values)
	{
		$column_info = fORMSchema::getInstance()->getColumnInfo($table, $column);
		// Make sure a value is provided for required columns
		if ($values[$column] === NULL && $column_info['not_null'] && $column_info['default'] === NULL && $column_info['auto_increment'] === FALSE) {
			fCore::toss('fValidationException', fORM::getColumnName($table, $column) . ': Please enter a value');
		}
		
		self::checkDataType($table, $column, $values[$column]);
		
		// Make sure a valid value is chosen
		if (isset($column_info['valid_values']) && $values[$column] !== NULL && !in_array($values[$column], $column_info['valid_values'])) {
			fCore::toss('fValidationException', fORM::getColumnName($table, $column) . ': Please choose from one of the following: ' . join(', ', $column_info['valid_values']));
		}
		// Make sure the value isn't too long
		if (isset($column_info['max_length']) && $values[$column] !== NULL && is_string($values[$column]) && strlen($values[$column]) > $column_info['max_length']) {
			fCore::toss('fValidationException', fORM::getColumnName($table, $column) . ': Please enter a value no longer than ' . $column_info['max_length'] . ' characters');
		}
		
		self::checkUniqueConstraints($table, $column, $values, $old_values);
		self::checkForeignKeyConstraints($table, $column, $values);
	}
	
	
	/**
	 * Validates against a conditional validation rule
	 *
	 * @throws  fValidationException
	 * 
	 * @param  string $table                The database table this validation rule applies to
	 * @param  array  &$values              An associative array of all values for the record
	 * @param  string $main_column          The column to check for a value
	 * @param  array  $conditional_values   If empty, any value in the main column will trigger the conditional columns, otherwise the value must match one of these
	 * @param  array  $conditional_columns  The columns that are to be required
	 * @return void
	 */
	static private function checkConditionalRule($table, &$values, $main_column, $conditional_values, $conditional_columns)
	{
		if (!empty($conditional_values))  {
			settype($conditional_values, 'array');
		}
		settype($conditional_columns, 'array');
		
		if ($values[$main_column] === NULL) {
			return;
		}
		
		if ((!empty($conditional_values) && in_array($values[$main_column], $conditional_values)) || (empty($conditional_values))) {
			$messages = array();
			foreach ($conditional_columns as $conditional_column) {
				if ($values[$conditional_column] === NULL) {
					$messages[] = fORM::getColumnName($table, $conditional_column) . ': Please enter a value';
				}
			}
			if (!empty($messages)) {
				fCore::toss('fValidationException', join("\n", $messages));
			}
		}
	}
	
	
	/**
	 * Validates a value against the database data type
	 *
	 * @param  string $table   The database table the column exists in
	 * @param  string $column  The column to check
	 * @param  mixed  $value   The value to check
	 * @return void
	 */
	static private function checkDataType($table, $column, $value)
	{
		$column_info = fORMSchema::getInstance()->getColumnInfo($table, $column);
		if ($value !== NULL) {
			switch ($column_info['type']) {
				case 'varchar':
				case 'char':
				case 'text':
				case 'blob':
					if (!is_string($value) && !is_numeric($value)) {
						fCore::toss('fValidationException', fORM::getColumnName($table, $column) . ': Please enter a string');
					}
					break;
				case 'integer':
				case 'float':
					if (!is_numeric($value)) {
						fCore::toss('fValidationException', fORM::getColumnName($table, $column) . ': Please enter a number');
					}
					break;
				case 'timestamp':
				case 'date':
				case 'time':
					if (strtotime($value) === FALSE) {
						fCore::toss('fValidationException', fORM::getColumnName($table, $column) . ': Please enter a date or time');
					}
					break;
				
			}
		}
	}
	
	
	/**
	 * Validates values against foreign key constraints
	 *
	 * @param  string $table    The database table
	 * @param  string $column   The column to check
	 * @param  array  &$values  The values to check
	 * @return void
	 */
	static private function checkForeignKeyConstraints($table, $column, &$values)
	{
		if ($values[$column] === NULL) {
			return;
		}
		
		$foreign_keys = fORMSchema::getInstance()->getKeys($table, 'foreign');
		foreach ($foreign_keys AS $foreign_key) {
			if ($foreign_key['column'] == $column) {
				try {
					$sql  = "SELECT " . $foreign_key['foreign_column'];
					$sql .= " FROM " . $foreign_key['foreign_table'];
					$sql .= " WHERE ";
					$sql .= $column . fORMDatabase::prepareBySchema($table, $column, $values[$column], '=');
					$sql  = str_replace('WHERE ' . $column, 'WHERE ' . $foreign_key['foreign_column'], $sql);
					
					$result = fORMDatabase::getInstance()->translatedQuery($sql);
					$result->tossIfNoResults();
				} catch (fNoResultsException $e) {
					fCore::toss('fValidationException', fORM::getColumnName($table, $column) . ': The value specified is invalid');
				}
			}
		}
	}
	
	
	/**
	 * Checks a value to make sure it is the right format
	 *
	 * @throws  fValidationException
	 * 
	 * @param  string $table        The table the column is located in
	 * @param  array  &$values      An associative array of all values for the record
	 * @param  string $column       The column to check
	 * @param  string $format_type  The type of formatting the column should have
	 * @return void
	 */
	static private function checkFormattingRule($table, &$values, $column, $format_type)
	{
		if ($values[$column] === NULL) {
			return;
		}
		
		if ($format_type == 'email') {
			if (!preg_match('#^[a-z0-9\\.\'_\\-\\+]+@(?:[a-z0-9\\-]+\.)+[a-z]{2,}$#i', $values[$column])) {
				fCore::toss('fValidationException', fORM::getColumnName($table, $column) . ': Please enter an email address in the form name@example.com');
			}
		
		} elseif ($format_type == 'link') {
			if (!preg_match('#^(http(s)?://|/|([a-z0-9\\-]+\.)+[a-z]{2,})#i', $values[$column])) {
				fCore::toss('fValidationException', fORM::getColumnName($table, $column) . ': Please enter a link in the form http://www.example.com');
			}
			if (preg_match('#^([a-z0-9\\-]+\.)+[a-z]{2,}#i', $values[$column])) {
				$values[$column] = 'http://' . $values[$column];
			} elseif (substr($values[$column], 0, 1) == '/') {
				$values[$column] = fURL::getDomain() . $values[$column];
			}
		}
	}
	
	
	/**
	 * Validates against a many-to-many validation rule
	 *
	 * @throws  fValidationException
	 * 
	 * @param  array  &$values                An associative array of all values for the record
	 * @param  string $plural_related_column  The plural name of the related column
	 * @return void
	 */
	static private function checkManyToManyRule(&$values, $plural_related_column)
	{
		if (!isset($values[$plural_related_column]) || empty($values[$plural_related_column])) {
			fCore::toss('fValidationException', fInflection::humanize($plural_related_column) . ': Please select at least one');
		}
	}
	
	
	/**
	 * Validates against an only-one validation rule
	 *
	 * @throws  fValidationException
	 * 
	 * @param  string $table    The database table the column exists in
	 * @param  array  &$values  An associative array of all values for the record
	 * @param  array  $columns  The columns to check
	 * @return void
	 */
	static private function checkOnlyOneRule($table, &$values, $columns)
	{
		settype($columns, 'array');
		
		$found_value = FALSE;
		foreach ($columns as $column) {
			if ($values[$column] !== NULL) {
				if ($found_value) {
					$column_names = '';
					$column_num = 0;
					foreach ($columns as $column) {
						if ($column_num) { $column_names .= ', '; }
						$column_names .= fORM::getColumnName($table, $column);
						$column_num++;
					}
					fCore::toss('fValidationException', $column_names . ': Please enter a value for only one');
				}
				$found_value = TRUE;
			}
		}
	}
	
	
	/**
	 * Validates against a one-or-more validation rule
	 *
	 * @throws  fValidationException
	 * 
	 * @param  string $table    The database table the columns exists in
	 * @param  array  &$values  An associative array of all values for the record
	 * @param  array  $columns  The columns to check
	 * @return void
	 */
	static private function checkOneOrMoreRule($table, &$values, $columns)
	{
		settype($columns, 'array');
		
		$found_value = FALSE;
		foreach ($columns as $column) {
			if ($values[$column] !== NULL) {
				$found_value = TRUE;
			}
		}
		
		if (!$found_value) {
			$column_names = '';
			$column_num = 0;
			foreach ($columns as $column) {
				if ($column_num) { $column_names .= ', '; }
				$column_names .= fORM::getColumnName($table, $column);
				$column_num++;
			}
			fCore::toss('fValidationException', $column_names . ': Please enter a value for at least one');
		}
	}
	
	
	/**
	 * Makes sure a record with the same primary keys is not already in the database
	 *
	 * @throws  fValidationException
	 * 
	 * @param  string $table        The database table the column exists in
	 * @param  array  &$values      An associative array of all values going into the row (needs all for multi-field unique constraint checking)
	 * @param  array  &$old_values  The old values for the record
	 * @return void
	 */
	static private function checkPrimaryKeys($table, &$values, &$old_values)
	{
		$primary_keys = fORMSchema::getInstance()->getKeys($table, 'primary');
		
		$exists = TRUE;
		$key_set = FALSE;
		foreach ($primary_keys as $primary_key) {
			if ((array_key_exists($primary_key, $old_values) && $old_values[$primary_key] === NULL) || $values[$primary_key] === NULL) {
				$exists = FALSE;
			}
			if ($values[$primary_key] !== NULL) {
				$key_set = TRUE;
			}
		}
		
		// We don't need to check if the record is existing
		if ($exists || !$key_set) {
			return;
		}
		
		try {
			$sql = "SELECT * FROM " . $table . " WHERE ";
			$key_num = 0;
			$columns = '';
			foreach ($primary_keys as $primary_key) {
				if ($key_num) { $sql .= " AND "; $columns.= ', '; }
				$sql .= $primary_key . fORMDatabase::prepareBySchema($table, $primary_key, (!empty($old_values[$primary_key])) ? $old_values[$primary_key] : $values[$primary_key], '=');
				$columns .= fInflection::humanize($primary_key);
				$key_num++;
			}
			$result = fORMDatabase::getInstance()->translatedQuery($sql);
			$result->tossIfNoResults();
			
			fCore::toss('fValidationException', 'A ' . fORM::getRecordName(fORM::classize($table)) . ' with the same ' . $columns . ' already exists');
			
		} catch (fNoResultsException $e) {
			return;
		}
	}
	
	
	/**
	 * Validates values against unique constraints
	 *
	 * @throws fValidationException
	 * 
	 * @param  string $table        The database table
	 * @param  string $column       The column to check
	 * @param  array  &$values      The values to check
	 * @param  array  &$old_values  The old values for the record
	 * @return void
	 */
	static private function checkUniqueConstraints($table, $column, &$values, &$old_values)
	{
		$key_info = fORMSchema::getInstance()->getKeys($table);
		
		$primary_keys = $key_info['primary'];
		$unique_keys  = $key_info['unique'];
		
		$exists = TRUE;
		foreach ($primary_keys as $primary_key) {
			if ((array_key_exists($primary_key, $old_values) && $old_values[$primary_key] === NULL) || $values[$primary_key] === NULL) {
				$exists = FALSE;
			}
		}
		
		foreach ($unique_keys AS $unique_columns) {
			if (in_array($column, $unique_columns)) {
				$sql = "SELECT " . join(', ', $key_info['primary']) . " FROM " . $table . " WHERE ";
				$column_num = 0;
				foreach ($unique_columns as $unique_column) {
					if ($column_num) { $sql .= " AND "; }
					$sql .= $unique_column . fORMDatabase::prepareBySchema($table, $unique_column, $values[$unique_column], '=');
					$column_num++;
				}
				
				if ($exists) {
					$sql .= ' AND (';
					$first = TRUE;
					foreach ($primary_keys as $primary_key) {
						$sql .= ($first && !$first = FALSE) ? '' : ' AND '; 
						$sql .= $table . '.' . $primary_key . fORMDatabase::prepareBySchema($table, $primary_key, $values[$primary_key], '<>');	
					}
					$sql .= ')';
				}
				
				try {
					$result = fORMDatabase::getInstance()->translatedQuery($sql);
					$result->tossIfNoResults();
				
					// If an exception was not throw, we have existing values
					$column_names = '';
					$column_num = 0;
					foreach ($unique_columns as $unique_column) {
						if ($column_num) { $column_names .= ', '; }
						$column_names .= fORM::getColumnName($table, $unique_column);
						$column_num++;
					}
					fCore::toss('fValidationException', $column_names . ': The values specified must be a unique combination, but the specified combination already exists');
				
				} catch (fNoResultsException $e) { }
			}
		}
	}
	
	
	/**
	 * Checks to see if there is a column format rule
	 *
	 * @internal
	 * 
	 * @param  mixed  $class        The class name or an instance of the class this validation rule applies to
	 * @param  string $column       The column to check the format of
	 * @param  string $format_type  The format to check for: email, link
	 * @return void
	 */
	static public function hasFormattingRule($class, $column, $format_type)
	{
		$table = fORM::tablize($class);
		
		if (!isset(self::$formatting_rules[$table])) {
			return FALSE;
		}
		
		$valid_formats = array('email', 'link');
		if (!in_array($format_type, $valid_formats)) {
			fCore::toss('fProgrammerException', 'The format type specified, ' . $format_type , ', should be one of the following: ' . join(', ', $valid_formats));
		}
		
		if (!isset(self::$formatting_rules[$table][$column]) || self::$formatting_rules[$table][$column] != $format_type) {
			return FALSE;
		}
		
		return TRUE;
	}
	
	
	/**
	 * Reorders list items in an html string based on their contents
	 * 
	 * @internal
	 * 
	 * @param  mixed $class                 The class name or an instance of the class to reorder messages for                 
	 * @param  array &$validation_messages  An array of one validation message per value
	 * @return void
	 */
	static public function reorderMessages($class, &$validation_messages)
	{
		$table = fORM::tablize($class);
		
		if (!isset(self::$message_orders[$table])) {
			return;
		}
			
		$matches = self::$message_orders[$table];
		
		$ordered_items = array_fill(0, sizeof($matches), array());
		$other_items   = array();
		
		foreach ($validation_messages as $validation_message) {
			foreach ($matches as $num => $match_string) {
				if (strpos($validation_message, $match_string) !== FALSE) {
					$ordered_items[$num][] = $validation_message;
					continue 2;
				}
			}
			
			$other_items[] = $validation_message;
		}
		
		$final_list = array();
		foreach ($ordered_items as $ordered_item) {
			$final_list = array_merge($final_list, $ordered_item);
		}
		$validation_messages = array_merge($final_list, $other_items);
	}
	
	
	/**
	 * Allows setting the order that the list items in a validation message will be displayed
	 *
	 * @param  mixed $class    The class name or an instance of the class to set the message order for
	 * @param  array $matches  This should be an ordered array of strings. If a line contains the string it will be displayed in the relative order it occurs in this array.
	 * @return void
	 */
	static public function setMessageOrder($class, $matches)
	{
		$table = fORM::tablize($class);
		
		self::$message_orders[$table] = $matches;
	}
	
	
	/**
	 * Validates values for an fActiveRecord object
	 *
	 * @internal
	 * 
	 * @param  string  $class       The class name or instance of the class to validate
	 * @param  array   $values      The values to validate
	 * @param  array   $old_values  The old values for the record
	 * @param  boolean $existing    If the record currently exists in the database
	 * @return array  An array of validation messages
	 */
	static public function validate($class, $values, $old_values)
	{
		$table = fORM::tablize($class);
		
		$validation_messages = array();
		
		// Convert objects into values for alidation
		foreach ($values as $key => $value) {
			$values[$key] = fORM::scalarize($value);
		}
		foreach ($old_values as $key => $value) {
			$old_values[$key] = fORM::scalarize($value);
		}
		
		try {
			self::checkPrimaryKeys($table, $values, $old_values);
		} catch (fValidationException $e) {
			$validation_messages[] = $e->getMessage();
		}
		
		$column_info = fORMSchema::getInstance()->getColumnInfo($table);
		foreach ($column_info as $column => $info) {
			try {
				self::checkAgainstSchema($table, $column, $values, $old_values);
			} catch (fValidationException $e) {
				$validation_messages[] = $e->getMessage();
			}
		}
		
		self::$conditional_validation_rules[$table] = (isset(self::$conditional_validation_rules[$table])) ? self::$conditional_validation_rules[$table] : array();
		foreach (self::$conditional_validation_rules[$table] as $rule) {
			try {
				self::checkConditionalRule($table, $values, $rule['main_column'], $rule['conditional_values'], $rule['conditional_columns']);
			} catch (fValidationException $e) {
				$messages = explode("\n", $e->getMessage());
				foreach ($messages as $message) {
					$validation_messages[] = $message;
				}
			}
		}
		
		self::$one_or_more_validation_rules[$table] = (isset(self::$one_or_more_validation_rules[$table])) ? self::$one_or_more_validation_rules[$table] : array();
		foreach (self::$one_or_more_validation_rules[$table] as $rule) {
			try {
				self::checkOneOrMoreRule($table, $values, $rule['columns']);
			} catch (fValidationException $e) {
				$validation_messages[] = $e->getMessage();
			}
		}
		
		self::$only_one_validation_rules[$table] = (isset(self::$only_one_validation_rules[$table])) ? self::$only_one_validation_rules[$table] : array();
		foreach (self::$only_one_validation_rules[$table] as $rule) {
			try {
				self::checkOnlyOneRule($table, $values, $rule['columns']);
			} catch (fValidationException $e) {
				$validation_messages[] = $e->getMessage();
			}
		}
		
		self::$many_to_many_validation_rules[$table] = (isset(self::$many_to_many_validation_rules[$table])) ? self::$many_to_many_validation_rules[$table] : array();
		foreach (self::$many_to_many_validation_rules[$table] as $rule) {
			try {
				self::checkManyToManyRule($values, $rule['plural_related_column']);
			} catch (fValidationException $e) {
				$validation_messages[] = $e->getMessage();
			}
		}
		
		self::$formatting_rules[$table] = (isset(self::$formatting_rules[$table])) ? self::$formatting_rules[$table] : array();
		foreach (self::$formatting_rules[$table] as $column => $format_type) {
			try {
				self::checkFormattingRule($table, $values, $column, $format_type);
			} catch (fValidationException $e) {
				$validation_messages[] = $e->getMessage();
			}
		}
		
		return $validation_messages;
	}
	
	
	/**
	 * Validates related records for an fActiveRecord object
	 *
	 * @internal
	 * 
	 * @param  string  $class             The class to validate
	 * @param  array   &$related_records  The related records to validate
	 * @return array  An array of validation messages
	 */
	static public function validateRelated($class, &$related_records)
	{
		$table = fORM::tablize($class);
		
		$validation_messages = array();
		
		// Find the record sets to validate
		foreach ($related_records as $related_table => $routes) {
			foreach ($routes as $route => $record_set) {
				if (!$record_set->isFlaggedForAssociation()) {
					continue;
				}
				
				$relationship = fORMSchema::getRoute($table, $related_table, $route);	
																												
				if (isset($relationship['join_table'])) {
					$related_messages = self::validateManyToMany($table, $related_table, $route, $record_set);	
				} else {
					$related_messages = self::validateOneToMany($table, $related_table, $route, $record_set);
				}
				
				$validation_messages = array_merge($validation_messages, $related_messages);
			}
		}
		
		return $validation_messages;	
	}
	
	
	/**
	 * Validates one-to-many related records
	 *
	 * @internal
	 * 
	 * @param  string     $table          The table these records are related to
	 * @param  string     $related_table  The table for this record set
	 * @param  string     $route          The route between the table and related table
	 * @param  fRecordSet $record_set     The related records to validate
	 * @return array  An array of validation messages
	 */
	static private function validateOneToMany($table, $related_table, $route, $record_set)
	{
		$related_record_name = fORMRelatedData::getRelatedRecordName($table, fORM::classize($related_table), $route);
		$record_number = 1;
		
		$primary_keys = fORMSchema::getInstance()->getKeys($table, 'primary');
		
		$messages = array();
		
		foreach ($record_set as $record) {
			$record_messages = $record->validate(TRUE);
			foreach ($record_messages as $record_message) {
				// Ignore validation messages about the primary key since it will be added 
				if (strpos($record_message, fORM::getColumnName($table, $primary_keys[0])) !== FALSE) {
					continue;	
				}
				$messages[] = $related_record_name . ' #' . $record_number . ' ' . $record_message;	
			}
			$record_number++;
		}	
		
		return $messages;	
	}
	
	
	/**
	 * Validates many-to-many related records
	 *
	 * @internal
	 * 
	 * @param  string     $table          The table these records are related to
	 * @param  string     $related_table  The table for this record set
	 * @param  string     $route          The route between the table and related table
	 * @param  fRecordSet $record_set     The related records to validate
	 * @return array  An array of validation messages
	 */
	static private function validateManyToMany($table, $related_table, $route, $record_set)
	{
		$related_record_name = fORMRelatedData::getRelatedRecordName($table, fORM::classize($related_table), $route);
		$record_number = 1;
		
		$messages = array();
		
		foreach ($record_set as $record) {
			if (!$record->isExisting()) {
				$messages[] = $related_record_name . ' #' . $record_number . ': Please select a ' . $related_record_name;		
			}
			$record_number++;
		}	
		
		return $messages;	
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