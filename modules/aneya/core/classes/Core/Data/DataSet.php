<?php
/*
 * aneya CMS & Framework
 * Copyright (c) 2011-2022 Paschalis Pagonidis <p.pagonides@gmail.com>
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
 * Portions created by Paschalis Ch. Pagonidis are Copyright (C) 2011-2022
 * Paschalis Ch. Pagonidis. All Rights Reserved.
 */

namespace aneya\Core\Data;

use aneya\Core\CMS;
use aneya\Core\Collection;
use aneya\Core\CollectionEventArgs;
use aneya\Core\Data\ORM\IDataObject;
use aneya\Core\EventStatus;
use aneya\Structures\Mesh;
use aneya\Structures\Node;

class DataSet extends DataTable {
	#region Properties
	public DataTableCollection $tables;
	public DataRelationCollection $relations;
	public DataFilterCollection $filtering;
	public DataSortingCollection $sorting;
	public DataColumnCollection $grouping;
	public DataFilterCollection $having;
	#endregion

	#region Constructor
	/**
	 * @param DataTable|DataTable[]|DataTableCollection $tables
	 * @param DataColumn[]|DataColumnCollection         $columns
	 */
	public function __construct($tables = null, $columns = null) {
		if ($tables != null) {
			if (is_array($tables)) {
				$this->tables = new DataTableCollection ();
				foreach ($tables as $table)
					$this->tables->add($table);
			}
			elseif ($tables instanceof DataTableCollection)
				$this->tables = $tables;
			elseif ($tables instanceof DataTable) {
				$this->tables = new DataTableCollection ();
				$this->tables->add($tables);
			}
			else
				$this->tables = new DataTableCollection ();
		}
		else
			$this->tables = new DataTableCollection();

		if ($columns != null) {
			if (is_array($columns)) {
				$this->columns = new DataColumnCollection ();
				foreach ($columns as $col)
					$this->columns->add($col);
			}
			elseif ($columns instanceof DataColumnCollection)
				$this->columns = $columns;
		}
		else {
			$this->columns = new DataColumnCollection();
		}

		$this->rows = new DataRowCollection ();
		$this->relations = new DataRelationCollection ();
		$this->children = new DataRelationCollection();
		$this->filtering = new DataFilterCollection ();
		$this->sorting = new DataSortingCollection ();
		$this->grouping = new DataColumnCollection ();
		$this->having = new DataFilterCollection ();

		$this->rows->parent = $this;

		foreach ($this->tables->all() as $tbl) {
			$isMaster = ($tbl->equals($this->masterTable()));
			foreach ($tbl->columns->all() as $col) {
				$this->columns->add($col);
				$col->isMaster = $isMaster;
			}
		}
		// Initialize listeners on children table collections
		$this->_initTables();

		$this->hooks()->register([
									 self::EventOnRetrieving, self::EventOnRetrieve, self::EventOnRetrieved,
									 self::EventOnSaving, self::EventOnSave, self::EventOnSaved
								 ]);
	}

	protected function _initTables() {
		#region Auto-add/remove columns from joined tables' column collection
		$masterTbl = $this->masterTable();

		foreach ($this->tables->all() as $tbl) {
			$tbl->parent = $this;

			#region Listen for tables' column collection changes so that DataSet::columns collection is always synchronized
			$tbl->columns->on(Collection::EventOnItemAdded, function (CollectionEventArgs $args) use ($tbl, $masterTbl) {
				$this->columns->add($col = $args->newItem);
				$col->isMaster = ($tbl === $masterTbl);
			});
			$tbl->columns->on(Collection::EventOnItemRemoved, function (CollectionEventArgs $args) {
				$this->columns->remove($args->oldItem);
			});
			#endregion
		}

		$this->tables->on(Collection::EventOnItemAdded, function (CollectionEventArgs $args) {
			/** @var DataTable $tbl */
			$tbl = $args->newItem;
			$tbl->parent = $this;

			$isMaster = ($tbl === $this->masterTable());

			// Add new table's columns
			foreach ($tbl->columns->all() as $col) {
				$this->columns->add($col);
				$col->isMaster = $isMaster;
			}

			#region Listen for tables' column collection changes so that DataSet::columns collection is always synchronized
			$tbl->columns->on(Collection::EventOnItemAdded, function (CollectionEventArgs $args) use ($isMaster) {
				$this->columns->add($col = $args->newItem);
				$col->isMaster = $isMaster;
			});
			$tbl->columns->on(Collection::EventOnItemRemoved, function (CollectionEventArgs $args) {
				$this->columns->remove($args->oldItem);
			});
			#endregion
		});

		$this->tables->on(Collection::EventOnItemRemoved, function (CollectionEventArgs $args) {
			/** @var DataTable $tbl */
			$tbl = $args->oldItem;
			if ($tbl->parent === $this) {
				$tbl->parent = null;
			}

			// Remove old table's columns
			foreach ($tbl->columns as $col) {
				$this->columns->remove($col);
			}
		});
		#endregion
	}
	#endregion

