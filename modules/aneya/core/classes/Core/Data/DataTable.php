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
use aneya\Core\CoreObject;
use aneya\Core\Data\ORM\IDataObject;
use aneya\Core\EventStatus;
use aneya\Core\IStorable;
use aneya\Core\Storable;

class DataTable extends CoreObject implements \JsonSerializable {
	#region Events
	/** Triggered when DataTable's rows collection is being retrieved from database. Passes a DataTableRetrieveEventArgs argument on listeners. */
	const EventOnRetrieving = 'OnRetrieving';
	/** Triggered when DataTable's rows collection is being retrieved from database. Used to bypass default retrieval mechanism and provide a custom retrieval to fill in the collection with rows. Listeners should flag the return status as handled in order to bypass the default mechanism. Passes a DataTableRetrieveEventArgs argument on listeners. */
	const EventOnRetrieve = 'OnRetrieve';
	/** Triggered when DataTable's rows collection was retrieved from database. Passes a DataTableRetrieveEventArgs argument on listeners. */
	const EventOnRetrieved = 'OnRetrieved';

	const EventOnSaving = 'OnSaving';
	const EventOnSave   = 'OnSave';
	const EventOnSaved  = 'OnSaved';
	#endregion

	#region Properties
	/** @var string|int|null DataTable's Id. Used if DataTable information was retrieved from database and applied dynamically. */
	public string|int|null $id = null;

	/** @var ?string Table's name */
	public ?string $name = null;

	/** @var ?string Table's alias (if any) */
	public ?string $alias = null;

	public DataRowCollection $rows;

	public DataColumnCollection $columns;

	/** @var ?DataSet If DataTable is part of a DataSet, the property points to the parent DataSet instance */
	public ?DataSet $parent = null;

	/** @var DataRelationCollection Contains all child tables relation information */
	public DataRelationCollection $children;

	/** @var bool If true, new rows will be automatically bound to generated objects of the defined mapped class */
	public bool $autoGenerateObjects = false;

	/** @var bool Indicates whether the data table has retrieved rows from database */
	public bool $isRetrieved = false;

	#region Protected properties
	protected ?Database $_db = null;
	protected ?string $_dbTag = null;

	/** @var string The class name (preferably implementing IDataObject) of the objects that the DataTable's rows will be mapped to */
	protected string $_mappedClass = '\\stdClass';
	#endregion
	#endregion

	#region Constructor
	/**
	 * @param DataRow[]|DataRowCollection $rows
	 * @param DataColumn[]|DataColumnCollection $columns
	 * @param Database|null $db The database the table belongs to
	 * @param ?string $className The class of the objects that the DataTable's rows will be mapped to
	 */
	public function __construct($rows = null, $columns = null, Database $db = null, string $className = null) {
		if ($rows instanceof DataRowCollection)
			$this->rows = $rows;
		else
			$this->rows = new DataRowCollection ($rows);

		$this->rows->parent = $this;

		if ($columns instanceof DataColumnCollection)
			$this->columns = $columns;
		else
			$this->columns = new DataColumnCollection ($columns);

		// Set parent so that all items in the columns' collection will point to this instance
		$this->columns->parent = $this;
		foreach ($this->columns->all() as $c) {
			$c->table = $this;
		}

		$this->db($db);

		$this->children = new DataRelationCollection();
	}
	#endregion

	#region Methods
	/** Gets/sets the table's database */
	public function db(Database $db = null): ?Database {
		if ($db instanceof Database) {
			$this->_db = $db;

			// If argument's connection is a named framework database connection, only keep the tag to allow connections reuse
			if (isset($db->tag) && strlen($db->tag) > 0)
				$this->_dbTag = $db->tag;
			else
				$this->_dbTag = '';
		}

		return (strlen($this->_dbTag) > 0) ? CMS::db($this->_dbTag) : $this->_db;
	}

