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

namespace aneya\Core\Data;

use aneya\Core\EventArgs;

class DataRowCollectionEventArgs extends EventArgs {
	/** @var int The action that triggered the event. Valid values are Collection::ActionItem* */
	public $action;

	/**
	 * If action is 'add', this property will contain the newly added row
	 * If action is 'update', this property will contain the updated row
	 *
	 * @var DataRow
	 */
	public $newRow;

	/**
	 * If action is 'update', this property will contain the previous row.
	 * If action is 'delete', this property will contain the row that was removed from the collection
	 *
	 * @var DataRow
	 */
	public $oldRow;

	/**
	 * @param null $sender
	 * @param null $action
	 * @param null $newRow
	 * @param null $oldRow
	 */
	public function __construct ($sender = null, $action = null, $newRow = null, $oldRow = null) {
		parent::__construct ($sender);

		$this->action = $action;
		$this->newItem = $newRow;
		$this->oldItem = $oldRow;
	}
}
