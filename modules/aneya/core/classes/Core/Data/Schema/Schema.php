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

use aneya\Core\ApplicationError;
use aneya\Core\CMS;
use aneya\Core\Data\Database;
use aneya\Core\Data\DataColumn;
use aneya\Core\Data\DataRelation;
use aneya\Core\Data\DataSet;
use aneya\Core\Data\DataTable;
use aneya\Core\Utils\JsonUtils;
use aneya\Core\Utils\StringUtils;

abstract class Schema {
	#region Properties
	/** @var bool Indicates whether data changes in any table in this schema are allowed or not. */
	public bool $readonly = false;

	protected ?Database $_db;

	/** @var Table[] */
	protected array $_tables;

	/** @var Relation[] */
	protected array $_relations;

	protected ?\DateTime $_lastChanged;
	/** @var string[] Stores the last date/time schema change per table */
	protected array $_lastChangedTable = [];
	#endregion

	#region Methods
	/**
	 * Sets the Schema's instance active database connection
	 *
	 * @param Database $db
	 */
	public function setDatabaseInstance(Database $db) {
		$this->_db = $db;
	}

	/**
	 * Returns the schema's name
	 */
	public function schemaName(): string {
		return ($this->_db !== null) ? $this->_db->getSchemaName() : '';
	}

	/**
	 * Returns an array of all tables found in the schema
	 *
	 * @return Table[]
	 */
	public abstract function tables(bool $forceRetrieve = false): array;

	/**
	 * Returns an array of all table relations found in the schema
	 *
	 * @param bool $forceRetrieve
	 *
	 * @return Relation[]
	 */
	public abstract function relations(bool $forceRetrieve = false): array;

	/**
	 * Returns the SchemaTable object in the Schema which matches the given table name
	 *
	 * @param string $name Table's name
	 *
	 * @return Table|null
	 */
	public function getTableByName(string $name): ?Table {
		$tables = $this->tables();
		return (isset ($tables[$name])) ? $tables[$name] : null;
	}

	/**
	 * Returns the date the schema (or a specific table if an argument is provided) was last changed
	 *
	 * @param string|null $tableName (optional)
	 *
	 * @return \DateTime
	 */
	public abstract function getLastChanged(string $tableName = null): \DateTime;

