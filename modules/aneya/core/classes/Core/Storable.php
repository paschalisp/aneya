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

use aneya\Core\Data\DataColumn;
use aneya\Core\Data\DataObjectFactory;
use aneya\Core\Data\DataRow;
use aneya\Core\Data\DataRowSaveEventArgs;
use aneya\Core\Data\DataRowValidationEventStatus;
use aneya\Core\Data\DataSet;
use aneya\Core\Data\DataTable;
use aneya\Core\Data\ODBMS;
use aneya\Core\Data\ORM\DataObjectMapping;
use aneya\Core\Data\ORM\DataObjectProperty;
use aneya\Core\Data\ORM\DataObjectPropertySaveEventArgs;
use aneya\Core\Data\ORM\ORM;

trait Storable {
	#region Properties
	protected ?DataObjectMapping $_orm;

	/** @var bool Indicates if the object has additional changes beyond ORM's changed status */
	protected bool $_hasChanged = false;

	/** @var array The current version of the class's signature. Used to store the version number at the time an instantiated objects gets converted into a ODM document. */
	private static array $__classVersion = [];

	/**
	 * Array of property names per class that their values will be passed (in the defined order) to the class's constructor in order to create a new instance of the class.
	 * The associative array holds property names in array's keys and their default value in array's values.
	 *
	 * Example return value is: array ('id' => 0, 'isConnected' => false);
	 */
	private static array $__classArgs = [];

	/** @var DataObjectMapping[] per class */
	private static array $_classORM = [];

	/** @var DataTable[]|DataSet[] per class */
	private static array $__classDataSet = [];
	#endregion

	#region Methods
	#region Class-related methods
	/**
	 * Returns the property names that their values will be passed (in order) to the class's constructor in order to create a new instance of this class.
	 * The associative array holds property names in array's keys and their default value in array's values.
	 *
	 * Example return value is: array ('id' => 0, 'isConnected' => false);
	 *
	 * @return array
	 */
	public final function __classArgs(): ?array { return self::$__classArgs[static::class] ?? null; }

	/**
	 * Returns the current version of the class's signature. Used to store the version number at the time an instantiated objects gets converted into a ODM document.
	 *
	 * @return mixed
	 */
	public final function __classVersion() { return self::$__classVersion[static::class] ?? null; }

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
	public final function __classProperties(): array { return (property_exists(static::class, '__classProperties') && is_array(static::$__classProperties)) ? static::$__classProperties : []; }

	public final function __classGetProperty($property) {
		if (property_exists($this, $property))
			return $this->$property;

		return null;
	}

	public final function __classSetProperty($property, $value) {
		if (property_exists($this, $property)) {
			$this->$property = DataObjectFactory::create($value);
		}
	}

	/**
	 * Returns a hash array containing the object properties that are allowed to be serialized along with their values.
	 *
	 * @return array
	 */
	public final function __classStorableProperties(): array {
		$array = array ();
		$classProperties = $this->__classProperties();

		// We have to use Reflection to cycle through Iterators properties
		if ($this instanceof \Iterator) {
			try {
				$ref = new \ReflectionClass($this);
				$properties = $ref->getProperties();
			}
			catch (\Exception $e) {
				$properties = [];
			}

			foreach ($properties as $property) {
				if ($property->isStatic())
					continue;

				$propertyName = $property->getName();
				if ($propertyName == '_orm')
					continue;

				if (!Rule::isAllowedSt($property, $classProperties))
					continue;

				$value = $property->getValue();

				if (is_resource($value) || $value instanceof \Closure || $value instanceof \PDO || $value instanceof \PDOOCI\PDO)
					continue;

				$array[] = $propertyName;
			}
		}
		else {
			foreach ($this as $property => $value) {
				if (strlen($property) == 0 || $property == '_orm')
					continue;

				if (!Rule::isAllowedSt($property, $classProperties))
					continue;

				if (is_resource($value) || $value instanceof \Closure || $value instanceof \PDO || $value instanceof \PDOOCI\PDO)
					continue;

				$array[$property] = $value;
			}
		}

		return $array;
	}

	/**
	 * Returns an associative array with all property names and their values that should be stored in object-oriented databases.
	 *
	 * @param ?ODBMS $db (optional) The database driver to use to convert any non-IStorable object properties found (hierarchically) into native objects
	 *
	 * @return array
	 */
	public final function __classToArray(ODBMS $db = null): array {
		$ret = array (
			'__class'   => get_class($this),
			'__version' => $this->__classVersion()
		);
		$properties = static::__classStorableProperties();
		foreach ($properties as $property => $value) {
			if (is_object($value)) {
				if ($value instanceof IStorable) {
					$class = get_class($value);
					$version = $value->__classVersion();
					$value = $value->__classToArray($db);
					$value['__class'] = $class;
					$value['__version'] = $version;
				}
				elseif ($db instanceof ODBMS) {
					$value = $db->toNativeObj($value);
				}
			}

			$ret[$property] = $value;
		}

		return $ret;
	}

