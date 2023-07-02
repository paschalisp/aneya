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

use aneya\Core\CoreObject;

class Address extends CoreObject {
	#region Properties
	/** @var string */
	public $address;
	/** @var string */
	public $city;
	/** @var string */
	public $zipCode;
	/** @var string */
	public $state;
	/** @var string */
	public $stateTitle;
	/** @var int|string */
	public $county;
	/** @var int */
	public $countyTitle;
	/** @var int|string */
	public $province;
	/** @var string */
	public $provinceTitle;
	/** @var string Country code */
	public $country;
	/** @var string */
	public $countryTitle;
	#endregion

	#region Methods
	public function toHtml () {
		$address1 = implode (', ', [$this->address, $this->city]);
		$address2 = implode (', ', [$this->provinceTitle, $this->zipCode]);

		$address = [];
		if (strlen ($address1) > 0) {
			$address[] = $address1;
		}
		if (strlen ($address2) > 0) {
			$address[] = $address2;
		}

		return implode ('<br />', $address);
	}
	#endregion

	#region Magic methods
	public function __toString () {
		$address = [$this->address, $this->city, $this->zipCode, $this->provinceTitle, $this->countryTitle];

		return implode (', ', $address);
	}
	#endregion
}
