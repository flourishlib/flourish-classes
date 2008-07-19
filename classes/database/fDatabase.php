<?php
/**
 * Provides a common API for different databases - will automatically use any installed extension
 * 
 * This class is implemented to use the UTF-8 character encoding. Please see
 * {@link http://flourishlib.com/docs/UTF-8} for more information.
 * 
 * The following databases are supported:
 *   - {@link http://microsoft.com/sql/ MSSQL}
 *   - {@link http://mysql.com MySQL}
 *   - {@link http://postgresql.org PostgreSQL}
 *   - {@link http://sqlite.org SQLite}
 * 
 * The class will automatically use the first of the following extensions it finds:
 *   - MSSQL (via ODBC)
 *     - {@link http://php.net/pdo_odbc pdo_odbc}
 *     - {@link http://php.net/odbc odbc}
 *   - MSSQL
 *     - {@link http://msdn.microsoft.com/en-us/library/cc296221.aspx sqlsrv}
 *     - {@link http://php.net/pdo_dblib pdo_dblib}
 *     - {@link http://php.net/mssql mssql} (or {@link http://php.net/sybase sybase})
 *   - MySQL
 *     - {@link http://php.net/mysql mysql}
 *     - {@link http://php.net/mysqli mysqli}
 *     - {@link http://php.net/pdo_mysql pdo_mysql}
 *   - PostgreSQL
 *     - {@link http://php.net/pgsql pgsql}
 *     - {@link http://php.net/pdo_pgsql pdo_pgsql}
 *   - SQLite
 *     - {@link http://php.net/pdo_sqlite pdo_sqlite} (for v3.x)
 *     - {@link http://php.net/sqlite sqlite} (for v2.x)
 * 
 * @copyright  Copyright (c) 2007-2008 William Bond
 * @author     William Bond [wb] <will@flourishlib.com>
 * @license    http://flourishlib.com/license
 * 
 * @package    Flourish
 * @link       http://flourishlib.com/fDatabase
 * 
 * @version    1.0.0b
 * @changes    1.0.0b  The initial implementation [wb, 2007-09-25]
 */
