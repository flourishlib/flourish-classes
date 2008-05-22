<?php
/**
 * Representation of an unbuffered result from a query against the fDatabase class
 * 
 * @copyright  Copyright (c) 2007-2008 William Bond
 * @author     William Bond [wb] <will@flourishlib.com>
 * @license    http://flourishlib.com/license
 * 
 * @link  http://flourishlib.com/fUnbufferedResult
 * 
 * @version  1.0.0
 * @changes  1.0.0    The initial implementation [wb, 2008-05-07]
 */
class fUnbufferedResult implements Iterator
{
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
	 * The result resource
	 * 
	 * @var resource
	 */
	private $result = NULL;
	
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
	 * @param  string $extension  The database extension used (valid: 'mssql', 'mysql', 'mysqli', 'pgsql', 'sqlite', 'pdo')
	 * @return fUnbufferedResult
	 */
	public function __construct($extension)
	{
		$valid_extensions = array('mssql', 'mysql', 'mysqli', 'pgsql', 'sqlite', 'pdo');
		if (!in_array($extension, $valid_extensions)) {
			fCore::toss('fProgrammerException', 'Invalid database extension, ' . $extension . ', selected. Must be one of: ' . join(', ', $valid_extensions) . '.');
		}
		$this->extension = $extension;
	}
	
	
	/**
	 * Frees up the result object
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
			sqlite_fetch_all($this->result);
		} elseif ($this->extension == 'pdo') {
			$this->result->closeCursor();
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
			if (empty($row)) {
				mssql_fetch_batch($this->result);
				$row = mssql_fetch_assoc($this->result);
			}
			if (!empty($row)) {
				$row = $this->fixDblibMssqlDriver($row);
				
				// This is an unfortunate fix that required for databases that don't support limit
				// clauses with an offset. It prevents unrequested columns from being returned.
				if (!empty($row) && $this->untranslated_sql !== NULL && isset($row['__flourish_limit_offset_row_num'])) {
					unset($row['__flourish_limit_offset_row_num']);
				}
			}
				
		} elseif ($this->extension == 'mysql') {
			$row = mysql_fetch_assoc($this->result);
		} elseif ($this->extension == 'mysqli') {
			$row = mysqli_fetch_assoc($this->result);
		} elseif ($this->extension == 'pgsql') {
			$row = pg_fetch_assoc($this->result);
		} elseif ($this->extension == 'sqlite') {
			$row = sqlite_fetch_array($this->result, SQLITE_ASSOC);
		} elseif ($this->extension == 'pdo') {
			$row = $this->result->fetch(PDO::FETCH_ASSOC);
		}
		
		$this->current_row = $row;
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
		// Primes the result set
		if ($this->pointer === NULL) {
			$this->pointer = 0;
			$this->advanceCurrentRow();
		}
		
		if(!$this->current_row && $this->pointer == 0) {
			fCore::toss('fNoResultsException', 'The query specified did not return any rows');
		} elseif (!$this->current_row) {
			fCore::toss('fNoRemainingException', 'There are no remaining rows');
		}
		
		return $this->current_row;
	}
	
	
	/**
	 * Returns the row next row in the result set (where the pointer is currently assigned to)
	 * 
	 * @throws  fNoResultsException
	 * @throws  fNoRemainingException
	 * 
	 * @return array|false  The associative array of the row or FALSE if no remaining rows
	 */
	public function fetchRow()
	{
		$row = $this->current();
		$this->next();
		return $row;
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
	 * Returns the result
	 * 
	 * @return mixed  The result of the query
	 */
	public function getResult()
	{
		return $this->result;
	}
	
	
	/**
	 * Returns the sql used in the query
	 * 
	 * @return string  The sql used in the query
	 */
	public function getSQL()
	{
		return $this->sql;
	}
	
	
	/**
	 * Returns the sql as it was before translation
	 * 
	 * @return string  The sql from before translation
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
		
		$this->advanceCurrentRow();
		$this->pointer++;
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
		if (!empty($this->pointer)) {
			fCore::toss('fProgrammerException', 'Database results can not be iterated through multiple times');
		}
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
	 * Sets the sql used in the query
	 * 
	 * @internal
	 * 
	 * @param  string $sql  The sql used in the query
	 * @return void
	 */
	public function setSQL($sql)
	{
		$this->sql = $sql;
	}
	
	
	/**
	 * Sets the sql from before translation
	 * 
	 * @internal
	 * 
	 * @param  string $untranslated_sql  The sql from before translation
	 * @return void
	 */
	public function setUntranslatedSQL($untranslated_sql)
	{
		$this->untranslated_sql = $untranslated_sql;
	}
	
	
	/**
	 * Throws an fNoResultException if the fResult did not return or affect any rows
	 * 
	 * @throws  fNoResultsException
	 * 
	 * @return void
	 */
	public function tossIfNoResults()
	{
		$this->current();
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
		if (!$this->return_rows) {
			return FALSE;
		}
		
		if ($this->pointer === NULL) {
			$this->advanceCurrentRow();
			$this->pointer = 0;
		}
		
		return !empty($this->current_row);
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