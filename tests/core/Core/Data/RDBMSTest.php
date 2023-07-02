<?php
require_once (__DIR__ . '/../../../../aneya.php');

use aneya\Core\CMS;
use aneya\Core\Data\DataBase;
use aneya\Core\Data\DataColumn;
use aneya\Core\Data\DataFilter;
use aneya\Core\Data\DataFilterCollection;
use aneya\Core\Data\DataRelation;
use aneya\Core\Data\DataSet;
use aneya\Core\Data\DataSorting;
use aneya\Core\Data\DataSortingCollection;
use aneya\Core\Data\DataTable;
use aneya\Core\I18N;
use PHPUnit\Framework\TestCase;

class RDBMSTest extends TestCase {
	public function __construct () {
		parent::__construct();

		// Switch to English
		I18N::setLanguageCode('en');
	}

	#region DataTable tests
	/**
	 * @return DataTable
	 * @throws Exception
	 */
	public function test_DataTable_Start () {
		$dt = new DataTable ();
		$dt->name = 'cms_forms';
		$dt->alias = 'T1';
		$dt->db(CMS::db ('test'));
		$c0 = $dt->columns->add (new DataColumn ('form_id', DataColumn::DataTypeInteger));
		$dt->columns->add (new DataColumn ('schema_id', DataColumn::DataTypeInteger));
		$c2 = $dt->columns->add (new DataColumn ('tag'));
		$dt->columns->add (new DataColumn ('table_name'));
		$dt->columns->add (new DataColumn ('table_alias'));
		$c0->isKey = true;

		$this->assertEquals($c2, $dt->columns->itemAt(2));
		$this->assertCount(5, $dt->columns->all());

		return $dt;
	}

	#region Retrieve
	/**
	 * @depends test_DataTable_Start
	 * @param DataTable $dt
	 * @return DataTable
	 */
	public function test_DataTable_Retrieve (DataTable $dt) {
		$num = $dt->retrieve ();
		$this->assertGreaterThan (0, $num);

		// Retrieve with filter & sorting
		$filters = new DataFilterCollection();

		// Filter by primary key, so result is 1 ('cms_forms')
		$c0 = $dt->columns->get('form_id');
		$filter = $filters->add (new DataFilter ($c0, DataFilter::Equals, 1));
		$num = $dt->retrieve ($filters);

		$this->assertEquals (1, $num);
		$row = $dt->rows->first();
		$this->assertEquals ('cms_forms', $row->getValue('tag'));

		$filters->remove ($filter);

		// Filter by tag and add sorting too
		$c1 = $dt->columns->get('table_name');
		$filters->add(new DataFilter($c1, DataFilter::StartsWith, 'cms_forms_'));
		$sorting = new DataSortingCollection();
		$sorting->add (new DataSorting ($c1, DataSorting::Descending));
		$dt->retrieve ($filters, $sorting);

		$this->assertCount(7, $dt->rows->all()); // Should be 7 records: cms_forms_tables_keys, cms_forms_tables, cms_forms_sorting, cms_forms_roles, cms_forms_relations_keys, cms_forms_relations, cms_forms_filtering
		$this->assertEquals('cms_forms_filtering', $dt->rows->last()->getValue('table_name'));

		return $dt;
	}
	#endregion

	#region Save
	/**
	 * @depends test_DataTable_Retrieve
	 * @param DataTable $dt
	 * @return DataTable
	 */
	public function test_DataTable_Change (DataTable $dt) {

		// Change a value
		$cId = $dt->columns->itemAt(0);
		$cSchema = $dt->columns->itemAt(1);
		$cAlias = $dt->columns->get ('table_alias');
		$row = $dt->rows->last();
		$this->assertEquals (false, $dt->rows->last()->hasChanged());
		$row->setValue ($cAlias, 'T2');
		$this->assertEquals (true, $dt->rows->last()->hasChanged());

		// Create errors
		$formId = $row->getValue ($cId);
		$schemaId = $row->getValue ($cSchema);
		$this->assertEquals(false, $row->hasErrors());
		$row->setValue($cId, 'test');
		$this->assertEquals(true, $row->hasErrors());
		$row->setValue($cSchema, 'new_schema');
		$this->assertCount(2, $row->status->errors->all());

		// Fix errors
		$row->setValue($cId, $formId);			// Fix first error
		$this->assertCount(1, $row->status->errors->all());
		$row->setValue($cSchema, $schemaId);	// Fix second error
		$this->assertEquals(false, $row->hasErrors(), 'After fixing the invalid values');

		$this->assertCount (1, $dt->rows->getChanged()->all(), 'Number of changed rows');
		$this->assertEquals($row, $dt->rows->getChanged()->first());

		return $dt;
	}

