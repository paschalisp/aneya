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

require_once ('Hook.php');
require_once ('IHookable.php');
require_once ('ICollection.php');
require_once ('Hookable.php');
require_once ('HookCollection.php');
require_once ('I18N/I18N.php');
require_once ('I18N/Locale.php');
require_once ('CoreObject.php');
require_once ('Configuration.php');
require_once ('ClassLoader.php');
require_once ('IStorable.php');
require_once ('Storable.php');
require_once ('Collection.php');
require_once ('KeyValueCollection.php');
require_once ('ModuleFactory.php');
require_once ('Environment/Environment.php');
require_once ('Environment/Session.php');
require_once ('Data/Database.php');
require_once ('Data/RDBMS.php');

use aneya\Core\Data\Database;
use aneya\Core\Environment\Environment;
use aneya\Core\Environment\Session;
use aneya\Core\I18N\Locale;
use aneya\FileSystem\FileSystem;
use aneya\M17N\M17N;
use aneya\Routing\Router;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;

final class CMS implements IHookable {
	use Hookable;

	#region Constants
	const CMS_DB_TAG         = 'cms';
	const CMS_DEFAULT_LOCALE = 'en-US';
	#endregion

	#region Properties
	private static string $_version = '5.0.8.0';

	private static string $_licenseKey = '0000-0000-0000-0000';

	private static bool $_isInitialized = false;

	/** @var \stdClass[] Database schemas configuration */
	protected static array $_schemas;

	protected static AppNamespaceCollection $_appNamespaces;

	/** @var ?AppNamespace $_ns The current namespace the framework runs in */
	protected static ?AppNamespace $_ns = null;

	protected static ModuleFactory $_modules;

	/** @var array Associative array of form { tag => className } */
	protected static array $_themes;

	/** @var Database[] */
	protected static array $_db;
	protected static Configuration $_cfg;
	protected static Environment $_env;
	/** @var I18N */
	protected static $_i18n;
	/** @var M17N */
	protected static $_m17n;
	/** @var Locale */
	protected static $_locale;
	/** @var Router */
	protected static $_router;
	/** @var Session */
	protected static $_session;
	protected static \DateTimeZone $_timeZone;

	/** @var ClassLoader */
	protected static ClassLoader $_loader;

	/** @var FileSystem */
	protected static $_fileSystem;

	/** @var Logger */
	protected static $_logger;

	protected static $_cache;

	protected static string $_appPath = '';
	protected static string $_webDir = '/www';
	#endregion

	#region Events
	const EventOnModulesLoaded = 'OnModulesLoaded';
	const EventOnInitialized   = 'OnInitialized';
	#endregion

