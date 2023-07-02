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

import {v4 as uuid} from 'uuid'
import {Application} from '../../../../../../appstyle/html/src/classes/Application/Application.js'

/**
 * @class
 * @property {number|string} id
 * @property {number} sender
 * @property {number[]} recipients
 * @property {string} content
 * @property {string} contentType
 * @property {Date} dateSent
 * @property {string[]} status
 */
export class Message {
	// region Constants
	static get MediaFile () { return 'file' }
	static get MediaText () { return 'txt' }

	static get StatusNone () { return '-' }
	static get StatusSending () { return '..' }
	static get StatusSent () { return '>' }
	static get StatusReceived () { return '>|' }
	static get StatusRead () { return 'oo' }
	// endregion

	// region Construction
	constructor (cfg = undefined) {
		this.applyCfg(cfg)
	}

	applyCfg (cfg = {}) {
		this.id = cfg.id || uuid()
		this.sender = cfg.sender || null
		this.recipients = Array.isArray(cfg.recipients) ? cfg.recipients.map(p => Number(p)) : (cfg.recipients && cfg.recipients.length > 0 ? cfg.recipients.split(',').map(p => Number(p)) : [])
		this.content = cfg.content || ''
		this.contentType = cfg.contentType || Message.MediaText
		this.dateSent = (cfg.dateSent instanceof Date) ? cfg.dateSent : (cfg.dateSent && cfg.dateSent.length > 0 ? new Date(cfg.dateSent) : new Date())
		this.status = cfg.status || {}
	}
	// endregion

	// region Getters/setters
	get contentPreview () {
		return this.content.substr(0, 30)
	}

	/**
	 * Returns message's participants ordered by user id.
	 * @return {number[]}
	 */
	get participants () {
		return this.recipients.slice(0).concat(this.sender).sort()
	}

	/**
	 * Returns message's discussion Id
	 * @return {string}
	 */
	get discussionId () {
		return this.participants.join(',')
	}

	/**
	 * Returns message in a storable document format
	 * @return {{sender: number, discussionId: string, recipients: number[], msgId: (number|string), dateSent: Date, contentType: string, content: string, status: string[]}}
	 */
	get dbDoc () {
		return {
			id: this.id,
			discussionId: this.discussionId,
			sender: this.sender,
			recipients: this.recipients,
			participants: this.participants,
			content: this.content,
			contentType: this.contentType,
			dateSent: this.dateSent,
			status: this.status
		}
	}
	// endregion

	// region Methods
	/**
	 * Gets message status for the given user
	 *
	 * @param {number} user
	 * @returns {string}
	 */
	getStatus (user) {
		return this.status[user]
	}

	/**
	 * Sets message status for an individual or all users.
	 *
	 * @param {?number} user
	 * @param {string} status
	 * @returns {Message}
	 */
	setStatus (user, status) {
		if ([Message.StatusNone, Message.StatusSending, Message.StatusSent, Message.StatusReceived, Message.StatusRead].indexOf(status) >= 0) {
			// If no user is given, or status is common to all users, set status to all users
			if ([Message.StatusNone, Message.StatusSending, Message.StatusSent].indexOf(status) >= 0 || user == null) {
				for (let num = 0; num < this.recipients.length; num++)
					Application.instance.$app.$set(this.status, this.recipients[num], status)
			}
			else
				// Set status individually for the given user
				Application.instance.$app.$set(this.status, user, status)
		}

		return this
	}
	// endregion
}
