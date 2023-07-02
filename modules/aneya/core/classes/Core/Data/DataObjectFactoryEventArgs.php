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

namespace aneya\Core\Data;

use aneya\Core\EventArgs;

class DataObjectFactoryEventArgs extends EventArgs {
	/** @var string The class of the object when it gets instantiated */
	public $class;
	/** @var float The version of the class at the time the object was serialized */
	public $version;
	/** @var array Associative array with the property names (keys) that the object's constructor requires to be passed as arguments, and their default value (array values) */
	public $args;
	/** @var array Associative array that contains the object's property names (array keys) and their value (array values) */
	public $data;
	/** @var DataObject The newly instantiated object */
	public $object;

	/**
	 * @param object $sender
	 * @param string $class		The class of the object when it gets instantiated
	 * @param float  $version	The version of the class's signature at the time the object was serialized
	 * @param array  $args		Array with the property names that the object's constructor requires to be passed as arguments
	 * @param array  $data		Associative array that contains the object's property names (array keys) and their value (array values)
	 * @param ODBMS  $db        The database driver's instance to use for converting the object from/to native format
	 */
	public function __construct ($sender = null, $class = '\\stdClass', $version = 1.0, $args = array(), $data = null) {
		parent::__construct ($sender);

		$this->class	= $class;
		$this->version	= $version;
		$this->args		= $args;
		$this->data		= $data;
	}
}
