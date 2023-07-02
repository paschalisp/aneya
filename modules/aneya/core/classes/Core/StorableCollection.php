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

use aneya\Core\Data\DataSet;
use aneya\Core\Data\DataTable;

trait StorableCollection {
	#region Properties
	/** @var IStorable[] */
	private array $_storableCollection = [];
	#endregion

	#region Methods
	/**
	 * @return DataSet|DataTable|null
	 */
	public function dataSet() {
		if (class_exists($this->_type) && is_subclass_of($this->_type, '\\aneya\\Core\\IStorable')) {
			/** @var IStorable $class */
			$class = $this->_type;
			return $class::ormSt()->dataSet();
		}

		return null;
	}

	/**
	 * Initializes and prepares the storable collection
	 */
	public function initStorableCollection() {
		$this->_storableCollection = [];

		// Initialize the storable objects collection
		if (isset($this->_collection) && is_array($this->_collection) && count($this->_collection) > 0)
			$this->_storableCollection = array_merge($this->_collection);

		// Whenever an object is added into the collection,
		// also add it to the storable objects collection
		$this->off(Collection::EventOnItemAdded, 'storableOnItemAdded');
		$this->on(Collection::EventOnItemAdded, function (CollectionEventArgs $args) {
			// Force strict comparison in in_array() to avoid circular reference crashes
			if ($args->newItem instanceof IStorable && !in_array($args->newItem, $this->_storableCollection, true)) {
				array_push($this->_storableCollection, $args->newItem);
			}
		}, PHP_INT_MAX, 'storableOnItemAdded'); // Let it be the last listener to be executed

		// Whenever an object is removed from the collection,
		// mark its row as deleted and leave it in the storable objects collection
		$this->off(Collection::EventOnItemRemoved, 'storableOnItemRemoved');
		$this->on(Collection::EventOnItemRemoved, function (CollectionEventArgs $args) {
			// Force strict comparison in in_array() to avoid circular reference crashes
			if ($args->oldItem instanceof IStorable && in_array($args->oldItem, $this->_storableCollection, true)) {
				$args->oldItem->orm()->row()->delete();
			}
		}, PHP_INT_MAX, 'storableOnItemRemoved'); // Let it be the last listener to be executed
	}

	/**
	 * Calls the storable objects internal save mechanism.
	 * @return EventStatus
	 */
	public function save(): EventStatus {
		$status = new EventStatus();

		foreach ($this->_storableCollection as $item) {
			$status = $item->save();
			if ($status->isError())
				return $status;
		}

		return $status;
	}
	#endregion
}
