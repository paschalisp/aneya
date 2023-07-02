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

class DataRowSaveEventArgs extends EventArgs {
	#region Properties
	/** @var ?string The database action that takes place. Valid values are DataRow::Action* constants. */
	public ?string $action;

	public ?DataRow $row;
	#endregion

	#region Constructor
	/**
	 * @param ?DataRow $sender
	 * @param ?string $action One of the following DataRow states: DataRowState::StateAdded, DataRowState::StateModified, DataRowState::StateDeleted
	 */
	public function __construct (DataRow $sender = null, string $action = null) {
		parent::__construct($this->row = $sender);

		$this->action = $action;
	}
	#endregion
}
