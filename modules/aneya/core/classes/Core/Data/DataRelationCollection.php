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

use aneya\Core\CMS;
use aneya\Core\Collection;
use aneya\Core\CollectionEventArgs;
use aneya\Core\ISortable;
use aneya\Structures\Mesh;
use aneya\Structures\Node;
use aneya\Structures\NodeCollection;

class DataRelationCollection extends Collection implements ISortable {
	#region Properties
	/**
	 * @var DataRelation[]
	 */
	protected array $_collection;

	private bool $_isSorted = false;
	#endregion

	#region Constructor
	public function __construct() {
		parent::__construct('\\aneya\\Core\\Data\\DataRelation', true);

		$this->_initCollection();
	}
	#endregion

	#region Methods
	/**
	 * Returns all relations whose parent or child table is the provided in the arguments
	 *
	 * @param DataTable|DataTable[]|DataTableCollection $tables
	 *
	 * @return DataRelation[]
	 */
	public function getByTables($tables): array {
		if ($tables instanceof DataTableCollection) {
			return $this->getByTables($tables->all());
		}
		elseif ($tables instanceof DataTable) {
			$relations = array ();
			foreach ($this->_collection as $c) {
				if ($c->parent === $tables || $c->child === $tables)
					$relations[] = $c;
			}

			return $relations;
		}
		elseif (is_array($tables)) {
			$relations = array ();
			foreach ($tables as $tbl) {
				foreach ($this->_collection as $c) {
					if ($c->parent == $tbl || $c->child == $tbl)
						$relations[] = $c;
				}
			}

			return $relations;
		}
		else {
			CMS::logger()->warning("Invalid argument in " . __METHOD__);
			return [];
		}
	}

	/**
	 * Returns all relations whose parent table is the provided in the arguments
	 *
	 * @param DataTable $parent
	 *
	 * @return DataRelation[]
	 */
	public function getByParent(DataTable $parent): array {
		$relations = array ();
		foreach ($this->_collection as $c) {
			if ($c->parent === $parent)
				$relations[] = $c;
		}

		// Sort relations by priority
		usort($relations, function (DataRelation $a, DataRelation $b) {
			return $a->joinOrder < $b->joinOrder ? -1 : ($a->joinOrder > $b->joinOrder ? 1 : ($a->isDefault ? -1 : ($b->isDefault ? 1 : 0)));
		});

		return $relations;
	}

	/**
	 * Returns all relations whose child table is the provided in the arguments
	 *
	 * @param DataTable $child
	 *
	 * @return DataRelation[]
	 */
	public function getByChild(DataTable $child): array {
		$relations = array ();
		foreach ($this->_collection as $c) {
			if ($c->child === $child)
				$relations[] = $c;
		}

		// Sort relations by priority
		usort($relations, function (DataRelation $a, DataRelation $b) {
			return $a->joinOrder < $b->joinOrder ? -1 : ($a->joinOrder > $b->joinOrder ? 1 : ($a->isDefault ? -1 : ($b->isDefault ? 1 : 0)));
		});

		return $relations;
	}

	/**
	 * Returns all tables in the collection, sorted by relation priority
	 *
	 * @param bool $omitNonSaveable If true, will omit from the resulting collection any tables that are flagged as not saveable.
	 *
	 * @return DataTableCollection
	 */
	public function getTables(bool $omitNonSaveable = false): DataTableCollection {
		$this->sort();
		$tables = new DataTableCollection();
		foreach ($this->_collection as $r) {
			$tables->add($r->parent);
			if ($r->child != null && (!$omitNonSaveable || $r->isSaveable))
				$tables->add($r->child);
		}

		return $tables;
	}

	/**
	 * @inheritdoc
	 * @return DataRelation[]
	 */
	public function all(callable $f = null): array {
		return parent::all($f);
	}

	/**
	 * @inheritdoc
	 */
	public function first(callable $f = null): ?DataRelation {
		return parent::first($f);
	}

	/**
	 * @inheritdoc
	 */
	public function last(callable $f = null): ?DataRelation {
		return parent::last($f);
	}

