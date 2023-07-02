<?php
require_once (__DIR__ . '/../../../aneya.php');

use aneya\Core\Collection;
use aneya\Snippets\Snippet;
use PHPUnit\Framework\TestCase;

class CollectionTest extends TestCase {
	#region Test scalars - not unique
	public function testCreate_ScalarNotUnique () {
		$collection = new Collection('string');
		$this->assertEquals(0, $collection->count());

		return $collection;
	}

	/**
	 * Adds items: item1, item2, item3, item1
	 * @depends testCreate_ScalarNotUnique
	 */
	public function testWrite_ScalarNotUnique (Collection $collection) {
		$str = $collection->add ('item1');
		$this->assertCount(1, $collection->all ());
		$this->assertEquals('item1', $str);

		$collection->add ('item2');
		$collection->add ('item4');
		$collection->add ('item1');

		$this->assertCount (4, $collection->all());

		$collection->insertAt('item0', 0);
		$collection->insertAt('item3', 3);
		$this->assertEquals('item4', $collection->itemAt(4));

		return $collection;
	}

	/**
	 * @depends testWrite_ScalarNotUnique
	 * @param Collection $collection
	 */
	public function testRead_ScalarNotUnique (Collection $collection) {
		$this->assertEquals('item2', $collection->itemAt(2));
		$this->assertEquals(4, $collection->indexOf('item4'));
		$this->assertEquals('item0', $collection->first());
	}

	/**
	 * @depends testCreate_ScalarNotUnique
	 * @@expectedException InvalidArgumentException
	 */
	public function testWrite_ScalarException (Collection $collection) {
		$s = new stdClass();
		$collection->add($s);
	}
	#endregion

	#region Test scalars - unique
	public function testCreate_ScalarUnique () {
		$collection = new Collection('string', true);
		$this->assertEquals(0, $collection->count());

		return $collection;
	}

	/**
	 * Adds items: item1, item2, item3, item1
	 * @depends testCreate_ScalarUnique
	 */
	public function testWrite_ScalarUnique (Collection $collection) {
		$str = $collection->add ('item1');
		$this->assertCount(1, $collection->all ());
		$this->assertEquals('item1', $str);

		$collection->add ('item2');
		$collection->add ('item4');
		$collection->add ('item1');

		$this->assertCount (3, $collection->all());

		$collection->insertAt('item0', 0);
		$collection->insertAt('item3', 3);
		$this->assertEquals('item4', $collection->itemAt(4));

		return $collection;
	}

	/**
	 * @depends testWrite_ScalarUnique
	 * @param Collection $collection
	 */
	public function testRead_ScalarUnique (Collection $collection) {
		$this->assertEquals('item2', $collection->itemAt(2));
		$this->assertEquals(4, $collection->indexOf('item4'));
		$this->assertEquals('item0', $collection->first());
	}
	#endregion

	#region Test objects - not unique
	public function testCreate_ObjNotUnique () {
		$collection = new Collection('\\aneya\\Core\\Snippet');
		$this->assertEquals(0, $collection->count());

		return $collection;
	}

	/**
	 * Adds items: item1, item2, item3, item1
	 * @depends testCreate_ObjNotUnique
	 */
	public function testWrite_ObjNotUnique (Collection $collection) {
		/** @var Snippet $s1 */
		$s1 = $collection->add (new Snippet('item1', 'item1'));
		$this->assertCount(1, $collection->all ());
		$this->assertEquals('item1', $s1->compile());

		$collection->add (new Snippet('item2', 'item2'));
		$collection->add (new Snippet('item4', 'item4'));

		$this->assertCount (3, $collection->all());

		$collection->insertAt(new Snippet('item0', 'item0'), 0);
		$this->assertCount(4, $collection->all());
		$collection->insertAt(new Snippet('item3', 'item3'), 3);
		$this->assertEquals('item4', $collection->itemAt(4)->compile());

		return $collection;
	}

	/**
	 * @depends testCreate_ObjNotUnique
	 * @@expectedException InvalidArgumentException
	 */
	public function testWrite_ObjException (Collection $collection) {
		$s = new stdClass();
		$collection->add($s);
	}

	/**
	 * @depends testWrite_ObjNotUnique
	 * @param Collection $collection
	 */
	public function testRead_ObjNotUnique (Collection $collection) {
		$item2 = $collection->itemAt(2);
		$this->assertEquals('item2', ($item2->compile()));
		$this->assertEquals(2, $collection->indexOf($item2));
		$this->assertEquals('item0', $collection->first()->compile());
	}
	#endregion

	#region Test objects - unique
	public function testCreate_ObjUnique () {
		$collection = new Collection('\aneya\Core\Snippet', true);
		$this->assertEquals(0, $collection->count());

		return $collection;
	}

	/**
	 * Adds items: item1, item2, item3, item1
	 * @depends testCreate_ObjUnique
	 */
	public function testWrite_ObjUnique (Collection $collection) {
		/** @var Snippet $s1 */
		$s1 = $collection->add (new Snippet('item1', 'item1'));
		$this->assertCount(1, $collection->all ());						// idx = 0, cnt = 1
		$this->assertEquals('item1', $s1->compile());

		$collection->add (new Snippet('item2', 'item2'));				// idx = 1, cnt = 2
		$collection->add (new Snippet('itemX', 'itemX'));				// idx = 2, cnt = 3
		$collection->add ($s1);											// cnt = 3

		$item5 = new Snippet('item5', 'item5');

		$this->assertCount (3, $collection->all());

		$collection->insertAt(new Snippet('item0', 'item0'), 0);		// cnt = 4   [item0,item1,item2,itemX]
		$this->assertCount (4, $collection->all());

		$collection->insertAt(new Snippet('item3', 'item3'), 3);		// idx = 3, cnt = 5	[item0,item1,item2,item3,itemX]
		$collection->set(4, $item5);									// [item0,item1,item2,item3,item5]
		$this->assertEquals($item5, $collection->itemAt(4));
		$this->assertEquals('item5', $collection->itemAt(4)->compile());

		$collection->removeAt(3);										// [item0,item1,item2,item5]
		$this->assertCount(4, $collection->all());
		$this->assertEquals('item5', $collection->itemAt(3)->compile());

		$collection->remove ($item5);									// [item0,item1,item2]
		$this->assertCount(3, $collection->all());

		return $collection;
	}

	/**
	 * @depends testWrite_ObjUnique
	 * @param Collection $collection
	 */
	public function testRead_ObjUnique (Collection $collection) {
		$item2 = $collection->itemAt(2);
		$this->assertEquals('item2', ($item2->compile()));
		$this->assertEquals(2, $collection->indexOf($item2));
		$this->assertEquals('item0', $collection->first()->compile());
		$this->assertEquals('item2', $collection->last()->compile());
	}
	#endregion
}
