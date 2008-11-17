<?php
/**
 * Represents a time of day as a value object
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
	 * Composes text using fText if loaded
	 * 
	 * @param  string  $message    The message to compose
	 * @param  mixed   $component  A string or number to insert into the message
	 * @param  mixed   ...
	 * @return string  The composed and possible translated message
	 */
	static protected function compose($message)
	{
		$args = array_slice(func_get_args(), 1);
		
		if (class_exists('fText', FALSE)) {
			return call_user_func_array(
				array('fText', 'compose'),
				array($message, $args)
			);
		} else {
			return vsprintf($message, $args);
		}
	}
	
	
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
	 * @param  fTime|object|string|integer $time  The time to represent, `NULL` is interpreted as now
	 * @return fTime
	 */
	public function __construct($time=NULL)
	{
		if ($time === NULL) {
			$timestamp = strtotime('now');
		} elseif (is_numeric($time) && ctype_digit($time)) {
			$timestamp = (int) $time;
		} else {
			if (is_object($time) && is_callable(array($time, '__toString'))) {
				$time = $time->__toString();	
			} elseif (is_numeric($time) || is_object($time)) {
				$time = (string) $time;	
			}
			$timestamp = strtotime($time);
		}
		
		if ($timestamp === FALSE || $timestamp === -1) {
			throw new fValidationException(
				'The time specified, %s, does not appear to be a valid time',
				$time
			);
		}
		
		$this->time = strtotime(date('1970-01-01 H:i:s', $timestamp));
	}
	
	
	/**
	 * All requests that hit this method should be requests for callbacks
	 * 
	 * @param  string $method  The method to create a callback for
	 * @return callback  The callback for the method requested
	 */
	public function __get($method)
	{
		return array($this, $method);		
	}
	
	
	/**
	 * Returns this time in `'H:i:s'` format
	 * 
	 * @return string  The `'H:i:s'` format of this time
	 */
	public function __toString()
	{
		return date('H:i:s', $this->time);
	}
	
	
	/**
	 * Changes the time by the adjustment specified, only asjustments of `'hours'`, `'minutes'`, and `'seconds'` are allowed
	 * 
	 * @throws fValidationException
	 * 
	 * @param  string $adjustment  The adjustment to make
	 * @return fTime  The adjusted time
	 */
	public function adjust($adjustment)
	{
		$timestamp = strtotime($adjustment, $this->time);
		
		if ($timestamp === FALSE || $timestamp === -1) {
			throw new fValidationException(
				'The adjustment specified, %s, does not appear to be a valid relative time measurement',
				$adjustment
			);
		}
		
		if (!preg_match('#^\s*(([+-])?\d+(\s+(min(untes?)?|sec(onds?)?|hours?))?\s*|now\s*)+\s*$#iD', $adjustment)) {
			throw new fValidationException(
				'The adjustment specified, %s, appears to be a date or timezone adjustment. Only adjustments of hours, minutes and seconds are allowed for times.',
				$adjustment
			);
		}
		
		return new fTime($timestamp);
	}
	
	
	/**
	 * Formats the time
	 * 
	 * @throws fValidationException
	 * 
	 * @param  string $format  The [http://php.net/date date()] function compatible formatting string, or a format name from fTimestamp::defineFormat()
	 * @return string  The formatted time
	 */
	public function format($format)
	{
		$format = fTimestamp::translateFormat($format);
		
		$restricted_formats = 'cdDeFIjlLmMnNoOPrStTUwWyYzZ';
		if (preg_match('#(?!\\\\).[' . $restricted_formats . ']#', $format)) {
			throw new fProgrammerException(
				'The formatting string, %1$s, contains one of the following non-time formatting characters: %2$s',
				$format,
				join(', ', str_split($restricted_formats))
			);
		}
		
		return fTimestamp::callFormatCallback(date($format, $this->time));
	}
	
	
	/**
	 * Returns the approximate difference in time, discarding any unit of measure but the least specific.
	 * 
	 * The output will read like:
	 * 
	 *  - "This time is `{return value}` the provided one" when a time it passed
	 *  - "This time is `{return value}`" when no time is passed and comparing with the current time
	 * 
	 * Examples of output for a time passed might be:
	 * 
	 *  - `'5 minutes after'`
	 *  - `'2 hours before'`
	 *  - `'at the same time'`
	 * 
	 * Examples of output for no time passed might be:
	 * 
	 *  - `'5 minutes ago'`
	 *  - `'2 hours ago'`
	 *  - `'right now'`
	 * 
	 * You would never get the following output since it includes more than one unit of time measurement:
	 * 
	 *  - `'5 minutes and 28 seconds'`
	 *  - `'1 hour, 15 minutes'`
	 * 
	 * Values that are close to the next largest unit of measure will be rounded up:
	 * 
	 *  - `'55 minutes'` would be represented as `'1 hour'`, however `'45 minutes'` would not
	 * 
	 * @param  fTime|object|string|integer $other_time  The time to create the difference with, `NULL` is interpreted as now
	 * @return string  The fuzzy difference in time between the this time and the one provided
	 */
	public function getFuzzyDifference($other_time=NULL)
	{
		$relative_to_now = FALSE;
		if ($other_time === NULL) {
			$relative_to_now = TRUE;
		}
		$other_time = new fTime($other_time);
		
		$diff = $this->time - $other_time->time;
		
		if (abs($diff) < 10) {
			if ($relative_to_now) {
				return self::compose('right now');
			}
			return self::compose('at the same time');
		}
		
		static $break_points = array();
		if (!$break_points) {
			$break_points = array(
				/* 45 seconds  */
				45     => array(1,     self::compose('second'), self::compose('seconds')),
				/* 45 minutes  */
				2700   => array(60,    self::compose('minute'), self::compose('minutes')),
				/* 18 hours    */
				64800  => array(3600,  self::compose('hour'),   self::compose('hours')),
				/* 5 days      */
				432000 => array(86400, self::compose('day'),    self::compose('days'))
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
				return self::compose('%1$s %2$s from now', $unit_diff, $units);
			}
			
			return self::compose('%1$s %2$s ago', $unit_diff, $units);
		}
		
		
		if ($diff > 0) {
			return self::compose('%1$s %2$s after', $unit_diff, $units);
		}
		
		return self::compose('%1$s %2$s before', $unit_diff, $units);
	}
	
	
	/**
	 * Returns the difference between the two times in seconds
	 * 
	 * @param  fTime|object|string|integer $other_time  The time to calculate the difference with, `NULL` is interpreted as now
	 * @return integer  The difference between the two times in seconds, positive if $other_time is before this time or negative if after
	 */
	public function getSecondsDifference($other_time=NULL)
	{
		$other_time = new fTime($other_time);
		return $this->time - $other_time->time;
	}
	
	
	/**
	 * Modifies the current time, creating a new fTime object
	 * 
	 * The purpose of this method is to allow for easy creation of a time
	 * based on this time. Below are some examples of formats to
	 * modify the current time:
	 * 
	 *  - `'17:i:s'` to set the hour of the time to 5 PM
	 *  - 'H:00:00'` to set the time to the beginning of the current hour
	 * 
	 * @param  string $format  The current time will be formatted with this string, and the output used to create a new object
	 * @return fTime  The new time
	 */
	public function modify($format)
	{
	   return new fTime($this->format($format));
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