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

require_once "CMS.php";
require_once "ClassLoaderPath.php";

class ClassLoader {
	#region Properties
	/**
	 * @var ClassLoaderPath[]
	 */
	protected array $_paths = [];

	protected static ClassLoader $_instance;
	#endregion

	#region Constructor
	protected function __construct() { }
	#endregion

	#region Methods
	/**
	 * @param string $className
	 */
	public function load(string $className) {
		if (strlen($className) == 0) return;

		#region Initialize variables
		$namespaces = explode('\\', $className);
		$max = count($namespaces);
		$vendor = ($max > 1) ? $namespaces[0] : '';
		#endregion

		#region 1st try: Search within namespace paths
		if (strlen($vendor) > 0) {
			$paths = $this->getPaths($vendor);
			// Search also for paths tagged from 1st namespace (usually module's name)
			$paths = array_merge($paths, $this->getPaths($namespaces[1]));

			#region Maybe class namespace definition is PSR-0 compatible
			foreach ($paths as $path) {
				$file = $this->getClassFile($className, $path);
				if ($file) {
					require_once($file);
					return;
				}
			}
			#endregion
		}
		#endregion

		#region 2nd try: Search in the root of all fallback paths
		foreach ($this->_paths as $path) {
			// Don't use module files for classes
			if (strpos($className, 'Module') === false && strpos($path->filePrefix, 'module.') !== false) continue;
			elseif (strpos($className, 'Theme') === false && strpos($path->filePrefix, 'theme.') !== false) continue;

			$file = $this->getClassFile($className, $path);
			if ($file) {
				require_once($file);
				return;
			}
		}
		#endregion

		#region 3rd try: Check if class is a Module or Theme
		if (strpos($className, 'Module') > 0) {
			$modules = CMS::modules()->all();
			foreach ($modules as $tag) {
				$moduleClass = CMS::modules()->info($tag)->className;
				if ($className == ($moduleClass . 'Module')) {
					$file = CMS::appPath() . "/modules/$tag/module.$moduleClass.php";
					if (file_exists($file)) {
						require_once($file);
						return;
					}
				}
			}
		}
		elseif (strpos($className, 'Theme') > 0) {
			foreach (CMS::themes() as $tag => $themeClass) {
				if ($className == ($themeClass . 'Theme')) {
					$file = CMS::webPath() . "/themes/$tag/theme.$themeClass.php";
					if (file_exists($file)) {
						require_once($file);
						return;
					}
				}
			}
		}
		#endregion

		// Useful to set a breakpoint when finding a class fails
		return;
	}

	protected function getClassFile($className, ClassLoaderPath $path) {
		#region Initialize variables
		$namespaces = explode('\\', $className);
		$max = count($namespaces);
		$vendor = ($max > 1) ? $namespaces[0] : '';
		$class = ($max > 1) ? $namespaces[$max - 1] : $className;
		$base = ($path->isRelative) ? (CMS::appPath() . (strpos($path->path, '/') !== 0 ? '/' : '') . $path->path . (substr($path->path, -1, 1) !== '/' ? '/' : '')) : $path->path;
		#endregion

		#region 1st try: Search in the root of the path
		$file = $base . $path->filePrefix . $class . '.php';
		if (file_exists($file)) {
			return $file;
		}
		#endregion

		#region 2nd try: Search within namespace paths
		if (strlen($vendor) > 0) {
			#region Maybe class namespace definition is fully PSR-0 compatible
			$file = $base . (str_replace('\\', '/', $className)) . '.php';
			if (file_exists($file)) {
				return $file;
			}

			// or, almost fully (include class file prefix)
			$prefixClassName = substr($className, 0, strrpos($className, '\\')) . '\\' . $path->filePrefix . $class;
			$file = $base . (str_replace('\\', '/', $prefixClassName)) . '.php';
			if (file_exists($file)) {
				return $file;
			}

			// or, almost fully (omit vendor's name)
			$prefixStart = strpos($className, '\\') + 1;
			$prefixClassName = substr($prefixClassName, $prefixStart, strrpos($className, '\\') - $prefixStart) . '\\' . $path->filePrefix . $class;
			$file = $base . (str_replace('\\', '/', $prefixClassName)) . '.php';
			if (file_exists($file)) {
				return $file;
			}
			#endregion

			#region Search within namespace hierarchy
			$pathWithVendor = '';
			$pathNoVendor = '';
			// Omit vendor name
			for ($i = 0; $i < $max - 1; $i++) {
				$pathWithVendor .= $namespaces[$i] . '/';
				if ($i > 0) {
					$pathNoVendor .= $namespaces[$i] . '/';
				}

				// Maybe class directory starts from vendor's name
				$file = $base . $pathWithVendor . $path->filePrefix . $class . '.php';
				if (file_exists($file)) {
					return $file;
				}

				// Maybe class directory starts after vendor's name
				$file = $base . $pathNoVendor . $path->filePrefix . $class . '.php';
				if (file_exists($file)) {
					return $file;
				}

				// Maybe class directory starts from vendor's name, but without the file prefix
				$file = $base . $pathWithVendor . $class . '.php';
				if (file_exists($file)) {
					return $file;
				}

				// Maybe class directory starts after vendor's name, but without the file prefix
				$file = $base . $pathNoVendor . $class . '.php';
				if (file_exists($file)) {
					return $file;
				}
			}
			#endregion
		}
		#endregion

		return false;
	}

