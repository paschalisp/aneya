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

use aneya\Core\ApplicationError;
use aneya\Core\CMS;
use aneya\Core\CoreObject;
use aneya\Core\Data\ORM\DataObjectProperty;
use aneya\Core\Encrypt;
use aneya\Core\EventArgs;
use aneya\Core\EventStatus;
use aneya\Core\I18N\Locale;
use aneya\Core\IStorable;
use aneya\Core\KeyValue;
use aneya\Core\KeyValueCollection;
use aneya\Core\StateChangedEventArgs;
use aneya\Core\Utils\DateUtils;
use aneya\Core\Utils\JsonUtils;

class DataRow extends CoreObject implements IFilterable, \JsonSerializable {
	#region Constants
	#region States
	/** No state is defined */
	const StateNone = 0;
	/** Row was added to the collection and does not exist in database */
	const StateAdded = 1;
	/** Row exists in database and is unchanged */
	const StateUnchanged = 2;
	/** Row exists in database and is modified */
	const StateModified = 3;
	/** Row exists in database and is marked for deletion */
	const StateDeleted = 9;
	/** Row was deleted from database */
	const StatePurged = 10;
	#endregion

	#region Values Source
	/** Row's values were retrieved from memory */
	const SourceInMemory = 'M';
	/** Row's values were retrieved from its bound object */
	const SourceObject = 'O';
	/** Row's values were retrieved from database */
	const SourceDatabase = 'D';
	#endregion

	#region Actions
	/** No action should take place in the database */
	const ActionNone = '-';
	/** Add a new row into the database */
	const ActionInsert = 'I';
	/** Update an existing row in the database */
	const ActionUpdate = 'U';
	/** Delete an existing row from the database */
	const ActionDelete = 'D';
	#endregion

	#region Events
	/**
	 * Triggered when a row's value is being changed, allowing listeners to change the final value.
	 * In order to change the value that is going to be assigned, listeners should flag the return status as handled and assign the new value to the 'data' property of EventStatus.
	 * Passes a DataRowValueChangedEventArgs argument on listeners.
	 */
	const EventOnValueChanging = 'OnValueChanging';
	/**
	 * Triggered when a row's value has been changed.
	 * Passes a DataRowValueChangedEventArgs argument on listeners.
	 */
	const EventOnValueChanged = 'OnValueChanged';
	/** Triggered when the row's state has been changed */
	const EventOnStateChanged = 'OnStateChanged';
	/**
	 * Triggered just before the row is saved.
	 * Passes a DataRowSaveEventArgs argument on listeners.
	 */
	const EventOnSaving = 'OnSaving';
	/**
	 * Triggered when the row is being saved.
	 * Is used to let a listeners bypass the default saving mechanism and implement their own saving function.
	 * Passes a DataRowSaveEventArgs argument on listeners.
	 */
	const EventOnSave = 'OnSave';
	/**
	 * Triggered when the row has been saved successfully.
	 * Passes a DataRowSaveEventArgs argument on listeners.
	 */
	const EventOnSaved = 'OnSaved';
	/**
	 * Triggered just before the row is being marked as deleted (triggered only for rows that exist in database)
	 * It is used to let listeners block the deletion of the record if necessary.
	 * Passes a DataRowSaveEventArgs argument on listeners.
	 */
	const EventOnDeleting = 'OnDeleting';
	/**
	 * Triggered just before the row is validated for errors.
	 * Passes a DataRowValidationEventArgs argument on listeners.
	 */
	const EventOnValidating = 'OnValidating';
	/**
	 * Triggered when the row is being validated for errors.
	 * Is used to let a listeners bypass the default validation mechanism and implement their own validating function.
	 * Passes a DataRowValidationEventArgs argument on listeners.
	 */
	const EventOnValidate = 'OnValidate';
	/**
	 * Triggered when the row has been validated.
	 * Passes a DataRowValidationEventArgs argument on listeners.
	 */
	const EventOnValidated = 'OnValidated';
	/** Triggered when an IDataDelegateObject has been delegated to control row's functionality */
	const EventOnDelegate = 'OnDelegate';
	#endregion
	#endregion

	#region Properties
	#region Public properties
	public DataTable $parent;
	/** @var ?DataRow If DataRow's parent table is child of another table (one-to-many relationships), parentRow property points to the parent table's row that is linked with this row instance */
	public ?DataRow $parentRow = null;

	/** @var DataRowValidationEventStatus Row's validation result status(available if row's validation is called */
	public DataRowValidationEventStatus $status;

	/** @var bool */
	public bool $isSelected = false;

	/** @var string Indicates if initial values were set by retrieving the original values in database or set from a memory variable or object */
	public string $source = self::SourceInMemory;

	/** @var bool If true, values set in the row will not be automatically set in the mapped object */
	public bool $suspendObjectSync = false;
	#endregion

	#region Protected properties
	/** @var array */
	protected array $_originalValues = [];
	protected array $_values = [];

	/** @var array */
	protected array $_valuesIsSet = [];

	/** @var int Row's state. Valid values are DataRow::State* constants */
	protected int $_state = self::StateNone;

	/** @var bool Indicates whether the row has been validated for errors */
	protected bool $_isValidated = false;

	/** @var ?object Delegates all the database storage functionality to the associated object, along with any custom actions */
	protected ?object $_object = null;
	#endregion
	#endregion

