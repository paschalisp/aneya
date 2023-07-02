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
use aneya\Expressions\Expression;
use aneya\Expressions\ExpressionEventArgs;
use aneya\Expressions\ExpressionEventStatus;
use aneya\Expressions\InvalidExpressionException;
use aneya\Routing\RouteController;

abstract class Module extends CoreObject {
	#region Constants
	#endregion

	#region Events
	/** Triggered (instance & statically) when Module is being loaded */
	const EventOnLoading	= 'OnLoading';
	/** Triggered (instance & statically) when Module has been loaded */
	const EventOnLoaded		= 'OnLoaded';
	/** Triggered (instance & statically) to execute module-related cleaning when project's clean is triggered from the command-line */
	const EventOnClean		= 'OnClean';
	/** Triggered (instance & statically) to execute module-related build code when project's build is triggered from the command-line */
	const EventOnBuilding	= 'OnBuilding';
	/** Triggered (instance & statically) to execute module-related build code when project's build is triggered from the command-line */
	const EventOnBuild		= 'OnBuild';
	#endregion

	#region Properties
	protected string $_vendor;
	protected string $_tag;
	/** @var string Module's version in x.x.x.x format. Derived modules really need to set this variable. */
	protected string $_version;

	protected \stdClass $_cfg;

	/** @var Module[] */
	protected static array $_mods;
	/** @var string[] */
	private static array $_loaded;
	/** @var string[] */
	private static array $_compiling;
	/** @var string[] */
	private static array $_compiled;
	/** @var string[] */
	private static array $_built;
	#endregion

	#region Constructor
	protected function __construct () {
		$this->_cfg = new \stdClass();
		$this->hooks()->register([self::EventOnLoading, self::EventOnLoaded]);
	}
	#endregion

	#region Installation/upgrade methods
	public function install (): Status { return new Status(); }
	public function uninstall (): Status { return new Status(); }
	public function upgrade (): Status { return new Status(); }
	public function validate (): Status {
		// TODO: Validate registered files checksum for external changes
		return new Status();
	}
	#endregion

	#region Getter methods
	public final function checksum() {
		return md5($this->_vendor . '/' . $this->_tag . '/' . $this->_version);
	}

	public final function dependencies() {
		$mod = CMS::modules()->info($this->_vendor . '/' . $this->_tag);

		return (is_object($mod->requires) && is_object($mod->requires->modules)) ? $mod->requires->modules : new \stdClass();
	}

	public final function cfg(): \stdClass {
		return $this->_cfg;
	}

	public final function tag(): string {
		return "$this->_vendor/$this->_tag";
	}
	#endregion

	#region Loading methods
	/**
	 * Executes module-related initialization code and registers any class loading, routing,
	 * events or other information, functionality or mechanism during module's loading.
	 */
	public final function load(): ?EventStatus {
		if (isset(self::$_loaded[$this->_vendor . '/' . $this->_tag]))
			return new EventStatus();

		$args = new EventArgs($this);
		$this->trigger(self::EventOnLoading, $args);
		static::triggerSt(self::EventOnLoading, $args);

		$this->_cfg = CMS::modules()->info("$this->_vendor/$this->_tag") ?? new \stdClass();

		#region Register autoload paths
		if (isset($this->_cfg->autoload) && is_array($this->_cfg->autoload)) {
			foreach ($this->_cfg->autoload as $path) {
				$path = (strpos($path, '/') === 0) ? "/modules/$this->_vendor/$this->_tag" . $path : "/modules/$this->_vendor/$this->_tag/$path";
				if (!CMS::loader()->exists($path))
					CMS::loader()->addPath(new ClassLoaderPath($path, $this->_vendor));
			}
		}
		#endregion

		// Invoke onLoad() to allow descendants execute their own loading code
		$ret = $this->onLoad($args);

		if (!($ret instanceof EventStatus))
			$ret = new EventStatus();

		if ($ret->isError())
			return $ret;

		#region Register any route controllers
		if (!CMS::env()->isCLI() && isset($this->_cfg->routes) && is_array($this->_cfg->routes)) {
			foreach ($this->_cfg->routes as $route) {
				$class = $route->controller;
				if (!(class_exists($class) && is_subclass_of($class, '\\aneya\\Routing\\RouteController')))
					continue;

				/** @var RouteController $controller */
				$controller = new $class();
				if (isset($route->priority) && is_numeric($route->priority))
					$controller->priority = (int)$route->priority;

				CMS::router()->controllers->add($controller);
			}
		}
		#endregion

		self::$_loaded[$this->_vendor . '/' . $this->_tag] = true;

		$this->onLoaded($args);
		$this->trigger(self::EventOnLoaded, $args);
		static::triggerSt(self::EventOnLoaded, $args);

		return $ret;
	}

