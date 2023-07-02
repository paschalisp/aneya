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

namespace aneya\Messaging;

use aneya\Core\Collection;
use aneya\Core\ISortable;

/**
 * Class AttachmentCollection
 * Represents a collection of attachments
 *
 * @package aneya\Messaging
 */
class AttachmentCollection extends Collection implements ISortable {
	#region Properties
	/** @var int */
	public mixed $id;

	/** @var Attachment[] */
	protected array $_collection;
	#endregion

	#region Constructor
	public function __construct() {
		parent::__construct('\\aneya\\Messaging\\Attachment', true);
	}
	#endregion

	#region Methods
	/**
	 * @inheritdoc
	 * @return Attachment[]
	 */
	public function all(callable $f = null) : array {
		return parent::all($f);
	}
	#endregion

	#region Interface implementations
	/**
	 * Sorts the collection and returns back the instance sorted
	 * @return AttachmentCollection
	 */
	public function sort () {
		usort ($this->_collection, function (Attachment $a, Attachment $b) {
			return strcmp($a->fileName, $b->fileName);
		});
		$this->rewind ();

		return $this;
	}
	#endregion
}
