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
 * Portions created by Paschalis Ch. Pagonidis are Copyright (C) 2007-2022
 * Paschalis Ch. Pagonidis. All Rights Reserved.
 */

namespace core\Core\Utils;

require_once (__DIR__ . '/../../../../aneya.php');

use aneya\Core\Utils\ObjectUtils;
use PHPUnit\Framework\TestCase;


/**
 * Class ObjectUtilsTest
 *
 * @covers ObjectUtils
 */
class ObjectUtilsTest extends TestCase {
	public $obj;

	protected function setUp() {
		$this->obj = new \stdClass();

		$this->obj->contact = new \stdClass();
		$this->obj->contact->address = new \stdClass();

		$this->obj->type = 'contact';
		$this->obj->contact->firstName = 'First';
		$this->obj->contact->lastName = 'Last';
		$this->obj->contact->address->city = 'Athens';
		$this->obj->contact->address->zipCode = 123456;
		$this->obj->contact->address->countryCode = 'GR';
    }

	public function testGetProperty() {
		$val = ObjectUtils::getProperty($this->obj, 'type');
		$this->assertEquals('contact', $val);

		$val = ObjectUtils::getProperty($this->obj, 'contact');
		$this->assertTrue(is_object($val));

		$val = ObjectUtils::getProperty($this->obj, 'contact.firstName');
		$this->assertEquals('First', $val);

		$val = ObjectUtils::getProperty($this->obj, 'contact.address.zipCode');
		$this->assertEquals(123456, $val);
	}

	public function testSetProperty() {
		ObjectUtils::setProperty($this->obj, 'type', 'account');
		$val = ObjectUtils::getProperty($this->obj, 'type');
		$this->assertEquals('account', $val);

		ObjectUtils::setProperty($this->obj, 'type', 'contact', false);
		$val = ObjectUtils::getProperty($this->obj, 'type');
		$this->assertNotEquals('contact', $val);

		ObjectUtils::setProperty($this->obj, 'contact.address.zipCode', 567890);
		$val = ObjectUtils::getProperty($this->obj, 'contact.address.zipCode');
		$this->assertEquals(567890, $val);


		$val = ObjectUtils::getProperty($this->obj, 'does.not.exist.so.far');
		$this->assertNull($val);

		ObjectUtils::setProperty($this->obj, 'does.not.exist.so.far', 'yes');
		$val = ObjectUtils::getProperty($this->obj, 'does.not.exist.so.far');
		$this->assertEquals('yes', $val);
	}
}
