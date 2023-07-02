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

namespace aneya\Security;

use aneya\Core\Action;
use aneya\Core\ActionEventArgs;
use aneya\Core\AppNamespace;
use aneya\Core\Cache;
use aneya\Core\Cacheable;
use aneya\Core\CMS;
use aneya\Core\Collection;
use aneya\Core\CoreObject;
use aneya\Core\Data\Database;
use aneya\Core\Data\DataColumn;
use aneya\Core\Data\DataFilter;
use aneya\Core\Data\DataRow;
use aneya\Core\Data\DataRowSaveEventArgs;
use aneya\Core\Data\DataSet;
use aneya\Core\Data\ORM\DataObjectMapping;
use aneya\Core\Data\ORM\IDataObject;
use aneya\Core\Data\ORM\ORM;
use aneya\Core\Encrypt;
use aneya\Core\Environment\Net;
use aneya\Core\EventStatus;
use aneya\Core\ICacheable;
use aneya\Core\IStorable;
use aneya\Core\Status;
use aneya\Core\Storable;
use aneya\Core\Utils\DateUtils;
use aneya\Messaging\Messaging;
use aneya\Security\Authentication\AuthCookie;
use aneya\Security\Authentication\Authentication;
use aneya\Security\Authentication\AuthenticationEventArgs;
use aneya\Security\Authentication\AuthenticationOptions;
use aneya\Security\Authentication\IAuthenticatable;
use aneya\Snippets\Snippet;

class User extends CoreObject implements ICacheable, IDataObject, IStorable, IAuthenticatable, \JsonSerializable {
	use Cacheable, Storable;

	#region Constants
	const StatusInvalid  = -1;
	const StatusPending  = 0;
	const Status1stLogin = 1;
	const StatusActive   = 2;
	const StatusLocked   = 3;
	const StatusDisabled = 9;

	const LoginLocked = -2;
	const LoginFailed = -1;
	const LoginPassed = 2;
	#endregion

	#region Event constants
	const EventOnAction = 'OnAction';
	const EventOnLogin  = 'OnLogin';
	const EventOnLogout = 'OnLogout';
	#endregion

	#region Properties
	#region Core properties
	public ?int $id = null;
	public ?string $firstName = null;
	public ?string $lastName = null;
	public ?string $email = null;
	public ?string $phone = null;
	public ?string $username = null;
	/** @var ?string User's encrypted password */
	public ?string $encPassword = null;
	public ?string $jobTitle = null;
	public ?string $defaultLanguage = null;
	public ?string $description = null;
	public ?int $status = null;
	public ?string $activationCode = null;
	/** @var bool Indicates if this is the first time the user is logged in */
	public ?bool $firstLogin = null;
	/** @var bool Indicates if the JSON serialization should expose sensitive properties such as user's id. */
	public bool $jsonTrusted = false;

	public ?\DateTime $dateCreated = null;
	public ?\DateTime $dateActivated = null;
	public ?\DateTime $dateAccessed = null;
	/** @var ?string User's profile photo URL */
	public ?string $photoUrl = null;
	/** @var ?string User's namespace */
	public ?string $NS = null;

	public array $roles = [];
	public array $permissions = [];
	public array $namespaces = [];
	#endregion

	#region Protected properties
	protected Messaging $_messaging;
	#endregion

	#region Static properties
	/** @var string */
	protected static string $_dbTag = CMS::CMS_DB_TAG;

	/** @var User[] The currently logged-in user, per namespace */
	protected static array $_user = array ();
	protected static string $_activationUrl = '/{lang}/user/activate/{code}';
	protected static string $_resetPwdUrl = '/{lang}/user/reset/{code}';

	protected static string $_authenticationClass = '\\aneya\\Security\\Authentication\\BasicAuthentication';

	protected static array $__classProperties = ['deny' => ['_messaging', '_notifications', 'jsonTrusted']];

	/** @var string[] Stores all known fully-qualified User-derived classes */
	protected static array $_userClasses = ['aneya\\Security\\User'];
	#endregion
	#endregion

	#region Constructor and initialization methods
	/**
	 * User constructor.
	 * @param int|string|mixed $id (optional)
	 * @throws \Exception
	 */
	public function __construct($id = null) {
		self::hooks()->register([Authentication::EventOnAuthenticationValidated, Authentication::EventOnAuthenticated, Authentication::EventOnAuthenticationFailed, self::EventOnLogin, self::EventOnLogout]);

		if (is_numeric($id)) {
			$column = $this->orm()->row()->columnAt('userId');
			if ($column == null) {
				// Most probably a descendant class joined into ORM more database tables than the default "cms_users" table
				$column = $this->orm()->row()->columnAt('cms_users.userId');

				// If still null, check if cms_users table has been given an alias
				if ($column == null) {
					/** @var DataSet $ds */
					$ds = $this->orm()->row()->parent;
					if ($ds instanceof DataSet) {
						$tbl = $ds->tables->get('cms_users')->alias;
						if (strlen($tbl) > 0) {
							$column = $this->orm()->row()->columnAt($tbl . '.userId');
							if ($column == null) {
								$column = $this->orm()->row()->columnAt($tbl . '_userId');
							}
						}
					}
				}
			}
			$filter = new DataFilter ($column, DataFilter::Equals, $id);
			$ret = $this->orm()->retrieve($filter);
			if ($ret === false)
				throw new \Exception("User with Id $id was not found for User-derived class " . static::class);
		}
	}

