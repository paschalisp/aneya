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
use aneya\Core\EventStatus;
use aneya\Core\Utils\BitOps;
use aneya\Core\Utils\JsonUtils;
use Monolog\Logger;

abstract class RDBMS extends Database {
	#region Properties
	protected ?\PDO $_pdo;

	protected $_rowsAffected;
	#endregion

	#region Constructor
	public function __construct() {
		parent::__construct();

		$this->_pdo = $this->_link = null;
		$this->lastError = new EventStatus();

		// Store all date/times in UTC by default
		$this->timezone = new \DateTimeZone('UTC');
	}
	#endregion

	#region Methods
	#region DataSet methods
	public function save(DataRow $row): EventStatus {
		if ($row->parent->db()->schema->readonly === true) {
			$e = new \Exception('RDBMS::save() Cannot save or delete data in a readonly schema');
			$this->logger()->error($e->getMessage() . ' Trace: ' . $e->getTraceAsString());
			return new EventStatus(false, 'Cannot save or delete data in a readonly schema [' . $row->parent->db()->schema->schemaName() . ']');
		}

		$state = $row->getState();
		if ($state == DataRow::StateAdded) {
			$columns = $row->parent->columns->filter(function (DataColumn $c) { return $c->isActive; })->all();
			$cols = $bindCols = $values = $sql = [];

			#region Build the SQL statement
			$sql[] = "INSERT INTO " . ($row->parent->db()->getSchemaName() . '.' . $row->parent->name);
			foreach ($columns as $c) {
				if ($c->isFake) continue;                // Don't save fake fields
				if (!$c->isSaveable) continue;           // Don't save non-saveable fields
				if ($c->isMultilingual) continue;        // Multilingual fields are saved elsewhere
				if ($c->isExpression) continue;          // Don't save expressions
				if ($c->isAutoIncrement && $row->getValue($c) == null) continue;	// Don't add auto-increments in the query if null

				$cols[] = $c->columnName(false, false);
			}
			$sql[] = '(' . $this->_quote . implode($this->_quote . ', ' . $this->_quote, $cols) . $this->_quote . ')';
			$sql[] = 'VALUES';
			foreach ($columns as $c) {
				if ($c->isFake) continue;                // Don't save fake fields
				if (!$c->isSaveable) continue;           // Don't save non-saveable fields
				if ($c->isMultilingual) continue;        // Multilingual fields are saved elsewhere
				if ($c->isExpression) continue;          // Don't save expressions
				if ($c->isAutoIncrement && $row->getValue($c) == null) continue;	// Don't add auto-increments in the query if null

				$value = $row->getValue($c);
				$colName = $c->columnName(false, false);

				if (($c->allowNull || $c->isAutoIncrement) && (is_scalar($value) || empty($value)) && strlen((string)$value) == 0) {
					$null = null;
					$bindCols[] = ":$colName";
					$values[$colName] = [$null, \PDO::PARAM_NULL];
				}
				else {
					switch ($c->dataType) {
						case DataColumn::DataTypeInteger:
						case DataColumn::DataTypeFloat:
							$type = \PDO::PARAM_INT;
							break;
						case DataColumn::DataTypeBoolean:
							$value = ((bool)$value) ? 1 : 0;
							$type = \PDO::PARAM_BOOL;
							break;
						case DataColumn::DataTypeChar:
						case DataColumn::DataTypeString:
							$type = \PDO::PARAM_STR;
							if ($c->allowHTML)
								$value = htmlspecialchars_decode($value);
							if ($value !== null && $c->allowTrim)
								$value = trim($value);
							break;
						case DataColumn::DataTypeDate:
						case DataColumn::DataTypeDateTime:
							if ($value instanceof \DateTime) {
								#region Set correct timezone, if necessary
								if ($value->getTimezone()->getName() != $this->timezone->getName()) {
									// Clone the date/time as we don't want to affect the original value
									$value = clone $value;
									// Always store date/times in UTC
									$value->setTimezone($this->timezone);
								}
								#endregion

								$value = $value->format($this->getDateNativeFormat($c->dataType == DataColumn::DataTypeDateTime));
							}
							$type = \PDO::PARAM_STR;
							break;
						case DataColumn::DataTypeTime:
							if ($value instanceof \DateTime) {
								#region Set correct timezone, if necessary
								/*	if ($value->getTimezone()->getName() != $this->timezone->getName()) {
										// Clone the date/time as we don't want to affect the original value
										$value = clone $value;
										// Always store date/times in UTC
										$value->setTimezone ($this->timezone);
									}*/
								#endregion

								$value = $value->format($this->getTimeNativeFormat());
							}
							$type = \PDO::PARAM_STR;
							break;
						case DataColumn::DataTypeArray:
							if ($value instanceof Collection)
								$value = $value->all();
							elseif (is_scalar($value))
								$value = [$value];

							switch ($this->getDriverType()) {
								case Database::PostgreSQL:
									$value = sprintf('{%s}', implode(',', $value));
									break;
								default:
									$value = implode(',', $value);
							}

							$type = \PDO::PARAM_STR;
							break;
						case DataColumn::DataTypeJson:
						case DataColumn::DataTypeObject:
							$type = \PDO::PARAM_STR;
							$value = JsonUtils::encode($value);
							break;
						case DataColumn::DataTypeBlob:
							$type = \PDO::PARAM_LOB;
							break;
						case DataColumn::DataTypeGeoPoint:
						case DataColumn::DataTypeGeoPolygon:
						case DataColumn::DataTypeGeometry:
						case DataColumn::DataTypeGeoMultiPoint:
						case DataColumn::DataTypeGeoMultiPolygon:
						case DataColumn::DataTypeGeoCollection:
							$value = $this->getValueExpression($c, $value);
							if ($value === null)
								$type = \PDO::PARAM_NULL;
							else
								$type = 'expression';
							break;
						default:
							$type = \PDO::PARAM_STR;
							if ($c->allowHTML)
								$value = htmlspecialchars_decode($value);
							if ($value !== null && $c->allowTrim)
								$value = trim($value);
					}

					if ($type === 'expression') {
						$bindCols[] = $value;
					}
					else {
						$bindCols[] = ":$colName";
						$values[$colName] = [$value, $type];
					}
				}
			}
			$sql[] = '(' . implode(', ', $bindCols) . ')';
			$sql = implode("\n", $sql);
			#endregion

			#region Prepare & execute the statement
			$stmt = $this->prepare($sql);
			if ($stmt == false) {
				$e = new \Exception(sprintf("RDBMS::save() Failed preparing statement.\nStatement: %s \nError Code: %s \nMessage: %s", $sql, $this->lastError->code, $this->lastError->debugMessage));
				$this->logger()->error($e->getMessage() . ' Trace: ' . $e->getTraceAsString());
				return new EventStatus (false, CMS::translator()->translate('record_save_error', 'cms'), $this->lastError->code, $this->lastError->debugMessage);
			}

			foreach ($values as $col => $value) {
				$stmt->bindValue(":$col", $value[0], $value[1]);
				$this->getLastError(true);    // Catch any database error
			}
			$ret = $this->execute($stmt);
			if (!$ret) {
				$e = new \Exception(sprintf("RDBMS::save() Failed executing statement.\nStatement: %s \nError Code: %s \nMessage: %s", $sql, $this->lastError->code, $this->lastError->debugMessage));
				$this->logger()->error($e->getMessage() . ' Trace: ' . $e->getTraceAsString());
				return new EventStatus (false, CMS::translator()->translate('record_save_error', 'cms'), $this->lastError->code, $this->lastError->debugMessage);
			}
			#endregion

			#region Check for auto-increment columns and update the row accordingly
			$columns = $row->parent->columns->filter(function (DataColumn $c) { return $c->isActive && $c->isAutoIncrement && $c->isSaveable && !$c->isFake; })->all();
			foreach ($columns as $col) {
				$row->setValue($col, $this->getInsertID($col));
			}
			#endregion
		}
		elseif ($state == DataRow::StateModified) {
			$columns = $row->parent->columns->filter(function (DataColumn $c) { return $c->isActive; })->all();
			$sql = $cSql = $cols = $values = [];

			#region Build the SQL statement
			$sql[] = 'UPDATE ' . ($row->parent->db()->getSchemaName() . '.' . $row->parent->name) . ' SET';
			foreach ($columns as $c) {
				if ($c->isFake) continue;                // Don't save fake fields
				if (!$c->isSaveable) continue;            // Don't save display-only fields
				if ($c->isMultilingual) continue;        // Multilingual fields are saved elsewhere
				if ($c->isExpression) continue;            // Don't save expressions

				$cols[] = $colName = $c->columnName(false, false);

				$value = $row->getValue($c);
				$type = \PDO::PARAM_STR;

				if (($c->allowNull || $c->isAutoIncrement) && !is_object($value) && !is_array($value) && !is_bool($value) && strlen(trim($value)) == 0) {
					$value = null;
					$values[$colName] = [$value, \PDO::PARAM_NULL];
				}
				else {
					switch ($c->dataType) {
						case DataColumn::DataTypeInteger:
						case DataColumn::DataTypeFloat:
							$type = \PDO::PARAM_INT;
							break;
						case DataColumn::DataTypeBoolean:
							$type = \PDO::PARAM_BOOL;
							break;
						case DataColumn::DataTypeChar:
						case DataColumn::DataTypeString:
							$type = \PDO::PARAM_STR;
							if ($c->allowHTML)
								$value = htmlspecialchars_decode($value);
							if ($value !== null && $c->allowTrim)
								$value = trim($value);
						break;
						case DataColumn::DataTypeDate:
						case DataColumn::DataTypeDateTime:
							if ($value instanceof \DateTime) {
								#region Set correct timezone, if necessary
								if ($value->getTimezone()->getName() != $this->timezone->getName()) {
									// Clone the date/time as we don't want to affect the original value
									$value = clone $value;
									// Always store date/times in UTC
									$value->setTimezone($this->timezone);
								}
								#endregion

								$value = $value->format($this->getDateNativeFormat($c->dataType == DataColumn::DataTypeDateTime));
							}
							$type = \PDO::PARAM_STR;
							break;
						case DataColumn::DataTypeTime:
							if ($value instanceof \DateTime) {
								#region Set correct timezone, if necessary
								/*								if ($value->getTimezone()->getName() != $this->timezone->getName()) {
																	// Clone the date/time as we don't want to affect the original value
																	$value = clone $value;
																	// Always store date/times in UTC
																	$value->setTimezone ($this->timezone);
																}*/
								#endregion

								$value = $value->format($this->getTimeNativeFormat());
							}
							$type = \PDO::PARAM_STR;
							break;
						case DataColumn::DataTypeBlob:
							$type = \PDO::PARAM_LOB;
							break;
						case DataColumn::DataTypeJson:
						case DataColumn::DataTypeObject:
							$type = \PDO::PARAM_STR;
							$value = JsonUtils::encode($value);
							break;
						case DataColumn::DataTypeArray:
							$type = \PDO::PARAM_STR;
							if (is_array($value)) {
								switch ($c->subDataType) {
									case DataColumn::DataTypeInteger:
									case DataColumn::DataTypeFloat:
									case DataColumn::DataTypeBoolean:
										$subQuotes = '';
										break;
									default:
										$subQuotes = '"';
								}
								switch ($this->getDriverType()) {
									case Database::PostgreSQL:
										$value = '{' . $subQuotes . implode("$subQuotes, $subQuotes", $value) . $subQuotes . '}';
										break;
									default:
										$value = implode(",", $value);
										break;
								}
							}
							break;
						case DataColumn::DataTypeGeoPoint:
						case DataColumn::DataTypeGeoPolygon:
						case DataColumn::DataTypeGeometry:
						case DataColumn::DataTypeGeoMultiPoint:
						case DataColumn::DataTypeGeoMultiPolygon:
						case DataColumn::DataTypeGeoCollection:
							$value = $this->getValueExpression($c, $value);
							if ($value === null)
								$type = \PDO::PARAM_NULL;
							else
								$type = 'expression';
							break;
						default:
							$type = \PDO::PARAM_STR;
							if ($c->allowHTML)
								$value = htmlspecialchars_decode($value);
							if ($value !== null && $c->allowTrim)
								$value = trim($value);
					}

					if ($type !== 'expression')
						$values[$colName] = [$value, $type];
				}

				if ($type === 'expression')
					$cSql[] = "$colName=$value";
				else
					$cSql[] = "$colName=:$colName";
			}
			$sql[] = implode(', ', $cSql);

			#region Build the criteria
			$columns = $row->parent->columns->filter(function (DataColumn $c) { return $c->isActive && $c->isKey; })->all();
			$cols = $where = $kValues = [];

			#region Build the SQL statement
			$sql[] = 'WHERE';
			foreach ($columns as $c) {
				$cols[] = $colName = $c->columnName(false, false);
				$where[] = "$colName=:KEY___$colName";

				$value = $row->getOriginalValue($c);

				switch ($c->dataType) {
					case DataColumn::DataTypeInteger:
					case DataColumn::DataTypeFloat:
						$type = \PDO::PARAM_INT;
						break;
					case DataColumn::DataTypeBoolean:
						$type = \PDO::PARAM_BOOL;
						break;
					case DataColumn::DataTypeChar:
					case DataColumn::DataTypeString:
						$type = \PDO::PARAM_STR;
						if ($c->allowHTML)
							$value = htmlspecialchars_decode($value);
						if ($value !== null && $c->allowTrim)
							$value = trim($value);
						break;
					case DataColumn::DataTypeDate:
					case DataColumn::DataTypeTime:
					case DataColumn::DataTypeDateTime:
						$type = \PDO::PARAM_STR;
						break;
					case DataColumn::DataTypeBlob:
						$type = \PDO::PARAM_LOB;
						break;
					default:
						$type = \PDO::PARAM_STR;
						if ($c->allowHTML)
							$value = htmlspecialchars_decode($value);
						if ($value !== null && $c->allowTrim)
							$value = trim($value);
				}
				$kValues[$colName] = [$value, $type];
			}
			$sql[] = implode(' AND ', $where);
			#endregion
			#endregion

			$sql = implode("\n", $sql);
			#endregion

			#region Prepare the statement & execute it
			$stmt = $this->prepare($sql);
			if ($stmt === false) {
				$e = new \Exception(sprintf("RDBMS::save() Failed preparing statement.\nStatement: %s \nError Code: %s \nMessage: %s", $sql, $this->lastError->code, $this->lastError->debugMessage));
				$this->logger()->error($e->getMessage() . ' Trace: ' . $e->getTraceAsString());
				return new EventStatus (false, CMS::translator()->translate('record_save_error', 'cms'), $this->lastError->code, $this->lastError->debugMessage);
			}

			foreach ($values as $col => $value) {
				$stmt->bindValue(":$col", $value[0], $value[1]);
			}
			foreach ($kValues as $col => $value) {
				$stmt->bindValue(":KEY___$col", $value[0], $value[1]);
			}
			$ret = $this->execute($stmt);
			if (!$ret) {
				$e = new \Exception(sprintf("RDBMS::save() Failed executing statement.\nStatement: %s \nError Code: %s \nMessage: %s", $sql, $this->lastError->code, $this->lastError->debugMessage));
				$this->logger()->error($e->getMessage() . ' Trace: ' . $e->getTraceAsString());
				return new EventStatus (false, CMS::translator()->translate('record_save_error', 'cms'), $this->lastError->code, $this->lastError->debugMessage);
			}
			#endregion
		}
		elseif ($state == DataRow::StateDeleted) {
			$columns = $row->parent->columns->filter(function (DataColumn $c) { return $c->isActive && $c->isKey; })->all();
			$sql = $cols = $where = $values = array ();

			#region Build the SQL statement
			$sql[] = 'DELETE FROM ' . ($row->parent->db()->getSchemaName() . '.' . $row->parent->name) . ' WHERE';
			foreach ($columns as $c) {
				$cols[] = $colName = $c->columnName(false, false);
				$where[] = "$colName=:$colName";

				$value = $row->getValue($c);

				switch ($c->dataType) {
					case DataColumn::DataTypeInteger:
					case DataColumn::DataTypeFloat:
						$type = \PDO::PARAM_INT;
						break;
					case DataColumn::DataTypeBoolean:
						$type = \PDO::PARAM_BOOL;
						break;
					case DataColumn::DataTypeChar:
					case DataColumn::DataTypeString:
						$type = \PDO::PARAM_STR;
						if ($c->allowHTML)
							$value = htmlspecialchars_decode($value);
						if ($value !== null && $c->allowTrim)
							$value = trim($value);
						break;
					case DataColumn::DataTypeDate:
					case DataColumn::DataTypeTime:
					case DataColumn::DataTypeDateTime:
						$type = \PDO::PARAM_STR;
						break;
					case DataColumn::DataTypeBlob:
						$type = \PDO::PARAM_LOB;
						break;
					default:
						$type = \PDO::PARAM_STR;
						if ($c->allowHTML)
							$value = htmlspecialchars_decode($value);
						if ($value !== null && $c->allowTrim)
							$value = trim($value);
				}
				$values[$colName] = [$value, $type];
			}
			$sql[] = implode(' AND ', $where);
			$sql = implode("\n", $sql);
			#endregion

			#region Prepare & execute the statement
			$stmt = $this->prepare($sql);
			foreach ($values as $col => $value) {
				$stmt->bindValue(":$col", $value[0], $value[1]);
			}
			$ret = $this->execute($stmt);
			if (!$ret) {
				$e = new \Exception(sprintf("RDBMS::save() Failed executing statement.\nStatement: %s \nError Code: %s \nMessage: %s", $sql, $this->lastError->code, $this->lastError->debugMessage));
				$this->logger()->error($e->getMessage() . ' Trace: ' . $e->getTraceAsString());
				return new EventStatus (false, CMS::translator()->translate('record_save_error', 'cms'), $this->lastError->code, $this->lastError->debugMessage);
			}
			#endregion
		}

		#region For inserts & updates, save any multilingual columns
		if ($state == DataRow::StateAdded || $state == DataRow::StateModified) {

			$trColumns = $row->parent->columns->filter(function (DataColumn $c) { return $c->isActive && $c->isMultilingual; })->all();
			if (count($trColumns) > 0) {
				/** @var DataColumn[] $keyColumns */
				$keyColumns = $row->parent->columns->filter(function (DataColumn $c) { return $c->isActive && $c->isKey; })->all();
				$langColumn = new DataColumn('language_code');
				$langColumn->table = $row->parent;
				$keyColumns[] = $langColumn;
				$sql = $cols = $bindCols = $values = [];

				/** @var DataColumn[] $allColumns */
				$allColumns = array_merge($keyColumns, $trColumns);

				#region Build the SQL statement
				$sql[] = sprintf('INSERT INTO %s.%sTr', $row->parent->db()->getSchemaName() , $row->parent->name);
				foreach ($allColumns as $c) {
					if ($c->isFake) continue;
					if (!$c->isSaveable) continue;			// Don't save display-only fields
					if ($c->isExpression) continue;			// Don't save expressions

					$cols[] = $c->columnName(false, false);
				}
				$sql[] = '(' . implode(', ', $cols) . ')';
				$sql[] = 'VALUES';
				$sql[] = '(:' . implode(', :', $cols) . ')';
				switch ($this->getDriverType()) {
					case static::PostgreSQL:
						$sql[] = sprintf('ON CONFLICT(%s) DO UPDATE SET %s',
							implode(', ', array_map(function (DataColumn $c) { return $c->columnName(false, false); }, $keyColumns)),
							implode(', ', array_map(function (DataColumn $c) { return sprintf('%s=excluded.%s', $colName = $c->columnName(false, false), $colName);}, $trColumns))
						);
						break;
					case static::MySQL:
						$sql[] = sprintf('ON DUPLICATE KEY UPDATE %s',
							implode(', ', array_map(function (DataColumn $c) { return sprintf('%s=VALUES(%s)', $colName = $c->columnName(false, false), $colName);}, $trColumns))
						);
						break;
				}

				foreach ($keyColumns as $c) {
					if ($c === $langColumn || $c->isMultilingual)
						continue;

					$value = $row->getValue($c);
					$colName = $c->columnName(false, false);

					if (($c->allowNull || $c->isAutoIncrement) && strlen($value) == 0) {
						$value = null;
						$bindCols[] = ":$colName";
						$type = \PDO::PARAM_NULL;
					}
					else {
						switch ($c->dataType) {
							case DataColumn::DataTypeInteger:
							case DataColumn::DataTypeFloat:
								$type = \PDO::PARAM_INT;
								break;
							case DataColumn::DataTypeBoolean:
								$type = \PDO::PARAM_BOOL;
								break;
							case DataColumn::DataTypeChar:
							case DataColumn::DataTypeString:
								$type = \PDO::PARAM_STR;
								if ($c->allowHTML)
									$value = htmlspecialchars_decode($value);
								if ($value !== null && $c->allowTrim)
									$value = trim($value);
								break;
							case DataColumn::DataTypeDate:
							case DataColumn::DataTypeTime:
							case DataColumn::DataTypeDateTime:
								$type = \PDO::PARAM_STR;
								break;
							case DataColumn::DataTypeJson:
							case DataColumn::DataTypeObject:
								$type = \PDO::PARAM_LOB;
								$value = JsonUtils::encode($value);
								break;
							case DataColumn::DataTypeBlob:
								$type = \PDO::PARAM_LOB;
								break;
							case DataColumn::DataTypeGeoPoint:
							case DataColumn::DataTypeGeoPolygon:
							case DataColumn::DataTypeGeometry:
							case DataColumn::DataTypeGeoMultiPoint:
							case DataColumn::DataTypeGeoMultiPolygon:
							case DataColumn::DataTypeGeoCollection:
								$value = $this->getValueExpression($c, $value);
								if ($value === null)
									$type = \PDO::PARAM_NULL;
								else
									$type = 'expression';
								break;
							default:
								$type = \PDO::PARAM_STR;
								if ($c->allowHTML)
									$value = htmlspecialchars_decode($value);
								if ($value !== null && $c->allowTrim)
									$value = trim($value);
						}
					}

					if ($type === 'expression') {
						$bindCols[] = $value;
					} else {
						$bindCols[] = ":$colName";
						$values[$colName] = [$value, $type];
					}
				}
				#endregion

				#region Prepare the statement
				$sql = implode("\n", $sql);
				$stmt = $this->prepare($sql);
				// If statement can't get prepared, just warn and return. No multilingual values can be saved.
				if ($stmt === false) {
					$e = new \Exception(sprintf("RDBMS::save() Could not prepare statement. SQL: %s. Error Code: %s. Message: %s", $sql, $this->lastError->code, $this->lastError->debugMessage));
					$this->logger()->error($e->getMessage() . ' Trace: ' . $e->getTraceAsString());
					return new EventStatus();
				}

				foreach ($values as $colName => $value) {
					$stmt->bindValue(":$colName", $value[0], $value[1]);
				}
				#endregion

				#region Prepare the appropriate list of translation language codes
				$allLangCodes = array_keys(CMS::translator()->languages());
				$usedLangCodes = [];

				foreach ($trColumns as $c) {
					if ($c->isFake) continue;
					if (!$c->isSaveable) continue;				// Don't save display-only fields
					if ($c->isExpression) continue;				// Don't save expressions

					$usedLangCodes = array_merge($usedLangCodes, array_keys($row->getValueTr($c)));
				}
				// Remove duplicated language codes
				$usedLangCodes = array_unique($usedLangCodes);
				// We only need used language codes that are also enabled in the environment
				$usedLangCodes = array_intersect($usedLangCodes, $allLangCodes);
				#endregion

				// Save the columns values for each language
				foreach ($usedLangCodes as $langCode) {
					#region Bind params & execute the statement
					$stmt->bindValue(":language_code", $langCode, \PDO::PARAM_STR);

					// Indicates if language code is used in any of the multilingual columns;
					// if not query shall not be executed
					$langUsed = false;

					foreach ($trColumns as $c) {
						if ($c->isFake) continue;
						if (!$c->isSaveable) continue;			// Don't save display-only fields
						if ($c->isExpression) continue;			// Don't save expressions

						$colName = $c->columnName(false, false);
						$value = $row->getValue($c, $langCode);

						if (($c->allowNull || $c->isAutoIncrement) && strlen($value) == 0) {
							$value = null;
							$type = \PDO::PARAM_NULL;
						}
						else {
							switch ($c->dataType) {
								case DataColumn::DataTypeInteger:
								case DataColumn::DataTypeFloat:
									$type = \PDO::PARAM_INT;
									break;
								case DataColumn::DataTypeBoolean:
									$type = \PDO::PARAM_BOOL;
									break;
								case DataColumn::DataTypeChar:
								case DataColumn::DataTypeString:
									$type = \PDO::PARAM_STR;
									if ($c->allowHTML)
										$value = htmlspecialchars_decode($value);
									if ($value !== null && $c->allowTrim)
										$value = trim($value);
									break;
								case DataColumn::DataTypeDate:
								case DataColumn::DataTypeDateTime:
									$type = \PDO::PARAM_STR;
									break;
								case DataColumn::DataTypeBlob:
									$type = \PDO::PARAM_LOB;
									break;
								default:
									$type = \PDO::PARAM_STR;
									if ($c->allowHTML)
										$value = htmlspecialchars_decode($value);
									if ($value !== null && $c->allowTrim)
										$value = trim($value);
							}
						}
						$stmt->bindValue(":$colName", $value, $type);

						if ($value != null)
							$langUsed = true;
					}

					if (!$langUsed)
						continue;

					$ret = $this->execute($stmt);
					#endregion
				}
			}
		}
		#endregion

		return new EventStatus ();
	}

