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

use aneya\Core\CMS;
use aneya\Core\Data\ConnectionOptions;
use aneya\Core\Data\Database;
use aneya\Core\Data\DataColumn;
use aneya\Core\Data\DataColumnCollection;
use aneya\Core\Data\DataExpression;
use aneya\Core\Data\DataFilter;
use aneya\Core\Data\DataFilterCollection;
use aneya\Core\Data\DataRecord;
use aneya\Core\Data\DataRelation;
use aneya\Core\Data\DataRelationCollection;
use aneya\Core\Data\DataRow;
use aneya\Core\Data\DataSorting;
use aneya\Core\Data\DataSortingCollection;
use aneya\Core\Data\DataTable;
use aneya\Core\Data\DataTableCollection;
use aneya\Core\Data\RDBMS;
use aneya\Core\Data\Schema\Schema;
use aneya\UI\Calendar\DateTimeRange;
use Monolog\Logger;

class MySQL extends RDBMS {
	#region Constants
	const DefaultPort = 3306;
	const SupportedDataTypes = [
		DataColumn::DataTypeBlob, DataColumn::DataTypeChar, DataColumn::DataTypeDate, DataColumn::DataTypeDateTime, DataColumn::DataTypeFloat,
		DataColumn::DataTypeGeoCollection, DataColumn::DataTypeGeometry, DataColumn::DataTypeGeoMultiPoint, DataColumn::DataTypeGeoMultiPolygon, DataColumn::DataTypeGeoPoint, DataColumn::DataTypeGeoPolygon,
		DataColumn::DataTypeInteger, DataColumn::DataTypeObject, DataColumn::DataTypeString, DataColumn::DataTypeTime
	];
	#endregion

	#region Properties
	/** @var MySQLSchema */
	public Schema $schema;

	protected ?string $_encoding = 'utf8';
	protected string $_timezone = 'UTC';
	protected string $_quote    = '`';
	public readonly string $quoteChar;
	#endregion

	#region Constructor
	public function __construct() {
		parent::__construct();

		$this->_type = Database::MySQL;
		$this->quoteChar = '`';
		$this->schema = new MySQLSchema();
		$this->schema->setDatabaseInstance($this);

		$this->options = new ConnectionOptions();
		/**
		 * If setting timezone triggers MySQL error, try to execute the following command in the shell:
		 * ~$ mysql_tzinfo_to_sql /usr/share/zoneinfo | mysql -u root mysql
		 */
		$this->options->pdoOptions[\PDO::MYSQL_ATTR_INIT_COMMAND] = "SET NAMES utf8;SET time_zone='UTC';SET session time_zone='UTC'";
	}
	#endregion

	#region Methods
	#region Connection methods
	public function connect(ConnectionOptions $options = null): bool {
		if (isset ($this->_link))
			$this->disconnect();

		// Apply argument connection options to instance's connection options
		if (isset($options) && $options !== $this->options)
			$this->options->applyCfg($options->toJson());

		$this->_pdo = $this->_link = new \PDO ($this->getConnectionString(), $this->options->username, $this->options->password, $this->options->pdoOptions);

//		$this->exec ('SET TIME_ZONE=:timezone', array (':timezone' => $this->_timezone));
//		$this->exec ('SET SESSION TIME_ZONE=:timezone', array (':timezone' => $this->_timezone));
//		$this->exec ('SET NAMES :charset', array (':charset' => $this->_encoding));

		if ($this->timezone->getName() != $this->_timezone) {
			$this->timezone = new \DateTimeZone($this->_timezone);
		}

		// Force true prepare statements for MySQL
		$this->_pdo->setAttribute(\PDO::ATTR_EMULATE_PREPARES, false);

		return true;
	}

	public function reconnect(): mixed {
		if (!$this->isConnected()) {
			if (strlen($this->tag) > 0) {
				$db = CMS::db($this->tag);
				if ($db instanceof RDBMS) {
					$this->_pdo = $this->_link = $db->pdo();
				}
			}
			// If still not connected, probably the connection details are user-defined and not stored in the framework
			if (!$this->isConnected()) {
				$this->_pdo = $this->_link = new \PDO ($this->getConnectionString(), $this->options->username, $this->options->password, $this->options->pdoOptions);
			}
		}

		return true;
	}

	public function disconnect() {
		if (isset ($this->_link)) {
			unset ($this->_link);
			unset ($this->_pdo);
		}
	}

	protected function getConnectionString(): string {
		return ($this->options->connString) ?: sprintf('mysql:host=%s;dbname=%s;port=%d;charset=%s;', $this->options->host, $this->options->database, $this->options->port, $this->options->charset);
	}