	#region Methods
	/** Gets/sets the table's database. */
	public function db(Database $db = null): Database {
		if ($db instanceof Database) {
			return parent::db($db);
		}

		if (strlen($this->_dbTag) == 0 || $this->_db === null) {
			$this->_dbTag = $this->masterTable()->_dbTag;
			$this->_db = $this->masterTable()->_db;
		}

		return (strlen($this->_dbTag) > 0) ? CMS::db($this->_dbTag) : $this->_db;
	}

	/**
	 * @param DataFilterCollection|DataFilter|DataFilter[]		$filters Additional filters to apply along to DataSet's own filters
	 * @param DataSortingCollection|DataSorting|DataSorting[]	$sorting Sorting rules to apply overriding any sorting defined in DataSet.
	 * @param int|null $start
	 * @param int|null $limit
	 *
	 * @return DataSet
	 */
	public function retrieve(DataFilterCollection|DataFilter|array $filters = null, DataSortingCollection|DataSorting|array $sorting = null, int $start = null, int $limit = null): DataSet {
		$this->rows->clear();

		/** @var DataSetConnectionSet[] $connections */
		$connections = [];

		#region Combine DataSet's filters with any additional filters passed in the arguments
		$filtering = new DataFilterCollection();
		if ($this->filtering->count() > 0) {
			$filtering->add($this->filtering);
		}
		if ($filters != null) {
			if ($filters instanceof DataFilter || ($filters instanceof DataFilterCollection && $filters->count() > 0)) {
				$filtering->add($filters);
			}
			elseif (is_array($filters)) {
				$fCollection = new DataFilterCollection();
				$fCollection->addRange($filters);
				$filtering->add($fCollection);
			}
		}
		#endregion

		#region Group tables by database connection
		foreach ($this->tables->all() as $tbl) {
			$found = false;
			foreach ($connections as $conn) {
				if ($conn->db === $tbl->db() && $conn->db->supportsJoins()) {
					$conn->tables->add($tbl);
					$conn->columns->addRange($tbl->columns->all());

					$found = true;
					break;
				}
			}
			if (!$found) {
				$conn = new DataSetConnectionSet();
				$conn->db = $tbl->db();
				$conn->tables = new DataTableCollection();
				$conn->tables->add($tbl);
				$conn->columns = new DataColumnCollection($tbl->columns->all());
				$conn->listColumns = new DataColumnCollection();
				$conn->relations = new DataRelationCollection();
				$conn->filters = new DataFilterCollection();
				$conn->sorting = new DataSortingCollection();

				$conn->filters->operand = $this->filtering->operand;

				$connections[] = $conn;
			}
		}
		#endregion

		if ($sorting instanceof DataSorting) {            // Convert sorting to a sorting collection
			$srt = $sorting;
			$sorting = new DataSortingCollection();
			$sorting->add($srt);
		}
		elseif (is_array($sorting)) {
			$srt = $sorting;
			$sorting = new DataSortingCollection();
			$sorting->addRange($srt);
		}
		elseif (($sorting === null || !($sorting instanceof DataSortingCollection) || $sorting->count() == 0) && $this->sorting->count() > 0) {
			$sorting = new DataSortingCollection();
			foreach ($this->sorting->all() as $sort) {
				$sorting->add($sort);
			}
		}
		$args = new DataTableRetrieveEventArgs($this);
		$args->filters = $filtering;
		$args->sorting = $sorting;
		$args->numOfConnections = count($connections);

		$this->trigger(self::EventOnRetrieving, $args);

		$fromTime = microtime(true);

		$triggers = $this->trigger(self::EventOnRetrieve, $args);
		$isHandled = false;
		foreach ($triggers as $t) {
			if ($t->isHandled) {
				$isHandled = true;
				break;
			}
		}

		if (!$isHandled) {
			#region Set up relations, columns, filters and sorting information per connection
			foreach ($connections as $conn) {
				#region Setup relations
				foreach ($this->relations->all() as $r) {
					if (in_array($r->parent, $conn->tables->all(), true) && in_array($r->child, $conn->tables->all(), true)) {        // Pass true to strict comparison and avoid too-deep circular reference exceptions
						$conn->relations->add($r);
					}
				}

				$conn->relations->sort();
				#endregion

				#region Setup columns
				foreach ($conn->columns->filter(function (DataColumn $f) { return $f->isActive && !$f->isFake; }) as $c) {
					$conn->listColumns->add($c);
				}
				#endregion

				#region Setup filters
				foreach ($filtering->all() as $f) {
					if ($f instanceof DataFilter) {
						if (in_array($f->column, $conn->columns->all(), true) && (!($f->value instanceof DataColumn) || in_array($f->value, $conn->columns->all(), true))) {    // Pass true to strict comparison and avoid too-deep circular reference exceptions
							$conn->filters->add($f);
						}
					}
					elseif ($f instanceof DataFilterCollection && $f->refersToSchema($conn->db)) {
						$conn->filters->add($f);
					}
				}
				#endregion

				#region Setup sorting
				if ($sorting != null) {
					foreach ($sorting->all() as $s) {
						if (in_array($s->column, $conn->columns->all(), true))        // Pass true to strict comparison and avoid too-deep circular reference exceptions
							$conn->sorting->add($s);
					}
				}
				#endregion

				#region Calculate row count for each connection
				$conn->dataSet = new DataSet($conn->tables, $conn->columns);
				$conn->count = $conn->db->retrieveCnt($conn->dataSet, $conn->tables, $conn->relations, $conn->filters);
				#endregion
			}
			#endregion

			#region Retrieve rows per connection
			if (count($connections) == 1) {
				$conn = $connections[0];
				$num = $conn->db->retrieve($conn->dataSet, $conn->tables, $conn->relations, $conn->columns, $conn->listColumns, $conn->filters, $conn->sorting, $this->grouping, $this->having, $start, $limit);
				$conn->rows = $conn->dataSet->rows;

				// Add connection's rows to the DataSet
				foreach ($conn->rows as $row) {
					$row->parent = $this;
					$row->source = DataRow::SourceDatabase;
					$this->rows->addWithState($row, DataRow::StateUnchanged);
				}
			}
			else {
				// Sort the connections by root connections and by those with the less results to the most dependent and with the most results
				$mesh = $this->_meshConnections($connections, $this->relations, $filtering);

				$sortedConnections = $this->_sortConnections($mesh);
				if ($sortedConnections === false) { // If sorting resulted in false value, parsing the connections tree was failed
					$toTime = microtime(true);
					$args->duration = (float)$toTime - (float)$fromTime;
					$args->numOfRows = $this->rows->count();

					$this->trigger(self::EventOnRetrieved, $args);

					$this->isRetrieved = true;

					return $this;
				}

				$max = count($sortedConnections);

				// Retrieve rows per connection, then search for relations and apply extra filters to the related connections
				for ($num = 0; $num < $max; $num++) {
					// Currently processed connection
					$conn = $sortedConnections[$num];

					// Check if connection has already been processed
					if ($conn->rows != null)
						continue;

					// Retrieve rows in the connection
					$numRows = $conn->db->retrieve($conn->dataSet, $conn->tables, $conn->relations, $conn->columns, $conn->listColumns, $conn->filters);
					$conn->rows = $conn->dataSet->rows;

					// Store all values per column that were retrieved from the database
					$conn->values = array ();

					// Filter all related connections by current connection's values
					foreach ($conn->node->outgoing() as $link) {
						if (!isset ($link->filters) || !($link->filters instanceof DataFilterCollection))
							continue;

						$linkFilters = $link->filters;
						foreach ($linkFilters->all() as $filter) {
							/** @var DataFilter $f */
							$f = $filter;
							for ($rNum = $num + 1; $rNum < $max; $rNum++) {
								if (!$sortedConnections[$rNum]->columns->contains($f->column))
									continue;

								// If parent connection didn't fetch any row, all related connections should also return no records
								if ($numRows == 0) {
									$sortedConnections[$rNum]->filters->add(new DataFilter($f->column, DataFilter::FalseFilter));
								}
								else {
									// Create collection of additional (join) filters to apply to the related connection
									/** @var DataColumn $pCol */
									$pCol = $f->value;
									$cCol = $f->column;

									// Store all retrieved values of the linked column
									if (!isset ($conn->values[$pCol->tag])) {
										foreach ($conn->rows->all() as $row) {
											$conn->values[$pCol->tag][] = $value = $row->getValue($pCol);
										}
										//Add the additional filters to the child connection
										$sortedConnections[$rNum]->filters->add(new DataFilter($cCol, DataFilter::InList, $conn->values[$pCol->tag]));
									}
								}
							}
						}
					}
				}

				#region Combine all retrieved (per connection) rows into a single set
				$masterRows = new DataRowCollection();
				foreach ($sortedConnections[0]->rows->all() as $row) {
					$mRow = $this->newRow(false);

					foreach ($row->parent->columns->all() as $col) {
						$mRow->setValue($col, $row->getValue($col));
					}

					$masterRows->add($mRow);
				}

				$max = count($sortedConnections);
				for ($num = 1; $num < $max; $num++) {
					$this->_applyConnectionResults($sortedConnections[$num], $masterRows, $mesh);
				}

				// Filter the final records
				/** @var DataRowCollection $masterRows */
				$masterRows = $masterRows->match($this->filtering)->match($filters);

				// Limit the results to the given range (if any)
				$max = $masterRows->count();
				if ($start == null)
					$start = 0;
				else
					$start = ($start >= 0) ? $start : 0;
				if ($limit == null)
					$limit = $max;
				else
					$limit = ($limit <= $max) ? $limit : $max;

				for ($num = $start; $num < $limit; $num++) {
					$row = $masterRows->itemAt($num);
					if ($row == null || isset($row->_notFound)) {
						if ($limit < $max)
							$limit++;
						continue;
					}
					$row->parent = $this;
					$row->source = DataRow::SourceDatabase;
					$this->rows->addWithState($row, DataRow::StateUnchanged);
				}
				#endregion
			}
			#endregion
		}

		$toTime = microtime(true);
		$args->duration = (float)$toTime - (float)$fromTime;
		$args->numOfRows = $this->rows->count();

		#region Retrieve children DataTables, if any
		if ($this->children->count() > 0) {
			#region Collect all parent values in one array in order to limit the number of additional queries
			$parentValues = [];
			foreach ($this->children->all() as $rel) {
				foreach ($rel->getLinks() as $link) {
					/** @var DataColumn $pCol */
					$pCol = $link[0];
					if (!isset ($parentValues[$pCol->tag])) {
						$values = [];
						foreach ($this->rows->all() as $row) {
							$values[] = $row->getValue($pCol);
						}
						$parentValues[$pCol->tag] = array_unique($values);
					}
				}
			}
			#endregion

			foreach ($this->children->all() as $rel) {
				$filters = new DataFilterCollection();
				foreach ($rel->getLinks() as $link) {
					/** @var DataColumn $pCol */
					$pCol = $link[0];
					/** @var DataColumn $cCol */
					$cCol = $link[1];
					$filters->add(new DataFilter($cCol, DataFilter::InList, $parentValues[$pCol->tag]));
				}

				$rel->child->retrieve($filters);
			}
		}
		#endregion

		#region Generate a mapped object per row, if conditions are met
		if ($this->autoGenerateObjects) {
			$this->generateObjects();
		}
		#endregion

		$this->trigger(self::EventOnRetrieved, $args);

		$this->isRetrieved = true;

		return $this;
	}

