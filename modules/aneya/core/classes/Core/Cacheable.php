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

trait Cacheable {
	#region Methods
	public function getCacheCategory(): string {
		return get_class($this);
	}

	public function getCacheContentType(): string {
		return Cache::MimeSerialized;
	}

	public function cache() {
		if ($this instanceof IHookable) {
			$this->on(Cache::EventOnCaching, function () {
				return $this->onCaching();
			});
			$this->on(Cache::EventOnCached, function () {
				return $this->onCached();
			});

			$this->trigger(Cache::EventOnCaching);
		}

		return $this->onCache();
	}

	/** Forces the object to expire its cache. */
	public function expireCache() {
		if ($this instanceof ICacheable) {
			Cache::expire($this->getCacheUid(), $this->getCacheCategory());
		}
	}

	/**
	 * Performs loading from database (or cache) given the object's uid
	 *
	 * @param mixed $uid
	 * @param ?callable $callback
	 * @return mixed
	 */
	public static function load(mixed $uid, callable $callback = null): mixed {
		$obj = static::loadFromCache($uid);
		if ($obj == null) {
			$class = static::class;
			$obj = new $class ($uid);

			// Cache object for performance
			Cache::store($obj);
		}

		if (is_callable($callback)) {
			call_user_func($callback, $obj);
		}

		return $obj;
	}

	public static function loadFromCache($uid) {
		return Cache::retrieve(static::class, $uid);
	}
	#endregion

	#region Event methods
	public function onCaching() {
		return null;
	}

	public function onCache() {
		return null;
	}

	public function onCached() {
		return null;
	}
	#endregion
}