	/**
	 * Retrieves rows from database
	 *
	 * @param DataFilterCollection|DataFilter|DataFilter[]    $filters
	 * @param DataSortingCollection|DataSorting|DataSorting[] $sorting
	 * @param ?int $start
	 * @param ?int $limit
	 *
	 * @return $this
	 */
	public function retrieve(DataFilterCollection|DataFilter|array $filters = null, DataSortingCollection|DataSorting|array $sorting = null, int $start = null, int $limit = null): DataTable {
		$tables = new DataTableCollection();
		$tables->add($this);

		if ($filters instanceof DataFilter) {
			$flt = $filters;
			$filters = new DataFilterCollection();
			$filters->add($flt);
		}
		elseif (is_array($filters)) {
			$flt = $filters;
			$filters = new DataFilterCollection();
			$filters->addRange($flt);
		}

		if ($sorting instanceof DataSorting) {
			$srt = $sorting;
			$sorting = new DataSortingCollection();
			$sorting->add($srt);
		}
		elseif (is_array($sorting)) {
			$srt = $sorting;
			$sorting = new DataSortingCollection();
			$sorting->addRange($srt);
		}

		$args = new DataTableRetrieveEventArgs($this);
		$args->filters = $filters;
		$args->sorting = $sorting;
		$args->numOfConnections = 1;

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
			$this->db()->retrieve($this, $tables, new DataRelationCollection(), $this->columns, $this->columns, $filters, $sorting, $start, $limit);

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
								$values[$pCol->tag][] = $row->getValue($pCol);
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
		}

		$toTime = microtime(true);
		$args->duration = (float)$toTime - (float)$fromTime;
		$args->numOfRows = $this->rows->count();

		$this->trigger(self::EventOnRetrieved, $args);

		$this->isRetrieved = true;