	/**
	 * Returns a complete DataSet instance that represents the given table(s) in the database, already joined (if multiple tables provided) by retrieving foreign-key information.
	 * If any multilingual columns are identified, their translation table gets automatically added to the resulting DataSet.
	 *
	 * Conflicting field names are prefixed with their table name.
	 *
	 * You may use an asterisk in the $columns argument to denote use of all fields in a table (e.g. ['table1.*', 'table2.id'])
	 * You may also use table aliases by passing a hash array for tables definitions (e.g. ['T1' => 'table1', 'T2' => 'table2')
	 *
	 * @param Table|Table[]|string|string[] $tables
	 * @param Field|Field[]|string|string[] $columns
	 * @param bool $camelCase If true, column tags will be renamed to camel case
	 *
	 * @return DataSet
	 */
	public function getDataSet(Table|array|string $tables, Field|array|string $columns = null, bool $camelCase = false): DataSet {
		$ds = new DataSet();
		$ds->db($this->_db);

		// If single table was provided, convert to array
		if (!is_array($tables)) {
			$tables = [$tables];
		}

		/** @var Relation[] $_relations */
		$_relations = [];
		$tableNames = [];
		$tablesCnt = 0;
		$isAssociative = JsonUtils::isAssociativeArray($tables);
		$aliases = array_keys($tables);
		$num = 0;
		$nextId = 1;
		foreach ($tables as $table) {
			if (!($table instanceof Table)) {
				// Keep table's name
				$tblName = $table;

				if ($isAssociative) {
					$alias = $aliases[$num++];
					$table = $this->getTableByName($table);
					$table->alias = $alias;                                // Add custom property to store table's alias (will be needed later)
				}
				else {
					$table = $this->getTableByName($table);
				}

				// If still table wasn't found, log the error and continue silently
				if (!($table instanceof Table)) {
					CMS::app()->log($err = new ApplicationError(sprintf('Could not find database table with name [%s] in schema [%s] while generating DataSet.', $tblName, $this->schemaName())));
					continue;
				}
			}

			$ds->tables->add($dt = new DataTable (null, null, $this->_db));
			$dt->id = $nextId++;
			$dt->name = $table->name;
			$dt->table = $table;										// Add custom property to link the data table with the original schema table (will be needed later)
			if (isset($table->alias)) {
				$dt->alias = $table->alias;
			}
			$tablesCnt++;

			#region Check if there's a translation table
			$tableTr = $this->getTableByName($table->name . 'Tr');
			if ($tableTr instanceof Table) {
				$dt->isMultilingual = true;								// Add custom property to flag the table as multilingual (will be needed later)
				$dt->multilingualFields = $tableTr->getFields();		// Add custom property to store multilingual fields (will be needed later)
			}
			#endregion
		}

		$colNames = [];
		$nextId = 1;
		foreach ($ds->tables->all() as $dt) {
			#region Build fields information
			/** @var Table $table */
			$table = $dt->table;
			$fields = $table->getFields();

			#region Add multilingual fields
			if (isset($dt->isMultilingual) && $dt->isMultilingual) {
				/** @var Field[] $multilingualFields */
				$multilingualFields = $dt->multilingualFields;
				if ($columns == null) {
					#region Add all multilingual fields from the translation table
					foreach ($multilingualFields as $f) {
						if ($f->isPrimary)
							continue;

						$f->isMultilingual = true;						// Add custom property to flag the field as multilingual (will be needed later)
						$fields[] = $f;
					}
					#endregion
				}
				else {
					#region Add only the specified multilingual fields from the translation table
					foreach ($columns as $col) {
						foreach ($multilingualFields as $f) {
							if (($col instanceof Field && $f->name == $col->name) || ($f->name === $col) || "$dt->name.$f->name" === $col || "$dt->name.*" === $col || "$dt->alias.$f->name" === $col || "$dt->alias.*" === $col) {
								if ($f->isPrimary)
									continue;

								$f->isMultilingual = true;				// Add custom property to flag the field as multilingual (will be needed later)
								$fields[] = $f;
							}
						}
					}
					#endregion
				}
			}
			#endregion

			foreach ($fields as $f) {
				#region Omit columns not provided in the second argument (if any)
				if ($columns != null) {
					if (!is_array($columns))
						$columns = [$columns];

					$ok = false;
					foreach ($columns as $tag => $col) {
						if ($col instanceof Field && strtolower($f->name) == strtolower($col->name)) {
							$colTag = is_string($tag) ? $tag : null;
							$ok = true;
							break;
						}
						elseif (strtolower($f->name) === strtolower($col)) {
							$colTag = is_string($tag) ? $tag : null;
							$ok = true;
							break;
						}
						elseif (strpos($col, '.') > 0) {				// Column was prefixed with table name
							[$tmpTbl, $tmpCol] = explode('.', $col, 2);
							if ((strtolower($tmpTbl) == strtolower($dt->name) || strtolower($tmpTbl) == strtolower($dt->alias)) && ($tmpCol == '*' || strtolower($tmpCol) == strtolower($f->name))) {
								$colTag = is_string($tag) ? $tag : null;
								$ok = true;
								break;
							}
						}
					}
					if (!$ok)
						continue;
				}
				#endregion

				$dt->columns->add($column = new DataColumn ($f->name, $f->dataType));
				$column->id = $nextId++;
				$column->tag = isset($colTag) && strlen($colTag) > 0 ? $colTag : (($camelCase == true) ? StringUtils::toCamelCase($f->name) : $f->name);
				$column->title = StringUtils::capitalize($f->name);
				$column->isKey = $f->isPrimary;
				$column->isAutoIncrement = $f->isAutoIncrement;
				$column->isUnsigned = $f->isUnsigned;
				$column->defaultValue = $f->defaultValue;
				$column->allowNull = $f->isNullable;
				$column->isRequired = !$f->isNullable;
				$column->isMultilingual = isset ($f->isMultilingual);
				$column->maxLength = $f->maxLength;
				$column->comment = $f->comment;

				$colNames[$column->tag][] = $column;
			}
			#endregion

			$rels = $table->getRelations();
			if (count($rels) > 0) {
				$_relations = array_merge($_relations, $rels);
			}

			$tableNames[] = $table->name;
		}

		if ($tablesCnt > 1) {
			foreach ($colNames as $tag => $cols) {
				if (count($cols) == 1)
					continue;

				/** @var DataColumn[] $cols */
				foreach ($cols as $col) {
					$col->tag = (isset($col->table->alias) ? $col->table->alias : $col->table->name) . '_' . $col->tag;
				}
			}
		}

		// If no relations were found, just return the created DataSet
		if (count($_relations) == 0)
			return $ds;

		#region Build the relations between the database tables
		$order = 0;
		$nextId = 1;
		foreach ($_relations as $rel) {
			if (!in_array($rel->foreignTable, $tableNames)) {
				continue;
			}
			// If there's a foreign key to the same table, ignore it (i.e. a parent key relationship)
			if ($rel->masterTable === $rel->foreignTable) {
				continue;
			}

			$master = $ds->tables->get($rel->masterTable);
			$child = $ds->tables->get($rel->foreignTable);
			$pCol = $master->columns->get($rel->masterField, false);    // Columns are not required to exist, so don't produce warnings
			$cCol = $child->columns->get($rel->foreignField, false);

			// If either master field or foreign field were not loaded in the DataSet (not needed for the purposes of the DataSet), ignore the relation
			if ($pCol === null || $cCol === null) {
				continue;
			}

			$ds->relations->add($dr = new DataRelation($master, $child, DataRelation::JoinInner, $order));
			$dr->id = $nextId++;
			$dr->link($pCol, $cCol);
			$order++;

			// If parent key is auto-increment, child key does not need to be required as during saving, it'll automatically get parent key's value
			if ($pCol->isAutoIncrement) {
				$cCol->isRequired = false;
			}
		}

		// If order is greater than zero, it means that there were relations added to the DataSet
		if ($order > 0) {
			$ds->relations->root()->isDefault = true;
		}
		#endregion

		return $ds;
	}