	#region Methods
	#region Initialization methods
	/**
	 * Initializes the CMS
	 *
	 * @param bool $useCache  Will use cached initialization if found
	 * @param bool $saveCache Will cache initialization to file for improved boot performance
	 *
	 * @throws ApplicationError
	 * @throws \Exception
	 */
	public static function init(bool $useCache = true, bool $saveCache = true) {
		if (self::$_isInitialized) return;

		self::$_isInitialized = true;

		#region Setup class loader & auto-loading
		self::$_loader = ClassLoader::instance();
		self::$_loader->register(true);

		// Manually set critical autoload paths
		self::$_loader->addPath(new ClassLoaderPath('/modules/aneya/core/classes/', 'aneya'));
		#endregion

		#region Create instances for protected object properties
		if (!isset(self::$_cfg))
			self::$_cfg = new Configuration();

		self::$_env = Environment::instance();
		self::$_session = Session::instance();
		self::$_modules = ModuleFactory::instance();
		self::$_i18n = new I18N();
		self::$_db = array ();
		#endregion

		#region Apply environmental security measures
		// Turn off magic quotes (try at least)
		ini_set("magic_quotes_gpc", false);
		ini_set("magic_quotes_runtime", false);

		// Apply UTF-8 encoding in multi-byte functions
		mb_internal_encoding('UTF-8');
		mb_regex_encoding('UTF-8');
		#endregion

		#region Read & apply configuration
		$cacheOutdated = true;

		if ($useCache) {
			if ($cache = @file_get_contents(self::$_appPath . '/cache/aneya.boot.cache')) {
				self::$_cache = unserialize($cache);

				$cacheOutdated = false;

				#region Load from cache
				self::$_cfg = self::$_cache->cfg;
				self::$_appNamespaces = self::$_cache->namespaces;
				self::$_timeZone = self::$_cache->timezone;
				#endregion
			}
		}

		// Initialize framework
		self::boot($useCache, $saveCache);

		// If cache was outdated, store the newer cache back in database
		if ($cacheOutdated && $saveCache) {
			self::$_cache = new \stdClass();
			self::$_cache->cfg = self::$_cfg;
			self::$_cache->namespaces = self::$_appNamespaces;
			self::$_cache->timezone = self::$_timeZone;

			@file_put_contents(self::$_appPath . '/cache/aneya.boot.cache', serialize(self::$_cache));
		}
		#endregion

		// Dispose unneeded memory
		self::$_cache = null;

		#region Handle PHP errors
		// Set error & exception handlers
		set_error_handler('aneya\Core\Application::errorSt');
		set_exception_handler('aneya\Core\Application::exceptionSt');

		// Set shutdown function
		register_shutdown_function('aneya\Core\Application::shutdownSt');
		#endregion

		// Inform modules that framework initialization has been completed
		self::hooksSt()->trigger(self::EventOnInitialized);
	}

