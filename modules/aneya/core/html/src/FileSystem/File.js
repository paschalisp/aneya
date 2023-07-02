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

import {FileCollection} from './FileCollection'

/**
 * @class
 * @property {string} path
 * @property {string} basename
 * @property {string} type
 * @property {int} size
 * @property {?string} hash
 * @property {?FileCollection} files (optional, may be set if object represents a directory)
 */
export class File {
	// region Constants
	static get File () { return 'file' }
	static get Directory () { return 'directory' }
	static get Symlink () { return 'symlink' }

	static get Audio () { return 'audio' }
	static get Archive () { return 'archive' }
	static get Document () { return 'document' }
	static get Drawing () { return 'drawing' }
	static get Executable () { return 'executable' }
	static get Image () { return 'image' }
	static get Model () { return 'model' }
	static get Code () { return 'code' }
	static get Pdf () { return 'pdf' }
	static get Presentation () { return 'presentation' }
	static get Spreadsheet () { return 'spreadsheet' }
	static get Text () { return 'text' }
	static get Word () { return 'word' }
	static get Video () { return 'video' }
	static get Unknown () { return '-' }

	static get MimeTypes () {
		return {
			Audio: [],
			Code: ['application/xml', 'application/xhtml+xml', 'application/javascript', 'application/x-sql', 'application/x-ruby', 'text/css', 'text/html', 'text/xml', 'text/x-java', 'text/x-perl', 'text/x-pascal', 'text/x-python'],
			Archive: ['application/x-iso9660-image', 'application/zip', 'application/rar', 'application/tar', 'application/x-tar', 'application/gzip', 'application/java-archive', 'application/x-msi', 'application/x-7z-compressed', 'application/vnd.android.package-archive', 'application/java-vm'],
			Document: [],
			Drawing: ['image/svg+xml', 'application/vnd.visio'],
			Executable: ['application/x-msdos-program', 'text/x-sh'],
			Image: [],
			Spreadsheet: ['application/vnd.ms-excel', 'application/x-kspread', 'application/vnd.openxmlformats-officedocument.spreadsheetml'],
			Pdf: ['application/pdf'],
			Presentation: ['application/vnd.ms-powerpoint', 'application/vnd.openxmlformats-officedocument.presentation'],
			Text: ['text/plain'],
			Word: ['application/vnd.ms-word', 'application/msword', 'application/vnd.wordperfect', 'application/x-abiword', 'application/vnd.openxmlformats-officedocument.word', 'application/rtf'],
			Video: []
		}
	}
	// endregion

	// region Constructor & Initialization
	/**
	 * @constructor
	 * @param {?object} cfg
	 */
	constructor (cfg = undefined) {
		this.applyCfg(cfg || {
			path: '',
			basename: '',
			filename: '',
			extension: '',
			type: File.File,
			size: 0,
			hash: null
		})
	}

	/**
	 * Initializes the object with the configuration passed in the arguments.
	 * @param {Object} cfg
	 * @returns {File}
	 */
	applyCfg (cfg) {
		this.path = cfg.path || this.path
		this.basename = cfg.basename || this.basename
		this.type = cfg.type || this.type
		this.size = cfg.size || this.size
		this.hash = cfg.hash || this.hash

		if (this.type === File.Directory) {
			this.files = null

			if (Object.prototype.hasOwnProperty.call(cfg, 'files')) {
				if (cfg.files instanceof FileCollection) {
					this.files = cfg.files
				}
				else if (Array.isArray(cfg.files)) {
					this.files = new FileCollection(cfg.files)
				}
			}
		}

		return this
	}
	// endregion

	// region Getters / Setters
	/**
	 * Returns file's full path and file name.
	 * @return {string}
	 */
	get name () {
		return `${this.path}/${this.basename}`
	}

	/**
	 * Returns file's name without the extension.
	 * @return {string}
	 */
	get filename () {
		const dot = this.basename.lastIndexOf('.')

		return (dot > -1)
			? this.basename.substr(0, dot)
			: this.basename
	}

	/**
	 * Returns file's extension.
	 * @return {string}
	 */
	get extension () {
		const dot = this.basename.lastIndexOf('.')

		return (dot > -1)
			? this.basename.substr(dot + 1)
			: ''
	}

	/**
	 * Returns file's document type based on its mime-type property.
	 * @return {string}
	 */
	get docType () {
		const type = this.type.split('/')

		if ([File.Directory].indexOf(this.type) >= 0)
			return this.type
		if (File.MimeTypes.Word.indexOf(this.type) >= 0)
			return File.Word
		else if (File.MimeTypes.Spreadsheet.indexOf(this.type) >= 0)
			return File.Spreadsheet
		else if (File.MimeTypes.Presentation.indexOf(this.type) >= 0)
			return File.Presentation
		else if (File.MimeTypes.Archive.indexOf(this.type) >= 0)
			return File.Archive
		else if (File.MimeTypes.Pdf.indexOf(this.type) >= 0)
			return File.Pdf
		else if (File.MimeTypes.Executable.indexOf(this.type) >= 0)
			return File.Executable
		else if (File.MimeTypes.Code.indexOf(this.type) >= 0)
			return File.Code
		else if (type[0] === 'text')
			return File.Text
		else if (type[0] === 'image')
			return File.Image
		else if (type[0] === 'audio')
			return File.Audio
		else if (type[0] === 'video')
			return File.Video
		else if (this.type === 'application/octet-stream') {
			switch (this.extension) {
				case 'xls':
				case 'xlsx':
					return File.Spreadsheet
			}
		}

		return File.File
	}
	// endregion

	// region Methods
	// endregion

	// region Static Methods
	static isValid (obj) {
		return Object.prototype.hasOwnProperty.call(obj, 'path') &&
			Object.prototype.hasOwnProperty.call(obj, 'basename') &&
			Object.prototype.hasOwnProperty.call(obj, 'type')
	}
	// endregion
}