	/**
	 * @inheritdoc
	 */
	public function parseCfg(\stdClass $cfg): ConnectionOptions {
		$connOpts = new ConnectionOptions();
		$connOpts->host = $cfg->host;
		$connOpts->port = (int)$cfg->port > 0 ? (int)$cfg->port : self::DefaultPort;
		$connOpts->database = $cfg->database;
		$connOpts->schema = $cfg->database;
		$connOpts->charset = 'utf8';
		$connOpts->timezone = 'UTC';
		$connOpts->username = $cfg->username;
		$connOpts->password = $cfg->password;

		$connOpts->readonly = isset($cfg->readonly) && $cfg->readonly === true;

		return $connOpts;
	}
	#endregion

	#region Transaction methods
	public function beginTransaction(string $name = null): bool|int {
		if (count($this->_savePoints) == 0) {
			$ret = $this->pdo()->beginTransaction(); //->exec ('BEGIN');
			if ($ret === false)
				return $ret;
		}

		if (!isset ($name))
			$name = '__transaction_' . count($this->_savePoints);

		$name = $this->escape($name);
		$this->_savePoints[] = $name;
		return $this->pdo()->exec("SAVEPOINT $name");
	}

	public function commit(string $name = null): bool|int {
		$level = -1;
		$max = count($this->_savePoints);

		if (!isset ($name)) {
			for ($i = $max - 1; $i >= 0; $i--) {
				if (strpos($this->_savePoints[$i], '__transaction_') === 0) {
					$name = $this->_savePoints[$i];
					$level = $i;
					break;
				}
			}
		}
		else {
			for ($i = 0; $i < $max; $i++) {
				if ($name == $this->_savePoints[$i]) {
					$level = $i;
					break;
				}
			}
		}

		// Transaction was not found
		if ($level < 0 || !isset ($name))
			return false;

		// Commit all child savepoints
		for ($i = $max - 1; $i >= $level; $i--) {
			$ret = $this->pdo()->exec("RELEASE SAVEPOINT " . $this->_savePoints[$i]);
			unset ($this->_savePoints[$i]);
		}
		$this->_savePoints = array_values($this->_savePoints);

		if ($level == 0) {
			$ret = $this->pdo()->commit(); //->exec ('COMMIT');
		}

		return $ret;
	}

	public function rollback(string $name = null) {
		$level = -1;
		$max = count($this->_savePoints);

		if (!isset ($name)) {
			for ($i = $max - 1; $i >= 0; $i--) {
				if (strpos($this->_savePoints[$i], '__transaction_') === 0) {
					$name = $this->_savePoints[$i];
					$level = $i;
					break;
				}
			}
		}
		else {
			for ($i = 0; $i < $max; $i++) {
				if ($name == $this->_savePoints[$i]) {
					$level = $i;
					break;
				}
			}
		}

		// Transaction was not found
		if ($level < 0 || !isset ($name))
			return false;

		// Commit all child savepoints
		for ($i = $max - 1; $i >= $level; $i--) {
			$ret = $this->pdo()->exec("ROLLBACK TO SAVEPOINT " . $this->_savePoints[$i]);
			unset ($this->_savePoints[$i]);
		}
		$this->_savePoints = array_values($this->_savePoints);

		if ($level == 0) {
			$ret = $this->pdo()->rollBack(); //->exec ('ROLLBACK');
		}

		return $ret;
	}
	#endregion

	#region DataSet methods
	/**
	 * @param DataTable                         $parent
	 * @param DataTableCollection               $tables
	 * @param DataRelationCollection            $relations
	 * @param DataColumnCollection              $columns
	 * @param DataColumnCollection              $listColumns
	 * @param DataFilterCollection|DataFilter   $filters
	 * @param DataSortingCollection|DataSorting $sorting
	 * @param DataColumnCollection|DataColumn   $grouping
	 * @param DataFilterCollection|DataFilter   $having
	 * @param int                               $start
	 * @param int                               $limit
	 *
	 * @return int
	 */
	public function retrieve(DataTable $parent, DataTableCollection $tables, DataRelationCollection $relations, DataColumnCollection $columns, DataColumnCollection $listColumns, $filters = null, $sorting = null, $grouping = null, $having = null, $start = null, $limit = null): bool|int {
		$parent->rows->clear();

		// Get the generated query
		$sql = $this->retrieveQuery($parent, $tables, $relations, $columns, $listColumns, $filters, $sorting, $grouping, $having, $start, $limit);

		#region Retrieve data
		$rows = $this->fetchAll($sql, null, $start, $limit);
		if ($rows === false) {
			return false;
		}

		#region Convert Date/time columns' value into \DateTime objects
		/** @var DataColumn[] $dateCols */
		$dateCols = array ();
		foreach ($parent->columns->all() as $c) {
			if ($c->dataType == DataColumn::DataTypeDate || $c->dataType == DataColumn::DataTypeDateTime)
				$dateCols[] = $c;
		}

		$max = count($rows);
		for ($num = 0; $num < $max; $num++) {
			foreach ($dateCols as $c) {
				if ($rows[$num][$c->tag] === null) {
					continue;
				}
				$rows[$num][$c->tag] = \DateTime::createFromFormat($this->getDateNativeFormat($c->dataType == DataColumn::DataTypeDateTime || strlen($rows[$num][$c->tag]) > 10), $rows[$num][$c->tag]);
			}
		}

		/** @var DataColumn[] $timeCols */
		$timeCols = array ();
		foreach ($parent->columns->all() as $c) {
			if ($c->dataType == DataColumn::DataTypeTime)
				$timeCols[] = $c;
		}

		$max = count($rows);
		for ($num = 0; $num < $max; $num++) {
			foreach ($timeCols as $c) {
				if ($rows[$num][$c->tag] === null) {
					continue;
				}
				$time = \DateTime::createFromFormat($this->getTimeNativeFormat(), $rows[$num][$c->tag]);
				// Set same date to all time fields, to allow correct comparisons later on
				$time->setDate(2000, 1, 1);
				$rows[$num][$c->tag] = $time;
			}
		}
		#endregion

		foreach ($rows as $row) {
			$dr = new DataRecord ($row, $parent);
			$dr->source = DataRow::SourceDatabase;
			$parent->rows->add($dr);
		}
		#endregion

		return $parent->rows->count();
	}

