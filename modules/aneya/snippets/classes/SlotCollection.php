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

namespace aneya\Snippets;

use aneya\Core\Collection;

class SlotCollection extends Collection {
	#region Properties
	/**
	 * @var Slot[]
	 */
	protected array $_collection;
	#endregion

	#region Constructor
	public function __construct() {
		parent::__construct('\\aneya\\Snippets\\Slot');

		$numArgs = func_num_args();
		if ($numArgs > 0) {
			$this->tag = func_get_arg(0);
		}
	}
	#endregion

	#region Methods
	/**
	 * @inheritdoc
	 * @return Slot[]
	 */
	public function all(callable $f = null): array {
		return parent::all($f);
	}

	/**
	 * Returns true if Snippet with the given tag exists in the collection
	 */
	public function existsByTag(string $tag): bool {
		foreach ($this->_collection as $item) {
			if ($item->tag == $tag)
				return true;
		}

		return false;
	}

	/**
	 * Returns the Slot in the collection that has the given tag.
	 */
	public function getByTag(string $tag): ?Slot {
		foreach ($this->_collection as $item) {
			if ($item->tag == $tag)
				return $item;
		}

		return null;
	}
	#endregion
}
