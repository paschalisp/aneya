<?php
require_once (__DIR__ . '/../../../aneya.php');

use aneya\Core\Cache;
use aneya\Core\Collection;
use PHPUnit\Framework\TestCase;

class CacheTest extends TestCase {
	public function testCachePlain() {
		Cache::clear('testcases');

		$data = '<div class="testcase"><p>Testing caching of plain text</p></div>';

		$hash = Cache::store($data, null, 'test1', 'testcases');
		$data2 = Cache::retrieve('testcases', $hash);
		$this->assertEquals($data, $data2);

		$num = Cache::clear('testcases');
		$this->assertEquals(1, $num);
	}

	public function testCacheObject() {
		$obj = new Collection();
		$obj->add('test');

		$hash = Cache::store($obj, null, 'test2', 'testcases');
		/** @var Collection $obj2 */
		$obj2 = Cache::retrieve('testcases', $hash);
		$this->assertTrue($obj2 instanceof Collection);
		$this->assertTrue($obj2->count() == 1);
		$this->assertTrue($obj2->first() == 'test');

		$num = Cache::clear('testcases', $hash);
		$this->assertEquals(1, $num);
	}
}