	/**
	 * @param DataTable $parent
	 * @param DataTableCollection $tables
	 * @param DataRelationCollection $relations
	 * @param DataColumnCollection $columns
	 * @param DataColumnCollection $listColumns
	 * @param DataFilterCollection|DataFilter|array|null $filters
	 * @param DataSorting|DataSortingCollection|array|null $sorting
	 * @param DataColumn|DataColumnCollection|array|null $grouping
	 * @param DataFilterCollection|DataFilter|array|null $having
	 * @param ?int $start
	 * @param ?int $limit
	 *
	 * @return string
	 */
	public function retrieveQuery(DataTable $parent, DataTableCollection $tables, DataRelationCollection $relations, DataColumnCollection $columns, DataColumnCollection $listColumns, DataFilterCollection|DataFilter|array $filters = null, DataSorting|DataSortingCollection|array $sorting = null, DataColumn|DataColumnCollection|array $grouping = null, DataFilterCollection|DataFilter|array $having = null, int $start = null, int $limit = null): string {
		$sql = array ();
		$sql[] = "SELECT ";

		#region Build columns
		$cols = array ();
		foreach ($listColumns->filter(function (DataColumn $c) { return $c->isActive && !$c->isFake; })->all() as $c) {
			$cols[] = $c->columnName(true, true);
		}
		$sql[] = implode(",\n\t", $cols);
		#endregion

		#region Build table joins
		$relations->sort();
		if ($relations->count() == 0)
			$masterTbl = $tables->first();
		else
			$masterTbl = $relations->first()->parent;

		$sql[] = "FROM " . $masterTbl->name . (strlen($masterTbl->alias ?? '') > 0 ? ' ' . $masterTbl->alias : '');
		$joins = [$masterTbl->db()->getDatabaseName() . '.' . $masterTbl->name . '.' . $masterTbl->alias => true];
		foreach ($this->sortRelations($relations)->all() as $rel) {
			$pTblTag = $rel->parent->db()->getDatabaseName() . '.' . $rel->parent->name . '.' . $rel->parent->alias;
			$cTblTag = $rel->child->db()->getDatabaseName() . '.' . $rel->child->name . '.' . $rel->child->alias;

			if (isset ($joins[$cTblTag])) {
				if (isset ($joins[$pTblTag])) {
					$sql[] = ' AND ' . $rel->getExpression(DataRelation::ExprBothJoined);
				}
				else {
					$sql[] = $rel->getExpression(DataRelation::ExprChildJoined);
					$joins[$pTblTag] = true;
				}
			}
			else {
				$sql[] = $rel->getExpression();
				$joins[$cTblTag] = true;
			}
		}

		#region Join translation tables
		foreach ($tables->all() as $tbl) {
			if (!$tbl->isMultilingual())
				continue;

			// Left join the translation table
			$trName = $tbl->name . 'Tr';
			$tblAlias = (strlen($tbl->alias) > 0) ? $tbl->alias : $tbl->name;
			$trAlias = $tblAlias . 'Tr';
			$trCriteria = array ();

			$keyCols = $tbl->columns->filter(function (DataColumn $c) { return $c->isActive && $c->isKey; })->all();
			foreach ($keyCols as $c) {
				if ($c->table === $tbl)
					$trCriteria[] = "$tblAlias.$c->name=$trAlias.$c->name";
			}
			$lang = CMS::translator()->currentLanguage()->code;
			$trCriteria[] = "$trAlias.language_code='$lang'";

			$trCriteria = implode(' AND ', $trCriteria);
			$sql[] = "LEFT JOIN $trName $trAlias ON ($trCriteria)";
		}
		#endregion
		#endregion

		#region Build criteria
		if ($filters != null && ($filters instanceof DataFilter || ($filters instanceof DataFilterCollection && $filters->count() > 0) || (is_array($filters) && count($filters) > 0))) {
			$filterExpr = $this->getFilterExpression($filters);
			if (strlen($filterExpr) > 0)
				$sql[] = "WHERE $filterExpr";
		}
		#endregion

		#region Build grouping
		if ($grouping != null) {
			if ($grouping instanceof DataColumn) {
				$groupExpr = array ($grouping->tag);
			}
			elseif ($grouping instanceof DataColumnCollection && $grouping->count() > 0) {
				$groupExpr = array ();
				foreach ($grouping->filter(function (DataColumn $c) { return $c->isActive; })->all() as $col)
					$groupExpr[] = $col->tag;
			}
			if (isset ($groupExpr)) {
				$sql[] = "GROUP BY " . implode(', ', $groupExpr);
			}
		}
		#endregion

		#region Build having
		if (isset ($having) && ($having instanceof DataFilter || ($having instanceof DataFilterCollection && $having->count() > 0) || (is_array($having) && count($having) > 0))) {
			$filterExpr = $this->getFilterExpression($having);
			if (strlen($filterExpr) > 0)
				$sql[] = "HAVING $filterExpr";
		}
		#endregion

		#region Build sorting
		if ($sorting != null && ($sorting instanceof DataSorting || ($sorting instanceof DataSortingCollection && $sorting->count() > 0) || (is_array($sorting) && count($sorting) > 0))) {
			$sortExpr = $this->getSortingExpression($sorting);
			if (strlen($sortExpr) > 0)
				$sql[] = "ORDER BY $sortExpr";
		}
		#endregion

		return implode("\n", $sql);
	}

