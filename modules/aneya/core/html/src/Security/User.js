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
 * @class User
 *
 * @property {int} id
 * @property {string} firstName
 * @property {string} lastName
 * @property {string} email
 * @property {string} username
 * @property {string} jobTitle
 * @property {boolean} rememberMe
 * @property {Object} options
 * @property {string[]} roles
 * @property {string[]} permissions
 */
export class User {
	// region Constants
	static get StatusInvalid () { return -1 }
	static get StatusPending () { return 0 }
	static get Status1stLogin () { return 1 }
	static get StatusActive () { return 2 }
	static get StatusLocked () { return 3 }
	static get StatusDisabled () { return 9 }
	// endregion

	// region Constructor
	constructor (cfg = {}) {
		this.applyCfg(cfg, true)
	}

	applyCfg (cfg, strict = false) {
		Object.assign(
			this,
			strict ? {
				id: null,
				firstName: '',
				lastName: '',
				email: '',
				username: '',
				jobTitle: '',
				rememberMe: false,
				lastAccess: null,

				roles: [],
				permissions: [],

				options: {
					urlSignIn: '',
					urlInfo: ''
				}
			} : {},
			cfg)

		return this
	}
	// endregion

	// region Getters/Setters
	get fullName () {
		return (this.firstName + ' ' + this.lastName).trim()
	}
	// endregion

	// region Methods
	// region Security methods
	/**
	 * Returns true if user is granted any of the given permissions.
	 *
	 * @param {string|string[]} permissions
	 *
	 * @returns {boolean}
	 */
	allowed (permissions) {
		if (!Array.isArray(permissions)) {
			permissions = [permissions]
		}

		for (let num = 0; num < permissions.length; num++) {
			if (this.permissions.indexOf(permissions[num]) >= 0)
				return true
		}

		return false
	}
	// endregion
	// endregion

	// region Static Methods
	static isValid (obj) {
		return Object.prototype.hasOwnProperty.call(obj, 'username') &&
			Object.prototype.hasOwnProperty.call(obj, 'firstName') &&
			Object.prototype.hasOwnProperty.call(obj, 'lastName') &&
			Object.prototype.hasOwnProperty.call(obj, 'roles') &&
			Object.prototype.hasOwnProperty.call(obj, 'permissions') &&
			Object.prototype.hasOwnProperty.call(obj, 'email')
	}
	// endregion
}
