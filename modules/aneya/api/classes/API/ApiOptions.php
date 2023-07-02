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


use aneya\Core\CMS;
use Monolog\Logger;

class ApiOptions {
	#region Properties
	public string $version;
	public string $namespace = '';
	public ?string $serverKey;
	public ?string $trustKey;
	public ?string $permission;
	public int $tokenExpiresIn;
	public ?string $logFile;
	public int $logLevel;
	#endregion

	#region Constructor
	public function __construct(string $version, string $namespace, string $permission = null, string $serverKey = null, int $tokenExpiresIn = 120, string $logFile = '', int $logLevel = Logger::ERROR, string $trustKey = null) {
		$this->version = $version;
		$this->namespace = $namespace;
		$this->permission = $permission;
		$this->serverKey = $serverKey;
		$this->trustKey = $trustKey;
		$this->tokenExpiresIn = $tokenExpiresIn;
		$this->logFile = strlen($logFile) > 0 ? $logFile : CMS::appPath() . '/logs/api.log';
		$this->logLevel = $logLevel;
	}
	#endregion

	#region Methods
	#endregion

	#region Static methods
	#endregion
}