	/**
	 * Initializes the User accounts subsystem.
	 *
	 * It is already called internally and there is no need to be by outside scripts.
	 */
	public static function init() {
		static::hooksSt()->register([Authentication::EventOnAuthenticationValidated, Authentication::EventOnAuthenticated, Authentication::EventOnAuthenticationFailed, 'OnLogin', 'OnLogout']);

		static::onSt(Authentication::EventOnAuthenticationValidated, function (AuthenticationEventArgs $args) {
			static::onAuthenticationValidatedSt($args);
		});
		static::onSt(Authentication::EventOnAuthenticated, function (AuthenticationEventArgs $args) {
			static::onAuthenticatedSt($args);
		});
		static::onSt(Authentication::EventOnAuthenticationFailed, function (AuthenticationEventArgs $args) {
			static::onAuthenticationFailedSt($args);
		});
	}

	/** Returns the User accounts subsystem database instance */
	public function db(): Database {
		return CMS::db();
	}
	#endregion

	#region Methods
	#region User methods
	#region Login / Activation functions
	/**
	 * Generates an activation code for the user account
	 */
	public function generateActivationCode(): string {
		return $this->activationCode = preg_replace('/[^a-z\d]/i', '', md5($this->id . date('Y-m-d H:i:s') . rand(10000, 99999)));
	}

	/**
	 * Returns the User's activation URL address
	 *
	 * @param bool $fullUrl If true, the full URL address will be returned, including the protocol plus the server's name.
	 * @param bool $secure  If true, https:// will be returned instead of http:// as the URL prefix.
	 *
	 * @return string
	 */
	public function getActivationUrl(bool $fullUrl = true, bool $secure = false): string {
		$ret = ($fullUrl) ? 'http' . ($secure ? 's' : '') . '://' . $_SERVER['SERVER_NAME'] : '';

		if (strpos(static::$_activationUrl, '/') !== 0)
			$ret .= '/';

		$url = static::$_activationUrl;
		$url = str_ireplace('{lang}', CMS::translator()->currentLanguage()->code, $url);
		$url = str_ireplace('{code}', $this->activationCode, $url);
		$url = str_ireplace('{id}', $this->id, $url);
		$url = str_ireplace('{username}', $this->username, $url);

		$ret .= $url;

		return $ret;
	}
	#endregion

	#region Role / permission functions
	/** Returns all user's accessible namespaces */
	public function namespaces(): Collection {
		return (new Collection())->addRange(array_map(function (string $ns) {
			return CMS::namespaces()->get($ns);
		}, $this->namespaces));
	}

	/** Returns all user's assigned roles */
	public function roles(bool $forceRetrieve = false): RoleCollection {
		return (new RoleCollection())->addRange(array_map(function (string $role) {
			return CMS::env()->roles()->getByCode($role);
		}, $this->roles));
	}

	/** Returns all granted permissions of User */
	public function permissions(bool $forceRetrieve = false): PermissionCollection {
		$permissions = new PermissionCollection();

		// Include user's roles' permissions
		$this->roles()->forEach(function (Role $role) use ($permissions) {
			$permissions->addRange($role->permissions->all());
		});

		// Include user's custom set permissions
		$permissions->addRange(array_map(function (string $permission) {
			return CMS::env()->permissions()->getByCode($permission);
		}, $this->permissions));

		return $permissions;
	}

	/**
	 * Checks if User has the given permission either directly or through their associated role(s)
	 *
	 * @param Permission|string $permission
	 *
	 * @return bool
	 */
	public function hasPermission($permission): bool {
		if ($this->permissions()->contains($permission))
			return true;

		foreach ($this->roles()->all() as $role) {
			if ($role->permissions->contains($permission))
				return true;
		}

		return false;
	}
	#endregion
	#endregion

