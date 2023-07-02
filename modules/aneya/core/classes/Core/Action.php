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

namespace aneya\Core;

use aneya\Security\User;

class Action implements IHookable {
	use Hookable;

	#region Constants
	const Allow = true;
	const Deny  = false;

	const NotExecuted = 0;
	const Executed    = 1;
	/** Execution of the action was denied because of insufficient permissions */
	const Denied = -1;
	#endregion

	#region Events
	/** Triggered when the Action is being executed. Passes a ActionEventArgs argument on listeners */
	const EventOnExecuting = 'OnExecuting';
	/** Triggered when the Action is being executed allowing listeners to override the default Action's behaviour. Passes a ActionEventArgs argument on listeners */
	const EventOnExecute = 'OnExecute';
	/** Triggered when the Action has completed its execution. Passes a ActionEventArgs argument on listeners */
	const EventOnExecuted = 'OnExecuted';
	/** Triggered when the framework checks whether the current user is allowed to execute this Action. Passes a ActionEventArgs argument on listeners */
	const EventOnCheckAllowed = 'OnCheckAllowed';
	#endregion

	#region Properties
	/** @var string The command that defines the action */
	public $command;

	/** @var bool Set the default security policy if there's no specific security policy defined when checking a User towards this action. Deny by default. */
	public bool $defaultPolicy = self::Deny;

	/** @var string[] The commands that are allowed to be executed after this action */
	public array $precedes = array ();

	/** @var string[] The commands that this action is allowed to be executed after */
	public array $follows = array ();

	/** @var string[] The user roles that are allowed to perform this action */
	public array $allowedRoles = array ();

	/** @var string[] The user permissions that are allowed to perform this action */
	public $allowedPermissions = array ();

	/** @var bool Indicates if the action will be recorded automatically in the audit logs when it gets processed */
	public bool $isAuditable = false;

	/** @var bool Indicates if the action is the default to execute if no action or command information is set in the environment */
	public bool $isDefault = false;

	/** @var EventStatus Stores the last EventStatus produced by the last call to the "isAllowed()" method */
	public EventStatus $lastStatus;

	/** @var int Indicates the execution status of the action. Valid values are Action::[NotExecuted|Executed|Denied] */
	public int $executionStatus = Action::Denied;
	#endregion

	#region Construction
	/**
	 * @param string $command
	 * @param array  $allowedRoles
	 * @param array  $allowedPermissions
	 * @param bool   $defaultPolicy
	 * @param bool   $isDefault
	 */
	public function __construct() {
		$command = null;
		$allowedRoles = [];
		$allowedPermissions = [];
		$defaultPolicy = self::Deny;
		$isDefault = false;

		if (func_num_args() > 0)
			$command = func_get_arg(0);
		if (func_num_args() > 1 && !empty(func_get_arg(1)))
			$allowedRoles = func_get_arg(1);
		if (func_num_args() > 2 && !empty(func_get_arg(2)))
			$allowedPermissions = func_get_arg(2);
		if (func_num_args() > 3)
			$defaultPolicy = (bool)func_get_arg(3);
		if (func_num_args() > 4)
			$isDefault = (bool)func_get_arg(4);

		$this->command = $command;
		$this->allowedRoles = is_array($allowedRoles) ? $allowedRoles : [$allowedRoles];
		$this->allowedPermissions = is_array($allowedPermissions) ? $allowedPermissions : [$allowedPermissions];
		$this->defaultPolicy = ($defaultPolicy == self::Allow) ? self::Allow : self::Deny;
		$this->isDefault = $isDefault;
	}
	#endregion

	#region Methods
	/**
	 * Executes the Action
	 *
	 * @return EventStatus|null
	 */
	public final function execute(): ?EventStatus {
		$args = new ActionEventArgs ($this, $this);

		$this->trigger(self::EventOnExecuting, $args);

		$status = null;
		$customProcessed = false;
		$triggers = $this->trigger(self::EventOnExecute, $args);
		foreach ($triggers as $t) {
			if ($t->isHandled) {
				$customProcessed = true;
				$status = $t;
				break;
			}
		}
		if (!$customProcessed) {
			$status = $this->OnExecute($args);
		}

		$this->executionStatus = self::Executed;
		$args->status = $status;

		// Call user-customized setup
		$this->trigger(self::EventOnExecuted, $args);

		return $status;
	}

	/**
	 * @triggers Action::EventOnCheckAllowed
	 */
	public final function isAllowed(User $user, string $previousCmd = ''): bool {
		$args = new ActionEventArgs ($this, $this, $user, $previousCmd);
		$statuses = $this->trigger(self::EventOnCheckAllowed, $args);
		if ($statuses) {
			foreach ($statuses as $status) {
				if ($status->isHandled) {
					$this->lastStatus = $status;
					return $status->isOK();
				}
			}

			// If there's no specific policy for this user type, return the default policy
			if ($this->defaultPolicy == self::Allow)
				$ret = new EventStatus (true, '', 0, 'Action was granted by default policy');
			else
				$ret = new EventStatus (false, 'Action denied', -1, 'No rule was set for this user');
		}
		else {
			$ret = $this->OnCheckAllowed($args);
		}

		$this->lastStatus = $ret;

		// Set execution status to denied
		if ($ret->isError()) {
			$this->executionStatus = self::Denied;
		}

		return $ret->isOK();
	}

	public function __toString() {
		return $this->command;
	}
	#endregion

	#region Events implementation
	/**
	 * @param ActionEventArgs $args
	 *
	 * @return EventStatus|null
	 */
	public function OnExecute(ActionEventArgs $args): ?EventStatus { return null; }

	/**
	 * @param ActionEventArgs $args
	 *
	 * @return EventStatus
	 */
	public function OnCheckAllowed(ActionEventArgs $args): EventStatus {
		if ($args->user == null) {
			if ((count($this->allowedRoles) > 0 && !in_array('anonymous', $this->allowedRoles)) || count($this->allowedPermissions) > 0) {
				return new EventStatus(false, 'Action denied', -1, 'No rule was set for this user');
			}
			elseif ($this->defaultPolicy == self::Allow) {
				return new EventStatus (true, '', 0, 'Action was granted by default policy');
			}
			else {
				return new EventStatus (false, 'Action denied', -1, 'No rule was set for this user');
			}
		}

		if (count($this->allowedRoles) == 0 && count($this->allowedPermissions) == 0) {
			return new EventStatus ();
		}

		foreach ($this->allowedRoles as $role) {
			if ($args->user->roles()->contains($role)) {
				return new EventStatus ();
			}
		}

		foreach ($this->allowedPermissions as $perm) {
			if ($args->user->hasPermission($perm)) {
				return new EventStatus ();
			}
		}

		// TODO: Implement check against previous command

		// If there's no specific policy for this user type, return the default policy
		if ($this->defaultPolicy == self::Allow) {
			return new EventStatus (true, '', 0, 'Action was granted by default policy');
		}
		else
			return new EventStatus (false, 'Action denied', -1, 'No rule was set for this user');
	}
	#endregion
}
