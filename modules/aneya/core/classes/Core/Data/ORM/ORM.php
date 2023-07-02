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


use aneya\Core\CMS;
use aneya\Core\CoreObject;
use aneya\Core\Data\DataRow;
use aneya\Core\Data\DataSet;
use aneya\Core\Data\DataTable;
use aneya\Core\Data\Schema\Schema;
use aneya\Core\IStorable;
use aneya\Core\Utils\ObjectUtils;
use aneya\Core\Utils\StringUtils;

final class ORM {
	#region Events
	/** Triggered when a Storable object's non-scalar property is being saved. Passes a DataObjectPropertySaveEventArgs argument on listeners. */
	const EventOnSaveProperty = 'OnSaveProperty';
	#endregion

	#region Static properties
	/** @var DataObjectTableMapping[] */
	private static array $__mappings = [];
	#endregion

	#region Static methods
	/**
	 * Retrieves all already-stored ORM information about a Class from the database.
	 *
	 * @throws \Exception
	 */
	public static function retrieve(string $className): DataObjectMapping {
		$sql = 'SELECT object_id, schema_tag, table_name
				FROM cms_objects
				WHERE class_name=:className';

		$objMap = new DataObjectMapping();
		$rows = CMS::db()->fetchAll($sql, [':className' => $className]);
		if (!$rows)
			return $objMap;

		$ids = [];
		foreach ($rows as $row) {
			$tblMap = new DataObjectTableMapping($row['schema_tag'], $className, $row['table_name']);
			self::$__mappings[(int)$row['object_id']] = $objMap->add($tblMap);
			$ids[] = (int)$row['object_id'];
		}

		$ids = implode(', ', $ids);
		$sql = "SELECT property_id, object_id, field_name, property_name, value_class
				FROM cms_objects_properties
				WHERE object_id IN ($ids) AND is_key=1";
		$props = CMS::db()->fetchAll($sql);
		foreach ($props as $p) {
			$prMap = new DataObjectProperty ($p['field_name'], $p['property_name']);
			$prMap->id = (int)$p['property_id'];
			$prMap->objectId = (int)$p['object_id'];
			$prMap->valueClass = $p['value_class'];

			self::$__mappings[$prMap->objectId]->properties->add($prMap);
		}

		return $objMap;
	}

	/**
	 * Generates a Data/Object mapping given the schema and table name(s)
	 *
	 * @param Schema $schema
	 * @param string|string[] $tableNames
	 * @param string|null $className
	 * @param DataObjectMappingOptions|null $options
	 * @return DataObjectMapping
	 */
	public static function schemaToMapping(Schema $schema, $tableNames, string $className = null, DataObjectMappingOptions $options = null): DataObjectMapping {
		// Get a complete DataSet instance that represents the given table(s)
		$dataSet = $schema->getDataSet($tableNames);

		return self::dataSetToMapping($dataSet, $className, $options);
	}

	/**
	 * Instantiates an object of the given class by generating the ORM info given the schema and table name(s)
	 *
	 * @param Schema $schema
	 * @param string|string[] $tableNames
	 * @param string $className
	 * @param DataObjectMappingOptions|null $options
	 * @return DataObjectMapping
	 */
	public static function schemaToObject(Schema $schema, $tableNames, string $className, DataObjectMappingOptions $options = null): DataObjectMapping {
		$dObj = self::schemaToMapping($schema, $tableNames, $className, $options);
		return $dObj->generateObject();
	}

	/**
	 * Generates a Data/Object mapping given a DataSet instance
	 *
	 * @param DataTable|DataSet $dataSet
	 * @param null $className
	 * @param DataObjectMappingOptions|null $options
	 * @return DataObjectMapping
	 */
	public static function dataSetToMapping(DataTable $dataSet, $className = null, DataObjectMappingOptions $options = null): DataObjectMapping {
		$dObj = new DataObjectMapping();
		if ($options == null)
			$options = new DataObjectMappingOptions();

		if ($className == null)
			$className = "\\aneya\\Core\\Data\\ORM\\DataObject";

		// If provided argument is DataTable, convert to DataSet
		if (!($dataSet instanceof DataSet)) {
			$ds = new DataSet($dataSet);
			$dataSet = $ds;
		}

		// Associate the generated/provided DataSet instance
		$dObj->dataSet($dataSet);

		if ($options->mapClassNameToDataSet && $className !== "\\aneya\\Core\\Data\\ORM\\DataObject")
			$dataSet->mapClass($className);

		$_props = [];
		foreach ($dataSet->tables->all() as $dt) {
			$dObj->add($dotm = new DataObjectTableMapping($dt->db()->schema, $className, $dt->name));

			foreach ($dt->columns->all() as $col) {
				$propName = ($options->underscoreToCamelCase) ? StringUtils::toCamelCase($col->tag) : $col->tag;
				if (in_array($propName, $_props))
					continue;

				$dotm->properties->add($prop = new DataObjectProperty($col->tag, $propName, $col));
				$prop->id = $col->id;

				$_props[] = $propName;
			}
		}

		return $dObj;
	}

	/**
	 * Generates a Data/Object mapping given a DataRow instance
	 *
	 * @param DataRow $row
	 * @param string $className
	 * @param DataObjectMappingOptions|null $options
	 * @return DataObjectMapping
	 */
	public static function dataRowToMapping(DataRow $row, string $className, DataObjectMappingOptions $options = null): DataObjectMapping {
		return self::dataSetToMapping($row->parent, $className, $options);
	}

	/**
	 * Instantiates an object of the given class by generating the ORM info given a DataRow instance
	 *
	 * @param DataTable|DataSet $dataSet
	 * @param string|null $className
	 * @param DataObjectMappingOptions|null $options
	 * @return DataObjectMapping
	 */
	public static function dataSetToObject(DataTable $dataSet, string $className = null, DataObjectMappingOptions $options = null): DataObjectMapping {
		$dotm = self::dataSetToMapping($dataSet, $className, $options);
		return $dotm->generateObject();
	}

	/**
	 * Instantiates an object of the given class by generating the ORM info given a DataRow instance and applying row's values to the generated object's properties
	 *
	 * @param DataRow $row
	 * @param string|null $className
	 * @param DataObjectMappingOptions|null $options
	 * @return DataObjectMapping
	 */
	public static function dataRowToObject(DataRow $row, string $className = null, DataObjectMappingOptions $options = null) {
		$dotm = self::dataRowToMapping($row, $className, $options);
		$obj = $dotm->generateObject();

		if ($obj instanceof IDataObject) {
			$obj->bulkSetValues($row);
		} else {
			foreach ($row->parent->columns->all() as $col) {
				$propName = $dotm->getPropertyName($col);
				$value = $row->getValue($col);
				CoreObject::setObjectProperty($obj, $propName, $value);
			}
		}

		return $obj;
	}

	/**
	 * Returns the DataRow representation of an object. Sub-properties found are first flattened then added using dot notation.
	 * @param mixed $object
	 * @param DataTable $table
	 * @return DataRow
	 */
	public static function objectToDataRow($object, DataTable $table): DataRow {
		if ($object instanceof IStorable)
			$flat = $object->__classToArray(CMS::db());
		else
			$flat = ObjectUtils::flatten($object);

		$row = $table->newRow(false);

		foreach ($flat as $prop => $value) {
			$row->setValue($prop, $value);
		}

		return $row;
	}
	#endregion
}
