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

namespace aneya\Core\Data;

use aneya\Core\Collection;
use aneya\Core\Utils\JsonUtils;

class DataRelation implements \JsonSerializable {
	#region Constants
	const MasterTable = '1';
	const JoinInner   = 'J';
	const JoinLeft    = 'L';
	const OneToMany   = 'O';
	const ManyToMany  = 'M';

	/** Parent table has already been joined from a previous relation when building the join command */
	const ExprParentJoined = '-';
	/** Child table has already been joined from a previous relation when building the join command */
	const ExprChildJoined = 'P';
	/** Both parent and child tables have already been joined from previous relations when building the join command */
	const ExprBothJoined = 'B';
	#endregion

	#region Public properties
	/**
	 * An id for the relation.
	 *
	 * Mostly used to distinguish relations and joins that were retrieved from the database.
	 *
	 * @var int
	 */
	public $id;

	/** @var DataTable */
	public $parent;

	/** @var DataTable */
	public $child;

	/** @var string */
	public $joinType;

	/** @var string On Many-To-Many relationships it provides the name of the intermediate table that contains the linked columns */
	public $intermediate;

	/** @var int */
	public $joinOrder;

	/** @var bool */
	public $isDefault;

	/** @var DataFilterCollection|DataFilter|string|mixed Used to set additional custom criteria between parent table and child table */
	public $criteria;

	/**
	 * @var bool Indicates if the child table's columns will be also saved in the database along with the parent table's save process
	 */
	public bool $isSaveable = true;
	#endregion

	#region Protected properties
	/** @var Collection */
	public Collection $_collection;
	#endregion

	#region Constructor
	/**
	 * @param ?DataTable $parent
	 * @param ?DataTable $child
	 * @param string $joinType Valid values DataRelation::Join* constants
	 * @param int $joinOrder
	 * @param bool $isDefault
	 */
	public function __construct(DataTable $parent = null, DataTable $child = null, string $joinType = DataRelation::JoinInner, int $joinOrder = 0, bool $isDefault = false) {
		$this->parent = $parent;
		$this->child = $child;
		$this->joinType = $joinType;
		$this->joinOrder = $joinOrder;
		$this->isDefault = $isDefault;

		$this->_collection = new Collection('array');
	}
	#endregion

	#region Methods
	/**
	 * Applies configuration from an \stdClass instance.
	 *
	 * @param \stdClass $cfg
	 * @param DataSet $dataSet
	 *
	 * @return DataRelation
	 */
	public function applyJsonCfg($cfg, DataSet $dataSet): DataRelation {
		$this->id = (int)$cfg->id;
		$this->isDefault = $cfg->default === true || $cfg->default == 'true';
		$this->joinOrder = (int)$cfg->order;
		$this->isSaveable = $cfg->saveable === true || $cfg->saveable == 'true';
		$this->joinType = $cfg->type;

		$this->parent = $dataSet->tables->get($cfg->parent->name);

		// If there is child table information, apply reference relation & columns linkage configuration
		if (isset($cfg->child)) {
			$this->child = $dataSet->tables->get($cfg->child->name);

			foreach ($cfg->linkage as $linkage)
				$this->link($this->parent->columns->get($linkage->parentColumn), $this->child->columns->get($linkage->childColumn));
		}

		if (is_array($cfg->criteria)) {
			// If array is associative, represents a DataFilter object; otherwise is an array of DataFilter objects
			if (JsonUtils::isAssociativeArray($cfg->criteria))
				$this->criteria = new DataFilter($cfg->criteria->column, $cfg->criteria->operand, $cfg->criteria->value);
			else {
				$this->criteria = new DataFilterCollection();
				foreach ($cfg->criteria as $filter)
					$this->criteria->add(new DataFilter($filter->column, $filter->operand, $filter->value));
			}
		}
		else
			$this->criteria = (string)$cfg->criteria;

		return $this;
	}

