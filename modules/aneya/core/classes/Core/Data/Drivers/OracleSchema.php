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


final class OracleSchema extends Schema {
	#region Properties
	/** @var Field[] */
	private array $_fields = [];

	/** @var bool Lock the generated database schema cache to minimize the delays when accessing schema table information. */
	public bool $lockCache = false;
	/** @var string[] Limit the tables to be known and managed by the driver for this schema */
	public array $limitTables = [];
	#endregion

	#region Methods
	/**
	 * @inheritdoc
	 */
	public function tables(bool $forceRetrieve = false): array {
		if (!isset($this->_tables) || $forceRetrieve) {
			$this->_tables = [];
			$outdatedTables = [];
			$outdatedFields = [];
			$dataInfo = [];
			$owner = strtoupper($this->schemaName());

			// Get tables' checksum from cache as well
			$cachedInfo = Cache::retrieve('aneya.schema', $this->schemaName() . '..tables');

			if ($cachedInfo === null || !$this->lockCache) {
				if (is_array($this->limitTables) && count($this->limitTables) > 0) {
					$includedTables = implode("', '", $this->limitTables);

					// Get schema's tables' checksum directly from database
					// Limit comments length to avoid "string concatenation too long" error
					$sql = "SELECT tbl.TABLE_NAME, CAST(STANDARD_HASH(SUBSTR(tbl.OWNER || tbl.TABLE_NAME || SUBSTR(tbc.COMMENTS, 1, 255), 1, 4000), 'SHA1') AS VARCHAR(255)) AS table_checksum, fc.checksum AS fields_checksum
					FROM (
						SELECT OWNER, TABLE_NAME FROM ALL_TABLES WHERE OWNER=:owner1 AND TABLE_NAME IN ('$includedTables')
						UNION
						SELECT OWNER, VIEW_NAME AS TABLE_NAME FROM ALL_VIEWS WHERE OWNER=:owner2 AND VIEW_NAME IN ('$includedTables')
					) tbl
					LEFT JOIN ALL_TAB_COMMENTS tbc ON tbc.OWNER=tbl.OWNER AND tbc.TABLE_NAME=tbl.TABLE_NAME
					LEFT JOIN (
						SELECT t.OWNER, t.TABLE_NAME, CAST(STANDARD_HASH(SUBSTR(LISTAGG(t.TABLE_NAME || t.COLUMN_NAME || t.DATA_TYPE || t.DATA_LENGTH || t.DATA_PRECISION || t.DATA_SCALE || t.NULLABLE || t.IDENTITY_COLUMN, ',') WITHIN GROUP (ORDER BY t.TABLE_NAME), 1, 4000), 'SHA1') AS VARCHAR(255)) AS checksum
						FROM ALL_TAB_COLUMNS t
						LEFT JOIN ALL_COL_COMMENTS c ON c.OWNER=t.OWNER AND c.TABLE_NAME=t.TABLE_NAME AND c.COLUMN_NAME=t.COLUMN_NAME
						WHERE t.OWNER=:owner3 AND t.TABLE_NAME IN ('$includedTables')
						GROUP BY t.OWNER, t.TABLE_NAME) fc ON fc.OWNER=tbl.OWNER AND fc.TABLE_NAME=tbl.TABLE_NAME
					WHERE tbl.OWNER=:owner4";
				}
				else {
					// Get schema's tables' checksum directly from database
					// Limit comments length to avoid "string concatenation too long" error
					$sql = 'SELECT tbl.TABLE_NAME, CAST(STANDARD_HASH(SUBSTR(tbl.OWNER || tbl.TABLE_NAME || SUBSTR(tbc.COMMENTS, 1, 255), 1, 4000), \'SHA1\') AS VARCHAR(255)) AS table_checksum, fc.checksum AS fields_checksum
					FROM (
						SELECT OWNER, TABLE_NAME FROM ALL_TABLES WHERE OWNER=:owner1
						UNION
						SELECT OWNER, VIEW_NAME AS TABLE_NAME FROM ALL_VIEWS WHERE OWNER=:owner2
					) tbl
					LEFT JOIN ALL_TAB_COMMENTS tbc ON tbc.OWNER=tbl.OWNER AND tbc.TABLE_NAME=tbl.TABLE_NAME
					LEFT JOIN (
						SELECT t.OWNER, t.TABLE_NAME, CAST(STANDARD_HASH(SUBSTR(LISTAGG(t.TABLE_NAME || t.COLUMN_NAME || t.DATA_TYPE || t.DATA_LENGTH || t.DATA_PRECISION || t.DATA_SCALE || t.NULLABLE || t.IDENTITY_COLUMN, \',\') WITHIN GROUP (ORDER BY t.TABLE_NAME), 1, 4000), \'SHA1\') AS VARCHAR(255)) AS checksum
						FROM ALL_TAB_COLUMNS t
						LEFT JOIN ALL_COL_COMMENTS c ON c.OWNER=t.OWNER AND c.TABLE_NAME=t.TABLE_NAME AND c.COLUMN_NAME=t.COLUMN_NAME
						WHERE t.OWNER=:owner3
						GROUP BY t.OWNER, t.TABLE_NAME) fc ON fc.OWNER=tbl.OWNER AND fc.TABLE_NAME=tbl.TABLE_NAME
					WHERE tbl.OWNER=:owner4';
				}
					/*
					UNION
					SELECT tbl.SYNONYM_NAME, CAST(STANDARD_HASH(SUBSTR(tbl.OWNER || tbl.SYNONYM_NAME || SUBSTR(tbc.COMMENTS, 1, 255), 1, 4000), \'SHA1\') AS VARCHAR(255)) AS table_checksum, fc.checksum AS fields_checksum
					FROM ALL_SYNONYMS tbl
					LEFT JOIN ALL_TAB_COMMENTS tbc on tbc.OWNER=tbl.TABLE_OWNER AND tbc.TABLE_NAME=tbl.TABLE_NAME
					LEFT JOIN (
						SELECT t.OWNER, t.TABLE_NAME, CAST(STANDARD_HASH(SUBSTR(LISTAGG(t.TABLE_NAME || t.COLUMN_NAME || t.DATA_TYPE || t.DATA_LENGTH || t.DATA_PRECISION || t.DATA_SCALE || t.NULLABLE || t.IDENTITY_COLUMN || TO_CLOB(c.COMMENTS), \',\') WITHIN GROUP (ORDER BY t.TABLE_NAME), 1, 4000), \'SHA1\') AS VARCHAR(255)) AS checksum
						FROM ALL_TAB_COLUMNS t
						LEFT JOIN ALL_COL_COMMENTS c ON c.OWNER=t.OWNER AND c.TABLE_NAME=t.TABLE_NAME AND c.COLUMN_NAME=t.COLUMN_NAME
						GROUP BY t.OWNER, t.TABLE_NAME) fc ON fc.OWNER=tbl.TABLE_OWNER AND fc.TABLE_NAME=tbl.TABLE_NAME
					WHERE tbl.OWNER=:owner5';
					*/
				$schemaInfo = $this->_db->fetchAll($sql, [':owner1' => $owner, ':owner2' => $owner, ':owner3' => $owner, ':owner4' => $owner]);

				#region If cached info is outdated, flag the outdated tables to be retrieved from database
				foreach ($schemaInfo as $tbl) {
					// Store all tables in upper-case to avoid lower/upper case mismatches
					$tbl['TABLE_NAME'] = strtoupper($tbl['TABLE_NAME']);

					$dataInfo[$tbl['TABLE_NAME']] = new \stdClass();
					$dataInfo[$tbl['TABLE_NAME']]->tableChecksum = $tbl['TABLE_CHECKSUM'];
					$dataInfo[$tbl['TABLE_NAME']]->fieldsChecksum = $tbl['FIELDS_CHECKSUM'];

					if (!isset($cachedInfo[$tbl['TABLE_NAME']]) || $cachedInfo[$tbl['TABLE_NAME']] === null) {
						$outdatedTables[] = $tbl['TABLE_NAME'];
						$outdatedFields[] = $tbl['TABLE_NAME'];
					}
					else {
						if ($cachedInfo[$tbl['TABLE_NAME']]->tableChecksum != $tbl['TABLE_CHECKSUM']) {
							$outdatedTables[] = $tbl['TABLE_NAME'];
						}
						else {
							$dataInfo[$tbl['TABLE_NAME']]->data = $this->_tables[$tbl['TABLE_NAME']] = $cachedInfo[$tbl['TABLE_NAME']]->data;
						}

						if ($cachedInfo[$tbl['TABLE_NAME']]->fieldsChecksum != $tbl['FIELDS_CHECKSUM']) {
							$outdatedFields[] = $tbl['TABLE_NAME'];
						}
					}
				}
				#endregion
			}
			else {
				foreach ($cachedInfo as $name => $tbl)
					$this->_tables[$name] = $tbl->data;

				// Also retrieve table relations
				$relations = Cache::retrieve('aneya.schema', $this->schemaName() . '..refs');
				if (is_array($relations))
					$this->_relations = $relations;
			}

			// TODO: The algorithm does not remove deleted tables from cache

			if (count($outdatedTables) > 0) {
				#region Retrieve outdated table information from database
				$tables = implode("', '", $outdatedTables);
				$sql = "SELECT t.TABLE_NAME, t.NUM_ROWS, c.COMMENTS
						FROM (
							SELECT t.TABLE_NAME, t.NUM_ROWS, t.OWNER
							FROM ALL_TABLES t
							WHERE t.OWNER=:owner1
							UNION
							SELECT t.VIEW_NAME AS TABLE_NAME, COUNT(1) AS NUM_ROWS, t.OWNER
							FROM ALL_VIEWS t
							WHERE t.OWNER=:owner2
							GROUP BY t.VIEW_NAME, t.OWNER
						) t
						LEFT JOIN ALL_TAB_COMMENTS c on c.OWNER=t.OWNER AND c.TABLE_NAME=t.TABLE_NAME
						WHERE t.OWNER=:owner3 AND t.TABLE_NAME IN ('$tables')";
				$rows = $this->_db->fetchAll($sql, [':owner1' => $owner, ':owner2' => $owner, ':owner3' => $owner]);
				if ($rows) {
					foreach ($rows as $row) {
						$t = new Table($this);
						$t->name = $row['TABLE_NAME'];
						$t->numOfRows = (int)$row['NUM_ROWS'];
						$t->comment = (string)$row['COMMENTS'];

						$this->_tables[strtoupper($t->name)] = $t;
						$dataInfo[strtoupper($t->name)]->data = $t;
					}
				}
				#endregion
			}

			#region Include synonyms in table information
			$sql = 'SELECT SYNONYM_NAME, TABLE_NAME FROM ALL_SYNONYMS WHERE OWNER=:owner';
			$synonyms = $this->_db->fetchAll($sql, [':owner' => $owner]);
			foreach ($synonyms as $row) {
				// If synonym already exists & it's source table is not outdated, skip the synonym
				if (isset($this->_tables[$row['SYNONYM_NAME']]) && !in_array($row['TABLE_NAME'], $outdatedTables) && !in_array($row['TABLE_NAME'], $outdatedFields))
					continue;

				$table = $this->_tables[$row['TABLE_NAME']];
				// If source table is not found (probably comes from another schema), skip the synonym
				if ($table === null)
					continue;

				// Add the synonym just like tables
				$t = new Table($this);
				$t->name = $row['SYNONYM_NAME'];
				$t->numOfRows = $table->numOfRows;
				$t->comment = $table->comment;

				$this->_tables[strtoupper($t->name)] = $t;
				$dataInfo[strtoupper($t->name)]->data = $t;

				$outdatedTables[] = $t->name;
				$outdatedFields[] = $t->name;
			}
			#endregion

			// Restore schema information in cached tables
			foreach ($this->_tables as $tbl) {
				/** @var Table $tbl */
				$tbl->setSchema($this);
			}

			if (count($outdatedFields) > 0) {
				#region Retrieve outdated fields information from database
				$tables = implode("', '", $outdatedFields);
				$sql = "SELECT t.TABLE_NAME, t.COLUMN_NAME, t.DATA_TYPE, t.DATA_LENGTH, t.DATA_PRECISION, t.DATA_SCALE, t.NULLABLE, t.DATA_DEFAULT, t.IDENTITY_COLUMN, jp.PRIMARY_COLUMN, jf.FOREIGN_COLUMN, c.COMMENTS
						FROM ALL_TAB_COLUMNS t
						LEFT JOIN ALL_COL_COMMENTS c ON c.OWNER=t.OWNER AND c.TABLE_NAME=t.TABLE_NAME AND c.COLUMN_NAME=t.COLUMN_NAME
						LEFT JOIN (
							SELECT cc.*, 'YES' AS PRIMARY_COLUMN FROM ALL_CONS_COLUMNS cc WHERE CONSTRAINT_NAME IN (
								SELECT CONSTRAINT_NAME FROM ALL_CONSTRAINTS ct
								WHERE ct.TABLE_NAME=cc.TABLE_NAME AND ct.CONSTRAINT_TYPE IN ('P')
							)
						) jp ON c.OWNER=t.OWNER AND jp.TABLE_NAME=t.TABLE_NAME AND jp.COLUMN_NAME=t.COLUMN_NAME
						LEFT JOIN (
							SELECT cc.*, 'YES' AS FOREIGN_COLUMN FROM ALL_CONS_COLUMNS cc WHERE CONSTRAINT_NAME IN (
								SELECT CONSTRAINT_NAME FROM ALL_CONSTRAINTS ct
								WHERE ct.TABLE_NAME=cc.TABLE_NAME AND ct.CONSTRAINT_TYPE IN ('R')
							)
						) jf ON c.OWNER=t.OWNER AND jf.TABLE_NAME=t.TABLE_NAME AND jf.COLUMN_NAME=t.COLUMN_NAME
						WHERE t.OWNER=:owner AND t.TABLE_NAME IN ('$tables')";
				$rows = $this->_db->fetchAll($sql, [':owner' => $owner]);
				if ($rows) {
					foreach ($rows as $row) {
						switch ($row['DATA_TYPE']) {
							case 'NUMBER'    :
							case 'SMALLINT'  :
							case 'INT'       :
							case 'INTEGER'   :
							case 'LONG'      :
								if ((int)$row['DATA_SCALE'] > 0) {
									$dataType = DataColumn::DataTypeFloat;
									$maxLength = 30;
								}
								else {
									if ($row['DATA_PRECISION'] == 1) {
										$dataType = DataColumn::DataTypeBoolean;
										$maxLength = 1;
									}
									else {
										$dataType = DataColumn::DataTypeInteger;
										$maxLength = 50;
									}
								}
								break;
							case 'bit'       :
								$dataType = (substr($row['COLUMN_TYPE'], 0, 6) == 'bit(1)' ? DataColumn::DataTypeBoolean : DataColumn::DataTypeInteger);
								if ($dataType == DataColumn::DataTypeBoolean) {
									$maxLength = 1;
								}
								else {
									$maxLength = 30;
								}
								break;
							case 'FLOAT'     :
							case 'DECIMAL'   :
							case 'DOUBLE PRECISION':
							case 'REAL'      :
								$dataType = DataColumn::DataTypeFloat;
								$maxLength = 30;
								break;
							case 'DATE'      :
								$dataType = DataColumn::DataTypeDateTime;
								$maxLength = 20;
								break;
							case 'TIMESTAMP' :
								$dataType = DataColumn::DataTypeDateTime;
								$maxLength = 30;
								break;
							case 'time'      :
								$dataType = DataColumn::DataTypeTime;
								$maxLength = 20;
								break;
							case 'BLOB'      :
								$dataType = DataColumn::DataTypeBlob;
								$maxLength = null;
								break;
							case 'CHAR'      :
							case 'NCHAR'     :
							case 'VARCHAR'   :
							case 'VARCHAR2'  :
							case 'NVARCHAR'  :
							case 'CLOB'      :
							case 'NCLOB'     :
								$dataType = ((int)$row['DATA_LENGTH'] == 1 ? DataColumn::DataTypeChar : DataColumn::DataTypeString);
								$maxLength = (int)$row['DATA_LENGTH'];
								break;
							default          :
								$dataType = DataColumn::DataTypeString;
								$maxLength = 1;
						}

						$table = $this->getTableByName($row['TABLE_NAME']);
						$f = new Field ($table);
						$f->name = $row['COLUMN_NAME'];
						$f->isNullable = ($row['NULLABLE'] == 'Y');
						$f->isAutoIncrement = ($row['IDENTITY_COLUMN'] == 'YES');
						$f->isPrimary = ($row['PRIMARY_COLUMN'] == 'YES');
						$f->isForeign = ($row['FOREIGN_COLUMN'] == 'YES');
						$f->isIndex = $f->isPrimary || $f->isForeign;
						$f->isUnsigned = false;
						$f->dataType = $dataType;
						$f->columnType = '';
						$f->maxLength = $maxLength;
						$f->defaultValue = $f->isAutoIncrement ? '' : $row['DATA_DEFAULT'];
						$f->comment = $row['COMMENTS'];

						$this->_fields[strtoupper($row['TABLE_NAME'])][$f->name] = $f;
					}
				}
				#endregion

				#region Include synonyms in fields information
				foreach ($synonyms as $row) {
					// If synonym's source table is not outdated, skip the synonym
					if (!in_array($row['SYNONYM_NAME'], $outdatedTables) && !in_array($row['SYNONYM_NAME'], $outdatedFields))
						continue;

					$table = $this->_tables[$row['SYNONYM_NAME']];
					// If synonym table is not found (not added earlier, probably comes from another schema), skip the synonym
					if ($table === null)
						continue;

					// Clone all source fields and add them in the synonym table
					foreach ($this->_fields[$row['TABLE_NAME']] as $name => $field) {
						/** @var Field $f */
						$f = clone $field;
						$f->table = $table;

						$this->_fields[strtoupper($row['SYNONYM_NAME'])][$f->name] = $f;
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
	public function getTableByName($name): ?Table {
		// Enforce lowercase searching to be inline with tables cache
		$name = strtoupper($name);

		$tables = $this->tables();
		return (isset ($tables[$name])) ? $tables[$name] : null;
	}

	/**
	 * @inheritdoc
	 */
	public function getFields($tableName): array {
		// Enforce lowercase searching to be inline with tables cache
		$tableName = strtoupper($tableName);

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

	public function relations($forceRetrieve = false): array {
		if ($this->_relations == null || $forceRetrieve) {
			$this->_relations = [];

			if (!$forceRetrieve) {
				$cached = Cache::retrieve('aneya.schema', $this->schemaName() . '..refs');

				if (is_array($cached))
					return $this->_relations = $cached;
			}

			$sql = "SELECT p.TABLE_NAME AS PARENT_TABLE, p.COLUMN_NAME AS PARENT_COLUMN, a.TABLE_NAME AS CHILD_TABLE, c.COLUMN_NAME AS CHILD_COLUMN
				FROM ALL_CONSTRAINTS a
				JOIN ALL_CONS_COLUMNS p ON p.OWNER=a.OWNER AND p.CONSTRAINT_NAME=a.R_CONSTRAINT_NAME
				JOIN ALL_CONS_COLUMNS c ON c.OWNER=a.OWNER AND c.CONSTRAINT_NAME=a.CONSTRAINT_NAME
				WHERE a.OWNER=:owner AND a.CONSTRAINT_TYPE='R'";
			$rows = $this->_db->fetchAll($sql, [':owner' => strtoupper($this->schemaName())]);
			if ($rows) {
				foreach ($rows as $row) {
					$cT = $row['CHILD_TABLE'];                // Child table
					$mT = $row['PARENT_TABLE'];               // Master (referenced) table

					if ($cT == null || $mT == null)
						continue;

					$cF = $row['CHILD_COLUMN'];                // Child field
					$mF = $row['PARENT_COLUMN'];    // Master (referenced) field

					$this->_relations[] = new Relation($mT, $mF, $cT, $cF);
				}
			}

			Cache::store($this->_relations, null, $this->schemaName() . '..refs', 'aneya.schema');
		}

		return $this->_relations;
	}

	public function getLastChanged($tableName = null): \DateTime {
		if (($tableName == null && !($this->_lastChanged instanceof \DateTime)) || (is_string($tableName) && !($this->_lastChangedTable[$tableName] instanceof \DateTime))) {
			if (is_string($tableName) && strlen($tableName) > 0) {
				$sql = "SELECT LAST_DDL_TIME FROM all_objects WHERE OBJECT_NAME=:table AND OWNER=:owner AND OBJECT_TYPE='TABLE'";
				$date = $this->_db->fetchColumn($sql, 'LAST_DDL_TIME', [':table' => $tableName, ':owner' => strtoupper($this->schemaName())]);
			}
			else {
				$sql = "SELECT max(LAST_DDL_TIME) AS last_ddl_time FROM all_objects WHERE OWNER=:owner AND OBJECT_TYPE='TABLE'";
				$date = $this->_db->fetchColumn($sql, 'last_ddl_time', [':owner' => strtoupper($this->schemaName())]);
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
