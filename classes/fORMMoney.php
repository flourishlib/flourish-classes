<?php
/**
 * Provides money functionality for {@link fActiveRecord} classes
 * 
 * @copyright  Copyright (c) 2008 William Bond
 * @author     William Bond [wb] <will@flourishlib.com>
 * @license    http://flourishlib.com/license
 * 
 * @package    Flourish
 * @link       http://flourishlib.com/fORMMoney
 * 
 * @version    1.0.0b
 * @changes    1.0.0b  The initial implementation [wb, 2008-09-05]
 */
class fORMMoney
{
	/**
	 * Columns that store currency information for a money column
	 * 
	 * @var array
	 */
	static private $currency_columns = array();
	
	/**
	 * Columns that should be formatted as money
	 * 
	 * @var array
	 */
	static private $money_columns = array();
	
	
	/**
	 * Sets a column to be formatted as an fMoney object
	 * 
	 * @param  mixed  $class            The class name or instance of the class to set the column format
	 * @param  string $column           The column to format as an fMoney object
	 * @param  string $currency_column  If specified, this column will store the currency of the fMoney object
	 * @return void
	 */
	static public function configureMoneyColumn($class, $column, $currency_column=NULL)
	{
		$class     = fORM::getClass($class);
		$table     = fORM::tablize($class);
		$data_type = fORMSchema::getInstance()->getColumnInfo($table, $column, 'type');
		
		$valid_data_types = array('float');
		if (!in_array($data_type, $valid_data_types)) {
			fCore::toss(
				'fProgrammerException',
				fGrammar::compose(
					'The column specified, %1$s, is a %2$s column. Must be %3$s to be set as a money column.',
					fCore::dump($column),
					$data_type,
					join(', ', $valid_data_types)
				)
			);
		}
		
		if ($currency_column !== NULL) {
			$currency_column_data_type = fORMSchema::getInstance()->getColumnInfo($table, $currency_column, 'type');
			$valid_currency_column_data_types = array('varchar', 'char', 'text');
			if (!in_array($currency_column_data_type, $valid_currency_column_data_types)) {
				fCore::toss(
					'fProgrammerException',
					fGrammar::compose(
						'The currency column specified, %1$s, is a %2$s column. Must be %3$s to be set as a currency column.',
						fCore::dump($currency_column),
						$currency_column_data_type,
						join(', ', $valid_currency_column_data_types)
					)
				);
			}
		}
		
		$camelized_column = fGrammar::camelize($column, TRUE);
		
		fORM::registerHookCallback(
			$class,
			'replace::inspect' . $camelized_column . '()',
			array('fORMMoney', 'inspect')
		);
		
		fORM::registerHookCallback(
			$class,
			'replace::encode' . $camelized_column . '()',
			array('fORMMoney', 'encodeMoneyColumn')
		);
		
		fORM::registerHookCallback(
			$class,
			'replace::prepare' . $camelized_column . '()',
			array('fORMMoney', 'prepareMoneyColumn')
		);
		
		$hook     = 'post::validate()';
		$callback = array('fORMMoney', 'validateMoneyColumns');
		if (!fORM::checkHookCallback($class, $hook, $callback)) {
			fORM::registerHookCallback($class, $hook, $callback);
		}
		
		fORM::registerReflectCallback(
			$class,
			array('fORMMoney', 'reflect')
		);
		
		$value = FALSE;
		
		if ($currency_column) {
			$value = $currency_column;	
			
			if (empty(self::$currency_columns[$class])) {
				self::$currency_columns[$class] = array();
			}
			self::$currency_columns[$class][$currency_column] = $column;
			
			$hook     = 'post::loadFromResult()';
			$callback = array('fORMMoney', 'makeMoneyObjects');
			if (!fORM::checkHookCallback($class, $hook, $callback)) {
				fORM::registerHookCallback($class, $hook, $callback);
			}
			
			$hook     = 'pre::validate()';
			$callback = array('fORMMoney', 'makeMoneyObjects');
			if (!fORM::checkHookCallback($class, $hook, $callback)) {
				fORM::registerHookCallback($class, $hook, $callback);
			}
			
			fORM::registerHookCallback(
				$class,
				'replace::set' . $camelized_column . '()',
				array('fORMMoney', 'setMoneyColumn')
			);
			
			fORM::registerHookCallback(
				$class,
				'replace::set' . fGrammar::camelize($currency_column, TRUE) . '()',
				array('fORMMoney', 'setCurrencyColumn')
			);
		
		} else {
			fORM::registerObjectifyCallback(
				$class,
				$column,
				array('fORMMoney', 'objectifyMoney')
			);
		}
		
		if (empty(self::$money_columns[$class])) {
			self::$money_columns[$class] = array();
		}
		
		self::$money_columns[$class][$column] = $value;
	}
	
	
	/**
	 * Encodes a money column by calling {@link fMoney::__toString()}
	 * 
	 * @internal
	 * 
	 * @param  fActiveRecord $object            The fActiveRecord instance
	 * @param  array         &$values           The current values
	 * @param  array         &$old_values       The old values
	 * @param  array         &$related_records  Any records related to this record
	 * @param  string        &$method_name      The method that was called
	 * @param  array         &$parameters       The parameters passed to the method
	 * @return string  The encoded monetary value
	 */
	static public function encodeMoneyColumn($object, &$values, &$old_values, &$related_records, &$method_name, &$parameters)
	{
		list ($action, $column) = fORM::parseMethod($method_name);
		
		$value = $values[$column];
		
		if ($value instanceof fMoney) {
			$value = $value->__toString();
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
		
		unset($info['valid_values']);
		unset($info['max_length']);
		unset($info['auto_increment']);
		
		$info['feature'] = 'money';
		
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
	 * Turns a float value into an {@link fMoney} object with a currency specified by another column
	 * 
	 * @internal
	 * 
	 * @param  fActiveRecord $object            The fActiveRecord instance
	 * @param  array         &$values           The current values
	 * @param  array         &$old_values       The old values
	 * @param  array         &$related_records  Any records related to this record
	 * @return void
	 */
	static public function makeMoneyObjects($object, &$values, &$old_values, &$related_records)
	{
		$class = get_class($object);
		
		if (!isset(self::$currency_columns[$class])) {
			return;	
		}
		
		foreach(self::$currency_columns[$class] as $currency_column => $value_column) {
			self::objectifyMoneyWithCurrency($values, $old_values, $value_column, $currency_column);
		}	
	}
	
	
	/**
	 * Turns a monetary value into an {@link fMoney} object
	 * 
	 * @internal
	 * 
	 * @param  string $class   The class this value is for
	 * @param  string $column  The column the value is in
	 * @param  mixed  $value   The value
	 * @return mixed  The {@link fMoney} object or raw value
	 */
	static public function objectifyMoney($class, $column, $value)
	{
		if (!fCore::stringlike($value)) {
			return $value;
		}
		
		try {
			return new fMoney($value);
			 
		// If there was some error creating the money object, just return the raw value
		} catch (fExpectedException $e) {
			return $value;
		}
	}
	
	
	/**
	 * Turns a monetary value into an {@link fMoney} object with a currency specified by another column
	 * 
	 * @internal
	 * 
	 * @param  array  &$values          The current values
	 * @param  array  &$old_values      The old values
	 * @param  string $value_column     The column holding the value
	 * @param  string $currency_column  The column holding the currency code
	 * @return void
	 */
	static public function objectifyMoneyWithCurrency(&$values, &$old_values, $value_column, $currency_column)
	{
		if (!fCore::stringlike($values[$value_column])) {
			return;
		}
			
		try {
			$value = $values[$value_column];
			if ($value instanceof fMoney) {
				$value = $value->__toString();	
			}
			
			$currency = $values[$currency_column];
			if (!$currency && $currency !== '0' && $currency !== 0) {
				$currency = NULL;	
			}
			
			$value = new fMoney($value, $currency);
			 
			if (!empty($old_values[$currency_column]) && empty($old_values[$value_column])) {
				fActiveRecord::assign($values, $old_values, $value_column, $value);		
			} else {
				$values[$value_column] = $value;
			}
			
			if ($values[$currency_column] === NULL) {
				fActiveRecord::assign($values, $old_values, $currency_column, $value->getCurrency());
			}
			 
		// If there was some error creating the money object, we just leave all values alone
		} catch (fExpectedException $e) { }	
	}
	
	
	/**
	 * Prepares a money column by calling {@link fMoney::format()}
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
	static public function prepareMoneyColumn($object, &$values, &$old_values, &$related_records, &$method_name, &$parameters)
	{
		list ($action, $column) = fORM::parseMethod($method_name);
		
		if (empty($values[$column])) {
			return $values[$column];
		}
		$value = $values[$column];
		
		if ($value instanceof fMoney) {
			$value = $value->format();
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
		if (!isset(self::$money_columns[$class])) {
			return;	
		}
		
		foreach(self::$money_columns[$class] as $column => $enabled) {
			$camelized_column = fGrammar::camelize($column, TRUE);
			
			// Get and set methods
			$signature = '';
			if ($include_doc_comments) {
				$signature .= "/**\n";
				$signature .= " * Gets the current value of " . $column . "\n";
				$signature .= " * \n";
				$signature .= " * @return fMoney  The current value\n";
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
				$signature .= " * @param  fMoney|string|integer \$" . $column . "  The new value - a string or integer will be converted to the default currency (if defined)\n";
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
				$signature .= " * If the value is an fMoney object, the ->__toString() method will be called\n";
				$signature .= " * resulting in the value minus the currency symbol and thousands separators\n";
				$signature .= " * \n";
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
				$signature .= " * If the value is an fMoney object, the ->format() method will be called\n";
				$signature .= " * resulting in the value including the currency symbol and thousands separators\n";
				$signature .= " * \n";
				$signature .= " * @return string  The HTML-ready value\n";
				$signature .= " */\n";
			}
			$prepare_method = 'prepare' . $camelized_column;
			$signature .= 'public function ' . $prepare_method . '()';
			
			$signatures[$prepare_method] = $signature;
		}
	}
	
	
	/**
	 * Sets the currency column and then tries to objectify the related money column
	 * 
	 * @internal
	 * 
	 * @param  fActiveRecord $object            The fActiveRecord instance
	 * @param  array         &$values           The current values
	 * @param  array         &$old_values       The old values
	 * @param  array         &$related_records  Any records related to this record
	 * @param  string        &$method_name      The method that was called
	 * @param  array         &$parameters       The parameters passed to the method
	 * @return void
	 */
	static public function setCurrencyColumn($object, &$values, &$old_values, &$related_records, &$method_name, &$parameters)
	{
		list ($action, $column) = fORM::parseMethod($method_name);
		
		$class = get_class($object);
		
		if (!isset($parameters[0])) {
			fCore::toss(
				'fProgrammerException',
				fGrammar::compose(
					'The method, %s, requires at least one parameter',
					$method_name . '()'
				)
			);	
		}
		
		fActiveRecord::assign($values, $old_values, $column, $parameters[0]);
		
		// See if we can make an fMoney object out of the values
		self::objectifyMoneyWithCurrency(
			$values,
			$old_values,
			self::$currency_columns[$class][$column],
			$column
		);
	}
	
	
	/**
	 * Sets the money column and then tries to objectify it with an related currency column
	 * 
	 * @internal
	 * 
	 * @param  fActiveRecord $object            The fActiveRecord instance
	 * @param  array         &$values           The current values
	 * @param  array         &$old_values       The old values
	 * @param  array         &$related_records  Any records related to this record
	 * @param  string        &$method_name      The method that was called
	 * @param  array         &$parameters       The parameters passed to the method
	 * @return void
	 */
	static public function setMoneyColumn($object, &$values, &$old_values, &$related_records, &$method_name, &$parameters)
	{
		list ($action, $column) = fORM::parseMethod($method_name);
		
		$class = get_class($object);
		
		if (!isset($parameters[0])) {
			fCore::toss(
				'fProgrammerException',
				fGrammar::compose(
					'The method, %s, requires at least one parameter',
					$method_name . '()'
				)
			);	
		}
		
		$value = $parameters[0];
		
		fActiveRecord::assign($values, $old_values, $column, $value);
		
		$currency_column = self::$money_columns[$class][$column];
		
		// See if we can make an fMoney object out of the values
		self::objectifyMoneyWithCurrency($values, $old_values, $column, $currency_column);
		
		if ($currency_column) {
			if ($value instanceof fMoney) {
				fActiveRecord::assign($values, $old_values, $currency_column, $value->getCurrency());
			}	
		}
	}
	
	
	/**
	 * Validates all money columns
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
	static public function validateMoneyColumns($object, &$values, &$old_values, &$related_records, &$validation_messages)
	{
		$class = get_class($object);
		
		if (empty(self::$money_columns[$class])) {
			return;
		}
		
		foreach (self::$money_columns[$class] as $column => $currency_column) {
			if ($values[$column] instanceof fMoney || $values[$column] === NULL) {
				continue;
			}
			if ($currency_column && !in_array($values[$currency_column], fMoney::getCurrencies())) {
				$validation_messages[] = fGrammar::compose(
					'%s: The currency specified is invalid',
					fORM::getColumnName($class, $currency_column)
				);	
				
			} else {
				$validation_messages[] = fGrammar::compose(
					'%s: Please enter a monetary value',
					fORM::getColumnName($class, $column)
				);
			}
		}
	}
	
	
	/**
	 * Forces use as a static class
	 * 
	 * @return fORMMoney
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