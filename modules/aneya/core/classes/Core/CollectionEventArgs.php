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

namespace aneya\Core;


class CollectionEventArgs extends EventArgs {
	/** The action that triggered the event. Valid values are Collection::ActionItem* */
	public ?string $action;

	/**
	 * If action is 'add', this property will contain the newly added item
	 * If action is 'update', this property will contain the updated item
	 *
	 * @var mixed
	 */
	public $newItem;

	/**
	 * If action is 'update', this property will contain the previous item
	 * If action is 'delete', this property will contain the item that was removed from the collection
	 *
	 * @var mixed
	 */
	public $oldItem;

	/**
	 * @param mixed	$sender
	 * @param string|null $action	The action that triggered the event. Valid values are Collection::ActionItem*
	 * @param mixed	$newItem	The newly added (if action is 'add') or the updated item (if action is 'update')
	 * @param mixed	$oldItem The deleted item (if action is 'delete') or the previous item before gets updated (if action is 'update')
	 */
	public function __construct ($sender = null, string $action = null, $newItem = null, $oldItem = null) {
		parent::__construct($sender);

		$this->action = $action;
		$this->newItem = $newItem;
		$this->oldItem = $oldItem;
	}
}
