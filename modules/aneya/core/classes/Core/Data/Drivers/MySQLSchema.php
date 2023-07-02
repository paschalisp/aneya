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

namespace aneya\Core\Data\Drivers;

use aneya\Core\Cache;
use aneya\Core\Data\DataColumn;
use aneya\Core\Data\Schema\Field;
use aneya\Core\Data\Schema\Relation;
use aneya\Core\Data\Schema\Schema;
use aneya\Core\Data\Schema\Table;


final class MySQLSchema extends Schema {
	#region Properties
	/** @var Field[] */
	private $_fields = [];
	#endregion

	#region Methods
	public function schemaName(): string {
		return ($this->_db !== null) ? ($this->_db->getSchemaName() ?? $this->_db->getDatabaseName()) : '';
	}

	/**
	 * @inheritdoc
	 */
	public function tables(bool $forceRetrieve = false): array {
		if (!isset($this->_tables) || $forceRetrieve) {
			// Get schema's tables' checksum directly from database
			$sql = 'SELECT T1.TABLE_NAME, T1.checksum AS table_checksum, T2.checksum AS fields_checksum
					FROM
						(SELECT TABLE_NAME, sha1(group_concat(concat(TABLE_NAME, TABLE_COLLATION, TABLE_COMMENT))) AS checksum FROM information_schema.TABLES WHERE TABLE_SCHEMA=:schema1 GROUP BY TABLE_NAME) AS T1
					JOIN
						(SELECT TABLE_NAME, sha1(group_concat(concat(COLUMN_NAME, ifnull(COLUMN_DEFAULT,\'\'), IS_NULLABLE, DATA_TYPE, ifnull(CHARACTER_MAXIMUM_LENGTH,\'\'), COLUMN_TYPE, ifnull(COLUMN_KEY,\'\'), ifnull(EXTRA,\'\')))) AS checksum
							FROM information_schema.columns WHERE table_schema=:schema2 GROUP BY TABLE_NAME
						) AS T2 ON T2.TABLE_NAME=T1.TABLE_NAME';
			$schemaInfo = $this->_db->fetchAll($sql, [':schema1' => $this->schemaName(), ':schema2' => $this->schemaName()]);

			// Get tables' checksum from cache as well
			$cachedInfo = Cache::retrieve('aneya.schema', $this->schemaName() . '..tables');

			$this->_tables = [];
			$outdatedTables = [];
			$outdatedFields = [];
			$dataInfo = [];


			#region If cached info is outdated, flag the outdated tables to be retrieved from database
			foreach ($schemaInfo as $tbl) {
				// Store all tables in lower-case to avoid lower/upper case mismatches
				$tbl['TABLE_NAME'] = strtolower($tbl['TABLE_NAME']);

				$dataInfo[$tbl['TABLE_NAME']] = new \stdClass();
				$dataInfo[$tbl['TABLE_NAME']]->tableChecksum = $tbl['table_checksum'];
				$dataInfo[$tbl['TABLE_NAME']]->fieldsChecksum = $tbl['fields_checksum'];

				if (!isset($cachedInfo[$tbl['TABLE_NAME']]) || $cachedInfo[$tbl['TABLE_NAME']] === null) {
					$outdatedTables[] = $tbl['TABLE_NAME'];
					$outdatedFields[] = $tbl['TABLE_NAME'];
				}
				else {
					if ($cachedInfo[$tbl['TABLE_NAME']]->tableChecksum != $tbl['table_checksum']) {
						$outdatedTables[] = $tbl['TABLE_NAME'];
					}
					else {
						$dataInfo[$tbl['TABLE_NAME']]->data = $this->_tables[$tbl['TABLE_NAME']] = $cachedInfo[$tbl['TABLE_NAME']]->data;
					}

					if ($cachedInfo[$tbl['TABLE_NAME']]->fieldsChecksum != $tbl['fields_checksum']) {
						$outdatedFields[] = $tbl['TABLE_NAME'];
					}
				}
			}
			#endregion
			// TODO: The algorithm does not remove deleted tables from cache

			if (count($outdatedTables) > 0) {
				#region Retrieve outdated table information from database
				$tables = implode("', '", $outdatedTables);
				$sql = "SELECT TABLE_NAME, TABLE_ROWS, TABLE_COLLATION, TABLE_COMMENT, UPDATE_TIME FROM information_schema.TABLES WHERE TABLE_SCHEMA=:schema AND TABLE_NAME IN ('$tables')";
				$rows = $this->_db->fetchAll($sql, [':schema' => $this->schemaName()]);
				if ($rows) {
					foreach ($rows as $row) {
						$t = new Table ($this);
						$t->name = $row['TABLE_NAME'];
						$t->numOfRows = (int)$row['TABLE_ROWS'];
						$t->collation = $row['TABLE_COLLATION'];
						$t->comment = $row['TABLE_COMMENT'];

						$this->_tables[strtolower($t->name)] = $t;
						$dataInfo[strtolower($t->name)]->data = $t;
					}
				}
				#endregion
			}

			// Restore schema information in cached tables
			foreach ($this->_tables as $tbl) {
				/** @var Table $tbl */
				$tbl->setSchema($this);
			}

			if (count($outdatedFields) > 0) {
				#region Retrieve outdated fields information from database
				$tables = implode("', '", $outdatedFields);
				$sql = "SELECT TABLE_NAME, COLUMN_NAME, COLUMN_DEFAULT, IS_NULLABLE, DATA_TYPE, CHARACTER_MAXIMUM_LENGTH, COLUMN_TYPE, COLUMN_KEY, EXTRA, COLUMN_COMMENT FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=:schema AND TABLE_NAME IN ('$tables')";
				$rows = $this->_db->fetchAll($sql, array (':schema' => $this->schemaName()));
				if ($rows) {
					foreach ($rows as $row) {
						switch ($row['DATA_TYPE']) {
							case 'bigint'	:
							case 'int'		:
							case 'smallint'	:
							case 'tinyint'	:
							case 'year'		:
								$dataType = (substr($row['COLUMN_TYPE'], 0, 10) == 'tinyint(1)' ? DataColumn::DataTypeBoolean : DataColumn::DataTypeInteger);
								if ($dataType == DataColumn::DataTypeBoolean) {
									$maxLength = 1;
								}
								elseif ($row['DATA_TYPE'] == 'tinyint') {
									$maxLength = 4;
								}
								else {
									$maxLength = 30;
								}
								break;
							case 'bit'		:
								$dataType = (substr($row['COLUMN_TYPE'], 0, 6) == 'bit(1)' ? DataColumn::DataTypeBoolean : DataColumn::DataTypeInteger);
								if ($dataType == DataColumn::DataTypeBoolean) {
									$maxLength = 1;
								}
								else {
									$maxLength = 30;
								}
								break;
							case 'float'	:
							case 'double'	:
							case 'decimal'	:
								$dataType = DataColumn::DataTypeFloat;
								$maxLength = 30;
								break;
							case 'date'		:
								$dataType = DataColumn::DataTypeDate;
								$maxLength = 20;
								break;
							case 'timestamp':
							case 'datetime'	:
								$dataType = DataColumn::DataTypeDateTime;
								$maxLength = 30;
								break;
							case 'time'		:
								$dataType = DataColumn::DataTypeTime;
								$maxLength = 20;
								break;
							case 'blob'		:
								$dataType = DataColumn::DataTypeBlob;
								$maxLength = null;
								break;
							case 'char'		:
							case 'varchar'	:
							case 'text'		:
							case 'mediumtext':
							case 'longtext':
								$dataType = ($row['COLUMN_TYPE'] == 'char(1)' ? DataColumn::DataTypeChar : DataColumn::DataTypeString);
								$maxLength = (!is_null($row['CHARACTER_MAXIMUM_LENGTH']) ? (int)$row['CHARACTER_MAXIMUM_LENGTH'] : null);
								break;

							case 'geometry':
								$dataType = DataColumn::DataTypeGeometry;
								$maxLength = null;
								break;
							case 'point':
								$dataType = DataColumn::DataTypeGeoPoint;
								$maxLength = null;
								break;
							case 'polygon':
								$dataType = DataColumn::DataTypeGeoPolygon;
								$maxLength = null;
								break;
							case 'multipoint':
								$dataType = DataColumn::DataTypeGeoMultiPoint;
								$maxLength = null;
								break;
							case 'multipolygon':
								$dataType = DataColumn::DataTypeGeoMultiPolygon;
								$maxLength = null;
								break;
							case 'geometrycollection':
								$dataType = DataColumn::DataTypeGeoCollection;
								$maxLength = null;
								break;

							default			:
								$dataType = DataColumn::DataTypeString;
								$maxLength = 1;
						}

						$table = $this->getTableByName($row['TABLE_NAME']);
						$f = new Field ($table);
						$f->name = $row['COLUMN_NAME'];
						$f->defaultValue = $row['COLUMN_DEFAULT'];
						$f->isNullable = (strtolower($row['IS_NULLABLE']) == 'yes');
						$f->isAutoIncrement = ($row['EXTRA'] == 'auto_increment');
						$f->isPrimary = ($row['COLUMN_KEY'] == 'PRI');
						$f->isForeign = ($row['COLUMN_KEY'] == 'MUL');
						$f->isIndex = (strlen($row['COLUMN_KEY']) > 0);
						$f->isUnsigned = (stripos($row['COLUMN_TYPE'], 'unsigned') > 0);
						$f->dataType = $dataType;
						$f->columnType = $row['COLUMN_TYPE'];
						$f->maxLength = $maxLength;
						$f->comment = $row['COLUMN_COMMENT'];

						$this->_fields[strtolower($row['TABLE_NAME'])][$f->name] = $f;
					}
				}
				#endregion

				#region Cache the updated fields information
				foreach ($outdatedFields as $table) {
					Cache::store($this->_fields[$table], null, $this->schemaName() . ".$table", 'aneya.schema');
				}
				#endregion
			}

			if (count($outdatedTables) > 0 || count($outdatedFields) > 0) {
				// Cache the updated tables information
				Cache::store($dataInfo, null, $this->schemaName() . '..tables', 'aneya.schema');
			}
		}

		return $this->_tables;
	}

	/**
	 * @inheritdoc
	 */
	public function getTableByName(string $name): ?Table {
		// Enforce lowercase searching to be inline with tables cache
		$name = strtolower($name);

		$tables = $this->tables();
		return (isset ($tables[$name])) ? $tables[$name] : null;
	}

	/**
	 * @inheritdoc
	 */
	public function getFields(Table|string $table): array {
		// Enforce lowercase searching to be inline with tables cache
		$tableName = strtolower(($table instanceof Table) ? $table->name : $table);

		if (!isset ($this->_fields[$tableName])) {

			// Trigger updating schemas' cache (if needed)
			$this->tables();

			// Retrieve fields information from cache
			$this->_fields[$tableName] = Cache::retrieve('aneya.schema', $this->schemaName() . ".$tableName");

			// Check if cache information is missing (cache information was manually deleted)
			if ($this->_fields[$tableName] == null) {
				$cachedInfo = Cache::retrieve('aneya.schema', $this->schemaName() . '..tables');
				$cachedInfo[$tableName]->fieldsChecksum = null;

				// Trigger (again) updating schemas' cache (if needed)
				$this->tables();

				// Retrieve fields information from cache
				$this->_fields[$tableName] = Cache::retrieve('aneya.schema', $this->schemaName() . ".$tableName");
			}
		}

		return $this->_fields[$tableName];
	}

	public function relations(bool $forceRetrieve = false): array {
		if (!isset($this->_relations) || $forceRetrieve) {
			$this->_relations = [];

			if (!$forceRetrieve) {
				$cached = Cache::retrieve('aneya.schema', $this->schemaName() . '..refs');

				if (is_array($cached))
					return $this->_relations = $cached;
			}

			$sql = "SELECT TABLE_NAME, COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME
					FROM information_schema.KEY_COLUMN_USAGE
					WHERE TABLE_SCHEMA=:schema AND REFERENCED_TABLE_NAME IS NOT NULL";

			$rows = $this->_db->fetchAll($sql, array (':schema' => $this->schemaName()));
			if ($rows) {
				foreach ($rows as $row) {
					$cT = $row['TABLE_NAME'];                // Child table
					$mT = $row['REFERENCED_TABLE_NAME'];    // Master (referenced) table

					if ($cT == null || $mT == null)
						continue;

					$cF = $row['COLUMN_NAME'];                // Child field
					$mF = $row['REFERENCED_COLUMN_NAME'];    // Master (referenced) field

					$this->_relations[] = new Relation ($mT, $mF, $cT, $cF);
				}
			}

			Cache::store($this->_relations, null, $this->schemaName() . '..refs', 'aneya.schema');
		}

		return $this->_relations;
	}

	public function getLastChanged(string $tableName = null): \DateTime {
		if (($tableName == null && !isset($this->_lastChanged)) || (is_string($tableName) && !($this->_lastChangedTable[$tableName] instanceof \DateTime))) {
			if (is_string($tableName) && strlen($tableName) > 0) {
				$sql = "SELECT CASE WHEN UPDATE_TIME IS null THEN CREATE_TIME ELSE UPDATE_TIME END AS update_time FROM information_schema.tables WHERE table_schema=:schema AND table_name=:table";
				$date = $this->_db->fetchColumn($sql, 'update_time', [':schema' => $this->schemaName(), ':table' => $tableName]);
			}
			else {
				$sql = "SELECT max(CASE WHEN UPDATE_TIME IS null THEN CREATE_TIME ELSE UPDATE_TIME END) as update_time FROM information_schema.tables WHERE table_schema=:schema";
				$date = $this->_db->fetchColumn($sql, 'update_time', [':schema' => $this->schemaName()]);
			}

			if ($date == null) {
				$date = new \DateTime('1970-01-01');
			}
			else {
				$date = new \DateTime($date);
			}

			if ($tableName == null) {
				$this->_lastChanged = $date;
			}
			else {
				$this->_lastChangedTable[$tableName] = $date;
			}
		}

		return $this->_lastChanged;
	}
	#endregion
}
