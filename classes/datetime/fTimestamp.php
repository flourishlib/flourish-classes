<?php
/**
 * Represents a date and time
 * 
 * @copyright  Copyright (c) 2007-2008 William Bond
 * @author     William Bond [wb] <will@flourishlib.com>
 * @license    http://flourishlib.com/license
 * 
 * @link  http://flourishlib.com/fTimestamp
 * 
 * @version  1.0.0
 * @changes  1.0.0    The initial implementation [wb, 2008-02-12]
 */
class fTimestamp
{
	/**
	 * Pre-defined formatting styles
	 * 
	 * @var array
	 */
	static private $formats = array();
	
	
	/**
	 * Checks to make sure the current version of PHP is high enough to support timezone features
	 * 
	 * @return void
	 */
	static private function checkPHPVersion()
	{
		if (version_compare(fCore::getPHPVersion(), '5.1.0') == -1) {
			fCore::toss('fEnvironmentException', 'The fTimestamp class takes advantage of the timezone features in PHP 5.1.0 and newer. Unfortunately it appears you are running an older version of PHP.');
		}
	}
	
	
	/**
	 * Creates an fTimestamp object from fDate, fTime objects and optionally a timezone
	 * 
	 * @throws fValidationException
	 * 
	 * @param  fDate  $date      The date to combine
	 * @param  fTime  $time      The time to combine
	 * @param  string $timezone  The timezone for the date/time. This causes the date/time to be interpretted as being in the specified timezone. . If not specified, will default to timezone set by {@link fTimestamp::setDefaultTimezone()}.
	 * @return fTimestamp
	 */
	static public function combine(fDate $date, fTime $time, $timezone=NULL)
	{
		return new fTimestamp($date . ' ' . $time, $timezone);
	}
	
	
	/**
	 * Creates a reusable format for formatting fDate/fTime/fTimestamp
	 * 
	 * @param  string $name               The name of the format
	 * @param  string $formatting_string  The format string compatible with the {@link http://php.net/date date()} function
	 * @return void
	 */
	static public function createFormat($name, $formatting_string)
	{
		self::$formats[$name] = $formatting_string;
	}
	
	
	/**
	 * Provides a consistent interface to getting the default timezone. Wraps the {@link http://php.net/date_default_timezone_get date_default_timezone_get()} function.
	 * 
	 * @return string  The default timezone used for all date/time calculations
	 */
	static public function getDefaultTimezone()
	{
		self::checkPHPVersion();
		
		return date_default_timezone_get();
	}
	
	
	/**
	 * Returns the number of seconds in a given timespan (e.g. '30 minutes', '1 hour', '5 days', etc). Useful for comparing with {@link fTime::getSecondsDifference()} and {@link fTimestamp::getSecondsDifference()}.
	 * 
	 * @param  string $timespan  The timespan to calculate the number of seconds in
	 * @return integer  The number of seconds in the timestamp specified
	 */
	static public function getSeconds($timespan)
	{
		return strtotime($timespan) - time();
	}
	
	
	/**
	 * Provides a consistent interface to setting the default timezone. Wraps the {@link http://php.net/date_default_timezone_set date_default_timezone_set()} function.
	 * 
	 * @param  string $timezone  The default timezone to use for all date/time calculations
	 * @return void
	 */
	static public function setDefaultTimezone($timezone)
	{
		self::checkPHPVersion();
		
		$result = date_default_timezone_set($timezone);
		if (!$result) {
			fCore::toss('fProgrammerException', 'The timezone specified, ' . $timezone . ', does not appear to be a valid timezone');
		}
	}
	
	
	/**
	 * Takes a format name set via {@link fTimestamp::createFormat()} and returns the {@link http://php.net/date date()} function formatting string
	 * 
	 * @internal
	 * 
	 * @param  string $format  The format to translate
	 * @return string  The formatting string. If no matching format was found, this will be the same as the $format parameter.
	 */
	static public function translateFormat($format)
	{
		if (isset(self::$formats[$format])) {
			$format = self::$formats[$format];
		}
		return $format;
	}
	
	
	/**
	 * The date/time
	 * 
	 * @var integer
	 */
	private $timestamp;
	
