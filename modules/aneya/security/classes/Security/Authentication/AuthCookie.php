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

namespace aneya\Security\Authentication;

use aneya\Core\CMS;
use aneya\Core\Environment\Cookie;
use aneya\Security\Token;
use aneya\Security\User;

class AuthCookie {
	#region Constants
	const AuthCookie			= 'auth_token';
	const AuthRememberCookie	= 'auth_token_remember';
	#endregion

	#region Properties
	private static AuthCookie $_instance;
	#endregion

	#region Constructor
	private function __construct() {
		static::$_instance = $this;
	}
	#endregion

	#region Methods
	/** Clears the authentication cookies for the given namespace. */
	public function clear(string $namespace) {
		Cookie::clearCookie(self::AuthCookie . "_$namespace");
		Cookie::clearCookie(self::AuthRememberCookie . "_$namespace");

		// Also clear user information from Session
		CMS::session()->del('user_id', $namespace);
		CMS::session()->del('user_class', $namespace);
	}

	/** Returns the (JWT-compatible) authentication token string that is found in Request's cookies. */
	public function getToken(string $namespace): string {
		return Cookie::getCookie(self::AuthCookie . "_$namespace");
	}

	/** Returns true if the "remember" flag is set in Request's authentication cookies. */
	public function getRemember(string $namespace): bool {
		return (bool)Cookie::getCookie(self::AuthRememberCookie . "_$namespace");
	}

	/**
	 * Sends an authentication cookie to the browser via the response headers.
	 * (@see setcookie())
	 */
	public function setAuthCookie(string $namespace, User $user, bool $rememberMe = false): bool {
		$ns = CMS::namespaces()->get($namespace);
		if ($ns === null || $ns->options->authCookie === null)
			return false;

		#region Calculate cookie expiration (in Unix epoch time)
		if ($rememberMe) {
			$date = new \DateTime();
			try {
				$date->add(new \DateInterval(sprintf("PT%dS", (int)$ns->options->authCookie->timeout)));
			}
			catch (\Exception $e) {}

			$expire = (int)$date->format('U');
		}
		else
			// Zero, to expire when browser closes
			$expire = 0;
		#endregion

		// Generate JWT-compatible token to store in the cookie
		$token = Token::encode($ns->options->authCookie->key, null, (int)$ns->options->authCookie->timeout, null, $user);

		// Send the authentication cookie to the browser
		$ret = Cookie::setCookie(self::AuthCookie . "_$namespace", $token, $expire);
		if (!$ret)
			return false;

		if ($rememberMe)
			// Also send the "remember" flag cookie to the browser
			Cookie::setCookie(self::AuthRememberCookie . "_$namespace", true, $expire);
		else
			Cookie::clearCookie(self::AuthRememberCookie . "_$namespace");

		// Store user information in Session
		CMS::session()->set('user_id', $user->id, $namespace);
		CMS::session()->set('user_class', get_class($user), $namespace);

		return true;
	}
	#endregion

	#region Static methods
	/** Returns authentication cookie's singleton instance. */
	public static function instance(): AuthCookie {
		if (!isset(static::$_instance))
			static::$_instance = new AuthCookie();

		return static::$_instance;
	}
	#endregion
}