class fDatabase
{
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
	 *   - mssql, mysql, mysqli, odbc, pgsql, sqlite, sqlsrv, pdo
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
	 * The database type (postgresql, mysql, sqlite)
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
	 * Establishes a connection to the database.
	 * 
	 * @param  string  $type      The type of the database: 'mssql', 'mysql', 'postgresql', 'sqlite'
	 * @param  string  $database  Name of the database. If an ODBC connection 'dsn:' concatenated with the DSN, if SQLite the path to the database file.
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
			fCore::toss(
				'fProgrammerException',
				fGrammar::compose(
					'The database type specified, %s, is invalid. Must be one of: %s.',
					fCore::dump($type),
					join(', ', $valid_types)
				)
			);
		}
		
		if (empty($database)) {
			fCore::toss(
				'fProgrammerException',
				fGrammar::compose('No database was specified')
			);
		}
		
		if ($type != 'sqlite') {
			if (empty($username)) {
				fCore::toss(
					'fProgrammerException',
					fGrammar::compose('No username was specified')
				);
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
			
			fCore::toss(
				'fSQLException',
				fGrammar::compose(
					'%s error (%s) in %s',
					$db_type_map[$this->type],
					$message,
					$result->getSQL()
				)
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
			
		if ($this->extension == 'msyqli') {
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
			fCore::toss(
				'fConnectivityException',
				fGrammar::compose(
					'Unable to connect to database'
				)
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
						fCore::toss(
							'fConnectivityException',
							fGrammar::compose(
								'The database specified does not appear to be a valid %s or %s database',
								'SQLite v2.1',
								'v3'
							)
						);
					}
				}
				
				if ((!$sqlite_version || $sqlite_version == 3) && class_exists('PDO', FALSE) && in_array('sqlite', PDO::getAvailableDrivers())) {
					$this->extension = 'pdo';
					
				} elseif ($sqlite_version == 3 && (!class_exists('PDO', FALSE) || !in_array('sqlite', PDO::getAvailableDrivers()))) {
					fCore::toss(
						'fEnvironmentException',
						fGrammar::compose(
							'The database specified is an %s database and the %s extension is not installed',
							'SQLite v3',
							'pdo_sqlite'	
						)
					);
					
				} elseif ($sqlite_version == 2 && extension_loaded('sqlite')) {
					$this->extension = 'sqlite';
					
				} elseif ($sqlite_version == 2 && !extension_loaded('sqlite')) {
					fCore::toss(
						'fEnvironmentException',
						fGrammar::compose(
							'The database specified is an %s database and the %s extension is not installed',
							'SQLite v2.1',
							'sqlite'
						)
					);
					
				} else {
					$type = 'SQLite';
					$exts = 'pdo_sqlite, sqlite';
				}
				break;
		}
		
		if (!$this->extension) {
			fCore::toss(
				'fEnvironmentException',
				fGrammar::compose(
					'The server does not have any of the following extensions for %s support: %s',
					$type,
					$exts
				)
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
	 * {@link fCore::enableErrorHandling()} to log or email these warnings.
	 * 
	 * @param  integer $threshold  The limit of how long an SQL query can take before a warning is triggered
	 * @return void
	 */
	public function enableSlowQueryWarnings($threshold)
	{
		$this->slow_query_threshold = (int) $threshold;
	}
	
	
	/**
	 * Escapes a blob for use in SQL, includes surround quotes when appropriate
	 * 
	 * A NULL value will be returned as 'NULL'
	 * 
	 * @param  string $value  The blob to escape
	 * @return string  The escaped blob
	 */
	public function escapeBlob($value)
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
		} elseif ($this->extension == 'sqlite') {
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
	 * A NULL value will be returned as 'NULL'
	 * 
	 * @param  boolean $value  The boolean to escape
	 * @return string  The database equivalent of the boolean passed
	 */
	public function escapeBoolean($value)
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
	 * A NULL value will be returned as 'NULL'
	 * 
	 * @throws fValidationException
	 * 
	 * @param  string $value  The date to escape
	 * @return string  The escaped date
	 */
	public function escapeDate($value)
	{
		if ($value === NULL) {
			return 'NULL';	
		}
		
		if (!strtotime($value)) {
			fCore::toss(
				'fValidationException',
				fGrammar::compose(
					'The value provided, %s, is not a valid date',
					fCore::dump($value)
				)
			);
		}
		return "'" . date('Y-m-d', strtotime($value)) . "'";
	}
	
	
	/**
	 * Escapes a float for use in SQL
	 * 
	 * A NULL value will be returned as 'NULL'
	 * 
	 * @param  float $value  The float to escape
	 * @return string  The escaped float
	 */
	public function escapeFloat($value)
	{
		if ($value === NULL) {
			return 'NULL';	
		}
		
		return (float) $value;
	}
	
	
	/**
	 * Escapes an integer for use in SQL
	 * 
	 * A NULL value will be returned as 'NULL'
	 * 
	 * @param  integer $value  The integer to escape
	 * @return string  The escaped integer
	 */
	public function escapeInteger($value)
	{
		if ($value === NULL) {
			return 'NULL';	
		}
		
		return (int) $value;
	}
	
	
	/**
	 * Escapes a string for use in SQL, includes surrounding quotes
	 * 
	 * A NULL value will be returned as 'NULL'
	 * 
	 * @param  string $value  The string to escape
	 * @return string  The escaped string
	 */
	public function escapeString($value)
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
			if (preg_match('#[^\x00-\x7F]#')) {
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
				return $output;
			
			// ASCII text is normal
			} else {
				return "'" . str_replace("'", "''", $value) . "'";
			}
		
		} elseif ($this->extension == 'pdo') {
			return $this->connection->quote($value);
		}
	}
	
	
	/**
	 * Escapes a time for use in SQL, includes surrounding quotes
	 * 
	 * A NULL value will be returned as 'NULL'
	 * 
	 * @throws fValidationException
	 * 
	 * @param  string $value  The time to escape
	 * @return string  The escaped time
	 */
	public function escapeTime($value)
	{
		if ($value === NULL) {
			return 'NULL';	
		}
		
		if (!strtotime($value)) {
			fCore::toss(
				'fValidationException',
				fGrammar::compose(
					'The value provided, %s, is not a valid time',
					fCore::dump($value)
				)
			);
		}
		return "'" . date('H:i:s', strtotime($value)) . "'";
	}
	
	
	/**
	 * Escapes a timestamp for use in SQL, includes surrounding quotes
	 * 
	 * A NULL value will be returned as 'NULL'
	 * 
	 * @throws fValidationException
	 * 
	 * @param  string $value  The timestamp to escape
	 * @return string  The escaped timestamp
	 */
	public function escapeTimestamp($value)
	{
		if ($value === NULL) {
			return 'NULL';	
		}
		
		if (!strtotime($value)) {
			fCore::toss(
				'fValidationException',
				fGrammar::compose(
					'The value provided, %s, is not a valid timestamp',
					fCore::dump($value)
				)
			);
		}
		return "'" . date('Y-m-d H:i:s', strtotime($value)) . "'";
	}
	
	
	/**
	 * Executes an SQL query
	 * 
	 * @param  fResult $result  The result object for the query
	 * @return void
	 */
	private function executeQuery(fResult $result)
	{
		if ($this->extension == 'mssql') {
			$result->setResult(@mssql_query($result->getSQL(), $this->connection));
		} elseif ($this->extension == 'mysql') {
			$result->setResult(@mysql_query($result->getSQL(), $this->connection));
		} elseif ($this->extension == 'mysqli') {
			$result->setResult(@mysqli_query($this->connection, $result->getSQL()));
		} elseif ($this->extension == 'odbc') {
			$rows = array();
			$resource = @odbc_exec($this->connection, $result->getSQL());
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
			$result->setResult(@pg_query($this->connection, $result->getSQL()));
		} elseif ($this->extension == 'sqlite') {
			$result->setResult(@sqlite_query($this->connection, $result->getSQL(), SQLITE_ASSOC, $sqlite_error_message));
		} elseif ($this->extension == 'sqlsrv') {
			$rows = array();
			$resource = @sqlsrv_query($this->connection, $result->getSQL());
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
	private function executeUnbufferedQuery(fUnbufferedResult $result)
	{
		if ($this->extension == 'mssql') {
			$result->setResult(@mssql_query($result->getSQL(), $this->connection, 20));
		} elseif ($this->extension == 'mysql') {
			$result->setResult(@mysql_unbuffered_query($result->getSQL(), $this->connection));
		} elseif ($this->extension == 'mysqli') {
			$result->setResult(@mysqli_query($this->connection, $result->getSQL(), MYSQLI_USE_RESULT));
		} elseif ($this->extension == 'odbc') {
			$result->setResult(@odbc_exec($this->connection, $result->getSQL()));
		} elseif ($this->extension == 'pgsql') {
			$result->setResult(@pg_query($this->connection, $result->getSQL()));
		} elseif ($this->extension == 'sqlite') {
			$result->setResult(@sqlite_unbuffered_query($this->connection, $result->getSQL(), SQLITE_ASSOC, $sqlite_error_message));
		} elseif ($this->extension == 'sqlsrv') {
			$result->setResult(@sqlsrv_query($this->connection, $result->getSQL()));
		} elseif ($this->extension == 'pdo') {
			$result->setResult($this->connection->query($result->getSQL()));
		}
		
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
		preg_match_all("#(?:'(?:''|\\\\'|\\\\[^']|[^'\\\\])*')|(?:[^']+)#", $sql, $matches);
		
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
	 * Gets the php extension being used (mssql, mysql, mysqli, pgsql, sqlite, or pdo)
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
	 * @return string  The database type (mssql, mysql, pgsql or sqlite)
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
	private function handleAutoIncrementedValue(fResult $result)
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
			
			$insert_id_res = @pg_query($this->connection, "SELECT lastval()");
			
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
							$this->connection->query("ROLLBACK TO get_last_val");
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
	 * @return mixed  If the connection is not via PDO will return FALSE, otherwise an object of the type $result_class
	 */
	private function handleTransactionQueries($sql, $result_class)
	{
		if (!is_object($this->connection)) {
			return FALSE;
		}
		
		$success = FALSE;
		
		try {
			if (preg_match('#^\s*(begin|start)(\s+transaction)?\s*$#i', $sql)) {
				$this->connection->beginTransaction();
				$success = TRUE;
			}
			if (preg_match('#^\s*(commit)(\s+transaction)?\s*$#i', $sql)) {
				$this->connection->commit();
				$success = TRUE;
			}
			if (preg_match('#^\s*(rollback)(\s+transaction)?\s*$#i', $sql)) {
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
			
			fCore::toss(
				'fSQLException',
				fGrammar::compose(
					'%s error (%s) in %s',
					$db_type_map[$this->type],
					$e->getMessage(),
					$sql
				)
			);
		}
		
		if ($success) {
			$result = new $result_class($this->extension);
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
	 * Executes one or more sql queries
	 * 
	 * @param  string $sql  One or more SQL statements
	 * @return fResult|array  The fResult object(s) for the query
	 */
	public function query($sql)
	{
		$this->connectToDatabase();
		
		// Ensure an SQL statement was passed
		if (empty($sql)) {
			fCore::toss(
				'fProgrammerException',
				fGrammar::compose('No SQL statement passed')
			);
		}
		
		// Split multiple queries
		if (strpos($sql, ';') !== FALSE) {
			$sql_queries = $this->explodeQueries($sql);
			$sql = array_shift($sql_queries);
		}
		
		$start_time = microtime(TRUE);
		
		$this->trackTransactions($sql);
		if (!$result = $this->handleTransactionQueries($sql, 'fResult')) {
			$result = new fResult($this->extension);
			$result->setSQL($sql);
			
			$this->executeQuery($result);
		}
		
		// Write some debugging info
		$query_time = microtime(TRUE) - $start_time;
		$this->query_time += $query_time;
		fCore::debug(
			fGrammar::compose(
				'Query time was %s seconds for:%s',
				$query_time,
				"\n" . $result->getSQL()
			),
			$this->debug
		);
		
		if ($this->slow_query_threshold && $query_time > $this->slow_query_threshold) {
			fCore::trigger(
				'warning',
				fGrammar::compose(
					'The following query took %s milliseconds, which is above the slow query threshold of %s:%s',
					$query_time,
					$this->slow_query_threshold,
					"\n" . $result->getSQL()
				)
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
	 * @param  mixed   $resource  Only applicable for pdo, odbc and sqlsrv extentions, this is either the PDOStatement object or odbc or sqlsrv resource
	 * @return void
	 */
	private function setAffectedRows(fResult $result, $resource=NULL)
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
	private function setReturnedRows(fResult $result)
	{
		if (is_resource($result->getResult())) {
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
		if (preg_match('#^\s*(begin|start)(\s+transaction)?\s*$#i', $sql)) {
			if ($this->inside_transaction) {
				fCore::toss(
					'fProgrammerException',
					fGrammar::compose('A transaction is already in progress')
				);
			}
			$this->inside_transaction = TRUE;
			
		} elseif (preg_match('#^\s*(commit)(\s+transaction)?\s*$#i', $sql)) {
			if (!$this->inside_transaction) {
				fCore::toss(
					'fProgrammerException',
					fGrammar::compose('There is no transaction in progress')
				);
			}
			$this->inside_transaction = FALSE;
			
		} elseif (preg_match('#^\s*(rollback)(\s+transaction)?\s*$#i', $sql)) {
			if (!$this->inside_transaction) {
				fCore::toss(
					'fProgrammerException',
					fGrammar::compose('There is no transaction in progress')
				);
			}
			$this->inside_transaction = FALSE;
		}
	}
	
	
	/**
	 * Translates the SQL statement using fSQLTranslation and executes it
	 * 
	 * @param  string $sql  One or more SQL statements
	 * @return fResult|array  The fResult object(s) for the query
	 */
	public function translatedQuery($sql)
	{
		if (!$this->translation) {
			$this->connectToDatabase();
			$this->translation = new fSQLTranslation($this->connection, $this->type, $this->extension);
			$this->translation->enableDebugging($this->debug);
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
	 * @param  string $sql  A single SQL statement
	 * @return fUnbufferedResult  The result object for the unbuffered query
	 */
	public function unbufferedQuery($sql)
	{
		$this->connectToDatabase();
		
		// Ensure an SQL statement was passed
		if (empty($sql)) {
			fCore::toss(
				'fProgrammerException',
				fGrammar::compose('No SQL statement passed')
			);
		}
		
		if ($this->unbuffered_result) {
			$this->unbuffered_result->__destruct();
		}
		
		$start_time = microtime(TRUE);
		
		$this->trackTransactions($sql);
		if (!$result = $this->handleTransactionQueries($sql, 'fUnbufferedRequest')) {
			$result = new fUnbufferedResult($this->extension);
			$result->setSQL($sql);
			
			$this->executeUnbufferedQuery($result);
		}
		
		// Write some debugging info
		$query_time = microtime(TRUE) - $start_time;
		$this->query_time += $query_time;
		fCore::debug(
			fGrammar::compose(
				'Query time was %s seconds for (unbuffered):%s',
				$query_time,
				"\n" . $result->getSQL()
			),
			$this->debug
		);
		
		if ($this->slow_query_threshold && $query_time > $this->slow_query_threshold) {
			fCore::trigger(
				'warning',
				fGrammar::compose(
					'The following query took %s milliseconds, which is above the slow query threshold of %s:%s',
					$query_time,
					$this->slow_query_threshold,
					"\n" . $result->getSQL()
				)
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
	 * @param  string $sql  A single SQL statement
	 * @return fUnbufferedResult  The result object for the unbuffered query
	 */
	public function unbufferedTranslatedQuery($sql)
	{
		if (!$this->translation) {
			$this->connectToDatabase();
			$this->translation = new fSQLTranslation($this->connection, $this->type, $this->extension);
		}
		$result = $this->unbufferedQuery($this->translation->translate($sql));
		$result->setUntranslatedSQL($sql);
		return $result;
	}
	
	
	/**
	 * Unescapes a blob coming out of the database
	 * 
	 * @param  string $value  The value to unescape
	 * @return binary  The binary data
	 */
	public function unescapeBlob($value)
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
	public function unescapeBoolean($value)
	{
		return ($value == 'f' || !$value) ? FALSE : TRUE;
	}
	
	
	/**
	 * Unescapes a date coming out of the database
	 * 
	 * @param  string $value  The value to unescape
	 * @return string  The date in YYYY-MM-DD format
	 */
	public function unescapeDate($value)
	{
		return date('Y-m-d', strtotime($value));
	}
	
	
	/**
	 * Unescapes a float coming out of the database (included for completeness)
	 * 
	 * @param  string $value  The value to unescape
	 * @return float  The float
	 */
	public function unescapeFloat($value)
	{
		return (float) $value;
	}
	
	
	/**
	 * Unescapes an integer coming out of the database (included for completeness)
	 * 
	 * @param  string $value  The value to unescape
	 * @return integer  The integer
	 */
	public function unescapeInteger($value)
	{
		return (integer) $value;
	}
	
	
	/**
	 * Unescapes a string coming out of the database (included for completeness)
	 * 
	 * @param  string $value  The value to unescape
	 * @return string  The string
	 */
	public function unescapeString($value)
	{
		return $value;
	}
	
	
	/**
	 * Unescapes a time coming out of the database
	 * 
	 * @param  string $value  The value to unescape
	 * @return string  The time in HH:MM:SS format
	 */
	public function unescapeTime($value)
	{
		return date('H:i:s', strtotime($value));
	}
	
	
	/**
	 * Unescapes a timestamp coming out of the database
	 * 
	 * @param  string $value  The value to unescape
	 * @return string  The timestamp in YYYY-MM-DD HH:MM:SS format
	 */
	public function unescapeTimestamp($value)
	{
		return date('Y-m-d H:i:s', strtotime($value));
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