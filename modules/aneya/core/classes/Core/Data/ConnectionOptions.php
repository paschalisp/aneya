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


class ConnectionOptions {
	#region Properties
	/** @var string  The connection string. If provided, all other options are omitted, except from pdoOptions */
	public string $connString = '';

	/** @var string */
	public string $host = '';

	/** @var int */
	public int $port = 0;

	/** @var string */
	public string $database = '';

	/** @var string */
	public string $schema = '';

	/** @var string */
	public string $username = '';

	/** @var string */
	public string $password = '';

	/** @var string */
	public string $charset = '';

	/** @var string */
	public string $timezone = 'UTC';

	/** @var bool */
	public bool $readonly = false;

	/** @var int[] */
	public array $pdoOptions = [
		\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
    	\PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
		\PDO::ATTR_PERSISTENT => false							// Avoid persistent connections which tend to cause concurrency issues
	];
	#endregion

	#region Methods
	public function applyCfg(\stdClass $cfg, bool $strict = false): ConnectionOptions {
		if ($strict) {
			$this->host = $cfg->host ?? '';
			$this->port = $cfg->port ?? 0;
			$this->database = $cfg->database ?? '';
			$this->schema = $cfg->schema ?? '';
			$this->username = $cfg->username ?? '';
			$this->password = $cfg->password ?? '';
			$this->charset = $cfg->charset ?? '';
			$this->timezone = $cfg->timezone ?? $cfg->timeZone ?? '';
			$this->connString = $cfg->connString ?? '';
		}
		else {
			if (isset($cfg->host))
				$this->host = $cfg->host;
			if (isset($cfg->port))
				$this->port = $cfg->port;
			if (isset($cfg->database))
				$this->database = $cfg->database;
			if (isset($cfg->schema))
				$this->schema = $cfg->schema;
			if (isset($cfg->username))
				$this->username = $cfg->username;
			if (isset($cfg->password))
				$this->password = $cfg->password;
			if (isset($cfg->charset))
				$this->charset = $cfg->charset;
			if (isset($cfg->timeZone))
				$this->timezone = $cfg->timezone;
			if (isset($cfg->connString))
				$this->connString = $cfg->connString;
		}

		return $this;
	}

	public function toJson(): \stdClass {
		return (object)[
			'host' => $this->host,
			'port' => $this->port,
			'database' => $this->database,
			'schema' => $this->schema,
			'username' => $this->username,
			'password' => $this->password,
			'charset' => $this->charset,
			'timezone' => $this->timezone,
			'connString' => $this->connString
		];
	}
	#endregion

	#region Static methods
	public static function fromJson(\stdClass $cfg): ConnectionOptions {
		$class = static::class;
		$obj = new $class();
		return $obj->applyCfg($cfg);
	}
	#endregion
}
