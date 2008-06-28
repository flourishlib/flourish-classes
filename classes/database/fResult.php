<?php
/**
 * Representation of a result from a query against the {@link fDatabase} class
 * 
 * @copyright  Copyright (c) 2007-2008 William Bond
 * @author     William Bond [wb] <will@flourishlib.com>
 * @license    http://flourishlib.com/license
 * 
 * @package    Flourish
 * @link       http://flourishlib.com/fResult
 * 
 * @version    1.0.0b
 * @changes    1.0.0b  The initial implementation [wb, 2007-09-25]
 */
class fResult implements Iterator
{
	/**
	 * The number of rows affected by an insert, update, delete, etc
	 * 
	 * @var integer
	 */
	private $affected_rows = 0;
	
	/**
	 * The auto incremented value from the query
	 * 
	 * @var integer
	 */
	private $auto_incremented_value = NULL;
	
	/**
	 * The current row of the result set
	 * 
	 * @var array
	 */
	private $current_row = NULL;
	
	/**
	 * The php extension used for database interaction
	 * 
	 * @var string
	 */
	private $extension = NULL;
	
	/**
	 * The position of the pointer in the result set
	 * 
	 * @var integer
	 */
	private $pointer;
	
	/**
	 * The result resource or array
	 * 
	 * @var mixed
	 */
	private $result = NULL;
	
	/**
	 * The number of rows returned by a select
	 * 
	 * @var integer
	 */
	private $returned_rows = 0;
	
	/**
	 * The sql query
	 * 
	 * @var string
	 */
	private $sql = '';
	
