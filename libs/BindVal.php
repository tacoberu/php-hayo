<?php declare(strict_types = 1);

/**
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 * Copyright (c) since 2004 Martin Takáč
 * @author Martin Takáč <martin@takac.name>
 */

namespace Taco\Hayo;

/**
 * Hodnota vyžaduje nějaké argumenty. Výsledek je tedy třeba vypočítat.
 */
class BindVal
{

	private string $name;

	private string $type;

	function __construct(string $name, string $type)
	{
		$this->name = $name;
		$this->type = $type;
	}



	function getName(): string
	{
		list($x, ) = explode('.', $this->name, 2);
		return $x;
	}



	function getBindName(): string
	{
		return $this->name;
	}



	function getTypeName(): string
	{
		return $this->type;
	}



	function isPath(): bool
	{
		return (bool) strpos($this->name, '.');
	}



	function isLibrarySymbol(): bool
	{
		return (bool) (strpos($this->name, '.') && ctype_upper($this->name[0]));
	}



	function unpack(): string
	{
		return "<?{$this->name}> :: {$this->type}";
	}



	function __toString(): string
	{
		return "<?{$this->name}> :: {$this->type}";
	}

}