	/**
	 * @depends test_DataTable_Change
	 * @param DataTable $dt
	 */
	public function test_DataTable_Save (DataTable $dt) {
		$status = $dt->save();
		$this->assertEquals(true, $status->isOK(), 'Save succeeded');

		$c1 = $dt->columns->get('table_name');
		$filters = new DataFilterCollection();
		$filters->add (new DataFilter ($c1, DataFilter::Equals, 'cms_forms_filtering'));
		$num = $dt->retrieve ($filters);
		$this->assertEquals(1, $num);

		$row = $dt->rows->first();
		$this->assertEquals ('T2', $row->getValue('table_alias'), "Table's alias should be T2 after saving the table");

		// Put back the old value and save directly the row
		$row->setValue ('table_alias', 'T1');
		$ret = $row->save ();
		$this->assertEquals(true, $ret->isOK());

		// Fetch again the data
		$num = $dt->retrieve ($filters);
		$this->assertEquals(1, $num);

		$row = $dt->rows->first();
		$this->assertEquals ('T1', $row->getValue('table_alias'), "Table's alias should now be again T1");
	}
	#endregion
	#endregion

	#region DataTableTr tests
	/**
	 * @return DataTable
	 * @throws Exception
	 */
	public function test_DataTableTr_Start () {
		$dt = new DataTable ();
		$dt->name = 'test_i18n';
		$dt->alias = 'T1';
		$dt->db (CMS::db ('test'));
		$c0 = $dt->columns->add (new DataColumn ('id', DataColumn::DataTypeInteger));
		$dt->columns->add (new DataColumn ('tag'));
		$c2 = $dt->columns->add (new DataColumn ('title'));			// Introduce a new, multilingual column
		$c3 = $dt->columns->add (new DataColumn ('description'));	// Introduce a new, multilingual column
		$c0->isKey = true;
		$c0->isAutoIncrement = true;
		$c2->isMultilingual = true;
		$c3->isMultilingual = true;

		$this->assertEquals(true, true);
		return $dt;
	}

	#region Retrieve
	/**
	 * @depends test_DataTableTr_Start
	 * @param DataTable $dt
	 * @return DataTable
	 */
	public function test_DataTableTr_Insert (DataTable $dt) {
		$row = $dt->newRow();
		$row->setValue('tag', 'test');
		$row->setValue('title', array('el' => 'Τίτλος', 'en' => 'Title'));
		$row->setValue('description', 'test...');
		$status = $dt->save();
		$this->assertEquals(true, $status->isOK());

		return $dt;
	}
	#endregion

	#region Retrieve
	/**
	 * @depends test_DataTableTr_Insert
	 * @param DataTable $dt
	 * @return DataTable
	 */
	public function test_DataTableTr_Retrieve (DataTable $dt) {
		$dt->retrieve ();

		$this->assertCount(1, $dt->rows->all());
		$this->assertEquals('test', $dt->rows->first()->getValue('tag'));
//		$this->assertEquals('Τίτλος', $dt->rows->first()->getValue('title', 'el'));
		$this->assertEquals('test...', $dt->rows->first()->getValue('description'));

		return $dt;
	}
	#endregion

	#region Save
	/**
	 * @depends test_DataTableTr_Retrieve
	 * @param DataTable $dt
	 * @return DataTable
	 */
	public function test_DataTableTr_Change (DataTable $dt) {

		// Change a value
		$cTag = $dt->columns->get ('tag');
		$cTitle = $dt->columns->get ('title');
		$row = $dt->rows->last();
		$this->assertEquals (false, $dt->rows->last()->hasChanged());
		$row->setValue ($cTag, 'test2');
		$this->assertEquals (true, $dt->rows->last()->hasChanged());

		$row->setValue ($cTitle, 'Title2');
		$title = $row->getValue ($cTitle);
		$this->assertEquals ('Title2', $title);

		$this->assertCount (1, $dt->rows->getChanged()->all(), 'Number of changed rows');
		$this->assertEquals($row, $dt->rows->getChanged()->first());

		return $dt;
	}