	#region Constructor
	/**
	 * @param array|IStorable $values
	 * @param DataTable $parent
	 * @param int $state
	 */
	public function __construct($values, DataTable $parent, int $state = self::StateUnchanged) {
		$this->parent = $parent;
		$this->_state = $state;

		$this->status = new DataRowValidationEventStatus ();

		$_columns = [];
		$emptyValues = [];
		foreach ($parent->columns->all() as $c) {
			$emptyValues[$c->tag] = $this->_originalValues[$c->tag] = $this->_values[$c->tag] = null;
			$this->_valuesIsSet[$c->tag] = $state == self::StateUnchanged;

			// Store for later usage and gain some performance
			$_columns[$c->tag] = $c;
		}

		$lang = CMS::translator()->currentLanguage()->code;

		if ($values instanceof IStorable) {
			foreach ($values as $key => $value) {
				/** @var DataColumn $column */
				$column = $_columns[$key] ?? null;
				if (!$column)
					continue;

				// Ensure values are get stored at the correct data type
				$value = static::convertValue($column, $value);

				if ($column != null && $column->isMultilingual) {
					if (is_array($value)) {
						$this->_originalValues[$key] = $this->_values[$key] = $value;
					}
					else {
						$this->_originalValues[$key][$lang] = $this->_values[$key][$lang] = $value;
					}
				}
				else {
					$this->_originalValues[$key] = $this->_values[$key] = $value;
				}

				$this->_valuesIsSet[$key] = true;
			}

			$this->object($values);
		}
		elseif (is_array($values) && count($values) > 0) {
			// If argument is an associative array, treat values accordingly
			if (JsonUtils::isAssociativeArray($values)) {
				foreach ($values as $key => $value) {
					/** @var DataColumn $column */
					$column = $_columns[$key] ?? null;
					if (!$column)
						continue;

					// Ensure values are get stored at the correct data type
					$value = static::convertValue($column, $value);

					if ($column != null && $column->isMultilingual) {
						if (is_array($value)) {
							$this->_originalValues[$key] = $this->_values[$key] = $value;
						}
						else {
							$this->_originalValues[$key][$lang] = $this->_values[$key][$lang] = $value;
						}
					}
					else {
						$this->_originalValues[$key] = $this->_values[$key] = $value;
					}

					$this->_valuesIsSet[$key] = true;
				}
			}
			// Argument is a numeric array, hence columns argument is mandatory
			else {
				$maxC = $parent->columns->count();
				$maxV = count($values);
				$max = min($maxC, $maxV);
				for ($num = 0; $num < $max; $num++) {
					/** @var DataColumn $column */
					$column = $parent->columns->itemAt($num);

					// Ensure values are get stored at the correct data type
					$values[$num] = static::convertValue($column, $values[$num]);

					if ($column->isMultilingual) {
						if (is_array($values[$num])) {
							$this->_originalValues[$column->name] = $this->_values[$column->name] = $values[$num];
						}
						else {
							$this->_originalValues[$column->name][$lang] = $this->_values[$column->name][$lang] = $values[$num];
						}
					}
					$this->_valuesIsSet[$column->name] = true;
				}
			}
		}

		// If record is new, empty original values back to null
		if ($state == self::StateAdded) {
			$this->_originalValues = $emptyValues;
		}

		$this->hooks()->register([self::EventOnValueChanged, self::EventOnStateChanged]);
	}
	#endregion

	#region Methods
	#region Data methods
	/**
	 * Returns true if the row is valid depending on any validation criteria have been set on the columns.
	 * In case of validation errors, it returns an array with all error statuses
	 *
	 * @return DataRowValidationEventStatus
	 */
	public final function validate(): DataRowValidationEventStatus {
		$isHandled = false;

		#region reset row's error status
		$this->status->isPositive = true;
		$this->status->message = $this->status->debugMessage = '';
		$this->status->code = $this->status->data = null;
		$this->status->isHandled = false;
		$this->status->errors->clear();
		#endregion

		$args = new DataRowValidationEventArgs ($this, null, $this);

		$this->trigger(self::EventOnValidating, $args);

		/** @var DataRowValidationEventStatus[] $listeners */
		$listeners = $this->trigger(self::EventOnValidate, $args);
		foreach ($listeners as $listener) {
			if ($listener == null || $listener->isOK())
				continue;

			$this->status->isPositive = false;
			$this->status->errors->addRange($listener->errors->all());

			if ($listener->isHandled) {
				$isHandled = true;
				break;
			}
		}
		if (!$isHandled) {
			$status = $this->onValidate($args);
			if ($status->isError()) {
				$this->status->isPositive = false;
				$this->status->errors->addRange($status->errors->all());
			}
		}

		// Flag row as validated
		$this->_isValidated = true;

		$this->trigger(self::EventOnValidated, $args);

		return $this->status;
	}

	/** Saves row's changes back to the database */
	public final function save(): EventStatus {

		switch ($this->_state) {
			case self::StateAdded:
				$action = self::ActionInsert;
				break;
			case self::StateModified:
				$action = self::ActionUpdate;
				break;
			case self::StateDeleted:
				$action = self::ActionDelete;
				break;
			default:
				$action = self::ActionNone;
		}

		// Only needed if row has changed
		if ($hasChanged = $this->hasChanged()) {
			// Trigger OnSaving event to allow any last-minute changes to be applied on the DataRow or to cancel the procedure
			$triggers = $this->trigger(self::EventOnSaving, new DataRowSaveEventArgs ($this, $action));
			foreach ($triggers as $status) {
				if ($status->isError()) {
					return $status;
				}
				if ($status->isHandled) {
					break;
				}
			}

			// Synchronize with mapped object before validation and storage
			if ($this->_object instanceof IStorable) {
				$this->syncObject();
			}

			// No need to validate records being deleted
			if ($action != self::ActionDelete) {
				// Validate row if not already
				if (!$this->_isValidated)
					$this->validate();

				// If row has errors, return
				if ($this->hasErrors())
					return new EventStatus (false, $this->status->errors->toString('<br />'), -1);
			}
		}

		$status = new EventStatus();
		$status->isHandled = false;

		// Start database transaction
		$connections = DatabaseCollection::fromDataRow($this);
		$connections->beginTransaction();

		$listeners = $this->trigger(self::EventOnSave, new DataRowSaveEventArgs ($this, $action));
		foreach ($listeners as $st) {
			if ($st->isError()) {
				$connections->rollback();
				return $st;
			}
			if ($st->isHandled) {
				$status = $st;
				break;
			}
		}

		if (!$status->isHandled) {
			if ($hasChanged)
				$status = $this->onSave();

			// Save all children rows, if any
			if ($status->isOK()) {
				$status = $this->saveChildRows();
			}
		}

		if ($status->isError()) {
			$connections->rollback();
			if (CMS::env()->debugging && strlen($status->debugMessage) > 0)
				CMS::app()->log(new ApplicationError($status->debugMessage));
		}
		else {
			$connections->commit();

			if ($hasChanged) {
				// Set state to either unchanged or purged
				$this->trigger(self::EventOnSaved, new DataRowSaveEventArgs ($this, $action));
				if ($this->_state == self::StateDeleted) {
					$this->setState(self::StatePurged);
				}
				else {
					$this->setState(self::StateUnchanged);
				}
			}
		}

		return $status;
	}

