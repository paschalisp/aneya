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
import {MessageCollection} from './MessageCollection.js'

/**
 * @class
 * @property {number[]} participants
 * @property {MessageCollection} messages
 * @property {boolean} synced
 */
export class Discussion {
	// region Constants
	// endregion

	// region Construction
	constructor (cfg = undefined) {
		this.applyCfg(cfg)
	}

	applyCfg (cfg = {}) {
		this.participants = Array.isArray(cfg.participants)
			? cfg.participants.map(p => Number(p))
			: (cfg.participants && cfg.participants.length > 0
				? [cfg.participants.split(',').map(p => Number(p))]
				: []
			)

		this.messages = cfg.messages instanceof MessageCollection
			? cfg.messages
			: (Array.isArray(cfg.messages)
				? new MessageCollection(cfg.messages)
				: new MessageCollection()
			)

		this.synced = Boolean(cfg.synced)
	}
	// endregion

	// region Getters/setters
	/**
	 * Returns the auto-generated identifier for the discussion.
	 * @returns {string}
	 */
	get id () {
		return this.participants.slice(0).sort().join(',')
	}
	// endregion

	// region Methods
	/**
	 * Returns the ids of all participants except the id that is passed as sender.
	 * @param {number} sender
	 * @return {number[]}
	 */
	recipients (sender) {
		return this.participants.filter(p => p !== sender)
	}

	/**
	 * Returns true if discussion contains unread messages for the given user.
	 * @param {number|Contact} user
	 * @return {boolean}
	 */
	unread (user) {
		const userId = typeof user === 'object' && user != null ? user.id : Number(user)
		return this.messages.all
			.filter(m => m.sender !== userId)
			.map(m => m.status[userId])
			.filter(status => [Message.StatusSending, Message.StatusSent, Message.StatusReceived].indexOf(status) >= 0).length > 0
	}
	// endregion
}
