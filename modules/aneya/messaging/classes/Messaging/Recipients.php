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

namespace aneya\Messaging;

use aneya\Core\CMS;
use aneya\Core\Environment\Net;
use aneya\Security\User;

class Recipients {
	#region Properties
	/** @var string|string[]|int|int[]|User|User[] */
	public $to;
	/** @var string|string[]|int|int[]|User|User[] */
	public $cc;
	/** @var string|string[]|int|int[]|User|User[] */
	public $bcc;

	/** @var Recipients */
	protected $_inEmails;
	/** @var Recipients */
	protected $_inUserIds;
	#endregion

	#region Constructor
	/**
	 * @param string|string[]|int|int[]|User|User[] $to
	 * @param string|string[]|int|int[]|User|User[] $cc
	 * @param string|string[]|int|int[]|User|User[] $bcc
	 */
	public function __construct($to = [], $cc = [], $bcc = []) {
		$this->to = $to;
		$this->cc = $cc;
		$this->bcc = $bcc;
	}
	#endregion

	#region Methods
	/**
	 * Returns a copy of the recipients list with all entries replaced by the recipients' user Ids
	 *
	 * @param bool $forceRebuild
	 *
	 * @return Recipients
	 */
	public function toUserIds($forceRebuild = false) {
		if ($this->_inUserIds === null || $forceRebuild) {
			$this->_inUserIds = new Recipients ();

			#region Prepare to, cc & bcc lists
			#region Prepare to: list
			if (is_numeric($this->to)) {
				$this->_inUserIds->to[] = (int)$this->to;
			}
			elseif ($this->to instanceof User) {
				$this->_inUserIds->to[] = $this->to->id;
			}
			elseif (is_array($this->to)) {
				foreach ($this->to as $rcp) {
					if (is_numeric($rcp)) {
						$this->_inUserIds->to[] = (int)$rcp;
					}
					elseif ($rcp instanceof User && is_numeric($rcp->id)) {
						$this->_inUserIds->to[] = (int)$rcp->id;
					}
				}
			}
			#endregion

			#region Prepare cc: list
			if (is_numeric($this->cc)) {
				$this->_inUserIds->cc[] = (int)$this->cc;
			}
			elseif ($this->cc instanceof User) {
				$this->_inUserIds->cc[] = $this->cc;
			}
			elseif (is_array($this->cc)) {
				foreach ($this->cc as $rcp) {
					if (is_numeric($rcp)) {
						$this->_inUserIds->cc[] = (int)$rcp;
					}
					elseif ($rcp instanceof User && is_numeric($rcp->id)) {
						$this->_inUserIds->cc[] = (int)$rcp->id;
					}
				}
			}
			#endregion

			#region Prepare bcc: list
			if (is_numeric($this->bcc)) {
				$this->_inUserIds->bcc[] = (int)$this->bcc;
			}
			elseif ($this->bcc instanceof User) {
				$this->_inUserIds->bcc[] = $this->bcc;
			}
			elseif (is_array($this->bcc)) {
				foreach ($this->bcc as $rcp) {
					if (is_numeric($rcp)) {
						$this->_inUserIds->bcc[] = (int)$rcp;
					}
					elseif ($rcp instanceof User && is_numeric($rcp->id)) {
						$this->_inUserIds->bcc[] = (int)$rcp->id;
					}
				}
			}
			#endregion
			#endregion
		}

		return $this->_inUserIds;
	}

