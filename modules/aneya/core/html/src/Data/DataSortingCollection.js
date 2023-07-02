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
import {DataSorting} from './DataSorting'

/**
 * @class
 * @property {DataSorting[]} all All items in the collection.
 */
export class DataSortingCollection {
	// region Constructor
	/**
	 * @constructor
	 * @param {Object[]|DataSorting[]} data
	 */
	constructor (data = undefined) {
		/** @type {DataSorting[]} */
		this.all = []

		if (Array.isArray(data)) {
			for (let num = 0; num < data.length; num++) {
				this.add(data[num])
			}
		}

		// Sort items to force re-indexing of orders
		this.sort()
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
	 * Returns the maximum priority order found in the collection.
	 * @returns {number}
	 */
	get maxOrder () {
		let max = 0

		for (let num = 0; num < this.all.length; num++) {
			if (this.all[num].order > max)
				max = this.all[num].order
		}

		return max
	}
	// endregion

	// region Methods
	/**
	 * Adds a sorting into the collection.
	 * @param {Object|DataSorting} item
	 * @returns {DataSortingCollection}
	 */
	add (item) {
		if (item instanceof DataSorting) {
			this.all.push(item)
		} else {
			if (DataSorting.isValid(item)) {
				if (item.order == null)
					item.order = this.length > 0 ? this.maxOrder + 1 : 0

				this.all.push(new DataSorting(item.column, item.direction, item.order))
			} else {
				throw new CollectionException('Only DataSorting (or compatible) objects can be added in this collection')
			}
		}

		return this
	}

	/**
	 * Adds an array of items into the collection.
	 * @param {Object[]|DataSorting[]} items
	 * @returns {DataSortingCollection}
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
	 * @returns {DataSortingCollection}
	 */
	clear () {
		this.all = []
		return this
	}

	/**
	 * Returns true if the given sorting (or column name) exists in the collection.
	 * @param {DataSorting|string} item
	 * @returns {boolean}
	 */
	exists (item) {
		const column = (item instanceof Object) ? item.column : item

		for (let num = 0; num < this.all.length; num++) {
			if (this.all[num].column === column) {
				return true
			}
		}

		return false
	}

	/**
	 * Returns the DataSorting instance that has the column name specified in the arguments.
	 * @param {string} column
	 * @returns {DataSorting}
	 */
	find (column) {
		for (let num = 0; num < this.all.length; num++) {
			if (this.all[num].column === column) {
				return this.all[num]
			}
		}

		return null
	}

	/**
	 * Removes the sorting specified in the arguments from the collection.
	 * @param {DataSorting|string} item
	 * @returns {DataSortingCollection}
	 */
	remove (item) {
		const column = (item instanceof Object) ? item.column : item

		for (let num = 0; num < this.all.length; num++) {
			if (this.all[num] === item || this.all[num].column === column) {
				this.all.splice(num, 1)
			}
		}

		return this
	}

	/**
	 * Sorts the collection based on items' ordering.
	 * @returns {DataSortingCollection}
	 */
	sort () {
		this.all.sort((a, b) => { return a.order <= b.order ? -1 : 1 })

		for (let num = 0; num < this.length; num++)
			this.all[num].order = num

		return this
	}
	// endregion
}
