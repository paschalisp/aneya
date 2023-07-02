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

// region DataFilter constants
const NoFilter			= '-'
/** Filter returns false always */
const FalseFilter		= 'false'
/** Custom filter which expression is represented in the value */
const Custom			= '?'
const Equals			= '='
const NotEqual			= '!='
const LessThan			= '<'
const LessOrEqual		= '<='
const GreaterThan		= '>'
const GreaterOrEqual	= '>='
const IsNull			= 'null'
const IsEmpty			= 'empty'
const NotEmpty			= '!empty'
const NotNull			= '!null'
const StartsWith		= '.*'
const EndsWith			= '*.'
const NotStartWith		= '!.*'
const NotEndWith		= '!*.'
const Contains			= '*'
const NotContain		= '!*'
const InList			= '[]'
const NotInList			= '![]'
const InSet				= '{}'
const NotInSet			= '!{}'
const Between			= '><'
// endregion

/**
 * @class
 * @property {string} column
 * @property {string} operand
 * @property {number} value
 */
export class DataFilter {
	// region Constants
	static get NoFilter () { return NoFilter }
	/** Filter returns false always */
	static get FalseFilter () { return FalseFilter }
	/** Custom filter which expression is represented in the value */
	static get Custom () { return Custom }
	static get Equals () { return Equals }
	static get NotEqual () { return NotEqual }
	static get LessThan () { return LessThan }
	static get LessOrEqual () { return LessOrEqual }
	static get GreaterThan () { return GreaterThan }
	static get GreaterOrEqual () { return GreaterOrEqual }
	static get IsNull () { return IsNull }
	static get IsEmpty () { return IsEmpty }
	static get NotEmpty () { return NotEmpty }
	static get NotNull () { return NotNull }
	static get StartsWith () { return StartsWith }
	static get EndsWith () { return EndsWith }
	static get NotStartWith () { return NotStartWith }
	static get NotEndWith () { return NotEndWith }
	static get Contains () { return Contains }
	static get NotContain () { return NotContain }
	static get InList () { return InList }
	static get NotInList () { return NotInList }
	static get InSet () { return InSet }
	static get NotInSet () { return NotInSet }
	static get Between () { return Between }
	// endregion

	// region Constructor
	/**
	 * @constructor
	 * @param {string} col
	 * @param {string} operand
	 * @param {?*} value
	 */
	constructor (col, operand, value = null) {
		this.column = col || ''
		this.operand = operand || '='
		this.value = value || null
	}
	// endregion

	// region Static Methods
	static isValid (obj) {
		return Object.prototype.hasOwnProperty.call(obj, 'column') &&
			Object.prototype.hasOwnProperty.call(obj, 'operand')
	}
	// endregion
}
