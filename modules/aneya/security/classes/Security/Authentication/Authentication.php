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

namespace aneya\Security\Authentication;

use aneya\Core\AppNamespace;
use aneya\Core\CMS;
use aneya\Core\EventStatus;
use aneya\Core\IHookable;
use aneya\Security\Permission;
use aneya\Security\PermissionCollection;
use aneya\Security\Role;
use aneya\Security\RoleCollection;
use aneya\Security\User;

abstract class Authentication {
	#region Constants
	const AuthLocked = -2;
	const AuthFailed = -1;
	const AuthPassed = 2;
	#endregion

	#region Events
	/** Triggered when the IAuthenticatable object succeeded username/password validation during the authentication process. Passes an AuthenticationEventArgs instance to listeners. */
	const EventOnAuthenticationValidated = 'OnAuthenticationValidated';
	/** Triggered when the IAuthenticatable object succeeded authentication. Passes an AuthenticationEventArgs instance to listeners. */
	const EventOnAuthenticated = 'OnAuthenticated';
	/** Triggered when the IAuthenticatable object succeeded authentication. Passes an AuthenticationEventArgs instance to listeners. */
	const EventOnAuthenticationFailed = 'OnAuthenticationFailed';
	#endregion

	#region Error Codes
	const ErrorAuthenticationFailed = 9000;
	const ErrorRoleUnmatched        = 9001;
	const ErrorNamespaceUnmatched   = 9002;
	#endregion

	#region Properties
	#endregion

	#region Methods
	/**
	 * Authenticates an IAuthenticatable instance against the given credentials and returns the authentication status.
	 * Upon success, status's data property will contain the User object that was authenticated and instantiated.
	 */
	public function authenticate($credentials, IAuthenticatable $user = null, AuthenticationOptions $options = null): EventStatus {
		$status = $this->validate($credentials, $user, $options);
		$args = new AuthenticationEventArgs ($this, $user, $this, $options, $status);
		if ($status->isError()) {
			$status->code = self::ErrorAuthenticationFailed;

			if ($user instanceof IHookable)
				$user->trigger(self::EventOnAuthenticated, $args);

			User::triggerSt(self::EventOnAuthenticated, $args);

			return $status;
		}

		// The successfully validated IAuthenticatable's identifier is stored in the returned status
		$args->uid = $status->data;

		if ($user instanceof IHookable)
			$user->trigger(self::EventOnAuthenticationValidated, $args);

		User::triggerSt(self::EventOnAuthenticationValidated, $args);

		if ($user === null && $args->user instanceof IAuthenticatable) {
			$user = $args->user;
		}

		if ($user instanceof IAuthenticatable) {
			#region Validate against allowed namespace(s)
			if (strlen($options->namespace) > 0) {
				if (!(($ns = CMS::namespaces()->get($options->namespace)) instanceof AppNamespace && $user->namespaces()->contains($ns))) {
					$status->isPositive = false;
					$status->code = self::ErrorNamespaceUnmatched;
					$status->message = 'User is not granted access to this application section';
					$status->debugMessage = "User did not match namespace '$options->namespace'";
				}
			}
			#endregion

			#region Validate against allowed role(s)
			if ($status->isOK()) {
				if ($options->allowedRoles instanceof RoleCollection) {
					foreach ($options->allowedRoles->all() as $role) {
						if (!$user->roles()->contains($role)) {
							$status->isPositive = false;
							$status->code = self::ErrorRoleUnmatched;
							$status->message = 'User does not belong to the required roles';
							$status->debugMessage = "User did not match role '$role->code'";
						}
					}
				}
				elseif ($options->allowedRoles instanceof Role) {
					$role = $options->allowedRoles;
					if (!$user->roles()->contains($role)) {
						$status->isPositive = false;
						$status->code = self::ErrorRoleUnmatched;
						$status->message = 'User does not belong to the required roles';
						$status->debugMessage = "User did not match role '$role->code'";
					}
				}
				elseif (is_string($options->allowedRoles) && strlen($options->allowedRoles) > 0) {
					/** @var \SecurityModule $mod */
					$mod = CMS::module('aneya/security');
					$role = $mod->roles()->getByCode($options->allowedRoles);
					if (!$user->roles()->contains($role)) {
						if ($role == null)
							$role->code = $options->allowedRoles;

						$status->isPositive = false;
						$status->code = self::ErrorRoleUnmatched;
						$status->message = 'User does not belong to the required roles';
						$status->debugMessage = "User did not match role '$role->code'";
					}
				}
			}
			#endregion

			#region Validate against allowed permission(s)
			if ($status->isOK()) {
				if ($options->allowedPermissions instanceof PermissionCollection) {
					foreach ($options->allowedPermissions->all() as $perm) {
						if (!$user->hasPermission($perm)) {
							$status->isPositive = false;
							$status->code = self::ErrorRoleUnmatched;
							$status->message = 'User is not granted with all required permissions';
							$status->debugMessage = "User did not match permission '$perm->code'";
						}
					}
				}
				elseif ($options->allowedPermissions instanceof Permission) {
					$perm = $options->allowedPermissions;
					if (!$user->hasPermission($perm)) {
						$status->isPositive = false;
						$status->code = self::ErrorRoleUnmatched;
						$status->message = 'User is not granted with all required permissions';
						$status->debugMessage = "User did not match permission '$perm->code'";
					}
				}
				elseif (is_string($options->allowedPermissions) && strlen($options->allowedPermissions) > 0) {
					/** @var \SecurityModule $mod */
					$mod = CMS::module('aneya/security');
					$perm = $mod->permissions()->getByCode($options->allowedPermissions);
					if (!$user->hasPermission($perm)) {
						if ($perm == null)
							$perm->code = $options->allowedPermissions;

						$status->isPositive = false;
						$status->code = self::ErrorRoleUnmatched;
						$status->message = 'User is not granted with all required permissions';
						$status->debugMessage = "User did not match permission '$perm->code'";
					}
				}
			}
			#endregion
		}

		if ($status->isOK()) {
			if ($user === null && $args->user instanceof IAuthenticatable)
				$user = $args->user;

			if ($user === null) {
				$status->isPositive = false;
				$status->code = self::ErrorRoleUnmatched;
				$status->message = 'Error instantiating user';
				$status->debugMessage = sprintf("Could not instantiate User object for namespace %s (userClass: %s)", $args->options->namespace, $args->options->userClass);
			}
			else {
				if ($user instanceof IHookable)
					$user->trigger(self::EventOnAuthenticated, $args);

				User::triggerSt(self::EventOnAuthenticated, $args);

				if ($options->rememberMe)
					$user->setRememberToken($options->namespace);

				if ($options->stateless == false)
					$user->login($options->namespace);

				// If user isn't logged in, but options specify to update the last access
				if ($options->stateless && $options->updateLastAccess)
					$user->updateLastAccess();
			}

			// Store the instantiated User object to the returning status
			$status->data = $user;
		}

		else {
			if ($user instanceof IHookable)
				$user->trigger(self::EventOnAuthenticationFailed, $args);

			User::triggerSt(self::EventOnAuthenticationFailed, $args);
		}

		return $status;
	}

	/**
	 * Checks the validity of an IAuthenticatable instance's authentication information with the given credentials
	 *
	 * @param mixed                 $credentials
	 * @param ?IAuthenticatable      $user    (optional) If provided, validates credentials additionally against the instance's properties
	 * @param ?AuthenticationOptions $options (optional)
	 *
	 * @return EventStatus
	 */
	public abstract function validate($credentials, IAuthenticatable $user = null, AuthenticationOptions $options = null): EventStatus;
	#endregion
}
