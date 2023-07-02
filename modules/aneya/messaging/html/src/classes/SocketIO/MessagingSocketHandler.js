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

import {MongoClient} from 'mongodb'
import {Contact} from './Contacts/Contact.js'
import {ContactCollection} from './Contacts/ContactCollection.js'
import {Message} from './Messages/Message.js'
import {SocketIoNamespaceHandler} from '../../../../../socketio/node/src/classes/SocketIoNamespaceHandler.js'
import {SocketIoServerApp} from '../../../../../socketio/node/src/classes/SocketIoServerApp.js'
import {SocketIoUser} from '../../../../../socketio/node/src/classes/SocketIoUser.js'

/**
 * @class MessagingSocketHandler
 * @description Chat server class
 * @property {API} api
 * @property {object} cfg
 * @property {object} db
 * @property {object} dbCol MongoDb discussions collection
 */
export class MessagingSocketHandler extends SocketIoNamespaceHandler {
	// region Constants
	static get EventOnSending () { return 'OnMessagingSending' }
	static get EventOnSent () { return 'OnMessagingSent' }
	static get EventOnReceived () { return 'OnMessagingReceived' }
	static get EventOnTyping () { return 'OnMessagingTyping' }
	// endregion

	// region Construction & initialization
	/**
	 * @param {SocketIoServerApp} server
	 * @param {string} namespace
	 * @param {?Object} options
	 */
	constructor (server, namespace = '', options = undefined) {
		super(server, namespace, options)

		this.__mongoClient = null
	}

	/**
	 * @param {Object} options
	 */
	init (options) {
		super.init(options)
	}

	/**
	 * Stops listening for chat connections
	 */
	stop () {
		this.__mongoClient.close()
	}
	// endregion

	// region Getters/setters
	// endregion

	// region Event methods
	async OnSending () {}

	/**
	 * Starts listening for chat connections
	 */
	async OnStarting () {
		await super.OnStarting()

		this.__mongoClient = new MongoClient(this.cfg.mongodb.url, this.cfg.mongodb.options)

		try {
			await this.__mongoClient.connect()
			this.db = this.__mongoClient.db(this.cfg.mongodb.db)
			this.dbCol = this.db.collection(this.cfg.mongodb.collection)

			this.io.on('connect', (socket) => {
				socket.on('fetch', async (id, since, callback) => { await this.fetch(socket, id, since, callback) })
				socket.on('typing', async (to) => { await this.typing(socket, to) })
				socket.on('send', async (id, message, callback) => { await this.send(socket, id, new Message(message), callback) })
				socket.on('acknowledge', async (id, msgId, status, callback) => { await this.acknowledge(socket, id, msgId, status, callback) })

				socket.on('contacts.list', async (callback) => { await this.contacts(socket, 'list', callback) })
				socket.on('contacts.status', async (callback) => { await this.contacts(socket, 'status', callback) })
				socket.on('discussions', async (callback) => { await this.discussions(socket, callback) })
			})
		}
		catch (err) {
			let error = new Error('Connection to database failed. ' + err.message)
			this.emit(SocketIoServerApp.EventOnError, error)
		}
	}

	async OnAuthenticated (socket, user) {
		await super.OnAuthenticated(socket, user)

		// region Update all user's online contacts about user's online status
		await this.contacts(socket, 'list')
			.then((contacts) => {
				for (let num = 0; num < contacts.length; num++) {
					let usr = this.userById(contacts[num].id)
					let sockt2 = usr instanceof SocketIoUser ? this.__sockets[usr.socketId] : undefined
					if (sockt2) {
						sockt2.emit('contact.status', user.id, Contact.StatusOnline)
					}
				}
			})
		// endregion

		return this
	}

	async OnDisconnecting (socket, user) {
		await super.OnDisconnecting(socket, user)

		if (user instanceof SocketIoUser) {
			// region Update all user's online contacts about user's offline status
			await this.contacts(socket, 'list')
				.then((contacts) => {
					for (let num = 0; num < contacts.length; num++) {
						let usr = this.userById(contacts[num].id)
						let sockt2 = usr instanceof SocketIoUser ? this.__sockets[usr.socketId] : undefined
						if (sockt2) {
							sockt2.emit('contact.status', user.id, Contact.StatusOffline)
						}
					}
				})
			// endregion
		}

		return this
	}

