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

namespace aneya\CLI;

use aneya\Core\CMS;
use aneya\Core\Encrypt;
use aneya\Core\EventArgs;
use aneya\Core\Module;
use aneya\Core\Status;
use aneya\FileSystem\File;
use aneya\FileSystem\FileSystemStatus;
use aneya\Security\User;
use League\CLImate\CLImate;
use Monolog\Logger;

final class CLI {
	#region Properties
	/** @var CLImate */
	public CLImate $cli;
	#endregion

	#region Constructor
	public function __construct() {
		$this->cli = new CLImate();

		$this->_init();
	}
	#endregion

	#region Methods
	public function run() {
		#region Parse arguments
		// Get framework's logging level
		$level = CMS::logLevel();
		$parsed = false;
		try {
			// Set logging level to errors or above
			CMS::logLevel(Logger::ERROR);

			$this->cli->arguments->parse($_SERVER['argv']);
			$parsed = true;
		}
		catch (\Exception $e) { }
		finally {
			// Restore framework's logging level
			CMS::logLevel($level);

			if (!$parsed) {
				$this->cli->usage();
				return;
			}
		}
		#endregion

		$this->cli->bold('aneya CMS & Framework command-line administration tool');

		if ($this->cli->arguments->defined('init')) {
			$this->cli->out("New aneya project initialization is not yet implemented");
		}
		elseif ($this->cli->arguments->defined('build')) {
			$this->build();
		}
		elseif ($this->cli->arguments->defined('clean')) {
			// TODO: Clean environment from build files and settings
		}
		elseif ($this->cli->arguments->defined('clear')) {
			$option = $this->cli->arguments->get('clear');

			switch ($option) {
				case 'cache':
					$this->cli->out("Clearing cache...");
					$status = $this->clearCache();
					break;
				case 'config':
					$this->cli->out("Clearing cached modules configuration...");
					$status = $this->clearConfig();
					break;
				case '':
					$this->cli->out("Clearing cache...");
					$status = $this->clearCache();
					if ($status->isOK()) {
						$this->cli->out("Clearing cached modules configuration...");
						$status = $this->clearConfig();
					}
					break;
				default:
					$this->cli->usage();
			}
		}
		elseif ($this->cli->arguments->defined('dbcheck')) {
			$this->dbCheck();
		}
		elseif ($this->cli->arguments->defined('set')) {
			$option = $this->cli->arguments->get('set');

			switch ($option) {
				case 'password':
					if (!$this->cli->arguments->defined('user')) {
						$this->cli->usage();
						break;
					}
					$user = $this->cli->arguments->get('user');

					if ($this->cli->arguments->defined('password')) {
						$password = $this->cli->arguments->defined('password');
					}
					else {
						$pass1 = $this->cli->password('New password:')->prompt();
						$pass2 = $this->cli->password('(again):')->prompt();
						if ($pass1 == $pass2) {
							$password = $pass1;
						}
						else {
							$status = new Status(false, 'Passwords did not match');
							break;
						}
					}

					$status = $this->setUserPassword($user, $password);
					break;

				case 'status':
					if (!$this->cli->arguments->defined('user')) {
						$this->cli->usage();
						break;
					}
					$user = $this->cli->arguments->get('user');

					if ($this->cli->arguments->defined('status')) {
						$status = $this->setUserStatus($user, $this->cli->arguments->get('status'));
					}
					else {
						$this->cli->usage();
						$status = new Status(false, 'Missing status argument');
					}

					break;

				default:
					$this->cli->usage();
			}
		}
		elseif ($this->cli->arguments->defined('generate-salt')) {
			$txt = Encrypt::generateKey();
			$this->cli->out("Salt: $txt\n");
		}
		elseif ($this->cli->arguments->defined('encrypt')) {
			$txt = $this->cli->arguments->get('encrypt');
			$enc = Encrypt::encrypt($txt);
			$this->cli->out("Plain text: $txt\nEncrypted text: $enc");
		}
		elseif ($this->cli->arguments->defined('decrypt')) {
			$enc = $this->cli->arguments->get('decrypt');
			$txt = Encrypt::decrypt($enc);
			$this->cli->out("Encrypted text: $enc\nPlain text: $txt");
		}
		elseif ($this->cli->arguments->defined('hash')) {
			$txt = $this->cli->arguments->get('hash');
			$enc = Encrypt::hashPassword($txt);
			$this->cli->out("Plain text: $txt\nHashed text: $enc");
		}
		elseif ($this->cli->arguments->defined('help')) {
			$this->cli->usage();
		}
		else {
			$this->cli->usage();
		}

		if (isset($status) && $status instanceof Status) {
			if ($status->isPositive) {
				$this->cli->out($status->message);
			}
			else {
				$this->cli->error($status->message);
				$this->cli->error($status->debugMessage);
			}
		}
	}

