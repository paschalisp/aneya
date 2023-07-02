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
use aneya\Core\Data\Database;
use aneya\Core\Data\DataColumn;
use Codeception\Test\Unit;

class PostgreSQLSchemaTest extends Unit {
	protected Database $db;
	protected Database $dbTest;
	protected string $_testSchema;
	protected string $_testTable = 'test_table';

	protected function _before() {
		parent::_before();

		$this->db = CMS::db('postgres');
		$this->dbTest = CMS::db('test_postgres');
		$this->_testSchema = $this->dbTest->getSchemaName();

		$this->dbTest->execute("create table $this->_testSchema.$this->_testTable
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
					array_col varchar[],
					json_col json
				)");

		$this->dbTest->schema->tables(true);
	}

	protected function _after() {
		parent::_after();

		if ($this->dbTest->isConnected())
			$this->dbTest->execute("drop table if exists $this->_testSchema.$this->_testTable");
	}

	public function testTables() {
		$tables = $this->db->schema->tables();
		$this->assertGreaterThan(0, count($tables), 'Tables count is zero');

		$ds = $this->db->schema->getDataSet('cms_users');
		$this->assertInstanceOf('\\aneya\\Core\\Data\\DataSet', $ds);
	}

	public function testGetTableByName() {
		$tbl = $this->db->schema->getTableByName('cms_users');
		$this->assertInstanceOf('\\aneya\\Core\\Data\\Schema\\Table', $tbl);
	}

	public function testRelations() {
		$relations = $this->db->schema->relations();
		$this->assertGreaterThan(0, count($relations), 'Relations count is zero');

		$rel = $this->db->schema->getRelationsByMasterField('user_id')[0] ?? null;
		$this->assertGreaterThan(0, count($relations), 'No relations found for column "user_id"');

		$this->assertTrue($rel->masterField === 'user_id', 'Relation master field is not "user_id"');
		$this->assertTrue($rel->masterTable === 'cms_users', 'Relation master table is not "cms_users"');
	}

	public function testGetFields() {
		$cols = $this->db->schema->getFields('cms_users');
		$this->assertGreaterThan(0, count($cols), 'Columns count for table "cms_users" is zero');
		$this->assertInstanceOf('\\aneya\\Core\\Data\\Schema\\Field', $cols['user_id']);
	}

	public function testGetDataSet() {
		$ds = $this->db->schema->getDataSet('cms_users', ['user_id', 'username', 'date_created', 'default_language', 'first_login'], true);
		$this->assertInstanceOf('\\aneya\\Core\\Data\\DataSet', $ds);

		$this->assertInstanceOf('\\aneya\\Core\\Data\\DataColumn', $col = $ds->columns->get('userId'));
		$this->assertTrue($col->dataType === DataColumn::DataTypeInteger);
		$this->assertTrue($col->isKey);
		$this->assertTrue($col->isAutoIncrement);
		$this->assertNull($col->defaultValue, 'Auto-increment column default value is not null');

		$this->assertTrue($ds->columns->get('username')->dataType === DataColumn::DataTypeString);
		$this->assertTrue($ds->columns->get('dateCreated')->dataType === DataColumn::DataTypeDateTime);
		$this->assertTrue($ds->columns->get('firstLogin')->dataType === DataColumn::DataTypeBoolean);
		$this->assertTrue($ds->columns->get('defaultLanguage')->maxLength === 2);
	}

	public function testGetDataSetMultilingual() {
		$ds = $this->db->schema->getDataSet('cms_translations', null, true);
		$this->assertInstanceOf('\\aneya\\Core\\Data\\DataSet', $ds);
		$this->assertInstanceOf('\\aneya\\Core\\Data\\DataColumn', $col = $ds->columns->get('value'));

		$this->assertTrue($col->isMultilingual, 'Translatable column is not multilingual');
	}

	public function testGetDataSetWithJoin() {
		$ds = $this->db->schema->getDataSet(['cms_users', 'cms_users_roles']);
		$col = $ds->columns->get ('user_id', 'Should return the field from the first table');
		$this->assertTrue($col instanceof DataColumn);
		$this->assertTrue($col->table->name == 'cms_users', 'Should return first table\'s name');
		$this->assertTrue($col->tag == 'cms_users_user_id', 'Tag should be prefixed with table name and underscore (_)');

		$col = $ds->columns->get ('cms_users_roles_user_id');
		$this->assertInstanceOf('\\aneya\\Core\\Data\\DataColumn', $col, 'Foreign key with same name should be prefixed by table name in joined tables');

		$col = $ds->columns->get ('role');
		$this->assertInstanceOf('\\aneya\\Core\\Data\\DataColumn', $col, 'Unique field\'s tag should not be changed in joined table');
	}
}
