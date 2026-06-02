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


class CompilerTest extends TestCase
{

	#[DataProvider('dataScalar')]
	#[DataProvider('dataCompositeFinal')]
	#[DataProvider('dataOperations')]
	#[DataProvider('dataFunctions')]
	#[DataProvider('dataLambdas')]
	#[DataProvider('dataFinalValueWithSymbol')]
	#[DataProvider('dataParametricValue')]
	#[DataProvider('dataLists')]
	#[DataProvider('dataPredicators')]
	#[DataProvider('dataShortLinkBind')]
	#[DataProvider('dataPaths')]
	#[DataProvider('dataDicts')]
	function testCompile(string $code, $expected)
	{
		$this->assertEquals($expected, $this->compile($code));
	}



	function testReturnStructWithBind()
	{
		$this->assertEquals(new FinalValue((object) [
			'a' => new FinalValue(45, 'Int'),
			'b' => new FinalValue('abc', 'Str'),
			'c' => new FinalValue(88, 'Int'),
		], 'Dict'), $this->compile('
{a: a, b: "abc", c: c}')
		->apply(['a' => new FinalValue(45, 'Int'), 'c' => new FinalValue(88, 'Int')])
		);
	}



	function testComposeDict_1()
	{
		$call = $this->compile("
{
	content: content
}
");
		$this->assertSame("Dict", $call->type());
		$this->assertEquals([
			new BindValue('content', '?'),
		], $call->getBinds());
		$this->assertEquals(new FinalValue((object) [
			'content' => new FinalValue('Iem', 'Str'),
			], 'Dict')
			, $call->apply([
				'content' => new FinalValue("Iem", 'Str'),
			]));
	}



	function testComposeDict_2()
	{
		$call = $this->compile("
{
	a: {
		b: content
	}
}
");
		$this->assertSame("Dict", $call->type());
		$this->assertEquals([
			new BindValue('content', '?'),
		], $call->getBinds());

		$this->assertEquals(new FinalValue((object) [
				'a' => new FinalValue((object) [
					'b' => new FinalValue('Iem', 'Str'),
				], 'Dict'),
			], 'Dict')
			, $call->apply([
				'content' => new FinalValue("Iem", 'Str'),
			]));
	}



	function testStringLen()
	{
		$result = HayoEngine::WithDefaultLibraries()
			->evaluate('(Str.len "hi")');
		$this->assertEquals(2, $result);
	}



	function testStringLenAssignAsSymbol()
	{
		$result = HayoEngine::WithDefaultLibraries()
			->evaluate('
a = (Str.len "hi")
a
');
		$this->assertEquals(2, $result);
	}



	function testStringSplit()
	{
		$this->assertEquals(new FinalValue([
			new FinalValue('une', 'Str'),
			new FinalValue(' deux', 'Str'),
			new FinalValue(' trois', 'Str'),
		], 'List<Str>'), $this->compile('
(Str.split "une, deux, trois" ",")')
		);
	}



	function testListFirst()
	{
		$this->assertEquals(new FinalValue("une", 'a'), $this->compile('
xs = (Str.split "une, deux, trois" ",")
-- xs = []
(List.first xs "")')
		);
	}



	function testListFirstDefault()
	{
		$this->assertEquals(new FinalValue('', 'a'), $this->compile('
xs = []
(List.first xs "")')
		);
	}



	function testListExist()
	{
		$this->assertEquals(new FinalValue(true, 'Bool'), $this->compile('
xs = (Str.split "une, deux, trois" ",")
(List.exist xs 0)')
		);
	}



	function testListAt()
	{
		$this->assertEquals(new FinalValue(' deux', 'a'), $this->compile('
xs = (Str.split "une, deux, trois" ",")
(List.at xs 1 "")')
		);
	}



	function testComposeDictStatic()
	{
		$this->assertEquals(new FinalValue((object) [
			'une' => new FinalValue('une', 'a'),
			'deux' => new FinalValue(' deux', 'a'),
			'trois' => new FinalValue(' trois', 'a'),
		], 'Dict'), $this->compile('
xs = (Str.split "une, deux, trois" ",")
{
	une: (List.first xs "")
	deux: (List.at xs 1 "")
	trois: (List.at xs 2 "")
}')
		);
	}



	function testComposeListStatic()
	{
		$this->assertEquals(new FinalValue([
			new FinalValue('une', 'a'),
			new FinalValue(' deux', 'a'),
			new FinalValue(' trois', 'a'),
		], 'List'), $this->compile('
xs = (Str.split "une, deux, trois" ",")
[
	(List.first xs "")
	(List.at xs 1 "")
	(List.at xs 2 "")
]')
		);
	}



	function testComposeTupleStatic()
	{
		$this->assertEquals(new FinalValue([
			new FinalValue('une', 'a'),
			new FinalValue(' deux', 'a'),
			new FinalValue(' trois', 'a'),
		], 'Tuple'), $this->compile('
xs = (Str.split "une, deux, trois" ",")
(
	(List.first xs "")
	(List.at xs 1 "")
	(List.at xs 2 "")
)')
		);
	}



	function testComposeDict()
	{
		$call = $this->compile('
xs = (Str.split src ",")
{
	une: (List.first xs "")
	deux: (List.at xs 1 "")
	trois: (List.at xs 2 "")
}');
//~ dump($call);
		$this->assertSame("Dict", $call->type());
		$this->assertEquals([
			new BindValue('src', 'Str'),
		], $call->getBinds());
		$this->assertEquals(new FinalValue((object) [
			'une' => new FinalValue('Lorem ipsum', 'a'),
			'deux' => new FinalValue(' doler ist', 'a'),
			'trois' => new FinalValue('', 'a'),
			], 'Dict')
			, $call->apply(['src' => new FinalValue("Lorem ipsum, doler ist", 'Str')]));
	}



	/**
	 * The structure references a symbol that is being created. This causes a cycle.
	 * A solution could be to forbid self-referencing.
	 * Forbidding it means I cannot reference a symbol I am part of,
	 * so it won't resolve and will be required from outside.
	 */
	function testSelfReferencingBug()
	{
		$call = $this->compile("
content = {
	foo: content
}
{
	name: \"contact\"
	content: content
}
");
		$this->assertSame("Dict", $call->type());
		$this->assertEquals([
			new BindValue('content', '?'),
		], $call->getBinds());

		$this->assertEquals(new FinalValue((object) [
			'name' => new FinalValue('contact', 'Str'),
			'content' => new FinalValue((object) [
					'foo' => new FinalValue('Iem', 'Str'),
				], 'Dict'),
			], 'Dict')
			, $call->apply([
				'content' => new FinalValue("Iem", 'Str'),
			]));
	}



	function testComposeDictBug1()
	{
		$call = $this->compile("
{
	content: [
		{
			key: \"content\"
			content: content
		}
	]
}
");
		$this->assertSame("Dict", $call->type());
		$this->assertEquals([
			new BindValue('content', '?'),
		], $call->getBinds());

		$this->assertEquals(new FinalValue((object) [
			'content' => new FinalValue([
				new FinalValue((object) [
					'key' => new FinalValue('content', 'Str'),
					'content' => new FinalValue('Iem', 'Str'),
					], 'Dict'),
				], 'List'),
			], 'Dict')
			, $call->apply([
				'content' => new FinalValue("Iem", 'Str'),
			]));
	}



	function testComposeDictBug2()
	{
		$call = $this->compile("
{
	name: \"contact\"
	recipient: \"contact@domain.tld\"
	content: [
		{key: \"youremail\", content: email}
		{key: \"yourname\", content: author}
		{key: \"content\", content: content}
		{key: \"domain\", content: \"domain.tld\"}
	]
}
");
		$this->assertSame("Dict", $call->type());
		$this->assertEquals([
			new BindValue('email', '?'),
			new BindValue('author', '?'),
			new BindValue('content', '?'),
		], $call->getBinds());
		$this->assertEquals(new FinalValue((object) [
			//~ 'author' => new FinalValue("Iem", 'Str'),
			'name' => new FinalValue("contact", 'Str'),
			'recipient' => new FinalValue("contact@domain.tld", 'Str'),
			'content' => new FinalValue([
				new FinalValue((object) [
					'key' => new FinalValue("youremail", 'Str'),
					'content' => new FinalValue("iem@domain.tld", 'Str'),
					], 'Dict'),
				new FinalValue((object) [
					'key' => new FinalValue("yourname", 'Str'),
					'content' => new FinalValue("Iem", 'Str'),
					], 'Dict'),
				new FinalValue((object) [
					'key' => new FinalValue("content", 'Str'),
					'content' => new FinalValue("Lorem ipsum doler ist", 'Str'),
					], 'Dict'),
				new FinalValue((object) [
					'key' => new FinalValue("domain", 'Str'),
					'content' => new FinalValue("domain.tld", 'Str'),
					], 'Dict'),
				], 'List'),
			], 'Dict')
			, $call->apply([
				'author' => new FinalValue("Iem", 'Str'),
				'email' => new FinalValue("iem@domain.tld", 'Str'),
				'content' => new FinalValue("Lorem ipsum doler ist", 'Str'),
			]));
	}



	function testComposeDictBug3()
	{
		$call = $this->compile("
author = \"John\"
{
	recipient: \"contact@domain.tld\"
	content: author
	address: address
}
");
		$this->assertSame("Dict", $call->type());
		$this->assertEquals([
			new BindValue('address', '?'),
		], $call->getBinds());
		$this->assertEquals(new FinalValue((object) [
			'recipient' => new FinalValue("contact@domain.tld", 'Str'),
			'content' => new FinalValue("John", 'Str'),
			'address' => new FinalValue("Iem", 'Str'),
			], 'Dict')
			, $call->apply([
				'address' => new FinalValue("Iem", 'Str'),
			]));
	}



	function testComposeDictBug4()
	{
		$call = $this->compile("
author = address
{
	recipient: \"contact@domain.tld\"
	content: author
	address: address
}
");
		$this->assertSame("Dict", $call->type());
		$this->assertEquals([
			new BindValue('address', '?'),
		], $call->getBinds());
		$this->assertEquals(new FinalValue((object) [
			'recipient' => new FinalValue("contact@domain.tld", 'Str'),
			'content' => new FinalValue("John", 'Str'),
			'address' => new FinalValue("John", 'Str'),
			], 'Dict')
			, $call->apply([
				'address' => new FinalValue("John", 'Str'),
			]));
	}



	function testComposeDictBug5()
	{
		$call = $this->compile("
author = {
	foo: address
}
{
	recipient: \"contact@domain.tld\"
	content: author
	address: address
}
");
		$this->assertSame("Dict", $call->type());
		$this->assertEquals([
			new BindValue('address', '?'),
		], $call->getBinds());
		$this->assertEquals(new FinalValue((object) [
			'recipient' => new FinalValue("contact@domain.tld", 'Str'),
			'content' => new FinalValue((object) [
				'foo' => new FinalValue("John", 'Str'),
			], 'Dict'),
			'address' => new FinalValue("John", 'Str'),
			], 'Dict')
			, $call->apply([
				'address' => new FinalValue("John", 'Str'),
			]));
	}



	function testComposeDictDopredneDohledaniSymbolu()
	{
		$call = $this->compile("
author = {
	foo: address
	boo: name
}
name = \"Hi\"
{
	recipient: \"contact@domain.tld\"
	content: author
	address: address
}
");
		$this->assertSame("Dict", $call->type());
		$this->assertEquals([
			new BindValue('address', '?'),
		], $call->getBinds());
		$this->assertEquals(new FinalValue((object) [
			'recipient' => new FinalValue("contact@domain.tld", 'Str'),
			'content' => new FinalValue((object) [
				'foo' => new FinalValue("John", 'Str'),
				'boo' => new FinalValue("Hi", 'Str'),
			], 'Dict'),
			'address' => new FinalValue("John", 'Str'),
			], 'Dict')
			, $call->apply([
				'address' => new FinalValue("John", 'Str'),
			]));
	}



	/**
	 * Problem where a parameter is nested inside a local variable.
	 * LogicException: Symbol 'author' is not found.
	 */
	function testComposeDictBugX()
	{
		$call = $this->compile("
content = [
	{key: \"yourname\", content: author}
	{key: \"youremail\", content: email}
	{key: \"domain\", content: \"domain.tld\"}
]
{
	name: \"Contact\"
	recipient: \"contact@domain.tld\"
	content: content
}
");
		$this->assertSame("Dict", $call->type());
		$this->assertEquals([
			new BindValue('author', '?'),
			new BindValue('email', '?'),
		], $call->getBinds());
		$this->assertEquals(new FinalValue((object) [
			'name' => new FinalValue('Contact', 'Str'),
			'recipient' => new FinalValue('contact@domain.tld', 'Str'),
			'content' => new FinalValue([
				new FinalValue((object) [
					'key' => new FinalValue('yourname', 'Str'),
					'content' => new FinalValue('Laura', 'Str'),
					], 'Dict'),
				new FinalValue((object) [
					'key' => new FinalValue('youremail', 'Str'),
					'content' => new FinalValue('contact@domain.tld', 'Str'),
					], 'Dict'),
				new FinalValue((object) [
					'key' => new FinalValue('domain', 'Str'),
					'content' => new FinalValue('domain.tld', 'Str'),
					], 'Dict'),
				], 'List'),
			], 'Dict')
			, $call->apply([
				'author' => new FinalValue("Laura", 'Str'),
				'email' => new FinalValue("contact@domain.tld", 'Str'),
			]));
	}



	/**
	 * @param array<mixed> $_args
	 * @param class-string<Throwable> $exception
	 */
	#[DataProvider('dataErrors')]
	function testCompileWithErrors(string $code, array $_args, string $exception, string $message): void
	{
		$this->expectException($exception);
		$this->expectExceptionMessage($message);
		$this->compile($code);
	}



	/**
	 * convenience: errors surfaced through HayoEngine::evaluate
	 * @param array<mixed> $args
	 * @param class-string<Throwable> $exception
	 */
	#[DataProvider('dataErrors')]
	function testEvaluateWithErrors(string $code, array $args, string $exception, string $messageFragment): void
	{
		$this->expectException($exception);
		$this->expectExceptionMessageMatches('/' . preg_quote($messageFragment, '/') . '/i');
		HayoEngine::WithDefaultLibraries()->evaluate($code, $args);
	}



	function testPassPartiallyAppliedScriptAsArgument(): void
	{
		$compiled = $this->compile('1 + a');
		$partial = $this->compile('b + 1');
		$this->expectException(ScriptRuntimeException::class);
		$this->expectExceptionMessageMatches('/partially-applied/i');
		$compiled->apply(['a' => $partial]);
	}



	/**
	 * @return array<array<mixed>>
	 */
	static function dataFunctions(): array
	{
		return [
			['Str.split "" " "',
				new FinalValue([], 'List<Str>'),
				],
			['Str.split " " ""',
				new FinalValue([" "], 'List<Str>'),
				],
			['Str.split " " " "',
				new FinalValue(['', ''], 'List<Str>'),
				],
		];
	}



	/**
	 * @return array<array<mixed>>
	 */
	static function dataScalar(): array
	{
		return [
			['42', new FinalValue(42, 'Int')],
			['0', new FinalValue(0, 'Int')],
			['-1', new FinalValue(-1, 'Int')],

			['0.0', new FinalValue(0.0, 'Real')],
			['0.1', new FinalValue(0.1, 'Real')],
			['3.1415', new FinalValue(3.1415, 'Real')],
			['-3.1415', new FinalValue(-3.1415, 'Real')],

			['"Ahoj"', new FinalValue('Ahoj', 'Str')],
			['"Sinead O\'Connor"', new FinalValue("Sinead O'Connor", 'Str')],
			['"""Sinead O\'Connor"""', new FinalValue("Sinead O'Connor", 'Str')],

			['True', new FinalValue(true, 'Bool')],
			['False', new FinalValue(false, 'Bool')],
			['Null', new FinalValue(null, 'Symbol')],
		];
	}



	/**
	 * @return array<array<mixed>>
	 */
	static function dataCompositeFinal(): array
	{
		return [
			['()', new FinalValue([], 'Tuple')],

			['[]', new FinalValue([], 'List')],
			['[0]', new FinalValue([
				new FinalValue(0, 'Int'),
				], 'List')],
			['[1]', new FinalValue([
				new FinalValue(1, 'Int'),
				], 'List')],
			['[42]', new FinalValue([
				new FinalValue(42, 'Int'),
				], 'List')],

			['{}', new FinalValue((object) [], 'Dict')],
			['{a: 42}', new FinalValue((object) ['a' => new FinalValue(42, 'Int')], 'Dict')],
			['{a: 42, b: 555}', new FinalValue((object) [
				'a' => new FinalValue(42, 'Int'),
				'b' => new FinalValue(555, 'Int'),
				], 'Dict')],

			["a = 555\n{a: 42, b: a}", new FinalValue((object) [
				'a' => new FinalValue(42, 'Int'),
				'b' => new FinalValue(555, 'Int'),
				], 'Dict')],

			["a = 554\n{a: 42, b: a + 1}", new FinalValue((object) [
				'a' => new FinalValue(42, 'Int'),
				'b' => new FinalValue(555, 'Int'),
				], 'Dict')],

			["a = 554\n{a: 42, b: (a + a) + 1}", new FinalValue((object) [
				'a' => new FinalValue(42, 'Int'),
				'b' => new FinalValue(1109, 'Int'),
				], 'Dict')],

			["a = 100 + 454\n{a: 42, b: a + 1}", new FinalValue((object) [
				'a' => new FinalValue(42, 'Int'),
				'b' => new FinalValue(555, 'Int'),
				], 'Dict')],

			// @TODO
		];
	}



	/**
	 * @return array<array<mixed>>
	 */
	static function dataOperations(): array
	{
		return [
			['40 + 2', new FinalValue(42, 'Int')],
			['40 + (1 + 1)', new FinalValue(42, 'Int')],
			['(10 + 30) + (1 + 1)', new FinalValue(42, 'Int')],
			['2 * ((10 + 30) + (1 + 1))', new FinalValue(84, 'Int')],
			['2 * 10 + 30 + 1 + 1', new FinalValue(52, 'Int')],
			['(2 * 10) + 30 + 1 + 1', new FinalValue(52, 'Int')],
			['2 * 10 div 30 + 1 + 1', new FinalValue(2, 'Int')],
			['(2 * 10) div 30 + 1 + 1', new FinalValue(2, 'Int')],
			['((2 * 10) div 30) + 1 + 1', new FinalValue(2, 'Int')],
			['2 * 10 mod 30 + 1 + 1', new FinalValue(22, 'Int')],
			['(2 * 10) mod 30 + 1 + 1', new FinalValue(22, 'Int')],
			['((2 * 10) mod 30) + 1 + 1', new FinalValue(22, 'Int')],

			// @TODO
		];
	}



	/**
	 * @return array<array<mixed>>
	 */
	static function dataFinalValueWithSymbol(): array
	{
		return [
			["a = 2\n40 + a", new FinalValue(42, 'Int')],
			["a = 2\nb = 40\nb + a", new FinalValue(42, 'Int')],
			["a = 2\nb = 20\n(b + b) + a", new FinalValue(42, 'Int')],

			["a = 554\n{a: 42, b: a + 1}", new FinalValue((object) [
					'a' => new FinalValue(42, 'Int'),
					'b' => new FinalValue(555, 'Int'),
				], 'Dict')],

			["a = 5\nb = 13\ncalc = a + 1 * b\ncalc", new FinalValue(18, 'Int')],

			// @TODO
		];
	}



	/**
	 * @return array<array<mixed>>
	 */
	static function dataParametricValue(): array
	{
		return [
			['40 + a', ParametricValue::Expr_(Expr::Bin_(
					new FinalValue(40, 'Int'),
					new MathOperator('+'),
					new BindValue('a', 'Num')
					)
				, 'Num'
				, [ new BindValue('a', 'Num'),
					])],

			["b = 13\ncalc = a + 1 * b\ncalc", ParametricValue::Expr_(Expr::Bin_(
				new BindValue('a', 'Num'),
				new MathOperator('+'),
				new FinalValue(13, 'Int')
				), 'Num', [
					new BindValue('a', 'Num'),
				])],

			['40 + (a + a)', ParametricValue::Expr_(Expr::Bin_(
					new FinalValue(40, 'Int'),
					new MathOperator('+'),
					Expr::Bin_(
						new BindValue('a', 'Num'),
						new MathOperator('+'),
						new BindValue('a', 'Num')
						)
					)
				, 'Num'
				, [ new BindValue('a', 'Num'),
				])],

			['40 + (a + b)', ParametricValue::Expr_(Expr::Bin_(
					new FinalValue(40, 'Int'),
					new MathOperator('+'),
					Expr::Bin_(
						new BindValue('a', 'Num'),
						new MathOperator('+'),
						new BindValue('b', 'Num')
						)
					)
				, 'Num'
				, [ new BindValue('a', 'Num'),
					new BindValue('b', 'Num'),
					])],

			// @TODO
		];
	}



	/**
	 * @return array<array<mixed>>
	 */
	static function dataLambdas(): array
	{
		return [
			[""
			. "a = 5\n"
			. "b = 13\n"
			. "inc = a -> a + 1 * b\n"
			. "inc 29",
				new FinalValue(42, 'Int'),
				],

			["inc = a -> a + 1\n"
			."inc 41",
				new FinalValue(42, 'Int'),
				],

			[""
			."a = 5\n"
			."inc = a -> a + b\n"
			."inc 41",
				ParametricValue::Expr_(Expr::Bin_(
					new FinalValue(41, 'Int'),
					new MathOperator('+'),
					new BindValue("b", 'Num')
				), 'Num', [
					new BindValue("b", 'Num'),
				]),
				],

/*			["id = () -> 42\nid ()",
				new FinalValue(42, 'Int'),
				],
				//*/

			// @TODO
		];
	}



	/**
	 * @return array<array<mixed>>
	 */
	static function dataShortLinkBind(): array
	{
		return [
			['a', ParametricValue::ShortLinkBind(new BindValue('a', '?')
				, '?'
				, [ new BindValue('a', '?'),
					])],
			["b = 1\na", ParametricValue::ShortLinkBind(new BindValue('a', '?')
				, '?'
				, [ new BindValue('a', '?'),
					])],
			["b = c\na", ParametricValue::ShortLinkBind(new BindValue('a', '?')
				, '?'
				, [ new BindValue('a', '?'),
					])],
		];
	}



	/**
	 * @return array<array<mixed>>
	 */
	static function dataLists(): array
	{
		return [
			[""
			. "a = 5\n"
			. "b = 13\n"
			. "xs = [1, 2, 3, 4]\n"
			. "xs",
				new FinalValue([
					new FinalValue(1, 'Int'),
					new FinalValue(2, 'Int'),
					new FinalValue(3, 'Int'),
					new FinalValue(4, 'Int'),
					], 'List'),
				],

			// List.len
			[""
			. "xs = [1, 2, 3, 4]\n"
			. "List.len xs",
				new FinalValue(4, 'Int'),
				],
			[""
			. "xs = []\n"
			. "List.len xs",
				new FinalValue(0, 'Int'),
				],

			// List.first
			[""
			. "xs = [1, 2, 3, 4]\n"
			. "List.first xs Null",
				new FinalValue(1, 'a'),
				],
			[""
			. "xs = []\n"
			. "List.first xs Null",
				new FinalValue(Null, 'a'),
				],
			[""
			. "xs = []\n"
			. "List.first xs 0",
				new FinalValue(0, 'a'),
				],

			// List.at
			[""
			. "xs = []\n"
			. "List.at xs 0 0",
				new FinalValue(0, 'a'),
				],
			[""
			. "xs = []\n"
			. "List.at xs 0 Null",
				new FinalValue(Null, 'a'),
				],
			[""
			. "xs = []\n"
			. "List.at xs 999 Null",
				new FinalValue(Null, 'a'),
				],
			[""
			. "xs = [1, 2, 3, 4]\n"
			. "List.at xs 999 Null",
				new FinalValue(Null, 'a'),
				],
			[""
			. "xs = [1, 2, 3, 4]\n"
			. "List.at xs 0 Null",
				new FinalValue(1, 'a'),
				],
			[""
			. "xs = [1, 2, 3, 4]\n"
			. "List.at xs 3 Null",
				new FinalValue(4, 'a'),
				],
			[""
			. "xs = [1, 2, 3, 4]\n"
			. "List.at xs 4 Null",
				new FinalValue(Null, 'a'),
				],

			// List.exists
			[""
			. "xs = [1, 2, 3, 4]\n"
			. "List.exist xs 0",
				new FinalValue(True, 'Bool'),
				],
			[""
			. "xs = [1, 2, 3, 4]\n"
			. "List.exist xs 3",
				new FinalValue(True, 'Bool'),
				],
			[""
			. "xs = [1, 2, 3, 4]\n"
			. "List.exist xs 4",
				new FinalValue(False, 'Bool'),
				],
			[""
			. "xs = [1, 2, 3, 4]\n"
			. "List.exist xs -4",
				new FinalValue(False, 'Bool'),
				],

			// `List.slice`
			[""
			. "xs = [1, 2, 3, 4]\n"
			. "List.slice xs 1 2",
				new FinalValue([
					new FinalValue(2, 'Int'),
					new FinalValue(3, 'Int'),
					], 'List<a>'),
				],
			[""
			. "xs = [1, 2, 3, 4]\n"
			. "List.slice xs 10 2",
				new FinalValue([], 'List<a>'),
				],
			[""
			. "xs = [1, 2, 3, 4]\n"
			. "List.slice xs 0 2",
				new FinalValue([
					new FinalValue(1, 'Int'),
					new FinalValue(2, 'Int'),
					], 'List<a>'),
				],
			[""
			. "xs = [1, 2, 3, 4]\n"
			. "List.slice xs 2 0",
				new FinalValue([], 'List<a>'),
				],
			[""
			. "xs = [1, 2, 3, 4]\n"
			. "List.slice xs 2 9999",
				new FinalValue([
					new FinalValue(3, 'Int'),
					new FinalValue(4, 'Int'),
					], 'List<a>'),
				],

			// `List.concat`
			[""
			. "xs = [1, 2, 3, 4]\n"
			. "List.concat xs xs",
				new FinalValue([
					new FinalValue(1, 'Int'),
					new FinalValue(2, 'Int'),
					new FinalValue(3, 'Int'),
					new FinalValue(4, 'Int'),
					new FinalValue(1, 'Int'),
					new FinalValue(2, 'Int'),
					new FinalValue(3, 'Int'),
					new FinalValue(4, 'Int'),
					], 'List<a>'),
				],

			// List.map
			[""
			. "xs = [1, 2, 3, 4]\n"
			. "List.map xs (x -> x * x)",
				new FinalValue([
					new FinalValue(1, 'Int'),
					new FinalValue(4, 'Int'),
					new FinalValue(9, 'Int'),
					new FinalValue(16, 'Int'),
					], 'List'),
				],

			// List.filter
			[""
			. "xs = [1, 2, 3, 4]\n"
			. "List.filter xs (x -> x > 2)",
				new FinalValue([
					new FinalValue(3, 'Int'),
					new FinalValue(4, 'Int'),
					], 'List'),
				],

			// List.fold
			[""
			. "xs = [1, 2, 3, 4]\n"
			. "List.fold xs 0 (prev x -> prev + x)",
				new FinalValue(10, 'Int'),
				],

			// `List.split`
			[""
			. "xs = [1, 2, 0, 3, 4, 0, 5, 8]\n"
			. "List.split xs (x -> x == 0) 0",
				new FinalValue([], 'List'),
				],
			[""
			. "xs = [1, 2, 0, 3, 4, 0, 5, 8]\n"
			. "List.split xs (x -> x == 0) 1",
				new FinalValue([
					new FinalValue([
						new FinalValue(1, 'Int'),
						new FinalValue(2, 'Int'),
						new FinalValue(0, 'Int'),
						new FinalValue(3, 'Int'),
						new FinalValue(4, 'Int'),
						new FinalValue(0, 'Int'),
						new FinalValue(5, 'Int'),
						new FinalValue(8, 'Int'),
						], 'List'),
					], 'List'),
				],
			[""
			. "xs = [1, 2, 0, 3, 4, 0, 5, 8]\n"
			. "List.split xs (x -> x == 0) 2",
				new FinalValue([
					new FinalValue([
						new FinalValue(1, 'Int'),
						new FinalValue(2, 'Int'),
						], 'List'),
					new FinalValue([
						new FinalValue(3, 'Int'),
						new FinalValue(4, 'Int'),
						new FinalValue(0, 'Int'),
						new FinalValue(5, 'Int'),
						new FinalValue(8, 'Int'),
						], 'List'),
					], 'List'),
				],
			[""
			. "xs = [1, 2, 0, 3, 4, 0, 5, 8]\n"
			. "List.split xs (x -> x == 0) 5",
				new FinalValue([
					new FinalValue([
						new FinalValue(1, 'Int'),
						new FinalValue(2, 'Int'),
						], 'List'),
					new FinalValue([
						new FinalValue(3, 'Int'),
						new FinalValue(4, 'Int'),
						], 'List'),
					new FinalValue([
						new FinalValue(5, 'Int'),
						new FinalValue(8, 'Int'),
						], 'List'),
					], 'List'),
				],

			// `List.indexOf`
			[""
			. "xs = [1, 2, 0, 3, 4, 0, 5, 8]\n"
			. "List.indexOf xs (x -> x == 9) 0",
				new FinalValue(-1, 'Int'),
				],
			[""
			. "xs = [1, 2, 0, 3, 4, 0, 5, 8]\n"
			. "List.indexOf xs (x -> x == 1) 0",
				new FinalValue(0, 'Int'),
				],
			[""
			. "xs = [1, 2, 0, 3, 4, 0, 5, 8]\n"
			. "List.indexOf xs (x -> x == 2) 0",
				new FinalValue(1, 'Int'),
				],
			[""
			. "xs = [1, 2, 0, 3, 4, 0, 5, 8]\n"
			. "List.indexOf xs (x -> x == 0) 0",
				new FinalValue(2, 'Int'),
				],
			[""
			. "xs = [1, 2, 0, 3, 4, 0, 5, 8]\n"
			. "List.indexOf xs (x -> x == 4) 0",
				new FinalValue(4, 'Int'),
				],
			[""
			. "xs = [1, 2, 0, 3, 4, 0, 5, 8]\n"
			. "List.indexOf xs (x -> x == 8) 0",
				new FinalValue(7, 'Int'),
				],
			[""
			. "xs = [1, 2, 0, 3, 4, 0, 5, 8]\n"
			. "List.indexOf xs (x -> x == 0) 2",
				new FinalValue(2, 'Int'),
				],
			[""
			. "xs = [1, 2, 0, 3, 4, 0, 5, 8]\n"
			. "List.indexOf xs (x -> x == 0) 3",
				new FinalValue(5, 'Int'),
				],
		];
	}



	/**
	 * @return array<array<mixed>>
	 */
	static function dataPredicators(): array
	{
		return [
			["True",
				new FinalValue(true, 'Bool'),
				],

			["1 == 2",
				new FinalValue(False, 'Bool'),
				],

			["1 == 2 || 2 == 3",
				new FinalValue(False, 'Bool'),
				],

			["1 == 2 or 2 == 3",
				new FinalValue(False, 'Bool'),
				],

			["6 == 6 && (2 + 1) == 3",
				new FinalValue(True, 'Bool'),
				],

			["6 == a && (2 + 1) == 3",
				ParametricValue::Expr_(Expr::Bin_(Expr::Bin_(
						new FinalValue(6, 'Int'),
						new PredicateFunction('=='),
						new BindValue('a', 'a')
						),
					new PredicateFunction('&&'),
					new FinalValue(True, 'Bool')
					), 'Bool', [
						new BindValue('a', 'a'),
					]),
				],
		];
	}



	/**
	 * @return array<array<mixed>>
	 */
	static function dataPaths(): array
	{
		return [
			["x = { foo: { doo: 41 } }\n1 + x.foo.doo",
				new FinalValue(42, 'Int'),
				],
			["x = { foo: { doo: 41 } }\ny = x.foo.doo\n1 + y",
				new FinalValue(42, 'Int'),
				],
			["x = { foo: { doo: 41 } }\ny = x.foo\n1 + y.doo",
				new FinalValue(42, 'Int'),
				],
		];
	}



	/**
	 * @return array<array<mixed>>
	 */
	static function dataDicts(): array
	{
		return [
			// Dict.keys
			["x = { foo: { doo: 41 }, groooo: 43 }\n"
			."Dict.keys x",
				new FinalValue([
					new FinalValue('foo', 'Str'),
					new FinalValue('groooo', 'Str'),
					], 'List<Str>'),
				],

			["x = { foo: { doo: 41 }, groooo: 43 }\n"
			."xs = x.foo\n"
			."Dict.keys xs",
				new FinalValue([
					new FinalValue('doo', 'Str'),
					], 'List<Str>'),
				],

			["x = {  }\n"
			."Dict.keys x",
				new FinalValue([], 'List<Str>'),
				],

			// Dict.values
			["x = {  }\n"
			."Dict.values x",
				new FinalValue([], 'List<?>'),
				],

			["x = { foo: { doo: 41 }, groooo: 43 }\n"
			."Dict.values x",
				new FinalValue([
					new FinalValue((object) [
						'doo' => 41,
						], '?'),
					new FinalValue(43, '?'),
					], 'List<?>'),
				],

			["x = { foo: { doo: 41 }, groooo: 43 }\n"
			."Dict.values x.foo",
				new FinalValue([
					new FinalValue(41, '?'),
					], 'List<?>'),
				],

			// Dict.has
			["xs = {  }\n"
			.'Dict.has xs "foo"',
				new FinalValue(False, 'Bool'),
				],

			["xs = { foo: { doo: 41 }, groooo: 43 }\n"
			.'Dict.has xs "noo"',
				new FinalValue(False, 'Bool'),
				],

			["xs = { foo: { doo: 41 }, groooo: 43 }\n"
			.'Dict.has xs "foo"',
				new FinalValue(True, 'Bool'),
				],

			["xs = { foo: { doo: 41 }, groooo: 43 }\n"
			.'Dict.has xs.foo "doo"',
				new FinalValue(True, 'Bool'),
				],

			["xs = { foo: { doo: False }, groooo: 43 }\n"
			.'Dict.has xs.foo "doo"',
				new FinalValue(True, 'Bool'),
				],

			// Dict.get
			["xs = {  }\n"
			.'Dict.get xs "foo" "noop"',
				new FinalValue("noop", 'Str'),
				],
			["xs = { foo: { doo: 41 }, groooo: 43 }\n"
			.'Dict.get xs "foo" ""',
				new FinalValue((object) [
					'doo' => 41,
					], 'a'),
				],

			// Dict.merge
			["xs = {  }\n"
			.'Dict.merge xs {}',
				new FinalValue((object) [], 'Dict'),
				],
			["xs = { foo: { doo: 41 }, groooo: 43 }\n"
			.'Dict.merge xs {}',
				new FinalValue((object) [
					'foo' => (object) [
						'doo' => 41,
						],
					'groooo' => 43,
					], 'Dict'),
				],
			["xs = { foo: { doo: 41 }, groooo: 43 }\n"
			.'Dict.merge {} xs',
				new FinalValue((object) [
					'foo' => (object) [
						'doo' => 41,
						],
					'groooo' => 43,
					], 'Dict'),
				],
			["xs = { foo: { doo: 41 }, groooo: 43 }\n"
			.'Dict.merge xs {broo: 44}',
				new FinalValue((object) [
					'foo' => (object) [
						'doo' => 41,
						],
					'groooo' => 43,
					'broo' => 44,
					], 'Dict'),
				],

			// Dict.combine


		];
	}



	/**
	 * @return array<array<mixed>>
	 */
	static function dataErrors(): array
	{
		return [
			'missing List.noth' => ['List.noth (a b -> a + b) xs',
				['xs' => new FinalValue([], 'List<a>')],
				SymbolNotFound::class, 'Unable to find symbols: List.noth.'],

			// Unknown symbol
			'unknown list function' => ['List.nope xs',
				['xs' => new FinalValue([], 'List<a>')],
				SymbolNotFound::class, 'List.nope'],
			'unknown str function' => ['Str.nope src',
				['src' => new FinalValue("abc", 'str')],
				SymbolNotFound::class, 'Str.nope'],

			// Division by zero: the compiler catches DivisionByZeroError from partial evaluation
			// and re-throws it as CompileException so callers never see a raw PHP error.
			'int div zero (constant)' => ['10 div 0',
				[],
				CompileException::class, 'zero'],
			'int mod zero (constant)' => ['10 mod 0',
				[],
				CompileException::class, 'zero'],
			'float div zero (constant)' => ['3.14 div 0.0',
				[],
				CompileException::class, 'zero'],
			// mod accepts only Int — passing a Real fires a type error before the zero-check
			'float mod zero (constant)' => ['10.0 mod 0',
				[],
				CompileException::class, 'Expected int'],

			// Wrong argument type passed to a built-in — caught at compile time
			// when all operands are constants and the call is partially evaluated.
			'Str.len on integer literal' => ['Str.len 42',
				[],
				CompileException::class, 'Expected string'],

			// Syntax / parse errors
			'incomplete if' => ['if a then',
				[],
				CompileException::class, 'Required closing bracked: EOF.'],
			'lambda with tuple args' => [
				'List.sort xs ((a b) -> a + b)',
				['xs' => new FinalValue([], 'List<a>')],
				CompileException::class, 'Lambda arguments must be simple names',
				],

			// Compile-time type mismatch: both operands are known, `+` requires Num
			'type mismatch at compile' => [
				"b = \"Hi\"\n1 + b",
				[],
				CompileException::class, 'Invalid arguments of Math.+:',
				],

			// Reassignment is not allowed
			'reassign symbol' => ["xs = 1\nxs = 2\nxs", [], CompileException::class, "Symbol 'xs' is already defined"],

			// AND/OR/NOT require Bool operands
			'int OR int' => [
				'1 OR 2 OR 3',
				[],
				CompileException::class, 'Expected bool',
				],

			'int || int' => [
				'1 || 2 || 3',
				[],
				CompileException::class, 'Expected bool',
				],

			'bool AND int' => [
				'(1 == 1) && 1',
				[],
				CompileException::class, 'Expected bool',
				],

			'str AND int' => [
				'"hello" AND 1',
				[],
				CompileException::class, 'Expected bool',
				],

			'str AND zero' => [
				'"hello" AND 0',
				[],
				CompileException::class, 'Expected bool',
				],

			'zero OR zero' => [
				'0 OR 0',
				[],
				CompileException::class, 'Expected bool',
				],

			// Phase 2: type inference catches wrong-typed arguments
			// List.at expects src:List<a> as first arg, but Int (2) is passed
			'List.at with wrong arg types' => [
				'List.at 2 ["une", a, "trois"]',
				[],
				CompileException::class, "Cannot unify",
				],

			// Unsupported lambda forms
			'zero-arg lambda' => [
				"f = () -> 42\nf",
				[],
				CompileException::class, 'Zero-argument lambdas are not supported',
				],
			'curried lambda' => [
				"f = x -> y -> x + y\nf 1 2",
				[],
				CompileException::class, 'Curried lambdas',
				],

		];
	}



	private function compile($src)
	{
		return (new Compiler([
			'predicate' => new PredicatesProvider(),
			'Bool' => new BoolProvider(),
			'Math' => new MathsProvider(),
			'Str' => new StringsProvider(),
			'List' => new ListsProvider(),
			'Dict' => new DictsProvider(),
			]))
			->compile($src);
	}

}
