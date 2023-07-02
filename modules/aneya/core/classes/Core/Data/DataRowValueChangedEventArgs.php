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

namespace aneya\Core\Data;

use aneya\Core\EventArgs;

class DataRowValueChangedEventArgs extends EventArgs {
	#region Properties
	/** @var mixed */
	public $oldValue;
	/** @var mixed */
	public $newValue;
	public ?int $columnIndex;
	public ?DataColumn $column;
	#endregion

	#region Constructor
	/**
	 * @param mixed $sender
	 * @param mixed $newValue
	 * @param mixed $oldValue
	 * @param ?DataColumn $column
	 * @param ?int $columnIndex
	 */
	public function __construct ($sender = null, $newValue = null, $oldValue = null, DataColumn $column = null, int $columnIndex = null) {
		parent::__construct($sender);

		$this->newValue = $newValue;
		$this->oldValue = $oldValue;
		$this->column = $column;
		$this->columnIndex = $columnIndex;
	}
	#endregion
}
