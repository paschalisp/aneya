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

import {CollectionException} from '../Core/CollectionException'
import {File} from './File'

/**
 * @class FileCollection
 * @property {File[]} all
 */
export class FileCollection {
	// region Constructor
	/**
	 * @constructor
	 * @param {Object[]|File[]} data
	 */
	constructor (data = undefined) {
		/** @type {File[]} all */
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
	 * @param {Object|File} item
	 * @returns {FileCollection}
	 */
	add (item) {
		if (item instanceof File) {
			this.all.push(item)
		} else {
			if (File.isValid(item)) {
				this.all.push(new File(item))
			} else {
				throw new CollectionException('Only File (or compatible) objects can be added in this collection')
			}
		}

		return this
	}

	/**
	 * Adds an array of items into the collection.
	 * @param {Object[]|File[]} items
	 * @returns {FileCollection}
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
	 * @returns {FileCollection}
	 */
	clear () {
		this.all = []
		return this
	}

	/**
	 * Returns true if the collection contains the file instance or name or hash specified in the arguments.
	 * @param {File|string} item File instance, base name or hash
	 * @returns {boolean}
	 */
	contains (item) {
		for (let num = 0; num < this.all.length; num++) {
			if (this.all[num] === item || this.all[num].name === item || this.all[num].hash === item) {
				return true
			}
		}

		return false
	}

	/**
	 * Returns the instance that has the name or hash specified in the arguments.
	 * @param {string} item
	 * @returns {File}
	 */
	find (item) {
		for (let num = 0; num < this.all.length; num++) {
			if (this.all[num].name === item || this.all[num].hash === item) {
				return this.all[num]
			}
		}

		return null
	}

	/**
	 * Removes the item (or given its name or hash) from the collection.
	 * @param {File|string} item
	 * @returns {FileCollection}
	 */
	remove (item) {
		for (let num = 0; num < this.all.length; num++) {
			if (this.all[num] === item || this.all[num].name === item || this.all[num].hash === item) {
				this.all.splice(num, 1)
			}
		}

		return this
	}
	// endregion
}
