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

use Monolog\Logger;

class Collection extends CoreObject implements ICollection, \JsonSerializable {
	#region Constants
	const ActionItemAdded   = 'C';
	const ActionItemChanged = 'U';
	const ActionItemDeleted = 'D';
	#endregion

	#region Events
	/** Triggered when an item has been added to the Collection. Passes a CollectionEventArgs argument on listeners. */
	const EventOnItemAdded = 'OnItemAdded';
	/** Triggered when an item changes index within the Collection. Passes a CollectionEventArgs argument on listeners. */
	const EventOnItemChanged = 'OnItemChanged';
	/** Triggered when an item has been removed from the Collection. Passes a CollectionEventArgs argument on listeners. */
	const EventOnItemRemoved = 'OnItemRemoved';

	/** Triggered when calling the collect() method. Passes a CollectionDelegateEventArgs argument on listeners. */
	const EventOnCollect = 'OnCollect';
	#endregion

	#region Properties
	/** @var mixed */
	protected mixed $id = null;

	/** @var array|object[] */
	protected array $_collection = [];

	/** @var ?string The type or fully qualified class name of the items that are stored in the collection */
	protected ?string $_type = null;

	/** @var bool Indicates whether the collection should allow multiple items of the same value or automatically update existing keys on new value assignments */
	protected bool $_uniqueKeys = false;

	/** @var bool Indicates if events triggering should be suspended from being fired */
	protected bool $_suspendCollectionEvents = false;
	#endregion

	#region Constructor
	/**
	 * @param string $type
	 * @param bool   $uniqueKeys Indicates whether the collection should allow multiple items of the same value or automatically update existing keys on new value assignments
	 */
	public function __construct() {
		$this->_collection = [];

		$numArgs = func_num_args();
		if ($numArgs > 0) {
			$this->_type = func_get_arg(0);
		}

		if ($numArgs > 1) {
			$this->_uniqueKeys = (bool)func_get_arg(1);
		}

		$this->hooks()->register([self::EventOnCollect, self::EventOnItemAdded, self::EventOnItemChanged, self::EventOnItemRemoved]);
	}
	#endregion

	#region Methods
	/**
	 * Returns true if the parameter is valid to be added to the collection
	 *
	 * @param mixed $item
	 *
	 * @return bool
	 */
	public function isValid(mixed $item): bool {
		if (is_null($this->_type) || strlen($this->_type) == 0)
			return true;

		if (is_object($item) && is_a($item, $this->_type))
			return true;

		if (is_scalar($item) && in_array($this->_type, array ('int', 'integer', 'float', 'bool', 'boolean', 'string')))
			return true;

		if ($this->_type == 'array')
			return true;

		return false;
	}

	public function isScalar(): bool {
		return (in_array($this->_type, array ('int', 'integer', 'float', 'bool', 'string')));
	}

	/**
	 * Triggers the OnCollect event and adds any returned items from listeners to the Collection.
	 *
	 * It is useful when the collection needs to delegate filling with items to different listeners.
	 *
	 * @return int Returns the number of items collected.
	 */
	public function collect(): int {
		$num = 0;
		$statuses = $this->trigger(self::EventOnCollect, new CollectionDelegateEventArgs($this, $this));
		foreach ($statuses as $st) {
			if ($st->isOK() && $st instanceof CollectionDelegateEventStatus) {
				foreach ($st->items as $item) {
					if ($this->isValid($item)) {
						$this->add($item);
						$num++;
					}
				}
			}
		}

		return $num;
	}

	/**
	 * Filters the collection by user-defined criteria and returns a new collection of same type with the items
	 * that matched the criteria.
	 *
	 * The callable function will be passed with all collection's items one by one and should return true or false
	 * depending on whether the item passes the criteria or not.
	 *
	 * @see array_filter()
	 *
	 * @param callable $f
	 *
	 * @return $this
	 */
	public function filter(callable $f): static {
		/** @var Collection $class */
		$class = static::class;
		$collection = new $class();

		$items = array_filter($this->_collection, $f);
		$collection->addRange($items);

		return $collection;
	}

	/**
	 * Applies a callable to all items in the collection and returns the resulted array.
	 *
	 * @see array_map()
	 */
	public function map(callable $f): array {
		return array_map($f, $this->_collection);
	}

