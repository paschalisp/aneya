/**
 * infinitech ERP software
 * Copyright (c) 2007-2022 Paschalis Pagonidis <info@infinitech-intl.com>
 * All rights reserved.
 * -----------------------------------------------------------------------------
 * PLEASE READ  THE FULL TEXT OF SOFTWARE LICENSE AGREEMENT IN  THE "COPYRIGHT"
 * FILE PROVIDED WITH  THIS DISTRIBUTION. THE AGREEMENT TEXT IS ALSO AVAILABLE
 * AT THE FOLLOWING URL: http://www.infinitech-intl.com/en/pages/license-erp
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

import {User} from './User.js'
import {CollectionException} from '../Core/CollectionException.js'

/**
 * @class UserCollection
 * @property {User[]} all
 */
export class UserCollection {
	// region Constructor
	/**
	 * @constructor
	 * @param {Object[]|User[]} data
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
	 * @param {Object|User} item
	 * @returns {UserCollection}
	 */
	add (item) {
		if (item instanceof User) {
			this.all.push(item)
		} else {
			if (User.isValid(item)) {
				this.all.push(new User(item))
			} else {
				throw new CollectionException('Only User (or compatible) objects can be added in this collection')
			}
		}

		return this
	}

	/**
	 * Adds an array of items into the collection.
	 * @param {Object[]|User[]} items
	 * @returns {UserCollection}
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
	 * @returns {UserCollection}
	 */
	clear () {
		this.all = []
		return this
	}

	/**
	 * Returns the User instance that has the id specified in the arguments.
	 * @param {number} id
	 * @returns {User}
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
	 * Removes the item (given its instance or id) from the collection.
	 * @param {User|number} item
	 * @returns {UserCollection}
	 */
	remove (item) {
		const id = (typeof item === 'object') ? item.id : item

		for (let num = 0; num < this.all.length; num++) {
			if (this.all[num] === item || this.all[num].id === id) {
				this.all.splice(num, 1)
			}
		}

		return this
	}
	// endregion
}
