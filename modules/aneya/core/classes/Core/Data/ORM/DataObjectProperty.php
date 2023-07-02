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
 * Portions created by Paschalis Ch. Pagonidis are Copyright (C) 2007-2022
 * Paschalis Ch. Pagonidis. All Rights Reserved.
 */

namespace aneya\Core\Data\ORM;


use aneya\Core\Data\DataColumn;

class DataObjectProperty {
	#region Properties
	/** @var int */
	public $id = null;
	/** @var int */
	public $objectId = null;
	/** @var string */
	public $fieldName = '';
	/** @var string */
	public $propertyName = '';
	/** @var string The fully qualified class name of the objects that the property holds (for Object data types only) */
	public $valueClass;

	/** @var DataColumn */
	public $column;
	#endregion

	#region Constructor
	public function __construct ($fieldName, $propertyName, DataColumn $column = null) {
		$this->fieldName = $fieldName;
		$this->propertyName = $propertyName;
		$this->column = $column;
	}
	#endregion

	#region Magic methods
	public function __toString () {
		return $this->propertyName;
	}
	#endregion
}
