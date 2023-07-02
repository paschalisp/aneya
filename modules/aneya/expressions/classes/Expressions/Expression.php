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

namespace aneya\Expressions;

use aneya\Core\Hookable;
use aneya\Core\IHookable;
use aneya\Core\Utils\StringUtils;

class Expression implements IHookable {
	use Hookable;

	#region Constants
	const Keywords = ['=', '<', '<=', '>', '>=', 'AND', 'OR', 'NOT', '(', ')', 'IN'];
	#endregion

	#region Event Constants
	/**
	 * Triggered when an unknown variable (a variable that its value is not predefined via the arg() method) is found
	 * during expression's evaluation.
	 *
	 * Passes an ExpressionEventArgs to listeners with the 'variable' property set and expects listeners to return
	 * an EventStatus with the 'data' property set; otherwise the token in which the value was found will evaluate
	 * as FALSE.
	 */
	const EventOnUnknownVariable = 'OnUnknownVariable';
	#endregion

	#region Properties
	/** @var string Stores the expression that is to be tested */
	protected $_expression;

	/** @var string Stores the parsed (compiled) formula of the expression */
	protected $_formula;

	/** @var array Stores all parentheses found in the expression */
	protected $_pars = [];

	/** @var array Stores all variables passed to the instance */
	protected $_vars = [];

	/** @var array Stores all quoted strings found in the expression and replaces them with internal variables */
	protected $_quot = [];

	/** @var array Caches tokenized expressions to speed up with repetitive expressions */
	protected $_cache = [];

	/** @var array Stores hierarchically all tokens found in the expression */
	protected $_tokens = [];


	/** @var bool True if predicate has been evaluated */
	protected $_evaluated;

	/** @var bool True if predicate's expression has been parsed */
	protected $_parsed;

	/** @var bool|null Stores the resulting evaluation */
	protected $_result;
	#endregion

	#region Constructor
	public function __construct($expression = '') {
		$this->expression($expression);
	}
	#endregion

	#region Methods
	/**
	 * Declares an argument (expression variable) with its value.
	 * Multiple arguments can be declared at once by passing an associative array.
	 *
	 * @param string|array $arg
	 * @param mixed $value
	 *
	 * @return Expression
	 */
	public function arg($arg, $value = null) {
		if (is_array($arg)) {
			foreach ($arg as $key => $value) {
				$this->_vars[$key] = $value;
			}
		}
		elseif (is_string($arg))
			$this->_vars[$arg] = $value;

		// Reset properties
		$this->_evaluated = false;
		$this->_result = null;

		return $this;
	}

	/**
	 * Clears all arguments (variables) stored in the predicate
	 *
	 * @return Expression
	 */
	public function clear() {
		$this->_vars = [];

		// Reset properties
		$this->_evaluated = false;
		$this->_result = null;

		return $this;
	}

	/**
	 * Gets/Sets the expression to be tested
	 *
	 * @param ?string $expression (optional)
	 *
	 * @return Expression|string
	 */
	public function expression(string $expression = null) {
		if ($expression !== null) {
			$this->_expression = $this->_formula = preg_replace('/[\s]{2,}/', ' ', $expression);

			// Reset all properties
			$this->_vars	= [];
			$this->_cache	= [];
			$this->_evaluated = false;
			$this->_pars	= [];
			$this->_parsed	= false;
			$this->_quot	= [];
			$this->_result	= null;
			$this->_tokens	= [];

			return $this;
		}

		return $this->_expression;
	}

	/**
	 * Returns the result of the expression's evaluation.
	 *
	 * @param string|array $arg
	 * @param mixed $value
	 * @return mixed
	 *
	 * @throws InvalidExpressionException
	 */
	public function evaluate($arg = null, $value = null) {
		// Overwrite instance arguments with the given ones, if any
		if (isset($arg)) {
			$this->clear();
			$this->arg($arg, $value);
		}

		// Return result directly, if already evaluated
		if ($this->_evaluated)
			return $this->_result;

		#region Prepare the expression, if not already parsed
		if ($this->_parsed !== true) {

			$this->parseQuotes();

			$o = substr_count($this->_formula, '(');
			$c = substr_count($this->_formula, ')');
			if ($o < $c)
				throw new InvalidExpressionException("Unused closing parenthesis found");
			elseif ($o > $c)
				throw new InvalidExpressionException("Parenthesis did not close correctly");

			$this->_formula = $this->parseParentheses($this->_formula);
			$this->_parsed = true;

			// Generate hierarchical tokens
			$this->_tokens = $this->tokenize($this->_formula);
		}
		#endregion

		#region Evaluate tokens
		$this->_result = $this->evaluateToken($this->_tokens);
		$this->_evaluated = true;
		#endregion

		return $this->_result;
	}
	#endregion

