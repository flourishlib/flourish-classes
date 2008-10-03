<?php
/**
 * Provides special column functionality for {@link fActiveRecord} classes
 * 
 * @copyright  Copyright (c) 2008 William Bond
 * @author     William Bond [wb] <will@flourishlib.com>
 * @license    http://flourishlib.com/license
 * 
 * @package    Flourish
 * @link       http://flourishlib.com/fORMColumn
 * 
 * @version    1.0.0b
 * @changes    1.0.0b  The initial implementation [wb, 2008-05-27]
 */
class fORMColumn
{
	const configureEmailColumn  = 'fORMColumn::configureEmailColumn';
	const configureLinkColumn   = 'fORMColumn::configureLinkColumn';
	const configureNumberColumn = 'fORMColumn::configureNumberColumn';
	const configureRandomColumn = 'fORMColumn::configureRandomColumn';
	const encodeNumberColumn    = 'fORMColumn::encodeNumberColumn';
	const inspect               = 'fORMColumn::inspect';
	const objectifyNumber       = 'fORMColumn::objectifyNumber';
	const prepareLinkColumn     = 'fORMColumn::prepareLinkColumn';
	const prepareNumberColumn   = 'fORMColumn::prepareNumberColumn';
	const reflect               = 'fORMColumn::reflect';
	const setRandomStrings      = 'fORMColumn::setRandomStrings';
	const validateEmailColumns  = 'fORMColumn::validateEmailColumns';
	const validateLinkColumns   = 'fORMColumn::validateLinkColumns';
	
	
	/**
	 * Columns that should be formatted as email addresses
	 * 
	 * @var array
	 */
	static private $email_columns = array();
	
	/**
	 * Columns that should be formatted as links
	 * 
	 * @var array
	 */
	static private $link_columns = array();
	
	/**
	 * Columns that should be returned as fNumber objects
	 * 
	 * @var array
	 */
	static private $number_columns = array();
	