	/**
	 * Returns an array with field information of the given table name
	 *
	 * @param Table|string $table Either a table name or a SchemaTable object
	 *
	 * @return Field[]
	 */
	public abstract function getFields(Table|string $table): array;

	#region Foreign-key Relations functions
	/**
	 * @param Table|string $table
	 *
	 * @return Relation[]
	 */
	public function getRelationsByMasterTable($table): array {
		if ($table instanceof Table) {
			$table = $table->name;
		}

		$relations = $this->relations();
		$ret = array ();
		foreach ($relations as $rel) {
			if ($rel->masterTable === $table)
				$ret[] = $rel;
		}

		return $ret;
	}

	/**
	 * @param Table|string $table
	 *
	 * @return Relation[]
	 */
	public function getRelationsByForeignTable($table): array {
		if ($table instanceof Table) {
			$table = $table->name;
		}

		$relations = array ();
		foreach ($this->relations() as $rel)
			if ($rel->foreignTable == $table)
				$relations[] = $rel;

		return $relations;
	}

	/**
	 * @param Field|string $field
	 *
	 * @return Relation[]
	 */
	public function getRelationsByMasterField($field): array {
		if ($field instanceof Field) {
			$field = $field->name;
		}

		$relations = array ();
		foreach ($this->relations() as $rel)
			if ($rel->masterField == $field)
				$relations[] = $rel;

		return $relations;
	}

	/**
	 * @param Field|string $field
	 *
	 * @return Relation[]
	 */
	public function getRelationsByForeignField($field): array {
		if ($field instanceof Field) {
			$field = $field->name;
		}

		$relations = array ();
		foreach ($this->relations() as $rel)
			if ($rel->foreignField == $field)
				$relations[] = $rel;

		return $relations;
	}
	#endregion
	#endregion

	#region region Magic methods
	public function __toString() { return $this->schemaName(); }
	#endregion
}