	/**
	 * Iterates through all items in the collection and calls the given callable with each item as the argument.
	 *
	 * @see array_map()
	 *
	 * @param callable $f
	 *
	 * @return static
	 */
	public function forEach(callable $f): static {
		foreach ($this->_collection as $item) {
			$f($item);
		}

		return $this;
	}
	#endregion

	#region Interface implementation
	#region ICollection
	/**
	 * Returns all items in the collection in an array
	 *
	 * @param callable|null $f Used to apply filtering before returning the array of items.
	 * @return array
	 */
	public function all(callable $f = null): array {
		if (is_callable($f))
			return $this->filter($f)->all();
		else
			return $this->_collection;
	}

	/**
	 * Removes any duplicates from the collection.
	 * If uniqueKeys flag is enabled in the collection, then there is no need to call this method.
	 *
	 * @return static
	 */
	public function unique(): static {
		$this->_collection = array_unique($this->_collection, SORT_REGULAR);
		return $this;
	}

	/**
	 * Returns the first item in the collection.
	 * If the collection implements ISortable, sort() will be executed before retrieving the first item.
	 *
	 * @param callable|null $f Used to apply filtering before returning the first item.
	 *
	 * @return mixed
	 */
	public function first(callable $f = null): mixed {
		if ($this instanceof ISortable)
			$this->sort();

		if (is_callable($f))
			return $this->filter($f)->first();

		return $this->itemAt(0);
	}

	/**
	 * Returns the last item in the collection.
	 * If the collection implements ISortable, sort() will be executed before retrieving the last item.
	 *
	 * @param callable|null $f Used to apply filtering before returning the last item.
	 *
	 * @return mixed
	 */
	public function last(callable $f = null): mixed {
		if (($cnt = $this->count()) == 0)
			return null;

		if ($this instanceof ISortable)
			$this->sort();

		if (is_callable($f))
			return $this->filter($f)->last();

		return $this->itemAt($cnt - 1);
	}

	/**
	 * Adds a value into the collection.
	 *
	 * @param mixed $item
	 *
	 * @throws \InvalidArgumentException
	 * @triggers OnItemAdded
	 * @return static
	 */
	public function add(mixed $item): static {
		if (!$this->isValid($item)) {
			throw new \InvalidArgumentException ("Parameter is not a $this->_type value");
		}

		if ($this->_uniqueKeys) {
			// Force strict mode (===) if values are not plain scalars
			if (in_array($item, $this->_collection, !$this->isScalar()))
				return $this;
		}

		$this->_collection[] = $item;
		if (!$this->_suspendCollectionEvents) {
			$this->trigger(self::EventOnItemAdded, new CollectionEventArgs ($this, self::ActionItemAdded, $item));
		}

		return $this;
	}

	/**
	 * Adds a range of values into the collection.
	 *
	 * @param array $items
	 *
	 * @throws \InvalidArgumentException
	 * @triggers OnItemAdded
	 * @return static
	 */
	public function addRange(array $items): static {
		foreach ($items as $item) {
			if (!$this->isValid($item))
				continue;

			$this->add($item);
		}

		return $this;
	}

	/**
	 * Inserts a value into the collection at the specified index (zero-based).
	 *
	 * @param mixed $item
	 * @param int $index
	 *
	 * @return static
	 * @triggers OnItemAdded
	 */
	public function insertAt(mixed $item, int $index): static {
		if ($index < 0 || $index > $this->count())
			throw new \OutOfRangeException("Index $index is out of range");

		if (!$this->isValid($item))
			throw new \InvalidArgumentException ("Parameter is not a $this->_type value");

		if ($this->_uniqueKeys && in_array($item, $this->_collection, !$this->isScalar()))
			return $this;

		if (is_scalar($item))
			array_splice($this->_collection, $index, 0, $item);
		else
			array_splice($this->_collection, $index, 0, [$item]);

		if (!$this->_suspendCollectionEvents) {
			$this->trigger(self::EventOnItemAdded, new CollectionEventArgs ($this, self::ActionItemAdded, $item));
		}

		return $this;
	}

	/**
	 * Replaces a value with the provided one at the specified index (zero-based).
	 *
	 * Alias of Collection::set()
	 *
	 * @param mixed|array $item
	 * @param int $index
	 *
	 * @return static
	 * @triggers OnItemAdded
	 */
	public function replaceAt(mixed $item, int $index): static {
		return $this->set($index, $item);
	}