	#region Internal methods
	#region Expression evaluation methods
	/**
	 * Parses the given expression hierarchically and returns its main tokens.
	 *
	 * @param string $expression
	 *
	 * @return array|string
	 *
	 * @throws InvalidExpressionException
	 */
	protected function tokenize(string $expression) {
		if (isset($this->_cache[$expression]))
			return $this->_cache[$expression];

		$tokens = [];
		$expression = $this->parseParentheses($expression);

		#region Placeholder replacement (PARENTHESIS, QUOTE)
		$ret = preg_match("/^___(?<placeholder>QUOTE|PARENTHESIS)(?<num>\d+)___$/", $expression, $arr);
		if ($ret > 0) {
			if ($arr['placeholder'] == 'QUOTE')
				$tokens = '"' . $this->_quot[(int)$arr['num'] - 1] . '"';
			else
				$tokens = $this->tokenize($this->_pars[(int)$arr['num'] - 1]);
		}
		#endregion

		else {
			#region 1st level tokenization (&&, ||)
			$ret = preg_match("/^(?<left>[^&&|\|\|]+)\s+(?<operand>&&|\|\|)\s+(?<right>.+)$/", $expression, $arr);
			if ($ret > 0) {
				$tokens = ['left' => $arr['left'], 'operand' => $arr['operand'], 'right' => $arr['right']];
				$tokens['left'] = $this->tokenize($tokens['left']);
				$tokens['right'] = $this->tokenize($tokens['right']);
			}
			#endregion

			else {
				#region 2rd level tokenization (=, !=, >, >=, <, <=, ~=, *=, =*)
				$ret = preg_match("/^(?<left>[^!=|<>|>=|<=|~=|\*=|=\*|=|<|>]+)\s*(?<operand>!=|<>|>=|<=|~=|\*=|=\*|=|<|>)\s*(?<right>.+)$/", $expression, $arr);
				if ($ret > 0) {
					$tokens = ['left' => $arr['left'], 'operand' => $arr['operand'], 'right' => $arr['right']];
					$tokens['left'] = $this->tokenize($tokens['left']);
					$tokens['right'] = $this->tokenize($tokens['right']);
				}
				#endregion

				else {
					#region 3rd level tokenization (+, -)
					$ret = preg_match("/^(?<left>[^\+|\-]+)\s*(?<operand>\+|\-)\s*(?<right>.+)$/", $expression, $arr);
					if ($ret > 0) {
						$tokens = ['left' => $arr['left'], 'operand' => $arr['operand'], 'right' => $arr['right']];
						$tokens['left'] = $this->tokenize($tokens['left']);
						$tokens['right'] = $this->tokenize($tokens['right']);
					}
					#endregion

					else {
						#region 4th level tokenization (*, /)
						$ret = preg_match("/^(?<left>[^\*|\/]+)\s*(?<operand>\*|\/)\s*(?<right>.+)$/", $expression, $arr);
						if ($ret > 0) {
							$tokens = ['left' => $arr['left'], 'operand' => $arr['operand'], 'right' => $arr['right']];
							$tokens['left'] = $this->tokenize($tokens['left']);
							$tokens['right'] = $this->tokenize($tokens['right']);
						}
						#endregion
					}
				}
			}
		}

		// If expression has been fully parsed, return the expression itself
		if (is_array($tokens) && count($tokens) == 0)
			$tokens = $expression;

		// Cache results to speed up repetitive expressions
		$this->_cache[$expression] = $tokens;

		return $tokens;
	}

	/**
	 * Evaluates a token and returns the result
	 *
	 * @param mixed $token
	 *
	 * @return mixed
	 *
	 * @throws InvalidExpressionException
	 */
	protected function evaluateToken($token) {
		#region Evaluate variables & values
		if (is_scalar($token)) {
			$token = trim($token);

			#region Token holds a value
			// Quoted texts
			if (substr($token, 0, 1) == '"' && substr($token, -1, 1) == '"')
				return $token;


			// Numbers
			if (is_numeric($token))
				return (float)$token;

			elseif (is_null($token))
				return null;


			// keywords
			elseif ($token === "true")
				return true;

			elseif ($token === "false")
				return false;

			elseif ($token === "null")
				return null;
			#endregion

			#region Token holds a variable
			else {
				// Token holds a named variable
				if (isset($this->_vars[$token]))
					return $this->_vars[$token];

				else {
					#region Handle unknown variable
					$args = new ExpressionEventArgs($this);
					$args->variable = $token;
					$statuses = $this->trigger(self::EventOnUnknownVariable, $args);
					/** @var ExpressionEventStatus $status */
					foreach ($statuses as $status) {
						if ($status->isOK())
							return $status->evaluation;

						throw new InvalidExpressionException("Could not evaluate variable $token. Event listener message: " . $status->message);
					}
					#endregion

					// If not handled, throw exception
					throw new InvalidExpressionException("Undefined variable $token found in expression.");
				}
			}
			#endregion
		}
		#endregion

		#region Evaluate simplified tokens
		elseif (is_array($token)) {
			$left = $this->evaluateToken($token['left']);
			$operand = $token['operand'];
			$right = $this->evaluateToken($token['right']);

			switch ($operand) {
				// 1st level operands
				case '&&':
					return $left && $right;

				case '||':
					return $left || $right;


				// 2nd level operands
				case '=':
					return $left == $right;

				case '!=':
				case '<>':
					return $left != $right;

				case '>':
					return $left > $right;

				case '>=':
					return $left >= $right;

				case '<':
					return $left < $right;

				case '<=':
					return $left <= $right;

				case '~=':	// Contains
					if (is_array($left))
						return in_array(StringUtils::trimQuotes($right), $left);
					else
						return stripos($left, $right) !== false;

				case '*=':	// Starts with
					return stripos($left, $right) === 0;

				case '=*':	// Ends with
					return substr($left, -strlen($right)) == $right;


				// 3rd level operands
				case '*':
					return $left * $right;

				case '/':
					return $left / $right;


				// 4th level operands
				case '+':
					return $left + $right;

				case '-':
					return $left - $right;
			}
		}
		#endregion

		return null;
	}
	#endregion

