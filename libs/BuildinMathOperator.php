<?php declare(strict_types = 1);

/**
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 * Copyright (c) since 2004 Martin Takáč
 * @author Martin Takáč <martin@takac.name>
 */

namespace Taco\Hayo;

use LogicException;


/**
 * ???
 */
class BuildinMathOperator implements BuildinFunc
{

	private string $op;

	function __construct(string $op)
	{
		$this->op = $op;
	}



	function type(): string
	{
		return "<buildin-func {$this->op}>";
	}



	/**
	 * @return list<string>
	 */
	function refs(): array
	{
		return [];
	}



	function buildValue(): VariadicVal
	{
		switch ($this->op) {
			case '+':
			case '-':
			case '*':
			case 'div':
			case 'mod':
				return new VariadicVal($this->buildCallback()
					, 'Int'
					, [new BindVal('a', 'Int'), new BindVal('a', 'Int')]);

			default:
				throw new LogicException("Unsupported operator: {$this->op}.");
		}
	}



	/**
	 * @return callable
	 */
	private function buildCallback()
	{
		switch ($this->op) {
			case '+':
				return static function(array $args) {
					return $args[0]->unpack() + $args[1]->unpack();
				};
			case '-':
				return static function(array $args) {
					return $args[0]->unpack() - $args[1]->unpack();
				};
			case '*':
				return static function(array $args) {
					return $args[0]->unpack() * $args[1]->unpack();
				};
			case 'div':
				return static function(array $args) {
					return intdiv($args[0]->unpack(), $args[1]->unpack());
				};
			case 'mod':
				return static function(array $args) {
					return $args[0]->unpack() % $args[1]->unpack();
				};

			default:
				throw new LogicException("Unsupported operator: {$this->op}.");
		}
	}



	function apply(array $args): Val
	{
		throw new \LogicException("Comming soon...");
	}



	function __toString()
	{
		return $this->type();
	}

}
