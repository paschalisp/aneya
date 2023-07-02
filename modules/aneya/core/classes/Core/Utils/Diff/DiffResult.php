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

namespace aneya\Core\Utils\Diff;

class DiffResult {
	/**
	 * @var mixed
	 */
	public $source;
	/**
	 * @var mixed
	 */
	public $target;
	/**
	 * @var string|DiffResult[]
	 */
	public $result;

	public function __construct ($source, $target = null) {
		$this->source = $source;
		$this->target = $target;
	}

	public function isUnchanged () {
		if (is_array ($this->result)) {
			foreach ($this->result as $res) {
				if (is_array ($res)) {
					foreach ($res as $res2) {
						if (($res2 instanceof DiffResult) && !$res2->isUnchanged ())
							return false;
					}
				}
				elseif ($res != Diff::UNCHANGED)
					return false;
			}
		}
		elseif ($this->result != Diff::UNCHANGED)
			return false;

		return true;
	}

	public function isUnchangedOrModified (): bool {
		return is_array ($this->result) || $this->result == Diff::UNCHANGED || $this->result == Diff::MODIFIED;
	}

	/**
	 * Returns comparison result to string
	 * @return string
	 */
	public function resultToString (): string {
		switch ($this->result) {
			case Diff::ADDED	: return "Added";
			case Diff::DELETED	: return "Deleted";
			case Diff::MODIFIED	: return "Modified";
			case Diff::UNCHANGED: return "Unchanged";
		}

		return '';
	}
}
