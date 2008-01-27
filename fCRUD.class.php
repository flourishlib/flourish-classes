<?php
/**
 * Provides functionality for CRUD pages
 * 
 * CRUD stands for Create, Read, Update and Delete - the basic functionality of
 * almost all web applications.
 * 
 * @copyright  Copyright (c) 2007 William Bond
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
	 * Prints standard sub nav based on list/add/edit/delete 
	 * 
	 * @param  string  $action         The currently selected action
	 * @param  string  $parameter      The parameter for the primary key of the object we are managing
	 * @param  boolean $show_all_link  If the add link should be shown on the list view
	 * @return void
	 */
	static public function showSubNav($action, $parameter, $show_add_link=TRUE)
	{
		if (($action == 'list' && $show_add_link) || $action != 'list') {
			?>
			<ul>
				<? if ($action == 'edit' || $action == 'update') { ?>
					<li><a href="<?= fURL::get() ?>?action=delete&amp;<?= $parameter ?>=<?= fRequest::get($parameter) ?>">Delete</a></li>
				<? } ?>
				<? if ($action == 'delete' || $action == 'remove') { ?>
					<li><a href="<?= fURL::get() ?>?action=edit&amp;<?= $parameter ?>=<?= fRequest::get($parameter) ?>">Edit</a></li>
				<? } ?>
				<? if ($action == 'list' && $show_add_link) { ?>
					<li><a href="<?= fURL::get() ?>?action=add">Add</a></li>
				<? } ?>
				<? if ($action != 'list') { ?>
					<li><a href="<?= fURL::get() ?>">List</a></li>
				<? } ?>
			</ul>
			<?	
		}
	}
	
	
	/**
	 * Prints a paragraph (or div if the content has block-level html) with the contents and the class specified. Will not print if no content 
	 * 
	 * @param  string $content    The content to display
	 * @param  string $css_class  The css class to apply
	 * @return void
	 */
	static public function show($content, $css_class)
	{
		if (empty($content)) {
			return;
		}		
		$contains_block_level = strip_tags($content, '<a><abbr><acronym><b><code><em><i><span><strong>') != $content;
		if ($contains_block_level) {
			echo '<div class="' . $css_class . '">' . fHTML::prepareHTML($content) . '</div>';	
		} else {
			echo '<p class="' . $css_class . '">' . fHTML::prepareHTML($content) . '</p>';
		}
	}
	
	
	/**
	 * Prints successful 'added', 'updated', 'deleted' messaging 
	 * 
	 * @param  string $object_name  The name of the type of objects we are manipulating
	 * @return void
	 */
	static public function showSuccessMessages($object_name)
	{
		if (fRequest::get('added', 'boolean')) {
			?><p class="success">The <?= fHTML::encodeEntities($object_name) ?> was successfully added</p><?	
		}
		
		if (fRequest::get('updated', 'boolean')) {
			?><p class="success">The <?= fHTML::encodeEntities($object_name) ?> was successfully updated</p><?	
		}
		
		if (fRequest::get('deleted', 'boolean')) {
			?><p class="success">The <?= fHTML::encodeEntities($object_name) ?> was successfully deleted</p><?	
		}
	}
	
	
	/**
	 * Removes lines from a fPrintableException based on the field name
	 * 
	 * @param  fPrintableException $e          The exception to remove field names from
	 * @param  string              $field,...  The fields to remove from the exception error message
	 * @return void
	 */
	static public function removeFields(fPrintableException $e)
	{
		$fields = func_get_args();
		$fields = array_map(array('fInflection', 'humanize'), array_slice($fields, 1));
		$message = $e->getMessage();
		$lines   = array_merge(array_filter(preg_split("#\n|((?<=</li>)(?=<li>))|((?<=<ul>)(?=<li>))|((?<=</li>)(?=</ul>))#", $message)));

		$new_lines = array();
		foreach ($lines as $line) {
			$found = FALSE;
			foreach ($fields as $field) {
				if (strpos($line, $field) !== FALSE) {
					$found = TRUE;
				}
			}
			if ($found) {
				continue;
			}	
			$new_lines[] = $line;			
		}
		
		$e->setMessage(join("\n", $new_lines));
	}
	
	
	/**
	 * Checks to see if any values (search or sort) were loaded from the session, and if so redirects the user to the current URL with those values added
	 * 
	 * @return void
	 */
	static public function redirectWithLoadedValues()
	{
		$query_string = fURL::replaceInQueryString(array_keys(self::$loaded_values), array_values(self::$loaded_values), FALSE);
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
		if (self::checkForAction() && self::getPreviousSearchValue($column) && fRequest::get($column) === NULL) {
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
		if (self::checkForAction() && self::getPreviousSortColumn() && fRequest::get('sort') === NULL) {
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
		if (self::checkForAction() && self::getPreviousSortDirection() && fRequest::get('dir') === NULL) {
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
		?><a href="<?= fURL::get() . fURL::replaceInQueryString($columns, $values) ?>" class="sortable_column<?= (self::$sort_column == $column) ? ' ' . self::$sort_direction : '' ?>"><?= fHTML::encodeEntities($column_name) ?></a><?	
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
		if ($row_value == $affected_value &&
			(fRequest::get('added', 'boolean') ||
			 fRequest::get('updated', 'boolean'))) {
			 self::$row_number++;
			 return 'highlighted';
		}
			
		$class = (self::$row_number++ % 2) ? 'odd' : 'even';
		$class .= (self::$row_number == 2) ? ' first' : '';
		return $class;
	}
	
	
	/**
	 * Overrides the value of 'action' in $_REQUEST, $_POST, $_GET based on the 'action::ACTION_NAME' value in $_REQUEST, $_POST, $_GET. Used for multiple submit buttons.
	 * 
	 * @return void
	 */
	static public function overrideAction()
	{
		foreach ($_REQUEST as $key => $value) {
			if (substr($key, 0, 8) == 'action::') {
				$_REQUEST['action'] = substr($key, 8);
				unset($_REQUEST[$key]);
			}	
		}
		foreach ($_POST as $key => $value) {
			if (substr($key, 0, 8) == 'action::') {
				$_POST['action'] = substr($key, 8);
				unset($_POST[$key]);
			}	
		}
		foreach ($_GET as $key => $value) {
			if (substr($key, 0, 8) == 'action::') {
				$_GET['action'] = substr($key, 8);
				unset($_GET[$key]);
			}	
		}
	}
	
	
	/**
	 * Indicates if something was just added, updated, or deleted
	 * 
	 * @return void
	 */
	static private function checkForAction()
	{
		if (fRequest::get('added', 'boolean') ||
			fRequest::get('updated', 'boolean') ||
			fRequest::get('deleted', 'boolean')) {
			return TRUE;	
		}	
		return FALSE;
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
 * Copyright (c) 2007 William Bond <will@flourishlib.com>
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