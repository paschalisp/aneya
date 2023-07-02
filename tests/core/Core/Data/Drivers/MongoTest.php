<?php
require_once (__DIR__ . '/../../../../../aneya.php');

use aneya\Core\CMS;
use aneya\Core\Data\DataColumn;
use aneya\Core\Data\DataColumnCollection;
use aneya\Core\Data\DataTable;
use aneya\Core\Data\Drivers\MongoDb;
use PHPUnit\Framework\TestCase;

class MongoTest extends TestCase {
	private MongoDb $db;

	/**
	 * @return DataTable
	 * @throws Exception
	 */
	public function test_Start() {
		$this->db = CMS::db('mongo');

		$columns = new DataColumnCollection(array (
			new DataColumn('id', DataColumn::DataTypeInteger),
			new DataColumn('firstName', DataColumn::DataTypeString),
			new DataColumn('lastName', DataColumn::DataTypeString),
			new DataColumn('email', DataColumn::DataTypeString),
			new DataColumn('userName', DataColumn::DataTypeString),
			new DataColumn('status', DataColumn::DataTypeInteger),
			new DataColumn('internalVar', DataColumn::DataTypeString)
		));

		$dt = new DataTable(null, $columns, $this->db);
		$dt->name = 'MongoTest';

		$this->assertTrue(true);

		return $dt;
	}

	/**
	 * @depends test_Start
	 * @param DataTable $ds
	 * @return DataTable
	 */
	public function test_Insert(DataTable $ds) {
		$row = $ds->newRow ();
		$row->setValue('id', 1);
		$row->setValue('firstName', 'Foo');
		$row->setValue('lastName', 'Bar');
		$row->setValue('email', 'foo@example.com');
		$row->setValue('userName', 'foo');
		$row->setValue('status', 2);
		$row->setValue('internalVar', 'test');

		$ret = $ds->save();
		$this->assertEquals(true, $ret->isOK());

		return $ds;
	}

	/**
	 * @depends test_Insert
	 * @param DataTable $ds
	 * @return DataTable
	 */
	public function test_Fetch(DataTable $ds) {
		$ds->rows->clear();
		$ds->retrieve();
		$this->assertCount(1, $ds->rows->all());
		$row = $ds->rows->first();
		$this->assertEquals('Foo', $row->getValue('firstName'));
		$this->assertEquals('Bar', $row->getValue('lastName'));
		$this->assertEquals('foo@example.com', $row->getValue('email'));
		$this->assertEquals('foo', $row->getValue('userName'));
		$this->assertEquals(2, $row->getValue('status'));
		// TODO: Check multilingual columns

		return $ds;
	}

	/**
	 * @depends test_Fetch
	 * @param DataTable $ds
	 * @return DataTable
	 */
	public function test_Change(DataTable $ds) {
		$row = $ds->rows->first();
		$row->setValue('firstName', 'Foo changed...');
		$row->setValue('lastName', 'Bar changed...');
		$this->assertCount(1, $ds->rows->getChanged()->all());

		$ret = $row->save(); // Test saving row directly
		$this->assertEquals(true, $ret->isOK());

		$ds->retrieve();
		$row = $ds->rows->first();
		$this->assertEquals('Foo changed...', $row->getValue ('firstName'));
		$this->assertEquals('Bar changed...', $row->getValue ('lastName'));

		return $ds;
	}

	/**
	 * @depends test_Change
	 * @param DataTable $ds
	 */
	public function test_Delete(DataTable $ds) {
		foreach ($ds->rows->all() as $row)
			$row->delete();

		$ret = $ds->save();
		$this->assertEquals(true, $ret->isOK());
		$ds->retrieve();
		$this->assertCount(0, $ds->rows->all());
	}
}

class testClass implements \aneya\Core\IStorable {
	use \aneya\Core\Storable;

	public $id;
	public $firstName;
	public $lastName;
	public $email;
	public $userName;
	public $status;
	public $internalVar;

	protected static $__classProperties = ['deny' => ['internalVar']];

	public function __construct ($id) {
		$this->id = $id;
	}

	public function __classArgs () {
		return array ('id' => 0);
	}

	public function __classVersion () {
		return 1.1;
	}
}
