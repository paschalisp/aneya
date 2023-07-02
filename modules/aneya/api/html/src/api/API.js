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

import axios from 'axios'
import EventEmitter from 'eventemitter3'
import qs from 'qs'
import URI from 'urijs'

import {User} from '../../../../core/html/src/Security/User.js'
import {Status} from '../../../../core/html/src/Core/Status.js'

const EventOnAuthError = 'auth-error'
const EventOnAuthenticated = 'auth'

/**
 * @class API
 */
export class API extends EventEmitter {
	// region Constants
	static get EventOnAuthError () { return EventOnAuthError }
	static get EventOnAuthenticated () { return EventOnAuthenticated }
	// endregion

	// region Properties
	host = ''
	/** @property {number} port */
	port = null
	/** @property {boolean} ssl */
	ssl = true
	version = 'v1.0'
	namespace = 'app'
	url = {}
	user = null
	cookies = true
	allowRemoteOrigin = true

	/** @property {?Date} expiresAt */
	expiresAt = null
	/** @property {?Date} lastAccess */
	lastAccess = null
	// endregion

	// region Constructor
	constructor (options) {
		super()

		if (typeof options === 'object') {
			this.init(options)
		} else {
			this.host = 'localhost'
			this.port = null
			this.ssl = true
			this.namespace = 'app'
			this.version = 'v1.0'
			this.cookies = true
			this.allowRemoteOrigin = false

			this.url = {
				base: '/api' + this.version,
				auth: '/api' + this.version + '/auth',
				signIn: '/api' + this.version + '/user/signin',
				userInfo: '/api' + this.version + '/user/info',
				language: '/api' + this.version + '/user/language'
			}
		}
		this.user = new User()

		this.expiresAt = null
		this.lastAccess = null

		if (this.allowRemoteOrigin) {
			axios.defaults.crossOrigin = true
			axios.defaults.preflightContinue = true
			axios.defaults.withCredentials = true
		}
	}

	init (options) {
		this.host = options.host || 'localhost'
		this.port = options.port || null
		this.ssl = Boolean(options.ssl)
		this.cookies = options.cookies != null ? Boolean(options.cookies) : true
		this.namespace = options.namespace || 'app'
		this.version = options.version || 'v1.0'
		this.allowRemoteOrigin = Boolean(options.allowRemoteOrigin)

		this.url = options.url || {
			base: '/api' + this.version,
			auth: '/api' + this.version + '/auth',
			signIn: '/api' + this.version + '/user/signin',
			userInfo: '/api' + this.version + '/user/info',
			language: '/api' + this.version + '/user/language'
		}
	}
	// endregion

	// region Getters / setters
	get address () {
		return 'http' + (this.ssl ? 's' : '') + '://' + this.host + (this.port > 0 ? ':' + this.port : '')
	}

	get expired () {
		return this.token == null || this.token.length === 0
	}

	get isAuthenticated () {
		return !this.expired
	}

	/**
	 * @return {object}
	 */
	get headers () {
		const headers = {}

		headers['X-Api-Token'] = this.token

		return headers
	}

	/** @return {?string} */
	get token () {
		/** @see https://developer.mozilla.org/en-US/docs/Web/API/Document/cookie */
		const rx = new RegExp('(?:(?:^|.*;\\s*)' + ('auth_token_' + this.namespace) + '\\s*\\=\\s*([^;]*).*$)|^.*$')

		// Get token considering the running environment
		if (this.useTokenCookie) {
			return document.cookie.replace(rx, '$1')
		}
		else {
			return this.__cookie != null ? this.__cookie.replace(rx, '$1') : null
		}
	}

	/** @param {string} value */
	set token (value) {
		// Set cookie considering the running environment
		if (this.useTokenCookie) {
			document.cookie = `auth_token_${this.namespace}=${value};path=/;`
		} else {
			// Hold cookie in an internal variable
			this.__cookie = `auth_token_${this.namespace}=${value};path=/;`
		}
	}

	/** @return {boolean} */
	get rememberMe () {
		/** @see https://developer.mozilla.org/en-US/docs/Web/API/Document/cookie */
		const rx = new RegExp('(?:(?:^|.*;\\s*)' + ('auth_remember_' + this.namespace) + '\\s*\\=\\s*([^;]*).*$)|^.*$')

		// Get token considering the running environment
		if (this.useTokenCookie) {
			return document.cookie.replace(rx, '$1') === 'true'
		}
		else {
			return this.__cookie != null ? this.__cookie.replace(rx, '$1') === 'true' : false
		}
	}

	/** @param {boolean|string} value */
	set rememberMe (value) {
		// Set cookie considering the running environment
		if (this.useTokenCookie) {
			document.cookie = `auth_remember_${this.namespace}=${value === true || value === 'true' ? 'true' : 'false'};max-age=315360000;path=/;`
		} else {
			// Hold cookie in an internal variable
			this.__cookie = `auth_remember_${this.namespace}=${value === true || value === 'true' ? 'true' : 'false'};max-age=315360000;path=/;`
		}
	}

	/**
	 * Returns true if storing authentication token in cookie is enabled and API instance runs in browser environment.
	 * @return {boolean}
	 */
	get useTokenCookie () {
		return (this.cookies == null || this.cookies === true) && typeof document !== 'undefined'
	}
	// endregion

