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

class NodeCollection extends Collection {
	#region Properties
	/** @var Node[] */
	protected array $_collection;
	#endregion

	#region Constructor
	public function __construct() {
		parent::__construct('\\aneya\\Structures\\Node', true);
	}
	#endregion

	#region Methods
	/**
	 * @inheritdoc
	 * @return Node[]
	 */
	public function all(callable $f = null): array {
		return parent::all($f);
	}

	/** Returns the Node in the collection given its bound object, tag or name */
	#[Pure] public function get(object|string $tag_name_or_object): ?Node {
		if (is_object($tag_name_or_object)) {
			$obj = $tag_name_or_object;
			foreach ($this->_collection as $node)
				if ($node->object() === $obj)
					return $node;
		}
		elseif (is_string($tag_name_or_object) && strlen($tag_name_or_object) > 0) {
			// First, search by tag
			foreach ($this->_collection as $node)
				if ($node->tag == $tag_name_or_object)
					return $node;

			// If fails, search by name
			foreach ($this->_collection as $node)
				if ($node->name == $tag_name_or_object)
					return $node;
		}

		return null;
	}

	/**
	 * @inheritdoc
	 */
	public function first(callable $f = null): ?Node {
		return parent::first($f);
	}
	#endregion
}
