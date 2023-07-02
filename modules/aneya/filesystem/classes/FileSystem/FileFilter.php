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

namespace aneya\FileSystem;

class FileFilter {
	#region Constants
	const Name				= 'name';
	const BaseName			= 'basename';
	const FileName			= 'filename';
	const Extension			= 'ext';
	const Type				= 'type';
	const Size				= 'size';
	const Path				= 'dir';

	/** Filter returns true always */
	const NoFilter			= '-';
	/** Filter returns false always */
	const FalseFilter		= 'false';
	/** Custom filter which expression is represented in the value */
	const Custom			= '?';

	const Equals			= '=';
	const NotEqual			= '!=';
	const IsEmpty			= 'empty';
	const NotEmpty			= '!empty';
	const StartsWith		= '.*';
	const EndsWith			= '*.';
	const NotStartWith		= '!.*';
	const NotEndWith		= '!*.';
	const Contains			= '*';
	const NotContain		= '!*';
	const InList			= '[]';
	const NotInList			= '![]';
	const InFolder			= '[D]';
	const NotInFolder		= '![D]';
	const Exists			= '==';
	const NotExists			= '!==';

	// Size conditions
	const LessThan			= '<';
	const LessOrEqual		= '<=';
	const GreaterThan		= '>';
	const GreaterOrEqual	= '>=';
	const Between			= '><';
	#endregion

	#region Properties
	/** @var File */
	public $file;
	/** @var string */
	public $property;
	/** @var string */
	public $condition;
	/** @var mixed */
	public $value;
	#endregion

	#region Constructor
	public function __construct ($property = FileFilter::Name, $condition = FileFilter::Equals, $value = null) {
		$this->property		= $property;
		$this->condition	= $condition;
		$this->value		= $value;
	}
	#endregion

	#region Methods
	/**
	 * Returns true if the property, condition and value are set correctly
	 * @return bool
	 */
	public function isValid () {
		if (!in_array($this->condition, [self::NoFilter, self::FalseFilter, self::InFolder, self::NotInFolder, self::Exists, self::NotExists]) && !in_array($this->property, [self::Name, self::Extension, self::Type, self::Size, self::Path]))
			return false;

		switch ($this->property) {
			case self::Name:
			case self::BaseName:
			case self::FileName:
			case self::Extension:
				if (!in_array($this->condition, [self::NoFilter, self::FalseFilter, self::Equals, self::NotEqual, self::StartsWith, self::NotStartWith, self::EndsWith, self::NotEndWith, self::Contains, self::NotContain, self::InList, self::NotInList]))
					return false;

				break;

			case self::Size:
				if (!in_array($this->condition, [self::NoFilter, self::FalseFilter, self::Equals, self::NotEqual, self::LessThan, self::LessOrEqual, self::GreaterThan, self::GreaterOrEqual, self::Between]))
					return false;

				if (!is_numeric($this->value))
					return false;

				break;

			case self::Type:
				if (!in_array($this->condition, [self::NoFilter, self::FalseFilter, self::Equals, self::NotEqual, self::InList, self::NotInList, self::Contains, self::NotContain]))
					return false;

				break;

			case self::Path:
				if (!in_array($this->condition, [self::NoFilter, self::FalseFilter, self::Equals, self::NotEqual, self::InFolder, self::NotInFolder, self::Exists, self::NotExists]))
					return false;
		}

		return true;
	}
	#endregion

	#region Static methods
	#endregion
}