	/**
	 * Sets a value at the specified index in the collection
	 *
	 * @param int $index
	 * @param mixed $item
	 *
	 * @return $this
	 * @triggers OnItemChanged
	 */
	public function set(int $index, $item): static {
		if ($index < 0 || $index >= $this->count())
			return $this;

		if (!$this->isValid($item))
			throw new \InvalidArgumentException ("Parameter is not a $this->_type value");

		$oldItem = $this->_collection[$index];
		$this->_collection[$index] = $item;

		if (!$this->_suspendCollectionEvents) {
			$this->trigger(self::EventOnItemChanged, new CollectionEventArgs ($this, self::ActionItemChanged, $item, $oldItem));
		}

		return $this;
	}

	/**
	 * Removes an item (specified by index or object) from the collection
	 *
	 * @param mixed $item
	 *
	 * @return $this
	 * @triggers OnItemRemoved
	 */
	public function remove($item): static {
		if ($this->count() <= 0)
			return $this;

		if (!$this->isValid($item))
			return $this;

		$index = $this->indexOf($item);
		if ($index === false)
			return $this;

		array_splice($this->_collection, $index, 1);

		if (!$this->_suspendCollectionEvents)
			$this->trigger(self::EventOnItemRemoved, new CollectionEventArgs ($this, self::ActionItemDeleted, null, $item));

		return $this;
	}

	/**
	 * Removes an item (specified by index or object) from the collection
	 *
	 * @param int $index
	 *
	 * @return bool true if the item was found and removed from the collection.
	 * @triggers OnItemRemoved
	 */
	public function removeAt(int $index): bool {
		if ($this->count() <= 0)
			return false;

		$item = $this->itemAt($index);

		if (!$this->isValid($item))
			return false;

		array_splice($this->_collection, $index, 1);

		if (!$this->_suspendCollectionEvents) {
			$this->trigger(self::EventOnItemRemoved, new CollectionEventArgs ($this, self::ActionItemDeleted, null, $item));
		}

		return true;
	}

	/**
	 * Extracts a slice from the collection and returns a new collection of the same class containing the sliced items.
	 *
	 * @see array_slice()
	 *
	 * @param $offset
	 * @param $length
	 *
	 * @return $this
	 */
	public function slice($offset, $length = null): static {
		try {
			$class = static::class;

			$collection = new $class();
			$items = array_slice($this->_collection, $offset, $length);
			$collection->addRange($items);

			return $collection;
		}
		catch (\Exception $e) {
			CMS::app()->log($e, Logger::WARNING);
			return $this;
		}
	}

	/**
	 * Returns the item at the specified index
	 *
	 * @param int $index
	 *
	 * @return mixed The item or null if no item was found at the specified index
	 */
	public function itemAt(int $index): mixed {
		if ($index < 0 || $index >= $this->count())
			return null;

		return $this->_collection[$index];
	}

	/**
	 * Returns the index of the item in the collection
	 *
	 * @param mixed $item
	 *
	 * @return int|bool
	 * @throws \InvalidArgumentException
	 */
	public function indexOf($item): bool|int {
		if (!$this->isValid($item))
			throw new \InvalidArgumentException ("Parameter is not a $this->_type value");

		if (($max = $this->count()) <= 0)
			return false;

		for ($i = 0; $i < $max; $i++) {
			if ($item === $this->_collection[$i]) {
				return $i;
			}
		}

		return false;
	}

	/**
	 * Returns true if the collection contains the given item
	 *
	 * @param mixed $item
	 *
	 * @return bool
	 */
	public function contains(mixed $item): bool {
		if ($this->isValid($item)) {

			if ($this->isScalar()) {
				foreach ($this->_collection as $i)
					if ($i == $item)
						return true;
			}
			else {
				foreach ($this->_collection as $i)
					if ($i === $item)
						return true;
			}
		}

		return false;
	}

	/**
	 * Returns the number of items in the collection.
	 *
	 * @param ?callable $f Used to apply filtering before returning the count.
	 *
	 * @return int
	 */
	public function count(callable $f = null): int {
		if (is_callable($f))
			return count(array_filter($this->_collection, $f));

		return count($this->_collection);
	}

