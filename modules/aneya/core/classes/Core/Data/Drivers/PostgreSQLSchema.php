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

/** @noinspection SqlResolve */

namespace aneya\Core\Data\Drivers;

use aneya\Core\Cache;
use aneya\Core\Data\DataColumn;
use aneya\Core\Data\Schema\Field;
use aneya\Core\Data\Schema\Relation;
use aneya\Core\Data\Schema\Schema;
use aneya\Core\Data\Schema\Table;

final class PostgreSQLSchema extends Schema {
	#region Properties
	/** @var Field[] */
	private array $_fields = [];
	#endregion

	#region Methods
	/**
	 * @inheritdoc
	 */
	public function tables(bool $forceRetrieve = false): array {
		if (!isset($this->_tables) || $forceRetrieve) {
			// Get schema's tables' checksum directly from database
			$schemaName = $this->_db->getSchemaName();
			$extSchema = strlen((string)$this->_db->options->extensionsSchema) > 0
				? $this->_db->options->extensionsSchema
				: $this->_db->getSchemaName();
			$sql = "SELECT T1.table_name, T1.checksum AS table_checksum, T2.checksum AS fields_checksum
					FROM
						(SELECT table_name, encode($extSchema.digest(array_to_string(array_agg(table_name), '|')::bytea, 'sha1'), 'hex') AS checksum
						FROM information_schema.tables
						WHERE table_schema='$schemaName'
						GROUP BY table_name
						) AS T1
					JOIN
						(SELECT c.table_name, encode($extSchema.digest(array_to_string(array_agg(concat(
								C.table_name, C.column_name,
								C.column_default,
								CASE C.is_nullable WHEN 'YES' THEN true ELSE false END,
								C.data_type,
								coalesce(C.character_maximum_length, 0),
								coalesce(C.numeric_precision, 0),
								coalesce(C.numeric_precision_radix, 0),
								C.udt_name,
								K.column_name,
								CASE WHEN K.column_name IS NOT NULL THEN true ELSE false END,
								CASE WHEN substr(C.column_default, 0, 9) = 'nextval(' THEN true ELSE false END
							)), '|')::bytea, 'sha1'), 'hex') AS checksum
						FROM information_schema.columns C
						LEFT JOIN information_schema.key_column_usage K ON K.table_schema=C.table_schema AND K.table_name=C.table_name AND K.column_name=C.column_name
						WHERE C.table_schema='$schemaName' GROUP BY c.table_name
						) AS T2 ON T2.table_name=T1.table_name";
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
				$tbl['table_name'] = strtolower($tbl['table_name']);

				$dataInfo[$tbl['table_name']] = new \stdClass();
				$dataInfo[$tbl['table_name']]->tableChecksum = $tbl['table_checksum'];
				$dataInfo[$tbl['table_name']]->fieldsChecksum = $tbl['fields_checksum'];

				if (!isset($cachedInfo[$tbl['table_name']]) || $cachedInfo[$tbl['table_name']] === null || $forceRetrieve) {
					$outdatedTables[] = $tbl['table_name'];
					$outdatedFields[] = $tbl['table_name'];
				}
				else {
					if ($cachedInfo[$tbl['table_name']]->tableChecksum != $tbl['table_checksum']) {
						$outdatedTables[] = $tbl['table_name'];
					}
					else {
						$dataInfo[$tbl['table_name']]->data = $this->_tables[$tbl['table_name']] = $cachedInfo[$tbl['table_name']]->data;
					}

					if ($cachedInfo[$tbl['table_name']]->fieldsChecksum != $tbl['fields_checksum']) {
						$outdatedFields[] = $tbl['table_name'];
					}
				}
			}
			#endregion
			// TODO: The algorithm does not remove deleted tables from cache

			if (count($outdatedTables) > 0) {
				#region Retrieve outdated table information from database
				$tables = implode("', '", $outdatedTables);
				$sql = "SELECT cl.relname AS table_name, cl.reltuples AS table_rows, obj_description(cl.oid) AS table_comment, (SELECT datcollate FROM pg_catalog.pg_database WHERE datname=current_database()) AS table_collation
						FROM pg_class cl
						JOIN pg_catalog.pg_namespace ns ON cl.relnamespace = ns.oid
						WHERE ns.nspname=:schema AND cl.relkind = 'r' AND cl.relname IN ('$tables')";
				$rows = $this->_db->fetchAll($sql, [':schema' => $this->schemaName()]);
				if ($rows) {
					foreach ($rows as $row) {
						$t = new Table ($this);
						$t->name = $row['table_name'];
						$t->numOfRows = (int)$row['table_rows'];
						$t->collation = $row['table_collation'];
						$t->comment = $row['table_comment'];

						$this->_tables[strtolower($t->name)] = $t;
						$dataInfo[strtolower($t->name)]->data = $t;
					}
				}
				#endregion
			}

			// Restore schema information in cached tables
			foreach ($this->_tables as $tbl)
				$tbl->setSchema($this);

			if (count($outdatedFields) > 0) {
				#region Retrieve outdated fields information from database
				$tables = implode("', '", $outdatedFields);
				$sql = "SELECT C.table_name, C.column_name,
								C.column_default,
								CASE C.is_nullable WHEN 'YES' THEN true ELSE false END AS is_nullable,
								C.data_type AS data_type,
								C.character_maximum_length,
								C.numeric_precision,
								C.numeric_precision_radix,
								C.udt_name,
								CASE WHEN K.column_name IS NOT NULL THEN true ELSE false END AS is_primary,
								CASE WHEN substr(C.column_default, 0, 9) = 'nextval(' THEN true ELSE false END AS is_autoincrement,
							   pg_catalog.col_description(CONCAT(c.table_schema, '.', c.table_name)::regclass::oid, c.ordinal_position::int) AS column_comment
						FROM information_schema.columns C
								 LEFT JOIN information_schema.key_column_usage K ON K.table_schema=C.table_schema AND K.table_name=C.table_name AND K.column_name=C.column_name AND K.POSITION_IN_UNIQUE_CONSTRAINT IS null
						WHERE C.table_schema=:schema AND C.table_name IN ('$tables')";
				$rows = $this->_db->fetchAll($sql, array (':schema' => $this->schemaName()));
				if ($rows) {
					foreach ($rows as $row) {
						$row['data_type'] = strtolower($row['data_type']);
						$subDataType = null;

						switch ($row['data_type']) {
							case 'boolean':
								$dataType = DataColumn::DataTypeBoolean;
								break;
							case 'smallint':
							case 'integer':
							case 'bigint':
							case 'smallserial':
							case 'serial':
							case 'bigserial':
								$dataType = DataColumn::DataTypeInteger;
								if (in_array($row['data_type'], ['smallint', 'smallserial']))
									$maxLength = 5;
								else
									$maxLength = 30;
								break;
							case 'bit':
								$dataType = DataColumn::DataTypeInteger;
								$maxLength = 30;
								break;
							case 'real':
							case 'numeric':
							case 'decimal':
							case 'double precision':
								$dataType = DataColumn::DataTypeFloat;
								$maxLength = 30;
								break;
							case 'date':
								$dataType = DataColumn::DataTypeDate;
								$maxLength = 20;
								break;
							case 'timestamp':
							case 'timestamp with time zone':
							case 'timestamp without time zone':
								$dataType = DataColumn::DataTypeDateTime;
								$maxLength = 30;
								break;
							case 'time':
							case 'time with time zone':
							case 'time without time zone':
								$dataType = DataColumn::DataTypeTime;
								$maxLength = 20;
								break;
							case 'bytea':
								$dataType = DataColumn::DataTypeBlob;
								$maxLength = null;
								break;
							case 'character':
							case 'character varying':
							case 'varchar':
							case 'uuid':
							case 'text':
								$dataType = ($row['character_maximum_length'] == 1 ? DataColumn::DataTypeChar : DataColumn::DataTypeString);
								$maxLength = (!is_null($row['character_maximum_length']) ? (int)$row['character_maximum_length'] : null);
								break;

							case 'geometry':
								$dataType = DataColumn::DataTypeGeometry;
								$maxLength = null;
								break;
							case 'circle':
								$dataType = DataColumn::DataTypeGeoCircle;
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
							case 'line':
							case 'lseg':
							case 'box':
							case 'path':
								$dataType = DataColumn::DataTypeGeoMultiPoint;
								$maxLength = null;
								break;

							case 'json':
								$dataType = DataColumn::DataTypeJson;
								break;

							case 'array':
								$dataType = DataColumn::DataTypeArray;
								if (str_contains($row['udt_name'], 'char'))
									$subDataType = DataColumn::DataTypeString;
								elseif (str_contains($row['udt_name'], 'int'))
									$subDataType = DataColumn::DataTypeInteger;
								elseif (str_contains($row['udt_name'], 'bool'))
									$subDataType = DataColumn::DataTypeBoolean;
								elseif (str_contains($row['udt_name'], 'float'))
									$subDataType = DataColumn::DataTypeFloat;
								elseif (str_contains($row['udt_name'], 'point'))
									$subDataType = DataColumn::DataTypeGeoPoint;
								elseif (str_contains($row['udt_name'], 'timestamp') || str_contains($row['udt_name'], 'datetime'))
									$subDataType = DataColumn::DataTypeDateTime;
								elseif (str_contains($row['udt_name'], 'time'))
									$subDataType = DataColumn::DataTypeTime;
								elseif (str_contains($row['udt_name'], 'date'))
									$subDataType = DataColumn::DataTypeDate;
								elseif (str_contains($row['udt_name'], 'char'))
									$subDataType = DataColumn::DataTypeString;
								break;

							default:
								$dataType = DataColumn::DataTypeString;
								$maxLength = (!is_null($row['character_maximum_length']) ? (int)$row['character_maximum_length'] : null);
						}

						$table = $this->getTableByName($row['table_name']);
						$f = new Field($table);
						$f->name = $row['column_name'];
						$f->defaultValue = str_starts_with($row['column_default'], 'nextval(') || str_contains($row['column_default'], '::') ? null : $row['column_default'];
						$f->isNullable = $row['is_nullable'];
						$f->isAutoIncrement = $row['is_autoincrement'];
						$f->isPrimary = $row['is_primary'];
						$f->isForeign = null; // TODO:
						$f->isIndex = null; // TODO:
						$f->isUnsigned = false;
						$f->dataType = $dataType;
						$f->subDataType = $subDataType;
						$f->columnType = $row['udt_name'];
						$f->maxLength = $maxLength ?? null;
						$f->comment = $row['column_comment'];

						$this->_fields[strtolower($row['table_name'])][$f->name] = $f;
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

			$sql = "SELECT ns.nspname AS schema_name,
						   tbl.relname AS table_name,
						   att.attname AS column_name,
						   f_ns.nspname AS referenced_schema,
						   f_tbl.relname AS referenced_table_name,
						   f_att.attname AS referenced_column_name
					FROM pg_constraint rel
						 JOIN pg_class tbl ON tbl.oid = rel.conrelid
						 JOIN pg_namespace ns ON ns.oid = tbl.relnamespace
						 LEFT JOIN LATERAL UNNEST(rel.conkey) WITH ORDINALITY AS cols(attnum, attposition) ON TRUE
						 LEFT JOIN LATERAL UNNEST(rel.confkey) WITH ORDINALITY AS f_cols(attnum, attposition) ON f_cols.attposition = cols.attposition
						 LEFT JOIN pg_attribute att ON (att.attrelid = tbl.oid AND att.attnum = cols.attnum)
						 LEFT JOIN pg_class f_tbl ON f_tbl.oid = rel.confrelid
						 LEFT JOIN pg_namespace f_ns ON f_ns.oid = f_tbl.relnamespace
						 LEFT JOIN pg_attribute f_att ON (f_att.attrelid = f_tbl.oid AND f_att.attnum = f_cols.attnum)
					WHERE ns.nspname=:schema1 AND f_ns.nspname=:schema2 AND rel.contype='f'
					ORDER BY schema_name, table_name";

			$rows = $this->_db->fetchAll($sql, [
				':schema1' => $this->schemaName(),
				':schema2' => $this->schemaName()
			]);
			if ($rows) {
				foreach ($rows as $row) {
					$cT = $row['table_name'];                // Child table
					$mT = $row['referenced_table_name'];    // Master (referenced) table

					if ($cT == null || $mT == null)
						continue;

					$cF = $row['column_name'];                // Child field
					$mF = $row['referenced_column_name'];    // Master (referenced) field

					$this->_relations[] = new Relation ($mT, $mF, $cT, $cF);
				}
			}

			Cache::store($this->_relations, null, $this->schemaName() . '..refs', 'aneya.schema');
		}

		return $this->_relations;
	}

	public function getLastChanged(string $tableName = null): \DateTime {
		if (($tableName == null && !isset($this->_lastChanged)) || (is_string($tableName) && !($this->_lastChangedTable[$tableName] instanceof \DateTime))) {
			$date = new \DateTime('1970-01-01');

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