	protected final function saveChildRows(): EventStatus {
		$ret = new EventStatus();

		foreach ($this->parent->children->all() as $rel) {
			$subFilters = new DataFilterCollection();
			foreach ($rel->getLinks() as $l) {
				/** @var DataColumn $parentCol */
				$parentCol = $l[0];
				$value = $this->getValue($parentCol);
				/** @var DataColumn $childCol */
				$childCol = $l[1];

				$subFilters->add(new DataFilter($childCol, ($value == null) ? DataFilter::IsNull : DataFilter::Equals, $value));
			}
			$childRows = $rel->child->rows->match($subFilters);
			foreach ($childRows->all() as $row) {
				$ret = $row->save();
				if ($ret->isError()) {
					break;
				}
			}

			if (isset ($ret) && $ret->isError()) {
				break;
			}
		}

		return $ret;
	}

	/**
	 * Marks the record for deletion from the database.
	 * There won't be any affection in the database until DataRow::save() is called.
	 *
	 * @return EventStatus
	 */
	public final function delete(): EventStatus {
		switch ($this->_state) {
			case self::StateAdded:
				$state = self::StateNone;
				break;
			case self::StatePurged:
				$state = self::StatePurged;
				break;
			case self::StateNone:
				$state = self::StateNone;
				break;
			default:
				$state = self::StateDeleted;

				// Allow listeners to cancel the procedure
				$statuses = $this->trigger(self::EventOnDeleting, new EventArgs ($this));
				foreach ($statuses as $status) {
					if ($status->isError() && $status->isHandled) {
						return $status;
					}
				}
		}

		$this->setState($state);

		return new EventStatus();
	}
	#endregion

	#region Event methods
	protected function onValidate(DataRowValidationEventArgs $args): DataRowValidationEventStatus {
		$status = new DataRowValidationEventStatus ();

		// Trigger all columns' validate event
		foreach ($this->parent->columns->all() as $c) {
			if ($c->isMultilingual) {
				$st = null;
				$languages = CMS::translator()->languages();
				foreach ($languages as $lang) {
					$st = $c->validate(new DataRowValidationEventArgs($this, $this->getValue($c, $lang->code), $this));
					// If there's at least one translation valid, pass validation
					if ($st->isOK()) {
						break;
					}

					$st->message .= ' ( ' . CMS::translator()->translate('in all languages', 'cms') . ')';
				}
			}
			else {
				$st = $c->validate(new DataRowValidationEventArgs($this, $this->getValue($c), $this));
			}
			if ($st->isOK())
				continue;

			$status->isPositive = false;
			$status->errors->addRange($st->errors->all());
		}

		return $status;
	}

	/** Used internally and implements the saving mechanism of row's changes to the database */
	public function onSave(): EventStatus {
		if ($this->parent instanceof DataSet) {
			if ($this->parent->relations->count() > 0)
				$tables = $this->parent->sortedTables();        // Omit non-saveable tables
			else
				$tables = $this->parent->tables;

			// Trigger all columns' OnSaving event
			foreach ($this->parent->columns->all() as $c) {
				$statuses = $c->trigger(DataColumn::EventOnSaving, new DataRowValidationEventArgs($this, $this->getValue($c), $this));
				foreach ($statuses as $status)
					if ($status->isHandled && $status->isError())
						return $status;
			}

			// Split columns into tables and save each table separately
			foreach ($tables->all() as $tbl) {
				$row = clone $this;
				$row->parent = $tbl;
				$status = $row->onSave();
				if ($status->isError())
					return $status;

				if (in_array($this->_state, array (self::StateAdded, self::StateModified))) {
					#region Set back any auto-increment values from cloned row
					$aiCols = $tbl->columns->filter(function (DataColumn $c) { return $c->isActive && $c->isAutoIncrement; })->all();
					foreach ($aiCols as $c) {
						$this->setValue($c, $row->getValue($c->tag));
					}
					#endregion

					#region Assign parent column values to child columns in the relation
					$relations = $this->parent->children->getByParent($tbl);
					foreach ($relations as $r) {
						foreach ($r->getLinks() as $link) {
							/** @var DataColumn $pCol */
							$pCol = $link[0];
							/** @var DataColumn $cCol */
							$cCol = $link[1];

							// We are interested in non-increment columns only.
							// Auto-increment columns are already assigned with the new value.
							if ($pCol->isAutoIncrement)
								continue;

							// We are interested in relations between different tables.
							// Same table relations are usually hierarchical and values of parent columns
							// should be set explicitly depending on the business logic.
							if ($pCol->table === $cCol->table)
								continue;

							$pValue = $row->getValue($pCol->tag);
							$cRows = $this->childRows($cCol->table);

							foreach ($cRows->all() as $cRow) {
								$cRow->setValue($pCol, $pValue);
							}
						}
					}
					#endregion
				}
			}

			return new EventStatus ();
		}
		else {
			return $this->parent->db()->save($this);
		}
	}
	#endregion

	#region Object mapping methods
	/**
	 * Gets/Binds the IStorable object to synchronize and delegate database storage operations & custom actions
	 *
	 * @param object|null $object |null $object     (optional)
	 * @param string $syncSource (optional) Valid values are DataRow::Source* constants
	 *
	 * @return object|null
	 */
	public function object(object $object = null, string $syncSource = DataRow::SourceObject): ?object {
		// Bind the object & call its bind method for further custom operations
		if ($object !== null && $object !== $this->_object) {
			if ($this->_object instanceof IStorable) {
				if ($this->_object->orm()->dataSet() !== $this->parent) {
					throw new \InvalidArgumentException ('Storable object\'s class DataSet is not the same with row\'s DataSet');
				}
				$this->_object->orm()->row($this);
			}
			$this->_object = $object;
			$this->source = $syncSource;

			$this->trigger(self::EventOnDelegate, $args = new DataRowDelegateEventArgs ($this, $this->_object, $this));
		}

		// If object is null, generate a new object
		if ($this->_object === null) {
			$class = $this->parent->getMappedClass();
			$obj = new $class();
			$this->object($obj, DataRow::SourceDatabase);

			if ($obj instanceof IStorable) {
				$obj->orm()->row($this);
			}

			$this->syncObject();
		}

		return $this->_object;
	}

