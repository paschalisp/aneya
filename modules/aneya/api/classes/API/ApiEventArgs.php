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


use aneya\Routing\RouteEventArgs;
use aneya\Routing\RouteMatch;

class ApiEventArgs extends RouteEventArgs {
	#region Properties
	public $version;
	public $data;
	/** @var string|null ApiRoute::AuthType* set of constants */
	public ?string $authType = null;
	#endregion

	#region Constructor
	/**
	 * ApiEventArgs constructor.
	 * If sender is RouteEventArgs, sender's routing information will be used to initialize the arguments
	 *
	 * @param string|RouteEventArgs $sender
	 * @param ?string               $uri
	 * @param ?string               $serverName
	 * @param ?string               $requestedHostName
	 * @param ?int                  $serverPort
	 * @param ?string               $method
	 * @param bool                  $isSSL
	 * @param bool                  $isAjax
	 * @param string                $authType
	 * @param ?array                $getVars
	 * @param ?array                $postVars
	 */
	public function __construct(mixed $sender = null, string $uri = null, ?string $serverName = null, string $requestedHostName = null, int $serverPort = null, string $method = null, bool $isSSL = null, bool $isAjax = null, array $getVars = null, array $postVars = null, $authType = null) {
		#region If sender is RouteEventArgs, initialize properties with sender's routing information
		if ($sender instanceof RouteEventArgs) {
			if ($uri === null)
				$uri = $sender->uri;
			if ($serverName === null)
				$serverName = $sender->serverName;
			if ($requestedHostName === null)
				$requestedHostName = $sender->requestedHostName;
			if ($serverPort === null)
				$serverPort = $sender->serverPort;
			if ($method === null)
				$method = $sender->method;
			if ($isSSL === null)
				$isSSL = $sender->isSSL;
			if ($isAjax === null)
				$isAjax = $sender->isAjax;

			$getVars = $sender->getVars ?? [];
			$postVars = $sender->postVars ?? [];

			$this->routeMatch = $sender->routeMatch;
			$sender = $sender->sender;

			// Set authorization type, if not explicitly set
			if ($authType === null && $this->routeMatch instanceof RouteMatch && $this->routeMatch->route instanceof ApiRoute) {
				/** @var ApiRoute $route */
				$route = $this->routeMatch->route;
				$authType = $route->authType;
			}
		}
		#endregion

		parent::__construct($sender, $uri, $serverName, $requestedHostName, $serverPort, $method, $isSSL, $isAjax, $getVars, $postVars);

		// Client credentials by default
		if ($authType === null)
			$authType = ApiRoute::AuthTypeClientCredentials;

		$this->authType = $authType;
	}
	#endregion

	#region Methods
	public function __toString() {
		return sprintf("%s %s",
					   strtoupper($this->method),
					   'http' . ($this->isSSL ? 's://' : '://') . $this->serverName . ((int)$this->serverPort > 0 ? ":$this->serverPort" : '') . $this->uri
		) . ($this->isAjax ? ' [XHR]' : '');
	}
	#endregion

	#region Static methods
	static function fromRouteArgs(RouteEventArgs $args): ApiEventArgs {
		return new ApiEventArgs($args->sender, $args->uri, $args->serverName, $args->requestedHostName, $args->serverPort, $args->method, $args->isSSL, $args->isAjax, $args->getVars, $args->postVars);
	}
	#endregion
}
