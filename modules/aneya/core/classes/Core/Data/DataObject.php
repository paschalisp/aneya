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
use aneya\Core\Data\ORM\IDataObject;
use aneya\Core\EventStatus;

class DataObject extends DataRow {
	#region Methods
	#region Event methods
	/**
	 * Used internally and implements the saving mechanism of row's changes to the database
	 * @return EventStatus
	 */
	public function onSave(): EventStatus {
		if ($this->parent instanceof DataSet) {
			$tables = $this->parent->sortedTables();

			// Split columns into tables and save each table separately
			foreach ($tables as $tbl) {
				$row = clone $this;
				$row->parent = $tbl;
				$status = $row->onSave();
				if ($status->isError())
					return $status;

				if (in_array ($this->_state, array (self::StateAdded, self::StateModified))) {
					#region Assign parent column values to child columns in the relation
					$relations = $this->parent->relations->getByParent ($tbl);
					foreach ($relations as $r) {
						foreach ($r->getLinks() as $link) {
							/** @var DataColumn $pCol */
							$pCol = $link[0];
							/** @var DataColumn $cCol */
							$cCol = $link[1];

							$pValue = $row->getValue ($pCol->tag);
							if ($pCol->isAutoIncrement)
								$this->setValue ($pCol->tag, $pValue);

							$this->setValue ($cCol->tag, $pValue);
						}
					}
					#endregion
				}
			}

			return new EventStatus ();
		}
		else
			return $this->parent->db()->save ($this);
	}
	#endregion

	#region Value methods
	/**
	 * Returns the stored value of the given column in the row.
	 * If the column is multilingual, unless there is a language code specified as a second argument, it returns the column's value of the current language code.
	 *
	 * @param DataColumn|string|int $column
	 * @param string|null $langCode If column is multilingual and this argument contains a language code, then the returned value will be the column's value for the given language code.
	 * @return mixed
	 */
	public function getValue(DataColumn|string|int $column, string $langCode = null): mixed {
		if (!isset($this->_object) || !property_exists($this->_object, $column) || (func_num_args()>2 && func_get_arg(2) == self::SourceDatabase))
			return parent::getValue($column, $langCode);

		if (is_int ($column)) {
			$column = $this->columnAt ($column);
		}
		elseif (is_string ($column)) {
			$column = $this->columnAt ($column);
		}

		if ($column instanceof DataColumn) {
			$property = $column->tag;

			$value = $this->getObjProperty($property);

			// If column is multilingual, either return the value of the specified (if provided) or current language code
			if ($column->isMultilingual) {
				$lang = (isset ($langCode)) ? $langCode : CMS::translator()->currentLanguage()->code;
				if (isset ($value[$lang]))
					$value = $value[$lang];
				else
					return null;
			}

			switch ($column->dataType) {
				case DataColumn::DataTypeInteger:
					return (int)$value;
				case DataColumn::DataTypeFloat:
					return (float)$value;
				case DataColumn::DataTypeBoolean:
					return (bool)$value;
				default:
					return $value;
			}
		}

		return null;
	}

	/**
	 * Returns all translation values of the given column in the row in a hash array with the language codes as key.
	 * E.g. [ 'es' => 'value', 'fr' => 'value', 'it' => 'value' ]
	 * @param DataColumn|string|int $column
	 * @return array
	 */
	public function getValueTr ($column): ?array {
		if (!isset($this->_object))
			return parent::getValueTr($column);

		if (is_int ($column)) {
			$column = $this->columnAt ($column);
		}
		elseif (is_string ($column)) {
			$column = $this->columnAt ($column);
		}

		if (!($column instanceof DataColumn))
			return null;

		if (!$column->isMultilingual)
			return null;

		$property = $column->tag;
		if (property_exists (__CLASS__, $property))
			return $this->$property;

		return null;
	}