	/**
	 * Executes module-related build code when project's build is triggered from the command-line.
	 */
	public final function build(): Status {
		if (isset(self::$_built[$this->_vendor . '/' . $this->_tag]))
			return new Status();

		$args = new EventArgs($this);

		if ($this->_cfg === null)
			$this->_cfg = CMS::modules()->info("$this->_vendor/$this->_tag") ?? new \stdClass();

		$this->trigger(self::EventOnBuilding, $args);
		static::triggerSt(self::EventOnBuilding, $args);

		$ret = $this->onBuild($args);
		if (!($ret instanceof Status))
			$ret = new Status();

		if ($ret->isError())
			return $ret;

		$this->trigger(self::EventOnBuild, $args);
		static::triggerSt(self::EventOnBuild, $args);

		self::$_built[$this->_vendor . '/' . $this->_tag] = true;

		return $ret;
	}

	/**
	 * Executes module-related cleaning when project's clean is triggered from the command-line.
	 *
	 * @return EventStatus
	 */
	public final function clean(): EventStatus {
		$args = new EventArgs();

		$ret = $this->onClean($args);
		if (!($ret instanceof EventStatus))
			$ret = new EventStatus();

		// Remove module from built modules to allow re-build, if requested
		unset(self::$_built[$this->_vendor . '/' . $this->_tag]);
		unset(self::$_compiled[$this->_vendor . '/' . $this->_tag]);
		unset(self::$_compiling[$this->_vendor . '/' . $this->_tag]);

		if ($ret->isError())
			return $ret;

		$this->trigger(self::EventOnClean, $args);
		static::triggerSt(self::EventOnClean, $args);

		return $ret;
	}

	/**
	 * Compiles any conditional configuration options and returns the final configuration.
	 * @param mixed $cfg
	 * @param bool	$condition Will be set to true if passed $cfg argument was found to be a conditional configuration.
	 *
	 * @return mixed
	 */
	public final function compileCfg($cfg = null, &$condition = false) {
		if (isset(self::$_compiled[$this->_vendor . '/' . $this->_tag]) || isset(self::$_compiling[$this->_vendor . '/' . $this->_tag]))
			return $this->_cfg;

		if (func_num_args() == 0) {
			self::$_compiling[$this->_vendor . '/' . $this->_tag] = true;

			#region Compile any prerequisite dependency modules
			$deps = $this->dependencies();
			foreach ($deps as $mod => $version) {
				if (isset(self::$_compiling[$mod]))
					continue;

				try {
					self::instance($mod)->compileCfg();
				}
				catch (\Exception $e) { }
			}
			#endregion

			unset(self::$_compiling[$this->_vendor . '/' . $this->_tag]);

			$this->_cfg = $this->compileCfg($this->_cfg);

			return $this->_cfg;
		}
		else {
			if (is_scalar($cfg) || empty($cfg))
				return $cfg;

			$expr = new Expression();
			$expr->on(Expression::EventOnUnknownVariable, function (ExpressionEventArgs $args) {
				return $this->parseCfgVariable($args);
			});

			foreach ($cfg as $property => $value) {
				$lcProperty = strtolower($property);
				if ($lcProperty == 'if' || $lcProperty == 'if+' || $lcProperty == 'if=') {
					$expr->expression($value->cond);
					try {
						$ret = (bool)$expr->evaluate();
					}
					catch (InvalidExpressionException $e) {
						$ret = false;
					}

					if ($ret == true) {
						$value = (isset($value->true))
							? $this->compileCfg($value->true)
							: null;
					}
					else {
						$value = (isset($value->false))
							? $this->compileCfg($value->false)
							: null;
					}

					switch ($lcProperty) {
						case 'if':
						case 'if=':
							return $value;

						case 'if+':
							if ($value instanceof \stdClass) {
								foreach ($value as $k => $v)
									$cfg->$k = $v;

								return $cfg;
							}
							else
								return array_merge($cfg, $value);
					}
				}

				if (is_array($value) || $value instanceof \stdClass) {
					$value = $this->compileCfg($value);

					if (is_array($cfg)) {
						if ($value === null)
							unset($cfg[$property]);
						elseif (is_array($value))
							$cfg = array_merge($cfg, $value);
						elseif (is_int($property)) {
							unset ($cfg[$property]);
							foreach ($value as $k => $v)
								$cfg[$k] = $v;
						}
						else
							$cfg[$property] = $value;
					}
					else {
						if ($value === null)
							unset($cfg->$property);
						else
							$cfg->$property = $value;
					}
				}
			}

			return $cfg;
		}
	}

