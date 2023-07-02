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
use aneya\Core\Collection;
use aneya\Core\CoreObject;
use aneya\Core\EventStatus;
use aneya\Core\JsonCompatible;
use aneya\Core\Utils\BitOps;

class DataColumn extends CoreObject implements \JsonSerializable {
	use JsonCompatible;

	#region Constants
	const DataTypeArray           = 'array';
	const DataTypeBlob            = 'blob';
	const DataTypeBoolean         = 'bool';
	const DataTypeChar            = 'char';
	const DataTypeDate            = 'date';
	const DataTypeDateTime        = 'datetime';
	const DataTypeFloat           = 'float';
	const DataTypeGeoCircle       = 'geocircle';
	const DataTypeGeoPoint        = 'geopoint';
	const DataTypeGeoPolygon      = 'geopoly';
	const DataTypeGeometry        = 'geometry';
	const DataTypeGeoMultiPoint   = 'geomultipoint';
	const DataTypeGeoMultiPolygon = 'geomultipoly';
	const DataTypeGeoCollection   = 'geocollection';
	const DataTypeInteger         = 'int';
	const DataTypeJson            = 'json';
	const DataTypeObject          = 'obj';
	const DataTypeString          = 'string';
	const DataTypeTime            = 'time';

	const FlagActive        = 0b0000000000000000000000000000000000000000000000000000000000000001;
	const FlagKey           = 0b0000000000000000000000000000000000000000000000000000000000000010;
	const FlagAutoIncrement = 0b0000000000000000000000000000000000000000000000000000000000000100;
	const FlagUnique        = 0b0000000000000000000000000000000000000000000000000000000000001000;
	const FlagRequired      = 0b0000000000000000000000000000000000000000000000000000000000010000;
	const FlagReadOnly      = 0b0000000000000000000000000000000000000000000000000000000000100000;
	const FlagSaveable      = 0b0000000000000000000000000000000000000000000000000000000001000000;
	const FlagMaster        = 0b0000000000000000000000000000000000000000000000000000000010000000;
	const FlagHtml          = 0b0000000000000000000000000000000000000000000000000000000100000000;
	const FlagMultilingual  = 0b0000000000000000000000000000000000000000000000000000001000000000;
	const FlagExpression    = 0b0000000000000000000000000000000000000000000000000000010000000000;
	const FlagAggregate     = 0b0000000000000000000000000000000000000000000000000000100000000000;
	const FlagFake          = 0b0000000000000000000000000000000000000000000000000001000000000000;
	#endregion

	#region Events
	/** Triggered during column's validation to allow extending or bypassing the default validation rules by applying additional or custom validation rules. Passes a DataRowValidationEventArgs argument on listeners with the column as sender. */
	const EventOnValidate = 'OnValidate';
	/** Triggered during column's parent row save to allow columns execute custom code during saving. Passes a DataRowValidationEventArgs argument on listeners with the column as sender. */
	const EventOnSaving = 'OnSaving';
	#endregion

	#region Properties
	#region Basic properties
	public ?int $id = null;
	public ?string $title = '';
	public string $tag = '';
	public string $name = '';
	public ?string $alias = '';
	public string $dataType = self::DataTypeString;
	/** @var ?string For array data types, indicates the type of array values the column stores */
	public ?string $subDataType = null;
	/** @var bool (for numeric columns only) Indicates whether only unsigned values should be valid */
	public bool $isUnsigned = false;
	/** @var DataTable */
	public DataTable $table;
	/** @var ?string Validation rule set in RegEx format */
	public ?string $validationRule = null;
	/** @var mixed */
	public $defaultValue = null;
	/** @var ?int Maximum length for character-based columns */
	public ?int $maxLength = null;
	/** @var ?string A developer's comment to accompany the column's definition */
	public ?string $comment = '';
	#endregion

	#region Boolean attributes
	/** @var bool Indicates if a null value should be stored in the database when column's value in the DataRow is empty */
	public bool $allowNull = true;
	/** @var bool Indicates if values should be trimmed when they are set to the column */
	public bool $allowTrim = true;
	/** @var bool Indicates if the column is a primary key in its corresponding table */
	public bool $isKey = false;
	/** @var bool Indicates if the column belongs to the master table or it's a primary key that should be treated as master key in the DataSet */
	public bool $isMaster = false;
	/**
	 * @var bool Indicates if the column's values are unique in the database
	 * @deprecated This property is not used anymore. Use database constraints instead.
	 */
	public bool $isUnique = false;
	public bool $isRequired = false;
	public bool $isAutoIncrement = false;
	/** @var bool Indicates if the value stored in the field's name is an expression rather than an actual database field name */
	public bool $isExpression = false;
	/** @var bool Indicates if the value stored in the field's name is an aggregation expression */
	public bool $isAggregate = false;
	/** @var bool Indicates if the column is fake. Fake columns behave the same, except that they do not participate in database queries */
	public bool $isFake = false;
	/** @var bool Indicates if the column's value should be saved in the database */
	public bool $isSaveable = true;
	/** @var bool Indicates if the column's value is read-only, ignoring any setValue(...) commands */
	public bool $isReadOnly = false;
	/** @var bool Indicates if the column is multilingual */
	public bool $isMultilingual = false;
	/** @var bool Indicates if the column can store unescaped HTML content */
	public bool $allowHTML = true;
	/** @var bool Indicates if the column is active in the DataSet */
	public bool $isActive = true;

