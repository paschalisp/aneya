<?php
/*
 * aneya CMS & Framework
 * Copyright (c) 2007-2022 Paschalis Pagonidis <p.pagonides@gmail.com>
 * All rights reserved.
 * -----------------------------------------------------------------------------
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

namespace aneya\Core;

class ClassLoaderPath {
	#region Properties
	/** @var string Root namespace or module name. If not provided, the ClassLoader will look into the global namespace */
	public $tag;

	/** @var string The path to add into the list of fallback paths */
	public $path;

	/** @var string $filePrefix A prefix to prepend when searching for class files */
	public $filePrefix;

	/** @var bool Indicates if the path is relative to the project's root path in the file system */
	public $isRelative = true;
	#endregion

	#region Constructor
	public function __construct ($path, $tag = '', $filePrefix = '', $isPathRelative = true) {
		$this->path = $path . (substr($path, -1, 1) !== '/' ? '/' : '');
		$this->tag = $tag;
		$this->filePrefix = $filePrefix;
		$this->isRelative = $isPathRelative;
	}
	#endregion
}
