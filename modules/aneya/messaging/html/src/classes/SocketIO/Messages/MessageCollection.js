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

import {Message} from './Message.js'

/**
 * @class
 */
export class MessageCollection {
	// region Constructor
	/**
	 * @constructor
	 * @param {Object[]|Message[]} items
	 */
	constructor (items = undefined) {
		/** @type {Message[]} */
		this.all = []

		if (Array.isArray(items)) {
			for (let num = 0; num < items.length; num++) {
				this.add(items[num])
			}
		}
	}
	// endregion

	// region Getters / setters
	/**
	 * Returns the number of items in the collection.
	 * @returns {number}
	 */
	get length () {
		return this.all.length
	}

	/**
	 * Returns the latest message found in the collection.
	 * @returns {number}
	 */
	get latest () {
		return this.length > 0
			? this.sorted[this.sorted.length - 1]
			: null
	}

	/**
	 * Returns messages from oldest to newest
	 * @return {Message[]}
	 */
	get sorted () {
		return this.all.slice(0).sort((a, b) => { return a.dateSent < b.dateSent ? -1 : (a.dateSent > b.dateSent ? 1 : 0) })
	}
	// endregion

	// region Methods
	/**
	 * Adds an item into the collection.
	 * @param {Object|Message} item
	 * @returns {MessageCollection}
	 */
	add (item) {
		if (item instanceof Message) {
			this.all.push(item)
		} else {
			this.all.push(new Message(item))
		}

		return this
	}

	/**
	 * Adds an array of items into the collection.
	 * @param {Object[]|Message[]} items
	 * @returns {MessageCollection}
	 */
	addRange (items) {
		if (Array.isArray(items)) {
			for (let num = 0; num < items.length; num++) {
				this.add(items[num])
			}
		}

		return this
	}

	/**
	 * Clears the collection.
	 * @returns {MessageCollection}
	 */
	clear () {
		this.all = []
		return this
	}

	/**
	 * Returns true if the given Message object (or id) exists in the collection.
	 * @param {Message|string} item
	 * @returns {boolean}
	 */
	exists (item) {
		const id = (item instanceof Object) ? item.id : item

		for (let num = 0; num < this.all.length; num++) {
			if (this.all[num] === item || this.all[num].id === id) {
				return true
			}
		}

		return false
	}

	/**
	 * Returns the Message instance that has the id specified in the arguments.
	 * @param {number} id
	 * @returns {?Message}
	 */
	find (id) {
		for (let num = 0; num < this.all.length; num++) {
			if (this.all[num].id === id) {
				return this.all[num]
			}
		}

		return null
	}

	/**
	 * Removes the Message (given its instance or id) from the collection.
	 * @param {Message|string} item
	 * @returns {MessageCollection}
	 */
	remove (item) {
		const id = (item instanceof Object && item != null) ? item.id : item

		for (let num = 0; num < this.all.length; num++) {
			if (this.all[num] === item || this.all[num].id === id) {
				this.all.splice(num, 1)
				return this
			}
		}

		return this
	}

	/**
	 * Returns all messages unread by given user in the given discussion.
	 * @param {number|Contact} user
	 * @return {MessageCollection}
	 */
	unread (user) {
		const userId = typeof user === 'object' && user != null ? user.id : Number(user)
		const messages = this.all.filter(m => m.sender !== userId && [Message.StatusSending, Message.StatusSent, Message.StatusReceived].indexOf(m.status[userId]) >= 0)

		return new MessageCollection(messages)
	}
	// endregion
}
