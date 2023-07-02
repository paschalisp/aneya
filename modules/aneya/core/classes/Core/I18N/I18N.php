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

namespace aneya\Core;

use aneya\Core\Data\DataColumn;

final class I18N {
	#region Properties
	protected static $_locales = null;
	#endregion

	#region Locale methods
	public static function getLocales() {
		if (isset (self::$_locales))
			return self::$_locales;

		// Take directly from configuration, as framework might not have been loaded yet.
		$schema = CMS::cfg()->db->cms->schema;
		$quote = CMS::db()->quoteChar;
		$true = in_array(DataColumn::DataTypeBoolean, CMS::db()::SupportedDataTypes) ? 'true' : 1;
		$sql = "SELECT T1.locale, T1.date_mode AS dateMode, T1.date_sep AS " . $quote . "dateSeparator$quote, T1.year_mode as " . $quote . "yearDigits$quote, T1.num_thousand_sep AS " . $quote . "thousandSeparator$quote, T1.num_decimal_sep AS " . $quote . "decimalSeparator$quote,
					T2.currency_name AS " . $quote . "currencyName$quote, T2.currency_symbol AS " . $quote . "currencySymbol$quote, T1.currency_pos AS " . $quote . "currencySymbolPosition$quote,
					T3.language_code AS " . $quote . "languageCode$quote, T3.name AS " . $quote . "languageName$quote, T4.country_code AS " . $quote . "countryCode$quote, T4.name AS " . $quote . "countryName$quote
				FROM $schema.cms_locales T1
                JOIN $schema.cms_helper_countries T4 ON T4.locale=T1.locale
				JOIN $schema.cms_languages T3 ON T3.language_code=T4.language_code
				LEFT JOIN $schema.cms_currencies T2 ON T2.currency_code=T1.currency_code
				WHERE T1.is_enabled=$true AND T3.is_enabled=$true";
		return self::$_locales = CMS::db()->fetchAll($sql, null, null, null, \PDO::FETCH_OBJ);
	}

	public static function getLocaleByCode($code) {
		$locales = self::getLocales();

		foreach ($locales as $l) {
			if ($l->locale == $code) {
				return $l;
			}
		}

		return null;
	}

	public static function getLocaleByCountryCode($countryCode) {
		$locales = self::getLocales();

		foreach ($locales as $l) {
			if ($l->countryCode == $countryCode) {
				return $l;
			}
		}

		return null;
	}

	public static function getDefaultLocale() {
		return CMS::cfg()->env->locale;
	}

	public static function getCurrentLocale() {
		$locale = false;

		if (isset ($_REQUEST['__i18n_locale'])) {
			$locales = self::getLocales();

			foreach ($locales as $l)
				if (strtolower($l->locale) == strtolower($_REQUEST['__i18n_locale'])) {
					$locale = $l->locale;
					break;
				}
		}

		if (!$locale) {
			if (strlen($locale = CMS::session()->get('__i18n_locale') ?? '') !== 5)
				$locale = self::getDefaultLocale();
		}

		if (strlen($locale) > 0)
			CMS::session()->set('__i18n_locale', $locale);

		return $locale;
	}

	public static function getCurrentCountryCode() {
		$locale = self::getCurrentLocale();

		return strtolower(substr($locale, 3, 2));
	}

	public static function setLocale(\stdClass|string $locale) {
		if ($locale instanceof \stdClass && property_exists($locale, 'locale') && property_exists($locale, 'languageCode') && strlen($locale->locale) == 5) {
			CMS::session()->set('__i18n_locale', $locale->locale);
		}
		elseif (strlen($locale) == 5) {
			CMS::session()->set('__i18n_locale', $locale);

			$locale = self::getLocaleByCode($locale);
		}

		if ($locale && CMS::translator()->currentLanguage()->code !== $locale->languageCode)
			CMS::translator()->setCurrentLanguage($locale->languageCode);
	}
	#endregion
}
