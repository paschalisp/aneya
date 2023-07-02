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

use JetBrains\PhpStorm\ArrayShape;
use JetBrains\PhpStorm\Pure;
use JsonSerializable;

class Status implements JsonSerializable {
	#region Constants
	const OK = true;
	const ERROR = false;
	#endregion

	#region Properties
	/** @var bool Indicates whether the status code has a positive meaning (i.e. successful operation) */
	public bool $isPositive = true;
	public $code = 0;
	public ?string $message;
	public ?string $debugMessage;

	/** @var mixed Custom payload to pass along with the status. */
	public $data = null;
	#endregion

	#region Constructor
	/**
	 * EventStatus constructor.
	 *
	 * @param bool $isPositive Defines whether status is positive or negative
	 * @param ?string $message A status message
	 * @param ?int|?string $code A status code
	 * @param ?string $debugMessage Detailed information to be used internally for debugging purposes.
	 */
	public function __construct(bool $isPositive = true, ?string $message = '', $code = 0, ?string $debugMessage = '') {
		$this->isPositive = $isPositive;
		$this->code = $code;
		$this->message = $message;
		$this->debugMessage = $debugMessage;
	}
	#endregion

	#region Methods
	/**
	 * Returns true if the status is positive
	 * @return bool
	 */
	public function isOK (): bool {
		return $this->isPositive;
	}

	/**
	 * Returns true if the status is negative (some error occurred)
	 * @return bool
	 */
	public function isError (): bool {
		return !$this->isPositive;
	}

	/**
	 * Returns a generated Exception based on the event status.
	 */
	public function toThrowable(): \Exception {
		return new \Exception($this->message . '. ' . $this->debugMessage, $this->code);
	}
	#endregion

	#region Interface Methods
	/**
	 * Returns the object in a JSON-compatible format.
	 *
	 * If debug argument is true, it will include debugMessage;
	 * otherwise debugMessage will be included if debugging mode is activated in the environment.
	 *
	 * @param bool $debug
	 * @return array
	 */
	#[Pure]
	#[ArrayShape(['isPositive' => "bool", 'code' => "int|null|string", 'message' => "null|string", 'data' => "mixed|null", 'debugMessage' => "null|string"])]
	#[\ReturnTypeWillChange]
	public function jsonSerialize(bool $debug = false): array {
		$data = [
			'isPositive'	=> $this->isPositive,
			'code'			=> $this->code,
			'message'		=> $this->message,
			'data'			=> $this->data
		];

		if ($debug || CMS::app()->debugMode)
			$data['debugMessage'] = $this->debugMessage;

		return $data;
	}
	#endregion
}
