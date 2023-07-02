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

use aneya\Core\ApplicationError;
use aneya\Core\AppNamespace;
use aneya\Core\CMS;
use aneya\Core\Data\DataColumn;
use aneya\Core\Data\DataFilter;
use aneya\Core\Data\DataFilterCollection;
use aneya\Core\Data\DataRow;
use aneya\Core\Data\DataSorting;
use aneya\Core\Data\DataTable;
use aneya\Core\EventStatus;
use aneya\Core\Status;
use aneya\Forms\Form;
use aneya\Routing\Request;
use aneya\Routing\RouteController;
use aneya\Routing\RouteEventArgs;
use aneya\Routing\RouteMatch;
use aneya\Security\Authentication\AuthenticationEventArgs;
use aneya\Security\Authentication\AuthenticationOptions;
use aneya\Security\Token;
use aneya\Security\User;
use Firebase\JWT\ExpiredException;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;

/**
 * Class ApiController
 * Used to control common API routes, such as API client authentication.
 * @package aneya\API
 */
abstract class ApiController extends RouteController {
	#region Constants
	#endregion

	#region Event constants
	/** Triggered when a user is authenticated. Passes an AuthenticationEventArgs with the authentication results, either successful or failure. */
	const EventOnAuthenticated = 'OnAuthenticated';

	/** Triggered when CRUD operation is being executed, allowing listeners to alter the entity or object before the CRUD operation. Passes an CrudEventArgs argument on listeners. */
	const EventOnExecutingCrud = 'OnExecutingCrud';
	/** Triggered when CRUD operation has been executed, allowing listeners to alter the data resulted from the CRUD operation. Passes an CrudEventArgs argument on listeners. */
	const EventOnExecutedCrud = 'OnExecutedCrud';
	#endregion

	#region Properties
	/** @var string */
	public string $version = '';

	/** @var bool If true, client credentials will be used as user credentials too */
	public bool $clientIsUser = false;

	public string $namespace = '';
	public string $serverKey;
	/** @var string Public certificate or key that if matched with the posted header X-Api-Trusted-Key during authorization, will mark user as trusted (usually client is an internal script). */
	public string $trustKey = '';
	public string $permission = '';

	/** @var ?ApiEventStatus The status that was returned by the authorization mechanism */
	public ?ApiEventStatus $authStatus;

	public int $defaultReturnLimit = 10;
	/** @var bool Indicates if API is stateless (authenticate on every request) or stateful */
	public bool $stateless = true;

	/** @var string $timezone TimeZone string to convert all output dates */
	public string $timezone = 'UTC';

	/** @var bool True if API requires SSL to allow connections (or not if false, or no preference if null) */
	public ?bool $forceSSL = null;
	/** @var int Defines after how many seconds tokens should expire */
	public int $tokenExpiresIn = 120;

	protected RouteEventArgs $_args;

	protected ?Logger $_logger = null;

	protected $_state;

	protected ?User $_user = null;
	protected bool $_userIsSet = false;

	protected ?User $_client = null;
	protected bool $_clientIsSet = false;
	#endregion

	#region Constructor
	public function __construct(ApiEventArgs $args = null, ApiOptions $options = null) {
		parent::__construct($args);

		$this->hooks()->register(self::EventOnAuthenticated);

		if ($options instanceof ApiOptions) {
			$this->version = $options->version;
			$this->namespace = $options->namespace;
			$this->serverKey = $options->serverKey;
			$this->trustKey = $options->trustKey;
			$this->permission = $options->permission;
			$this->tokenExpiresIn = $options->tokenExpiresIn;
		}
		else {
			$options = new \stdClass();
			$options->logLevel = Logger::ERROR;
			$options->logFile = CMS::appPath() . "/logs/api.$this->namespace.log";
		}

		try {
			$this->_logger = new Logger('api');
			$this->_logger->pushHandler(new StreamHandler($options->logFile, $options->logLevel));
		}
		catch (\Exception $e) { }
	}
	#endregion

	#region Object methods
	/** Return's API's default logger */
	public function logger(): ?Logger {
		return $this->_logger;
	}

	/** Returns the authenticated client's instance that has accessed the API or null if no client is authenticated. */
	public function client(): ?User {
		return $this->_client;
	}

