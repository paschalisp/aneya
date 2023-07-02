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

use aneya\Core\CMS;
use aneya\Core\Environment\Net;
use aneya\Core\Hookable;
use aneya\Core\IHookable;
use GuzzleHttp\Psr7\ServerRequest;

class Request extends ServerRequest implements IHookable {
	use Hookable;

	#region Constants
	const MethodGet     = 'get';
	const MethodPost    = 'post';
	const MethodPut     = 'put';
	const MethodDelete  = 'delete';
	const MethodHead    = 'head';
	const MethodOptions = 'options';
	const MethodPatch   = 'patch';
	const MethodUnknown = '-';

	const ResponseCodeOK                   = 200;
	const ResponseCodeCreated              = 201;
	const ResponseCodeAccepted             = 202;
	const ResponseCodeNonAuthoritativeInfo = 203;
	const ResponseCodeNoContent            = 204;
	const ResponseCodeResetContent         = 205;
	const ResponseCodePartialContent       = 206;
	const ResponseCodeMultiStatus          = 207;
	const ResponseCodeAlreadyReported      = 208;

	const ResponseCodeMultipleChoices   = 300;
	const ResponseCodeMovedPermanently  = 301;
	const ResponseCodeFound             = 302;
	const ResponseCodeSeeOther          = 303;
	const ResponseCodeNotModified       = 304;
	const ResponseCodeUseProxy          = 305;
	const ResponseCodeTemporaryRedirect = 307;
	const ResponseCodePermanentRedirect = 308;

	const ResponseCodeBadRequest                  = 400;
	const ResponseCodeUnauthorized                = 401;
	const ResponseCodePaymentRequired             = 402;
	const ResponseCodeForbidden                   = 403;
	const ResponseCodeNotFound                    = 404;
	const ResponseCodeMethodNotAllowed            = 405;
	const ResponseCodeNotAcceptable               = 406;
	const ResponseCodeProxyAuthRequired           = 407;
	const ResponseCodeRequestTimeout              = 408;
	const ResponseCodeConflict                    = 409;
	const ResponseCodeGone                        = 410;
	const ResponseCodeLengthRequired              = 411;
	const ResponseCodePreconditionFailed          = 412;
	const ResponseCodePayloadTooLarge             = 413;
	const ResponseCodeURITooLong                  = 414;
	const ResponseCodeUnsupportedMediaType        = 415;
	const ResponseCodeRangeNotSatisfiable         = 416;
	const ResponseCodeExpectationFailed           = 417;
	const ResponseCodeMisdirectedRequest          = 421;
	const ResponseCodeUnprocessableEntity         = 422;
	const ResponseCodeLocked                      = 423;
	const ResponseCodeFailedDependency            = 424;
	const ResponseCodeUpgradeRequired             = 425;
	const ResponseCodePreconditionRequired        = 426;
	const ResponseCodeTooManyRequests             = 429;
	const ResponseCodeRequestHeaderFieldsTooLarge = 431;
	const ResponseCodeUnavailableForLegalReasons  = 451;

	const ResponseCodeInternalServerError           = 500;
	const ResponseCodeNotImplemented                = 501;
	const ResponseCodeBadGateway                    = 502;
	const ResponseCodeServiceUnavailable            = 503;
	const ResponseCodeGatewayTimeout                = 504;
	const ResponseCodeHTTPVersionNotSupported       = 505;
	const ResponseCodeVariantAlsoNegotiates         = 506;
	const ResponseCodeInsufficientStorage           = 507;
	const ResponseCodeLoopDetected                  = 508;
	const ResponseCodeNotExtended                   = 510;
	const ResponseCodeNetworkAuthenticationRequired = 511;

	const ModePage = 'page';
	const ModeAjax = 'ajax';
	#endregion

	#region Properties
	/** @var string */
	public string $method;
	/** @var string */
	public string $protocol;
	/** @var string */
	public string $serverName;
	/** @var string */
	public string $requestHostname;
	/** @var string */
	public string $ipAddress;
	/** @var string|int */
	public $port;
	/** @var string */
	public string $uri;
	/** @var string */
	public string $authType;
	/** @var string */
	public string $contentType;
	/** @var bool true if the page was called via an ajax call */
	public ?bool $isAjax;
	/** @var bool true if the HTTP/WebSocket request was held via SSL */
	public ?bool $isSSL;
	/** @var array */
	public array $getVars;
	/** @var array */
	public array $postVars;
	/** @var array */
	public array $filesVars;
	/** @var array */
	public array $serverVars;
	/** @var array */
	public array $sessionVars;
	/** @var array */
	public array $cookieVars;
	#endregion

