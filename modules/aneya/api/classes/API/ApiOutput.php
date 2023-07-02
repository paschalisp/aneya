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

namespace aneya\API;


use aneya\Core\IRenderable;
use aneya\Core\Utils\JsonUtils;
use aneya\Snippets\Renderable;
use aneya\Snippets\Snippet;

class ApiOutput implements IRenderable {
	use Renderable;

	#region Properties
	public ApiEventStatus $status;
	#endregion

	#region Constructor
	public function __construct(ApiEventStatus $status) {
		$this->status = $status;
	}
	#endregion

	#region Methods
	public function render(): string {
		http_response_code ($this->status->code);
		header("Content-Type: " . $this->snippet()->contentType() . "; charset=utf-8");

		if (!isset($this->status->headers['Access-Control-Allow-Origin']) || isset($_SERVER['HTTP_ORIGIN'])) {
			header('Access-Control-Allow-Origin: ' . ($_SERVER['HTTP_ORIGIN'] ?? '*'));
			header('Access-Control-Allow-Credentials: true');
			header('Access-Control-Max-Age: 86400');	// Allow caching for one day
		}

		foreach ($this->status->headers as $header => $value)
			header("$header: $value");

		if ($this->status->data !== null) {
			if (is_scalar($this->status->data))
				$this->snippet()->params->add('output', $this->status->data);
			else
				$this->snippet()->params->add('output', JsonUtils::encode($this->status->data));
		}
		else
			$this->_snippet->params->add('output', '{}');

		return $this->snippet()->compile();
	}

	protected function snippet(): Snippet {
		if ($this->_snippet === null) {
			$this->_snippet = new Snippet();
			$this->_snippet->loadContentFromVariable('{%output}');
			$this->_snippet->params->add('output', '{}');
			$this->_snippet->contentType('application/json');
			$this->_snippet->parent = $this;
		}

		return $this->_snippet;
	}
	#endregion

	#region Static methods
	#endregion
}
