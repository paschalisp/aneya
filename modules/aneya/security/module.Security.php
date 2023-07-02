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

/**
 * Security module
 *
 * @package    aneya
 * @subpackage Security
 * @author     Paschalis Pagonidis <p.pagonides@gmail.com>
 * @copyright  Copyright (c) 2007-2022 Paschalis Pagonidis <p.pagonides@gmail.com>
 * @license    https://www.gnu.org/licenses/agpl-3.0.txt
 */

use aneya\Core\CMS;
use aneya\Core\EventArgs;
use aneya\Core\EventStatus;
use aneya\Core\Module;
use aneya\Core\Status;
use aneya\Security\Permission;
use aneya\Security\PermissionCollection;
use aneya\Security\Role;
use aneya\Security\RoleCollection;

class SecurityModule extends Module {
	#region Properties
	protected string $_vendor  = 'aneya';
	protected string $_tag     = 'security';
	protected string $_version = '1.0.0.0';

	protected RoleCollection $_envRoles;
	protected PermissionCollection $_envPermissions;
	#endregion

	#region Installation methods
	public function install(): Status { return new Status(); }

	public function uninstall(): Status { return new Status(); }

	public function upgrade(): Status { return new Status(); }
	#endregion

	#region Event methods
	protected function onLoad(EventArgs $args = null): ?EventStatus {
		$status = parent::onLoad($args);

		$this->_envRoles = new RoleCollection();
		$this->_envPermissions = new PermissionCollection();

		CMS::onSt(CMS::EventOnModulesLoaded, function() {
			#region Cache all modules' security role definitions
			$mods = CMS::modules()->allLoaded();
			foreach ($mods as $tag) {
				$json = CMS::modules()->info($tag);
				if (!isset($json->security))
					continue;

				if (isset($json->security->roles)) {
					foreach ($json->security->roles as $code => $cfg) {
						$role = $this->_envRoles->getByCode($code);
						if (!isset($role))
							$this->_envRoles->add($role = new Role($code));

						if (!(isset($cfg->permissions) && is_array($cfg->permissions)))
							continue;

						foreach ($cfg->permissions as $permCode) {
							$permission = $this->_envPermissions->getByCode($permCode);
							if (!isset($permission))
								$this->_envPermissions->add($permission = new Permission($permCode));

							$role->permissions->add($permission);
						}
					}
				}

				if (isset($json->security->permissions)) {
					foreach ($json->security->permissions as $permCode) {
						$permission = $this->_envPermissions->getByCode($permCode);
						if (!isset($permission))
							$this->_envPermissions->add(new Permission($permCode));
					}
				}
			}
			#endregion
		});

		return $status;
	}
	#endregion

	#region Methods
	/** Returns a Collection with all user roles defined in the framework */
	public function roles(): RoleCollection {
		return $this->_envRoles;
	}

	/**
	 * Returns a Collection with all user permissions defined in the framework
	 *
	 * @return PermissionCollection
	 */
	public function permissions(): PermissionCollection {
		return $this->_envPermissions;
	}
	#endregion
}
