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

namespace aneya\Routing;

use aneya\Core\CoreObject;
use aneya\Security\User;

/**
 * Class Route
 * Represents a set of request-related rules to be checked for matching against an HTTP request.
 *
 * @package aneya\Core\Environment
 */
class Route extends CoreObject {
	#region Properties
	/** @var ?string A tag to name the Route */
	public ?string $tag = null;

	/** @var string[] Collection of URI regular expressions to test the request for matching */
	public ?array $uris = null;
	/** @var string[] */
	public ?array $methods = null;

	public ?string $requestedHostName = null;
	public ?string $serverName = null;
	public ?int $serverPort = null;

	public ?string $userNamespace = null;
	/** @var string[] */
	public ?array $roles = null;
	/** @var string[] */
	public ?array $permissions = null;

	public ?bool $ssl = null;

	public ?bool $ajax = null;

	/** @var int The priority to execute the Route for matching in a route collection or array */
	public int $priority = 0;
	#endregion

	#region Constructor
	/**
	 * @param ?string|?string[]	$uris			Allowed URI(s) regular expressions (compatible with preg_match) to match with the request
	 * @param ?string|?string[]	$methods		Allowed HTTP methods. Valid values are Request::Method* constants
	 * @param ?string $userNamespace			User's namespace to check for roles/permissions
	 * @param ?string|?string[]	$roles			Allowed user roles
	 * @param ?string|?string[]	$permissions	Allowed user permissions
	 * @param ?bool $ssl						Allowed (or not) request via an SSL connection
	 * @param ?bool $ajax						Allowed (or not) request via an Ajax call
	 * @param ?string $tag						A tag to name the Route
	 */
	public function __construct ($uris, $methods = null, string $userNamespace = null, $roles = null, $permissions = null, bool $ssl = null, bool $ajax = null, string $tag = null) {
		$this->uris = is_array($uris) ? $uris : [$uris];

		if ($methods !== null) {
			$this->methods = is_array($methods) ? $methods : [$methods];
		}

		$this->userNamespace = $userNamespace;
		if (isset($this->userNamespace)) {
			if ($roles !== null) {
				$this->roles = is_array($roles) ? $roles : [$roles];
			}

			if ($permissions !== null) {
				$this->permissions = is_array($permissions) ? $permissions : [$permissions];
			}
		}

		$this->ssl = ($ssl !== null) ? ($ssl == true) : null;
		$this->ajax = ($ajax !== null) ? ($ajax == true) : null;

		$this->tag = $tag;
	}
	#endregion

	#region Methods
	/**
	 * @param RouteEventArgs $args
	 * @return RouteMatch|RouteEventStatus|bool
	 */
	public function match(RouteEventArgs $args) {
		return self::matchSt($args, $this->uris, $this->methods, $this->requestedHostName, $this->serverName, $this->serverPort, $this->userNamespace, $this->roles, $this->permissions, $this->ssl, $this->ajax);
	}
	#endregion

