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

import {DataColumnCollection} from './DataColumnCollection'

/**
 * @class
 * @property {string} alias
 * @property {?number} id
 * @property {string} name
 * @property {string} schema
 * @property {DataColumnCollection} columns
 */
export class DataTable {
	// region Constructor & Initialization
	/**
	 * @constructor
	 * @param {?Object} cfg
	 */
	constructor (cfg = undefined) {
		if (cfg instanceof Object) {
			this.init(cfg)
		} else {
			this.alias = ''
			this.id = null
			this.name = ''
			this.schema = ''

			this.columns = new DataColumnCollection()
		}
	}

	/**
	 * Initializes the object with the configuration found in the arguments.
	 * @param {Object} cfg
	 * @returns {DataTable}
	 */
	init (cfg) {
		this.alias = cfg.alias || ''
		this.id = cfg.id || null
		this.name = cfg.name || ''
		this.schema = cfg.schema || ''

		this.columns = (cfg.columns instanceof DataColumnCollection) ? cfg.columns : new DataColumnCollection(cfg.columns)

		return this
	}
	// endregion

	// region Getters / setters
	get fullName () {
		return this.schema + '.' + this.name
	}
	// endregion

	// region Methods
	// endregion

	// region Static Methods
	static isValid (obj) {
		return Object.prototype.hasOwnProperty.call(obj, 'name') &&
			Object.prototype.hasOwnProperty.call(obj, 'schema') &&
			Object.prototype.hasOwnProperty.call(obj, 'alias')
	}
	// endregion
}
