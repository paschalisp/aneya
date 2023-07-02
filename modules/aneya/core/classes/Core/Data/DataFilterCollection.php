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

class DataFilterCollection extends Collection {
	#region Constants
	const OperandOr  = '|';
	const OperandAnd = '&';
	#endregion

	#region Properties
	/** @var string The operand to use to join the filters into one expression */
	public string $operand = DataFilterCollection::OperandAnd;

	/**
	 * @var DataFilter[]
	 */
	protected array $_collection;
	#endregion

	#region Constructor
	public function __construct() {
		parent::__construct('\\aneya\\Core\\Data\\DataFilter', true);
	}
	#endregion

	#region Methods
	/**
	 * @inheritdoc
	 *
	 * @return DataFilter[]|DataFilterCollection[]
	 */
	public function all(callable $f = null): array {
		return parent::all($f);
	}

	/** @inheritdoc */
	public function first(callable $f = null): DataFilter {
		return parent::first($f);
	}

	/** Returns all filter information for the given column */
	public function byColumn(DataColumn $column): static {
		$ret = new DataFilterCollection();
		foreach ($this->_collection as $c)
			if ($c->column === $column)
				$ret->add($c);

		return $ret;
	}

	/** Returns all columns that participate in the filter collection, also considering sub-filters collections. */
	public function allColumns(): DataColumnCollection {
		$columns = new DataColumnCollection();

		foreach ($this->_collection as $f)
			if ($f instanceof DataFilter) {
				$columns->add($f->column);
				if ($f->value instanceof DataColumn)
					$columns->add($f->value);
			}
			elseif ($f instanceof DataFilterCollection) {
				$cols = $f->allColumns();
				foreach ($cols as $c)
					$columns->add($c);
			}

		return $columns;
	}

	/** Returns true if the parameter is valid to be added to the collection */
	public function isValid($item): bool {
		return ($item instanceof DataFilter || $item instanceof DataFilterCollection);
	}

	/** Returns true if all columns associated with the filtering collection belong to the given schema */
	public function refersToSchema(Database $db): bool {
		foreach ($this->_collection as $f) {
			if ($f instanceof DataFilter) {
				if ($f->column->table->db() !== $db || ($f->value instanceof DataColumn && $f->value->table->db() !== $db)) {
					return false;
				}
			}
			elseif ($f instanceof DataFilterCollection && $f->count() > 0) {
				if (!$f->refersToSchema($db)) {
					return false;
				}
			}
		}

		return true;
	}

	/** Merges collection's filters with the given filters. */
	public function mergeWith(DataFilterCollection|DataFilter|array $filters): static {
		if ($filters instanceof DataFilter)
			$this->add($filters);

		elseif (is_array($filters))
			$this->addRange($filters);

		elseif ($filters instanceof DataFilterCollection) {
			if ($this->operand === $filters->operand)
				// Both collections have the same operand, so merge is possible
				$this->addRange($filters->all());
		}

		return $this;
	}

	/** Sets instance's operand. */
	public function setOperand(string $operand): static {
		if (in_array($operand, [static::OperandAnd, static::OperandOr]))
			$this->operand = $operand;

		return $this;
	}
	#endregion
}
