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

namespace aneya\FileSystem;

use aneya\Core\EventStatus;
use JetBrains\PhpStorm\ArrayShape;

class FileSystemStatus extends EventStatus {
	#region Properties
	/** @var string|File|null The source or original file or folder */
	public string|File|null $source;
	/** @var string|File|null The destination or target file or folder */
	public string|File|null $destination;
	/** @var ?string the executed filesystem command */
	public ?string $command;
	#endregion

	#region Constructor
	#endregion

	#region Methods
	/**
	 * @inheritdoc
	 */
	#[Pure]
	#[ArrayShape(['isPositive' => "bool", 'code' => "\int|null|string", 'message' => "\null|string", 'data' => "\mixed|null", 'debugMessage' => "\null|string", 'destination' => "null|string", 'source' => "null|string"])]
	public function jsonSerialize(bool $debug = false): array {
		$data = parent::jsonSerialize($debug);

		$data['source'] = $this->source;
		$data['destination'] = $this->destination;

		return $data;
	}
	#endregion
}