	#region Notification & messaging functions
	/**
	 * Sends an activation e-mail to the Account's e-mail address.
	 *
	 * Applies only to User accounts with status StatusPending; otherwise returns false
	 *
	 * @param bool $useSecureHttp if true, https:// prefix will be used for the activation link instead of http://
	 *
	 * @return Status true if the username was found and the e-mail was sent successfully; false otherwise
	 */
	public function sendActivationEmail(bool $useSecureHttp = false): Status {
		$lang = CMS::translator()->currentLanguage()->code;
		$s = new Snippet ();
		$s->loadContentFromDb('system-user-activation-email');

		$s->params->username = $this->username;
		$s->params->firstname = $this->firstName;
		$s->params->lastname = $this->lastName;
		$s->params->fullname = implode(' ', [$this->firstName, $this->lastName]);
		$s->params->email = $this->email;
		$s->params->site = $_SERVER['SERVER_NAME'];
		$s->params->lang = $lang;
		$s->params->activation_code = $this->activationCode;
		$s->params->activation_link = $this->getActivationUrl(true, $useSecureHttp);
		$message = $s->compile();

		return static::systemEmail($this, CMS::translator()->translate('account activation', 'cms'), $message);
	}

	/**
	 * Sends a template-based e-mail to the user.
	 *
	 * Requires aneya/snippets module to be enabled.
	 *
	 * Upon success, status's data will hold e-mail's subject and body as an \stdClass object {subject, body}.
	 *
	 * @param string $snippetTag
	 * @param array $params
	 *
	 * @return Status
	 */
	public function sendEmailFromTemplate(string $snippetTag, array $params = []): Status {
		if (!CMS::modules()->isAvailable('aneya/snippets'))
			return new Status(false, 'Module unavailable', 0, 'aneya/snippets module is required for this method.');

		$s = new Snippet();
		$s->loadContentFromDb($snippetTag);

		foreach ($params as $param => $value)
			$s->params->add($param, $value);

		$subject = $s->title;
		$body = $s->compile();

		// Send the e-mail to the user
		$status = Net::sendMail(CMS::app()->systemEmail, $this->email, $subject, $body);

		$status->data = new \stdClass();
		$status->data->subject = $subject;
		$status->data->body = $body;

		return $status;
	}

	/** Returns User's messaging subsystem */
	public function messaging(): Messaging {
		if (!isset($this->_messaging)) {
			$this->_messaging = new Messaging($this);
		}

		return $this->_messaging;
	}
	#endregion

	#region Authentication methods
	/**
	 * Authenticates against the given credentials and returns the authentication status.
	 * Upon success, status's data property will contain the User object that was authenticated and instantiated.
	 *
	 * @param string $username
	 * @param string $password
	 * @param ?User                  $user
	 * @param ?AuthenticationOptions $options
	 *
	 * @return EventStatus
	 */
	public static function authenticate(string $username, string $password, User $user = null, AuthenticationOptions $options = null): EventStatus {
		try {
			if ($options == null)
				$options = new AuthenticationOptions();

			/** @var Authentication $auth */
			$auth = new static::$_authenticationClass();
			$status = $auth->authenticate(['username' => $username, 'password' => $password], $user, $options);
		}
		catch (\Exception $e) {
			$status = new EventStatus(false, 'Internal error occurred during authentication', Authentication::AuthFailed, $e->getMessage());
		}

		return $status;
	}

	public function getUID(): string {
		return $this->username;
	}

	public function getPassword(): string {
		return $this->encPassword;
	}

	/**
	 * Sends an authentication cookie to the browser via the response headers.
	 *
	 * @see AuthCookie::setAuthCookie()
	 */
	public function setRememberToken(string $namespace): bool {
		return static::authCookie()->setAuthCookie($namespace, $this, true);
	}

	/**
	 * Removes user's authentication token for the given namespace
	 *@see AuthCookie::clear()
	 */
	public function removeRememberToken(string $namespace) {
		static::authCookie()->clear($namespace);
	}

	public function login(string $namespace = null) {
		if (empty($namespace))
			$namespace = CMS::ns()->tag;

		// Set user's default language code
		if (strlen($this->defaultLanguage) > 0) {
			CMS::translator()->setCurrentLanguage($this->defaultLanguage);
		}

		CMS::logger()->info("User [id: $this->id] logged in.", [$namespace]);

		// Store in internal cache
		static::$_user[$namespace] = $this;

		// Set the user in the environment for later access
		static::authCookie()->setAuthCookie($namespace, $this);

		// Update user's last access date
		$this->updateLastAccess();
	}

	public function logout(string $namespace = null) {
		if (empty($namespace))
			$namespace = CMS::ns()->tag;

		// Unset user from environment
		unset(static::$_user[$namespace]);

		// Clear authentication cookies & Session
		static::authCookie()->clear($namespace);
	}

	/** Updates User's last access information to current time */
	public function updateLastAccess(): static {
		$this->dateAccessed = new \DateTime();

		$schema = CMS::db()->getSchemaName();
		$sql = match (CMS::db()->getDriverType()) {
			Database::PostgreSQL => "UPDATE $schema.cms_users SET date_accessed=timezone('UTC', now()) WHERE user_id=:user_id",
			default => "UPDATE $schema.cms_users SET date_accessed=UTC_TIMESTAMP() WHERE user_id=:user_id",
		};

		CMS::db()->execute($sql, [':user_id' => $this->id]);

		return $this;
	}

