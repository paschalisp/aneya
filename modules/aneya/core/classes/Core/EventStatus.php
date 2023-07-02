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

namespace aneya\Core;

class EventStatus extends Status {
	#region Properties
	/** @var bool Indicates whether the event has been handled by the listener and the framework should ignore the remaining event listeners */
	public bool $isHandled = true;
	#endregion

	#region Constructor
	/**
	 * EventStatus constructor.
	 *
	 * @param bool $isPositive Defines whether status is positive or negative
	 * @param ?string $message A status message
	 * @param ?int|?string $code A status code
	 * @param ?string $debugMessage Detailed information to be used internally for debugging purposes.
	 * @param bool $isHandled If handled, further execution should break in the event that the status is used.
	 */
	public function __construct (bool $isPositive = true, ?string $message = '', $code = 0, ?string $debugMessage = '', bool $isHandled = true) {
		parent::__construct($isPositive, $message, $code, $debugMessage);

		$this->isHandled = $isHandled;
	}
	#endregion

	#region Methods
	#endregion

	#region Static Methods
	/** Creates and returns an EventStatus based on the values set in the given status */
	public static function fromStatus(Status $status): static {
		$class = static::class;
		return new $class(isPositive: $status->isPositive, message: $status->message, code: $status->code, debugMessage: $status->debugMessage);
	}
	#endregion
}
