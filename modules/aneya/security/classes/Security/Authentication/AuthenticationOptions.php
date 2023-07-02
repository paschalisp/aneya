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

use aneya\Security\Permission;
use aneya\Security\PermissionCollection;
use aneya\Security\Role;
use aneya\Security\RoleCollection;
use aneya\Security\User;

class AuthenticationOptions {
	#region Properties
	/** @var bool If true, a cookie will be stored on client's browser to remember the authentication and auto-login the next time */
	public bool $rememberMe = false;
	/** @var string The application's namespace to log into */
	public string $namespace = 'app';
	/** @var ?string User-derived class to instantiate upon successful authentication */
	public ?string $userClass = null;
	/** @var bool If true, no user information will be stored on the session (used for API-based authentications) */
	public bool $stateless = false;
	/** @var bool If true, user's last access information will be updated */
	public bool $updateLastAccess = true;
	/** @var RoleCollection|Role|string|null */
	public RoleCollection|Role|string|null $allowedRoles;
	/** @var PermissionCollection|Permission|array|string|null */
	public PermissionCollection|Permission|array|string|null $allowedPermissions;
	/** @var int[] Allowed user statuses. Valid array values are User::Status* constants */
	public array $allowedStatuses = [User::StatusActive];
	#endregion

	#region Constructor
	public function __construct (string $namespace = 'app', RoleCollection|Role|array|string $allowedRoles = null, PermissionCollection|Permission|array|string $allowedPermissions = null, bool $rememberMe = false, string $userClass = null, bool $updateLastAccess = true, bool $stateless = false, array $allowedStatuses = [User::StatusActive]) {
		$this->namespace = $namespace;
		$this->allowedRoles = $allowedRoles;
		$this->allowedPermissions = $allowedPermissions;
		$this->rememberMe = $rememberMe;
		$this->userClass = $userClass;
		$this->updateLastAccess = $updateLastAccess;
		$this->stateless = $stateless;
		$this->allowedStatuses = $allowedStatuses;
	}
	#endregion
}
