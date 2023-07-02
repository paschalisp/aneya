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


interface IHookable {
	#region Methods
	/**
	 * Returns object's collection of Hooks
	 */
	public function hooks(): HookCollection;

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
	public function on(string $event, callable $listener, float $weight = 0.0, string $tag = null, bool $once = false);

	/**
	 * Triggers a registered event and returns the triggered functions return values in an array, ordered by the listeners' weight.
	 *
	 * @param string $event The event to trigger.
	 * @param ?EventArgs $args (optional) Arguments to pass to the triggered function.
	 * @param mixed $params (optional) Additional parameters to pass to the triggered function.
	 * @return EventStatus[] Array of the return values of the triggered functions.
	 */
	public function trigger(string $event, EventArgs $args = null, ...$params): array;
	#endregion

	#region Static methods
	/**
	 * Adds a listener on the given static event's hook stack.
	 *
	 * For more information, see the listenEvent() function.
	 *
	 * @param string $event
	 * @param callable $listener
	 * @param float $weight The weight (order) by which the listener will be triggered against the other listeners that listen on the same event.
	 * @param string|null $tag	A tag to identify the callable during program's execution
	 * @param bool $once	If true, the listener will be called only once and then will be removed from the listeners' queue
	 * @return mixed
	 */
	public static function onSt(string $event, callable $listener, float $weight = 0.0, string $tag = null, bool $once = false);

	/**
	 * Triggers a statically registered event and returns the triggered functions return values in an array, order by the listeners' weight.
	 *
	 * @param string $event The event to trigger.
	 * @param ?EventArgs $args (optional) Arguments to pass to the triggered function.
	 * @param mixed $params (optional) Additional parameters to pass to the triggered function.
	 * @return EventStatus[] Array of the return values of the triggered functions.
	 */
	public static function triggerSt(string $event, EventArgs $args = null, ...$params): array;
	#endregion
}
