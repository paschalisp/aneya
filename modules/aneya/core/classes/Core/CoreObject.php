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

/**
 * Class CoreObject
 * Represents a base, bare bone ancestor with event hooks support, for all objects introduced in the Framework
 *
 * @package aneya\Core
 */
class CoreObject implements IHookable {
	use Hookable;

	#region Static methods
	/**
	 * Sets an object's public property with the given value, using dot (.) notation to dive into sub-properties (e.g. "contactInfo.address.zipCode").
	 * @param mixed $object
	 * @param string $property
	 * @param mixed $value
	 * @return bool
	 */
	public static function setObjectProperty($object, $property, $value) {
		#region Search through all sub-properties hierarchy to properly set the value
		$hierarchy = explode('.', $property);
		if (($cnt = count($hierarchy)) == 1) {
			try {
				$object->$property = $value;
			}
			catch (\Exception $e) {}
		} else {
			$obj = $object;
			for ($i = 0; $i < ($cnt - 1); $i++) {
				$prop = $hierarchy[$i];
				// If sub-property is not set, break and don't set any value
				if (!isset ($obj->$prop)) {
					return false;
				}
				$obj = $obj->$prop;
			}

			$prop = $hierarchy[$cnt - 1];

			try {
				$obj->$prop = $value;
			}
			catch (\Exception $e) {}
		}
		#endregion

		return true;
	}

	/**
	 * Returns the value of an object's public property, traversing through any sub-properties, if necessary, using dot (.) notation (e.g. "contactInfo.address.zipCode").
	 * @param mixed $object
	 * @param string $property
	 * @return mixed
	 */
	public static function getObjectProperty($object, $property) {
		// Search through all sub-properties hierarchy to find for value
		$hierarchy = explode('.', $property);
		$obj = $object;
		foreach ($hierarchy as $prop) {
			if (!isset ($obj->$prop)) {
				return null;
			}

			$obj = $obj->$prop;
		}

		return $obj;
	}
	#endregion

	#region Magic methods
	public function __sleep() {
		$array = array();

		// We have to use Reflection to cycle through Iterators properties
		if ($this instanceof \Iterator) {
			$ref = new \ReflectionClass($this);
			$properties = $ref->getProperties();

			foreach ($properties as $property) {
				if ($property->isStatic())
					continue;

				if ($property->isPublic()) {
					$value = $property->getValue($ref);

					if (is_resource($value) || $value instanceof \Closure || $value instanceof \PDO || $value instanceof \PDOOCI\PDO)
						continue;
				}

				$array[] = $property->getName();
			}
		} else {
			foreach ($this as $property => $value) {
				if (strlen($property) == 0)
					continue;
				if (is_resource($value) || $value instanceof \Closure || $value instanceof \PDO || $value instanceof \PDOOCI\PDO)
					continue;

				$array[] = $property;
			}
		}

		return $array;
	}
	#endregion
}
