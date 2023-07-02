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
use aneya\Core\Data\DataFilter;
use aneya\Core\Data\DataRow;
use aneya\Core\Data\DataRowCollection;
use aneya\Core\Data\ORM\DataObject;
use aneya\Core\Data\ORM\DataObjectMapping;
use aneya\Core\Data\ORM\ORM;

class Country extends DataObject {
	#region Properties
	public ?string $code = null;
	public string $name = '';
	public string $languageCode = '';
	public ?string $locale = '';
	public ?string $telCode = '';

	/** @var DataRowCollection */
	protected static DataRowCollection $_countries;
	#endregion

	#region Constructor
	#endregion

	#region Methods
	#endregion

	#region Static methods
	/**
	 * @param $countryCode
	 *
	 * @return Country|null
	 */
	public static function get($countryCode): ?Country {
		$ds = static::ormSt()->dataSet();

		if (!isset(static::$_countries)) {
			$ds->clear()->retrieve();
			static::$_countries = $ds->rows;
		}

		$row = static::$_countries->match(new DataFilter($ds->columns->get('country_code'), DataFilter::Equals, $countryCode))->first();
		if ($row instanceof DataRow) {
			/** @var Country $obj */
			return $row->object();
		}

		return null;
	}
	#endregion

	#region ORM methods
	protected static function onORM(): ?DataObjectMapping {
		$ds = static::classDataSet(CMS::db()->schema->getDataSet('cms_helper_countries'));
		$ds->mapClass(static::class);
		$orm = ORM::dataSetToMapping($ds, static::class);

		$orm->getProperty('countryCode')->propertyName = 'code';

		return $orm;
	}
	#endregion
}
