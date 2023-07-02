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

use aneya\Core\Collection;
use aneya\Core\ISortable;

class DataSortingCollection extends Collection implements ISortable {
	#region Properties
	/**
	 * @var DataSorting[]
	 */
	protected array $_collection;
	#endregion

	#region Constructor
	public function __construct() {
		parent::__construct('\\aneya\\Core\\Data\\DataSorting', true);
	}
	#endregion

	#region Methods
	/**
	 * @inheritdoc
	 *
	 * @return DataSorting[]
	 */
	public function all(callable $f = null): array {
		return parent::all($f);
	}

	/**
	 * @inheritdoc
	 */
	public function first(callable $f = null): DataSorting {
		return parent::first($f);
	}

	/**
	 * Returns any sorting information for the given column
	 */
	public function byColumn(DataColumn $column): ?DataSorting {
		foreach ($this->_collection as $c)
			if ($c->column === $column)
				return $c;

		return null;
	}
	#endregion

	#region Interface implementation(s)
	/**
	 * Sorts the collection and returns back the instance sorted
	 */
	public function sort(): DataSortingCollection {
		usort($this->_collection, function (DataSorting $a, DataSorting $b) {
			if ($a->priority == $b->priority) {
				return 0;
			}
			return ($a->priority < $b->priority) ? -1 : 1;
		});
		$this->rewind();

		return $this;
	}

	public function jsonSerialize(): array {
		$ret = [];

		foreach ($this->sort()->all() as $sort)
			$ret[] = ['column' => $sort->column->tag, 'direction' => $sort->mode == DataSorting::Descending ? DataSorting::Descending : DataSorting::Ascending];

		return $ret;
	}
	#endregion
}
