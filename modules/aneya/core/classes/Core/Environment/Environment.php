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

namespace aneya\Core\Environment;

use aneya\Core\CMS;

class Environment {
	#region Properties
	/** @var string Running environment's tag */
	public string $tag = '';

	/** @var Environment */
	protected static Environment $_env;
	#endregion

	#region Constructor
	private function __construct() { }
	#endregion

	#region Methods
	#region Interpreter methods
	public function isCLI(): bool {
		return php_sapi_name() === 'cli';
	}
	#endregion

	#region Request methods
	/**
	 * Returns current request's full-path URI location ($_SERVER['REQUEST_URI']).
	 * @return string
	 */
	public function uri(): string {
		if ($this->isCLI())
			return '';

		return $_SERVER['REQUEST_URI'];
	}
	#endregion

	#region Security methods
	/**
	 * @see \SecurityModule::roles()
	 */
	public function roles(): ?\aneya\Security\RoleCollection {
		if (CMS::modules()->isLoaded('aneya/security')) {
			/** @var \SecurityModule $mod */
			$mod = CMS::module('aneya/security');
			return $mod->roles();
		}
		else
			return null;
	}

	/**
	 * @see \SecurityModule::permissions()
	 */
	public function permissions(): ?\aneya\Security\PermissionCollection {
		if (CMS::modules()->isLoaded('aneya/security')) {
			/** @var \SecurityModule $mod */
			$mod = CMS::module('aneya/security');
			return $mod->permissions();
		}
		else
			return null;
	}
	#endregion
	#endregion

	#region Static methods
	/**
	 * Returns running environment's instance.
	 * @return Environment
	 */
	public static function instance(): Environment {
		if (!isset(static::$_env))
			static::$_env = new Environment();

		return static::$_env;
	}
	#endregion
}