	/**
	 * @inheritdoc
	 *
	 * @param DataTable                                    $parent
	 * @param DataFilter|DataFilter[]|DataFilterCollection $filters
	 *
	 * @return EventStatus
	 *
	 * @throws \InvalidArgumentException
	 */
	public function delete(DataTable $parent, DataFilterCollection|DataFilter|array $filters): EventStatus {
		if ($parent->db()->schema->readonly === true) {
			$e = new \Exception('RDBMS::delete() Cannot save or delete data in a readonly schema');
			$this->logger()->error($e->getMessage() . ' Trace: ' . $e->getTraceAsString());
			return new EventStatus(false, 'Cannot delete data in a readonly schema [' . $parent->db()->schema->schemaName() . ']');
		}

		if ($filters instanceof DataFilter) {
			$f = $filters;
			$filters = new DataFilterCollection();
			$filters->add($f);
		}
		$sql = $cols = $where = $values = array ();

		#region Build the SQL statement
		$sql[] = 'DELETE FROM ' . $parent->db()->schema->schemaName() . '.' . $parent->name . ' WHERE';
		foreach ($filters->all() as $f) {
			if (!($f instanceof DataFilter))
				throw new \InvalidArgumentException();

			$cols[] = $colName = $f->column->columnName(false, false);
			$where[] = "$colName=:$colName";

			$value = $f->value;

			switch ($f->column->dataType) {
				case DataColumn::DataTypeInteger:
				case DataColumn::DataTypeFloat:
					$type = \PDO::PARAM_INT;
					break;
				case DataColumn::DataTypeBoolean:
					$type = \PDO::PARAM_BOOL;
					break;
				case DataColumn::DataTypeChar:
				case DataColumn::DataTypeString:
					$type = \PDO::PARAM_STR;
					if ($f->column->allowHTML)
						$value = htmlspecialchars_decode($value);
					if ($value !== null && $f->column->allowTrim)
						$value = trim ($value);
					break;
				case DataColumn::DataTypeDate:
				case DataColumn::DataTypeTime:
				case DataColumn::DataTypeDateTime:
					$type = \PDO::PARAM_STR;
					break;
				case DataColumn::DataTypeBlob:
					$type = \PDO::PARAM_LOB;
					break;
				default:
					$type = \PDO::PARAM_STR;
					if ($f->column->allowHTML)
						$value = htmlspecialchars_decode($value);
					if ($value !== null && $f->column->allowTrim)
						$value = trim ($value);
			}
			$values[$colName] = [$value, $type];
		}
		$sql[] = implode(' AND ', $where);
		$sql = implode("\n", $sql);
		#endregion

		#region Prepare & execute the statement
		$stmt = $this->prepare($sql);
		if ($stmt === false) {
			$e = new \Exception(sprintf("RDBMS::delete() Failed preparing statement.\nStatement: %s \nError Code: %s \nMessage: %s", $sql, $this->lastError->code, $this->lastError->debugMessage));
			$this->logger()->error($e->getMessage() . ' Trace: ' . $e->getTraceAsString());
			return new EventStatus (false, '', $this->lastError->code, $this->lastError->debugMessage);
		}

		foreach ($values as $col => $value) {
			$stmt->bindValue(":$col", $value[0], $value[1]);
		}
		$ret = $this->execute($stmt);
		if (!$ret) {
			$e = new \Exception(sprintf("RDBMS::delete() Failed executing statement.\nStatement: %s \nError Code: %s \nMessage: %s", $sql, $this->lastError->code, $this->lastError->debugMessage));
			$this->logger()->error($e->getMessage() . ' Trace: ' . $e->getTraceAsString());
			return new EventStatus (false, '', $this->lastError->code, $this->lastError->debugMessage);
		}
		#endregion

		return new EventStatus();
	}
	#endregion

