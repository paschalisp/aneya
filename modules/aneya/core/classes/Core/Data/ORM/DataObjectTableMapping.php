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
use aneya\Core\Data\DataSet;
use aneya\Core\Data\Schema\Schema;

class DataObjectTableMapping {
	#region Properties
	/** @var Schema */
	public $schema;
	/** @var string The fully qualified Class name of the object that is being mapped */
	public string $className;
	/** @var string The name of the database table that is being mapped */
	public string $tableName;
	/** @var DataObjectPropertyCollection */
	public DataObjectPropertyCollection $properties;
	#endregion

	#region Constructor
	/**
	 * @param Schema|string	$schema_tag_or_obj
	 * @param string $className
	 * @param string $tableName
	 */
	public function __construct ($schema_tag_or_obj, string $className, string $tableName) {
		$this->schema = ($schema_tag_or_obj instanceof Schema) ? $schema_tag_or_obj : CMS::db($schema_tag_or_obj);
		$this->className = $className;
		$this->tableName = $tableName;

		$this->properties = new DataObjectPropertyCollection();
	}
	#endregion

	#region Methods
	/**
	 * Generates and returns a DataSet representation of the data/object table mapping
	 */
	public function toDataSet(): DataSet {
		$ds = $this->schema->getDataSet($this->tableName);

		#region Remove any DataSet columns that are not mapped in the data/object table mapping
		$cols = [];

		foreach ($this->properties->all() as $prop) {
			$cols[] = $prop->fieldName;
		}

		foreach ($ds->columns->all() as $col) {
			if (!in_array($col->name, $cols))
				$ds->columns->remove($col);
		}
		#endregion

		return $ds;
	}
	#endregion
}
