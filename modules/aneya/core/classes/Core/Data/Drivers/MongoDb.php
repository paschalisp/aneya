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

use aneya\Core\ApplicationError;
use aneya\Core\CMS;
use aneya\Core\Data\ConnectionOptions;
use aneya\Core\Data\Database;
use aneya\Core\Data\DataColumn;
use aneya\Core\Data\DataColumnCollection;
use aneya\Core\Data\DataFilter;
use aneya\Core\Data\DataFilterCollection;
use aneya\Core\Data\DataObject;
use aneya\Core\Data\DataObjectFactory;
use aneya\Core\Data\DataObjectFactoryEventArgs;
use aneya\Core\Data\DataObjectFactoryEventStatus;
use aneya\Core\Data\DataRelation;
use aneya\Core\Data\DataRelationCollection;
use aneya\Core\Data\DataRow;
use aneya\Core\Data\DataRowSaveEventArgs;
use aneya\Core\Data\DataSet;
use aneya\Core\Data\DataSorting;
use aneya\Core\Data\DataSortingCollection;
use aneya\Core\Data\DataTable;
use aneya\Core\Data\DataTableCollection;
use aneya\Core\Data\ODBMS;
use aneya\Core\Data\Schema\Schema;
use aneya\Core\EventStatus;
use aneya\Core\IHookable;
use aneya\Core\IStorable;
use aneya\Core\Utils\ObjectUtils;
use JetBrains\PhpStorm\Pure;
use MongoDB\Client;
use MongoDB\Driver\Cursor;
use MongoDB\InsertManyResult;
use MongoDB\InsertOneResult;
use MongoDB\UpdateResult;
use Monolog\Logger;

final class MongoDb extends ODBMS {
	#region Constants
	const DefaultPort = 27017;
	#endregion

	#region Properties
	/** @var MongoDbSchema */
	public Schema $schema;
	/** @var Client */
	protected $_link = null;
	protected ?\MongoDB\Database $_db = null;
	#endregion

	#region Constructor & initialization
	public function __construct() {
		parent::__construct();

		$this->_link = null;
		$this->_type = Database::MongoDb;
		$this->schema = new MongoDbSchema();
		$this->schema->setDatabaseInstance($this);
		$this->options = new ConnectionOptions();

		$this->_supportsJoins = false;
	}

	public static function init() {
		DataObjectFactory::onSt(DataObjectFactory::EventStOnCreate, function (DataObjectFactoryEventArgs $args) {
			$status = new DataObjectFactoryEventStatus();
			if (is_object($args->data)) {
				if ($args->data instanceof \MongoDB\BSON\ObjectId) {
					$status->object = $args->data; // Keep the same MongoId object
					$status->isHandled = $status->isPositive = true;

					return $status;
				}
				elseif ($args->data instanceof \MongoDB\BSON\UTCDateTime) {
					$status->object = $args->data->toDateTime();
					$status->isHandled = $status->isPositive = true;

					return $status;
				}
			}

			return null;
		});
	}
	#endregion

	#region Methods
	#region Connection methods
	/** @inheritdoc */
	public function connect(ConnectionOptions $options = null): bool {
		if (isset ($this->_link))
			$this->disconnect();

		// Apply argument connection options to instance's connection options
		if (isset($options) && $options !== $this->options)
			$this->options->applyCfg($options->toJson());

		try {
			$this->_link = new Client($this->getConnectionString());
			$this->_db = $this->_link->selectDatabase($this->options->database);
		}
		catch (\MongoDB\Driver\Exception\Exception $e) {
			$this->lastError = new EventStatus(false, $e->getMessage(), $e->getCode(), $e->getTraceAsString());
			$this->logger()->log($e, ApplicationError::SeverityAlert);
			return false;
		}

		return true;
	}

	/** @inheritdoc */
	public function reconnect() {
		if ($this->isConnected())
			return;

		try {
			$this->_link = new Client($this->getConnectionString());
			$this->_db = $this->_link->selectDatabase($this->options->database);
		}
		catch (\MongoDB\Driver\Exception\Exception $e) {
			$this->lastError = new EventStatus(false, $e->getMessage(), $e->getCode(), $e->getTraceAsString());
			$this->logger()->log($e, ApplicationError::SeverityAlert);
		}
	}

	/** @inheritdoc */
	public function disconnect() {
		if ($this->_link instanceof Client) {
			unset($this->_db);
			unset($this->_link);
		}
	}

	/** @inheritdoc */
	public function isConnected(): bool {
		return ($this->_link instanceof Client && $this->_db instanceof \MongoDB\Database);
	}

	/** @inheritdoc */
	public function getConnectionString(): string {
		return (strlen($this->options->username) > 0)
			? sprintf('mongodb://%s:%s@%s:%d', $this->options->username, $this->options->password, $this->options->host, $this->options->port)
			: sprintf('mongodb://%s:%d', $this->options->host, $this->options->port);
	}

	/**
	 * @inheritdoc
	 */
	#[Pure] public function parseCfg(\stdClass $cfg): ConnectionOptions {
		$connOpts = new ConnectionOptions();
		$connOpts->host = $cfg->host;
		$connOpts->port = (int)$cfg->port > 0 ? (int)$cfg->port : self::DefaultPort;
		$connOpts->database = $cfg->database;
		$connOpts->charset = 'utf8';
		$connOpts->timezone = 'UTC';
		$connOpts->username = $cfg->username;
		$connOpts->password = $cfg->password;

		$connOpts->readonly = isset($cfg->readonly) && $cfg->readonly === true;

