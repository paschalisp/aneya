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

namespace aneya\Core\I18N;

use aneya\Core\CMS;

class Locale {
	#region Constants
	const DateTime		= 'DT';
	const DateOnly		= 'D-';
	const TimeOnly		= '-T';

	const LittleEndian	= 'DMY';
	const MiddleEndian	= 'MDY';
	const BigEndian		= 'YMD';

	const YearModeFull = 4;
	const YearModeHalf = 2;

	const HourMode24 = 24;
	const HourMode12 = 12;

	const Left	= 'L';
	const Right	= 'R';

	const LeftToRight	= 'LTR';
	const RightToLeft	= 'RTL';
	#endregion

	#region Properties
	public string $dateMode;
	public int $yearMode;
	public string $dateSeparator = '';

	public int $hourMode;
	public string $thousandSeparator;
	public string $decimalSeparator;
	public string $currencyCode;
	public string $currencyName;
	public string $currencyShortName;
	public string $currencySymbol;
	public string $currencySymbolPosition;

	public string $languageCode;
	public string $textDirection;

	/** @var string Date format to pass to date() function */
	protected string $dateFormat;
	/** @var string Time format to pass to date() function */
	protected string $timeFormat;
	/** @var string Date format to pass to strftime() function */
	protected string $strDateFormat;
	/** @var string Time format to pass to strftime() function */
	protected string $strTimeFormat;
	#endregion

	#region Constructor & loading
	/**
	 * @param ?string $dateMode
	 * @param ?int $yearMode
	 * @param ?int $hourMode
	 * @param ?string $dateSeparator
	 * @param ?string $thousandSeparator
	 * @param ?string $decimalSeparator
	 * @param ?string $currencyName
	 * @param ?string $currencyCode
	 * @param ?string $currencySymbol
	 * @param ?string $currencySymbolPosition
	 * @param ?string $textDirection
	 */
	public function __construct (?string $dateMode = Locale::LittleEndian, ?int $yearMode = Locale::YearModeFull, ?int $hourMode = Locale::HourMode24,
								 ?string $dateSeparator = '/', ?string $thousandSeparator = '.', ?string $decimalSeparator = ',',
								 ?string $currencyName = 'Euro', ?string $currencyCode = 'EUR', ?string $currencySymbol = '€', ?string $currencySymbolPosition = Locale::Right,
								 ?string $textDirection = Locale::LeftToRight) {
		$this->dateMode = $dateMode ?? Locale::LittleEndian;
		$this->yearMode = $yearMode ?? Locale::YearModeFull;
		$this->dateSeparator = $dateSeparator ?? '/';
		$this->hourMode = $hourMode ?? Locale::HourMode24;
		$this->thousandSeparator = $thousandSeparator ?? '.';
		$this->decimalSeparator = $decimalSeparator ?? ',';
		$this->currencyName = $currencyName ?? 'Euro';
		$this->currencyCode = $this->currencyShortName = $currencyCode ?? 'EUR';
		$this->currencySymbol = $currencySymbol ?? '€';
		$this->currencySymbolPosition = $currencySymbolPosition ?? Locale::Right;
		$this->textDirection = $textDirection ?? Locale::LeftToRight;

		$year = ($this->yearMode == Locale::YearModeFull) ? 'Y' : 'y';

		switch ($this->dateMode) {
			case self::LittleEndian	:
				$this->dateFormat = 'd' . $this->dateSeparator . 'm' . $this->dateSeparator . $year;
				$this->strDateFormat = '%d' . $this->dateSeparator . '%m' . $this->dateSeparator . '%' . $year;
				break;
			case self::MiddleEndian	:
				$this->dateFormat = 'm' . $this->dateSeparator . 'd' . $this->dateSeparator . $year;
				$this->strDateFormat = '%m' . $this->dateSeparator . '%d' . $this->dateSeparator . '%' . $year;
				break;
			case self::BigEndian	:
				$this->dateFormat = $year . $this->dateSeparator . 'm' . $this->dateSeparator . 'd';
				$this->strDateFormat = '%' . $year . $this->dateSeparator . '%m' . $this->dateSeparator . '%d';
				break;
		}

		switch ($this->hourMode) {
			case self::HourMode24:
				$this->timeFormat = 'H:i:s';
				$this->strTimeFormat = '%H:%M:%S';
				break;
			case self::HourMode12:
				$this->timeFormat = 'h:i:sA';
				$this->strTimeFormat = '%l:%M:%S%p';
				break;
		}
	}

