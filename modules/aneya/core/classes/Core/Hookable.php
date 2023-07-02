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

trait Hookable {
	#region Properties
	private ?HookCollection $_hooks;
	private static ?HookCollection $_hooksSt;
	#endregion

	#region Methods
	/**
	 * Returns object's collection of Hooks
	 * @return HookCollection
	 */
	public final function hooks(): HookCollection {
		if (!isset($this->_hooks)) {
			$this->_hooks = new HookCollection();
			$this->_hooks->register('onEvent');
		}

		return $this->_hooks;
	}

	/**
	 * Adds a listener on the given event's hook stack.
	 *
	 * @param string $event
	 * @param callable $listener
	 * @param float $weight The weight (order) by which the listener will be triggered against the other listeners that listen on the same event.
	 * @param string|null $tag	A tag to identify the callable during program's execution
	 * @param bool $once	If true, the listener will be called only once and then will be removed from the listeners' queue
	 * @return mixed
	 */
	public final function on(string $event, callable $listener, float $weight = 0.0, string $tag = null, bool $once = false) {
		$this->hooks()->on($event, $listener, $weight, $tag, $once);

		return $this;
	}

	/**
	 * Removes a listener from given event's hook stack.
	 *
	 * @param string $event
	 * @param callable|string $listener Listener's callable function or tag
	 *
	 * @return mixed
	 */
	public final function off(string $event, $listener) {
		$this->hooks()->off($event, $listener);

		return $this;
	}

	/**
	 * Triggers a registered event and returns the triggered functions return values in an array, ordered by the listeners' weight.
	 *
	 * @param string $event The event to trigger.
	 * @param ?EventArgs $args (optional) Arguments to pass to the triggered function.
	 * @param mixed $params (optional) Additional parameters to pass to the triggered function.
	 * @return EventStatus[] Array of the return values of the triggered functions.
	 */
	public final function trigger(string $event, EventArgs $args = null, ...$params): array {
		return $this->hooks()->trigger($event, $args, ...$params);
	}
	#endregion

	#region Static methods
	/**
	 * Returns class's collection of static Hooks
	 */
	public final static function hooksSt(): HookCollection {
		if (!isset(self::$_hooksSt)) {
			self::$_hooksSt = new HookCollection();
			self::$_hooksSt->register('onEvent');
		}

		return self::$_hooksSt;
	}

	/**
	 * Adds a listener on the given static event's hook stack.
	 *
	 * @param string $event
	 * @param callable $listener
	 * @param float $weight The weight (order) by which the listener will be triggered against the other listeners that listen on the same event.
	 * @param string|null $tag	A tag to identify the callable during program's execution
	 * @param bool $once	If true, the listener will be called only once and then will be removed from the listeners' queue
	 * @return mixed
	 */
	public static final function onSt(string $event, callable $listener, float $weight = 0.0, string $tag = null, bool $once = false) {
		return static::hooksSt()->on($event, $listener, $weight, $tag, $once);
	}

	/**
	 * Triggers a statically registered event and returns the triggered functions return values in an array, order by the listeners' weight.
	 *
	 * @param string $event The event to trigger.
	 * @param ?EventArgs $args (optional) Arguments to pass to the triggered function.
	 * @param mixed $params (optional) Additional parameters to pass to the triggered function.
	 * @return EventStatus[] Array of the return values of the triggered functions.
	 */
	public static final function triggerSt(string $event, EventArgs $args = null, ...$params): array {
		return static::hooksSt()->trigger($event, $args, ...$params);
	}
	#endregion
}
