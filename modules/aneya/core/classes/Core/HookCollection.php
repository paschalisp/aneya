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


class HookCollection implements ICollection {
	#region Constants
	const WeightFirst	= -999999;
	const WeightLast	=  999999;
	#endregion

	#region Properties
	/** @var Hook[] */
	protected array $_collection = [];
	#endregion

	#region Constructor
	public function __construct() { }
	#endregion

	#region Methods
	/**
	 * Adds a listener on the given event's hook stack.
	 *
	 * @param string $event
	 * @param callable	$listener
	 * @param float $weight		The weight (order) by which the listener will be triggered against the other listeners that listen on the same event.
	 * @param string|null $tag		A tag to identify the callable during program's execution
	 * @param bool $once		If true, the listener will be called only once and then will be removed from the listeners queue
	 * @return HookCollection
	 */
	public final function on(string $event, callable $listener, float $weight = 0.0, string $tag = null, bool $once = false): HookCollection {
		$hook = $this->itemAt($event);

		if ($hook === null) {
			$this->register($event);
			$hook = $this->itemAt($event);
		}

		$hook->addListener($listener, $weight, $tag, $once);

		return $this;
	}

	/**
	 * Removes a listener from given event's hook stack.
	 *
	 * @param string $event
	 * @param callable|string $listener Listener's callable function or tag
	 *
	 * @return HookCollection
	 */
	public final function off(string $event, callable|string $listener): HookCollection {
		$hook = $this->itemAt($event);

		if ($hook instanceof Hook) {
			$hook->removeListener($listener);
		}

		return $this;
	}

	/**
	 * Triggers a registered event and returns the triggered functions return values in an array, ordered by the listeners' weight.
	 *
	 * @param string $event The event to trigger.
	 * @param EventArgs|null $args (optional) Arguments to pass to the triggered function.
	 * @param mixed $params (optional) Additional parameters to pass to the triggered function.
	 * @return EventStatus[] Array of the return values of the triggered functions.
	 */
	public final function trigger(string $event, EventArgs $args = null, ...$params): array {
		$hook = $this->itemAt($event);

		if ($hook === null) {
			return [];
		}

		return $hook->trigger($args, ...$params);
	}

	/** Enables an event so that it can be triggered. */
	public final function enable(string $event): HookCollection {
		$hook = $this->itemAt($event);

		if ($hook instanceof Hook) {
			$hook->isEnabled = true;
		}

		return $this;
	}

	/** Disables an event from being able to be triggered. */
	public final function disable(string $event): HookCollection {
		$hook = $this->itemAt($event);

		if ($hook instanceof Hook) {
			$hook->isEnabled = false;
		}

		return $this;
	}

	/**
	 * Registers new event(s) in the object's Hook system.
	 *
	 * @param string|string[] $event Event name(s)
	 * @param string|null $tag Event(s) tag
	 * @return HookCollection
	 */
	public final function register(string|array $event, string $tag = null): HookCollection {
		if (is_string($event)) {
			$event = [$event];
		}

		foreach ($event as $ev) {
			if (!is_string($ev)) {
				continue;
			}

			$hook = $this->itemAt($ev);

			if ($hook === null) {
				$this->add(new Hook($ev, $tag));
			}
		}

		return $this;
	}

	/**
	 * Gets the Hook instance having the given event name
	 */
	public final function get(string $event): ?Hook {
		return $this->itemAt($event);
	}

	/**
	 * Gets all Hook instances having the given tag
	 *
	 * @param string $tag
	 *
	 * @return Hook[]
	 */
	public final function getByTag(string $tag): array {
		$hooks = [];
		foreach ($this->_collection as $hook) {
			if ($hook->tag == $tag) {
				$hooks[] = $hook;
			}
		}
		return $hooks;
	}
	#endregion

	#region Interface methods
	/**
	 * Add a value into the collection.
	 * @param Hook $item
	 * @throws \InvalidArgumentException when trying to add a value of wrong type
	 * @return HookCollection
	 */
	public function add($item): HookCollection {
		if (!($item instanceof Hook)) {
			throw new \InvalidArgumentException ("Parameter is not a \\aneya\\Core\\Hook value");
		}

		if (isset($this->_collection[$item->name])) {
			return $this;
		}

		$this->_collection[$item->name] = $item;

		return $this;
	}

