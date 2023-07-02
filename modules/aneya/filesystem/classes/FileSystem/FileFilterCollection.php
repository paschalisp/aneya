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
 * Portions created by Paschalis Ch. Pagonidis are Copyright (c) 2007-2022
 * Paschalis Ch. Pagonidis. All Rights Reserved.
 */

namespace aneya\FileSystem;

use aneya\Core\CMS;
use aneya\Core\Collection;

class FileFilterCollection extends Collection {
	#region Constants
	const OperandOr		= '|';
	const OperandAnd	= '&';
	#endregion

	#region Properties
	/** @var int The operand to use to join the filters into one expression */
	public $operand = FileFilterCollection::OperandAnd;

	/**
	 * @var FileFilter[]
	 */
	protected array $_collection;
	#endregion

	#region Constructor
	public function __construct() {
		parent::__construct('\\aneya\\FileSystem\\FileFilter', true);
	}
	#endregion

	#region Methods
	/**
	 * @inheritdoc
	 * @return FileFilter[]
	 */
	public function all(callable $f = null): array {
		return parent::all($f);
	}

	/**
	 * @inheritdoc
	 */
	public function first(callable $f = null): FileFilter {
		return parent::first($f);
	}

	/**
	 * Returns all filter information for the given file
	 * @param File|string $file
	 * @return FileFilterCollection
	 */
	public function byFile($file): FileFilterCollection {
		$ret = new FileFilterCollection();

		if (is_string($file)) {
			$file = CMS::filesystem()->localize($file);

			foreach ($this->_collection as $c)
				if ($c->value == $file)
					$ret->add($c);
		}
		elseif ($file instanceof File) {
			foreach ($this->_collection as $c)
				if ($c->value == $file->name())
					$ret->add($c);
		}

		return $ret;
	}

	/**
	 * @inheritdoc
	 */
	public function isValid($item): bool {
		return ($item instanceof FileFilter || $item instanceof FileFilterCollection);
	}
	#endregion

	#region Static methods
	#endregion
}
