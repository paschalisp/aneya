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

namespace aneya\Core\Data\ORM;

use aneya\Core\ApplicationError;
use aneya\Core\Cacheable;
use aneya\Core\CMS;
use aneya\Core\Collection;
use aneya\Core\CoreObject;
use aneya\Core\Data\DataColumn;
use aneya\Core\Data\DataFilter;
use aneya\Core\Data\DataFilterCollection;
use aneya\Core\Data\DataRow;
use aneya\Core\Data\DataTable;
use aneya\Core\ICacheable;
use aneya\Core\IStorable;


class DataObjectMapping extends Collection implements ICacheable {
	use Cacheable;

	#region Properties
	/** @var DataObjectTableMapping[] $_collection */
	protected array $_collection;

	/** @var mixed */
	protected $_object;
	protected DataTable $_dataSet;
	protected DataRow $_dataRow;
	#endregion

	#region Constructor
	public function __construct($object = null) {
		parent::__construct('\\aneya\\Core\\Data\\ORM\\DataObjectTableMapping', true);

		$this->_object = $object;
	}
	#endregion

	#region Methods
	#region ORM methods
	/**
	 * Gets/sets the object that is associated with the data/object mapping instance
	 *
	 * @param object|null $object
	 * @return object
	 */
	public function object(object $object = null): object {
		if (is_object($object)) {
			$this->_object = $object;
		}

		return $this->_object;
	}

	/**
	 * Returns a new instantiated object based on the mapping information
	 * @return mixed
	 */
	public function generateObject(): mixed {
		if ($this->count() == 0)
			return null;

		$className = '';
		try {
			$className = $this->first()->className;
			$obj = new $className();
			if ($obj instanceof IDataObject) {
				foreach ($this->_collection as $dotm) {
					foreach ($dotm->properties->all() as $prop) {
						$obj->setPropertyValue($prop, $prop->column->defaultValue);
					}
				}
			} else {
				foreach ($this->_collection as $dotm) {
					foreach ($dotm->properties->all() as $prop) {
						CoreObject::setObjectProperty($obj, $prop->propertyName, $prop->column->defaultValue);
					}
				}
			}

			return $obj;
		} catch (\Exception $e) {
			$error = new ApplicationError("Failed to generate object from class $className. Exception message: " . $e->getMessage());
			CMS::app()->log($error, ApplicationError::SeverityWarning);
		}

		return null;
	}

	/**
	 * Gets/sets the DataSet that is associated with this data/object mapping instance
	 *
	 * @param DataTable|null $ds If provided, it associates the internal DataSet instance with the given argument
	 */
	public function dataSet(DataTable $ds = null): DataTable {
		if ($ds instanceof DataTable) {
			if ($this->_object instanceof IStorable) {
				/** @var IStorable|string $class */
				$class = get_class($this->_object);
				if ($class::ormSt()->dataSet() !== $ds) {
					throw new \InvalidArgumentException("Cannot change Storable class's $class DataSet");
				}
			} else {
				$this->_dataSet = $ds;
			}
		}

		// If internal DataSet is still null, generate one given the first data/object table mapping (as there is no relation information between the other mappings)
		if ($this->_dataSet == null) {
			if ($this->_object instanceof IStorable) {
				/** @var IStorable $class */
				$class = get_class($this->_object);
				$this->_dataSet = $class::ormSt()->dataSet();
			} else {
				$this->_dataSet = $this->first()->toDataSet();
			}
		}

		return $this->_dataSet;
	}

	/**
	 * Gets/sets the DataRow that is associated with the data/object mapping instance
	 */
	public function row(DataRow $row = null): DataRow {
		$set = false;

		if ($row instanceof DataRow) {
			if (!$row->parent->equals($this->dataSet())) {
//				throw new \InvalidArgumentException("Cannot bind to a DataRow of a different DataSet");
			}
			$this->_dataRow = $row;
			$set = true;
		}

		if (!isset($this->_dataRow)) {
			$this->_dataRow = $this->dataSet()->clear()->newRow();
			$set = true;
		}

		if ($set) {
			if (is_object($this->_object)) {
				$this->_dataRow->object($this->_object);
			}
		}

		return $this->_dataRow;
	}

	/** Retrieves a row from the database and synchronizes the associated object with the retrieved values */
	public function retrieve(DataFilterCollection|DataFilter $filters): bool {
		$num = $this->dataSet()->clear()->retrieve($filters)->rows->count();
		if ($num == 0)
			return false;

		$this->row()->bulkSetValues($this->dataSet()->rows->first());

		$this->synchronize(DataRow::SourceDatabase);
		$this->row()->setState(DataRow::StateUnchanged);

		$this->dataSet()->rows->clear();

		return true;
	}

	/**
	 * Synchronizes ORM's internal DataRow with the object's current property values and vice versa, depending the direction the argument dictates.
	 *
	 * @param string $source (optional) Valid values are DataRow::SourceObject|SourceDatabase (default value is DataRow::SourceObject)
	 */
	public function synchronize(string $source = DataRow::SourceObject): DataObjectMapping {
		$row = $this->row();

		if ($source == DataRow::SourceDatabase) {
			foreach ($row->parent->columns->all() as $col) {
				$prop = $this->getPropertyName($col);
				if ($prop !== null) {
					$this->setPropertyValue($prop, $row->getValue($col));
				}
			}
		} elseif ($source == DataRow::SourceObject) {
			$flag = $row->suspendObjectSync;
			$row->suspendObjectSync = false;
			foreach ($this->_collection as $dotm) {
				foreach ($dotm->properties->all() as $prop) {
					$value = $this->getPropertyValue($prop);
					$row->setValue($prop->column, $value);
				}
			}
			$row->suspendObjectSync = $flag;
		}

		return $this;
	}

