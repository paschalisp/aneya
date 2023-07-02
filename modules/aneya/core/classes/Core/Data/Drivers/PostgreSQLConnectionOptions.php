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

class PostgreSQLConnectionOptions extends ConnectionOptions {
	#region Properties
	/** @var string The schema where PostgreSQL extensions are installed (e.g. pgcrypto). If empty, current schema will be assumed. */
	public string $extensionsSchema = '';
	#endregion

	#region Methods
	public function applyCfg(\stdClass $cfg, bool $strict = false): ConnectionOptions {
		parent::applyCfg($cfg, $strict);

		if ($strict) {
			$this->extensionsSchema = $cfg->extensionsSchema ?? '';
		}
		else {
			if (isset($cfg->extensionsSchema))
				$this->extensionsSchema = $cfg->extensionsSchema;
		}

		return $this;
	}

	public function toJson(): \stdClass {
		$cfg = parent::toJson();
		$cfg->extensionsSchema = $this->extensionsSchema;

		return $cfg;
	}
	#endregion
}
