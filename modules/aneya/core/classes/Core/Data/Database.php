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
use aneya\Core\CoreObject;
use aneya\Core\Data\Schema\Schema;
use aneya\Core\EventStatus;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;

abstract class Database extends CoreObject {
	#region Constants
	const DB2      = 'DB2';
	const Firebird = 'Firebird';
	const MongoDb  = 'MongoDb';
	const MSSQL    = 'MSSQL';
	const MySQL    = 'MySQL';
	const Oracle   = 'Oracle';
	const PostgreSQL = 'PostgreSQL';
	const SQLite   = 'SQLite';

	const EventOnError    = 'OnError';
	const EventOnExecuted = 'OnExecuted';

	/** @var string[] */
	const SupportedDataTypes = [];
	#endregion

	#region Properties
	public ?EventStatus $lastError = null;
	public Schema $schema;
	/** @var string The framework's schema tag used to establish this database connection */
	public string $tag = '';
	public readonly string $quoteChar;
	public ?\DateTimeZone $timezone = null;
	public ConnectionOptions $options;

	protected $_type = null;
	/** @var mixed */
	protected $_link;

	/** @var int Fetch mode. Acceptable values from \PDO::FETCH_* constants */
	protected int $_fetchMode = \PDO::FETCH_ASSOC;

	protected array $_savePoints = [];

	/** @var string[] Stores all queries executed in the Database */
	protected array $_queries = [];

	/** @var Logger */
	protected Logger $_logger;

	/** @var EventStatus[] Stores all error statuses that occurred in the Database */
	protected array $_errors = [];

	protected bool $_supportsJoins = true;

	/** @var string Quotation character */
	protected string $_quote = '';
	#endregion

	#region Construction
	public function __construct() {}
	#endregion

	#region Methods
	#region Connection methods
	/**
	 * Returns a Database instance of the given type
	 *
	 * @param string $type
	 * @param ?string $tag
	 *
	 * @throws \Exception
	 * @return Database
	 */
	public static function load(string $type, string $tag = null): Database {
		if (in_array($type, array (self::DB2, self::Firebird, self::MongoDb, self::MSSQL, self::MySQL, self::Oracle, self::PostgreSQL, self::SQLite))) {
			$class = "aneya\\Core\\Data\\Drivers\\$type";

			$db = new $class();
			$db->schemaTag = $tag;

			try {
				$db->_logger = new Logger("sql.$db->schemaTag");
				$db->_logger->pushHandler(new StreamHandler(CMS::appPath() . "/logs/sql.$db->schemaTag.log", CMS::cfg()->env->debugging ? Logger::DEBUG : Logger::INFO));
			}
			catch (\Exception $e) {}

			return $db;
		}
		else {
			throw new \Exception ("DBMS '$type' is not supported");
		}
	}

	/**
	 * Connects to the database server
	 *
	 * @param ?ConnectionOptions $options
	 *
	 * @triggers OnConnected
	 */
	public abstract function connect(ConnectionOptions $options = null);

	/**
	 * Reconnect to the database.
	 * The function does nothing if a connection is already established.
	 *
	 * @return mixed
	 */
	public abstract function reconnect(): mixed;

	/**
	 * Disconnects from the database server
	 *
	 * @triggers OnDisconnected
	 */
	public abstract function disconnect();

	/**
	 * Returns true if there is active connection to the database server
	 *
	 * @return boolean
	 */
	public function isConnected(): bool {
		return (isset ($this->_link));
	}

	/**
	 * Parses vendor-related database configuration and returns a corresponding ConnectionOptions object,
	 * ready to be used to connect to the database.
	 *
	 * @param \stdClass $cfg
	 *
	 * @return ConnectionOptions
	 */
	public abstract function parseCfg(\stdClass $cfg): ConnectionOptions;

	protected abstract function linkFrom(Database $db);

	protected abstract function getConnectionString();
	#endregion

	#region Command execution methods
	/**
	 * Executes a query and returns the number of rows that were affected.
	 *
	 * @param string $query
	 * @param ?array $params Parameters to used in the prepared statement before executing the command
	 *
	 * @return bool|int
	 * @triggers OnExec, OnError
	 */
	public abstract function exec(string $query, array $params = null): bool|int;

	/**
	 * Executes a query or prepared statement.
	 *
	 * If the statement is string, then it gets automatically prepared and then gets executed passing the parameters, if provided in the second argument.
	 *
	 * @param object|string $statement The command to execute or a prepared statement.
	 * @param array|null $params    Parameters to used in the prepared statement before executing the command
	 *
	 * @return bool|mixed Return value depends on the DBMS and the command statement or false on error
	 * @triggers OnExecute, OnCommand, OnError
	 */
	public abstract function execute(object|string $statement, array $params = null): mixed;

