<?php
require_once (__DIR__ . '/../../../../../aneya.php');

use aneya\Core\CMS;
use aneya\Core\Data\DataFilter;
use aneya\Core\Data\DataSet;
use aneya\Core\Data\ORM\DataObjectMapping;
use aneya\Core\Data\ORM\ORM;
use aneya\Security\User;
use PHPUnit\Framework\TestCase;


class ORMTest extends TestCase {
	/**
	 * @return DataObjectMapping
	 * @throws Exception
	 */
	public function test_ORM_base() {
		$obj = ORM::schemaToObject(CMS::db()->schema(), 'cms_currencies', 'CurrencyTestClass');
		$this->assertTrue($obj instanceof CurrencyTestClass);

		$dom = ORM::schemaToMapping(CMS::db()->schema(), 'cms_currencies', 'CurrencyTestClass');
		$this->assertTrue($dom->hasProperty('currencyCode'));
		$prop = $dom->getProperty('currencyCode');
		$this->assertEquals(true, $prop->column->isKey);

		$ds = CMS::db()->schema()->getDataSet('cms_currencies');
		$row = $ds->newRow();
		$row->setValue('currency_code', 'EUR');
		$row->setValue('currency_name', 'Euro');
		$row->setValue('currency_symbol', '€');

		/** @var CurrencyTestClass $obj */
		$obj = ORM::dataRowToObject($row, 'CurrencyTestClass');
		$this->assertEquals('EUR', $obj->currencyCode);
		$this->assertEquals('Euro', $obj->currencyName);
		$this->assertEquals('€', $obj->currencySymbol);

		return $dom;
	}

	/** @depends test_ORM_base */
	public function test_ORM_objects() {
		$user = new UserTestClass();
		$this->assertTrue($user->orm()->hasProperty('photoUrl'));

		$user->userName = 'test';
		$user->defaultLanguage = 'en';

		$user->location = '12345.45678,98765.43210';
		$user->mobile = '00-111-222-3333';

		#region Test validation
		// Clone the object's DataSet to use for retrieving the test record
		/** @var DataSet $ds */
		$ds = $user->orm()->dataSet();
		$ds = clone $ds;
		$ds->filtering->add(new DataFilter($user->orm()->getProperty('userName')->column, DataFilter::Equals, 'test'));

		$status = $user->save();						// Should fail as dateCreated is required
		$this->assertTrue($status->isError());
		#endregion

		#region Test save
		$user->dateCreated = new DateTime();
		$status = $user->save();						// Should pass now
		$this->assertTrue($status->isOK(), $status->message);
		$this->assertTrue($user->id == $user->orm()->row()->getValue('user_id'));

		$num = $ds->retrieve()->rows->count();
		$this->assertTrue($num === 1);
		#endregion

		#region Test sub-properties
		$user->roles()->add('administrator');
		$user->permissions()->add('admin_manage_administration');	// Already exists in roles, should not be saved
		$user->permissions()->add('admin_manage_development');		// Not included in administrator role

		$status = $user->save();
		$this->assertTrue($status->isOK(), $status->message);

		$sql = 'SELECT permission FROM cms_users_permissions WHERE user_id=:user_id';
		$rows = $user->db()->fetchAll($sql, [':user_id' => $user->id]);
		$this->assertTrue(count($rows) == 1);
		$this->assertTrue($rows[0]['permission'] == 'admin_manage_development');
		#endregion

		#region Test delete
		$user->delete();
		$num = $ds->retrieve()->rows->count();
		$this->assertTrue($num === 0);
		#endregion
	}
}

class CurrencyTestClass {
	public $currencyCode;
	public $currencyName;
	public $currencySymbol;
}

class CurrencyObjTestClass {
	public $currencyCode;
	public $currencyName;
	public $currencySymbol;
}

class UserTestClass extends User {
	protected static $_classORM;

	public $mobile;
	public $location;
	public $country;

	protected static function onORM () {
		$ds = static::classDataSet(CMS::db ()->schema ()->getDataSet('cms_users'));
		$orm = ORM::dataSetToMapping ($ds, get_called_class());

		$orm->getProperty ('username')->propertyName = 'userName';
		$orm->getProperty ('userId')->propertyName = 'id';
		$orm->first ()->properties->remove ($orm->getProperty ('dateDisabled'));

		$orm->getProperty ('password')->column->isRequired = false;
		$orm->getProperty ('firstName')->column->isRequired = false;
		$orm->getProperty ('dateAccessed')->column->isRequired = false;
		$orm->getProperty('status')->column->defaultValue = self::StatusActive;

		return $orm;
	}
}