	#region Prepare, execution & fetching methods
	/***
	 * @param object|string $statement The prepared statement or query to fetch results from
	 * @param array|null $params    If statement is string, $params will be passed when executing the statement
	 * @param int|null $start     If statement is string, $start will be used to fetch results starting from this value
	 * @param int|null $limit     If statement is string, $limit will be used to limit the results of the query
	 * @param int|null $fetchMode A fetch mode, one of the PDO::FETCH_* constants
	 * @param mixed|null $argument  Parameters to be passed when preparing the statement, in case the statement provided was a string
	 *
	 * @return array|bool
	 */
	public function fetchAll(object|string $statement, array $params = null, int $start = null, int $limit = null, int $fetchMode = null, mixed $argument = null): bool|array {
		if ($fetchMode == null)
			$fetchMode = $this->_fetchMode;

		$this->fixParams($params, $statement);

		if ($statement instanceof \PDOStatement) {
			if (BitOps::hasBit($fetchMode, \PDO::FETCH_COLUMN) || BitOps::hasBit($fetchMode, \PDO::FETCH_CLASS) || BitOps::hasBit($fetchMode, \PDO::FETCH_FUNC))
				$ret = $statement->fetchAll($fetchMode, $argument);
			else
				$ret = $statement->fetchAll($fetchMode);

			return $ret;
		}

		if ($start !== null || $limit !== null)
			$statement = $this->addLimitParams($statement, $start, $limit);

		$fromTime = microtime(true);
		try {
			$sth = $this->_pdo->prepare($statement);
			$ret = $sth->execute($params);
			if (!$ret) {
				$this->trace($statement, $fromTime);
				$this->getLastError(true);
				$this->trigger(self::EventOnError, new DatabaseExecutionErrorArgs($this, $this->options->host, $this->options->database, $this->options->schema, get_class($this), $statement));
				self::triggerSt(self::EventOnError, new DatabaseExecutionErrorArgs($this, $this->options->host, $this->options->database, $this->options->schema, get_class($this), $statement));

				$e = new \Exception(sprintf("RDBMS::fetchAll() Failed executing statement.\nError Code: %s \nMessage: %s", $this->lastError->code, $this->lastError->debugMessage));
				$this->logger()->error($e->getMessage() . ' Trace: ' . $e->getTraceAsString());

				return false;
			}

			if (BitOps::hasBit($fetchMode, \PDO::FETCH_COLUMN) || BitOps::hasBit($fetchMode, \PDO::FETCH_CLASS) || BitOps::hasBit($fetchMode, \PDO::FETCH_FUNC))
				$ret = $sth->fetchAll($fetchMode, $argument);
			else
				$ret = $sth->fetchAll($fetchMode);

			$this->trace($statement, $fromTime);
			$this->trigger(self::EventOnExecuted, new DatabaseExecutionErrorArgs($this, $this->options->host, $this->options->database, $this->options->schema, get_class($this), $statement));
			self::triggerSt(self::EventOnExecuted, new DatabaseExecutionErrorArgs($this, $this->options->host, $this->options->database, $this->options->schema, get_class($this), $statement));

			return $ret;
		}
		catch (\PDOException $e) {
			$this->trace($statement, $fromTime);
			$this->_errors[] = $this->lastError = new EventStatus(false, $e->errorInfo[2], $e->errorInfo[1], $e->getMessage());
			CMS::app()->log($e, Logger::DEBUG);
			$this->trigger(self::EventOnError, new DatabaseExecutionErrorArgs($this, $this->options->host, $this->options->database, $this->options->schema, get_class($this), $statement));
			self::triggerSt(self::EventOnError, new DatabaseExecutionErrorArgs($this, $this->options->host, $this->options->database, $this->options->schema, get_class($this), $statement));

			$e = new \Exception(sprintf("RDBMS::fetchAll() Failed executing statement.\nError Code: %s \nMessage: %s", $this->lastError->code, $this->lastError->debugMessage));
			$this->logger()->error($e->getMessage() . ' Trace: ' . $e->getTraceAsString());

			return false;
		}
	}

