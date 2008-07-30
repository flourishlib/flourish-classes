<?php
/**
 * Represents a date
 * 
 * @copyright  Copyright (c) 2007-2008 William Bond
 * @author     William Bond [wb] <will@flourishlib.com>
 * @license    http://flourishlib.com/license
 * 
 * @package    Flourish
 * @link       http://flourishlib.com/fDate
 * 
 * @version    1.0.0b
 * @changes    1.0.0b  The initial implementation [wb, 2008-02-10]
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
	 * @param  string $date  The date to represent
	 * @return fDate
	 */
	public function __construct($date)
	{
		$timestamp = strtotime($date);
		if ($timestamp === FALSE || $timestamp === -1) {
			fCore::toss(
				'fValidationException',
				fGrammar::compose(
					'The date specified, %s, does not appear to be a valid date',
					fCore::dump($date)
				)
			);
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
		
		$restricted_formats = 'aABcegGhHiIOPrsTuUZ';
		if (preg_match('#(?!\\\\).[' . $restricted_formats . ']#', $format)) {
			fCore::toss(
				'fProgrammerException',
				fGrammar::compose(
					'The formatting string, %1$s, contains one of the following non-date formatting characters: %2$s',
					fCore::dump($format),
					join(', ', str_split($restricted_formats))
				)
			);
		}
		
		$date = $this->date;
		
		if ($adjustment) {
			$date = $this->makeAdjustment($adjustment, $date);
		}
		
		return fTimestamp::callFormatCallback(date($format, $date));
	}
	
	
	/**
	 * Returns the approximate difference in time, discarding any unit of measure but the least specific.
	 * 
	 * The output will read like:
	 *  - "This date is {return value} the provided one" when a date it passed
	 *  - "This date is {return value}" when no date is passed and comparing with today
	 * 
	 * Examples of output for a date passed might be:
	 *  - 2 days after
	 *  - 1 year before
	 *  - same day
	 * 
	 * Examples of output for no date passed might be:
	 *  - 2 days from now
	 *  - 1 year ago
	 *  - today
	 * 
	 * You would never get the following output since it includes more than one unit of time measurement:
	 *  - 3 weeks and 1 day
	 *  - 1 year and 2 months
	 * 
	 * Values that are close to the next largest unit of measure will be rounded up:
	 *  - 6 days would be represented as 1 week, however 5 days would not
	 *  - 29 days would be represented as 1 month, but 21 days would be shown as 3 weeks
	 * 
	 * @param  fDate $other_date  The date to create the difference with, if NULL is passed will compare with current date
	 * @return string  The fuzzy difference in time between the this date and the one provided
	 */
	public function getFuzzyDifference(fDate $other_date=NULL)
	{
		$relative_to_now = FALSE;
		if ($other_date === NULL) {
			$other_date = new fDate('now');
			$relative_to_now = TRUE;
		}
		
		$diff = $this->date - strtotime($other_date->format('Y-m-d 00:00:00'));
		
		if (abs($diff) < 86400) {
			if ($relative_to_now) {
				return fGrammar::compose('today');
			}
			return fGrammar::compose('same day');
		}
		
		static $break_points = array();
		if (!$break_points) {
			$break_points = array(
				/* 5 days      */
				432000     => array(86400,    fGrammar::compose('day'),   fGrammar::compose('days')),
				/* 3 weeks     */
				1814400    => array(604800,   fGrammar::compose('week'),  fGrammar::compose('weeks')),
				/* 9 months    */
				23328000   => array(2592000,  fGrammar::compose('month'), fGrammar::compose('months')),
				/* largest int */
				2147483647 => array(31536000, fGrammar::compose('year'),  fGrammar::compose('years'))
			);
		}
		
		foreach ($break_points as $break_point => $unit_info) {
			if (abs($diff) > $break_point) { continue; }
			
			$unit_diff = round(abs($diff)/$unit_info[0]);
			$units     = fGrammar::inflectOnQuantity($unit_diff, $unit_info[1], $unit_info[2]);
			break;
		}
		
		if ($relative_to_now) {
			if ($diff > 0) {
				return fGrammar::compose(
					'%1$s %2$s from now',
					$unit_diff,
					$units
				);
			}
			
			return fGrammar::compose(
				'%1$s %2$s ago',
				$unit_diff,
				$units
			);
		}
		
		if ($diff > 0) {
			return fGrammar::compose('%1$s %2$s after', $unit_diff, $units);
		}
		
		return fGrammar::compose('%1$s %2$s before', $unit_diff, $units);
	}
	
	
	/**
	 * Returns the difference between the two dates in seconds
	 * 
	 * @param  fDate $other_date  The date to calculate the difference with, if NULL is passed will compare with current date
	 * @return integer  The difference between the two dates in seconds, positive if $other_date is before this date or negative if after
	 */
	public function getSecondsDifference(fDate $other_date=NULL)
	{
		if ($other_date === NULL) {
			$other_date = new fDate('now');
		}
		
		return $this->date - strtotime($other_date->format('Y-m-d 00:00:00'));
	}
	
	
	/**
	 * Makes an adjustment, returning the adjusted date
	 * 
	 * @throws fValidationException
	 * 
	 * @param  string  $adjustment  The adjustment to make
	 * @param  integer $timestamp   The date to adjust
	 * @return integer  The adjusted timestamp
	 */
	private function makeAdjustment($adjustment, $timestamp)
	{
		$timestamp = strtotime($adjustment, $timestamp);
		
		if ($timestamp === FALSE || $timestamp === -1) {
			fCore::toss(
				'fValidationException',
				fGrammar::compose(
					'The adjustment specified, %s, does not appear to be a valid relative date measurement',
					fCore::dump($adjustment)
				)
			);
		}
		
		if (date('H:i:s', $timestamp) != '00:00:00') {
			fCore::toss(
				'fValidationException',
				fGrammar::compose(
					'The adjustment specified, %s, appears to be a time or timezone adjustment. Only adjustments of a day or greater are allowed for dates.',
					fCore::dump($adjustment)
				)
			);
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
		$this->date = strtotime(date('Y-m-d 00:00:00', $timestamp));
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
			fCore::toss(
				'fValidationException',
				fGrammar::compose(
					'The year specified, %s, does not appear to be a valid year',
					fCore::dump($year)
				)
			);
		}
		if (!is_numeric($month) || $month < 1 || $month > 12) {
			fCore::toss(
				'fValidationException',
				fGrammar::compose(
					'The month specified, %s, does not appear to be a valid month',
					fCore::dump($month)
				)
			);
		}
		if (!is_numeric($day) || $day < 1 || $day > 31) {
			fCore::toss(
				'fValidationException', 
				fGrammar::compose(
					'The day specified, %s, does not appear to be a valid day',
					fCore::dump($day)
				)
			);
		}
		
		settype($month, 'integer');
		settype($day,   'integer');
		
		if ($month < 10) { $month = '0' . $month; }
		if ($day < 10)   { $day   = '0' . $day; }
		
		$timestamp = strtotime($year . '-' . $month . '-' . $day);
		if ($timestamp === FALSE || $timestamp === -1) {
			fCore::toss(
				'fValidationException',
				fGrammar::compose(
					'The date specified, %1$s-%2$s-%3$s, does not appear to be a valid date',
					fCore::dump($year),
					fCore::dump($month),
					fCore::dump($day)
				)
			);
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
		$week        = ($week === NULL)        ? date('W', $this->date) : $week;
		$day_of_week = ($day_of_week === NULL) ? date('N', $this->date) : $day_of_week;
		
		if (!is_numeric($year) || $year < 1901 || $year > 2038) {
			fCore::toss(
				'fValidationException',
				fGrammar::compose(
					'The year specified, %s, does not appear to be a valid year',
					fCore::dump($year)
				)
			);
		}
		if (!is_numeric($week) || $week < 1 || $week > 53) {
			fCore::toss(
				'fValidationException',
				fGrammar::compose(
					'The week specified, %s, does not appear to be a valid week',
					fCore::dump($week)
				)
			);
		}
		if (!is_numeric($day_of_week) || $day_of_week < 1 || $day_of_week > 7) {
			fCore::toss(
				'fValidationException',
				fGrammar::compose(
					'The day of week specified, %s, does not appear to be a valid day of the week',
					fCore::dump($day_of_week)
				)
			);
		}
		
		settype($week, 'integer');
		
		if ($week < 10) { $week = '0' . $week; }
		
		$timestamp = strtotime($year . '-01-01 +' . ($week-1) . ' weeks +' . ($day_of_week-1) . ' days');
		if ($timestamp === FALSE || $timestamp === -1) {
			fCore::toss(
				'fValidationException', 
				fGrammar::compose(
					'The ISO date specified, %1$s-W%2$s-%3$s, does not appear to be a valid ISO date',
					fCore::dump($year),
					fCore::dump($week),
					fCore::dump($day_of_week)
				)
			);
		}
		$this->set($timestamp);
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