	/**
	 * Columns that should be formatted as a random string
	 * 
	 * @var array
	 */
	static private $random_columns = array();
	
	
	/**
	 * Sets a column to be formatted as an email address
	 * 
	 * @param  mixed  $class   The class name or instance of the class to set the column format
	 * @param  string $column  The column to format as an email address
	 * @return void
	 */
	static public function configureEmailColumn($class, $column)
	{
		$class     = fORM::getClass($class);
		$table     = fORM::tablize($class);
		$data_type = fORMSchema::getInstance()->getColumnInfo($table, $column, 'type');
		
		$valid_data_types = array('varchar', 'char', 'text');
		if (!in_array($data_type, $valid_data_types)) {
			fCore::toss(
				'fProgrammerException',
				fGrammar::compose(
					'The column specified, %1$s, is a %2$s column. Must be one of %3$s to be set as an email column.',
					fCore::dump($column),
					$data_type,
					join(', ', $valid_data_types)
				)
			);
		}
		
		$camelized_column = fGrammar::camelize($column, TRUE);
		
		fORM::registerHookCallback(
			$class,
			'replace::inspect' . $camelized_column . '()',
			array('fORMColumn', 'inspect')
		);
		
		$hook     = 'post::validate()';
		$callback = array('fORMColumn', 'validateEmailColumns');
		if (!fORM::checkHookCallback($class, $hook, $callback)) {
			fORM::registerHookCallback($class, $hook, $callback);
		}
		
		if (empty(self::$email_columns[$class])) {
			self::$email_columns[$class] = array();
		}
		
		self::$email_columns[$class][$column] = TRUE;
	}
	
	
	/**
	 * Sets a column to be formatted as a link
	 * 
	 * @param  mixed  $class   The class name or instance of the class to set the column format
	 * @param  string $column  The column to format as a link
	 * @return void
	 */
	static public function configureLinkColumn($class, $column)
	{
		$class     = fORM::getClass($class);
		$table     = fORM::tablize($class);
		$data_type = fORMSchema::getInstance()->getColumnInfo($table, $column, 'type');
		
		$valid_data_types = array('varchar', 'char', 'text');
		if (!in_array($data_type, $valid_data_types)) {
			fCore::toss(
				'fProgrammerException',
				fGrammar::compose(
					'The column specified, %1$s, is a %2$s column. Must be one of %3$s to be set as a link column.',
					fCore::dump($column),
					$data_type,
					join(', ', $valid_data_types)
				)
			);
		}
		
		$camelized_column = fGrammar::camelize($column, TRUE);
		
		fORM::registerHookCallback(
			$class,
			'replace::inspect' . $camelized_column . '()',
			array('fORMColumn', 'inspect')
		);
		
		fORM::registerHookCallback(
			$class,
			'replace::prepare' . $camelized_column . '()',
			array('fORMColumn', 'prepareLinkColumn')
		);
		
		$hook     = 'post::validate()';
		$callback = array('fORMColumn', 'validateLinkColumns');
		if (!fORM::checkHookCallback($class, $hook, $callback)) {
			fORM::registerHookCallback($class, $hook, $callback);
		}
		
		fORM::registerReflectCallback(
			$class,
			array('fORMColumn', 'reflect')
		);
		
		if (empty(self::$link_columns[$class])) {
			self::$link_columns[$class] = array();
		}
		
		self::$link_columns[$class][$column] = TRUE;
	}
	
	
	/**
	 * Sets a column to be returned as an fNumber object from calls to get{Column}()
	 * 
	 * @param  mixed  $class   The class name or instance of the class to set the column format
	 * @param  string $column  The column to return as an fNumber object
	 * @return void
	 */
	static public function configureNumberColumn($class, $column)
	{
		$class     = fORM::getClass($class);
		$table     = fORM::tablize($class);
		$data_type = fORMSchema::getInstance()->getColumnInfo($table, $column, 'type');
		
		$valid_data_types = array('integer', 'float');
		if (!in_array($data_type, $valid_data_types)) {
			fCore::toss(
				'fProgrammerException',
				fGrammar::compose(
					'The column specified, %1$s, is a %2$s column. Must be %3$s to be set as a number column.',
					fCore::dump($column),
					$data_type,
					join(', ', $valid_data_types)
				)
			);
		}
		
		$camelized_column = fGrammar::camelize($column, TRUE);
		
		fORM::registerHookCallback(
			$class,
			'replace::inspect' . $camelized_column . '()',
			array('fORMColumn', 'inspect')
		);
		
		fORM::registerHookCallback(
			$class,
			'replace::encode' . $camelized_column . '()',
			array('fORMColumn', 'encodeNumberColumn')
		);
		
		fORM::registerHookCallback(
			$class,
			'replace::prepare' . $camelized_column . '()',
			array('fORMColumn', 'prepareNumberColumn')
		);
		
		fORM::registerReflectCallback(
			$class,
			array('fORMColumn', 'reflect')
		);
		
		fORM::registerObjectifyCallback(
			$class,
			$column,
			array('fORMColumn', 'objectifyNumber')
		);
		
		if (empty(self::$number_columns[$class])) {
			self::$number_columns[$class] = array();
		}
		
		self::$number_columns[$class][$column] = TRUE;
	}
	
	
	/**
	 * Sets a column to be a random string column - a random string will be generated when the record is saved
	 * 
	 * @param  mixed   $class   The class name or instance of the class
	 * @param  string  $column  The column to set as a random column
	 * @param  string  $type    The type of random string, must be one of: 'alphanumeric', 'alpha', 'numeric', 'hexadecimal'
	 * @param  integer $length  The length of the random string
	 * @return void
	 */
	static public function configureRandomColumn($class, $column, $type, $length)
	{
		$class     = fORM::getClass($class);
		$table     = fORM::tablize($class);
		$data_type = fORMSchema::getInstance()->getColumnInfo($table, $column, 'type');
		
		$valid_data_types = array('varchar', 'char', 'text');
		if (!in_array($data_type, $valid_data_types)) {
			fCore::toss(
				'fProgrammerException',
				fGrammar::compose(
					'The column specified, %1$s, is a %2$s column. Must be one of %3$s to be set as a random string column.',
					fCore::dump($column),
					$data_type,
					join(', ', $valid_data_types)
				)
			);
		}
		
		$valid_types = array('alphanumeric', 'alpha', 'numeric', 'hexadecimal');
		if (!in_array($type, $valid_types)) {
			fCore::toss(
				'fProgrammerException',
				fGrammar::compose(
					'The type specified, %1$s, is an invalid type. Must be one of: %2$s.',
					fCore::dump($type),
					join(', ', $valid_types)
				)
			);
		}
		
		if (!is_numeric($length) || $length < 1) {
			fCore::toss(
				'fProgrammerException',
				fGrammar::compose(
					'The length specified, %s, needs to be an integer greater than zero.',
					$length
				)
			);
		}
		
		$camelized_column = fGrammar::camelize($column, TRUE);
		
		fORM::registerHookCallback(
			$class,
			'replace::inspect' . $camelized_column . '()',
			array('fORMColumn', 'inspect')
		);
		
		$hook     = 'pre::validate()';
		$callback = array('fORMColumn', 'setRandomStrings');
		if (!fORM::checkHookCallback($class, $hook, $callback)) {
			fORM::registerHookCallback($class, $hook, $callback);
		}
		
		if (empty(self::$random_columns[$class])) {
			self::$random_columns[$class] = array();
		}
		
		self::$random_columns[$class][$column] = array('type' => $type, 'length' => (int) $length);
	}
	
	
	/**
	 * Encodes a number column by calling {@link fNumber::__toString()}
	 * 
	 * @internal
	 * 
	 * @param  fActiveRecord $object            The fActiveRecord instance
	 * @param  array         &$values           The current values
	 * @param  array         &$old_values       The old values
	 * @param  array         &$related_records  Any records related to this record
	 * @param  string        &$method_name      The method that was called
	 * @param  array         &$parameters       The parameters passed to the method
	 * @return string  The encoded number
	 */
	static public function encodeNumberColumn($object, &$values, &$old_values, &$related_records, &$method_name, &$parameters)
	{
		list ($action, $column) = fORM::parseMethod($method_name);
		
		$column_info = fORMSchema::getInstance()->getColumnInfo(fORM::tablize($object), $column);	
		$value       = $values[$column];
		
		if ($value instanceof fNumber) {
			if ($column_info['type'] == 'float') {
				$decimal_places = (isset($parameters[0])) ? (int) $parameters[0] : $column_info['decimal_places'];
				$value = $value->trunc($decimal_places)->__toString();
			} else {
				$value = $value->__toString();
			}
		}
		
		return fHTML::prepare($value);
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
		list ($action, $column) = fORM::parseMethod($method_name);
		
		$class   = get_class($object);
		$info    = fORMSchema::getInstance()->getColumnInfo(fORM::tablize($class), $column);
		$element = (isset($parameters[0])) ? $parameters[0] : NULL;
		
		if (!in_array($info['type'], array('varchar', 'char', 'text'))) {
			unset($info['valid_values']);
			unset($info['max_length']);
		}
		
		if ($info['type'] != 'float') {
			unset($info['decimal_places']);
		}
		
		if ($info['type'] != 'integer') {
			unset($info['auto_increment']);
		}
		
		if (!empty(self::$email_columns[$class][$column])) {
			$info['feature'] = 'email';
		}
		
		if (!empty(self::$link_columns[$class][$column])) {
			$info['feature'] = 'link';
		}
		
		if (!empty(self::$random_columns[$class][$column])) {
			$info['feature'] = 'random';
		}
		
		if (!empty(self::$number_columns[$class][$column])) {
			$info['feature'] = 'number';
		}
		
		if ($element) {
			if (!isset($info[$element])) {
				fCore::toss(
					'fProgrammerException',
					fGrammar::compose(
						'The element specified, %1$s, is invalid. Must be one of: %2$s.',
						fCore::dump($element),
						join(', ', array_keys($info))
					)
				);
			}
			return $info[$element];
		}
		
		return $info;
	}
	
	
	/**
	 * Turns a monetary value into an {@link fNumber} object
	 * 
	 * @internal
	 * 
	 * @param  string $class   The class this value is for
	 * @param  string $column  The column the value is in
	 * @param  mixed  $value   The value
	 * @return mixed  The {@link fNumber} object or raw value
	 */
	static public function objectifyNumber($class, $column, $value)
	{
		if (!fCore::stringlike($value)) {
			return $value;
		}
		
		try {
			return new fNumber($value);
			 
		// If there was some error creating the number object, just return the raw value
		} catch (fExpectedException $e) {
			return $value;
		}
	}
	
	
	/**
	 * Prepares a link column so that the link will work properly in an A tag
	 * 
	 * @internal
	 * 
	 * @param  fActiveRecord $object            The fActiveRecord instance
	 * @param  array         &$values           The current values
	 * @param  array         &$old_values       The old values
	 * @param  array         &$related_records  Any records related to this record
	 * @param  string        &$method_name      The method that was called
	 * @param  array         &$parameters       The parameters passed to the method
	 * @return string  The formatted link
	 */
	static public function prepareLinkColumn($object, &$values, &$old_values, &$related_records, &$method_name, &$parameters)
	{
		list ($action, $column) = fORM::parseMethod($method_name);
		
		$value = $values[$column];
		
		// Fix domains that don't have the protocol to start
		if (preg_match('#^([a-z0-9\\-]+\.)+[a-z]{2,}(/|$)#i', $value)) {
			$value = 'http://' . $value;
		}
		
		return fHTML::prepare($value);
	}
	
	
	/**
	 * Prepares a number column by calling {@link fNumber::format()}
	 * 
	 * @internal
	 * 
	 * @param  fActiveRecord $object            The fActiveRecord instance
	 * @param  array         &$values           The current values
	 * @param  array         &$old_values       The old values
	 * @param  array         &$related_records  Any records related to this record
	 * @param  string        &$method_name      The method that was called
	 * @param  array         &$parameters       The parameters passed to the method
	 * @return string  The formatted link
	 */
	static public function prepareNumberColumn($object, &$values, &$old_values, &$related_records, &$method_name, &$parameters)
	{
		list ($action, $column) = fORM::parseMethod($method_name);
		
		$column_info = fORMSchema::getInstance()->getColumnInfo(fORM::tablize($object), $column);	
		$value       = $values[$column];
		
		if ($value instanceof fNumber) {
			if ($column_info['type'] == 'float') {
				$decimal_places = (isset($parameters[0])) ? (int) $parameters[0] : $column_info['decimal_places'];
				if ($decimal_places !== NULL) {
					$value = $value->trunc($decimal_places)->format();
				} else {
					$value = $value->format();
				}
			} else {
				$value = $value->format();
			}
		}
		
		return fHTML::prepare($value);
	}
	
	
	/**
	 * Adjusts the {@link fActiveRecord::reflect()} signatures of columns that have been configured in this class
	 * 
	 * @internal
	 * 
	 * @param  string  $class                 The class to reflect
	 * @param  array   &$signatures           The associative array of {method name} => {signature}
	 * @param  boolean $include_doc_comments  If doc comments should be included with the signature
	 * @return void
	 */
	static public function reflect($class, &$signatures, $include_doc_comments)
	{
		
		if (isset(self::$link_columns[$class])) {
			foreach(self::$link_columns[$class] as $column => $enabled) {
				$signature = '';
				if ($include_doc_comments) {
					$signature .= "/**\n";
					$signature .= " * Prepares the value of " . $column . " for output into HTML\n";
					$signature .= " * \n";
					$signature .= " * This method will ensure all links that start with a domain name are preceeded by http://\n";
					$signature .= " * \n";
					$signature .= " * @return string  The HTML-ready value\n";
					$signature .= " */\n";
				}
				$prepare_method = 'prepare' . fGrammar::camelize($column, TRUE);
				$signature .= 'public function prepare' . $prepare_method . '()';
				
				$signatures[$prepare_method] = $signature;
			}
		}
		
		if (isset(self::$number_columns[$class])) {
			foreach(self::$number_columns[$class] as $column => $enabled) {
				$camelized_column = fGrammar::camelize($column, TRUE);
				$type             = fORMSchema::getInstance()->getColumnInfo(fORM::tablize($class), $column, 'type');
				
				// Get and set methods
				$signature = '';
				if ($include_doc_comments) {
					$signature .= "/**\n";
					$signature .= " * Gets the current value of " . $column . "\n";
					$signature .= " * \n";
					$signature .= " * @return fNumber  The current value\n";
					$signature .= " */\n";
				}
				$get_method = 'get' . $camelized_column;
				$signature .= 'public function ' . $get_method . '()';
				
				$signatures[$get_method] = $signature;
				
				
				$signature = '';
				if ($include_doc_comments) {
					$signature .= "/**\n";
					$signature .= " * Sets the value for " . $column . "\n";
					$signature .= " * \n";
					$signature .= " * @param  fNumber|string|integer \$" . $column . "  The new value - don't use floats since they are imprecise\n";
					$signature .= " * @return void\n";
					$signature .= " */\n";
				}
				$set_method = 'set' . $camelized_column;
				$signature .= 'public function ' . $set_method . '($' . $column . ')';
				
				$signatures[$set_method] = $signature;
				
				$signature = '';
				if ($include_doc_comments) {
					$signature .= "/**\n";
					$signature .= " * Encodes the value of " . $column . " for output into an HTML form\n";
					$signature .= " * \n";
					$signature .= " * If the value is an fNumber object, the ->__toString() method will be called\n";
					$signature .= " * resulting in the value without any thousands separators\n";
					$signature .= " * \n";
					if ($type == 'float') {
						$signature .= " * @param  integer \$decimal_places  The number of decimal places to display - not passing any value or passing NULL will result in the intrisinc number of decimal places being shown\n"; 		
					}
					$signature .= " * @return string  The HTML form-ready value\n";
					$signature .= " */\n";
				}
				$encode_method = 'encode' . $camelized_column;
				$signature .= 'public function ' . $encode_method . '()';
				
				$signatures[$encode_method] = $signature;
				
				$signature = '';
				if ($include_doc_comments) {
					$signature .= "/**\n";
					$signature .= " * Prepares the value of " . $column . " for output into HTML\n";
					$signature .= " * \n";
					$signature .= " * If the value is an fNumber object, the ->format() method will be called\n";
					$signature .= " * resulting in the value including thousands separators\n";
					$signature .= " * \n";
					if ($type == 'float') {
						$signature .= " * @param  integer \$decimal_places  The number of decimal places to display - not passing any value or passing NULL will result in the intrisinc number of decimal places being shown\n"; 		
					}
					$signature .= " * @return string  The HTML-ready value\n";
					$signature .= " */\n";
				}
				$prepare_method = 'prepare' . $camelized_column;
				$signature .= 'public function ' . $prepare_method . '()';
				
				$signatures[$prepare_method] = $signature;
			}
		}
	}
	
	
	/**
	 * Sets the appropriate column values to a random string if the object is new
	 * 
	 * @internal
	 * 
	 * @param  fActiveRecord $object            The fActiveRecord instance
	 * @param  array         &$values           The current values
	 * @param  array         &$old_values       The old values
	 * @param  array         &$related_records  Any records related to this record
	 * @return string  The formatted link
	 */
	static public function setRandomStrings($object, &$values, &$old_values, &$related_records)
	{
		if ($object->exists()) {
			return;
		}
		
		$class = get_class($object);
		$table = fORM::tablize($class);
		
		foreach (self::$random_columns[$class] as $column => $settings) {
			
			// Check to see if this is a unique column
			$unique_keys      = fORMSchema::getInstance()->getKeys($table, 'unique');
			$is_unique_column = FALSE;
			foreach ($unique_keys as $unique_key) {
				if ($unique_key == array($column)) {
					$is_unique_column = TRUE;
					do {
						$value = fCryptography::randomString($settings['length'], $settings['type']);
						
						// See if this is unique
						$sql = "SELECT " . $column . " FROM " . $table . " WHERE " . $column . " = " . fORMDatabase::getInstance()->escape('string', $value);
					
					} while (fORMDatabase::getInstance()->query($sql)->getReturnedRows());
				}
			}
			
			// If is is not a unique column, just generate a value
			if (!$is_unique_column) {
				$value = fCryptography::randomString($settings['length'], $settings['type']);
			}
			
			fActiveRecord::assign($values, $old_values, $column, $value);
		}
	}
	
	
	/**
	 * Validates all email columns
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
	static public function validateEmailColumns($object, &$values, &$old_values, &$related_records, &$validation_messages)
	{
		$class = get_class($object);
		
		if (empty(self::$email_columns[$class])) {
			return;
		}
		
		foreach (self::$email_columns[$class] as $column => $enabled) {
			if (!fCore::stringlike($values[$column])) {
				continue;
			}
			if (!preg_match('#^[a-z0-9\\.\'_\\-\\+]+@(?:[a-z0-9\\-]+\.)+[a-z]{2,}$#i', $values[$column])) {
				$validation_messages[] = fGrammar::compose(
					'%s: Please enter an email address in the form name@example.com',
					fORM::getColumnName($class, $column)
				);
			}
		}
	}
	
	
	/**
	 * Validates all link columns
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
	static public function validateLinkColumns($object, &$values, &$old_values, &$related_records, &$validation_messages)
	{
		$class = get_class($object);
		
		if (empty(self::$link_columns[$class])) {
			return;
		}
		
		foreach (self::$link_columns[$class] as $column => $enabled) {
			if (!fCore::stringlike($values[$column])) {
				continue;
			}
			if (!preg_match('#^(http(s)?://|/|([a-z0-9\\-]+\.)+[a-z]{2,})#i', $values[$column])) {
				$validation_messages[] = fGrammar::compose(
					'%s: Please enter a link in the form http://www.example.com',
					fORM::getColumnName($class, $column)
				);
			}
		}
	}
	
	
	/**
	 * Forces use as a static class
	 * 
	 * @return fORMColumn
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