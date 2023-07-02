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
use aneya\Core\CMS;

class Scheduler {
	const TASK_RUNNABLE = 0;
	const TASK_IN_PROGRESS = 1;
	const TASK_ENABLED = 1;
	const TASK_DISABLED = 0;

	const TYPE_COMMAND = 'C';
	const TYPE_PHP_FILE = 'P';

	protected static $tasks = array ();

	public static function reload () {
		$sql = "SELECT schedule_id, tag, internal_title, schedule, command, type, email, status, is_enabled FROM cms_scheduler";
		$tasks = CMS::db()->fetchAll ($sql);
		if (!$tasks) return;

		foreach ($tasks as $row) {
			$task = new ScheduledTask ();
			$task->id = $row['schedule_id'];
			$task->tag = $row['tag'];
			$task->title = $row['internal_title'];
			$task->schedule = $row['schedule'];
			$task->command = (strpos ($row['command'], "/") === 0) ? $row['command'] : CMS::appPath() . "/" . $row['command'];
			$task->email = $row['email'];
			$task->isEnabled = $row['is_enabled'];
			$task->status = $row['status'];
			$task->type = $row['type'];

			static::$tasks[] = $task;
		}
	}

	public static function run ($tag = null) {
		static::reload ();

		for ($i = 0; $i < count (static::$tasks); $i++) {
			$task = static::$tasks[$i];
			if (isset ($tag) && $task->tag != $tag) continue;

			if ($task->isRunnable () && function_exists ('exec')) {
				exec (CMS::appPath() . "/modules/core/jobs/exec.php $task->tag > /dev/null &");
			}
		}
	}

	/**
	 * Returns a ScheduledTask object given its tag
	 * @param string $tag
	 * @return ScheduledTask|bool
	 */
	public static function getTask ($tag) {
		if (count (static::$tasks) == 0)
			static::reload ();

		for ($i = 0; $i < count (static::$tasks); $i++)
			if (static::$tasks[$i]->tag == $tag)
				return static::$tasks[$i];

		return false;
	}

	public static function addTask ($tag, $title, $schedule, $command, $type = Scheduler::TYPE_PHP_FILE, $email = null, $status = Scheduler::TASK_ENABLED) {
		$db = CMS::db();

		$type = ($type == self::TYPE_COMMAND) ? self::TYPE_COMMAND : self::TYPE_PHP_FILE;
		$status = intval ($status);

		if (!self::isScheduleValid ($schedule))
			return false;

		$sql = 'INSERT INTO cms_scheduler (tag, internal_title, schedule, command, type, email, status, is_enabled)
				VALUES (:tag, :internal_title, :schedule, :command, :type, :email, :status, :is_enabled)';
		return $db->execute ($sql, array (
			':tag'				=> $tag,
			':internal_title'	=> $title,
			':schedule'			=> $schedule,
			':command'			=> $command,
			':type'				=> $type,
			':email'			=> $email,
			':status'			=> $status,
			':is_enabled'		=> 1
		));
	}

	public static function delTask ($tag) {
		$sql = 'DELETE FROM cms_scheduler WHERE tag=:tag';
		$ret = CMS::db()->exec ($sql, array (':tag' => $tag));

		return ($ret == 1);
	}

	public static function isScheduleValid ($schedule) {
		// Remove multiple spaces
		$schedule = preg_replace ('!\s+!', ' ', $schedule);

		// Split cron entry
		$array = explode (" ", $schedule);

		if (count ($array) != 5) return false;

		[$min, $hour, $day, $month, $dow] = $array;

		if (!ScheduledTask::isExpressionValid ($min, 0, 59) ||
			!ScheduledTask::isExpressionValid ($hour, 0, 23) ||
			!ScheduledTask::isExpressionValid ($day, 1, 31) ||
			!ScheduledTask::isExpressionValid ($month, 1, 12) ||
			!ScheduledTask::isExpressionValid ($dow, 1, 7)
		) return false;

		return true;
	}
}
