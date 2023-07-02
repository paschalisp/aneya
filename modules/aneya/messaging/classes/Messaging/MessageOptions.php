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

namespace aneya\Messaging;


class MessageOptions {
	#region Properties
	/** @var string Sending method. Valid values are Message::Send* constants */
	public $method = Message::SendByEmail;
	/** @var int Message priority. Valid values are Message::Priority* constants */
	public $priority = Message::PriorityNormal;
	public $isHtml = true;
	/**@var bool If true, a notification e-mail will be sent to the user. Applies when sending method is Message::SendInternally */
	public $notify = false;
	#endregion

	public function __construct ($method = Message::SendByEmail, $priority = Message::PriorityNormal) {
		$this->method = $method;
		$this->priority = $priority;
	}
}
