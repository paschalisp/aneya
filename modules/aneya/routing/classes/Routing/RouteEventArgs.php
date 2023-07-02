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
 * Portions created by Paschalis Ch. Pagonidis are Copyright (C) 2007-2022
 * Paschalis Ch. Pagonidis. All Rights Reserved.
 */

namespace aneya\Routing;

use aneya\Core\EventArgs;

class RouteEventArgs extends EventArgs {
	#region Properties
	/** @var string The requested URI. */
	public $uri;
	/** @var string The request's HTTP Method. Valid values are Request::Method* constants */
	public $method;
	/** @var string The server's hostname under which the page is executed */
	public $serverName;
	/** @var string The requested hostname under which the page is executed */
	public $requestedHostName;
	/** @var int The server's port under which the page is executed */
	public $serverPort;
	/** @var bool Indicates whether the executing page is requested via an SSL connection */
	public $isSSL;
	/** @var bool Indicates whether the executing page is requested via an Ajax call */
	public $isAjax;
	/** @var RouteMatch The matched route information (it is set after a route has matched) */
	public $routeMatch;
	/** @var array */
	public $getVars;
	/** @var array */
	public $postVars;
	/** @var array */
	public $filesVars;
	/** @var array */
	public $serverVars;
	/** @var array */
	public $sessionVars;
	/** @var array */
	public $cookieVars;
	#endregion

	#region Constructor
	/**
	 * @param mixed		$sender
	 * @param ?string	$uri				The requested URI. If not provided, $_SERVER['REQUEST_URI'] is applied by default.
	 * @param ?string	$serverName			The server's hostname. If not provided, $_SERVER['SERVER_NAME'] is applied by default.
	 * @param ?string	$requestedHostName	The requested hostname. If not provided, $_SERVER['HOST_NAME'] is applied by default.
	 * @param ?int		$serverPort			The server's port. If not provided, $_SERVER['PORT'] is applied by default.
	 * @param ?string	$method				The request's HTTP method. If not provided, $_SERVER['REQUEST_METHOD'] is applied by default.
	 * @param ?bool		$isSSL				Indicates whether the executing page is requested via an SSL connection. If not provided, it is calculated automatically.
	 * @param ?bool		$isAjax				Indicates whether the executing page is requested via an Ajax call. If not provided, it is calculated automatically.
	 * @param ?array	$getVars			The environment's GET variables. If not provided, $_GET is applied by default.
	 * @param ?array	$postVars			The environment's POST variables. If not provided, $_POST is applied by default.
	 * @param ?array	$filesVars			The environment's FILES variables. If not provided, $_FILES is applied by default.
	 * @param ?array	$serverVars			The environment's SERVER variables. If not provided, $_SERVER is applied by default.
	 * @param ?array	$sessionVars		The environment's SESSION variables. If not provided, $_SESSION is applied by default.
	 * @param ?array	$cookieVars			The environment's COOKIE variables. If not provided, $_COOKIE is applied by default.
	 */
	public function __construct(mixed $sender = null, string $uri = null, string $serverName = null, string $requestedHostName = null, int $serverPort = null, string $method = null, bool $isSSL = null, bool $isAjax = null, array $getVars = null, array $postVars = null, array $filesVars = null, array $serverVars = null, array $sessionVars = null, array $cookieVars = null) {
		parent::__construct($sender);

		$r = Request::fromEnv();

		$this->uri					= !is_null($uri)				? $uri					: $r->uri;
		$this->serverName			= !is_null($serverName)			? $serverName			: $r->serverName;
		$this->requestedHostName	= !is_null($requestedHostName)	? $requestedHostName	: $r->requestHostname;
		$this->serverPort			= !is_null($serverPort)			? $serverPort			: $r->port;
		$this->method				= !is_null($method)				? $method				: $r->method;
		$this->isSSL				= !is_null($isSSL)				? $isSSL				: $r->isSSL;
		$this->isAjax				= !is_null($isAjax)				? $isAjax				: $r->isAjax;
		$this->getVars				= is_array($getVars)			? $getVars				: $r->getVars ?? [];
		$this->postVars				= is_array($postVars)			? $postVars				: $r->postVars ?? [];
		$this->filesVars			= is_array($filesVars)			? $filesVars			: $r->filesVars ?? [];
		$this->serverVars			= is_array($serverVars)			? $serverVars			: $r->serverVars ?? [];
		$this->sessionVars			= is_array($sessionVars)		? $sessionVars			: $r->sessionVars ?? [];
		$this->cookieVars			= is_array($cookieVars)			? $cookieVars			: $r->cookieVars ?? [];
	}
	#endregion

	#region Methods
	#endregion

	#region Static methods
	#endregion
}
