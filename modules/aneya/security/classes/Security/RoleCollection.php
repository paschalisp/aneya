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

use aneya\Core\CMS;
use aneya\Core\Collection;

class RoleCollection extends Collection {
	#region Properties
	/** @var Role[] */
	protected array $_collection;
	#endregion

	#region Constructor
	public function __construct() {
		parent::__construct('\\aneya\\Security\\Role', true);
	}
	#endregion

	#region Methods
	/**
	 * @inheritdoc
	 * @return Role[]
	 */
	public function all(callable $f = null): array {
		return parent::all($f);
	}

	/**
	 * Returns the keys of all roles contained in the collection.
	 * @return string[]
	 */
	public function allKeys(): array {
		return array_map(function (Role $role) {
			return $role->code;
		}, $this->_collection);
	}

	/**
	 * Returns the Role in the collection given the code
	 *
	 * @param string $code
	 * @return Role
	 */
	public function getByCode(string $code): ?Role {
		foreach ($this->_collection as $role) {
			if ($role->code == $code)
				return $role;
		}

		return null;
	}

	/**
	 * Returns true if the collection contains the given item
	 * @param Role|string $item
	 * @return bool
	 */
	public function contains($item) : bool {
		if ($item instanceof Role)
			$item = $item->code;

		foreach ($this->_collection as $role)
			if ((string)$item == $role)
				return true;

		return false;
	}

	/**
	 * @inheritdoc
	 * @param Role|string $item
	 */
	public function add($item): static {
		if (is_string($item)) {
			/** @var \SecurityModule $mod */
			$mod = CMS::module('aneya/security');
			$role = $mod->roles()->getByCode($item);

			if ($role === null)
				$item = new Role($item);
			else
				$item = $role;
		}

		return parent::add($item);
	}

	/**
	 * @inheritdoc
	 * @param Role|string $item
	 */
	public function remove($item): static {
		if (is_string($item)) {
			/** @var \SecurityModule $mod */
			$mod = CMS::module('aneya/security');
			$role = $mod->roles()->getByCode($item);

			if ($role instanceof Role)
				$item = $role;
			else
				return $this;
		}

		return parent::remove($item);
	}

	/**
	 * Returns the index of the item in the collection
	 * @param Role|string $item
	 * @return int|bool
	 * @throws \InvalidArgumentException
	 */
	public function indexOf($item): int|bool {
		if (!$this->isValid($item))
			throw new \InvalidArgumentException ("Parameter is not a $this->_type value");

		if (($max = $this->count()) <= 0)
			return false;


		for ($i = 0; $i < $max; $i++) {
			if ((is_string($item) && $this->_collection[$i]->code === $item) || $item === $this->_collection[$i]) {
				return $i;
			}
		}

		return false;
	}
	#endregion
}
