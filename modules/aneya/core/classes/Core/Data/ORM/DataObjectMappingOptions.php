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

namespace aneya\Core\Data\ORM;


class DataObjectMappingOptions {
	#region Properties
	/** @var bool If true, ORM information generation will follow foreign-key constraints and will create sub-objects */
	public $followForeignKeys		= false;
	public $restrictToTables		= [];
	/** @var bool Indicates if underscore (_) should be replaced with the next character capitalized (e.g. first_name will be transported to firstName) */
	public $underscoreToCamelCase	= true;
	/** @var bool If true, Object's class will be mapped to the DataSet to generate objects of this class instead of plain \stdClass objects */
	public $mapClassNameToDataSet	= false;
	#endregion

	#region Constructor
	public function __construct(bool $followForeignKeys = false, $restrictToTables = [], bool $underscoreToCamelCase = true, bool $mapClassNameToDataSet = false) {
		$this->followForeignKeys = $followForeignKeys;
		$this->restrictToTables = $restrictToTables;
		$this->underscoreToCamelCase = $underscoreToCamelCase;
		$this->mapClassNameToDataSet = $mapClassNameToDataSet;
	}
	#endregion
}
