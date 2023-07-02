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

namespace aneya\Core\Environment;

use aneya\Core\CMS;
use aneya\Snippets\Snippet;

class ScheduledTask {
	public $id;
	public $tag;
	public $title;
	public $schedule;
	public $command;
	public $email;
	public $isEnabled;
	public $status;
	public $type;

	public function __construct ($schedule_id_or_tag = null) {
		if (!isset ($schedule_id_or_tag)) {
			$this->isEnabled = Scheduler::TASK_DISABLED;
			return;
		}

		if (is_numeric ($schedule_id_or_tag))
			$filter = 'schedule_id=:value';
		else
			$filter = 'tag=:value';

		$sql = "SELECT schedule_id, tag, internal_title, schedule, command, type, email, status, is_enabled FROM cms_scheduler WHERE $filter";
		$row = CMS::db()->fetch ($sql, array (':value' => $schedule_id_or_tag));

		if (!$row) {
			$this->isEnabled = Scheduler::TASK_DISABLED;
			return;
		}

		$this->id = $row['schedule_id'];
		$this->tag = $row['tag'];
		$this->title = $row['internal_title'];
		$this->schedule = $row['schedule'];
		$this->command = (strpos ($row['command'], "/") === 0) ? $row['command'] : CMS::appPath() . "/" . $row['command'];
		$this->email = $row['email'];
		$this->isEnabled = $row['is_enabled'];
		$this->status = $row['status'];
		$this->type = $row['type'];
	}

	public function execute () {
		CMS::logger()->info("Scheduled task '$this->title' [ID: $this->id] started.", 'backend');

		$sql = 'UPDATE cms_scheduler SET status=:status WHERE schedule_id=:schedule_id';
		CMS::db()->exec ($sql, array (':status' => Scheduler::TASK_IN_PROGRESS, ':schedule_id' => $this->id));

		$output = $error = "";
		$exit_code = 0;
		if ($this->type == Scheduler::TYPE_PHP_FILE) {
			ob_start();
			try {
				$validation = "";
				exec (escapeshellcmd ("php -l $this->command 2>&1"), $validation);
				$validation = implode ("\n", $validation);
				if (preg_match ("/error(s)? parsing/mi", $validation)) {
					$error = "Errors parsing $this->command";
					$exit_code = -1;
				}
				else {
					$dir = pathinfo ($this->command, PATHINFO_DIRNAME);
					chdir ($dir);
					include_once $this->command;
					$output = ob_get_contents ();
				}
			}
			catch (\Exception $e) {
				$error = ob_get_contents ();
				$exit_code = -1;
			}
			ob_end_clean();
		}
		elseif (function_exists ('exec')) {
			exec (escapeshellcmd ($this->command . " 2>&1"), $output, $exit_code);
			$output = implode ("\n", $output);
		}

		$sql = 'UPDATE cms_scheduler SET status=:status, last_executed=now(), last_output=:last_output, last_error=:last_error WHERE schedule_id=:schedule_id';
		CMS::db()->exec ($sql, array (
			':status'		=> Scheduler::TASK_RUNNABLE,
			':last_output'	=> $output,
			':last_error'	=> $error,
			':schedule_id'	=> $this->id
		));

		$emails = explode ("\n", $this->email);
		if (count ($emails) > 0) {
			$s = new Snippet ();
			$s->loadContentFromDb ('system-scheduled-tasks-email');
			$s->params->tag = $this->tag;
			$s->params->title = $this->title;
			$s->params->output = htmlspecialchars ($output);
			$s->params->error = htmlspecialchars ($error);
			$body = $s->compile ();

			foreach ($emails as $email) {
				if (strlen (trim ($email)) == 0) continue;

				Net::sendMail (CMS::app()->systemEmail, $email, "Scheduled task '$this->title' output", $body);
			}
		}

		CMS::logger()->info("Scheduled task '$this->title' [ID: $this->id] finished.", 'backend');
	}

	public function isRunnable () {
		if (!static::isScheduleValid ($this->schedule))
			return false;

		// Remove multiple spaces
		$schedule = preg_replace ('!\s+!', ' ', $this->schedule);

		// Split cron entry
		[$min, $hour, $day, $month, $dow] = explode (" ", $schedule);

		// Get current time
		[$n_min, $n_hour, $n_day, $n_month, $n_dow] = array (date ("i"), date ("G"), date ("j"), date ("n"), date("w"));

		if ($min == '*') $min = true;
		if ($hour == '*') $hour = true;
		if ($day == '*') $day = true;
		if ($month == '*') $month = true;
		if ($dow == '*') $dow = true;

		if ($min !== true) {
			$min = static::getExpressionPoints ($min);
			$min = in_array ($n_min, $min);
		}
		if ($hour !== true) {
			$hour = static::getExpressionPoints ($hour);
			$hour = in_array ($n_hour, $hour);
		}
		if ($day !== true) {
			$day = static::getExpressionPoints ($day);
			$day = in_array ($n_day, $day);
		}
		if ($month !== true) {
			$month = static::getExpressionPoints ($month);
			$month = in_array ($n_month, $month);
		}
		if ($dow !== true) {
			$dow = static::getExpressionPoints ($dow);
			$dow = in_array ($n_dow, $dow);
		}
		return ($min && $hour && $day && $month && $dow && ($this->isEnabled == Scheduler::TASK_ENABLED) && ($this->status == Scheduler::TASK_RUNNABLE));
	}

	public static function isScheduleValid ($schedule) {
		// Remove multiple spaces
		$schedule = preg_replace ('!\s+!', ' ', $schedule);

		// Split cron entry
		$array = explode (" ", $schedule);

		if (count ($array) != 5) return false;

		[$min, $hour, $day, $month, $dow] = $array;

		return static::isExpressionValid ($min, 0, 59)
		&& static::isExpressionValid ($hour, 0, 23)
		&& static::isExpressionValid ($day, 1, 31)
		&& static::isExpressionValid ($month, 1, 12)
		&& static::isExpressionValid ($dow, 1, 7);
	}

	protected static function getExpressionPoints ($expression) {
		$points = array ();

		$expression = explode (",", $expression);
		for ($i = 0; $i < count ($expression); $i++) {
			$expression[$i] = explode ("-", $expression[$i]);
			$from = $expression[$i][0];
			$to = isset ($expression[$i][1]) ? $expression[$i][1] : $expression[$i][0];
			for ($j = $from; $j <= $to; $j++) {
				$points[] = intval($j);
			}
		}

		return $points;
	}

	public static function isExpressionValid ($expression, $min, $max) {
		if ($expression == '*') return true;

		$expression = explode (",", $expression);
		for ($i = 0; $i < count ($expression); $i++) {
			if (is_numeric($expression[$i])) return true;

			$expression[$i] = explode ("-", $expression[$i]);
			if (count ($expression[$i]) != 2) return false;

			if (!is_numeric ($expression[$i][0]) || $expression[$i][0] < $min) return false;
			if (!is_numeric ($expression[$i][1]) || $expression[$i][1] > $max) return false;
			if ($expression[$i][0] >= $expression[$i][1]) return false;
		}

		return true;
	}

}