	/**
	 * Returns the object's state in the database. Return values are DataRow::State* constants
	 */
	public function state(): int {
		return $this->row()->getState();
	}

	/**
	 * Returns true if the object has changed property values since the last call to orm()->synchronize()
	 */
	public function hasChanged(): bool {
		return $this->row()->hasChanged();
	}
	#endregion

	#region Property methods
	/**
	 * Returns true if the provided property name exists in the ORM information
	 */
	public function hasProperty(string $property): bool {
		foreach ($this->_collection as $m) {
			foreach ($m->properties->all() as $p) {
				if ($p->propertyName == $property)
					return true;
			}
		}

		return false;
	}

	/**
	 * Returns the property's mapping information given the property's name
	 * @param DataColumn|string $name_or_column
	 * @return DataObjectProperty
	 */
	public function getProperty($name_or_column): ?DataObjectProperty {
		foreach ($this->_collection as $m) {
			foreach ($m->properties->all() as $p) {
				if (($name_or_column instanceof DataColumn && $p->column === $name_or_column) || ($p->propertyName == $name_or_column))
					return $p;
			}
		}

		return null;
	}

	/**
	 * Returns a field's ORM property name given its Column instance or field name
	 */
	public function getPropertyName(DataColumn $column): ?string {
		foreach ($this->_collection as $m) {
			foreach ($m->properties->all() as $p) {
				if ($column instanceof DataColumn && $p->column->tag == $column->tag) {
					return $p->propertyName;
				}
			}
		}

		return null;
	}

	/**
	 * Sets a property value
	 * @param DataObjectProperty|string $property The object's property name
	 * @param mixed $value The value to set the property
	 */
	public function setPropertyValue($property, $value) {
		$row = $this->row();

		if (!($property instanceof DataObjectProperty)) {
			$property = $this->getProperty($property);
		}

		// If property is not found in the collection, just return
		if (!($property instanceof DataObjectProperty)) {
			CMS::logger()->warning("Property $property was not found in ORM information of class " . get_class($this->_object));
			return;
		}

		// Convert values of specific data types
		$value = $row->convertValue($property->column, $value);

		$row->setValue($property->column, $value);

		// Set the new value to the object
		$this->setObjProperty($property, $value);
	}

	/**
	 * Returns a property's value
	 * @param DataObjectProperty|string $property The property's name from which the value will be returned
	 * @return mixed
	 */
	public function getPropertyValue($property) {
		if (!($property instanceof DataObjectProperty))
			$property = $this->getProperty($property);

		if (!($property instanceof DataObjectProperty)) {
			CMS::logger()->warning("Property was not found in ORM information of class " . get_class($this));
			return null;
		}

		$value = $this->getObjProperty($property);

		switch ($property->column->dataType) {
			case DataColumn::DataTypeInteger:
				return ($value === null || $value === '') ? null : (is_numeric($value) ? (int)$value : $value);
			case DataColumn::DataTypeFloat:
				return ($value === null || $value === '') ? null : (is_numeric($value) ? (float)$value : $value);
			case DataColumn::DataTypeBoolean:
				return ($value === null) ? null : (bool)$value;
			default:
				return $value;
		}
	}

	/**
	 * Performs bulk loading of values from a DataRow or a hash array
	 * @param DataRow|array $row_or_array
	 */
	public function bulkSetValues($row_or_array) {
		if ($row_or_array instanceof DataRow) {
			$row = $row_or_array;
			foreach ($row_or_array->parent->columns->all() as $col) {
				$prop = $this->getPropertyName($col);
				$this->setPropertyValue($prop, $row->getValue($col));
			}
		}
	}

	#region Internal methods

	/**
	 * Sets an object's property with the given value.
	 * It traverses all sub-property hierarchy
	 * @param DataObjectProperty|string $property
	 * @param mixed $value
	 */
	protected function setObjProperty($property, $value): bool {
		if ($property instanceof DataObjectProperty) {
			$property = $property->propertyName;
		}

		#region Search through all sub-properties hierarchy to properly set the value
		$hierarchy = explode('.', $property);
		if (($cnt = count($hierarchy)) == 1) {
			try {
				$this->object()->$property = $value;
			}
			catch (\Exception | \TypeError $e) {}
		} else {
			$obj = $this->object();
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
			catch (\Exception | \TypeError $e) {}
		}
		#endregion

		return true;
	}

	/**
	 * Returns the value of an object's property, traversing through any sub-properties, if necessary.
	 * @param DataObjectProperty|string $property
	 * @return mixed
	 */
	protected function getObjProperty($property) {
		if ($property instanceof DataObjectProperty)
			$property = $property->propertyName;

		// Search through all sub-properties hierarchy to find for value
		$hierarchy = explode('.', $property);
		$obj = $this->object();
		foreach ($hierarchy as $prop) {
			if (!isset ($obj->$prop)) {
				return null;
			}

			$obj = $obj->$prop;
		}

		return $obj;
	}
	#endregion
	#endregion

	#region Collection methods
	/**
	 * @inheritdoc
	 * @return DataObjectTableMapping[]
	 */
	public function all(callable $f = null): array {
		return parent::all($f);
	}

	/**
	 * @inheritdoc
	 */
	public function first(callable $f = null): DataObjectTableMapping {
		return parent::first($f);
	}
	#endregion

	#region Cacheable methods
	public function getCacheUid(): ?string {
		if ($this->count() == 0) {
			CMS::logger()->warning('Cannot get cache Uid on an empty DataObjectMapping');
			return null;
		}

		return $this->first()->className;
	}
	#endregion
	#endregion

	#region Static methods
	/**
	 * @throws \Exception
	 */
	public static function get($className): DataObjectMapping {
		return ORM::retrieve($className);
	}
	#endregion
}