	/**
	 * Retrieves the count of rows from database that match the tables' relation and filters
	 *
	 * @param DataFilterCollection|DataFilter|DataFilter[] $filters
	 * @param DataColumnCollection|DataColumn|DataColumn[] $grouping
	 * @param DataFilterCollection|DataFilter|DataFilter[] $having
	 *
	 * @return int
	 */
	public function retrieveCnt(DataFilterCollection|DataFilter|array $filters = null, DataColumnCollection|DataColumn|array $grouping = null, DataFilterCollection|DataFilter|array $having = null): int {
		/** @var DataSetConnectionSet[] $connections */
		$connections = array ();

		#region Combine DataSet's filters with any additional filters passed in the arguments
		// Add the additional filters to the DataSet's filtering
		$filtering = new DataFilterCollection();
		foreach ($this->filtering->all() as $f)
			$filtering->add($f);

		if ($filters != null) {
			if ($filters instanceof DataFilterCollection) {
				foreach ($filters->all() as $f)
					$filtering->add($f);
			}
			elseif (is_array($filters)) {
				$filtering->addRange($filters);
			}
			else {
				$filtering->add($filters);
			}
		}
		#endregion

		#region Group tables by database connection
		foreach ($this->tables->all() as $tbl) {
			$found = false;
			foreach ($connections as $conn) {
				if ($conn->db === $tbl->db() && $conn->db->supportsJoins()) {
					$conn->tables->add($tbl);
					foreach ($tbl->columns->all() as $c)
						$conn->columns->add($c);

					$found = true;
					break;
				}
			}
			if (!$found) {
				$conn = new DataSetConnectionSet();
				$conn->db = $tbl->db();
				$conn->tables = new DataTableCollection();
				$conn->tables->add($tbl);
				$conn->columns = new DataColumnCollection($tbl->columns->all());
				$conn->relations = new DataRelationCollection();
				$conn->filters = new DataFilterCollection();
				$conn->count = 0;
				$conn->dataSet = null;
				$conn->rows = null;

				$connections[] = $conn;
			}
		}
		#endregion

		#region Retrieve data for each connection
		foreach ($connections as $conn) {
			#region Setup relations
			foreach ($this->relations->all() as $r) {
				if (in_array($r->parent, $conn->tables->all(), true) && in_array($r->child, $conn->tables->all(), true))        // Pass true to strict comparison and avoid too-deep circular reference exceptions
					$conn->relations->add($r);
			}

			$conn->relations->sort();
			#endregion

			#region Setup filters
			foreach ($filtering->all() as $f) {
				if ($f instanceof DataFilter) {                    // Connection contains column
					if (in_array($f->column, $conn->columns->all(), true) && (!($f->value instanceof DataColumn) || in_array($f->value, $conn->columns->all(), true)))    // Pass true to strict comparison and avoid too-deep circular reference exceptions
						$conn->filters->add($f);
				}
				elseif ($f instanceof DataFilterCollection) {    // Connection contains all columns that participate in the filter collection
					if (count(array_diff($f->allColumns()->all(), $conn->columns->all())) == 0)
						$conn->filters->add($f);
				}
			}
			#endregion

			#region Retrieve rows
			$conn->dataSet = new DataSet($conn->tables);
			$conn->count = $conn->db->retrieveCnt($conn->dataSet, $conn->tables, $conn->relations, $conn->filters);
			#endregion
		}
		#endregion

		#region Combine retrieved data
		if (count($connections) == 1) {
			$conn = $connections[0];
			return (int)$conn->count;
		}
		else {
			// TODO: Finish combining records count from multiple connections
			return (int)$connections[0]->count;
		}
		#endregion
	}

