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
use aneya\Core\Data\DataColumn;
use aneya\Core\Data\DataColumnCollection;
use aneya\Core\Data\DataFilter;
use aneya\Core\Data\DataFilterCollection;
use aneya\Core\Data\DataRelation;
use aneya\Core\Data\DataRelationCollection;
use aneya\Core\Data\DataTable;
use aneya\Core\Data\DataTableCollection;
use aneya\Core\Data\RDBMS;
use aneya\UI\Calendar\DateTimeRange;

final class MSSQL extends RDBMS {
	// TODO: Class needs migration

	#region Constructor
	public function __construct() {
		parent::__construct();

		$this->type = RDBMS::MSSQL;
		$this->schema = new MSSQLSchema();
		$this->schema->setDatabaseInstance ($this);
	}
	#endregion

	#region Methods
	#region connection methods
	public function connect (ConnectionOptions $options) {
		if (isset ($this->link))
			$this->disconnect();

		$this->server = $options->host;
		$this->database = $options->database;
		$this->username = $options->username;
		$this->password = $options->password;
		$this->encoding = $options->charset;
		$this->timezone = $options->timezone;

		$this->link = mssql_connect ($this->options->host, $this->options->username, $this->options->password, true);
		if (!$this->link) {
			unset ($this->link);
			return false;
		}

		mssql_select_db ($this->options->database, $this->link);
//		mssql_query ("SET TIME_ZONE='" . $this->timezone . "'", $this->link);
//		mssql_query ("SET SESSION TIME_ZONE='" . $this->timezone . "'", $this->link);

		ini_set ('mssql.charset', $this->options->charset);

		return true;
	}

