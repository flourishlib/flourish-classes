<?php
/**
 * Provides functionality for CRUD pages
 * 
 * CRUD stands for Create, Read, Update and Delete - the basic functionality of
 * almost all web applications.
 * 
 * @copyright  Copyright (c) 2007-2008 William Bond
 * @author     William Bond [wb] <will@flourishlib.com>
 * @license    http://flourishlib.com/license
 * 
 * @link  http://flourishlib.com/fCRUD
 * 
 * @uses  fHTML
 * @uses  fInflection
 * @uses  fPrintableException
 * @uses  fRequest
 * @uses  fSession
 * @uses  fURL
 * 
 * @version  1.0.0
 * @changes  1.0.0    The initial implementation [wb, 2007-06-14]
 */
class fCRUD
{
	/**
	 * The current row number for alternating rows
	 * 
	 * @var integer 
	 */
	static private $row_number = 1;
	
	/**
	 * The column to sort by
	 * 
	 * @var string 
	 */
	static private $sort_column = NULL;
	
	/**
	 * The direction to sort
	 * 
	 * @var string 
	 */
	static private $sort_direction = NULL;
	
	/**
	 * The values for a search form
	 * 
	 * @var array 
	 */
	static private $search_values = array();
	
	/**
	 * Any values that were loaded from the session, used for redirection
	 * 
	 * @var array 
	 */
	static private $loaded_values = array();
   
   
	/**
	 * Prevent instantiation
	 * 
	 * @return fCRUD
	 */
	private function __construct() { }
	
	
	/**
	 * Removes list items from an fPrintableException based on their contents
	 * 
	 * @param  fPrintableException $e           The exception to remove field names from
	 * @param  string              $filter,...  If this content is found in a list item, the list item will be removed
	 * @return void
	 */
	static public function removeListItems(fPrintableException $e, $filter)
	{
		$filters = array_slice(func_get_args(), 1);
		$message = $e->getMessage();
		
		// If we can't find a list, don't bother continuing
		if (!preg_match('#^(.*<(?:ul|ol)[^>]*?>)(.*?)(</(?:ul|ol)>.*)$#i', $message, $matches)) {
			return;	
		}
		
		$beginning   = $matches[1];
		$list_items  = $matches[2];
		$ending      = $matches[3];

		preg_match_all('#<li(.*?)</li>#i', $list_items, $matches, PREG_SET_ORDER);
		
		$new_list_items = array();
		foreach ($matches as $match) {
			foreach ($filters as $filter) {
				if (strpos($match[1], $filter) !== FALSE) {
					continue 2;
				}
			}
			
			$new_list_items[] = $match[0];			
		}
		
		$e->setMessage($beginning . join("\n", $new_list_items) . $ending);
	}
	
	
	/**
	 * Reorders list items in an fPrintableException based on their contents
	 * 
	 * @param  fPrintableException $e           The exception to remove field names from
	 * @param  string              $filter,...  If this content is found in a list item, the list item will be displayed in the order it appears in this parameter list
	 * @return void
	 */
	static public function reorderListItems(fPrintableException $e, $filter)
	{
		$filters = array_slice(func_get_args(), 1);
		$message = $e->getMessage();
		
		// If we can't find a list, don't bother continuing
		if (!preg_match('#^(.*<(?:ul|ol)[^>]*?>)(.*?)(</(?:ul|ol)>.*)$#i', $message, $matches)) {
			return;	
		}
		
		$beginning   = $matches[1];
		$list_items  = $matches[2];
		$ending      = $matches[3];

		preg_match_all('#<li(.*?)</li>#i', $list_items, $matches, PREG_SET_ORDER);
		
		$ordered_items = array_fill(0, sizeof($filters), array());
		$other_items   = array();
		
		foreach ($matches as $match) {
			foreach ($filters as $num => $filter) {
				if (strpos($match[1], $filter) !== FALSE) {
					$ordered_items[$num][] = $match[0];
					continue 2;
				}
			}
			
			$other_items[] = $match[0];			
		}
		
		$list_items = array();
		foreach ($ordered_items as $ordered_item) {
			$list_items = array_merge($list_items, $ordered_item);	
		}
		$list_items = array_merge($list_items, $other_items);
		
		$e->setMessage($beginning . join("\n", $list_items) . $ending);
	}
	
	
	/**
	 * Checks to see if any values (search or sort) were loaded from the session, and if so redirects the user to the current URL with those values added
	 * 
	 * @return void
	 */
	static public function redirectWithLoadedValues()
	{
		$query_string = fURL::replaceInQueryString(array_keys(self::$loaded_values), array_values(self::$loaded_values));
		$url = fURL::get() . $query_string;
		fURL::redirect($url);	
	}
	
	
	/**
	 * Gets the current value of a search field 
	 * 
	 * @param  string $column   The column that is being pulled back
	 * @param  string $cast_to  The data type to cast to
	 * @param  string $default  The default value
	 * @return string  The current value
	 */
	static public function getSearchValue($column, $cast_to=NULL, $default=NULL)
	{
		if (self::getPreviousSearchValue($column) && fRequest::get($column) === NULL) {
			self::$search_values[$column] = self::getPreviousSearchValue($column);	
			self::$loaded_values[$column] = self::$search_values[$column];
		} else {
			self::$search_values[$column] = fRequest::get($column, $cast_to, $default);
			self::setPreviousSearchValue($column, self::$search_values[$column]);
		}
		return self::$search_values[$column];	
	}
	
	
	/**
	 * Gets the current column to sort by, defaults to first
	 * 
	 * @param  array $possible_columns  The columns that can be sorted by, defaults to first
	 * @return string  The column to sort by
	 */
	static public function getSortColumn($possible_columns)
	{
		if (self::getPreviousSortColumn() && fRequest::get('sort') === NULL) {
			self::$sort_column = self::getPreviousSortColumn();	
			self::$loaded_values['sort'] = self::$sort_column;
		} else {
			self::$sort_column = fRequest::getFromArray('sort', $possible_columns);
			self::setPreviousSortColumn(self::$sort_column);
		}
		return self::$sort_column;	
	}
	
	
	/**
	 * Gets the current sort direction
	 * 
	 * @param  string $default_direction  The default direction, 'asc' or 'desc'
	 * @return string  The direction, 'asc', or 'desc'
	 */
	static public function getSortDirection($default_direction)
	{
		if (self::getPreviousSortDirection() && fRequest::get('dir') === NULL) {
			self::$sort_direction = self::getPreviousSortDirection();	
			self::$loaded_values['dir'] = self::$sort_direction;
		} else {
			self::$sort_direction = fRequest::getFromArray('dir', array($default_direction, ($default_direction == 'asc') ? 'desc' : 'asc'));
			self::setPreviousSortDirection(self::$sort_direction);
		}
		return self::$sort_direction;	
	}
	
	
	/**
	 * Prints a sortable column header 
	 * 
	 * @param  string $column       The column to create the sortable header for
	 * @param  string $column_name  This will override the humanized version of the column
	 * @return void
	 */
	static public function createSortableColumn($column, $column_name=NULL)
	{
		if ($column_name === NULL) {
			$column_name = fInflection::humanize($column);	
		}

		if (self::$sort_column == $column) {
			$sort      = $column;
			$direction = (self::$sort_direction == 'asc') ? 'desc' : 'asc';
		} else {
			$sort      = $column;
			$direction = 'asc';	
		}
		
		$columns = array_merge(array('sort', 'dir'), array_keys(self::$search_values));
		$values  = array_merge(array($sort, $direction), array_values(self::$search_values));
		?><a href="<?= fHTML::preparePlainText(fURL::get() . fURL::replaceInQueryString($columns, $values)) ?>" class="sortable_column<?= (self::$sort_column == $column) ? ' ' . self::$sort_direction : '' ?>"><?= fHTML::encodeEntities($column_name) ?></a><?	
	}
	
	
	/**
	 * Prints a class attribute for a td if that td is part of the sorted column 
	 * 
	 * @param  string $column   The column this td is part of
	 * @return void
	 */
	static public function highlightSortedColumn($column)
	{
		if (self::$sort_column == $column) {
			echo ' class="sorted"';
		}	
	}
	
	
	/**
	 * Returns a css class name for a row. Will return even, odd, or highlighted if the two parameters are equal and added or updated is true
	 * 
	 * @param  mixed $row_value       The value from the row
	 * @param  mixed $affected_value  The value that was just added or updated
	 * @return string  The css class
	 */
	static public function getRowClass($row_value, $affected_value)
	{
		if ($row_value == $affected_value) {
			 self::$row_number++;
			 return 'highlighted';
		}
			
		$class = (self::$row_number++ % 2) ? 'odd' : 'even';
		$class .= (self::$row_number == 2) ? ' first' : '';
		return $class;
	}
	
	
	/**
	 * Return the previous sort direction, if one exists
	 * 
	 * @return string  The previous sort direction
	 */
	static private function getPreviousSortDirection()
	{
		return fSession::get(fURL::get() . '::previous_sort_direction', NULL, 'fCRUD::');
	}
	
	
	/**
	 * Set the sort direction to be used on returning pages
	 * 
	 * @param  string $sort_direction  The sort direction to save
	 * @return void
	 */
	static private function setPreviousSortDirection($sort_direction)
	{
		fSession::set(fURL::get() . '::previous_sort_direction', $sort_direction, 'fCRUD::');
	}
	
	
	/**
	 * Return the previous sort column, if one exists
	 * 
	 * @return string  The previous sort column
	 */
	static private function getPreviousSortColumn()
	{
		return fSession::get(fURL::get() . '::previous_sort_column', NULL, 'fCRUD::');
	}
	
	
	/**
	 * Set the sort column to be used on returning pages
	 * 
	 * @param  string $sort_column  The sort column to save
	 * @return void
	 */
	static private function setPreviousSortColumn($sort_column)
	{
		fSession::set(fURL::get() . '::previous_sort_column', $sort_column, 'fCRUD::');
	}
	
	
	/**
	 * Returns the previous values for the specified search field
	 * 
	 * @param  string $column  The column to get the value for
	 * @return mixed  The previous value
	 */
	static private function getPreviousSearchValue($column)
	{
		return fSession::get(fURL::get() . '::previous_search::' . $column, NULL, 'fCRUD::');
	}
	
	
	/**
	 * Sets a value for a search field
	 * 
	 * @param  string $column  The column to save the value for
	 * @param  mixed  $value   The value to save
	 * @return void
	 */
	static private function setPreviousSearchValue($column, $value)
	{
		fSession::set(fURL::get() . '::previous_search::' . $column, $value, 'fCRUD::');
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
?>