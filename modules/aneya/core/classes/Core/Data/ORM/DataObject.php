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
use aneya\Core\ActionEventArgs;
use aneya\Core\CMS;
use aneya\Core\CoreObject;
use aneya\Core\Data\DataColumn;
use aneya\Core\Data\DataFilter;
use aneya\Core\Data\DataFilterCollection;
use aneya\Core\Data\DataSet;
use aneya\Core\EventStatus;
use aneya\Core\IStorable;
use aneya\Core\Storable;

class DataObject extends CoreObject implements IDataObject, IStorable {
	use Storable;

	#region Events
	/**    Triggered when an Action is being passed for execution. Passes a ActionEventArgs argument on listeners */
	const EventOnAction = 'OnAction';

	const EventOnSaving	= 'OnSaving';

	const EventOnSaved	= 'OnSaved';

	const EventOnDeleting	= 'OnDeleting';

	const EventOnDeleted	= 'OnDeleted';
	#endregion

	#region Properties
	#endregion

	#region Methods
	/**
	 * Performs an action
	 *
	 * @param Action|string $action
	 *
	 * @return EventStatus
	 */
	public function action($action): EventStatus {
		if (!($action instanceof Action))
			$action = new Action ($action);

		$args = new ActionEventArgs ($this, $action);
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

	#region Event methods
	protected function onAction(ActionEventArgs $args): ?EventStatus { return null; }
	#endregion

	#region Static methods
	public static function load() {
		$uid = func_get_args();

		/** @var DataSet $ds */
		$ds = static::ormSt()->dataSet();

		if (!is_array($uid)) {
			$uid = [$uid];
		}
		$pCols = $ds->columns->filter(function (DataColumn $c) { return $c->isActive && $c->isMaster && $c->isKey; })->all();
		$filters = new DataFilterCollection();

		// Number of arguments should be equal to number of primary keys
		if (count($uid) != ($max = count($pCols))) {
			CMS::logger()->debug(sprintf('%s::load() was called with %d arguments instead of %d according to the DataObject\'s primary keys', static::class, count($uid), count($pCols)));
			return null;
		}

		for ($num = 0; $num < $max; $num++) {
			$filters->add(new DataFilter($pCols[$num], DataFilter::Equals, $uid[$num]));
		}

		$ds->clear()->retrieve($filters);
		if ($ds->rows->count() != 1) {
			return null;
		}

		return $ds->rows->first()->object();
	}

	public static function loadRange(array $uids): ?array {
		/** @var DataSet $ds */
		$ds = static::ormSt()->dataSet();

		$pCols = $ds->columns->filter(function (DataColumn $c) { return $c->isActive && $c->isMaster && $c->isKey; })->all();
		$filters = new DataFilterCollection();

		// If there's only one primary key, use List filter
		if (count($pCols) === 1) {
			$filters->add(new DataFilter($pCols[0], DataFilter::InList, $uids));
		}
		// If there are multiple primary keys, we have to construct filters for each record separately
		else {
			$filters->operand = DataFilterCollection::OperandOr;

			foreach ($uids as $uid) {
				// Number of array items should be equal to the number of primary keys
				if (count($uid) != ($max = count($pCols)))
					return null;

				// Build key filters for each record
				$keyFilters = new DataFilterCollection();

				for ($num = 0; $num < $max; $num++) {
					$keyFilters->add(new DataFilter($pCols[$num], DataFilter::Equals, $uid[$num]));
				}

				$filters->add($keyFilters);
			}
		}

		return $ds->clear()->retrieve($filters)->objects();
	}
	#endregion
}