	/**
	 * @depends test_DataTableTr_Change
	 * @param DataTable $dt
	 * @return DataTable
	 */
	public function test_DataTableTr_Update (DataTable $dt) {
		$status = $dt->save();
		$this->assertEquals(true, $status->isOK(), 'Save succeeded');

		$dt->retrieve();
		$this->assertEquals('test2', $dt->rows->first()->getValue('tag'));
		$this->assertEquals('Title2', $dt->rows->first()->getValue('title'));

		return $dt;
	}

	/**
	 * @depends test_DataTableTr_Update
	 * @param DataTable $dt
	 */
	public function test_DataTableTr_Delete (DataTable $dt) {
		foreach ($dt->rows->all() as $row)
			$row->delete();

		$ret = $dt->save();
		$this->assertEquals(true, $ret->isOK());
		$dt->retrieve();
		$this->assertCount(0, $dt->rows->all());
	}
	#endregion
	#endregion

	#region DataSet tests
	public function test_DataSet_Start() {
		$ds = new DataSet();

		#region Define tables
		$dtM = new DataTable();
		$dtM->name = 'test_ds_master';
		$dtM->alias = 'TM';
		$dtM->db (CMS::db('test'));
		$c = $dtM->columns->add(new DataColumn('row_id', DataColumn::DataTypeInteger, null, 'm_row_id'));
		$c->isKey = true;
		$c->isAutoIncrement = true;
		$dtM->columns->add (new DataColumn('tag', DataColumn::DataTypeString, null, 'tag'));
		$dtM->columns->add (new DataColumn('status', DataColumn::DataTypeInteger, 'status'));

		$dtJ1 = new DataTable();
		$dtJ1->name = 'test_ds_join1';
		$dtJ1->alias = 'TJ1';
		$dtJ1->db (CMS::db('test'));
		$c = $dtJ1->columns->add (new DataColumn('row_id', DataColumn::DataTypeInteger, null, 'j1_row_id'));
		$c->isKey = true;
		$dtJ1->columns->add (new DataColumn('tag', DataColumn::DataTypeString, null, 'j1_tag'));
		$c = $dtJ1->columns->add (new DataColumn('title', DataColumn::DataTypeString, null, 'j1_title'));
		$c->isMultilingual = true;

		$dtJ2 = new DataTable();
		$dtJ2->name = 'test_ds_join2';
		$dtJ2->alias = 'TJ2';
		$dtJ2->db (CMS::db('test'));
		$c = $dtJ2->columns->add (new DataColumn('row_id', DataColumn::DataTypeInteger, null, 'j2_row_id'));
		$c->isKey = true;
		$dtJ2->columns->add (new DataColumn('tag', DataColumn::DataTypeString, null, 'j2_tag'));
		$c = $dtJ2->columns->add (new DataColumn('title', DataColumn::DataTypeString, null, 'j2_title'));
		$c->isMultilingual = true;

		$dtJ3 = new DataTable();
		$dtJ3->name = 'test_ds_join3';
		$dtJ3->alias = 'TJ3';
		$dtJ3->db (CMS::db('test'));
		$c = $dtJ3->columns->add (new DataColumn('row_id', DataColumn::DataTypeInteger, null, 'j3_row_id'));
		$c->isKey = true;
		$dtJ3->columns->add (new DataColumn('tag', DataColumn::DataTypeString, null, 'j3_tag'));
		#endregion

		$ds->tables->add($dtM);
		$ds->tables->add($dtJ1);
		$ds->tables->add($dtJ2);
		$ds->tables->add($dtJ3);

		#region Define relations
		$r = $ds->relations->add(new DataRelation($dtM, $dtJ1, DataRelation::JoinInner, 1));
		$r->isSaveable = true;
		$r->link($dtM->columns->get('m_row_id'), $dtJ1->columns->get('j1_row_id'));

		$r = $ds->relations->add(new DataRelation($dtJ1, $dtJ2, DataRelation::JoinLeft, 2));
		$r->isSaveable = true;
		$r->link($dtJ1->columns->get('j1_row_id'), $dtJ2->columns->get('j2_row_id'));

		$r = $ds->relations->add(new DataRelation($dtM, $dtJ3, DataRelation::JoinLeft, 3));
		$r->isSaveable = true;
		$r->link($dtM->columns->get('m_row_id'), $dtJ3->columns->get('j3_row_id'));
		#endregion

		$this->assertTrue(true);

		return $ds;
	}

