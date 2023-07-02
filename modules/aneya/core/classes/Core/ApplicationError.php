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

namespace aneya\Core;


use Monolog\Logger;

class ApplicationError extends \Exception {
	#region Constants
	const SeverityDebug		= Logger::DEBUG;
	const SeverityInfo		= Logger::INFO;
	const SeverityNotice	= Logger::NOTICE;
	const SeverityWarning	= Logger::WARNING;
	const SeverityError		= Logger::ERROR;
	const SeverityCritical	= Logger::CRITICAL;
	const SeverityAlert		= Logger::ALERT;
	const SeverityEmergency	= Logger::EMERGENCY;
	#endregion

	#region Properties
	public $severity;
	public $pageCode;
	#endregion

	#region Constructor
	public function __construct($message = "", $code = 0, \Throwable $previous = null, $severity = Logger::ERROR) {
		parent::__construct($message, $code, $previous);

		$this->severity = $severity;

		if ($this->severity == null && $this->code > 0)
			$this->severity = self::errorCodeToSeverity($this->code, ApplicationError::SeverityDebug);
	}
	#endregion

	#region Methods
	public function setLine($line) {
		$this->line = $line;
	}

	public function setFile($file) {
		$this->file = $file;
	}
	#endregion

	#region Static methods
	public static function create(\Throwable $e, int $severity = null) {
		return new ApplicationError($e->getMessage(), $e->getCode(), $e, $severity);
	}

	public static function errorCodeToSeverity(int $code, int $unknown = ApplicationError::SeverityDebug) {
		if (in_array ($code, array (E_ERROR, E_USER_ERROR, E_COMPILE_ERROR, E_PARSE, E_CORE_ERROR, E_RECOVERABLE_ERROR))) {
			return self::SeverityError;
		}
		elseif (in_array ($code, array (E_WARNING, E_USER_WARNING, E_COMPILE_WARNING, E_CORE_WARNING))) {
			return self::SeverityWarning;
		}
		elseif (in_array ($code, array (E_NOTICE, E_USER_NOTICE))) {
			return self::SeverityNotice;
		}
		elseif (in_array($code, [E_DEPRECATED, E_STRICT])) {
			return self::SeverityDebug;
		}
		else {
			return $unknown;
		}
	}
	#endregion
}
