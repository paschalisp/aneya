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

import {CollectionException} from './CollectionException.js'
import {KeyValue} from './KeyValue.js'

/**
 * @class
 * @property {KeyValue[]} all All items in the collection.
 */
export class KeyValueCollection {
	// region Constructor
	/**
	 * @constructor
	 * @param {Object[]|KeyValue[]} data
	 */
	constructor (data = undefined) {
		this.all = []

		if (Array.isArray(data)) {
			for (let num = 0; num < data.length; num++) {
				this.add(data[num])
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
	// endregion

	// region Methods
	/**
	 * Adds an item into the collection.
	 * @param {Object|KeyValue} item
	 * @returns {KeyValueCollection}
	 */
	add (item) {
		if (item instanceof KeyValue) {
			// Disallow multiple entries of the same instance
			if (this.all.indexOf(item) < 0)
				this.all.push(item)
		} else {
			if (KeyValue.isValid(item)) {
				this.all.push(new KeyValue(item))
			} else {
				throw new CollectionException('Only KeyValue (or compatible) objects can be added in this collection')
			}
		}

		return this
	}

	/**
	 * Adds an array of items into the collection.
	 * @param {Object[]|KeyValue[]} items
	 * @returns {KeyValueCollection}
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
	 * @returns {KeyValueCollection}
	 */
	clear () {
		this.all = []
		return this
	}

	/**
	 * Returns true if the given KeyValue object (or key or id) exists in the collection.
	 * @param {KeyValue|string|number} item
	 * @returns {boolean}
	 */
	exists (item) {
		const idTag = (typeof item === 'object') ? (item.id > 0 ? item.id : item.key) : item

		for (let num = 0; num < this.all.length; num++) {
			if (this.all[num] === item || this.all[num].id === idTag || this.all[num].key === idTag) {
				return true
			}
		}

		return false
	}

	/**
	 * Returns the KeyValue instance that has the key or id specified in the arguments.
	 * @param {string|number} idKey
	 * @returns {KeyValue}
	 */
	find (idKey) {
		for (let num = 0; num < this.all.length; num++) {
			if (this.all[num].key === idKey || this.all[num].id === idKey) {
				return this.all[num]
			}
		}

		return null
	}

	/**
	 * Removes the item (given its instance or key or id) from the collection.
	 * @param {KeyValue|string|number} item
	 * @returns {KeyValueCollection}
	 */
	remove (item) {
		const idTag = (typeof item === 'object') ? (item.id > 0 ? item.id : item.key) : item

		for (let num = 0; num < this.all.length; num++) {
			if (this.all[num] === item || this.all[num].id === idTag || this.all[num].key === idTag) {
				this.all.splice(num, 1)
				return this
			}
		}

		return this
	}

	/**
	 * Returns the value of the key or id specified in the arguments.
	 * @param {string|number} idKey
	 * @returns {any}
	 */
	value (idKey) {
		for (let num = 0; num < this.all.length; num++) {
			if (this.all[num].key === idKey || this.all[num].id === idKey) {
				return this.all[num].value
			}
		}

		return null
	}
	// endregion
}
