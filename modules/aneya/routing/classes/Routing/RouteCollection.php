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

namespace aneya\Routing;

use aneya\Core\Collection;
use aneya\Core\ISortable;

class RouteCollection extends Collection implements ISortable {
	#region Properties
	/** @var Route[] */
	protected array $_collection;
	#endregion

	#region Constructor
	public function __construct() {
		parent::__construct('\\aneya\\Routing\\Route');
	}
	#endregion

	#region Methods
	/**
	 * @param RouteEventArgs $args
	 *
	 * @return RouteMatch|bool
	 */
	public function match(RouteEventArgs $args): RouteMatch|bool {
		$this->sort();

		foreach ($this->_collection as $route) {
			$ret = $route->match($args);
			if ($ret instanceof RouteMatch) {
				$ret->route = $route;
				break;
			}
		}

		return (isset($ret) && $ret instanceof RouteMatch) ? $ret : false;
	}

	/**
	 * @inheritdoc
	 * @return Route[]
	 */
	public function all(callable $f = null): array {
		return parent::all($f);
	}

	/**
	 * Returns true if Route with the given tag exists in the collection
	 */
	public function existsByTag(string $tag): bool {
		foreach ($this->_collection as $route) {
			if ($route->tag == $tag)
				return true;
		}

		return false;
	}

	/**
	 * Returns the Route with the given tag or null if there is no such Route in the collection
	 */
	public function getByTag(string $tag): ?Route {
		foreach ($this->_collection as $route) {
			if ($route->tag == $tag)
				return $route;
		}

		return null;
	}

	/**
	 * @inheritdoc
	 * @param Route $item
	 */
	public function add($item): static {
		// Auto-set priority
		if ($item->priority == 0) {
			$max = 0;
			foreach ($this->_collection as $route) {
				if ($route->priority > $max && $route->priority < PHP_INT_MAX) {
					$max = $route->priority;
				}
			}
			$item->priority = ++$max;
		}

		return parent::add($item);
	}
	#endregion

	#region Interface implementations
	/**
	 * Sorts the collection and returns back the instance sorted
	 *
	 * @return RouteCollection
	 */
	public function sort(): RouteCollection {
		usort($this->_collection, function (Route $a, Route $b) {
			if ($a->priority == $b->priority)
				return 0;

			return ($a->priority < $b->priority) ? -1 : 1;
		});
		$this->rewind();

		return $this;
	}
	#endregion
}
