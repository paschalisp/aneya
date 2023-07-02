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
import {DataTableRelation} from './DataTableRelation'

/**
 * @class
 * @property {DataTableRelation[]} all All items in the collection.
 */
export class DataTableRelationCollection {
	// Constructor
	/**
	 * @constructor
	 * @param {Object[]|DataTableRelation[]} data
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
	// endregion

	// region Methods
	/**
	 * Adds a filter into the collection.
	 * @param {DataTableRelation} item
	 * @returns {DataTableRelationCollection}
	 */
	add (item) {
		if (item instanceof DataTableRelation) {
			this.all.push(item)
		} else {
			if (DataTableRelation.isValid(item)) {
				this.all.push(new DataTableRelation(item))
			} else {
				throw new CollectionException('Only DataTableRelation (or compatible) objects can be added in this collection')
			}
		}
	}

	/**
	 * Adds an array of items into the collection.
	 * @param {Object[]|DataTableRelation[]} items
	 * @returns {DataTableRelationCollection}
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
	 * @returns {DataTableRelationCollection}
	 */
	clear () {
		this.all = []
		return this
	}

	/**
	 * Returns true if the given row exists in the collection.
	 * @param {DataTableRelation} item
	 * @returns {boolean}
	 */
	exists (item) {
		for (let num = 0; num < this.all.length; num++) {
			if (this.all[num] === item) {
				return true
			}
		}

		return false
	}

	/**
	 * Removes the row specified in the arguments from the collection.
	 * @param {DataTableRelation} item
	 * @returns {DataTableRelationCollection}
	 */
	remove (item) {
		for (let num = 0; num < this.all.length; num++) {
			if (this.all[num] === item) {
				this.all.splice(num, 1)
			}
		}

		return this
	}
	// endregion
}