	#region Static methods
	/**
	 * @param RouteEventArgs $args
	 * @param string|string[]	$uriRegex
	 * @param string|string[]	$methods
	 * @param string|null $requestedHostName
	 * @param string|null $serverName
	 * @param int|null $serverPort
	 * @param string|null $userNamespace
	 * @param string|string[]	$roles
	 * @param string|string[]	$permissions
	 * @param bool|null $ssl
	 * @param bool|null $ajax
	 * @return RouteMatch|RouteEventStatus|bool
	 */
	public function matchSt(RouteEventArgs $args, $uriRegex, $methods = null, string $requestedHostName = null, string $serverName = null, int $serverPort = null, string $userNamespace = null, $roles = null, $permissions = null, bool $ssl = null, bool $ajax = null) {
		#region Check against URIs
		$matches = false;
		$matchedUri = null;
		if (is_string($uriRegex)) {
			$uriRegex = [$uriRegex];
		}
		$ok = false;
		foreach ($uriRegex as $reg) {
			// String-based checking (doesn't start & end with slash (/) and doesn't start & end with the same character plus ^ and $ within the regex
			if ((!str_starts_with($reg, '/') || !str_ends_with($reg, '/')) && !(substr($reg, 0, 1) == substr($reg, -1) && substr($reg, 1, 1) == '^' && substr($reg, -2, 1) == '$')) {
				if ($reg == $args->uri) {
					$ok = true;
					$matchedUri = $reg;
				}
			}
			// Regex-based checking
			else {
				$ret = preg_match($reg, $args->uri, $matches);
				if ($ret > 0) {
					$matchedUri = $reg;
					$ok = true;
					break;
				}
			}
		}
		if (!$ok) {
			$status = new RouteEventStatus(false);
			$status->internalCode = RouteEventStatus::FailedURI;
			return $status;
		}
		#endregion

		#region Check against HTTP method
		$matchedMethod = $args->method;
		if (is_array($methods)) {
			$ok = false;
			foreach ($methods as $m) {
				if ($args->method == $m) {
					$matchedMethod = $m;
					$ok = true;
					break;
				}
			}

			if (!$ok) {
				$status = new RouteEventStatus(false);
				$status->internalCode = RouteEventStatus::FailedProtocol;
				return $status;
			}
		}
		elseif ($methods !== null && $args->method != $methods) {
			$status = new RouteEventStatus(false);
			$status->internalCode = RouteEventStatus::FailedProtocol;
			return $status;
		}
		#endregion

		#region Check against server info
		if ($requestedHostName !== null && $requestedHostName != $args->requestedHostName) {
			$status = new RouteEventStatus(false);
			$status->internalCode = RouteEventStatus::FailedHostname;
			return $status;
		}

		if ($serverName !== null) {
			// String-based checking (doesn't start & end with slash (/) and doesn't start & end with the same character plus ^ and $ within the regex
			if ((substr($serverName, 0, 1) !== '/' || substr($serverName, -1) !== '/') && !(substr($serverName, 0, 1) == substr($serverName, -1) && substr($serverName, 1, 1) == '^' && substr($serverName, -2, 1) == '$')) {
				if (strtolower($serverName) == strtolower($args->serverName)) {
					$ok = true;
				}
			}
			// Regex-based checking
			else {
				$ret = preg_match($serverName, $args->serverName, $matches);
				if ($ret) {
					$ok = true;
				}
			}
		}
		if (!$ok) {
			$status = new RouteEventStatus(false);
			$status->internalCode = RouteEventStatus::FailedHostname;
			return $status;
		}

		if ($serverPort !== null && $serverPort != $args->serverPort) {
			$status = new RouteEventStatus(false);
			$status->internalCode = RouteEventStatus::FailedPort;
			return $status;
		}
		#endregion

		#region Check against user permissions
		$matchedRole = $matchedPermission = null;
		if ($userNamespace !== null || $roles !== null || $permissions !== null) {
			$user = User::get($userNamespace);
			if (!($user instanceof User)) {
				$status = new RouteEventStatus(false);
				$status->internalCode = RouteEventStatus::FailedNamespace;
				return $status;
			}

			if ($roles !== null) {
				if (is_array($roles)) {
					$ok = false;
					foreach ($roles as $role) {
						if ($user->roles()->contains($role)) {
							$ok = true;
							break;
						}
					}
					if (!$ok) {
						$status = new RouteEventStatus(false);
						$status->internalCode = RouteEventStatus::FailedRole;
						return $status;
					}
				}
				elseif (!$user->roles()->contains($roles)) {
					$status = new RouteEventStatus(false);
					$status->internalCode = RouteEventStatus::FailedRole;
					return $status;
				}
			}

			if ($permissions !== null) {
				if (is_array($permissions)) {
					$ok = false;
					foreach ($permissions as $permission) {
						if ($user->hasPermission($permission)) {
							$ok = true;
							break;
						}
					}
					if (!$ok) {
						$status = new RouteEventStatus(false);
						$status->internalCode = RouteEventStatus::FailedPermission;
						return $status;
					}
				}
				elseif (!$user->hasPermission($permissions)) {
					$status = new RouteEventStatus(false);
					$status->internalCode = RouteEventStatus::FailedPermission;
					return $status;
				}
			}
		}
		#endregion

		#region Check against connection
		if ($ssl !== null && $ssl !== $args->isSSL) {
			$status = new RouteEventStatus(false);
			$status->internalCode = RouteEventStatus::FailedSSL;
			return $status;
		}

		if ($ajax !== null && $ajax !== $args->isAjax) {
			$status = new RouteEventStatus(false);
			$status->internalCode = RouteEventStatus::FailedAjax;
			return $status;
		}
		#endregion

		$ret = new RouteMatch($matchedUri, $matchedMethod, $userNamespace, $matchedRole, $matchedPermission, $args->isSSL, $args->isAjax);
		$ret->uriRegexMatches = $matches;
		$ret->requestedHostName = $args->requestedHostName;
		$ret->serverName = $args->serverName;
		$ret->serverPort = $args->serverPort;
		$ret->args = $args;

		return $ret;
	}
	#endregion
}