	/**
	 * @param bool $useCache      Will use cached initialization if found
	 * @param bool $saveCache     Will cache initialization to file for improved boot performance
	 *
	 * @throws ApplicationError
	 * @throws \Exception
	 */
	protected static function boot(bool $useCache = true, bool $saveCache = true) {
		#region Read & apply core configuration from JSON
		if (!isset(self::$_cache)) {
			$json = @file_get_contents(self::$_appPath . '/cfg/config.json');
			self::$_cfg->applyJson($json);

			// Apply current environment's tag (or additional configuration)
			if (file_exists($file = self::$_appPath . '/cfg/config.env.json')) {
				$json = @file_get_contents($file);
				self::$_cfg->applyJson($json);
			}

			// Store environment tag in lowercase
			self::$_env->tag = strtolower(self::$_cfg->env->tag);

			// Apply additional environment-specific configuration
			if (file_exists($file = self::$_appPath . '/cfg/config.' . self::$_env->tag . '.json')) {
				$json = @file_get_contents($file);
				self::$_cfg->applyJson($json);
			}

			self::$_appNamespaces = new AppNamespaceCollection();
			foreach (self::$_cfg->namespaces as $tag => $config)
				self::$_appNamespaces->add(new AppNamespace($tag, $config));
		}
		else {
			// Set (again) environment tag in lowercase
			self::$_env->tag = strtolower(self::$_cfg->env->tag);
		}

		// Apply additional environment configurations
		date_default_timezone_set(self::$_cfg->env->timezone);
		self::$_timeZone = new \DateTimeZone(self::$_cfg->env->timezone);

		self::webDir(self::$_cfg->env->path->web ?? '/');
		#endregion

		// Include Composer's autoloader at this point, before enabling modules
		require_once self::$_appPath . '/vendor/autoload.php';

		#region Parse database schemas' configuration
		foreach (self::$_cfg->db as $tag => $db) {
			try {
				/** @var Database|string $class */
				$class = "\\aneya\\Core\\Data\\Drivers\\$db->driver";
				if (!class_exists($class)) {
					self::logger()->error("Database driver $db->driver for '$tag' is invalid. Reading schema configuration failed.");
					continue;
				}

				self::$_schemas[$tag] = $db;
			}
			catch (\Exception $e) {
				self::logger()->error("Database configuration for '$tag' failed parsing. Reading schema configuration failed.");
			}
		}
		#endregion

		#region Connect to the pre-configured database
		$connOptions = self::$_cfg->db->cms;
		$connOptions->password = Encrypt::isEncrypted($connOptions->password)
			? Encrypt::decrypt($connOptions->password)
			: $connOptions->password;
		$connOptions->charset = $connOptions->charset ?? 'utf8'; // aneya's database supports only UTF-8 for storage
		$connOptions->timezone = $connOptions->timezone ?? 'UTC'; // Store all date/times in UTC by default
		self::$_db[self::CMS_DB_TAG] = Database::load($connOptions->driver, self::CMS_DB_TAG);
		self::$_db[self::CMS_DB_TAG]->options->applyCfg($connOptions);
		self::$_db[self::CMS_DB_TAG]->connect();
		#endregion

		#region Initialize modules
		// Initialize modules factory
		self::$_modules->init($useCache, $saveCache);

		// Load Core module
		self::$_modules->load('aneya/core');

		// Registered core events
		self::hooksSt()->register([self::EventOnInitialized, self::EventOnModulesLoaded]);

		#region Find which modules to load depending on the request
		if (self::$_env->isCLI())
			// Load all modules for command-line scripts
			$modules = self::$_modules->all();
		else {
			$uri = self::$_env->uri();
			$modules = [];

			// Start loading modules based on their routing information
			foreach (self::$_modules->all() as $mod) {				// For each module
				if (isset(self::$_modules->info($mod)->routes) && is_array(($module = self::$_modules->info($mod))->routes)) {
					foreach ($module->routes as $route) {    		// For each module's routes
						$ret = preg_match($route->regex, $uri);
						if (!$ret)
							continue;

						// Load module
						$modules[] = $mod;

						#region Load any subsequent modules based on matching route's namespace configuration
						if (isset($route->namespace)) {
							if (!(isset($runningNamespace) || $route->namespace == '*'))
								$runningNamespace = $route->namespace;

							foreach (self::$_cfg->namespaces as $cfgNs => $config) {
								// Find the matching namespace
								if ($cfgNs != $route->namespace)
									continue;

								if (!is_object($config->modules))
									continue;

								foreach ($config->modules as $module => $version)
									$modules[] = $module;
							}
						}
						#endregion
					}
				}
			}

			// Remove duplicates
			$modules = array_unique($modules);
		}
		#endregion

		// Call enabled modules' enable() method to execute module-related code and register any auto-loader paths, events etc.
		foreach ($modules as $mod)
			self::$_modules->load($mod);
		#endregion

		// Inform modules that all modules have been loaded & enabled
		self::hooksSt()->trigger(self::EventOnModulesLoaded);

		// Set current namespace, based on the router matched the URI request
		if (isset($runningNamespace))
			self::ns($runningNamespace);
	}
	#endregion

	#region Object methods
	/** Returns the current application instance */
	public static function app(): ?Application {
		return Application::$current ?? null;
	}

	/** Returns framework's configuration instance */
	public static function cfg(): Configuration {
		return self::$_cfg;
	}

	/**
	 * Returns a Database instance connected to the provided schema or connected to the default CMS schema if no parameter is provided.
	 *
	 * @param int|string|null $schema_tag The requested schema's ID or tag
	 *
	 * @return ?Database A Database instance; already connected and ready for use
	 */
	public static function db(int|string $schema_tag = null): ?Database {
		if (!is_string($schema_tag) || empty($schema_tag) || $schema_tag == self::CMS_DB_TAG)
			return self::$_db[self::CMS_DB_TAG];

		if (!isset(self::$_schemas[$schema_tag])) {
			self::logger()->error("Could not find schema '$schema_tag' on the active server environment [" . self::$_env->tag . "].");
			return null;
		}

		$row = self::$_schemas[$schema_tag];

		// Check if the requested Database is already instantiated and connected
		if (isset(self::$_db[$schema_tag]) && self::$_db[$schema_tag] instanceof Database) {
			if (!self::$_db[$schema_tag]->isConnected()) {
				$connOpts = self::$_db[$schema_tag]->parseCfg($row);
				self::$_db[$schema_tag]->connect($connOpts);
			}
		}
		else {
			try {
				// Instantiate a new Database object and try to connect to the database
				self::$_db[$schema_tag] = Database::load($row->driver, $schema_tag);
				$connOpts = self::$_db[$schema_tag]->parseCfg($row);
				self::$_db[$schema_tag]->connect($connOpts);
			}
			catch (\Exception $e) {
				self::logger()->error($e->getMessage());
				return null;
			}
		}

		return self::$_db[$schema_tag];
	}

