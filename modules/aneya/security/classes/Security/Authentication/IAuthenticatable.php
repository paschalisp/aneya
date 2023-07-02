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

use aneya\Core\Collection;
use aneya\Core\Data\Database;
use aneya\Security\Permission;
use aneya\Security\PermissionCollection;
use aneya\Security\RoleCollection;
use aneya\Security\User;

interface IAuthenticatable {
	/** Returns authenticated object's identifier, usually a username or e-mail */
	function getUID(): string;

	/** Returns authenticated object's encrypted password */
	function getPassword(): string;

	/** Returns a User (or User derived) class by retrieving the authentication token in user's browser (if any) */
	static function getByAuthenticationToken(string $namespace): ?User;

	function setRememberToken(string $namespace);

	/** Forces the authenticated object to update its last access information property */
	function updateLastAccess(): User;

	function namespaces(): Collection;

	function roles(): RoleCollection;

	function permissions(): PermissionCollection;

	/**
	 * @param Permission|string $permission
	 * @return boolean
	 */
	function hasPermission($permission): bool;

	/** Returns the IAuthenticatable instance's underlying database (if any) */
	function db(): Database;

	/**
	 * @param string $namespace
	 *
	 * @return mixed
	 */
	function login(string $namespace = 'app');

	function logout(string $namespace = 'app');
}
