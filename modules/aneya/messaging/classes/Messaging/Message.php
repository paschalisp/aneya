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

use aneya\CMS\ContentItem;
use aneya\Core\Data\ORM\DataObjectMapping;
use aneya\Core\Data\ORM\ORM;
use aneya\Core\IStorable;
use aneya\Core\Storable;
use aneya\Core\Utils\BitOps;
use aneya\Security\User;

class Message extends ContentItem implements IStorable {
	use Storable;

	#region Constants
	#region Priority constants
	const PriorityNotify			= -2;
	const PriorityLow				= -1;
	const PriorityNormal			= 0;
	const PriorityHigh				= 1;
	const PriorityUrgent			= 2;
	#endregion

	#region Sending method constants
	/** Send by e-mail only */
	const SendByEmail				= 'E';
	/** Send by e-mail but also via internal messaging */
	const SendByEmailAndInternally	= 'EI';
	/** Send by internal messaging only */
	const SendInternally			= 'I';
	#endregion

	#region Status constants
	const StatusDraft				= 0;
	const StatusUnread				= 1;
	const StatusRead				= 2;
	const StatusArchived			= 8;
	const StatusDeleted				= 9;
	#endregion

	#region Flag constants
	const FlagDraft					= 1;
	const FlagUnread				= 2;
	const FlagRead					= 4;
	const FlagArchived				= 8;
	const FlagDeleted				= 16;
	const FlagPriorityNotify		= 32;
	const FlagPriorityNormal		= 64;
	const FlagPriorityHigh			= 128;
	const FlagPriorityUrgent		= 256;
	const FlagNotifyOnRead			= 512;
	const FlagIsReply				= 1024;
	#endregion
	#endregion

	#region Properties
	/** @var int Message Id */
	public int $id;

	/** @var int Parent message's Id (applies on messages that are a reply) */
	public int $parentMessageId;

	public User $sender;

	public int $senderId;

	public int $recipientId;

	public \DateTime $dateSent;

	public \DateTime $dateRead;

	/** @var int Valid values are Message::Priority* constants */
	public $priority = self::PriorityNormal;

	public string $subject;

	public string $body;

	/** @var string Sending and notification method. Valid values are Message::SendBy* constants */
	public string $method;

	/** @var Message status */
	public $status = self::StatusDraft;

	/** @var bool Notify sender when the recipient opens the message. Applies only for priorities equal or above normal and sending methods that involve internal messaging */
	public bool $notifyOnRead = false;

	public string $comments;
	#endregion

	#region Constructor
	public function __construct ($subject = '', $body = '', $priority = Message::PriorityNormal) {
		$this->subject = $subject;
		$this->body = $body;
		$this->priority = $priority;
	}
	#endregion

	#region Methods
	/**
	 * Returns message's enabled flags
	 * @return integer
	 */
	public function flagsValue (): int {
		$flags = 00000000000000000;

		if ($this->status == self::StatusDraft)				$flags = BitOps::addBit ($flags, self::FlagDraft);
		if ($this->status == self::StatusUnread)			$flags = BitOps::addBit ($flags, self::FlagUnread);
		if ($this->status == self::StatusRead)				$flags = BitOps::addBit ($flags, self::FlagRead);
		if ($this->status == self::StatusArchived)			$flags = BitOps::addBit ($flags, self::FlagArchived);
		if ($this->status == self::StatusDeleted)			$flags = BitOps::addBit ($flags, self::FlagDeleted);
		if ($this->priority == self::PriorityNotify)		$flags = BitOps::addBit ($flags, self::FlagPriorityNotify);
		if ($this->priority == self::PriorityNormal)		$flags = BitOps::addBit ($flags, self::FlagPriorityNormal);
		if ($this->priority == self::PriorityHigh)			$flags = BitOps::addBit ($flags, self::FlagPriorityHigh);
		if ($this->priority == self::PriorityUrgent)		$flags = BitOps::addBit ($flags, self::FlagPriorityUrgent);
		if ($this->notifyOnRead == true)					$flags = BitOps::addBit ($flags, self::FlagNotifyOnRead);
		if ($this->parentMessageId > 0)						$flags = BitOps::addBit ($flags, self::FlagIsReply);

		return $flags;
	}
	#endregion

	#region Static methods
	#endregion

	#region ORM-related methods
	protected static function onORM (): ?DataObjectMapping {
		$ds = static::classDataSet(Messaging::db ()->schema->getDataSet (['cms_messaging', 'cms_messaging_recipients'], ['msg_id', 'parent_id', 'sender_id', 'recipient_id', 'recipient_type', 'date_sent', 'priority', 'status', 'subject', 'message']));
		$ds->columns->get ('cms_messaging.msg_id')->tag = 'id';
		$ds->columns->get ('parent_id')->tag = 'parentMessageId';
		$ds->columns->get ('sender_id')->tag = 'senderId';
		$ds->columns->get ('date_sent')->tag = 'dateSent';
		$ds->columns->get ('message')->tag = 'content';

		$ds->mapClass(static::class);
		$ds->autoGenerateObjects = true;

		$orm = ORM::dataSetToMapping ($ds, static::class);

		$orm->getProperty('msgId')->propertyName = 'id';
		$orm->getProperty('parentId')->propertyName = 'parentMessageId';
		$orm->getProperty('message')->propertyName = 'content';

		return $orm;
	}
	#endregion
}