	/** Synchronizes User's last access information from the database to the instance. */
	public function fetchLastAccess(): static {
		$schema = CMS::db()->getSchemaName();
		$col = $this->orm()->dataSet()->columns->get('dateAccessed');
		$sql = sprintf('SELECT %s FROM %s.cms_users WHERE user_id=:user_id', CMS::db()->getColumnExpression($col, false, true), $schema);
		$date = CMS::db()->fetchColumn($sql, $col->tag, [':user_id' => $this->id]);
		if ($date != null) {
			try {
				$this->dateAccessed = \DateTime::createFromFormat(CMS::db()->getDateNativeFormat(), $date, new \DateTimeZone('UTC'))->setTimezone(CMS::timezone());
			}
			catch (\Exception) {}
		}

		return $this;
	}
	#endregion

	#region Action methods
	/** Performs an action */
	public function action(Action|string $action): EventStatus {
		if (!($action instanceof Action))
			$action = new Action($action);

		$args = new ActionEventArgs($this, $action);
		$statuses = $this->trigger(self::EventOnAction, $args);
		foreach ($statuses as $st) {
			if ($st->isHandled) {
				$status = $st;
				break;
			}
		}

		// If no user-defined listener handled the event, call the class's own action handling method
		if (!isset ($status))
			$status = $this->onAction($args);

		return $status;
	}
	#endregion

	#region Interface methods
	/** Forces the instance to expire its cache in all registered User classes. */
	public function expireCache() {
		static::expireCacheAll($this->id);
	}
	#endregion
	#endregion

	#region Static user methods
	/** Returns AuthCookie's singleton instance to get/set authentication cookies per namespace. */
	public static function authCookie(): AuthCookie {
		return AuthCookie::instance();
	}

	/**
	 * Sends a system e-mail to the given User recipient
	 *
	 * @param User   $to      The recipient of the message
	 * @param string $subject The e-mail's subject
	 * @param string $message The e-mail's message body
	 * @param bool $isHtml  (default true) if true, text/html will be used for content/type
	 *
	 * @return Status true if user has e-mail address set and the e-mail was sent successfully; false otherwise
	 */
	public static function systemEmail(User $to, string $subject, string $message, bool $isHtml = true): Status {
		if (strlen($to->email) == 0) {
			return new Status (false, 'E-mail address not set');
		}

		return Net::sendMail(CMS::app()->systemEmail, $to->email, $subject, $message, $isHtml);
	}

	/**
	 * Returns a user's ID given their activation code.
	 *
	 * @param string $activation_code The account's activation code.
	 *
	 * @return int The user's ID; 0 if no user found with the given activation code
	 */
	public static function getIdByActivationCode(string $activation_code): int {
		$db = CMS::db(static::$_dbTag);

		$schema = $db->getSchemaName();
		// Check if user exists and has the right credentials
		$sql = "SELECT user_id FROM $schema.cms_users WHERE activation_code=:activation_code";
		return (int)$db->fetchColumn($sql, 'user_id', array (':activation_code' => $activation_code));
	}

	/**
	 * Returns a user's ID given their e-mail address.
	 *
	 * @param string $email The account's e-mail address.
	 * @param int[] $statuses (optional) Only search for users with the given statuses.
	 *
	 * @return int The user's ID; 0 if no user found with the given e-mail address
	 */
	public static function getIdByEmail(string $email, array $statuses = []): int {
		$db = CMS::db(static::$_dbTag);

		$schema = $db->getSchemaName();
		if (count($statuses) > 0)
			$sql = sprintf("SELECT user_id FROM $schema.cms_users WHERE email=:email AND status IN (%s)", implode(', ', $statuses));
		else
			$sql = "SELECT user_id FROM $schema.cms_users WHERE email=:email";

		return (int)$db->fetchColumn($sql, 'user_id', [':email' => $email]);
	}

	/**
	 * Returns a user's ID given their username.
	 *
	 * @param string $username The account's username.
	 * @param int[] $statuses (optional) Only search for users with the given statuses.
	 *
	 * @return int The user's ID; 0 if no user found with the given username
	 */
	public static function getIdByUsername(string $username, array $statuses = []): int {
		$db = CMS::db(static::$_dbTag);
		$schema = $db->getSchemaName();

		if (count($statuses) > 0)
			$sql = sprintf("SELECT user_id FROM $schema.cms_users WHERE username=:username AND status IN (%s)", implode(', ', $statuses));
		else
			$sql = "SELECT user_id FROM $schema.cms_users WHERE username=:username";

		return (int)$db->fetchColumn($sql, 'user_id', [':username' => $username]);
	}

