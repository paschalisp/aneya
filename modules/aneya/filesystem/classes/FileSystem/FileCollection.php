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

use aneya\Core\Collection;
use aneya\Core\Data\IFilterable;
use aneya\Core\ISortable;

class FileCollection extends Collection implements ISortable, IFilterable {
	#region Properties
	/** @var File[] */
	protected array $_collection;
	#endregion

	#region Constructor
	public function __construct() {
		parent::__construct('\\aneya\\FileSystem\\File', true);
	}
	#endregion

	#region Methods
	/**
	 * @inheritdoc
	 * @return File[]
	 */
	public function all(callable $f = null): array {
		return parent::all($f);
	}

	/**
	 * @inheritdoc
	 * @param File|string $item
	 *
	 * @return bool
	 */
	public function contains($item): bool {
		if (is_string($item)) {
			return $this->match(new FileFilter(FileFilter::Name, FileFilter::Equals, $item))->count() > 0;
		}
		else
			return parent::contains($item);
	}

	public function first(callable $f = null): File {
		return parent::first($f);
	}

	/**
	 * Returns all file entries (including symlinks) in the collection
	 */
	public function files(): FileCollection {
		$col = new FileCollection();

		foreach ($this->_collection as $file) {
			if ($file->is() != File::Directory)
				$col->add($file);
		}

		return $col;
	}

	/**
	 * Returns all directory entries in the collection
	 */
	public function folders(): FileCollection {
		$col = new FileCollection();

		foreach ($this->_collection as $file) {
			if ($file->is() == File::Directory)
				$col->add($file);
		}

		return $col;
	}

	/**
	 * @inheritdoc
	 */
	public function sort(): FileCollection {
		usort ($this->_collection, function (File $a, File $b) {
			if ($a->type == File::Directory && $b->type != File::Directory)
				return -1;

			if ($a->type != File::Directory && $b->type == File::Directory)
				return 1;

			return strcasecmp($a->name(), $b->name());
		});
		$this->rewind ();

		return $this;
	}

	/**
	 * @inheritdoc
	 * @param FileFilter|FileFilter[]|FileFilterCollection $filters
	 *
	 * @return FileCollection
	 */
	public function match($filters): FileCollection {
		$files = new FileCollection();

		foreach ($this->_collection as $file) {
			if ($file->match ($filters)) {
				$files->add ($file);
			}
		}
		return $files;
	}
	#endregion

	#region Static methods
	#endregion
}