	/** @inheritDoc */
	public function retrieveQuery(DataFilterCollection|DataFilter|array $filters = null, DataSortingCollection|DataSorting|array $sorting = null, int $start = null, int $limit = null): mixed {
		/** @var DataSetConnectionSet[] $connections */
		$connections = array ();

		#region Combine DataSet's filters with any additional filters passed in the arguments
		$filtering = new DataFilterCollection();
		if ($this->filtering->count() > 0) {
			$filtering->add($this->filtering);
		}
		if ($filters != null && ($filters instanceof DataFilter || ($filters instanceof DataFilterCollection && $filters->count() > 0))) {
			$filtering->add($filters);
		}
		elseif (is_array($filters)) {
			foreach ($filters as $filter) {
				$filtering->add($filter);
			}
		}
		#endregion

		#region Group tables by database connection
		foreach ($this->tables->all() as $tbl) {
			$found = false;
			foreach ($connections as $conn) {
				if ($conn->db === $tbl->db() && $conn->db->supportsJoins()) {
					$conn->tables->add($tbl);
					foreach ($tbl->columns->all() as $c)
						$conn->columns->add($c);

					$found = true;
					break;
				}
			}
			if (!$found) {
				$conn = new DataSetConnectionSet();
				$conn->db = $tbl->db();
				$conn->tables = new DataTableCollection();
				$conn->tables->add($tbl);
				$conn->columns = new DataColumnCollection($tbl->columns->all());
				$conn->listColumns = new DataColumnCollection();
				$conn->relations = new DataRelationCollection();
				$conn->filters = new DataFilterCollection();
				$conn->sorting = new DataSortingCollection();

				$conn->filters->operand = $this->filtering->operand;

				$connections[] = $conn;
			}
		}
		#endregion

		if ($sorting instanceof DataSorting) {            // Convert sorting to a sorting collection
			$srt = $sorting;
			$sorting = new DataSortingCollection();
			$sorting->add($srt);
		}
		elseif (is_array($sorting)) {
			$srt = $sorting;
			$sorting = new DataSortingCollection();
			foreach ($srt as $sort) {
				$sorting->add($sort);
			}
		}
		elseif (!($sorting instanceof DataSortingCollection) && $this->sorting->count() > 0) {
			$sorting = new DataSortingCollection();
			foreach ($this->sorting->all() as $sort) {
				$sorting->add($sort);
			}
		}

		#region Set up relations, columns, filters and sorting information per connection
		foreach ($connections as $conn) {
			#region Setup relations
			foreach ($this->relations->all() as $r) {
				if (in_array($r->parent, $conn->tables->all(), true) && in_array($r->child, $conn->tables->all(), true))        // Pass true to strict comparison and avoid too-deep circular reference exceptions
					$conn->relations->add($r);
			}

			$conn->relations->sort();
			#endregion

			#region Setup columns
			foreach ($conn->columns as $c) {
				if ($c->isFake || !$c->isActive)
					continue;

				$conn->listColumns->add($c);
			}
			#endregion

			#region Setup filters
			foreach ($filtering->all() as $f) {
				if ($f instanceof DataFilter) {
					if (in_array($f->column, $conn->columns->all(), true) && (!($f->value instanceof DataColumn) || in_array($f->value, $conn->columns->all(), true)))    // Pass true to strict comparison and avoid too-deep circular reference exceptions
						$conn->filters->add($f);
				}
				elseif ($f instanceof DataFilterCollection && $f->count() > 0) {
					$conn->filters->add($f);
				}
			}
			#endregion

			#region Setup sorting
			if ($sorting != null) {
				foreach ($sorting->all() as $s) {
					if (in_array($s->column, $conn->columns->all(), true))        // Pass true to strict comparison and avoid too-deep circular reference exceptions
						$conn->sorting->add($s);
				}
			}
			#endregion

			#region Calculate row count for each connection
			$conn->dataSet = new DataSet($conn->tables, $conn->columns);
			$conn->count = $conn->db->retrieveCnt($conn->dataSet, $conn->tables, $conn->relations, $conn->filters);
			#endregion
		}
		#endregion

		#region Generate queries per connection
		if (count($connections) == 1) {
			$conn = $connections[0];
			return $conn->query = $conn->db->retrieveQuery($conn->dataSet, $conn->tables, $conn->relations, $conn->columns, $conn->listColumns, $conn->filters, $conn->sorting, $this->grouping, $this->having, $start, $limit);
		}
		else {
			// Sort the connections by root connections and by those with the less results to the most dependent and with the most results
			$mesh = $this->_meshConnections($connections, $this->relations, $filtering);

			$sortedConnections = $this->_sortConnections($mesh);

			$max = count($sortedConnections);

			$queries = [];
			#region Generate queries per connection
			for ($num = 0; $num < $max; $num++) {
				// Currently processed connection
				$conn = $sortedConnections[$num];

				// Check if connection has already been processed
				if ($conn->rows != null)
					continue;

				// Generate query for the connection
				$queries[] = $conn->query = $conn->db->retrieveQuery($conn->dataSet, $conn->tables, $conn->relations, $conn->columns, $conn->listColumns, $conn->filters);
			}
			#endregion

			return $queries;
		}
		#endregion
	}

