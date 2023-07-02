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

final class ModuleFactory {
	#region Constants
	#endregion

	#region Properties
	/** @var \stdClass[] */
	protected array $_modules = [];
	/** @var string[] */
	protected array $_loading = [];
	/** @var string[] */
	protected array $_loaded = [];
	private bool $_initialized = false;

	private static ModuleFactory $_instance;
	#endregion

	#region Constructor & initialization
	private function __construct() { }

	/** Retrieves vendor/modules list and configuration from disk. */
	private function retrieve(): ModuleFactory {
		$path = CMS::appPath();

		#region Parse /modules folder for vendors
		$res = opendir("$path/modules");
		while (false != ($vendor = readdir($res))) {
			if (!is_dir("$path/modules/$vendor"))
				continue;

			#region Parse /modules/{vendor} folder for modules
			$res2 = opendir("$path/modules/$vendor");
			while (false != ($tag = readdir($res2))) {
				if (!is_dir("$path/modules/$vendor/$tag"))
					continue;

				if (!file_exists("$path/modules/$vendor/$tag/module.json") || ($json = @file_get_contents("$path/modules/$vendor/$tag/module.json")) === false)
					continue;

				#region Parse JSON configuration
				$mod = json_decode($json);
				if (!isset($mod->options))
					$mod->options = (object)['options' => new \stdClass()];

				// Apply additional (application-specific) configuration
				if (file_exists("$path/modules/$vendor/$tag/module.config.json") && ($cfg = @file_get_contents("$path/modules/$vendor/$tag/module.config.json")) !== false)
					ObjectUtils::extend(json_decode($cfg), $mod);

				// Apply additional global configuration options
				$fqtag = "$vendor/$tag";
				if (isset(CMS::cfg()->modules->$fqtag))
					ObjectUtils::extend(CMS::cfg()->modules->$fqtag, $mod->options);

				$mod->fqtag = "$vendor/$tag";
				$mod->folder = "/modules/$mod->fqtag";

				// Mark module as enabled
				$mod->enabled = !isset($cfg->enabled) || $cfg->enabled === true;
				#endregion

				if (!$mod->enabled)
					continue;

				$this->_modules[$mod->fqtag] = $mod;
			}

			closedir($res2);
			#endregion
		}
		#endregion

		return $this;
	}
	#endregion

	#region Methods
	/**
	 * Returns all available modules in the framework, regardless if they are currently enabled or not.
	 * @return string[]
	 */
	public function all(): array {
		return array_keys($this->_modules);
	}

	/**
	 * Returns all currently loaded modules in the framework.
	 * @return string[]
	 */
	public function allLoaded(): array {
		return array_keys($this->_loaded);
	}

	/**
	 * Builds all modules taking into account current namespace's configuration.
	 */
	public function build(): Status {
		// Reset initialized flag to allow re-initialization
		$this->_initialized = false;

		#region Build modules
		$mod = CMS::modules()->get('aneya/core');
		$status = $mod->build();
		if ($status->isError())
			return $status;

		else {
			$mods = CMS::modules()->all();
			sort($mods);

			foreach ($mods as $tag) {
				if ($tag === 'aneya/core')
					continue;

				$mod = CMS::modules()->get($tag);
				if ($mod instanceof Module) {
					$status = $mod->build();
					if ($status->isError())
						return $status;
				}
			}
		}
		#endregion

		// Save namespace & modules cache for faster loading
		if ($status->isOK())
			CMS::ns()->saveCache();

		return new Status();
	}

	/**
	 * Cleans all temporary or cache files created by modules during build.
	 */
	public function clean(): ModuleFactory {
		// Reset initialized flag to allow re-initialization
		$this->_initialized = false;

		$mod = CMS::modules()->get('aneya/core');
		$mod->clean();

		$mods = CMS::modules()->all();
		sort($mods);

		foreach ($mods as $tag) {
			if ($tag === 'aneya/core')
				continue;

			$mod = CMS::modules()->get($tag);
			if ($mod instanceof Module)
				$mod->clean();
		}

		return $this;
	}

