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


use aneya\Core\Utils\ObjectUtils;

final class Configuration {
	#region Methods
	/**
	 * Applies the given JSON configuration to the instance.
	 * If property values are already set in the instance, they will be overwritten;
	 * otherwise they will be appended to the existing properties.
	 *
	 * @param string|\stdClass $json
	 *
	 * @return $this
	 */
	public function applyJson($json) {
		if (is_string($json)) {
			$json = json_decode($json);
			if ($json === null) {
				CMS::logger()->warning("Failed to decode JSON configuration from passed string in aneya\\Core\\Configuration::applyJson(). Error message: " . json_last_error_msg());
			}
			else
				$this->applyJson($json);
		}
		else
			ObjectUtils::extend($json, $this);

		return $this;
	}

	/**
	 * Adds a configuration variable
	 *
	 * @param string $key
	 * @param string $value
	 */
	public function add($key, $value) {
		$this->_config[$key] = $value;
	}

	/**
	 * Returns the value of a configuration variable
	 *
	 * @param string $key The dot-annotation expression of the key
	 *
	 * @return mixed|null The value; null if key was not found
	 */
	public function get(string $key) {
		return ObjectUtils::getProperty($this, $key);
	}

	/**
	 * Sets the value to an existing configuration variable
	 *
	 * @param string $key
	 * @param string $value
	 * @param bool   $overwrite
	 */
	public function set($key, $value, $overwrite = true) {
		ObjectUtils::setProperty($this, $key, $value, $overwrite);
	}
	#endregion
}