	/**
	 * Returns a user's first and last name given their id. If array of Ids is given, an hash array with users first/last name will be returned instead.
	 *
	 * Return format can be specified by the second argument.
	 *
	 * @param int|int[] $userId
	 * @param string $format
	 *
	 * @return string|string[]|null
	 */
	public static function getFullNameById(array|int $userId, string $format = '{firstName} {lastName}'): array|string|null {
		$db = CMS::db(static::$_dbTag);
		$schema = $db->getSchemaName();

		if (is_int($userId)) {
			$sql = "SELECT first_name, last_name, username, email FROM $schema.cms_users WHERE user_id=:user_id";
			$row = $db->fetch($sql, [':user_id' => $userId]);
			if (!$row)
				return null;

			return trim(str_replace(['{firstName}', '{lastName}', '{username}', '{email}'], [$row['first_name'], $row['last_name'], $row['username'], $row['email']], $format));
		}
		elseif (is_array($userId)) {
			if (count($userId) == 0)
				return [];

			// Ensure all ids are numeric
			array_walk($userId, function (&$item) {
				$item = (int)$item;
			});
			$ids = implode(',', $userId);

			$sql = "SELECT user_id, first_name, last_name, username, email FROM $schema.cms_users WHERE user_id IN ($ids)";
			$rows = $db->fetchAll($sql);
			if (!$rows)
				return [];

			$ret = [];

			foreach ($rows as $row) {
				$ret[(int)$row['user_id']] = trim(str_replace(['{firstName}', '{lastName}', '{username}', '{email}'], [$row['first_name'], $row['last_name'], $row['username'], $row['email']], $format));
			}

			return $ret;
		}

		return null;
	}

	/** Returns the global URL format to the user activation page */
	public static function getActivationUrlFormat(): string {
		return static::$_activationUrl;
	}

	/**
	 * Sets the global URL format to the user activation page
	 * e.g. /{lang}/user/activate/{code}
	 *
	 * Available variables are:
	 * {lang}     Language code
	 * {code}     User's activation code
	 * {id}       User Id
	 * {username} User's username
	 *
	 * @param string $url
	 */
	public static function setActivationUrlFormat(string $url) {
		static::$_activationUrl = $url;
	}

	/**
	 * Returns true if a user is currently logged in; false otherwise
	 *
	 * @param string|null $namespace (optional) The namespace to search for user information in $_SESSION
	 *
	 * @return boolean
	 */
	public static function isLoggedIn(string $namespace = null): bool {
		if (empty($namespace))
			$namespace = CMS::ns()->tag;

		return (static::get($namespace) instanceof User);
	}

	/** Returns the currently logged-in user instance. null if no user is logged in. */
	public static function get(string $namespace = null): ?User {
		if (strlen($namespace) == 0)
			$namespace = CMS::ns()->tag;

		// Check if user information is already stored in internal cache
		if (isset(static::$_user[$namespace]))
			return static::$_user[$namespace];

		// Check if user has login information in session
		if (CMS::session()->exists('user_id', $namespace)) {
			/** @var User $class */
			$class = CMS::session()->get('user_class', $namespace);
			static::$_user[$namespace] = $class::load((int)CMS::session()->get('user_id', $namespace));
			return static::$_user[$namespace];
		}

		// Check if user information is available in authentication cookie
		$user = static::getByAuthenticationToken($namespace);
		if ($user instanceof User) {
			// If there's no user information in session,
			// but there's authentication token information in user's browser,
			// then auto-authenticate user.
			$user->login($namespace);

			return static::$_user[$namespace] = $user;
		}

		return null;
	}

	/** Returns the User-derived class that is defined for the given namespace (or current namespace if no argument is passed). */
	public static function getClassByNamespace(string $namespace = null): ?string {
		if (strlen($namespace ?? '') > 0) {
			$class = CMS::namespaces()->get($namespace)->userClass;
			if ($class === null)
				$class = '\\aneya\\Security\\User';

			if ($class != '\\aneya\\Security\\User' && !is_subclass_of($class, '\\aneya\\Security\\User')) {
				CMS::logger()->error("User::load() Failed to load User instance. Class $class is not a subclass of \\aneya\\Core\\Security\\User");
				return null;
			}
		}
		else
			$class = static::class;

		return $class;
	}

	/** @inheritdoc */
	public static function load($uid = null, callable $callback = null, string $namespace = null): ?User {
		/** @var User $obj */
		$obj = static::loadFromCache($uid);
		if ($obj == null) {
			$class = static::getClassByNamespace($namespace);
			if ($class == null)
				return null;

			try {
				/** @var User $obj */
				$obj = new $class ($uid);
			}
			catch (\Exception $e) {
				CMS::logger()->error("User::load() Failed to load User instance. Exception message: " . $e->getMessage());
				return null;
			}

			// Cache object for performance
			Cache::store($obj);
		}
		else {
			// Fetch last access from the database
			$obj->fetchLastAccess();

			// Synchronize ORM row with User object and explicitly set ORM row's state as unchanged
			$obj->orm()->synchronize();
			$obj->orm()->row()->setState(DataRow::StateUnchanged);
		}

		if (is_callable($callback)) {
			call_user_func($callback, $obj);
		}

		return $obj;
	}

