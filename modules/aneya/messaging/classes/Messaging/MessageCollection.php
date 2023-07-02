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

class MessageCollection extends Collection {
	#region Constants
	#endregion

	#region Properties
	/** @var Message[] */
	protected array $_collection;
	#endregion

	#region Constructor
	public function __construct() {
		parent::__construct('\\aneya\\Messaging\\Message', true);
	}
	#endregion

	#region Methods
	/**
	 * @inheritdoc
	 * @return Message
	 */
	public function first(callable $f = null): ?Message {
		return parent::first($f);
	}

	/**
	 * Returns the Message specified by the given id
	 *
	 * @param int $messageId
	 *
	 * @return Message|null
	 */
	public function getById(int $messageId): ?Message {
		foreach ($this->_collection as $msg) {
			if ($msg->id == (int)$messageId) {
				return $msg;
			}
		}

		return null;
	}
	#endregion
}