	/**
	 * Prepares a query for execution and returns the prepared statement.
	 *
	 * @param string $query
	 * @param array $options Driver options (optional)
	 *
	 * @return mixed
	 * @triggers OnError
	 */
	public abstract function prepare(string $query, array $options = []): mixed;
	#endregion

	#region Data retrieval methods
	/***
	 * @param object|string $statement The prepared statement or query to fetch results from
	 * @param array|null $params If statement is string, $params will be passed when executing the statement
	 * @param int|null $start If statement is string, $start will be used to fetch results starting from this value
	 * @param int|null $limit If statement is string, $limit will be used to limit the results of the query
	 * @param int|null $fetchMode A fetch mode, one of the PDO::FETCH_* constants
	 * @param mixed|null $arguments Parameters to be passed when preparing the statement, in case the statement provided was a string
	 *
	 * @return mixed
	 */
	public abstract function fetchAll(object|string $statement, array $params = null, int $start = null, int $limit = null, int $fetchMode = null, mixed $arguments = null): mixed;

	/**
	 * Fetches the next row from the specified statement.
	 *
	 * If statement is string, then a prepared statement is generated automatically, using the parameters passed in the third argument.
	 *
	 * @param object|string $statement A prepared statement or query string
	 * @param array|null $params If statement is string, $params will be passed when executing the statement
	 * @param int|null $fetchMode A fetch mode, one of the PDO::FETCH_* constants.
	 *
	 * @return mixed
	 */
	public abstract function fetch(object|string $statement, array $params = null, int $fetchMode = null): mixed;

	/**
	 * Fetches the next row from the specified statement and returns the given column's value.
	 *
	 * If statement is string, then a prepared statement is generated automatically, using the parameters passed in the third argument.
	 *
	 * @param object|string $statement  A prepared statement or query string
	 * @param string $columnName The column name which value is to return
	 * @param array|null $params     If statement is string, $params will be passed when executing the statement
	 *
	 * @return mixed
	 */
	public abstract function fetchColumn(object|string $statement, string $columnName, array $params = null): mixed;

	/**
	 * Sets the default fetch mode when executing queries.
	 *
	 * @param int $fetchMode A fetch mode, one of the PDO::FETCH_* constants.
	 */
	public function setFetchMode(int $fetchMode) {
		$this->_fetchMode = $fetchMode;
	}
	#endregion

	#region DataSet methods
	/**
	 * Retrieves rows from database
	 *
	 * @param DataTable                                       $parent
	 * @param DataTableCollection                             $tables
	 * @param DataRelationCollection                          $relations
	 * @param DataColumnCollection                            $columns
	 * @param DataColumnCollection                            $listColumns
	 * @param DataFilterCollection|DataFilter|DataFilter[]    $filters
	 * @param DataSortingCollection|DataSorting|DataSorting[] $sorting
	 * @param DataColumnCollection|DataColumn                 $grouping
	 * @param DataFilterCollection|DataFilter|DataFilter[]    $having
	 * @param int                                             $start
	 * @param int                                             $limit
	 *
	 * @return int|bool The number of rows that were retrieved or false if query failed
	 */
	public abstract function retrieve(DataTable $parent, DataTableCollection $tables, DataRelationCollection $relations, DataColumnCollection $columns, DataColumnCollection $listColumns, $filters = null, $sorting = null, $grouping = null, $having = null, $start = null, $limit = null): bool|int;

	/**
	 * Retrieves the count of rows from database that match the tables' relation and filters
	 *
	 * @param DataTable                                    $parent
	 * @param DataTableCollection                          $tables
	 * @param DataRelationCollection                       $relations
	 * @param DataFilterCollection|DataFilter|DataFilter[] $filters
	 * @param DataColumnCollection|DataColumn              $grouping
	 * @param DataFilterCollection|DataFilter|DataFilter[] $having
	 *
	 * @return int The number of rows that match the tables' relation and filters
	 */
	public abstract function retrieveCnt(DataTable $parent, DataTableCollection $tables, DataRelationCollection $relations, $filters = null, $grouping = null, $having = null): int;