	/** Saves changes back to the database. */
	public function save(): EventStatus {
		$rows = $this->rows->getChanged();
		if ($rows->count() == 0) {
			return new EventStatus (true, '', 1, 'No changes found');
		}

		// Force validation of all rows in the collection
		$this->validate();

		#region Check for validation errors
		foreach ($rows->all() as $row) {
			if ($row->hasErrors()) {
				return new EventStatus (false, CMS::translator()->translate('Changed row(s) contain errors', 'cms') . ':<br />' . $row->status->errors->toString('<br />'), -1);
			}
		}
		#endregion

		$connections = DatabaseCollection::fromDataSet($this);
		$connections->beginTransaction();

		if (is_subclass_of($this->_mappedClass, '\\aneya\\Core\\Data\\ORM\\IDataObject')) {
			foreach ($rows->all() as $row) {
				$obj = $row->object();

				if ($obj instanceof IDataObject) {
					// Call ORM object's own save() method
					$ret = $obj->save();
				}
				else {
					// For failback cases, call row's save()
					$ret = $row->save();
				}

				if ($ret->isError()) {
					$connections->rollback();
					return $ret;
				}
			}
		}
		else {
			foreach ($rows->all() as $row) {
				$ret = $row->save();
				if ($ret->isError()) {
					$connections->rollback();
					return $ret;
				}
			}
		}
		$connections->commit();

		#region Clear deleted/purged rows
		$max = $rows->count();
		for ($idx = 0; $idx < $max; $idx++) {
			if (in_array($rows->itemAt($idx)->getState(), array (DataRow::StateNone, DataRow::StatePurged))) {
				$this->rows->remove($rows->itemAt($idx--));
			}
		}
		#endregion

		return new EventStatus(true, CMS::translator()->translate('record_save_success', 'cms'));
	}

