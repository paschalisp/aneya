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

namespace aneya\Core\Utils;


use aneya\Core\IStorable;

class ObjectUtils {
	#region Properties
	#endregion

	#region Constructor
	#endregion

	#region Methods
	#endregion

	#region Static methods
	/**
	 * Returns the (sub)property value of an object, given the property name's path represented in dot annotation (e.g. "contact.address.zipCode")
	 * @param object $object
	 * @param string $property
	 *
	 * @return mixed
	 */
	public static function getProperty($object, string $property) {
		$props = explode('.', $property);

		$value = $object;

		foreach ($props as $prop) {
			if (!isset($value->$prop)) {
				return null;
			}

			$value = $value->$prop;
		}

		return $value;
	}

	/**
	 * Sets an object's (sub)property value, given the property name's path represented in dot annotation (e.g. "contact.address.zipCode")
	 *
	 * If intermediate properties are not present or are scalars, they will be (re)constructed as an \stdClass.
	 *
	 * @param object $object
	 * @param string $property
	 * @param mixed $value
	 * @param bool $overwrite If false, the property will not be overwritten in case it is already set
	 */
	public static function setProperty($object, string $property, $value, $overwrite = true) {
		$props = explode('.', $property);

		$obj = $object;

		$cnt = count($props);
		$num = 0;

		foreach ($props as $prop) {
			if ($num++ == ($cnt-1)) {
				if (isset($obj->$prop) && !$overwrite)
					return;

				$obj->$prop = $value;
				return;
			}

			if (!is_object($obj->$prop))
				$obj->$prop = new \stdClass();

			$obj = $obj->$prop;
		}
	}

	/**
	 * Recursively copies source object's properties into target object, removing any sub-properties of conflicting properties.
	 * @param object $source
	 * @param object $target
	 */
	public static function copy($source, $target) {
		// We have to use Reflection to cycle through Iterators properties
		if ($source instanceof \Iterator) {
			$ref = new \ReflectionClass($source);
			$properties = $ref->getProperties();

			foreach ($properties as $property) {
				if ($property->isStatic())
					continue;

				if (!$property->isPublic()) {
					if ($source instanceof IStorable)
						$source->__classSetProperty($property->getName(), $source->__classGetProperty($property->getName()));

					continue;
				}

				$value = $property->getValue($ref);

				if (is_scalar($value)) {
					$target->$property = $value;
				}
				elseif (is_null($value)) {
					$target->$property = null;
				}
				elseif (strlen($property) == 0) {
					continue;
				}
				elseif ($value instanceof \MongoId) {
					$target->$property = $value;
				}
				else {
					$target->$property = unserialize(serialize($value));
				}
			}
		}
		else {
			foreach ($source as $property => $value) {
				if ($property == 'protected' || $property == 'private') {
					if ($source instanceof IStorable)
						$source->__classSetProperty($property, $value);
				}
				elseif (is_scalar($value)) {
					$target->$property = $value;
				}
				elseif (is_null($value)) {
					$target->$property = null;
				}
				elseif (strlen($property) == 0) {
					continue;
				}
				elseif ($value instanceof \MongoId) {
					$target->$property = $value;
				}
				else {
					$target->$property = unserialize(serialize($value));
				}
			}
		}
	}

	/**
	 * Extends target object by applying source object's properties recursively.
	 * @param object $source
	 * @param object $target
	 */
	public static function extend($source, $target) {
		#region For Iterators use Reflection to cycle through properties
		if ($source instanceof \Iterator) {
			$ref = new \ReflectionClass($source);
			$properties = $ref->getProperties();

			foreach ($properties as $property) {
				if ($property->isStatic())
					continue;

				if (!$property->isPublic()) {
					if ($source instanceof IStorable)
						$source->__classSetProperty($property->getName(), $source->__classGetProperty($property->getName()));

					continue;
				}

				$value = $property->getValue($ref);

				if (is_scalar($value)) {
					$target->$property = $value;
				}
				elseif (is_null($value)) {
					$target->$property = null;
				}
				elseif (strlen($property) == 0) {
					continue;
				}
				elseif ($value instanceof \MongoId) {
					$target->$property = $value;
				}
				else {
					if (is_object($value) && is_object($target->$property)) {
						static::extend($value, $target->$property);
					}
					else {
						$target->$property = unserialize(serialize($value));
					}
				}
			}
		}
		#endregion

		#region For all other object types use foreach to loop to properties
		else {
			foreach ($source as $property => $value) {
				if ($property == 'protected' || $property == 'private') {
					if ($source instanceof IStorable)
						$source->__classSetProperty($property, $value);
				}
				elseif (is_scalar($value)) {
					$target->$property = $value;
				}
				elseif (is_null($value)) {
					$target->$property = null;
				}
				elseif (strlen($property) == 0) {
					continue;
				}
				elseif ($value instanceof \MongoId) {
					$target->$property = $value;
				}
				else {
					if (is_object($value) && isset($target->$property) && is_object($target->$property)) {
						static::extend($value, $target->$property);
					}
					else {
						$target->$property = unserialize(serialize($value));
					}
				}
			}
		}
		#endregion
	}

	/**
	 * Flattens given object into an associative array composed by keys (object's properties) and values.
	 * Uses dot notation to convert sub-properties into keys and values pair.
	 *
	 * @param object|array $obj
	 * @return array
	 */
	public static function flatten ($obj) {
		$ret = array();
		foreach ($obj as $property => $value) {
			if ($property == 'protected' || $property == 'private' || strlen ($property) == 0)
				continue;

			$ret[$property] = $value;

			if (is_object($value)) {
				static::_flattenRecursively($value, $ret, $property);
			}
		}

		return $ret;
	}

	protected static function _flattenRecursively ($obj, &$array, $prefix) {
		foreach ($obj as $property => $value) {
			if ($property == 'protected' || $property == 'private' || strlen ($property) == 0)
				continue;

			$array["$prefix.$property"] = $value;
			if (is_object ($value)) {
				static::_flattenRecursively($value, $array, "$prefix.$property");
			}
		}
	}
	#endregion
}