	protected function parseCfgVariable(ExpressionEventArgs $args): ExpressionEventStatus {
		$status = new ExpressionEventStatus();

		// Try own configuration properties
		$value = ObjectUtils::getProperty($this->_cfg, $args->variable);
		if ($value !== null) {
			$status->evaluation = $value;
			return $status;
		}

		// Try other modules' configuration properties
		@list($vendor, $module, $cfg) = @explode('.', $args->variable, 3);

		// Assume same vendor with this module
		if (!CMS::modules()->exists("$vendor/$module")) {
			$vendor = static::cfg()->vendor->tag;
			@list($module, $cfg) = @explode('.', $args->variable, 2);
		}

		$modCfg = CMS::modules()->info("$vendor/$module");
		if ($modCfg === null) {
			$status->isPositive = false;
			$status->message = "Unknown module configuration expression " . $args->variable;
			return $status;
		}

		$value = ObjectUtils::getProperty($modCfg, $cfg);
		$status->evaluation = $value;

		return $status;
	}
	#endregion

	#region Event methods
	protected function onLoad(EventArgs $args = null): ?EventStatus { return new EventStatus(); }

	protected function onLoaded(EventArgs $args = null): ?EventStatus { return new EventStatus(); }

	protected function onBuild(EventArgs $args = null): ?EventStatus { return new EventStatus(); }

	protected function onClean(EventArgs $args = null): ?EventStatus { return new EventStatus(); }
	#endregion

	#region Static methods
	/**
	 * Returns the instance of the given module
	 * @param string $module
	 *
	 * @return Module|null
	 */
	public static function instance(string $module): ?Module {
		if (!isset(self::$_mods[$module])) {
			// @var \stdClass $cfg Module's configuration
			$cfg = CMS::modules()->info($module);

			/** @var Module $module */
			$class = $cfg->className . 'Module';

			try {
				self::$_mods[$module] = new $class();
				self::$_mods[$module]->_cfg = $cfg;
			}
			catch (\Exception $e) {
				return null;
			}
		}

		return self::$_mods[$module];
	}

	/**
	 * Returns true if the given module exists
	 *
	 * @param string $module
	 *
	 * @return bool
	 */
	public static function exists(string $module): bool {
		if (isset(self::$_mods[$module]))
			return true;

		// @var \stdClass $cfg Module's configuration
		$cfg = CMS::modules()->info($module);
		if ($cfg === null)
			return false;

		/** @var Module $module */
		$class = $cfg->className . 'Module';

		return class_exists($class);
	}
	#endregion
}