	/**
	 * Generates the database retrieval query and returns it without executing it.
	 *
	 * @param DataTable $parent
	 * @param DataTableCollection $tables
	 * @param DataRelationCollection $relations
	 * @param DataColumnCollection $columns
	 * @param DataColumnCollection $listColumns
	 * @param DataFilterCollection|DataFilter[]|DataFilter|null $filters
	 * @param DataSorting|DataSortingCollection|DataSorting[]|null $sorting
	 * @param DataColumn|DataColumnCollection|DataColumn[]|null $grouping
	 * @param DataFilterCollection|DataFilter|DataFilter[]|null $having
	 * @param ?int $start
	 * @param ?int $limit
	 *
	 * @return mixed
	 */
	public abstract function retrieveQuery(DataTable $parent, DataTableCollection $tables, DataRelationCollection $relations, DataColumnCollection $columns, DataColumnCollection $listColumns, DataFilterCollection|DataFilter|array $filters = null, DataSorting|DataSortingCollection|array $sorting = null, DataColumn|DataColumnCollection|array $grouping = null, DataFilterCollection|DataFilter|array $having = null, int $start = null, int $limit = null): mixed;

	/**
	 * Saves changes back to database
	 *
	 * @param DataRow $row
	 *
	 * @return EventStatus
	 */
	public abstract function save(DataRow $row): EventStatus;

	/**
	 * Deletes rows from database
	 *
	 * @param DataTable                                    $parent
	 * @param DataFilter|DataFilter[]|DataFilterCollection $filters
	 *
	 * @return EventStatus
	 *
	 * @throws \InvalidArgumentException
	 */
	public abstract function delete(DataTable $parent, DataFilterCollection|DataFilter|array $filters): EventStatus;
	#endregion

	#region Transaction methods
	/**
	 * Starts a transaction
	 * Warning: Not all DBMSes support transactions
	 *
	 * @param string|null $name The name of the transaction to start
	 */
	public abstract function beginTransaction(string $name = null): bool|int;

	/**
	 * Commits a transaction's changes to the database
	 * Warning: Not all DBMSes support transactions
	 *
	 * @param string|null $name The name of the transaction to commit
	 */
	public abstract function commit(string $name = null): bool|int;

	/**
	 * Rolls back a transaction's changes
	 * Warning: Not all DBMSes support transactions
	 *
	 * @param string|null $name The name of the transaction to rollback
	 */
	public abstract function rollback(string $name = null);

	/**
	 * Returns the nesting level of the current transaction
	 * Warning: Not all DBMSes support transactions
	 *
	 * @return int
	 */
	public function getTransactionLevel(): int {
		return count($this->_savePoints) - 1;
	}

	public abstract function inTransaction(): bool;
	#endregion

	#region Database methods
	/** Returns database's driver type (one of the Database::* driver constants) */
	public function getDriverType(): string {
		return $this->_type ?? '';
	}

	/** Returns the database name of the instantiated DBMS object */
	public function getDatabaseName(): string {
		return $this->options->database;
	}

	/** Returns the schema name of the instantiated DBMS object */
	public function getSchemaName(): string {
		return $this->options->schema;
	}

	/** Returns true if the DBMS supports joins between tables of the same connection */
	public function supportsJoins(): bool {
		return $this->_supportsJoins;
	}
	#endregion

	#region Expression methods
	/**
	 * Returns a unified filtering expression for the given filter(s), compatible to the database's syntax
	 *
	 * @param DataFilter|DataFilter[]|DataFilterCollection $filter
	 *
	 * @return mixed
	 * @throws \InvalidArgumentException
	 */
	public abstract function getFilterExpression(DataFilterCollection|DataFilter|array $filter): mixed;

	/**
	 * Returns a unified sorting expression for the given sorting, compatible to the database's syntax
	 *
	 * @param DataSorting|DataSorting[]|DataSortingCollection $sorting
	 *
	 * @return mixed
	 * @throws \InvalidArgumentException
	 */
	public abstract function getSortingExpression(array|DataSorting|DataSortingCollection $sorting): mixed;

	/**
	 * Returns a unified join expression for the given table relation(s), compatible to the database's syntax
	 *
	 * @param DataRelation|DataRelationCollection $relation
	 * @param string $mode Join expression mode. Valid values are DataRelation::Expr* constants
	 *
	 * @return mixed
	 */
	public abstract function getRelationExpression(DataRelationCollection|DataRelation $relation, string $mode = DataRelation::ExprParentJoined): mixed;

	/**
	 * Returns the column's name expression as it is found in the database, depending on column's data type.
	 *
	 * @param DataColumn $column
	 * @param bool $prefixTableAlias
	 * @param bool $suffixColumnAlias
	 *
	 * @return string|mixed
	 */
	public abstract function getColumnExpression(DataColumn $column, bool $prefixTableAlias = false, bool $suffixColumnAlias = false): mixed;