	/**
	 * Inserts a value into the collection at the specified index.
	 *
	 * @param Hook $item
	 * @param int $index
	 * @return HookCollection
	 */
	public function insertAt($item, int $index): HookCollection {
		return $this->add($item);
	}

	/**
	 * Sets a value at the specified index in the collection
	 *
	 * @param int $index
	 * @param Hook $item
	 * @return HookCollection
	 *@throws \InvalidArgumentException when trying to set a value of wrong type
	 */
	public function set(int $index, $item): HookCollection {
		if (!($item instanceof Hook)) {
			throw new \InvalidArgumentException ("Parameter is not a \\aneya\\Core\\Hook value");
		}

		if ($index !== $item->name) {
			throw new \InvalidArgumentException ("Cannot set hook under different name");
		}

		if (strlen($item->name) > 0 && key_exists($item->name, $this->_collection)) {
			$this->_collection[$item->name] = $item;
			return $this;
		}
		else {
			return $this->add($item);
		}
	}

	/**
	 * Removes an item from the collection
	 * @param Hook|string $item
	 * @throws \InvalidArgumentException when trying to access an index which is out of the bounds of the collection
	 * @returns HookCollection
	 */
	public function remove($item): HookCollection {
		if ($item instanceof Hook) {
			$item = $item->name;
		}

		if (isset($this->_collection[$item])) {
			unset ($this->_collection[$item]);
			$this->_collection = array_filter($this->_collection, function ($var) { return !is_null($var); });
		}

		return $this;
	}

	/** Removes the item at the specified index from the collection. */
	public function removeAt(int $index): HookCollection {
		if (isset($this->_collection[$index])) {
			unset ($this->_collection[$index]);
			$this->_collection = array_filter($this->_collection, function ($var) { return !is_null($var); });
		}

		return $this;
	}

	/**
	 * Returns the item at the specified index
	 *
	 * @param int|mixed $index
	 * @return ?Hook
	 */
	public function itemAt($index): ?Hook {
		return $this->_collection[$index] ?? null;
	}

	/** Returns the first item in the collection. */
	public function first(): ?Hook {
		foreach ($this->_collection as $key => $hook) {
			return $hook;
		}

		return null;
	}

	/**
	 * Returns the last item in the collection.
	 * If the collection implements ISortable, sort() will be executed before retrieving the last item.
	 *
	 * @return Hook|null
	 */
	public function last(): ?Hook {
		if (count($this->_collection) == 0) {
			return null;
		}

		end($this->_collection);         // move the internal pointer to the end of the array
		$key = key($this->_collection);
		reset($this->_collection);

		return $this->_collection[$key];
	}

	/**
	 * Returns all items in the collection in an array
	 * @return Hook[]
	 */
	public function all(): array {
		return $this->_collection;
	}

	/**
	 * Removes any duplicates from the collection.
	 * If uniqueKeys flag is enabled in the collection, then there is no need to call this method.
	 *
	 * @return $this
	 */
	public function unique(): static {
		$this->_collection = array_unique($this->_collection, SORT_REGULAR);
		return $this;
	}

	/**
	 * Returns the index of the item in the collection
	 * @param object $item
	 * @return int|bool|null
	 */
	public function indexOf($item): bool|int|null {
		if (!($item instanceof Hook)) {
			throw new \InvalidArgumentException ("Parameter is not a \\aneya\\Core\\Hook value");
		}

		if (!isset($this->_collection[$item->name])) {
			return null;
		}

		return $item->name;
	}

	/**
	 * Returns true if the item exists in the collection
	 * @param Hook|string $item
	 * @return bool
	 */
	public function contains($item): bool {
		if ($item instanceof Hook) {
			return isset($this->_collection[$item->name]);
		}
		elseif (is_string($item)) {
			return isset($this->_collection[$item]);
		}

		return false;
	}

