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

namespace aneya\Core\Data\ORM;


use aneya\Core\Data\DataSet;
use aneya\Core\Data\DataTable;
use aneya\Core\Storable;

class ObjectPool {
	#region Properties
	/** @var string */
	protected $_className;
	/** @var DataTable|DataSet */
	protected $_dataSet;
	#endregion

	#region Constructor
	/**
	 * @param string $className The fully qualified name of the Storable class to create the object pool for.
	 */
	public function __construct($className) {
		if (!is_a($className, '\\aneya\\Core\\Storable')) {
			throw new \InvalidArgumentException("Cannot create object pool for non-Storable class $className");
		}

		/** @var Storable $class */
		$class = $this->_className = $className;

		$this->_dataSet = clone $class::ormSt()->dataSet()->clear();
	}
	#endregion

	#region Methods
	public function exists ($rowKeyHash) {

	}

	public function get ($rowHeyHash) {

	}
	#endregion

	#region Static methods
	#endregion
}