	/**
	 * Synchronizes record's value with the delegated object's properties.
	 * If no source parameter was given it uses the DataRow's source value to determine the source
	 *
	 * @param string|null $source Valid values are DataRow::SourceObject|SourceDatabase constants
	 *
	 * @return void
	 */
	public function syncObject(string $source = null) {
		if (!is_object($this->_object))
			return;

		if ($source == null)
			$source = $this->source;

		// If object is IStorable, let the ORM make the synchronization
		if ($this->_object instanceof IStorable) {
			$this->_object->orm()->synchronize($source);
			return;
		}

		if ($source == self::SourceObject) {
			foreach ($this->_values as $key => $value) {
				$value = $this->getObjProperty($key);
				$this->setValue($key, $value);
			}
		}
		elseif ($source == self::SourceDatabase) {
			$lang = CMS::translator()->currentLanguage()->code;
			foreach ($this->_values as $key => $value) {
				// Set object properties current language's value only
				if ($this->columnAt($key)->isMultilingual) {
					$value = $value[$lang];
				}
				$this->setObjProperty($key, $value);
			}
		}
	}

	/**
	 * Sets a record object's property with the given value.
	 * It traverses all sub-property hierarchy
	 *
	 * @param string $property
	 * @param mixed  $value
	 *
	 * @return bool
	 */
	protected function setObjProperty(string $property, $value): bool {
		#region Search through all sub-properties hierarchy to properly set the value
		$hierarchy = explode('.', $property);
		if (($cnt = count($hierarchy)) == 1) {
			try {
				$this->_object->$property = $value;
			}
			catch (\Exception $e) {}
		}
		else {
			$obj = $this->_object;
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
			catch (\Exception $e) {}
		}
		#endregion

		return true;
	}

	/**
	 * Returns the value of a record object's property, traversing through any sub-properties, if necessary.
	 *
	 * @param string $property
	 *
	 * @return mixed
	 */
	protected function getObjProperty(string $property) {
		// Search through all sub-properties hierarchy to find for value
		$hierarchy = explode('.', $property);
		$obj = $this->_object;
		foreach ($hierarchy as $prop) {
			if (!isset ($obj->$prop)) {
				return null;
			}

			$obj = $obj->$prop;
		}

		return $obj;
	}
	#endregion

	#region Filtering methods
	/**
	 * Returns true if the row matches the given filter(s)
	 *
	 * @param DataFilterCollection|DataFilter[]|DataFilter $filters
	 *
	 * @return bool
	 */
	public function match($filters): bool {
		if ($filters instanceof DataFilterCollection) {
			if ($filters->operand == DataFilterCollection::OperandAnd) {
				foreach ($filters as $filter) {
					if (!$this->match(($filter)))
						return false;
				}

				return true;
			}
			elseif ($filters->operand == DataFilterCollection::OperandOr) {
				foreach ($filters as $filter) {
					if ($this->match(($filter)))
						return true;
				}
			}

			return false;
		}
		elseif ($filters instanceof DataFilter) {
			$f = $filters;
			$value = $this->getValue($f->column);
			$mValue = ($f->value instanceof DataColumn) ? $this->getValue($f->value) : $f->value;

			// Make string matching case-insensitive
			if ($f->column->dataType == DataColumn::DataTypeString || $f->column->dataType == DataColumn::DataTypeChar) {
				$value = strtolower($value);
				$mValue = strtolower($mValue);
			}

			switch ($f->condition) {
				case DataFilter::NoFilter        :
					return true;
				case DataFilter::FalseFilter    :
					return false;
				case DataFilter::Equals            :
					return ($value == $mValue);
				case DataFilter::NotEqual        :
					return ($value != $mValue);
				case DataFilter::LessThan        :
					return ($value < $mValue);
				case DataFilter::LessOrEqual    :
					return ($value <= $mValue);
				case DataFilter::GreaterThan    :
					return ($value > $mValue);
				case DataFilter::GreaterOrEqual    :
					return ($value >= $mValue);
				case DataFilter::Between        :
					return ($value >= $mValue[0] && $value <= $mValue[1]);
				case DataFilter::IsNull            :
					return (is_null($value));
				case DataFilter::NotNull        :
					return (!is_null($value));
				case DataFilter::IsEmpty        :
					return (empty ($value));
				case DataFilter::NotEmpty        :
					return (!empty ($value));
				case DataFilter::StartsWith        :
					return ($mValue == '' || stripos((string)$value, (string)$mValue) === 0);
				case DataFilter::EndsWith        :
					return ($mValue == '' || substr((string)$value, -strlen((string)$mValue)) == (string)$mValue);
				case DataFilter::NotStartWith    :
					return (stripos((string)$value, (string)$mValue) !== 0);
				case DataFilter::NotEndWith        :
					return (substr((string)$value, -strlen((string)$mValue)) != (string)$mValue);
				case DataFilter::Contains        :
					return (stripos((string)$value, (string)$mValue) !== false);
				case DataFilter::NotContain        :
					return (stripos((string)$value, (string)$mValue) === false);
				case DataFilter::InList            :
					return (in_array($value, $mValue));
				case DataFilter::NotInList        :
					return (!in_array($value, $mValue));
				case DataFilter::InSet            :
					return (in_array($mValue, explode(',', $value)));
				case DataFilter::NotInSet        :
					return (!in_array($mValue, explode(',', $value)));
				default:
					CMS::logger()->warning("Condition $f->condition not supported");
					return false;
			}
		}
		elseif (is_array($filters) && count($filters) > 0) {
			$collection = new DataFilterCollection();
			$collection->operand = DataFilterCollection::OperandAnd;
			$collection->addRange($filters);

			return $this->match($collection);
		}
		elseif ($filters == null)
			return true;

		return false;
	}
	#endregion