	/**
	 * Retrieves the count of rows from database that match the tables' relation and filters
	 *
	 * @param DataTable                       $parent
	 * @param DataTableCollection             $tables
	 * @param DataRelationCollection          $relations
	 * @param DataFilterCollection|DataFilter $filters
	 * @param DataColumnCollection|DataColumn $grouping
	 * @param DataFilterCollection|DataFilter $having
	 *
	 * @return int The number of rows that match the tables' relation and filters
	 */
	public function retrieveCnt(DataTable $parent, DataTableCollection $tables, DataRelationCollection $relations, $filters = null, $grouping = null, $having = null): int {
		$sql = array ();
		$sql[] = "SELECT count(1) AS cnt";

		$parent->rows->clear();

		#region Build table joins
		$relations->sort();
		if ($relations->count() == 0)
			$masterTbl = $tables->first();
		else
			$masterTbl = $relations->first()->parent;

		$sql[] = "FROM " . $masterTbl->name . (strlen($masterTbl->alias ?? '') > 0 ? ' ' . $masterTbl->alias : '');
		$joins = [$masterTbl->db()->getDatabaseName() . '.' . $masterTbl->name . '.' . $masterTbl->alias => true];
		foreach ($this->sortRelations($relations)->all() as $rel) {
			$pTblTag = $rel->parent->db()->getDatabaseName() . '.' . $rel->parent->name . '.' . $rel->parent->alias;
			$cTblTag = $rel->child->db()->getDatabaseName() . '.' . $rel->child->name . '.' . $rel->child->alias;

			if (isset ($joins[$cTblTag])) {
				if (isset ($joins[$pTblTag])) {
					$sql[] = ' AND ' . $rel->getExpression(DataRelation::ExprBothJoined);
				}
				else {
					$sql[] = $rel->getExpression(DataRelation::ExprChildJoined);
					$joins[$pTblTag] = true;
				}
			}
			else {
				$sql[] = $rel->getExpression();
				$joins[$cTblTag] = true;
			}
		}

		#region Join translation tables
		foreach ($tables->all() as $tbl) {
			if (!$tbl->isMultilingual())
				continue;

			// Left join the translation table
			$trName = $tbl->name . 'Tr';
			$tblAlias = (strlen($tbl->alias) > 0) ? $tbl->alias : $tbl->name;
			$trAlias = $tblAlias . 'Tr';
			$trCriteria = array ();

			$keyCols = $tbl->columns->filter(function (DataColumn $c) { return $c->isActive && $c->isKey; })->all();
			foreach ($keyCols as $c) {
				if ($c->table === $tbl)
					$trCriteria[] = "$tblAlias.$c->name=$trAlias.$c->name";
			}
			$lang = CMS::translator()->currentLanguage()->code;
			$trCriteria[] = "$trAlias.language_code='$lang'";

			$trCriteria = implode(' AND ', $trCriteria);
			$sql[] = "LEFT JOIN $trName $trAlias ON ($trCriteria)";
		}
		#endregion
		#endregion

		#region Build criteria
		if ($filters != null && ($filters instanceof DataFilter || ($filters instanceof DataFilterCollection && $filters->count() > 0))) {
			$filterExpr = $this->getFilterExpression($filters);
			if (strlen($filterExpr) > 0)
				$sql[] = "WHERE $filterExpr";
		}
		#endregion

		#region Build grouping
		if ($grouping != null) {
			if ($grouping instanceof DataColumn) {
				$groupExpr = array ($grouping->tag);
			}
			elseif ($grouping instanceof DataColumnCollection && $grouping->count() > 0) {
				$groupExpr = array ();
				foreach ($grouping->filter(function (DataColumn $c) { return $c->isActive; })->all() as $col)
					$groupExpr[] = $col->tag;
			}
			if (isset ($groupExpr)) {
				$sql[] = "GROUP BY " . implode(', ', $groupExpr);
			}
		}
		#endregion

		#region Build having
		if (isset ($having) && ($having instanceof DataFilter || ($having instanceof DataFilterCollection && $having->count() > 0))) {
			$filterExpr = $this->getFilterExpression($having);
			if (strlen($filterExpr) > 0)
				$sql[] = "HAVING $filterExpr";
		}
		#endregion

		$sql = implode("\n", $sql);

		return (int)$this->fetchColumn($sql, 'cnt');
	}
	#endregion