	#region Expression parsing methods
	/**
	 * Replaces 1st-level parentheses in expression with internal placeholders
	 * @param string $expression
	 *
	 * @return string
	 *
	 * @throws InvalidExpressionException
	 */
	protected function parseParentheses($expression) {
		$ok = false;

		/** @var int Last parenthesis position found in the expression */
		$lastPos = 0;

		/** @var string Stores the number of parentheses parsed */
		$pNum = 0;

		do {
			$p = strpos($expression, '(', $lastPos);

			if ($p !== false) {
				$pNum++;

				$expression = $this->replaceParenthesis($expression, $p);
				$lastPos = $p;

			}
			else {
				// Finished parentheses conversion
				$ok = true;
			}
		}
		while ($ok == false);

		return $expression;
	}

	/**
	 * Replaces quoted text in formula with internal placeholders
	 * @throws InvalidExpressionException
	 */
	protected function parseQuotes() {
		$ok = false;

		/** @var int Last quote position found in the formula */
		$lastPos = 0;

		/** @var string Stores the number of single quotes parsed */
		$sNum = 0;
		/** @var string Stores the number of double quotes parsed */
		$dNum = 0;

		do {
			$s = strpos($this->_formula, "'", $lastPos);
			$d = strpos($this->_formula, '"', $lastPos);

			if ($s !== false) {
				if ($d !== false) {
					if ($s < $d) {
						#region Single quotes (probably) containing double quotes
						$sNum++;

						try {
							$this->replaceQuote("'", $s);
							$lastPos = $s;
						}
						catch (InvalidExpressionException $e) {
							throw new InvalidExpressionException("Single quote (number $sNum in the expression) did not close correctly");
						}
						#endregion
					}
					else {
						#region Double quotes (probably) containing single quotes
						$dNum++;

						try {
							$this->replaceQuote('"', $d);
							$lastPos = $d;
						}
						catch (InvalidExpressionException $e) {
							throw new InvalidExpressionException("Double quote (number $dNum in the expression) did not close correctly");
						}
						#endregion
					}
				}
				else {
					#region Only single quotes were found
					$sNum++;

					try {
						$this->replaceQuote("'", $s);
						$lastPos = $s;
					}
					catch (InvalidExpressionException $e) {
						throw new InvalidExpressionException("Single quote (number $sNum in the expression) did not close correctly");
					}
					#endregion
				}
			}
			elseif ($d !== false) {
				#region Only double quotes were found
				$dNum++;

				try {
					$this->replaceQuote('"', $d);
					$lastPos = $d;
				}
				catch (InvalidExpressionException $e) {
					throw new InvalidExpressionException("Double quote (number $dNum in the expression) did not close correctly");
				}
				#endregion
			}
			else {
				// Finished quotes conversion
				$ok = true;
			}
		}
		while ($ok == false);
	}

	/**
	 * Parses text inside parenthesis and returns the generated tokens
	 *
	 * @param string $expression
	 * @param int $from
	 *
	 * @return string
	 *
	 * @throws InvalidExpressionException
	 */
	protected function replaceParenthesis($expression, $from) {
		$to = strpos($expression, ')', $from + 1);
		if ($to === false)
			throw new InvalidExpressionException("Parenthesis did not close correctly");

		$opening = substr_count($expression, '(', $from + 1, $to - 1);

		for ($num = 0; $num < $opening; $num++) {
			$to = strpos($expression, ')', $to + 1);
			if ($to === false)
				throw new InvalidExpressionException("Parenthesis did not close correctly");
		}

		$this->_pars[] = substr($expression, $from + 1, $to - $from - 1);
		return substr($expression, 0, $from) . "___PARENTHESIS" . count($this->_pars) . '___' . substr($expression, $to + 1);
	}

	/**
	 * Replaces a quoted text starting from the given start in the formula with an internal tag
	 *
	 * @param string $quote
	 * @param int    $from
	 *
	 * @throws InvalidExpressionException
	 */
	protected function replaceQuote($quote, $from) {
		$to = strpos($this->_formula, $quote, $from + 1);
		if ($to === false)
			throw new InvalidExpressionException("Quote did not close correctly");

		$this->_quot[] = substr($this->_formula, $from + 1, $to - $from - 1);
		$this->_formula = substr($this->_formula, 0, $from) . "___QUOTE" . count($this->_quot) . '___' . substr($this->_formula, $to + 1);
	}
	#endregion
	#endregion
}