	/**
	 * Returns the configuration of the given module.
	 *
	 * @param string $tag Module's tag in vendor/module format
	 *
	 * @return \stdClass|null
	 */
	public function cfg(string $tag): ?\stdClass {
		try {
			return isset($this->_modules[$tag]) ? $this->_modules[$tag]->options : null;
		} catch (\Exception $e) {
			return new \stdClass();
		}
	}

	/**
	 * Returns the JSON information of the given module.
	 *
	 * @param string $tag Module's tag in vendor/module format
	 *
	 * @return \stdClass|null
	 */
	public function info(string $tag): ?\stdClass {
		return $this->_modules[$tag] ?? null;
	}

	/**
	 * Traverses all configuration and compiles any conditional configuration into its final state.
	 */
	public function compile(): ModuleFactory {
		$mods = array_keys($this->_modules);

		foreach ($mods as $tag) {
			$module = $this->get($tag);
			$cfg = $module->compileCfg();

			$this->_modules[$tag] = $cfg;
		}

		return $this;
	}

	/**
	 * Returns a hash list of modules (in "vendor/module" => "version" format) that the given module requires in order to load.
	 *
	 * @param string $tag       Module's tag in vendor/module format
	 * @param bool $recursive Search for indirect dependencies recursively
	 * @param bool $dev	      Also search for development dependencies
	 * @param string[] $ignore    List of dependencies to ignore from parsing
	 *
	 * @return string[]
	 */
	public function dependencies(string $tag, bool $recursive = false, bool $dev = false, array $ignore = []): array {
		if (!isset($this->_modules[$tag]) || in_array($tag, $ignore))
			return [];

		$mod = $this->_modules[$tag];

		// Search for direct dependencies
		$mods = (isset($mod->requires) && is_object($mod->requires) && isset($mod->requires->modules) && is_object($mod->requires->modules))
			? $mod->requires->modules
			: [];

		$ret = [];
		foreach ($mods as $depTag => $version)
			$ret[$depTag] = $version;


		// Search for development dependencies
		if ($dev) {
			$mods = (isset($mod->requiresDev) && is_object($mod->requiresDev) && isset($mod->requiresDev->modules) && is_object($mod->requiresDev->modules))
				? $mod->requiresDev->modules
				: [];

			foreach ($mods as $depTag => $version)
				if (isset($ret[$depTag])) {
					// Keep the older version
					if ($version < $ret[$depTag])
						$ret[$depTag] = $version;
				}
				else
					$ret[$depTag] = $version;
		}

		// Search for indirect dependencies
		if ($recursive) {
			// Add current module in parsed dependencies list
			$ignore = array_merge($ignore, [$tag => true]);

			foreach (array_keys($ret) as $depTag) {
				if (in_array($depTag, $ignore))
					continue;

				$mods = $this->dependencies($depTag, true, $dev, $ignore);
				foreach ($mods as $depTag2 => $version)
					if (isset($ret[$depTag2])) {
						// Keep the older version
						if ($version < $ret[$depTag2])
							$ret[$depTag2] = $version;
					}
					else
						$ret[$depTag2] = $version;
			}
		}

		return $ret;
	}

	/**
	 * Returns true if the given module exists.
	 *
	 * @param string $tag Module's tag in vendor/module format
	 *
	 * @return bool
	 *
	 * @see Module::exists()
	 */
	public function exists(string $tag): bool {
		return Module::exists($tag);
	}

	/**
	 * Returns the given module's instance.
	 *
	 * @param string $tag Module's tag in vendor/module format
	 *
	 * @return Module|null
	 */
	public function get(string $tag): ?Module {
		return Module::instance($tag);
	}

	/**
	 * Initializes the Modules factory by retrieving vendors/modules list and configuration.
	 *
	 * This method is called internally during framework's initialization and should not be called explicitly.
	 *
	 * @param bool $useCache  Will use cached initialization if found
	 * @param bool $saveCache Will cache initialization to file for improved boot performance
	 */
	public function init(bool $useCache = true, bool $saveCache = true) {
		if ($this->_initialized)
			return;

		$path = CMS::appPath();
		$cacheOutdated = true;

		if ($useCache) {
			// Load pre-compiled modules configuration cache, if available
			if (($cache = @file_get_contents("$path/cache/aneya.modules.cache")) !== false) {
				static::$_instance->_modules = unserialize($cache);
				$cacheOutdated = false;
			}
		}

		if ($cacheOutdated) {
			static::$_instance->retrieve();

			if ($saveCache) {
				// Cache pre-compiled modules configuration, for faster initialization next time
				$cache = serialize(static::$_instance->_modules);
				@file_put_contents("$path/cache/aneya.modules.cache", $cache);
			}
		}

		$this->_initialized = true;
	}