	#region Expression Methods
	/** @inheritdoc */
	public function getColumnExpression(DataColumn $column, bool $prefixTableAlias = false, bool $suffixColumnAlias = false): string {
		$tblName = (strlen($column->table->alias ?? '') > 0) ? $column->table->alias : $column->table->db()->getDatabaseName() . '.' . $column->table->name;
		if ($column->isMultilingual)
			$tblName .= 'Tr';

		if ($column->isExpression)
			return $suffixColumnAlias ? "$column->name AS $column->tag" : $column->name;

		if (($column->dataType == DataColumn::DataTypeDate || $column->dataType == DataColumn::DataTypeDateTime) && $this->timezone->getName() != CMS::timezone()->getName()) {
			if ($prefixTableAlias) {
				return 'CONVERT_TZ(' . $tblName . '.' . $this->_quote . $column->name . $this->_quote . ", '" . $this->timezone->getName() . "', '" . CMS::timezone()->getName() . "')" . ($suffixColumnAlias ? ' AS ' . $this->_quote . $column->tag . $this->_quote : '');
			} else {
				if ($suffixColumnAlias) {
					return 'CONVERT_TZ(' . $column->name . ", '" . $this->timezone->getName() . "', '" . CMS::timezone()->getName() . "')" . ($suffixColumnAlias && $column->tag != $column->name ? ' AS ' . $this->_quote . $column->tag . $this->_quote : '');
				} else {
					// If neither table prefix nor tag suffix are required, it means it's used in INSERT/UPDATE statements
					return $column->name;
				}
			}
		} elseif (($column->dataType == DataColumn::DataTypeTime) && $this->timezone->getName() != CMS::timezone()->getName()) {
			if ($prefixTableAlias) {
				return 'TIME(' . $tblName . '.' . $this->_quote . $column->name . $this->_quote . ")" . ($suffixColumnAlias ? ' AS ' . $this->_quote . $column->tag . $this->_quote : '');
			} else {
				if ($suffixColumnAlias) {
					return 'TIME(' . $column->name . ")" . ($suffixColumnAlias && $column->tag != $column->name ? ' AS ' . $this->_quote . $column->tag . $this->_quote : '');
				} else {
					// If neither table prefix nor tag suffix are required, it means it's used in INSERT/UPDATE statements
					return $column->name;
				}
			}
		} elseif ($column->dataType == DataColumn::DataTypeGeoPoint) {
			if ($prefixTableAlias) {
				return sprintf("CONCAT(x(%s),',',y(%s))",
						$tblName . '.' . $this->_quote . $column->name . $this->_quote,
						$tblName . '.' . $this->_quote . $column->name . $this->_quote) . ($suffixColumnAlias ? ' AS ' . $this->_quote . $column->tag . $this->_quote : '');
			}
			else {
				if ($suffixColumnAlias) {
					return sprintf("CONCAT(x(%s),',',y(%s))",
							$this->_quote . $column->name . $this->_quote,
							$this->_quote . $column->name . $this->_quote) . ($suffixColumnAlias && $column->tag != $column->name ? ' AS ' . $this->_quote . $column->tag . $this->_quote : '');
				} else {
					// If neither table prefix nor tag suffix are required, it means it's used in INSERT/UPDATE statements
					return $column->name;
				}
			}
		} else {
			if ($prefixTableAlias)
				return $tblName . '.' . $this->_quote . $column->name . $this->_quote . ($suffixColumnAlias && $column->tag != $column->name ? ' AS ' . $this->_quote . $column->tag . $this->_quote : '');
			else
				return $column->name . ($suffixColumnAlias && $column->tag != $column->name ? ' AS ' . $this->_quote . $column->tag . $this->_quote : '');
		}
	}

