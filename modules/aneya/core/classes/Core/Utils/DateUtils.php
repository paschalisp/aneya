<?php
/*
 * aneya CMS & Framework
 * Copyright (c) 2007-2022 Paschalis Pagonidis <p.pagonides@gmail.com>
 * All rights reserved.
 * -----------------------------------------------------------------------------
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 * -----------------------------------------------------------------------------
 * The Sole Developer of the Original Code is Paschalis Ch. Pagonidis
 * Portions created by Paschalis Ch. Pagonidis are Copyright (c) 2007-2022
 * Paschalis Ch. Pagonidis. All Rights Reserved.
 */

namespace aneya\Core\Utils;

use aneya\Core\CMS;

class DateUtils {
	#region Static methods
	public static function convertDate($date, $fromFormat, $toFormat) {
		$d = \DateTime::createFromFormat($fromFormat, $date);
		if (!$d)
			return '';

		return $d->format($toFormat);
	}

	/**
	 * Converts a datetime string into human readable string that represents the time elapsed between the datetime up to present.
	 *
	 * @param string $time
	 *
	 * @return string
	 */
	public static function humanTimeElapsed($time) {
		$m17n = CMS::translator();
		$time = time() - strtotime($time);

		$tokens = array (
			31536000 => array ($m17n->translate('year', 'cms'),   $m17n->translate('years', 'cms')),
			2592000  => array ($m17n->translate('month', 'cms'),  $m17n->translate('months', 'cms')),
			604800   => array ($m17n->translate('week', 'cms'),   $m17n->translate('weeks', 'cms')),
			86400    => array ($m17n->translate('day', 'cms'),    $m17n->translate('days', 'cms')),
			3600     => array ($m17n->translate('hour', 'cms'),   $m17n->translate('hours', 'cms')),
			60       => array ($m17n->translate('minute', 'cms'), $m17n->translate('minutes', 'cms')),
			1        => array ($m17n->translate('second', 'cms'), $m17n->translate('seconds', 'cms'))
		);

		$cnt = 0;
		$tr = array ('', '');
		foreach ($tokens as $step => $label) {
			if ($time < $step) continue;
			$cnt = floor($time / $step);
			$tr = $label;
			break;
		}
		$label = ($cnt > 1) ? $tr[1] : $tr[0];
		return "$cnt $label";
	}

	/**
	 * Returns true if given date is valid based on the provided set of date formats
	 *
	 * @param string       $date
	 * @param array|string $formats
	 *
	 * @return bool
	 */
	public static function isDate($date, $formats = array ("Y-m-d", "d.m.Y", "d/m/Y", "Ymd")) {
		if (is_string($formats))
			$formats = array ($formats);

		foreach ($formats as $fmt) {
			$d = \DateTime::createFromFormat($fmt, $date);
			if ($d && $d->format($fmt) == $date)
				return true;
		}

		return false;
	}

	#region MySQL/MariaDB support
	/**
	 * Converts a MySQL date/datetime field into a PHP date
	 *
	 * @param string $date   The MySQL date/datetime value
	 * @param string $format The returned date format (see PHP's date() functions for available options)
	 *
	 * @return string The formatted date
	 */
	public static function mysqldate($date, $format = "d-m-Y") {
		return (substr($date, 0, 10) == '0000-00-00') ? '' : date($format, strtotime($date));
	}
	#endregion

	#region Moment.js support
	/**
	 * Converts a moment.js date string format into a format compatible with PHP date() command.
	 *
	 * @param string $format The moment.js compatible date string format
	 *
	 * @return string
	 */
	public static function fromMomentDate($format) {
		$matches = [
			// Day conversions
			'DD'   => 'd',
			'ddd'  => 'D',
			'D'    => 'j',
			'dddd' => 'l',
			'E'    => 'N',
			'o'    => 'S',
			'e'    => 'w',
			'DDD'  => 'z',

			// Week conversions
			'W'    => 'W',

			// Month conversions
			'MMMM' => 'F',
			'MM'   => 'm',
			'MMM'  => 'M',
			'M'    => 'n',

			// Year conversions
			'YYYY' => 'Y',
			'YY'   => 'y',

			// Time conversions
			'a'    => 'a',
			'A'    => 'A',
			'h'    => 'g',
			'H'    => 'G',
			'hh'   => 'h',
			'HH'   => 'H',
			'mm'   => 'i',
			'ss'   => 's',
			'SSS'  => 'u',

			// Timezone conversions
			'X'    => 'U'
		];
		return strtr($format, $matches);
	}

