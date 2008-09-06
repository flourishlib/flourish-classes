<?php
/**
 * Gets information about the selected database
 * 
 * @copyright  Copyright (c) 2007-2008 William Bond
 * @author     William Bond [wb] <will@flourishlib.com>
 * @license    http://flourishlib.com/license
 * 
 * @package    Flourish
 * @link       http://flourishlib.com/fSchema
 * 
 * @version    1.0.0b
 * @changes    1.0.0b  The initial implementation [wb, 2007-09-25]
 */
class fSchema implements fISchema
{
	/**
	 * The file to cache the info to
	 * 
	 * @var string
	 */
	private $cache_file = NULL;
	
	/**
	 * The cached column info
	 * 
	 * @var array
	 */
	private $column_info = array();
	
	/**
	 * The column info to override
	 * 
	 * @var array
	 */
	private $column_info_override = array();
	
	/**
	 * A reference to an instance of the {@link fDatabase} class
	 * 
	 * @var fDatabase
	 */
	private $database = NULL;
	
	/**
	 * If the info has changed (and should be written to cache)
	 * 
	 * @var boolean
	 */
	private $info_changed = FALSE;
	
	/**
	 * The cached key info
	 * 
	 * @var array
	 */
	private $keys = array();
	
	/**
	 * The key info to override
	 * 
	 * @var array
	 */
	private $keys_override = array();
	
	/**
	 * The merged column info
	 * 
	 * @var array
	 */
	private $merged_column_info = array();
	
	/**
	 * The merged key info
	 * 
	 * @var array
	 */
	private $merged_keys = array();
	
	/**
	 * The relationships in the database
	 * 
	 * @var array
	 */
	private $relationships = array();
	
	/**
	 * The state of the info
	 * 
	 * @var string
	 */
	private $state = 'current';
	
