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

namespace aneya\Core\Data;

use aneya\Core\Hookable;
use aneya\Core\IHookable;
use aneya\Core\IStorable;
use aneya\Core\Utils\ObjectUtils;

class DataObjectFactory implements IHookable {
	use Hookable;

	#region Events
	const EventStOnCreate  = 'OnCreate';
	const EventStOnCreated = 'OnCreated';
	#endregion

	#region Methods
	/**
	 * Initializes the DataObjectFactory. Used internally and should not be called explicitly.
	 */
	public static function init() {
		static::hooksSt()->register([self::EventStOnCreate, self::EventStOnCreated]);
	}

	/**
	 * @param mixed $doc
	 * @return IStorable|mixed
	 */
	public static function create (mixed $doc): mixed {
		if (is_object ($doc)) {
			if (!isset ($doc->__class))
				return $doc;

			$class		= $doc->__class ?? '\\stdClass';
			$version	= $doc->__version ?? 1.0;
			$args		= (property_exists($class, '__classArgs') && isset($class::$__classArgs)) ? $class::$__classArgs : (isset($doc->__args) ? $doc->__args : array());
		}
		elseif (is_array ($doc)) {
			if (!isset ($doc['__class']))
				return $doc;

			$class		= $doc['__class'] ?? '\\stdClass';
			$version	= $doc['__version'] ?? 1.0;
			$args		= $doc['__args'] ?? [];
		} else
			return $doc;

		$statuses = self::triggerSt (self::EventStOnCreate, $args = new DataObjectFactoryEventArgs(null, $class, $version, $args, $doc));
		$isHandled = false;
		$object = null;
		foreach ($statuses as $status) {
			if ($status instanceof DataObjectFactoryEventStatus && $status->isHandled) {
				$object = $status->object;
				$isHandled = true;
			}
		}

		if (!$isHandled)
			$object = static::onCreate($args);

		$args->object = $object;
		self::triggerSt(self::EventStOnCreated, $args);

		return $object;
	}
	#endregion

	#region Event methods
	/**
	 * @param DataObjectFactoryEventArgs $args
	 * @return IStorable
	 */
	protected static function onCreate (DataObjectFactoryEventArgs $args) {
		$reflection = new \ReflectionClass($args->class);
		$classArgs = array();

		#region Instantiate the object
		if (is_object ($args->data)) { // object in $args is an object (stdClass probably)
			foreach ($args->args as $arg => $defaultValue) {
				if (isset ($args->data->$arg))
					$classArgs[] = $args->data->$arg;
				else
					$classArgs[] = $defaultValue;
			}
		}
		else { // object in $args is hash array
			foreach ($args->args as $arg => $defaultValue) {
				if (isset ($args->data[$arg]))
					$classArgs[] = $args->data[$arg];
				else
					$classArgs[] = $defaultValue;
			}

		}
		$obj = $reflection->newInstanceArgs($classArgs);
		#endregion

		$properties = array_keys (get_class_vars (get_class($obj)));

		// If object is IStorable, first call its own property initialization function
		if ($obj instanceof IStorable) {
			$obj->__classFromArray ($args->data);
		}

		#region Recursively copy all property values
		foreach ($args->data as $property => $value) {
			if (!in_array ($property, $properties)) {
				// Consider the case where the property name uses dot notation structure to sub-properties; then go through the sub-properties to set the correct property
				if (strpos ($property, '.') !== false) {
					$propsHierarchy = explode ('.', $property);
					$prop = $propsHierarchy[0];
					$tmpObj = $obj;
					$cnt = count ($propsHierarchy);
					$isOk = true;
					for ($i = 0; $i < $cnt - 1; $i++) {
						if (!isset ($tmpObj->$prop) || is_object($tmpObj->$prop)) {
							$isOk = false;
							break;
						}
						$tmpObj = $tmpObj->$prop;
					}
					if (!$isOk)
						continue;

					$tmpObj->$propsHierarchy[$cnt - 1] = $value;
				}
				else
					continue;
			}

			if (is_object($value) || is_array ($value)) {
				// Value is either object or an array, so we need to recursively assign sub-property's values to the property
				if (is_object ($obj->$property) && is_object($value) && get_class($obj->$property) == get_class ($value)) {
					// If both property and source value are objects of same type, just copy the values
					ObjectUtils::copy ($value, $obj->$property);
				}
				else {
					// Else, create an instance of the same class as value, and then copy the value
					$obj->$property = static::create ($value);
				}
			}
			else {
				// Value is scalar, so just assign it to the property
				$obj->$property = $value;
			}
		}
		#endregion

		return $obj;
	}
	#endregion
}

// Call DataObjectFactory's static initialization
DataObjectFactory::init();