	/**
	 * Converts a PHP date() string format into a format compatible with moment.js
	 *
	 * @param string $format The PHP date() compatible string format
	 *
	 * @return string
	 */
	public static function toMomentDate($format) {
		$matches = [
			// Day conversions
			'd' => 'DD',
			'D' => 'ddd',
			'j' => 'D',
			'l' => 'dddd',
			'N' => 'E',
			'S' => 'o',
			'w' => 'e',
			'z' => 'DDD',

			// Week conversions
			'W' => 'W',

			// Month conversions
			'F' => 'MMMM',
			'm' => 'MM',
			'M' => 'MMM',
			'n' => 'M',
			't' => '',        // No support for number of days in month

			// Year conversions
			'L' => '',        // No support for leap year indication
			'o' => 'YYYY',
			'Y' => 'YYYY',
			'y' => 'YY',

			// Time conversions
			'a' => 'a',
			'A' => 'A',
			'B' => '',        // No support for Swatch Internet time
			'g' => 'h',
			'G' => 'H',
			'h' => 'hh',
			'H' => 'HH',
			'i' => 'mm',
			's' => 'ss',
			'u' => 'SSS',
			'v' => '',        // No support for milliseconds

			// Timezone conversions
			'e' => '',        // No support for string-based timezone identifier
			'I' => '',        // No support for daylight saving time
			'O' => '',        // No support for GMT difference
			'P' => '',        // No support for GMT difference
			'T' => '',        // No support for timezone abbreviation
			'Z' => '',        // No support for timezone offset in sec
			'c' => '',        // No support for ISO 8601 date format
			'r' => '',        // No support for RFC 2822 date format
			'U' => 'X'
		];
		return strtr($format, $matches);
	}
	#endregion

	#region Javascript/JSON support
	/**
	 * Converts a Javascript-formatted date into PHP \DateTime
	 */
	public static function fromJsDate(string $date): ?\DateTime {
		// 1st guess
		$ret = \Datetime::createFromFormat('D, d M Y H:i:s O', $date);	// DateTime::RFC2822
		if ($ret instanceof \DateTime)
			return $ret;

		// 2nd guess
		$ret = \Datetime::createFromFormat('Y-m-d\TH:i:sP', $date);		// DateTime::RFC3339
		if ($ret instanceof \DateTime)
			return $ret;

		// 3rd guess
		$ret = \Datetime::createFromFormat('Y-m-d\TH:i:sO', $date);		// DateTime::ISO8601
		if ($ret instanceof \DateTime)
			return $ret;

		// 4th guess
		$ret = \Datetime::createFromFormat('Y-m-d H:i:s', $date);
		if ($ret instanceof \DateTime)
			return $ret;

		// 5th guess
		$timestamp = strtotime($date);
		if ($timestamp!== false) {
			return new \DateTime("@$timestamp");
		}

		return null;
	}

	/**
	 * Converts a PHP \DateTime into Javascript-formatted date
	 */
	public static function toJsDate(\DateTime $date): string {
		return $date->format('D, d M Y H:i:s O');
	}

	/**
	 * Returns a \DateInterval representation that equals to the provided time lapse in seconds.
	 */
	public static function toDateInterval(int $seconds): \DateInterval {
		$str = 'P';

		if (($val = floor($seconds / 31536000)) > 0) {	// One year in seconds
			$str .= (int)$val . 'Y';
			$seconds = 31536000 % $seconds;
		}
		if (($val = floor($seconds / 2592000)) > 0) {	// One month in seconds
			$str .= (int)$val . 'M';
			$seconds = 2592000 % $seconds;
		}
		if (($val = floor($seconds / 86400)) > 0) {		// One day in seconds
			$str .= (int)$val . 'D';
			$seconds = 86400 % $seconds;
		}
		if ($seconds > 0) {
			$str .= 'T';

			if (($val = floor($seconds / 3600)) > 0) {		// One hour in seconds
				$str .= (int)$val . 'H';
				$seconds = 3600 % $seconds;
			}
			if (($val = floor($seconds / 60)) > 0) {		// One minute in seconds
				$str .= (int)$val . 'M';
				$seconds = 60 % $seconds;
			}
			if ($seconds > 0)								// The remaining seconds
				$str .= (int)$val . 'S';
		}

		/** @var \DateInterval $dt */
		$dt = null;
		try {
			$dt = new \DateInterval($str);
		}
		catch (\Exception $e) {}

		return $dt;
	}
	#endregion
	#endregion
}
