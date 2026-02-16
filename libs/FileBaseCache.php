<?php declare(strict_types = 1);

/**
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 * Copyright (c) since 2004 Martin Takáč
 * @author Martin Takáč <martin@takac.name>
 */

namespace Taco\Hayo;

use RuntimeException;


class FileBaseCache implements Cache
{

	const VersionABI = '0.3.0';

	private string $path;

	function __construct(string $path)
	{
		$path = rtrim($path, '/\\');
		if ( ! is_writable($path)) {
			throw new RuntimeException("Location for file cache: '{$path}' is not writeable.");
		}
		$path .= '/' . self::VersionABI;
		if (!file_exists($path) && !@mkdir($path)) {
			throw new RuntimeException("Location for file cache: '{$path}' could not be created.");
		}
		$this->path = $path;
	}



	/**
	 * @param callable $cb
	 * @return FinalVal | VariadicVal
	 */
	function load(string $key, $cb)
	{
		$location = "{$this->path}/{$key}";
		if (file_exists($location) && ($data = file_get_contents($location)) !== False) {
			return unserialize($data);
		}
		$data = $cb();
		file_put_contents($location, serialize($data));
		return $data;
	}

}
