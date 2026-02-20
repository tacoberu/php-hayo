<?php declare(strict_types = 1);

/**
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 * Copyright (c) since 2004 Martin Takáč
 * @author Martin Takáč <martin@takac.name>
 */

namespace Taco\Hayo;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use DateTime;


class DateTimeFuncTest extends TestCase
{

	function testFromDateTime()
	{
		$inst = new DateTimeFunc('fromDateTime');
		$this->assertSame('DateTime', $inst->type());
		$this->assertEquals([
			new BindValue('year', 'Int'),
			new BindValue('month', 'Int'),
			new BindValue('day', 'Int'),
			new BindValue('hour', 'Int'),
			new BindValue('minute', 'Int'),
			new BindValue('sec', 'Int'),
		], $inst->getBinds());
		$this->assertSame("<DateTime.fromDateTime>", (string) $inst);
	}



	#[DataProvider('dataFromDateTimeApply')]
	function testFromDateTimeApply($year, $month, $day, $hour, $minute, $sec, $expected)
	{
		$inst = new DateTimeFunc('fromDateTime');
		$this->assertEquals($expected, $inst->apply([
			new FinalValue($year, 'Int'),
			new FinalValue($month, 'Int'),
			new FinalValue($day, 'Int'),
			new FinalValue($hour, 'Int'),
			new FinalValue($minute, 'Int'),
			new FinalValue($sec, 'Int'),
		])->unpack());
	}



	function testFromDate()
	{
		$inst = new DateTimeFunc('fromDate');
		$this->assertSame('DateTime', $inst->type());
		$this->assertEquals([
			new BindValue('year', 'Int'),
			new BindValue('month', 'Int'),
			new BindValue('day', 'Int'),
		], $inst->getBinds());
		$this->assertSame("<DateTime.fromDate>", (string) $inst);
	}



	#[DataProvider('dataFromDateApply')]
	function testFromDateApply($year, $month, $day, $expected)
	{
		$inst = new DateTimeFunc('fromDate');
		$this->assertEquals($expected, $inst->apply([
			new FinalValue($year, 'Int'),
			new FinalValue($month, 'Int'),
			new FinalValue($day, 'Int'),
		])->unpack());
	}



	function testToTimestamp()
	{
		$inst = new DateTimeFunc('toTimestamp');
		$this->assertSame('Int', $inst->type());
		$this->assertEquals([
			new BindValue('src', 'DateTime'),
		], $inst->getBinds());
		$this->assertSame("<DateTime.toTimestamp>", (string) $inst);
	}



	#[DataProvider('dataFromToTimestampApply')]
	function testToTimestampApply(int $expected, DateTime $src)
	{
		$inst = new DateTimeFunc('toTimestamp');
		$this->assertEquals($expected, $inst->apply([
			new FinalValue($src, 'Calendar'),
		])->unpack());
	}



	function testFromTimestamp()
	{
		$inst = new DateTimeFunc('fromTimestamp');
		$this->assertSame('DateTime', $inst->type());
		$this->assertEquals([
			new BindValue('src', 'Int'),
		], $inst->getBinds());
		$this->assertSame("<DateTime.fromTimestamp>", (string) $inst);
	}



	#[DataProvider('dataFromToTimestampApply')]
	function testFromTimestampApply(int $src, $expected)
	{
		$inst = new DateTimeFunc('fromTimestamp');
		$this->assertEquals($expected, $inst->apply([
			new FinalValue($src, 'Int'),
		])->unpack());
	}



	#[DataProvider('dataFormatApply')]
	function testFormatApply(string $format, DateTime $src, $expected)
	{
		$inst = new DateTimeFunc('format');
		$this->assertEquals($expected, $inst->apply([
			new FinalValue($format, 'Str'),
			new FinalValue($src, 'DateTime'),
		])->unpack());
	}



	/**
	 * @return array<mixed>
	 */
	static function dataFromDateTimeApply(): array
	{
		return [
			[2022, 8, 11, 12, 25, 32,
				DateTime::createFromFormat('Y-n-j G:i:s', "2022-08-11 12:25:32"),
				],
		];
	}



	/**
	 * @return array<mixed>
	 */
	static function dataFromDateApply(): array
	{
		return [
			[2022, 8, 11,
				DateTime::createFromFormat('Y-n-j G:i:s', "2022-08-11 00:00:00"),
				],
		];
	}



	/**
	 * @return array<mixed>
	 */
	static function dataFromToTimestampApply(): array
	{
		return [
			[1660176000,
				DateTime::createFromFormat('Y-n-j G:i:s', "2022-08-11 00:00:00"),
				],
			[946684800,
				DateTime::createFromFormat('Y-n-j G:i:s', "2000-01-01 00:00:00"),
				],
		];
	}



	/**
	 * @return array<mixed>
	 */
	static function dataFormatApply(): array
	{
		return [
			["",
				DateTime::createFromFormat('Y-n-j G:i:s', "2022-08-11 00:00:00"),
				"",
				],
			["Y-m-d H:i:s",
				DateTime::createFromFormat('Y-n-j G:i:s', "2022-08-11 13:45:12"),
				"2022-08-11 13:45:12",
				],
			["Y-m-d",
				DateTime::createFromFormat('Y-n-j G:i:s', "2022-08-11 00:00:00"),
				"2022-08-11",
				],
			["H:i:s - Y. n. j.",
				DateTime::createFromFormat('Y-n-j G:i:s', "2022-08-01 13:45:12"),
				"13:45:12 - 2022. 8. 1.",
				],
		];
	}

}
