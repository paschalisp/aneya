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


namespace aneya\Core\Utils;


class JsonUtils {
	#region Constants
	#endregion

	#region Properties
	#endregion

	#region Constructor
	#endregion

	#region Methods
	#endregion

	#region Static methods
	/**
	 * Camelizes all (public) properties of the given object.
	 * Usually used for objects created dynamically from JSON strings.
	 *
	 * @param \stdClass|object $obj
	 *
	 * @return \stdClass|object
	 */
	public static function camelize($obj) {
		if (is_object($obj)) {
			foreach ($obj as $property => $value) {
				if ($property == 'protected' || $property == 'private' || strlen($property) == 0)
					continue;

				if (is_object($value))
					$value = static::camelize($value);

				$property = StringUtils::toCamelCase($property);
				$obj->$property = $value;
			}
		}

		return $obj;
	}

	/**
	 * Returns the decoded object representation of a JSON string.
	 *
	 * @see json_decode()
	 *
	 * @param string $str
	 * @param bool   $assoc
	 * @param int    $depth
	 * @param int    $options
	 *
	 * @return \stdClass|array
	 */
	public static function decode($str, $assoc = false, $depth = 512, $options = 0) {
		return json_decode($str, $assoc, $depth, $options);
	}

	/**
	 * Returns a JSON encoded representation of the given variable with unescaped slashes and unicode
	 *
	 * @param mixed $var
	 * @param bool  $stripNull If true, null properties will be omitted from the JSON representation
	 *
	 * @return string
	 */
	public static function encode($var, $stripNull = false) {
		if ($stripNull) {
			// Omit properties with null values
			$var = (object)array_filter((array)$var);
		}

		// Don't flatten JsonSerializable objects as they'll lose their
		if (!($var instanceof \JsonSerializable) && !is_scalar($var))
			$var = static::flatten($var);

		return json_encode($var, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	}

	/**
	 * Flattens (recursively) given object into an associative array composed by keys (object's properties) and values.
	 *
	 * @param object|array $obj
	 * @param bool $omitJsonSerializable If true (default), flattening will completely omit any JsonSerializable objects and property values.
	 *
	 * @return \JsonSerializable|array|string
	 */
	public static function flatten(object|array $obj, bool $omitJsonSerializable = true): \JsonSerializable|array|string {
		if ($obj instanceof \DateTime)
			return $obj->format('Y-m-d H:i:s');

		// Leave JsonSerializable objects intact
		if ($obj instanceof \JsonSerializable && $omitJsonSerializable)
			return $obj;

		$ret = array ();

		if (is_object($obj)) {
			foreach ($obj as $property => $value) {
				if ($property == 'protected' || $property == 'private' || strlen($property) == 0)
					continue;

				if (is_resource ($value) || $value instanceof \Closure || $value instanceof \PDO || $value instanceof \PDOOCI\PDO)
					continue;

				if ($value !== null && !is_scalar($value))
					$value = static::flatten($value, $omitJsonSerializable);

				$ret[$property] = $value;
			}
		}
		else {
			foreach ($obj as $property => $value) {
				if (is_resource ($value) || $value instanceof \Closure || $value instanceof \PDO || $value instanceof \PDOOCI\PDO)
					continue;

				if ($value !== null && !is_scalar($value))
					$value = static::flatten($value, $omitJsonSerializable);

				$ret[$property] = $value;
			}
		}

		return $ret;
	}

	/**
	 * Returns true if the given argument is an associative array (hash)
	 *
	 * @param array $array
	 *
	 * @return bool
	 */
	public static function isAssociativeArray($array) {
		return (is_array($array) && array_keys($array) !== range(0, count($array) - 1));
	}
	#endregion
}