	/**
	 * @param string|null $localeCode Locale's code in the form of language/country, i.e. 'en-US'
	 * @return Locale
	 * @throws \Exception
	 */
	public static function create(string $localeCode = null): Locale {
		if ($localeCode == null || strlen($localeCode) == 0)
			$localeCode = CMS::CMS_DEFAULT_LOCALE;

		// Try load the locale information from the configuration
		$options = CMS::cfg()->env->locales->$localeCode;

		if (!isset($options)) {
			// Take directly from configuration, as framework might not have been loaded yet.
			$schema = CMS::cfg()->db->cms->schema;
			$sql = "SELECT T1.locale, T1.date_mode, T1.year_mode, T1.hour_mode, T1.date_sep, T1.num_thousand_sep, T1.num_decimal_sep, T1.currency_code, T3.currency_name, T3.currency_symbol, T1.currency_pos, T2.language_code, T1.text_dir
				FROM $schema.cms_locales T1
				JOIN $schema.cms_helper_countries T2 ON T2.locale=T1.locale
				JOIN $schema.cms_currencies T3 ON T3.currency_code=T1.currency_code
				WHERE T2.locale=:locale";
			$options = CMS::db()->fetch($sql, [':locale' => $localeCode], \PDO::FETCH_OBJ);

			if (!$options) {
				throw new \Exception("Could not load locale for country code $localeCode.");
			}
		}

		$locale = new Locale($options->date_mode, $options->year_mode, $options->hour_mode, $options->date_sep, $options->num_thousand_sep, $options->num_decimal_sep, $options->currency_name, $options->currency_code, $options->currency_symbol, $options->currency_pos, $options->text_dir);
		$locale->languageCode = $options->language_code;

		return $locale;
	}
	#endregion

	#region Methods
	/**
	 * Returns a date/time formatted string based on the Locale's settings
	 *
	 * @param \DateTime|int    $timestamp
	 * @param string $show Indicates which part of timestamp to return. Valid values are Locale::DateTime, Locale::DateOnly, Locale::TimeOnly
	 * @return bool|string
	 */
	public function toDate($timestamp, string $show = Locale::DateOnly) {
		switch ($show) {
			case self::DateOnly	: $format = $this->dateFormat; break;
			case self::TimeOnly	: $format = $this->timeFormat; break;
			case self::DateTime	: $format = $this->dateFormat . ' ' . $this->timeFormat; break;
			default				: $format = '';
		}

		if ($timestamp instanceof \DateTime) {
			return $timestamp->format($format);
		}
		else {
			return date($format, $timestamp);
		}
	}

	/**
	 * Returns a time formatted string based on the Locale's settings
	 * @param \DateTime|int    $timestamp
	 * @return bool|string
	 */
	public function toTime($timestamp) {
		return $this->toDate($timestamp, self::TimeOnly);
	}

	/**
	 * Returns a DateTime object given a date/time string with format based on the Locale's settings
	 *
	 * @param string $timestamp
	 * @param string $show Indicates which part of timestamp to use for the conversion to DateTime. Valid values are Locale::DateTime|DateOnly|TimeOnly
	 * @return \DateTime
	 */
	public function toDateObj($timestamp, string $show = Locale::DateOnly): ?\DateTime {
		if (is_string($timestamp) && strlen($timestamp) == 0)
			return null;

		switch ($show) {
			case self::DateOnly : $format = $this->dateFormat; break;
			case self::TimeOnly : $format = $this->timeFormat; break;
			case self::DateTime : $format = $this->dateFormat . ' ' . $this->timeFormat; break;
			default:
				$format = '';
		}
		$date = \DateTime::createFromFormat($format, $timestamp, CMS::timezone());
		if ($date == false) {
			// Try fail-safe universal format
			switch ($show) {
				case self::DateOnly : $format = 'Y-m-d'; break;
				case self::TimeOnly : $format = (strlen($timestamp) == 5) ? 'H:i' : 'H:i:s'; break;
				case self::DateTime : $format = 'Y-m-d H:i:s'; break;
				default:
					$format = '';
			}
			$date = \DateTime::createFromFormat($format, $timestamp, CMS::timezone());
			if ($date == false) {
				// 2nd-level fail-safe format for time
				if ($show == self::TimeOnly && strlen($timestamp) == 8) {
					$format = 'H:i A';
					$date = \DateTime::createFromFormat($format, $timestamp, CMS::timezone());
				}

				if ($date == false)
					CMS::logger()->debug("Could not convert string '$timestamp' to DateTime using format '$format'");
			}
		}

		// Set same date to all time objects to allow correct comparisons
		if ($date instanceof \DateTime && $show == Locale::TimeOnly) {
			$date->setDate(2000, 1, 1);
		}

		return ($date instanceof \DateTime) ? $date : null;
	}

	/**
	 * Returns a numerically formatted string based on the Locale's settings
	 *
	 * @param float|int $number
	 * @param int   $decimals
	 * @return string
	 */
	public function toNumber($number, int $decimals = 0): string {
		return number_format($number, $decimals, $this->decimalSeparator, $this->thousandSeparator);
	}

