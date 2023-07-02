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
 * Portions created by Paschalis Ch. Pagonidis are Copyright (C) 2007-2022
 * Paschalis Ch. Pagonidis. All Rights Reserved.
 */

namespace aneya\Core\Data;

abstract class ODBMS extends Database {
	/**
	 * Converts an object into a base \stdClass in order to be used in ODBMS
	 * @param object $obj
	 * @return array|object
	 */
	public abstract function toNativeObj ($obj);

	/**
	 * Converts a native object back to its original class object.
	 * In case the source class implements IDataSerializableObject, the object instantiates to this class; otherwise, as no class information is stored internally, \stdClass is used to instantiate the object for fallback purposes.
	 * @param object|array $obj
	 * @return mixed
	 */
	public abstract function fromNativeObj ($obj);
}
