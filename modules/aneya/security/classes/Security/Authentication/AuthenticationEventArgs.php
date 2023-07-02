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

namespace aneya\Security\Authentication;

use aneya\Core\EventArgs;
use aneya\Core\EventStatus;

class AuthenticationEventArgs extends EventArgs {
	#region Properties
	/** @var IAuthenticatable */
	public $user;
	/** @var string */
	public $uid;
	/** @var Authentication */
	public $method;
	/** @var AuthenticationOptions */
	public $options;
	/** @var EventStatus */
	public $status;
	#endregion

	#region Constructor
	public function __construct ($sender = null, IAuthenticatable $user = null, Authentication $method = null, AuthenticationOptions $options = null, EventStatus $status = null) {
		parent::__construct($sender);

		$this->user = $user;
		$this->m = $method;
		$this->options = $options;
		$this->status = $status;
	}
	#endregion
}
