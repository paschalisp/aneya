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
use aneya\Core\IStorable;

class DataRowDelegateEventArgs extends EventArgs {
	/** @var object|IStorable The object to which row's functionality will be delegated to */
	public $object;

	/** @var ?DataRow The row whose functionality will be delegated */
	public ?DataRow $row;

	/**
	 * @param object	$sender
	 * @param IStorable $object
	 * @param ?DataRow	$row
	 */
	public function __construct ($sender = null, $object = null, DataRow $row = null) {
		parent::__construct ($sender);

		$this->object = $object;
		$this->row = $row;
	}
}
