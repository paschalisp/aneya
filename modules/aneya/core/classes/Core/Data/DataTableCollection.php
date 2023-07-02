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

class DataTableCollection extends Collection {
	#region Properties
	/**
	 * @var DataTable[]
	 */
	protected array $_collection;
	#endregion

	#region Constructor
	public function __construct() {
		parent::__construct('\\aneya\\Core\\Data\\DataTable', true);
	}
	#endregion

	#region Methods
	/**
	 * @inheritdoc
	 * @return DataTable[]
	 */
	public function all(callable $f = null): array {
		return parent::all($f);
	}

	/**
	 * @inheritdoc
	 */
	public function first(callable $f = null): ?DataTable {
		return parent::first($f);
	}

	/**
	 * Returns the table in the collection by identified its id, name or alias
	 *
	 * @param mixed $id_or_name_or_alias
	 */
	public function get($id_or_name_or_alias): ?DataTable {
		if (is_numeric($id_or_name_or_alias)) {
			$id = (int)$id_or_name_or_alias;
			foreach ($this->_collection as $tbl)
				if ($tbl->id == $id)
					return $tbl;
		}
		else {
			foreach ($this->_collection as $tbl)
				if ($tbl->name == $id_or_name_or_alias || $tbl->alias == $id_or_name_or_alias)
					return $tbl;
		}

		return null;
	}
	#endregion

	#region Interface Methods
	public function jsonSerialize($definition = false): array {
		$tables = [];

		foreach ($this->_collection as $tbl)
			$tables[] = $tbl->jsonSerialize($definition);

		return $tables;
	}
	#endregion
}
