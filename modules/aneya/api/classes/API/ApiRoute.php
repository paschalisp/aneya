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

namespace aneya\API;

use aneya\Routing\Route;
use aneya\Routing\RouteEventArgs;
use aneya\Routing\RouteMatch;

class ApiRoute extends Route {
	#region Constants
	/**
	 * No authorization is required (unsafe; use with caution in controlled/internal environments).
	 */
	const AuthTypeNone = 'none';
	/**
	 * Direct to an intermediate authorization server and return back with the authorization code.
	 * @see https://tools.ietf.org/html/rfc6749#section-1.3.1
	 */
	const AuthTypeAuthorizationCode = 'authcode';
	/**
	 * Clients are issued access token directly without authentication.
	 * @see https://tools.ietf.org/html/rfc6749#section-1.3.2
	 */
	const AuthTypeImplicit = 'implicit';
	/**
	 * Resource owner's (e.g. users) password credentials are used for authorization to obtain an access token.
	 * @see https://tools.ietf.org/html/rfc6749#section-1.3.3
	 */
	const AuthTypePasswordCredentials = 'password';
	/**
	 * Clients' credentials are used for authorization to obtain an access token.
	 * @see https://tools.ietf.org/html/rfc6749#section-1.3.4
	 */
	const AuthTypeClientCredentials = 'client';
	#endregion

	#region Properties
	/** @var string $authType Authorization type to check against during route's matching */
	public string $authType;
	/** @var bool $crud Enable automated CRUD operation */
	public bool $crud;
	#endregion

	#region Constructor
	/**
	 * @param string|string[] $uris Allowed URI(s) regular expressions (compatible with preg_match) to match with the request
	 * @param string|string[] $methods Allowed HTTP methods. Valid values are Request::Method* constants
	 * @param string|null $userNamespace User's namespace to check for roles/permissions
	 * @param string|string[] $roles Allowed user roles
	 * @param string|string[] $permissions Allowed user permissions
	 * @param bool|null $ssl Allowed (or not) request via an SSL connection
	 * @param bool|null $ajax Allowed (or not) request via an Ajax call
	 * @param string|null $tag A tag to name the Route
	 * @param string $authType Authorization type to check against during route's matching
	 * @param bool $crud Enable automated CRUD operation
	 */
	public function __construct($uris, $methods = null, string $userNamespace = null, $roles = null, $permissions = null, bool $ssl = null, bool $ajax = null, string $tag = null, string $authType = ApiRoute::AuthTypeClientCredentials, bool $crud = false) {
		parent::__construct($uris, $methods, $userNamespace, $roles, $permissions, $ssl, $ajax, $tag);

		$this->authType = $authType !== null ? $authType : ApiRoute::AuthTypeClientCredentials;
		$this->crud = $crud;
	}
	#endregion

	#region Methods
	/**
	 * @inheritdoc
	 */
	public function match(RouteEventArgs $args) {
		if (!($args instanceof ApiEventArgs)) {
			$args = new ApiEventArgs($args);
			$args->authType = $this->authType;
		}

		return self::matchSt($args, $this->uris, $this->methods, $this->requestedHostName, $this->serverName, $this->serverPort, $this->userNamespace, $this->roles, $this->permissions, $this->ssl, $this->ajax, $this->authType);
	}
	#endregion

	#region Static methods
	/**
	 * @inheritdoc
	 */
	public function matchSt(RouteEventArgs $args, $uriRegex, $methods = null, string $requestedHostName = null, string $serverName = null, int $serverPort = null, string $userNamespace = null, $roles = null, $permissions = null, bool $ssl = null, bool $ajax = null, $authType = self::AuthTypeClientCredentials) {
		// Ignore user credentials at this point, as this will be handled by the ApiController
		$ret = parent::matchSt($args, $uriRegex, $methods, $requestedHostName, $serverName, $serverPort, null, null, null, $ssl, $ajax);

		if ($args instanceof ApiEventArgs && !is_null($authType))
			$args->authType = $authType;

		#region Check additionally for API authorization
		if ($ret instanceof RouteMatch) {

		}
		#endregion

		return $ret;
	}
	#endregion
}