	#region Value methods
	/**
	 * Returns the stored value of the given column in the row.
	 * If the column is multilingual, unless there is a language code specified as a second argument, it returns the column's value of the current language code.
	 *
	 * @param DataColumn|string|int $column
	 * @param ?string               $langCode If column is multilingual and this argument contains a language code, then the returned value will be the column's value for the given language code.
	 *
	 * @return mixed
	 */
	public function getValue(DataColumn|string|int $column, string $langCode = null): mixed {
		if (is_int($column)) {
			$column = $this->columnAt($column);
		}
		elseif (is_string($column)) {
			$column = $this->columnAt($column);
		}

		if ($column instanceof DataColumn) {
			if (isset ($this->_values[$column->tag])) {
				$value = $this->_values[$column->tag];

				// If column is multilingual, either return the value of the specified (if provided) or current language code
				if ($column->isMultilingual) {
					$lang = (isset ($langCode)) ? $langCode : CMS::translator()->currentLanguage()->code;
					if (isset ($value[$lang])) {
						$value = $value[$lang];
					}
					else {
						return null;
					}
				}

				if ($value === null)
					return null;

				switch ($column->dataType) {
					case DataColumn::DataTypeInteger:
						return is_numeric($value) ? (int)$value : null;
					case DataColumn::DataTypeFloat:
						return is_numeric($value) ? (float)$value : null;
					case DataColumn::DataTypeBoolean:
						return (bool)$value;
					case DataColumn::DataTypeArray:
						switch ($column->table->db()->getDriverType()) {
							case Database::PostgreSQL:
								return (is_string($value))
									? array_filter(explode(',', substr($value, 1, -1)), function (string $val) { return strlen(trim($val)) > 0; } )
									: $value;
							default:
								return (is_string($value))
									? explode(',', $value)
									: $value;
						}
					default:
						return $value;
				}
			}
			elseif (!$this->parent->columns->contains($column)) {
				foreach ($this->parent->children->all() as $rel) {
					if ($rel->child->columns->contains($column)) {
						$value = [];
						$rows = $this->childRows($rel->child);
						foreach ($rows as $row) {
							$value[] = $row->getValue($column);
						}

						return $value;
					}
				}
			}
		}

		return null;
	}

