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

namespace aneya\M17N;

class Language {
	#region Properties
	/** @var string */
	public $code;
	/** @var string */
	public $abbr;
	/** @var string */
	public $name;
	/** @var string */
	public $locale;
	#endregion

	#region Constructor
	public function __construct(string $code = null, string $abbr = null, string $name = null, string $locale = null) {
		$this->code = $code;
		$this->abbr = $abbr;
		$this->name = $name;
		$this->locale = $locale;
	}
	#endregion

	#region Getters/Setters
	public function countryCode() {
		return strtolower(substr($this->locale ?? '', 3, 2));
	}
	#endregion
}
