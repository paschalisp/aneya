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

import {Application} from '../../../../appstyle/html/src/classes/Application/Application'
import {DataColumn} from './DataColumn'
import {DataColumnCollection} from './DataColumnCollection'
import {DataFilterCollection} from './DataFilterCollection'
import {DataRow} from './DataRow'
import {DataRowCollection} from './DataRowCollection'
import {DataSortingCollection} from './DataSortingCollection'
import {DataTable} from './DataTable'
import {DataTableCollection} from './DataTableCollection'
import {DataTableRelationCollection} from './DataTableRelationCollection'

/**
 * @class
 * @property {string} alias
 * @property {?number} id
 * @property {string} name
 * @property {string} schema
 * @property {DataColumnCollection} columns
 * @property {DataRowCollection} rows
 * @property {DataFilterCollection} filtering
 * @property {DataSortingCollection} sorting
 * @property {string[]} languages
 */
export class DataSet {
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

			this.rows = new DataRowCollection()
			this.filtering = new DataFilterCollection()
			this.sorting = new DataSortingCollection()
			this.tables = new DataTableCollection()
			this.relations = new DataTableRelationCollection()

			this.languages = Application.instance.languages
		}
	}

	/**
	 * Initializes the object with the configuration found in the arguments.
	 * @param {Object} cfg
	 * @returns {DataSet}
	 */
	init (cfg) {
		this.alias = cfg.alias || ''
		this.id = cfg.id || null
		this.name = cfg.name || ''
		this.schema = cfg.schema || ''

		this.tables = (cfg.tables instanceof DataTableCollection) ? cfg.tables : new DataTableCollection(cfg.tables)
		this.relations = (cfg.relations instanceof DataTableRelationCollection) ? cfg.relations : new DataTableRelationCollection(cfg.relations)
		this.rows = (cfg.rows instanceof DataRowCollection) ? cfg.rows : new DataRowCollection(cfg.rows)
		this.filtering = (cfg.filtering instanceof DataFilterCollection) ? cfg.filtering : new DataFilterCollection(cfg.filtering)
		this.sorting = (cfg.sorting instanceof DataSortingCollection) ? cfg.sorting : new DataSortingCollection(cfg.sorting)

		// If columns information is provided, add them to the first available table (or create one)
		// Usually this is the case when DataSet is used as a DataTable to avoid exposing schema information
		// to the client application.
		if (cfg.columns) {
			if (this.tables.length === 0) {
				this.tables.add(new DataTable({name: '__table', schema: '__', alias: 'T', columns: cfg.columns}))
			} else {
				this.tables[0].columns.addRange(cfg.columns)
			}
		}

		this.languages = cfg.languages || Application.instance.languages

		return this
	}
	// endregion

	// region Getters / setters
	/**
	 * Returns all DataColumns under DataSet's collection of tables.
	 * @returns {DataColumnCollection}
	 */
	get columns () {
		const cols = []

		this.tables.all.forEach(c => cols.push(...c.columns.all))

		return new DataColumnCollection(cols)
	}
	// endregion

	// region Methods
	/**
	 * Applies given filtering (additional to default filtering) & sorting and returns the matched rows in a new DataRowCollection.
	 * @param filtering
	 * @param sorting
	 * @returns {DataRowCollection}
	 */
	apply (filtering = undefined, sorting = undefined) {
		const filters = new DataFilterCollection(this.filtering.all)
		const sort = sorting instanceof DataSortingCollection ? sorting : this.sorting

		if (filtering instanceof DataFilterCollection) {
			filters.addRange(filtering.all)
		}

		return this.rows.filter(filters).sort(sort)
	}

	/**
	 * Returns a new DataRow instance with configuration set by it's parent DataSet instance (this object).
	 * @param addToCollection
	 * @returns {DataRow}
	 */
	newRow (addToCollection = true) {
		const defaultValues = {}

		// region Build default values
		for (let num = 0; num < this.columns.length; num++) {
			if (this.columns.all[num].isMultilingual) {
				const value = {}
				for (const lang in this.languages) {
					if (Object.prototype.hasOwnProperty.call(this.languages, lang)) {
						value[lang] = this.columns.all[num].defaultValue
					}
				}
				defaultValues[this.columns.all[num].tag] = value
			} else {
				switch (this.columns.all[num].dataType) {
					case DataColumn.DataTypeDate:
					case DataColumn.DataTypeDateTime:
						defaultValues[this.columns.all[num].tag] = this.columns.all[num].defaultValue
						break
					default:
						defaultValues[this.columns.all[num].tag] = this.columns.all[num].defaultValue
				}
			}
		}
		// endregion

		const row = new DataRow(this, defaultValues)

		if (addToCollection) {
			this.rows.add(row)
		}

		return row
	}
	// endregion
}
