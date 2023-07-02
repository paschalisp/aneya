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
 * Portions created by Paschalis Ch. Pagonidis are Copyright (C) 2007-2022
 * Paschalis Ch. Pagonidis. All Rights Reserved.
 */

namespace aneya\Core;


use aneya\Core\Utils\BitOps;

class Notification extends CoreObject {

	#region Constants
	const TypeInformation	= 'info';
	const TypeAlert			= 'alert';
	const TypeError			= 'error';

	const FlagInformation	= 1;
	const FlagAlert			= 2;
	const FlagError			= 4;
	const FlagUnread		= 8;
	const FlagRead			= 16;
	const FlagLast24H		= 32;
	#endregion

	#region Properties
	/** @var int */
	public $id;

	/** @var \DateTime */
	public $dateSent;

	/** @var string */
	public $message;

	/** @var string Valid values are Notification::Type* constants */
	public $type = self::TypeInformation;

	/** @var bool */
	public $isRead = false;
	#endregion

	#region Constructor
	public function __construct ($content = '', $type = Notification::TypeInformation) {
		$this->content = $content;
		$this->type = $type;
	}
	#endregion

	#region Methods
	/**
	 * Marks a user notification as dismissed
	 * @return mixed
	 * @throws \Exception
	 */
	public function dismiss () {
		$sql = 'UPDATE cms_users_notifications SET is_read=1 WHERE notification_id=:notification_id';
		return CMS::db()->execute ($sql, ['notification_id' => $this->id]);
	}

	/**
	 * Returns notification's enabled flags
	 * @return integer
	 */
	public function flagsValue () {
		$flags = 00000000000000000;

		if ($this->type == self::TypeInformation)	$flags = BitOps::addBit ($flags, self::FlagInformation);
		if ($this->type == self::TypeAlert)			$flags = BitOps::addBit ($flags, self::FlagAlert);
		if ($this->type == self::TypeError)			$flags = BitOps::addBit ($flags, self::FlagError);
		if ($this->isRead)							$flags = BitOps::addBit ($flags, self::FlagRead);
		if (!$this->isRead)							$flags = BitOps::addBit ($flags, self::FlagUnread);
		if ($this->dateSent instanceof \DateTime && $this->dateSent->diff (new \DateTime())->h < 24) {
			$flags = BitOps::addBit ($flags, self::FlagUnread);
		}

		return $flags;
	}
	#endregion
}