	public function fetch(object|string $statement, array $params = null, int $fetchMode = null): mixed {
		if ($fetchMode == null)
			$fetchMode = $this->_fetchMode;

		if ($statement instanceof \PDOStatement)
			return $statement->fetch($fetchMode);

		$this->fixParams($params, $statement);

		$fromTime = microtime(true);
		try {
			$sth = $this->_pdo->prepare($statement);
			$ret = $sth->execute($params);
			if (!$ret) {
				$this->trace($statement, $fromTime);
				$this->trigger(self::EventOnError, new DatabaseExecutionErrorArgs($this, $this->options->host, $this->options->database, $this->options->schema, get_class($this), $statement));
				self::triggerSt(self::EventOnError, new DatabaseExecutionErrorArgs($this, $this->options->host, $this->options->database, $this->options->schema, get_class($this), $statement));

				$e = new \Exception(sprintf("RDBMS::fetch() Failed executing statement.\nError Code: %s \nMessage: %s", $this->lastError->code, $this->lastError->debugMessage));
				$this->logger()->error($e->getMessage() . ' Trace: ' . $e->getTraceAsString());

				return false;
			}

			$ret = $sth->fetch($fetchMode);
			$this->trace($statement, $fromTime);

			return $ret;
		}
		catch (\PDOException $e) {
			$this->trace($statement, $fromTime);
			$this->_errors[] = $this->lastError = new EventStatus(false, $e->errorInfo[2], $e->errorInfo[1], $e->getMessage());

			$this->trigger(self::EventOnError, new DatabaseExecutionErrorArgs($this, $this->options->host, $this->options->database, $this->options->schema, get_class($this), $statement));
			self::triggerSt(self::EventOnError, new DatabaseExecutionErrorArgs($this, $this->options->host, $this->options->database, $this->options->schema, get_class($this), $statement));

			$e = new \Exception(sprintf("RDBMS::fetch() Failed executing statement.\nError Code: %s \nMessage: %s", $this->lastError->code, $this->lastError->debugMessage));
			$this->logger()->error($e->getMessage() . ' Trace: ' . $e->getTraceAsString());

			return false;
		}
	}