	/**
	 * The tables in the database
	 * 
	 * @var array
	 */
	private $tables = NULL;
	
	
	/**
	 * Sets the database
	 * 
	 * @param  fDatabase $database  The {@link fDatabase} instance
	 * @return fSchema
	 */
	public function __construct(fDatabase $database)
	{
		$this->database = $database;
	}
	
	
	/**
	 * Stores the info in the cache file if set
	 * 
	 * @return void
	 */
	public function __destruct()
	{
		if ($this->cache_file && $this->info_changed) {
			$contents = serialize(array('column_info'   => $this->column_info,
										'keys'          => $this->keys));
			file_put_contents($this->cache_file, $contents);
		}
	}
	
	
	/**
	 * Checks to see if a column is part of a single-column unique key
	 * 
	 * @param  string $table   The table the column is located in
	 * @param  string $column  The column to check
	 * @return boolean  If the column is part of a single-column unique key
	 */
	private function checkForSingleColumnUniqueKey($table, $column)
	{
		foreach ($this->merged_keys[$table]['unique'] as $key) {
			if (array($column) == $key) {
				return TRUE;
			}
		}
		return FALSE;
	}
	
	
	/**
	 * Gets the column info from the database for later access
	 * 
	 * @param  string $table  The table to fetch the column info for
	 * @return void
	 */
	private function fetchColumnInfo($table)
	{
		switch ($this->database->getType()) {
			case 'mssql':
				$column_info = $this->fetchMSSQLColumnInfo($table);
				break;
			
			case 'mysql':
				$column_info = $this->fetchMySQLColumnInfo($table);
				break;
				
			case 'postgresql':
				$column_info = $this->fetchPostgreSQLColumnInfo($table);
				break;
				
			case 'sqlite':
				$column_info = $this->fetchSQLiteColumnInfo($table);
				break;
		}
			
		if (!$column_info) {
			return;	
		}
			
		$this->column_info[$table] = $column_info;
		$this->info_changed = TRUE;
	}
	
	
	/**
	 * Gets the primary key, foreign key and unique key constraints from the database
	 * 
	 * @return void
	 */
	private function fetchKeys()
	{
		switch ($this->database->getType()) {
			case 'mssql':
				$keys = $this->fetchMSSQLKeys();
				break;
				
			case 'mysql':
				$keys = $this->fetchMySQLKeys();
				break;
				
			case 'postgresql':
				$keys = $this->fetchPostgreSQLKeys();
				break;
			
			case 'sqlite':
				$keys = $this->fetchSQLiteKeys();
				break;
		}
			
		$this->keys = $keys;
		$this->info_changed = TRUE;
	}
	
	
	/**
	 * Gets the column info from a MSSQL database
	 * 
	 * The returned array is in the format:
	 * 
	 * <pre>
	 * array(
	 *     (string) {column name} => array(
	 *         'type'           => (string)  {data type},
	 *         'not_null'       => (boolean) {if value can't be null},
	 *         'default'        => (mixed)   {the default value},
	 *         'valid_values'   => (array)   {the valid values for a char/varchar field},
	 *         'max_length'     => (integer) {the maximum length in a char/varchar field},
	 *         'decimal_places' => (integer) {the number of decimal places for a decimal/numeric/money/smallmoney field},
	 *         'auto_increment' => (boolean) {if the integer primary key column is an identity column}
	 *     ),...
	 * )
	 * </pre>
	 * 
	 * @param  string $table  The table to fetch the column info for
	 * @return array  The column info for the table specified (see method description for details)
	 */
	private function fetchMSSQLColumnInfo($table)
	{
		$column_info = array();
		
		$data_type_mapping = array(
			'bit'			    => 'boolean',
			'tinyint'           => 'integer',
			'smallint'			=> 'integer',
			'int'				=> 'integer',
			'bigint'			=> 'integer',
			'datetime'			=> 'timestamp',
			'smalldatetime'     => 'timestamp',
			'varchar'	        => 'varchar',
			'nvarchar'          => 'varchar',
			'char'			    => 'char',
			'nchar'             => 'char',
			'real'				=> 'float',
			'float'             => 'float',
			'money'             => 'float',
			'smallmoney'        => 'float',
			'decimal'			=> 'float',
			'numeric'			=> 'float',
			'binary'			=> 'blob',
			'varbinary'         => 'blob',
			'image'             => 'blob',
			'text'				=> 'text',
			'ntext'             => 'text'
		);
		
		// Get the column info
		$sql = "SELECT
						c.column_name              AS 'column',
						c.data_type                AS 'type',
						c.is_nullable              AS not_null,
						c.column_default           AS 'default',
						c.character_maximum_length AS max_length,
						c.numeric_scale            AS decimal_places,
						CASE WHEN COLUMNPROPERTY(OBJECT_ID(QUOTENAME(c.table_schema) + '.' + QUOTENAME(c.table_name)), c.column_name, 'IsIdentity') = 1 AND
								  OBJECTPROPERTY(OBJECT_ID(QUOTENAME(c.table_schema) + '.' + QUOTENAME(c.table_name)), 'IsMSShipped') = 0
							 THEN '1'
							 ELSE '0'
						  END AS auto_increment,
						cc.check_clause            AS 'constraint'
					FROM
						INFORMATION_SCHEMA.COLUMNS AS c LEFT JOIN
						INFORMATION_SCHEMA.CONSTRAINT_COLUMN_USAGE AS ccu ON c.column_name = ccu.column_name AND c.table_name = ccu.table_name AND c.table_catalog = ccu.table_catalog LEFT JOIN
						INFORMATION_SCHEMA.CHECK_CONSTRAINTS AS cc ON ccu.constraint_name = cc.constraint_name AND ccu.constraint_catalog = cc.constraint_catalog
					WHERE
						c.table_name = '" . $table . "' AND
						c.table_catalog = '" . $this->database->getDatabase() . "'";
		$result = $this->database->query($sql);
		
		foreach ($result as $row) {
			$info = array();
			
			foreach ($data_type_mapping as $data_type => $mapped_data_type) {
				if (stripos($row['type'], $data_type) === 0) {
					$info['type'] = $mapped_data_type;
					break;
				}
			}
			if (!isset($info['type'])) {
				$info['type'] = $row['type'];
			}
			
			// Handle decimal places for numeric/decimals
			if (in_array($row['type'], array('numeric', 'decimal'))) {
				$info['decimal_places'] = $row['decimal_places'];
			}
			
			// Handle decimal places for money/smallmoney
			if (in_array($row['type'], array('money', 'smallmoney'))) {
				$info['decimal_places'] = 2;
			}
			
			// Handle the special data for varchar columns
			if (in_array($info['type'], array('char', 'varchar'))) {
				$info['max_length'] = $row['max_length'];
			}
			
			// If the column has a constraint, look for valid values
			if (in_array($info['type'], array('char', 'varchar')) && !empty($row['constraint'])) {
				if (preg_match('#^\(((?:(?: OR )?\[[^\]]+\]=\'(?:\'\'|[^\']+)+\')+)\)$#', $row['constraint'], $matches)) {
					$valid_values = explode(' OR ', $matches[1]);
					foreach ($valid_values as $key => $value) {
						$valid_values[$key] = substr($value, 4 + strlen($row['column']), -1);
					}
					$info['valid_values'] = $valid_values;
				}
			}
			
			// Handle auto increment
			if ($row['auto_increment']) {
				$info['auto_increment'] = TRUE;
			}
			
			// Handle default values
			if ($row['default'] !== NULL) {
				if (in_array($info['type'], array('char', 'varchar', 'text', 'timestamp')) ) {
					$info['default'] = substr($row['default'], 2, -2);
				} elseif (in_array($info['type'], array('integer', 'float', 'boolean')) ) {
					$info['default'] = str_replace(array('(', ')'), '', $row['default']);
				} else {
					$info['default'] = pack('H*', substr($row['default'], 3, -1));
				}
			}
			
			// Handle not null
			$info['not_null'] = ($row['not_null'] == 'NO') ? TRUE : FALSE;
			
			$column_info[$row['column']] = $info;
		}
		
		return $column_info;
	}
	
	
	/**
	 * Fetches the key info for an MSSQL database
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
	 * @return array  The key info arrays for every table in the database (see method description for details)
	 */
	private function fetchMSSQLKeys()
	{
		$keys = array();
		
		$tables   = $this->getTables();
		foreach ($tables as $table) {
			$keys[$table] = array();
			$keys[$table]['primary'] = array();
			$keys[$table]['unique']  = array();
			$keys[$table]['foreign'] = array();
		}
		
		$sql  = "SELECT
						c.table_name AS 'table',
						kcu.constraint_name AS constraint_name,
						CASE c.constraint_type
							WHEN 'PRIMARY KEY' THEN 'primary'
							WHEN 'FOREIGN KEY' THEN 'foreign'
							WHEN 'UNIQUE' THEN 'unique'
						END AS 'type',
						kcu.column_name AS 'column',
						ccu.table_name AS foreign_table,
						ccu.column_name AS foreign_column,
						REPLACE(LOWER(rc.delete_rule), ' ', '_') AS on_delete,
						REPLACE(LOWER(rc.update_rule), ' ', '_') AS on_update
					FROM
						INFORMATION_SCHEMA.TABLE_CONSTRAINTS AS c INNER JOIN
						INFORMATION_SCHEMA.KEY_COLUMN_USAGE AS kcu ON c.table_name = kcu.table_name AND c.constraint_name = kcu.constraint_name LEFT JOIN
						INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS AS rc ON c.constraint_name = rc.constraint_name LEFT JOIN
						INFORMATION_SCHEMA.CONSTRAINT_COLUMN_USAGE AS ccu ON ccu.constraint_name = rc.unique_constraint_name
					WHERE
						c.constraint_catalog = '" . $this->database->getDatabase() . "'
					ORDER BY
						LOWER(c.table_name),
						c.constraint_type,
						LOWER(kcu.constraint_name),
						LOWER(kcu.column_name)";
		
		$result = $this->database->query($sql);
		
		$last_name  = '';
		$last_table = '';
		$last_type  = '';
		foreach ($result as $row) {
			if ($row['constraint_name'] != $last_name) {
				if ($last_name) {
					if ($last_type == 'foreign' || $last_type == 'unique') {
						$keys[$last_table][$last_type][] = $temp;
					} else {
						$keys[$last_table][$last_type] = $temp;
					}
				}
				$temp = array();
				if ($row['type'] == 'foreign') {
					$temp['column']         = $row['column'];
					$temp['foreign_table']  = $row['foreign_table'];
					$temp['foreign_column'] = $row['foreign_column'];
					$temp['on_delete']      = 'no_action';
					$temp['on_update']      = 'no_action';
					if (!empty($row['on_delete'])) {
						$temp['on_delete'] = $row['on_delete'];
					}
					if (!empty($row['on_update'])) {
						$temp['on_update'] = $row['on_update'];
					}
				} else {
					$temp[] = $row['column'];
				}
				$last_table = $row['table'];
				$last_name  = $row['constraint_name'];
				$last_type  = $row['type'];
			} else {
				$temp[] = $row['column'];
			}
		}
		if (isset($temp)) {
			if ($last_type == 'foreign') {
				$keys[$last_table][$last_type][] = $temp;
			} else {
				$keys[$last_table][$last_type] = $temp;
			}
		}
		
		return $keys;
	}
	
	
	/**
	 * Gets the column info from a MySQL database
	 * 
	 * The returned array is in the format:
	 * 
	 * <pre>
	 * array(
	 *     (string) {column name} => array(
	 *         'type'           => (string)  {data type},
	 *         'not_null'       => (boolean) {if value can't be null},
	 *         'default'        => (mixed)   {the default value},
	 *         'valid_values'   => (array)   {the valid values for a char/varchar field},
	 *         'max_length'     => (integer) {the maximum length in a char/varchar field},
	 *         'decimal_places' => (integer) {the number of decimal places for a decimal field},
	 *         'auto_increment' => (boolean) {if the integer primary key column is auto_increment}
	 *     ),...
	 * )
	 * </pre>
	 * 
	 * @param  string $table  The table to fetch the column info for
	 * @return array  The column info for the table specified (see method description for details)
	 */
	private function fetchMySQLColumnInfo($table)
	{
		$data_type_mapping = array(
			'tinyint(1)'		=> 'boolean',
			'tinyint'			=> 'integer',
			'smallint'			=> 'integer',
			'int'				=> 'integer',
			'bigint'			=> 'integer',
			'datetime'			=> 'timestamp',
			'date'				=> 'date',
			'time'				=> 'time',
			'enum'				=> 'varchar',
			'varchar'			=> 'varchar',
			'char'				=> 'char',
			'float'				=> 'float',
			'double'			=> 'float',
			'decimal'			=> 'float',
			'tinyblob'			=> 'blob',
			'blob'				=> 'blob',
			'mediumblob'		=> 'blob',
			'longblob'			=> 'blob',
			'tinytext'			=> 'text',
			'text'				=> 'text',
			'mediumtext'		=> 'text',
			'longtext'			=> 'text'
		);
		
		$column_info = array();
		
		$result     = $this->database->query('SHOW CREATE TABLE ' . $table);
		
		try {
			$row        = $result->fetchRow();
			$create_sql = $row['Create Table'];
		} catch (fNoResultsException $e) {
			return array();			
		}
		
		preg_match_all('#(?<=,|\()\s+(?:"|\`)(\w+)(?:"|\`)\s+(?:([a-z]+)(?:\(([^)]+)\))?)( NOT NULL)?(?: default ((?:[^, \']*|\'(?:\'\'|[^\']+)*\')))?( auto_increment)?\s*(?:,|\s*(?=\)))#mi', $create_sql, $matches, PREG_SET_ORDER);
		
		foreach ($matches as $match) {
			$info = array();
			
			foreach ($data_type_mapping as $data_type => $mapped_data_type) {
				if (stripos($match[2], $data_type) === 0) {
					$info['type'] = $mapped_data_type;
					break;
				}
			}
			if (!isset($info['type'])) {
				$info['type'] = preg_replace('#^([a-z ]+).*$#i', '\1', $match[2]);
			}
		
			if (stripos($match[2], 'enum') === 0) {
				$info['valid_values'] = preg_replace("/^'|'\$/", '', explode(",", $match[3]));
				$match[3] = 0;
				foreach ($info['valid_values'] as $valid_value) {
					if (strlen(utf8_decode($valid_value)) > $match[3]) {
						$match[3] = strlen(utf8_decode($valid_value));
					}
				}
			}
			
			// Type specific information
			if (in_array($info['type'], array('char', 'varchar'))) {
				$info['max_length'] = $match[3];
			}
			
			// Grab the number of decimal places
			if (stripos($match[2], 'decimal') === 0) {
				if (preg_match('#^\s*\d+\s*,\s*(\d+)\s*$#', $match[3], $data_type_info)) {
					$info['decimal_places'] = $data_type_info[1];
				}
			}
			
			// Not null
			$info['not_null'] = (!empty($match[4])) ? TRUE : FALSE;
		
			// Default values
			if (!empty($match[5]) && $match[5] != 'NULL') {
				$info['default'] = preg_replace("/^'|'\$/", '', $match[5]);
			}
			if ($info['type'] == 'boolean' && isset($info['default'])) {
				$info['default'] = (boolean) $info['default'];
			}
		
			// Auto increment fields
			if (!empty($match[6])) {
				$info['auto_increment'] = TRUE;
			}
		
			$column_info[$match[1]] = $info;
		}
		
		return $column_info;
	}
	
	
	/**
	 * Fetches the keys for a MySQL database
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
	 * @return array  The keys arrays for every table in the database (see method description for details)
	 */
	private function fetchMySQLKeys()
	{
		$tables   = $this->getTables();
		$keys = array();
		
		foreach ($tables as $table) {
			$keys[$table] = array();
			$keys[$table]['primary'] = array();
			$keys[$table]['foreign'] = array();
			$keys[$table]['unique']  = array();
			
			$result = $this->database->query('SHOW CREATE TABLE `' . substr($this->database->escapeString($table), 1, -1) . '`');
			$row    = $result->fetchRow();
			// Primary keys
			preg_match_all('/PRIMARY KEY\s+\("([^"]+)"\),?\n/U', $row['Create Table'], $matches, PREG_SET_ORDER);
			if (!empty($matches)) {
				$keys[$table]['primary'] = explode('","', $matches[0][1]);
			}
			// Unique keys
			preg_match_all('/UNIQUE KEY\s+"([^"]+)"\s+\("([^"]+)"\),?\n/U', $row['Create Table'], $matches, PREG_SET_ORDER);
			foreach ($matches as $match) {
				$keys[$table]['unique'][] = explode('","', $match[2]);
			}
			// Foreign keys
			preg_match_all('#FOREIGN KEY \("([^"]+)"\) REFERENCES "([^"]+)" \("([^"]+)"\)(?:\sON\sDELETE\s(SET\sNULL|SET\sDEFAULT|CASCADE|NO\sACTION|RESTRICT))?(?:\sON\sUPDATE\s(SET\sNULL|SET\sDEFAULT|CASCADE|NO\sACTION|RESTRICT))?#', $row['Create Table'], $matches, PREG_SET_ORDER);
			foreach ($matches as $match) {
				$temp = array('column'         => $match[1],
							  'foreign_table'  => $match[2],
							  'foreign_column' => $match[3],
							  'on_delete'      => 'no_action',
							  'on_update'      => 'no_action');
				if (isset($match[4])) {
					$temp['on_delete'] = strtolower(str_replace(' ', '_', $match[4]));
				}
				if (isset($match[5])) {
					$temp['on_update'] = strtolower(str_replace(' ', '_', $match[5]));
				}
				$keys[$table]['foreign'][] = $temp;
			}
		}
		
		return $keys;
	}
	
	
	/**
	 * Gets the column info from a PostgreSQL database
	 * 
	 * The returned array is in the format:
	 * 
	 * <pre>
	 * array(
	 *     (string) {column name} => array(
	 *         'type'           => (string)  {data type},
	 *         'not_null'       => (boolean) {if value can't be null},
	 *         'default'        => (mixed)   {the default value},
	 *         'valid_values'   => (array)   {the valid values for a char/varchar field},
	 *         'max_length'     => (integer) {the maximum length in a char/varchar field},
	 *         'decimal_places' => (integer) {the number of decimal places for a decimal/numeric field},
	 *         'auto_increment' => (boolean) {if the integer primary key column is a serial}
	 *     ),...
	 * )
	 * </pre>
	 * 
	 * @param  string $table  The table to fetch the column info for
	 * @return array  The column info for the table specified (see method description for details)
	 */
	private function fetchPostgreSQLColumnInfo($table)
	{
		$column_info = array();
		
		$data_type_mapping = array(
			'boolean'			=> 'boolean',
			'smallint'			=> 'integer',
			'int'				=> 'integer',
			'bigint'			=> 'integer',
			'serial'			=> 'integer',
			'bigserial'			=> 'integer',
			'timestamp'			=> 'timestamp',
			'date'				=> 'date',
			'time'				=> 'time',
			'character varying'	=> 'varchar',
			'character'			=> 'char',
			'real'				=> 'float',
			'double'			=> 'float',
			'numeric'			=> 'float',
			'bytea'				=> 'blob',
			'text'				=> 'text',
			'mediumtext'		=> 'text',
			'longtext'			=> 'text'
		);
		
		// PgSQL required this complicated SQL to get the column info
		$sql = "SELECT
						pg_attribute.attname                                        AS column,
						format_type(pg_attribute.atttypid, pg_attribute.atttypmod)  AS data_type,
						pg_attribute.attnotnull                                     AS not_null,
						pg_attrdef.adsrc                                            AS default,
						pg_get_constraintdef(pg_constraint.oid)                     AS constraint
					FROM
						pg_attribute LEFT JOIN
						pg_class ON pg_attribute.attrelid = pg_class.oid LEFT JOIN
						pg_type ON pg_type.oid = pg_attribute.atttypid LEFT JOIN
						pg_constraint ON pg_constraint.conrelid = pg_class.oid AND
										 pg_attribute.attnum = ANY (pg_constraint.conkey) AND
										 (pg_constraint.contype = 'c' OR
										  pg_constraint.contype = 'u') LEFT JOIN
						pg_attrdef ON pg_class.oid = pg_attrdef.adrelid AND
									  pg_attribute.attnum = pg_attrdef.adnum
					WHERE
						NOT pg_attribute.attisdropped AND
						pg_class.relname = " . $this->database->escapeString($table) . " AND
						pg_type.typname NOT IN ('oid', 'cid', 'xid', 'cid', 'xid', 'tid')
					ORDER BY
						pg_attribute.attnum,
						pg_constraint.contype";
		$result = $this->database->query($sql);
		
		foreach ($result as $row) {
			$info = array();
			
			// Get the column type
			preg_match('#([\w ]+)\s*(?:\(\s*(\d+)(?:\s*,\s*(\d+))?\s*\))?#', $row['data_type'], $column_data_type);
			
			foreach ($data_type_mapping as $data_type => $mapped_data_type) {
				if (stripos($column_data_type[1], $data_type) === 0) {
					$info['type'] = $mapped_data_type;
					break;
				}
			}
			if (!isset($info['type'])) {
				$info['type'] = $column_data_type[1];
			}
			
			// Handle the length of decimal/numeric fields
			if ($info['type'] == 'float' && isset($column_data_type[3]) && strlen($column_data_type[3]) > 0) {
				$info['decimal_places'] = (int) $column_data_type[3];
			}
			
			// Handle the special data for varchar fields
			if (in_array($info['type'], array('char', 'varchar'))) {
				$info['max_length'] = $column_data_type[2];
			}
			
			// Handle check constraints that are just simple lists
			if (in_array($info['type'], array('varchar', 'char')) && !empty($row['constraint'])) {
				if (preg_match('/CHECK[\( "]+' . $row['column'] . '[a-z\) ":]+\s+=\s+/i', $row['constraint'])) {
					if (preg_match_all("/(?!').'((''|[^']+)*)'/", $row['constraint'], $matches, PREG_PATTERN_ORDER)) {
						$info['valid_values'] = str_replace("''", "'", $matches[1]);
					}
				}
			}
			
			// Handle default values and serial data types
			if ($info['type'] == 'integer' && stripos($row['default'], 'nextval(') !== FALSE) {
				$info['auto_increment'] = TRUE;
			} elseif ($row['default'] !== NULL) {
				$info['default'] = str_replace("''", "'", preg_replace("/^'(.*)'::[a-z ]+$/i", '\1', $row['default']));
				if ($info['type'] == 'boolean') {
					$info['default'] = ($info['default'] == 'false' || !$info['default']) ? FALSE : TRUE;
				}
			}
			
			// Not null values
			$info['not_null'] = ($row['not_null'] == 't') ? TRUE : FALSE;
			
			$column_info[$row['column']] = $info;
		}
		
		return $column_info;
	}
	
	
	/**
	 * Fetches the key info for a PostgreSQL database
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
	 * @return array  The key info arrays for every table in the database (see method description for details)
	 */
	private function fetchPostgreSQLKeys()
	{
		$keys = array();
		
		$tables   = $this->getTables();
		foreach ($tables as $table) {
			$keys[$table] = array();
			$keys[$table]['primary'] = array();
			$keys[$table]['unique']  = array();
			$keys[$table]['foreign'] = array();
		}
		
		$sql  = "SELECT
						 t.relname AS table,
						 con.conname AS constraint_name,
						 CASE con.contype
							 WHEN 'f' THEN 'foreign'
							 WHEN 'p' THEN 'primary'
							 WHEN 'u' THEN 'unique'
						 END AS type,
						 col.attname AS column,
						 ft.relname AS foreign_table,
						 fc.attname AS foreign_column,
						 CASE con.confdeltype
							 WHEN 'c' THEN 'cascade'
							 WHEN 'a' THEN 'no_action'
							 WHEN 'r' THEN 'restrict'
							 WHEN 'n' THEN 'set_null'
							 WHEN 'd' THEN 'set_default'
						 END AS on_delete,
						 CASE con.confupdtype
							 WHEN 'c' THEN 'cascade'
							 WHEN 'a' THEN 'no_action'
							 WHEN 'r' THEN 'restrict'
							 WHEN 'n' THEN 'set_null'
							 WHEN 'd' THEN 'set_default'
						 END AS on_update
					 FROM
						 pg_attribute AS col INNER JOIN
						 pg_class AS t ON col.attrelid = t.oid INNER JOIN
						 pg_constraint AS con ON (col.attnum = ANY (con.conkey) AND
												  con.conrelid = t.oid) LEFT JOIN
						 pg_class AS ft ON con.confrelid = ft.oid LEFT JOIN
						 pg_attribute AS fc ON (fc.attnum = ANY (con.confkey) AND
												ft.oid = fc.attrelid)
					 WHERE
						 NOT col.attisdropped AND
						 (con.contype = 'p' OR
						  con.contype = 'f' OR
						  con.contype = 'u')
					 ORDER BY
						 t.relname,
						 con.contype,
						 con.conname,
						 col.attname";
		
		$result = $this->database->query($sql);
		
		$last_name  = '';
		$last_table = '';
		$last_type  = '';
		foreach ($result as $row) {
			if ($row['constraint_name'] != $last_name) {
				if ($last_name) {
					if ($last_type == 'foreign' || $last_type == 'unique') {
						$keys[$last_table][$last_type][] = $temp;
					} else {
						$keys[$last_table][$last_type] = $temp;
					}
				}
				$temp = array();
				if ($row['type'] == 'foreign') {
					$temp['column']         = $row['column'];
					$temp['foreign_table']  = $row['foreign_table'];
					$temp['foreign_column'] = $row['foreign_column'];
					$temp['on_delete']      = 'no_action';
					$temp['on_update']      = 'no_action';
					if (!empty($row['on_delete'])) {
						$temp['on_delete'] = $row['on_delete'];
					}
					if (!empty($row['on_update'])) {
						$temp['on_update'] = $row['on_update'];
					}
				} else {
					$temp[] = $row['column'];
				}
				$last_table = $row['table'];
				$last_name  = $row['constraint_name'];
				$last_type  = $row['type'];
			} else {
				$temp[] = $row['column'];
			}
		}
		if (isset($temp)) {
			if ($last_type == 'foreign') {
				$keys[$last_table][$last_type][] = $temp;
			} else {
				$keys[$last_table][$last_type] = $temp;
			}
		}
		
		return $keys;
	}
	
	
	/**
	 * Gets the column info from a SQLite database
	 * 
	 * The returned array is in the format:
	 * 
	 * <pre>
	 * array(
	 *     (string) {column name} => array(
	 *         'type'           => (string)  {data type},
	 *         'not_null'       => (boolean) {if value can't be null},
	 *         'default'        => (mixed)   {the default value},
	 *         'valid_values'   => (array)   {the valid values for a char/varchar field},
	 *         'max_length'     => (integer) {the maximum length in a char/varchar field},
	 *         'decimal_places' => (integer) {the number of decimal places for a decimal field},
	 *         'auto_increment' => (boolean) {if the integer primary key column is autoincrement}
	 *     ),...
	 * )
	 * </pre>
	 * 
	 * @param  string $table  The table to fetch the column info for
	 * @return array  The column info for the table specified (see method description for details)
	 */
	private function fetchSQLiteColumnInfo($table)
	{
		$column_info = array();
		
		$data_type_mapping = array(
			'boolean'			=> 'boolean',
			'smallint'			=> 'integer',
			'int'				=> 'integer',
			'bigint'			=> 'integer',
			'timestamp'			=> 'timestamp',
			'date'				=> 'date',
			'time'				=> 'time',
			'varchar'			=> 'varchar',
			'char'				=> 'char',
			'real'				=> 'float',
			'float'             => 'float',
			'double'			=> 'float',
			'decimal'			=> 'float',
			'blob'				=> 'blob',
			'text'				=> 'text'
		);
		
		$result     = $this->database->query("SELECT sql FROM sqlite_master WHERE type = 'table' AND name = " . $this->database->escapeString($table));
		
		try {
			$row        = $result->fetchRow();
			$create_sql = $row['sql'];
		} catch (fNoResultsException $e) {
			return array();			
		}
		
		preg_match_all('#(?<=,|\()\s*(\w+)\s+([a-z]+)(?:\(\s*(\d+)(?:\s*,\s*(\d+))?\s*\))?(?:(\s+NOT\s+NULL)|(?:\s+DEFAULT\s+([^, \']*|\'(?:\'\'|[^\']+)*\'))|(\s+UNIQUE)|(\s+PRIMARY\s+KEY(?:\s+AUTOINCREMENT)?)|(\s+CHECK\s*\(\w+\s+IN\s+\(\s*(?:(?:[^, \']+|\'(?:\'\'|[^\']+)*\')\s*,\s*)*\s*(?:[^, \']+|\'(?:\'\'|[^\']+)*\')\)\)))*(\s+REFERENCES\s+\w+\s*\(\s*\w+\s*\)\s*(?:\s+(?:ON\s+DELETE|ON\s+UPDATE)\s+(?:CASCADE|NO\s+ACTION|RESTRICT|SET\s+NULL|SET\s+DEFAULT))*(?:\s+(?:DEFERRABLE|NOT\s+DEFERRABLE))?)?\s*(?:,|\s*(?=\)))#mi', $create_sql, $matches, PREG_SET_ORDER);
		
		foreach ($matches as $match) {
			$info = array();
			
			foreach ($data_type_mapping as $data_type => $mapped_data_type) {
				if (stripos($match[2], $data_type) === 0) {
					$info['type'] = $mapped_data_type;
					break;
				}
			}
		
			// Type specific information
			if (in_array($info['type'], array('char', 'varchar'))) {
				$info['max_length'] = $match[3];
			}
			
			// Figure out how many decimal places for a decimal
			if (in_array(strtolower($match[2]), array('decimal', 'numeric')) && !empty($match[4])) {
				$info['decimal_places'] = $match[4];
			}
			
			// Not null
			$info['not_null'] = (!empty($match[5]) || !empty($match[8])) ? TRUE : FALSE;
		
			// Default values
			if (isset($match[6]) && $match[6] != '' && $match[6] != 'NULL') {
				$info['default'] = preg_replace("/^'|'\$/", '', $match[6]);
			}
			if ($info['type'] == 'boolean' && isset($info['default'])) {
				$info['default'] = ($info['default'] == 'f' || $info['default'] == 0 || $info['default'] == 'false') ? FALSE : TRUE;
			}
		
			// Check constraints
			if (isset($match[9]) && preg_match('/CHECK\s*\(\s*' . $match[1] . '\s+IN\s+\(\s*((?:(?:[^, \']*|\'(?:\'\'|[^\']+)*\')\s*,\s*)*(?:[^, \']*|\'(?:\'\'|[^\']+)*\'))\s*\)/i', $match[9], $check_match)) {
				$info['valid_values'] = str_replace("''", "'", preg_replace("/^'|'\$/", '', preg_split("#\s*,\s*#", $check_match[1])));
			}
		
			// Auto increment fields
			if (!empty($match[8]) && (stripos($match[8], 'autoincrement') !== FALSE || $info['type'] == 'integer')) {
				$info['auto_increment'] = TRUE;
			}
		
			$column_info[$match[1]] = $info;
		}
		
		return $column_info;
	}
	
	
	/**
	 * Fetches the key info for an SQLite database
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
	 * @return array  The key info arrays for every table in the database (see method description for details)
	 */
	private function fetchSQLiteKeys()
	{
		$tables   = $this->getTables();
		$keys = array();
		
		foreach ($tables as $table) {
			$keys[$table] = array();
			$keys[$table]['primary'] = array();
			$keys[$table]['foreign'] = array();
			$keys[$table]['unique']  = array();
			
			$result     = $this->database->query("SELECT sql FROM sqlite_master WHERE type = 'table' AND name = " . $this->database->escapeString($table));
			$row        = $result->fetchRow();
			$create_sql = $row['sql'];
			
			// Get column level key definitions
			preg_match_all('#(?<=,|\()\s*(\w+)\s+(?:[a-z]+)(?:\((?:\d+)\))?(?:(?:\s+NOT\s+NULL)|(?:\s+DEFAULT\s+(?:[^, \']*|\'(?:\'\'|[^\']+)*\'))|(\s+UNIQUE)|(\s+PRIMARY\s+KEY(?:\s+AUTOINCREMENT)?)|(?:\s+CHECK\s*\(\w+\s+IN\s+\(\s*(?:(?:[^, \']+|\'(?:\'\'|[^\']+)*\')\s*,\s*)*\s*(?:[^, \']+|\'(?:\'\'|[^\']+)*\')\)\)))*(\s+REFERENCES\s+(\w+)\s*\(\s*(\w+)\s*\)\s*(?:(?:\s+(?:ON\s+DELETE\s+(CASCADE|NO\s+ACTION|RESTRICT|SET\s+NULL|SET\s+DEFAULT)))|(?:\s+(?:ON\s+UPDATE\s+(CASCADE|NO\s+ACTION|RESTRICT|SET\s+NULL|SET\s+DEFAULT))))*(?:\s+(?:DEFERRABLE|NOT\s+DEFERRABLE))?)?\s*(?:,|\s*(?=\)))#mi', $create_sql, $matches, PREG_SET_ORDER);
			
			foreach ($matches as $match) {
				if (!empty($match[2])) {
					$keys[$table]['unique'][] = array($match[1]);
				}
				
				if (!empty($match[3])) {
					$keys[$table]['primary'] = array($match[1]);
				}
				
				if (!empty($match[4])) {
					$temp = array('column'         => $match[1],
								  'foreign_table'  => $match[5],
								  'foreign_column' => $match[6],
								  'on_delete'      => 'no_action',
								  'on_update'      => 'no_action');
					if (isset($match[7])) {
						$temp['on_delete'] = strtolower(str_replace(' ', '_', $match[7]));
					}
					if (isset($match[8])) {
						$temp['on_update'] = strtolower(str_replace(' ', '_', $match[8]));
					}
					$keys[$table]['foreign'][] = $temp;
				}
			}
			
			// Get table level primary key definitions
			preg_match_all('#(?<=,|\()\s*PRIMARY\s+KEY\s*\(\s*((?:\s*\w+\s*,\s*)*\w+)\s*\)\s*(?:,|\s*(?=\)))#mi', $create_sql, $matches, PREG_SET_ORDER);
			
			foreach ($matches as $match) {
				$keys[$table]['primary'] = preg_split('#\s*,\s*#', $match[1]);
			}
			
			// Get table level foreign key definitions
			preg_match_all('#(?<=,|\()\s*FOREIGN\s+KEY\s*(?:(\w+)|\((\w+)\))\s+REFERENCES\s+(\w+)\s*\(\s*(\w+)\s*\)\s*(?:\s+(?:ON\s+DELETE\s+(CASCADE|NO\s+ACTION|RESTRICT|SET\s+NULL|SET\s+DEFAULT)))?(?:\s+(?:ON\s+UPDATE\s+(CASCADE|NO\s+ACTION|RESTRICT|SET\s+NULL|SET\s+DEFAULT)))?(?:\s+(?:DEFERRABLE|NOT\s+DEFERRABLE))?\s*(?:,|\s*(?=\)))#mi', $create_sql, $matches, PREG_SET_ORDER);
			
			foreach ($matches as $match) {
				if (empty($match[1])) { $match[1] = $match[2]; }
				$temp = array('column'         => $match[1],
							  'foreign_table'  => $match[3],
							  'foreign_column' => $match[4],
							  'on_delete'      => 'no_action',
							  'on_update'      => 'no_action');
				if (isset($match[5])) {
					$temp['on_delete'] = strtolower(str_replace(' ', '_', $match[5]));
				}
				if (isset($match[6])) {
					$temp['on_update'] = strtolower(str_replace(' ', '_', $match[6]));
				}
				$keys[$table]['foreign'][] = $temp;
			}
			
			// Get table level unique key definitions
			preg_match_all('#(?<=,|\()\s*UNIQUE\s*\(\s*((?:\s*\w+\s*,\s*)*\w+)\s*\)\s*(?:,|\s*(?=\)))#mi', $create_sql, $matches, PREG_SET_ORDER);
			
			foreach ($matches as $match) {
				$keys[$table]['unique'][] = preg_split('#\s*,\s*#', $match[1]);
			}
		}
		
		return $keys;
	}
	
	
	/**
	 * Finds many-to-many relationship for the table specified
	 * 
	 * @param  string $table  The table to find the relationships on
	 * @return void
	 */
	private function findManyToManyRelationships($table)
	{
		if (!$this->isJoiningTable($table)) {
			return;
		}
		
		list ($key1, $key2) = $this->merged_keys[$table]['foreign'];
		
		$temp = array();
		$temp['table']               = $key1['foreign_table'];
		$temp['column']              = $key1['foreign_column'];
		$temp['related_table']       = $key2['foreign_table'];
		$temp['related_column']      = $key2['foreign_column'];
		$temp['join_table']          = $table;
		$temp['join_column']         = $key1['column'];
		$temp['join_related_column'] = $key2['column'];
		$temp['on_update']           = $key1['on_update'];
		$temp['on_delete']           = $key1['on_delete'];
		$this->relationships[$key1['foreign_table']]['many-to-many'][] = $temp;
		
		$temp = array();
		$temp['table']               = $key2['foreign_table'];
		$temp['column']              = $key2['foreign_column'];
		$temp['related_table']       = $key1['foreign_table'];
		$temp['related_column']      = $key1['foreign_column'];
		$temp['join_table']          = $table;
		$temp['join_column']         = $key2['column'];
		$temp['join_related_column'] = $key1['column'];
		$temp['on_update']           = $key2['on_update'];
		$temp['on_delete']           = $key2['on_delete'];
		$this->relationships[$key2['foreign_table']]['many-to-many'][] = $temp;
	}
	
	
	/**
	 * Finds one-to-many relationship for the table specified
	 * 
	 * @param  string $table  The table to find the relationships on
	 * @return void
	 */
	private function findOneToManyRelationships($table)
	{
		foreach ($this->merged_keys[$table]['foreign'] as $key) {
			$temp = array();
			$temp['table']          = $key['foreign_table'];
			$temp['column']         = $key['foreign_column'];
			$temp['related_table']  = $table;
			$temp['related_column'] = $key['column'];
			$temp['on_delete']      = $key['on_delete'];
			$temp['on_update']      = $key['on_update'];
			$this->relationships[$key['foreign_table']]['one-to-many'][] = $temp;
		}
	}
	
	
	/**
	 * Finds one-to-one and many-to-one relationship for the table specified
	 * 
	 * @param  string $table  The table to find the relationships on
	 * @return void
	 */
	private function findStarToOneRelationships($table)
	{
		foreach ($this->merged_keys[$table]['foreign'] as $key) {
			$temp = array();
			$temp['table']          = $table;
			$temp['column']         = $key['column'];
			$temp['related_table']  = $key['foreign_table'];
			$temp['related_column'] = $key['foreign_column'];
			$type = ($this->checkForSingleColumnUniqueKey($table, $key['column'])) ? 'one-to-one' : 'many-to-one';
			$this->relationships[$table][$type][] = $temp;
		}
	}
	
	
	/**
	 * Finds the one-to-one, many-to-one, one-to-many and many-to-many relationships in the database
	 * 
	 * @return void
	 */
	private function findRelationships()
	{
		$this->relationships = array();
		$tables = $this->getTables();
		
		foreach ($tables as $table) {
			$this->relationships[$table]['one-to-one']   = array();
			$this->relationships[$table]['many-to-one']  = array();
			$this->relationships[$table]['one-to-many']  = array();
			$this->relationships[$table]['many-to-many'] = array();
		}
		
		// Calculate the relationships
		foreach ($this->merged_keys as $table => $keys) {
			$this->findManyToManyRelationships($table);
			if ($this->isJoiningTable($table)) {
				continue;
			}
			$this->findStarToOneRelationships($table);
			$this->findOneToManyRelationships($table);
		}
	}
	
	
	/**
	 * Makes sure the info is current, if not it deletes it so that all info is current
	 * 
	 * @internal
	 * 
	 * @return void
	 */
	public function flushInfo()
	{
		if ($this->state != 'current') {
			$this->tables             = NULL;
			$this->column_info        = array();
			$this->keys               = array();
			$this->merged_column_info = array();
			$this->merged_keys        = array();
			$this->relationships      = array();
			$this->state              = 'current';
			$this->info_changed       = TRUE;
		}
	}
	
	
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
	 *         'decimal_places' => (integer) {the number of decimal places for a decimal/numeric/money/smallmoney field},
	 *         'auto_increment' => (boolean) {if the integer primary key column is a serial/autoincrement/auto_increment/indentity column}
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
	 *     'valid_values'   => (array)   {the valid values for a char/varchar field},
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
	public function getColumnInfo($table, $column=NULL, $element=NULL)
	{
		$valid_elements = array('type', 'not_null', 'default', 'valid_values', 'max_length', 'decimal_places', 'auto_increment');
		if ($element && !in_array($element, $valid_elements)) {
			fCore::toss(
				'fProgrammerException',
				fGrammar::compose(
					'The element specified, %1$s, is invalid. Must be one of: %2$s.',
					fCore::dump($element),
					join(', ', $valid_elements)
				)
			);
		}
		
		if (!in_array($table, $this->getTables())) {
			fCore::toss(
				'fProgrammerException',
				fGrammar::compose(
					'The table specified, %s, does not exist in the database',
					fCore::dump($table)
				)
			);
		}
		
		// Return the saved column info if possible
		if (!$column && isset($this->merged_column_info[$table])) {
			return $this->merged_column_info[$table];
		}
		if ($column && isset($this->merged_column_info[$table][$column])) {
			if ($element !== NULL) {
				return $this->merged_column_info[$table][$column][$element];
			}
			return $this->merged_column_info[$table][$column];
		}
		
		$this->fetchColumnInfo($table);
		$this->mergeColumnInfo();
		
		if ($column && !isset($this->merged_column_info[$table][$column])) {
			fCore::toss(
				'fProgrammerException',
				fGrammar::compose(
					'The column specified, %1$s, does not exist in the table %2$s',
					fCore::dump($column),
					fCore::dump($table)
				)
			);
		}
		
		if ($column) {
			if ($element) {
				return $this->merged_column_info[$table][$column][$element];
			}
			return $this->merged_column_info[$table][$column];
		}
		return $this->merged_column_info[$table];
	}
	
	
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
	public function getKeys($table, $key_type=NULL)
	{
		$valid_key_types = array('primary', 'foreign', 'unique');
		if ($key_type !== NULL && !in_array($key_type, $valid_key_types)) {
			fCore::toss(
				'fProgrammerException',
				fGrammar::compose(
					'The key type specified, %1$s, is invalid. Must be one of: %2$s.',
					fCore::dump($key_type),
					join(', ', $valid_key_types)
				)
			);
		}
		
		// Return the saved column info if possible
		if (!$key_type && isset($this->merged_keys[$table])) {
			return $this->merged_keys[$table];
		}
		
		if ($key_type && isset($this->merged_keys[$table][$key_type])) {
			return $this->merged_keys[$table][$key_type];
		}
		
		if (!in_array($table, $this->getTables())) {
			fCore::toss(
				'fProgrammerException',
				fGrammar::compose(
					'The table specified, %s, does not exist in the database',
					fCore::dump($table)
				)
			);
		}
		
		$this->fetchKeys();
		$this->mergeKeys();
		
		if ($key_type) {
			return $this->merged_keys[$table][$key_type];
		}
		return $this->merged_keys[$table];
	}
	
	
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
	public function getRelationships($table, $relationship_type=NULL)
	{
		$valid_relationship_types = array('one-to-one', 'many-to-one', 'one-to-many', 'many-to-many');
		if ($relationship_type !== NULL && !in_array($relationship_type, $valid_relationship_types)) {
			fCore::toss(
				'fProgrammerException',
				fGrammar::compose(
					'The relationship type specified, %1$s, is invalid. Must be one of: %2$s.',
					fCore::dump($relationship_type),
					join(', ', $valid_relationship_types)
				)
			);
		}
		
		// Return the saved column info if possible
		if (!$relationship_type && isset($this->relationships[$table])) {
			return $this->relationships[$table];
		}
		
		if ($relationship_type && isset($this->relationships[$table][$relationship_type])) {
			return $this->relationships[$table][$relationship_type];
		}
		
		if (!in_array($table, $this->getTables())) {
			fCore::toss(
				'fProgrammerException',
				fGrammar::compose(
					'The table specified, %s, does not exist in the database',
					fCore::dump($table)
				)
			);
		}
		
		$this->fetchKeys();
		$this->mergeKeys();
		
		if ($relationship_type) {
			return $this->relationships[$table][$relationship_type];
		}
		return $this->relationships[$table];
	}
	
	
	/**
	 * Returns the tables in the current database
	 * 
	 * @return array  The tables in the current database
	 */
	public function getTables()
	{
		if ($this->tables !== NULL) {
			return $this->tables;
		}
		
		switch ($this->database->getType()) {
			case 'mssql':
				$sql = 'SELECT
								TABLE_NAME
							FROM
								INFORMATION_SCHEMA.TABLES
							ORDER BY
								LOWER(TABLE_NAME)';
				break;
			
			case 'mysql':
				$sql = 'SHOW TABLES';
				break;
			
			case 'postgresql':
				$sql = "SELECT
								 tablename
							FROM
								 pg_tables
							WHERE
								 tablename !~ '^(pg|sql)_'
							ORDER BY
								lower(tablename)";
				break;
								
			case 'sqlite':
				$sql = "SELECT
								name
							FROM
								sqlite_master
							WHERE
								type = 'table' AND
								name NOT LIKE 'sqlite_%'
							ORDER BY
								name ASC";
				break;
		}
		
		$result = $this->database->query($sql);
		
		$this->tables = array();
		
		foreach ($result as $row) {
			$keys = array_keys($row);
			$this->tables[] = $row[$keys[0]];
		}
		return $this->tables;
	}
		
	
	/**
	 * Determines if a table is a joining table
	 * 
	 * @param  string $table  The table to check
	 * @return boolean  If the table is a joining table
	 */
	private function isJoiningTable($table)
	{
		$primary_key_columns = $this->merged_keys[$table]['primary'];
		$foreign_key_columns = array();
		foreach ($this->merged_keys[$table]['foreign'] as $key) {
			$foreign_key_columns[] = $key['column'];
		}
		$diff = array_diff($foreign_key_columns, $primary_key_columns);
		return sizeof($primary_key_columns) == 2 && sizeof($foreign_key_columns) == 2 && !$diff;
	}
	
	
	/**
	 * Merges the column info with the column info override
	 * 
	 * @return void
	 */
	private function mergeColumnInfo()
	{
		$this->merged_column_info = $this->column_info;
		foreach ($this->column_info_override as $table => $info) {
			if (!isset($this->merge_column_info[$table])) {
				$this->merged_column_info[$table] = array();
			}
			$this->merged_column_info[$table] = array_merge($this->merged_column_info[$table], $info);
		}
		$optional_elements = array('default', 'not_null', 'valid_values', 'max_length', 'decimal_places', 'auto_increment');
		foreach ($this->merged_column_info as $table => $column_array) {
			foreach ($column_array as $column => $info) {
				foreach ($optional_elements as $element) {
					if (!isset($this->merged_column_info[$table][$column][$element])) {
						$this->merged_column_info[$table][$column][$element] = ($element == 'auto_increment') ? FALSE : NULL;
					}
				}
			}
		}
	}
		
	
	/**
	 * Merges the keys with the keys override
	 * 
	 * @return void
	 */
	private function mergeKeys()
	{
		// Handle the database and override key info
		$this->merged_keys = $this->keys;
		foreach ($this->keys_override as $table => $info) {
			if (!isset($this->merge_keys[$table])) {
				$this->merged_keys[$table] = array();
			}
			$this->merged_keys[$table] = array_merge($this->merged_keys[$table], $info);
		}
		
		$this->findRelationships();
	}
	
	
	/**
	 * Sets a file to cache the info to
	 * 
	 * @param  string $file  The cache file
	 * @return void
	 */
	public function setCacheFile($file)
	{
		$file = realpath($file);
		
		if (file_exists($file) && !is_writable($file)) {
			fCore::toss(
				'fEnvironmentException',
				fGrammar::compose(
					'The cache file specified, %s, is not writable',
					fCore::dump($file)
				)
			);
		}
		
		if (!file_exists($file) && !is_writable(dirname($file))) {
			fCore::toss(
				'fEnvironmentException',
				fGrammar::compose(
					'The cache file specified, %1$s, does not exist and the cache file directory, %2$s, is not writable',
					fCore::dump($file),
					fCore::dump(dirname($file) . DIRECTORY_SEPARATOR)
				)
			);
		}
		
		$this->cache_file = $file;
		
		$contents = file_get_contents($this->cache_file);
		if ($contents) {
			$info = unserialize($contents);
			$this->tables        = $info['tables'];
			$this->column_info   = $info['column_info'];
			$this->keys          = $info['keys'];
		}
		
		if (!empty($this->column_info) || !empty($this->keys)) {
			$this->state = 'cached';
		}
	}
	
	
	/**
	 * Allows overriding of column info. Performs an array merge, so to erase a column set values to null.
	 * 
	 * @param  array  $column_info  The modified column info (see {@link fSchema::getColumnInfo()} for format)
	 * @param  string $table        The table to override
	 * @param  string $column       The column to override
	 * @return void
	 */
	public function setColumnInfoOverride($column_info, $table, $column=NULL)
	{
		if (!isset($this->column_info_override[$table])) {
			$this->column_info_override[$table] = array();
		}
		if (!empty($column)) {
			$this->column_info_override[$table][$column] = $column_info;
		} else {
			$this->column_info_override[$table] = $column_info;
		}
		$this->mergeColumnInfo();
	}
	
	
	/**
	 * Allows overriding of key info. Replaces existing info, so provide full key info for type selected (or all).
	 * 
	 * @param  array  $keys      The modified keys (see {@link fSchema::getKeys()} for format)
	 * @param  string $table     The table to override
	 * @param  string $key_type  The key type to override ('primary', 'foreign', 'unique')
	 * @return void
	 */
	public function setKeysOverride($keys, $table, $key_type=NULL)
	{
		$valid_key_types = array('primary', 'foreign', 'unique');
		if (!in_array($key_type, $valid_key_types)) {
			fCore::toss(
				'fProgrammerException',
				fGrammar::compose(
					'The key type specified, %1$s, is invalid. Must be one of: %2$s.',
					fCore::dump($key_type),
					join(', ', $valid_key_types)
				)
			);
		}
		
		if (!isset($this->keys_override[$table])) {
			$this->keys_override[$table] = array();
		}
		if (!empty($key_type)) {
			$this->keys_override[$table][$key_type] = $keys;
		} else {
			$this->keys_override[$table] = $keys;
		}
		$this->mergeKeys();
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