	/** @inheritdoc */
	public function getFilterExpression(DataFilterCollection|DataFilter|array $filter): string|array {
		if ($filter instanceof DataFilter) {
			$value = $filter->value;
			if ($value instanceof DataColumn) {
				$value = $value->columnName(!$filter->column->isExpression);
				$q = '';
			}
			else {
				$q = "'";
				if ($value instanceof \DateTime) {
					$q = '';

					// Ensure date is in environment's timezone
					$date = clone $value;
					$date->setTimezone(CMS::timezone());
					$value = "'" . $date->format('Y-m-d H:i:s') . "'";
				}
				elseif (is_array($value)) {
					$max = count($value);
					for ($num = 0; $num < $max; $num++) {
						if ($value[$num] instanceof \DateTime) {
							$q = '';
							// Ensure date is in environment's timezone
							$date = clone $value[$num];
							$date->setTimezone(CMS::timezone());
							$value[$num] = "'" . $date->format('Y-m-d H:i:s') . "'";
						}
					}
				}
				elseif ($value instanceof DateTimeRange) {
					$q = '';
					$date = [];
					$sDate = clone $value->startDate;
					$eDate = clone $value->endDate;

					$sDate->setTimezone(CMS::timezone());
					$eDate->setTimezone(CMS::timezone());

					$date[] = "'" . $sDate->format('Y-m-d H:i:s') . "'";
					$date[] = "'" . $eDate->format('Y-m-d H:i:s') . "'";
					$value = $date;
				}
				elseif (is_bool($value)) {
					$value = (bool)$value ? 1 : 0;
				}
			}

			if ($filter->column->isExpression) {
				$fieldName = $filter->column->name;
			}
			elseif (($filter->column->dataType == DataColumn::DataTypeDate || $filter->column->dataType == DataColumn::DataTypeDateTime) && $this->timezone->getName() != CMS::timezone()->getName()) {
				$fieldName = "DATE_FORMAT(" . $filter->column->columnName(true) . ", '%Y-%m-%d %H:%i:%s')";        // Timezone conversion is done in columnName() method
			}
			elseif (($filter->column->dataType == DataColumn::DataTypeTime) && $this->timezone->getName() != CMS::timezone()->getName()) {
				$fieldName = "TIME_FORMAT(" . $filter->column->columnName(true) . ", '%H:%i:%s')";    // No need for timezone conversion for time fields
			}
			else {
				$fieldName = $filter->column->columnName(true);
			}

			if ($value instanceof DataExpression) {
				switch ($filter->condition) {
					case DataFilter::Equals:
						return "$fieldName=$value";
					case DataFilter::NotEqual:
						return "$fieldName<>$value";
					case DataFilter::Contains:
						return "$fieldName LIKE $value";
					case DataFilter::NotContain:
						return "$fieldName NOT LIKE $value";
					case DataFilter::StartsWith:
						return "$fieldName LIKE $value";
					case DataFilter::NotStartWith:
						return "$fieldName NOT LIKE $value";
					case DataFilter::EndsWith:
						return "$fieldName LIKE $value";
					case DataFilter::NotEndWith:
						return "$fieldName NOT LIKE $value";
					case DataFilter::GreaterThan:
						return "$fieldName>$value";
					case DataFilter::LessThan:
						return "$fieldName<$value";
					case DataFilter::GreaterOrEqual:
						return "$fieldName>=$value";
					case DataFilter::LessOrEqual:
						return "$fieldName<=$value";
					case DataFilter::IsEmpty:
						return "($fieldName='' OR $fieldName IS NULL)";
					case DataFilter::IsNull:
						return "$fieldName IS NULL";
					case DataFilter::NotEmpty:
						return "length($fieldName)>0";
					case DataFilter::NotNull:
						return "$fieldName IS NOT NULL";
					case DataFilter::InList:
						return "$fieldName IN (" . $value . ")";
					case DataFilter::NotInList:
						return "$fieldName NOT IN (" . $value . ")";
					case DataFilter::InSet:
						return "find_in_set('$value', $fieldName)";
					case DataFilter::NotInSet:
						return "NOT find_in_set('$value', $fieldName)";
					case DataFilter::Between:
						return "$fieldName BETWEEN $value";
					case DataFilter::Custom:
						return str_ireplace('{field}', $fieldName, (string)$value);
					default:
						$this->logger()->log(Logger::NOTICE, "Condition '$filter->condition' not supported' by MySQL database driver");
						return '1=2';
				}
			}
			else {
				switch ($filter->condition) {
					case DataFilter::Equals:
						return "$fieldName=" . $q . $value . $q;
					case DataFilter::NotEqual:
						return "$fieldName<>" . $q . $value . $q;
					case DataFilter::Contains:
						return "$fieldName LIKE " . $q . "%$value%" . $q;
					case DataFilter::NotContain:
						return "$fieldName NOT LIKE " . $q . "%$value%" . $q;
					case DataFilter::StartsWith:
						return "$fieldName LIKE " . $q . $value . '%' . $q;
					case DataFilter::NotStartWith:
						return "$fieldName NOT LIKE " . $q . $value . '%' . $q;
					case DataFilter::EndsWith:
						return "$fieldName LIKE " . $q . "%$value" . $q;
					case DataFilter::NotEndWith:
						return "$fieldName NOT LIKE " . $q . "%$value" . $q;
					case DataFilter::GreaterThan:
						return "$fieldName>$value";
					case DataFilter::LessThan:
						return "$fieldName<$value";
					case DataFilter::GreaterOrEqual:
						return "$fieldName>=$value";
					case DataFilter::LessOrEqual:
						return "$fieldName<=$value";
					case DataFilter::IsEmpty:
						return "($fieldName='' OR $fieldName IS NULL)";
					case DataFilter::IsNull:
						return "$fieldName IS NULL";
					case DataFilter::NotEmpty:
						return "length($fieldName)>0";
					case DataFilter::NotNull:
						return "$fieldName IS NOT NULL";
					case DataFilter::InList:
						return "$fieldName IN ('" . (is_array($value) ? implode("', '", $value) : $value) . "')";
					case DataFilter::NotInList:
						return "$fieldName NOT IN ('" . (is_array($value) ? implode("', '", $value) : $value) . "')";
					case DataFilter::InSet:
						return "find_in_set(" . $q . $value . $q . ", $fieldName)";
					case DataFilter::NotInSet:
						return "NOT find_in_set(" . $q . $value . $q . ", $fieldName)";
					case DataFilter::Between:
						return "$fieldName BETWEEN $q" . $value[0] . "$q AND $q" . $value[1] . "$q";
					case DataFilter::Custom:
						return str_ireplace('{field}', $fieldName, $value);
					default:
						CMS::logger()->warning("Condition '$filter->condition' not supported' by MySQL database driver");
						return '1=2';
				}
			}
		}
		elseif ($filter instanceof DataFilterCollection) {
			$sql = array ();

			foreach ($filter->all() as $f) {
				if ($f instanceof DataFilter) {
					$expr = $f->getExpression();
					if (strlen($expr) > 0) {
						$sql[] = $expr;
					}
					else {
						// If expression is invalid, disallow returning any record
						$sql[] = '1=2';
					}
				}
				elseif ($f instanceof DataFilterCollection) {
					$sql[] = static::getFilterExpression($f);
				}
			}

			$operand = $filter->operand == DataFilterCollection::OperandOr ? 'OR' : 'AND';

			// Ensure that if the imploded filters are empty, the query won't break
			$sql = implode(") $operand (", $sql);
			if (strlen($sql) > 0)
				$sql = "($sql)";

			return $sql;
		}
		elseif (is_array($filter)) {
			$sql = [];

			foreach ($filter as $f) {
				if ($f instanceof DataFilter) {
					$expr = $f->getExpression();
					if (strlen($expr) > 0) {
						$sql[] = $expr;
					}
					else {
						// If expression is invalid, disallow returning any record
						$sql[] = '1=2';
					}
				}
				elseif ($f instanceof DataFilterCollection) {
					$sql[] = static::getFilterExpression($f);
				}
			}

			// Assume AND as operand for filters passed as an array
			$operand = 'AND';

			// Ensure that if the imploded filters are empty, the query won't break
			$sql = implode(") $operand (", $sql);
			if (strlen($sql) > 0)
				$sql = "($sql)";

			return $sql;
		}
		else {
			throw new \InvalidArgumentException();
		}
	}

