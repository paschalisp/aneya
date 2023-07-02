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

use aneya\Core\Data\DataColumn;
use aneya\Core\Data\Schema\Field;
use aneya\Core\Data\Schema\Relation;
use aneya\Core\Data\Schema\Schema;
use aneya\Core\Data\Schema\Table;

class SQLiteSchema extends Schema {
	#region Properties
	/** @var Field[][] */
	private array $_fields = [];
	#endregion

	#region Methods
	/**
	 * @inheritdoc
	 */
	public function tables(bool $forceRetrieve = false): array {
		if (!isset($this->_tables)) {
			$sql = "SELECT name FROM sqlite_master WHERE type='table'";

			$rows = $this->_db->fetchAll($sql);

			$this->_tables = [];

			#region Retrieve table information from database
			if ($rows) {
				foreach ($rows as $row) {
					$t = new Table($this);
					$t->name = $row['name'];
					$t->numOfRows = 0;
					$t->collation = 'utf-8';
					$t->comment = '';

					$this->_tables[$t->name] = $t;
				}
			}
			#endregion

			#region Retrieve fields information from database
			foreach ($this->_tables as $table) {
				$sql = "PRAGMA table_info($table->name)";
				$rows = $this->_db->fetchAll($sql);

				if ($rows) {
					foreach ($rows as $row) {
						switch (strtolower($row['type'])) {
							case 'integer'    :
								$dataType = DataColumn::DataTypeInteger;
								$maxLength = 30;
								break;
							case 'real'        :
								$dataType = DataColumn::DataTypeFloat;
								$maxLength = 30;
								break;
							case 'blob'        :
								$dataType = DataColumn::DataTypeBlob;
								$maxLength = null;
								break;
							case 'text'        :
								$dataType = DataColumn::DataTypeString;
								$maxLength = null;
								break;
							default            :
								$dataType = DataColumn::DataTypeString;
								$maxLength = 1;
						}

						$f = new Field($table);
						$f->name = $row['name'];
						$f->defaultValue = $row['dflt_value'];
						$f->isNullable = ((int)($row['notnull']) == 0);
						$f->isAutoIncrement = false;
						$f->isPrimary = ($row['pk'] == 1);
						$f->isForeign = false;
						$f->isIndex = false;
						$f->isUnsigned = false;
						$f->dataType = $dataType;
						$f->columnType = $row['type'];
						$f->maxLength = $maxLength;
						$f->comment = '';

						$this->_fields[$table->name][$f->name] = $f;
					}
				}
			}
			#endregion
		}

		return $this->_tables;
	}

	/**
	 * @inheritdoc
	 */
	public function getTableByName($name): ?Table {
		$tables = $this->tables();
		return (isset ($tables[$name])) ? $tables[$name] : null;
	}

	/**
	 * @inheritdoc
	 */
	public function getFields($tableName): array {
		if (!isset ($this->_fields[$tableName])) {
			// Trigger updating schemas' tables (if needed)
			$this->tables();
		}

		return $this->_fields[$tableName];
	}

	/**
	 * @inheritdoc
	 */
	public function relations(bool $forceRetrieve = false): array {
		if ($this->_relations == null || $forceRetrieve) {
			$this->_relations = [];

			foreach ($this->tables() as $table) {
				$sql = "PRAGMA foreign_key_list($table->name)";
				$rows = $this->_db->fetchAll($sql);
				if ($rows) {
					foreach ($rows as $row) {
						$cT = $table->name;            // Child table
						$mT = $row['table'];        // Master (referenced) table

						if ($cT == null || $mT == null) {
							continue;
						}

						$cF = $row['from'];            // Child field
						$mF = $row['to'];            // Master (referenced) field

						$this->_relations[] = new Relation ($mT, $mF, $cT, $cF);
					}
				}
			}
		}

		return $this->_relations;
	}

	/**
	 * @inheritdoc
	 */
	public function getLastChanged($tableName = null): \DateTime {
		if ($this->_lastChanged === null)
			$this->_lastChanged = new \DateTime();

		return $this->_lastChanged;
	}
	#endregion
}
