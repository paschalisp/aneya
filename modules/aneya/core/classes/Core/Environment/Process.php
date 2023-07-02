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

namespace aneya\Core\Environment;


use aneya\Core\EventArgs;
use aneya\Core\Hookable;
use aneya\Core\IHookable;

class Process implements IHookable {
	use Hookable;

	#region Constants
	/** Execute the command and wait (block) until it terminates */
	const ModeBlocking		= 'B';
	/** Execute the command and terminate it if object gets out of script's execution scope */
	const ModeNonBlocking	= 'N';
	/** Execute the command independently from script's execution scope */
	const ModeExecute		= 'X';

	/** Command not executed */
	const StatusNone		= 0;
	/** Command is running */
	const StatusStarted		= 1;
	/** Command terminated */
	const StatusTerminated	= 2;
	#endregion

	#region Event constants
	const EventOnStarted		= 'OnStarted';
	const EventOnInterval		= 'OnInterval';
	const EventOnTerminated		= 'OnTerminated';
	#endregion

	#region Properties
	/** @var string */
	protected $_command;

	/** @var string */
	protected $_mode;

	/** @var bool */
	protected $_stopOnExit;

	/** @var string */
	protected $_stdout;

	/** @var string */
	protected $_stderr;

	/** @var int */
	protected $_pid;

	/** @var array */
	protected $_pipes;

	/** @var resource */
	protected $_resource;

	/** @var int */
	protected $_status = self::StatusNone;

	/** @var float */
	protected $_timeStarted;

	/** @var float */
	protected $_timeTerminated;


	/** @var string Current working directory. Leave NULL to use the working dir of the current PHP process */
	public $cwd;
	/** @var array Environment variables. Leave NULL to use the same environment as the current PHP process */
	public $env;
	/** @var int Sleep interval (in milliseconds) */
	public $sleepInterval = 10;
	/** @var int Maximum time (in seconds) to allow the command till completes execution (Blocking mode only) */
	public $timeout = 0;
	#endregion

	#region Constructor
	/**
	 * Process constructor.
	 *
	 * @param string $commandLine	The command line to execute
	 * @param string $executionMode	The execution mode. Valid values are Process::Mode* set of constants
	 * @param bool $stopOnExit If true, when current Process instance is being destructed (due to garbage collection
	 *                         or the running script that created the Process object exits), then the process of the
	 *                         executed command will be killed (if still running).
	 */
	public function __construct(string $commandLine, string $executionMode = Process::ModeNonBlocking, bool $stopOnExit = false) {
		$this->_command = $commandLine;

		if (!in_array($executionMode, [self::ModeBlocking, self::ModeNonBlocking]))
			throw new \InvalidArgumentException();

		$this->_mode = $executionMode;
		$this->_stopOnExit = $stopOnExit;
	}

	public function __destruct() {
		if ($this->_stopOnExit)
			$this->stop();
	}
	#endregion

	#region Methods
	/**
	 * Returns the time duration of the command since it started its execution
	 * @return float
	 */
	public function duration() {
		if ($this->_status == self::StatusNone)
			return 0;

		if ($this->_status == self::StatusStarted)
			return microtime(true) - $this->_timeStarted;
		else
			return $this->_timeTerminated - $this->_timeStarted;
	}

	/**
	 * Returns command's error output (stderr)
	 * @return string
	 */
	public function error() {
		return $this->_stderr;
	}

	/**
	 * Returns command's exit code (if finished execution)
	 * @return int
	 */
	public function exitCode() {
		$status = $this->status();

		if ($status['running'] === true)
			return 0;

		$code = $status['exitcode'];
		if ($code == -1) {
			if ($status['signaled'] && $status['termsig'] > 0)
				$code = $status['termsig'] + 128;
		}

		return $code;
	}

	/**
	 * Flushes command's standard output and error buffers
	 * @return $this
	 */
	public function flush() {
		while ($r = fgets($this->_pipes[1], 1024))
			$this->_stdout .= $r;

		while ($r = fgets($this->_pipes[2], 1024))
			$this->_stderr .= $r;

		return $this;
	}

	/**
	 * Sends an input to the executing process
	 * @param string $input
	 * @return $this
	 */
	public function input($input) {
		fwrite($this->_pipes[0], $input);

		return $this;
	}