		return $connOpts;
	}
	#endregion

	#region Prepare, execution & fetching methods
	#region Not used by Mongo
	/**
	 * @param string $query
	 * @param array $options Driver options (optional)
	 * @return mixed|string
	 * @deprecated Should not be used in Mongo databases
	 */
	public function prepare($query, $options = []) {
		// Not used in Mongo driver
		return $query;
	}

	/**
	 * Alias to the exec($query, $params) function.
	 *
	 * @param array $statement Query to execute.
	 * @param array $params
	 * @return array|bool
	 * @deprecated Should not be used in Mongo databases
	 */
	public function execute($statement, $params = array()) {
		// Not used in Mongo driver
		return false;
	}

	/**
	 * @param string $query
	 * @param array $options
	 * @return bool|int
	 * @deprecated Should not be used in Mongo databases
	 */
	public function exec($query, $options = array()) {
		// Not used in Mongo driver
		return false;
	}

	/**
	 * @param \MongoDB\Collection|string $collection The MongoCollection instance or name to fetch results from
	 * @param array $query The fields to search
	 * @param int $start If statement is string, $start will be used to fetch results starting from this value
	 * @param int $limit If statement is string, $limit will be used to limit the results of the query
	 * @param int $fetchMode (not used in Mongo) A fetch mode, one of the PDO::FETCH_* constants
	 * @param mixed $arguments (not used in Mongo) Parameters to be passed when preparing the statement, in case the statement provided was a string
	 * @return Cursor
	 */
	public function fetchAll($collection, $query = [], $start = null, $limit = null, $fetchMode = null, $arguments = []): Cursor {
		if (!($collection instanceof \MongoDB\Collection))
			$collection = $this->_db->selectCollection($collection);

		$options = [];
		if (is_int($start))
			$options['skip'] = (int)$start;
		if (is_int($limit))
			$options['limit'] = (int)$limit;

		return $collection->find($query, $options);
	}

	/**
	 * Fetches the next row from the specified statement.
	 *
	 * If statement is string, then a prepared statement is generated automatically, using the parameters passed in the third argument.
	 *
	 * @param string|object $statement A prepared statement or query string
	 * @param array $params If statement is string, $params will be passed when executing the statement
	 * @param int $fetchMode A fetch mode, one of the PDO::FETCH_* constants.
	 * @return mixed
	 * @deprecated Should not be used in Mongo databases
	 */
	public function fetch($statement, $params = null, $fetchMode = null) {
		// Not used in Mongo driver
		return false;
	}

	/**
	 * Fetches the next row from the specified statement and returns the given column's value.
	 *
	 * If statement is string, then a prepared statement is generated automatically, using the parameters passed in the third argument.
	 *
	 * @param string|object $statement A prepared statement or query string
	 * @param string $columnName The column name which value is to return
	 * @param array $params If statement is string, $params will be passed when executing the statement
	 * @return mixed
	 * @deprecated Should not be used in Mongo databases
	 */
	public function fetchColumn($statement, $columnName, $params = null) {
		// Not used in Mongo driver
		return false;
	}
	#endregion
	#endregion

	#region Mongo-specific methods
	/**
	 * Inserts an object into the specified collection
	 *
	 * @param \MongoDB\Collection|string $collection
	 * @param object|array $object
	 * @param array $options
	 * @return InsertManyResult|InsertOneResult|bool
	 */
	public function insert(\MongoDB\Collection|string $collection, object|array $object, array $options = []): bool|InsertManyResult|InsertOneResult {
		if (!is_string($collection) && !($collection instanceof \MongoDB\Collection)) {
			throw new \InvalidArgumentException('Argument 1 is neither a \\MongoDB\\Collection nor a string');
		}
		if (is_array($object)) {
			$doc = [];
			foreach ($object as $o) {
				$doc[] = $this->toNativeObj($o);
			}
		} elseif (is_object($object)) {
			$doc = $this->toNativeObj($object);
		} else {
			$this->logger()->log(new \Error('Argument 2 is not an object or array of objects'), Logger::DEBUG);
			return false;
		}

		if (is_string($collection))
			$collection = $this->db()->selectCollection($collection);

		try {
			if (is_array($doc)) {
				return $collection->insertMany($doc, $options);
			}
			else {
				$ret = $collection->insertOne($doc, $options);
				// Assign the newly generated MongoId to the object
				$object->_id = $doc->_id;
				$object->_id = $ret->getInsertedId();

				return $ret;
			}
		}
		catch (\Exception $e) {
			$this->lastError = new EventStatus(false, $e->getMessage(), $e->getCode(), $e->getTraceAsString());
			$this->logger()->log($e, Logger::ERROR);
			return false;
		}
	}

	/**
	 * Updates one or more documents in the specified collection, that match the given criteria.
	 *
	 * @param \MongoDB\Collection|string $collection
	 * @param array $criteria
	 * @param object|array $set
	 * @param array $options
	 * @return UpdateResult|bool
	 */
	public function update(\MongoDB\Collection|string $collection, array $criteria, object|array $set, array $options = []): bool|UpdateResult {
		if (!is_string($collection) && !($collection instanceof \MongoDB\Collection)) {
			throw new \InvalidArgumentException('Argument 1 is neither a \\MongoDB\\Collection nor a string');
		}

		if (is_string($collection))
			$collection = $this->db()->selectCollection($collection);

		try {
			return $collection->updateMany($criteria, $set, $options);
		}
		catch (\Exception $e) {
			$this->lastError = new EventStatus(false, $e->getMessage(), $e->getCode(), $e->getTraceAsString());
			$this->logger()->log($e, Logger::ERROR);
			return false;
		}
	}

	public function db(): ?\MongoDB\Database {
		return $this->_db;
	}

	public function link(): ?Client {
		return $this->_link;
	}
	#endregion

	#region ODBMS methods
	/** @inheritdoc */
	public function toNativeObj($obj) {
		if (is_scalar($obj) || is_null($obj) ||
				$obj instanceof \MongoDB\BSON\Serializable ||
				$obj instanceof \MongoDB\BSON\ObjectId ||
				$obj instanceof \MongoDB\BSON\UTCDateTime ||
				$obj instanceof \MongoDB\BSON\Timestamp ||
				$obj instanceof \MongoDB\BSON\Binary ||
				$obj instanceof \MongoDB\BSON\Decimal128 ||
				$obj instanceof \MongoDB\BSON\Javascript ||
				$obj instanceof \MongoDB\BSON\Regex)
			return $obj;

		if ($obj instanceof \DateTime)
			return new \MongoDB\BSON\UTCDateTime($obj->getTimestamp());

		$ret = new \stdClass();
		$serializable = array();
		if ($obj instanceof IStorable) {
			$ret->__class = get_class($obj);
			$ret->__version = $obj->__classVersion();
			$serializable = $obj->__classProperties();

			$obj = $obj->__classToArray($this);
		}

		foreach ($obj as $property => $value) {
			$property = (string)$property; // Convert to string to avoid erroneously skipping zeroes as property name (usually the first element of index-based arrays)
			if ($property == 'protected' || $property == 'private' || strlen($property) == 0)
				continue;
			if (isset ($serializable['allow']) && count($serializable['allow']) > 0 && !in_array($property, $serializable['allow']))
				continue;
			if (isset ($serializable['deny']) && in_array($property, $serializable['deny']))
				continue;
			if (count($serializable) > 0 && !isset($serializable['allow']) && !isset($serializable['deny']) && !in_array($property, $serializable))
				continue;
			if ($property == '_id' && $value == null)
				continue;

			if (is_scalar($value))
				$ret->$property = $value;
			else {
				if (is_object($value)) {
					if ($value instanceof \DateTime) {
						$value = new \MongoDB\BSON\UTCDateTime($value->getTimestamp());
					} elseif (!($value instanceof \MongoDB\BSON\Serializable ||
							$value instanceof \MongoDB\BSON\ObjectId ||
							$value instanceof \MongoDB\BSON\UTCDateTime ||
							$value instanceof \MongoDB\BSON\Timestamp ||
							$value instanceof \MongoDB\BSON\Binary ||
							$value instanceof \MongoDB\BSON\Decimal128 ||
							$value instanceof \MongoDB\BSON\Javascript ||
							$value instanceof \MongoDB\BSON\Regex)) {
						$value = $this->toNativeObj($value);
					}
				} elseif (is_array($value)) {
					$value2 = array();
					foreach ($value as $k => $v) {
						$value2[$k] = (is_scalar($v) || $v == null) ? $v : $this->toNativeObj($v);
					}
					$value = $value2;
				}

				$ret->$property = $value;
			}
		}

		return $ret;
	}

	/** @inheritdoc */
	public function fromNativeObj($obj) {
		if (is_scalar($obj) || $obj == null)
			return $obj;

		if (is_array($obj)) {
			$ret = array();

			foreach ($obj as $key => $value) {
				$ret[$key] = (is_scalar($value) || $value == null) ? $value : $this->fromNativeObj($value);
			}

			if (isset ($ret['__class']))
				$ret = DataObjectFactory::create($ret);
		} elseif (is_object($obj)) {
			if ($obj instanceof \MongoDB\BSON\ObjectId)
				return $obj;
			elseif ($obj instanceof \MongoDB\BSON\UTCDateTime || $obj instanceof \MongoDB\BSON\Timestamp) {
				return $obj->toDateTime();
			}

			// Leave the object argument intact
			$ret = clone $obj;

			foreach ($ret as $property => $value) {
				// If property is storable and value hasn't set storage/retrieval information, add it manually
				if (is_object($ret->$property) && isset($ret->$property->__class)) {
					if (is_array($value) && !isset($value['__class'])) {
						$value['__class'] = $ret->$property->__class;
						$value['__version'] = $ret->$property->__version;
					}
				}
				// Only objects and arrays need to be converted
				if (is_object($value) || is_array($value)) {
					$ret->$property = $this->fromNativeObj($value);
				}
			}

			if (isset ($ret->__class))
				$ret = DataObjectFactory::create($ret);
		} else
			$ret = $obj;

		return $ret;
	}
	#endregion

	#region Transaction methods
	/** @inheritdoc */
	public function beginTransaction(string $name = null): bool|int {
		// Not used in Mongo driver
		return false;
	}

	/** @inheritdoc */
	public function commit(string $name = null): bool|int {
		// Not used in Mongo driver
		return false;
	}

	/** @inheritdoc */
	public function rollback(string $name = null) {
		// Not used in Mongo driver
		return false;
	}

	/** @inheritdoc */
	public function inTransaction(): bool {
		// Not used in Mongo driver
		return false;
	}
	#endregion

	#region DataSet methods
	/** @inheritdoc */
	public function retrieve(DataTable $parent, DataTableCollection $tables, DataRelationCollection $relations, DataColumnCollection $columns, DataColumnCollection $listColumns, $filters = null, $sorting = null, $grouping = null, $having = null, $start = null, $limit = null): bool|int {
		if ($parent instanceof DataSet)
			$collection = $this->_db->selectCollection($parent->masterTable()->name);
		else
			$collection = $this->_db->selectCollection($parent->name);

		$parent->rows->clear();

		#region Build columns
		$columnsArray = array();
		$hasExpressions = (
			$columns->count(function (DataColumn $col) { return $col->isActive && $col->isExpression; }) > 0 ||
			(isset ($grouping) && ($grouping instanceof DataColumn || ($grouping instanceof DataColumnCollection && $grouping->count() > 0) || (is_array($grouping) && count($grouping) > 0))) ||
			(isset ($having) && ($having instanceof DataFilter || ($having instanceof DataFilterCollection && $having->count() > 0) || (is_array($having) && count($having) > 0)))
		);
		if ($hasExpressions) {
			$project = array('__class' => 1, '__version' => 1);
			foreach ($columns->all() as $c) {
				if ($c->isExpression)
					$project[$c->tag] = $c->name;
				else {
					$project[$c->name] = 1;

					// Add __class and __version information for sub-properties
					$propsHierarchy = explode('.', $c->name);
					if (($cnt = count($propsHierarchy)) > 1) {
						$prop = $propsHierarchy[0];
						for ($i = 0; $i < $cnt - 1; $i++) {
							if ($i > 0)
								$prop .= '.' . $propsHierarchy[$i];
							$project["$prop.__class"] = 1;
							$project["$prop.__version"] = 1;
						}
					}
				}
			}
			$columnsArray['$project'] = $project;
		}
		else {
			$columnsArray = array('__class' => 1, '__version' => 1);
			foreach ($listColumns->all() as $c) {
				$columnsArray[$c->name] = 1;

				// Add __class and __version information for sub-properties
				$propsHierarchy = explode('.', $c->name);
				if (($cnt = count($propsHierarchy)) > 1) {
					$prop = $propsHierarchy[0];
					for ($i = 0; $i < $cnt - 1; $i++) {
						if ($i > 0)
							$prop .= '.' . $propsHierarchy[$i];

						$columnsArray["$prop.__class"] = 1;
						$columnsArray["$prop.__version"] = 1;
					}
				}
			}
		}
		#endregion

		#region Build criteria
		$filtersArray = array();
		if ($filters instanceof DataFilter || (is_array($filters) && count($filters) > 0) || ($filters instanceof DataFilterCollection && $filters->count() > 0)) {
			$filtersArray = $this->getFilterExpression($filters);
		}
		#endregion

		#region Build grouping
		$groupArray = array();
		if ($grouping != null) {
			if ($grouping instanceof DataColumn) {
				$array = array('_id' => null);
				$c = $grouping;
				$cName = $c->isExpression ? $c->tag : $c->name;

				if ($c->dataType == DataColumn::DataTypeDate || $c->dataType == DataColumn::DataTypeDateTime) {
					// Group date/time fields by the date part only
					$array[$cName] = array('month' => array('$month' => '$' . $cName), array('$day' => '$' . $cName), array('$year' => '$' . $cName));
				}
				else
					$array[$cName] = '$' . $cName;
			}
			elseif (is_array($grouping) && count($grouping) > 0) {
				$array = array('_id' => null);
				foreach ($grouping as $c) {
					$cName = $c->isExpression ? $c->tag : $c->name;
					if ($c->dataType == DataColumn::DataTypeDate || $c->dataType == DataColumn::DataTypeDateTime) {
						// Group date/time fields by the date part only
						$array[$cName] = array('month' => array('$month' => '$' . $cName), array('$day' => '$' . $cName), array('$year' => '$' . $cName));
					}
					else
						$array[$cName] = '$' . $cName;
				}
			}
			elseif ($grouping instanceof DataColumnCollection && ($grouping->count() > 0 || $columns->count(function (DataColumn $col) { return $col->isActive && $col->isAggregate; }) > 0)) {
				$array = array('_id' => null);
				foreach ($grouping->all() as $c) {
					$cName = $c->isExpression ? $c->tag : $c->name;
					if ($c->dataType == DataColumn::DataTypeDate || $c->dataType == DataColumn::DataTypeDateTime) {
						// Group date/time fields by the date part only
						$array[$cName] = array('month' => array('$month' => '$' . $cName), array('$day' => '$' . $cName), array('$year' => '$' . $cName));
					}
					else
						$array[$cName] = '$' . $cName;
				}
			}

			if (isset ($array)) {
				if (count($array) > 0)
					$groupArray['_id'] = $array;

				$aggregates = $columns->all(function (DataColumn $col) { return $col->isActive && $col->isAggregate; });
				foreach ($aggregates as $c) {
					$groupArray['_id'][] = array($c->tag => $c->name);
				}
			}
		}
		#endregion

		#region Build having
		$havingArray = array();
		if ($having != null && ($having instanceof DataFilter || ($having instanceof DataFilterCollection && $having->count() > 0))) {
			$havingArray = $this->getFilterExpression($having);
		}
		#endregion

		#region Build sorting
		$sortArray = array();
		if ($sorting != null && ($sorting instanceof DataSorting || ($sorting instanceof DataSortingCollection && $sorting->count() > 0))) {
			$sortArray = $this->getSortingExpression($sorting);
		}
		#endregion

		#region Retrieve data
		if ($hasExpressions) {
			$pipeline = array();
			$pipeline[] = $columnsArray;
			if (count($filtersArray) > 0)
				$pipeline[] = array('$match' => $filtersArray);
			if (count($groupArray) > 0)
				$pipeline[] = array('$group' => $groupArray);
			if (count($havingArray) > 0)
				$pipeline[] = array('$match' => $havingArray);
			if (count($sortArray) > 0)
				$pipeline[] = array('$sort' => $sortArray);
			if (is_int($start))
				$pipeline[] = array('$skip' => $start);
			if (is_int($limit))
				$pipeline[] = array('$limit' => $limit);

			try {
				$docs = $collection->aggregate($pipeline);
			}
			catch (\Exception $e) {
				$this->lastError = new EventStatus(false, $e->getMessage(), $e->getCode(), $e->getTraceAsString());
				$this->logger()->log(Logger::ERROR, $e->getMessage() . "\n" . $e->getTraceAsString());
				return 0;
			}
		} else {
			$options = [];
			if (is_int($start))
				$options['skip'] = $start;
			if (is_int($limit))
				$options['limit'] = $limit;
			if (count($sortArray) > 0)
				$options['sort'] = $sortArray;

			try {
				$docs = $collection->find($filtersArray, $options);
			}
			catch (\Exception $e) {
				$this->lastError = new EventStatus(false, $e->getMessage(), $e->getCode(), $e->getTraceAsString());
				$this->logger()->log(Logger::ERROR, $e->getMessage() . "\n" . $e->getTraceAsString());
				return 0;
			}
		}

		foreach ($docs as $doc) {
			$obj = $this->fromNativeObj($doc);
			$doc2 = ObjectUtils::flatten($obj);
			// Include any columns that might not exist in the object, such as expressions and aggregated values
			foreach ($doc as $key => $value) {
				if (!isset ($doc2[$key]))
					$doc2[$key] = $value;
			}
			$dr = new DataObject($doc2, $parent);
			$parent->rows->addWithState($dr, DataRow::StateUnchanged);
			$dr->object($obj, DataRow::SourceDatabase);
		}
		#endregion

		return $parent->rows->count();
	}

	/**
	 * @inheritdoc
	 * @todo Does not yet handle grouping & having
	 * @todo Does not exclude expressions in Mongo queries
	 */
	public function retrieveCnt(DataTable $parent, DataTableCollection $tables, DataRelationCollection $relations, $filters = null, $grouping = null, $having = null): int {
		if ($parent instanceof DataSet)
			$collection = $this->_db->selectCollection($parent->masterTable()->name);
		else
			$collection = $this->_db->selectCollection($parent->name);

		// TODO: Does not yet handle grouping & having
		#region Build criteria
		$filtersArray = array();
		if ($filters != null && ($filters instanceof DataFilter || ($filters instanceof DataFilterCollection && $filters->count() > 0))) {
			$where = new DataFilterCollection();
			foreach ($filters->all() as $f) {
				if ($f->column->isExpression)
					continue;    // TODO: Exclude expressions in Mongo queries
				else
					$where->add($f);
			}
			$filtersArray = $this->getFilterExpression($where);
		}
		#endregion

		return $collection->countDocuments($filtersArray);
	}

	/**
	 * @inheritdoc
	 */
	public function retrieveQuery(DataTable $parent, DataTableCollection $tables, DataRelationCollection $relations, DataColumnCollection $columns, DataColumnCollection $listColumns, $filters = null, $sorting = null, $grouping = null, $having = null, $start = null, $limit = null) {
		#region Build columns
		$columnsArray = [];
		$hasExpressions = (
			$columns->count(function (DataColumn $col) { return $col->isActive && $col->isExpression; }) > 0 ||
			(isset ($grouping) && ($grouping instanceof DataColumn || ($grouping instanceof DataColumnCollection && $grouping->count() > 0) || (is_array($grouping) && count($grouping) > 0))) ||
			(isset ($having) && ($having instanceof DataFilter || ($having instanceof DataFilterCollection && $having->count() > 0) || (is_array($having) && count($having) > 0)))
		);
		if ($hasExpressions) {
			$project = array('__class' => 1, '__version' => 1);
			foreach ($columns->all() as $c) {
				if ($c->isExpression)
					$project[$c->tag] = $c->name;
				else {
					$project[$c->name] = 1;

					// Add __class and __version information for sub-properties
					$propsHierarchy = explode('.', $c->name);
					if (($cnt = count($propsHierarchy)) > 1) {
						$prop = $propsHierarchy[0];
						for ($i = 0; $i < $cnt - 1; $i++) {
							if ($i > 0)
								$prop .= '.' . $propsHierarchy[$i];
							$project["$prop.__class"] = 1;
							$project["$prop.__version"] = 1;
						}
					}
				}
			}
			$columnsArray['$project'] = $project;
		}
		else {
			$columnsArray = array('__class' => 1, '__version' => 1);
			foreach ($listColumns->all() as $c) {
				$columnsArray[$c->name] = 1;

				// Add __class and __version information for sub-properties
				$propsHierarchy = explode('.', $c->name);
				if (($cnt = count($propsHierarchy)) > 1) {
					$prop = $propsHierarchy[0];
					for ($i = 0; $i < $cnt - 1; $i++) {
						if ($i > 0)
							$prop .= '.' . $propsHierarchy[$i];

						$columnsArray["$prop.__class"] = 1;
						$columnsArray["$prop.__version"] = 1;
					}
				}
			}
		}
		#endregion

		#region Build criteria
		$filtersArray = array();
		if ($filters instanceof DataFilter || (is_array($filters) && count($filters) > 0) || ($filters instanceof DataFilterCollection && $filters->count() > 0)) {
			$filtersArray = $this->getFilterExpression($filters);
		}
		#endregion

		#region Build grouping
		$groupArray = [];
		if ($grouping != null) {
			if ($grouping instanceof DataColumn) {
				$array = ['_id' => null];
				$c = $grouping;
				$cName = $c->isExpression ? $c->tag : $c->name;
				if ($c->dataType == DataColumn::DataTypeDate || $c->dataType == DataColumn::DataTypeDateTime) {
					// Group date/time fields by the date part only
					$array[$cName] = ['month' => ['$month' => '$' . $cName], ['$day' => '$' . $cName], ['$year' => '$' . $cName]];
				}
				else
					$array[$cName] = '$' . $cName;
			}
			elseif (is_array($grouping) && count($grouping) > 0) {
				$array = ['_id' => null];
				foreach ($grouping as $c) {
					$cName = $c->isExpression ? $c->tag : $c->name;
					if ($c->dataType == DataColumn::DataTypeDate || $c->dataType == DataColumn::DataTypeDateTime) {
						// Group date/time fields by the date part only
						$array[$cName] = ['month' => ['$month' => '$' . $cName], ['$day' => '$' . $cName], ['$year' => '$' . $cName]];
					}
					else
						$array[$cName] = '$' . $cName;
				}
			}
			elseif ($grouping instanceof DataColumnCollection && ($grouping->count() > 0 || $columns->count(function (DataColumn $col) { return $col->isActive && $col->isAggregate; }) > 0)) {
				$array = ['_id' => null];
				foreach ($grouping->all() as $c) {
					$cName = $c->isExpression ? $c->tag : $c->name;
					if ($c->dataType == DataColumn::DataTypeDate || $c->dataType == DataColumn::DataTypeDateTime) {
						// Group date/time fields by the date part only
						$array[$cName] = ['month' => ['$month' => '$' . $cName], ['$day' => '$' . $cName], ['$year' => '$' . $cName]];
					}
					else
						$array[$cName] = '$' . $cName;
				}
			}

			if (isset ($array)) {
				if (count($array) > 0)
					$groupArray['_id'] = $array;

				$aggregates = $columns->all(function (DataColumn $col) { return $col->isActive && $col->isAggregate; });
				foreach ($aggregates as $c) {
					$groupArray['_id'][] = array($c->tag => $c->name);
				}
			}
		}
		#endregion

		#region Build having
		$havingArray = [];
		if ($having instanceof DataFilter || (is_array($having) && count($having) > 0) || ($having instanceof DataFilterCollection && $having->count() > 0)) {
			$havingArray = $this->getFilterExpression($having);
		}
		#endregion

		#region Build sorting
		$sortArray = array();
		if ($sorting != null && ($sorting instanceof DataSorting || ($sorting instanceof DataSortingCollection && $sorting->count() > 0))) {
			$sortArray = $this->getSortingExpression($sorting);
		}
		#endregion

		if ($hasExpressions) {
			$pipeline = array();
			$pipeline[] = $columnsArray;
			if (count($filtersArray) > 0)
				$pipeline[] = array('$match' => $filtersArray);
			if (count($groupArray) > 0)
				$pipeline[] = array('$group' => $groupArray);
			if (count($havingArray) > 0)
				$pipeline[] = array('$match' => $havingArray);
			if (count($sortArray) > 0)
				$pipeline[] = array('$sort' => $sortArray);
			if (is_int($start))
				$pipeline[] = array('$skip' => $start);
			if (is_int($limit))
				$pipeline[] = array('$limit' => $limit);

			return $pipeline;
		}
		else {
			return $filtersArray;
		}
	}

	/**
	 * Returned event status $data property points to the operation's \Mongo\Operation\InsertOneResult or \Mongo\Operation\UpdateResult or \Mongo\Operation\DeleteResult instance,
	 * depending on the operation concluded from the provided row's state.
	 *
	 * @inheritdoc
	 */
	public function save(DataRow $row): EventStatus {
		if ($row->parent->db()->schema->readonly === true) {
			$e = new \Exception('MongoDb::save() Cannot save or delete data in a readonly schema');
			$this->logger()->error($e->getMessage() . ' Trace: ' . $e->getTraceAsString());
			return $this->lastError = new EventStatus(false, 'Cannot save data in a readonly schema [' . $row->parent->db()->schema->schemaName() . ']');
		}

		$status = new EventStatus();

		$state = $row->getState();
		$collection = ($row->parent instanceof DataSet)
			? $row->parent->masterTable()->name
			: $row->parent->name;

		$collection = $this->_db->selectCollection($collection);

		$object = $row->object();

		if ($object instanceof IHookable) {
			$object->trigger(DataRow::EventOnSaving, new DataRowSaveEventArgs($row));
		}

		if ($state == DataRow::StateAdded) {
			$doc = $this->toNativeObj($object);
			try {
				$ret = $collection->insertOne($doc);
				$status->data = $ret;
				// Apply the newly created MongoId back to the row
				$row->setValue('_id', $ret->getInsertedId());
			}
			catch (\MongoDB\Driver\Exception\Exception $e) {
				$this->logger()->error($e->getMessage() . ' Trace: ' . $e->getTraceAsString());
				return $this->lastError = new EventStatus(false, CMS::translator()->translate('record_save_error', 'cms'), $e->getCode(), $e->getMessage() . ' Trace: ' . $e->getTraceAsString());
			}
		}
		elseif ($state == DataRow::StateModified) {
			$criteria = [];
			$keyCols = $row->parent->columns->all(function (DataColumn $col) { return $col->isActive && $col->isKey; });
			foreach ($keyCols as $col) {
				$property = $col->tag;
				$criteria[$col->name] = $object->$property;
			}
			// Fail-safe if no any criteria are set
			if (count($criteria) == 0 && isset ($object->_id) && $object->_id instanceof \MongoDB\BSON\ObjectId) {
				$criteria = array('_id' => $object->_id);
			}

			// Find and update only changed columns
			$columns = array();
			foreach ($row->parent->columns->all() as $col) {
				if (!$col->isActive || $col->isExpression || $col->isFake || !$col->isSaveable)
					continue;

				if (!$row->hasColumnChanged($col))
					continue;

				$value = ($col->isMultilingual) ? $row->getValueTr($col) : $row->getValue($col);
				$columns[$col->name] = is_scalar($value) ? $value : $this->toNativeObj($value);
			}

			if (count($columns) > 0) {
				try {
					$ret = $collection->updateOne($criteria, ['$set' => $columns]);
					$status->data = $ret;
				}
				catch (\MongoDB\Driver\Exception\Exception $e) {
					$this->logger()->error($e->getMessage() . ' Trace: ' . $e->getTraceAsString());
					return $this->lastError = new EventStatus(false, CMS::translator()->translate('record_save_error', 'cms'), $e->getCode(), $e->getMessage() . ' Trace: ' . $e->getTraceAsString());
				}
			}
		}
		elseif ($state == DataRow::StateDeleted) {
			if (!isset ($object->_id)) {
				$criteria = [];
				$keyCols = $row->parent->columns->all(function (DataColumn $col) { return $col->isActive && $col->isKey; });
				foreach ($keyCols as $col) {
					$property = $col->tag;
					$criteria[$col->name] = $object->$property;
				}
			} else {
				$criteria = ['_id' => $object->_id];
			}

			try {
				$ret = $collection->deleteOne($criteria);
				$status->data = $ret;
			}
			catch (\MongoDB\Driver\Exception\Exception $e) {
				$this->logger()->error($e->getMessage() . ' Trace: ' . $e->getTraceAsString());
				return $this->lastError = new EventStatus(false, CMS::translator()->translate('record_delete_error', 'cms'), $e->getCode(), $e->getMessage() . ' Trace: ' . $e->getTraceAsString());
			}
		}

		if ($object instanceof IHookable) {
			$object->trigger(DataRow::EventOnSaved, new DataRowSaveEventArgs($row));
		}

		return $status;
	}

	/**
	 * Returned event status $data property points to the operation's \Mongo\Operation\DeleteResult instance.
	 * @inheritdoc
	 */
	public function delete(DataTable $parent, DataFilterCollection|DataFilter|array $filters): EventStatus {
		if ($parent->db()->schema->readonly === true) {
			$e = new \Exception('MongoDb::delete() Cannot save or delete data in a readonly schema');
			$this->logger()->error($e->getMessage() . ' Trace: ' . $e->getTraceAsString());
			return new EventStatus(false, 'Cannot delete data in a readonly schema [' . $parent->db()->schema->schemaName() . ']');
		}

		$collection = ($parent instanceof DataSet)
			? $parent->masterTable()->name
			: $parent->name;

		$collection = $this->_db->selectCollection($collection);
		try {
			$ret = $collection->deleteMany($filters);
			$status = new EventStatus();
			$status->data = $ret;
			return $status;
		}
		catch (\MongoDB\Driver\Exception\Exception $e) {
			$this->logger()->error($e->getMessage() . ' Trace: ' . $e->getTraceAsString());
			return $this->lastError = new EventStatus(false, CMS::translator()->translate('record_delete_error', 'cms'), $e->getCode(), $e->getMessage() . ' Trace: ' . $e->getTraceAsString());
		}
	}
	#endregion

	#region Expression Methods
	/** @inheritdoc */
	public function getFilterExpression(DataFilterCollection|DataFilter|array $filter) {
		if ($filter instanceof DataFilter) {
			$value = $filter->value;
			$fieldName = $filter->column->isExpression ? $filter->column->tag : $filter->column->name;
			switch ($filter->condition) {
				case DataFilter::Equals:
					return [$fieldName => $value];
				case DataFilter::NotEqual:
					return [$fieldName => ['$ne' => $value]];
				case DataFilter::Contains:
					return [$fieldName => ['$regex' => new \MongoDB\BSON\Regex($value)]];
				case DataFilter::NotContain:
					return [$fieldName => ['$not' => new \MongoDB\BSON\Regex($value)]];
				case DataFilter::StartsWith:
					return [$fieldName => ['$regex' => new \MongoDB\BSON\Regex("^$value")]];
				case DataFilter::NotStartWith:
					return [$fieldName => ['$not' => new \MongoDB\BSON\Regex("^$value")]];
				case DataFilter::EndsWith:
					return [$fieldName => ['$regex' => new \MongoDB\BSON\Regex("$value\$")]];
				case DataFilter::NotEndWith:
					return [$fieldName => ['$not' => new \MongoDB\BSON\Regex("$value\$")]];
				case DataFilter::GreaterThan:
					return [$fieldName => ['$gt' => $value]];
				case DataFilter::LessThan:
					return [$fieldName => ['$lt' => $value]];
				case DataFilter::GreaterOrEqual:
					return [$fieldName => ['$gte' => $value]];
				case DataFilter::LessOrEqual:
					return [$fieldName => ['$lte' => $value]];
				case DataFilter::IsEmpty:
					return ['$or' => [[$fieldName => ['$ne' => '']], [$fieldName => ['$ne' => null]], [$fieldName => ['$exists' => false]]]];
				case DataFilter::IsNull:
					return [$fieldName => null];
				case DataFilter::NotEmpty:
					return ['$and' => [[$fieldName => ['$ne' => '']], [$fieldName => ['$ne' => null]], [$fieldName => ['$exists' => true]]]];
				case DataFilter::NotNull:
					return [$fieldName => ['$ne' => null]];
				case DataFilter::InList:
					return [$fieldName => ['$in' => $value]];
				case DataFilter::NotInList:
					return [$fieldName => ['$nin' => $value]];
				default:
					$this->logger()->log(Logger::NOTICE, "Unknown condition '$filter->condition'");
					return [md5(rand(0, PHP_INT_MAX)) => md5(rand(0, PHP_INT_MAX))];
			}
		}
		elseif ($filter instanceof DataFilterCollection) {
			$operand = ($filter->operand == DataFilterCollection::OperandOr) ? '$or' : '$and';
			$exprArray = [];
			foreach ($filter->all() as $f) {
				if ($f instanceof DataFilter) {
					$expr = $f->getExpression();
					if (count($expr) > 0) {
						$exprArray[] = $expr;
					}
				} elseif ($f instanceof DataFilterCollection) {
					$exprArray[] = MongoDb::getFilterExpression($f);
				}
			}

			return ($filter->count() > 1) ? [$operand => $exprArray] : $exprArray[0];
		}
		elseif (is_array($filter)) {
			$operand = '$and';
			$exprArray = [];
			foreach ($filter as $f) {
				if ($f instanceof DataFilter) {
					$expr = $f->getExpression();
					if (count($expr) > 0) {
						$exprArray[] = $expr;
					}
				}
				elseif ($f instanceof DataFilterCollection) {
					$exprArray[] = MongoDb::getFilterExpression($f);
				}
			}

			return (count($filter) > 1) ? [$operand => $exprArray] : $exprArray[0];
		}
		else {
			throw new \InvalidArgumentException();
		}
	}

	/** @inheritdoc */
	public function getSortingExpression(array|DataSorting|DataSortingCollection $sorting) {
		if ($sorting instanceof DataSorting) {
			$cName = $sorting->column->isExpression ? $sorting->column->tag : $sorting->column->name;
			return [$cName => (($sorting->mode == DataSorting::Descending) ? -1 : 1)];
		}
		elseif ($sorting instanceof DataSortingCollection) {
			$ret = [];

			foreach ($sorting->all() as $s) {
				$expr = $s->getExpression();
				if (count($expr) > 0)
					$ret[key($expr)] = $expr[key($expr)];
			}

			return $ret;
		}
		else {
			throw new \InvalidArgumentException();
		}
	}

	/** @inheritdoc */
	public function getRelationExpression(DataRelationCollection|DataRelation $relation, $mode = DataRelation::ExprParentJoined) {
		if ($relation instanceof DataRelation) {
			return [];
		} else {
			$expr = [];

			$relation->sort();

			foreach ($relation->all() as $r) {
				$expr[] = $r->getExpression();
			}

			return $expr;
		}
	}

	/** @inheritdoc */
	public function getColumnExpression(DataColumn $column, $prefixTableAlias = false, $suffixColumnAlias = false) {
		return ($column->isExpression) ? $column->name : $column->tag;
	}

	/**
	 * @inheritdoc
	 * @todo Implement MongoDb::getCASEExpression() method.
	 */
	public function getCASEExpression(DataColumn $column, array $keyValues): mixed {
		// TODO: Implement
		return null;
	}

	/** @inheritdoc */
	public function getValueExpression(DataColumn $column, $value = null) {
		switch ($column->dataType) {
			case DataColumn::DataTypeGeoPoint:
				// if value is string, it should be either space or comma separated floating numbers
				if (is_string($value)) {
					$pt = explode(',', $value);
					if (count($pt) == 2) {
						// Comma-separated, ensure they are floats
						if (is_numeric($pt[0]) && is_numeric($pt[1]))
							return [
								'type' => 'Point',
								'coordinates' => [(float)$pt[0], (float)$pt[1]]
							];
						else
							return null;
					}
					else {
						$pt = explode(' ', $value);
						if (count($pt) == 2) {
							// Space-separated, ensure they are floats
							if (is_numeric($pt[0]) && is_numeric($pt[1]))
								return [
									'type' => 'Point',
									'coordinates' => [(float)$pt[0], (float)$pt[1]]
								];
							else
								return null;
						}
					}
				}
				// If value is object, it should have lat/lng properties
				elseif ($value instanceof \stdClass || is_object($value)) {
					if (isset($value->lat) && isset($value->lng))
						return [
							'type' => 'Point',
							'coordinates' => [(float)$value->lat, (float)$value->lng]
						];
					else
						return null;
				}

				// Fallback
				return $value;

			default:
				return $value;
		}
	}
	#endregion

	#region Misc. methods
	/** @deprecated Not applicable in MongoDb driver */
	public function getInsertID() {
		// Not used in Mongo driver
		return false;
	}

	/**
	 * @deprecated Not used in MongoDb driver
	 * @inheritdoc
	 */
	public function getLastError($forceDbError = false): EventStatus {
		return new EventStatus();
	}

	/**
	 * Returns the text of the error message of the previous command execution
	 *
	 * @return string The error message
	 */
	public function getLastErrorMessage() {
		return $this->lastError instanceof EventStatus ? $this->lastError->message : '';
	}

	/**
	 * @deprecated Not applicable in MongoDb driver
	 * @inheritdoc
	 */
	public function escape(array|string $str, bool $allowHtml = true): array|string {
		// Not used in Mongo driver
		return $str;
	}

	/**
	 * @deprecated Not applicable in MongoDb driver
	 * @inheritdoc
	 */
	public function quote(array|string $str): array|string {
		// Not used in Mongo driver
		return $str;
	}

	public function usesTablePrefixInQueries(): bool {
		return false;
	}

	/**
	 * @param string $name
	 * @return \MongoDB\Collection
	 */
	public function collection(string $name): \MongoDB\Collection {
		return $this->db()->selectCollection($name);
	}

	public function getDateNativeFormat($includeTime = true): string {
		return ($includeTime) ? 'Y-m-d H:i:s' : 'Y-m-d';
	}

	public function getTimeNativeFormat(): string {
		return 'H:i:s';
	}
	#endregion

	#region Protected methods
	protected function linkFrom(Database $db) {
		if ($db instanceof MongoDb) {
			$this->_link = $db->_link;
			$this->_db = $db->_db;
		}
	}
	#endregion
	#endregion
}

MongoDb::init();