	/** Returns a new User (or User derived) instance, considering the given namespace (if any) */
	public static function loadNew(string $namespace = null): ?User {
		$class = static::getClassByNamespace($namespace);
		if ($class == null)
			return new User();

		try {
			return new $class();
		}
		catch (\Exception $e) {
			CMS::logger()->error("User::loadNew() Failed to load User instance. Exception message: " . $e->getMessage());
			return null;
		}
	}

	/** Returns a User (or User derived) class by retrieving the authentication token in user's browser (if any) */
	public static function getByAuthenticationToken(string $namespace): ?User {
		$cookie = static::authCookie()->getToken($namespace);
		if (strlen($cookie) > 0) {
			try {
				$ns = CMS::namespaces()->get($namespace);
				$token = Token::decode($cookie, $ns->options->authCookie->key);

				return $token->user();
			}
			catch (\Exception $e) {}
		}

		return null;
	}

	/**
	 * Returns true if a user account exists in the framework.
	 *
	 * @param string    $username The user account's username to check
	 * @param int|int[] $exclude  (optional) A user Id or array of user Ids to exclude when searching
	 * @param int[] $statuses  (optional) Limit search to the given user statuses only
	 *
	 * @return bool true if a user account by that username exists; false otherwise
	 */
	public static function existsUsername(string $username, $exclude = null, array $statuses = []): bool {
		$db = CMS::db(static::$_dbTag);
		$schema = $db->getSchemaName();
		$sql = "SELECT user_id FROM $schema.cms_users WHERE username=:username";
		if (count($statuses) > 0)
			$sql .= " AND status IN (" . implode(', ', $statuses) . ')';
		if (is_int($exclude))
			$sql .= " AND user_id<>$exclude";
		elseif (is_array($exclude)) {
			$ids = array ();
			foreach ($exclude as $id)
				$ids[] = (int)$id;

			$exclude = implode(', ', $ids);
			$sql .= " AND user_id NOT IN ($exclude)";
		}

		$ret = $db->fetchColumn($sql, 'user_id', array (':username' => $username));
		return ((int)$ret != 0);
	}

	/**
	 * Returns true if an e-mail is associated with a pending, active or locked account
	 *
	 * @param string $email The user account's email address to check
	 * @param int|int[] (optional) $exclude A user Id or array of user Ids to exclude when searching
	 *
	 * @return bool true if a user account by that e-mail exists; false otherwise
	 */
	public static function existsEmail(string $email, $exclude = null): bool {
		$db = CMS::db(static::$_dbTag);
		$schema = $db->getSchemaName();

		$sql = "SELECT user_id FROM $schema.cms_users WHERE email=:email AND status IN (" . implode(', ', array (self::StatusPending, self::StatusActive, self::StatusLocked)) . ')';
		if (is_int($exclude))
			$sql .= " AND user_id<>$exclude";
		elseif (is_array($exclude)) {
			$ids = array ();
			foreach ($exclude as $id)
				$ids[] = (int)$id;

			$exclude = implode(', ', $ids);
			$sql .= " AND user_id NOT IN ($exclude)";
		}

		$ret = $db->fetchColumn($sql, 'user_id', array (':email' => $email));
		return ((int)$ret != 0);
	}

	/**
	 * Sends the password reset e-mail to the Account's e-mail address.
	 *
	 * Applies only to User accounts with status ST_ACTIVE or Status1stLogin; otherwise returns false
	 *
	 * @param string $username   The Account's username
	 * @param string $linkFormat The link template to use for the reset page URL link (default is: "http://{site}/{lang}/user/resetpwd/{activation_code}")
	 *
	 * @return Status true if the username was found and the e-mail was sent successfully; false otherwise
	 */
	public static function sendPasswordResetEmail(string $username, string $linkFormat = 'https://{domain}/{lang}/user/reset/{code}'): Status {
		$db = CMS::db(static::$_dbTag);
		$schema = $db->getSchemaName();

		$sql = "SELECT user_id, activation_code, first_name, last_name, username, email
				FROM $schema.cms_users
				WHERE username=:username AND status IN (:statuses)";
		$row = $db->fetch($sql, array (':username' => $username, ':statuses' => self::StatusActive . "," . self::StatusLocked));
		if (!$row)
			return new Status(false);

		$lang = CMS::translator()->currentLanguage()->code;
		$s = new Snippet ();
		$s->loadContentFromDb('system-user-password-reset-email');

		$s->params->username = $row['username'];
		$s->params->firstname = $row['first_name'];
		$s->params->lastname = $row['last_name'];
		$s->params->name = $s->params->fullname = trim($row['first_name'] . ' ' . $row['last_name']);
		$s->params->email = $row['email'];
		$s->params->domain = $_SERVER['SERVER_NAME'];
		$s->params->lang = $lang;
		$s->params->code = $s->params->activation_code = $row['activation_code'];
		$s->params->link = $s->params->activation_link = str_replace('{lang}', $lang, str_replace('{code}', $row['activation_code'], str_replace('{site}', $_SERVER['SERVER_NAME'], $linkFormat)));
		$message = $s->compile();

		return Net::sendMail(CMS::app()->systemEmail, $row['email'], CMS::translator()->translate('password reset', 'cms'), $message, 'text/html');
	}