	/**
	 * The timezone for this date/time
	 * 
	 * @var string
	 */
	private $timezone;
	
	
	/**
	 * Creates the date/time to represent
	 * 
	 * @throws fValidationException
	 * 
	 * @param  string $datetime  The date/time to represent
	 * @param  string $timezone  The timezone for the date/time. This causes the date/time to be interpretted as being in the specified timezone. If not specified, will default to timezone set by {@link fTimestamp::setDefaultTimezone()}.
	 * @return fTimestamp
	 */
	public function __construct($datetime, $timezone=NULL)
	{
		self::checkPHPVersion();
		
		$default_tz = date_default_timezone_get();
		
		if ($timezone) {
			if (!$this->isValidTimezone($timezone)) {
				fCore::toss('fValidationException', 'The timezone specified, ' . $timezone . ', is not a valid timezone');
			}
			
		} else {
			$timezone = $default_tz;
		}
		
		$this->timezone = $timezone;
		
		$timestamp = strtotime($datetime . ' ' . $timezone);
		if ($timestamp === FALSE || $timestamp === -1) {
			fCore::toss('fValidationException', 'The date/time specified, ' . $datetime . ', does not appear to be a valid date/time');
		}
		
		$this->timestamp = $timestamp;
	}
	
	
	/**
	 * Returns this date/time in the current default timezone
	 * 
	 * @return string  The 'Y-m-d H:i:s' format of this date/time in the current default timezone
	 */
	public function __toString()
	{
		return $this->format('Y-m-d H:i:s', self::getDefaultTimezone());
	}
	
	
	/**
	 * Changes the time by the adjustment specified
	 * 
	 * @throws fValidationException
	 * 
	 * @param  string $adjustment  The adjustment to make
	 * @return void
	 */
	public function adjust($adjustment)
	{
		if ($this->isValidTimezone($adjustment)) {
			$this->setTimezone($adjustment);
		} else {
			$this->timestamp = $this->makeAdustment($adjustment, $this->timestamp);
		}
	}
	
	
	/**
	 * Takes a date/time to pass to strtotime and interprets it using the current timestamp's timezone
	 * 
	 * @param  string $datetime  The datetime to interpret
	 * @return integer  The timestamp
	 */
	private function covertToTimestampWithTimezone($datetime)
	{
		$default_tz = date_default_timezone_get();
		date_default_timezone_set($this->timezone);
		$timestamp = strtotime($datetime);
		date_default_timezone_set($default_tz);
		return $timestamp;
	}
	
	
	/**
	 * Formats the date/time, with an optional adjustment of a relative date/time or a timezone
	 * 
	 * @throws fValidationException
	 * 
	 * @param  string $format      The {@link http://php.net/date date()} function compatible formatting string, or a format name from {@link fTimestamp::createFormat()}
	 * @param  string $adjustment  A temporary adjustment to make, can be a relative date/time amount or a timezone
	 * @return string  The formatted (and possibly adjusted) date/time
	 */
	public function format($format, $adjustment=NULL)
	{
		$format = self::translateFormat($format);
		
		$timestamp = $this->timestamp;
		
		// Handle an adjustment that is a timezone
		if ($adjustment && $this->isValidTimezone($adjustment)) {
			$default_tz = date_default_timezone_get();
			date_default_timezone_set($adjustment);
			
		} else {
			$default_tz = date_default_timezone_get();
			date_default_timezone_set($this->timezone);
		}
		
		// Handle an adjustment that is a relative date/time
		if ($adjustment && !$this->isValidTimezone($adjustment)) {
			$timestamp = $this->makeAdjustment($adjustment, $timestamp);
		}
		
		$formatted = date($format, $timestamp);
		
		date_default_timezone_set($default_tz);
		
		return $formatted;
	}
	
	
	/**
	 * Returns the approximate difference in time, discarding any unit of measure but the least specific.
	 * 
	 * The output will read like:
	 *  - "This timestamp is {return value} the provided one" when a timestamp it passed
	 *  - "This timestamp is {return value}" when no timestamp is passed and comparing with the current timestamp
	 * 
	 * Examples of output for a timestamp passed might be:
	 *  - 5 minutes after
	 *  - 2 hours before
	 *  - 2 days after
	 *  - at the same time
	 * 
	 * Examples of output for no timestamp passed might be:
	 *  - 5 minutes ago
	 *  - 2 hours ago
	 *  - 2 days from now
	 *  - 1 year ago
	 *  - right now
	 * 
	 * You would never get the following output since it includes more than one unit of time measurement:
	 *  - 5 minutes and 28 seconds
	 *  - 3 weeks, 1 day and 4 hours
	 * 
	 * Values that are close to the next largest unit of measure will be rounded up:
	 *  - 55 minutes would be represented as 1 hour, however 45 minutes would not
	 *  - 29 days would be represented as 1 month, but 21 days would be shown as 3 weeks
	 * 
	 * @param  fTimestamp $other_timestamp  The timestamp to create the difference with, passing NULL will get the difference with the current timestamp
	 * @return string  The fuzzy difference in time between the this timestamp and the one provided
	 */
	public function getFuzzyDifference(fTimestamp $other_timestamp=NULL)
	{
		$relative_to_now = FALSE;
		if ($other_timestamp === NULL) {
			$other_timestamp = new fTimestamp('now');
			$relative_to_now = TRUE;
		}
		
		$diff = $this->timestamp - $other_timestamp->format('U');
		
		if (abs($diff) < 10) {
			return ($relative_to_now) ? 'right now' : 'at the same time';
		}
		
		if ($relative_to_now) {
			$suffix = ($diff > 0) ? ' from now' : ' ago';
		} else {
			$suffix = ($diff > 0) ? ' after' : ' before';
		}
		
		$diff = abs($diff);
		
		$break_points = array(
			45         /* 45 seconds  */ => array(1,        'second'),
			2700       /* 45 minutes  */ => array(60,       'minute'),
			64800      /* 18 hours    */ => array(3600,     'hour'),
			432000     /* 5 days      */ => array(86400,    'day'),
			1814400    /* 3 weeks     */ => array(604800,   'week'),
			23328000   /* 9 months    */ => array(2592000,  'month'),
			2147483647 /* largest int */ => array(31536000, 'year')
		);
		
		foreach ($break_points as $break_point => $unit_info) {
			if ($diff > $break_point) { continue; }
			
			$unit_diff = round($diff/$unit_info[0]);
			$units     = fInflection::inflectOnQuantity($unit_diff, $unit_info[1], $unit_info[1] . 's');
			
			return $unit_diff . ' ' . $units . $suffix;
		}
	}
	
	
	/**
	 * Returns the difference between the two timestamps in seconds
	 * 
	 * @param  fTimestamp $other_timestamp  The timestamp to calculate the difference with, if NULL is passed will compare with current timestamp
	 * @return integer  The difference between the two timestamps in seconds, positive if $other_timestamp is before this time or negative if after
	 */
	public function getSecondsDifference(fTimestamp $other_timestamp=NULL)
	{
		if ($other_timestamp === NULL) {
			$other_timestamp = new fTimestamp('now');
		}
		
		return $this->timestamp - $other_timestamp->format('U');
	}
	
	
	/**
	 * Returns the timezone for this date/time
	 * 
	 * @return string  The timezone for thie date/time
	 */
	public function getTimezone()
	{
		return $this->timezone;
	}
	
	
	/**
	 * Checks to see if a timezone is valid
	 * 
	 * @param  string  $timezone   The timezone to check
	 * @param  integer $timestamp  The time to adjust
	 * @return integer  The adjusted timestamp
	 */
	private function isValidTimezone($timezone)
	{
		$default_tz = date_default_timezone_get();
		$valid_tz = @date_default_timezone_set($timezone);
		date_default_timezone_set($default_tz);
		return $valid_tz;
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
			fCore::toss('fValidationException', 'The adjustment specified, ' . $adjustment . ', does not appear to be a valid relative date/time measurement');
		}
		
