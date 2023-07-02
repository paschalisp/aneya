<?php
/*************************************************************************
 * [2021] - [2021] Enfinity Software FZ-LLC. All Rights Reserved.
 * __________________
 * This file is subject to the terms and conditions defined in
 * file 'LICENSE.txt', which is part of this source code package.
 *************************************************************************/

namespace unit\aneya\Core\Data\Drivers;

require_once (__DIR__ . '/../../../../../../aneya.php');

use aneya\Core\CMS;
use aneya\Core\Data\Drivers\PostgreSQL;

class PostgreSQLTest extends \Codeception\Test\Unit {
	protected PostgreSQL $db;
	protected string $_testSchema;
	protected string $_testTable = 'test_table';

	#region Test initialization
	protected function _before() {
		parent::_before();

		$this->db = CMS::db('test_postgres');
		$this->_testSchema = $this->db->getSchemaName();

		$this->db->execute("create table $this->_testSchema.$this->_testTable
				(
					id_col bigserial not null
						constraint test_table_pk
							primary key,
					uuid_col uuid,
					tag varchar(50) not null,
					date_col date,
					date_range_col daterange,
					decimal_col decimal(10,2),
					time_col time without time zone,
					time__tz_col time with time zone,
					timestamp_col timestamp without time zone,
					timestamp_tz_col timestamp with time zone,
					point_col point,
					array_col string[],
					json_col json
				)");

		$this->db->schema->tables(true);
	}

	protected function _after() {
		parent::_after();

		if ($this->db->isConnected())
			$this->db->execute("drop table if exists $this->_testSchema.$this->_testTable");
	}
	#endregion

	#region Connection methods
	public function testParseCfg() {
		/** @var \stdClass $cfg */
		$cfg = CMS::cfg()->db->test_postgres;

		$this->assertInstanceOf('\\stdClass', $cfg);
		$options = $this->db->parseCfg($cfg);
		$this->assertInstanceOf('\\aneya\\Core\\Data\\ConnectionOptions', $options);
		$this->assertAttributeNotEmpty('database', $options);
		$this->assertAttributeNotEmpty('schema', $options);
	}

	public function testConnect() {
		/** @var \stdClass $cfg */
		$cfg = CMS::cfg()->db->test_postgres;

		$ok = $this->db->connect($this->db->parseCfg($cfg));
		$this->assertTrue($ok, 'Not connected');
		$this->assertTrue($this->db->isConnected());
	}

	public function testDisconnect() {
		$this->db->disconnect();
		$this->assertFalse($this->db->isConnected());
	}

	public function testReconnect() {
		$this->db->disconnect();
		$this->assertFalse($this->db->isConnected());

		$this->db->reconnect();
		$this->assertTrue($this->db->isConnected());
	}
	#endregion

	#region Transaction methods
	public function testBeginTransaction() {
		$ok = $this->db->beginTransaction();
		$this->assertNotFalse($ok);
	}

	public function testCommit() {

	}

	public function testRollback() {

	}
	#endregion

	#region DataSet methods
	public function testRetrieveQuery() {

	}

	public function testRetrieveCnt() {

	}

	public function testRetrieve() {

	}
	#endregion

	#region Expression methods
	public function testGetColumnExpression() {

	}

	public function testGetFilterExpression() {

	}

	public function testGetRelationExpression() {

	}

	public function testGetSortingExpression() {

	}

	public function testGetValueExpression() {

	}
	#endregion

	#region Misc. methods
	public function testUsesTablePrefixInQueries() {

	}


	public function testGetDateNativeFormat() {

	}

	public function testGetTimeNativeFormat() {

	}
	#endregion
}
