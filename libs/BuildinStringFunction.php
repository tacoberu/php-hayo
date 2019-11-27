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
class BuildinStringFunction implements BuildinFunc
{

	private string $name;

	function __construct(string $name)
	{
		$this->name = $name;
	}



	/**
	 * @return string
	 */
	function type()
	{
		return "<buildin-func {$this->name}>";
	}



	/**
	 * @return array<string>
	 */
	function refs()
	{
		return [];
	}



	function buildValue(): VariadicVal
	{
		throw new LogicException("Comming soon...");
	}



	/**
	 * @return callable
	 */
	function buildCallback()
	{
		switch ($this->name) {
			case 'strings.len':
				return static function(array $args) {
					return strlen($args[0]->unpack());
				};

			default:
				throw new LogicException("Unsupported function: {$this->name}.");
		}
	}



	function __toString()
	{
		return $this->type();
	}

}
