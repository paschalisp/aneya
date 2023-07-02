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

use aneya\Core\CoreObject;

class Node extends CoreObject {
	#region Properties
	/** @var string */
	public $tag;
	/** @var string */
	public $name;
	/** @var int A numeric value indicating how the node should be prioritized when parsed among other nodes of the same mesh parsing round */
	public $priority = 0;

	/** @var Mesh */
	protected $_parent;

	/** @var object An object that accompanies the node */
	protected $_object;

	/** @var LinkCollection */
	protected $_incomingLinks;
	/** @var LinkCollection */
	protected $_outgoingLinks;
	#endregion

	#region Event
	/** Triggered when the node is linked to another node regardless the direction. The event passes a NodeEventArgs argument on listeners. */
	const EventOnLink = 'OnLink';
	const EventOnUnlink = 'OnUnlink';
	#endregion

	#region Constructor
	public function __construct(Mesh $parent = null, $tag = null, $name = null, $object = null) {
		$this->_incomingLinks = new LinkCollection();
		$this->_outgoingLinks = new LinkCollection();

		$this->tag = (string)$tag;
		$this->name = (string)$name;
		$this->_parent = $parent;
		$this->_object = $object;

		$this->hooks()->register([self::EventOnLink, self::EventOnUnlink]);
	}

	public function __toString() {
		if (is_object($this->_object) && method_exists($this->_object, '__toString'))
			return (string)$this->_object;
		else
			return (strlen($this->tag) > 0) ? $this->tag : $this->name;
	}
	#endregion

	#region Methods
	/**
	 * @param mixed $object Binds the node with an object
	 */
	public function bind($object) {
		$this->_object = $object;
	}

	/**
	 * Returns the object that was bound to the node
	 * @return mixed
	 */
	public function object() {
		return $this->_object;
	}

	/**
	 * Lets other objects notify the node for being linked or unlinked with other nodes in the mesh
	 * @param NodeEventArgs $args
	 */
	public function notify(NodeEventArgs $args) {
		if (!in_array($args->action, array(NodeEventArgs::Linked, NodeEventArgs::Unlinked, NodeEventArgs::Added, NodeEventArgs::Removed)))
			return;

		if ($args->action != NodeEventArgs::Added && ($args->sender !== $this->_parent || !($args->link instanceof Link)))
			return;

		if ($args->action == NodeEventArgs::Linked) {
			if ($args->link->source === $this) {
				$this->_outgoingLinks->add($args->link);
			} elseif ($args->link->target === $this) {
				$this->_incomingLinks->add($args->link);
			}
		} elseif ($args->action == NodeEventArgs::Unlinked) {
			if ($args->link->source === $this) {
				$this->_outgoingLinks->remove($args->link);
			} elseif ($args->link->target === $this) {
				$this->_incomingLinks->remove($args->link);
			}
		} elseif ($args->action == NodeEventArgs::Added) {
			$this->_parent = $args->sender;
		} elseif ($args->action == NodeEventArgs::Removed) {
			if ($this->_parent === $args->sender)
				$this->_parent = null;
		}
	}

	/**
	 * Links the node with a target node
	 * @param $target
	 * @return Link|bool
	 */
	public function linkTo($target) {
		return $this->_parent->link($this, $target);
	}

	/**
	 * Returns all links that are connected to the node, regardless the link direction (source or target)
	 * @return Link[]
	 */
	public function links() {
		return array_merge($this->_incomingLinks->all(), $this->_outgoingLinks->all());
	}

	/**
	 * Returns true if the node has a link targeted to the given argument
	 * @param Node $target
	 * @return bool
	 */
	public function isLinkedTo(Node $target) {
		return ($this->_outgoingLinks->countByTarget($target) > 0);
	}

	/**
	 * Returns true if the node is target of a link sourced by the given argument
	 * @param Node $source
	 * @return bool
	 */
	public function isLinkedBy(Node $source) {
		return ($this->_incomingLinks->countBySource($source) > 0);
	}

	/**
	 * Returns all links where node is their target
	 * @return Link[]
	 */
	public function incoming() {
		return $this->_incomingLinks->all();
	}

	/**
	 * Returns all links where node is their source
	 * @return Link[]
	 */
	public function outgoing() {
		return $this->_outgoingLinks->all();
	}

	/**
	 * Finds and returns child linked nodes that are roots if the provided list of nodes, plus the current node are excluded.
	 * Used by tree-parsing algorithms.
	 *
	 * @param Node[] $excludeNodes
	 * @return Node[] $exclude
	 */
	public function findChildRootNodes($excludeNodes = []) {
		$parents = new NodeCollection();
		$parents->add($this);
		if (is_array($excludeNodes))
			foreach ($excludeNodes as $node) {
				if ($node instanceof Node)
					$parents->add($node);
			}

		$nodes = array();

		foreach ($this->_outgoingLinks->all() as $link) {
			$node = $link->target;
			$isRoot = true;
			foreach ($node->incoming() as $link2) {
				if ($parents->contains($link2->source))
					continue;

				// If we get up to here, the node has dependencies, so remove the root flag
				$isRoot = false;
				break;
			}

			if ($isRoot)
				$nodes[] = $node;
		}

		// Sort the returning nodes (by node priority)
		usort($nodes, function(Node $a, Node $b) use ($excludeNodes) {
			if ((float)$a->priority == (float)$b->priority) {
				// The earliest a node is linked by a parent node, the more it is prioritized
				foreach ($excludeNodes as $parent) {
					if ($a->isLinkedBy($parent)) {
						return -1;
					}
					elseif ($b->isLinkedBy($parent))
						return 1;
				}

				return 0;
			}
			return ((float)$a->priority < (float)$b->priority) ? -1 : 1;
		});

		return $nodes;
	}

	/**
	 * Returns true if the node has target links but is no target of any link
	 * @return bool
	 */
	public function isRoot() {
		return ($this->_incomingLinks->count() == 0 && $this->_outgoingLinks->count() > 0);
	}

	/**
	 * Returns true if the node is no associated to any link, regardless the direction (source or target)
	 * @return bool
	 */
	public function isOrphan() {
		return ($this->_incomingLinks->count() == 0 && $this->_outgoingLinks->count() == 0);
	}
	#endregion
}