		return $timestamp;
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
		$year  = ($year === NULL)  ? date('Y', $this->timestamp) : $year;
		$month = ($month === NULL) ? date('m', $this->timestamp) : $month;
		$day   = ($day === NULL)   ? date('d', $this->timestamp) : $day;
		
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
		
		$timestamp = $this->covertToTimestampWithTimezone($year . '-' . $month . '-' . $day . date(' H:i:s', $this->timestamp));
		
		if ($timestamp === FALSE || $timestamp === -1) {
			fCore::toss('fValidationException', 'The date specified, ' . $year . '-' . $month . '-' . $day . ', does not appear to be a valid date');
		}
		
		$this->timestamp = $timestamp;
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
		$year        = ($year === NULL)        ? date('Y', $this->timestamp) : $year;
		$week        = ($week === NULL)        ? date('W', $this->timestamp) : $week;
		$day_of_week = ($day_of_week === NULL) ? date('N', $this->timestamp) : $day_of_week;
		
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
		
		$timestamp = $this->covertToTimestampWithTimezone($year . '-01-01 ' . date('H:i:s', $this->timestamp) . ' +' . ($week-1) . ' weeks +' . ($day_of_week-1) . ' days');
		
		if ($timestamp === FALSE || $timestamp === -1) {
			fCore::toss('fValidationException', 'The ISO date specified, ' . $year . '-W' . $week . '-' . $day_of_week . ', does not appear to be a valid ISO date');
		}
		
