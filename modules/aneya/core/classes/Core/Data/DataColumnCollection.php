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

use aneya\Core\CMS;
use aneya\Core\Collection;

class DataColumnCollection extends Collection {
	#region Properties
	/**
	 * @var DataTable|mixed
	 */
	public $parent;

	/**
	 * @var DataColumn[]
	 */
	protected array $_collection;
	#endregion

	#region Constructor
	/**
	 * @param DataColumn[]|null $columns
	 */
	public function __construct(array $columns = null) {
		parent::__construct('\\aneya\\Core\\Data\\DataColumn', true);

		if (is_array($columns)) {
			foreach ($columns as $col)
				if ($col instanceof DataColumn)
					$this->add($col);
		}
	}
	#endregion

	#region Methods
	/**
	 * @inheritdoc
	 *
	 * @return DataColumn[]
	 */
	public function all(callable $f = null): array {
		return parent::all($f);
	}

	/**
	 * @inheritdoc
	 * @param DataColumn $item
	 */
	public function add($item): static {
		parent::add($item);

		if (!isset($item->table) && $this->parent instanceof DataTable)
			$item->table = $this->parent;

		return $this;
	}

	/**
	 * Inserts a value into the collection at the specified index.
	 *
	 * @param DataColumn|DataColumn[] $item
	 * @param int $index
	 *
	 * @return static
	 * @triggers OnItemAdded
	 */
	public function insertAt($item, int $index): static {
		$index = (int)$index;
		if ($index < 0 || $index > $this->count())
			return $this;

		if (is_array($item)) {
			$offset = 0;
			foreach ($item as $i) {
				$ret = $this->insertAt($i, $index + $offset);
				if ($ret)
					$offset++;
			}
		}

		if ($this->parent instanceof DataTable)
			$item->table = $this->parent;

		return $this;
	}

	/**
	 * Returns the DataColumn in the collection identified by its id, tag or column name
	 *
	 * @param mixed $id_or_tag_or_name
	 */
	public function get($id_or_tag_or_name): ?DataColumn {
		if (is_numeric($id_or_tag_or_name)) {
			$id = (int)$id_or_tag_or_name;
			foreach ($this->_collection as $c) {
				if ($c->id == $id) {
					return $c;
				}
			}
		}
		else {
			// Searching by tag is prioritized
			foreach ($this->_collection as $c) {
				if ($c->tag == $id_or_tag_or_name) {
					return $c;
				}
			}

			// Try with the column name this time
			foreach ($this->_collection as $c) {
				if ($c->name == $id_or_tag_or_name) {
					return $c;
				}
			}
		}

		if (func_num_args() == 2 && func_get_arg(1) == true) {
			CMS::logger()->warning("No field width id or name '$id_or_tag_or_name' found on table/data set '" . $this->parent->name . "'");
		}

		return null;
	}

	/**
	 * @inheritdoc
	 */
	public function first(callable $f = null): ?DataColumn {
		return parent::first($f);
	}

	/**
	 * @inheritdoc
	 */
	public function last(callable $f = null): ?DataColumn {
		return parent::last($f);
	}

	/**
	 * Returns true if the collection contains the given item
	 *
	 * @param DataColumn|string|int $item
	 *
	 * @return bool
	 */
	public function contains($item): bool {
		if (is_numeric($item)) {
			$id = (int)$item;
			foreach ($this->_collection as $c) {
				if ($c->id == $id) {
					return true;
				}
			}
		}
		else {
			// Searching by tag is prioritized
			foreach ($this->_collection as $c) {
				if ($c->tag == $item) {
					return true;
				}
			}

			// Try with the column name this time
			foreach ($this->_collection as $c) {
				if ($c->name == $item) {
					return true;
				}
			}
		}

		return false;
	}
	#endregion

	#region Interface methods
	public function jsonSerialize(): array {
		$col = [];
		foreach ($this->filter(function (DataColumn $c) { return $c->isActive; })->all() as $c)
			$col[] = $c->jsonSerialize();

		return $col;
	}
	#endregion
}
