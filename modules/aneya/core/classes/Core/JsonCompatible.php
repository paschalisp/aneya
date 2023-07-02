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

use aneya\Core\Data\DataRow;
use aneya\Core\Data\ORM\IDataObject;
use aneya\Core\Utils\DateUtils;
use aneya\Core\Utils\JsonUtils;

trait JsonCompatible {
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
	 *
	 * @return array
	 */
	public final function __jsProperties(): array {
		return (property_exists(static::class, '__jsProperties') && is_array(static::$__jsProperties)) ? static::$__jsProperties : [];
	}

	/** Converts object to array of properties and values. */
	#[\ReturnTypeWillChange]
	public function jsonSerialize(): array {
		$array = [];
		$jsProperties = $this->__jsProperties();

		if ($this instanceof IStorable) {
			$properties = $this->__classStorableProperties();
			foreach ($properties as $property => $value) {
				// Additional rule for storable objects (ignore internal Storage property)
				if ($property == '_hasChanged')
					continue;

				if (!Rule::isAllowedSt($property, $jsProperties))
					continue;

				// Auto-convert date formats
				if ($value instanceof \DateTime)
					$value = DateUtils::toJsDate($value);

				$array[$property] = $value;
			}
		}

		// We have to use Reflection to cycle through Iterators properties
		elseif ($this instanceof \Iterator) {
			try {
				$ref = new \ReflectionClass($this);
				$props = $ref->getProperties();

				foreach ($props as $prop) {
					if ($prop->isStatic())
						continue;

					$property = $prop->getName();

					if (!Rule::isAllowedSt($property, $jsProperties))
						continue;

					$value = $prop->getValue();

					if (is_resource($value) || $value instanceof \Closure || $value instanceof \PDO || $value instanceof \PDOOCI\PDO)
						continue;

					// Auto-convert date formats
					if ($value instanceof \DateTime)
						$value = DateUtils::toJsDate($value);
					elseif ($value instanceof \JsonSerializable)
						$value = $value->jsonSerialize();

					$array[$property] = $value;
				}
			}
			catch (\Exception $e) {}
		}

		else {
			foreach ($this as $property => $value) {
				// No need to output hooks on client-side application
				if (strlen($property) == 0 || $property == '_orm' || $property == '_hooks')
					continue;

				if (!Rule::isAllowedSt($property, $jsProperties))
					continue;

				if (is_resource($value) || $value instanceof \Closure || $value instanceof \PDO || $value instanceof \PDOOCI\PDO)
					continue;

				// Auto-convert date formats
				if ($value instanceof \DateTime)
					$value = DateUtils::toJsDate($value);
				elseif ($value instanceof \JsonSerializable)
					$value = $value->jsonSerialize();

				$array[$property] = $value;
			}
		}

		return JsonUtils::flatten($array);
	}

	/** Applies the matching property values of the given object to this instance. */
	public function applyJsonCfg(object $obj): static {
		$jsProperties = $this->__jsProperties();

		if ($this instanceof IDataObject) {
			$this->orm()->row()->bulkSetValues($obj);
			$this->orm()->synchronize(DataRow::SourceDatabase);
		}
		elseif ($this instanceof IStorable) {
			$properties = $this->__classStorableProperties();
			foreach ($properties as $property => $value) {
				// Additional rule for storable objects (ignore internal Storage property)
				if ($property == '_hasChanged')
					continue;

				if (!Rule::isAllowedSt($property, $jsProperties))
					continue;

				$this->$property = $value;
			}
		}

		// We have to use Reflection to cycle through Iterators properties
		elseif ($this instanceof \Iterator) {
			try {
				$ref = new \ReflectionClass($this);
				$props = $ref->getProperties();

				foreach ($props as $prop) {
					if ($prop->isStatic())
						continue;

					$property = $prop->getName();

					if (!Rule::isAllowedSt($property, $jsProperties))
						continue;

					$value = $prop->getValue();

					// Auto-convert date formats
					$this->$property = $value;
				}
			}
			catch (\Exception $e) {}
		}

		else {
			foreach ($this as $property => $value) {
				// No need to output hooks on client-side application
				if (strlen($property) == 0 || $property == '_orm' || $property == '_hooks')
					continue;

				if (!Rule::isAllowedSt($property, $jsProperties))
					continue;

				$this->$property = $value;
			}
		}

		return $this;
	}
}
