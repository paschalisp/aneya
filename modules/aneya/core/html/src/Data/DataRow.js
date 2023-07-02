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

import Vue from 'vue'

import {Application} from '../../../../appstyle/html/src/classes/Application/Application'
import {DataColumn} from './DataColumn'
import {Status} from '../Core/Status'

/**
 * @class
 * @property {DataColumnCollection} columns
 * @property {object} values
 * @property {object} originalValues
 * @property {DataSet} parent
 */
export class DataRow {
	// region Constructor & Initialization
	/**
	 * @constructor
	 * @param {DataSet} parent
	 * @param {?object} values
	 * @param {?string} hash Values hash string
	 */
	constructor (parent, values = { }, hash = undefined) {
		this.parent = parent
		this.languages = Application.instance.languages
		this._hash = null

		if (values.__rowhash !== undefined && values.__rowhash.length > 0) {
			this._hash = values.__rowhash
			delete values.__rowhash
		}

		this.values = this.normalizeValues({})
		this.originalValues = this.normalizeValues({})

		// Reset to default values
		this.init()

		// Set any values passed in the arguments
		Object.assign(this.values, this.normalizeValues(values))
		Object.assign(this.originalValues, this.normalizeValues(values))
	}

	/**
	 * Initializes the object with the columns configuration found in the parent.
	 * @returns DataRow
	 */
	init () {
		return this.resetDefault()
	}
	// endregion

	// region Getters / Setters
	/**
	 *
	 * @returns {DataColumnCollection}
	 */
	get columns () {
		return this.parent.columns
	}

	/**
	 * Returns true if row has changed
	 * @returns {boolean}
	 */
	get hasChanged () {
		return (JSON.stringify(this.values) !== JSON.stringify(this.originalValues))
	}

	/**
	 * Returns row's original values hash.
	 * @returns {string}
	 */
	get hash () {
		return this._hash
	}
	// endregion

	// region Methods
	/**
	 * Sets in bulk all passed column values to row.
	 * @param {object} values
	 * @param {boolean} partial If true, any columns not found in values will be left intact and not set to null
	 */
	apply (values, partial = false) {
		Object.assign(this.values, this.normalizeValues(values, partial))
	}

	normalizeValues (values, partial = false) {
		for (let num = 0; num < this.columns.length; num++) {
			/** @type {DataColumn} col */
			const col = this.columns.all[num]

			// Multilingual columns need special normalization
			if (col.isMultilingual) {
				const trValue = (Object.prototype.hasOwnProperty.call(values, col.tag)) ? values[col.tag] : {}

				for (const lang in this.languages) {
					if (Object.prototype.hasOwnProperty.call(this.languages, lang)) {
						// Set missing translations to null
						if (!Object.prototype.hasOwnProperty.call(trValue, lang)) {
							trValue[this.languages[lang].tag] = null
						}
					}
				}

				values[col.tag] = trValue
			}
			else {
				// Column not found in given values
				if (!Object.prototype.hasOwnProperty.call(values, col.tag)) {
					if (!partial) {
						// Set missing column value to null, only in partial mode
						values[col.tag] = null
					}
				}
			}
		}

		return values
	}

	/**
	 * Returns the value of a column in the row.
	 * @param {DataColumn|string} col
	 * @returns {*}
	 */
	getValue (col) {
		if (Object.prototype.hasOwnProperty.call(this.values, col)) {
			return this.values[col]
		}

		return null
	}

	/**
	 * Returns an array of all primary key columns.
	 * @returns {DataColumn[]}
	 */
	keys () {
		const keys = []
		for (let num = 0; num < this.columns.length; num++) {
			if (this.columns.all[num].isKey) {
				keys.push(this.columns.all[num])
			}
		}

		return keys
	}

	/**
	 * Returns an hash array with the value of each primary key in the row.
	 * @param {boolean} originalKeys If true, original key values (prior any changes) will be returned instead.
	 * @returns Object
	 */
	keyValues (originalKeys = false) {
		const values = []
		const keys = this.keys()

		if (originalKeys) {
			for (let num = 0; num < keys.length; num++) {
				values[keys[num].tag] = this.originalValues[keys[num].tag]
			}
		} else {
			for (let num = 0; num < keys.length; num++) {
				values[keys[num].tag] = this.values[keys[num].tag]
			}
		}

		return values
	}

	match (filters) {
		// TODO: Implement method
		return false
	}

	/**
	 * Resets row to its original values (or the given values, if specified).
	 * @param {?object} values
	 * @param {?string} hash
	 * @returns {DataRow}
	 */
	reset (values = undefined, hash = undefined) {
		this.values = {}

		// Reset to the specified values
		if (values instanceof Object) {
			this.originalValues = JSON.parse(JSON.stringify(this.normalizeValues(values)))
		}

		this.values = JSON.parse(JSON.stringify(this.originalValues))

		this._hash = hash

		return this
	}

	/**
	 * Resets row to each containing column's default value.
	 * @returns {DataRow}
	 */
	resetDefault () {
		this.values = {}

		for (let num = 0; num < this.columns.length; num++) {
			if (this.columns.all[num].isMultilingual) {
				const value = []
				for (const lang in this.languages) {
					if (Object.prototype.hasOwnProperty.call(this.languages, lang)) {
						value[lang] = this.columns.all[num].defaultValue
					}
				}
				Vue.set(this.values, this.columns.all[num].tag, value)
			} else {
				switch (this.columns.all[num].dataType) {
					case DataColumn.DataTypeDate:
					case DataColumn.DataTypeDateTime:
						Vue.set(this.values, this.columns.all[num].tag, this.columns.all[num].defaultValue)
						break
					default:
						Vue.set(this.values, this.columns.all[num].tag, this.columns.all[num].defaultValue)
				}
			}
		}

		return this
	}

	/**
	 * Sets the value of a column in the row.
	 * @param col
	 * @param value
	 * @returns {DataRow}
	 */
	setValue (col, value) {
		if (Object.prototype.hasOwnProperty.call(this.values, col)) {
			Vue.set(this.values, col, value)
		}

		return this
	}

	/**
	 * Validates row against given rules
	 * @param {object|array} rules
	 * @returns {Status}
	 */
	validate (rules) {
		// TODO: Implement method
		return new Status(true)
	}
	// endregion
}
