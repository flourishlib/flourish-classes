<?php
/**
 * Takes a subset of SQL from MySQL, PostgreSQL, SQLite and MSSQL and translates into the various dialects allowing for cross-database code
 * 
 * @copyright  Copyright (c) 2007-2008 William Bond
 * @author     William Bond [wb] <will@flourishlib.com>
 * @license    http://flourishlib.com/license
 * 
 * @package    Flourish
 * @link       http://flourishlib.com/fSQLTranslation
 * 
 * @internal
 * 
 * @version    1.0.0b
 * @changes    1.0.0b  The initial implementation [wb, 2007-09-25]
 */
class fSQLTranslation
{
	/**
	 * The database connection resource or PDO object
	 * 
	 * @var mixed
	 */
	private $connection;
	
	/**
	 * If debugging is enabled
	 * 
	 * @var boolean
	 */
	private $debug;
	
	/**
	 * The extension to use for the database specified.
	 * 
	 * Options include:
	 *  - mssql
	 *  - mysql
	 *  - mysqli
	 *  - odbc
	 *  - pdo
	 *  - pgsql
	 *  - sqlite
	 *  - sqlsrv
	 * 
	 * @var string
	 */
	private $extension;
	
	/**
	 * The database type (mssql, mysql, postgresql, sqlite)
	 * 
	 * @var string
	 */
	private $type;
	
	
	/**
	 * Sets up the class and creates functions for SQLite databases
	 * 
	 * @internal
	 * 
	 * @param  mixed  $connection  The connection resource or PDO object
	 * @param  string $type        The type of the database ('mssql', 'mysql', 'postgresql', or 'sqlite')
	 * @param  string $extension   The extension being used to connect to the database ('mssql', 'mysql', 'mysqli', 'odbc', 'pdo', 'pgsql', 'sqlite', or 'sqlsrv')
	 * @return fSqlTranslation
	 */
	public function __construct($connection, $type, $extension)
	{
		if (!is_resource($connection) && !is_object($connection)) {
			fCore::toss('fProgrammerException', 'The connection specified is not a valid database connection');
		}
		
		$valid_types = array('mssql', 'mysql', 'postgresql', 'sqlite');
		if (!in_array($type, $valid_types)) {
			fCore::toss('fProgrammerException', 'The type specified, ' . $type . ', is not valid. Must be one of: ' . join(', ', $valid_types) . '.');
		}
		
		$valid_extensions = array('mssql', 'mysql', 'mysqli', 'odbc', 'pdo', 'pgsql', 'sqlite', 'sqlsrv');
		if (!in_array($extension, $valid_extensions)) {
			fCore::toss('fProgrammerException', 'The extension specified, ' . $extension . ', is not valid. Must be one of: ' . join(', ', $valid_extensions) . '.');
		}
		
		$this->connection = $connection;
		$this->type       = $type;
		$this->extension  = $extension;
		
		if ($this->type == 'sqlite') {
			$this->createSqliteFunctions();
		}
	}
	
	
	/**
	 * Creates a trigger for SQLite that handles an on delete clause
	 * 
	 * @param  string $referencing_table   The table that contains the foreign key
	 * @param  string $referencing_column  The column the foriegn key constraint is on
	 * @param  string $referenced_table    The table the foreign key references
	 * @param  string $referenced_column   The column the foreign key references
	 * @param  string $delete_clause       What is to be done on a delete
	 * @return string  The trigger
	 */
	private function createSqliteForeignKeyTriggerOnDelete($referencing_table, $referencing_column, $referenced_table, $referenced_column, $delete_clause)
	{
		switch (strtolower($delete_clause)) {
			case 'no action':
			case 'restrict':
				$sql = "\nCREATE TRIGGER fkd_res_" . $referencing_table . "_" . $referencing_column . "
							 BEFORE DELETE ON " . $referenced_table . "
							 FOR EACH ROW BEGIN
								 SELECT RAISE(ROLLBACK, 'delete on table \"" . $referenced_table . "\" can not be executed because it would violate the foreign key constraint on column \"" . $referencing_column . "\" of table \"" . $referencing_table . "\"')
								 WHERE (SELECT " . $referencing_column . " FROM " . $referencing_table . " WHERE " . $referencing_column . " = OLD." . $referenced_table . ") IS NOT NULL;
							 END;";
				break;
			
			case 'set null':
				$sql = "\nCREATE TRIGGER fkd_nul_" . $referencing_table . "_" . $referencing_column . "
							 BEFORE DELETE ON " . $referenced_table . "
							 FOR EACH ROW BEGIN
								 UPDATE " . $referencing_table . " SET " . $referencing_column . " = NULL WHERE " . $referencing_column . " = OLD." . $referenced_column . ";
							 END;";
				break;
				
			case 'cascade':
				$sql = "\nCREATE TRIGGER fkd_cas_" . $referencing_table . "_" . $referencing_column . "
							 BEFORE DELETE ON " . $referenced_table . "
							 FOR EACH ROW BEGIN
								 DELETE FROM " . $referencing_table . " WHERE " . $referencing_column . " = OLD." . $referenced_column . ";
							 END;";
				break;
		}
		return $sql;
	}
	
	
	/**
	 * Creates a trigger for SQLite that handles an on update clause
	 * 
	 * @param  string $referencing_table   The table that contains the foreign key
	 * @param  string $referencing_column  The column the foriegn key constraint is on
	 * @param  string $referenced_table    The table the foreign key references
	 * @param  string $referenced_column   The column the foreign key references
	 * @param  string $update_clause       What is to be done on an update
	 * @return string  The trigger
	 */
	private function createSqliteForeignKeyTriggerOnUpdate($referencing_table, $referencing_column, $referenced_table, $referenced_column, $update_clause)
	{
		switch (strtolower($update_clause)) {
			case 'no action':
			case 'restrict':
				$sql = "\nCREATE TRIGGER fku_res_" . $referencing_table . "_" . $referencing_column . "
							 BEFORE UPDATE ON " . $referenced_table . "
							 FOR EACH ROW BEGIN
								 SELECT RAISE(ROLLBACK, 'update on table \"" . $referenced_table . "\" can not be executed because it would violate the foreign key constraint on column \"" . $referencing_column . "\" of table \"" . $referencing_table . "\"')
								 WHERE (SELECT " . $referencing_column . " FROM " . $referencing_table . " WHERE " . $referencing_column . " = OLD." . $referenced_column . ") IS NOT NULL;
							 END;";
				break;
			
			case 'set null':
				$sql = "\nCREATE TRIGGER fku_nul_" . $referencing_table . "_" . $referencing_column . "
							 BEFORE UPDATE ON " . $referenced_table . "
							 FOR EACH ROW BEGIN
								 UPDATE " . $referencing_table . " SET " . $referencing_column . " = NULL WHERE OLD." . $referenced_column . " <> NEW." . $referenced_column . " AND " . $referencing_column . " = OLD." . $referenced_column . ";
							 END;";
				break;
				
			case 'cascade':
				$sql = "\nCREATE TRIGGER fku_cas_" . $referencing_table . "_" . $referencing_column . "
							 BEFORE UPDATE ON " . $referenced_table . "
							 FOR EACH ROW BEGIN
								 UPDATE " . $referencing_table . " SET " . $referencing_column . " = NEW." . $referenced_column . " WHERE OLD." . $referenced_column . " <> NEW." . $referenced_column . " AND " . $referencing_column . " = OLD." . $referenced_column . ";
							 END;";
				break;
		}
		return $sql;
	}
	
	
	/**
	 * Creates a trigger for SQLite that prevents inserting or updating to values the violate a foreign key constraint
	 * 
	 * @param  string  $referencing_table     The table that contains the foreign key
	 * @param  string  $referencing_column    The column the foriegn key constraint is on
	 * @param  string  $referenced_table      The table the foreign key references
	 * @param  string  $referenced_column     The column the foreign key references
	 * @param  boolean $referencing_not_null  If the referencing columns is set to not null
	 * @return string  The trigger
	 */
	private function createSqliteForeignKeyTriggerValidInsertUpdate($referencing_table, $referencing_column, $referenced_table, $referenced_column, $referencing_not_null)
	{
		// Verify key on inserts
		$sql  = "\nCREATE TRIGGER fki_ver_" . $referencing_table . "_" . $referencing_column . "
					  BEFORE INSERT ON " . $referencing_table . "
					  FOR EACH ROW BEGIN
						  SELECT RAISE(ROLLBACK, 'insert on table \"" . $referencing_table . "\" violates foreign key constraint on column \"" . $referencing_column . "\"')
							  WHERE ";
		if (!$referencing_not_null) {
			$sql .= "NEW." . $referencing_column . " IS NOT NULL AND ";
		}
		$sql .= " (SELECT " . $referenced_column . " FROM " . $referenced_table . " WHERE " . $referenced_column . " = NEW." . $referencing_column . ") IS NULL;
					  END;";
					
		// Verify key on updates
		$sql .= "\nCREATE TRIGGER fku_ver_" . $referencing_table . "_" . $referencing_column . "
					  BEFORE UPDATE ON " . $referencing_table . "
					  FOR EACH ROW BEGIN
						  SELECT RAISE(ROLLBACK, 'update on table \"" . $referencing_table . "\" violates foreign key constraint on column \"" . $referencing_column . "\"')
							  WHERE ";
		if (!$referencing_not_null) {
			$sql .= "NEW." . $referencing_column . " IS NOT NULL AND ";
		}
		$sql .= " (SELECT " . $referenced_column . " FROM " . $referenced_table . " WHERE " . $referenced_column . " = NEW." . $referencing_column . ") IS NULL;
					  END;";
		
		return $sql;
	}
	
	
	/**
	 * Adds a number of math functions to SQLite that MSSQL, MySQL and PostgreSQL have by default
	 * 
	 * @return void
	 */
	private function createSqliteFunctions()
	{
		$function = array();
		$functions[] = array('acos',     'acos',                                         1);
		$functions[] = array('asin',     'asin',                                         1);
		$functions[] = array('atan',     'atan',                                         1);
		$functions[] = array('atan2',    'atan2',                                        2);
		$functions[] = array('ceil',     'ceil',                                         1);
		$functions[] = array('ceiling',  'ceil',                                         1);
		$functions[] = array('cos',      'cos',                                          1);
		$functions[] = array('cot',      array('fSqlTranslation', 'sqliteCotangent'),    1);
		$functions[] = array('degrees',  'rad2deg',                                      1);
		$functions[] = array('exp',      'exp',                                          1);
		$functions[] = array('floor',    'floor',                                        1);
		$functions[] = array('ln',       'log',                                          1);
		$functions[] = array('log',      array('fSqlTranslation', 'sqliteLogBaseFirst'), 2);
		$functions[] = array('pi',       'pi',                                           1);
		$functions[] = array('power',    'pow',                                          1);
		$functions[] = array('radians',  'deg2rad',                                      1);
		$functions[] = array('sign',     array('fSqlTranslation', 'sqliteSign'),         1);
		$functions[] = array('sqrt',     'sqrt',                                         1);
		$functions[] = array('sin',      'sin',                                          1);
		$functions[] = array('tan',      'tan',                                          1);
		
		foreach ($functions as $function) {
			if ($this->extension == 'pdo') {
				$this->connection->sqliteCreateFunction($function[0], $function[1], $function[2]);
			} else {
				sqlite_create_function($this->connection, $function[0], $function[1], $function[2]);
			}
		}
	}
	
	
	/**
	 * Sets if debug messages should be shown
	 * 
	 * @param  boolean $flag  If debugging messages should be shown
	 * @return void
	 */
	public function showDebug($flag)
	{
		$this->debug = (boolean) $flag;
	}
	
	
	/**
	 * Callback for custom SQLite function; calculates the cotangent of a number
	 * 
	 * @internal
	 * 
	 * @param  numeric $x  The number to calculate the cotangent of
	 * @return numeric  The contangent of $x
	 */
	public static function sqliteCotangent($x)
	{
		return 1/tan($x);
	}
	
	
	/**
	 * Callback for custom SQLite function; calculates the log to a specific base of a number
	 * 
	 * @internal
	 * 
	 * @param  integer $base  The base for the log calculation
	 * @param  numeric $num   The number to calculate the logarithm of
	 * @return numeric  The logarithm of $num to $base
	 */
	public static function sqliteLogBaseFirst($base, $num)
	{
		return log($num, $base);
	}
	
	
	/**
	 * Callback for custom SQLite function; changes the sign of a number
	 * 
	 * @internal
	 * 
	 * @param  numeric $x  The number to change the sign of
	 * @return numeric  $x with a changed sign
	 */
	public static function sqliteSign($x)
	{
		return -1 * $x;
	}
	
	
	/**
	 * Translates FlourishSQL into the dialect for the current database
	 * 
	 * @internal
	 * 
	 * @param  string $sql  The SQL to translate
	 * @return string  The translated SQL
	 */
	public function translate($sql)
	{
		// Separate the SQL from quoted values
		preg_match_all("#(?:'(?:''|\\\\'|\\\\[^']|[^'\\\\])*')|(?:[^']+)#", $sql, $matches);
		
		$new_sql = '';
		foreach ($matches[0] as $match) {
			// This is a quoted string value, don't do anything to it
			if ($match[0] == "'") {
				$new_sql .= $match;
			
			// Raw SQL should be run through the fixes
			} else {
				$new_sql .= $this->translateBasicSyntax($match);
			}
		}
		
		// Fix stuff that includes sql and quotes values
		$new_sql = $this->translateDateFunctions($new_sql);
		$new_sql = $this->translateComplicatedSyntax($new_sql);
		$new_sql = $this->translateCreateTableStatements($new_sql);
		
		if ($sql != $new_sql) {
			fCore::debug("Original SQL:\n" . $sql, $this->debug);
			fCore::debug("Translated SQL:\n" . $new_sql, $this->debug);
		}
		
		return $new_sql;
	}
	
	
	/**
	 * Translates basic syntax differences of the current database
	 * 
	 * @param  string $sql  The SQL to translate
	 * @return string  The translated SQL
	 */
	private function translateBasicSyntax($sql)
	{
		// SQLite fixes
		if ($this->type == 'sqlite') {
			
			if ($this->type == 'sqlite' && $this->extension == 'pdo') {
				static $regex_sqlite = array(
					'#\binteger\s+autoincrement\s+primary\s+key\b#i'  => 'INTEGER PRIMARY KEY AUTOINCREMENT',
					'#\bcurrent_timestamp\b#i'                        => "datetime(CURRENT_TIMESTAMP, 'localtime')",
					'#\btrue\b#i'                                     => "'1'",
					'#\bfalse\b#i'                                    => "'0'"
				);
			} else {
				static $regex_sqlite = array(
					'#\binteger\s+autoincrement\s+primary\s+key\b#i'  => 'INTEGER PRIMARY KEY',
					'#\bcurrent_timestamp\b#i'       => "datetime(CURRENT_TIMESTAMP, 'localtime')",
					'#\btrue\b#i'                    => "'1'",
					'#\bfalse\b#i'                   => "'0'"
				);
			}
			
			return preg_replace(array_keys($regex_sqlite), array_values($regex_sqlite), $sql);
		}
		
		// PostgreSQL fixes
		if ($this->type == 'postgresql') {
			static $regex_postgresql = array(
				'#\blike\b#i'                    => 'ILIKE',
				'#\bblob\b#i'                    => 'bytea',
				'#\binteger\s+autoincrement\b#i' => 'serial'
			);
			
			return preg_replace(array_keys($regex_postgresql), array_values($regex_postgresql), $sql);
		}
		
		// MySQL fixes
		if ($this->type == 'mysql') {
			static $regex_mysql = array(
				'#\brandom\(#i'                  => 'rand(',
				'#\btext\b#i'                    => 'MEDIUMTEXT',
				'#\bblob\b#i'                    => 'LONGBLOB',
				'#\btimestamp\b#i'               => 'DATETIME',
				'#\binteger\s+autoincrement\b#i' => 'INTEGER AUTO_INCREMENT'
			);
		
			return preg_replace(array_keys($regex_mysql), array_values($regex_mysql), $sql);
		}
		
		// MSSQL fixes
		if ($this->type == 'mssql') {
			static $regex_mssql = array(
				'#\bbegin\s*(?!tran)#i'          => 'BEGIN TRANSACTION ',
				'#\brandom\(#i'                  => 'RAND(',
				'#\batan2\(#i'                   => 'ATN2(',
				'#\bceil\(#i'                    => 'CEILING(',
				'#\bln\(#i'                      => 'LOG(',
				'#\blength\(#i'                  => 'LEN(',
				'#\bblob\b#i'                    => 'IMAGE',
				'#\btimestamp\b#i'               => 'DATETIME',
				'#\btime\b#i'                    => 'DATETIME',
				'#\bdate\b#i'                    => 'DATETIME',
				'#\binteger\s+autoincrement\b#i' => 'INTEGER IDENTITY(1,1)',
				'#\bboolean\b#i'                 => 'BIT',
				'#\btrue\b#i'                    => "'1'",
				'#\bfalse\b#i'                   => "'0'",
				'#\|\|#i'                      => '+'
			);
		
			return preg_replace(array_keys($regex_mssql), array_values($regex_mssql), $sql);
		}
	}
	
	
	/**
	 * Translates more complicated inconsistencies
	 * 
	 * @param  string $sql  The SQL to translate
	 * @return string  The translated SQL
	 */
	private function translateComplicatedSyntax($sql)
	{
		if ($this->type == 'mssql') {
			
			$sql = $this->translateLimitOffsetToRowNumber($sql);
			
			static $regex_mssql = array(
				// These wrap multiple mssql functions to accomplish another function
				'#\blog\(\s*((?>[^()\',]+|\'[^\']*\'|\((?1)(?:,(?1))?\)|\(\))+)\s*,\s*((?>[^()\',]+|\'[^\']*\'|\((?2)(?:,(?2))?\)|\(\))+)\s*\)#i' => '(LOG(\1)/LOG(\2))',
				'#\btrim\(\s*((?>[^()\',]+|\'(?:\'\'|\\\\\'|\\\\[^\']|[^\'\\\\])*\'|\((?1)\)|\(\))+)\s*\)#i' => 'RTRIM(LTRIM(\1))',
				
				// This fixes limit syntax
				'#select(\s*(?:[^()\']+|\'(?:\'\'|\\\\\'|\\\\[^\']|[^\'\\\\])*\'|\((?1)\)|\(\))+\s*)\s+limit\s+(\d+)#i' => 'SELECT TOP \2\1'
			);
		
			$sql = preg_replace(array_keys($regex_mssql), array_values($regex_mssql), $sql);
		}
		
		return $sql;
	}
	
	
	/**
	 * Translates the structure of create table statements to the database specific syntax
	 * 
	 * @param  string $sql  The SQL to translate
	 * @return string  The translated SQL
	 */
	private function translateCreateTableStatements($sql)
	{
		// Make sure MySQL uses InnoDB tables
		if ($this->type == 'mysql' && stripos($sql, 'CREATE TABLE') !== FALSE) {
			preg_match_all('#(?<=,|\()\s*(\w+)\s+(?:[a-z]+)(?:\(\d+\))?(?:(\s+NOT\s+NULL)|(\s+DEFAULT\s+(?:[^, \']*|\'(?:\'\'|[^\'])*\'))|(\s+UNIQUE)|(\s+PRIMARY\s+KEY)|(\s+CHECK\s*\(\w+\s+IN\s+(\(\s*(?:(?:[^, \']+|\'(?:\'\'|[^\'])*\')\s*,\s*)*\s*(?:[^, \']+|\'(?:\'\'|[^\'])*\')\))\)))*(\s+REFERENCES\s+\w+\s*\(\s*\w+\s*\)\s*(?:\s+(?:ON\s+DELETE|ON\s+UPDATE)\s+(?:CASCADE|NO\s+ACTION|RESTRICT|SET\s+NULL|SET\s+DEFAULT))*(?:\s+(?:DEFERRABLE|NOT\s+DEFERRABLE))?)?\s*(?:,|\s*(?=\)))#mi', $sql, $matches, PREG_SET_ORDER);
			
			foreach ($matches as $match) {
				if (!empty($match[6])) {
					$sql = str_replace($match[0], "\n " . $match[1] . ' enum' . $match[7] . $match[2] . $match[3] . $match[4] . $match[5] . $match[8] . ', ', $sql);
				}
			}
			
			$sql = preg_replace('#\)\s*;?\s*$#', ')ENGINE=InnoDB', $sql);
		
		
		// Create foreign key triggers for SQLite
		} elseif ($this->type == 'sqlite' && preg_match('#CREATE\s+TABLE\s+(\w+)#i', $sql, $table_matches) !== FALSE && stripos($sql, 'REFERENCES') !== FALSE) {
			
			$referencing_table = $table_matches[1];
			
			preg_match_all('#(?:(?<=,|\()\s*(\w+)\s+(?:[a-z]+)(?:\((?:\d+)\))?(?:(\s+NOT\s+NULL)|(?:\s+DEFAULT\s+(?:[^, \']*|\'(?:\'\'|[^\'])*\'))|(?:\s+UNIQUE)|(?:\s+PRIMARY\s+KEY(?:\s+AUTOINCREMENT)?)|(?:\s+CHECK\s*\(\w+\s+IN\s+\(\s*(?:(?:[^, \']+|\'(?:\'\'|[^\'])*\')\s*,\s*)*\s*(?:[^, \']+|\'(?:\'\'|[^\'])*\')\)\)))*(\s+REFERENCES\s+(\w+)\s*\(\s*(\w+)\s*\)\s*(?:\s+(?:ON\s+DELETE\s+(CASCADE|NO\s+ACTION|RESTRICT|SET\s+NULL)))?(?:\s+(?:ON\s+UPDATE\s+(CASCADE|NO\s+ACTION|RESTRICT|SET\s+NULL)))?)?\s*(?:,|\s*(?=\)))|(?<=,|\()\s*FOREIGN\s+KEY\s*(?:(\w+)|\((\w+)\))\s+REFERENCES\s+(\w+)\s*\(\s*(\w+)\s*\)\s*(?:\s+(?:ON\s+DELETE\s+(CASCADE|NO\s+ACTION|RESTRICT|SET\s+NULL)))?(?:\s+(?:ON\s+UPDATE\s+(CASCADE|NO\s+ACTION|RESTRICT|SET\s+NULL)))?\s*(?:,|\s*(?=\))))#mi', $sql, $matches, PREG_SET_ORDER);
			
			// Make sure we have a semicolon so we can add triggers
			$sql = trim($sql);
			if (substr($sql, -1) != ';') {
				$sql .= ';';
			}
			
			$not_null_columns = array();
			foreach ($matches as $match) {
				// Find all of the not null columns
				if (!empty($match[2])) {
					$not_null_columns[] = $match[1];
				}
				
				// If neither of these fields is matched, we don't have a foreign key
				if (empty($match[3]) && empty($match[10])) {
					continue;
				}
				
				// 8 and 9 will be an either/or set, so homogenize
				if (empty($match[9])) { $match[9] = $match[8]; }
				
				// Handle column level foreign key inserts/updates
				if ($match[1]) {
					$sql .= $this->createSqliteForeignKeyTriggerValidInsertUpdate($referencing_table, $match[1], $match[4], $match[5], in_array($match[1], $not_null_columns));
				
				// Handle table level foreign key inserts/update
				} elseif ($match[9]) {
					$sql .= $this->createSqliteForeignKeyTriggerValidInsertUpdate($referencing_table, $match[9], $match[10], $match[11], in_array($match[9], $not_null_columns));
				}
				
				// If none of these fields is matched, we don't have on delete or on update clauses
				if (empty($match[6]) && empty($match[7]) && empty($match[12]) && empty($match[13])) {
					continue;
				}
				
				// Handle column level foreign key delete/update clauses
				if (!empty($match[3])) {
					if ($match[6]) {
						$sql .= $this->createSqliteForeignKeyTriggerOnDelete($referencing_table, $match[1], $match[4], $match[5], $match[6]);
					}
					if ($match[7]) {
						$sql .= $this->createSqliteForeignKeyTriggerOnUpdate($referencing_table, $match[1], $match[4], $match[5], $match[7]);
					}
					continue;
				}
				
				// Handle table level foreign key delete/update clauses
				if ($match[12]) {
					$sql .= $this->createSqliteForeignKeyTriggerOnDelete($referencing_table, $match[9], $match[10], $match[11], $match[12]);
				}
				if ($match[13]) {
					$sql .= $this->createSqliteForeignKeyTriggerOnUpdate($referencing_table, $match[9], $match[10], $match[11], $match[13]);
				}
			}
		}
		
		return $sql;
	}
	
	
	/**
	 * Translates custom date/time functions to the current database
	 * 
	 * @param  string $sql  The SQL to translate
	 * @return string  The translated SQL
	 */
	private function translateDateFunctions($sql)
	{
		// fix diff_seconds()
		preg_match_all("#diff_seconds\\(((?>(?:[^()',]+|'[^']+')|\\((?1)(?:,(?1))?\\)|\\(\\))+)\\s*,\\s*((?>(?:[^()',]+|'[^']+')|\\((?2)(?:,(?2))?\\)|\\(\\))+)\\)#ims", $sql, $diff_matches, PREG_SET_ORDER);
		foreach ($diff_matches as $match) {
			
			// SQLite
			if ($this->type == 'sqlite') {
				$sql = str_replace($match[0], "round((julianday(" . $match[2] . ") - julianday('1970-01-01 00:00:00')) * 86400) - round((julianday(" . $match[1] . ") - julianday('1970-01-01 00:00:00')) * 86400)", $sql);
			
			// PostgreSQL
			} elseif ($this->type == 'postgresql') {
				$sql = str_replace($match[0], "extract(EPOCH FROM age(" . $match[2] . ", " . $match[1] . "))", $sql);
			
			// MySQL
			} elseif ($this->type == 'mysql') {
				$sql = str_replace($match[0], "(UNIX_TIMESTAMP(" . $match[2] . ") - UNIX_TIMESTAMP(" . $match[1] . "))", $sql);
				
			// MSSQL
			} elseif ($this->type == 'mssql') {
				$sql = str_replace($match[0], "DATEDIFF(second, " . $match[1] . ", " . $match[2] . ")", $sql);
			}
		}
		
		// fix add_interval()
		preg_match_all("#add_interval\\(((?>(?:[^()',]+|'[^']+')|\\((?1)(?:,(?1))?\\)|\\(\\))+)\\s*,\\s*'([^']+)'\\s*\\)#i", $sql, $add_matches, PREG_SET_ORDER);
		foreach ($add_matches as $match) {
			
			// SQLite
			if ($this->type == 'sqlite') {
				preg_match_all("#(?:\\+|\\-)\\d+\\s+(?:year|month|day|hour|minute|second)(?:s)?#i", $match[2], $individual_matches);
				$strings = "'" . join("', '", $individual_matches[0]) . "'";
				$sql = str_replace($match[0], "datetime(" . $match[1] . ", " . $strings . ")", $sql);
			
			// PostgreSQL
			} elseif ($this->type == 'postgresql') {
				$sql = str_replace($match[0], "(" . $match[1] . " + INTERVAL '" . $match[2] . "')", $sql);
			
			// MySQL
			} elseif ($this->type == 'mysql') {
				preg_match_all("#(\\+|\\-)(\\d+)\\s+(year|month|day|hour|minute|second)(?:s)?#i", $match[2], $individual_matches, PREG_SET_ORDER);
				$intervals_string = '';
				foreach ($individual_matches as $individual_match) {
					$intervals_string .= ' ' . $individual_match[1] . ' INTERVAL ' . $individual_match[2] . ' ' . strtoupper($individual_match[3]);
				}
				$sql = str_replace($match[0], "(" . $match[1] . $intervals_string . ")", $sql);
			
			// MSSQL
			} elseif ($this->type == 'mssql') {
				preg_match_all("#(\\+|\\-)(\\d+)\\s+(year|month|day|hour|minute|second)(?:s)?#i", $match[2], $individual_matches, PREG_SET_ORDER);
				$date_add_string = '';
				$stack = 0;
				foreach ($individual_matches as $individual_match) {
					$stack++;
					$date_add_string .= 'DATEADD(' . $individual_match[3] . ', ' . $individual_match[1] . $individual_match[2] . ', ';
				}
				$sql = str_replace($match[0], $date_add_string . $match[1] . str_pad('', $stack, ')'), $sql);
			}
		}
		
		return $sql;
	}
	
	
	/**
	 * Translates limit x offset x to row_number() over (order by) syntax
	 * 
	 * @param  string $sql  The SQL to translate
	 * @return string  The translated SQL
	 */
	private function translateLimitOffsetToRowNumber($sql)
	{
		preg_match_all('#((select(?:\s*(?:[^()\']+|\'(?:\'\'|\\\\\'|\\\\[^\']|[^\'\\\\])*\'|\((?1)\)|\(\))+\s*))\s+limit\s+(\d+)\s+offset\s+(\d+))#i', $sql, $matches, PREG_SET_ORDER);
		
		foreach ($matches as $match) {
			$clauses = fSQLParsing::parseSelectSQL($match[1]);
			
			if ($clauses['ORDER BY'] == NULL) {
				$clauses['ORDER BY'] = '1 ASC';
			}
			
			$replacement = '';
			foreach ($clauses as $key => $value) {
				if (empty($value)) {
					continue;
				}
				
				if ($key == 'SELECT') {
					$replacement .= 'SELECT ' . $value . ', ROW_NUMBER() OVER (';
					$replacement .= 'ORDER BY ' . $clauses['ORDER BY'];
					$replacement .= ') AS __flourish_limit_offset_row_num ';
				} elseif ($key == 'LIMIT' || $key == 'ORDER BY') {
					// Skip this clause
				} else {
					$replacement .= $key . ' ' . $value . ' ';
				}
			}
			
			$replacement = 'SELECT * FROM (' . trim($replacement) . ') AS original_query WHERE __flourish_limit_offset_row_num > ' . $match[4] . ' AND __flourish_limit_offset_row_num <= ' . ($match[3] + $match[4]) . ' ORDER BY __flourish_limit_offset_row_num';
			
			$sql = str_replace($match[1], $replacement, $sql);
		}
		
		return $sql;
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