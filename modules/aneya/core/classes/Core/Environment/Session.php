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

namespace aneya\Core\Environment;

use aneya\Core\CMS;

class Session {
	#region Constructor
	private function __construct() { }

	/** @var ?Session */
	protected static ?Session $_session;
	#endregion

	#region Methods
	/**
	 * Returns the value of a session variable in the given or current namespace.
	 * @param         $key
	 * @param ?string $namespace (optional)
	 *
	 * @return mixed
	 */
	public function get($key, string $namespace = null): mixed {
		if (empty($namespace))
			$namespace = CMS::ns()->tag ?? '';

		return $_SESSION['session_' . $namespace . '_' . $key] ?? null;
	}

	/**
	 * Sets a session variable in current or given namespace.
	 *
	 * @param string $key
	 * @param mixed  $value
	 * @param string $namespace The application namespace to set a variable to
	 *
	 * @return Session
	 */
	public function set($key, $value, $namespace = null) {
		if (empty($namespace))
			$namespace = CMS::ns()->tag;

		$_SESSION['session_' . $namespace . '_' . $key] = $value;

		return $this;
	}

	/** Unsets a session variable from current or given namespace. */
	public function del($key, $namespace = null): static {
		if (empty($namespace))
			$namespace = CMS::ns()->tag;

		if (isset($_SESSION['session_' . $namespace . '_' . $key]))
			unset($_SESSION['session_' . $namespace . '_' . $key]);

		return $this;
	}

	/** Returns the number of variables set under current or given namespace. */
	public function count(string $namespace = null): int {
		if (empty($namespace))
			$namespace = CMS::ns()->tag;

		$num = 0;
		foreach ($_SESSION as $key => $value)
			if (str_starts_with($key, 'session_' . $namespace . '_'))
				$num++;

		return $num;
	}

	/** Returns true if the session variable is set in current or given namespace. */
	public function exists(string $key, string $namespace = null): bool {
		if (empty($namespace))
			$namespace = CMS::ns()->tag;

		return (isset($_SESSION['session_' . $namespace . '_' . $key]));
	}

	/** Clears all session variables in current or given namespace. */
	public function clear(string $namespace = null): static {
		if (empty($namespace))
			$namespace = CMS::ns()->tag;

		if (is_array($_SESSION) && count($_SESSION) > 0) {
			$keys = array_keys($_SESSION);
			foreach ($keys as $key)
				if (strpos($key, 'session_' . $namespace) === 0)
					unset($_SESSION[$key]);
		}

		return $this;
	}

	/**
	 * Commits session information.
	 * It is automatically called by aneya during shutdown and should not be explicitly called.
	 *
	 * @see session_commit()
	 */
	public function commit() {
		session_commit();
	}

	/** Sets Session's current namespace */
	public static function setNamespace(string $namespace) {
		if (!is_string($namespace)) {
			Debug::warn("Argument '$namespace' is not a string");
			return;
		}
		self::$ns = $namespace;
	}
	#endregion

	#region Static methods
	public static function instance(): static {
		if (!isset(static::$_session))
			static::$_session = new Session();

		return static::$_session;
	}
	#endregion
}