	/** Returns the running environment instance */
	public static function env(): Environment {
		return self::$_env;
	}

	/** Returns the framework's default ClassLoader instance */
	public static function loader(): ClassLoader {
		return self::$_loader;
	}

	/** Returns framework's default logger instance */
	public static function logger(): Logger {
		if (self::$_logger === null) {
			$path = CMS::appPath() . '/logs';
			$handlers = [];

			$level = CMS::cfg()->get('logLevel');
			if (!in_array($level, [Logger::DEBUG, Logger::INFO, Logger::NOTICE, Logger::WARNING, Logger::ERROR, Logger::CRITICAL, Logger::ALERT, Logger::EMERGENCY]))
				$level = Logger::ERROR;

			try {
				$handlers[] = new StreamHandler("$path/application.err.log", Logger::ERROR, false);
				$handlers[] = new StreamHandler("$path/application.log", $level, false);

				if ($level < Logger::ERROR) {

					if (CMS::cfg()->env->debugging === true && $level > Logger::DEBUG)
						$handlers[] = new StreamHandler("$path/application.debug.log", Logger::DEBUG, false);
				}
				else {
					if (CMS::cfg()->env->debugging === true)
						$handlers[] = new StreamHandler("$path/application.debug.log", Logger::DEBUG, false);
				}
			}
			catch (\Exception $e) { }
			self::$_logger = new Logger('cms', $handlers);
		}

		return self::$_logger;
	}

	/** Returns framework's filesystem instantiated object */
	public static function filesystem(): FileSystem {
		if (self::$_fileSystem === null) {
			self::$_fileSystem = new FileSystem();

			// Change directory to application's root
			self::$_fileSystem->chdir('');
		}

		return self::$_fileSystem;
	}

	/** Returns the default I18N instance */
	public static function i18n(): I18N {
		return self::$_i18n;
	}

	/** Returns the currently set up Locale instance to access localization functions */
	public static function locale(): Locale {
		if (!isset(self::$_locale)) {
			$code = self::$_cfg->env->locale;
			if ($code == null)
				$code = self::CMS_DEFAULT_LOCALE;
			try {
				self::$_locale = Locale::create($code);
			}
			catch (\Exception $e) {
				self::$_locale = new Locale();
			}
		}

		return self::$_locale;
	}

	/** Returns framework's modules factory instance */
	public static function modules(): ModuleFactory {
		return self::$_modules;
	}

	/** Returns module's instance given its tag */
	public static function module(string $mod): ?Module {
		return self::$_modules->get($mod);
	}

	/**
	 * Returns frameworks Router instance.
	 * If 'aneya/routing' module is not available, it will return null instead.
	 */
	public static function router(): ?Router {
		if (!isset(self::$_router)) {
			if (!class_exists('\\aneya\\Routing\\Router')) {
				self::logger()->warning('aneya/routing is not available to be able to use CMS::router()');
				return null;
			}

			self::$_router = new Router();
		}

		return self::$_router;
	}

	/** Returns framework's Session instance */
	public static function session(): Session {
		return self::$_session;
	}

	/** Returns the configured (application-level) Timezone instance of the framework */
	public static function timezone(): \DateTimeZone {
		return self::$_timeZone;
	}

