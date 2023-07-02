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

namespace aneya\Core\Data\ORM;


use aneya\Core\Action;
use aneya\Core\Data\DataRowValidationEventStatus;
use aneya\Core\EventStatus;

interface IDataObject {
	#region Methods
	/**
	 * Returns the ORM information that is associated with this object
	 * @return DataObjectMapping
	 */
	public function orm(): DataObjectMapping;

	/**
	 * Performs storage to the database
	 * @return EventStatus
	 */
	public function save(): EventStatus;

	/**
	 * Performs deletion from the database
	 * @return EventStatus
	 */
	public function delete(): EventStatus;

	/**
	 * Performs validation of object's properties
	 * @return DataRowValidationEventStatus
	 */
	public function validate(): DataRowValidationEventStatus;

	/**
	 * Performs an action
	 * @param Action $action
	 * @return EventStatus
	 */
	public function action(Action $action): EventStatus;
	#endregion

	#region Static methods
	/**
	 * Returns the ORM information that is available for this class
	 * @return DataObjectMapping
	 */
	static function ormSt(): DataObjectMapping;

	/**
	 * Performs loading from database (or cache) given the primary key values as arguments (in the respective order) or the object's uid (if any)
	 * @param mixed
	 * @return mixed
	 */
	public static function load();
	#endregion
}
