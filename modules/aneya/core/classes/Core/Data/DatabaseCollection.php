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

class DatabaseCollection extends Collection {
	#region Properties
	/**
	 * @var Database[]
	 */
	protected array $_collection;
	#endregion

	#region Constructor
	/**
	 * @param Database[]|null $databases
	 */
	public function __construct(array $databases = null) {
		parent::__construct('\\aneya\\Core\\Data\\Database', true);

		if (is_array($databases)) {
			foreach ($databases as $db)
				if ($db instanceof Database)
					$this->add($db);
		}
	}
	#endregion

	#region Methods
	/**
	 * Begins a transaction in all databases in the collection
	 *
	 * @param string|null $name
	 */
	public function beginTransaction(string $name = null): bool|int {
		foreach ($this->_collection as $db) {
			$ret = $db->beginTransaction($name);
			if ($ret === false)
				return false;
		}

		return $ret ?? true;
	}

	/**
	 * Commits all pending changes in all databases in the collection
	 *
	 * @param string|null $name
	 */
	public function commit(string $name = null) {
		foreach ($this->_collection as $db) {
			$db->commit($name);
		}
	}

	/**
	 * Rolls back all pending changes in all databases in the collection
	 *
	 * @param string|null $name
	 */
	public function rollback(string $name = null) {
		foreach ($this->_collection as $db) {
			$db->rollback($name);
		}
	}

	/**
	 * @inheritdoc
	 * @return Database[]
	 */
	public function all(callable $f = null): array {
		return parent::all($f);
	}
	#endregion

	#region Static methods
	/**
	 * Returns a DatabaseCollection instance that contains all database connections found in the given DataTable or DataSet
	 */
	public static function fromDataSet(DataTable $dataSet): DatabaseCollection {
		$collection = new DatabaseCollection ();

		foreach ($dataSet->columns->all() as $column)
			$collection->add($column->table->db());

		return $collection;
	}

	/**
	 * Returns a DatabaseCollection instance that contains all database connections related to the given DataRow
	 */
	public static function fromDataRow(DataRow $row): DatabaseCollection {
		$collection = new DatabaseCollection();

		foreach ($row->parent->columns->filter(function (DataColumn $c) {
			return $c->isActive;
		})->all() as $column) {
			if ($column->isFake || !($column->table->db() instanceof Database)) {
				continue;
			}
			$collection->add($column->table->db());
		}

		return $collection;
	}
	#endregion
}
