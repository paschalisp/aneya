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

use aneya\Core\CMS;
use aneya\Core\Data\Database;
use aneya\Core\Data\DataFilter;
use aneya\Core\Data\DataSet;
use aneya\Core\Environment\Net;
use aneya\Core\EventStatus;
use aneya\Core\Utils\DateUtils;
use aneya\Security\User;
use aneya\Snippets\Snippet;

class Messaging {
	#region Properties
	/** @var User The User instance whose messages are managed by this Messaging instance */
	public $user;

	/** @var MessageCollection */
	protected $_inbox;

	/** @var MessageCollection */
	protected $_sent;

	protected static $_dbTag = CMS::CMS_DB_TAG;
	#endregion

	#region Constructor
	public function __construct(User $user) {
		$this->user = $user;
	}
	#endregion

	#region Methods
	/**
	 * Returns all active contacts the user can communicate with based on the given role rules.
	 * @param array $rules
	 *
	 * @return array
	 */
	public function contacts($rules = []) {
		$roles = [];
		$contacts = [];

		// Gather all affiliate roles the user can contact to
		foreach ($rules as $role => $affiliates) {
			if ($this->user->roles()->contains($role)) {
				$roles = array_unique(array_merge($roles, $affiliates));
			}
		}

		$roles = array_map(function ($role) { return CMS::db()->escape($role); }, $roles);
		$roles = implode("', '", $roles);

		$sql = "SELECT DISTINCT U.user_id, trim(concat(U.first_name, ' ', U.last_name)) AS name, U.photo_url, U.date_accessed
				FROM cms_users U
				JOIN cms_users_roles UR ON UR.user_id=U.user_id
				WHERE role IN ('$roles') AND U.status=:status AND U.user_id<>:user_id";

		$rows = CMS::db()->fetchAll($sql, [':status' => User::StatusActive, ':user_id' => $this->user->id]);
		if ($rows) {
			$contacts = array_map(function ($row) {
				$contact = new \stdClass();
				$contact->id = $row['user_id'];
				$contact->name = $row['name'];
				$contact->lastSeen = strlen($row['date_accessed']) > 0 ? DateUtils::toJsDate(new \DateTime($row['date_accessed'])) : null;
				$contact->photo = $row['photo_url'];

				return $contact;
			}, $rows);
		}

		return $contacts;
	}

	/**
	 * @return MessageCollection
	 */
	public function inbox() {
		if ($this->_inbox === null) {
			$this->_inbox = new MessageCollection ();

			/** @var DataSet $ds */
			$ds = Message::ormSt()->dataSet()->clear();
			$ds->retrieve(new DataFilter ($ds->columns->get('recipient_id'), DataFilter::Equals, $this->user->id));

			/** @var Message[] $objects */
			$objects = $ds->objects();
			$this->_inbox->addRange($objects);
		}

		return $this->_inbox;
	}

	/**
	 * Sends a message from the user
	 *
	 * @param string|int|Recipients|User $recipients
	 * @param string $subject
	 * @param string $body
	 * @param ?MessageOptions            $options (optional)
	 *
	 * @return EventStatus
	 */
	public function send($recipients, string $subject, string $body, MessageOptions $options = null): EventStatus {
		$status = new EventStatus (false, 'Invalid arguments');

		// Set default options if not provided
		if ($options === null) {
			$options = new MessageOptions ();
		}

		if (strlen($this->user->email) == 0 && in_array($options->method, [Message::SendByEmail, Message::SendByEmailAndInternally])) {
			return new EventStatus (false, 'E-mail address not set');
		}


		#region Convert recipients to a Recipients object (if not already)
		if (!($recipients instanceof Recipients)) {
			$recipients = new Recipients ($recipients);
		}
		#endregion

		if ($options->method == Message::SendByEmail || $options->method == Message::SendByEmailAndInternally) {
			$status = Net::sendMail($this->user->email, $recipients, $subject, $body, $options->isHtml);
		}

		if ($options->method == Message::SendInternally || $options->method == Message::SendByEmailAndInternally) {
			$message = new Message ($subject, $body, $options->priority);

			// Replace all recipient entries to their User Ids
			$rcpt = $recipients->toUserIds();

			/** @var DataSet $ds */
			$ds = $message->orm()->dataSet();
			$ds->relations->first()->isSaveable = false;            // Disable storing on cms_messaging_recipients at this point
			$row = $ds->newRow();
			$row->setValue('senderId', $this->user->id);
			$row->setValue('dateSent', new \DateTime());
			$row->setValue('subject', $subject);
			$row->setValue('content', $body);
			$row->setValue('priority', $options->priority);

			// Back-up and change some flags
			$rel = $ds->relations->getByChild($ds->tables->get('cms_messaging_recipients'))[0];
			$rcpId = $ds->columns->get('recipient_id');
			$isSaveable = $rel->isSaveable;
			$isRequired = $rcpId->isRequired;
			$rel->isSaveable = false;
			$rcpId->isRequired = false;

			$status = $row->save();

			// Restore changed flags
			$rel->isSaveable = $isSaveable;
			$rcpId->isRequired = $isRequired;

			if ($status->isError())
				return $status;

			$msgId = $row->getValue('id');

			$ds = static::db()->schema->getDataSet('cms_messaging_recipients', ['msg_id', 'recipient_id', 'recipient_type', 'status']);

			#region Add a database row for every recipient
			foreach ($rcpt->to as $to) {
				$row = $ds->newRow();
				$row->setValue('msg_id', $msgId);
				$row->setValue('recipient_id', $to);
				$row->setValue('recipient_type', 'T');
				$row->setValue('status', Message::StatusUnread);
			}

			foreach ($rcpt->cc as $cc) {
				$row = $ds->newRow();
				$row->setValue('msg_id', $msgId);
				$row->setValue('recipient_id', $cc);
				$row->setValue('recipient_type', 'C');
				$row->setValue('status', Message::StatusUnread);
			}

			foreach ($rcpt->bcc as $bcc) {
				$row = $ds->newRow();
				$row->setValue('msg_id', $msgId);
				$row->setValue('recipient_id', $bcc);
				$row->setValue('recipient_type', 'B');
				$row->setValue('status', Message::StatusUnread);
			}
			#endregion

			foreach ($ds->rows as $row) {
				$status = $row->save();
				if ($status->isOK()) {
					if ($options->method == Message::SendInternally && $options->notify == true) {
						$s = new Snippet ();
						$s->loadContentFromDb('system-messaging-new-message-notification-email');
						$s->params->subject = $subject;
						$s->params->senderName = trim($this->user->firstName . ' ' . $this->user->lastName);
						$s->params->senderEmail = $this->user->email;
						$msg = $s->compile();

						$status = Net::sendMail(CMS::app()->systemEmail, $this->user->email, $s->title, $msg);
					}
				}
			}
		}

		return $status;
	}
	#endregion

	#region Static methods
	/** Gets/sets Messaging system's database tag
	 *
	 * @param string $tag (optional) If provided, sets the Messaging system's database tag
	 *
	 * @return Database
	 */
	public static function db($tag = null) {
		if (isset ($tag)) {
			static::$_dbTag = $tag;
		}

		return CMS::db(static::$_dbTag);
	}
	#endregion
}
