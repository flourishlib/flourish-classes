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
	 * Prevent instantiation
	 * 
	 * @since  1.0.0
	 * 
	 * @return fCRUD
	 */
	private function __construct() { }
	
	
	/**
	 * Overrides the value of 'method' in $_REQUEST, $_POST, $_GET based on the 'method::METHOD_NAME' value in $_REQUEST, $_POST, $_GET. Useful with multiple submit buttons.
	 * 
	 * @since  1.0.0
	 * 
	 * @return void
	 */
	static public function overrideMethod()
	{
		foreach ($_REQUEST as $key => $value) {
			if (substr($key, 0, 8) == 'method::') {
				$_REQUEST['method'] = substr($key, 8);
				unset($_REQUEST[$key]);
			}	
		}
		foreach ($_POST as $key => $value) {
			if (substr($key, 0, 8) == 'method::') {
				$_POST['method'] = substr($key, 8);
				unset($_POST[$key]);
			}	
		}
		foreach ($_GET as $key => $value) {
			if (substr($key, 0, 8) == 'method::') {
				$_GET['method'] = substr($key, 8);
				unset($_GET[$key]);
			}	
		}
	}
	
	
	/**
	 * Removes lines from a fPrintableException based on the field name
	 * 
	 * @since  1.0.0
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
	 * Prints a paragraph (or div if the content has block-level html) with the contents and the class specified. Will not print if no content 
	 * 
	 * @since  1.0.0
	 * 
	 * @param  string $content   The content to display
	 * @param  string $class     The css class to apply
	 * @return void
	 */
	static public function display($content, $class)
	{
		if (empty($content)) {
			return;
		}		
		$contains_block_level = strip_tags($content, '<a><abbr><acronym><b><code><em><i><span><strong>') != $content;
		if ($contains_block_level) {
			echo '<div class="' . $class . '">' . fHTML::encodeHtml($content) . '</div>';	
		} else {
			echo '<p class="' . $class . '">' . fHTML::encodeHtml($content) . '</p>';
		}
	}
	
	
	/**
	 * Returns a css class name for a row. Will return even, odd, or highlighted if the two parameters are equal and added or updated is true
	 * 
	 * @since  1.0.0
	 * 
	 * @param  mixed $first_value    The first value to compare
	 * @param  mixed $second_value   The second value to compare
	 * @return string  The css class
	 */
	static public function getRowClass($first_value, $second_value)
	{
		if ($first_value == $second_value &&
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
	 * Prints successful 'added', 'updated', 'deleted' messaging 
	 * 
	 * @since  1.0.0
	 * 
	 * @param  string $object_name  The name of the type of objects we are manipulating
	 * @return void
	 */
	static public function showMessages($object_name)
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
	 * Prints standard sub nav based on list/add/edit/delete 
	 * 
	 * @since  1.0.0
	 * 
	 * @param  string  $method         The currently selected method
	 * @param  string  $parameter      The parameter for the primary key of the object we are managing
	 * @param  boolean $show_all_link  If the add link should be shown on the list view
	 * @return void
	 */
	static public function showSubNav($method, $parameter, $show_add_link=TRUE)
	{
		if (($method == 'list' && $show_add_link) || $method != 'list') {
			?>
			<ul>
				<? if ($method == 'edit' || $method == 'update') { ?>
					<li><a href="<?= fURL::get() ?>?method=delete&amp;<?= $parameter ?>=<?= fRequest::get($parameter) ?>">Delete</a></li>
				<? } ?>
				<? if ($method == 'delete' || $method == 'delete_action') { ?>
					<li><a href="<?= fURL::get() ?>?method=edit&amp;<?= $parameter ?>=<?= fRequest::get($parameter) ?>">Edit</a></li>
				<? } ?>
				<? if ($method == 'list' && $show_add_link) { ?>
					<li><a href="<?= fURL::get() ?>?method=add">Add</a></li>
				<? } ?>
				<? if ($method != 'list') { ?>
					<li><a href="<?= fURL::get() ?>">List</a></li>
				<? } ?>
			</ul>
			<?	
		}
	}
	
	
	/**
	 * Gets the current column to sort by, defaults to first
	 * 
	 * @since  1.0.0
	 * 
	 * @param  array $possible_columns  The columns that can be sorted by, defaults to first
	 * @return string  The column to sort by
	 */
	static public function getSortColumn($possible_columns)
	{
		if (self::checkForAction() && self::getPreviousSortColumn() && fRequest::get('sort') === NULL) {
			self::$sort_column = self::getPreviousSortColumn();	
		} else {
			self::$sort_column = fRequest::getFromArray('sort', $possible_columns);
			self::setPreviousSortColumn(self::$sort_column);
		}
		return self::$sort_column;	
	}
	
	
	/**
	 * Gets the current sort direction
	 * 
	 * @since  1.0.0
	 * 
	 * @param  string $default_direction  The default direction, 'asc' or 'desc'
	 * @return string  The direction, 'asc', or 'desc'
	 */
	static public function getSortDirection($default_direction)
	{
		if (self::checkForAction() && self::getPreviousSortDirection() && fRequest::get('dir') === NULL) {
			self::$sort_direction = self::getPreviousSortDirection();	
		} else {
			self::$sort_direction = fRequest::getFromArray('dir', array($default_direction, ($default_direction == 'asc') ? 'desc' : 'asc'));
			self::setPreviousSortDirection(self::$sort_direction);
		}
		return self::$sort_direction;	
	}
	
	
	/**
	 * Gets the current value of a search field 
	 * 
	 * @since  1.0.0
	 * 
	 * @param  string $column   The column that is being pulled back
	 * @param  string $cast_to  The data type to cast to
	 * @param  string $default  The default value
	 * @return string  The current value
	 */
	static public function getSearchValue($column, $cast_to=NULL, $default=NULL)
	{
		if (self::checkForAction() && self::getPreviousSearchValue($column) && fRequest::get($column) === NULL) {
			self::$search_values[$column] = self::getPreviousSearchValue();	
		} else {
			self::$search_values[$column] = fRequest::get($column, $cast_to, $default);
			self::setPreviousSearchValue($column, self::$search_values[$column]);
		}
		return self::$search_values[$column];	
	}
	
	
	/**
	 * Prints a sortable column header 
	 * 
	 * @since  1.0.0
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
		?>
		<a href="<?= fURL::get() . fURL::replaceInQueryString($columns, $values) ?>" class="sortable_column<?= (self::$sort_column == $column) ? ' ' . self::$sort_direction : '' ?>"><?= fHTML::encodeEntities($column_name) ?></a>
		<?	
	}
	
	
	/**
	 * Prints a class attribute for a td if that td is part of the sorted column 
	 * 
	 * @since  1.0.0
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
	 * Indicates if something was just added, updated, or deleted
	 * 
	 * @since  1.0.0
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
	 * @since  1.0.0
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
	 * @since  1.0.0
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
	 * @since  1.0.0
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
	 * @since  1.0.0
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
	 * @since  1.0.0
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
	 * @since  1.0.0
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