	/**
	 * Adds a path into the loader's list of fallback paths
	 * Warning: If provided path is relative, it will be automatically be converted into absolute path.
	 *
	 * @param ClassLoaderPath $path
	 * @param bool            $isRelative Indicates if full path to the project should be automatically prepended when using this path
	 */
	public function addPath(ClassLoaderPath $path, $isRelative = true) {
		// Convert into absolute path, if necessary
		if ($isRelative) {
			if (strpos($path->path, "/") !== 0)
				$path->path = "/" . $path->path;

			$___root = CMS::appPath();
			$path->path = $___root . $path->path;
		}

		if ($path->tag == '')
			$path->tag = '\\';

		$this->_paths[] = $path;

		$path->isRelative = false;
	}

	/**
	 * Adds an array of paths into the loader's list of fallback paths
	 *
	 * @param      $paths
	 * @param bool $areRelative Indicates if full path to the project should be automatically prepended when using this path
	 */
	public function addPaths($paths, $areRelative) {
		foreach ($paths as $path) {
			if ($path instanceof ClassLoaderPath)
				$this->addPath($path, $areRelative);
		}
	}

	/**
	 * Returns true if the given path is already set in the class loader.
	 *
	 * @param string $path
	 * @param bool   $isRelative
	 *
	 * @return bool
	 */
	public function exists($path, $isRelative = true) {
		$___root = CMS::appPath();

		if ($isRelative) {
			if (strpos($path, "/") !== 0)
				$path = "/" . $path;

			$path = $___root . $path;
		}

		$path = $path . (substr($path, -1, 1) !== '/' ? '/' : '');

		foreach ($this->_paths as $p) {
			if ($p->path === $path)
				return true;
		}

		return false;
	}

	/**
	 * Returns an array of all fallback paths for the given namespace or tag
	 * If tag is omitted, it will return all global namespace paths
	 *
	 * @param string $tag (optional) The namespace or module tag
	 *
	 * @return ClassLoaderPath[]
	 */
	public function getPaths($tag = '') {
		$paths = array ();

		if ($tag == '')
			$tag = '\\';

		foreach ($this->_paths as $path) {
			if (strtolower($path->tag) != strtolower($tag))
				continue;

			$paths[] = $path;
		}

		return $paths;
	}

	/**
	 * Registers this ClassLoader instance into PHP's list of autoloaders
	 *
	 * @param bool $prepend
	 */
	public function register($prepend = false) {
		spl_autoload_register(array ($this, 'load'), true, $prepend);
	}

	/**
	 * Unregisters this ClassLoader instance from PHP's list of autoloaders
	 */
	public function unRegister() {
		spl_autoload_unregister(array ($this, 'load'));
	}
	#endregion

	#region Static methods
	public static function instance(): ClassLoader {
		if (!isset(static::$_instance))
			static::$_instance = new ClassLoader();

		return static::$_instance;
	}
	#endregion
}
