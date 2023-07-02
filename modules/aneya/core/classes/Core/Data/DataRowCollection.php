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

class DataRowCollection extends Collection implements IFilterable {
	#region Properties
	/** @var DataRow[] */
	protected array $_collection;

	/** @var ?DataSortingCollection */
	private ?DataSortingCollection $__tmpSorting;

	/** @var DataTable */
	public DataTable $parent;
	#endregion

	#region Constructor
	/**
	 * @param DataRow[]|null $rows
	 */
	public function __construct(array $rows = null) {
		parent::__construct('\\aneya\\Core\\Data\\DataRow', true);

		if (is_array($rows)) {
			$this->addRange($rows);
		}
	}
	#endregion

	#region Methods
	/**
	 * @inheritdoc
	 * @return DataRow[]
	 */
	public function all(callable $f = null): array {
		return parent::all($f);
	}

	/**
	 * @inheritdoc
	 */
	public function first(callable $f = null): ?DataRow {
		return parent::first($f);
	}

	/**
	 * @inheritdoc
	 */
	public function last(callable $f = null): ?DataRow {
		return parent::last($f);
	}

	/**
	 * Returns the item at the specified index
	 *
	 * @param int $index
	 *
	 * @return ?DataRow The item or null if no item was found at the specified index
	 */
	public function itemAt(int $index): ?DataRow {
		return parent::itemAt($index);
	}

	/**
	 * @param DataRow|DataRow[] $record
	 * @param int $state
	 */
	public function addWithState($record, int $state = DataRow::StateUnchanged) {
		if (is_array($record)) {
			foreach ($record as $r) {
				if (!($r instanceof DataRow))
					continue;

				$r->setState($state);
				$this->add($r);
			}
		} elseif ($record instanceof DataRow) {
			$this->add($record);
		}
	}

	/**
	 * Returns the selected records in the collection
	 */
	public function getSelected(): DataRowCollection {
		$rows = new DataRowCollection ();

		foreach ($this->_collection as $r) {
			if ($r->isSelected) {
				$rows->add($r);
			}
		}

		return $rows;
	}

	/**
	 * Returns the changed records in the collection (either added, modified or deleted)
	 */
	public function getChanged(int $rowState = null): DataRowCollection {
		$rows = new DataRowCollection ();

		foreach ($this->_collection as $r) {
			if (in_array($r->getState(), array(DataRow::StateAdded, DataRow::StateModified, DataRow::StateDeleted))) {
				if ($rowState == null || $r->getState() == $rowState) {
					$rows->add($r);
				}
			}
		}

		return $rows;
	}

	/**
	 * Flags all rows in the collection as selected
	 *
	 * @return int The actual number of rows that were not already selected before calling this function
	 */
	public function select(): int {
		$num = 0;
		foreach ($this->_collection as $r) {
			if (!$r->isSelected) {
				$r->isSelected = true;
				$num++;
			}
		}

		return $num;
	}

	/**
	 * @param DataSortingCollection|DataSorting|array $sorting
	 *
	 * @return DataRowCollection
	 */
	public function sort($sorting): DataRowCollection {
		#region Prepare sorting
		if ($sorting instanceof DataSortingCollection) {
			$this->__tmpSorting = $sorting;
		} else {
			$this->__tmpSorting = new DataSortingCollection();

			if ($sorting instanceof DataSorting) {
				$sorting = [$sorting];
			}

			foreach ($sorting as $s) {
				if ($s instanceof DataSorting) {
					$this->__tmpSorting->add($s);
				}
			}
		}

		$this->__tmpSorting->sort();
		#endregion

		usort($this->_collection, function (DataRow $a, DataRow $b) {
			foreach ($this->__tmpSorting->all() as $s) {
				$aVal = $a->getValue($s->column);
				$bVal = $b->getValue($s->column);

				if ($aVal == $bVal) {
					continue;
				}

				if ($s->mode == DataSorting::Ascending) {
					return ($aVal < $bVal) ? -1 : 1;
				} else {
					return ($aVal > $bVal) ? -1 : 1;
				}
			}

			return 0;
		});
		$this->rewind();

		$this->__tmpSorting = null;

		return $this;
	}

	/**
	 * Returns the rows as an array. If column tags are provided as arguments, the function returns only the specified columns in the order that are passed to the function.
	 */
	public function toArray(): array {
		$args = func_num_args();
		$first = $this->first();
		$cols = [];
		$ret = [];

		if ($first === null) {
			return $ret;
		}

		if ($args == 0) {
			foreach ($this->all() as $row) {
				$ret[] = array_values($row->bulkGetValues());
			}
		} else {
			for ($num = 0; $num < $args; $num++) {
				$col = func_get_arg($num);
				if (!is_string($col)) {
					continue;
				}

				$cols[] = $col;
			}

			foreach ($this->all() as $row) {
				$ret[] = array_values($row->bulkGetValues($cols));
			}
		}

		return $ret;
	}

	/**
	 * Returns any records in the collection that match the given filters
	 *
	 * @param DataFilterCollection|DataFilter[]|DataFilter $filters
	 */
	public function match($filters): DataRowCollection {
		$rows = new DataRowCollection ();

		foreach ($this->_collection as $row) {
			if ($row->match($filters)) {
				$rows->add($row);
			}
		}
		return $rows;
	}
	#endregion
}
