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


trait Renderable {
	#region Properties
	/** @var Snippet */
	protected $_snippet;
	#endregion

	#region Methods
	/**
	 * Gets/Sets the renderable object's compile order
	 *
	 * @param int|null $order (optional)
	 *
	 * @return ?int
	 */
	public function renderOrder(int $order = null): ?int {
		if (is_numeric($order)) {
			$this->snippet()->compileOrder = (int)$order;
		}

		return $this->snippet()->compileOrder;
	}

	/**
	 * Gets/Sets the renderable object's render tag
	 *
	 * @param string|null $tag (optional)
	 *
	 * @return string
	 */
	public function renderTag(string $tag = null): string {
		if (is_string($tag)) {
			$this->snippet()->tag = (string)$tag;
		}

		return $this->snippet()->tag;
	}

	/**
	 * Renders the object by returning its output
	 *
	 * @return string
	 */
	public function render(): string {
		if ($this->snippet()->isStandalone)
			return $this->snippet()->compile();
		else
			return $this->snippet()->prepare();
	}

	/**
	 * Return's the renderable object's internal Snippet
	 *
	 * @return Snippet
	 */
	function snippet(): Snippet {
		if ($this->_snippet === null) {
			$this->_snippet = new Snippet ();
			$this->_snippet->parent = $this;
		}

		return $this->_snippet;
	}

	public function contentType(string $contentType = null): string {
		if (isset($contentType))
			$this->snippet()->contentType($contentType);

		return $this->snippet()->contentType();
	}
	#endregion
}
