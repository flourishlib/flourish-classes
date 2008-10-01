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
	 * @param  fDate|object|string|integer $date  The date to represent, NULL is interpreted as today
	 * @return fDate
	 */
	public function __construct($date=NULL)
	{
		if ($date === NULL) {
			$timestamp = strtotime('now');
		} elseif (is_numeric($date) && ctype_digit($date)) {
			$timestamp = (int) $date;
		} else {
			if (is_object($date) && is_callable(array($date, '__toString'))) {
				$date = $date->__toString();	
			} elseif (fCore::stringlike($date)) {
				$date = (string) $date;	
			}
			$timestamp = strtotime(fTimestamp::fixISOWeek($date));
		}
		
		if ($timestamp === FALSE || $timestamp === -1) {
			fCore::toss(
				'fValidationException',
				fGrammar::compose(
					'The date specified, %s, does not appear to be a valid date',
					fCore::dump($date)
				)
			);
		}
		
		$this->date = strtotime(date('Y-m-d 00:00:00', $timestamp));
	}
	
	
	/**
	 * Returns this date in 'Y-m-d' format
	 * 
	 * @return string  The 'Y-m-d' format of this date
	 */
	public function __toString()
	{
		return date('Y-m-d', $this->date);
	}
	
	
	/**
	 * Changes the date by the adjustment specified, only asjustments of a day or more will be made
	 * 
	 * @throws fValidationException
	 * 
	 * @param  string $adjustment  The adjustment to make
	 * @return fDate  The adjusted date
	 */
	public function adjust($adjustment)
	{
		$timestamp = strtotime($adjustment, $this->date);
		
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
		
		return new fDate($timestamp);
	}
	
	
	/**
	 * Formats the date
	 * 
	 * @throws fValidationException
	 * 
	 * @param  string $format  The {@link http://php.net/date date()} function compatible formatting string, or a format name from {@link fTimestamp::defineFormat()}
	 * @return string  The formatted date
	 */
	public function format($format)
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
		
		return fTimestamp::callFormatCallback(date($format, $this->date));
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
	 * @param  fDate|object|string|integer $other_date  The date to create the difference with, NULL is interpreted as today
	 * @return string  The fuzzy difference in time between the this date and the one provided
	 */
	public function getFuzzyDifference($other_date=NULL)
	{
		$relative_to_now = FALSE;
		if ($other_date === NULL) {
			$relative_to_now = TRUE;
		}
		$other_date = new fDate($other_date);
		
		$diff = $this->date - $other_date->date;
		
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
	 * @param  fDate|object|string|integer $other_date  The date to calculate the difference with, NULL is interpreted as today
	 * @return integer  The difference between the two dates in seconds, positive if $other_date is before this date or negative if after
	 */
	public function getSecondsDifference($other_date=NULL)
	{
		$other_date = new fDate($other_date);
		return $this->date - $other_date->date;
	}
	
	
	/**
	 * Modifies the current date, creating a new fDate object
	 * 
	 * The purpose of this method is to allow for easy creation of a date
	 * based on this date. Below are some examples of formats to
	 * modify the current date:
	 * 
	 * To change the date to the first of the month:
	 * 
	 * <pre>
	 * Y-m-01
	 * </pre>
	 * 
	 * To change the date to the last of the month:
	 * 
	 * <pre>
	 * Y-m-t
	 * </pre>
	 * 
	 * To change the date to the 5th week of the year:
	 * 
	 * <pre>
	 * Y-\W5-N
	 * </pre>
	 * 
	 * @param  string $format  The current date will be formatted with this string, and the output used to create a new object
	 * @return fDate  The new date
	 */
	public function modify($format)
	{
	   return new fDate($this->format($format));
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