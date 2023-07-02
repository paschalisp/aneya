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

namespace aneya\Security;

use aneya\Core\Collection;

class UserCollection extends Collection {
	#region Properties
	/** @var User[] */
	protected array $_collection;
	#endregion

	#region Constructor
	public function __construct() {
		parent::__construct('\\aneya\\Security\\User', true);
	}
	#endregion

	#region Methods
	/**
	 * @inheritdoc
	 * @return User[]
	 */
	public function all(callable $f = null): array {
		return parent::all($f);
	}

	/**
	 * Returns the User instance given the id.
	 */
	public function byId(int $id): ?User {
		foreach ($this->_collection as $user) {
			if ($user->id === $id)
				return $user;
		}

		return null;
	}

	/**
	 * Returns a JSON-compatible representation of all items in the collection.
	 */
	public function jsonSerialize(bool $definition = false): array {
		$items = [];

		foreach ($this->_collection as $item)
			$items[] = $item->jsonSerialize($definition);

		return $items;
	}
	#endregion

	#region Static methods
	#endregion
}
