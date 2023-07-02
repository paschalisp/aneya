<?php
/*
 * aneya CMS & Framework
 * Copyright (c) 2011-2022 Paschalis Pagonidis <p.pagonides@gmail.com>
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
 * Portions created by Paschalis Ch. Pagonidis are Copyright (C) 2011-2022
 * Paschalis Ch. Pagonidis. All Rights Reserved.
 */

namespace aneya\Core;

use aneya\Core\Environment\Net;
use aneya\Messaging\Recipients;
use aneya\Security\User;
use Monolog\Logger;

class Application implements IHookable {
	use Hookable;

	#region Constants
	#region Event constants
	/** Triggered when a new Application has been instantiated. Passes an ErrorEventArgs argument to listeners. */
	const EventOnInstantiate = 'OnInstantiate';
	/** Triggered when Application::terminate() is called. Passes an ErrorEventArgs argument to listeners. Both Static and non-static. */
	const EventOnTerminate = 'OnTerminate';
	/** Triggered when the running script raises an error or warning. Passes an ErrorEventArgs argument to listeners. */
	const EventOnError = 'OnError';
	/** Triggered when an unhandled exception is raised. Passes an ErrorEventArgs argument to listeners. */
	const EventOnException = 'OnException';
	/** Triggered when the running script finishes execution. */
	const EventOnShutdown = 'OnShutdown';
	#endregion

	#region Application termination codes
	const ErrorPageNotFound          = 404;
	const ErrorAccessDenied          = 403;
	const ErrorInternal              = 500;
	const ErrorPageUnderConstruction = 2001;
	const ErrorUnderMaintenance      = 2002;
	const ErrorServiceUnavailable    = 503;
	const ErrorCustom                = 999;
	#endregion
	#endregion

	#region Properties
	#region Base properties
	public $name;
	public $owner;
	public $author;
	/** @var string E-mail address to use as the sender for alert & bugs notifications. */
	public $systemEmail;
	/** @var string|string[] E-mail address(es) to send e-mail alert notifications. */
	public $alertsTo;
	/** @var string|string[] E-mail address(es) to send critical e-mail notifications for bugs and issues. */
	public $bugsTo;
	public bool $debugMode = false;

	public ErrorCollection $errors;
	#endregion

	#region Static properties
	public static Application $current;
	#endregion
	#endregion

	#region Construction & initialization
	public function __construct() {
		$this->hooks()->register([self::EventOnInstantiate, self::EventOnError, self::EventOnException, self::EventOnTerminate, self::EventOnShutdown]);

		$this->errors = new ErrorCollection();

		static::$current = $this;

		if (!isset($this->name) || strlen($this->name) == 0)
			$this->name = CMS::cfg()->app->title;

		if (!isset($this->author) || strlen($this->author) == 0)
			$this->author = CMS::cfg()->app->author;

		if (!isset($this->owner) || strlen($this->owner) == 0)
			$this->owner = CMS::cfg()->app->owner;

		if (!isset($this->systemEmail) || strlen($this->systemEmail) == 0)
			$this->systemEmail = CMS::cfg()->app->systemEmail;

		if (!isset($this->alertsTo) || (is_string($this->alertsTo) && strlen($this->alertsTo) == 0) || (is_array($this->alertsTo) && count($this->alertsTo) == 0))
			$this->alertsTo = CMS::cfg()->app->alertsTo;

		if (!isset($this->bugsTo) || (is_string($this->bugsTo) && strlen($this->bugsTo) == 0) || (is_array($this->bugsTo) && count($this->bugsTo) == 0))
			$this->bugsTo = CMS::cfg()->app->bugsTo;

		$this->debugMode = (bool)CMS::cfg()->env->debugging;
	}
	#endregion

	#region Methods
	/** Sends an alert report to the e-mail(s) that have been configured to receive system alert notifications. */
	public final function sendAlert(string $subject, string $message): Status {
		$recipients = new Recipients($this->alertsTo);
		return Net::sendMail($this->systemEmail, $recipients, $subject, $message);
	}

	/**
	 * Sends a bug/issue report to the e-mail(s) that have been configured to receive bug notifications.
	 *
	 * @param string (optional) $subject (Pass null to leave the default value)
	 * @param string $message
	 * @param ?\Throwable $e
	 *
	 * @return Status
	 */
	public final function sendBugReport($subject = 'Bug Report for project {name}', string $message = '', \Throwable $e = null): Status {
		if (strlen($subject) === 0)
			$subject = 'Bug Report for project {name}';

		$subject = str_replace(['{name}'], [$this->name], $subject);
		$recipients = new Recipients($this->bugsTo);

		if ($e === null)
			$e = new \Exception();

		$trace = str_replace(CMS::filesystem()->normalize('/'), '/', $e->getTraceAsString());
		$trace = str_replace("\n", '<br />', $trace);

		$user = CMS::env()->isCLI() ? null : User::get();
		$body = sprintf('<h3>Bug Report for Project <em>%s</em></h3><h4>Message</h4><p>%s</p><p>%s</p><p><strong>Namespace:</strong> <em>%s</em></p><p><strong>User:</strong> <em>%s</em></p><p><strong>Error Code:</strong> <em>%s</em><p><strong>Trace:</strong> <em>%s</em><p><strong>URI:</strong> <em>%s</em><p>Env. <strong>GET</strong>: <em>%s</em><p>Env. <strong>POST</strong>: <em>%s</em></p><p>Env. <strong>FILES</strong>: <em>%s</em></p><p>Env. <strong>SESSION</strong>: <em>%s</em></p><p>Env. <strong>SERVER</strong>: <em>%s</em></p><p>Env. <strong>ENV</strong>: <em>%s</em></p>',
			$this->name,
			$message,
			$e->getMessage(),
			CMS::ns()->tag,
			($user instanceof User ? (string)$user->id : '-'),
			(string)$e->getCode(),
			$trace,
			CMS::env()->uri(),
			json_encode($_GET),
			json_encode($_POST),
			json_encode($_FILES),
			json_encode($_SESSION),
			json_encode($_SERVER),
			json_encode($_ENV)
		);

		return Net::sendMail($this->systemEmail, $recipients, $subject, $body);
	}