	/**
	 * The sql from before translation
	 * 
	 * @var string
	 */
	private $untranslated_sql = NULL;
	
	
	/**
	 * Sets the PHP extension the query occured through
	 * 
	 * @internal
	 * 
	 * @param  string $extension  The database extension used (valid: 'array', 'mssql', 'mysql', 'mysqli', 'pgsql', 'sqlite')
	 * @return fResult
	 */
	public function __construct($extension)
	{
		// Certain extensions don't offer a buffered query, so it is emulated using an array
		if (in_array($extension, array('odbc', 'pdo', 'sqlsrv'))) {
			$extension = 'array';	
		}
		
		$valid_extensions = array('array', 'mssql', 'mysql', 'mysqli', 'pgsql', 'sqlite');
		if (!in_array($extension, $valid_extensions)) {
			fCore::toss('fProgrammerException', 'Invalid database extension, ' . $extension . ', selected. Must be one of: ' . join(', ', $valid_extensions) . '.');
		}
		$this->extension = $extension;
	}
	
	
	/**
	 * Frees up the result object to save memory
	 * 
	 * @internal
	 * 
	 * @return void
	 */
	public function __destruct()
	{
		if (!is_resource($this->result) && !is_object($this->result)) {
			return;
		}
		
		if ($this->extension == 'mssql') {
			mssql_free_result($this->result);
		} elseif ($this->extension == 'mysql') {
			mysql_free_result($this->result);
		} elseif ($this->extension == 'mysqli') {
			mysqli_free_result($this->result);
		} elseif ($this->extension == 'pgsql') {
			pg_free_result($this->result);
		} elseif ($this->extension == 'sqlite') {
			// Sqlite doesn't have a way to free a result
		}
	}
	
	
	/**
	 * Gets the next row from the result and assigns it to the current row
	 * 
	 * @return void
	 */
	private function advanceCurrentRow()
	{
		if ($this->extension == 'mssql') {
			$row = mssql_fetch_assoc($this->result);
			$row = $this->fixDblibMssqlDriver($row);
			
			// This is an unfortunate fix that required for databases that don't support limit
			// clauses with an offset. It prevents unrequested columns from being returned.
			if ($this->untranslated_sql !== NULL && isset($row['__flourish_limit_offset_row_num'])) {
				unset($row['__flourish_limit_offset_row_num']);
			}
				
		} elseif ($this->extension == 'mysql') {
			$row = mysql_fetch_assoc($this->result);
		} elseif ($this->extension == 'mysqli') {
			$row = mysqli_fetch_assoc($this->result);
		} elseif ($this->extension == 'pgsql') {
			$row = pg_fetch_assoc($this->result);
		} elseif ($this->extension == 'sqlite') {
			$row = sqlite_fetch_array($this->result, SQLITE_ASSOC);
		} elseif ($this->extension == 'array') {
			$row = $this->result[$this->pointer];
		}
		
		$this->current_row = $row;
	}
	
	
	/**
	 * Returns if there are any remaining rows
	 * 
	 * @return boolean  If there are remaining rows in the result
	 */
	public function areRemainingRows()
	{
		return $this->valid();
	}
	
	
	/**
	 * Returns the current row in the result set (required by iterator interface)
	 * 
	 * @throws  fNoResultsException
	 * @throws  fNoRemainingException
	 * @internal
	 * 
	 * @return array  The current Row
	 */
	public function current()
	{
		if(!$this->returned_rows) {
			fCore::toss('fNoResultsException', 'The query specified did not return any rows');
		}
		
		if (!$this->valid()) {
			fCore::toss('fNoRemainingException', 'There are no remaining rows');
		}
		
		// Primes the result set
		if ($this->pointer === NULL) {
			$this->pointer = 0;
			$this->advanceCurrentRow();
		}
		
		return $this->current_row;
	}
	
	
	/**
	 * Returns all of the rows from the result set
	 * 
	 * @return array  The array of rows
	 */
	public function fetchAllRows()
	{
		if (!empty($this->pointer)) {
			fCore::toss('fProgrammerException', 'It is not possible to fetch all rows from a result if at least one row has already been returned');
		}
		
		$all_rows = array();
		foreach ($this as $row) {
			$all_rows[] = $row;
		}
		return $all_rows;
	}
	
	
	/**
	 * Returns the row next row in the result set (where the pointer is currently assigned to)
	 * 
	 * @throws  fNoResultsException
	 * @throws  fNoRemainingException
	 * 
	 * @return array|false  The associative array of the row or FALSE if no remaining records
	 */
	public function fetchRow()
	{
		$row = $this->current();
		$this->next();
		return $row;
	}
	
	
	/**
	 * Wraps around {@link fetchRow()} and returns the first field from the row instead of the whole row.
	 * 
	 * @throws  fNoResultsException
	 * @throws  fNoRemainingException
	 * 
	 * @return string|number  The first scalar value from {@link fetchRow()}
	 */
	public function fetchScalar()
	{
		$row = $this->fetchRow();
		return array_shift($row);
	}
	
	
	/**
	 * Warns the user about bugs in the dblib driver for mssql, fixes some bugs
	 * 
	 * @param  array $row  The row from the database
	 * @return array  The fixed row
	 */
	private function fixDblibMssqlDriver($row)
	{
		static $using_dblib = NULL;
		
		if ($using_dblib === NULL) {
		
			// If it is not a windows box we are definitely not using dblib
			if (fCore::getOS() != 'windows') {
				$using_dblib = FALSE;
			
			// Check this windows box for dblib
			} else {
				ob_start();
				phpinfo(INFO_MODULES);
				$module_info = ob_get_contents();
				ob_end_clean();
				
				$using_dblib = preg_match('#<a name="module_mssql">mssql</a>.*?Library version </td><td class="v">\s*FreeTDS\s*</td>#ims', $module_info, $match);
			}
		}
		
		if (!$using_dblib) {
			return $row;
		}
		
		foreach ($row as $key => $value) {
			if ($value == ' ') {
				$row[$key] = '';
				fCore::trigger('notice', 'The fResult class changed a single space coming out of the database into an empty string, see <a href="http://bugs.php.net/bug.php?id=26315">http://bugs.php.net/bug.php?id=26315</a>');
			}
			if (strlen($key) == 30) {
				fCore::trigger('notice', 'The fResult class detected a column name exactly 30 characters in length coming out of the database. This column name may be truncated, see <a href="http://bugs.php.net/bug.php?id=23990">http://bugs.php.net/bug.php?id=23990</a>');
			}
			if (strlen($value) == 256) {
				fCore::trigger('notice', 'The fResult class detected a value exactly 255 characters in length coming out of the database. This value may be truncated, see <a href="http://bugs.php.net/bug.php?id=37757">http://bugs.php.net/bug.php?id=37757</a>');
			}
		}
		
		return $row;
	}
	
	
	/**
	 * Returns the number of rows affected by the query
	 * 
	 * @return integer  The number of rows affected by the query
	 */
	public function getAffectedRows()
	{
		return $this->affected_rows;
	}
	
	
	/**
	 * Returns the last auto incremented value for this database connection. This may or may not be from the current query.
	 * 
	 * @return integer  The auto incremented value
	 */
	public function getAutoIncrementedValue()
	{
		return $this->auto_incremented_value;
	}
	
	
	/**
	 * Returns the current position of the pointer in the result set
	 * 
	 * @return integer  The current position of the pointer in the result set
	 */
	public function getPointer()
	{
		return $this->pointer;
	}
	
	
	/**
	 * Returns the result
	 * 
	 * @return mixed  The result of the query
	 */
	public function getResult()
	{
		return $this->result;
	}
	
	
	/**
	 * Returns the number of rows returned by the query
	 * 
	 * @return integer  The number of rows returned by the query
	 */
	public function getReturnedRows()
	{
		return $this->returned_rows;
	}
	
	
	/**
	 * Returns the SQL used in the query
	 * 
	 * @return string  The SQL used in the query
	 */
	public function getSQL()
	{
		return $this->sql;
	}
	
	
	/**
	 * Returns the SQL as it was before translation
	 * 
	 * @return string  The SQL from before translation
	 */
	public function getUntranslatedSQL()
	{
		return $this->untranslated_sql;
	}
	
	
	/**
	 * Returns the current row number (required by iterator interface)
	 * 
	 * @throws fNoResultsException
	 * @internal
	 * 
	 * @return integer  The current row number
	 */
	public function key()
	{
		if ($this->pointer === NULL) {
			$this->current();
		}
		
		return $this->pointer;
	}
	
	
	/**
	 * Advances to the next row in the result (required by iterator interface)
	 * 
	 * @throws fNoResultsException
	 * @internal
	 * 
	 * @return array|null  The next row or null
	 */
	public function next()
	{
		if ($this->pointer === NULL) {
			$this->current();
		}
		
		$this->pointer++;
		
		if ($this->valid()) {
			$this->advanceCurrentRow();
		} else {
			$this->current_row = NULL;
		}
	}
	
	
	/**
	 * Rewinds the query (required by iterator interface)
	 * 
	 * @internal
	 * 
	 * @return void
	 */
	public function rewind()
	{
		try {
			$this->seek(0);
		} catch (Exception $e) { }
	}
	
	
	/** 
	 * Seeks to the specified zero-based row for the specified SQL query
	 * 
	 * @throws  fNoResultsException
	 * 
	 * @param  integer $row  The row number to seek to (zero-based)
	 * @return void
	 */
	public function seek($row)
	{
		if(!$this->returned_rows) {
			fCore::toss('fNoResultsException', 'The query specified did not return any rows');
		}
		
		if ($row >= $this->returned_rows || $row < 0) {
			fCore::toss('fProgrammerException', 'The row requested does not exist');
		}
		
		$this->pointer = $row;
					
		if ($this->extension == 'mssql') {
			$success = mssql_data_seek($this->result, $row);
		} elseif ($this->extension == 'mysql') {
			$success = mysql_data_seek($this->result, $row);
		} elseif ($this->extension == 'mysqli') {
			$success = mysqli_data_seek($this->result, $row);
		} elseif ($this->extension == 'pgsql') {
			$success = pg_result_seek($this->result, $row);
		} elseif ($this->extension == 'sqlite') {
			$success = sqlite_seek($this->result, $row);
		} elseif ($this->extension == 'array') {
			// Do nothing since we already changed the pointer
			$success = TRUE;
		}
		
		if (!$success) {
			fCore::toss('fSQLException', 'There was an error seeking to result ' . $row);
		}
		
		$this->advanceCurrentRow();
	}
	
	
	/**
	 * Sets the number of affected rows
	 * 
	 * @internal
	 * 
	 * @param  integer $affected_rows  The number of affected rows
	 * @return void
	 */
	public function setAffectedRows($affected_rows)
	{
		$this->affected_rows = (int) $affected_rows;
	}
	
	
	/**
	 * Sets the auto incremented value
	 * 
	 * @internal
	 * 
	 * @param  integer $auto_incremented_value  The auto incremented value
	 * @return void
	 */
	public function setAutoIncrementedValue($auto_incremented_value)
	{
		$this->auto_incremented_value = ($auto_incremented_value == 0) ? NULL : $auto_incremented_value;
	}
	
	
	/**
	 * Sets the result from the query
	 * 
	 * @internal
	 * 
	 * @param  mixed $result  The result from the query
	 * @return void
	 */
	public function setResult($result)
	{
		$this->result = $result;
	}
	
	
	/**
	 * Sets the number of rows returned
	 * 
	 * @internal
	 * 
	 * @param  integer $returned_rows  The number of rows returned
	 * @return void
	 */
	public function setReturnedRows($returned_rows)
	{
		$this->returned_rows = (int) $returned_rows;
		if ($this->returned_rows) {
			$this->affected_rows = 0;
		}
	}
	
	
	/**
	 * Sets the SQL used in the query
	 * 
	 * @internal
	 * 
	 * @param  string $sql  The SQL used in the query
	 * @return void
	 */
	public function setSQL($sql)
	{
		$this->sql = $sql;
	}
	
	
	/**
	 * Sets the SQL from before translation
	 * 
	 * @internal
	 * 
	 * @param  string $untranslated_sql  The SQL from before translation
	 * @return void
	 */
	public function setUntranslatedSQL($untranslated_sql)
	{
		$this->untranslated_sql = $untranslated_sql;
	}
	
	
	/**
	 * Throws an {@link fNoResultException} if the query did not return any rows
	 * 
	 * @throws  fNoResultsException
	 * 
	 * @return void
	 */
	public function tossIfNoResults()
	{
		if (empty($this->returned_rows) && empty($this->affected_rows)) {
			fCore::toss('fNoResultsException', 'No rows were return or affected by the query');
		}
	}
	
	
	/**
	 * Returns if the query has any rows left (required by iterator interface)
	 * 
	 * @internal
	 * 
	 * @return boolean  If the iterator is still valid
	 */
	public function valid()
	{
		if (!$this->returned_rows) {
			return FALSE;
		}
		
		if ($this->pointer === NULL) {
			return TRUE;
		}
		
		return ($this->pointer < $this->returned_rows);
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