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

namespace aneya\Core\Data;

use aneya\Core\Collection;
use aneya\Core\CollectionEventArgs;
use aneya\Core\EventStatus;
use aneya\Forms\FormField;
use JetBrains\PhpStorm\ArrayShape;

class DataRowValidationEventStatus extends EventStatus implements \JsonSerializable {
	#region Properties
	/** @var DataRowErrorStatusCollection Hash array of errors found during the validation with column tag as key */
	public DataRowErrorStatusCollection $errors;
	#endregion

	#region Constructor
	public function __construct($isPositive = true, $message = '', $code = 0, $debugMessage = '', $isHandled = true) {
		parent::__construct($isPositive, $message, $code, $debugMessage, $isHandled);
		$this->errors = new DataRowErrorStatusCollection();

		$this->errors->on(Collection::EventOnItemAdded, function (CollectionEventArgs $args) {
			/** @var DataRowErrorStatus $status */
			$status = $args->newItem;
			if ($status->isError()) {
				$this->isPositive = false;
			}
		});
	}
	#endregion

	#region Methods
	/** Returns given column's error status collection. */
	public function get(DataColumn|FormField|string $column_or_tag): DataRowErrorStatusCollection {
		return $this->errors->get($column_or_tag);
	}

	/** Returns true if the status is positive. */
	public function isOK(): bool {
		return ($this->isPositive = !$this->errors->hasErrors());
	}

	/** Returns true if the status is negative (some error occurred). */
	public function isError(): bool {
		return !($this->isPositive = !$this->errors->hasErrors());
	}
	#endregion

	#region Interface methods
	#[ArrayShape(['isPositive' => "bool", 'code' => "\int|null|string", 'message' => "\null|string", 'data' => "\mixed|null", 'debugMessage' => "\null|string", 'columns' => "array"])]
	#[\ReturnTypeWillChange]
	public function jsonSerialize(bool $debug = false): array {
		$data = (new EventStatus($this->isOK(), strlen($this->message) > 0 ? $this->message : $this->errors->toString(), $this->code, $this->debugMessage, $this->isHandled))->jsonSerialize($debug);
		$data['columns'] = [];

		foreach ($this->errors->all() as $error) {
			$data['columns'][$error->column->tag] = (new EventStatus($error->isOK(), $error->message, $error->code, $error->debugMessage, $error->isHandled))->jsonSerialize($debug);
		}

		return $data;
	}
	#endregion
}
