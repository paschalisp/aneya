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

use aneya\Core\CoreObject;
use aneya\Structures\Node;

/**
 * Class DataSetConnectionSet
 *
 * Internal class used by DataSet
 *
 * @package aneya\Core\Data
 */
class DataSetConnectionSet extends CoreObject {
	/** @var Database */
	public $db;
	/** @var DataSet */
	public $dataSet;
	/** @var DataTableCollection */
	public $tables;
	/** @var DataColumnCollection */
	public $columns;
	/** @var DataColumnCollection */
	public $listColumns;
	/** @var DataRelationCollection */
	public $relations;
	/** @var DataFilterCollection */
	public $filters;
	/** @var DataSortingCollection */
	public $sorting;
	/** @var DataRowCollection */
	public $rows;
	/** @var array Associative array that stores retrieved values per column (key is column's tag, value is array of the values found in all retrieved rows for that column) */
	public $values;
	/** @var int Keeps the number of rows retrieved */
	public $count = 0;
	/** @var Node */
	public $node;
	/** @var mixed The database query used to retrieve rows for the connection */
	public $query;
}