	/**
	 * Returns the number of items in the collection
	 *
	 * @param string|null $tag
	 *
	 * @return int
	 */
	public function count(string $tag = null): int {
		if (func_num_args() > 0 && !is_null($tag = func_get_arg(0))) {
			if (isset($this->_collection[$tag]))
				return $this->_collection[$tag]->countListeners();

			return 0;
		}
		else
			return count ($this->_collection);
	}

	/**
	 * Clears the collection by removing all its items
	 * @return HookCollection
	 */
	public function clear(): HookCollection {
		$this->_collection = [];

		return $this;
	}

	/**
	 * Returns the variable type (or fully qualified class name for objects) of the items contained in the collection
	 * @return string
	 */
	public function itemsType(): string {
		return '\\aneya\\Core\\Hook';
	}


	/**
	 * (PHP 5 &gt;= 5.0.0)<br/>
	 * Return the current element
	 *
	 * @link http://php.net/manual/en/iterator.current.php
	 * @return bool|Hook
	 */
	public function current(): bool|Hook {
		return current ($this->_collection);
	}

	/**
	 * (PHP 5 &gt;= 5.0.0)<br/>
	 * Move forward to next element
	 *
	 * @link http://php.net/manual/en/iterator.next.php
	 * @return void Any returned value is ignored.
	 */
	public function next(): void {
		next ($this->_collection);
	}

	/**
	 * (PHP 5 &gt;= 5.0.0)<br/>
	 * Return the key of the current element
	 *
	 * @link http://php.net/manual/en/iterator.key.php
	 * @return int|string|null scalar on success, or null on failure.
	 */
	public function key(): int|string|null {
		return key ($this->_collection);
	}

	/**
	 * (PHP 5 &gt;= 5.0.0)<br/>
	 * Checks if current position is valid
	 *
	 * @link http://php.net/manual/en/iterator.valid.php
	 * @return boolean The return value will be casted to boolean and then evaluated.
	 *       Returns true on success or false on failure.
	 */
	public function valid(): bool {
		$key = key ($this->_collection);
		return ($key !== null);
	}

	/**
	 * (PHP 5 &gt;= 5.0.0)<br/>
	 * Rewind the Iterator to the first element
	 *
	 * @link http://php.net/manual/en/iterator.rewind.php
	 * @return void Any returned value is ignored.
	 */
	public function rewind(): void {
		reset ($this->_collection);
	}

	/**
	 * (PHP 5 &gt;= 5.0.0)<br/>
	 * Whether a offset exists
	 *
	 * @link http://php.net/manual/en/arrayaccess.offsetexists.php
	 * @param int $offset   <p>
	 *                      An offset to check for.
	 *                      </p>
	 * @return boolean true on success or false on failure.
	 *                      </p>
	 *                      <p>
	 *                      The return value will be casted to boolean if non-boolean was returned.
	 */
	public function offsetExists($offset): bool {
		return isset($this->_collection[$offset]);
	}

	/**
	 * (PHP 5 &gt;= 5.0.0)<br/>
	 * Offset to retrieve
	 *
	 * @link http://php.net/manual/en/arrayaccess.offsetget.php
	 * @param int $offset   <p>
	 *                      The offset to retrieve.
	 *                      </p>
	 * @return Hook|null Can return all value types.
	 */
	public function offsetGet($offset): ?Hook {
		return $this->itemAt($offset);
	}

	/**
	 * (PHP 5 &gt;= 5.0.0)<br/>
	 * Offset to set
	 *
	 * @link http://php.net/manual/en/arrayaccess.offsetset.php
	 * @param int $offset   <p>
	 *                      The offset to assign the value to.
	 *                      </p>
	 * @param mixed $value  <p>
	 *                      The value to set.
	 *                      </p>
	 * @return void
	 */
	public function offsetSet($offset, mixed $value): void {
		$this->set($offset, $value);
	}

	/**
	 * (PHP 5 &gt;= 5.0.0)<br/>
	 * Offset to unset
	 *
	 * @link http://php.net/manual/en/arrayaccess.offsetunset.php
	 * @param int $offset   <p>
	 *                      The offset to unset.
	 *                      </p>
	 * @return void
	 */
	public function offsetUnset($offset): void {
		$this->removeAt($offset);
	}
	#endregion
}
