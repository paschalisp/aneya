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
 * Portions created by Paschalis Ch. Pagonidis are Copyright (C) 2007-2022
 * Paschalis Ch. Pagonidis. All Rights Reserved.
 */

namespace aneya\Core\Utils\Diff;

class Diff {
	#region Constants
	const UNCHANGED = 0;
	const MODIFIED = 1;
	const ADDED = 2;
	const DELETED = 3;

	const OUTPUT_TEXT = 'text';
	const OUTPUT_HTML = 'html';
	#endregion

	#region Static methods
	/**
	 * Compares two variables and returns a corresponding DiffResult object
	 * @param mixed $a
	 * @param mixed $b
	 * @return DiffResult
	 */
	public static function compare ($a, $b) {
		$dr = new DiffResult ($a, $b);
		if (is_null ($a) && is_null ($b)) {
			$dr->result = self::UNCHANGED;
		}
		elseif (is_null ($a)) {
			$dr->result = self::ADDED;
		}
		elseif (is_null ($b)) {
			$dr->result = self::DELETED;
		}
		elseif (is_scalar ($a)) {
			if (is_scalar ($b)) {
				$dr = static::compareScalar ($a, $b);
			}
			else {
				$dr->result = self::MODIFIED;
			}
		}
		elseif (is_object ($a)) {
			if (is_object ($b)) {
				if (get_class ($a) == get_class ($b)) {
					$dr = static::compareObjects ($a, $b);
				}
				else {
					$dr->result = self::MODIFIED;
				}
			} else {
				$dr->result = self::MODIFIED;
			}
		}
		elseif (is_array ($a)) {
			if (is_array ($b)) {
				$dr = static::compareArray ($a, $b);
			}
			else {
				$dr->result = self::MODIFIED;
			}
		}

		return $dr;
	}

	/**
	 * Outputs a diff result in text or HTML mode
	 * @param DiffResult $df The diff result to output
	 * @param string $mode Valid values are Diff::OUTPUT_*
	 * @return string
	 */
	public static function output (DiffResult $df, $mode = Diff::OUTPUT_TEXT) {
		$ret = '';
		if (is_string ($df->result)) {
			$ret .= "\r$df->source\t\t\t\t$df->target\t\t\t\t" . $df->resultToString() . "\n";
		}

		elseif (is_array ($df->result)) {
			foreach ($df->result as $df2) {
				$ret .= static::output ($df2, $mode);
			}
		}

		return $ret;
	}

	public static function compareCallback ($a, $b) {
		$dr = static::compare ($a, $b);

		return ($dr->isUnchangedOrModified ()) ? 0 : -1;
	}

	#region Protected methods
	/**
	 * @param $a
	 * @param $b
	 * @return DiffResult
	 */
	protected static function compareScalar ($a, $b) {
		$dr = new DiffResult ($a, $b);
		if ($a == $b) {
			$dr->result = self::UNCHANGED;
		}

		else {
			$dr->result = self::MODIFIED;
		}

		return $dr;
	}

	/**
	 * @param array $a
	 * @param array $b
	 * @return DiffResult
	 */
	protected static function compareArray ($a, $b) {
		$dr = new DiffResult ($a, $b);
		$ret = array ();

		$aa = $a;
		$bb = $b;

		$maxA = count ($a);
		$maxB = count ($b);

		for ($i = 0; $i < $maxA; $i++) {
			$dr = new DiffResult ($aa[$i]);
			if (!isset ($bb[$i])) {
				$dr->result = self::DELETED;
			}
			else {
				$dr = static::compare ($aa[$i], $bb[$i]);
			}

			$ret[] = $dr;
		}

		for ($i = $maxA; $i < $maxB; $i++) {
			$dr = new DiffResult (null, $bb[$i]);
			$dr->result = self::ADDED;

			$ret[] = $dr;
		}

		$dr->result = $ret;

		return $dr;
	}

	/**
	 * @param object $a
	 * @param object $b
	 * @return DiffResult
	 */
	protected static function compareObjects ($a, $b) {
		$dr = new DiffResult ($a, $b);

		$ret = array ();

		// Check source properties
		foreach ($a as $prop => $value) {
			$drp = new DiffResult ($a->$prop);

			if (!isset ($b->$prop)) {
				$drp->target = null;
				$drp->result = self::DELETED;
			}
			else {
				$drp = static::compare ($a->$prop, $b->$prop);
			}

			$ret[] = $drp;
		}

		// Check if target has properties not found in source
		foreach ($b as $prop => $value) {
			if (!isset ($a->$prop)) {
				$drp = new DiffResult (null, $b->$prop);
				$drp->result = self::ADDED;
				$ret[] = $drp;
			}
		}

		$dr->result = $ret;

		return $dr;
	}
	#endregion
	#endregion
}
