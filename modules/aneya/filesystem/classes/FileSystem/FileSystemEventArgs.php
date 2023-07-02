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

use aneya\Core\EventArgs;

class FileSystemEventArgs extends EventArgs {
	#region Properties
	/** @var ?string The executed filesystem command */
	public ?string $command;
	/** @var ?string The source or original file or folder */
	public ?string $source;
	/** @var ?string The destination or target file or folder */
	public ?string $destination;
	#endregion

	#region Constructor
	/**
	 * @param mixed $sender (optional)
	 * @param string|null $command The executed filesystem command
	 * @param string|null $source The source or original file or folder
	 * @param string|null $destination The destination or target file or folder
	 */
	public function __construct ($sender = null, string $command = null, string $source = null, string $destination = null) {
		parent::__construct($sender);

		$this->command = $command;
		$this->source = $source;
		$this->destination = $destination;
	}
	#endregion

	#region Methods
	#endregion

	#region Static methods
	#endregion
}
