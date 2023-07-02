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

require_once (__DIR__ . '/../../../../aneya.php');

use aneya\Core\Environment\Process;
use PHPUnit\Framework\TestCase;

class ProcessTest extends TestCase {
	public function testExecution() {
		$p = new Process('echo hello', Process::ModeBlocking);
		$p->run();

		$this->assertGreaterThan(0, $p->pid(), 'PID is not valid');
		$this->assertStringStartsWith('hello', $p->output() , 'Output different than hello');

		$p = new Process("bash -c 'sleep `shuf -i 1-2 -n 1`; shuf -i 1-5 -n 5; sleep `shuf -i 1-2 -n 1`; echo finished'", Process::ModeBlocking);
		$p->run();

		$this->assertGreaterThan(2, $dur = $p->duration());
		$this->assertStringEndsWith("finished\n", $p->output());
	}

	public function testInput() {
		$p = new Process("bash -c 'read -p \"Give my your name: \" name; echo \$name;'");
		$p->run();

		$p->input("aneya\n")->wait();

		$this->assertStringEndsWith("aneya\n", $p->output());
	}
}