	/**
	 * Returns a list with the fully-qualified class names of all known User-derived classes
	 *
	 * @return \string[]
	 */
	public static function getUserClasses(): array {
		return self::$_userClasses;
	}

	/** Expires all User-derived classes from cache. */
	public static function expireCacheAll(int|string $id) {
		$classes = User::getUserClasses();

		foreach ($classes as $class) {
			Cache::expire($id, $class);
		}
	}

	/** Registers a (fully-qualified) class name to the list of known User-derived classes */
	public static function registerUserClass($className) {
		if (!in_array($className, self::$_userClasses)) {
			self::$_userClasses[] = $className;
		}
	}
	#endregion

	#region Event methods
	protected function onAction(ActionEventArgs $args): ?EventStatus {
		switch ($args->action->command) {
			case 'save':
				return $this->save();

			default:
				return null;
		}
	}

	protected function onAuthenticationValidated(AuthenticationEventArgs $args) { }

	protected static function onAuthenticationValidatedSt(AuthenticationEventArgs $args) {
		if ($args->user == null && $args->uid > 0) {
			if (strlen($args->options->userClass) > 0 && is_subclass_of($args->options->userClass, '\\aneya\\Security\\User')) {
				/** @var User $class Use the fully qualified class name set in the authentication options */
				$class = $args->options->userClass;
				$args->user = $class::load($args->uid);
			}
			elseif (($ns = CMS::namespaces()->get($args->options->namespace)) instanceof AppNamespace && strlen($ns->userClass) > 0 && is_subclass_of($ns->userClass, '\\aneya\\Security\\User')) {
				/** @var User $class Use the fully qualified class name set as default in framework's configuration */
				$class = $ns->userClass;
				$args->user = $class::load($args->uid);
			}
			else
				$args->user = User::load($args->uid);
		}
	}

	protected function onAuthenticated(AuthenticationEventArgs $args) { }

	protected static function onAuthenticatedSt(AuthenticationEventArgs $args) { }

	protected function onAuthenticationFailed(AuthenticationEventArgs $args) { }

	protected static function onAuthenticationFailedSt(AuthenticationEventArgs $args) { }
	#endregion

	#region ORM methods
	protected static function onORM(): DataObjectMapping {
		$ds = static::classDataSet(CMS::db()->schema->getDataSet('cms_users', null, true));
		$ds->mapClass(static::class);
		$orm = ORM::dataSetToMapping($ds, static::class);

		// Explicitly set roles, permissions & namespaces columns datatype to ARRAY for RDBMSes with no native ARRAY type
		$ds->columns->get('roles')->dataType = DataColumn::DataTypeArray;
		$ds->columns->get('permissions')->dataType = DataColumn::DataTypeArray;
		$ds->columns->get('namespaces')->dataType = DataColumn::DataTypeArray;

		$orm->getProperty('userId')->propertyName = 'id';
		$orm->getProperty('username')->propertyName = 'username';
		$orm->getProperty('password')->propertyName = 'encPassword';
		$orm->first()->properties->remove($orm->getProperty('dateDisabled'));

		$orm->getProperty('username')->column->isRequired = true;
		$orm->getProperty('defaultLanguage')->column->isRequired = true;
		$prop = $orm->getProperty('status');
		$prop->column->isRequired = true;
		$prop->column->defaultValue = static::StatusPending;
		$prop = $orm->getProperty('encPassword');
		$prop->column->isRequired = false;

		return $orm;
	}