	#region Static methods
	/** Returns a Request instance initialized with all information found in the environment ($_SERVER, $_GET/$_POST global vars). */
	public static function fromEnv(): Request {
		$r = new Request($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);

		$mode = strtolower($_SERVER['REQUEST_METHOD']);
		$vars = array_change_key_case($_SERVER, CASE_LOWER);

		$r->uri = $_SERVER['REQUEST_URI'];
		$r->method = (in_array($mode, array (self::MethodGet, self::MethodPost, self::MethodPut, self::MethodDelete, self::MethodHead, self::MethodOptions))) ? $mode : self::MethodUnknown;
		$r->serverName = $_SERVER['SERVER_NAME'];
		$r->port = $_SERVER['SERVER_PORT'];
		$r->protocol = $_SERVER['SERVER_PROTOCOL'];
		$r->requestHostname = $_SERVER['HTTP_HOST'];
		$r->ipAddress = Net::getIpAddress();
		$r->contentType = $_SERVER['CONTENT_TYPE'] ?? '';
		$r->authType = $_SERVER['AUTH_TYPE'] ?? '';
		$r->isAjax = (
			isset($vars['http_x_requested_with']) && strtolower($vars['http_x_requested_with']) === 'xmlhttprequest' ||
			isset($vars['x-requested-with']) && strtolower($vars['x-requested-with']) === 'xmlhttprequest'
		);
		$r->isSSL = !empty($_SERVER['HTTPS']);
		$r->getVars = $_GET ?? [];
		$r->postVars = $_POST ?? [];
		$r->filesVars = $_FILES ?? [];
		$r->serverVars = $_SERVER ?? [];
		$r->sessionVars = $_SESSION ?? [];
		$r->cookieVars = $_COOKIE ?? [];

		return $r;
	}

	/** Returns environment's POST with special characters (like dots & spaces) as they were actually received */
	public static function getPOST(): ?array {
		if (isset ($_SERVER['CONTENT_TYPE']) && stripos($_SERVER['CONTENT_TYPE'], 'multipart/form-data') === 0) {
			return $_POST;
		}

		$post = file_get_contents("php://input");
		if (strlen($post) == 0) {
			return null;
		}

		$ret = null;

		#region Rebuild POST
		$keyValues = explode("&", $post);
		foreach ($keyValues as $keyVal) {
			$keyVal = explode("=", $keyVal);
			$key = urldecode($keyVal[0]);
			if (!isset($keyVal[1]))
				$keyVal[1] = '';
			$value = is_string($keyVal[1]) ? urldecode($keyVal[1]) : $keyVal[1];
			// Check if key represents an array expression
			$arrKeys = self::__strArrayKeys($key);
			$numKeys = count($arrKeys);
			$arrKeyname = substr($key, 0, strpos($key, '['));
			if ($numKeys > 0) {
				$val = $value;
				for ($i = $numKeys - 1; $i >= 0; $i--) {
					$arr = ($arrKeys[$i] == '') ? $val : array ((string)$arrKeys[$i] => $val);
					$val = $arr;
				}
				if ($arrKeys[$numKeys - 1] == '') {            // Last array is sequential (e.g. $x[])
					if (!isset($ret[$arrKeyname]))
						$ret[$arrKeyname] = array ();

					if ($numKeys == 1) {                        // Sequential array is root (e.g. $array[])
						$ret[$arrKeyname][] = $value;
					}
					else {                                        // Sequential array is nested (e.g. $array['key'][])
						$ret[$arrKeyname] = self::__array_merge_recursive($ret[$arrKeyname], $val);
					}
				}
				elseif (isset($ret[$arrKeyname])) {            // Last array is associative
					$ret[$arrKeyname] = array_replace_recursive($ret[$arrKeyname], $val);
				}
				else {
					$ret[$arrKeyname] = $val;
				}
			}
			else {
				$ret[$key] = $value;
			}
		}
		#endregion

		return $ret;
	}

