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

class Hook {
	#region Properties
	/** @var string Event's name */
	public $name;
	/** @var string Event's tag or group */
	public $tag;
	/** @var bool */
	public $isEnabled = true;

	/** @var \stdClass[] */
	private $_eventListeners = [];
	#endregion

	#region Constructor
	public function __construct($name = null, $tag = null, $isEnabled = true) {
		$this->name = $name;
		$this->tag = $tag;
		$this->isEnabled = $isEnabled;
	}
	#endregion

	#region Methods
	/**
	 * Triggers the hook and returns the listeners' return values in an array, ordered by the listeners' weight.
	 * @param ?EventArgs $args (optional) Arguments to pass to the triggered function.
	 * @param mixed $params (optional) Additional parameters to pass to the triggered function.
	 * @return EventStatus[] Array of the return values of the triggered functions.
	 */
	public final function trigger(EventArgs $args = null, ...$params): array {
		if (count ($this->_eventListeners) == 0)
			return [];

		// Check whether event is enabled; otherwise return
		if ($this->isEnabled !== true)
			return [];

		usort($this->_eventListeners, function ($a, $b) {
			if ($a->weight == $b->weight) {
				return 0;
			}
			return ($a->weight < $b->weight) ? -1 : 1;
		});

		$statuses = [];

		foreach ($this->_eventListeners as $idx => $listener) {
			$func = $listener->func;
			$ret = $func($args, ...$params);

			if ($ret instanceof EventStatus) {
				$statuses[] = $ret;
			}

			// If listener is flagged to be called only once, remove it from the queue
			if ($listener->once == true) {
				unset($this->_eventListeners[$idx]);
				$changed = true;
			}
		}

		if (isset($changed))
			$this->_eventListeners = array_values($this->_eventListeners);

		return $statuses;
	}

	/**
	 * Adds a listener into hook's list of event listeners.
	 *
	 * @param callable $listener
	 * @param float    $weight
	 * @param string|null $tag
	 * @param bool $once if true, it will be called only once and then will be removed from the queue
	 *
	 * @return Hook
	 */
	public final function addListener(callable $listener, float $weight = 0.0, string $tag = null, bool $once = false): Hook {
		foreach ($this->_eventListeners as $l) {
			if ($l->func === $listener) {
				$l->weight = $weight;
				return $this;
			}
		}

		$l = new \stdClass();
		$l->func = $listener;
		$l->weight = $weight;
		$l->tag = $tag;
		$l->once = $once;

		$this->_eventListeners[] = $l;

		return $this;
	}

	/**
	 * Removes a listener from hook's list of event listeners.
	 * @param callable|string $listener Listener's callable function or tag
	 *
	 * @return Hook
	 */
	public final function removeListener($listener): Hook {
		$cnt = count($this->_eventListeners);
		for ($i = 0; $i < $cnt; $i++) {
			if ((is_callable($listener) && $this->_eventListeners[$i]->func == $listener) || (is_string($listener) && $this->_eventListeners[$i]->tag == $listener)) {
				unset($this->_eventListeners[$i]);
				$changed = true;
			}
		}
		if (isset($changed))
			$this->_eventListeners = array_values($this->_eventListeners);

		return $this;
	}

	/**
	 * Returns the number of event listeners attached in the hook
	 */
	public final function countListeners(): int {
		return count($this->_eventListeners);
	}
	#endregion

	#region Magic methods
	public function __sleep() {
		// Clear event listeners or a warning will be produced (cannot serialize callables)
		$this->_eventListeners = [];

		return ['name', 'tag', 'isEnabled'];
	}
	#endregion
}
