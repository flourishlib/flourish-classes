<?php
/**
 * Represents a time of day
 * 
 * @copyright  Copyright (c) 2007-2008 William Bond
 * @author     William Bond [wb] <will@flourishlib.com>
 * @license    http://flourishlib.com/license
 * 
 * @package    Flourish
 * @link       http://flourishlib.com/fTime
 * 
 * @version    1.0.0b
 * @changes    1.0.0b  The initial implementation [wb, 2008-02-12]
 */
class fTime
{
	/**
	 * A timestamp of the time
	 * 
	 * @var integer
	 */
	private $time;
	
	
	/**
	 * Creates the time to represent, no timezone is allowed since times don't have timezones
	 * 
	 * @throws fValidationException
	 * 
	 * @param  string $time  The time to represent
	 * @return fTime
	 */
	public function __construct($time)
	{
		$parsed = strtotime($time);
		if ($parsed === FALSE || $parsed === -1) {
			fCore::toss(
				'fValidationException',
				fGrammar::compose(
					'The time specified, %s, does not appear to be a valid time',
					fCore::dump($time)
				)
			);
		}
		$this->set($parsed);
	}
	
	
	/**
	 * Returns this time in 'H:i:s' format
	 * 
	 * @return string  The 'H:i:s' format of this time
	 */
	public function __toString()
	{
		return $this->format('H:i:s');
	}
	
	
	/**
	 * Changes the time by the adjustment specified, only asjustments of 'hours', 'minutes', 'seconds' are allowed
	 * 
	 * @throws fValidationException
	 * 
	 * @param  string $adjustment  The adjustment to make
	 * @return void
	 */
	public function adjust($adjustment)
	{
		$time = $this->makeAdustment($adjustment, $this->time);
		$this->set($time);
	}
	
	
	/**
	 * Formats the time, with an optional adjustment
	 * 
	 * @throws fValidationException
	 * 
	 * @param  string $format      The {@link http://php.net/date date()} function compatible formatting string, or a format name from {@link fTimestamp::createFormat()}
	 * @param  string $adjustment  A temporary adjustment to make
	 * @return string  The formatted (and possibly adjusted) time
	 */
	public function format($format, $adjustment=NULL)
	{
		$format = fTimestamp::translateFormat($format);
		
		$restricted_formats = 'cdDeFIjlLmMnNoOPrStTUwWyYzZ';
		if (preg_match('#(?!\\\\).[' . $restricted_formats . ']#', $format)) {
			fCore::toss(
				'fProgrammerException',
				fGrammar::compose(
					'The formatting string, %1$s, contains one of the following non-time formatting characters: %2$s',
					fCore::dump($format),
					join(', ', str_split($restricted_formats))
				)
			);
		}
		
		$time = $this->time;
		
		if ($adjustment) {
			$time = $this->makeAdjustment($adjustment, $time);
		}
		
		return fTimestamp::callFormatCallback(date($format, $time));
	}
	
	
	/**
	 * Returns the approximate difference in time, discarding any unit of measure but the least specific.
	 * 
	 * The output will read like:
	 *  - "This time is {return value} the provided one" when a time it passed
	 *  - "This time is {return value}" when no time is passed and comparing with the current time
	 * 
	 * Examples of output for a time passed might be:
	 *  - 5 minutes after
	 *  - 2 hours before
	 *  - at the same time
	 * 
	 * Examples of output for no time passed might be:
	 *  - 5 minutes ago
	 *  - 2 hours ago
	 *  - right now
	 * 
	 * You would never get the following output since it includes more than one unit of time measurement:
	 *  - 5 minutes and 28 seconds
	 *  - 1 hour, 15 minutes
	 * 
	 * Values that are close to the next largest unit of measure will be rounded up:
	 *  - 55 minutes would be represented as 1 hour, however 45 minutes would not
	 * 
	 * @param  fTime $other_time  The time to create the difference with, passing NULL will get the difference with the current time
	 * @return string  The fuzzy difference in time between the this time and the one provided
	 */
	public function getFuzzyDifference(fTime $other_time=NULL)
	{
		$relative_to_now = FALSE;
		if ($other_time === NULL) {
			$other_time = new fTime('now');
			$relative_to_now = TRUE;
		}
		
		$diff = $this->time - strtotime($other_time->format('1970-01-01 H:i:s'));
		
		if (abs($diff) < 10) {
			if ($relative_to_now) {
				return fGrammar::compose('right now');	
			}
			return fGrammar::compose('at the same time');
		}
		
		static $break_points = array();
		if (!$break_points) {
			$break_points = array(
				/* 45 seconds  */
				45     => array(1,     fGrammar::compose('second'), fGrammar::compose('seconds')),
				/* 45 minutes  */
				2700   => array(60,    fGrammar::compose('minute'), fGrammar::compose('minutes')),
				/* 18 hours    */
				64800  => array(3600,  fGrammar::compose('hour'),   fGrammar::compose('hours')),
				/* 5 days      */
				432000 => array(86400, fGrammar::compose('day'),    fGrammar::compose('days'))
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
				return fGrammar::compose('%1$s %2$s from now', $unit_diff, $units);
			}
			
			return fGrammar::compose('%1$s %2$s ago', $unit_diff, $units);	
		}
		
		
		if ($diff > 0) {
			return fGrammar::compose('%1$s %2$s after', $unit_diff, $units);
		}
		
		return fGrammar::compose('%1$s %2$s before', $unit_diff, $units);
	}
	
	
	/**
	 * Returns the difference between the two times in seconds
	 * 
	 * @param  fTime $other_time  The time to calculate the difference with, if NULL is passed will compare with current time
	 * @return integer  The difference between the two times in seconds, positive if $other_time is before this time or negative if after
	 */
	public function getSecondsDifference(fTime $other_time=NULL)
	{
		if ($other_time === NULL) {
			$other_time = new fTime('now');
		}
		
		return $this->time - strtotime($other_time->format('1970-01-01 H:i:s'));
	}
	
	
	/**
	 * Makes an adjustment, returning the adjusted time
	 * 
	 * @throws fValidationException
	 * 
	 * @param  string  $adjustment  The adjustment to make
	 * @param  integer $timestamp   The time to adjust
	 * @return integer  The adjusted timestamp
	 */
	private function makeAdjustment($adjustment, $timestamp)
	{
		$timestamp = strtotime($adjustment, $timestamp);
		
		if ($timestamp === FALSE || $timestamp === -1) {
			fCore::toss(
				'fValidationException',
				fGrammar::compose(
					'The adjustment specified, %s, does not appear to be a valid relative time measurement',
					fCore::dump($adjustment)
				)
			);
		}
		
		if (!preg_match('#^\s*(([+-])?\d+(\s+(min(untes?)?|sec(onds?)?|hours?))?\s*|now\s*)+\s*$#i', $adjustment)) {
			fCore::toss(
				'fValidationException',
				fGrammar::compose(
					'The adjustment specified, %s, appears to be a date or timezone adjustment. Only adjustments of hours, minutes and seconds are allowed for times.',
					fCore::dump($adjustment)
				)
			);
		}
		
		return $timestamp;
	}
	
	
	/**
	 * Sets the time, changing the date on the timestamp to 1970-01-01
	 * 
	 * @param  integer $timestamp  The time to set. The date will be changed to 1970-01-01.
	 * @return void
	 */
	private function set($timestamp)
	{
		$this->time = strtotime(date('1970-01-01 H:i:s', $timestamp));
	}
	
	
	/**
	 * Changes the time to the time specified. Any parameters that are NULL are ignored.
	 * 
	 * @throws fValidationException
	 * 
	 * @param  integer $hour    The hour to change to
	 * @param  integer $minute  The minute to change to
	 * @param  integer $second  The second to change to
	 * @return void
	 */
	public function setTime($hour, $minute, $second)
	{
		$hour   = ($hour === NULL)   ? date('H', $this->time) : $hour;
		$minute = ($minute === NULL) ? date('i', $this->time) : $minute;
		$second = ($second === NULL) ? date('s', $this->time) : $second;
		
		if (!is_numeric($hour) || $hour < 0 || $hour > 23) {
			fCore::toss(
				'fValidationException',
				fGrammar::compose(
					'The hour specified, %s, does not appear to be a valid hour',
					fCore::dump($hour)
				)
			);
		}
		if (!is_numeric($minute) || $minute < 0 || $minute > 59) {
			fCore::toss(
				'fValidationException',
				fGrammar::compose(
					'The minute specified, %s, does not appear to be a valid minute',
					fCore::dump($minute)
				)
			);
		}
		if (!is_numeric($second) || $second < 0 || $second > 59) {
			fCore::toss(
				'fValidationException',
				fGrammar::compose(
					'The second specified, %s, does not appear to be a valid second',
					fCore::dump($second)
				)
			);
		}
		
		settype($minute, 'integer');
		settype($second, 'integer');
		
		if ($minute < 10) { $minute = '0' . $minute; }
		if ($second < 10) { $second = '0' . $second; }
		
		$parsed = strtotime($hour . ':' . $minute . ':' . $second);
		if ($parsed === FALSE || $parsed === -1) {
			fCore::toss(
				'fValidationException',
				fGrammar::compose(
					'The time specified, %1$s:%2$s:%3$s, does not appear to be a valid time'.
					fCore::dump($hour),
					fCore::dump($minute),
					fCore::dump($second)
				)
			);
		}
		$this->set($parsed);
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