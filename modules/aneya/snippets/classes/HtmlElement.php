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

namespace aneya\Snippets;


trait HtmlElement {
	public $htmlTag;
	/** @var array Holds CSS classes to be added to the HTML element when it gets rendered */
	public $htmlClasses = array();
	/** @var array Holds data properties to be added to the HTML element when it gets rendered */
	public $htmlData = array();
	/** @var array Holds CSS styles to be added to the HTML element when it gets rendered */
	public $htmlStyles = array();
	public $htmlId;
	/** @var array Holds additional HTML attributes to be added to the HTML element when it gets rendered */
	public $htmlAttrs = array();

	/**
	 * Returns a string that combines all provided attributes of the HTML element.
	 * The returning string does not contain the element name itself, but only its parameters.
	 */
	public function getHtmlAttributesString(): string {
		$str = array();

		if (strlen ($this->htmlId ?? '') > 0)
			$str[] = 'id="' . $this->htmlId . '"';

		foreach ($this->htmlData as $data => $value)
			$str[] = 'data-' . $data . '="' . $value . '"';

		if (count($this->htmlClasses) > 0)
			$str[] = 'class="' . implode (' ', $this->htmlClasses) . '"';

		if (count($this->htmlStyles) > 0)
			$str[] = 'class="' . implode (' ', $this->htmlStyles) . '"';

		foreach ($this->htmlAttrs as $attr => $value)
			$str[] = 'data-' . $attr . '="' . $value . '"';

		return implode (' ', $str);
	}

	/** Returns a string that unifies all provided data attributes of the HTML element. */
	public function getHtmlDataAttributesString(): string {
		$str = array();

		foreach ($this->htmlData as $data => $value)
			$str[] = 'data-' . $data . '="' . $value . '"';

		return implode (' ', $str);
	}
}