	/**
	 * Returns a unified sorting expression for the given sorting, compatible to the database's syntax
	 *
	 * @param array|DataSorting|DataSortingCollection $sorting
	 *
	 * @return string
	 * @throws \InvalidArgumentException
	 */
	public function getSortingExpression(array|DataSorting|DataSortingCollection $sorting): string {
		if ($sorting instanceof DataSorting) {
			return $sorting->column->columnName(true) . (($sorting->mode == DataSorting::Descending) ? ' DESC' : ' ASC');
		}
		elseif ($sorting instanceof DataSortingCollection) {
			$sql = [];

			foreach ($sorting->all() as $s) {
				$expr = $s->getExpression();
				if (strlen($expr) > 0)
					$sql[] = $expr;
			}

			return implode(', ', $sql);
		}
		elseif (is_array($sorting)) {
			$sql = [];

			foreach ($sorting as $s) {
				if ($s instanceof DataSorting) {
					$expr = $s->getExpression();
					if (strlen($expr) > 0)
						$sql[] = $expr;
				}
			}

			return implode(', ', $sql);
		}
		else {
			throw new \InvalidArgumentException();
		}
	}

	/**
	 * Returns a unified join expression for the given table relation(s), compatible to the database's syntax
	 *
	 * @param DataRelation|DataRelationCollection $relation
	 * @param string $mode Join expression mode. Valid values are DataRelation::Expr* constants
	 *
	 * @return string
	 * @throws \InvalidArgumentException
	 */
	public function getRelationExpression(DataRelationCollection|DataRelation $relation, string $mode = DataRelation::ExprParentJoined): string {
		if ($relation instanceof DataRelation) {
			switch ($relation->joinType) {
				case DataRelation::JoinLeft:
					$joinType = "LEFT JOIN";
					break;
				case DataRelation::JoinInner:
				default:
					$joinType = "JOIN";
					break;
			}

			if ($mode == DataRelation::ExprParentJoined) {
				$tblName = $relation->child->name . ' ' . ((strlen($relation->child->alias) > 0) ? $relation->child->alias : '');
				$tblAlias = (strlen($relation->child->alias) > 0) ? $relation->child->alias : $relation->child->name;
			}
			elseif ($mode == DataRelation::ExprChildJoined) {
				$tblName = $relation->parent->name . ' ' . ((strlen($relation->parent->alias) > 0) ? $relation->parent->alias : '');
				$tblAlias = (strlen($relation->parent->alias) > 0) ? $relation->parent->alias : $relation->parent->name;
			}

			#region Build join criteria
			$criteria = array ();
			$links = $relation->getLinks();
			foreach ($links as $pair) {
				/** @var DataColumn */
				$pCol = $pair[0];
				/** @var DataColumn */
				$cCol = $pair[1];
				if ($pCol instanceof DataColumn && $cCol instanceof DataColumn) {
					$pName = ((strlen($pCol->table->alias) > 0) ? $pCol->table->alias : $pCol->table->name) . '.' . $pCol->name;
					$cName = ((strlen($cCol->table->alias) > 0) ? $cCol->table->alias : $cCol->table->name) . '.' . $cCol->name;
					$criteria[] = "$pName=$cName";
				}
			}

			// Build custom criteria, if any
			if ($relation->criteria instanceof DataFilterCollection || $relation->criteria instanceof DataFilter) {
				$criteria[] = $this->getFilterExpression($relation->criteria);
			}
			elseif (is_string($relation->criteria) && strlen($relation->criteria) > 0) {
				$criteria[] = $relation->criteria;
			}

			$criteria = implode(' AND ', $criteria);
			#endregion

			if ($mode == DataRelation::ExprBothJoined) {
				$sql[] = $criteria;
			}
			else {
				$sql[] = "$joinType $tblName ON $criteria";
			}

			return implode("\n", $sql);
		}
		elseif ($relation instanceof DataRelationCollection) {
			$sql = array ();

			$relation->sort();

			foreach ($relation->all() as $r) {
				$sql[] = $r->getExpression();
			}

			return implode("\n", $sql);
		}
		else
			throw new \InvalidArgumentException();
	}

