<?php
/**
 * Provides database schema information
 * 
 * @copyright  Copyright (c) 2007-2008 William Bond
 * @author     William Bond [wb] <will@flourishlib.com>
 * @license    http://flourishlib.com/license
 * 
 * @package    Flourish
 * @link       http://flourishlib.com/fISchema
 * 
 * @internal
 * 
 * @version    1.0.0b
 * @changes    1.0.0b  The initial definition [wb, 2008-01-15]
 */
interface fISchema
{
	/**
	 * Returns column information for the table specified
	 * 
	 * If only a table is specified, column info is in the following format:
	 * 
	 * <pre>
	 * array(
	 *     (string) {column name} => array(
	 *         'type'           => (string)  {data type},
	 *         'not_null'       => (boolean) {if value can't be null},
	 *         'default'        => (mixed)   {the default value},
	 *         'valid_values'   => (array)   {the valid values for a varchar field},
	 *         'max_length'     => (integer) {the maximum length in a varchar field},
	 *         'auto_increment' => (boolean) {if the integer column is auto increment or serial}
	 *     ),...
	 * )
	 * </pre>
	 * 
	 * If a table and column are specified, column info is in the following format:
	 * 
	 * <pre>
	 * array(
	 *     'type'           => (string)  {data type},
	 *     'not_null'       => (boolean) {if value can't be null},
	 *     'default'        => (mixed)   {the default value},
	 *     'max_length'     => (integer) {the maximum length in a char/varchar field},
	 *     'decimal_places' => (integer) {the number of decimal places for a decimal/numeric/money/smallmoney field},
	 *     'auto_increment' => (boolean) {if the integer primary key column is a serial/autoincrement/auto_increment/indentity column}
	 * )
	 * </pre>
	 * 
	 * If a table, column and element are specified, returned value is the single element specified.
	 * 
	 * The 'type' element is homogenized to a value from the following list:
	 *   - varchar
	 *   - char
	 *   - text
	 *   - integer
	 *   - float
	 *   - timestamp
	 *   - date
	 *   - time
	 *   - boolean
	 *   - blob
	 * 
	 * @param  string $table    The table to get the column info for
	 * @param  string $column   The column to get the info for
	 * @param  string $element  The element to return ('type', 'not_null', 'default', 'valid_values', 'max_length', 'decimal_places', 'auto_increment')
	 * @return array  The column info for the table/column/element specified (see method description for format)
	 */
	public function getColumnInfo($table, $column=NULL, $element=NULL);
	
	
	/**
	 * Returns a list of primary key, foreign key and unique key constraints for the table specified
	 * 
	 * The structure of the returned array is:
	 * 
	 * <pre>
	 * array(
	 *      'primary' => array(
	 *          {column name},...
	 *      ),
	 *      'unique'  => array(
	 *          array(
	 *              {column name},...
	 *          ),...
	 *      ),
	 *      'foreign' => array(
	 *          array(
	 *              'column'         => {column name},
	 *              'foreign_table'  => {foreign table name},
	 *              'foreign_column' => {foreign column name},
	 *              'on_delete'      => {the ON DELETE action: 'no_action', 'restrict', 'cascade', 'set_null', or 'set_default'},
	 *              'on_update'      => {the ON UPDATE action: 'no_action', 'restrict', 'cascade', 'set_null', or 'set_default'}
	 *          ),...
	 *      )
	 * )
	 * </pre>
	 * 
	 * @param  string $table     The table to return the keys for
	 * @param  string $key_type  The type of key to return ('primary', 'foreign', 'unique')
	 * @return array  An array of all keys, or just the type specified (see method description for format)
	 */
	public function getKeys($table, $key_type=NULL);
	
	
	/**
	 * Returns a list of one-to-one, many-to-one, one-to-many and many-to-many relationships for the table specified
	 * 
	 * The structure of the returned array is:
	 * 
	 * <pre>
	 * array(
	 *     'one-to-one' => array(
	 *         array(
	 *             'table'          => (string) {the name of the table this relationship is for},
	 *             'column'         => (string) {the column in the specified table},
	 *             'related_table'  => (string) {the related table},
	 *             'related_column' => (string) {the related column}
	 *         ),...
	 *     ),
	 *     'many-to-one' => array(
	 *         array(
	 *             'table'          => (string) {the name of the table this relationship is for},
	 *             'column'         => (string) {the column in the specified table},
	 *             'related_table'  => (string) {the related table},
	 *             'related_column' => (string) {the related column}
	 *         ),...
	 *     ),
	 *     'one-to-many' => array(
	 *         array(
	 *             'table'          => (string) {the name of the table this relationship is for},
	 *             'column'         => (string) {the column in the specified table},
	 *             'related_table'  => (string) {the related table},
	 *             'related_column' => (string) {the related column},
	 *             'on_delete'      => (string) {the ON DELETE action: 'no_action', 'restrict', 'cascade', 'set_null', or 'set_default'},
	 *             'on_update'      => (string) {the ON UPDATE action: 'no_action', 'restrict', 'cascade', 'set_null', or 'set_default'}
	 *         ),...
	 *     ),
	 *     'many-to-many' => array(
	 *         array(
	 *             'table'               => (string) {the name of the table this relationship is for},
	 *             'column'              => (string) {the column in the specified table},
	 *             'related_table'       => (string) {the related table},
	 *             'related_column'      => (string) {the related column},
	 *             'join_table'          => (string) {the table that joins the specified table to the related table},
	 *             'join_column'         => (string) {the column in the join table that references 'column'},
	 *             'join_related_column' => (string) {the column in the join table that references 'related_column'},
	 *             'on_delete'           => (string) {the ON DELETE action: 'no_action', 'restrict', 'cascade', 'set_null', or 'set_default'},
	 *             'on_update'           => (string) {the ON UPDATE action: 'no_action', 'restrict', 'cascade', 'set_null', or 'set_default'}
	 *         ),...
	 *     )
	 * )
	 * </pre>
	 * 
	 * @param  string $table              The table to return the relationships for
	 * @param  string $relationship_type  The type of relationship to return ('one-to-one', 'many-to-one', 'one-to-many', 'many-to-many')
	 * @return array  An array of all relationships, or just the type specified (see method description for format)
	 */
	public function getRelationships($table, $relationship_type=NULL);
	
	
	/**
	 * Returns the tables in the current database
	 * 
	 * @return array  The tables in the current database
	 */
	public function getTables();
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