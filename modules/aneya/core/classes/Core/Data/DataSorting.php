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

class DataSorting {
	#region Constants
	const NoSorting  = '-';
	const Ascending  = 'asc';
	const Descending = 'desc';
	#endregion

	#region Properties
	/**
	 * @var DataColumn
	 */
	public $column;
	public $mode     = self::NoSorting;
	public $priority = null;
	#endregion

	#region Constructor
	public function __construct(DataColumn $column, $mode = self::NoSorting, $priority = null) {
		$this->column = $column;

		$this->mode = ($mode == self::Ascending || $mode == self::Descending) ? $mode : self::Ascending;
		if ($priority !== null) {
			$this->priority = intval($priority);
		}
	}
	#endregion

	#region Methods
	/**
	 * Returns a sorting expression built in respect to the given database's syntax
	 *
	 * @internal string    $fieldName
	 * @internal Database    $db
	 * @return   mixed
	 */
	public function getExpression() {
		return $this->column->table->db()->getSortingExpression($this);
	}

	/**
	 * Returns true if the sorting parameters are set correctly
	 *
	 * @return bool
	 */
	public function isValid() {
		if (!in_array($this->mode, array (self::NoSorting, self::Ascending, self::Descending)))
			return false;

		if ($this->mode != self::NoSorting && (int)$this->priority < 0)
			return false;

		return true;
	}

	/**
	 * Returns true if no sorting information should be handled
	 *
	 * @return boolean
	 */
	public function isEmpty() {
		return ($this->mode == self::NoSorting);
	}

	/**
	 * Returns true if no sorting information should be handled
	 *
	 * @return boolean
	 */
	public function isNull() {
		return (is_null($this->mode) || is_null($this->priority));
	}
	#endregion
}
