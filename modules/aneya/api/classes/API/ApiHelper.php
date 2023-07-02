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

abstract class ApiHelper {
	#region Properties
	/** @var ApiController */
	protected ApiController $controller;

	/** @var ApiEntity */
	protected ApiEntity $entity;

	/** @var ApiEventArgs */
	protected ApiEventArgs $args;
	#endregion

	#region Constructor
	public function __construct(ApiController $controller, ApiEventArgs $args) {
		$this->controller = $controller;
		$this->args = $args;
	}
	#endregion

	#region Methods
	public abstract function apiSetup(): ?ApiEntity;

	public abstract function apiProcess(ApiEventArgs $args, ApiEntity $entity = null): ?ApiEventStatus;
	#endregion

	#region Static methods
	#endregion
}