	/** Returns environment's GET with special characters (like dots & spaces) as they were actually received */
	public static function getGET(): ?array {
		if (!isset($_SERVER['QUERY_STRING']) || strlen($_SERVER['QUERY_STRING']) == 0)
			return null;

		$keyValues = explode("&", $_SERVER['QUERY_STRING']);
		$ret = array ();
		foreach ($keyValues as $keyVal) {
			$keyVal = explode("=", $keyVal);
			$key = urldecode($keyVal[0]);
			if (!isset($keyVal[1]))
				$keyVal[1] = '';
			$value = is_string($keyVal[1]) ? stripslashes(urldecode($keyVal[1])) : $keyVal[1];
			$ret[$key] = $value;
		}
		return $ret;
	}

	/**
	 * Returns REQUEST's URI its language code information switched to the provided language code.
	 *
	 * @param string $langCode
	 * @param string|null $fallbackUrl
	 * @param string $pattern
	 * @param string $replacePattern Use {LC} as placeholder to
	 *
	 * @return ?string
	 */
	public static function switchLanguageURL(string $langCode, string $fallbackUrl = null, string $pattern = '#^/([a-z]{2})(/.*)?$#', $replacePattern = '/{LC}$2'): ?string {
		$url = $_SERVER['REQUEST_URI'];

		$langs = CMS::translator()->languages();
		$matched = false;
		foreach ($langs as $l) {
			preg_match($pattern, $url, $matches);
			foreach ($matches as $m) {
				if ($m !== $l->code)
					continue;

				$replacePattern = str_ireplace('{lc}', $langCode, $replacePattern);
				$url = preg_replace($pattern, $replacePattern, $url, 1);
				$matched = true;
				break;
			}
		}
		if (!$matched && strlen($fallbackUrl) > 0)
			$url = $fallbackUrl;

		return $url;
	}

	/**
	 * Called internally from framework to initialize the Request class.
	 *
	 * @internal
	 */
	public static function init() {
		// Strip slashes and encode special chars from all input (when not running as shell script)
		if (!CMS::env()->isCLI()) {
			$_POST = Request::getPOST();
			$_GET = Request::getGET();
			$_REQUEST = [];
			if ($_GET != null)
				$_REQUEST = array_merge($_GET);
			if ($_POST != null)
				$_REQUEST = array_merge($_REQUEST, $_POST);
			$_COOKIE = array_map(function ($str) { return (is_string($str)) ? stripslashes($str) : $str; }, $_COOKIE);
		}
	}

	#region Private methods
	/**
	 * Returns all keys found in a string that contains an array representation (e.g. 'prop[0][data]' returns [0,data])
	 *
	 * @param $str
	 *
	 * @return array
	 */
	private static function __strArrayKeys($str): array {
		$keys = array ();
		preg_match('/^(.+)(\\[(.*)\\])+$/', $str, $matches);
		if (isset ($matches[1]) && strlen($matches[1]) > 0) {
			$subkeys = self::__strArrayKeys($matches[1]);
			foreach ($subkeys as $k)
				$keys[] = $k;

			$keys[] = $matches[3];
		}

		return $keys;
	}

	/**
	 * Merges any number of arrays recursively,
	 * The method is based on Mark Roduner's <mark.roduner@gmail.com> array merge function http://www.php.net/manual/en/function.array-merge-recursive.php#96201)
	 * with the difference that if a key from a merged array already exists in base array and its value is not an array, the value is automatically converted to array
	 * and the new value (from the merged array) is appended to the key of the base array (instead of replacing the previous value as per the original method).
	 *
	 * @param  array $base Base array to merge
	 * @param  array ... One or more arrays to merge into the base array
	 *
	 * @return array
	 */
	private static function __array_merge_recursive(): array {
		$arrays = func_get_args();
		$base = array_shift($arrays);

		if (!is_array($base)) $base = empty($base) ? array () : array ($base);

		foreach ($arrays as $append) {
			if (!is_array($append))
				$append = array ($append);

			foreach ($append as $key => $value) {
				if (!array_key_exists($key, $base) && !is_numeric($key)) {
					$base[$key] = $value;
					continue;
				}
				if (is_array($value) || is_array($base[$key])) {
					$base[$key] = self::__array_merge_recursive($base[$key], $value);
				}
				else if (is_numeric($key)) {
					if (!in_array($value, $base)) $base[] = $value;
				}
				else {
					if (!is_array($base[$key])) {
						$base[$key] = array ($base[$key]);
					}
					$base[$key][] = $value;
				}
			}
		}

		return $base;
	}
	#endregion
	#endregion
}

Request::init();