	/** @inheritdoc */
	public function delete($filters = null): EventStatus {
		return $this->masterTable()->delete($filters);
	}

	/** Clears any previously retrieved rows, filtering and sorting information, and it returns itself to allow chaining. */
	public function clear(): static {
		$this->filtering->clear();
		$this->sorting->clear();

		parent::clear();

		return $this;
	}

	/** Returns the DataSet's main (master) table. */
	public function masterTable(): ?DataTable {
		// Return the first table available if no relation information is provided
		if ($this->relations->count() == 0)
			return $this->tables->first();

		return $this->relations->root();
	}

	/** Returns all tables in the DataSet, sorted by table relation priority. */
	public function sortedTables(): DataTableCollection {
		$collection = new DataTableCollection();

		if ($this->relations->count() == 0) {
			$collection->addRange($this->tables->all());
			return $collection;
		}

		$nodes = $this->relations->mesh()->parseNodes();
		foreach ($nodes->all() as $node) {
			/** @var DataTable $tbl */
			$tbl = $node->object();
			$collection->add($tbl);
		}

		return $collection;
	}

	/** Returns a collection of DataSet's associated database connections. */
	public function connections(): DatabaseCollection {
		return DatabaseCollection::fromDataSet($this);
	}

	/** Returns true if the DataSet provided as an argument contain the same table definitions. */
	public function equals(DataTable $dt): bool {
		if ($dt instanceof DataSet) {
			foreach ($this->tables->all() as $tbl) {
				$ok = false;

				foreach ($dt->tables as $tbl2) {
					if ($tbl->equals($tbl2)) {
						$ok = true;
						break;
					}
				}

				if (!$ok) {
					return false;
				}
			}
		}
		else {
			return ($this->tables->count() == 1 && $this->tables->first()->equals($dt));
		}

		return true;
	}

