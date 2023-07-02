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

namespace aneya\API\Controllers;

use aneya\API\ApiController;
use aneya\API\ApiEntity;
use aneya\API\ApiEventArgs;
use aneya\API\ApiEventStatus;
use aneya\API\ApiOptions;
use aneya\API\ApiRoute;
use aneya\Routing\Request;
use aneya\Routing\RouteEventArgs;
use aneya\Routing\RouteMatch;

class ApiDefaultController extends ApiController {
	#region Properties
	#endregion

	#region Constructor
	public function __construct(ApiEventArgs $args = null, ApiOptions $options = null) {
		parent::__construct($args, $options);

		$this->routes->add($route = new ApiRoute('#^.*$#', Request::MethodOptions, null, null, null, null, null, 'options', ApiRoute::AuthTypeNone));
		$route->priority = PHP_INT_MAX;

		$this->routes->add($fail = new ApiRoute('#^/api/(.*)?$#', null, null, null, null, true, null, 'fallback', ApiRoute::AuthTypeNone));
		$fail->priority = PHP_INT_MAX;
	}
	#endregion

	#region Methods
	/** @inheritdoc */
	public function route(RouteEventArgs $args = null): RouteMatch|ApiEventStatus {
		#region Test if a route matches the request
		if ($args == null)
			$args = $this->_args;

		$match = parent::route($args);

		if (!($match instanceof RouteMatch))
			return $match;
		#endregion

		$status = new ApiEventStatus();

		#region Handle CORS requests
		if (isset($args->serverVars['HTTP_ORIGIN'])) {
			$status->headers['Access-Control-Allow-Origin'] = $args->serverVars['HTTP_ORIGIN'];
			$status->headers['Access-Control-Allow-Credentials'] = 'true';
			$status->headers['Access-Control-Max-Age'] = 86400;		// Allow caching for one day
		}
		if ($match->route->tag === 'options') {
			if (isset($args->serverVars['HTTP_ACCESS_CONTROL_REQUEST_METHOD'])) {
				$status->headers['Access-Control-Allow-Methods'] = strtoupper(implode(',', [Request::MethodGet, Request::MethodPost, Request::MethodPut, Request::MethodDelete, Request::MethodHead, Request::MethodOptions]));
			}
			if (isset($args->serverVars['HTTP_ACCESS_CONTROL_REQUEST_HEADERS'])) {
				$status->headers['Access-Control-Allow-Headers'] = $args->serverVars['HTTP_ACCESS_CONTROL_REQUEST_HEADERS'];
			}

			return $status;
		}
		#endregion

		#region Respond to fallback route
		if ($match->route->tag === 'fallback') {
			return new ApiEventStatus(false, '', Request::ResponseCodeNotFound);
		}
		#endregion

		return new ApiEventStatus(false, isHandled: false);
	}

	public function process(ApiEventArgs $args, ApiEntity $entity = null): ?ApiEventStatus {
		return null;
	}

	public function setup(ApiEventArgs $args): ?ApiEntity {
		return null;
	}
	#endregion

	#region Static methods
	#endregion
}
