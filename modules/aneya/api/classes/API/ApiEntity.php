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

namespace aneya\API;


use aneya\Core\Data\DataRow;
use aneya\Core\Data\DataSet;
use aneya\Core\Data\DataTable;
use aneya\Core\Data\ORM\DataObject;
use aneya\Forms\Form;

class ApiEntity {
	#region Properties
	/** @var DataSet|DataTable|Form */
	public $container;
	/** @var DataRow|DataObject */
	public $object;
	#endregion

	#region Constructor
	/**
	 * ApiEntity constructor.
	 *
	 * @param DataTable|Form $container
	 * @param DataRow|DataObject|ApiEventArgs $object
	 */
	public function __construct($container, $object) {
		if ($container instanceof DataTable || $container instanceof Form)
			$this->container = $container;
		else
			throw new \InvalidArgumentException('Container argument is not an instance of DataTable or Form');

		if ($object instanceof DataRow || $object instanceof DataObject)
			$this->object = $object;
		elseif ($object instanceof ApiEventArgs)
			$this->object = ApiController::rowByMethod($this->table(), $object);
		else
			throw new \InvalidArgumentException('Object argument is not an instance of DataRow or ORM\DataObject');
	}
	#endregion

	#region Methods
	/**
	 * Returns entity's DataTable
	 * @return DataSet|DataTable
	 */
	public function table() {
		return ($this->container instanceof Form) ? $this->container->dataSet : $this->container;
	}

	/**
	 * Returns entity's DataRow
	 * @return DataRow
	 */
	public function row() {
		return ($this->object instanceof DataObject) ? $this->object->orm()->row() : $this->object;
	}
	#endregion

	#region Static methods
	#endregion
}
