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

class PermissionCollection extends Collection {
	#region Properties
	/** @var Permission[] */
	protected array $_collection;
	#endregion

	#region Constructor
	public function __construct() {
		parent::__construct('\\aneya\\Security\\Permission', true);
	}
	#endregion

	#region Methods
	/**
	 * @inheritdoc
	 * @return Permission[]
	 */
	public function all(callable $f = null): array {
		return parent::all($f);
	}

	/**
	 * Returns the keys of all permissions contained in the collection.
	 * @return string[]
	 */
	public function allKeys(): array {
		return array_map(function (Permission $permission) {
			return $permission->code;
		}, $this->_collection);
	}

	/**
	 * Returns the Permission in the collection given the code
	 *
	 * @param string $code
	 * @return Permission
	 */
	public function getByCode(string $code): ?Permission {
		foreach ($this->_collection as $role) {
			if ($role->code == $code)
				return $role;
		}

		return null;
	}

	/**
	 * Returns true if the collection contains the given item
	 * @param Permission|string $item
	 * @return bool
	 */
	public function contains($item): bool {
		if ($item instanceof Role)
			$item = $item->code;

		foreach ($this->_collection as $role)
			if ((string)$item == $role)
				return true;

		return false;
	}

	/**
	 * @inheritdoc
	 * @param Permission|string $item
	 */
	public function add($item): static {
		if (is_string($item)) {
			/** @var \SecurityModule $mod */
			$mod = CMS::module('aneya/security');
			$perm = $mod->permissions()->getByCode($item);
			if ($perm === null)
				$item = new Permission($item);
			else
				$item = $perm;
		}

		return parent::add($item);
	}

	/**
	 * @inheritdoc
	 * @param Permission|string $item
	 */
	public function remove($item): static {
		if (is_string($item)) {
			/** @var \SecurityModule $mod */
			$mod = CMS::module('aneya/security');
			$perm = $mod->permissions()->getByCode($item);
			if ($perm instanceof Permission)
				$item = $perm;
			else
				return $this;
		}

		return parent::remove($item);
	}

	/**
	 * Returns the index of the item in the collection
	 * @param Permission|string $item
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