	/**
	 * Sets properties from the values found in the given associative array; usually retrieved from an object-oriented database.
	 *
	 * @param $array
	 *
	 * @return bool
	 */
	public final function __classFromArray($array): bool {
		foreach ($array as $property => $value) {
			if (is_array($value) && isset ($value['__class'])) {
				$this->$property = DataObjectFactory::create($value);
			}
			elseif (property_exists($this, $property)) {
				$this->$property = $value;
			}
		}

		return true;
	}

	/**
	 * Sets the property names that their values will be passed (in order) to the class's constructor in order to create a new instance of this class.
	 * The associative array holds property names in array's keys and their default value in array's values.
	 *
	 * Example return value is: array ('id' => 0, 'isConnected' => false);
	 *
	 * @param array $args
	 *
	 * @return array
	 */
	protected static final function classArgs($args): array { return self::$__classArgs[static::class] = $args; }

	/**
	 * Sets the current version of the class's signature. Used to store the version number at the time an instantiated objects gets converted into a ODM document.
	 *
	 * @param string $version
	 *
	 * @return string
	 */
	protected static final function classVersion($version): string { return self::$__classVersion[static::class] = $version; }

	/**
	 * Sets class's DataSet that is used to store and retrieve rows/objects
	 *
	 * @param DataTable|DataSet $ds
	 *
	 * @return DataTable|DataSet
	 */
	protected static final function classDataSet(DataTable|DataSet $ds): DataTable|DataSet { return self::$__classDataSet[static::class] = $ds; }
	#endregion

	#region ORM-related methods
	public static final function ormSt(): DataObjectMapping {
		if (!isset(self::$_classORM[static::class]) || self::$_classORM[static::class] == null) {
			// Try to retrieve ORM info from cache
			// self::$_classORM[static::class] = DataObjectMapping::loadFromCache(static::class);
			// if (self::$_classORM[static::class] == null) {
				self::$_classORM[static::class] = static::onORM();

				// If ORM info was generated, cache it for future reference and performance purposes
				// if (self::$_classORM[static::class] instanceof DataObjectMapping) {
					// TODO: Disable temporarily ORM caching to allow development
					// Cache::store (static::$_classORM);
				// }
			// }
		}

		return self::$_classORM[static::class];
	}

	/**
	 * Returns the ORM information that is available for this class
	 */
	public function orm(): DataObjectMapping {
		if (!isset($this->_orm)) {
			$this->_orm = clone static::ormSt();
			$this->_orm->object($this);
		}

		return $this->_orm;
	}

	/** Called the first time ORM information is requested in the class and its cache is either not present or expired. Should be overridden by classes to generate and return real data/object mapping information. */
	protected static function onORM(): ?DataObjectMapping { return null; }
	#endregion

	#region Database-related methods
	#region Action methods
	/** Performs storage to the database */
	public function save(): EventStatus|DataRowValidationEventStatus {
		$row = $this->orm()->row();

		// Synchronize object properties with the internal DataRow
		$this->orm()->synchronize();

		switch ($row->getState()) {
			case DataRow::StateAdded:
				$rowAction = DataRow::ActionInsert;
				break;
			case DataRow::StateModified:
				$rowAction = DataRow::ActionUpdate;
				break;
			case DataRow::StateDeleted:
				// Clean the storage cache from the object being deleted
				if ($this instanceof ICacheable)
					$this->expireCache();

				// Forward to delete() method instead
				return $this->delete();
			default:
				$rowAction = DataRow::ActionNone;
		}

		// Begin transactions on all connections
		$dataSet = $this->orm()->dataSet();
		if ($dataSet instanceof DataSet)
			$dataSet->connections()->beginTransaction();
		else
			$dataSet->db()->beginTransaction();

		$args = new DataRowSaveEventArgs($row, $rowAction);
		if ($this instanceof IHookable)
			$this->trigger(DataRow::EventOnSaving, $args);

		$this->onSaving($args);

		// Validate object
		$status = $this->validate();
		if ($status->isError()) {
			return $status;
		}

		$status = $this->onSave($args);

		if ($status->isOK()) {
			// Commit transactions on all connections
			if ($dataSet instanceof DataSet)
				$dataSet->connections()->commit();
			else
				$dataSet->db()->commit();

			// Synchronize object properties with the internal DataRow
			// in case any auto-increment field or database triggers changed values during save
			$this->orm()->synchronize(DataRow::SourceDatabase);

			// Reset the "changed" flag
			$this->_hasChanged = false;

			if ($this instanceof ICacheable)
				Cache::store($this);

			$args = new DataRowSaveEventArgs($row, $rowAction);
			if ($this instanceof IHookable)
				$this->trigger(DataRow::EventOnSaved, $args);

			$this->onSaved($args);
		}
		else {
			// Rollback transactions on all connections
			if ($dataSet instanceof DataSet)
				$dataSet->connections()->rollback();
			else
				$dataSet->db()->rollback();
		}

		return $status;
	}

