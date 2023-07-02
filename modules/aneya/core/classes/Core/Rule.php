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
 * Portions created by Paschalis Ch. Pagonidis are Copyright (C) 2007-2022
 * Paschalis Ch. Pagonidis. All Rights Reserved.
 */

namespace aneya\Core;


class Rule {
	#region Constants
	const Allow		= 'allow';
	const Deny		= 'deny';
	#endregion

	#region Properties
	/** @var string */
	protected $defaultRule = Rule::Allow;

	/** @var string[] Allowed names */
	protected $_allowed = [];
	/** @var string[] Disallowed names */
	protected $_denied = [];
	#endregion

	#region Constructor
	#endregion

	#region Methods
	/**
	 * Adds a name to the allowed list
	 * @param string|string[] $name
	 *
	 * @return Rule
	 */
	public function allow($name) {
		if (is_array($name)) {
			foreach ($name as $n) {
				if (!in_array($n, $this->_allowed)) {
					$this->_allowed[] = $n;
				}
			}
		}
		elseif (!in_array($name, $this->_allowed)) {
			$this->_allowed[] = $name;
		}

		return $this;
	}

	/**
	 * Adds a name to the denied list
	 * @param string|string[] $name
	 *
	 * @return $this
	 */
	public function deny($name) {
		if (is_array($name)) {
			foreach ($name as $n) {
				if (!in_array($n, $this->_denied)) {
					$this->_denied[] = $n;
				}
			}
		}
		elseif (!in_array($name, $this->_denied)) {
			$this->_denied[] = $name;
		}

		return $this;
	}

	/**
	 * Returns true if the name is allowed; false otherwise
	 * @param string $name
	 *
	 * @return bool
	 */
	public function isAllowed($name) {
		return self::isAllowedSt($name, ['allow' => $this->_allowed, 'deny' => $this->_denied]);
	}
	#endregion

	#region Static methods
	/**
	 * Returns true if the name is allowed in the given permissions list; false otherwise
	 *
	 * A valid permissions list should be an array with an 'allow' and/or a 'deny' sub-arrays. (e.g. ['allow' => ['name1', 'name2'], 'deny' => 'name3', 'name4']])
	 * @param string $name
	 * @param array $list
	 *
	 * @return bool
	 */
	public static function isAllowedSt($name, $list) {
		if (isset ($list['allow']) && count($list['allow'])>0 && !in_array($name, $list['allow']))
			return false;
		if (isset ($list['deny']) && in_array($name, $list['deny']))
			return false;
		if (count($list) > 0 && !isset($list['allow']) && !isset($list['deny']) && !in_array($name, $list))
			return false;

		return true;
	}
	#endregion
}
