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

class UnitUtils {
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
	 * Converts a number (representing a size in bytes) into human readable string.
	 *
	 * @param int|float $size
	 *
	 * @return string
	 */
	public static function humanSizeBytes($size) {
		$unit = 'bytes';
		if ($size > 1024) {
			$unit = 'KB';
			$size /= 1024;
		}
		if ($size > 1024) {
			$unit = 'MB';
			$size /= 1024;
		}
		if ($size > 1024) {
			$unit = 'GB';
			$size /= 1024;
		}

		return CMS::locale()->toNumber($size) . ' ' . $unit;
	}

	public static function num2alpha($n) {
		for ($r = ""; $n >= 0; $n = intval($n / 26) - 1)
			$r = chr($n % 26 + 0x41) . $r;
		return $r;
	}
	#endregion
}
