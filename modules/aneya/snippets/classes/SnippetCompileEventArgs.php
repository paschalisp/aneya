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

namespace aneya\Snippets;

use aneya\Core\EventArgs;

class SnippetCompileEventArgs extends EventArgs {
	/** @var string The content that was produced by the listener when the event was triggered */
	public $content;
	/** @var string|mixed A user-defined render or compilation mode information that can be passed along with the event */
	public $mode;

	/**
	 * @param Snippet $sender
	 * @param string $content The content that was produced by the listener when the event was triggered
	 * @param string|mixed A user-defined render or compilation mode information that can be passed along with the event
	 */
	public function __construct ($sender = null, $content = null, $mode = null) {
		parent::__construct($sender);

		$this->content = $content;
		$this->mode = $mode;
	}
}