	/** Prepares the framework for graceful shutdown */
	public final function shutdown(): Application {
		$this->trigger(self::EventOnShutdown);

		if ($this->errors->count() > 0) {
			// Good place to add a breakpoint and hold the execution whenever errors or exceptions occurred during the program's execution
			$empty = null;
		}

		return $this;
	}

	/**
	 * Outputs an error page back to the browser and exits
	 *
	 * @param ErrorEventArgs|int $terminationCode If integer is provided, valid values are Application::Error* constants
	 */
	public function terminate(ErrorEventArgs|int $terminationCode): never {
		if (!($terminationCode instanceof ErrorEventArgs)) {
			$error = new ApplicationError(null, $terminationCode);
			$args = new ErrorEventArgs (static::$current, $error);
		}
		else {
			$args = $terminationCode;
		}
		$this->trigger(self::EventOnTerminate, $args);

		exit;
	}

	/**
	 * Adds an entry to framework's default logger
	 *
	 * @param \Throwable $e
	 * @param int|null $level Monolog\Logger error level constants
	 *
	 * @return Application
	 */
	public function log(\Throwable $e, int $level = null): Application {
		if ($level === null)
			$level = ($e instanceof ApplicationError) ? $e->severity : ApplicationError::errorCodeToSeverity($e->getCode());

		$type = match ($level) {
			Logger::DEBUG => 'DEBUG',
			Logger::INFO => 'INFO',
			Logger::NOTICE => 'NOTICE',
			Logger::WARNING => 'WARNING',
			Logger::ERROR => 'ERROR',
			Logger::CRITICAL => 'CRITICAL',
			Logger::ALERT => 'ALERT',
			Logger::EMERGENCY => 'EMERGENCY',
			default => 'ERROR',
		};
		$msg = sprintf("[%s] File: %s, Line: %s.\nMessage: %s.\n\nTrace:\n%s", $type, $e->getFile(), $e->getLine(), $e->getMessage(), $e->getTraceAsString());

		CMS::logger()->log($level, $msg);

		if (!$this->errors->contains($e))
			$this->errors->add($e);

		return $this;
	}
	#endregion

	#region Static Methods
	public static final function errorSt(int $code, string $message, string $file = null, int $line = null, array $context = null) {
		if (isset(static::$current)) {
			static::$current->OnError($code, $message, $file, $line);
		}
	}

	public static final function exceptionSt(\Throwable $ex) {
		if (isset(static::$current)) {
			static::$current->OnException($ex);
		}
	}

	public static final function shutdownSt() {
		if (isset(static::$current)) {
			static::$current->shutdown();
		}

		CMS::session()->commit();
	}
	#endregion

	#region Events
	public function OnError($code, $message, $file, $line): ?ApplicationError {
		// Avoid warnings handling when running in production mode to gain performance
		if (!$this->debugMode && in_array($code, [E_WARNING, E_USER_WARNING, E_COMPILE_WARNING, E_CORE_WARNING, E_NOTICE, E_USER_NOTICE, E_DEPRECATED, E_STRICT]))
			return null;

		$error = new ApplicationError($message, $code);
		$error->setFile($file);
		$error->setLine($line);
		$this->errors->add($error);

		$statuses = $this->trigger(self::EventOnError, new ErrorEventArgs ($this, $error));
		if (count($statuses) > 0) {
			foreach ($statuses as $status)
				if ($status->isHandled)
					return $error;
		}

		// De-escalate all source code warnings & notices and process them as debugging
		if (in_array($code, [E_ERROR, E_USER_ERROR, E_COMPILE_ERROR, E_PARSE, E_CORE_ERROR, E_RECOVERABLE_ERROR]))
			$error->severity = Logger::ERROR;
		elseif (in_array($code, [E_WARNING, E_USER_WARNING, E_COMPILE_WARNING, E_CORE_WARNING]))
			$error->severity = Logger::DEBUG;
		elseif (in_array($code, [E_NOTICE, E_USER_NOTICE, E_DEPRECATED, E_STRICT]))
			$error->severity = Logger::DEBUG;
		else
			$error->severity = Logger::DEBUG;

		// Output to PHP error log file
		$this->log($error);

		return $error;
	}

	public function OnException(\Throwable $e) {
		$triggers = self::triggerSt(self::EventOnException, new ErrorEventArgs($this, $e));
		foreach ($triggers as $status) {
			if ($status->isHandled)
				break;
		}

		if (!isset($status) || !$status->isHandled)
			// Output to PHP error log file
			$this->log($e, Logger::ERROR);

		$this->errors->add($e);
	}

	public function OnShutdown() {}
	#endregion
}
