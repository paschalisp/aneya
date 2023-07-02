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

class Link {
	#region Properties
	/** @var Node Source node */
	public Node $source;
	/** @var Node Target node */
	public Node $target;

	/** @var string */
	public string $name;

	/** @var float A weight value indicator used to sort the link within a collection (the smaller the more prioritized) */
	public float $weight = 0.0;
	#endregion

	#region Constructor
	public function __construct (Node $source, Node $target, $weight = 0.0, $name = null) {
		$this->source = $source;
		$this->target = $target;
		$this->weight = (float)$weight;
		$this->name = (string)$name;
	}

	public function __toString() {
		return (string)$this->source . '=>' . (string)$this->target;
	}
	#endregion

	#region Methods
	#endregion
}
