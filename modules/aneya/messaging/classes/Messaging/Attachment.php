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

namespace aneya\Messaging;

use aneya\Core\CMS;
use aneya\Core\CoreObject;

/**
 * Class Attachment
 * Represents an attachment that accompanies a Message.
 * Attachments hold link information and metadata.
 *
 * @package aneya\Core\Messaging
 */
class Attachment extends CoreObject {
	#region Properties
	/** @var  string */
	public $fileUrl;
	/** @var  string */
	public $fileName;
	/** @var  string */
	public $description;
	#endregion

	#region Constructor
	public function __construct($file = null, $filename = null) {
		if (is_string($file)) {
			$this->fileUrl = CMS::filesystem()->localize($file);

			if ($filename === null) {
				// Set default filename
				$this->fileName = pathinfo($this->fileUrl, PATHINFO_BASENAME);
			}
			else
				$this->fileName = $filename;
		}
	}
	#endregion

	#region Methods
	public function contentType() {
		return CMS::filesystem()->mimetype($this->fileUrl);
	}
	#endregion
}
