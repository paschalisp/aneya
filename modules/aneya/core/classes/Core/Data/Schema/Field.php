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

namespace aneya\Core\Data\Schema;


use aneya\Core\Data\ORM\DataObject;

class Field extends DataObject {
	#region Properties
	/** @var Table */
	public $table;

	/** @var string */
	public $name;

	/** @var mixed */
	public $defaultValue;

	/** @var bool */
	public $isNullable;

	/** @var bool */
	public $isUnsigned;

	/** @var bool */
	public $isPrimary;

	/** @var bool */
	public $isForeign;

	/** @var bool */
	public $isIndex;

	/** @var bool */
	public $isAutoIncrement;

	/** @var string */
	public $dataType;

	/** @var string */
	public $subDataType;

	/** @var int */
	public $maxLength;

	/** @var string */
	public $columnType;

	/** @var string */
	public $comment;

	/** @var Field */
	public $referencesField;

	protected static $__classProperties = [
		'deny' => ['table', 'referencesField']
	];
	#endregion

	#region Constructor
	public function __construct(Table $table) {
		$this->table = $table;
	}
	#endregion

	#region Methods
	public function getSchema(): Schema {
		return $this->table->getSchema();
	}

	/**
	 * @return Field[]
	 */
	public function getReferencedFields(): array {
		$fields = array ();
		$relations = $this->getSchema()->getRelationsByMasterField($this);
		foreach ($relations as $rel)
			$fields[] = $rel->foreignField;

		return $fields;
	}

	/** Returns the SchemaField this field points as a foreign key */
	public function getMasterField(): ?Field {
		$relations = $this->getSchema()->getRelationsByForeignField($this);
		return (count($relations) > 0) ? $relations[0]->masterField : null;
	}
	#endregion
}