	public function fetchColumn(object|string $statement, string $columnName, array $params = null): mixed {
		$row = $this->fetch($statement, $params, \PDO::FETCH_ASSOC);

		if ($row) {
			$row = array_change_key_case($row);
			$columnName = strtolower($columnName);

			if (isset ($row[$columnName])) {
				return $row[$columnName];
			}
		}

		return false;
	}

	public function execute(object|string $statement, array $params = null): bool {

		if ($statement instanceof \PDOStatement) {
			$fromTime = microtime(true);

			$this->fixParams($params, $statement);

			try {
				$ret = $statement->execute($params);
				$this->trace($statement->queryString, $fromTime);
				if (!$ret) {
					$error = $statement->errorInfo();
					$this->_errors[] = $this->lastError = new EventStatus(false, $error[2], $error[0], $error[2]);
					$this->trigger(self::EventOnError, new DatabaseExecutionErrorArgs($this, $this->options->host, $this->options->database, $this->options->schema, get_class($this), $statement->queryString));
					self::triggerSt(self::EventOnError, new DatabaseExecutionErrorArgs($this, $this->options->host, $this->options->database, $this->options->schema, get_class($this), $statement->queryString));

					$e = new \Exception(sprintf("RDBMS::execute() Failed executing statement.\nError Code: %s \nMessage: %s", $this->lastError->code, $this->lastError->debugMessage));
					$this->logger()->error($e->getMessage() . ' Trace: ' . $e->getTraceAsString());

					return false;
				}
				$this->_rowsAffected = $statement->rowCount();
				$this->trigger(self::EventOnExecuted, new DatabaseExecutionErrorArgs($this, $this->options->host, $this->options->database, $this->options->schema, get_class($this), $statement->queryString));

				return true;
			}
			catch (\PDOException $e) {
				$this->trace($statement->queryString, $fromTime);
				$this->_errors[] = $this->lastError = new EventStatus(false, $e->errorInfo[2], $e->errorInfo[0], $e->getMessage());

				$this->trigger(self::EventOnError, new DatabaseExecutionErrorArgs($this, $this->options->host, $this->options->database, $this->options->schema, get_class($this), $statement->queryString));
				self::triggerSt(self::EventOnError, new DatabaseExecutionErrorArgs($this, $this->options->host, $this->options->database, $this->options->schema, get_class($this), $statement->queryString));

				$e = new \Exception(sprintf("RDBMS::execute() Failed executing statement.\nSQL: %s, Error Code: %s \nMessage: %s", $statement->queryString, $this->lastError->code, $this->lastError->debugMessage));
				$this->logger()->error($e->getMessage() . ' Trace: ' . $e->getTraceAsString());

				return false;
			}
		}
		else {
			$sth = $this->_pdo->prepare($statement);
			return $this->execute($sth, $params);
		}
	}