	/** Returns the authenticated user's instance that has accessed the API or null if no user is authenticated. */
	public function user(): ?User {
		return $this->_user;
	}
	#endregion

	#region Authorization methods
	/**
	 * Authenticates against the given credentials, generates and returns a JSON Web Token string upon success.
	 * Client Id and Secret values should be passed through the HTTP headers as API_CLIENT_ΙD and API_SECRET respectively.
	 *
	 * @param ApiEventArgs $args
	 *
	 * @return ApiEventStatus Authentication status, which upon success, contains a JSON Web Token string that was generated for the client application.
	 */
	public function authenticate(ApiEventArgs $args): ApiEventStatus {
		$this->onAuthenticating($args);

		// Return error if API requires SSL but connected via plain-text connection
		if ($this->forceSSL && !Request::fromEnv()->isSSL) {
			return $this->toAuthStatus(new ApiEventStatus(false, '', Request::ResponseCodeNotAcceptable));
		}

		// If no authorization is required for the request, return OK
		if ($args->authType === ApiRoute::AuthTypeNone)
			return $this->toAuthStatus(new ApiEventStatus(true, '', Request::ResponseCodeOK));

		// Authorize against token (if available)
		if (isset($args->serverVars['HTTP_X_API_TOKEN'])) {
			$status = $this->validateClientToken($args);

			// If status's data is set, it contains the previous token ready for re-use
			$token = $status->data;

			if ($status->isError()) {
				$this->logger()->error(sprintf('Token validation error: [%s] %s. %s', (string)$status->code, $status->message, $status->debugMessage), ["api.$this->namespace"]);
				return $this->toAuthStatus($status);
			}
		}
		else {
			// Authorize against client credentials in HTTP headers
			$status = $this->authorizeClient($args);

			if ($status->isOK()) {
				/** @var User $client */
				$client = $status->data;

				$this->_client = $client;
				$this->_clientIsSet = true;
			}
			else {
				$this->logger()->error(sprintf('Token authorization error: [%s] %s. %s', $status->code, $status->message, $status->debugMessage), ["api.$this->namespace"]);
				return $this->toAuthStatus(EventStatus::fromStatus($status));
			}
		}

		// If connection is trusted, expose user's sensitive properties
		if (isset($args->serverVars['HTTP_X_API_TRUST_KEY']) && strlen($args->serverVars['HTTP_X_API_TRUST_KEY']) > 0) {
			if ($args->serverVars['HTTP_X_API_TRUST_KEY'] === $this->trustKey) {
				$this->client()->jsonTrusted = true;
			}
		}

		// If client is same as user, return the status
		if ($this->clientIsUser) {
			$this->_user = $this->_client;
			$this->_userIsSet = $this->_clientIsSet;
		}

		if ($args->authType == ApiRoute::AuthTypeClientCredentials || isset($args->serverVars['HTTP_X_API_TOKEN'])) {
			$authStatus = $this->toAuthStatus($status, $token ?? $this->generateToken());
			$this->onAuthenticated($args, $authStatus);

			// If client's last login was more than 2 hours ago, re-login or update their last access
			if ($this->client()->dateAccessed == null || $this->client()->dateAccessed->diff(new \DateTime())->h >= 2) {
				// If a new token was generated, re-login the user or just update the last access date
				if ($this->clientIsUser)
					$this->client()->login();
				else
					$this->client()->updateLastAccess();
			}

			return $authStatus;
		}

		if ($args->authType == ApiRoute::AuthTypePasswordCredentials) {
			// Authorize against user credentials
			if (!($this->_user && $this->_userIsSet))
				$status = $this->authorizeUser($args);

			if ($status->isOK()) {
				/** @var User $user */
				$user = $status->data;

				$this->_user = $user;
				$this->_userIsSet = true;

				// If connection is trusted, expose user's sensitive properties
				if (isset($args->serverVars['HTTP_X_API_TRUST_KEY']) && strlen($args->serverVars['HTTP_X_API_TRUST_KEY']) > 0) {
					if ($args->serverVars['HTTP_X_API_TRUST_KEY'] === $this->trustKey) {
						$this->user()->jsonTrusted = true;
					}
				}
			}
			else {
				$this->logger()->error(sprintf('User authorization error: [%s] %s. %s', (string)$status->code, $status->message, $status->debugMessage), ["api.$this->namespace"]);
				return $this->toAuthStatus(EventStatus::fromStatus($status));
			}
		}

		// Regenerate token to include user info
		if (!isset($token)) {
			$token = $this->generateToken();
		}

		$authStatus = $this->toAuthStatus($status, $token);
		$this->onAuthenticated($args, $authStatus);

		return $authStatus;
	}

