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

namespace aneya\Core\Data\Drivers;


use aneya\Core\Data\ConnectionOptions;

class OracleConnectionOptions extends ConnectionOptions {
	#region Properties
	/** @var ?string */
	public ?string $sid = null;

	/** @var ?string The name of the pluggable DB that contains the schema. */
	public ?string $container = null;

	/** @var string The NLS_LANG environment variable value to set prior connecting to Oracle. */
	public string $nlsLang = 'AMERICAN_AMERICA.AL32UTF8';

	/** @var bool Lock the generated database schema cache to minimize the delays when accessing schema table information. */
	public bool $lockCache = true;

	/** @var string[] Limit the tables to be known and managed by the driver for this schema. */
	public array $limitTables = [];
	#endregion

	#region Methods
	public function applyCfg(\stdClass $cfg, bool $strict = false): ConnectionOptions {
		parent::applyCfg($cfg, $strict);

		if ($strict) {
			$this->sid = $cfg->sid ?? '';
			$this->container = $cfg->container ?? '';
			$this->nlsLang = $cfg->nlsLang ?? '';
			$this->lockCache = $cfg->lockCache ?? true;
			$this->limitTables = $cfg->limitTables ?? [];
		}
		else {
			if (isset($cfg->sid))
				$this->sid = $cfg->sid;
			if (isset($cfg->container))
				$this->container = $cfg->container;
			if (isset($cfg->nlsLang))
				$this->nlsLang = $cfg->nlsLang;
			if (isset($cfg->lockCache))
				$this->lockCache = $cfg->lockCache;
			if (isset($cfg->limitTables))
				$this->limitTables = $cfg->limitTables;
		}

		return $this;
	}

	public function toJson(): \stdClass {
		$cfg = parent::toJson();

		$cfg->sid = $this->sid;
		$cfg->container = $this->container;
		$cfg->nlsLang = $this->nlsLang;
		$cfg->lockCache = $this->lockCache;
		$cfg->limitTables = $this->limitTables;

		return $cfg;
	}
	#endregion
}
