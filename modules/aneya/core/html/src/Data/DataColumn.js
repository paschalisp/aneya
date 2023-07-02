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

const DataTypeBlob = 'blob'
const DataTypeBoolean = 'bool'
const DataTypeChar = 'char'
const DataTypeDate = 'date'
const DataTypeDateTime = 'datetime'
const DataTypeFloat = 'float'
const DataTypeGeoPoint = 'geopoint'
const DataTypeGeoPolygon = 'geopoly'
const DataTypeGeometry = 'geometry'
const DataTypeGeoMultiPoint = 'geomultipoint'
const DataTypeGeoMultiPolygon = 'geomultipoly'
const DataTypeGeoCollection = 'geocollection'
const DataTypeInteger = 'int'
const DataTypeJson = 'json'
const DataTypeObject = 'obj'
const DataTypeString = 'string'
const DataTypeTime = 'time'

/**
 * @class
 * @property {boolean} allowHTML
 * @property {boolean} allowNull
 * @property {boolean} allowTrim
 * @property {string} dataType
 * @property {*} defaultValue
 * @property {number} id
 * @property {boolean} isActive
 * @property {boolean} isAggregate
 * @property {boolean} isAutoIncrement
 * @property {boolean} isExpression
 * @property {boolean} isFake
 * @property {boolean} isKey
 * @property {boolean} isMultilingual
 * @property {boolean} isReadOnly
 * @property {boolean} isRequired
 * @property {boolean} isSaveable
 * @property {boolean} isUnique
 * @property {boolean} isUnsigned
 * @property {string} name
 * @property {string} tag
 * @property {string} title
 * @property {object} validationRule
 */
export class DataColumn {
	// region Constants
	static get DataTypeBlob () { return DataTypeBlob }
	static get DataTypeBoolean () { return DataTypeBoolean }
	static get DataTypeChar () { return DataTypeChar }
	static get DataTypeDate () { return DataTypeDate }
	static get DataTypeDateTime () { return DataTypeDateTime }
	static get DataTypeFloat () { return DataTypeFloat }
	static get DataTypeGeoPoint () { return DataTypeGeoPoint }
	static get DataTypeGeoPolygon () { return DataTypeGeoPolygon }
	static get DataTypeGeometry () { return DataTypeGeometry }
	static get DataTypeGeoMultiPoint () { return DataTypeGeoMultiPoint }
	static get DataTypeGeoMultiPolygon () { return DataTypeGeoMultiPolygon }
	static get DataTypeGeoCollection () { return DataTypeGeoCollection }
	static get DataTypeInteger () { return DataTypeInteger }
	static get DataTypeJson () { return DataTypeJson }
	static get DataTypeObject () { return DataTypeObject }
	static get DataTypeString () { return DataTypeString }
	static get DataTypeTime () { return DataTypeTime }
	// endregion

	// region Constructor & Initialization
	/**
	 * @constructor
	 * @param {?object} cfg
	 */
	constructor (cfg = undefined) {
		if (cfg instanceof Object) {
			this.init(cfg)
		} else {
			this.allowHTML = false
			this.allowNull = true
			this.allowTrim = true
			this.dataType = ''
			this.defaultValue = null
			this.id = null
			this.isActive = false
			this.isAggregate = false
			this.isAutoIncrement = false
			this.isExpression = false
			this.isFake = false
			this.isKey = false
			this.isMultilingual = false
			this.isReadOnly = false
			this.isRequired = false
			this.isSaveable = false
			this.isUnique = false
			this.isUnsigned = false
			this.name = ''
			this.tag = ''
			this.title = ''
			this.validationRule = { }
		}
	}

	/**
	 * Initializes the object with the configuration passed in the arguments.
	 * @param {Object} cfg
	 * @returns {DataColumn}
	 */
	init (cfg) {
		this.allowHTML = Boolean(cfg.allowHTML)
		this.allowNull = Boolean(cfg.allowNull)
		this.allowTrim = Boolean(cfg.allowTrim)
		this.dataType = cfg.dataType || ''
		this.defaultValue = cfg.defaultValue || null
		this.id = cfg.id || null
		this.isActive = Boolean(cfg.isActive)
		this.isAggregate = Boolean(cfg.isAggregate)
		this.isAutoIncrement = Boolean(cfg.isAutoIncrement)
		this.isExpression = Boolean(cfg.isExpression)
		this.isFake = Boolean(cfg.isFake)
		this.isKey = Boolean(cfg.isKey)
		this.isMultilingual = Boolean(cfg.isMultilingual)
		this.isReadOnly = Boolean(cfg.isReadOnly)
		this.isRequired = Boolean(cfg.isRequired)
		this.isSaveable = Boolean(cfg.isSaveable)
		this.isUnique = Boolean(cfg.isUnique)
		this.isUnsigned = Boolean(cfg.isUnsigned)
		this.name = cfg.name || ''
		this.tag = cfg.tag || ''
		this.title = cfg.title || ''
		this.validationRule = cfg.validationRule || { }

		return this
	}
	// endregion

	// region Static Methods
	static isValid (obj) {
		return Object.prototype.hasOwnProperty.call(obj, 'name') &&
			Object.prototype.hasOwnProperty.call(obj, 'tag') &&
			Object.prototype.hasOwnProperty.call(obj, 'title') &&
			Object.prototype.hasOwnProperty.call(obj, 'dataType')
	}
	// endregion
}
