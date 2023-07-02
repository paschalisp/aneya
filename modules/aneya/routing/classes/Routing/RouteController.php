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

use aneya\Core\CMS;
use aneya\Core\Hookable;
use aneya\Core\IHookable;
use aneya\Core\Rule;
use aneya\Core\Utils\StringUtils;

class RouteController implements IHookable {
	use Hookable;

	#region Properties
	public RouteCollection $routes;

	/** @var string The namespace tag the router serves requests for */
	public string $namespace;

	/** @var int This controller's priority (the smaller number the more prioritized) over other route controllers registered in the framework */
	public int $priority = 0;

	protected RouteEventArgs $_args;

	protected Rule $autoMethods;
	#endregion

	#region Events
	const EventOnRoute = 'OnRoute';
	#endregion

	#region Constructor
	public function __construct(RouteEventArgs $args = null) {
		if ($args instanceof RouteEventArgs)
			$this->_args = $args;
		else
			// Apply the default routing arguments found in the environment
			$this->_args = new RouteEventArgs();

		$this->autoMethods = new Rule();

		$this->routes = new RouteCollection();
	}
	#endregion

	#region Methods
	/** Executes the given route and returns the routing result */
	public function route(RouteEventArgs $args = null): RouteMatch|RouteEventStatus {
		if ($args === null) {
			$args = $this->_args;
		}

		$ret = $this->routes->match($args);

		if ($ret instanceof RouteMatch) {
			$args->routeMatch = $ret;

			// Switch to controller's namespace
			if (strlen($this->namespace) > 0)
				CMS::ns($this->namespace);

			// Set language (if set) before routing the request
			$lang = $ret->uriRegexMatches['__LC'] ?? null;
			if (isset($lang))
				CMS::translator()->setCurrentLanguage($lang);

			// Call listeners
			$statuses = $this->trigger(self::EventOnRoute, $args);
			foreach ($statuses as $status) {
				if ($status->isHandled) {
					return $ret;
				}
			}

			// Call static listeners
			$this->triggerSt(self::EventOnRoute, $args);
			foreach ($statuses as $status) {
				if ($status->isHandled) {
					return $ret;
				}
			}

			#region Auto-execute by controllers' method name (if enabled)
			if (isset($ret->uriRegexMatches['routemethod'])) {
				$method = $ret->uriRegexMatches['routemethod'];

				if ($this->autoMethods->isAllowed($method)) {
					if (method_exists($this, $method)) {
						try {
							return $this->$method($ret);
						}
						catch (\TypeError $e) {}
					}
					elseif (method_exists($this, $camel = StringUtils::toCamelCase($method)) && $this->autoMethods->isAllowed($camel)) {

						try {
							return $this->$camel($ret);
						}
						catch (\TypeError $e) {}
					}
				}
			}
			#endregion

			return $ret;
		}

		else
			return new RouteEventStatus(false, isHandled: false);
	}

	/** Adds a route to the controller */
	public function add(Route $item): RouteController {
		$this->routes->add($item);

		return $this;
	}
	#endregion

	#region Static methods
	#endregion
}
