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

use aneya\Core\CMS;
use aneya\Core\Data\DataFilter;
use aneya\Core\Data\DataRow;
use aneya\Core\Data\DataSorting;
use aneya\Core\Encrypt;
use aneya\Core\EventStatus;
use aneya\Security\Permission;
use aneya\Security\PermissionCollection;
use aneya\Security\Role;
use aneya\Security\RoleCollection;
use aneya\Security\User;

class BasicAuthentication extends Authentication {
	public function validate($credentials, IAuthenticatable $user = null, AuthenticationOptions $options = null): EventStatus {
		$status = new EventStatus();

		if (!isset ($credentials['username']) || !isset($credentials['password'])) {
			$status->isPositive = false;
			$status->message = 'Invalid username or password';
			$status->debugMessage = 'Invalid credentials argument';
			$status->code = self::AuthFailed;

			return $status;
		}

		$username = strtolower(trim($credentials['username']));
		$password = trim($credentials['password']);

		#region Validate against credentials
		if ($user instanceof IAuthenticatable) {
			if ($username != $user->getUID() || Encrypt::verifyPassword($password, $user->getPassword())) {
				$status->isPositive = false;
				$status->message = 'Invalid username or password';
				$status->debugMessage = sprintf('Either user\'s username "%s" does not match the entered username "%s" or passwords don\'t match', $user->getUID(), $username);
				$status->code = self::AuthFailed;

				return $status;
			}
		}

		$db = ($user instanceof IAuthenticatable) ? $user->db() : CMS::db();
		$ds = $db->schema->getDataSet('cms_users', ['user_id', 'username', 'password', 'status', 'roles', 'permissions', 'namespaces'], true);

		// Ensure roles, permissions & namespaces have an array datatype
		$ds->columns->get('roles')->dataType = $ds->columns->get('permissions')->dataType = $ds->columns->get('namespaces')->dataType = DataColumn::DataTypeArray;

		$rows = $ds->retrieve(
				new DataFilter($ds->columns->get('username'), DataFilter::Equals, $username),
				new DataSorting($ds->columns->get('status'), DataSorting::Ascending)				// Sort by status to bring active users before deactivated
			)->rows;
		$userRow = $rows->first(function (DataRow $dataRow) use ($status, $password) {
			return Encrypt::verifyPassword($password, $dataRow->getValue('password'));
		});

		if (!isset($userRow)) {
			$status->isPositive = false;
			$status->message = 'Invalid username or password';
			if ($rows->count() > 0)
				$status->debugMessage = sprintf('Password hash mismatch for username %s', $username);
			else
				$status->debugMessage = sprintf('Username %s does not exist', $username);

			$status->code = self::AuthFailed;

			return $status;
		}
		#endregion

		#region Validate against allowed statuses
		if (!($options instanceof AuthenticationOptions) || !in_array($userRow->getValue('status'), $options->allowedStatuses)) {
			// Check if user is enabled
			switch ($userRow->getValue('status')) {
				case User::StatusPending:
					$status->isPositive = false;
					$status->message = $status->debugMessage = "Login failed for user '$username'. Account is pending.";
					$status->code = self::AuthFailed;
					break;

				case User::StatusLocked:
					$status->isPositive = false;
					$status->message = $status->debugMessage = "Login failed for user '$username'. Account is locked.";
					$status->code = self::AuthLocked;
					break;

				case User::StatusDisabled:
					$status->isPositive = false;
					$status->message = $status->debugMessage = "Login failed for user '$username'. Account is disabled.";
					$status->code = self::AuthFailed;
					break;

				case User::StatusActive:
					break;

				default:
					$status->isPositive = false;
					$status->message = $status->debugMessage = "Login failed for user '$username'. Invalid account status.";
					$status->code = self::AuthFailed;
					break;
			}

			return $status;
		}
		#endregion

		#region Validate against allowed namespace(s)
		if (strlen($options->namespace) > 0) {
			if (!in_array($options->namespace, $userRow->getValue('namespaces'))) {
				$status->isPositive = false;
				$status->code = self::ErrorNamespaceUnmatched;
				$status->message = 'User is not granted access to this application section';
				$status->debugMessage = "User did not match namespace '$options->namespace'";

				return $status;
			}
		}
		#endregion

		#region Validate against allowed role(s)
		if ($options->allowedRoles instanceof RoleCollection) {
			foreach ($options->allowedRoles->all() as $role) {
				if (!in_array($role, $userRow->getValue('roles'))) {
					$status->isPositive = false;
					$status->code = self::ErrorRoleUnmatched;
					$status->message = 'User does not belong to the required roles';
					$status->debugMessage = "User did not match role '$role->code'";

					return $status;
				}
			}
		} elseif ($options->allowedRoles instanceof Role) {
			$role = $options->allowedRoles;
			if (!in_array($role, $userRow->getValue('roles'))) {
				$status->isPositive = false;
				$status->code = self::ErrorRoleUnmatched;
				$status->message = 'User does not belong to the required roles';
				$status->debugMessage = "User did not match role '$role->code'";

				return $status;
			}
		} elseif (is_string($options->allowedRoles) && strlen($options->allowedRoles) > 0) {
			/** @var \SecurityModule $mod */
			$mod = CMS::module('aneya/security');
			$role = $mod->roles()->getByCode($options->allowedRoles);
			if (!in_array($role, $userRow->getValue('roles'))) {
				if ($role == null)
					$role->code = $options->allowedRoles;

				$status->isPositive = false;
				$status->code = self::ErrorRoleUnmatched;
				$status->message = 'User does not belong to the required roles';
				$status->debugMessage = "User did not match role '$role->code'";

				return $status;
			}
		}
		#endregion

		#region Validate against allowed permission(s)
		$permissions = [
			...([$userRow->getValue('permissions') ?? []]),
			...array_filter(
				array_map(function (string $s) {
					return ($role = CMS::env()->roles()->getByCode($s)) instanceof Role
						? $role->permissions->map(function (Permission $permission) { return $permission->code; } )
						: null;
					}, $userRow->getValue('roles')
				),
				function ($p) { return is_string($p) || is_array($p); }
			)
		];
		// Flatten the array
		$permissions = array_merge(...$permissions);

		if ($options->allowedPermissions instanceof PermissionCollection) {
			foreach ($options->allowedPermissions->all() as $perm) {
				if (!in_array($perm, $permissions)) {
					$status->isPositive = false;
					$status->code = self::ErrorRoleUnmatched;
					$status->message = 'User is not granted with all required permissions';
					$status->debugMessage = "User did not match permission '$perm->code'";

					return $status;
				}
			}
		} elseif ($options->allowedPermissions instanceof Permission) {
			$perm = $options->allowedPermissions;
			if (!in_array($perm->code, $permissions)) {
				$status->isPositive = false;
				$status->code = self::ErrorRoleUnmatched;
				$status->message = 'User is not granted with all required permissions';
				$status->debugMessage = "User did not match permission '$perm->code'";

				return $status;
			}
		} elseif (is_string($options->allowedPermissions) && strlen($options->allowedPermissions) > 0) {
			/** @var \SecurityModule $mod */
			$mod = CMS::module('aneya/security');
			$perm = $mod->permissions()->getByCode($options->allowedPermissions);
			if (!in_array($perm, $permissions)) {
				if ($perm == null) {
					$perm = new \stdClass();
					$perm->code = $options->allowedPermissions;
				}

				$status->isPositive = false;
				$status->code = self::ErrorRoleUnmatched;
				$status->message = 'User is not granted with all required permissions';
				$status->debugMessage = "User did not match permission '$perm->code'";

				return $status;
			}
		} elseif (is_array($options->allowedPermissions) && count($options->allowedPermissions) > 0) {
			foreach ($options->allowedPermissions as $perm) {
				if (!in_array($perm, $permissions)) {
					$status->isPositive = false;
					$status->code = self::ErrorRoleUnmatched;
					$status->message = 'User is not granted with all required permissions';
					$status->debugMessage = "User did not match permission '$perm'";

					return $status;
				}
			}
		}
		#endregion

		if ($status->isOK()) {
			$status->data = $userRow->getValue('userId');
		}

		return $status;
	}
}