	/**
	 * Returns all foreign key columns of the given column.
	 *
	 * @see DataRelation::getForeignKeysOf()
	 */
	public function getForeignKeysOf(DataColumn $c): DataColumnCollection {
		$cols = new DataColumnCollection();

		foreach ($this->relations->all() as $rel) {
			$cols->addRange($rel->getForeignKeysOf($c));
		}

		return $cols;
	}

	#region Internal methods
	/**
	 * Parses a Mesh tree of connections from root nodes up to the dependent nodes
	 *
	 * @param Mesh $mesh
	 *
	 * @return DataSetConnectionSet[]|boolean
	 */
	private function _sortConnections(Mesh $mesh): array|bool {
		$connections = [];
		$nodes = $mesh->parseNodes();

		// An endless loop or another error was encountered
		if (!$nodes)
			return false;

		// The exclusion order actually defines the correct to parse the nodes so that there's no conflict
		foreach ($nodes as $node)
			$connections[] = $node->object();

		return $connections;
	}

	/**
	 * @param DataSetConnectionSet[] $connections
	 * @param DataRelationCollection $relations
	 * @param DataFilterCollection   $filters
	 *
	 * @return Mesh
	 */
	private function _meshConnections(array $connections, DataRelationCollection $relations, DataFilterCollection $filters): Mesh {
		$mesh = new Mesh();

		// Create a node per connection
		$i = 0;
		foreach ($connections as $conn) {
			$node = new Node();
			$node->bind($conn);
			$conn->node = $node;
			$mesh->nodes->add($node);
			$node->tag = $i;
			$node->priority = $conn->count;
		}

		#region Link nodes by relation information
		// We are interested only for relations between different connections
		foreach ($relations->all() as $rel) {
			$found = false;
			foreach ($connections as $conn) {
				if ($conn->relations->contains($rel)) {
					$found = true;
					break;
				}
			}

			#region Find parent (source) and child (target) connections and link them
			if (!$found) { // Relation is between different connections
				// Find parent (source) and child (target) connections
				$pConn = $cConn = null;
				foreach ($connections as $conn) {
					if ($conn->tables->contains($rel->parent)) {
						$pConn = $conn;
						break;
					}
				}
				foreach ($connections as $conn) {
					if ($conn->tables->contains($rel->child)) {
						$cConn = $conn;
						break;
					}
				}

				if ($pConn == null || $cConn == null)
					continue;                                        // We should not get here

				$source = $target = null;
				foreach ($mesh->nodes->all() as $node) {
					if ($node->object() == $pConn) {
						$source = $node;
						if ($target != null)
							break;
					}
					elseif ($node->object() == $cConn) {
						$target = $node;
						if ($source != null)
							break;
					}
				}

				if ($source == null || $target == null)
					continue;                                        // We should not get here

				// Create the link between the nodes/connections
				$link = $mesh->link($source, $target, $pConn->count);
				$link->filters = new DataFilterCollection();
				foreach ($rel->getLinks() as $relLink) {
					if ($rel->parent->columns->contains($relLink[0])) {
						$pCol = $relLink[0];
						$cCol = $relLink[1];
					}
					else {
						$pCol = $relLink[1];
						$cCol = $relLink[0];
					}
					// Add filter information to the link
					$link->filters->add(new DataFilter($cCol, DataFilter::Equals, $pCol));
				}
			}
			#endregion
		}
		#endregion

		return $mesh;
	}

