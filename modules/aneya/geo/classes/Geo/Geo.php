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

namespace aneya\Geo;

use aneya\Core\CMS;
use aneya\Core\Data\DataSorting;
use aneya\Core\KeyValue;
use aneya\Core\KeyValueCollection;

final class Geo {
	#region Properties
	/** @var KeyValueCollection[] */
	protected static $_regions;
	/** @var Country[] */
	protected static $_countries;
	#endregion

	#region Static methods
	/**
	 * Returns the Country object given the country code.
	 *
	 * @param string $code
	 *
	 * @return Country|null
	 */
	public static function country($code) {
		foreach (static::countries() as $country) {
			if ($country->code == $code)
				return $country;
		}

		return null;
	}

	/**
	 * Returns an array of all countries instantiated
	 * @return Country[]
	 */
	public static function countries() {
		if (self::$_countries === null) {
			$ds = CMS::db()->schema->getDataSet('cms_helper_countries');
			$ds->mapClass('\\aneya\\Geo\\Country');
			$ds->autoGenerateObjects = true;
			$ds->retrieve(null, new DataSorting($ds->columns->get('name'), DataSorting::Ascending));

			self::$_countries = $ds->objects();
		}

		return self::$_countries;
	}

	/**
	 * Returns a collection of regions for the given country code.
	 * Default region for the country has an extra property isDefault set to true.
	 *
	 * @param $countryCode
	 * @return KeyValueCollection
	 */
	public static function regions($countryCode) {
		if (!isset (self::$_regions[$countryCode])) {
			self::$_regions[$countryCode] = new KeyValueCollection();

			$sql = "SELECT region_id, region, is_default FROM cms_helper_regions WHERE country_code=:country_code ORDER BY is_default DESC, region ASC";
			$rows = CMS::db()->fetchAll($sql, array(':country_code' => $countryCode));
			if ($rows) {
				foreach ($rows as $row) {
					$kv = new KeyValue ((int)$row['region_id'], $row['region']);
					// Add an unnamed property isDefault to flag the default region for this country
					if ($row['is_default']) {
						$kv->isDefault = true;
					}

					self::$_regions[$countryCode]->add($kv);
				}
			}
		}

		return self::$_regions[$countryCode];
	}

	/**
	 * Returns a stdClass with properties lat/long if query was successful; false otherwise.
	 * @param string $address
	 * @param string $apiKey
	 * @return \stdClass|bool
	 */
	public static function getLatLongByAddress($address, $apiKey) {
		$address = urlencode($address);
		$geocodeURL = "https://maps.googleapis.com/maps/api/geocode/json?address=$address&sensor=false&key=$apiKey";
		$ch = curl_init($geocodeURL);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		$result = curl_exec($ch);
		$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);
		if ($httpCode == 200) {
			$geocode = json_decode($result);
			if ($geocode->status != 'OK')
				return false;

			return $geocode->results[0]->geometry->location;
		} else
			return false;
	}
	#endregion
}