	/**
	 * @depends test_DataSet_Start
	 * @param DataSet $ds
	 * @return DataSet
	 */
	public function test_DataSet_Insert (DataSet $ds) {
		$row = $ds->newRow ();
		$row->setValue('tag', 'Master tag...');
		$row->setValue('status', 1);
		$row->setValue('j1_tag', 'Join 1 tag...');
		$row->setValue('j1_title', 'Join 1 title...');
		$row->setValue('j2_tag', 'Join 2 tag...');
		$row->setValue('j2_title', 'Join 2 title...');
		$row->setValue('j3_tag', 'Join 3 tag...');

		$ret = $ds->save();
		$this->assertEquals(true, $ret->isOK(), $ret->debugMessage);

		return $ds;
	}

	/**
	 * @depends test_DataSet_Insert
	 * @param DataSet $ds
	 * @return DataSet
	 */
	public function test_DataSet_Fetch (DataSet $ds) {
		$ds->rows->clear();
		$ds->retrieve();
		$this->assertCount(1, $ds->rows->all());
		$row = $ds->rows->first();
		$this->assertEquals('Join 1 tag...', $row->getValue('j1_tag'));
		$this->assertEquals('Join 2 tag...', $row->getValue('j2_tag'));
		$this->assertEquals('Join 3 tag...', $row->getValue('j3_tag'));
		$this->assertEquals('Join 1 title...', $row->getValue('j1_title')); // Check multilingual column
		$this->assertEquals('Join 2 title...', $row->getValue('j2_title')); // Check multilingual column

		return $ds;
	}

	/**
	 * @depends test_DataSet_Fetch
	 * @param DataSet $ds
	 * @return DataSet
	 */
	public function test_DataSet_Change (DataSet $ds) {
		$row = $ds->rows->first();
		$row->setValue('j1_tag', 'Join 1 tag changed...');
		$row->setValue('j2_title', 'Join 2 title changed...');
		$this->assertCount(1, $ds->rows->getChanged()->all());

		$ret = $row->save(); // Test saving row directly
		$this->assertEquals(true, $ret->isOK());

		$ds->retrieve();
		$row = $ds->rows->first();
		$this->assertEquals('Join 1 tag changed...', $row->getValue ('j1_tag'));
		$this->assertEquals('Join 2 title changed...', $row->getValue ('j2_title'));

		return $ds;
	}

	/**
	 * @depends test_DataSet_Change
	 * @param DataSet $ds
	 */
	public function test_DataSet_Delete (DataSet $ds) {
		foreach ($ds->rows->all() as $row)
			$row->delete();

		$ret = $ds->save();
		$this->assertEquals(true, $ret->isOK());
		$ds->retrieve();
		$this->assertCount(0, $ds->rows->all());
	}
	#endregion

