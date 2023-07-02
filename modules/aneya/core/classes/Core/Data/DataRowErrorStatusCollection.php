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
use aneya\Forms\FormField;

class DataRowErrorStatusCollection extends Collection {
	#region Properties
	/** @var DataRowErrorStatus[] */
	protected array $_collection;
	#endregion

	#region Constructor
	/**
	 * @param DataRowErrorStatus[]|null $errors
	 */
	public function __construct(array $errors = null) {
		parent::__construct('\\aneya\\Core\\Data\\DataRowErrorStatus', true);

		if (is_array($errors))
			$this->addRange($errors);
	}
	#endregion

	#region Methods
	/**
	 * Returns a collection of all error statuses found for the given column or field.
	 *
	 * @param DataColumn|FormField|string $item Either column's or field's instance or column's tag.
	 *
	 * @return DataRowErrorStatusCollection
	 */
	public function get($item): DataRowErrorStatusCollection {
		$ret = new DataRowErrorStatusCollection();
		$tag = null;

		if ($item instanceof DataColumn)
			$tag = $item->tag;
		elseif ($item instanceof FormField)
			$tag = $item->column->tag;
		elseif (is_string($item))
			$tag = $item;
		else
			return $ret;

		foreach ($this->_collection as $error)
			if ($error->column->tag == $tag)
				$ret->add($error);

		return $ret;
	}

	/**
	 * Returns true if the collection contains any erroneous statuses (with the "isPositive" property set to false)
	 */
	public function hasErrors(): bool {
		foreach ($this->_collection as $error)
			if ($error->isError())
				return true;

		return false;
	}

	/**
	 * Returns the number of erroneous statuses (with the "isPositive" property set to false) that are found in the collection
	 */
	public function countErrors(): int {
		$num = 0;
		foreach ($this->_collection as $error)
			if ($error->isError())
				$num++;

		return $num;
	}

	/**
	 * Combines all errors found during validation into a single string and returns it.
	 *
	 * @param string $delimiter
	 *
	 * @return string
	 */
	public function toString(string $delimiter = "\n"): string {
		$statuses = array ();
		foreach ($this->_collection as $status) {
			$statuses[] = $status->message;
		}

		return implode($delimiter, $statuses);
	}

	/**
	 * @inheritdoc
	 * @return DataRowErrorStatus[]
	 */
	public function all(callable $f = null): array {
		return parent::all($f);
	}

	/**
	 * @inheritdoc
	 */
	public function first(callable $f = null): DataRowErrorStatus {
		return parent::first($f);
	}

	/**
	 * @inheritdoc
	 */
	public function last(callable $f = null): DataRowErrorStatus {
		return parent::last($f);
	}
	#endregion
}
