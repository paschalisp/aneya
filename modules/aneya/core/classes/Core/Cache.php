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

namespace aneya\Core;

use aneya\Core\Environment\Process;
use aneya\Core\Utils\DateUtils;
use aneya\Core\Utils\JsonUtils;
use aneya\FileSystem\FileSystemStatus;

class Cache {
	#region Constants
	const MimePlain      = 'text/plain';
	const MimeSerialized = 'text/serialized';
	const MimeObject     = 'text/object';
	#endregion

	#region Event constants
	/** Triggered to objects at the time they being cached */
	const EventOnCaching = 'OnCaching';
	/** Triggered to objects at the time they are successfully cached. It passes a second argument with the generated hash value. */
	const EventOnCached             = 'OnCached';
	const EventOnRetrievedFromCache = 'OnRetrievedFromCache';
	#endregion

	#region Static methods
	/**
	 * Stores a content into cache and returns a hash string generated from the content using the SHA-256 algorithm.
	 *
	 * @param ICacheable|mixed $content The content to store into cache
	 * @param ?\DateTime $expires The date the cached content will expire
	 * @param int|string $uid The content's uid used to recall the cached content later
	 * @param string $category A user-defined category for the content
	 * @param string $mimeType The content's MIME type
	 *
	 * @return string|FileSystemStatus A hash string generated from the content using SHA-256, or FileSystemStatus if an error occurred during storing the cached content to the filesystem
	 */
	public static function store(mixed $content, \DateTime $expires = null, int|string $uid = '', string $category = '', string $mimeType = Cache::MimePlain): string|FileSystemStatus {
		try {
			if ($content instanceof ICacheable) {
				// Call object's own caching mechanism (if any)
				$ret = $content->cache();

				// If return value is a string means that the object implements its own caching mechanism (hence the string is a hash)
				if (is_string($ret))
					return $ret;

				// Otherwise, serialize and store the object
				$uid = $content->getCacheUid();
				$category = $content->getCacheCategory();
				$mimeType = $content->getCacheContentType();
				$object = $content;
				$content = serialize($content);
			}
			else {
				// If content isn't text already, serialize it first
				if (!is_string($content)) {
					$content = serialize($content);

					// If mime type argument has the default value, mark it as serialized so that when loading back from cache to unserialize it
					if ($mimeType == self::MimePlain)
						$mimeType = self::MimeSerialized;
				}
			}
		}
		catch (\Exception $e) {
			CMS::logger()->error('Exception occurred during serialization of cached object: ' . $e->getMessage());
			return new FileSystemStatus(false, 'Exception occurred during serialization of cached object', $e->getCode(), $e->getMessage());
		}

		if ($expires instanceof \DateTime)
			$expires = DateUtils::toJsDate($expires);

		// Generate hash from cached content
		$hash = hash('sha256', $content);

		$filename = sprintf('%s.%s', $category, $uid);
		$status = CMS::filesystem()->write(sprintf('/cache/cached/%s.cache', $filename), $content);
		if ($status->isError())
			return $status;

		$json = JsonUtils::encode(['uid' => $uid, 'category' => $category, 'mime' => $mimeType, 'hash' => $hash, 'cached' => DateUtils::toJsDate(new \DateTime()), 'expires' => $expires]);
		if ($json === null)
			return new FileSystemStatus(false, 'Error occurred during encoding the object to JSON');

		$status = CMS::filesystem()->write(sprintf('/cache/cached/%s.pid', $filename), $json);
		if ($status->isError())
			return $status;

		if (isset($object) && $object instanceof IHookable && $object instanceof ICacheable) {
			$args = new EventArgs($object);
			$args->hash = $hash;                                // Undocumented
			$object->trigger(self::EventOnCached, $args);
		}

		return $hash;
	}

	/**
	 * Retrieves the cached item and returns its content or object instance (depending on cached item's mime-type).
	 * If the cached item's MIME type was text/serialized, the method will return the unserialized version of the cached content.
	 *
	 * @param string $category The user-defined category to search for the uid or hash
	 * @param string|int $uid The cached content's uid to retrieve
	 * @param ?\DateTime $minCacheDate The minimum cache date in order to consider the cached item as valid
	 * @param ?\DateTime $maxCacheDate The maximum cache date in order to consider the cached item as valid
	 *
	 * @return mixed|null
	 */
	public static function retrieve(string $category, string|int $uid, \DateTime $minCacheDate = null, \DateTime $maxCacheDate = null): mixed {
		$filename = sprintf('%s.%s', $category, $uid);

		$json = CMS::filesystem()->read(sprintf('/cache/cached/%s.pid', $filename));
		if (!is_string($json))
			return null;

		$json = JsonUtils::decode($json);
		if ($json === null)
			return null;

		$json->cached = DateUtils::fromJsDate($json->cached);

		if ($json->expires !== null)
			$json->expires = DateUtils::fromJsDate($json->expires);

		// Check against cache expiration time and min/max cache date (if any)
		if (isset($json->expires) && $json->expires instanceof \DateTime) {
			$now = new \DateTime();
			if ($json->expires < $now) {
				static::clear($uid, $category);
				return null;
			}
		}
		if ($minCacheDate instanceof \DateTime && $json->cached < $minCacheDate)
			return null;

		if ($maxCacheDate instanceof \DateTime && $json->cached > $maxCacheDate)
			return null;

		$content = CMS::filesystem()->read(sprintf('/cache/cached/%s.cache', $filename));
		if (!$content)
			return null;

		// If content was serialized, deserialize the cached version
		if ($json->mime == self::MimeSerialized)
			$content = unserialize($content);

		if ($content instanceof IHookable && $content instanceof ICacheable)
			$content->trigger(self::EventOnRetrievedFromCache);

		return $content;
	}

	/**
	 * Clears cache from the given cached item.
	 *
	 * @param string|int $uid  The cached item's uid
	 * @param string $category The cached item's category
	 */
	public static function clear(string|int $uid, string $category): FileSystemStatus {
		$filename = sprintf('%s.%s', $category, $uid);

		CMS::filesystem()->delete(sprintf('/cache/cached/%s.pid', $filename));
		return CMS::filesystem()->delete(sprintf('/cache/cached/%s.cache', $filename));
	}

	/** Forces a cached item to expire from cache.
	 * @see Cache::clear() */
	public static function expire(string|int $uid, string $category): FileSystemStatus {
		return static::clear($uid, $category);
	}

	/** Forces all cached items of the given category to expire. */
	public static function expireCategory(string $category): Process {
		$cmd = sprintf('rm %s/cache/cached/%s*', CMS::appPath(), $category);
		return Process::cmd($cmd);
	}
	#endregion
}
