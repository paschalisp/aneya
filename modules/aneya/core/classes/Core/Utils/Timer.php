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

namespace aneya\Core\Utils;


class Timer {
	#region Properties
	protected $_timeStarted;
	protected $_timeStopped;
	protected $_isRunning = false;

	protected $_intervals = array();
	protected $_comments  = array();
	#endregion

	#region Constructor
	public function __construct($start = false) {
		if ($start == true)
			$this->start();
	}
	#endregion

	#region Methods
	/**
	 * Starts the timer. If timer is already running, it resets the timer information and intervals.
	 */
	public function start() {
		$this->_isRunning = true;
		$this->reset();
	}

	/**
	 * Resets the timer, just if it was just started.
	 */
	public function reset() {
		if (!$this->_isRunning)
			return;

		$this->_timeStarted = (float)microtime(true);
		$this->_timeStopped = 0;
		$this->_intervals = array($this->_timeStarted);
		$this->_comments = array('Timer started');
	}

	/**
	 * Stops the timer and returns the time elapsed since the last interval or since the start, if no any interval was pushed in the meantime.
	 * @return float
	 */
	public function stop() {
		if (!$this->_isRunning)
			return 0.0;

		$this->_intervals[] = $this->_timeStopped = (float)microtime(true);
		$this->_comments[] = 'Timer stopped';

		$this->_isRunning = false;

		return $this->_timeStopped;
	}

	/** Pushes the time elapsed since the previous interval or since the start (if no other interval was pushed in the meantime) in timer's array of intervals. */
	public function push(string $comment = ''): float {
		if (!$this->_isRunning)
			return 0.0;

		$this->_intervals[] = $elapsed = (float)microtime(true);
		$this->_comments[] = $comment;

		return $elapsed - $this->_intervals[count($this->_intervals) - 1];
	}

	/** Returns the timer's array of pushed intervals. */
	public function intervals(): array {
		$ret = [];
		$max = count ($this->_intervals);
		$previousTime = $this->_timeStarted;
		for ($num = 0; $num < $max; $num++) {
			$ret[(string)($this->_intervals[$num] - $previousTime)] = $this->_comments[$num];
			$previousTime = $this->_intervals[$num];
		}

		return $ret;
	}

	/** Returns the time elapsed since the last interval or since the start, if no any interval was pushed in the meantime. */
	public function elapsed(): float {
		if (!$this->_isRunning)
			return 0.0;

		return (float)microtime(true) - $this->_intervals[count($this->_intervals) - 1];
	}
	#endregion
}