	/**
	 * @param DataColumn|string|int $column
	 * @param mixed $value
	 * @triggers OnValueChanged
	 */
	public function setValue ($column, $value) {
		if (!isset($this->_object)) {
			parent::setValue($column, $value);
			return;
		}

		#region Store the column in $column and its name in $property
		if (is_int ($column)) {
			$idx = $column;
			$column = $this->columnAt ($idx);
		}
		elseif (is_string ($column)) {
			$column = $this->columnAt ($column);
			$idx = $this->indexOf ($column);
		}
		else {
			$idx = $this->indexOf ($column);
		}

		if (!($column instanceof DataColumn)) {
			return;
		}

		$property = ($column->isExpression) ? $column->tag : $column->name;
		#endregion

		// Convert values of specific data types
		$value = static::convertValue($column, $value);

		#region Store the old value in $oldValue
		$lang = CMS::translator()->currentLanguage()->code;
		$oldValue = $this->getObjProperty($property);
		if ($column->isMultilingual) {
			if (is_array ($value)) {
				if (isset ($value[$lang]) && $value[$lang] == $oldValue[$lang])
					return;
			}
			elseif ($value == $oldValue[$lang])
				return;
		} else {
			if ($value === $oldValue && $this->_values[$property] === $oldValue)
				return;
		}
		#endregion

		#region Reset any error statuses for this column
		$statuses = $this->status->get($column);
		foreach ($statuses->all() as $status)
			$this->status->errors->remove($status);
		#endregion

		#region Validate new value
		$status = $column->validate (new DataRowValidationEventArgs($this, $value, $this));
		if ($status->isError()) {
			$this->status->isPositive = false;
			$this->status->errors->addRange($status->errors->all());
		}
		#endregion

		#region Set the new value
		if ($column->isMultilingual) {
			if (is_array ($value)) {
				$ret = $this->setObjProperty($property, $value);
				if ($ret)
					$this->_values[$property] = $value;
			}
			else {
				$values = $this->getObjProperty($property);
				if (is_array($values)) {
					$values[$lang] = $value;
					$this->setObjProperty($property, $values);
					$this->_values[$property][$lang] = $value;
				}
			}
		}
		else {
			$ret = $this->setObjProperty($property, $value);
			if ($ret)
				$this->_values[$property] = $value;
		}

		if ($this->_object instanceof IDataObject)
			$this->_object->setPropertyValue ($property, $value);
		#endregion

		// The first time the value is set, don't raise any event
		if ($this->_valuesIsSet[$property]) {
			$this->trigger (self::EventOnValueChanged, new DataRowValueChangedEventArgs ($this, $value, $oldValue, $column, $idx));

			if (in_array ($this->_state, array (self::StateUnchanged, self::StateNone)))
				$this->setState (self::StateModified);
		}
		else
			$this->_valuesIsSet[$property] = true;

		// Flag row as not validated
		$this->_isValidated = false;
	}

	/** Resets the row to the columns' default value */
	public function reset (): static {
		foreach ($this->parent->columns->all() as $c) {
			$property = $c->tag;
			$this->$property = $c->defaultValue;
			$this->_valuesIsSet[$c->tag] = true;
			if ($this->_state == self::StateModified)
				$this->_state = self::StateUnchanged;
		}

		return $this;
	}
	#endregion

	#region Get/set methods
	/**
	 * Returns row's current state
	 * @return int
	 */
	public function getState (): int {
		$changed = false;
		foreach ($this->_object as $property => $value) {
			if ($this->_object->$property != $this->_values[$property]) {
				$changed = true;
				break;
			}
		}

		if ($changed)
			if (in_array($this->_state, [self::StateUnchanged, self::StateNone]))
				return ($this->_state = self::StateModified);

		return $this->_state;
	}

	/** Returns true if the row has been changed (was either added, modified or deleted) */
	public function hasChanged (): bool {
		// TODO: Check what happens when setting the row's value (if property is also changed, then hasChanged will always return false)
		$changed = false;
		foreach ($this->_object as $property => $value) {
			if ($this->_object->$property != $this->_values[$property]) {
				$changed = true;
				break;
			}
		}
		return ($this->_state != self::StatePurged) && ($changed || in_array ($this->_state, array (self::StateAdded, self::StateModified, self::StateDeleted)));
	}

	public function __toString () {
		$values = array();
		foreach ($this as $property => $value)
			$values[] = $value;

		return implode ('|', str_replace('|', '\\|', $values));
	}
	#endregion
	#endregion
}