	/**
	 * Loads a module specified by its tag.
	 *
	 * @param string $tag     Module's tag to load in vendor/module format
	 * @param string|null $version (optional) Module's compatible version
	 *
	 * @return EventStatus    The instantiated module's load() method return status
	 *
	 * @throws ApplicationError
	 */
	public function load(string $tag, string $version = null): EventStatus {
		// Prevent either disabled modules to be loaded, or already loaded modules to be reloaded
		if ($this->isLoaded($tag) || isset($this->_loading[$tag]))
			return new EventStatus(true);

		if (!$this->isAvailable($tag))
			return new EventStatus(false, 'Module is either not found or not enabled.');

		if (strlen((string)$version) > 0 && !$this->isCompatible($tag, $version))
			return new EventStatus(false, "Module's version is incompatible with the required version '$version'.");

		#region Instantiate module
		$class = $this->_modules[$tag]->className;
		if (($pos = strrpos($class, '\\')) !== false)
			$class = substr ($class, $pos + 1);

		$module = '\\' . $class . 'Module';
		if (!class_exists($module)) {
			throw new ApplicationError("Could not load module '$tag'. Invalid module class name '$class'.", 0, null, ApplicationError::SeverityCritical);
		}
		#endregion

		// Mark module as loading
		$this->_loading[$tag] = true;

		#region Load any prerequisite module dependencies
		$deps = $this->dependencies($tag);
		foreach ($deps as $mod => $version) {
			$ret = $this->load($mod, $version);
			if ($ret->isError()) {
				throw new ApplicationError("Failed to load dependency '$mod' for module '$tag'. Error: " . $ret->message, 0, null, ApplicationError::SeverityCritical);
			}
		}
		#endregion

		// Un-mark module as loading
		unset($this->_loading[$tag]);

		// Save the module for future reference
		$this->_loaded[$tag] = true;

		// Call module's load() method to let it execute its own load processing
		return $this->get($tag)->load();
	}

	/**
	 * Loads cached module configuration from the argument.
	 *
	 * @param array $cache
	 *
	 * @return ModuleFactory
	 */
	public function loadCache(array $cache): ModuleFactory {
		self::$_instance->_modules = $cache;

		return $this;
	}

	/**
	 * Returns all modules configuration in cache-ready format.
	 *
	 * @return \stdClass[]
	 */
	public function getCache(): array {
		return $this->_modules;
	}
	#endregion

	#region Predicate methods
	/**
	 * Returns true if given module is enabled and available to the framework.
	 *
	 * @param string $tag Module's tag in vendor/module format
	 *
	 * @return bool
	 */
	public function isAvailable(string $tag): bool {
		return isset($this->_modules[$tag]);
	}

	/**
	 * Returns true if given module's version is compatible with the version provided in the arguments.
	 *
	 * @param string $tag Module's tag in vendor/module format
	 * @param string $version Version number
	 *
	 * @return bool
	 */
	public function isCompatible(string $tag, string $version): bool {
		return isset($this->_modules[$tag]) && (version_compare($this->_modules[$tag]->version, $version, '>='));
	}

	/**
	 * Returns true if given module is loaded.
	 *
	 * @param string $tag Module's tag in vendor/module format
	 *
	 * @return bool
	 */
	public function isLoaded(string $tag): bool {
		return isset($this->_loaded[$tag]);
	}
	#endregion

	#region Static methods
	/**
	 * Returns framework's modules management class instance
	 *
	 * @return ModuleFactory
	 */
	public static function instance(): ModuleFactory {
		if (!isset(static::$_instance))
			static::$_instance = new ModuleFactory();

		return static::$_instance;
	}
	#endregion

	#region Magic methods
	/**
	 * @throws \Exception
	 */
	protected function __clone() { throw new \Exception('Clone is not allowed'); }
	#endregion
}
