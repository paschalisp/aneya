<?php
require_once (__DIR__ . '/../../../../aneya.php');

use aneya\Structures\Mesh;
use aneya\Structures\Node;
use PHPUnit\Framework\TestCase;

class MeshTest extends TestCase {
	public function test_start() {
		$mesh = new Mesh();

		$mesh->nodes->add ($node1 = new Node($mesh, 'node1'));
		$mesh->nodes->add ($node2 = new Node($mesh, 'node2'));
		$mesh->nodes->add ($node3 = new Node($mesh, 'node3'));
		$mesh->nodes->add ($node4 = new Node($mesh, 'node4'));

		#region Test link
		// Link node1 => node2
		$mesh->link($node1, $node2);

		$roots = $mesh->findRoots();
		$this->assertEquals(1, $roots->count());
		$this->assertTrue($roots->contains($node1));
		$orphans = $mesh->findOrphans();
		$this->assertEquals(2, $orphans->count());
		$this->assertTrue($orphans->contains($node3));
		$this->assertTrue($orphans->contains($node4));
		#endregion

		#region Test deeper level links
		$mesh->link($node2, $node3);
		$link34 = $mesh->link($node3, $node4);
		$link42 = $mesh->link($node4, $node2);

		$orphans = $mesh->findOrphans();
		$roots = $mesh->findRoots();
		$this->assertEquals(1, $roots->count());
		$this->assertEquals(0, $orphans->count());
		#endregion

		#region Test unlink
		$mesh->unlink($link42);
		$mesh->unlink($link34);

		$orphans = $mesh->findOrphans();
		$this->assertEquals(1, $orphans->count());
		$this->assertNotTrue($node3->isLinkedTo($node4));
		$this->assertNotTrue($node2->isLinkedBy($node4));
		#endregion
	}
}