	protected Collection $_customFlags;
	#endregion

	#region Static properties
	protected static array $__jsProperties = [
		'id', 'title', 'tag', 'name', 'dataType', 'validationRule', 'defaultValue',
		'allowNull', 'allowTrim', 'allowHTML',
		'isKey', 'isRequired', 'isUnique', 'isAutoIncrement', 'isExpression', 'isFake', 'isAggregate', 'isUnsigned', 'isSaveable', 'isReadOnly', 'isMultilingual', 'isActive'
	];
	#endregion
	#endregion

	#region Constructor
	public function __construct(string $name, string $dataType = DataColumn::DataTypeString, ?string $title = '', string $tag = '') {
		$this->name = $name;
		$this->tag = ((strlen($tag) > 0) ? $tag : $name);
		$this->dataType = $dataType;

		$this->title = (strlen($title) == 0) ? $this->name : $title;

		$this->hooks()->register([self::EventOnValidate, self::EventOnSaving]);

		$this->_customFlags = new Collection('int', true);
	}
	#endregion

	#region Methods
	/**
	 * Applies configuration from an \stdClass instance.
	 *
	 * @param \stdClass|object $obj
	 *
	 * @return $this
	 */
	public function applyJsonCfg($obj): DataColumn {
		$this->allowHTML = $obj->allowHTML == 'true';
		$this->allowNull = $obj->allowNull == 'true';
		$this->allowTrim = $obj->allowTrim == 'true';
		$this->dataType = $obj->dataType;
		$this->defaultValue = $obj->defaultValue;
		$this->id = (int)$obj->id ?? null;
		$this->isActive = $obj->isActive == 'true';
		$this->isAggregate = $obj->isAggregate == 'true';
		$this->isAutoIncrement = $obj->isAutoIncrement == 'true';
		$this->isExpression = $obj->isExpression == 'true';
		$this->isFake = $obj->isFake == 'true';
		$this->isKey = $obj->isKey == 'true';
		$this->isMultilingual = $obj->isMultilingual == 'true';
		$this->isReadOnly = $obj->isReadOnly == 'true';
		$this->isSaveable = $obj->isSaveable == 'true';
		$this->isUnsigned = $obj->isUnsigned == 'true';
		$this->name = $obj->name;
		$this->tag = $obj->tag;
		$this->title = $obj->title;

		return $this;
	}

	/**
	 * Returns the column's name as it is found in the database
	 *
	 * @param bool $prefixTableAlias
	 * @param bool $suffixColumnAlias
	 *
	 * @return string
	 */
	public function columnName(bool $prefixTableAlias = false, bool $suffixColumnAlias = false): string {
		if ($this->table instanceof DataTable && $this->table->db() instanceof Database) {
			return $this->table->db()->getColumnExpression($this, $prefixTableAlias, $suffixColumnAlias);
		}
		else {
			return ($this->isExpression) ? $this->tag : $this->name;
		}
	}

	/**
	 * Returns column's enabled flags
	 *
	 * @return integer
	 */
	public function flagsValue(): int {
		$flags = 0b0000000000000000000000000000000000000000000000000000000000000000;

		// Add custom flags to the value
		foreach ($this->flags()->all() as $f) {
			$flags = BitOps::addBit($flags, $f);
		}

		if ($this->isActive) $flags = BitOps::addBit($flags, self::FlagActive);
		if ($this->isAutoIncrement) $flags = BitOps::addBit($flags, self::FlagAutoIncrement);
		if ($this->isExpression) $flags = BitOps::addBit($flags, self::FlagExpression);
		if ($this->isAggregate) $flags = BitOps::addBit($flags, self::FlagAggregate);
		if ($this->isFake) $flags = BitOps::addBit($flags, self::FlagFake);
		if ($this->allowHTML) $flags = BitOps::addBit($flags, self::FlagHtml);
		if ($this->isKey) $flags = BitOps::addBit($flags, self::FlagKey);
		if ($this->isMaster) $flags = BitOps::addBit($flags, self::FlagMaster);
		if ($this->isMultilingual) $flags = BitOps::addBit($flags, self::FlagMultilingual);
		if ($this->isReadOnly) $flags = BitOps::addBit($flags, self::FlagReadOnly);
		if ($this->isRequired) $flags = BitOps::addBit($flags, self::FlagRequired);
		if ($this->isSaveable) $flags = BitOps::addBit($flags, self::FlagSaveable);
		if ($this->isUnique) $flags = BitOps::addBit($flags, self::FlagUnique);

		return $flags;
	}