	/**
	 * Clears the collection by removing all its items
	 *
	 * @return $this
	 */
	public function clear(): static {
		$_collection = $this->_collection;

		// Clear the array
		$this->_collection = [];

		if (!$this->_suspendCollectionEvents) {
			$args = new CollectionEventArgs ($this, self::ActionItemDeleted);

			// Trigger an event for every removed item
			foreach ($_collection as $item) {
				$args->oldItem = $item;
				$this->trigger(self::EventOnItemRemoved, $args);
			}
		}

		return $this;
	}

	/**
	 * Returns the variable type (or fully qualified class name for objects) of the items contained in the collection
	 *
	 * @return string
	 */
	public function itemsType(): string {
		return $this->_type;
	}

	/**
	 * Gets/Sets the flag to suspend collection's events from being fired
	 *
	 * @param bool|null $suspend (optional)
	 *
	 * @return bool
	 */
	public function suspendCollectionEvents(bool $suspend = null): bool {
		if ($suspend !== null) {
			$this->_suspendCollectionEvents = $suspend;
		}

		return $this->_suspendCollectionEvents;
	}

	/**
	 * (PHP 5 &gt;= 5.0.0)<br/>
	 * Return the current element
	 *
	 * @link http://php.net/manual/en/iterator.current.php
	 * @return mixed
	 */
	public function current(): mixed {
		return current($this->_collection);
	}

	/**
	 * (PHP 5 &gt;= 5.0.0)<br/>
	 * Move forward to next element
	 *
	 * @link http://php.net/manual/en/iterator.next.php
	 * @return void Any returned value is ignored.
	 */
	public function next(): void {
		next($this->_collection);
	}

	/**
	 * (PHP 5 &gt;= 5.0.0)<br/>
	 * Return the key of the current element
	 *
	 * @link http://php.net/manual/en/iterator.key.php
	 * @return int|string|null scalar on success, or null on failure.
	 */
	public function key(): int|string|null {
		return key($this->_collection);
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
		$key = key($this->_collection);
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
		reset($this->_collection);
	}

	/**
	 * (PHP 5 &gt;= 5.0.0)<br/>
	 * Whether a offset exists
	 *
	 * @link http://php.net/manual/en/arrayaccess.offsetexists.php
	 *
	 * @param int $offset   <p>
	 *                      An offset to check for.
	 *                      </p>
	 *
	 * @return boolean true on success or false on failure.
	 *                      </p>
	 *                      <p>
	 *                      The return value will be casted to boolean if non-boolean was returned.
	 */
	public function offsetExists($offset): bool {
		$offset = (int)$offset;
		return ($offset >= 0 && $offset < $this->count());
	}

	/**
	 * (PHP 5 &gt;= 5.0.0)<br/>
	 * Offset to retrieve
	 *
	 * @link http://php.net/manual/en/arrayaccess.offsetget.php
	 *
	 * @param int $offset   <p>
	 *                      The offset to retrieve.
	 *                      </p>
	 *
	 * @return mixed Can return all value types.
	 */
	public function offsetGet($offset): mixed {
		return $this->itemAt($offset);
	}

	/**
	 * (PHP 5 &gt;= 5.0.0)<br/>
	 * Offset to set
	 *
	 * @link http://php.net/manual/en/arrayaccess.offsetset.php
	 *
	 * @param int   $offset <p>
	 *                      The offset to assign the value to.
	 *                      </p>
	 * @param mixed $value  <p>
	 *                      The value to set.
	 *                      </p>
	 *
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
	 *
	 * @param int $offset   <p>
	 *                      The offset to unset.
	 *                      </p>
	 *
	 * @return void
	 */
	public function offsetUnset($offset): void {
		$this->removeAt($offset);
	}
	#endregion

	#[\ReturnTypeWillChange]
	public function jsonSerialize(): array {
		if ($this instanceof ISortable)
			$this->sort();

		$collection = [];
		if (is_subclass_of($this->itemsType(), '\JsonSerializable')) {
			/** @var \JsonSerializable $item */
			foreach ($this->_collection as $item) {
				try {
					$collection[] = $item->jsonSerialize();
				}
				catch (\Exception $e) {}
			}
		}
		else
			$collection = $this->_collection;

		return $collection;
	}
	#endregion
}