	protected function _init() {
		$this->cli->description('aneya CMS & Framework command-line administration tool');

		$this->cli->arguments->add([
									   'init' => [
									   		'longPrefix'	=> 'init',
											'description'	=> 'Initialize a new aneya project inside current directory (not yet implemented)',
											'noValue'		=> true
									   ],
									   'build' => [
											'longPrefix'	=> 'build',
											'description'	=> 'Build project\'s configuration and serialize it to a file to greatly boost start-up performance',
											'noValue'		=> true
									   ],
									   'clean' => [
											'longPrefix'	=> 'clean',
											'description'	=> 'Clean project\'s serialized configuration that was produced by previous builds. Equivalent to "aneya clear config"',
											'noValue'		=> true
									   ],
									   'clear' => [
											'longPrefix'	=> 'clear',
											'description'	=> 'Clear command. Available clear options: [cache|config]',
											'defaultValue'	=> ''
									   ],
									   'dbcheck' => [
											'longPrefix'	=> 'dbcheck',
											'description'	=> 'Checks all available database schemas and returns a list of tables found in each schema',
											'defaultValue'	=> ''
									   ],
									   'set' => [
									   		'longPrefix'	=> 'set',
											'description'	=> 'Set command. Available set options: [password|status]. Password/status should be followed by option "user"',
											'defaultValue'	=> ''
										],
									   'user' => [
											'prefix'		=> 'u',
											'longPrefix'	=> 'user',
											'description'	=> 'Username',
											'defaultValue'	=> ''
										],
										'password' => [
											'prefix'		=> 'p',
											'longPrefix'	=> 'password',
											'description'	=> 'Password. If not specified, password will be requested in the command prompt.',
											'defaultValue'	=> ''
										],
										'generate-salt' => [
											'longPrefix'	=> 'generate-salt',
											'description'	=> 'Generates a random salt',
											'noValue'	=> true
										],
										'encrypt' => [
											'longPrefix'	=> 'encrypt',
											'description'	=> 'Encrypts the given plain text',
											'defaultValue'	=> ''
										],
										'decrypt' => [
											'longPrefix'	=> 'decrypt',
											'description'	=> 'Decrypts the given encrypted text',
											'defaultValue'	=> ''
										],
										'hash' => [
											'longPrefix'	=> 'hash',
											'description'	=> 'Hashes the given plain text',
											'defaultValue'	=> ''
										],
										'status' => [
											'longPrefix'	=> 'status',
											'description'	=> 'User\'s new status (-1: Invalid, 0: Pending, 2: Active, 3: Locked, 9: Disabled)',
											'castTo'		=> 'int'
										],
										'salt' => [
											'longPrefix'	=> 'salt',
											'description'	=> 'The encryption salt to use or set (depending the command)',
											'defaultValue'	=> ''
										],
										'verbose' => [
											'prefix'		=> 'v',
											'longPrefix'	=> 'verbose',
											'description'	=> 'Verbose output',
											'noValue'		=> true
										],
										'help' => [
											'longPrefix'	=> 'help',
											'description'	=> 'Prints a usage statement',
											'noValue'		=> true
										]
								 ]);
	}
	#endregion

