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

namespace aneya\Core\Data\Schema;


class Relation {
	#region Constants
	const ACTION_SET_NULL = 'SN';
	const ACTION_NO_ACTION = 'NA';
	const ACTION_CASCADE = 'CS';
	const ACTION_RESTRICT = 'RS';
	#endregion

	#region Properties
	/** @var string */
	public $masterTable;

	/** @var string */
	public $masterField;

	/** @var string */
	public $foreignTable;

	/** @var string */
	public $foreignField;

	public $onUpdate;

	public $onDelete;
	#endregion

	#region Constructor
	/**
	 * @param string $masterTable
	 * @param string $masterField
	 * @param string $foreignTable
	 * @param string $foreignField
	 */
	public function __construct ($masterTable, $masterField, $foreignTable, $foreignField) {
		$this->masterTable = $masterTable;
		$this->masterField = $masterField;
		$this->foreignTable = $foreignTable;
		$this->foreignField = $foreignField;
	}
	#endregion
}
