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

use aneya\Core\EventStatus;
use aneya\Core\IRenderable;

class RouteEventStatus extends EventStatus {
	#region Constants
	const Succeeded 		= 'ok';
	const FailedURI			= 'uri';
	const FailedProtocol	= 'proto';
	const FailedMethod		= 'method';
	const FailedHostname	= 'host';
	const FailedPort		= 'port';
	const FailedNamespace	= 'ns';
	const FailedRole		= 'role';
	const FailedPermission	= 'perm';
	const FailedSSL			= 'ssl';
	const FailedAjax		= 'ajax';
	#endregion

	#region Properties
	/** @var string The URL to redirect the HTTP request if the property is set and status is marked as handled */
	public $redirectUrl;
	/** @var string Route matching internal coding to allow route handlers inspect the cause of a route matching failure */
	public $internalCode;
	/** @var int The HTTP response code to return (200 OK by default) */
	public $responseCode = Request::ResponseCodeOK;
	/** @var string The HTTP response code to use for the redirection, if a redirection URL has been set and the status is marked as handled */
	public $redirectResponseCode;
	/** @var string The fully qualified class name of the controller to call if status is marked as handled */
	public $controllerClass;

	/** @var IRenderable|string The renderable output to be sent back to the web client. Renderables can have any content type, like HTML code, JSON object, a file attachment etc. */
	public $output;
	#endregion

	#region Constructor
	#endregion

	#region Methods
	#endregion

	#region Static methods
	#endregion
}