	public function exec(string $query, array $params = null): bool|int {
		$stmt = ($query instanceof \PDOStatement) ? $query : $this->prepare($query);
		if ($stmt == false)
			return false; // method prepare() failed

		$ret = $stmt->execute($params);
		if (!$ret)
			return false;

		return $this->_rowsAffected = $stmt->rowCount();
	}

	/**
	 * Prepares a query for execution and returns the prepared statement.
	 *
	 * @param string $query
	 * @param array $options Driver options (optional)
	 *
	 * @return \PDOStatement|bool
	 * @triggers OnError
	 */
	public function prepare(string $query, array $options = []): \PDOStatement|bool {
		try {
			$ret = $this->_pdo->prepare($query, $options);
			$this->getLastError(true);
			return $ret;
		}
		catch (\PDOException $e) {
			$this->getLastError(true);
			$this->trigger(self::EventOnError, new DatabaseExecutionErrorArgs($this, $this->options->host, $this->options->database, $this->options->schema, get_class($this), $query));

			$e = new \Exception(sprintf("RDBMS::prepare() Failed preparing statement.\nStatement: %s \nError Code: %s \nMessage: %s", $query, $this->lastError->code, $this->lastError->debugMessage));
			$this->logger()->error($e->getMessage() . ' Trace: ' . $e->getTraceAsString());

			return false;
		}
	}
	#endregion

