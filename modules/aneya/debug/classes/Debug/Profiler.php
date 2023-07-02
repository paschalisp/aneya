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

namespace aneya\Debug;

use aneya\Core\CMS;
use aneya\Core\Utils\Timer;

final class Profiler {
	#region Properties
	protected ?Timer $_timer;
	protected $_slots;
	protected $_savePoints;
	#endregion

	#region Constructor
	#endregion

	#region Methods
	public function start() {
		$this->_slots = [];
		$this->_savePoints = [];

		$this->_timer = new Timer(true);
	}

	public function push($comment = null) {

	}

	public function stop() {
		return $this->_timer->stop();
	}

	public function savePoint($tag) {

	}
	#endregion

	#region Static methods
	/**
	 * Returns the number of files opened by the script so far.
	 * @return int
	 */
	public static function getFilesUsage (): int {
		$files = get_included_files();

		return count ($files);
	}

	/**
	 * Returns the maximum memory amount allocated to the script so far, in megabytes.
	 */
	public static function getMemoryUsage(): float {
		return (memory_get_peak_usage(false)/1024/1024);
	}

	/**
	 * Returns the current memory allocated to the script, in megabytes.
	 */
	public static function getCurrentMemoryUsage(): float {
		return memory_get_usage()/1024/1024;
	}

	/**
	 * Returns the number of database queries executed by the script so far, either for a specific connection or globally if no schema tag is given
	 * @param int|string $schemaTag (optional)
	 * @return int
	 */
	public static function getDatabaseUsage ($schemaTag = null): int {
		if (strlen($schemaTag) > 0) {
			try {
				$queries = CMS::db($schemaTag)->getExecutedQueries();
				$cnt = count($queries);
			}
			catch (\Exception $e) {
				$cnt = 0;
			}
		}
		else {
			$cnt = 0;

			$schemas = CMS::schemas();
			foreach ($schemas as $schema) {
				try {
					$queries = CMS::db($schema->tag)->getExecutedQueries();
					$cnt += count($queries);
				}
				catch (\Exception $e) { }
			}
		}

		return $cnt;
	}

	/**
	 * Returns the system time (in seconds) used to execute the script so far.
	 */
	public static function getSystemTimeUsage(): float {
		$r = getrusage();
		return ((float)$r['ru_utime.tv_usec']) / 1000000;
	}

	#endregion
}
