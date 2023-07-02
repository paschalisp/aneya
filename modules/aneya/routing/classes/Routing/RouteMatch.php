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

/**
 * Class RouteMatch
 * Represents
 * @package aneya\Core\Environment
 */
class RouteMatch {
	#region Properties
	/** @var string The matched URI regular expression */
	public $uri;
	/** @var array The matches array that was returned by preg_match() when the URI regex was checked */
	public $uriRegexMatches;

	/** @var string The matched HTTP method */
	public $method;

	/** @var string The matched requested host name */
	public $requestedHostName;
	/** @var string The matched server name */
	public $serverName;
	/** @var string The matched server port */
	public $serverPort;

	/** @var string The matched User namespace */
	public $userNamespace;
	/** @var string The matched User role */
	public $role;
	/** @var string The matched User permission */
	public $permission;

	/** @var bool The matched SSL connection status */
	public $ssl;
	/** @var bool The matched Ajax request status */
	public $ajax;

	/** @var Route The Route instance that matched the given rules */
	public $route;

	/** @var RouteEventArgs The original routing argument that was passed to the route method. Used to allow re-routing to subsequent or internal controllers. */
	public $args;
	#endregion

	#region Constructor
	/**
	 * @param string|string[]	$uri			The matched URI regular expression
	 * @param string|string[]	$method			The matched HTTP method. Valid values are Request::Method* constants
	 * @param string			$userNamespace	The matched User namespace that was searched for matching roles and permissions
	 * @param string|string[]	$role			The matched User role
	 * @param string|string[]	$permission		The matched User permission
	 * @param bool				$ssl			The matched SSL connection status
	 * @param bool				$ajax			The matched Ajax request status
	 */
	public function __construct ($uri, $method = null, $userNamespace = null, $role = null, $permission = null, $ssl = null, $ajax = null) {
		$this->uri = $uri;
		$this->method = $method;
		$this->userNamespace = $userNamespace;
		$this->role = $role;
		$this->permission = $permission;

		$this->ssl = $ssl;
		$this->ajax = $ajax;
	}
	#endregion

	#region Methods
	/**
	 * Returns the matched URI with language code switched to the given one.
	 *
	 * @param string $languageCode
	 * @param string $fallback
	 * @return mixed
	 */
	public function switchLanguageCode(string $languageCode, string $fallback = '/{__LC}') {
		$ret = preg_match($this->uri, $this->args->uri, $matches, PREG_OFFSET_CAPTURE);

		return ($ret && isset($matches['__LC']))
			? substr_replace($this->args->uri, $languageCode, $matches['__LC'][1], 2)
			: str_ireplace('{__LC}', $languageCode, $fallback);
	}
	#endregion
}
