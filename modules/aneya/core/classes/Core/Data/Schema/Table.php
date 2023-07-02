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

class Table extends DataObject {
	#region Properties
	/** @var Schema */
	protected $_schema;

	/** @var Field[] */
	protected $_fields;

	/** @var Relation[] */
	protected $_relations;


	/** @var string */
	public $name;

	/** @var int */
	public $numOfRows;

	/** @var string */
	public $collation;

	/** @var \DateTime */
	public $lastChanged;

	/** @var string */
	public $comment;

	protected static $__classProperties = [
		'deny' => ['_schema']
	];
	#endregion

	#region Constructor
	public function __construct(Schema $schema) {
		$this->_schema = $schema;
	}
	#endregion

	#region Methods
	/**
	 * @return Schema
	 */
	public function getSchema() {
		return $this->_schema;
	}

	/**
	 * @param Schema $schema
	 */
	public function setSchema(Schema $schema) {
		$this->_schema = $schema;
	}

	/**
	 * @param bool $forceRetrieve
	 *
	 * @return Field[]
	 */
	public function getFields($forceRetrieve = false) {
		if ($this->_fields == null || $forceRetrieve)
			$this->_fields = $this->_schema->getFields($this->name);

		return $this->_fields;
	}

	/**
	 * @param string $fieldName
	 *
	 * @return Field|null
	 */
	public function getFieldByName($fieldName) {
		$fields = $this->getFields();
		return (isset ($fields[$fieldName])) ? $fields[$fieldName] : null;
	}

	public function getRelations($forceRetrieve = false) {
		if ($this->_relations == null || $forceRetrieve) {
			$this->_relations = $this->_schema->getRelationsByMasterTable($this);
		}

		return $this->_relations;
	}
	#endregion
}