	/** Authorizes the client that is specified in request's headers. */
	protected function authorizeClient(ApiEventArgs $args): Status {
		// If a token is sent instead of credentials, validate & renew token
		if (isset($args->serverVars['HTTP_X_API_TOKEN'])) {
			$status = $this->validateClientToken($args);
		}
		else {
			// Authorize client by user credentials or cookie-based authentication token
			if (!isset($args->serverVars['HTTP_X_API_CLIENT_ID']) && $this->clientIsUser)
				return $this->authorizeUser($args);

			$clientId = $args->serverVars['HTTP_X_API_CLIENT_ID'];
			$secret = $args->serverVars['HTTP_X_API_SECRET'];

			$status = User::authenticate($clientId, $secret, null, new AuthenticationOptions($this->namespace, null, $this->permission, false, null, false, $this->stateless));
		}

		// Deliberately sleep for 1 sec to discourage brute-force password attacks
		if ($status->isError())
			sleep(1);

		return $status;
	}

	/** Authorizes the user credentials that are specified in request's body. */
	protected function authorizeUser(ApiEventArgs $args): Status {
		$username = '';
		$password = '';
		$rememberMe = false;

		if (!isset($_POST['username'])) {
			// If no credentials are set in the POST, check if credentials are passed via GET/POST
			if (isset($_REQUEST['HTTP_X_API_CLIENT_ID'])) {
				$username = $_REQUEST['HTTP_X_API_CLIENT_ID'];
				$password = $_REQUEST['HTTP_X_API_SECRET'];
			}
			else {
				// If no credentials are set in the POST, neither credentials are passed via GET/POST, try to authorize by authentication token
				$user = User::get($this->namespace);
				if ($user instanceof User) {
					$username = $user->username;
					$password = $user->encPassword;
					$rememberMe = User::authCookie()->getRemember($this->namespace);
				}
			}
		}
		else {
			$username = $_POST['username'];
			$password = $_POST['password'];
			$rememberMe = isset($_POST['remember']);
		}

		$status = User::authenticate($username, $password, null, $options = new AuthenticationOptions($this->namespace, $args->routeMatch->route->roles, $args->routeMatch->route->permissions, $rememberMe, null, false, $this->stateless));

		if ($status->isOK())
			$this->hooks()->trigger(self::EventOnAuthenticated, new AuthenticationEventArgs($this, $this->_user, null, $options, $status));
		else
			// Deliberately sleep for 1 sec to discourage brute-force password attacks
			sleep(1);

		return $status;
	}

	/** Generates a valid JWT-compatible access token that includes API client and end-user information */
	protected function generateToken(): string {
		$data = new \stdClass();

		if ($this->client() instanceof User) {
			$data->id = $this->client()->id;
			$data->clientId		= $this->client()->id;			// API client's id
			$data->clientName	= $this->client()->username;	// API client's username
			$data->roles		= $this->client()->roles;		// API client's roles, permissions & namespaces
			$data->permissions	= $this->client()->permissions;
			$data->namespaces	= $this->client()->namespaces;
		}

		return Token::encode($this->serverKey, null, $this->tokenExpiresIn, $data, $this->user());
	}

