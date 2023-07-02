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

import {DataFilterCollection} from './DataFilterCollection'

/**
 * @class DataTableRelation
 *
 * @property {?number} id
 * @property {Object} parent
 * @property {?Object} child
 * @property {string} joinType
 * @property {number} joinOrder
 * @property {DataFilterCollection} criteria
 * @property {boolean} isDefault
 * @property {Array|Object[]} links
 */
export class DataTableRelation {
	// region Constructor & Initialization
	/**
	 * @constructor
	 * @param {?Object} cfg
	 * @param {?DataSet} dataSet
	 */
	constructor (cfg = undefined, dataSet = undefined) {
		if (cfg instanceof Object) {
			this.init(cfg)
		} else {
			this.id = null
			this.parent = { schema: '', name: '' }
			this.child = null
			this.joinType = '1'
			this.joinOrder = 0
			this.isDefault = true
			this.isSaveable = true

			this.criteria = new DataFilterCollection()
			this.links = []
		}
	}

	/**
	 * Initializes the object with the configuration found in the arguments.
	 * @param {Object} cfg
	 * @returns {DataTableRelation}
	 */
	init (cfg) {
		this.id = cfg.id || null
		this.parent = cfg.parent || { schema: '', name: '' }
		this.child = cfg.child || null
		this.joinType = cfg.joinType || '1'
		this.joinOrder = cfg.joinOrder || 0
		this.isDefault = Boolean(cfg.isDefault)
		this.isSaveable = Boolean(cfg.isSaveable)

		this.criteria = (cfg.criteria instanceof DataFilterCollection) ? cfg.criteria : new DataFilterCollection(cfg.criteria)
		this.links = Array.isArray(cfg.links) ? cfg.links : []

		return this
	}
	// endregion

	// region Getters / setters
	// endregion

	// region Methods
	// endregion

	// region Static Methods
	static isValid (obj) {
		return Object.prototype.hasOwnProperty.call(obj, 'parent') &&
			Object.prototype.hasOwnProperty.call(obj, 'joinType')
	}
	// endregion
}