		return $this;
	}

	/**
	 * Retrieves the count of rows of the table in the database that match the given filters
	 *
	 * @param DataFilterCollection|DataFilter|DataFilter[] $filters
	 *
	 * @return int
	 */
	public function retrieveCnt(DataFilterCollection|DataFilter|array $filters = null): int {
		$tables = new DataTableCollection();
		$tables->add($this);

		return $this->db()->retrieveCnt($this, $tables, new DataRelationCollection(), $filters);
	}

	/**
	 * Generates the database retrieval query and returns it without executing it.
	 *
	 * @param DataFilterCollection|DataFilter|DataFilter[]    $filters
	 * @param DataSortingCollection|DataSorting|DataSorting[] $sorting
	 * @param ?int  			                              $start
	 * @param ?int              			                  $limit
	 *
	 * @return mixed
	 */
	public function retrieveQuery(DataFilterCollection|DataFilter|array $filters = null, DataSortingCollection|DataSorting|array $sorting = null, int $start = null, int $limit = null): mixed {
		$tables = new DataTableCollection();
		$tables->add($this);

		if ($filters instanceof DataFilter) {
			$flt = $filters;
			$filters = new DataFilterCollection();
			$filters->add($flt);
		}
		elseif (is_array($filters)) {
			$flt = $filters;
			$filters = new DataFilterCollection();
			$filters->addRange($flt);
		}

		if ($sorting instanceof DataSorting) {
			$srt = $sorting;
			$sorting = new DataSortingCollection();
			$sorting->add($srt);
		}
		elseif (is_array($sorting)) {
			$srt = $sorting;
			$sorting = new DataSortingCollection();
			$sorting->addRange($srt);
		}

		return $this->db()->retrieveQuery($this, $tables, new DataRelationCollection(), $this->columns, $this->columns, $filters, $sorting, $start, $limit);
	}

	/** Loads DataTable's Columns collection and other definition by retrieving columns and their base properties from database's schema definition. */
	public function loadDefinitionFromDb(string $table = null): bool {
		if (strlen($table) > 0) {
			$this->name = $table;
		}

		if (!($this->db() instanceof Database))
			return false;

		$this->columns->clear();

		$dt = $this->db()->schema->getDataSet($this->name);
		foreach ($dt->columns->all() as $c) {
			$this->columns->add($c);
			$c->table = $this;
		}

		return ($this->columns->count() > 0);
	}

	/** Saves changes back to the database. */
	public function save(): EventStatus {
		// Check for changes
		$rows = $this->rows->getChanged();
		if ($rows->count() == 0) {
			return new EventStatus(true, '', 1, 'No changes found');
		}

		// Force validation of all rows in the collection
		$this->validate();

		// Check for validation errors
		foreach ($rows->all() as $row) {
			if ($row->hasErrors()) {
				return new EventStatus(false, CMS::translator()->translate('Changed rows have validation errors', 'cms'), -1);
			}
		}

		$this->db()->beginTransaction();
		if (is_subclass_of($this->_mappedClass, '\\aneya\\Core\\Data\\ORM\\IDataObject')) {
			foreach ($rows->all() as $row) {
				$obj = $row->object();

				if ($obj instanceof IDataObject) {
					// Call ORM object's own save() method
					$ret = $obj->save();
				}
				else {
					// For fallback cases, call row's save()
					$ret = $row->save();
				}

				if ($ret->isError()) {
					$this->db()->rollback();
					return $ret;
				}
			}
		}
		else {
			foreach ($rows->all() as $row) {
				$ret = $row->save();
				if ($ret->isError()) {
					$this->db()->rollback();
					return $ret;
				}
			}
		}
		$this->db()->commit();

		#region Clear deleted/purged rows
		foreach ($rows as $row) {
			if (in_array($row->getState(), array (DataRow::StateNone, DataRow::StatePurged)))
				$this->rows->remove($row);
		}
		#endregion

		return new EventStatus ();
	}

	/**
	 * Deletes records from the Collection.
	 *
	 * If DataTable has been retrieved, then it marks records for deletion and returns itself;
	 * otherwise deletes the matched records from the database and returns the status.
	 *
	 * If filters argument is defined then it deletes records matching the filters;
	 * otherwise deletes any records from the DataTable that have been marked as selected.
	 *
	 * @param DataFilter|DataFilter[]|DataFilterCollection|array $filters
	 *
	 * @return EventStatus
	 *
	 * @throws \InvalidArgumentException
	 */
	public function delete(DataFilter|DataFilterCollection|array $filters = null): EventStatus {
		$col = new DataFilterCollection();

		if ($filters instanceof DataFilterCollection)
			$col = $filters;

		elseif ($filters instanceof DataFilter)
			$col->add($filters);

		elseif (is_array($filters)) {
			foreach ($filters as $f) {
				if ($f instanceof DataFilter)
					$col->add($f);

				elseif (is_array($f)) {
					foreach ($f as $tag => $value) {
						$c = $this->columns->get($tag);
						if (!($c instanceof DataColumn))
							continue;

						// Convert values of specific data types
						$value = DataRow::convertValue($c, $value);

						$col->add(new DataFilter($c, DataFilter::Equals, $value));
					}
				}
			}
		}
		elseif (!$this->isRetrieved)
			throw new \InvalidArgumentException('Arguments are not valid');

		// If DataTable is retrieved, try to delete from existing rows
		if ($this->isRetrieved) {
			// If no filters have been defined, assume that selected rows are to be deleted
			if ($col->count() == 0)
				$rows = $this->rows->getSelected();
			else
				$rows = $this->rows->match($col);

			if ($rows->count() == 0)
				return new EventStatus(false, CMS::translator()->translate('No record found', 'cms'));

			foreach ($rows as $row) {
				$row->delete();
			}

			return new EventStatus();
		}
		else {
			if ($col->count() == 0)
				return new EventStatus(false, CMS::translator()->translate('No record found', 'cms'));

			return $this->db()->delete($this, $col);
		}
	}

	/** Validates all rows in the DataSet and returns the combined rows validation results. */
	public function validate(): DataRowValidationEventStatus {
		$status = new DataRowValidationEventStatus();

		/** @var DataRowValidationEventStatus $firstErrorStatus */
		$firstErrorStatus = null;

		foreach ($this->rows->all() as $row) {
			// No need to validate deleted records
			if ($row->getState() == DataRow::StateDeleted) {
				continue;
			}

			$statuses = $row->validate();

			if ($statuses->isError()) {
				$status->isPositive = false;
				$firstErrorStatus = $statuses;
			}

			foreach ($statuses->errors->all() as $error) {
				$status->errors->add($error);
			}
		}

		// Set first erroneous status's error details as those to be returned
		if ($firstErrorStatus instanceof DataRowValidationEventStatus) {
			$status->code = $firstErrorStatus->code;
			$status->message = $firstErrorStatus->message;
			$status->debugMessage = $firstErrorStatus->debugMessage;
		}

		return $status;
	}

	/** Resets all rows in the DataTable to their original values */
	public function reset(): static {
		foreach ($this->rows->all() as $row) {
			$row->reset();
		}

		return $this;
	}

	/** Clears any previously retrieved rows. */
	public function clear(): static {
		$this->rows->clear();

		return $this;
	}

	public function aggregate() {
		// TODO: Implement method
	}

	/** Returns true if the DataTable provided as an argument points to the same database, table name and contains the same columns as with the current DataTable instance. */
	public function equals(DataTable $dt): bool {
		if ($this->db()->tag != $dt->db()->tag) {
			return false;
		}

		if ($this->name != $dt->name) {
			return false;
		}

		foreach ($this->columns->all() as $col) {
			if ($col->isSaveable && $dt->columns->get($col->tag) === null) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Maps the table to a class in order to easier row/object generation
	 *
	 * @param string $className Class's fully qualified name
	 *
	 * @throws \InvalidArgumentException
	 */
	public function mapClass(string $className): static {
		if (is_a($className, '\\aneya\\Core\\IStorable')) {
			/** @var IStorable $class */
			$class = $className;
			if ($class::ormSt()->dataSet() !== $this) {
				throw new \InvalidArgumentException("Cannot map class $className as its class DataTable does not point to this DataTable instance");
			}
		}

		$this->_mappedClass = $className;

		return $this;
	}

	/** Returns the fully qualified name of the class that is mapped to this DataTable */
	public function getMappedClass(): string {
		if (strlen($this->_mappedClass) == 0) {
			$this->_mappedClass = '\\stdClass';
		}

		return $this->_mappedClass;
	}

	/**
	 * Generates mapped objects per row. DataTable::mappedClass property needs to be already set.
	 *
	 * @return array|IStorable[]
	 */
	public function generateObjects(): array {
		$objects = [];
		$class = $this->getMappedClass();
		foreach ($this->rows->all() as $row) {
			$objects[] = $obj = new $class();
			$row->object($obj, DataRow::SourceDatabase);

			if ($obj instanceof IStorable) {
				$obj->orm()->row($row);
			}

			$row->syncObject();
		}

		return $objects;
	}

	/**
	 * Returns a list of delegated objects that are bound to the rows in the collection
	 *
	 * @return object[]|IStorable[]
	 */
	public function objects(): array {
		$objects = [];

		foreach ($this->rows->all() as $row) {
			$obj = $row->object();
			if (is_object($obj)) {
				$objects[] = $obj;
			}
		}

		return $objects;
	}

	/**
	 * Returns a newly instantiated DataRow-derived object with row state set to DataRow::StateAdded, already inserted into DataTable's rows collection.
	 *
	 * In case DataTable's db instance is an ODBMS, the returned row is an instance of DataObject; otherwise the row is a DataRecord.
	 *
	 * @param bool $addToCollection (true by default) Indicates if the new row should automatically be added to the DataTable's rows collection.
	 * @param array|null $values          (optional) The values to set to the record. If omitted, the record will be initialized with each column's default value.
	 *
	 * @return DataRow
	 */
	public function newRow(bool $addToCollection = true, array $values = null): DataRow {
		if (!is_array($values) || count($values) == 0) {
			// Retrieve default values
			$values = array ();
			foreach ($this->columns->all() as $c)
				$values[$c->tag] = $c->defaultValue;
		}

		$row = new DataRow ($values, $this, DataRow::StateAdded);

		// Auto-map a new object if conditions are met
		if (strlen($this->getMappedClass()) > 0 && $this->autoGenerateObjects) {
			$class = $this->getMappedClass();
			$obj = new $class();
			if ($obj instanceof Storable) {
				$obj->orm()->bulkSetValues($row);
			}
		}

		if ($addToCollection)
			$this->rows->add($row);

		return $row;
	}

	/** Returns true if the data table contains multilingual columns. */
	public function isMultilingual(): bool {
		foreach ($this->columns->all() as $c)
			if ($c->isMultilingual)
				return true;

		return false;
	}
	#endregion

	#region Interfaces implementation methods
	public function jsonSerialize($definition = false): array {
		if ($definition)
			return [
				'id'             => $this->id,
				'name'           => $this->name,
				'alias'          => $this->alias,
				'schema'         => $this->db()->tag,
				'columns'        => $this->columns->jsonSerialize(),
				'isMultilingual' => $this->isMultilingual(),
				'mappedClass'    => $this->getMappedClass()
			];

		else
			return [
				'name'    => $this->name,
				'columns' => $this->columns->jsonSerialize()
			];
	}
	#endregion

	#region Magic methods
	/**
	 * @return string
	 */
	public function __toString() {
		return $this->name;
	}
	#endregion
}