	/** Validates client's token, checking also user's access permissions to the API. */
	protected function validateClientToken(?ApiEventArgs $args = null): ApiEventStatus {
		// If set, validation has already been succeeded...
		if ($this->_client instanceof User)
			return new ApiEventStatus(true, '', Request::ResponseCodeOK);

		// Read token from the HTTP headers
		$token = $args->serverVars['HTTP_X_API_TOKEN'];
		/** @var AppNamespace $namespace */
		$namespace = CMS::namespaces()->get($this->namespace);

		// Negative by default
		$status = new ApiEventStatus(false);
		$status->isPositive = false;
		$status->code = Request::ResponseCodeUnauthorized;

		// Return error if API requires SSL but connected via plain-text connection
		if ($this->forceSSL && !Request::fromEnv()->isSSL) {
			$status->code = Request::ResponseCodeNotAcceptable;
			return $status;
		}

		if (strlen($token) == 0)
			return $status;

		try {
			$token = Token::decode($tokenStr = $token, $this->serverKey);
			if (!$this->_clientIsSet) {
				$this->_clientIsSet = true;

				$clientId = $token->data->clientId;
				if ($clientId === null && $this->clientIsUser)
					$clientId = $token->data->userId;

				if (isset($token->data->id)) {
					// Extract user from token data
					$client = User::loadNew($this->namespace)->applyJsonCfg($token->data);
					$client->fetchLastAccess();
				}
				else {
					// Instantiate a new user
					$client = User::load($clientId, null, $this->namespace);
				}
				if ($client === null)
					return $status;

				// Ensure client has access to API's namespace
				if (!$client->namespaces()->contains($namespace))
					return $status;

				// Ensure user has API's permission set
				if (!$client->hasPermission($this->permission))
					return $status;

				// Store client's instance for later use
				$this->_client = $client;
				$this->_clientIsSet = true;

				#region Retrieve user information from token
				if (isset($token->data->userId)) {
					$user = $this->clientIsUser ? $client : $token->user();

					if ($user instanceof User) {
						// Ensure client has access to API's namespace
						if ($client->namespaces()->contains($namespace)) {
							// Store user's instance for later use
							$this->_user = $user;
							$this->_userIsSet = true;
						}
					}
				}
				#endregion

				// Set status properties
				$status->isPositive = true;
				$status->code = Request::ResponseCodeOK;
				try {
					// If token's lifetime is more than a day and there are +24h remaining till it expires,
					// then it's safe to re-use the same token
					if ($token->expiresIn > 86400 && (\DateTime::createFromFormat('U', $token->issuedAt + $token->expiresIn - 86400)) > new \DateTime()) {
						$status->data = $tokenStr;
					}
				}
				catch (\Exception $e) {

				}
			}
		}
		catch (\Exception $e) {
			if ($e instanceof ExpiredException)
				$status->message = 'Token has expired';
			else {
				$status->message = 'Token is invalid';
				$status->debugMessage = $e->getMessage();
			}
		}

		return $status;
	}

	protected function toAuthStatus(EventStatus $status, $token = null): ApiEventStatus {
		$ret = new ApiEventStatus($status->isPositive, $status->message, $status->code, $status->debugMessage, $status->isHandled);
		if ($ret->isOK()) {
			$ret->code = Request::ResponseCodeOK;
			$ret->data = [
				'isPositive'	=> true,
				'code'			=> $ret->code,
				'message'		=> $ret->message,
				'data'			=> [
					'token'			=> $token,
					'expiresIn'		=> $this->tokenExpiresIn
				]
			];
		}
		else {
			$ret->code = Request::ResponseCodeForbidden;
			$ret->data = [
				'isPositive'	=> false,
				'code'			=> $ret->code,
				'message'		=> $ret->message
			];
		}

		return $ret;
	}
	#endregion

	#region Routing methods
	/** @inheritdoc */
	public function route(RouteEventArgs $args = null): RouteMatch|ApiEventStatus {
		if ($args === null)
			$args = $this->_args;

		#region Set language if defined (and if M17N module is available)
		if (isset($args->serverVars['HTTP_X_API_M17N_LANG']) && CMS::modules()->isAvailable('aneya/m17n'))
			CMS::translator()->setCurrentLanguage($args->serverVars['HTTP_X_API_M17N_LANG']);
		#endregion

		#region Test if a route matches the request
		try {
			$match = parent::route($args);
		}
		catch (\Exception $e) {
			CMS::app()->log($e, Logger::ERROR);
			return new ApiEventStatus(false, '', Request::ResponseCodeInternalServerError);
		}

		if (!($match instanceof RouteMatch))
			if (!($match instanceof ApiEventStatus)) {
				$status = ApiEventStatus::fromStatus($match);
				$status->isHandled = $match->isHandled;
				return $status;
			}
			else
				return $match;
		#endregion

		// Convert args to ApiEventArgs
		if (!($args instanceof ApiEventArgs))
			$args = new ApiEventArgs($args);

		#region Authorize request
		$this->authStatus = $this->authenticate($args);
		if ($this->authStatus->isError())
			return $this->authStatus;
		#endregion

		$match->args = $args;
		$args->version = $match->uriRegexMatches['version'] ?? 0.0;

		#region Process route
		if ($match->route instanceof ApiRoute) {
			$entity = $this->setup($args);

			// Setup forms before processing
			if (isset($entity->container) && $entity->container instanceof Form)
				$entity->container->setup();

			$status = $this->process($args, $entity);
			if ($status instanceof ApiEventStatus)
				return $status;
		}
		#endregion

		return $match;
	}