	#region Actions
	public function build() {
		$this->cli->bold('Build process started...');

		Module::onSt(Module::EventOnBuilding, function (EventArgs $args) {
			/** @var Module $mod */
			$mod = $args->sender;
			$this->cli->out('Building ' . $mod->tag() . '...');
		});

		foreach (CMS::namespaces()->all() as $ns) {
			$this->cli->bold('Switching environment to ' . $ns->tag . '...');
			CMS::ns($ns->tag);

			// Build all modules
			$status = CMS::modules()->build();

			$this->cli->out("\n");
		}

		$this->cli->bold('Build process completed');
	}

	/**
	 * Clears framework's cache completely
	 */
	public function clearCache() {
		$status = new Status();

		$files = CMS::filesystem()->ls('/cache')->filter(function (File $file) {
			return $file->is(File::File) && stripos($file->filename, 'aneya.') === 0 && $file->extension === 'cache';
		});
		$files->addRange(CMS::filesystem()->ls('/cache/cached')->all(function (File $file) {
			return $file->is(File::File);
		}));

		$progress = $this->cli->progress()->total($files->count());
		foreach ($files->all() as $file) {
			$status = CMS::filesystem()->delete($file->name());
			if ($status->isError())
				return $status;

			$progress->advance();
		}
		#endregion

		if ($status->isOK())
			$status->message = 'Cache was cleared successfully';

		return $status;
	}

	public function clearConfig(): FileSystemStatus {
		$status = new FileSystemStatus();

		if (CMS::filesystem()->exists('/cache/aneya.boot.cache'))
			$status = CMS::filesystem()->delete('/cache/aneya.boot.cache');

		if ($status->isOK())
			$status = CMS::filesystem()->delete('/cache/aneya.modules.cache');

		return $status;
	}

	public function dbCheck() {
		$db = CMS::cfg()->db;

		foreach ($db as $tag => $cfg) {
			$this->cli->bold("Previewing tables for '$tag':");
			$tables = CMS::db($tag)->schema->tables();

			foreach ($tables as $table)
				$this->cli->out("<bold><light_yellow>$table->name</light_yellow></bold>: <light_gray>$table->comment</light_gray>");

			$this->cli->out("\n");
		}

		$this->cli->bold('Database check completed');
	}

	public function setUserPassword($username, $password): Status {
		$pwd = Encrypt::hashPassword($password);
		$sql = sprintf('UPDATE %s.cms_users SET password=:password WHERE username=:username', $schema = CMS::db()->getSchemaName());
		$ret = CMS::db()->execute($sql, [
			':username' => $username,
			':password'	=> $pwd
		]);

		$status = new Status();

		if ($ret) {
			$status->message = 'User password was set successfully';

			// Clear user's cache
			$rows = CMS::db()->fetchAll("SELECT user_id FROM $schema.cms_users WHERE username=:username", [':username' => $username]);
			if ($rows) {
				foreach ($rows as $row) {
					User::expireCacheAll($row['user_id']);
				}
			}
		}
		else {
			$status->isPositive = false;
			$status->message = 'Failed setting user password';
			$status->debugMessage = CMS::db()->lastError;
		}

		return $status;
	}

	public function setUserStatus($username, $status): Status {
		if (!in_array($status, [User::StatusPending, User::StatusActive, User::StatusDisabled, User::StatusInvalid, User::StatusLocked])) {
			return new Status(false, 'Invalid user status');
		}

		$sql = sprintf('UPDATE %s.cms_users SET status=:status WHERE username=:username', $schema = CMS::db()->getSchemaName());
		$ret = CMS::db()->execute($sql, [
			':username' => $username,
			':status'	=> $status
		]);

		$status = new Status();

		if ($ret) {
			$status->message = 'User status was changed successfully';

			// Clear user's cache
			$rows = CMS::db()->fetchAll("SELECT user_id FROM $schema.cms_users WHERE username=:username", [':username' => $username]);
			if ($rows) {
				foreach ($rows as $row) {
					User::expireCacheAll($row['user_id']);
				}
			}
		}
		else {
			$status->isPositive = false;
			$status->message = 'Failed to change user status';
			$status->debugMessage = CMS::db()->lastError;
		}

		return $status;
	}
	#endregion

	#region Static methods
	#endregion
}
