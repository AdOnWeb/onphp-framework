<?php
/***************************************************************************
 *   Copyright (C) 2006-2007 by Garmonbozia Research Group                 *
 *   Anton E. Lebedevich, Konstantin V. Arkhipov                           *
 *                                                                         *
 *   This program is free software; you can redistribute it and/or modify  *
 *   it under the terms of the GNU General Public License as published by  *
 *   the Free Software Foundation; either version 2 of the License, or     *
 *   (at your option) any later version.                                   *
 *                                                                         *
 ***************************************************************************/
/* $Id$ */

	/**
	 * Date's container and utilities.
	 *
	 * @see DateRange
	 * 
	 * @ingroup Base
	**/
	class Date implements Stringable, DialectString
	{
		const WEEKDAY_MONDAY 	= 1;
		const WEEKDAY_TUESDAY	= 2;
		const WEEKDAY_WEDNESDAY	= 3;
		const WEEKDAY_THURSDAY	= 4;
		const WEEKDAY_FRIDAY	= 5;
		const WEEKDAY_SATURDAY	= 6;
		const WEEKDAY_SUNDAY	= 0; // because strftime('%w') is 0 on Sunday
		
		protected $string	= null;
		protected $int		= null;
		
		protected $year		= null;
		protected $month	= null;
		protected $day		= null;
		
		/**
		 * @return Date
		**/
		public static function create($date)
		{
			return new self($date);
		}
		
		public static function today($delimiter = '-')
		{
			return date("Y{$delimiter}m{$delimiter}d");
		}
		
		/**
		 * @return Date
		**/
		public static function makeToday()
		{
			return new self(self::today());
		}
		
		public static function dayDifference(Date $left, Date $right)
		{
			return 
				gregoriantojd(
					$right->getMonth(),
					$right->getDay(),
					$right->getYear()
				)
				- gregoriantojd(
					$left->getMonth(), 
					$left->getDay(), 
					$left->getYear()
				);
		}
		
		public function __construct($date)
		{
			if (is_int($date)) { // unix timestamp
				$this->int = $date;
				$this->string = date($this->getFormat(), $date);
			} elseif (is_string($date)) { 
				$this->int = strtotime($date);
				
				if (preg_match('/^\d{1,4}-\d{1,2}-\d{1,2}$/', $date))
					$this->string = $date;
				else
					$this->string = date($this->getFormat(), $this->int);
			} else {
				throw new WrongArgumentException(
					"strange date given - '{$date}'"
				);
			}
			
			$this->import($this->string);
		}
		
		public function toStamp()
		{
			return $this->int;
		}
		
		public function toDate($delimiter = '-')
		{
			return
				$this->year
				.$delimiter
				.$this->month
				.$delimiter
				.$this->day;
		}
		
		public function getYear()
		{
			return $this->year;
		}

		public function getMonth()
		{
			return $this->month;
		}

		public function getDay()
		{
			return $this->day;
		}
		
		public function getWeek()
		{
			return date('W', $this->int);
		}

		public function getWeekDay()
		{
			return strftime('%w', $this->int);
		}
		
		/**
		 * @return Date
		**/
		public function spawn($modification = null)
		{
			$child = new $this($this->string);
			
			if ($modification)
				return $child->modify($modification);
			
			return $child;
		}
		
		/**
		 * @throws WrongArgumentException
		 * @return Date
		**/
		public function modify($string)
		{
			try {
				$time = strtotime($string, $this->int);
				
				if ($time === false)
					throw new WrongArgumentException(
						"modification yielded false '{$string}'"
					);
				
				$this->int = $time;
				$this->string = date($this->getFormat(), $time);
				$this->import($this->string);
			} catch (BaseException $e) {
				throw new WrongArgumentException(
					"wrong time string '{$string}'"
				);
			}
			
			return $this;
		}
		
		public function getDayStartStamp()
		{
			return
				mktime(
					0, 0, 0,
					$this->month,
					$this->day,
					$this->year
				);
		}
		
		public function getDayEndStamp()
		{
			return
				mktime(
					23, 59, 59,
					$this->month,
					$this->day,
					$this->year
				);
		}
		
		/**
		 * @return Date
		**/
		public function getFirstDayOfWeek($weekStart = Date::WEEKDAY_MONDAY)
		{
			return $this->spawn(
				'-'.((7 + $this->getWeekDay() - $weekStart) % 7).' days'
			);
		}
		
		/**
		 * @return Date
		**/
		public function getLastDayOfWeek($weekStart = Date::WEEKDAY_MONDAY)
		{
			return $this->spawn(
				'+'.((13 - $this->getWeekDay() + $weekStart) % 7).' days'
			);
		}
		
		public function toString()
		{
			return $this->string;
		}
		
		public function toDialectString(Dialect $dialect)
		{
			// there are no known differences yet
			return $dialect->quoteValue($this->toString());
		}
		
		protected static function getFormat()
		{
			return 'Y-m-d';
		}
		
		/* void */ protected function import($string)
		{
			list($this->year, $this->month, $this->day) =
				explode('-', $string, 3);
			
			$this->normalizeSelf();
		}
		
		/* void */ protected function normalizeSelf()
		{
			if (strlen($this->year) < 4)
				$this->year = str_pad($this->year, 4, '0', STR_PAD_LEFT);
			
			if (strlen($this->month) < 2)
				$this->month = str_pad($this->month, 2, '0', STR_PAD_LEFT);
			
			if (strlen($this->day) < 2)
				$this->day = str_pad($this->day, 2, '0', STR_PAD_LEFT);
		}
	}
?>