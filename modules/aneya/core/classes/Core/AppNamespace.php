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

use aneya\Core\Utils\ObjectUtils;

class AppNamespace {
	#region Constants
	#endregion

	#region Properties
	/** @var string */
	public string $tag;
	/**@var string The User-derived class name (fully-qualified class name, including namespace) that the application namespace uses for authentication */
	public string $userClass = '\\aneya\\Security\\User';
	/** @var \stdClass Module dependencies */
	public \stdClass $modules;
	/** @var \stdClass Namespace configuration */
	public \stdClass $options;
	#endregion

	#region Constructor
	/** AppNamespace constructor. */
	public function __construct(string $tag = null, \stdClass $config = null) {
		if ($tag !== null)
			$this->tag = $tag;

		if ($config instanceof \stdClass)
			ObjectUtils::extend($config, $this);

		if (!isset($this->modules))
			$this->modules = new \stdClass();

		if (!isset($this->options))
			$this->options = new \stdClass();

		if (!isset($this->options->modules))
			$this->options->modules = new \stdClass();
	}
	#endregion

	#region Methods
	/** Activates the namespace by loading and applying modules configuration for this namespace. */
	public function activate(): static {
		// Reset modules' configuration to the pre-compiled state
		$mods = CMS::modules()->clean();
		$cacheOutdated = true;

		// Apply namespace's additional modules configuration
		if (!defined('___BUILD___')) {
			// Apply compiled cache directly to modules
			$path = CMS::appPath();

			// Apply compiled cache directly to the instance
			if (($cache = @file_get_contents("$path/cache/aneya.namespace.$this->tag.cache")) !== false) {
				try {
					// Apply cached configuration to modules
					$cache = unserialize($cache);
					$mods->loadCache($cache->modules);

					// Apply cached configuration to namespace
					ObjectUtils::extend($cache->config, $this);
					$cacheOutdated = false;
				}
				catch (\Exception $e) {
				}
			}
		}

		if ($cacheOutdated) {
			// Apply namespace configuration to modules
			foreach ($this->options->modules as $mod => $cfg) {
				if (($mod = $mods->info($mod)) !== null) {
					foreach ($cfg as $property => $value)
						$mod->options->$property = $value;
				}
			}

			// Compile conditional configuration into its evaluated state
			$mods->compile();

			$this->saveCache();
		}

		#region Setup modules' class auto-loading
		foreach ($mods->all() as $tag) {
			if (!CMS::modules()->isAvailable($tag))
				continue;

			$mod = CMS::modules()->info($tag);
			if (isset($mod->autoload) && is_array($mod->autoload)) {
				foreach ($mod->autoload as $path) {
					if (!CMS::loader()->exists($mod->folder . $path))
						CMS::loader()->addPath(new ClassLoaderPath($mod->folder . $path, $mod->vendor->tag));
				}
			}
		}
		#endregion

		#region Apply environment's language with default priority (request, session, session w/out ns, default)
		$langCode = $_REQUEST['__m17n_language'] ?? CMS::session()->get('__m17n_language') ?? $_SESSION['session____m17n_language'] ?? CMS::translator()->defaultLanguage()->code;
		CMS::translator()->setCurrentLanguage($langCode ?? '');
		#endregion

		return $this;
	}

	/** Saves namespace and modules configuration into cache for faster reloading. */
	public function saveCache(): static {
		$cache = new \stdClass();
		$cache->config = $this;
		$cache->modules = CMS::modules()->getCache();
		$cache = serialize($cache);

		$path = CMS::appPath();
		@file_put_contents("$path/cache/aneya.namespace.$this->tag.cache", $cache);

		return $this;
	}

	/** Returns the instance of namespace's default theme. */
	public function theme(): ?Theme {
		return Theme::instance($this->options->theme);
	}
	#endregion

	#region Static methods
	#endregion

	#region Magic methods
	public function __toString() {
		return $this->tag;
	}
	#endregion
}