	// region Methods
	async auth (username = undefined, password = undefined, rememberMe = undefined) {
		// Keep rememberMe information for later automated calls
		if (rememberMe != null) {
			this.rememberMe = rememberMe
		}

		const config = {
			headers: {}
		}
		if (this.allowRemoteOrigin) {
			config.crossOrigin = true
			config.preflightContinue = true
			config.withCredentials = true
		}

		const now = new Date()
		let authAddress = this.address + this.url.auth

		// If token has been expired, credentials are required to get a new token
		if ((username != null && password != null) || this.expired) {
			// Don't make unnecessary API call when there's no token, and no username or password are set
			if (username == null || password == null) {
				const err = new Error('Username and password are required for authentication')
				this.emit(API.EventOnAuthError, err)

				throw err
			}

			authAddress = URI(authAddress).query({
				HTTP_X_API_CLIENT_ID: username,
				HTTP_X_API_SECRET: password
			}).toString()
		}
		else {
			// es-lint-disable-next-line eqeqeq
			if (this.expiresAt == null || now.toDateString() === this.expiresAt.toDateString()) {
				// If not signed in during this session, or token expires today,
				// then force renewal of token's expiration time,
				// unless last authentication made was less than 2 minutes ago

				if (this.lastAccess && Math.abs(now.getTime() - this.lastAccess.getTime()) * 1000 * 60 < 2) {
					this.lastAccess = now

					this.emit(API.EventOnAuthenticated, new Status())

					return
				}
				else {
					config.headers['X-Api-Token'] = this.token
				}
			}
			else {
				// Otherwise it is safe to resolve authentication without a call
				this.lastAccess = now

				this.emit(API.EventOnAuthenticated, new Status())

				return
			}
		}

		try {
			// response contains the full http response, we only need the data status returned
			const response = await axios.get(authAddress, config)
			const status = new Status(response.data)

			this.lastAccess = this.user.lastAccess = now
			this.user.lastAccess = new Date(now)
			this.expiresAt = new Date(now)
			this.expiresAt.setSeconds(this.expiresAt.getSeconds() + status.data.expiresIn - 1)

			// Set cookie considering the running environment
			if (this.useTokenCookie) {
				if (this.rememberMe)
					document.cookie = `auth_token_${this.namespace}=${status.data.token};max-age=${status.data.expiresIn - 1};path=/;`
				else
					document.cookie = `auth_token_${this.namespace}=${status.data.token};path=/;`
			} else {
				// Hold cookie in an internal variable
				this.__cookie = `auth_token_${this.namespace}=${status.data.token};path=/;`
			}

			this.emit(API.EventOnAuthenticated, status)
		}
		catch (e) {
			this.emit(API.EventOnAuthError, e)
			throw e
		}
	}

	async quit () {
		this.expiresAt = this.lastAccess = null

		// Set cookie considering the running environment
		if (this.useTokenCookie) {
			document.cookie = `auth_token_${this.namespace}=;expires=Thu, 01 Jan 1970 00:00:00 GMT;path=/;`
		} else {
			this.__cookie = null
		}
	}
	// endregion

	// region Network methods
	async get (url, config = undefined, authenticate = true, rawResponse = false) {
		if (authenticate) {
			await this.auth()
			config = this.__pushToken(config)
		}

		const resp = await axios.get(this.address + url, config)
		return rawResponse ? resp : resp.data
	}

	async post (url, data = undefined, config = undefined, authenticate = true, rawResponse = false) {
		if (authenticate) {
			await this.auth()
			config = this.__pushToken(config)
		}

		const params = data instanceof FormData ? data : qs.stringify(data)
		const resp = await axios.post(this.address + url, params, config)
		return rawResponse ? resp : resp.data
	}

	async put (url, data = undefined, config = undefined, authenticate = true, rawResponse = false) {
		if (authenticate) {
			await this.auth()
			config = this.__pushToken(config)
		}

		const params = data instanceof FormData ? data : qs.stringify(data)
		const resp = await axios.put(this.address + url, params, config)
		return rawResponse ? resp : resp.data
	}

	async delete (url, config = undefined, authenticate = true, rawResponse = false) {
		if (authenticate) {
			await this.auth()
			config = this.__pushToken(config)
		}

		const resp = await axios.delete(this.address + url, config)
		return rawResponse ? resp : resp.data
	}

	async head (url, config = undefined, authenticate = true, rawResponse = false) {
		if (authenticate) {
			await this.auth()
			config = this.__pushToken(config)
		}

		const resp = await axios.head(this.address + url, config)
		return rawResponse ? resp : resp.data
	}

	async patch (url, data = undefined, config = undefined, authenticate = true, rawResponse = false) {
		if (authenticate) {
			await this.auth()
			config = this.__pushToken(config)
		}

		const params = qs.stringify(data)
		const resp = await axios.patch(this.address + url, params, config)
		return rawResponse ? resp : resp.data
	}
	// endregion

	// region Internal methods
	__pushToken (config) {
		if (config === undefined) {
			config = {}
		}

		if (config.headers === undefined) {
			config.headers = {}
		}

		config.headers['X-Api-Token'] = this.token
		config.crossOrigin = true
		config.preflightContinue = true
		config.withCredentials = true

		return config
	}
	// endregion
}