	/**
	 * Returns a currency-formatted string based on the Locale's settings
	 *
	 * @param float|int $amount
	 * @param int   $decimals
	 * @return string
	 */
	public function toCurrency($amount, int $decimals = 2): string {
		return ($amount < 0 ? '-' : '') . ($this->currencySymbolPosition == self::Left ? $this->currencySymbol : '') . $this->toNumber(abs($amount), $decimals) . ($this->currencySymbolPosition == self::Right ? $this->currencySymbol : '');
	}

	/**
	 * Returns the date string format that should be used for this locale, compatible to date() function's format specifications
	 * @return string
	 */
	public function dateFormat(): string {
		return $this->dateFormat;
	}

	/**
	 * Returns the time string format that should be used for this locale, compatible to date() function's format specifications
	 * @return string
	 */
	public function timeFormat(): string {
		return $this->timeFormat;
	}

	/**
	 * Returns the date & time string format that should be used for this locale, compatible to date() function's format specifications
	 * @return string
	 */
	public function dateTimeFormat(): string {
		return $this->dateFormat() . ' ' . $this->timeFormat();
	}

	/**
	 * Converts a PHP-formatted date/time format and returns the Moment.js equivalent date/time format for use in Javascript.
	 *
	 * @param string $format
	 *
	 * @return string
	 */
	public function toMomentFormat(string $format): string {
		$moment = $format;

		#region Build Moment.js compatible format
		$moment = str_replace('jS', 'Do', $moment);    // Day with suffix [1st, 16th]
		$moment = str_replace('j', 'DD', $moment);     // Day, no leading zeros [1, 16]
		$moment = str_replace('d', 'DD', $moment);     // Day, leading zeros [01, 16]
		$moment = str_replace('M', 'MMM', $moment);    // Month name 3 chars [Jan, Feb]
		$moment = str_replace('n', 'M', $moment);      // Month number, no leading zero [1, 7, 12]
		$moment = str_replace('m', 'MM', $moment);     // Month number, leading zero [01, 07, 12]
		$moment = str_replace('F', 'MMMM', $moment);   // Month name [January, February]
		$moment = str_replace('Y', 'YYYY', $moment);   // Year number, 4 digits [1978, 1984, 2015]
		$moment = str_replace('y', 'YY', $moment);     // Year number, 2 digits [78, 84, 15]

		$moment = str_replace('a', 'a', $moment);      // Meridian [am, pm]
		$moment = str_replace('A', 'A', $moment);      // Meridian [AM, PM]
		$moment = str_replace('g', 'h', $moment);      // Hours 12h format, no leading zero [1, 9, 12]
		$moment = str_replace('G', 'H', $moment);      // Hours 24h format, no leading zero [1, 9, 18]
		$moment = str_replace('h', 'hh', $moment);     // Hours 12h format, with leading zero [01, 09, 12]
		$moment = str_replace('H', 'HH', $moment);     // Hours 24h format, with leading zero [01, 09, 18]
		$moment = str_replace('i', 'mm', $moment);     // Minutes [00, 09, 39]
		$moment = str_replace('s', 'ss', $moment);     // Seconds [00, 09, 39]
		#endregion

		return $moment;
	}

	/**
	 * Converts a PHP-formatted date/time format and returns the Element UI DateTime picker equivalent date/time format for use in Javascript.
	 *
	 * @param string $format
	 *
	 * @return string
	 */
	public function toDatetimePickerFormat(string $format): string {
		$moment = $format;

		#region Build Javascript/Element UI compatible format
		$moment = str_replace('j', 'd', $moment);     // Day, no leading zeros [1, 16]
		$moment = str_replace('d', 'dd', $moment);     // Day, leading zeros [01, 16]
		$moment = str_replace('n', 'M', $moment);      // Month number, no leading zero [1, 7, 12]
		$moment = str_replace('m', 'MM', $moment);     // Month number, leading zero [01, 07, 12]
		$moment = str_replace('Y', 'yyyy', $moment);   // Year number, 4 digits [1978, 1984, 2015]
		$moment = str_replace('yyyy', 'yyyy', $moment);     // Year number, 2 digits [78, 84, 15]

		$moment = str_replace('a', 'a', $moment);      // Meridian [am, pm]
		$moment = str_replace('A', 'A', $moment);      // Meridian [AM, PM]
		$moment = str_replace('g', 'h', $moment);      // Hours 12h format, no leading zero [1, 9, 12]
		$moment = str_replace('G', 'H', $moment);      // Hours 24h format, no leading zero [1, 9, 18]
		$moment = str_replace('h', 'hh', $moment);     // Hours 12h format, with leading zero [01, 09, 12]
		$moment = str_replace('H', 'HH', $moment);     // Hours 24h format, with leading zero [01, 09, 18]
		$moment = str_replace('i', 'mm', $moment);     // Minutes [00, 09, 39]
		$moment = str_replace('s', 'ss', $moment);     // Seconds [00, 09, 39]
		#endregion

		return $moment;
	}
	#endregion

	#region Static methods
	#endregion
}
