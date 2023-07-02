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


class JavascriptUtils {
	#region Properties
	#endregion

	#region Constructor
	#endregion

	#region Methods
	#endregion

	#region Static methods
	/**
	 * Returns the position of the ending bracket inside $code, given the position of the opening bracket.
	 *
	 * @param string $code
	 * @param int    $pos     The opening bracket position
	 * @param string $quote   Quotes character to ignore string texts from
	 * @param string $bracket The "bracket" type to parse: {, [ or (
	 *
	 * @return int|null
	 */
	public static function closingBracketPos($code, $pos, $quote = '\'', $bracket = '{') {
		$code = substr($code, $pos);
		switch ($bracket) {
			case '(': $closingBracket = ')'; break;
			case '[': $closingBracket = ']'; break;
			default:  $closingBracket = '}';
		}

		$len = strlen($code);
		$balance = $code[$pos] == $bracket ? 0 : 1;
		$insideQuotes = false;
		for ($num = $pos; $num < $len; $num++) {
			if ($code[$num] == $quote)
				$insideQuotes = !$insideQuotes;

			if ($code[$num] == $bracket && !$insideQuotes)
				$balance++;
			elseif ($code[$num] == $closingBracket && !$insideQuotes)
				$balance--;

			if ($balance == 0)
				break;
		}

		if ($balance == 0)
			return $num;
		else
			return null;
	}

	/**
	 * Parses a string and escapes quotes so that it can be used in Javascript.
	 *
	 * @param string $str
	 * @param string $quotes (default value is double quotes ["])
	 *
	 * @return string
	 */
	public static function escape($str, $quotes = '"') {
		switch ($quotes) {
			case '"':
				$str = str_replace('"', '\\"', $str);
				return str_replace(["\r", "\n"], "\" +\n+\t\"", $str);
			case "'":
				$str = str_replace("'", "\\'", $str);
				return str_replace(["\r", "\n"], "' +\n\t'", $str);
		}

		return str_replace(["\r", "\n"], '', $str);
	}

	/**
	 * Returns true if given caret position in $code is inside a quoted string.
	 *
	 * @param int    $pos
	 * @param string $code
	 * @param string $quote The character that is used to quote strings (single quotes by default)
	 *
	 * @return bool
	 */
	public static function isPosInsideQuotes($pos, $code, $quote = '\'') {
		$code = substr($code, 0, $pos);
		$numQuotes = strlen($code) - strlen(str_replace($quote, '', $code));

		return $numQuotes % 2;
	}
	#endregion
}
