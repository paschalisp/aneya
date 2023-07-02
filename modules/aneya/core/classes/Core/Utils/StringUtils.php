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


namespace aneya\Core\Utils;


use aneya\Core\CMS;
use Defuse\Crypto\Encoding;
use Ramsey\Uuid\Uuid;

class StringUtils {
	#region Constants
	#endregion

	#region Properties
	#endregion

	#region Constructor
	#endregion

	#region Methods
	#endregion

	#region Static methods
	/**
	 * Get part of an HTML string
	 *
	 * @param string $str     The HTML string to extract the substring from
	 * @param int $length  Maximum chars to use from $str
	 * @param string $postfix A string to append to the resulting substring
	 *
	 * @return string Returns the portion of the stripped HTML string specified by the $length and $postfix parameters
	 */
	public static function htmlSubStr(string $str, int $length, string $postfix = ''): string {
		$str = mb_substr(htmlspecialchars_decode(strip_tags($str)), 0, $length);
		$pos = mb_strrpos($str, ' ');
		if (mb_strlen($str) == $length && ($pos === false || $pos < $length)) {
			$str = mb_substr($str, 0, $pos) . $postfix;
		}

		return $str;
	}

	/** Converts a string into camelCase */
	public static function toCamelCase(string $str): string {
		$str = preg_replace('/[^\da-z]/i', ' ', $str);

		// If all letters are uppercase (and not mixed with lowercase letters), then lowercase the whole string
		if (mb_strtoupper($str, 'utf-8') == $str)
			$str = strtolower($str);

		$parts = explode(' ', $str);
		$parts = array_map('ucfirst', $parts);

		return lcfirst(implode('', $parts));
	}

	/** Converts a string into PascalCase */
	public static function toPascalCase(string $str): string {
		$str = preg_replace('/[^\da-z]/i', ' ', $str);

		// If all letters are uppercase (and not mixed with lowercase letters), then lowercase the whole string
		if (mb_strtoupper($str, 'utf-8') == $str)
			$str = strtolower($str);

		$parts = explode(' ', $str);
		$parts = array_map('ucfirst', $parts);

		return implode('', $parts);
	}

	/** Capitalizes all words in a string and returns the result. */
	public static function capitalize(string $str): string {
		$str = preg_replace('/[^\da-z]/i', ' ', $str);

		// If all letters are uppercase (and not mixed with lowercase letters), then lowercase the whole string
		if (mb_strtoupper($str, 'utf-8') == $str)
			$str = strtolower($str);

		$parts = explode(' ', $str);
		$parts = array_map('ucfirst', $parts);

		return implode(' ', $parts);
	}

	/**
	 * Converts a string to its hexadecimal representation.
	 *
	 * @param string $str
	 *
	 * @return string
	 * @see \Defuse\Crypto\Encoding::binToHex()
	 *
	 */
	public static function toHex(string $str): string {
		try {
			return Encoding::binToHex($str);
		}
		catch (\Exception $e) {
			return '';
		}
	}

	/**
	 * Converts a hexadecimal back to its plain text representation.
	 *
	 * @param string $hex
	 *
	 * @return string
	 * @see \Defuse\Crypto\Encoding::hexToBin()
	 */
	public static function fromHex(string $hex): string {
		try {
			return Encoding::hexToBin($hex);
		}
		catch (\Exception $e) {
			return '';
		}
	}

	/**
	 * Parses a string and splits it into keywords
	 *
	 * @param string $str          The string to split
	 * @param int $minimumChars Each keyword should have at least $minimumChars in order to be added to the resulting array
	 *
	 * @return string[]
	 */
	public static function toKeywords(string $str, int $minimumChars = 3): array {
		$str = preg_replace('/\s\s+/', ' ', strip_tags(trim($str)));
		$str = array_unique(explode(" ", $str));
		$ret = array ();
		if (count($str) > 0)
			foreach ($str as $s) {
				if (mb_strlen($s) < $minimumChars) continue;
				$ret[] = CMS::db()->escape($s);
			}

		return $ret;
	}

	/**
	 * Returns a URL-compatible version of a given string.
	 * Specifically, the function:
	 *        - Decodes URL characters (e.g. %20 to ' ')
	 *        - Translates diacritics to their ASCII equivalent
	 *        - Converts string to lower case
	 *        - Removes unknown characters (keeps A-Z, a-z, 0-9 ,._-|+ and <space>)
	 *        - Limits the length of the resulting string
	 *
	 * @param string $str
	 * @param string $delimiter
	 * @param int $length
	 *
	 * @return mixed|string
	 *
	 * @credits Tralsiterator usage as suggested by: http://stackoverflow.com/a/16022459
	 */
	public static function toUri(string $str, string $delimiter = '-', int $length = 250) {
		// Convert diacritics to plain latin characters
		$clean = transliterator_transliterate("Any-Latin; Latin-ASCII; [\u0080-\u7fff] remove", $str);

		// Decode URL-encoded strings, like %20 to <space> etc...
		$clean = urldecode($clean);

		// Remove unacceptable characters
		$clean = preg_replace("%[^a-zA-Z0-9/_|+,. -]%", '', $clean);

		// Trim, limit and lower-case result
		$clean = strtolower(substr(trim($clean, '-'), 0, $length));

		// Replace any resulting spaces to the given delimiter
		return preg_replace("%[/_|+ -]+%", $delimiter, $clean);
	}

	/** Removes any starting or trailing quotes from the string. */
	public static function trimQuotes(string $str): string {
		if (strpos($str, "'") === 0 && substr($str, -1) === "'")
			return substr($str, 1, -1);

		if (strpos($str, '"') === 0 && substr($str, -1) === '"')
			return substr($str, 1, -1);

		return $str;
	}

	/**
	 * Generates and returns a 32-char hex UUIDs v4 (xxxxxxxx-xxxx-xxxx-xxxxxxxxxxxx)
	 *
	 * @see https://github.com/ramsey/uuid
	 *
	 * @return string
	 */
	public static function uuid(): string {
		return Uuid::uuid4()->toString();
	}

	/**
	 * Returns true if the given UUID is valid.
	 *
	 * @see http://php.net/manual/en/function.uniqid.php#94959
	 *
	 * @param string $uuid
	 *
	 * @return bool
	 */
	public static function isUuid(string $uuid): bool {
		return preg_match('/^\{?[0-9a-f]{8}\-?[0-9a-f]{4}\-?[0-9a-f]{4}\-?[0-9a-f]{4}\-?[0-9a-f]{12}\}?$/i', $uuid) === 1;
	}

	/** Returns a random string of maximum 32-characters length. */
	public static function random(int $length = 32): string {
		return substr(md5(static::uuid()), 0, $length);
	}
	#endregion
}
