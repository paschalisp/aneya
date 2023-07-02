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

namespace aneya\CMS;

use aneya\Core\Collection;

class ArticleCollection extends Collection {
	#region Properties
	/** @var  Article[] */
	protected array $_collection;
	#endregion

	#region Constructor
	public function __construct(string $type = '\\aneya\\CMS\\Article', bool $uniqueKeys = true) {
		parent::__construct($type, $uniqueKeys);
	}
	#endregion

	#region Methods
	/**
	 * @inheritdoc
	 *
	 * @return Article[]
	 */
	public function all(callable $f = null): array {
		return parent::all($f);
	}

	/**
	 * @inheritdoc
	 */
	public function first(callable $f = null): Article {
		return parent::first($f);
	}

	/**
	 * @inheritdoc
	 */
	public function last(callable $f = null): Article {
		return parent::last($f);
	}

	/**
	 * Returns the item at the specified index
	 *
	 * @param int $index
	 *
	 * @return Article The item or null if no item was found at the specified index
	 */
	public function itemAt(int $index): ?Article {
		$index = (int)$index;

		if ($index < 0 || $index >= $this->count())
			return null;

		return $this->_collection[$index];
	}

	/**
	 * Returns true if the collection contains the given item
	 *
	 * @param Article|string|int $item
	 *
	 * @return bool
	 */
	public function contains($item): bool {
		if ($this->isValid($item)) {
			return parent::contains($item);
		}
		elseif (is_string($item)) {
			foreach ($this->_collection as $colItem) {
				if ($colItem->seoUrl == $item)
					return true;
			}
		}
		elseif (is_int($item)) {
			foreach ($this->_collection as $colItem) {
				if ($colItem->id == $item)
					return true;
			}
		}

		return false;
	}
	#endregion
}
