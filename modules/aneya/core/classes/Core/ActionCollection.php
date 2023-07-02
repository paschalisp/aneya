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

use aneya\Security\User;

class ActionCollection extends Collection {
	#region Properties
	/** @var Action[] */
	public array $_collection;
	#endregion

	#region Constructor
	public function __construct() {
		parent::__construct('\\aneya\\Core\\Action');
	}
	#endregion

	#region Methods
	/**
	 * Returns the default Action in the collection
	 *
	 * @return Action
	 */
	public function default(): ?Action {
		foreach ($this->_collection as $action)
			if ($action->isDefault) {
				return $action;
			}

		return null;
	}

	/**
	 * Returns the Action in the collection that handles the specified command
	 *
	 * @param string $command
	 *
	 * @return Action
	 */
	public function get(string $command): ?Action {
		foreach ($this->_collection as $action)
			if ($action->command == $command) {
				return $action;
			}

		return null;
	}

	/**
	 * @param User          $user
	 * @param Action|string $actionOrCommand
	 * @param string $previousCmd
	 *
	 * @return bool
	 */
	public final function isAllowed(User $user, $actionOrCommand, string $previousCmd = ''): bool {
		$act = ($actionOrCommand instanceof Action) ? $actionOrCommand : new Action($actionOrCommand);
		foreach ($this->_collection as $action)
			if ($action->command == $act->command)
				return $action->isAllowed($user, $previousCmd);

		return false;
	}

	/**
	 * @inheritdoc
	 * @return Action
	 */
	public function first(callable $f = null): Action {
		return parent::first($f);
	}

	/**
	 * @inheritdoc
	 * @return Action
	 */
	public function last(callable $f = null): Action {
		return parent::last($f);
	}
	#endregion
}
