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

/**
 * @class
 * @property {boolean} isPositive
 * @property {?number} code
 * @property {string} message
 * @property {string} debugMessage
 * @property {object|array|string|number} data
 */
export class Status {
	/**
	 * @constructor
	 * @param {?boolean|Object} isPositive
	 * @param {?Number} code
	 * @param {?string} message
	 * @param {?string} debugMessage
	 * @param {?Object|?Array|?string|?Number} data
	 */
	constructor (isPositive = true, code = null, message = '', debugMessage = '', data = {}) {
		if (typeof isPositive === 'object') {
			const obj = isPositive

			this.isPositive = Boolean(obj.isPositive == null ? true : obj.isPositive)
			this.code = obj.code || 0
			this.message = obj.message || ''
			this.debugMessage = obj.debugMessage || ''
			this.data = obj.data || {}
		}
		else {
			this.isPositive = Boolean(isPositive == null ? true : isPositive)
			this.code = code || 0
			this.message = message || ''
			this.debugMessage = debugMessage || ''
			this.data = data || {}
		}
	}

	/**
	 * Returns true if status is positive.
	 * @returns {boolean}
	 */
	get isOK () {
		return this.isPositive === true
	}

	/**
	 * Returns true if status is negative.
	 * @returns {boolean}
	 */
	get isError () {
		return this.isPositive !== true
	}

	applyCfg (cfg, strict = false) {
		Object.assign(this, strict ? {
			isPositive: true,
			code: 0,
			message: '',
			debugMessage: '',
			data: {}
		} : {}, cfg)

		return this
	}
}
