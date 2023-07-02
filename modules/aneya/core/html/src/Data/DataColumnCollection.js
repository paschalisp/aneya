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
import {DataColumn} from './DataColumn'

/**
 * @class
 * @property {DataColumn[]} all All items in the collection.
 */
export class DataColumnCollection {
	// region Constructor
	/**
	 * @constructor
	 * @param {Object[]|DataColumn[]} data
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

	/**
	 * Returns the next item id that is available in the collection.
	 * @returns {number}
	 */
	get nextId () {
		const last = this.all.sort((a, b) => { return a.id < b.id ? 1 : (a.id > b.id ? -1 : 0) })[0]
		return (last instanceof DataColumn) ? last.id + 1 : 1
	}
	// endregion

	// region Methods
	/**
	 * Adds an item into the collection.
	 * @param {Object|DataColumn} item
	 * @returns {DataColumnCollection}
	 */
	add (item) {
		if (item instanceof DataColumn) {
			// Disallow multiple entries of the same instance
			if (this.all.indexOf(item) < 0)
				this.all.push(item)
		} else {
			if (DataColumn.isValid(item)) {
				this.all.push(item = new DataColumn(item))
			} else {
				throw new CollectionException('Only DataColumn (or compatible) objects can be added in this collection')
			}
		}

		if (item.id == null)
			item.id = this.nextId

		return this
	}

	/**
	 * Adds an array of items into the collection.
	 * @param {Object[]|DataColumn[]} items
	 * @returns {DataColumnCollection}
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
	 * @returns {DataColumnCollection}
	 */
	clear () {
		this.all = []
		return this
	}

	/**
	 * Returns true if the given column object (or column id or tag) exists in the collection.
	 * @param {DataColumn|string|number} item
	 * @returns {boolean}
	 */
	exists (item) {
		const idTag = (item instanceof Object) ? (item.id > 0 ? item.id : item.tag) : item

		for (let num = 0; num < this.all.length; num++) {
			if (this.all[num] === item || this.all[num].id === idTag || this.all[num].tag === idTag) {
				return true
			}
		}

		return false
	}

	/**
	 * Returns the DataColumn instance that has the column id or tag specified in the arguments.
	 * @param {string|number} idTag
	 * @returns {DataColumn}
	 */
	find (idTag) {
		for (let num = 0; num < this.all.length; num++) {
			if (this.all[num].id === idTag || this.all[num].tag === idTag) {
				return this.all[num]
			}
		}

		return null
	}

	/**
	 * Removes the column (given its instance or column id or tag) from the collection.
	 * @param {DataColumn|string|number} item
	 * @returns {DataColumnCollection}
	 */
	remove (item) {
		const idTag = (item instanceof Object) ? (item.id > 0 ? item.id : item.tag) : item

		for (let num = 0; num < this.all.length; num++) {
			if (this.all[num] === item || this.all[num].id === idTag || this.all[num].tag === idTag) {
				this.all.splice(num, 1)
				return this
			}
		}

		return this
	}
	// endregion
}