	/**
	 * Returns the originally stored value of the given column in the row prior to any changes.
	 * If the column is multilingual, unless there is a language code specified as a second argument, it returns the column's value of the current language code.
	 *
	 * @param DataColumn|string|int $column
	 * @param string|null $langCode If column is multilingual and this argument contains a language code, then the returned value will be the column's value for the given language code.
	 *
	 * @return mixed
	 */
	public function getOriginalValue($column, string $langCode = null) {
		if (is_int($column)) {
			$column = $this->columnAt($column);
		}
		elseif (is_string($column)) {
			$column = $this->columnAt($column);
		}

		if ($column instanceof DataColumn && isset ($this->_originalValues[$column->tag])) {
			$value = $this->_originalValues[$column->tag];

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
	 *
	 * @param DataColumn|string|int $column
	 *
	 * @return array
	 */
	public function getValueTr($column): ?array {
		if (is_int($column)) {
			$column = $this->columnAt($column);
		}
		elseif (is_string($column)) {
			$column = $this->columnAt($column);
		}

		if (!($column instanceof DataColumn))
			return null;

		if (!$column->isMultilingual)
			return null;

		if (isset ($this->_values[$column->tag]))
			return $this->_values[$column->tag];

		return null;
	}

	/**
	 * Retrieves all translations of all multilingual columns (or just the given, if provided) from the database and sets them directly as value
	 *
	 * @param ?DataColumn $column (optional)
	 *
	 * @return $this
	 */
	public function retrieveValuesTr(DataColumn $column = null): DataRow {
		/** @var DataColumn[] $allColumns Will contain all columns to be translated */
		$allColumns = [];

		if ($column instanceof DataColumn) {
			// Add only the given column to the retrieval
			$allColumns[] = $column;
		}
		else {
			// Add all multilingual columns to the retrieval
			$allColumns = $this->parent->columns->all(function (DataColumn $c) { return $c->isActive && $c->isMultilingual; });
		}

		#region Prepare translation table(s)
		/** @var DataTable[] $processedTables All original tables */
		$processedTables = [];

		foreach ($allColumns as $col) {
			if (in_array($col->table, $processedTables))
				continue;

			// Define a new (translation) table
			$table = clone $col->table;
			$table->columns = new DataColumnCollection(); // Ensure we don't overwrite original table's columns
			$table->columns->parent = $table;
			$table->rows = new DataRowCollection(); // Ensure we don't overwrite original table's records

			// Flag column's table as processed
			$processedTables[] = $col->table;

			$keyColumns = $col->table->columns->filter(function (DataColumn $c) { return $c->isActive && $c->isKey; })->all();
			$filters = new DataFilterCollection();
			foreach ($keyColumns as $c) {
				$value = $this->getValue($c);
				$condition = ($value == null) ? DataFilter::IsNull : DataFilter::Equals;

				// Clone column and attach it to the translation table,
				// so that the WHERE clause is built with the translation table's name
				$kCol = clone $c;
				$kCol->isMultilingual = false;
				$kCol->table = $table;

				$table->columns->add($kCol);
				$filters->add(new DataFilter($kCol, $condition, $value));
			}

			#region Add all multilingual columns that belong to the current translation table
			foreach ($allColumns as $c) {
				if ($c->table !== $col->table)
					continue;

				$cc = clone $c;
				$cc->table = $table;
				$cc->isMultilingual = false;
				$table->columns->add($cc);
			}
			#endregion

			// Add the language code column, which is necessary
			$table->columns->add(new DataColumn('language_code'));
			$table->name .= 'Tr';

			$table->retrieve($filters);

			#region Set translation values for all multilingual fields in the current translation table
			foreach ($allColumns as $c) {
				if (!$c->table == $col->table)
					continue;

				$values = [];

				foreach ($table->rows as $row)
					$values[$row->getValue('language_code')] = $row->getValue($c->tag);

				$this->setValue($c, $values);
			}
			#endregion
		}
		#endregion

		return $this;
	}

	/**
	 * @param DataColumn|string|int $column
	 * @param mixed                 $value
	 *
	 * @triggers OnValueChanged
	 */
	public function setValue($column, $value) {
		#region Store the column in the $column argument
		if (is_int($column)) {
			$idx = $column;
			$column = $this->columnAt($idx);
		}
		elseif (is_string($column)) {
			$column = $this->columnAt($column);
			$idx = $this->indexOf($column);
		}
		else {
			$idx = $this->indexOf($column);
		}

		if (!($column instanceof DataColumn)) {
			return;
		}
		#endregion

		#region Convert values of specific data types
		if ($column->isMultilingual && is_array($value)) {
			foreach ($value as $lang => $v) {
				$v = static::convertValue($column, $v);
				$value[$lang] = $v;
			}
		}
		else {
			$value = static::convertValue($column, $value);
		}
		#endregion

		#region Store the old value in $oldValue
		$lang = CMS::translator()->currentLanguage()->code;
		$oldValue = $this->_values[$column->tag];
		if ($column->isMultilingual) {
			if (is_array($value)) {
				if ($value == $oldValue) // Array operator returns true if both associative arrays have same key/value pairs
					return;
			}
			elseif ($value == $oldValue[$lang])
				return;
		}
		else {
			if ($value === $oldValue)
				return;
		}
		#endregion

		#region Reset any error statuses for this column
		$statuses = $this->status->get($column);
		foreach ($statuses as $status)
			$this->status->errors->remove($status);
		#endregion

		#region Raise the OnValueChanging event
		$statuses = $this->trigger(self::EventOnValueChanging, new DataRowValueChangedEventArgs ($this, $value, $oldValue, $column, $idx));
		foreach ($statuses as $status) {
			if ($status->isError())
				return;

			// Replace the value with the one that was passed by the listener
			if ($status->isHandled) {
				$value = $status->data;
				break;
			}
		}
		#endregion

		#region Validate new value
		$status = $column->validate(new DataRowValidationEventArgs($this, $value, $this));
		if ($status->isError())
			$this->status->errors->addRange($status->errors->all());
		#endregion

		#region Set the new value
		if ($column->isMultilingual) {
			if (is_array($value)) {
				$this->_values[$column->tag] = $value;
			}
			else {
				$this->_values[$column->tag][$lang] = $value;
			}
		}
		else {
			$this->_values[$column->tag] = $value;
		}
		#endregion

		#region Update mapped object's property, if any
		if ($this->_object instanceof IStorable && !$this->suspendObjectSync) {
			$property = $this->_object->orm()->getProperty($column);
			if ($property instanceof DataObjectProperty) {
				if ($column->isMultilingual && is_array($value))
					$this->_object->orm()->setPropertyValue($property, $value[$lang]);
				else
					$this->_object->orm()->setPropertyValue($property, $value);
			}
		}
		#endregion

		#region Set the value on any related columns
		if ($this->parent instanceof DataSet) {
			foreach ($this->parent->relations->getByParent($column->table) as $rel) {
				foreach ($rel->getLinks() as $l) {
					if ($l[0] != $column) {
						continue;
					}

					/** @var DataColumn $childCol */
					$childCol = $l[1];
					$this->setValue($childCol, $value);
				}
			}
		}
		#endregion

		#region Set the value on any associated children rows
		foreach ($this->parent->children->all() as $rel) {
			foreach ($rel->getLinks() as $l) {
				if ($l[0] != $column) {
					continue;
				}

				/** @var DataColumn $childCol */
				$childCol = $l[1];
				$childRows = $rel->child->rows->match(new DataFilter($childCol, ($oldValue == null) ? DataFilter::IsNull : DataFilter::Equals, $oldValue));
				foreach ($childRows->all() as $row) {
					$row->setValue($childCol, $value);
				}
			}
		}
		#endregion

		#region Raise the OnValueChanged event, unless it is the first time the value is set
		if ($this->_valuesIsSet[$column->tag]) {
			$this->trigger(self::EventOnValueChanged, new DataRowValueChangedEventArgs($this, $value, $oldValue, $column, $idx));
		}
		else {
			$this->_valuesIsSet[$column->tag] = true;
		}
		#endregion

		#region Change row's state
		if (in_array($this->_state, array (self::StateUnchanged, self::StateNone))) {
			$this->setState(self::StateModified);
		}
		#endregion

		// Flag row as not validated
		$this->_isValidated = false;
	}

	/**
	 * Bulk sets the values from the given DataRow or hash array
	 *
	 * @param DataRow|array|\stdClass $row
	 */
	public function bulkSetValues($row) {
		if ($row instanceof DataRow) {
			foreach ($row->parent->columns->all() as $col) {
				$this->setValue($col->tag, $row->getValue($col));
			}
		}
		elseif (is_array($row) || $row instanceof \stdClass) {
			foreach ($row as $col => $value) {
				$this->setValue($col, $value);
			}
		}
	}

	/**
	 * Returns all values that are set in the DataRow as a hash array
	 *
	 * @param string[]|null $columns If provided, returning array will contain only the given columns' values
	 *
	 * @return array
	 */
	public function bulkGetValues(array $columns = null): array {
		$values = [];

		if (!is_array($columns)) {
			foreach ($this->parent->columns->all() as $col)
				$values[$col->tag] = ($col->isMultilingual) ? $this->getValueTr($col) : $this->getValue($col);
		}
		else {
			foreach ($this->parent->columns->all() as $col) {
				if (!in_array($col->tag, $columns))
					continue;

				$values[$col->tag] = ($col->isMultilingual) ? $this->getValueTr($col) : $this->getValue($col);
			}
		}

		return $values;
	}

	/** Returns an encrypted hash string containing information regarding row's primary key column(s) and their value */
	public function getKeyHash(): string {
		$keyFields = $this->parent->columns->filter(function (DataColumn $c) { return $c->isActive && $c->isKey; })->all();
		$keyValues = array ();
		foreach ($keyFields as $k) {
			$keyValues[] = $k->tag . ':' . $this->getValue($k->tag);
		}
		$keyValues = implode("\n---\n", $keyValues);
		return Encrypt::encrypt($keyValues);
	}

	/** Parses the given hash string that was previously encrypted by DataRow::getKeyHash() and returns a KeyValueCollection containing primary column tags along with their values */
	public function parseKeyHash(string $keyHash): KeyValueCollection {
		$keyValues = Encrypt::decrypt($keyHash);
		$keyValues = explode("\n---\n", $keyValues);
		$c = new KeyValueCollection();
		foreach ($keyValues as $kv) {
			[$tag, $value] = explode(':', $kv, 2);
			$c->add(new KeyValue($tag, $value));
		}

		return $c;
	}

	/**
	 * Returns an encrypted hash string containing the values set in the row.
	 *
	 * @param bool $original If true, the original values will be returned instead of current values.
	 *
	 * @return string
	 */
	public function getValuesHash(bool $original = false): string {
		return base64_encode(gzcompress(Encrypt::encrypt(serialize($original ? $this->_originalValues : $this->_values))));
	}

	/**
	 * Returns true if column's value has changed
	 *
	 * @param DataColumn|string|int $column
	 *
	 * @return bool
	 */
	public function hasColumnChanged(DataColumn|string|int $column): bool {
		if (is_int($column)) {
			$column = $this->columnAt($column);
		}
		elseif (is_string($column)) {
			$column = $this->columnAt($column);
		}

		if ($column instanceof DataColumn && isset ($this->_values[$column->tag])) {
			return ($this->_values[$column->tag] != $this->_originalValues[$column->tag]);
		}

		return false;
	}

	/** Resets the row to the original values */
	public function reset(): static {
		foreach ($this->parent->columns->all() as $c) {
			$this->_values[$c->tag] = isset ($this->_originalValues[$c->tag]) ? $this->_originalValues[$c->tag] : null;
			$this->_valuesIsSet[$c->tag] = true;
			if ($this->_state == self::StateModified)
				$this->_state = self::StateUnchanged;
		}

		return $this;
	}

	/**
	 * Returns the given value converted if necessary to a value that is compatible to column's data type.
	 *
	 * @param DataColumn $column
	 * @param mixed      $value
	 *
	 * @return mixed
	 */
	public static function convertValue(DataColumn $column, $value) {
		if ($column->isMultilingual && is_array($value)) {
			$arr = array ();
			$languages = CMS::translator()->languages();
			foreach ($languages as $lang) {
				if (isset($value[$lang->code])) {
					$arr[$lang->code] = self::convertValue($column, $value[$lang->code]);
				}
				else {
					$arr[$lang->code] = null;
				}
			}

			return $arr;
		}
		else {
			switch ($column->dataType) {
				case DataColumn::DataTypeBoolean:
					// Keep null & boolean values as-is
					if ($value !== null && !is_bool($value)) {
						if (is_int($value)) {
							$value = $value !== 0;
						}
						elseif (is_string($value)) {
							// Auto-convert '1', 'true' and 'yes' text values as boolean true
							$value = strtolower($value);
							$value = in_array($value, ['1', 'true', 'yes']);
						}
						else {
							$value = (bool)$value;
						}
					}
					break;

				case DataColumn::DataTypeInteger:
					$value = ($value === null || $value === '') ? null : (is_numeric($value) ? (int)$value : $value);
					break;

				case DataColumn::DataTypeFloat:
					$value = ($value === null || $value === '') ? null : (is_numeric($value) ? (float)$value : $value);
					break;

				case DataColumn::DataTypeChar:
					$value = (string)$value;
					$value = ($value === null) ? null : (((string)$value === '') ? '' : (string)$value[0]);
					break;

				case DataColumn::DataTypeString:
					$value = ($value === null) ? null : (is_array($value) ? implode(',', $value) : (string)$value);
					break;

				case DataColumn::DataTypeDate:
				case DataColumn::DataTypeTime:
				case DataColumn::DataTypeDateTime:
					if ($value !== null && !($value instanceof \DateTime)) {
						$val = CMS::locale()->toDateObj($value,
														  $column->dataType == DataColumn::DataTypeDateTime ?
															  Locale::DateTime :
															  ($column->dataType == DataColumn::DataTypeTime ?
																  Locale::TimeOnly :
																  Locale::DateOnly
															  )
						);

						// If conversion failed, try Javascript date format
						if (!$val)
							$val = DateUtils::fromJsDate($value);

						$value = $val;
					}
					break;

				case DataColumn::DataTypeArray:
					if ($value === null) {
						if ($column->isRequired)
							$value = [];
					}
					elseif (is_string($value)) {
						if (strtolower($value) == 'null') {
							$value = ($column->isRequired) ? [] : null;
						}
						else {
							switch ($column->table->db()->getDriverType()) {
								case Database::PostgreSQL:
									$value = array_filter(explode(',', substr($value, 1, -1)), function (string $val) {
										return strlen(trim($val)) > 0;
									});
									break;
								default:
									$value = explode(',', $value);
							}
						}
					}
					elseif (!is_array($value)) {
						$value = [$value];
					}
					break;

				case DataColumn::DataTypeJson:
				case DataColumn::DataTypeObject:
					if ($value == null)
						break;

					if (is_array($value)) {
						// If array is associative, convert it into an object
						if ($value != [] && array_keys($value) !== range(0, count($value) - 1))
							$value = (object)$value;
					}
					elseif (!is_object($value)) {
						$val = JsonUtils::decode((string)$value);
						if ($val === null)
							CMS::logger()->warning(sprintf('Error converting DataRow value back to JSON object. Value: %s', $value));

						$value = $val;
					}
					break;

			}
		}

		return $value;
	}
	#endregion

	#region Row/SubRow methods
	/**
	 * Returns a newly instantiated DataRow-derived object with row state set to DataRow::StateAdded, already inserted into DataTable's rows collection.
	 *
	 * @param DataTable|string $dataTable
	 *
	 * @return DataRowCollection|null
	 */
	public function childRows($dataTable = null): ?DataRowCollection {
		if ($this->parent->children->count() == 0) {
			return null;
		}

		if ($dataTable === null) {                        // If no specific DataTable is provided, return all children rows
			$rows = new DataRowCollection();
			foreach ($this->parent->children->all() as $rel) {
				if ($rel->joinType == DataRelation::OneToMany || $rel->joinType == DataRelation::ManyToMany) {
					$filters = new DataFilterCollection();
					foreach ($rel->getLinks() as $l) {
						$pValue = $this->getValue($l[0]);
						if ($pValue === null) {
							$filters->add(new DataFilter($rel->child->columns->get($l[1]), DataFilter::IsNull));
						}
						else {
							$filters->add(new DataFilter($rel->child->columns->get($l[1]), DataFilter::Equals, $pValue));
						}
					}
					$rows->addRange($rel->child->rows->match($filters)->all());
				}
			}
			return $rows;
		}
		else {                                            // Else, return only given child DataTable's rows
			if (!($dataTable instanceof DataTable)) {
				foreach ($this->parent->children as $rel) {
					// BUG: If there are two tables with same name but come from different schema, it will associate to the first table matching the given name
					if ($rel->child->name == (string)$dataTable) {
						$dataTable = $rel->child;
						break;
					}
				}
			}

			if (!($dataTable instanceof DataTable)) {
				return null;
			}

			foreach ($this->parent->children as $rel) {
				if ($rel->child === $dataTable) {
					if ($rel->joinType == DataRelation::OneToMany || $rel->joinType == DataRelation::ManyToMany) {
						$filters = new DataFilterCollection();
						foreach ($rel->getLinks() as $l) {
							$pValue = $this->getValue($l[0]);
							if ($pValue === null) {
								$filters->add(new DataFilter($rel->child->columns->get($l[1]), DataFilter::IsNull));
							}
							else {
								$filters->add(new DataFilter($rel->child->columns->get($l[1]), DataFilter::Equals, $pValue));
							}
						}
						return $rel->child->rows->match($filters);
					}
				}
			}
		}

		return null;
	}

	/**
	 * Returns a newly instantiated DataRow-derived object with row state set to DataRow::StateAdded, already inserted into child DataTable's rows collection.
	 *
	 * @param DataTable|string $dataTable
	 * @param bool $addToCollection (true by default) Indicates if the new row should automatically be added to the DataTable's rows collection.
	 *
	 * @return DataRow
	 */
	public function newChildRow($dataTable, bool $addToCollection = true): DataRow {
		$rels = $this->parent->children->getByParent($this->parent);

		if (!($dataTable instanceof DataTable)) {
			foreach ($rels as $rel) {
				// BUG: If there are two tables with same name but come from different schema, it will associate to the first table matching the given name
				if ($rel->child->name == (string)$dataTable) {
					$dataTable = $rel->child;
					break;
				}
			}
		}

		if (!($dataTable instanceof DataTable)) {
			throw new \InvalidArgumentException ("DataTable with name $dataTable was not found in DataTable's " . $this->parent->name . " child relations");
		}

		foreach ($rels as $rel) {
			if ($rel->child === $dataTable) {
				if ($rel->joinType == DataRelation::OneToMany || $rel->joinType == DataRelation::ManyToMany) {
					$childRow = $dataTable->newRow($addToCollection);
					$childRow->parentRow = $this;

					#region Apply parent row's values
					foreach ($rel->getLinks() as $link) {
						/** @var DataColumn $pCol */
						$pCol = $link[0];
						/** @var DataColumn $cCol */
						$cCol = $link[1];

						$childRow->setValue($cCol, $this->getValue($pCol));
					}
					#endregion

					return $childRow;
				}
			}
		}

		throw new \InvalidArgumentException ("DataTable with name $dataTable->name is not joined by OneToMany or ManyToMany relationship with parent DataTable " . $this->parent->name);
	}
	#endregion

	#region Get/set methods
	/**
	 * @param DataColumn|string $column
	 *
	 * @return bool|int
	 */
	public function indexOf($column) {
		if ($column instanceof DataColumn) {
			return $this->parent->columns->indexOf($column);
		}
		elseif (is_string($column)) {
			$num = 0;
			foreach ($this->parent->columns->all() as $c) {
				if ($c->tag == $column)
					return $num;

				$num++;
			}
		}

		return false;
	}

	/**
	 * @param int|string $index_or_tag
	 *
	 * @return DataColumn
	 */
	public function columnAt($index_or_tag): ?DataColumn {
		if (is_int($index_or_tag) || (int)$index_or_tag != 0 || $index_or_tag == '0') {
			if ($index_or_tag < 0 || $index_or_tag >= $this->parent->columns->count())
				return null;

			return $this->parent->columns->itemAt($index_or_tag);
		}
		else {
			$idx = $this->indexOf($index_or_tag);
			return ($idx !== false) ? $this->parent->columns->itemAt($idx) : null;
		}
	}

	/** Returns row's current state */
	public function getState(): int {
		return $this->_state;
	}

	/**
	 * Sets row's state to the provided one
	 *
	 * @param int $state
	 *
	 * @triggers EventOnStateChanged
	 */
	public function setState(int $state) {
		if (!in_array($state, array (self::StateNone, self::StateAdded, self::StateUnchanged, self::StateModified, self::StateDeleted)))
			return;

		if ($this->_state == $state)
			return;

		$oldState = $this->_state;
		$this->_state = $state;

		if ($this->_state == self::StateUnchanged) {
			// Set current values as the original values
			$this->_originalValues = $this->_values; // Arrays are assigned by copy
		}

		$this->trigger(self::EventOnStateChanged, new StateChangedEventArgs ($this, $state, $oldState));
	}

	/** Returns true if the row has validation errors */
	public function hasErrors(): bool {
		return $this->status->isError();
	}

	/** Returns true if the row's values have been validated (regardless the validation outcome) */
	public function isValidated(): bool {
		return $this->_isValidated;
	}

	/** Returns true if the row has been changed (was either added, modified or deleted) */
	public function hasChanged(): bool {
		return (in_array($this->_state, array (self::StateAdded, self::StateModified, self::StateDeleted)));
	}

	/**
	 * Returns the row as form-looking formatted text
	 *
	 * @param bool $useHtml if true, a <br /> will be added to each output line
	 *
	 * @return string
	 */
	public function output(bool $useHtml): string {
		$output = '';
		$nl = ($useHtml == true) ? "<br />\n" : "\n";

		$columns = $this->parent->columns->filter(function (DataColumn $c) { return $c->isActive; })->all();
		foreach ($columns as $c) {
			$output .= $c->title . ': ' . $this->getValue($c->tag) . $nl;
		}

		return $output;
	}
	#endregion

	# region JSON/Javascript methods
	#[\ReturnTypeWillChange]
	public function jsonSerialize(): array {
		$data = [];

		foreach ($this->_values as $key => $value) {
			if ($value instanceof \DateTime)
				$value = DateUtils::toJsDate($value);

			$data[$key] = $value;
		}

		return $data;
	}
	#endregion
	#endregion

	#region Magic methods
	public function __toString() {
		return implode('|', str_replace('|', '\\|', $this->_values));
	}
	#endregion
}