	async OnSigningOut (socket, user) {
		await super.OnSigningOut(socket, user)

		// region Update all user's online contacts about user's offline status
		await this.contacts(socket, 'list')
			.then((contacts) => {
				for (let num = 0; num < contacts.length; num++) {
					let usr = this.userById(contacts[num].id)
					let sockt2 = usr instanceof SocketIoUser ? this.__sockets[usr.socketId] : undefined
					if (sockt2) {
						sockt2.emit('contact.status', user.id, Contact.StatusOffline)
					}
				}
			})
		// endregion

		return this
	}
	// endregion

	// region Methods
	/**
	 *
	 * @param {Socket} socket
	 * @param {string} action (list | status)
	 * @param {?function} callback
	 * @return {Promise<any>}
	 */
	async contacts (socket, action, callback) {
		return new Promise((resolve) => {
			/** @type {?SocketIoUser} user */
			let user = this.user(socket.client.id)

			switch (action) {
				case 'list':
					// If socket isn't yet authenticated, silently ignore the request and return an empty array
					if (user == null) {
						if (typeof callback === 'function')
							callback([])	// eslint-disable-line standard/no-callback-literal

						resolve([])
					}

					// if user already has contacts fetched, resolve directly
					if (user.hasOwnProperty('contacts') && user.contacts instanceof ContactCollection) {
						if (typeof callback === 'function')
							callback(user.contacts.all)

						resolve(user.contacts.all)
					}
					else {
						// If didn't already fetch user's contacts, retrieve contacts from server and cache them locally
						user.contacts = new ContactCollection()

						let config = {
							headers: {
								'X-Api-Token': user.token
							}
						}
						// Fetch contacts from server
						this.server.axios.get(`${this.server.authAddress}${this.cfg.apiUrl}/contacts`, config)
							.then((response) => {
								// Store socket's contacts locally
								user.contacts.addRange(response.data.contacts)

								for (let num = 0; num < user.contacts.length; num++) {
									user.contacts.all[num].status = (this.userById(user.contacts.all[num].id) instanceof SocketIoUser)
										? Contact.StatusOnline
										: Contact.StatusOffline
								}

								if (typeof callback === 'function')
									callback(user.contacts.all)

								resolve(user.contacts.all)
							})
							.catch((err) => {
								this.server.error(`Failed to fetch contacts from URL ${this.server.authAddress}${this.cfg.apiUrl}/contacts. Error message: ${err.message}`)

								resolve(user.contacts.all)
							})
					}
					break

				case 'status':
					// If socket isn't yet authenticated, silently ignore the request and return an empty array
					if (user == null) {
						if (typeof callback === 'function')
							callback([])	// eslint-disable-line standard/no-callback-literal

						resolve([])
					}

					// If didn't already fetch user's contacts, retrieve contacts from server and cache them locally
					if (!(user.hasOwnProperty('contacts') && user.contacts instanceof ContactCollection)) {
						this.contacts(socket, 'list', callback).then((contacts) => {
							resolve(contacts)
						})
					}
					else {
						if (typeof callback === 'function')
							callback(user.contacts.all)

						resolve(user.contacts.all)
					}
					break
			}
		})
	}

	/**
	 *
	 * @param {Socket} socket
	 * @param callback
	 * @return {Promise<Discussion[]>}
	 */
	async discussions (socket, callback) {
		let discussions = []
		let user = this.user(socket.client.id)

		if (!(user instanceof SocketIoUser)) {
			if (typeof callback === 'function')
				callback(discussions)

			return discussions
		}

		// region Fetch all user's discussions
		try {
			discussions = await this.dbCol.aggregate([
				{ $match: {participants: user.id} },
				{ $sort: {discussionId: 1, dateSent: 1} },
				{ $group:
					{
						_id: '$discussionId',
						participants: { $last: '$participants' },
						messages: {
							$push: {
								id: '$id',
								discussionId: '$discussionId',
								sender: '$sender',
								recipients: '$recipients',
								participants: '$participants',
								content: '$content',
								contentType: '$contentType',
								dateSent: '$dateSent',
								status: '$status'
							}
						}
					}
				}
			]).toArray()
		}
		catch (e) {
			let error = new Error(`Error fetching discussions for user ${user.id}. ${e.message}`)
			this.emit(SocketIoServerApp.EventOnError, error)
		}
		// endregion

		if (typeof callback === 'function')
			callback(discussions)

		return discussions
	}

