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

use aneya\Core\Hookable;
use aneya\Core\IHookable;

class Router implements IHookable {
	use Hookable;

	#region Events
	/** Triggers on HTTP request, when Request::route() method is called (usually right after framework's initialization). Passes a RouteEventArgs on listeners. */
	const EventOnHttpRequest	= 'OnHttpRequest';
	#endregion

	#region Properties
	public Request $request;

	public RouteControllerCollection $controllers;
	#endregion

	#region Constructor
	public function __construct() {
		$this->controllers = new RouteControllerCollection();

		$this->hooks()->register(self::EventOnHttpRequest);
	}
	#endregion

	#region Methods
	public function route(RouteEventArgs $args = null): ?RouteEventStatus {
		if ($args === null)
			// Let all default environment values to be applied
			$args = new RouteEventArgs();

		return $this->reroute($args);
	}

	public function reroute(RouteEventArgs $args): ?RouteEventStatus {
		$status = null;

		#region Route the request through the controllers collection
		$this->controllers->sort();
		foreach ($this->controllers->all() as $controller) {
			$status = $controller->route($args);
			if ($status instanceof RouteEventStatus && $status->isHandled)
				break;
		}
		#endregion

		#region Apply custom request routing if no controller handled the request
		if (!($status instanceof RouteEventStatus)) {
			$listeners = $this->trigger(self::EventOnHttpRequest, $args);
			foreach ($listeners as $listener) {
				if ($listener->isHandled) {
					$status = $listener;
					break;
				}
			}
		}
		#endregion

		if ($status instanceof RouteEventStatus) {
			if (strlen($status->redirectUrl) > 0) {
				$this->redirect($status->redirectUrl, $status->redirectResponseCode);
				return null;
			}
		}
		else
			return new RouteEventStatus(false, '', Request::ResponseCodeNotFound, '', false);

		return $status;
	}

	/** Sends HTTP headers with redirection information and exits the script's execution */
	public function redirect(string $url, int $responseCode = null): never {
		header("Location: $url", true, $responseCode);
		exit;
	}

	/**
	 * Registers a route controller to router's controller collection.
	 *
	 * Alias of: $this->controllers->add($controller);
	 */
	public function register(RouteController $controller): static {
		$this->controllers->add($controller);

		return $this;
	}
	#endregion

	#region Static methods
	#endregion
}
