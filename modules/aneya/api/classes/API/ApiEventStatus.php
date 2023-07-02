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

use aneya\Routing\RouteEventStatus;

class ApiEventStatus extends RouteEventStatus {
	#region Properties
	/** @var string[] */
	public array $headers = [];
	#endregion

	#region Constructor
	public function __construct($isPositive = true, $message = '', $code = 0, $debugMessage = '', $isHandled = true) {
		parent::__construct($isPositive, $message, $code, $debugMessage, $isHandled);

		$this->output = new ApiOutput($this);
	}
	#endregion

	#region Methods
	public function __toString() {
		return sprintf('Positive: %s, Code: %s, Message: %s', ($this->isPositive ? "true" : "false"), $this->code, $this->message);
	}
	#endregion

	#region Static methods
	#endregion
}