	/**
	 * Fetches all messages (optionally since given date) from the given discussion.
	 * @param {Socket} socket
	 * @param {string} id Discussion's id
	 * @param {?Date} since
	 * @param {?function} callback
	 * @return {Promise<*>}
	 */
	async fetch (socket, id, since, callback) {
		return new Promise(async (resolve) => {
			/** @type {?SocketIoUser} user */
			let user = this.server.authHandler.user(socket.client.id)

			// If socket isn't yet authenticated, silently ignore the request and return an empty array
			if (user == null) {
				if (typeof callback === 'function')
					callback([])	// eslint-disable-line standard/no-callback-literal

				resolve([])
			}

			/** @type {Message[]} */
			let messages = []

			// region Ensure user participates in the discussion
			if (id.split(',').indexOf(user.id.toString()) < 0) {
				this.server.error(`User ${user.id} does not participate to the requested discussion with id ${id}`)

				if (typeof callback === 'function')
					callback(messages)

				resolve(messages)
			}
			// endregion

			// region Fetch all discussion's messages from database
			try {
				messages = await this.dbCol.find({discussionId: id}).sort({dateSent: 1}).toArray()
			}
			catch (e) {
				let error = new Error(`Error fetching discussions for user ${user.id}. ${e.message}`)
				this.emit(SocketIoServerApp.EventOnError, error)
			}
			// endregion

			if (typeof callback === 'function')
				callback(messages)

			resolve(messages)
		})
	}

	/**
	 *
	 * @param {Socket} socket
	 * @param {string} id Discussion id
	 * @param {Message} message
	 * @param {function} callback
	 */
	async send (socket, id, message, callback) {
		await this.OnSending()
		this.emit(MessagingSocketHandler.EventOnSending, socket, message)

		try {
			await this.dbCol.insertOne(message.dbDoc)

			// region Additionally, send message directly to online participants
			for (let num = 0; num < message.recipients.length; num++) {
				let usr = this.userById(message.recipients[num])
				let sockt2 = usr instanceof SocketIoUser ? this.__sockets[usr.socketId] : undefined
				if (sockt2) {
					sockt2.emit('receive', id, message, async () => {
						message.setStatus(usr.id, Message.StatusReceived)
						socket.emit('status', id, message.id, usr.id, Message.StatusReceived)
					})
				}
			}
			// endregion
		}
		catch (e) {
			let error = new Error(`Error storing message with id ${message.id} in discussion ${message.discussionId} failed. ${e.message}`)
			this.emit(SocketIoServerApp.EventOnError, error)
		}
	}

	/**
	 *
	 * @param {Socket} socket
	 * @param {string} id Discussion id
	 * @param {string} msgId Message id
	 * @param {string} status Message's new status
	 * @param callback
	 * @return {Promise<void>}
	 */
	async acknowledge (socket, id, msgId, status, callback) {
		let user = this.user(socket.client.id)

		// If socket isn't yet authenticated, silently ignore the request and return an empty array
		if (user == null) {
			if (typeof callback === 'function')
				callback([])	// eslint-disable-line standard/no-callback-literal

			return
		}

		let message

		// region Update message status in database
		try {
			let ret = await this.dbCol.findOneAndUpdate(
				{
					id: msgId,
					discussionId: id
				},
				{
					$set: JSON.parse(`{"status.${user.id}": "${status}"}`)
				},
				{ returnOriginal: false }
			)
			if (ret.ok)
				message = new Message(ret.value)
			else
				throw new Error(`Could not find message with id ${msgId} in discussion ${id} to acknowledge.`)
		}
		catch (e) {
			let error = new Error(`Acknowledging message with id ${msgId} in discussion ${id} failed. ${e.message}`)
			this.emit(SocketIoServerApp.EventOnError, error)
			return
		}
		// endregion

		// region Update all other online participants about socket's acknowledgement
		let participants = message.participants.filter(p => p !== user.id)

		for (let num = 0; num < participants.length; num++) {
			let usr = this.userById(participants[num])
			let sockt2 = usr instanceof SocketIoUser ? this.__sockets[usr.socketId] : null
			if (sockt2) {
				sockt2.emit('status', id, message.id, user.id, status)
			}
		}
		// endregion
	}

	async typing (socket, to) {}
	// endregion
}