	/**
	 * Adds an association between two columns in the relation
	 *
	 * @param DataColumn $parentColumn
	 * @param DataColumn $childColumn
	 *
	 * @throws \InvalidArgumentException
	 */
	public function link(DataColumn $parentColumn, DataColumn $childColumn) {
		// If relation is not master/detail, check if both linked columns exist in the DataSet
		if (!in_array($this->joinType, [DataRelation::OneToMany, DataRelation::ManyToMany])) {
			if ($this->parent->parent instanceof DataSet) {                                // If relation tables are part of a DataSet, validate columns with DataSet's column collection
				if (!$this->parent->parent->columns->contains($parentColumn)) {
					throw new \InvalidArgumentException ('Parent column is not a column of the DataSet');
				}
				if (!$this->parent->parent->columns->contains($childColumn)) {
					throw new \InvalidArgumentException ('Child column is not a column of the DataSet');
				}
			}
			else {
				if ($parentColumn->table !== $this->parent) {
					throw new \InvalidArgumentException ('Parent column is not a column of the parent table in the relationship');
				}
				if ($childColumn->table !== $this->child) {
					throw new \InvalidArgumentException ('Child column is not a column of the child table in the relationship');
				}
			}
		}
		$this->_collection->add([$parentColumn, $childColumn]);
	}

	/**
	 * Removes a column association by providing the parent's column
	 *
	 * @param DataColumn $parentColumn
	 * @param DataColumn $childColumn
	 *
	 * @return bool
	 */
	public function unlink(DataColumn $parentColumn, DataColumn $childColumn): bool {
		$idx = 0;
		foreach ($this->_collection as $pair) {
			if ($pair[0] == $parentColumn && $pair[1] == $childColumn)
				return $this->_collection->removeAt($idx);

			$idx++;
		}

		return false;
	}

	/**
	 * Returns an array with all column associations found in the relation.
	 * Array's structure is: [[DataColumn $parent, DataColumn $child], ...]
	 *
	 * @return array
	 */
	public function getLinks(): array {
		return $this->_collection->all();
	}

	/**
	 * Returns all foreign key columns of the given column.
	 *
	 * @param DataColumn $c
	 * @return DataColumn[]
	 */
	public function getForeignKeysOf(DataColumn $c): array {
		return $this->_collection
			->filter(function (array $link) use ($c) { return $link[0] === $c; })
			->map(function (array $link) { return $link[1]; });
	}

	/**
	 * Returns a join expression built in respect to the given database's syntax
	 *
	 * @param string $mode Join expression mode. Valid values are DataRelation::Expr* constants
	 *
	 * @return mixed
	 */
	public function getExpression(string $mode = DataRelation::ExprParentJoined): mixed {
		return $this->parent->db()->getRelationExpression($this, $mode);
	}
	#endregion

	#region Interface methods
	#[\ReturnTypeWillChange]
	public function jsonSerialize(): array {
		$links = [];

		foreach ($this->_collection->all() as $l)
			$links[] = [
				'parentColumn'	=> $l[0]->tag,
				'childColumn'	=> $l[1]->tag
			];

		$data = [
			'id'			=> $this->id,
			'parent'		=> [
				'schema'	=> $this->parent->db()->tag,
				'name'		=> $this->parent->name
			],
			'joinType'		=> $this->joinType,
			'links'			=> $links,
			'joinOrder'		=> $this->joinOrder,
			'isSaveable'	=> $this->isSaveable,
			'isDefault'		=> $this->isDefault
		];

		if ($this->child instanceof DataTable)
			$data = array_merge($data, [
				'child' 	=> [
					'schema'=> $this->child->db()->tag,
					'name'	=> $this->child->name
				]
			]);

		if ($this->criteria instanceof DataFilterCollection || $this->criteria instanceof DataFilter)
			$data['criteria'] = $this->criteria->jsonSerialize();
		else
			$data['criteria'] = (string)$this->criteria;

		return $data;
	}
	#endregion
}