	protected function onSaving(DataRowSaveEventArgs $args): EventStatus {
		if (strlen($this->encPassword) > 0 && !Encrypt::isHash($this->encPassword)) {
			$this->encPassword = Encrypt::hashPassword($this->encPassword);
			$args->row->setValue('password', $this->encPassword);
		}

		if ($this->id > 0) {

		}
		else {
			// Setup new user properties
			if (!isset($this->dateCreated)) {
				$this->dateCreated = new \DateTime();
				$args->row->setValue('dateCreated', $this->dateCreated);
			}

			if ($this->status == static::StatusActive && $this->dateActivated === null) {
				$this->dateActivated = new \DateTime();
				$args->row->setValue('dateActivated', $this->dateActivated);
			}

			if (strlen($this->activationCode) === 0) {
				try {
					$this->activationCode = md5($this->username . Encrypt::generateKey());
				}
				catch (\Exception $e) {}
				finally {
					$args->row->setValue('activationCode', $this->activationCode);
				}
			}
		}

		return new EventStatus();
	}

	protected function onSaved(DataRowSaveEventArgs $args) {
		static::expireCacheAll($this->id);
	}
	#endregion

	#region Javascript/JSON methods
	/**
	 * Applies configuration from an \stdClass instance.
	 *
	 * @param \stdClass|object $cfg
	 * @param bool $strict If true, unset configuration properties will clear instance's properties back to their default value.
	 *
	 * @return User
	 */
	public function applyJsonCfg(object $cfg, bool $strict = false): User {
		if ($strict) {
			$this->id = $cfg->id ?? null;
			$this->firstName = $cfg->firstName ?? '';
			$this->lastName = $cfg->lastName ?? '';
			$this->username = $cfg->username ?? '';
			$this->email = $cfg->email ?? '';
			$this->photo = $cfg->photo ?? '';
			$this->jobTitle = $cfg->jobTitle ?? '';
			$this->encPassword = $cfg->encPassword ?? $cfg->password ?? '';

			$this->status = $cfg->status ?? static::StatusPending;
			$this->defaultLanguage = $cfg->defaultLanguage ?? 'en';

			$this->roles = is_array($cfg->roles) ? $cfg->roles : [];
			$this->permissions = is_array($cfg->permissions) ? $cfg->permissions : [];
			$this->namespaces = is_array($cfg->namespaces) ? $cfg->namespaces : [];
		}
		else {
			$this->id = $cfg->id ?? $this->id ?? null;
			$this->firstName = $cfg->firstName ?? $this->firstName ?? '';
			$this->lastName = $cfg->lastName ?? $this->lastName ?? '';
			$this->username = $cfg->username ?? $this->username ?? '';
			$this->email = $cfg->email ?? $this->email ?? '';
			$this->photo = $cfg->photo ?? $this->photo ?? '';
			$this->jobTitle = $cfg->jobTitle ?? $this->jobTitle ?? '';

			// Leave password as is if configuration is empty
			if (strlen($cfg->encPassword) > 0)
				$this->encPassword = $cfg->encPassword;
			elseif (strlen($cfg->password) > 0)
				$this->encPassword = $cfg->password;

			$this->status = $cfg->status ?? $this->status ?? static::StatusPending;
			$this->defaultLanguage = $cfg->defaultLanguage ?? $this->defaultLanguage ?? 'en';

			if (isset($cfg->roles) && is_array($cfg->roles))
				$this->roles = $cfg->roles;
			if (isset($cfg->permissions) && is_array($cfg->permissions))
				$this->permissions = $cfg->permissions;
			if (isset($cfg->namespaces) && is_array($cfg->namespaces))
				$this->namespaces = $cfg->namespaces;
		}

		return $this;
	}

	#[\ReturnTypeWillChange]
	public function jsonSerialize($definition = false): array {
		if ($definition) {
			return [
				'id'          => $this->id,
				'firstName'   => $this->firstName,
				'lastName'    => $this->lastName,
				'username'    => $this->username,
				'email'       => $this->email,
				'photo'       => $this->photoUrl,
				'jobTitle'    => $this->jobTitle,
				'roles'       => $this->roles,
				'permissions' => $this->permissions,
				'namespaces'  => $this->namespaces,
				'lastAccess'  => ($this->dateAccessed instanceof \DateTime) ? DateUtils::toJsDate($this->dateAccessed) : null,
				'status'      => $this->status,
				'defaultLanguage'	=> $this->defaultLanguage,
			];
		}
		else {
			$data = [
				'firstName'   => $this->firstName,
				'lastName'    => $this->lastName,
				'username'    => $this->username,
				'email'       => $this->email,
				'photo'       => $this->photoUrl,
				'jobTitle'    => $this->jobTitle,
				'roles'       => $this->roles,
				'permissions' => $this->permissions,
				'lastAccess'  => ($this->dateAccessed instanceof \DateTime) ? DateUtils::toJsDate($this->dateAccessed) : null
			];

			// If client script is trusted, expose user's sensitive properties
			if ($this->jsonTrusted) {
				$data['id'] = $this->id;
			}

			return $data;
		}
	}
	#endregion

	#region Interface methods
	public function getCacheUid(): int {
		return $this->id;
	}
	#endregion
}

// Call User class initialization method
User::init();
