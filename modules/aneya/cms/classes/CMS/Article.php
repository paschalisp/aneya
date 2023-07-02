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

namespace aneya\CMS;

/**
 * Class Article
 * Represents a generic, text-based content item (in rich-text format).
 */
class Article extends ContentItem {
	#region Constants
	const StatusDraft = 0;
	/** Article is pending approval */
	const StatusPending = 1;
	const StatusPublished = 4;
	const StatusHidden = 6;
	const StatusArchived = 8;
	const StatusDeleted = 9;
	#endregion

	#region Properties
	/** @var mixed Article's unique identifier */
	public $id;

	/** @var string Article's title */
	public $title;
	/** @var string Article's tag used to build the web page's SEO-friendly URL */
	public $seoUrl;

	/** @var string Article's accompanying thumbnail or icon URL */
	public $iconUrl;

	/** @var string Article's permanent link URL address */
	public $permanentLinkUrl;

	/** @var int */
	public $status = self::StatusPublished;

	/** @var \aneya\Security\User|string|int */
	public $author;

	/** @var \DateTime The time the article was created */
	public $dateCreated;
	/** @var \DateTime The time the article was published */
	public $datePublished;
	/** @var \DateTime The time the article was lastly modified */
	public $dateUpdated;

	protected static $__jsProperties = ['title', 'content', 'iconUrl', 'datePublished', 'tags'];
	#endregion
}