	/**
	 * Returns a copy of the recipients list with all entries replaced by the recipients' e-mails
	 *
	 * @param bool $forceRebuild
	 *
	 * @return Recipients
	 */
	public function toEmails($forceRebuild = false) {
		if ($this->_inEmails === null || $forceRebuild) {
			$this->_inEmails = new Recipients ();

			$to = $cc = $bcc = [];

			#region Prepare to, cc & bcc lists
			#region Prepare to: list
			if (is_numeric($this->to)) {
				$to[] = (int)$this->to;
			}
			elseif ($this->to instanceof User) {
				if (Net::validateEmail($this->to->email)) {
					$this->_inEmails->to[] = $this->to->email;
				}
			}
			elseif (is_string($this->to) && Net::validateEmail($this->to)) {
				$this->_inEmails->to[] = $this->to;
			}
			elseif (is_array($this->to)) {
				foreach ($this->to as $_to) {
					if (is_numeric($_to)) {
						$to[] = (int)$_to;
					}
					elseif ($_to instanceof User) {
						if (Net::validateEmail($_to->email)) {
							$this->_inEmails->to[] = $_to->email;
						}
					}
					elseif (is_string($_to) && Net::validateEmail($_to)) {
						$this->_inEmails->to[] = $_to;
					}
				}
			}
			#endregion

			#region Prepare cc: list
			if (is_numeric($this->cc)) {
				$cc[] = (int)$this->cc;
			}
			elseif ($this->cc instanceof User) {
				if (Net::validateEmail($this->cc->email)) {
					$this->_inEmails->cc[] = $this->cc->email;
				}
			}
			elseif (is_string($this->cc) && Net::validateEmail($this->cc)) {
				$this->_inEmails->cc[] = $this->cc;
			}
			elseif (is_array($this->cc)) {
				foreach ($this->cc as $_cc) {
					if (is_numeric($_cc)) {
						$cc[] = (int)$_cc;
					}
					elseif ($_cc instanceof User) {
						if (Net::validateEmail($_cc->email)) {
							$this->_inEmails->cc[] = $_cc->email;
						}
					}
					elseif (is_string($_cc) && Net::validateEmail($_cc)) {
						$this->_inEmails->cc[] = $_cc;
					}
				}
			}
			#endregion

			#region Prepare bcc: list
			if (is_numeric($this->bcc)) {
				$bcc[] = (int)$this->bcc;
			}
			elseif ($this->bcc instanceof User) {
				if (Net::validateEmail($this->bcc->email)) {
					$this->_inEmails->bcc[] = $this->bcc->email;
				}
			}
			elseif (is_string($this->bcc) && Net::validateEmail($this->bcc)) {
				$this->_inEmails->bcc[] = $this->bcc;
			}
			elseif (is_array($this->bcc)) {
				foreach ($this->bcc as $_bcc) {
					if (is_numeric($_bcc)) {
						$bcc[] = (int)$_bcc;
					}
					elseif ($_bcc instanceof User) {
						if (Net::validateEmail($_bcc->email)) {
							$this->_inEmails->bcc[] = $_bcc->email;
						}
					}
					elseif (is_string($_bcc) && Net::validateEmail($_bcc)) {
						$this->_inEmails->bcc[] = $_bcc;
					}
				}
			}
			#endregion
			#endregion

			// Fetch e-mails from database
			$ids = array_merge($to, $cc, $bcc);

			if (count($ids) > 0) {
				$ids = implode(', ', $ids);
				$sql = "SELECT user_id, email FROM cms_users WHERE user_id IN ($ids) AND length(email)>0";
				$rows = CMS::db()->fetchAll($sql);
				$ids = [];
				foreach ($rows as $row) {
					$ids[(int)$row['user_id']] = $row['email'];
				}
			}

			#region Add fetched e-mails to to, cc & bcc lists
			foreach ($to as $rcp) {
				if (isset ($ids[$rcp]) && Net::validateEmail($ids[$rcp])) {
					$this->_inEmails->to[] = $ids[$rcp];
				}
			}
			$this->_inEmails->to = array_unique($this->_inEmails->to);

			foreach ($cc as $rcp) {
				if (isset ($ids[$rcp]) && Net::validateEmail($ids[$rcp])) {
					$this->_inEmails->cc[] = $ids[$rcp];
				}
			}
			$this->_inEmails->cc = array_unique($this->_inEmails->cc);

			foreach ($bcc as $rcp) {
				if (isset ($ids[$rcp]) && Net::validateEmail($ids[$rcp])) {
					$this->_inEmails->bcc[] = $ids[$rcp];
				}
			}
			$this->_inEmails->bcc = array_unique($this->_inEmails->bcc);
			#endregion
		}

		return $this->_inEmails;
	}
	#endregion
}
