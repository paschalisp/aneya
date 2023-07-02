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

import EventEmitter from 'eventemitter3'

export class Contact extends EventEmitter {
	// region Constants
	static get StatusOffline () { return 'off' }
	static get StatusOnline () { return 'on' }
	// endregion

	// region Construction
	constructor (cfg = {}) {
		super()

		this.applyCfg(cfg)
	}

	applyCfg (cfg = {}) {
		this.id = cfg.id || null
		this.name = cfg.name || '-'
		this.photo = cfg.photo || null
		this.status = cfg.status || Contact.StatusOffline
		this.lastSeen = cfg.lastSeen instanceof Date ? cfg.lastSeen : (cfg.lastSeen && cfg.lastSeen.length > 0 ? new Date(cfg.lastSeen) : null)
	}
	// endregion

	// region Getters/setters
	// endregion

	// region Methods
	// endregion
}
