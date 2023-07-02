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
 * Core module
 *
 * @package    aneya
 * @subpackage Core
 * @author     Paschalis Pagonidis <p.pagonides@gmail.com>
 * @copyright  Copyright (c) 2007-2022 Paschalis Pagonidis <p.pagonides@gmail.com>
 * @license    https://www.gnu.org/licenses/agpl-3.0.txt
 */

use aneya\Core\CMS;
use aneya\Core\Environment\Process;
use aneya\Core\EventArgs;
use aneya\Core\EventStatus;
use aneya\Core\Module;
use aneya\Core\Status;

class CoreModule extends Module {
	#region Properties
	protected string $_vendor  = 'aneya';
	protected string $_tag     = 'core';
	protected string $_version = '1.8.0.0';
	#endregion

	#region Methods
	public function install(): Status { return new Status(); }

	public function uninstall(): Status { return new Status(); }

	public function upgrade(): Status { return new Status(); }
	#endregion

	#region Event methods
	protected function onBuild(EventArgs $args = null): ?EventStatus {
		$assets = new stdClass();
		$assets->node		= [];
		$assets->nodeDev	= [];
		$assets->composer	= [];
		$assets->composerDev= [];

		#region Build a structure with dependencies used in current namespace
		// Get current namespace's configuration
		$ns = CMS::ns();

		$parsed = [];

		// Read node & composer configuration
		$json = CMS::filesystem()->read('/package.json');
		$node = json_decode($json);
		$json = CMS::filesystem()->read('/composer.json');
		$composer = json_decode($json);


		// Parse each module for assets
		foreach ($ns->modules as $tag => $version) {
			if (isset($parsed[$tag]))
				continue;

			$mod = CMS::modules()->info($tag);
			$a = $this->extractDeps($mod);

			$assets->node		= array_merge($assets->node, $a->node);
			$assets->nodeDev	= array_merge($assets->nodeDev, $a->nodeDev);
			$assets->composer	= array_merge($assets->composer, $a->composer);
			$assets->composerDev= array_merge($assets->composerDev, $a->composerDev);

			// Search for module dependencies
			$deps = CMS::modules()->dependencies($tag, true, true, $parsed);
			$parsed[$tag] = $version;

			// For each module dependency, repeat the asset extraction
			foreach ($deps as $depTag => $depVer) {
				if (isset($parsed[$depTag]))
					continue;

				$mod = CMS::modules()->info($depTag);
				$a = $this->extractDeps($mod);

				$assets->node		= array_merge($assets->node, $a->node);
				$assets->nodeDev	= array_merge($assets->nodeDev, $a->nodeDev);
				$assets->composer	= array_merge($assets->composer, $a->composer);
				$assets->composerDev= array_merge($assets->composerDev, $a->composerDev);
			}
		}

		$assets->node		= array_unique($assets->node);
		$assets->nodeDev	= array_unique($assets->nodeDev);
		$assets->composer	= array_unique($assets->composer);
		$assets->composerDev= array_unique($assets->composerDev);
		#endregion

		#region Check against package.json and install any missing dependencies
		foreach ($assets->node as $pkg => $version) {
			if (!isset($node->dependencies->$pkg)) {
				echo "Installing missing node dependency $pkg...\n";
				$p = Process::cmd("npm install --save $pkg")->wait();
				echo $p->output();
			}
		}

		foreach ($assets->nodeDev as $pkg => $version) {
			if (!isset($node->devDependencies->$pkg)) {
				echo "Installing missing node development dependency $pkg...\n";
				$p = Process::cmd("npm install --save-dev $pkg")->wait();
				echo $p->output();
			}
		}
		#endregion

		#region Check against composer.json and install any missing dependencies
		foreach ($assets->composer as $pkg => $version) {
			if (!isset($composer->require->$pkg)) {
				echo "Installing missing composer dependency $pkg...\n";
				$p = Process::cmd("composer require $pkg:$version")->wait();
				if ($p->exitCode() != 0) {
					echo "Error installing dependency...\n";
					echo $p->output();
				}
			}
		}

		$param = 'require-dev';
		foreach ($assets->composerDev as $pkg => $version) {
			if (!isset($composer->$param->$pkg)) {
				echo "Installing missing composer development dependency $pkg...\n";
				$p = Process::cmd("npm require --dev $pkg:$version")->wait();
				if ($p->exitCode() != 0) {
					echo "Error installing dependency...\n";
					echo $p->output();
				}
			}
		}
		#endregion


		return parent::onBuild($args);
	}
	#endregion

	#region Internal methods
	/**
	 * Extracts node and composer dependencies from the given configuration
	 * and returns an \stdClass with the following array properties: node, composer
	 *
	 * @param \stdClass $cfg
	 *
	 * @return \stdClass
	 */
	protected function extractDeps(stdClass $cfg): stdClass {
		$deps = new \stdClass();
		$deps->node = [];
		$deps->nodeDev = [];
		$deps->composer = [];
		$deps->composerDev = [];

		#region Find npm package requirements
		if (isset($cfg->requires) && isset($cfg->requires->node)) {
			foreach ($cfg->requires->node as $pkg => $ver)
				$deps->node[$pkg] = $ver;
		}

		if (isset($cfg->requiresDev) && isset($cfg->requiresDev->node)) {
			foreach ($cfg->requiresDev->node as $pkg => $ver)
				$deps->nodeDev[$pkg] = $ver;
		}
		#endregion

		#region Find composer package requirements
		if (isset($cfg->requires) && isset($cfg->requires->composer)) {
			foreach ($cfg->requires->composer as $pkg => $ver)
				$deps->composer[$pkg] = $ver;
		}

		if (isset($cfg->requiresDev) && isset($cfg->requiresDev->composer)) {
			foreach ($cfg->requiresDev->composer as $pkg => $ver)
				$deps->composerDev[$pkg] = $ver;
		}
		#endregion

		return $deps;
	}
	#endregion
}
