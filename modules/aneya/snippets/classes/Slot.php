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


use aneya\Core\IRenderable;

class Slot implements IRenderable {
	#region Properties
	public ?string $tag = null;
	/** @var int $order Render order */
	public int $order = 0;
	/** @var int Slot's compilation status. Valid values are Snippet::CompileStatus* constants */
	public int $compileStatus = Snippet::CompileStatusNone;

	/** @var SnippetCollection */
	public SnippetCollection $snippets;

	protected string $_contentType = 'text/html';
	#endregion

	#region Constructor
	/**
	 * Slot constructor.
	 *
	 * @param string|null $tag
	 */
	public function __construct(string $tag = null) {
		if (strlen($tag) > 0)
			$this->tag = $tag;

		$this->snippets = new SnippetCollection();
	}
	#endregion

	#region Methods
	/**
	 * Gets/sets slot's parent Snippet.
	 * @see SnippetCollection::parent()
	 */
	public function parent(Snippet $parent = null): ?Snippet {
		return $this->snippets->parent($parent);
	}

	/**
	 * @see SnippetCollection::setup()
	 */
	public function setup(): Slot {
		$this->snippets->setup();

		return $this;
	}
	#endregion

	#region IRenderable
	public function contentType(string $contentType = null): string {
		if (isset($contentType))
			$this->_contentType = $contentType;

		return $this->_contentType;
	}

	/**
	 * Gets/Sets the renderable object's compile order
	 */
	function renderOrder(int $order = null): ?int {
		if (is_int($order))
			$this->order = $order;

		return $order;
	}

	/**
	 * Gets/Sets the renderable object's render tag
	 */
	function renderTag(string $tag = null): string {
		if (is_string($tag) && strlen($tag) > 0)
			$this->tag = $tag;

		return $this->tag;
	}

	/**
	 * Renders the IRenderable items contained in the collection by returning their concatenated output.
	 */
	function render(): string {
		$content = '';

		foreach ($this->snippets->sort()->all() as $snippet) {
			if ($snippet instanceof Snippet)
				$content .= $snippet->prepare();
			else
				$content .= $snippet->render();
		}

		return $content;
	}
	#endregion

	#region Static methods
	#endregion
}