	/** Processes the given API request and returns the resulting status. */
	abstract public function process(ApiEventArgs $args, ApiEntity $entity = null): ?ApiEventStatus;

	/** Initializes, prepares and returns the ApiEntity that corresponds to the given arguments, ready for processing. */
	abstract public function setup(ApiEventArgs $args): ?ApiEntity;
	#endregion

	#region Common route processing methods
	/** User sign-in route process method */
	protected function userSignIn(ApiEventArgs $args): ?ApiEventStatus {
		// Sign-in already handled by the API controller
		return $this->authStatus;
	}

	/** User sign-out route process method */
	protected function userSignOut(ApiEventArgs $args): ApiEventStatus {
		$status = new ApiEventStatus(true);

		$user = $this->user();
		if ($user == null)
			$user = User::get($this->namespace);

		if ($user instanceof User)
			$user->logout($this->namespace);

		return $status;
	}

	/** User info route process method */
	protected function userInfo(ApiEventArgs $args): ApiEventStatus {
		$status = new ApiEventStatus();
		/** @var User $class */
		$class = User::getClassByNamespace($this->namespace);
		$user = $class::load($this->user()->id);

		$status->data = array_merge(
			$user->jsonSerialize(),
			[
				'status' => [ 'isPositive' => true ]
			]
		);

		return $status;
	}

	/** Language switching route process method */
	protected function switchLanguage(ApiEventArgs $args): ApiEventStatus {
		$status = new ApiEventStatus();

		$code = $args->routeMatch->uriRegexMatches['code'] ?? $_REQUEST['code'] ?? $_REQUEST['__m17n_language'];
		$ret = CMS::translator()->setCurrentLanguage($code);
		$status->data = new EventStatus(true, strtoupper($code));
		$status->data->isPositive = $ret !== false;

		return $status;
	}
	#endregion

