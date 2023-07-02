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
 * Portions created by Paschalis Ch. Pagonidis are Copyright (c) 2007-2022
 * Paschalis Ch. Pagonidis. All Rights Reserved.
 */

namespace aneya\Structures;

use aneya\Core\Collection;
use aneya\Core\CollectionEventArgs;

class Mesh {
	#region Properties
	/** @var NodeCollection */
	public NodeCollection $nodes;

	/** @var LinkCollection */
	protected LinkCollection $_links;
	#endregion

	#region Constructor
	public function __construct() {
		$this->nodes = new NodeCollection();
		$this->_links = new LinkCollection();

		$this->nodes->on(Collection::EventOnItemAdded, function (CollectionEventArgs $args) {
			/** @var Node $node */
			$node = $args->newItem;
			$node->notify(new NodeEventArgs($this, null, null, null, NodeEventArgs::Added));
		});
		$this->nodes->on(Collection::EventOnItemRemoved, function (CollectionEventArgs $args) {
			/** @var Node $node */
			$node = $args->oldItem;
			$node->notify(new NodeEventArgs($this, null, null, null, NodeEventArgs::Removed));
		});
	}
	#endregion

	#region Methods
	/**
	 * Links two nodes together and returns the instantiated Link between them; or false if source or target were not found in the mesh
	 *
	 * @param Node $source
	 * @param Node $target
	 * @param float $weight
	 * @param string $name
	 * @return Link|bool
	 */
	public function link(Node $source, Node $target, $weight = 0.0, $name = null) {
		if ($source == null || $target == null)
			return false;

		if (!$this->nodes->contains($source) || !$this->nodes->contains($target))
			return false;

		$this->_links->add($link = new Link($source, $target, $weight, $name));
		$args = new NodeEventArgs($this, $source, $target, $link);
		$source->notify($args);
		$target->notify($args);

		return $link;
	}

	/**
	 * Unlinks two nodes from each other and removes the corresponding Link from the collection
	 *
	 * @param Node|Link $source_or_link
	 * @param ?Node $target Required if source node is provided instead of the actual link
	 * @return bool true if the nodes were found and unlinked from each other
	 */
	public function unlink($source_or_link, Node $target = null): bool {
		if ($source_or_link == null)
			return false;

		if ($source_or_link instanceof Node) {
			if ($target == null)
				return false;

			foreach ($this->_links->all() as $link) {
				if ($link->source === $source_or_link && $link->target === $target) {
					$args = new NodeEventArgs($this, $link->source, $link->target, $link, NodeEventArgs::Unlinked);
					$link->source->notify($args);
					$link->target->notify($args);
					$this->_links->remove($link);
					return true;
				}
			}

			return false;
		} elseif ($source_or_link instanceof Link) {
			$link = $source_or_link;
			if (!$this->_links->contains($link))
				return false;

			$args = new NodeEventArgs($this, $link->source, $link->target, $link, NodeEventArgs::Unlinked);
			$link->source->notify($args);
			$link->target->notify($args);
			$this->_links->remove($link);
			return true;
		} else
			return false;
	}

	/**
	 * Returns all nodes that have target links but are no targets of any link in the mesh
	 */
	public function findRoots(): NodeCollection {
		$nodes = new NodeCollection();

		// Consider the only node in the mesh as a root node
		if ($this->nodes->count() == 1) {
			$nodes->add($this->nodes->first());
			return $nodes;
		}

		foreach ($this->nodes->all() as $node)
			if ($node->isRoot()) {
				$nodes->add($node);
			}

		return $nodes;
	}

	/**
	 * Returns all nodes that are orphans in the mesh (no associated to any link, in any direction)
	 */
	public function findOrphans(): NodeCollection {
		$nodes = new NodeCollection();

		foreach ($this->nodes->all() as $node)
			if ($node->isOrphan())
				$nodes->add($node);

		return $nodes;
	}

	/**
	 * Parses the Mesh's tree of nodes and returns the nodes in an order so that can be safely used by algorithms, avoiding node dependency conflicts.
	 *
	 * @return NodeCollection|bool The ordered collection of Nodes; or false if the parsing encountered an endless loop (there cannot be an order can resolve all dependency conflicts)
	 */
	public function parseNodes() {
		$nodes = $this->findRoots();				// The nodes to start from
		$excludedNodes = new NodeCollection();		// Will keep all nodes that have been already parsed, which will give the final nodes order

		$max = $this->nodes->count();
		do {
			// Count the number of excluded nodes before starting the round
			$cnt = $excludedNodes->count();

			foreach ($nodes->all() as $node)
				$excludedNodes->add($node);

			foreach ($nodes->all() as $node)
				$this->parseNode($node, $excludedNodes);

			// Calculate the number of nodes that were excluded in this round
			$added = $excludedNodes->count() - $cnt;

			// If all nodes got excluded or there was no new node excluded, stop the recursion
			$ok = ($cnt == $max || $added == 0);
		} while (!$ok);

		// Check if parsing encountered an endless loop
		if ($excludedNodes->count() < $this->nodes->count()) {
			return false;
		}

		return $excludedNodes;
	}

	/**
	 * @param Node $node
	 * @param NodeCollection $excludedNodes Nodes that have been already parsed
	 */
	private function parseNode(Node $node, NodeCollection &$excludedNodes) {
		$nodes = $node->findChildRootNodes($excludedNodes->all());
		foreach ($nodes as $n) {
			$excludedNodes->add($n);
			$this->parseNode($n, $excludedNodes);
		}
	}

	/**
	 * @param Node $source
	 * @param Node $target
	 * @return LinkCollection|bool
	 */
	public function shortestPath(Node $source, Node $target): LinkCollection|bool {
		if (!$this->nodes->contains($source) || !$this->nodes->contains($target))
			return false;

		// TODO: Implement shortest path algorithm

		return new LinkCollection();
	}
	#endregion
}
