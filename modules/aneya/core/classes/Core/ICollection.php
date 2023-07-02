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

namespace aneya\Core;

interface ICollection extends \ArrayAccess, \Iterator, \Countable {
	/**
	 * Add a value into the collection.
	 *
	 * @param mixed $item
	 * @throws \InvalidArgumentException when trying to add a value of wrong type
	 */
	public function add($item): ICollection;

	/**
	 * Inserts a value into the collection at the specified index.
	 *
	 * @param mixed $item
	 * @param int $index
	 * @returns ICollection
	 */
	public function insertAt($item, int $index): ICollection;

	/**
	 * Sets a value at the specified index in the collection
	 *
	 * @param int $index
	 * @param mixed $item
	 * @throws \InvalidArgumentException when trying to set a value of wrong type
	 */
	public function set(int $index, $item);

	/**
	 * Removes an item from the collection
	 *
	 * @param mixed $item
	 * @throws \InvalidArgumentException when trying to access an index which is out of the bounds of the collection
	 * @returns ICollection
	 */
	public function remove($item): ICollection;

	/**
	 * Removes the item at the specified index from the collection
	 *
	 * @param int $index
	 * @throws \InvalidArgumentException when trying to access an index which is out of the bounds of the collection
	 * @returns bool
	 */
	public function removeAt(int $index);

	/**
	 * Returns the item at the specified index
	 *
	 * @param int $index
	 * @return mixed
	 */
	public function itemAt(int $index);

	/**
	 * Returns the first item in the collection.
	 *
	 * @return mixed
	 */
	public function first();

	/**
	 * Returns the last item in the collection.
	 * If the collection implements ISortable, sort() will be executed before retrieving the last item.
	 *
	 * @return mixed
	 */
	public function last();

	/**
	 * Returns all items in the collection in an array
	 *
	 * @return array
	 */
	public function all(): array;

	/**
	 * Returns all items in the collection in an array
	 *
	 * @return static
	 */
	public function unique(): static;

	/**
	 * Returns the index of the item in the collection
	 *
	 * @param object|scalar $item
	 * @return int|bool
	 */
	public function indexOf($item);

	/**
	 * Returns true if the item exists in the collection
	 *
	 * @param object|int $item
	 * @return bool
	 */
	public function contains($item): bool;

	/**
	 * Returns the number of items in the collection
	 *
	 * @return int
	 */
	public function count(): int;

	/**
	 * Clears the collection by removing all its items
	 */
	public function clear();

	/**
	 * Returns the variable type (or fully qualified class name for objects) of the items contained in the collection
	 *
	 * @return string
	 */
	public function itemsType(): string;
}
