<?php
/**
 * Provides a common API for different databases - will automatically use any installed extension
 * 
 * This class is implemented to use the UTF-8 character encoding. Please see
 * http://flourishlib.com/docs/UTF-8 for more information.
 * 
 * The following databases are supported:
 * 
 *  - [http://microsoft.com/sql/ MSSQL]
 *  - [http://mysql.com MySQL]
 *  - [http://postgresql.org PostgreSQL]
 *  - [http://sqlite.org SQLite]
 * 
 * The class will automatically use the first of the following extensions it finds:
 * 
 *  - MSSQL (via ODBC)
 *   - [http://php.net/pdo_odbc pdo_odbc]
 *   - [http://php.net/odbc odbc]
 *  - MSSQL
 *   - [http://msdn.microsoft.com/en-us/library/cc296221.aspx sqlsrv]
 *   - [http://php.net/pdo_dblib pdo_dblib]
 *   - [http://php.net/mssql mssql] (or [http://php.net/sybase sybase])
 *  - MySQL
 *   - [http://php.net/mysql mysql]
 *   - [http://php.net/mysqli mysqli]
 *   - [http://php.net/pdo_mysql pdo_mysql]
 *  - PostgreSQL
 *   - [http://php.net/pgsql pgsql]
 *   - [http://php.net/pdo_pgsql pdo_pgsql]
 *  - SQLite
 *   - [http://php.net/pdo_sqlite pdo_sqlite] (for v3.x)
 *   - [http://php.net/sqlite sqlite] (for v2.x)
 * 
 * @copyright  Copyright (c) 2007-2009 Will Bond
 * @author     Will Bond [wb] <will@flourishlib.com>
 * @license    http://flourishlib.com/license
 * 
 * @package    Flourish
 * @link       http://flourishlib.com/fDatabase
 * 
 * @version    1.0.0b6
 * @changes    1.0.0b6  Fixed a bug with executing transaction queries when using the mysqli extension [wb, 2009-02-12]
 * @changes    1.0.0b5  Changed @ error suppression operator to `error_reporting()` calls [wb, 2009-01-26]
 * @changes    1.0.0b4  Added a few error suppression operators back in so that developers don't get errors and exceptions [wb, 2009-01-14]
 * @changes    1.0.0b3  Removed some unnecessary error suppresion operators [wb, 2008-12-11]
 * @changes    1.0.0b2  Fixed a bug with PostgreSQL when using the PDO extension and executing an INSERT statement [wb, 2008-12-11]
 * @changes    1.0.0b   The initial implementation [wb, 2007-09-25]
 */
class fDatabase
{
	/**
	 * Composes text using fText if loaded
	 * 
	 * @param  string  $message    The message to compose
	 * @param  mixed   $component  A string or number to insert into the message
	 * @param  mixed   ...
	 * @return string  The composed and possible translated message
	 */
	static protected function compose($message)
	{
		$args = array_slice(func_get_args(), 1);
		
		if (class_exists('fText', FALSE)) {
			return call_user_func_array(
				array('fText', 'compose'),
				array($message, $args)
			);
		} else {
			return vsprintf($message, $args);
		}
	}
	
	
	/**
	 * The character set that data is coming back as
	 * 
	 * @var string 
	 */
	private $character_set;
	
	/**
	 * Database connection resource or PDO object
	 * 
	 * @var mixed
	 */
	private $connection;
	
	/**
	 * The database name
	 * 
	 * @var string
	 */
	private $database;
	
	/**
	 * If debugging is enabled
	 * 
	 * @var boolean
	 */
	private $debug;
	
	/**
	 * The extension to use for the database specified
	 * 
	 * Options include:
	 * 
	 *  - `'mssql'`
	 *  - `'mysql'`
	 *  - `'mysqli'`
	 *  - `'odbc'`
	 *  - `'pgsql'`
	 *  - `'sqlite'`
	 *  - `'sqlsrv'`
	 *  - `'pdo'`
	 * 
	 * @var string
	 */
	private $extension;
	
	/**
	 * The host the database server is located on
	 * 
	 * @var string
	 */
	private $host;
	
	/**
	 * If a transaction is in progress
	 * 
	 * @var boolean
	 */
	private $inside_transaction;
	
	/**
	 * The password for the user specified
	 * 
	 * @var string
	 */
	private $password;
	
	/**
	 * The port number for the host
	 * 
	 * @var string
	 */
	private $port;
	
	/**
	 * The total number of seconds spent executing queries
	 * 
	 * @var float
	 */
	private $query_time;
	
	/**
	 * The millisecond threshold for triggering a warning about SQL performance
	 * 
	 * @var integer
	 */
	private $slow_query_threshold;
	
	/**
	 * The fSQLTranslation object for this database
	 * 
	 * @var object
	 */
	private $translation;
	
	/**
	 * The database type: `'mssql'`, `'mysql'`, `'postgresql'`, or `'sqlite'`
	 * 
	 * @var string
	 */
	private $type;
	
	/**
	 * The unbuffered query instance
	 * 
	 * @var fUnbufferedResult
	 */
	private $unbuffered_result;
	
