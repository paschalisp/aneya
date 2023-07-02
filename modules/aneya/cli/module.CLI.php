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

use aneya\Core\Module;
use aneya\Core\Status;

/**
 * CLI module
 *
 * @package    aneya
 * @subpackage CLI
 * @author     Paschalis Pagonidis <p.pagonides@gmail.com>
 * @copyright  Copyright (c) 2007-2022 Paschalis Pagonidis <p.pagonides@gmail.com>
 * @license    https://www.gnu.org/licenses/agpl-3.0.txt
 */
class CLIModule extends Module {
	#region Properties
	protected string $_vendor  = 'aneya';
	protected string $_tag     = 'core';
	protected string $_version = '1.8.0.0';
	#endregion

	#region Methods
	public function install(): Status { return new Status(); }

	public function uninstall(): Status { return new Status(); }

	public function upgrade(): Status { return new Status(); }
	#endregion
}