	#region Expression methods
	/**
	 * Returns a CASE or equivalent statement of the given pair of key/values, native to database's syntax
	 *
	 * @param DataColumn $column
	 * @param array $keyValues
	 *
	 * @return string
	 */
	public function getCASEExpression(DataColumn $column, array $keyValues): string {
		$case = [];
		foreach ($keyValues as $key => $value) {
			$case[] = "WHEN '" . $this->escape($key) . "' THEN '" . $this->escape($value) . "'";
		}
		return 'CASE ' . $this->getColumnExpression($column, true) . ' ' . implode(' ', $case) . ' END';
	}
	#endregion

	#region Misc. methods
	/**
	 * Returns given relations in an ascendant/descendant order that can be safely used in table joins and SQL queries.
	 *
	 * @param DataRelationCollection $relations
	 *
	 * @return DataRelationCollection
	 */
	public function sortRelations(DataRelationCollection $relations) {
		$nodes = $relations->mesh()->parseNodes();

		$ordered = new DataRelationCollection();
		foreach ($nodes->all() as $node) {
			/** @var DataTable $tbl */
			$tbl = $node->object();
			$rels = $relations->getByParent($tbl);
			if (count($rels) > 1)
				usort($rels, function (DataRelation $a, DataRelation $b) use ($nodes) {
					foreach ($nodes->all() as $node) {
						if ($node->object() === $a->child)
							return -1;
						elseif ($node->object() === $b->child)
							return 1;
					}

					return 0; // Should not get here
				});

			foreach ($rels as $rel)
				$ordered->add($rel);
		}

		return $ordered;
	}