	/**
	 * Returns a collection with any custom flags that have been set to the column.
	 */
	public function flags(): Collection {
		return $this->_customFlags;
	}

	/**
	 * Returns true if the given value is valid depending on any validation criteria have been set on the column.
	 * In case of validation errors, it returns an array with all error statuses
	 *
	 * @param DataRowValidationEventArgs $args
	 *
	 * @return DataRowValidationEventStatus
	 */
	public final function validate(DataRowValidationEventArgs $args): DataRowValidationEventStatus {
		$ret = new DataRowValidationEventStatus();
		$isHandled = false;

		// If column is inactive, there's no need to validate
		if (!$this->isActive) {
			return $ret;
		}

		/** @var DataRowValidationEventStatus[]|DataRowErrorStatus[] $listeners */
		$listeners = $this->trigger(self::EventOnValidate, new DataRowValidationEventArgs ($this, $args->value, $args->row));
		if ($listeners != null) {
			foreach ($listeners as $status) {
				if ($status->isError()) {
					$ret->isPositive = false;
					if ($status instanceof DataRowValidationEventStatus) {
						$ret->errors->addRange($status->errors->all());
					}
					elseif ($status instanceof DataRowErrorStatus) {
						$ret->errors->add($status);
					}
					elseif ($status instanceof EventStatus) {
						$errorStatus = new DataRowErrorStatus($status->isPositive, $status->message, $status->code, $status->debugMessage, $status->isHandled);
						$errorStatus->column = $this;
						$ret->errors->add($errorStatus);
					}
				}
				if ($status->isHandled) {
					$isHandled = true;
					break;
				}
			}
		}

		if (!$isHandled) {
			$status = $this->onValidate($args);
			if ($status->isError()) {
				$ret->isPositive = false;
				$ret->errors->addRange($status->errors->all());
			}
		}

		// Set the column property for all errors to point to current object
		foreach ($ret->errors->all() as $error)
			$error->column = $this;

		return $ret;
	}

	/**
	 * @return string
	 */
	public function __toString() {
		return $this->name;
	}
	#endregion

	#region Event methods
	/**
	 * @param DataRowValidationEventArgs $args
	 *
	 * @return DataRowValidationEventStatus
	 */
	protected function onValidate(DataRowValidationEventArgs $args): DataRowValidationEventStatus {
		$ret = new DataRowValidationEventStatus ();

		$valErrorText = CMS::translator()->translate('content for field does not pass validation', 'cms');

		if ($this->isRequired && !$this->isAutoIncrement && (!isset ($args->value) || (is_scalar($args->value) && $this->dataType != self::DataTypeBoolean && strlen(trim($args->value)) == 0))) {
			$ret->isPositive = false;
			$reqErrorText = CMS::translator()->translate('field is required', 'cms');
			$status = new DataRowErrorStatus (false, str_replace('{field}', $this->title, $reqErrorText));
			$status->column = $this;
			$ret->errors->add($status);
		}

		if ($this->dataType == self::DataTypeInteger && strlen($args->value) > 0 && !is_numeric($args->value)) {
			$ret->isPositive = false;
			$status = new DataRowErrorStatus (false, CMS::translator()->translate('Invalid number format', 'cms'));
			$status->column = $this;
			$ret->errors->add($status);
		}

		// Data format validations
		if ($this->isMultilingual && is_array($args->value)) {
			$languages = CMS::translator()->languages();
			foreach ($languages as $lang) {
				$lang = $lang->code;
				if (isset($args->value[$lang]) && is_scalar($args->value[$lang]) && strlen($args->value[$lang]) > 0 && strlen($this->validationRule) > 0) {
					if (preg_match("/" . $this->validationRule . "/i", $args->value[$lang]) !== 1) {
						$ret->isPositive = false;
						$status = new DataRowErrorStatus (false, str_replace('{field}', $this->title, $valErrorText));
						$status->column = $this;
						$ret->errors->add($status);
					}
				}
			}
		}
		else if (is_scalar($args->value) && strlen($args->value) > 0 && strlen($this->validationRule ?? '') > 0) {
			if (preg_match("/" . $this->validationRule . "/i", $args->value) !== 1) {
				$ret->isPositive = false;
				$status = new DataRowErrorStatus (false, str_replace('{field}', $this->title, $valErrorText));
				$status->column = $this;
				$ret->errors->add($status);
			}
		}

		return $ret;
	}
	#endregion

	#region Static methods
	public static function fromJson($cfg): DataColumn {
		$c = new DataColumn($cfg->name);
		return $c->applyJsonCfg($cfg);
	}
	#endregion
}