	#region Special fields tests (expression, fake etc...)
	public function test_DataTable_Children() {
		#region Define the tables
		$dtForms = CMS::db()->schema()->getDataSet('cms_forms');
		$dtGroups = CMS::db()->schema()->getDataSet('cms_forms_field_groups');
		$dtFields = CMS::db()->schema()->getDataSet('cms_forms_fields');
		#endregion

		#region Relate the tables
		$rel = $dtForms->children->add(new DataRelation($dtForms, $dtGroups, DataRelation::OneToMany));
		$rel->link($dtForms->columns->get('form_id'), $dtGroups->columns->get('form_id'));

		$rel = $dtGroups->children->add(new DataRelation($dtGroups, $dtFields, DataRelation::OneToMany));
		$rel->link($dtGroups->columns->get('form_id'), $dtFields->columns->get('form_id'));
		$rel->link($dtGroups->columns->get('group_id'), $dtFields->columns->get('group_id'));
		#endregion

		#region Retrieve sample rows
		$filters = new DataFilterCollection();
		$filters->add(new DataFilter($dtForms->columns->get('form_id'), DataFilter::Equals, 1));
		$dtForms->retrieve($filters);

		$dtGroups->retrieve();

		$filters->clear();
		$filters->operand = DataFilterCollection::OperandOr;
		$filters->add(new DataFilter($dtFields->columns->get('form_id'), DataFilter::Equals, 1));
		$filters->add(new DataFilter($dtFields->columns->get('form_id'), DataFilter::Equals, 2));
		$dtFields->retrieve($filters);
		#endregion

		#region Test setting values on linked columns
		$gRows1 = $dtGroups->rows->match(new DataFilter($dtGroups->columns->get('form_id'), DataFilter::Equals, 1));
		$gRows2 = $dtGroups->rows->match(new DataFilter($dtGroups->columns->get('form_id'), DataFilter::Equals, 2));
		$fRows1 = $dtFields->rows->match(new DataFilter($dtFields->columns->get('group_id'), DataFilter::Equals, 1));
		$fRows2 = $dtFields->rows->match(new DataFilter($dtFields->columns->get('group_id'), DataFilter::Equals, 3));

		// Try to change a parent column
		$dtForms->rows->match(new DataFilter($dtForms->columns->get('form_id'), DataFilter::Equals, 1))->first()->setValue('form_id', 999);
		$this->assertEquals(999, $gRows1->first()->getValue('form_id'));
		$this->assertEquals(2, $gRows2->first()->getValue('form_id'));
		$this->assertEquals(999, $fRows1->first()->getValue('form_id'));
		$this->assertEquals(2, $fRows2->first()->getValue('form_id'));

		$dtGroups->rows->match(new DataFilter($dtGroups->columns->get('group_id'), DataFilter::Equals, 1))->first()->setValue('group_id', 888);
		$this->assertEquals(3, $fRows2->first()->getValue('group_id'));
		$this->assertEquals(888, $fRows1->first()->getValue('group_id'));
		#endregion
	}
	#endregion

	#region Prepare, execution & fetching tests
	public function test_Execution_Start () {
		$db = CMS::db('test');

		$this->assertTrue(true);

		return $db;
	}

	/**
	 * @depends test_Execution_Start
	 * @param Database $db
	 * @return Database
	 */
	public function test_Execution_CRUD (Database $db) {
		#region Test Insert
		$this->assertEquals(true, ($db instanceof Database), 'Database should be instantiated');
		$sql = "INSERT INTO cms_log(user_id, date_log, level, message) VALUES (:user_id, now(), :level, :message)";
		/** @var PDOStatement $stmt */
		$stmt = $db->prepare ($sql);
		$null = null;
		$msg = "Test Case: Insert";
		$level = Audit::LOG_DEBUG;
		$stmt->bindParam(':user_id', $null, PDO::PARAM_NULL);
		$stmt->bindParam(':level', $level, PDO::PARAM_INT);
		$stmt->bindParam(':message', $msg, PDO::PARAM_STR);
		$ret = $stmt->execute ();
		$this->assertEquals (true, $ret);
		#endregion

		$id = $db->getInsertID();
		$this->assertGreaterThan(0, $id, 'New log entry\'s Id > 0');

		#region Test Fetch
		$sql = "SELECT * FROM cms_log WHERE log_id=:log_id";
		$rows = $db->fetchAll ($sql, array(':log_id' => $id));
		$this->assertCount(1, $rows);
		$this->assertEquals('Test Case: Insert', $rows[0]['message']);
		#endregion

		#region Test Update
		$sql = "UPDATE cms_log SET message='Test Case: Update' WHERE log_id=:log_id";
		$ret = $db->exec ($sql, array(':log_id' => $id));
		$this->assertEquals(1, $ret);

		$sql = "SELECT * FROM cms_log WHERE log_id=:log_id";
		$row = $db->fetch ($sql, array(':log_id' => $id));
		$this->assertEquals('Test Case: Update', $row['message']);
		$msg = $db->fetchColumn ($sql, 'message', array(':log_id' => $id));
		$this->assertEquals('Test Case: Update', $msg);
		#endregion

		#region Test Delete
		$sql = "DELETE FROM cms_log WHERE log_id=:log_id";
		$ret = $db->exec ($sql, array(':log_id' => $id));
		$this->assertEquals(1, $ret);

		$sql = "SELECT * FROM cms_log WHERE log_id=:log_id";
		$row = $db->fetch ($sql, array(':log_id' => $id));
		$this->assertEquals(false, $row);
		#endregion

		return $db;
	}
	#endregion
}