	/**
	 * Returns frameworks multilingualization (M17N) instance.
	 * If 'aneya/m17n' module is not enabled, it will return null instead.
	 *
	 * @return ?M17N
	 */
	public static function translator(): ?M17N {
		if (self::$_m17n === null) {
			if (!class_exists('\\aneya\\M17N\\M17N')) {
				self::logger()->warning('aneya/m17n is not available to be able to use CMS::translator()');
				return null;
			}

			self::$_m17n = M17N::instance();
		}

		return self::$_m17n;
	}
	#endregion

	#region Getter/Setter Methods
	/**
	 * Adds a configuration variable.
	 * Should be only used when variables need to be set prior initializing the framework
	 *
	 * @param string $key
	 * @param mixed  $value
	 */
	public static function addVariable(string $key, mixed $value) {
		if (!(self::$_cfg instanceof Configuration))
			self::$_cfg = new Configuration();

		self::$_cfg->add($key, $value);
	}

	/**
	 * Returns/sets the full filesystem path to the application's root directory (usually one level before the web root directory)
	 *
	 * @param string|null $path (optional)
	 *
	 * @return string
	 */
	public static function appPath(string $path = null): string {
		if (strlen($path ?? '') > 0) {
			self::$_appPath = $path;
		}
		return self::$_appPath;
	}

	/**
	 * Returns / sets the relative path from application's root directory to the web directory (usually named 'www' or 'web')
	 *
	 * @param string|null $path (optional) Must be *relative* to application's root directory
	 *
	 * @return string
	 */
	public static function webDir(string $path = null): string {
		if (strlen($path ?? '') > 0) {
			#region Fix slashes (/) before setting the path
			if (strpos($path, '/') !== 0)
				$path = '/' . $path;
			if (strrpos($path, '/') == strlen($path) - 1)
				$path = substr($path, 0, -1);
			#endregion

			self::$_webDir = $path;
		}
		return self::$_webDir;
	}

	/** Returns the full filesystem path to the application's web root directory */
	public static function webPath(): string {
		return self::$_appPath . self::$_webDir;
	}

	/**
	 * Gets/Sets framework's logging level.
	 * Available levels are: Audit::LOG_* constants
	 */
	public static function logLevel(int $level = null): int {
		if ($level !== null)
			CMS::cfg()->env->logLevel = $level;

		return CMS::cfg()->env->logLevel;
	}

	/** Returns all available application namespaces defined in the framework */
	public static function namespaces(): AppNamespaceCollection {
		return self::$_appNamespaces;
	}

	/**
	 * Gets/sets framework's currently running application namespace.
	 *
	 * If setting/switching namespace, any namespace-specific configuration will be applied during the switch.
	 */
	public static function ns(string $tag = null): ?AppNamespace {
		if (strlen($tag ?? '') > 0) {
			$ns = self::$_appNamespaces->get($tag);

			if ($ns instanceof AppNamespace && (!isset(self::$_ns) || $tag != self::$_ns->tag)) {
				self::$_ns = $ns;

				// Apply namespace configuration
				self::$_ns->activate();
			}
		}

		return self::$_ns;
	}

	/** Returns an associative array with themes' tag as key and class name as value */
	public static function themes(): array {
		return self::$_themes;
	}

	/** Returns the license key */
	public static function licenseKey(): string {
		return self::$_licenseKey;
	}

	/** Returns the framework's version in x.x.x.x format */
	public static function version(): string {
		return self::$_version;
	}
	#endregion

	#region Schema methods
	/**
	 * Returns all available database schemas defined in the framework.
	 * Each array object has the following properties set: tag, host, port, driver, class, database, schema
	 *
	 * @return \stdClass[]
	 */
	public static function schemas(): array {
		$schemas = [];
		foreach (self::$_cfg->db as $tag => $db) {
			$schema = new \stdClass();
			$schema->tag = $tag;
			$schema->host = $db->host;
			$schema->port = $db->port;
			$schema->driver = $db->driver;
			$schema->class = "\\aneya\\Core\\Data\\Drivers\\$db->driver";
			$schema->database = $db->database;
			$schema->schema = $db->schema;

			$schemas[] = $schema;
		}

		return $schemas;
	}
	#endregion
	#endregion
}
