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

use aneya\Expressions\Expression;

require_once "../../../../aneya.php";

$expr = new Expression("a =   b && ((b = c) || d <> 'ok=\"ok\"') && (e=true || \"XX\"='YY') && f && a=c");
$expr->arg([
	'a' => 1,
	'b' => 1,
	'c' => 1,
	'd'	=> 'ok=\"ok\"',
	'e' => true,
	'f' => true
		   ]);

$ret = $expr->evaluate();
print_r($ret);
echo "\n";

$expr->expression('1+2+3+4*2');
$ret = $expr->evaluate();
print_r($ret);
echo "\n";

$expr->expression('(1+2+3+4)*2');
$ret = $expr->evaluate();
print_r($ret);
echo "\n";

$expr->expression('1+2+3+4+5 = 10 + 10/2');
$ret = $expr->evaluate();
print_r($ret);
echo "\n";