		$this->timestamp = $timestamp;
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
		$hour   = ($hour === NULL)   ? date('H', $this->timestamp) : $hour;
		$minute = ($minute === NULL) ? date('i', $this->timestamp) : $minute;
		$second = ($second === NULL) ? date('s', $this->timestamp) : $second;
		
		if (!is_numeric($hour) || $hour < 0 || $hour > 23) {
			fCore::toss('fValidationException', 'The hour specified, ' . $hour . ', does not appear to be a valid hour');
		}
		if (!is_numeric($minute) || $minute < 0 || $minute > 59) {
			fCore::toss('fValidationException', 'The minute specified, ' . $minute . ', does not appear to be a valid minute');
		}
		if (!is_numeric($second) || $second < 0 || $second > 59) {
			fCore::toss('fValidationException', 'The second specified, ' . $second . ', does not appear to be a valid second');
		}
		
		settype($minute, 'integer');
		settype($second, 'integer');
		
		if ($minute < 10) { $minute = '0' . $minute; }
		if ($second < 10) { $second = '0' . $second; }
		
		$timestamp = $this->covertToTimestampWithTimezone(date('Y-m-d ', $this->timestamp) . $hour . ':' . $minute . ':' . $second);
		
		if ($timestamp === FALSE || $timestamp === -1) {
			fCore::toss('fValidationException', 'The time specified, ' . $time . ', does not appear to be a valid time');
		}
		
		$this->timestamp = $timestamp;
	}
	
	
	/**
	 * Changes the timezone for this date/time
	 * 
	 * @throws fValidationException
	 * 
	 * @param  string $timezone  The timezone for this date/time
	 * @return void
	 */
	public function setTimezone($timezone)
	{
		if (!$this->isValidTimezone($timezone)) {
			fCore::toss('fValidationException', 'The timezone specified, ' . $timezone . ', is not a valid timezone');
		}
		$this->timezone = $timezone;
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