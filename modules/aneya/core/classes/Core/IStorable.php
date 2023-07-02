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

use aneya\Core\Data\DataRowValidationEventStatus;
use aneya\Core\Data\ODBMS;
use aneya\Core\Data\ORM\DataObjectMapping;

interface IStorable {
	#region Class-related methods
	/**
	 * Returns the property names that their values will be passed (in order) to the class's constructor in order to create a new instance of this class.
	 * The associative array holds property names in array's keys and their default value in array's values.
	 *
	 * Example return value is: array ('id' => 0, 'isConnected' => false);
	 */
	function __classArgs(): ?array;

	/**
	 * Returns the current version of the class's signature. Used to store the version number at the time an instantiated objects gets converted into a ODM document.
	 *
	 * @return mixed
	 */
	function __classVersion();

	/**
	 * Returns an associative array with the object properties that should be used (and/or ignored) to convert from one format to another (doc => obj and vice versa).
	 * Key 'allow' is used to indicate the properties to be used, and key 'deny' denotes the keys to ignore from serialization.
	 * If array is empty, then all object's properties will be converted.
	 *
	 * Examples:
	 *    array ('allow' => array ('id', 'title'), 'deny' => array ('__internalCount')); // Will allow only 'id' and 'title' properties to be serialized.
	 *    array ('deny' => array ('__internalCount')); // Will allow all properties except from '__internalCount'.
	 *    array ('id', 'title'); // Will allow only 'id' and 'title' properties to be serialized.
	 *    array ('allow' => array ('id', 'title', '__internalCount'), 'deny' => array('__internalCount')); // Will allow only 'id' and 'title' properties denying '__internalCount' property from being serialized.
	 */
	function __classProperties(): array;

	function __classGetProperty($property);

	function __classSetProperty($property, $value);

	/**
	 * Returns an array with the object properties that are allowed to be serialized
	 *
	 * @return string[]
	 */
	function __classStorableProperties(): array;

	/**
	 * Returns an associative array with all property names and their values that should be stored in object-oriented databases.
	 *
	 * @param ?ODBMS $db (optional) The database driver to use to convert any non-IStorable object properties found (hierarchically) into native objects
	 * @return array
	 */
	function __classToArray(ODBMS $db = null): array;

	/**
	 * Sets properties from the values found in the given associative array; usually retrieved from an object-oriented database.
	 *
	 * @param $array
	 * @return bool
	 */
	function __classFromArray($array): bool;
	#endregion

	#region Object-related methods
	/**
	 * Returns the ORM information that is available for this class
	 */
	function orm(): DataObjectMapping;

	/**
	 * Returns the ORM information that is available for this class
	 */
	static function ormSt(): DataObjectMapping;

	/**
	 * Returns true if the object has changed property values since the last call to orm()->synchronize()
	 */
	function hasChanged(): bool;
	#endregion

	#region Database-related methods
	/**
	 * Performs storage to the database
	 */
	function save(): EventStatus;

	/**
	 * Performs deletion of object record from the database
	 */
	function delete(): EventStatus;

	/**
	 * Performs validation of the object's properties
	 */
	function validate(): DataRowValidationEventStatus;
	#endregion
}