	/**
	 * @inheritdoc
	 */
	public function getValueExpression(DataColumn $column, $value = null): mixed {
		switch ($column->dataType) {
			case DataColumn::DataTypeGeoPoint:
				// if value is string, it should be either space or comma separated floating numbers
				if (is_string($value)) {
					$pt = explode(',', $value);
					if (count($pt) == 2) {
						// Comma-separated, ensure they are floats
						if (is_numeric($pt[0]) && is_numeric($pt[1]))
							return sprintf('PointFromText(\'POINT(%s %s)\')', (float)$pt[0], (float)$pt[1]);
						else
							return null;
					} else {
						$pt = explode(' ', $value);
						if (count($pt) == 2) {
							// Space-separated, ensure they are floats
							if (is_numeric($pt[0]) && is_numeric($pt[1]))
								return sprintf('PointFromText(\'POINT(%s %s)\')', (float)$pt[0], (float)$pt[1]);
							else
								return null;
						}
					}
				}
				// If value is object, it should have lat/lng properties
				elseif ($value instanceof \stdClass || is_object($value)) {
					if (isset($value->lat) && isset($value->lng))
						return sprintf('PointFromText(\'POINT(%F %F)\')', $value->lat, $value->lng);
					else
						return null;
				}
				elseif (is_array($value)) {
					if (isset($value['lat']) && isset($value['lng']))
						return sprintf('PointFromText(\'POINT(%F %F)\')', $value['lat'], $value['lng']);
					else
						return null;
				}
				break;

			default:
				return $value;
		}

		return null;
	}
	#endregion

	#region Misc. methods
	protected function addLimitParams($query, ?int $start = null, ?int $limit = null): string {
		if ($start === null && $limit === null)
			return $query;

		$sql = 'LIMIT ';
		$params = [];
		if ($start !== null)
			$params[] = (int)$start;
		if ($limit !== null)
			$params[] = (int)$limit;

		$sql .= implode(',', $params);

		return "$query $sql";
	}

	public function usesTablePrefixInQueries(): bool {
		return true;
	}

	public function getDateNativeFormat(bool $includeTime = true): string {
		return ($includeTime) ? 'Y-m-d H:i:s' : 'Y-m-d';
	}

	public function getTimeNativeFormat(): string {
		return 'H:i:s';
	}
	#endregion
	#endregion
}
