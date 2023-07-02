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

namespace aneya\Security;

use aneya\Core\CMS;
use aneya\Core\Environment\Environment;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\JWT;

class Token {
	#region Properties
	/** @var string */
	public string $issuer;
	/** @var int */
	public int $issuedAt;
	/** @var int Expires (in seconds) */
	public int $expiresIn;
	/** @var \stdClass|mixed */
	public mixed $data;
	#endregion

	#region Constructor
	/**
	 * Token constructor.
	 *
	 * @param ?string $issuer
	 * @param ?int $expiresIn (in seconds)
	 * @param ?object|mixed $data
	 */
	public function __construct(string $issuer = null, int $issuedAt = null, int $expiresIn = 120, mixed $data = null) {
		$this->issuer = $issuer ?? (Environment::instance()->isCLI() ? CMS::app()->name : $_SERVER['SERVER_NAME']);
		$this->issuedAt = $issuedAt ?? time();
		$this->expiresIn = $expiresIn;
		$this->data = (object)$data ?? new \stdClass();
	}
	#endregion

	#region Methods
	/** Generates a JWT token string based on token's properties. */
	public function generate(string $key, User $user = null, string $alg = 'HS256'): string {
		$token = [
			'iss'  => $this->issuer,											// Issuer
			'iat'  => $this->issuedAt = time(),									// Issued at
			'nbf'  => $this->issuedAt,											// Not before
			'exp'  => $this->issuedAt + $this->expiresIn,						// Expire
			'data' => $this->data												// Data to carriage within the token
		];

		// Add user information
		if ($user !== null) {
			if (!isset($token['data']->userId))
				$token['data']->userId = $user->id;
			if (!isset($token['data']->username))
				$token['data']->username = $user->username;
			if (!isset($token['data']->userClass))
				$token['data']->userClass = get_class($user);
		}

		return JWT::encode($token, $key, $alg);
	}

	/**
	 * Returns the user instance that corresponds to the User information that is set in the payload (if any).
	 *
	 * @param string|null $namespace
	 *
	 * @return User|null
	 */
	public function user(string $namespace = null): ?User {
		if (isset($this->data->userId) && isset($this->data->username) && isset($this->data->userClass)) {
			if ($this->data->userClass == 'aneya\\Security\\User' || is_subclass_of($this->data->userClass, 'aneya\\Security\\User')) {
				try {
					/** @var User $class */
					$class = $this->data->userClass;
					$user = $class::load($this->data->userId, null, $namespace);

					if ($user instanceof $class && $user->username === $this->data->username)
						return $user;
				}
				catch (\Exception $e) {
					return null;
				}
			}
		}

		return null;
	}
	#endregion

	#region Static methods
	/**
	 * Generates, encodes and returns a JWT token string.
	 *
	 * @see Token::generate()
	 *
	 * @param string $key
	 * @param ?string $issuer
	 * @param ?int $expiresIn (in seconds)
	 * @param object $data
	 * @param ?User $user
	 * @param string $alg
	 *
	 * @return string
	 */
	public static function encode(string $key, string $issuer = null, int $expiresIn = null, mixed $data = null, User $user = null, string $alg = 'HS256'): string {
		$token = new Token($issuer, null, $expiresIn, $data);
		return $token->generate($key, $user, $alg);
	}

	/**
	 * Decodes a JWT token and returns it's payload as a \stdClass.
	 *
	 * @param string $token
	 * @param string $key
	 * @param array $alg
	 *
	 * @return Token
	 *
	 * @throws ExpiredException
	 * @see JWT::decode()
	 */
	public static function decode(string $token, string $key, array $alg = ['HS256']): Token {
		$token = JWT::decode($token, $key, $alg);
		return new Token($token->iss, $token->iat, $token->exp - $token->iat, $token->data);
	}
	#endregion
}
