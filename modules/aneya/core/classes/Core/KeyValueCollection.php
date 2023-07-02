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


class KeyValueCollection extends Collection {
	#region Properties
	/** @var KeyValue[] */
	protected array $_collection;
	#endregion

	#region Constructor
	public function __construct() {
		parent::__construct('\\aneya\\Core\\KeyValue', true);
	}
	#endregion

	#region Methods
	/**
	 * @inheritdoc
	 * @return KeyValue[]
	 */
	public function all(callable $f = null): array {
		return parent::all($f);
	}

	/**
	 * @inheritdoc
	 */
	public function first(callable $f = null): KeyValue {
		return parent::first($f);
	}

	/**
	 * @inheritdoc
	 */
	public function last(callable $f = null): KeyValue {
		return parent::last($f);
	}

	/**
	 * Returns all Collection's keys
	 *
	 * @return string[]
	 */
	public function allKeys(): array {
		$keys = [];

		foreach ($this->_collection as $kv)
			$keys[] = $kv->key;

		return $keys;
	}

	/**
	 * Returns all Collection's values
	 */
	public function allValues(): array {
		$values = [];

		foreach ($this->_collection as $kv)
			$values[] = $kv->value;

		return $values;
	}

	/**
	 * Returns the KeyValue item given its key
	 */
	public function get(string $key): ?KeyValue {
		foreach ($this->_collection as $kv)
			if ($kv->key == (string)$key)
				return $kv;

		return null;
	}

	/**
	 * Returns the KeyValue's value given the key
	 *
	 * @return mixed
	 */
	public function getValue(string $key) {
		foreach ($this->_collection as $kv)
			if ($kv->key == (string)$key)
				return $kv->value;

		return null;
	}

	/**
	 * Returns the KeyValue's key given the value
	 *
	 * @param mixed $value
	 *
	 * @return string|null
	 */
	public function getKey($value): ?string {
		foreach ($this->_collection as $kv)
			if ($kv->value === $value)
				return $kv->key;

		return null;
	}

	/**
	 * Sets the KeyValue's value given the key
	 *
	 * @param mixed $index
	 * @param mixed $item
	 * @return static
	 */
	public function set($index, $item): static {
		foreach ($this->_collection as $kv)
			if ($kv->key == (string)$index) {
				$kv->value = $item;
				return $this;
			}

		return $this->add(new KeyValue($index, $item));
	}

	/**
	 * Returns true the given key exists in the collection
	 */
	public function hasKey(string $key): bool {
		foreach ($this->_collection as $kv)
			if ($kv->key == (string)$key)
				return true;

		return false;
	}

	/**
	 * Removes a KeyValue item from the collection given its key
	 */
	public function removeByKey(string $key) {
		foreach ($this->_collection as $kv)
			if ($kv->key == (string)$key) {
				$this->remove($kv);
				return;
			}
	}

	/**
	 * Returns a hash array representation of the collection
	 */
	public function toArray(): array {
		$arr = [];

		foreach ($this->_collection as $kv)
			$arr[(string)$kv->key] = $kv->value;

		return $arr;
	}
	#endregion
}
