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
 * Výsledná hodnota, zabalená s typem. Může se jednat o skalar, ale i o různě
 * zanořenou strukturu. Nevyžaduje žádné parametry, je tedy statická.
 */
class FinalVal implements Value
{

	/**
	 * @var mixed
	 */
	private $value;

	private string $type;

	/**
	 * @param mixed $value
	 */
	function __construct($value, string $type)
	{
		$this->value = $value;
		$this->type = $type;
	}



	function type(): string
	{
		return $this->type;
	}



	/**
	 * @return mixed
	 */
	function getValue()
	{
		return $this->value;
	}



	/**
	 * @return mixed
	 */
	function unpack()
	{
		if (is_scalar($this->value)) {
			return $this->value;
		}
		if (is_null($this->value)) {
			return $this->value;
		}
		if (is_object($this->value)) {
			$xs = [];
			foreach ((array) $this->value as $i => $x) {
				$xs[$i] = $x instanceof self
					? $x->unpack()
					: $x;
			}
			return (object) $xs;
		}
		if (is_array($this->value)) {
			$xs = [];
			foreach ($this->value as $i => $x) {
				$xs[$i] = $x instanceof self
					? $x->unpack()
					: $x;
			}
			return $xs;
		}
		throw new LogicException("Comming soon...: '" . print_r($this->value, true) . "'.");
	}



	function __toString(): string
	{
		return (string) $this->unpack();
	}

}
