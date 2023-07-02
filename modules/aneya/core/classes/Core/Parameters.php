<?php
/*
 * aneya CMS & Framework
 * Copyright (c) 2011-2022 Paschalis Pagonidis <p.pagonides@gmail.com>
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
 * Portions created by Paschalis Ch. Pagonidis are Copyright (C) 2011-2022
 * Paschalis Ch. Pagonidis. All Rights Reserved.
 */

namespace aneya\Core;


class Parameters implements \Iterator, \JsonSerializable {
	/** @var KeyValueCollection */
	protected $params;

	public function __construct($array = null) {
		$this->params = new KeyValueCollection();
		if (isset ($array) && is_array($array)) {
			foreach ($array as $key => $value) {
				$this->params->add(new KeyValue($key, $value));
			}
		}
	}

	public function __get($key) {
		return $this->params->getValue($key);
	}

	public function __set($key, $value) {
		return $this->params->set($key, $value);
	}

	public function __isset($key) {
		return $this->params->hasKey($key);
	}

	public function __unset($key) {
		$this->params->removeByKey($key);
	}

	public function get($key) {
		return $this->params->getValue($key);
	}

	public function set($key, $value) {
		if ($this->params->hasKey($key)) {
			$this->params->set($key, $value);
		}
	}

	public function add($key, $value, $overwrite = true) {
		if ($this->params->hasKey($key) || $overwrite) {
			$this->params->set($key, $value);
		}
	}

	public function contains($key) {
		return $this->params->hasKey($key);
	}

	/**
	 * @return KeyValue[]
	 */
	public function all() {
		return $this->params->all();
	}

	public function del($key) {
		$this->params->removeByKey($key);
	}

	public function count() {
		return $this->params->count();
	}

	public function rewind(): void {
		$this->params->rewind();
	}

	public function current(): ?KeyValue {
		return $this->params->current();
	}

	public function key(): ?string {
		return ($this->count() > 0) ? $this->current()->key : null;
	}

	public function next(): void {
		$this->params->next();
	}

	public function valid(): bool {
		$key = $this->key();
		return is_string($key) && strlen($key) > 0;
	}

	#[Pure]
	#[\ReturnTypeWillChange]
	public function jsonSerialize(): array {
		return $this->params->toArray();
	}
}