	/** Returns the master (default) table. */
	public function root(): DataTable|DataSet|null {
		$mesh = $this->mesh();
		if ($mesh === null)
			return null;

		$node = $mesh->parseNodes()[0];

		// If there is a root node, return it; otherwise (relations are complex and there's no specific root) return the first node
		return ($node !== null) ? $node->object() : $mesh->nodes->first()->object();
	}

	/**
	 * Returns table relations as a Mesh representation.
	 *
	 * @param bool $omitNonSaveable If true, will omit from the resulting collection any tables that are flagged as not saveable.
	 *
	 * @return Mesh
	 */
	public function mesh(bool $omitNonSaveable = false): Mesh {
		$mesh = new Mesh();

		if (count($this->_collection) == 0)
			return $mesh;

		foreach ($this->_collection as $rel) {
			$node = $mesh->nodes->get($rel->parent);
			if ($node == null) {
				$node = new Node($mesh, null, null, $rel->parent);
				$mesh->nodes->add($node);
			}

			// Omit non-saveable tables, if requested
			if ($rel->child === null || ($omitNonSaveable && !$rel->isSaveable))
				continue;

			$node = $mesh->nodes->get($rel->child);
			if ($node == null) {
				$node = new Node($mesh, null, null, $rel->child);
				$mesh->nodes->add($node);
			}
		}

		foreach ($this->_collection as $rel) {
			$pNode = $mesh->nodes->get($rel->parent);
			$cNode = $mesh->nodes->get($rel->child);
			$mesh->link($pNode, $cNode, $rel->joinOrder);
		}

		return $mesh;
	}
	#endregion

	#region Internal methods
	private function _initCollection() {
		#region Flag both DataTables' columns as Master/not Master as the relations have changed
		$this->on(Collection::EventOnItemAdded, function (CollectionEventArgs $args) {
			$tables = [];
			foreach ($this->_collection as $rel) {
				if (!in_array($rel->parent, $tables, true)) {                                        // Pass true to strict comparison and avoid too-deep circular reference exceptions
					$this->_markMasterFlag($rel->parent);
					$tables[] = $rel->parent;
				}

				if ($rel->child instanceof DataTable && !in_array($rel->child, $tables, true)) {    // Pass true to strict comparison and avoid too-deep circular reference exceptions
					$this->_markMasterFlag($rel->child);
					$tables[] = $rel->child;
				}
			}
		});
		#endregion
	}

	private function _markMasterFlag(DataTable $tbl) {
		$isMaster = (!($tbl->parent instanceof DataSet) || ($tbl->equals($tbl->parent->masterTable())));

		foreach ($tbl->columns->all() as $col) {
			$col->isMaster = $isMaster;
		}
	}
	#endregion

	#region Interface implementation(s)
	/**
	 * @inheritDoc
	 */
	public function sort(): DataRelationCollection {
		$nodes = $this->mesh()->parseNodes();

		#region Recalculate join orders based on relations' order
		if ($nodes instanceof NodeCollection) {
			$order = 0;

			// Clear join order information
			foreach ($this->_collection as $rel) {
				$rel->joinOrder = null;
			}

			// Build new join order information
			$nodes->map(function (Node $node) use (&$order) {
				/** @var DataRelation rel */
				$rel = $node->object()?->relation;
				if ($rel == null)
					return;

				// Don't re-order if already ordered previously in the loop
				if ($rel->joinOrder === null)
					$rel->joinOrder = $order++;
			});
		}
		#endregion

		usort($this->_collection, function (DataRelation $a, DataRelation $b) {
			if ($a->isDefault)
				return -1;
			elseif ($b->isDefault)
				return 1;
			elseif ($a->joinOrder == $b->joinOrder) {
				return 0;
			}
			return ($a->joinOrder < $b->joinOrder) ? -1 : 1;
		});
		$this->rewind();

		$this->_isSorted = true;

		return $this;
	}
	#endregion

	#region Magic methods
	public function __wakeup() {
		$this->_initCollection();
	}
	#endregion
}
