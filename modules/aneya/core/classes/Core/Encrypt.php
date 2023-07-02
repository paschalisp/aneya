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

use Defuse\Crypto\Crypto;
use Defuse\Crypto\Exception\EnvironmentIsBrokenException;
use Defuse\Crypto\Key;

class Encrypt {
	#region Constants
	/** Plain text passwords */
	const PlainText = 0;
	/** AES two-way encryption algorithm */
	const AES = 2;
	/** MD5 one-way encryption algorithm */
	const MD5 = 1;
	/** SHA256 one-way encryption algorithm */
	const SHA256 = 3;
	/** Blowfish one-way encryption algorithm */
	const BCRYPT = 4;
	#endregion

	#region Methods
	#region Encryption methods
	/**
	 * Encrypts a string using the pre-configured encryption salt
	 *
	 * @param string $text The string to encrypt
	 * @param Key|string $key  (optional) The key to use for the encryption. If no key is provided, framework's default configuration key is used instead.
	 *
	 * @return ?string The encrypted string or null on failure
	 */
	public static function encrypt(string $text, $key = null): ?string {
		if ($key == null) {
			$key = CMS::cfg()->env->encryptionSalt;
		}

		try {
			if (!($key instanceof Key)) {
				$key = self::convertKeyFromText($key);
			}

			return Crypto::encrypt($text, $key);
		}
		catch (\Exception $e) {
			CMS::app()->log($e);
			return null;
		}
	}

	/**
	 * Decrypts an encrypted string using the pre-configured encryption salt
	 *
	 * @param Key|string $key        (optional) The key to use for the encryption. If no key is provided, framework's default configuration key is used instead.
	 * @param string $cipherText The string to decrypt
	 *
	 * @return string The decrypted string
	 */
	public static function decrypt(string $cipherText, $key = null): ?string {
		if ($key == null) {
			$key = CMS::cfg()->env->encryptionSalt;
		}

		try {
			if (!($key instanceof Key)) {
				$key = self::convertKeyFromText($key);
			}

			return Crypto::decrypt($cipherText, $key);
		}
		catch (\Exception $e) {
			CMS::app()->log($e);
			return null;
		}
	}

	/**
	 * Tries to guess whether the provided value is encrypted or not.
	 *
	 * @param string $value
	 * @param int $type Encryption type. Valid values are Encrypt::* encryption constants
	 *
	 * @return bool
	 */
	public static function isEncrypted(string $value, int $type = Encrypt::MD5): bool {
		switch ($type) {
			case self::AES:
				return (ctype_print(Encrypt::decrypt($value)) === true);
			case self::MD5:
				return (strlen($value) == 32 && ctype_xdigit($value));
			case self::SHA256:
				return (strlen($value) == 64 && ctype_xdigit($value));
			case self::BCRYPT:
				return self::isHash(($value));
			case self::PlainText:
			default:
				return false;
		}
	}

	/**
	 * Generates a random encryption key and returns it as an ASCII-safe string, ready to be stored as text.
	 *
	 * @return string
	 * @throws EnvironmentIsBrokenException
	 */
	public static function generateKey(): string {
		$key = Key::createNewRandomKey();
		return $key->saveToAsciiSafeString();
	}

	/**
	 * Converts an encryption key passed as a string, back to its original form.
	 */
	public static function convertKeyFromText(string $key): ?Key {
		try {
			return Key::loadFromAsciiSafeString($key);
		}
		catch (\Exception $e) {
			CMS::app()->log($e);
			return null;
		}
	}
	#endregion

	#region Hashing methods
	/**
	 * Creates a hash of the given argument, using the Blowfish crypt algorithm.
	 *
	 * @param string $value The value to be hashed
	 *
	 * @return string|bool|null
	 */
	public static function hashPassword(string $value): string|bool|null {
		return password_hash($value, PASSWORD_BCRYPT);
	}

	/**
	 * Returns true if the given password matches the given hash.
	 *
	 * If password is already hashed, strings equality will be used as the comparison.
	 *
	 * @param string $password
	 * @param string $hash
	 *
	 * @return bool
	 */
	public static function verifyPassword(string $password, string $hash): bool {
		if (static::isHash($password))
			return $password == $hash;
		else
			return password_verify($password, $hash) || md5($password) == $hash; // try md5() for very old passwords
	}

	/**
	 * Returns true if the given hash is recognized by the framework and can be password-verified by the Encrypt::verifyPassword() method.
	 *
	 * @param string $value
	 *
	 * @return bool
	 */
	public static function isHash(string $value): bool {
		$arr = password_get_info($value);
		return $arr['algo'] === PASSWORD_BCRYPT || preg_match('/^[a-f0-9]{32}$/', $value);
	}
	#endregion

	#region Misc. methods
	/** Generates a random token, suitable for cryptographic operations */
	public static function generateToken(int $length = 32): string {
		$length = (int)$length;
		if ($length < 8) {
			$length = 8;
		}

		if (function_exists('random_bytes')) {
			return bin2hex(random_bytes($length));
		}
		elseif (function_exists('mcrypt_create_iv')) {
			return bin2hex(mcrypt_create_iv($length));
		}
		elseif (function_exists('openssl_random_pseudo_bytes')) {
			return bin2hex(openssl_random_pseudo_bytes($length));
		}
		else {
			// Fail-safe method, but not as secure as the above
			return hash('sha256', uniqid(php_uname(), true));
		}
	}

	/**
	 * Generates a random password and returns it as a string
	 *
	 * @param int $length       The generated password's desired length
	 * @param string $allowedChars The allowed characters to be used for the generated password
	 *
	 * @return string
	 */
	public static function generatePassword(int $length = 8, string $allowedChars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()_+-=/'): string {
		return substr(str_shuffle($allowedChars), 0, $length);
	}
	#endregion
	#endregion
}
