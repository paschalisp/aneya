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
 * Portions created by Paschalis Ch. Pagonidis are Copyright (C) 2007-2022
 * Paschalis Ch. Pagonidis. All Rights Reserved.
 */

namespace aneya\Core\Data\Drivers;

use aneya\Core\Data\Schema\Relation;
use aneya\Core\Data\Schema\Schema;

final class MSSQLSchema extends Schema {
	const DT_INTEGER = 1;
	const DT_FLOAT = 2;
	const DT_TEXT = 3;
	const DT_DATE = 4;
	const DT_DATETIME = 5;

	public function tables () {
		$sql = "SHOW TABLES";
		$rows = $this->_db->fetchAll ($sql);
		$tables = array ();

		if ($rows) foreach ($rows as $row) {
			$r = array_values ($row);
			$tables[] = $r[0];
		}

		return $tables;
	}

	public function getFields ($table_name) {
		$database_name = $this->_db->escape ($this->_db->getDatabaseName());
		$table_name = $this->_db->escape ($table_name);
		$sql = "SELECT COLUMN_NAME, COLUMN_DEFAULT, IS_NULLABLE, DATA_TYPE, CHARACTER_MAXIMUM_LENGTH, COLUMN_TYPE, COLUMN_KEY, EXTRA, COLUMN_COMMENT FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA='$database_name' AND TABLE_NAME='$table_name'";
		return $this->_db->fetchAll ($sql);
	}

	public function relations ($forceRetrieve = false) {
		$sql = "SELECT TABLE_NAME AS master_table_name, COLUMN_NAME AS master_field_name, REFERENCED_TABLE_NAME AS foreign_table_name, REFERENCED_COLUMN_NAME AS foreign_field_name
				FROM information_schema.KEY_COLUMN_USAGE
				WHERE TABLE_SCHEMA=:schema AND REFERENCED_TABLE_NAME IS NOT NULL";

		$rows = $this->_db->fetchAll ($sql, [':schema' => $this->schemaName()]);
		if ($rows)
			foreach ($rows as $row) {
				$cT = $row['TABLE_NAME'];				// Child table
				$mT = $row['REFERENCED_TABLE_NAME'];	// Master (referenced) table

				if ($cT == null || $mT == null)
					continue;

				$cF = $row['COLUMN_NAME'];				// Child field
				$mF = $row['REFERENCED_COLUMN_NAME'];	// Master (referenced) field

				$this->_relations[] = new Relation ($mT, $mF, $cT, $cF);
			}

		return $this->_relations;
	}

	public function getLastChanged ($table = null) {
		// TODO: Implement method
	}
}