	/** Deletes the object from the database */
	public final function delete(): EventStatus {
		$row = $this->orm()->row();

		// Begin transactions on all connections
		$dataSet = $this->orm()->dataSet();
		if ($dataSet instanceof DataSet) {
			$dataSet->connections()->beginTransaction();
		}
		else {
			$dataSet->db()->beginTransaction();
		}

		$args = new DataRowSaveEventArgs($row, DataRow::ActionDelete);
		$status = $this->onDeleting($args);

		if ($status->isOK() && $this instanceof IHookable) {
			$listeners = $this->trigger(DataRow::EventOnDeleting, $args);
			foreach ($listeners as $st) {
				if ($st->isError() && $st->isHandled) {
					// Block the deletion execution if a listener returned an error
					$status = $st;
					break;
				}
			}
		}

		if ($status->isOK())
			$status = $this->onDelete($args);

		if ($status->isOK()) {
			// Commit transactions on all connections
			if ($dataSet instanceof DataSet) {
				$dataSet->connections()->commit();
			}
			else {
				$dataSet->db()->commit();
			}

			// Reset the "changed" flag
			$this->_hasChanged = false;
		}
		else {
			// Rollback transactions on all connections
			if ($dataSet instanceof DataSet) {
				$dataSet->connections()->rollback();
			}
			else {
				$dataSet->db()->rollback();
			}
		}

		$this->onDeleted($args);

		return $status;
	}

	/** Validates object's properties */
	public final function validate(): DataRowValidationEventStatus {
		return $this->onValidate();
	}
	#endregion

	#region Event methods
	/**
	 * Performs the actual storage of the object to the database.
	 * Note: Object deletions should be handled by onDelete() method instead.
	 *
	 * @return EventStatus
	 * @var DataRowSaveEventArgs $args
	 */
	protected function onSave(DataRowSaveEventArgs $args): EventStatus {
		$row = $args->row;
		$state = $row->getState();

		// Save all direct scalar properties based on ORM
		$status = $row->save();

		if ($status->isError())
			return $status;

		if ($state == DataRow::StateAdded) {
			#region Check for auto-increment columns and update the row accordingly
			$columns = $row->parent->columns->filter(function (DataColumn $c) { return $c->isActive && $c->isAutoIncrement; })->all();
			foreach ($columns as $col) {
				if ($col->isFake || !$col->isSaveable) continue;

				$property = $this->orm()->getProperty($col);
				if ($property instanceof DataObjectProperty) {
					$this->orm()->setPropertyValue($property, $row->getValue($col));
					break; // Only one column should be defined as auto-increment in a table anyway
				}
			}
			#endregion
		}

		#region Save all non-scalar properties individually (and recursively)
		$properties = static::__classStorableProperties();
		foreach ($properties as $property => $value) {
			unset ($status);

			if (is_scalar($value))
				continue;

			$prop = $this->orm()->getProperty($property);
			if ($prop instanceof DataObjectProperty)
				$property = $prop;
			elseif ($prop === null && $value === null)
				continue;

			$args = new DataObjectPropertySaveEventArgs($this, $property, $value);
			if ($this instanceof IHookable) {
				$statuses = $this->trigger(ORM::EventOnSaveProperty, $args);
				foreach ($statuses as $st) {
					if ($st->isError() || $st->isHandled) {
						$status = $st;
					}
				}
			}
			if (!isset ($status)) {
				$status = $this->onSaveProperty($args);
				if ($status == null) {
					if ($value instanceof IStorable) {
						$status = $value->save();
					}
				}
			}

			if ($status instanceof EventStatus && $status->isError())
				break;
		}
		#endregion

		if (!isset($status))
			$status = new EventStatus();

		return $status;
	}

	/**
	 * Called during save in order to allow extra preparations to be done before the object is saved to the database.
	 * If the returned status is error, the saving process will stop and transactions will be rolled back.
	 */
	protected function onSaving(DataRowSaveEventArgs $args): ?EventStatus { return new EventStatus(); }

	/** Called after the object is successfully saved to the database */
	protected function onSaved(DataRowSaveEventArgs $args) { }

	protected function onSaveProperty(DataObjectPropertySaveEventArgs $args) { return null; }

	/** Called during validation of the object's properties */
	protected function onValidate(): DataRowValidationEventStatus {
		return $this->orm()->row()->validate();
	}

	/** Performs the actual deletion of the object from the database */
	protected function onDelete(DataRowSaveEventArgs $args): EventStatus {
		$row = $args->row;

		// Mark the row as deleted
		$status = $row->delete();
		if ($status->isError())
			return $status;

		// Save row to apply deletion
		return $row->save();
	}

	/**
	 * Called during deletion in order to allow extra preparations to be done before the object is deleted from the database.
	 * If the returned status is error, the deletion process will stop and transactions will be rolled back.
	 */
	protected function onDeleting(DataRowSaveEventArgs $args): ?EventStatus { return new EventStatus(); }

	/** Called after the object is successfully deleted from the database */
	protected function onDeleted(DataRowSaveEventArgs $args) { }
	#endregion
	#endregion

	#region Object-related methods
	public function hasChanged(): bool {
		return $this->orm()->hasChanged() || $this->_hasChanged;
	}
	#endregion

	#region Magic methods
	public function __sleep() {
		return array_keys($this->__classStorableProperties());
	}
	#endregion
	#endregion
}
