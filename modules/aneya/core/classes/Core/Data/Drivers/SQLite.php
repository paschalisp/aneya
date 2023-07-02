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


use aneya\Core\Data\ConnectionOptions;
use aneya\Core\Data\Database;
use aneya\Core\Data\DataColumn;
use aneya\Core\Data\DataExpression;
use aneya\Core\Data\DataFilter;
use aneya\Core\Data\DataFilterCollection;
use aneya\Debug\Debug;
use aneya\UI\Calendar\DateTimeRange;

class SQLite extends MySQL {
	#region Properties
	/** @var SQLiteSchema */
	public \aneya\Core\Data\Schema\Schema $schema;
	#endregion

	#region Constructor
	public function __construct() {
		parent::__construct();

		$this->_type = Database::SQLite;
		$this->schema = new SQLiteSchema();
		$this->schema->setDatabaseInstance($this);

		$this->options = new ConnectionOptions();
	}
	#endregion

	#region Methods
	#region Connection methods
	public function connect(ConnectionOptions $options): bool {
		if (isset ($this->_link))
			$this->disconnect();

		// Apply argument connection options to instance's connection options
		$this->options->applyCfg($options->toJson());

		$this->_pdo = $this->_link = new \PDO ($this->getConnectionString(), $this->options->username, $this->options->password, $this->options->pdoOptions);

		return true;
	}

	protected function getConnectionString(): string {
		return ($this->options->connString) ?: sprintf('sqlite:%s', $this->options->database);
	}
	#endregion

	#region Expression Methods
	/**
	 * Returns a unified filtering expression for all filters in the given array, compatible to the database's syntax
	 *
	 * @param DataFilter|DataFilterCollection $filter
	 *
	 * @return mixed
	 * @throws \InvalidArgumentException
	 */
	public function getFilterExpression($filter) {
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
					$value = $value->format('U');
				}
				elseif (is_array($value)) {
					$max = count($value);
					for ($num = 0; $num < $max; $num++) {
						if ($value[$num] instanceof \DateTime) {
							$q = '';
							// Ensure date is in environment's timezone
							$value[$num] = $value[$num]->format('U');
						}
					}
				}
				elseif ($value instanceof DateTimeRange) {
					$q = '';
					$date = [];
					$date[] = $value->startDate->format('U');
					$date[] = $value->endDate->format('U');
					$value = $date;
				}
				elseif (is_bool($value)) {
					$value = (bool)$value ? 1 : 0;
				}
			}
			if ($filter->column->isExpression) {
				$fieldName = $filter->column->name;
			}
			else {
				$fieldName = $filter->column->columnName(true);
			}
			if ($value instanceof DataExpression) {
				switch ($filter->condition) {
					case DataFilter::Equals:
						return "$fieldName=" . $value;
					case DataFilter::NotEqual:
						return "$fieldName<>" . $value;
					case DataFilter::Contains:
						return "$fieldName LIKE " . $value;
					case DataFilter::NotContain:
						return "$fieldName NOT LIKE " . $value;
					case DataFilter::StartsWith:
						return "$fieldName LIKE " . $value;
					case DataFilter::NotStartWith:
						return "$fieldName NOT LIKE " . $value;
					case DataFilter::EndsWith:
						return "$fieldName LIKE " . $value;
					case DataFilter::NotEndWith:
						return "$fieldName NOT LIKE " . $value;
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
					case DataFilter::Between:
						return "$fieldName BETWEEN " . $value;
					case DataFilter::Custom:
						return str_ireplace('{field}', $fieldName, (string)$value);
					default:
						Debug::warn("Condition '$filter->condition' not supported' by SQLite database driver");
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
					case DataFilter::Between:
						return "$fieldName BETWEEN $q" . $value[0] . "$q AND $q" . $value[1] . "$q";
					case DataFilter::Custom:
						return str_ireplace('{field}', $fieldName, $value);
					default:
						Debug::warn("Condition '$filter->condition' not supported' by SQLite database driver");
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
		else {
			throw new \InvalidArgumentException();
		}
	}
	#endregion

	#region Misc. methods
	public function usesTablePrefixInQueries() {
		return true;
	}

	public function getDateNativeFormat($includeTime = true) {
		return 'U';
	}

	public function getTimeNativeFormat() {
		return 'U';
	}
	#endregion
	#endregion
}