	/**
	 * Returns true if command is still running
	 * @return bool
	 */
	public function isRunning() {
		if (!$this->_status == self::StatusStarted)
			return false;

		$status = $this->status();

		return $status['running'] === true;
	}

	/**
	 * Returns command's output
	 * @return string
	 */
	public function output() {
		return $this->_stdout;
	}

	/**
	 * Return's executed command's Process ID
	 * @return int
	 */
	public function pid() {
		return $this->_pid;
	}

	/**
	 * Reads command's standard output and returns any newly written chunk of data
	 * @return string
	 */
	public function read() {
		$buffer = '';

		while ($r = fgets($this->_pipes[1], 1024)) {
			$buffer .= $r;
			$this->_stdout .= $r;
		}

		return $buffer;
	}

	/**
	 * Executes the command and waits till termination if execution mode is Blocking.
	 * @return $this
	 * @throws \RuntimeException
	 */
	public function run() {
		$this->start();

		if ($this->_mode == self::ModeBlocking)
			$this->wait();

		return $this;
	}

	/**
	 * Executes the command and returns process's PID (or false on error)
	 * @return $this
	 *
	 * @throws \RuntimeException
	 */
	public function start() {
		if ($this->isRunning())
			throw new \RuntimeException('Process is already running');

		// Spawn shell process
		$descriptor = [
			0 => ["pipe", "r"],  // stdin (read)
			1 => ["pipe", "w"],  // stdout (write)
			2 => ["pipe", "w"]   // stderr (write)
		];

		$this->_timeStarted = microtime(true);
		$this->_resource = proc_open($this->_command, $descriptor, $this->_pipes, $this->cwd, $this->env);
		if (!is_resource($this->_resource))
			throw new \RuntimeException('Failed to launch process for command: ' . $this->_command);

		$this->_status = self::StatusStarted;

		$status = $this->status();
		$this->_pid = $status['pid'];

		// Set output & error pipes to non-blocking
		stream_set_blocking($this->_pipes[1], 0);
		stream_set_blocking($this->_pipes[2], 0);

		$this->trigger(self::EventOnStarted, new EventArgs($this));

		// Update command's status as sometimes execution might already have been terminated
		$this->update();

		return $this;
	}

	/**
	 * @see proc_get_status()
	 * @return array|bool
	 */
	public function status() {
		return proc_get_status($this->_resource);
	}

	/**
	 * Stops the executed process (if still running)
	 * @return $this
	 */
	public function stop() {
		if (is_resource($this->_resource)) {
			$this->flush();

			proc_close($this->_resource);
			$this->_resource = null;
			$this->_timeTerminated = microtime(true);
		}

		$this->_status = self::StatusTerminated;
		$this->trigger(self::EventOnTerminated, new EventArgs($this));

		return $this;
	}

	/**
	 * Checks and updates command's execution status
	 * @return $this
	 */
	public function update() {
		$status = proc_get_status($this->_resource);

		if (!$status['running'] && $this->_status == self::StatusStarted)
			$this->stop();

		$this->flush();

		return $this;
	}

	/**
	 * Waits for the process to finish its execution
	 * @return $this
	 */
	public function wait() {
		$this->update();

		if ($this->_status != self::StatusStarted)
			return $this;

		do {
			$this->update();
			if ($this->_status == self::StatusTerminated)
				break;

			if ($this->timeout > 0) {
				if (($duration = $this->duration()) > $this->timeout)
					$this->stop();
			}

			usleep($this->sleepInterval * 1000);	// Sleep for the configured ms
			$this->trigger(self::EventOnInterval);
		}
		while ($this->isRunning());

		$this->update();

		return $this;
	}
	#endregion

	#region Static methods
	/**
	 * Executes a shell command in the background and returns its Process instance
	 *
	 * @param string  $command
	 *
	 * @return Process The generated Process instance after executing the command
	 */
	public static function cmd($command) {
		$p = new Process($command, self::ModeNonBlocking, false);
		$p->run();

		return $p;
	}

	/**
	 * Executes a shell command in the background and returns its PID
	 * @param string $command
	 * @return integer The command's PID
	 */
	public static function exec($command) {
		if (!function_exists ('exec'))
			return false;

		exec ("$command > /dev/null 2>&1 & echo $!", $op);

		return (int)$op[0];
	}
	#endregion
}