	#region CRUD methods
	/** Performs default CRUD operation on the given data object, based on the request method specified in the arguments. */
	public function crud(ApiEntity $entity, ApiEventArgs $args): CrudEventStatus {
		$crudArgs = new CrudEventArgs($args);
		$this->trigger(self::EventOnExecutingCrud, $crudArgs);

		switch ($args->method) {
			case Request::MethodGet:
				if (!(($obj = $entity->table()) instanceof DataTable)) {
					$status = new CrudEventStatus(false, '', Request::ResponseCodeInternalServerError);
					CMS::app()->log(new ApplicationError('CRUD method GET on API request "' . $args->serverVars['REQUEST_URI'] . '" caused internal server error. Details: Entity table not found.', -1, null, ApplicationError::SeverityError));
					$status->entity = $entity;
					break;
				}

				#region Default retrieval mechanism
				if (!$obj->isRetrieved) {
					if (isset($_GET['start']))
						$start = (int)$_GET['start'];
					elseif (isset($args->routeMatch->uriRegexMatches['start']))
						$start = (int)$args->routeMatch->uriRegexMatches['start'];
					else
						$start = 0;

					if (isset($_GET['limit']))
						$limit = (int)$_GET['limit'];
					elseif (isset($args->routeMatch->uriRegexMatches['limit']))
						$limit = (int)$args->routeMatch->uriRegexMatches['limit'];
					else
						$limit = $this->defaultReturnLimit;

					if (isset($_GET['sort']))
						$sort = $obj->columns->get($_GET['sort']);
					elseif (isset($args->routeMatch->uriRegexMatches['sort']))
						$sort = $obj->columns->get($args->routeMatch->uriRegexMatches['sort']);
					else
						$sort = null;

					if (isset($_GET['desc']))
						$dir = DataSorting::Descending;
					else
						$dir = DataSorting::Ascending;

					if ($sort instanceof DataColumn)
						$sort = new DataSorting($sort, $dir);

					#region Apply GET-based filters
					$filters = new DataFilterCollection();
					foreach ($_GET as $key => $value) {
						if (in_array($key, ['start', 'limit', 'sort', 'asc', 'desc']))
							continue;

						$col = $obj->columns->get($key);
						if ($col === null)
							continue;

						$filters->add(new DataFilter($col, DataFilter::Equals, urldecode($value)));
					}
					#endregion

					$obj->retrieve($filters, $sort, $start, $limit);
				}
				#endregion

				$timezone = new \DateTimeZone($this->timezone);
				$data = [];
				// If dataset is mapped with an ORM-compatible class, output objects
				if (strlen($obj->getMappedClass()) > 0 && $obj->autoGenerateObjects) {
					$data = $obj->objects();
				}
				else {	// Otherwise, output records
					foreach ($obj->rows->all() as $row) {
						$values = $row->bulkGetValues();

						#region Convert date/times to API default timezone
						foreach ($values as $key => $value) {
							if ($value instanceof \DateTime) {
								$date = clone $value;
								$date->setTimezone($timezone);
								$values[$key] = $date;
							}
						}
						#endregion
						$data[] = $values;
					}
				}

				$status = new CrudEventStatus(true, '', Request::ResponseCodeOK);
				$status->entity = $entity;
				$status->data = $data;

				break;


			case Request::MethodPost:
				$row = $entity->row();
				if ($row === null) {
					$obj = $entity->table();
					if ($obj === null) {
						$status = new CrudEventStatus(false, '', Request::ResponseCodeInternalServerError);
						CMS::app()->log(new ApplicationError('CRUD method POST on API request "' . $args->serverVars['REQUEST_URI'] . '" caused internal server error. Details: Entity table not found.', -1, null, ApplicationError::SeverityError));
						$status->entity = $entity;
						break;
					}

					$row = $obj->newRow();
				}
				else {
					// Ensure row's state is 'Add'
					$row->setState(DataRow::StateAdded);
				}

				$row->bulkSetValues($_POST);
				$st = $row->save();
				if ($st->isOK())
					$status = new CrudEventStatus(true, '', Request::ResponseCodeCreated);
				else
					$status = new CrudEventStatus(false, $st->message, Request::ResponseCodeBadRequest);

				$status->entity = $entity;
				$status->data = new EventStatus($st->isPositive, $st->message, $st->code);
				$status->data->data = ($row->parent->getMappedClass()) > 0 && $row->parent->autoGenerateObjects
					? $row->object()
					: $row;

				break;


			case Request::MethodPut:
				$row = $entity->row();
				if ($row === null) {
					$obj = $entity->table();
					if ($obj === null) {
						$status = new CrudEventStatus(false, 'Entity table not found', Request::ResponseCodeInternalServerError);
						CMS::app()->log(new ApplicationError('CRUD method PUT on API request "' . $args->serverVars['REQUEST_URI'] . '" caused internal server error. Details: Entity table not found.', -1, null, ApplicationError::SeverityError));
						$status->entity = $entity;
						break;
					}

					$row = static::rowByMethod($obj, $args);
					if ($row === null) {
						$status = new CrudEventStatus(false, 'Row not found', Request::ResponseCodeInternalServerError);
						CMS::app()->log(new ApplicationError('CRUD method PUT on API request "' . $args->serverVars['REQUEST_URI'] . '" caused internal server error. Details: Record not found.', -2, null, ApplicationError::SeverityError));
						$status->entity = $entity;
						break;
					}
				}

				if (isset($args->serverVars['CONTENT_TYPE']) && strtolower($args->serverVars['CONTENT_TYPE'] === 'application/json')) {
					$json = file_get_contents('php://input');
					$data = json_decode($json);
				}
				else
					$data = $_REQUEST;

				$row->bulkSetValues($data);
				$st = $row->save();
				if ($st->isOK())
					$status = new CrudEventStatus(true, '', Request::ResponseCodeOK);
				else
					$status = new CrudEventStatus(false, $st->message, Request::ResponseCodeBadRequest);

				$status->entity = $entity;
				$status->data = new EventStatus($st->isPositive, $st->message, $st->code);
				$status->data->data = ($row->parent->getMappedClass()) > 0 && $row->parent->autoGenerateObjects
					? $row->object()
					: $row;

				break;


			case Request::MethodDelete:
				$row = $entity->row();
				if ($row === null) {
					$tbl = $entity->table();
					if ($tbl === null) {
						$status = new CrudEventStatus(false, '', Request::ResponseCodeInternalServerError);
						CMS::app()->log(new ApplicationError('CRUD method DELETE on API request "' . $args->serverVars['REQUEST_URI'] . '" caused internal server error. Details: Entity table not found.', -1, null, ApplicationError::SeverityError));
						$status->entity = $entity;
						break;
					}

					$row = static::rowByMethod($tbl, $args);
					if ($row === null) {
						$status = new CrudEventStatus(false, '', Request::ResponseCodeInternalServerError);
						CMS::app()->log(new ApplicationError('CRUD method DELETE on API request "' . $args->serverVars['REQUEST_URI'] . '" caused internal server error. Details: Row not found.', -2, null, ApplicationError::SeverityError));
						$status->entity = $entity;
						break;
					}
				}

				$row->delete();
				$st = $row->save();

				if ($st->isOK())
					$status = new CrudEventStatus(true, '', Request::ResponseCodeOK);
				else
					$status = new CrudEventStatus(false, $st->message, Request::ResponseCodeBadRequest);

				$status->entity = $entity;
				$status->data = new EventStatus($st->isPositive, $st->message, $st->code);

				break;
		}

		if (!isset($status))
			$status = new CrudEventStatus(false, '', Request::ResponseCodeNotFound);

		if ($status->isOK()) {
			// Allow listeners to alter the returned status
			$crudArgs = new CrudEventArgs($args);
			$crudArgs->status = $status;
			$this->trigger(self::EventOnExecutedCrud, $crudArgs);
		}

		return $status;
	}
	#endregion

