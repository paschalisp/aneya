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

namespace aneya\Structures;

use aneya\Core\Collection;
use aneya\Core\ISortable;

class LinkCollection extends Collection implements ISortable {
	#region Properties
	/** @var Link[] */
	protected array $_collection;
	#endregion

	#region Constructor
	public function __construct() {
		parent::__construct('\\aneya\\Structures\\Link', true);
	}
	#endregion

	#region Methods
	/**
	 * @inheritdoc
	 * @return Link[]
	 */
	public function all(callable $f = null): array {
		return parent::all($f);
	}

	/**
	 * @param Node $source
	 * @return Link
	 */
	public function getBySource(Node $source): ?Link {
		foreach ($this->_collection as $link)
			if ($link->source === $source)
				return $link;

		return null;
	}

	/**
	 * @param Node $target
	 * @return Link
	 */
	public function getByTarget(Node $target): ?Link {
		foreach ($this->_collection as $link)
			if ($link->target === $target)
				return $link;

		return null;
	}

	/**
	 * @param Node $source
	 * @return int
	 */
	public function countBySource(Node $source): int {
		$cnt = 0;
		foreach ($this->_collection as $link)
			if ($link->source === $source)
				$cnt++;

		return $cnt;
	}

	/**
	 * @param Node $target
	 * @return int
	 */
	public function countByTarget(Node $target): int {
		$cnt = 0;
		foreach ($this->_collection as $link)
			if ($link->target === $target)
				$cnt++;

		return $cnt;
	}
	#endregion

	#region Interface implementations
	/**
	 * Sorts the collection and returns back the instance sorted
	 * @return LinkCollection
	 */
	public function sort (): LinkCollection {
		usort ($this->_collection, function (Link $a, Link $b) {
			if ($a->weight == $b->weight) {
				return 0;
			}
			return ($a->weight < $b->weight) ? -1 : 1;
		});
		$this->rewind ();

		return $this;
	}
	#endregion
}
