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

namespace aneya\Snippets;

use aneya\Core\Collection;
use aneya\Core\IRenderable;
use aneya\Core\ISortable;

class SnippetCollection extends Collection implements ISortable {
	#region Properties
	/** @var IRenderable[]|Snippet[] */
	protected array $_collection;

	protected Snippet $_parent;
	#endregion

	#region Constructor
	public function __construct() {
		parent::__construct('\\aneya\\Core\\IRenderable');
	}
	#endregion

	#region Methods
	/**
	 * @inheritdoc
	 * @param Snippet|IRenderable $item
	 */
	public function add($item): static {
		if ($item->compileOrder == null)
			$item->compileOrder = ($this->getMaxCompileOrder() + 1);

		// Flag parent snippet (if any) as outdated
		if ($this->_parent instanceof Snippet)
			$this->_parent->compileStatus = ($this->_parent->compileStatus > Snippet::CompileStatusNone) ? Snippet::CompileStatusInitialized : Snippet::CompileStatusNone;

		$item->isStandalone = false;

		return parent::add($item);
	}

	/**
	 * @inheritdoc
	 * @return Snippet[]
	 */
	public function all(callable $f = null) : array {
		return parent::all($f);
	}

	/**
	 * Returns true if Snippet with the given tag exists in the collection
	 *
	 * @param string $tag
	 *
	 * @return bool
	 */
	public function existsByTag(string $tag): bool {
		foreach ($this->_collection as $snippet) {
			if ($snippet->tag == $tag)
				return true;
		}

		return false;
	}

	/**
	 * Returns the Snippet with the given tag or null if there is no such Snippet in the collection
	 */
	public function getByTag(string $tag): ?Snippet {
		foreach ($this->_collection as $snippet) {
			if ($snippet->tag == $tag)
				return $snippet;
		}

		return null;
	}

	/**
	 * Returns the maximum compilation order found in the collection.
	 */
	protected function getMaxCompileOrder(): int {
		$max = 0;
		foreach ($this->_collection as $s) {
			if ($s->compileOrder > $max && $s->compileOrder < PHP_INT_MAX)
				$max = $s->compileOrder;
		}

		return $max;
	}

	/**
	 * Gets/sets collection's snippets' parent Snippet.
	 *
	 * @param Snippet|null $parent (optional)
	 */
	public function parent(Snippet $parent = null): ?Snippet {
		if ($parent !== null)
			$this->_parent = $parent;

		return $this->_parent;
	}

	/**
	 * Calls setup() method of all snippets contained in the collection.
	 */
	public function setup(): SnippetCollection {
		foreach ($this->_collection as $item) {
			if ($item instanceof Snippet)
				$item->setup();
		}

		return $this;
	}
	#endregion

	#region Interface methods
	/**
	 * Sorts the collection and returns back the instance sorted
	 */
	public function sort(): SnippetCollection {
		usort($this->_collection, function (IRenderable $a, IRenderable $b) {
			if ($a->renderOrder() == $b->renderOrder())
				return 0;

			return ($a->renderOrder() < $b->renderOrder()) ? -1 : 1;
		});
		$this->rewind();

		return $this;
	}
	#endregion
}
