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

use aneya\Core\Data\Database;
use aneya\Core\Data\DataColumn;
use aneya\Core\Data\Schema\Field;
use aneya\Core\Data\Schema\Schema;
use aneya\Core\Data\Schema\Table;

final class MongoDbSchema extends Schema {
	#region Properties
	/** @var ?MongoDb */
	protected ?Database $_db;
	#endregion

	#region Methods
	public function tables($forceRetrieve = false): array {
		if (!isset($this->_tables) || $forceRetrieve) {
			$collections = $this->_db->db()->listCollections();
			$this->_tables = [];

			foreach ($collections as $col) {
				$t = new Table ($this);
				$t->name = $col->getName();
				$t->numOfRows = null;
				$t->collation = 'utf-8';
				$t->comment = '';
				$this->_tables[] = $t;
			}
		}

		return $this->_tables;
	}

	public function getFields(Table|string $table): array {
		$tableName = strtolower(($table instanceof Table) ? $table->name : $table);
		$table = $this->getTableByName($tableName);

		$fields = array ();
		$obj = $this->_db->db()->selectCollection($tableName)->findOne();

		foreach ($obj as $key => $value) {
			if (in_array($key, array ('_id', '__class', '__version')))
				continue;

			switch (gettype($value)) {
				case 'integer':
					$dataType = DataColumn::DataTypeInteger;
					break;
				case 'float':
				case 'double':
					$dataType = DataColumn::DataTypeFloat;
					break;
				case 'string':
					$dataType = DataColumn::DataTypeString;
					break;
				default:
					$dataType = DataColumn::DataTypeObject;
			}

			$f = new Field($table);
			$f->name = $key;
			$f->defaultValue = null;
			$f->isNullable = true;
			$f->isAutoIncrement = false;
			$f->isPrimary = false;
			$f->isForeign = false;
			$f->isIndex = false;
			$f->isUnsigned = false;
			$f->dataType = $dataType;
			$f->columnType = gettype($value);
			$f->maxLength = null;
			$f->comment = '';

			$fields[] = $f;
		}

		return $fields;
	}

	public function relations($forceRetrieve = false): array {
		return $this->_relations = [];
	}

	/**
	 * (Not applicable in Mongo. Always returns 1 Jan. 1970.)
	 *
	 * @param ?string $tableName
	 * @param bool   $forceRetrieve
	 *
	 * @return \DateTime
	 */
	public function getLastChanged(string $tableName = null, bool $forceRetrieve = false): \DateTime {
		return new \DateTime('1970-01-01');
	}
	#endregion
}
