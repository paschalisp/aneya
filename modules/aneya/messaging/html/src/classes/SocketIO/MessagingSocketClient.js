/**
 * aneya CMS & Framework
 * Copyright (c) 2007-2022 Paschalis Pagonidis <p.pagonides@gmail.com>
 * All rights reserved.
 * -----------------------------------------------------------------------------
 * PLEASE READ  THE FULL TEXT OF SOFTWARE LICENSE AGREEMENT IN  THE "COPYRIGHT"
 * FILE PROVIDED WITH  THIS DISTRIBUTION. THE AGREEMENT TEXT IS ALSO AVAILABLE
 * AT THE FOLLOWING URL: http://www.aneyacms.com/en/pages/license
 *
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

import {Contact} from './Contacts/Contact.js'
import {ContactCollection} from './Contacts/ContactCollection.js'
import {Discussion} from './Messages/Discussion.js'
import {DiscussionCollection} from './Messages/DiscussionCollection.js'
import {Message} from './Messages/Message.js'
import {SocketIoClientApp} from '../../../../../socketio/node/src/classes/SocketIoClientApp.js'

/**
 * @class MessagingSocketClient
 * @description Chat/messaging client socket.io class
 * @property {object} cfg
 * @property {ContactCollection} contacts
 * @property {DiscussionCollection} discussions
 */
export class MessagingSocketClient extends SocketIoClientApp {
	// region Constants
	static get EventOnReceived () { return 'OnMessagingReceived' }
	static get EventOnSending () { return 'OnMessagingSending' }
	static get EventOnSent () { return 'OnMessagingSent' }
	static get EventOnTyping () { return 'OnMessagingTyping' }
	static get EventOnUpdated () { return 'OnMessagingUpdated' }
	// endregion

	// region Construction & initialization
	/**
	 * @param {string} namespace
	 * @param {?Object} options
	 */
	constructor (namespace, options = undefined) {
		super(namespace, options)

		this.contacts = new ContactCollection()
		this.discussions = new DiscussionCollection()
	}

	/**
	 * @param {Object} options
	 */
	init (options) {
		super.init(options)
	}

	async start () {
		await super.start()

		this.socket.on('contact.status', (id, status) => {
			const contact = this.contacts.find(id)
			if (contact instanceof Contact)
				contact.status = status
		})

		this.socket.on('receive', async (id, msg) => {
			const message = new Message(msg)
			let discussion = this.discussions.find(id)
			if (discussion == null) {
				discussion = new Discussion({ participants: id.split(',').map(p => Number(p)), messages: [] })
				this.discussions.add(discussion)
			}

			// Add new message to discussion
			discussion.messages.add(message)

			await this.OnReceived(id, message)
			this.emit(MessagingSocketClient.EventOnReceived, id, message)
		})

		this.socket.on('status', async (id, msgId, userId, status) => {
			const discussion = this.discussions.find(id)
			if (discussion == null)
				return

			const message = discussion.messages.find(msgId)
			if (message) {
				message.setStatus(userId, status)

				await this.OnUpdated(id, message, userId, status)
				this.emit(MessagingSocketClient.EventOnUpdated, id, message, userId, status)
			}
		})
	}
	// endregion

	// region Getters / setters
	/**
	 * Returns all discussions containing unread messages.
	 * @return {Discussion[]}
	 */
	get discussionsUnread () {
		return this.discussions.all.filter(d => d.unread(this.user.id))
	}
	// endregion

	// region Event methods
	/**
	 * Triggered when a message has been received in the given discussion.
	 * @param {string} id Discussion id
	 * @param {Message} message
	 */
	async OnReceived (id, message) {
		message.setStatus(this.user.id, Message.StatusReceived)
		await this.acknowledge(id, message.id, Message.StatusReceived)
	}

	/**
	 * Triggered when a message is being sent to the messaging server.
	 * @param {string} id Discussion id
	 * @param {Message} message
	 * @constructor
	 */
	async OnSending (id, message) {}

	/**
	 * Triggered when a message has being successfully sent to the messaging server.
	 * @param {string} id Discussion id
	 * @param {Message} message
	 * @constructor
	 */
	async OnSent (id, message) {}

	/**
	 * Triggered when a message has being updated, usually when a participant acknowledges read status on the message.
	 * @param {string} id Discussion id
	 * @param {Message} message The updated message
	 * @param {number} userId The id of the user whose status on message changed.
	 * @param {string} status The new message status for the given user.
	 */
	async OnUpdated (id, message, userId, status) {}

	/**
	 * Triggered when another participant is typing in a discussion.
	 * @param {string} id Discussion id
	 * @constructor
	 */
	async OnTyping (id) {}
	// endregion

	// region Auxiliary methods
	/**
	 * Fetches and returns all Contacts associated with the client socket.
	 * @return {Promise<Contact[]>}
	 */
	async requestContacts () {
		return new Promise((resolve) => {
			this.socket.emit('contacts.list', (contacts) => {
				this.contacts.clear().addRange(contacts)

				resolve(this.contacts.all)
			})
		})
	}

	/**
	 * Fetches and returns all Discussions the client socket is participating to.
	 * @return {Promise<Discussion[]>}
	 */
	async requestDiscussions () {
		return new Promise((resolve) => {
			this.socket.emit('discussions', (discussions) => {
				this.discussions.clear().addRange(discussions)

				resolve(this.discussions.all)
			})
		})
	}
	// endregion

	// region Chat methods
	/**
	 * Acknowledges given message to the given status
	 * @param {string} id
	 * @param {string} msgId
	 * @param {string} status
	 * @return {Promise<void>}
	 */
	async acknowledge (id, msgId, status) {
		await this.socket.emit('acknowledge', id, msgId, status)
	}

	/**
	 * Fetches and returns all messages associated to the given discussion.
	 * @param {string} id Discussion id
	 * @param since
	 * @return {Promise<Message[]>}
	 */
	async fetch (id, since = undefined) {
		return new Promise((resolve) => {
			const discussion = this.discussions.find(id)
			if (discussion == null) {
				resolve([])
				return
			}

			this.socket.emit('fetch', id, false, (messages) => {
				// Refresh discussion with fetched messages
				discussion.messages.clear().addRange(messages)

				// region Acknowledge 'received' to all new messages
				const newMessages = discussion.messages.all.filter(m => [Message.StatusNone, Message.StatusSending, Message.StatusSent].indexOf(m.status[this.user.id]) >= 0)
				for (let num = 0; num < newMessages.length; num++) {
					// Change message's status
					newMessages[num].setStatus(this.user.id, Message.StatusReceived)

					this.acknowledge(id, newMessages[num].id, Message.StatusReceived)
				}
				// endregion

				resolve(messages)
			})
		})
	}

	/**
	 * Sends the message to all participants in the discussion (except sender).
	 * @param {string} id Discussion id
	 * @param {Message} message
	 * @return {Promise<any>}
	 */
	async send (id, message) {
		return new Promise((resolve) => {
			message.setStatus(undefined, Message.StatusSending)
			this.OnSending(id, message)
			this.emit('sending', id, message)

			// region Add a new discussion in discussions collection, if not already
			let discussion = this.discussions.find(id)
			if (discussion == null) {
				discussion = new Discussion({participants: id.split(',').map(p => Number(p)), messages: [message]})
				this.discussions.add(discussion)
			}
			// endregion

			this.socket.emit('send', id, message, () => {
				message.setStatus(undefined, Message.StatusSent)
				this.OnSent(id, message)
				this.emit('sent', id, message)

				resolve()
			})
		})
	}
	// endregion
}
