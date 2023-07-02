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

namespace aneya\Core\Environment;

final class Cookie {
	/**
	 * @see setcookie()
	 *
	 * @param string $name
	 * @param $value
	 * @param int $expire
	 * @return bool
	 */
	public static function setCookie(string $name, $value, int $expire = 0): bool {
		return setcookie($name, $value, $expire, '/');
	}

	public static function getCookie($name): string {
		if (isset ($_COOKIE[$name])) {
			return htmlspecialchars($_COOKIE[$name]);
		} else {
			return '';
		}
	}

	public static function expireCookie($name) {
		unset($_COOKIE[$name]);
		setcookie($name, null, time() - 3600, '/');
	}

	public static function clearCookie($name) {
		self::expireCookie($name);
	}
}