	/**
	 * Returns database connection's last reported error
	 *
	 * @param bool $forceDbError Forces the retrieval of error information from the database connection.
	 *
	 * @return EventStatus
	 */
	protected function getLastError(bool $forceDbError = false): EventStatus {
		if (!$this->isConnected())
			return new EventStatus();

		if ($forceDbError) {
			$error = $this->_pdo->errorInfo();
			if (strlen($error[2]) == 0)
				return new EventStatus();

			$this->_errors[] = $this->lastError = new EventStatus(false, $error[2], $error[1], $error[2]);
		}

		return $this->lastError;
	}

	public function getInsertID($col = null): string|bool {
		if (!$this->isConnected())
			return false;

		return $this->_pdo->lastInsertId();
	}

	public function getRowsAffected(): string|int|bool {
		if (!$this->isConnected()) return false;

		return $this->_rowsAffected;
	}

	public function getRowsMatched(): string|int|bool {
		if (!$this->isConnected()) return false;

		return $this->_rowsAffected;

//		$info = mysql_info ($this->link);
//		preg_match ('/^\D+(\d+)\D+(\d+)\D+(\d+)$/',$info, $matches);
//		list ($str, $rows_matched, $rows_changed, $warnings) = $matches;
	}

	/**
	 * Returns the internal PDO object that is used for communicating with the database
	 *
	 * @return ?\PDO
	 */
	public function pdo(): ?\PDO {
		return $this->_pdo;
	}

	/**
	 * Escapes the input data quoting any special characters found
	 *
	 * @param array|string $data
	 * @param bool $allowHtml
	 *
	 * @return array|string
	 */
	public function escape(array|string $data, bool $allowHtml = true): array|string {
		if (is_array($data) && count($data) > 0) {
			$keys = array_keys($data);
			foreach ($keys as $k)
				$data[$k] = $this->escape($data[$k], $allowHtml);

			return $data;
		}

		if ($allowHtml)
			return substr($this->_pdo->quote($data), 1, -1);
		else
			return substr($this->_pdo->quote(htmlspecialchars($data)), 1, -1);
	}

	/**
	 * Quotes the input data quoting any special characters found
	 *
	 * @param array|string $data
	 * @param bool $allowHtml
	 *
	 * @return array|string
	 */
	public function quote(array|string $data, bool $allowHtml = false): array|string {
		if (is_array($data) && count($data) > 0) {
			$keys = array_keys($data);
			foreach ($keys as $k)
				$data[$k] = $this->quote($data[$k]);

			return $data;
		}

		if ($allowHtml)
			return $this->_pdo->quote($data);
		else
			return $this->_pdo->quote(htmlspecialchars($data));
	}

	public function inTransaction(): bool {
		return $this->_pdo->inTransaction();
	}
	#endregion

	#region Protected methods
	protected function linkFrom(Database $db) {
		if ($db instanceof RDBMS) {
			$this->_link = $db->_link;
			$this->_pdo = $db->_pdo;
		}
	}

	/**
	 * Fixes ad-hoc basic errors found in the given parameters.
	 * Useful before parameters are used in executing prepared statements
	 *
	 * @param array  $params
	 * @param string $query
	 */
	protected function fixParams(&$params, $query = null) {
		if (!is_array($params))
			return;

		// For Oracle driver, use getStatement() method as queryString is null
		if (class_exists('\\PDOOCI\\Statement') && $query instanceof \PDOOCI\Statement)
			$sql = $query->getStatement();
		elseif ($query instanceof \PDOStatement)
			$sql = $query->queryString;
		else
			$sql = (string)$query;

		foreach ($params as $key => $value) {
			// Prepend ":" if is missing
			if (strpos($key, ':') !== 0) {
				$params[":$key"] = $value;
				unset ($params[$key]);
				$key = ":$key";
			}

			// If parameter is not used anywhere in the query, remove it to avoid raising a PDO exception
			if (strpos($sql, $key) === false)
				unset ($params[$key]);
		}
	}

	protected abstract function addLimitParams($query, ?int $start = null, ?int $limit = null);
	#endregion
	#endregion
}