	/**
	 * Returns column's value expression, ready to be used in INSERT/UPDATE statements.
	 *
	 * @param DataColumn $column
	 * @param mixed|null $value
	 *
	 * @return string|mixed
	 */
	public abstract function getValueExpression(DataColumn $column, mixed $value = null): mixed;

	/**
	 * Returns a CASE or equivalent statement of the given pair of key/values, native to database's syntax
	 *
	 * @param DataColumn $column
	 * @param array $keyValues
	 *
	 * @return mixed
	 */
	public abstract function getCASEExpression(DataColumn $column, array $keyValues): mixed;
	#endregion

	#region Misc. methods
	/**
	 * Returns the text of the error information of the previous command execution
	 *
	 * @param bool $forceDbError Forces the retrieval of error information from the database connection.
	 *
	 * @return EventStatus
	 */
	protected abstract function getLastError(bool $forceDbError = false): EventStatus;

	/**
	 * Returns the auto-increment value generated by the last INSERT query
	 * Warning: Not all DBMSes support this function
	 *
	 * @return mixed
	 */
	public abstract function getInsertID(): mixed;

	/**
	 * Escapes the special characters in a string for use in a query
	 *
	 * @param array|string $str  The string or array to escape
	 * @param boolean $allowHtml Whether to convert special characters to HTML entities
	 *
	 * @return string|array The escaped string or array
	 */
	public abstract function escape(array|string $str, bool $allowHtml = true): array|string;

	/**
	 * Return's database's default logger
	 * @return Logger
	 */
	public function logger(): Logger {
		return $this->_logger;
	}

	/**
	 * Quotes the string for use in a query
	 *
	 * @param array|string $data The string or associative array whose values to escape
	 *
	 * @return string|array The quoted string or associative array
	 */
	public abstract function quote(array|string $data): array|string;

	/**
	 * Logs a query into an internal list for debugging purposes.
	 *
	 * @param string $query    The query to store
	 * @param float $fromTime Use microtime(true) to set its value just before the query's execution
	 * @param float|null $toTime   (optional) If omitted, microtime(true) will be automatically used to generate the stop time
	 */
	protected function trace(string $query, float $fromTime, float $toTime = null) {
		if ($toTime == null)
			$toTime = microtime(true);

		$this->_queries[] = array ('time' => date('H:i:s.u'), 'duration' => ((float)$toTime - (float)$fromTime), 'query' => (string)$query);
	}

	/**
	 * Returns all executed queries in this Database in a associative array of type: { time, duration, query }
	 * Warning: This function should be used for debugging purposes only as sensitive information might be included in the results.
	 *
	 * @return array
	 */
	public function getExecutedQueries(): array {
		return $this->_queries;
	}

	/**
	 * Returns true if this DBMS uses table's name or alias as a prefix to accompany field names.
	 *
	 * @return bool
	 */
	public abstract function usesTablePrefixInQueries(): bool;

	/**
	 * Returns the native format string (compatible by \DateTime::format() function) that the RDBMS can use to convert DateTime objects and store to native date/time values.
	 *
	 * @param bool $includeTime
	 *
	 * @return string
	 */
	public abstract function getDateNativeFormat(bool $includeTime = true): string;

	/**
	 * Returns the native format string (compatible by \DateTime::format() function) that the RDBMS can use to convert DateTime objects and store to native time values.
	 *
	 * @return string
	 */
	public abstract function getTimeNativeFormat(): string;
	#endregion
	#endregion

	#region Magic methods
	public function __sleep() {
		return ['schemaTag', 'schema', '_connString', '_type', '_server', '_port', '_options', '_dbName', '_username', '_password', '_encoding', '_fetchMode', '_supportsJoins', '_quote', '_timezone', 'timezone'];
	}

	public function __wakeup() {
		if (strlen($this->tag) > 0) {
			$db = CMS::db($this->tag);

			try {
				$this->_logger = new Logger("sql.$this->tag");
				$this->_logger->pushHandler(new StreamHandler(CMS::appPath() . "/logs/sql.$this->tag.log", CMS::cfg()->env->debugging ? Logger::DEBUG : Logger::INFO));
			}
			catch (\Exception $e) {}
		}

		// If a connection link has already been found in the framework, use it to avoid transaction conflicts
		if (isset ($db)) {
			$this->_link = $db->_link;
			$this->linkFrom($db);
		}

		if (!$this->isConnected()) {
			$this->reconnect();
		}
	}

	public function __toString() { return $this->options->schema; }
	#endregion
}
