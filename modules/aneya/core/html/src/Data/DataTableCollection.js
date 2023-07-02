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
import {DataTable} from './DataTable'

/**
 * @class
 * @property {DataTable[]} all All items in the collection.
 */
export class DataTableCollection {
	// Constructor
	/**
	 * @constructor
	 * @param {Object[]|DataTable[]} data
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

	// region Getters / Setters
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
		return (last instanceof DataTable) ? last.id + 1 : 1
	}
	// endregion

	// region Methods
	/**
	 * Adds a filter into the collection.
	 * @param {DataTable} item
	 * @returns {DataTableCollection}
	 */
	add (item) {
		if (item instanceof DataTable) {
			// Disallow multiple entries of the same instance
			if (this.all.indexOf(item) < 0)
				this.all.push(item)
		} else {
			if (DataTable.isValid(item)) {
				this.all.push(item = new DataTable(item))
			} else {
				throw new CollectionException('Only DataTable (or compatible) objects can be added in this collection')
			}
		}

		if (item.id == null)
			item.id = this.nextId

		return this
	}

	/**
	 * Adds an array of items into the collection.
	 * @param {Object[]|DataTable[]} items
	 * @returns {DataTableCollection}
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
	 * @returns {DataTableCollection}
	 */
	clear () {
		this.all = []
		return this
	}

	/**
	 * Returns true if the given row exists in the collection.
	 * @param {DataTable|string|number} item
	 * @returns {boolean}
	 */
	exists (item) {
		const idName = (item instanceof Object) ? (item.id > 0 ? item.id : item.name) : item

		for (let num = 0; num < this.all.length; num++) {
			if (this.all[num] === item || this.all[num].id === idName || this.all[num].name === idName) {
				return true
			}
		}

		return false
	}

	/**
	 * Returns the DataTable matching the given id or name or alias
	 *
	 * @param {string|number} idName
	 * @param {?string} schema
	 * @returns {DataTable}
	 */
	find (idName, schema = undefined) {
		for (let num = 0; num < this.all.length; num++) {
			if ((schema == null || this.all[num].schema === schema) && (this.all[num].id === idName || this.all[num].name === idName || this.all[num].alias === idName)) {
				return this.all[num]
			}
		}

		return null
	}

	/**
	 * Removes the row specified in the arguments from the collection.
	 * @param {DataTable} item
	 * @returns {DataTableCollection}
	 */
	remove (item) {
		const idName = (item instanceof Object) ? (item.id > 0 ? item.id : item.name) : item

		for (let num = 0; num < this.all.length; num++) {
			if (this.all[num] === item || this.all[num].id === idName || this.all[num].name === idName) {
				this.all.splice(num, 1)
				return this
			}
		}

		return this
	}
	// endregion
}
