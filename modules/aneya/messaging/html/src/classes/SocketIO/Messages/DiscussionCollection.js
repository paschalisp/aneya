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

import {Discussion} from './Discussion.js'

/**
 * @class
 */
export class DiscussionCollection {
	// region Constructor
	/**
	 * @constructor
	 * @param {Object[]|Discussion[]} items
	 */
	constructor (items = undefined) {
		/** @type {Discussion[]} */
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
	 * Returns an array of discussions ordered from newest to oldest
	 * @return {Discussion[]}
	 */
	get sorted () {
		return this.all.slice(0).sort((a, b) => {
			if (a.messages.latest == null)
				return 1
			else if (b.messages.latest == null)
				return -1
			else
				return a.messages.latest.dateSent < b.messages.latest.dateSent ? -1 : (a.messages.latest.dateSent > b.messages.latest.dateSent ? 1 : 0)
		})
	}
	// endregion

	// region Methods
	/**
	 * Adds an item into the collection.
	 * @param {Object|Discussion} item
	 * @returns {DiscussionCollection}
	 */
	add (item) {
		if (item instanceof Discussion) {
			this.all.push(item)
		} else {
			this.all.push(new Discussion(item))
		}

		return this
	}

	/**
	 * Adds an array of items into the collection.
	 * @param {Object[]|Discussion[]} items
	 * @returns {DiscussionCollection}
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
	 * @returns {DiscussionCollection}
	 */
	clear () {
		this.all = []
		return this
	}

	/**
	 * Returns true if the given Discussion object (or id) exists in the collection.
	 * @param {Discussion|string} item
	 * @returns {boolean}
	 */
	exists (item) {
		const id = (item instanceof Object && item != null) ? item.id : item

		for (let num = 0; num < this.all.length; num++) {
			if (this.all[num] === item || this.all[num].id === id) {
				return true
			}
		}

		return false
	}

	/**
	 * Returns the Discussion instance that has the id specified in the arguments.
	 * @param {string} id
	 * @returns {?Discussion}
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
	 * Removes the Discussion (given its instance or id) from the collection.
	 * @param {Discussion|string} item
	 * @returns {DiscussionCollection}
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
	// endregion
}
