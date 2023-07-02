<?php
namespace unit\aneya\Structures;

require_once (__DIR__ . '/../../../../aneya.php');

use aneya\Structures\Mesh;
use aneya\Structures\Node;

class MeshTest extends \Codeception\Test\Unit {
	public Mesh $mesh;
	/** @var Node[] */
	public array $nodes;

	protected function _before() {
		$this->mesh = new Mesh();
		$this->nodes = [];

		$this->mesh->nodes->add($this->nodes['node1'] = new Node($this->mesh, 'node1'));
		$this->mesh->nodes->add($this->nodes['node2'] = new Node($this->mesh, 'node2'));
		$this->mesh->nodes->add($this->nodes['node3'] = new Node($this->mesh, 'node3'));
		$this->mesh->nodes->add($this->nodes['node4'] = new Node($this->mesh, 'node4'));
	}

	public function testLink() {
		$this->mesh->link($this->nodes['node1'], $this->nodes['node2']);
		$this->mesh->link($this->nodes['node1'], $this->nodes['node3']);

		$this->assertTrue($this->nodes['node1']->isLinkedTo($this->nodes['node2']));
		$this->assertTrue($this->nodes['node1']->isLinkedTo($this->nodes['node3']));
		$this->assertTrue($this->nodes['node2']->isLinkedBy($this->nodes['node1']));

		$this->assertCount(2, $this->nodes['node1']->links());
		$this->assertCount(1, $this->nodes['node2']->links());
		$this->assertCount(1, $this->nodes['node3']->links());
	}

	public function testFindOrphans() {
		$this->mesh->link($this->nodes['node1'], $this->nodes['node2']);

		$orphans = $this->mesh->findOrphans();
		$this->assertEquals(2, $orphans->count());
		$this->assertTrue($orphans->contains($this->nodes['node3']));
		$this->assertTrue($orphans->contains($this->nodes['node3']));
		$this->assertFalse($orphans->contains($this->nodes['node1']));
	}

	public function testUnlink() {
		$this->mesh->link($this->nodes['node1'], $this->nodes['node2']);

		$this->mesh->unlink($this->nodes['node1'], $this->nodes['node2']);
		$this->assertFalse($this->nodes['node1']->isLinkedTo($this->nodes['node2']));
		$this->assertFalse($this->nodes['node2']->isLinkedBy($this->nodes['node1']));

		$link = $this->mesh->link($this->nodes['node1'], $this->nodes['node2']);

		$this->mesh->unlink($link);
		$this->assertFalse($this->nodes['node1']->isLinkedTo($this->nodes['node2']));
		$this->assertFalse($this->nodes['node2']->isLinkedBy($this->nodes['node1']));
	}

	public function testParseNodes() {
		$this->mesh->link($this->nodes['node1'], $this->nodes['node2']);
		$this->mesh->link($this->nodes['node3'], $this->nodes['node4']);
		$this->mesh->link($this->nodes['node3'], $this->nodes['node2']);

		// Should output node1, node2, node3, node4
		$nodes = $this->mesh->parseNodes()->all();
		$this->assertEquals($nodes[0], $this->nodes['node1']);
		$this->assertEquals($nodes[1], $this->nodes['node3']);
		$this->assertEquals($nodes[2], $this->nodes['node2']);
		$this->assertEquals($nodes[3], $this->nodes['node4']);
	}

	public function testFindRoots() {
		$this->mesh->link($this->nodes['node1'], $this->nodes['node2']);

		$roots = $this->mesh->findRoots();
		$this->assertEquals(1, $roots->count());
		$this->assertTrue($roots->contains($this->nodes['node1']));
	}
}