	public function reconnect () {
		if (!$this->isConnected ()) {
			if (strlen ($this->tag) > 0) {
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
	}

	public function disconnect () {
		if (isset ($this->link)) {
			mssql_close ($this->link);
			unset ($this->link);
		}
	}

	protected function getConnectionString () {
		// TODO: Fix
		return sprintf('mssql:host=%s;dbname=%s;port=%d;charset=%s;', $this->options->host, $this->options->database, $this->options->port, $this->options->charset);
	}
	#endregion

	#region DataSet methods
	/**
	 * @param DataTable							$parent
	 * @param DataTableCollection				$tables
	 * @param DataRelationCollection			$relations
	 * @param DataColumnCollection				$columns
	 * @param DataColumnCollection				$listColumns
	 * @param DataFilterCollection|DataFilter	$filters
	 * @param DataSortingCollection|DataSorting	$sorting
	 * @param DataColumnCollection|DataColumn	$grouping
	 * @param DataFilterCollection|DataFilter	$having
	 * @param int								$start
	 * @param int								$limit
	 * @return DataRecord[]
	 */
	public function retrieve (DataTable $parent, DataTableCollection $tables, DataRelationCollection $relations, DataColumnCollection $columns, DataColumnCollection $listColumns, $filters = null, $sorting = null, $grouping, $having, $start = null, $limit = null) {
		$parent->rows->clear();

		// Get the generated query
		$sql = $this->retrieveQuery ($parent, $tables, $relations, $columns, $listColumns, $filters, $sorting, $grouping, $having, $start, $limit);

		#region Retrieve data
		$rows = $this->fetchAll ($sql, null, $start, $limit);
		if ($rows === false) {
			return false;
		}

		#region Convert Date/time columns' value into \DateTime objects
		/** @var DataColumn[] $dateCols */
		$dateCols = array();
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
		#endregion

		foreach ($rows as $row) {
			$dr = new DataRecord ($row, $parent);
			$dr->source = DataRow::SourceDatabase;
			$parent->rows->add ($dr);
		}
		#endregion

		return $parent->rows->count ();
	}

	/**
	 * @param DataTable							$parent
	 * @param DataTableCollection				$tables
	 * @param DataRelationCollection			$relations
	 * @param DataColumnCollection				$columns
	 * @param DataColumnCollection				$listColumns
	 * @param DataFilterCollection|DataFilter	$filters
	 * @param DataSortingCollection|DataSorting	$sorting
	 * @param DataColumnCollection|DataColumn	$grouping
	 * @param DataFilterCollection|DataFilter	$having
	 * @param int								$start
	 * @param int								$limit
	 * @return string
	 */
	public function retrieveQuery (DataTable $parent, DataTableCollection $tables, DataRelationCollection $relations, DataColumnCollection $columns, DataColumnCollection $listColumns, $filters = null, $sorting = null, $grouping, $having, $start = null, $limit = null) {
		$sql = array ();
		$sql[] = "SELECT ";

		#region Build columns
		$cols = array ();
		foreach ($listColumns->all(DataColumn::FlagActive & ~DataColumn::FlagFake) as $c) {
			$cols[] = $c->columnName (true, true);
		}
		$sql[] = implode (",\n\t", $cols);
		#endregion

		#region Build table joins
		$relations->sort();
		if ($relations->count() == 0)
			$masterTbl = $tables->first();
		else
			$masterTbl = $relations->first()->parent;

		$sql[] = "FROM " . $masterTbl->name . (strlen ($masterTbl->alias) > 0 ? ' ' . $masterTbl->alias : '');
		$joins = [$masterTbl->db()->schemaTag . '.' . $masterTbl->name . '.' . $masterTbl->alias => true];
		foreach ($relations->all() as $rel) {
			$pTblTag = $rel->parent->db()->schemaTag . '.' . $rel->parent->name . '.' . $rel->parent->alias;
			$cTblTag = $rel->child->db()->schemaTag . '.' . $rel->child->name . '.' . $rel->child->alias;

			if (isset ($joins[$cTblTag])) {
				if (isset ($joins[$pTblTag])) {
					$sql[] = ' AND ' . $rel->getExpression (DataRelation::ExprBothJoined);
				}
				else {
					$sql[] = $rel->getExpression (DataRelation::ExprChildJoined);
					$joins[$pTblTag] = true;
				}
			}
			else {
				$sql[] = $rel->getExpression ();
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

			$keyCols = $tbl->columns->all (DataColumn::FlagActive | DataColumn::FlagKey);
			foreach ($keyCols as $c) {
				if ($c->table === $tbl)
					$trCriteria[] = "$tblAlias.$c->name=$trAlias.$c->name";
			}
			$lang = CMS::translator()->currentLanguage()->code;
			$trCriteria[] = "$trAlias.language_code='$lang'";

			$trCriteria = implode (' AND ', $trCriteria);
			$sql[] = "LEFT JOIN $trName $trAlias ON ($trCriteria)";
		}
		#endregion
		#endregion

		#region Build criteria
		if ($filters != null && ($filters instanceof DataFilter || ($filters instanceof DataFilterCollection && $filters->count() > 0))) {
			$filterExpr = $this->getFilterExpression ($filters);
			if (strlen ($filterExpr) > 0)
				$sql[] = "WHERE $filterExpr";
		}
		#endregion

		#region Build grouping
		if ($grouping != null) {
			if ($grouping instanceof DataColumn) {
				$groupExpr = array($grouping->tag);
			}
			elseif ($grouping instanceof DataColumnCollection && $grouping->count() > 0) {
				$groupExpr = array();
				foreach ($grouping->all (DataColumn::FlagActive) as $col)
					$groupExpr[] = $col->tag;
			}
			if (isset ($groupExpr)) {
				$sql[] = "GROUP BY " . implode (', ', $groupExpr);
			}
		}
		#endregion

		#region Build having
		if (isset ($having) && ($having instanceof DataFilter || ($having instanceof DataFilterCollection && $having->count() > 0))) {
			$filterExpr = $this->getFilterExpression ($having);
			if (strlen ($filterExpr) > 0)
				$sql[] = "HAVING $filterExpr";
		}
		#endregion

		#region Build sorting
		if ($sorting != null && ($sorting instanceof DataSorting || ($sorting instanceof DataSortingCollection && $sorting->count() > 0))) {
			$sortExpr = $this->getSortingExpression ($sorting);
			if (strlen ($sortExpr) > 0)
				$sql[] = "ORDER BY $sortExpr";
		}
		#endregion

		$sql = implode ("\n", $sql);

		return $sql;
	}

	/**
	 * Retrieves the count of rows from database that match the tables' relation and filters
	 *
	 * @param DataTable							$parent
	 * @param DataTableCollection				$tables
	 * @param DataRelationCollection			$relations
	 * @param DataFilterCollection|DataFilter	$filters
	 * @param DataColumnCollection|DataColumn	$grouping
	 * @param DataFilterCollection|DataFilter	$having
	 * @return int The number of rows that match the tables' relation and filters
	 */
	public function retrieveCnt (DataTable $parent, DataTableCollection $tables, DataRelationCollection $relations, $filters = null, $grouping = null, $having = null) {
		$sql = array ();
		$sql[] = "SELECT count(1) AS cnt";

		$parent->rows->clear();

		#region Build table joins
		$relations->sort();
		if ($relations->count() == 0)
			$masterTbl = $tables->first();
		else
			$masterTbl = $relations->first()->parent;

		$sql[] = "FROM " . $masterTbl->name . (strlen ($masterTbl->alias) > 0 ? ' ' . $masterTbl->alias : '');
		$joins = [$masterTbl->db()->schemaTag . '.' . $masterTbl->name . '.' . $masterTbl->alias => true];
		foreach ($relations->all() as $rel) {
			$pTblTag = $rel->parent->db()->schemaTag . '.' . $rel->parent->name . '.' . $rel->parent->alias;
			$cTblTag = $rel->child->db()->schemaTag . '.' . $rel->child->name . '.' . $rel->child->alias;

			if (isset ($joins[$cTblTag])) {
				if (isset ($joins[$pTblTag])) {
					$sql[] = ' AND ' . $rel->getExpression (DataRelation::ExprBothJoined);
				}
				else {
					$sql[] = $rel->getExpression (DataRelation::ExprChildJoined);
					$joins[$pTblTag] = true;
				}
			}
			else {
				$sql[] = $rel->getExpression ();
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

			$keyCols = $tbl->columns->all (DataColumn::FlagActive | DataColumn::FlagKey);
			foreach ($keyCols as $c) {
				if ($c->table === $tbl)
					$trCriteria[] = "$tblAlias.$c->name=$trAlias.$c->name";
			}
			$lang = CMS::translator()->currentLanguage()->code;
			$trCriteria[] = "$trAlias.language_code='$lang'";

			$trCriteria = implode (' AND ', $trCriteria);
			$sql[] = "LEFT JOIN $trName $trAlias ON ($trCriteria)";
		}
		#endregion
		#endregion

		#region Build criteria
		if ($filters != null && ($filters instanceof DataFilter || ($filters instanceof DataFilterCollection && $filters->count() > 0))) {
			$filterExpr = $this->getFilterExpression ($filters);
			if (strlen ($filterExpr) > 0)
				$sql[] = "WHERE $filterExpr";
		}
		#endregion

		#region Build grouping
		if ($grouping != null) {
			if ($grouping instanceof DataColumn) {
				$groupExpr = array($grouping->tag);
			}
			elseif ($grouping instanceof DataColumnCollection && $grouping->count() > 0) {
				$groupExpr = array();
				foreach ($grouping->all (DataColumn::FlagActive) as $col)
					$groupExpr[] = $col->tag;
			}
			if (isset ($groupExpr)) {
				$sql[] = "GROUP BY " . implode (', ', $groupExpr);
			}
		}
		#endregion

		#region Build having
		if (isset ($having) && ($having instanceof DataFilter || ($having instanceof DataFilterCollection && $having->count() > 0))) {
			$filterExpr = $this->getFilterExpression ($having);
			if (strlen ($filterExpr) > 0)
				$sql[] = "HAVING $filterExpr";
		}
		#endregion

		$sql = implode ("\n", $sql);

		$cnt = (int)$this->fetchColumn ($sql, 'cnt');
		return $cnt;
	}
	#endregion

	#region Expression methods
	/**
	 * Returns a unified filtering expression for all filters in the given array, compatible to the database's syntax
	 * @param DataFilter|DataFilterCollection $filter
	 * @return mixed
	 * @throws \InvalidArgumentException
	 */
	public function getFilterExpression ($filter) {
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
					$value = "'" . $value->format('Y-m-d h:i:s') . "'";
				}
				elseif (is_array($value)) {
					$max = count($value);
					for ($num = 0; $num < $max; $num++) {
						if ($value[$num] instanceof \DateTime) {
							$q = '';
							$value[$num] = "'" . $value[$num]->format('Y-m-d h:i:s') . "'";
						}
					}
				}
				elseif ($value instanceof DateTimeRange) {
					$q = '';
					$date = [];
					$date[] = "'" . $value->startDate->format('Y-m-d h:i:s') . "'";
					$date[] = "'" . $value->endDate->format('Y-m-d h:i:s') . "'";
					$value = $date;
				}
			}
			if ($filter->column->isExpression) {
				$fieldName = $filter->column->name;
			}
			elseif (($filter->column->dataType == DataColumn::DataTypeDate || $filter->column->dataType == DataColumn::DataTypeDateTime) && $this->timezone->getName() != CMS::timezone()->getName()) {
				$fieldName = "DATE_FORMAT(CONVERT_TZ(" . $filter->column->columnName (true) . ", '" . $this->timezone->getName() . "', '" . CMS::timezone()->getName() . "')" . ", '%Y-%m-%d %H:%i%s')";
			}
			else {
				$fieldName = $filter->column->columnName (true);
			}
			if ($value instanceof DataExpression) {
				switch ($filter->condition) {
					case DataFilter::Equals: 			return "$fieldName=" . $value;
					case DataFilter::NotEqual: 			return "$fieldName<>" . $value;
					case DataFilter::Contains: 			return "$fieldName LIKE " . $value;
					case DataFilter::NotContain: 		return "$fieldName NOT LIKE " . $value;
					case DataFilter::StartsWith: 		return "$fieldName LIKE " . $value;
					case DataFilter::NotStartWith:		return "$fieldName NOT LIKE " . $value;
					case DataFilter::EndsWith:			return "$fieldName LIKE " . $value;
					case DataFilter::NotEndWith:		return "$fieldName NOT LIKE " . $value;
					case DataFilter::GreaterThan:		return "$fieldName>$value";
					case DataFilter::LessThan:			return "$fieldName<$value";
					case DataFilter::GreaterOrEqual:	return "$fieldName>=$value";
					case DataFilter::LessOrEqual:		return "$fieldName<=$value";
					case DataFilter::IsEmpty:			return "($fieldName='' OR $fieldName IS NULL)";
					case DataFilter::IsNull:			return "$fieldName IS NULL";
					case DataFilter::NotEmpty:			return "length($fieldName)>0";
					case DataFilter::NotNull:			return "$fieldName IS NOT NULL";
					case DataFilter::InList:			return "$fieldName IN (" . $value . ")";
					case DataFilter::NotInList:			return "$fieldName NOT IN (" . $value . ")";
					case DataFilter::Between:			return "$fieldName BETWEEN " . $value;
					case DataFilter::Custom:			return str_ireplace ('{field}', $fieldName, (string)$value);
					default:
						Debug::warn ("Unknown condition '$filter->condition");
						return '1=2';
				}
			}
			else {
				switch ($filter->condition) {
					case DataFilter::Equals: 			return "$fieldName=" . $q . $value . $q;
					case DataFilter::NotEqual: 			return "$fieldName<>" . $q . $value . $q;
					case DataFilter::Contains: 			return "$fieldName LIKE " . $q . "%$value%" . $q;
					case DataFilter::NotContain: 		return "$fieldName NOT LIKE " . $q . "%$value%" . $q;
					case DataFilter::StartsWith: 		return "$fieldName LIKE " . $q . $value . '%' . $q;
					case DataFilter::NotStartWith:		return "$fieldName NOT LIKE " . $q . $value . '%' . $q;
					case DataFilter::EndsWith:			return "$fieldName LIKE " . $q . "%$value" . $q;
					case DataFilter::NotEndWith:		return "$fieldName NOT LIKE " . $q . "%$value" . $q;
					case DataFilter::GreaterThan:		return "$fieldName>$value";
					case DataFilter::LessThan:			return "$fieldName<$value";
					case DataFilter::GreaterOrEqual:	return "$fieldName>=$value";
					case DataFilter::LessOrEqual:		return "$fieldName<=$value";
					case DataFilter::IsEmpty:			return "($fieldName='' OR $fieldName IS NULL)";
					case DataFilter::IsNull:			return "$fieldName IS NULL";
					case DataFilter::NotEmpty:			return "length($fieldName)>0";
					case DataFilter::NotNull:			return "$fieldName IS NOT NULL";
					case DataFilter::InList:			return "$fieldName IN ('" . (is_array ($value) ? implode ("', '", $value) : $value) . "')";
					case DataFilter::NotInList:			return "$fieldName NOT IN ('" . (is_array ($value) ? implode ("', '", $value) : $value) . "')";
					case DataFilter::Between:			return "$fieldName BETWEEN $q" . $value[0] . "$q AND $q" . $value[1] . "$q";
					case DataFilter::Custom:			return str_ireplace ('{field}', $fieldName, $value);
					default:
						Debug::warn ("Unknown condition '$filter->condition");
						return '1=2';
				}
			}
		}
		elseif ($filter instanceof DataFilterCollection) {
			$sql = array();

			foreach ($filter->all() as $f) {
				if ($f instanceof DataFilter) {
					$expr = $f->getExpression ();
					if (strlen ($expr) > 0) {
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
			$sql = implode (") $operand (", $sql);
			if (strlen ($sql) > 0)
				$sql = "($sql)";

			return $sql;
		}
		else {
			throw new \InvalidArgumentException();
		}
	}

	/**
	 * Returns a unified sorting expression for the given sorting, compatible to the database's syntax
	 * @param DataSorting|DataSortingCollection $sorting
	 * @return mixed
	 * @throws \InvalidArgumentException
	 */
	public function getSortingExpression ($sorting) {
		if ($sorting instanceof DataSorting) {
			return $sorting->column->columnName(true) . (($sorting->mode == DataSorting::Descending) ? ' DESC' : ' ASC');
		}
		elseif ($sorting instanceof DataSortingCollection) {
			$sql = array();

			foreach ($sorting->all() as $s) {
				$expr = $s->getExpression ();
				if (strlen ($expr) > 0)
					$sql[] = $expr;
			}

			$sql = implode (', ', $sql);
			return $sql;
		}
		else {
			throw new \InvalidArgumentException();
		}
	}

	/**
	 * Returns a unified join expression for the given table relation(s), compatible to the database's syntax
	 * @param DataRelation|DataRelationCollection $relation
	 * @param string $mode Join expression mode. Valid values are DataRelation::Expr* constants
	 * @return mixed
	 * @throws \InvalidArgumentException
	 */
	public function getRelationExpression ($relation, $mode = DataRelation::ExprParentJoined) {
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
			$links = $relation->getLinks ();
			foreach ($links as $pair) {
				/** @var DataColumn */
				$pCol = $pair[0];
				/** @var DataColumn */
				$cCol = $pair[1];
				if ($pCol instanceof DataColumn && $cCol instanceof DataColumn) {
					$pName = ((strlen($pCol->table->alias)>0) ? $pCol->table->alias : $pCol->table->name) . '.' . $pCol->name;
					$cName = ((strlen($cCol->table->alias)>0) ? $cCol->table->alias : $cCol->table->name) . '.' . $cCol->name;
					$criteria[] = "$pName=$cName";
				}
			}

			// Build custom criteria, if any
			if ($relation->criteria instanceof DataFilterCollection || $relation->criteria instanceof DataFilter) {
				$criteria[] = $this->getFilterExpression($relation->criteria);
			}
			elseif (is_string($relation->criteria) && strlen ($relation->criteria) > 0) {
				$criteria[] = $relation->criteria;
			}

			$criteria = implode (' AND ', $criteria);
			#endregion

			if ($mode == DataRelation::ExprBothJoined) {
				$sql[] = $criteria;
			}
			else {
				$sql[] = "$joinType $tblName ON $criteria";
			}

			$sql = implode ("\n", $sql);
			return $sql;
		}
		elseif ($relation instanceof DataRelationCollection) {
			$sql = array();

			$relation->sort();

			foreach ($relation->all() as $r) {
				$sql[] = $r->getExpression();
			}

			$sql = implode ("\n", $sql);
			return $sql;
		}
		else
			throw new \InvalidArgumentException();
	}
	#endregion

	#region Misc. methods
	protected function addLimitParams ($query, $start, $limit) {
		if ($start === null && $limit === null)
			return $query;

		$sql = 'LIMIT ';
		$params = array();
		if ($start !== null)
			$params[] = ':start';
		if ($limit !== null)
			$params[] = ':limit';

		$sql .= implode (',', $params);

		return "$query $sql";
	}

	public function usesTablePrefixInQueries() {
		return true;
	}

	public function getDateNativeFormat ($includeTime = true) {
		return ($includeTime) ? 'Y-m-d H:i:s' : 'Y-m-d';
	}

	public function getTimeNativeFormat () {
		return 'H:i:s';
	}
	#endregion

	protected function _buildConnectionString () {
		return "";
	}

	public function getRecord ($sql) {
		if (!$this->isConnected()) return false;

		$result = $this->fetchAll ($sql, null, 0, 1);

		return ($result) ? $result[0] : false;
	}

	public function getRecordSet ($sql, $start = 0, $limit = null) {
		if (!$this->isConnected()) return false;

		$start = intval ($start);
		$limit = intval ($limit);

		if ($start > 0 && stripos ($sql, 'SELECT ') === 0) {
			$sql = "SELECT TOP $start " . substr ($sql, 8);
		}
		$result = mssql_query ($sql, $this->link);

		if (!$result)
			$this->trigger ('onSqlError', "Server: " . $this->options->host . "\nDatabase: " . $this->options->database . "\nSQL: " . $sql);

		if (!$result || mssql_num_rows ($result) == 0) return false;

		$rows = array ();

		while ($row = mssql_fetch_assoc ($result))
			$rows[] = $row;

		return $rows;
	}

	public function getRecordField ($sql, $fieldname) {
		if (!$this->isConnected()) return false;

		$row = $this->fetch ($sql);

		return ($row && isset ($row[$fieldname])) ? $row[$fieldname] : false;
	}

	public function getInsertID () {
		$sql = "SELECT SCOPE_IDENTITY() AS id";
		return $this->fetchColumn ($sql, 'id');
	}

	public function execute ($statement, $params = null) {
		if (!$this->isConnected()) return false;

		$ret = mssql_query ($statement, $this->link);
		if ($ret === false)
			$this->trigger ('onSqlError', "Server: " . $this->options->host . "\nDatabase: " . $this->options->database . "\nSQL: " . $statement);

		return ($ret !== false);
	}

	public function getError () {
		if (!$this->isConnected()) return false;

		$sql = "SELECT @@ERROR AS error";
		return $this->fetchColumn ($sql, 'error');
//		return mssql_get_last_message ();
	}

	public function getRowsAffected () {
		if (!$this->isConnected ()) return false;

		$sql = "SELECT @@ROWCOUNT AS cnt";
		return $this->fetchColumn ($sql, 'cnt');
	}

	public function getRowsMatched () {
		return $this->getRowsAffected ();
	}

	#region Transaction methods
	public function beginTransaction ($name = null) {
		if (!isset ($name))
			$name = '__transaction_' . count ($this->_savePoints);

		$this->_savePoints[] = $name;

		return $this->exec ("BEGIN TRANSACTION :name", array (':name' => $name));
	}

	public function commit ($name = null) {
		$level = -1;
		$max = count ($this->_savePoints);

		if (!isset ($name)) {
			for ($i = $max - 1; $i >= 0; $i--) {
				if (strpos ($this->_savePoints[$i], '__transaction_') === 0) {
					$name = $this->_savePoints[$i];
					$level = $i;
					break;
				}
			}
		} else {
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

		$name = $this->escape ($name, false);
		$ret = false;

		// Commit all child savepoints
		for ($i = $max - 1; $i >= $level; $i--) {
			$ret = $this->exec ("COMMIT TRANSACTION " . $this->_savePoints[$i]);
			unset ($this->_savePoints[$i]);
		}
		$this->savePoints = array_values ($this->_savePoints);

		return $ret;
	}

	public function rollback ($name = null) {
		$level = -1;
		$max = count ($this->_savePoints);

		if (!isset ($name)) {
			for ($i = $max - 1; $i >= 0; $i--) {
				if (strpos ($this->_savePoints[$i], '__transaction_') === 0) {
					$name = $this->_savePoints[$i];
					$level = $i;
					break;
				}
			}
		} else {
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

		$name = $this->escape ($name, false);
		$ret = false;

		// Commit all child savepoints
		for ($i = $max - 1; $i >= $level; $i--) {
			$ret = $this->exec ("ROLLBACK TRANSACTION " . $this->_savePoints[$i]);
			unset ($this->_savePoints[$i]);
		}

		return $ret;
	}
	#endregion

	public function str_escape ($str, $allow_html = true) {
		if (!isset($str) || empty($str)) return '';
		if (is_numeric($str)) return $str;

		$specialChars = array (
			'/%0[0-8bcef]/',            // url encoded 00-08, 11, 12, 14, 15
			'/%1[0-9a-f]/',             // url encoded 16-31
			'/[\x00-\x08]/',            // 00-08
			'/\x0b/',                   // 11
			'/\x0c/',                   // 12
			'/[\x0e-\x1f]/'             // 14-31
		);
		foreach ($specialChars as $sc)
			$str = preg_replace ($sc, '', $str);
		$str = str_replace ("'", "''", $str);

		if ($allow_html)
			return htmlspecialchars ($str);
		else
			return $str;
	}
	#endregion
	public function parseCfg(\stdClass $cfg) {
		// TODO: Implement parseCfg() method.
	}

	public function getColumnExpression(DataColumn $column, $prefixTableAlias = false, $suffixColumnAlias = false) {
		// TODO: Implement getColumnExpression() method.
	}

	public function getValueExpression(DataColumn $column, $value = null) {
		// TODO: Implement getValueExpression() method.
	}
}
