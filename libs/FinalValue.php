<?php declare(strict_types = 1);

/**
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 * Copyright (c) since 2004 Martin Takáč
 * @author Martin Takáč <martin@takac.name>
 */

namespace Taco\Hayo;

use LogicException;
use DateTimeInterface;
use stdClass;


/**
 * Final value, wrapped with a type. Can be a scalar or a variously
 * nested structure. Requires no parameters, so it is static.
 */
class FinalValue implements Value
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



	/**
	 * Builds a composite (product) value: a FinalValue whose payload is the list
	 * of its field FinalValues, tagged with the type name. The single source of
	 * truth for the value shape produced by HayoEngine::value() and by library
	 * constructor functions; read the fields back with fields().
	 *
	 * @param list<FinalValue> $fields
	 */
	static function composite(array $fields, string $type): self
	{
		return new self($fields, $type);
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
	 * Field FinalValues of a composite value built by composite().
	 * @return list<FinalValue>
	 */
	function fields(): array
	{
		if ( ! is_array($this->value)) {
			throw new LogicException("Value of type '{$this->type}' is not composite.");
		}
		return array_values($this->value);
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
		if ($this->value instanceof stdClass) {
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
		if (is_object($this->value) && self::allowedBaseObject($this->value)) {
			return $this->value;
		}
		throw new LogicException("Comming soon...: '" . print_r($this->value, true) . "'.");
	}



	private static function allowedBaseObject(object $inst): bool
    {
        return $inst instanceof DateTimeInterface
            || $inst instanceof SumTypeValue;
    }



	function __toString(): string
	{
		return (string) $this->unpack();
	}

}
