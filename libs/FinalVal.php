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
 *
 * @TODO Rename into LiteralVal
 */
class FinalVal implements Val, Term
{

	/**
	 * @var mixed
	 */
	private $val;

	private string $type;

	/**
	 * @param mixed $val
	 */
	function __construct($val, string $type)
	{
		$this->val = $val;
		$this->type = $type;
	}



	function type(): string
	{
		return $this->type;
	}



	function getTypeName(): string
	{
		return $this->type;
	}



	/**
	 * @return mixed
	 */
	function unpack()
	{
		if (is_scalar($this->val)) {
			return $this->val;
		}
		if (is_object($this->val)) {
			$xs = [];
			foreach ((array) $this->val as $i => $x) {
				$xs[$i] = $x instanceof self
					? $x->unpack()
					: $x;
			}
			return (object) $xs;
		}
		if (is_array($this->val)) {
			$xs = [];
			foreach ($this->val as $i => $x) {
				$xs[$i] = $x instanceof self
					? $x->unpack()
					: $x;
			}
			return $xs;
		}
		throw new LogicException("Comming soon...");
	}



	function __toString(): string
	{
		return (string) $this->unpack();
	}

}
