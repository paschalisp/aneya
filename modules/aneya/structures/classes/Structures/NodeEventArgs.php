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

use aneya\Core\EventArgs;

class NodeEventArgs extends EventArgs {
	#region Constants
	/** Indicates that the node was linked to another node */
	const Linked = 'L';
	/** Indicates that the node was unlinked from another node */
	const Unlinked = 'U';
	/** Indicates that the node was added to a Mesh */
	const Added = 'A';
	/** Indicates that the node was removed from its parent Mesh */
	const Removed = 'D';
	#endregion

	#region Properties
	/** @var Mesh */
	public $sender;
	/** @var Node */
	public $source;
	/** @var Node */
	public $target;
	/** @var Link */
	public $link;
	/** @var string Valid values are Link::Action* constants */
	public $action;
	#endregion

	#region Constructor
	public function __construct(Mesh $sender = null, Node $source = null, Node $target = null, Link $link = null, $action = NodeEventArgs::Linked) {
		parent::__construct($sender);

		$this->source = $source;
		$this->target = $target;
		$this->link = $link;
		$this->action = $action;
	}
	#endregion
}
