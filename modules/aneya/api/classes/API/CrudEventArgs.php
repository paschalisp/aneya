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

class CrudEventArgs extends ApiEventArgs {
	#region Properties
	/** @var CrudEventStatus The resulted status of the CRUD operation */
	public $status;
	#endregion

	#region Constructor
	/**
	 * CrudEventArgs constructor.
	 * If sender is RouteEventArgs, sender's routing information will be used to initialize the arguments
	 *
	 * @param string|RouteEventArgs $sender
	 * @param string                $uri
	 * @param string                $serverName
	 * @param string                $requestedHostName
	 * @param int                   $serverPort
	 * @param string                $method
	 * @param bool                  $isSSL
	 * @param bool                  $isAjax
	 * @param array                 $getVars
	 * @param array                 $postVars
	 * @param string                $authType
	 * @param CrudEventStatus       $status
	 */
	public function __construct($sender = null, $uri = null, $serverName = null, $requestedHostName = null, $serverPort = null, $method = null, $isSSL = null, $isAjax = null, $getVars = null, $postVars = null, $authType = null, $status = null) {
		parent::__construct($sender, $uri, $serverName, $requestedHostName, $serverPort, $method, $isSSL, $isAjax, $getVars, $postVars, $authType);

		$this->status = $status;
	}
	#endregion

	#region Methods
	#endregion

	#region Static methods
	#endregion
}