	private function _applyConnectionResults(DataSetConnectionSet $conn, DataRowCollection $rows, Mesh $mesh): int {
		// Find the node that the object is bound to
		$node = $mesh->nodes->get($conn);

		#region Build the relation filters to filter connection's rows per master row
		$relFilters = new DataFilterCollection();
		foreach ($node->incoming() as $link) {
			if (!isset ($link->filters) || !($link->filters instanceof DataFilterCollection))
				continue;

			$linkFilters = $link->filters;
			foreach ($linkFilters->all() as $f)
				$relFilters->add($f);
		}
		#endregion

		$numApplied = 0;
		$filters = new DataFilterCollection();
		foreach ($rows as $row) {
			#region Set up filters for current row
			$filters->clear();
			foreach ($relFilters->all() as $rf) {
				$pCol = $rf->value;
				$value = $row->getValue($pCol);
				$filters->add(new DataFilter($rf->column, DataFilter::Equals, $value));
			}
			#endregion

			#region Filter the retrieved connection rows and apply values to the master row
			// There should be exactly one record matching the relation criteria, otherwise skip the master record from being setup
			$connRows = $conn->rows->match($filters);
			if ($connRows->count() == 0) {
				// Flag the row not to be included from the final results
				$row->_notFound = true;
				continue;
			}

			$cRow = $connRows->first();
			foreach ($conn->columns->all() as $col)
				$row->setValue($col, $cRow->getValue($col));

			$numApplied++;
			#endregion
		}

		return $numApplied;
	}

	public function __sortConnCallback(\stdClass $a, \stdClass $b): int {
		if ($a->count == $b->count) {
			return 0;
		}
		return ($a->count < $b->count) ? -1 : 1;
	}
	#endregion
	#endregion

	#region Json Methods
	/** Applies configuration from an \stdClass instance. */
	public function applyJsonCfg(object $cfg): static {
		foreach ($cfg->tables as $t) {
			$cols = [];
			foreach ($t->columns as $c) {
				$col = new DataColumn($c->name, $c->dataType);
				$cols[] = $col->applyJsonCfg($c);
			}

			$this->tables->add($tbl = new DataTable(null, $cols, CMS::db($t->schema)));
			$tbl->alias = $t->alias;
			$tbl->id = $t->id;
			$tbl->name = $t->name;
		}

		foreach ($cfg->relations as $r) {
			if (!empty($r->child)) {
				// TODO: Check schema to avoid bugs when having same table name but on different schemas
				$pTbl = $this->tables->get($r->parent->name);
				$cTbl = $this->tables->get($r->child->name);
				$relation = new DataRelation($pTbl, $cTbl, $r->joinType, (int)$r->joinOrder);
				foreach ($r->links as $link) {
					$relation->link($pTbl->columns->get($link->parentColumn), $cTbl->columns->get($link->childColumn));
				}
			}
			else {
				$relation = new DataRelation($this->tables->get($r->parent->name), null, $r->joinType, (int)$r->joinOrder);
			}

			$relation->id = (int)$r->id ?? null;
			$relation->isDefault = (bool)$r->isDefault;
			$relation->isSaveable = (bool)$r->isSaveable;

			$this->relations->add($relation);
		}

		return $this;
	}

	/**
	 * Flattens DataSet's definition into Json-ready format.
	 *
	 * @param bool $definition If true, will return full DataSet definition.
	 *
	 * @return array
	 */
	public function jsonSerialize($definition = false): array {
		$data = parent::jsonSerialize($definition);

		if ($definition) {
			unset($data['columns']);

			$data = array_merge($data, [
				'tables'    => $this->tables->jsonSerialize($definition),
				'relations' => $this->relations->jsonSerialize(),
				'grouping'  => $this->grouping->jsonSerialize(),
				'having'    => $this->having->jsonSerialize()
			]);
		}

		return array_merge($data, [
			'filtering' => $this->filtering->jsonSerialize(),
			'sorting'   => $this->sorting->jsonSerialize()
		]);
	}
	#endregion

	#region Magic methods
	public function __wakeup() {
		$this->_initTables();
	}
	#endregion
}