	/**
	 * The user to connect to the database as
	 * 
	 * @var string
	 */
	private $username;
	
	
	/**
	 * Configures the connection to a database - connection is not made until the first query is executed
	 * 
	 * @param  string  $type      The type of the database: `'mssql'`, `'mysql'`, `'postgresql'`, `'sqlite'`
	 * @param  string  $database  Name of the database. If an ODBC connection `'dsn:'` concatenated with the DSN, if SQLite the path to the database file.
	 * @param  string  $username  Database username, required for all databases except SQLite
	 * @param  string  $password  The password for the username specified
	 * @param  string  $host      Database server host or ip, defaults to localhost for all databases except SQLite
	 * @param  integer $port      The port to connect to, defaults to the standard port for the database type specified
	 * @return fDatabase
	 */
	public function __construct($type, $database, $username=NULL, $password=NULL, $host=NULL, $port=NULL)
	{
		$valid_types = array('mssql', 'mysql', 'postgresql', 'sqlite');
		if (!in_array($type, $valid_types)) {
			throw new fProgrammerException(
				'The database type specified, %1$s, is invalid. Must be one of: %2$s.',
				$type,
				join(', ', $valid_types)
			);
		}
		
		if (empty($database)) {
			throw new fProgrammerException('No database was specified');
		}
		
		if ($type != 'sqlite') {
			if (empty($username)) {
				throw new fProgrammerException('No username was specified');
			}
			if ($host === NULL) {
				$host = 'localhost';
			}
		}
		
		$this->type     = $type;
		$this->database = $database;
		$this->username = $username;
		$this->password = $password;
		$this->host     = $host;
		$this->port     = $port;
		
		$this->character_set = NULL;
		
		$this->determineExtension();
	}
	
	
	/**
	 * Closes the open database connection
	 * 
	 * @return void
	 */
	public function __destruct()
	{
		if (!$this->connection) { return; }
		
		fCore::debug('Total query time: ' . $this->query_time . ' seconds', $this->debug);
		if ($this->extension == 'mssql') {
			mssql_close($this->connection);
		} elseif ($this->extension == 'mysql') {
			mysql_close($this->connection);
		} elseif ($this->extension == 'mysqli') {
			mysqli_close($this->connection);
		} elseif ($this->extension == 'odbc') {
			odbc_close($this->connection);
		} elseif ($this->extension == 'pgsql') {
			pg_close($this->connection);
		} elseif ($this->extension == 'sqlite') {
			sqlite_close($this->connection);
		} elseif ($this->extension == 'sqlsrv') {
			sqlsrv_close($this->connection);
		} elseif ($this->extension == 'pdo') {
			// PDO objects close their own connections when destroyed
		}
	}
	
	
	/**
	 * All requests that hit this method should be requests for callbacks
	 * 
	 * @param  string $method  The method to create a callback for
	 * @return callback  The callback for the method requested
	 */
	public function __get($method)
	{
		return array($this, $method);		
	}
	
	
	/**
	 * Checks to see if an SQL error occured
	 * 
	 * @param  fResult|fUnbufferedResult $result                The result object for the query
	 * @param  string                    $sqlite_error_message  If we are using the sqlite extension, this will contain an error message if one exists
	 * @return void
	 */
	private function checkForError($result, $sqlite_error_message=NULL)
	{
		if ($result->getResult() === FALSE) {
			
			if ($this->extension == 'mssql') {
				$message = mssql_get_last_message();
			} elseif ($this->extension == 'mysql') {
				$message = mysql_error($this->connection);
			} elseif ($this->extension == 'mysqli') {
				$message = mysqli_error($this->connection);
			} elseif ($this->extension == 'odbc') {
				$message = odbc_errormsg($this->connection);
			} elseif ($this->extension == 'pgsql') {
				$message = pg_last_error($this->connection);
			} elseif ($this->extension == 'sqlite') {
				$message = $sqlite_error_message;
			} elseif ($this->extension == 'sqlsrv') {
				$error_info = sqlsrv_errors(SQLSRV_ERR_ALL);
				$message = $error_info[0]['message'];
			} elseif ($this->extension == 'pdo') {
				$error_info = $this->connection->errorInfo();
				$message = $error_info[2];
			}
			
			$db_type_map = array(
				'mssql'      => 'MSSQL',
				'mysql'      => 'MySQL',
				'postgresql' => 'PostgreSQL',
				'sqlite'     => 'SQLite'
			);
			
			throw new fSQLException(
				'%1$s error (%2$s) in %3$s',
				$db_type_map[$this->type],
				$message,
				$result->getSQL()
			);
		}
	}
	
	
	/**
	 * Connects to the database specified if no connection exists
	 * 
	 * @return void
	 */
	private function connectToDatabase()
	{
		// Don't try to reconnect if we are already connected
		if ($this->connection) { return; }
		
		// Establish a connection to the database
		if ($this->extension == 'pdo') {
			if ($this->type == 'mssql') {
				$odbc = strtolower(substr($this->database, 0, 4)) == 'dsn:';
				if ($odbc && in_array('odbc', PDO::getAvailableDrivers())) {
					try {
						$this->connection = new PDO('odbc:' . substr($this->database, 4), $this->username, $this->password);
					} catch (PDOException $e) {
						$this->connection = FALSE;
					}
				}
				if (!$odbc && in_array('mssql', PDO::getAvailableDrivers())) {
					try {
						$separator = (fCore::getOS() == 'windows') ? ',' : ':';
						$port      = ($this->port) ? $separator . $this->port : '';
						$this->connection = new PDO('mssql:host=' . $this->host . $port . ';dbname=' . $this->database, $this->username, $this->password);
					} catch (PDOException $e) {
						$this->connection = FALSE;
					}
				}
				
			} elseif ($this->type == 'mysql') {
				try {
					$this->connection = new PDO('mysql:host=' . $this->host . ';dbname=' . $this->database . (($this->port) ? ';port=' . $this->port : ''), $this->username, $this->password);
					$this->connection->setAttribute(PDO::MYSQL_ATTR_DIRECT_QUERY, 1);
				} catch (PDOException $e) {
					$this->connection = FALSE;
				}
				
			} elseif ($this->type == 'postgresql') {
				try {
					$this->connection = new PDO('pgsql:host=' . $this->host . ' dbname=' . $this->database, $this->username, $this->password);
				} catch (PDOException $e) {
					$this->connection = FALSE;
				}
				
			} elseif ($this->type == 'sqlite') {
				try {
					$this->connection = new PDO('sqlite:' . $this->database);
				} catch (PDOException $e) {
					$this->connection = FALSE;
				}
			}
		}
		
		if ($this->extension == 'sqlite') {
			$this->connection = sqlite_open($this->database);
		}
		
		if ($this->extension == 'mssql') {
			$separator        = (fCore::getOS() == 'windows') ? ',' : ':';
			$this->connection = mssql_connect(($this->port) ? $this->host . $separator . $this->port : $this->host, $this->username, $this->password);
			if ($this->connection !== FALSE && mssql_select_db($this->database, $this->connection) === FALSE) {
				$this->connection = FALSE;
			}
		}
		
		if ($this->extension == 'mysql') {
			$this->connection = mysql_connect(($this->port) ? $this->host . ':' . $this->port : $this->host, $this->username, $this->password);
			if ($this->connection !== FALSE && mysql_select_db($this->database, $this->connection) === FALSE) {
				$this->connection = FALSE;
			}
		}
			
		if ($this->extension == 'mysqli') {
			if ($this->port) {
				$this->connection = mysqli_connect($this->host, $this->username, $this->password, $this->database, $this->port);
			} else {
				$this->connection = mysqli_connect($this->host, $this->username, $this->password, $this->database);
			}
		}
		
		if ($this->extension == 'odbc') {
			$this->connection = odbc_connect(substr($this->database, 4), $this->username, $this->password);
		}
			
		if ($this->extension == 'pgsql') {
			$this->connection = pg_connect("host='" . addslashes($this->host) . "'
											dbname='" . addslashes($this->database) . "'
											user='" . addslashes($this->username) . "'
											password='" . addslashes($this->password) . "'" .
											(($this->port) ? " port='" . $this->port . "'" : ''));
		}
		
		if ($this->extension == 'sqlsrv') {
			$options = array(
				'Database' => $this->database,
				'UID'      => $this->username,
				'PWD'      => $this->password
			);
			$this->connection = sqlsrv_connect($this->host, $options);
		}
		
		// Ensure the connection was established
		if ($this->connection === FALSE) {
			throw new fConnectivityException(
				'Unable to connect to database'
			);
		}
		
		// Make MySQL act more strict and use UTF-8
		if ($this->type == 'mysql') {
			$this->query("SET SQL_MODE = 'ANSI'");
			$this->query("SET NAMES 'utf8'");
			$this->query("SET CHARACTER SET utf8");
		}
		
		// Make SQLite behave like other DBs for assoc arrays
		if ($this->type == 'sqlite') {
			$this->query('PRAGMA short_column_names = 1');
		}
		
		// Fix some issues with mssql
		if ($this->type == 'mssql') {
			$this->query('SET TEXTSIZE 65536');
			$this->character_set = $this->query("SELECT 'WINDOWS-' + CONVERT(VARCHAR, COLLATIONPROPERTY(CONVERT(NVARCHAR, DATABASEPROPERTYEX(%s, 'Collation')), 'CodePage')) AS charset", $this->database)->fetchScalar();
		}
		
		// Make PostgreSQL use UTF-8
		if ($this->type == 'postgresql') {
			$this->query("SET NAMES 'UTF8'");
		}
	}
	
	
	/**
	 * Figures out which extension to use for the database type selected
	 * 
	 * @return void
	 */
	private function determineExtension()
	{
		switch ($this->type) {
			
			case 'mssql':
			
				$odbc = strtolower(substr($this->database, 0, 4)) == 'dsn:';
				
				if ($odbc) {
					if (class_exists('PDO', FALSE) && in_array('odbc', PDO::getAvailableDrivers())) {
						$this->extension = 'pdo';
						
					} elseif (extension_loaded('odbc')) {
						$this->extension = 'odbc';
						
					} else {
						$type = 'MSSQL (ODBC)';
						$exts = 'pdo_odbc, odbc';
					}
					
				} else {
					if (extension_loaded('sqlsrv')) {
						$this->extension = 'sqlsrv';
						
					} elseif (class_exists('PDO', FALSE) && in_array('mssql', PDO::getAvailableDrivers())) {
						$this->extension = 'pdo';
						
					} elseif (extension_loaded('mssql')) {
						$this->extension = 'mssql';
						
					} else {
						$type = 'MSSQL';
						$exts = 'sqlsrv, pdo_dblib, mssql';
					}
				}
				break;
			
			
			case 'mysql':
			
				if (class_exists('PDO', FALSE) && in_array('mysql', PDO::getAvailableDrivers())) {
					$this->extension = 'pdo';
					
				} elseif (extension_loaded('mysqli')) {
					$this->extension = 'mysqli';
					
				} elseif (extension_loaded('mysql')) {
					$this->extension = 'mysql';
					
				} else {
					$type = 'MySQL';
					$exts = 'mysql, mysqli, pdo_mysql';
				}
				break;
				
				
			case 'postgresql':
			
				if (class_exists('PDO', FALSE) && in_array('pgsql', PDO::getAvailableDrivers())) {
					$this->extension = 'pdo';
					
				} elseif (extension_loaded('pgsql')) {
					$this->extension = 'pgsql';
					
				} else {
					$type = 'PostgreSQL';
					$exts = 'pgsql, pdo_pgsql';
				}
				break;
				
				
			case 'sqlite':
			
				$sqlite_version = 0;
				
				if (file_exists($this->database)) {
					
					$database_handle  = fopen($this->database, 'r');
					$database_version = fread($database_handle, 64);
					fclose($database_handle);
					
					if (strpos($database_version, 'SQLite format 3') !== FALSE) {
						$sqlite_version = 3;
					} elseif (strpos($database_version, '** This file contains an SQLite 2.1 database **') !== FALSE) {
						$sqlite_version = 2;
					} else {
						throw new fConnectivityException(
							'The database specified does not appear to be a valid %1$s or %2$s database',
							'SQLite v2.1',
							'v3'
						);
					}
				}
				
				if ((!$sqlite_version || $sqlite_version == 3) && class_exists('PDO', FALSE) && in_array('sqlite', PDO::getAvailableDrivers())) {
					$this->extension = 'pdo';
					
				} elseif ($sqlite_version == 3 && (!class_exists('PDO', FALSE) || !in_array('sqlite', PDO::getAvailableDrivers()))) {
					throw new fEnvironmentException(
						'The database specified is an %1$s database and the %2$s extension is not installed',
						'SQLite v3',
						'pdo_sqlite'
					);
				
				} elseif ($sqlite_version == 2 && extension_loaded('sqlite')) {
					$this->extension = 'sqlite';
					
				} elseif ($sqlite_version == 2 && !extension_loaded('sqlite')) {
					throw new fEnvironmentException(
						'The database specified is an %1$s database and the %2$s extension is not installed',
						'SQLite v2.1',
						'sqlite'
					);
				
				} else {
					$type = 'SQLite';
					$exts = 'pdo_sqlite, sqlite';
				}
				break;
		}
		
		if (!$this->extension) {
			throw new fEnvironmentException(
				'The server does not have any of the following extensions for %2$s support: %2$s',
				$type,
				$exts
			);
		}
	}
	
	
	/**
	 * Sets if debug messages should be shown
	 * 
	 * @param  boolean $flag  If debugging messages should be shown
	 * @return void
	 */
	public function enableDebugging($flag)
	{
		$this->debug = (boolean) $flag;
		if ($this->translation) {
			$this->translation->enableDebugging($this->debug);
		}
	}
	
	
	/**
	 * Sets a flag to trigger a PHP warning message whenever a query takes longer than the millisecond threshold specified
	 * 
	 * It is recommended to use the error handling features of
	 * fCore::enableErrorHandling() to log or email these warnings.
	 * 
	 * @param  integer $threshold  The limit (in milliseconds) of how long an SQL query can take before a warning is triggered
	 * @return void
	 */
	public function enableSlowQueryWarnings($threshold)
	{
		$this->slow_query_threshold = (int) $threshold;
	}
	
	
	/**
	 * Escapes a value for insertion into SQL
	 * 
	 * The valid data types are:
	 * 
	 *  - `'blob'`
	 *  - `'boolean'`
	 *  - `'date'`
	 *  - `'float'`
	 *  - `'integer'`
	 *  - `'string'` (also varchar, char or text)
	 *  - `'varchar'`
	 *  - `'char'`
	 *  - `'text'`
	 *  - `'time'`
	 *  - `'timestamp'`
	 * 
	 * In addition to being able to specify the data type, you can also pass
	 * in an SQL statement with data type placeholders in the following form:
	 *   
	 *  - `%l` for a blob
	 *  - `%b` for a boolean
	 *  - `%d` for a date
	 *  - `%f` for a float
	 *  - `%i` for an integer
	 *  - `%s` for a string
	 *  - `%t` for a time
	 *  - `%p` for a timestamp
	 * 
	 * @param  string $sql_or_type  This can either be the data type to escape or an SQL string with a data type placeholder - see method description
	 * @param  mixed  $value        The value to escape - you should pass a single value if a data type is specified or a value for each placeholder
	 * @param  mixed  ...
	 * @return string  The escaped value/SQL
	 */
	public function escape($sql_or_type, $value)
	{
		$values = array_slice(func_get_args(), 1);
		
		if (sizeof($values) < 1) {
			throw new fProgrammerException(
				'No value was specified to escape'
			);	
		}
		
		// Convert all objects into strings
		$new_values = array();
		foreach ($values as $value) {
			if (is_object($value) && is_callable(array($value, '__toString'))) {
				$value = $value->__toString();
			} elseif (is_object($value)) {
				$value = (string) $value;	
			}
			$new_values[] = $value;
		}
		$values = $new_values;
		
		// Handle single value escaping
		$value = array_shift($values);
		
		switch ($sql_or_type) {
			case 'blob':
			case '%l':
				return $this->escapeBlob($value);
			case 'boolean':
			case '%b':
				return $this->escapeBoolean($value);
			case 'date':
			case '%d':
				return $this->escapeDate($value);
			case 'float':
			case '%f':
				return $this->escapeFloat($value);
			case 'integer':
			case '%i':
				return $this->escapeInteger($value);
			case 'string':
			case 'varchar':
			case 'char':
			case 'text':
			case '%s':
				return $this->escapeString($value);
			case 'time':
			case '%t':
				return $this->escapeTime($value);
			case 'timestamp':
			case '%p':
				return $this->escapeTimestamp($value);
		}	
		
		// Handle SQL escaping
		preg_match_all("#(?:'(?:''|\\\\'|\\\\[^']|[^'\\\\]+)*')|(?:[^']+)#", $sql_or_type, $matches);
		
		$temp_sql = '';
		$strings = array();
		
		// Replace strings with a placeholder so they don't mess use the regex parsing
		foreach ($matches[0] as $match) {
			if ($match[0] == "'") {
				$strings[] = $match;
				$match = ':string_' . (sizeof($strings)-1);
			}
			$temp_sql .= $match;
		}
		
		$pieces = preg_split('#(%[lbdfistp])\b#', $temp_sql, -1, PREG_SPLIT_DELIM_CAPTURE|PREG_SPLIT_NO_EMPTY);
		$sql = '';
		
		$missing_values = -1;
		
		foreach ($pieces as $piece) {
			switch ($piece) {
				case '%l':
					$sql .= $this->escapeBlob($value);
					break;
				case '%b':
					$sql .= $this->escapeBoolean($value);
					break;
				case '%d':
					$sql .= $this->escapeDate($value);
					break;
				case '%f':
					$sql .= $this->escapeFloat($value);
					break;
				case '%i':
					$sql .= $this->escapeInteger($value);
					break;
				case '%s':
					$sql .= $this->escapeString($value);
					break;
				case '%t':
					$sql .= $this->escapeTime($value);
					break;
				case '%p':
					$sql .= $this->escapeTimestamp($value);
					break;
				default:
					$sql .= $piece;
					continue 2;	
			} 		
			if (sizeof($values)) {
				$value = array_shift($values);
			} else {
				$value = NULL;
				$missing_values++;	
			}
		}
		
		if ($missing_values > 0) {
			throw new fProgrammerException(
				'%1$s value(s) are missing for the placeholders in: %2$s',
				$missing_values,
				$sql_or_type
			);	
		}
		
		if (sizeof($values)) {
			throw new fProgrammerException(
				'%1$s extra value(s) were passed for the placeholders in: %2$s',
				sizeof($values),
				$sql_or_type
			); 	
		}
		
		$string_number = 0;
		foreach ($strings as $string) {
			$sql = preg_replace('#:string_' . $string_number++ . '\b#', $string, $sql);	
		}
		
		return $sql;
	}
	
	
	/**
	 * Escapes a blob for use in SQL, includes surround quotes when appropriate
	 * 
	 * A `NULL` value will be returned as `'NULL'`
	 * 
	 * @param  string $value  The blob to escape
	 * @return string  The escaped blob
	 */
	private function escapeBlob($value)
	{
		if ($value === NULL) {
			return 'NULL';
		}
		
		$this->connectToDatabase();
		
		if ($this->extension == 'mysql') {
			return "'" . mysql_real_escape_string($value, $this->connection) . "'";
		} elseif ($this->extension == 'mysqli') {
			return "'" . mysqli_real_escape_string($this->connection, $value) . "'";
		} elseif ($this->extension == 'pgsql') {
			return "'" . pg_escape_bytea($this->connection, $value) . "'";
		} elseif ($this->type == 'sqlite') {
			return "X'" . bin2hex($value) . "'";
		} elseif ($this->type == 'mssql') {
			return '0x' . bin2hex($value);
		} elseif ($this->extension == 'pdo') {
			return $this->connection->quote($value, PDO::PARAM_LOB);
		}
	}
	
	
	/**
	 * Escapes a boolean for use in SQL, includes surround quotes when appropriate
	 * 
	 * A `NULL` value will be returned as `'NULL'`
	 * 
	 * @param  boolean $value  The boolean to escape
	 * @return string  The database equivalent of the boolean passed
	 */
	private function escapeBoolean($value)
	{
		if ($value === NULL) {
			return 'NULL';
		}
		
		if (in_array($this->type, array('postgresql', 'mysql'))) {
			return ($value) ? 'TRUE' : 'FALSE';
		} elseif (in_array($this->type, array('mssql', 'sqlite'))) {
			return ($value) ? "'1'" : "'0'";
		}
	}
	
	
	/**
	 * Escapes a date for use in SQL, includes surrounding quotes
	 * 
	 * A `NULL` or invalid value will be returned as `'NULL'`
	 * 
	 * @param  string $value  The date to escape
	 * @return string  The escaped date
	 */
	private function escapeDate($value)
	{
		if ($value === NULL) {
			return 'NULL';
		}
		if (!strtotime($value)) {
			return 'NULL';
		}
		return "'" . date('Y-m-d', strtotime($value)) . "'";
	}
	
	
	/**
	 * Escapes a float for use in SQL
	 * 
	 * A `NULL` value will be returned as `'NULL'`
	 * 
	 * @param  float $value  The float to escape
	 * @return string  The escaped float
	 */
	private function escapeFloat($value)
	{
		if ($value === NULL) {
			return 'NULL';
		}
		if (!strlen($value)) {
			return 'NULL';
		}
		if (!preg_match('#^[+\-]?[0-9]+(\.[0-9]+)?$#D', $value)) {
			return 'NULL';
		}
		return (string) $value;
	}
	
	
	/**
	 * Escapes an integer for use in SQL
	 * 
	 * A `NULL` or invalid value will be returned as `'NULL'`
	 * 
	 * @param  integer $value  The integer to escape
	 * @return string  The escaped integer
	 */
	private function escapeInteger($value)
	{
		if ($value === NULL) {
			return 'NULL';
		}
		if (!strlen($value)) {
			return 'NULL';
		}
		if (!preg_match('#^[+\-]?[0-9]+$#D', $value)) {
			return 'NULL';
		}
		return (string) $value;
	}
	
	
	/**
	 * Escapes a string for use in SQL, includes surrounding quotes
	 * 
	 * A `NULL` value will be returned as `'NULL'`
	 * 
	 * @param  string $value  The string to escape
	 * @return string  The escaped string
	 */
	private function escapeString($value)
	{
		if ($value === NULL) {
			return 'NULL';
		}
		
		$this->connectToDatabase();
		
		if ($this->extension == 'mysql') {
			return "'" . mysql_real_escape_string($value, $this->connection) . "'";
		} elseif ($this->extension == 'mysqli') {
			return "'" . mysqli_real_escape_string($this->connection, $value) . "'";
		} elseif ($this->extension == 'pgsql') {
			return "'" . pg_escape_string($value) . "'";
		} elseif ($this->extension == 'sqlite') {
			return "'" . sqlite_escape_string($value) . "'";
		
		} elseif ($this->type == 'mssql') {
			
			// If there are any non-ASCII characters, we need to escape
			if (preg_match('#[^\x00-\x7F]#', $value)) {
				$characters = preg_split('##u', $value);
				$output = "'";
				foreach ($characters as $character) {
					if (strlen($character) > 1) {
						$b = array_map('ord', str_split($character));
						switch (strlen($character)) {
							case 2:
								$bin = substr(decbin($b[0]), 3) .
										   substr(decbin($b[1]), 2);
								break;
							
							case 3:
								$bin = substr(decbin($b[0]), 4) .
										   substr(decbin($b[1]), 2) .
										   substr(decbin($b[2]), 2);
								break;
							
							// If it is a 4-byte character, MSSQL can't store it
							// so instead store a ?
							default:
								$output .= '?';
								continue;
						}
						$output .= "'+NCHAR(" . bindec($bin) . ")+'";
					} else {
						$output .= $character;
						// Escape single quotes
						if ($character = "'") {
							$output .= "'";
						}
					}
				}
				$output .= "'";
			
			// ASCII text is normal
			} else {
				$output = "'" . str_replace("'", "''", $value) . "'";
			}
			
			# a \ before a \r\n has to be escaped with another \
			return preg_replace('#(?<!\\\\)\\\\(?=\r\n)#', '\\\\', $output);
		
		} elseif ($this->extension == 'pdo') {
			return $this->connection->quote($value);
		}
	}
	
	
	/**
	 * Escapes a time for use in SQL, includes surrounding quotes
	 * 
	 * A `NULL` or invalid value will be returned as `'NULL'`
	 * 
	 * @param  string $value  The time to escape
	 * @return string  The escaped time
	 */
	private function escapeTime($value)
	{
		if ($value === NULL) {
			return 'NULL';
		}
		if (!strtotime($value)) {
			return 'NULL';
		}
		return "'" . date('H:i:s', strtotime($value)) . "'";
	}
	
	
	/**
	 * Escapes a timestamp for use in SQL, includes surrounding quotes
	 * 
	 * A `NULL` or invalid value will be returned as `'NULL'`
	 * 
	 * @param  string $value  The timestamp to escape
	 * @return string  The escaped timestamp
	 */
	private function escapeTimestamp($value)
	{
		if ($value === NULL) {
			return 'NULL';
		}
		if (!strtotime($value)) {
			return 'NULL';
		}
		return "'" . date('Y-m-d H:i:s', strtotime($value)) . "'";
	}
	
	
	/**
	 * Executes an SQL query
	 * 
	 * @param  fResult $result  The result object for the query
	 * @return void
	 */
	private function executeQuery($result)
	{
		$old_level = error_reporting(error_reporting() & ~E_WARNING);
		
		if ($this->extension == 'mssql') {
			$result->setResult(mssql_query($result->getSQL(), $this->connection));
		} elseif ($this->extension == 'mysql') {
			$result->setResult(mysql_query($result->getSQL(), $this->connection));
		} elseif ($this->extension == 'mysqli') {
			$result->setResult(mysqli_query($this->connection, $result->getSQL()));
		} elseif ($this->extension == 'odbc') {
			$rows = array();
			$resource = odbc_exec($this->connection, $result->getSQL());
			if (is_resource($resource)) {
				// Allow up to 1MB of binary data
				odbc_longreadlen($resource, 1048576);
				odbc_binmode($resource, ODBC_BINMODE_CONVERT);
				while ($row = odbc_fetch_array($resource)) {
					$rows[] = $row;
				}
				$result->setResult($rows);
			} else {
				$result->setResult($resource);
			}
		} elseif ($this->extension == 'pgsql') {
			$result->setResult(pg_query($this->connection, $result->getSQL()));
		} elseif ($this->extension == 'sqlite') {
			$result->setResult(sqlite_query($this->connection, $result->getSQL(), SQLITE_ASSOC, $sqlite_error_message));
		} elseif ($this->extension == 'sqlsrv') {
			$rows = array();
			$resource = sqlsrv_query($this->connection, $result->getSQL());
			if (is_resource($resource)) {
				while ($row = sqlsrv_fetch_array($resource, SQLSRV_FETCH_ASSOC)) {
					$rows[] = $row;
				}
				$result->setResult($rows);
			} else {
				$result->setResult($resource);
			}
		} elseif ($this->extension == 'pdo') {
			$pdo_statement = $this->connection->query($result->getSQL());
			$result->setResult((is_object($pdo_statement)) ? $pdo_statement->fetchAll(PDO::FETCH_ASSOC) : $pdo_statement);
		}
		
		error_reporting($old_level);
		
		if ($this->extension == 'sqlite') {
			$this->checkForError($result, $sqlite_error_message);
		} else {
			$this->checkForError($result);
		}
		
		if ($this->extension == 'pdo') {
			$this->setAffectedRows($result, $pdo_statement);
			$pdo_statement->closeCursor();
			unset($pdo_statement);
		} elseif ($this->extension == 'odbc') {
			$this->setAffectedRows($result, $resource);
			odbc_free_result($resource);
		} elseif ($this->extension == 'sqlsrv') {
			$this->setAffectedRows($result, $resource);
			sqlsrv_free_stmt($resource);
		} else {
			$this->setAffectedRows($result);
		}
		
		$this->setReturnedRows($result);
		
		$this->handleAutoIncrementedValue($result);
	}
	
	
	/**
	 * Executes an unbuffered SQL query
	 * 
	 * @param  fUnbufferedResult $result  The result object for the query
	 * @return void
	 */
	private function executeUnbufferedQuery($result)
	{
		$old_level = error_reporting(error_reporting() & ~E_WARNING);
		
		if ($this->extension == 'mssql') {
			$result->setResult(mssql_query($result->getSQL(), $this->connection, 20));
		} elseif ($this->extension == 'mysql') {
			$result->setResult(mysql_unbuffered_query($result->getSQL(), $this->connection));
		} elseif ($this->extension == 'mysqli') {
			$result->setResult(mysqli_query($this->connection, $result->getSQL(), MYSQLI_USE_RESULT));
		} elseif ($this->extension == 'odbc') {
			$result->setResult(odbc_exec($this->connection, $result->getSQL()));
		} elseif ($this->extension == 'pgsql') {
			$result->setResult(pg_query($this->connection, $result->getSQL()));
		} elseif ($this->extension == 'sqlite') {
			$result->setResult(sqlite_unbuffered_query($this->connection, $result->getSQL(), SQLITE_ASSOC, $sqlite_error_message));
		} elseif ($this->extension == 'sqlsrv') {
			$result->setResult(sqlsrv_query($this->connection, $result->getSQL()));
		} elseif ($this->extension == 'pdo') {
			$result->setResult($this->connection->query($result->getSQL()));
		}
		
		error_reporting($old_level);
		
		if ($this->extension == 'sqlite') {
			$this->checkForError($result, $sqlite_error_message);
		} else {
			$this->checkForError($result);
		}
	}
	
	
	/**
	 * Takes in a string of SQL that contains multiple queries and returns any array of them
	 * 
	 * @param  string $sql  The string of SQL to parse for queries
	 * @return array  The individual SQL queries
	 */
	private function explodeQueries($sql)
	{
		$sql_queries = array();
		
		// Separate the SQL from quoted values
		preg_match_all("#(?:'(?:''|\\\\'|\\\\[^']|[^'\\\\]*)*')|(?:[^']+)#", $sql, $matches);
		
		$cur_sql = '';
		foreach ($matches[0] as $match) {
			
			// This is a quoted string value, don't do anything to it
			if ($match[0] == "'") {
				$cur_sql .= $match;
			
			// Handle the SQL
			} else {
				$sql_strings = explode(';', $match);
				$cur_sql .= $sql_strings[0];
				for ($i=1; $i < sizeof($sql_strings); $i++) {
					// SQLite triggers have a ; before and after the "end"
					if (strtolower(trim($sql_strings[$i])) == 'end') {
						$cur_sql .= "; END";
						$i++;
						if ($i >= sizeof($sql_strings)) {
							break;
						}
					}
					$cur_sql = trim($cur_sql);
					if ($cur_sql) {
						$sql_queries[] = $cur_sql;
					}
					$cur_sql = $sql_strings[$i];
				}
			}
		}
		if (trim($cur_sql)) {
			$sql_queries[] = $cur_sql;
		}
		
		return $sql_queries;
	}
	
	
	/**
	 * Gets the name of the database currently connected to
	 * 
	 * @return string  The name of the database currently connected to
	 */
	public function getDatabase()
	{
		return $this->database;
	}
	
	
	/**
	 * Gets the php extension being used
	 * 
	 * @internal
	 * 
	 * @return string  The php extension used for database interaction
	 */
	public function getExtension()
	{
		return $this->extension;
	}
	
	
	/**
	 * Gets the database type
	 * 
	 * @return string  The database type: `'mssql'`, `'mysql'`, `'postgresql'` or `'sqlite'`
	 */
	public function getType()
	{
		return $this->type;
	}
	
	
	/**
	 * Will grab the auto incremented value from the last query (if one exists)
	 * 
	 * @param  fResult $result  The result object for the query
	 * @return void
	 */
	private function handleAutoIncrementedValue($result)
	{
		if (!preg_match('#^\s*INSERT#i', $result->getSQL())) {
			$result->setAutoIncrementedValue(NULL);
			return;
		}
		
		$insert_id = NULL;
		
		if ($this->extension == 'mssql') {
			$insert_id_res = mssql_query("SELECT @@IDENTITY AS insert_id", $this->connection);
			$insert_id     = mssql_result($insert_id_res, 0, 'insert_id');
			mssql_free_result($insert_id_res);
		
		} elseif ($this->extension == 'mysql') {
			$insert_id     = mysql_insert_id($this->connection);
		
		} elseif ($this->extension == 'mysqli') {
			$insert_id     = mysqli_insert_id($this->connection);
		
		} elseif ($this->extension == 'odbc') {
			$insert_id_res = odbc_exec("SELECT @@IDENTITY AS insert_id", $this->connection);
			$insert_id     = odbc_result($insert_id_res, 'insert_id');
			odbc_free_result($insert_id_res);
		
		} elseif ($this->extension == 'pgsql') {
			
			if (!$this->isInsideTransaction()) {
				pg_query($this->connection, "BEGIN");
			} else {
				pg_query($this->connection, "SAVEPOINT get_last_val");
			}
			
			$old_level = error_reporting(error_reporting() & ~E_WARNING);
			$insert_id_res = pg_query($this->connection, "SELECT lastval()");
			error_reporting($old_level);
			
			if (is_resource($insert_id_res)) {
				$insert_id_row = pg_fetch_assoc($insert_id_res);
				$insert_id = array_shift($insert_id_row);
				pg_free_result($insert_id_res);
				
				if (!$this->isInsideTransaction()) {
					pg_query($this->connection, "COMMIT");
				}
				
			} else {
				if (!$this->isInsideTransaction()) {
					pg_query($this->connection, "ROLLBACK");
				} else {
					pg_query($this->connection, "ROLLBACK TO get_last_val");
				}
			}
		
		} elseif ($this->extension == 'sqlite') {
			$insert_id = sqlite_last_insert_rowid($this->connection);
		
		} elseif ($this->extension == 'sqlsrv') {
			$insert_id_res = sqlsrv_query($this->connection, "SELECT @@IDENTITY AS insert_id");
			$insert_id     = sqlsrv_get_field($insert_id_res, 0);
			sqlsrv_free_stmt($insert_id_res);
		
		} elseif ($this->extension == 'pdo') {
			
			switch ($this->type) {
				
				case 'mssql':
					try {
						$insert_id_statement = $this->connection->query("SELECT @@IDENTITY AS insert_id");
						if (!$insert_id_statement) {
							throw new Exception();
						}
						
						$insert_id_row = $insert_id_statement->fetch(PDO::FETCH_ASSOC);
						$insert_id = array_shift($insert_id_row);
						
					} catch (Exception $e) {
						// If there was an error we don't have an insert id
					}
				
				case 'postgresql':
					try {
						
						if (!$this->isInsideTransaction()) {
							$this->connection->beginTransaction();
						} else {
							$this->connection->query("SAVEPOINT get_last_val");
						}
						
						$insert_id_statement = $this->connection->query("SELECT lastval()");
						if (!$insert_id_statement) {
							throw new Exception();
						}
						
						$insert_id_row = $insert_id_statement->fetch(PDO::FETCH_ASSOC);
						$insert_id = array_shift($insert_id_row);
						
						if (!$this->isInsideTransaction()) {
							$this->connection->commit();
						}
						
					} catch (Exception $e) {
						
						if (!$this->isInsideTransaction()) {
							$this->connection->rollBack();
						} else {
							$this->connection->exec("ROLLBACK TO get_last_val");
						}
						
					}
					break;
		
				case 'mysql':
					$insert_id = $this->connection->lastInsertId();
					break;
		
				case 'sqlite':
					$insert_id = $this->connection->lastInsertId();
					break;
			}
		}
		
		$result->setAutoIncrementedValue($insert_id);
	}
	
	
	/**
	 * Will hand off a transaction query to the PDO method if the current DB connection is via PDO
	 * 
	 * @param  string $sql           The SQL to check for a transaction query
	 * @param  string $result_class  The type of result object to create
	 * @return mixed  If the connection is not via PDO will return `FALSE`, otherwise an object of the type $result_class
	 */
	private function handleTransactionQueries($sql, $result_class)
	{
		if (!is_object($this->connection) || get_class($this->connection) != 'PDO') {
			return FALSE;
		}
		
		$success = FALSE;
		
		try {
			if (preg_match('#^\s*(begin|start)(\s+transaction)?\s*$#iD', $sql)) {
				$this->connection->beginTransaction();
				$success = TRUE;
			}
			if (preg_match('#^\s*(commit)(\s+transaction)?\s*$#iD', $sql)) {
				$this->connection->commit();
				$success = TRUE;
			}
			if (preg_match('#^\s*(rollback)(\s+transaction)?\s*$#iD', $sql)) {
				$this->connection->rollBack();
				$success = TRUE;
			}
		} catch (Exception $e) {
			$db_type_map = array(
				'mssql'      => 'MSSQL',
				'mysql'      => 'MySQL',
				'postgresql' => 'PostgreSQL',
				'sqlite'     => 'SQLite'
			);
			
			throw new fSQLException(
				'%1$s error (%2$s) in %3$s',
				$db_type_map[$this->type],
				$e->getMessage(),
				$sql
			);
		}
		
		if ($success) {
			$result = new $result_class($this->type, $this->extension);
			$result->setSQL($sql);
			$result->setResult(TRUE);
			return $result;
		}
		
		return FALSE;
	}
	
	
	/**
	 * Will indicate if a transaction is currently in progress
	 * 
	 * @return boolean  If a transaction has been started and not yet rolled back or committed
	 */
	public function isInsideTransaction()
	{
		return $this->inside_transaction;
	}
	
	
	/**
	 * Executes one or more SQL queries
	 * 
	 * @param  string $sql    One or more SQL statements
	 * @param  mixed  $value  The optional value(s) to place into any placeholders in the SQL - see ::escape() for details
	 * @param  mixed  ...
	 * @return fResult|array  The fResult object(s) for the query
	 */
	public function query($sql)
	{
		$this->connectToDatabase();
		
		// Ensure an SQL statement was passed
		if (empty($sql)) {
			throw new fProgrammerException('No SQL statement passed');
		}
		
		if (func_num_args() > 1) {
			$args = func_get_args();
			$sql  = call_user_func_array($this->escape, $args);
		}
		
		// Split multiple queries
		if (strpos($sql, ';') !== FALSE) {
			$sql_queries = $this->explodeQueries($sql);
			$sql = array_shift($sql_queries);
		}
		
		$start_time = microtime(TRUE);
		
		$this->trackTransactions($sql);
		if (!$result = $this->handleTransactionQueries($sql, 'fResult')) {
			$result = new fResult($this->type, $this->extension, $this->character_set);
			$result->setSQL($sql);
			
			$this->executeQuery($result);
		}
		
		// Write some debugging info
		$query_time = microtime(TRUE) - $start_time;
		$this->query_time += $query_time;
		fCore::debug(
			self::compose(
				'Query time was %1$s seconds for:%2$s',
				$query_time,
				"\n" . $result->getSQL()
			),
			$this->debug
		);
		
		if ($this->slow_query_threshold && $query_time > $this->slow_query_threshold) {
			trigger_error(
				self::compose(
					'The following query took %1$s milliseconds, which is above the slow query threshold of %2$s:%3$s',
					$query_time,
					$this->slow_query_threshold,
					"\n" . $result->getSQL()
				),
				E_USER_WARNING
			);
		}
		
		// Handle multiple SQL queries
		if (!empty($sql_queries)) {
			$result = array($result);
			foreach ($sql_queries as $sql_query) {
				$result[] = $this->query($sql_query);
			}
		}
		
		return $result;
	}
	
	
	/**
	 * Sets the number of rows affected by the query
	 * 
	 * @param  fResult $result    The result object for the query
	 * @param  mixed   $resource  Only applicable for `pdo`, `odbc` and `sqlsrv` extentions, this is either the `PDOStatement` object or `odbc` or `sqlsrv` resource
	 * @return void
	 */
	private function setAffectedRows($result, $resource=NULL)
	{
		if ($this->extension == 'mssql') {
			$affected_rows_result = mssql_query('SELECT @@ROWCOUNT AS rows', $this->connection);
			$result->setAffectedRows((int) mssql_result($affected_rows_result, 0, 'rows'));
		} elseif ($this->extension == 'mysql') {
			$result->setAffectedRows(mysql_affected_rows($this->connection));
		} elseif ($this->extension == 'mysqli') {
			$result->setAffectedRows(mysqli_affected_rows($this->connection));
		} elseif ($this->extension == 'odbc') {
			$result->setAffectedRows(odbc_num_rows($resource));
		} elseif ($this->extension == 'pgsql') {
			$result->setAffectedRows(pg_affected_rows($result->getResult()));
		} elseif ($this->extension == 'sqlite') {
			$result->setAffectedRows(sqlite_changes($this->connection));
		} elseif ($this->extension == 'sqlsrv') {
			$result->setAffectedRows(sqlsrv_rows_affected($resource));
		} elseif ($this->extension == 'pdo') {
			// This fixes the fact that rowCount is not reset for non INSERT/UPDATE/DELETE statements
			try {
				if (!$resource->fetch()) {
					throw new PDOException();
				}
				$result->setAffectedRows(0);
			} catch (PDOException $e) {
				$result->setAffectedRows($resource->rowCount());
			}
		}
	}
	
	
	/**
	 * Sets the number of rows returned by the query
	 * 
	 * @param  fResult $result  The result object for the query
	 * @return void
	 */
	private function setReturnedRows($result)
	{
		if (is_resource($result->getResult()) || is_object($result->getResult())) {
			if ($this->extension == 'mssql') {
				$result->setReturnedRows(mssql_num_rows($result->getResult()));
			} elseif ($this->extension == 'mysql') {
				$result->setReturnedRows(mysql_num_rows($result->getResult()));
			} elseif ($this->extension == 'mysqli') {
				$result->setReturnedRows(mysqli_num_rows($result->getResult()));
			} elseif ($this->extension == 'pgsql') {
				$result->setReturnedRows(pg_num_rows($result->getResult()));
			} elseif ($this->extension == 'sqlite') {
				$result->setReturnedRows(sqlite_num_rows($result->getResult()));
			}
		} elseif (is_array($result->getResult())) {
			$result->setReturnedRows(sizeof($result->getResult()));
		}
	}
	
	
	/**
	 * Keeps track to see if a transaction is being started or stopped
	 * 
	 * @param  string $sql  The SQL to check for a transaction query
	 * @return void
	 */
	private function trackTransactions($sql)
	{
		if (preg_match('#^\s*(begin|start)(\s+transaction)?\s*$#iD', $sql)) {
			if ($this->inside_transaction) {
				throw new fProgrammerException('A transaction is already in progress');
			}
			$this->inside_transaction = TRUE;
			
		} elseif (preg_match('#^\s*(commit)(\s+transaction)?\s*$#iD', $sql)) {
			if (!$this->inside_transaction) {
				throw new fProgrammerException('There is no transaction in progress');
			}
			$this->inside_transaction = FALSE;
			
		} elseif (preg_match('#^\s*(rollback)(\s+transaction)?\s*$#iD', $sql)) {
			if (!$this->inside_transaction) {
				throw new fProgrammerException('There is no transaction in progress');
			}
			$this->inside_transaction = FALSE;
		}
	}
	
	
	/**
	 * Translates the SQL statement using fSQLTranslation and executes it
	 * 
	 * @param  string $sql    One or more SQL statements
	 * @param  mixed  $value  The optional value(s) to place into any placeholders in the SQL - see ::escape() for details
	 * @param  mixed  ...
	 * @return fResult|array  The fResult object(s) for the query
	 */
	public function translatedQuery($sql)
	{
		if (!$this->translation) {
			$this->connectToDatabase();
			$this->translation = new fSQLTranslation($this, $this->connection);
			$this->translation->enableDebugging($this->debug);
		}
		
		if (func_num_args() > 1) {
			$args = func_get_args();
			$sql  = call_user_func_array($this->escape, $args);
		}
		
		$result = $this->query($this->translation->translate($sql));
		$result->setUntranslatedSQL($sql);
		return $result;
	}
	
	
	/**
	 * Executes a single SQL statement in unbuffered mode. This is optimal for
	 * large results sets since it does not load the whole result set into
	 * memory first. The gotcha is that only one unbuffered result can exist at
	 * one time. If another unbuffered query is executed, the old result will
	 * be deleted.
	 * 
	 * @param  string $sql    A single SQL statement
	 * @param  mixed  $value  The optional value(s) to place into any placeholders in the SQL - see ::escape() for details
	 * @param  mixed  ...
	 * @return fUnbufferedResult  The result object for the unbuffered query
	 */
	public function unbufferedQuery($sql)
	{
		$this->connectToDatabase();
		
		// Ensure an SQL statement was passed
		if (empty($sql)) {
			throw new fProgrammerException('No SQL statement passed');
		}
		
		if (func_num_args() > 1) {
			$args = func_get_args();
			$sql  = call_user_func_array($this->escape, $args);
		}
		
		if ($this->unbuffered_result) {
			$this->unbuffered_result->__destruct();
		}
		
		$start_time = microtime(TRUE);
		
		$this->trackTransactions($sql);
		if (!$result = $this->handleTransactionQueries($sql, 'fUnbufferedRequest')) {
			$result = new fUnbufferedResult($this->type, $this->extension, $this->character_set);
			$result->setSQL($sql);
			
			$this->executeUnbufferedQuery($result);
		}
		
		// Write some debugging info
		$query_time = microtime(TRUE) - $start_time;
		$this->query_time += $query_time;
		fCore::debug(
			self::compose(
				'Query time was %1$s seconds for (unbuffered):%2$s',
				$query_time,
				"\n" . $result->getSQL()
			),
			$this->debug
		);
		
		if ($this->slow_query_threshold && $query_time > $this->slow_query_threshold) {
			trigger_error(
				self::compose(
					'The following query took %1$s milliseconds, which is above the slow query threshold of %2$s:%3$s',
					$query_time,
					$this->slow_query_threshold,
					"\n" . $result->getSQL()
				),
				E_USER_WARNING
			);
		}
		
		$this->unbuffered_result = $result;
		
		return $result;
	}
	
	
	/**
	 * Translates the SQL statement using fSQLTranslation and then executes it
	 * in unbuffered mode. This is optimal for large results sets since it does
	 * not load the whole result set into memory first. The gotcha is that only
	 * one unbuffered result can exist at one time. If another unbuffered query
	 * is executed, the old result will be deleted.
	 * 
	 * @param  string $sql    A single SQL statement
	 * @param  mixed  $value  The optional value(s) to place into any placeholders in the SQL - see ::escape() for details
	 * @param  mixed  ...
	 * @return fUnbufferedResult  The result object for the unbuffered query
	 */
	public function unbufferedTranslatedQuery($sql)
	{
		if (!$this->translation) {
			$this->connectToDatabase();
			$this->translation = new fSQLTranslation($this, $this->connection);
		}
		
		if (func_num_args() > 1) {
			$args = func_get_args();
			$sql  = call_user_func_array($this->escape, $args);
		}
		
		$result = $this->unbufferedQuery($this->translation->translate($sql));
		$result->setUntranslatedSQL($sql);
		return $result;
	}
	
	
	/**
	 * Unescapes a value coming out of a database based on its data type
	 * 
	 * The valid data types are:
	 * 
	 *  - `'blob'` (or `'%l'`)
	 *  - `'boolean'` (or `'%b'`)
	 *  - `'date'` (or `'%d'`)
	 *  - `'float'` (or `'%f'`)
	 *  - `'integer'` (or `'%i'`)
	 *  - `'string'` (also `'%s, `'varchar'`, `'char'` or `'text'`)
	 *  - `'time'` (or `'%t'`)
	 *  - `'timestamp'` (or `'%p'`)
	 * 
	 * @param  string $data_type  The data type being unescaped - see method description for valid values
	 * @param  mixed  $value      The value to unescape
	 * @return mixed  The unescaped value
	 */
	public function unescape($data_type, $value)
	{
		switch ($data_type) {
			case 'blob':
			case '%l':
				return $this->unescapeBlob($value);
			case 'boolean':
			case '%b':
				return $this->unescapeBoolean($value);
			case 'date':
			case '%d':
				return $this->unescapeDate($value);
			case 'float':
			case '%f':
				return $this->unescapeFloat($value);
			case 'integer':
			case '%i':
				return $this->unescapeInteger($value);
			case 'string':
			case 'varchar':
			case 'char':
			case 'text':
			case '%s':
				return $this->unescapeString($value);
			case 'time':
			case '%t':
				return $this->unescapeTime($value);
			case 'timestamp':
			case '%p':
				return $this->unescapeTimestamp($value);
		}	
		
		throw new fProgrammerException(
			'Unknown data type, %1$s, specified. Must be one of: %2$s.',
			$data_type,
			'blob, %l, boolean, %b, date, %d, float, %f, integer, %i, string, %s, time, %t, timestamp, %p'
		);	
	}
	
	
	/**
	 * Unescapes a blob coming out of the database
	 * 
	 * @param  string $value  The value to unescape
	 * @return binary  The binary data
	 */
	private function unescapeBlob($value)
	{
		$this->connectToDatabase();
		
		if ($this->extension == 'pgsql') {
			return pg_unescape_bytea($this->connection, $value);
		} if ($this->extension == 'odbc') {
			return pack('H*', $value);
		} else  {
			return $value;
		}
	}
	
	
	/**
	 * Unescapes a boolean coming out of the database
	 * 
	 * @param  string $value  The value to unescape
	 * @return boolean  The boolean
	 */
	private function unescapeBoolean($value)
	{
		return ($value === 'f' || !$value) ? FALSE : TRUE;
	}
	
	
	/**
	 * Unescapes a date coming out of the database
	 * 
	 * @param  string $value  The value to unescape
	 * @return string  The date in YYYY-MM-DD format
	 */
	private function unescapeDate($value)
	{
		return date('Y-m-d', strtotime($value));
	}
	
	
	/**
	 * Unescapes a float coming out of the database (included for completeness)
	 * 
	 * @param  string $value  The value to unescape
	 * @return float  The float
	 */
	private function unescapeFloat($value)
	{
		return $value;
	}
	
	
	/**
	 * Unescapes an integer coming out of the database (included for completeness)
	 * 
	 * @param  string $value  The value to unescape
	 * @return integer  The integer
	 */
	private function unescapeInteger($value)
	{
		return $value;
	}
	
	
	/**
	 * Unescapes a string coming out of the database (included for completeness)
	 * 
	 * @param  string $value  The value to unescape
	 * @return string  The string
	 */
	private function unescapeString($value)
	{
		return $value;
	}
	
	
	/**
	 * Unescapes a time coming out of the database
	 * 
	 * @param  string $value  The value to unescape
	 * @return string  The time in `HH:MM:SS` format
	 */
	private function unescapeTime($value)
	{
		return date('H:i:s', strtotime($value));
	}
	
	
	/**
	 * Unescapes a timestamp coming out of the database
	 * 
	 * @param  string $value  The value to unescape
	 * @return string  The timestamp in `YYYY-MM-DD HH:MM:SS` format
	 */
	private function unescapeTimestamp($value)
	{
		return date('Y-m-d H:i:s', strtotime($value));
	}
}



/**
 * Copyright (c) 2007-2009 Will Bond <will@flourishlib.com>
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