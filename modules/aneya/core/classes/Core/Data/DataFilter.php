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

use aneya\Core\CMS;
use aneya\Core\I18N\Locale;
use aneya\UI\Calendar\DateTimeRange;

class DataFilter implements \JsonSerializable {
	#region Constants
	const NoFilter			= '-';
	/** Filter returns false always */
	const FalseFilter		= 'false';
	/** Custom filter which expression is represented in the value */
	const Custom			= '?';

	const Equals			= '=';
	const NotEqual			= '!=';
	const LessThan			= '<';
	const LessOrEqual		= '<=';
	const GreaterThan		= '>';
	const GreaterOrEqual	= '>=';
	const IsNull			= 'null';
	const IsEmpty			= 'empty';
	const NotEmpty			= '!empty';
	const NotNull			= '!null';
	const StartsWith		= '.*';
	const EndsWith			= '*.';
	const NotStartWith		= '!.*';
	const NotEndWith		= '!*.';
	const Contains			= '*';
	const NotContain		= '!*';
	const InList			= '[]';
	const NotInList			= '![]';
	const InSet				= '{}';
	const NotInSet			= '!{}';
	const Between			= '><';
	#endregion

	#region Properties
	/**
	 * @var DataColumn
	 */
	public $column = null;
	/**
	 * Valid values DataFilter::* constants
	 * @var string
	 */
	public $condition = null;
	/**
	 * @var mixed
	 */
	public $value = null;
	#endregion

	#region Constructor
	public function __construct (DataColumn $column, $condition = self::NoFilter, $value = null) {
		$this->column = $column;
		$this->condition = $condition;
		$this->value = $value;
	}
	#endregion

	#region Methods
	/**
	 * Returns a filtering expression built in respect to the given database's syntax
	 *
	 * @return mixed
	 */
	public function getExpression () {
		if (!$this->isValid ()) {
			CMS::logger()->warning("Invalid expression: " . $this->column->columnName (true) . ' '. $this->condition . ' ' . ($this->value === null) ? '<null>' : (is_bool($this->value) ? ($this->value === true ? 'true' : 'false') : $this->value));
			return null;
		}

		return $this->column->table->db()->getFilterExpression ($this);
	}

	/**
	 * Returns true if the condition and value are set correctly
	 * @return bool
	 */
	public function isValid () {
		if (!in_array($this->condition, array (self::NoFilter, self::FalseFilter, self::Custom, self::Equals, self::NotEqual, self::LessThan, self::LessOrEqual, self::GreaterThan, self::GreaterOrEqual,
			self::IsNull, self::NotNull, self::IsEmpty, self::NotEmpty, self::StartsWith, self::EndsWith, self::NotStartWith, self::NotEndWith, self::Contains, self::NotContain,
			self::InList, self::NotInList, self::InSet, self::NotInSet, self::Between, self::Custom)))
			return false;

		if ((!in_array($this->condition, array (self::NoFilter, self::FalseFilter, self::IsNull, self::IsEmpty, self::NotEmpty, self::NotNull, self::InList, self::NotInList, self::InSet, self::NotInSet)) && ($this->value === null || (is_scalar($this->value) && (($this->column->dataType === DataColumn::DataTypeBoolean) ? !(is_bool($this->value) || in_array($this->value, [0, 1])) : strlen($this->value) == 0)))) ||
			(in_array($this->condition, array(self::InList, self::NotInList)) && ((!is_array($this->value) || count($this->value) == 0) && !($this->value instanceof DataExpression))) ||
			(in_array($this->condition, array(self::Between)) && (!is_array($this->value) || count($this->value) == 0) && !(($this->column->dataType == DataColumn::DataTypeDate || $this->column->dataType == DataColumn::DataTypeDateTime || $this->column->dataType == DataColumn::DataTypeTime) && $this->value instanceof DateTimeRange))) {
			return false;
		}

		return true;
	}

	/**
	 * Returns true if no filtering information should be handled
	 * @return boolean
	 */
	public function isEmpty () {
		return ($this->condition == self::NoFilter);
	}

	/** Returns true if no filtering information has been set. */
	public function isNull (): bool {
		return (!$this->condition);
	}
	#endregion

	#region Interface methods
	#[\ReturnTypeWillChange]
	public function jsonSerialize() {
		$format = $this->column->dataType == DataColumn::DataTypeDateTime
			? Locale::DateTime
			: ($this->column->dataType == DataColumn::DataTypeTime
				? Locale::DateOnly
				:Locale::DateOnly);

		if ($this->value instanceof \DateTime) {
			$value = CMS::locale()->toDate($this->value, $format);
		}
		elseif (is_array($this->value)) {
			$value = [];
			$max = count ($this->value);
			for ($num = 0; $num < $max; $num++) {
				if ($this->value[$num] instanceof \DateTime) {
					$value[] = CMS::locale ()->toDate ($this->value[$num], $format);
				}
				else {
					$value[] = $this->value[$num];
				}
			}
		}
		else {
			$value = $this->value;
		}

		return ['column' => $this->column->tag, 'operand' => $this->condition, 'value' => $value];
	}
	#endregion
}