	#region Event methods
	/** Triggered right before executing API authentication */
	protected function onAuthenticating (ApiEventArgs $args) {}

	/** Triggered right after API authentication completes successfully */
	protected function onAuthenticated (ApiEventArgs $args, ApiEventStatus $status) {}
	#endregion

	#region Static methods
	/** Returns the DataRow of the given DataTable that corresponds to passed API arguments. */
	public static function rowByMethod(DataTable $table, ApiEventArgs $args): ?DataRow {
		switch ($args->method) {
			case Request::MethodPost:
				return $table->newRow();

			case Request::MethodPut:
			case Request::MethodDelete:
				#region Build filters
				if (isset($args->serverVars['CONTENT_TYPE']) && strtolower($args->serverVars['CONTENT_TYPE'] === 'application/json')) {
					$json = file_get_contents('php://input');
					$data = json_decode($json);
				}
				else
					$data = $_REQUEST;

				$filters = new DataFilterCollection();
				$max = count($table->columns->filter(function (DataColumn $c) { return $c->isKey; })->all());
				foreach ($data as $key => $value) {
					$col = $table->columns->get($key);

					if (!($col instanceof DataColumn)) {
						$ormClass = $table->getMappedClass();
						if (strlen($ormClass) === 0 || !is_subclass_of($ormClass, '\aneya\Core\Data\ORM\IDataObject'))
							continue;

						// Try to get column via ORM's column-to-property mapping information
						$prop = $ormClass::ormSt()->getProperty($key);
						if ($prop === null)
							continue;

						$col = $prop->column;
					}

					if (!$col->isActive || !$col->isKey)
						continue;

					$filters->add(new DataFilter($col, DataFilter::Equals, $value));

					// If all keys are found, there's no need to iterate through the rest of the properties
					if ($filters->count() == $max)
						break;
				}

				if ($filters->count() != $max)
					return null;
				#endregion

				#region Fetch corresponding DataRow
				if (!$table->isRetrieved)
					$rows = $table->retrieve($filters)->rows;
				else
					$rows = $table->rows->match($filters);

				if ($rows->count() !== 1)
					return null;
				#endregion

				return $rows->first();
		}

		return null;
	}
	#endregion
}
