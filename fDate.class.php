<?php
/**
 * Represents a date
 * 
 * @copyright  Copyright (c) 2007-2008 William Bond
 * @author     William Bond [wb] <will@flourishlib.com>
 * @license    http://flourishlib.com/license
 * 
 * @link  http://flourishlib.com/fDate
 * 
 * @uses  fCore
 * @uses  fProgrammerException
 * @uses  fTimestamp 
 * @uses  fValidationException
 * 
 * @version  1.0.0 
 * @changes  1.0.0    The initial implementation [wb, 2008-02-10]
 */
class fDate
{	
	/**
	 * A timestamp of the date
	 * 
	 * @var integer 
	 */
	private $date;   
	
	
	/**
	 * Creates the date to represent, no timezone is allowed since dates don't have timezones
	 * 
	 * @throws fValidationException
	 * 
	 * @param  string $date      The date to represent
	 * @return fDate
	 */
	public function __construct($date)
	{
		$timestamp = strtotime($date);
		if ($timestamp === FALSE || $timestamp === -1) {
			fCore::toss('fValidationException', 'The date specified, ' . $date . ', does not appear to be a valid date'); 		
		}
		$this->set($timestamp);
	}
	
	
	/**
	 * Returns this date in 'Y-m-d' format
	 * 
	 * @return string  The 'Y-m-d' format of this date
	 */
	public function __toString()
	{
		return $this->format('Y-m-d'); 
	}
	
	
	/**
	 * Changes the date to the date specified. Any parameters that are NULL are ignored.
	 * 
	 * @throws fValidationException
	 * 
	 * @param  integer $year   The year to change to
	 * @param  integer $month  The month to change to
	 * @param  integer $day    The day of the month to change to
	 * @return void
	 */
	public function setDate($year, $month, $day)
	{
		$year  = ($year === NULL)  ? date('Y', $this->date) : $year;
		$month = ($month === NULL) ? date('m', $this->date) : $month;
		$day   = ($day === NULL)   ? date('d', $this->date) : $day;
		
		if (!is_numeric($year) || $year < 1901 || $year > 2038) {
			fCore::toss('fValidationException', 'The year specified, ' . $year . ', does not appear to be a valid year'); 				
		}
		if (!is_numeric($month) || $month < 1 || $month > 12) {
			fCore::toss('fValidationException', 'The month specified, ' . $month . ', does not appear to be a valid month'); 				
		}
		if (!is_numeric($day) || $day < 1 || $day > 31) {
			fCore::toss('fValidationException', 'The day specified, ' . $day . ', does not appear to be a valid day'); 				
		}
		
		settype($month, 'integer');
		settype($day,   'integer');
		
		if ($month < 10) { $month = '0' . $month; }
		if ($day < 10)   { $day   = '0' . $day; }
		
		$timestamp = strtotime($year . '-' . $month . '-' . $day);
		if ($timestamp === FALSE || $timestamp === -1) {
			fCore::toss('fValidationException', 'The date specified, ' . $date . ', does not appear to be a valid date'); 		
		}
		$this->set($timestamp);
	}
	
	
	/**
	 * Changes the date to the ISO date (year, week, day of week) specified. Any parameters that are NULL are ignored.
	 * 
	 * @throws fValidationException
	 * 
	 * @param  integer $year         The year to change to
	 * @param  integer $week         The week to change to
	 * @param  integer $day_of_week  The day of the week to change to
	 * @return void
	 */
	public function setISODate($year, $week, $day_of_week)
	{
		$year        = ($year === NULL)        ? date('Y', $this->date) : $year;
		$week        = ($week === NULL)        ? date('W', $this->date) : $month;
		$day_of_week = ($day_of_week === NULL) ? date('N', $this->date) : $day_of_week;
		
		if (!is_numeric($year) || $year < 1901 || $year > 2038) {
			fCore::toss('fValidationException', 'The year specified, ' . $year . ', does not appear to be a valid year'); 				
		}
		if (!is_numeric($week) || $week < 1 || $week > 53) {
			fCore::toss('fValidationException', 'The week specified, ' . $week . ', does not appear to be a valid week'); 				
		}
		if (!is_numeric($day_of_week) || $day_of_week < 1 || $day_of_week > 7) {
			fCore::toss('fValidationException', 'The day of week specified, ' . $day_of_week . ', does not appear to be a valid day of the week'); 				
		}
		
		settype($week, 'integer');
		
		if ($week < 10) { $week = '0' . $week; }
		
		$timestamp = strtotime($year . '-W' . $week . '-' . $day_of_week);
		if ($timestamp === FALSE || $timestamp === -1) {
			fCore::toss('fValidationException', 'The date specified, ' . $date . ', does not appear to be a valid ISO date'); 		
		}
		$this->set($timestamp);
	}
	
	
	/**
	 * Changes the date by the adjustment specified, only asjustments of a day or more will be made
	 * 
	 * @throws fValidationException
	 * 
	 * @param  string $adjustment  The adjustment to make
	 * @return void
	 */
	public function adjust($adjustment)
	{
		$date = $this->makeAdjustment($adjustment, $this->date);
		$this->set($date);
	}
	
	
	/**
	 * Formats the date, with an optional adjustment
	 * 
	 * @throws fValidationException
	 * 
	 * @param  string $format      The {@link http://php.net/date date()} function compatible formatting string, or a format name from {@link fTimestamp::createFormat()}
	 * @param  string $adjustment  A temporary adjustment to make
	 * @return string  The formatted (and possibly adjusted) date
	 */
	public function format($format, $adjustment=NULL)
	{
		$format = fTimestamp::translateFormat($format);
		
		$restricted_formats = 'aABgGhHisueIOPTZcrU';
		if (preg_match('#(?!\\\\).[' . $restricted_formats . ']#')) {
			fCore::toss('fProgrammerException', 'The formatting string, ' . $format . ', contains one of the following non-date formatting characters: ' . join(', ', explode($restricted_formats)));	
		}
		
		$date = $this->date;
		
		if ($adjustment) {
			$date = $this->makeAdjustment($adjustment, $date);
		}
		
		return date($format, $date);
	}
	
	
	/**
	 * Makes an adjustment, returning the adjusted date
	 * 
	 * @throws fValidationException
	 * 
	 * @param  string $adjustment  The adjustment to make
	 * @param  integer $timestamp  The date to adjust
	 * @return integer  The adjusted timestamp
	 */
	private function makeAdjustment($adjustment, $timestamp)
	{
		if (preg_match('#hour|minute|second#i', $adjustment)) {
			fCore::toss('fValidationException', 'The adjustment specified, ' . $adjustment . ', contains an adjustment of a time such as: hour, minute, second. Only adjustments of date are allowed, such as: day, month, year, week.');	
		}
		
		$timestamp = strtotime($adjustment, $timestamp);
		
		if ($timestamp === FALSE || $timestamp === -1) {
			fCore::toss('fValidationException', 'The adjustment specified, ' . $adjustment . ', does not appear to be a valid relative date measurement'); 		
		}  
		
		return $timestamp;
	}
	
	
	/**
	 * Sets the date, making sure all hours minutes and seconds are removed
	 * 
	 * @param  integer $timestamp  The date to set. All hours, minutes and seconds will be removed
	 * @return void
	 */
	private function set($timestamp)
	{
		$this->date = date('Y-m-d', $timestamp);   
	}
}


/**
 * Copyright (c) 2008 William Bond <will@flourishlib.com>
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