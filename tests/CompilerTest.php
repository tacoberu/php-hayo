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

	function _testDevelopX()
	{
		$code = "a = 100 + 454\n{a: 42, b: a + 1}";
		$code = '
xs = (strings.split "," "une, deux, trois")
(list.exist 10 xs)';
		$code = "a = b\nb = c\na + 1";
		$code = "1 IN xs";

		dump($this->compile($code));
		dump($this->compile($code)->apply(['xs' => new FinalVal([1], "List")]));
	}



	#[DataProvider('dataScalar')]
	#[DataProvider('dataCompositeFinal')]
	#[DataProvider('dataOperations')]
	#[DataProvider('dataFunctions')]
	#[DataProvider('dataLambdas')]
	#[DataProvider('dataFinalValWithSymbol')]
	#[DataProvider('dataVariadicVal')]
	#[DataProvider('dataPredicators')]
	#[DataProvider('dataShortLinkBind')]
	function testCompile(string $code, $expected)
	{
		$this->assertEquals($expected, $this->compile($code));
	}



	function testReturnStructWithBind()
	{
		$this->assertEquals(new FinalVal((object) [
			'a' => new FinalVal(45, 'Int'),
			'b' => new FinalVal('abc', 'Str'),
			'c' => new FinalVal(88, 'Int'),
		], 'Dict'), $this->compile('
{a: a, b: "abc", c: c}')
		->apply(['a' => new FinalVal(45, 'Int'), 'c' => new FinalVal(88, 'Int')])
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
			new BindVal('content', '?'),
		], $call->getBinds());
		$this->assertEquals(new FinalVal((object) [
			'content' => new FinalVal('Iem', 'Str'),
			], 'Dict')
			, $call->apply([
				'content' => new FinalVal("Iem", 'Str'),
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
			new BindVal('content', '?'),
		], $call->getBinds());

		$this->assertEquals(new FinalVal((object) [
				'a' => new FinalVal((object) [
					'b' => new FinalVal('Iem', 'Str'),
				], 'Dict'),
			], 'Dict')
			, $call->apply([
				'content' => new FinalVal("Iem", 'Str'),
			]));
	}



	function testStringLen()
	{
		$result = HayoEngine::WithDefaultLibraries()
			->evaluate('(strings.len "hi")');
		$this->assertEquals(2, $result);
	}



	function testStringLenAssignAsSymbol()
	{
		$result = HayoEngine::WithDefaultLibraries()
			->evaluate('
a = (strings.len "hi")
a
');
		$this->assertEquals(2, $result);
	}



	function testStringSplit()
	{
		$this->assertEquals(new FinalVal([
			new FinalVal('une', 'Str'),
			new FinalVal(' deux', 'Str'),
			new FinalVal(' trois', 'Str'),
		], 'List'), $this->compile('
(strings.split "," "une, deux, trois")')
		);
	}



	function testListFirst()
	{
		$this->assertEquals(new FinalVal("une", 'a'), $this->compile('
xs = (strings.split "," "une, deux, trois")
-- xs = []
(list.first xs "")')
		);
	}



	function testListFirstDefault()
	{
		$this->assertEquals(new FinalVal('', 'a'), $this->compile('
xs = []
(list.first xs "")')
		);
	}



	function testListExist()
	{
		$this->assertEquals(new FinalVal(true, 'Bool'), $this->compile('
xs = (strings.split "," "une, deux, trois")
(list.exist 0 xs)')
		);
	}



	function testListAt()
	{
		$this->assertEquals(new FinalVal(' deux', 'a'), $this->compile('
xs = (strings.split "," "une, deux, trois")
(list.at 1 xs "")')
		);
	}



	function testComposeDictStatic()
	{
		$this->assertEquals(new FinalVal((object) [
			'une' => new FinalVal('une', 'a'),
			'deux' => new FinalVal(' deux', 'a'),
			'trois' => new FinalVal(' trois', 'a'),
		], 'Dict'), $this->compile('
xs = (strings.split "," "une, deux, trois")
{
	une: (list.first xs "")
	deux: (list.at 1 xs "")
	trois: (list.at 2 xs "")
}')
		);
	}



	function testComposeListStatic()
	{
		$this->assertEquals(new FinalVal([
			new FinalVal('une', 'a'),
			new FinalVal(' deux', 'a'),
			new FinalVal(' trois', 'a'),
		], 'List'), $this->compile('
xs = (strings.split "," "une, deux, trois")
[
	(list.first xs "")
	(list.at 1 xs "")
	(list.at 2 xs "")
]')
		);
	}



	function testComposeTupleStatic()
	{
		$this->assertEquals(new FinalVal([
			new FinalVal('une', 'a'),
			new FinalVal(' deux', 'a'),
			new FinalVal(' trois', 'a'),
		], 'Tuple'), $this->compile('
xs = (strings.split "," "une, deux, trois")
(
	(list.first xs "")
	(list.at 1 xs "")
	(list.at 2 xs "")
)')
		);
	}



	function testComposeDict()
	{
		$call = $this->compile('
xs = (strings.split "," src)
{
	une: (list.first xs "")
	deux: (list.at 1 xs "")
	trois: (list.at 2 xs "")
}');
//~ dump($call);
		$this->assertSame("Dict", $call->type());
		$this->assertEquals([
			new BindVal('src', '?'),
		], $call->getBinds());
		$this->assertEquals(new FinalVal((object) [
			'une' => new FinalVal('Lorem ipsum', 'a'),
			'deux' => new FinalVal(' doler ist', 'a'),
			'trois' => new FinalVal('', 'a'),
			], 'Dict')
			, $call->apply(['src' => new FinalVal("Lorem ipsum, doler ist", 'Str')]));
	}



	/**
	 * Struktura odkazuje na symbol, který bude vytvořen. Zacyklí se to.
	 * Řešení by mohlo být, že zakážu vytvářet odkazy na sebe sama.
	 * Zakázání se projeví tím, že nemohu odkazovat na symbol jehož jsem součástí
	 * a tudíž se to neresolvne a tudíž se ten symbol bude požadovat zvenčí.
	 */
	function _____testSelfReferencingBug()
	{
		// @TODO
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
		die("\n------\n" . __file__ . ':' . __line__ . "\n");
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
			new BindVal('content', '?'),
		], $call->getBinds());

		//~ dump($call->apply([
				//~ 'content' => new FinalVal("Iem", 'Str'),
			//~ ]));
		$this->assertEquals(new FinalVal((object) [
			'content' => new FinalVal([
				new FinalVal((object) [
					'key' => new FinalVal('content', 'Str'),
					'content' => new FinalVal('Iem', 'Str'),
					], 'Dict'),
				], 'List'),
			], 'Dict')
			, $call->apply([
				'content' => new FinalVal("Iem", 'Str'),
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
		/* dump($call->apply([
				'author' => new FinalVal("Iem", 'Str'),
				'email' => new FinalVal("iem@domain.tld", 'Str'),
				'content' => new FinalVal([], 'List'),
			])); //*/
		$this->assertEquals([
			new BindVal('email', '?'),
			new BindVal('author', '?'),
			new BindVal('content', '?'),
		], $call->getBinds());
		$this->assertEquals(new FinalVal((object) [
			//~ 'author' => new FinalVal("Iem", 'Str'),
			'name' => new FinalVal("contact", 'Str'),
			'recipient' => new FinalVal("contact@domain.tld", 'Str'),
			'content' => new FinalVal([
				new FinalVal((object) [
					'key' => new FinalVal("youremail", 'Str'),
					'content' => new FinalVal("iem@domain.tld", 'Str'),
					], 'Dict'),
				new FinalVal((object) [
					'key' => new FinalVal("yourname", 'Str'),
					'content' => new FinalVal("Iem", 'Str'),
					], 'Dict'),
				new FinalVal((object) [
					'key' => new FinalVal("content", 'Str'),
					'content' => new FinalVal("Lorem ipsum doler ist", 'Str'),
					], 'Dict'),
				new FinalVal((object) [
					'key' => new FinalVal("domain", 'Str'),
					'content' => new FinalVal("domain.tld", 'Str'),
					], 'Dict'),
				], 'List'),
			], 'Dict')
			, $call->apply([
				'author' => new FinalVal("Iem", 'Str'),
				'email' => new FinalVal("iem@domain.tld", 'Str'),
				'content' => new FinalVal("Lorem ipsum doler ist", 'Str'),
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
			new BindVal('address', '?'),
		], $call->getBinds());
		$this->assertEquals(new FinalVal((object) [
			'recipient' => new FinalVal("contact@domain.tld", 'Str'),
			'content' => new FinalVal("John", 'Str'),
			'address' => new FinalVal("Iem", 'Str'),
			], 'Dict')
			, $call->apply([
				'address' => new FinalVal("Iem", 'Str'),
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
			new BindVal('address', '?'),
		], $call->getBinds());
		$this->assertEquals(new FinalVal((object) [
			'recipient' => new FinalVal("contact@domain.tld", 'Str'),
			'content' => new FinalVal("John", 'Str'),
			'address' => new FinalVal("John", 'Str'),
			], 'Dict')
			, $call->apply([
				'address' => new FinalVal("John", 'Str'),
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
			new BindVal('address', '?'),
		], $call->getBinds());
		$this->assertEquals(new FinalVal((object) [
			'recipient' => new FinalVal("contact@domain.tld", 'Str'),
			'content' => new FinalVal((object) [
				'foo' => new FinalVal("John", 'Str'),
			], 'Dict'),
			'address' => new FinalVal("John", 'Str'),
			], 'Dict')
			, $call->apply([
				'address' => new FinalVal("John", 'Str'),
			]));
	}



	function _____testComposeDictDopredneDohledaniSymbolu()
	{
		// @FIXME Dopředné dohledání symbolů.
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
			new BindVal('address', '?'),
		], $call->getBinds());
		$this->assertEquals(new FinalVal((object) [
			'recipient' => new FinalVal("contact@domain.tld", 'Str'),
			'content' => new FinalVal((object) [
				'foo' => new FinalVal("John", 'Str'),
				'boo' => new FinalVal("Hi", 'Str'),
			], 'Dict'),
			'address' => new FinalVal("John", 'Str'),
			], 'Dict')
			, $call->apply([
				'address' => new FinalVal("John", 'Str'),
			]));
	}



	/**
	 * Problém, kdy parametr je zanořený v lokální proměnné.
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
			new BindVal('author', '?'),
			new BindVal('email', '?'),
		], $call->getBinds());
		$this->assertEquals(new FinalVal((object) [
			'name' => new FinalVal('Contact', 'Str'),
			'recipient' => new FinalVal('contact@domain.tld', 'Str'),
			'content' => new FinalVal([
				new FinalVal((object) [
					'key' => new FinalVal('yourname', 'Str'),
					'content' => new FinalVal('Laura', 'Str'),
					], 'Dict'),
				new FinalVal((object) [
					'key' => new FinalVal('youremail', 'Str'),
					'content' => new FinalVal('contact@domain.tld', 'Str'),
					], 'Dict'),
				new FinalVal((object) [
					'key' => new FinalVal('domain', 'Str'),
					'content' => new FinalVal('domain.tld', 'Str'),
					], 'Dict'),
				], 'List'),
			], 'Dict')
			, $call->apply([
				'author' => new FinalVal("Laura", 'Str'),
				'email' => new FinalVal("contact@domain.tld", 'Str'),
			]));
	}



	/**
	 * @return array<array<mixed>>
	 */
	static function dataFunctions(): array
	{
		return [
			['strings.split " " ""',
				new FinalVal([], 'List'),
				],
			['strings.split " " " "',
				new FinalVal(['', ''], 'List'),
				],
		];
	}



	/**
	 * @return array<array<mixed>>
	 */
	static function dataScalar(): array
	{
		return [
			['42', new FinalVal(42, 'Int')],
			['0', new FinalVal(0, 'Int')],
			['-1', new FinalVal(-1, 'Int')],

			['0.0', new FinalVal(0.0, 'Real')],
			['0.1', new FinalVal(0.1, 'Real')],
			['3.1415', new FinalVal(3.1415, 'Real')],
			['-3.1415', new FinalVal(-3.1415, 'Real')],

			['"Ahoj"', new FinalVal('Ahoj', 'Str')],
			['"Sinead O\'Connor"', new FinalVal("Sinead O'Connor", 'Str')],
			['"""Sinead O\'Connor"""', new FinalVal("Sinead O'Connor", 'Str')],

			['True', new FinalVal(true, 'Symbol')],
			['False', new FinalVal(false, 'Symbol')],
			['Null', new FinalVal(null, 'Symbol')],
		];
	}



	/**
	 * @return array<array<mixed>>
	 */
	static function dataCompositeFinal(): array
	{
		return [
			['()', new FinalVal([], 'Tuple')],

			['[]', new FinalVal([], 'List')],
			['[0]', new FinalVal([
				new FinalVal(0, 'Int'),
				], 'List')],
			['[1]', new FinalVal([
				new FinalVal(1, 'Int'),
				], 'List')],
			['[42]', new FinalVal([
				new FinalVal(42, 'Int'),
				], 'List')],

			['{}', new FinalVal((object) [], 'Dict')],
			['{a: 42}', new FinalVal((object) ['a' => new FinalVal(42, 'Int')], 'Dict')],
			['{a: 42, b: 555}', new FinalVal((object) [
				'a' => new FinalVal(42, 'Int'),
				'b' => new FinalVal(555, 'Int'),
				], 'Dict')],

			["a = 555\n{a: 42, b: a}", new FinalVal((object) [
				'a' => new FinalVal(42, 'Int'),
				'b' => new FinalVal(555, 'Int'),
				], 'Dict')],

			["a = 554\n{a: 42, b: a + 1}", new FinalVal((object) [
				'a' => new FinalVal(42, 'Int'),
				'b' => new FinalVal(555, 'Int'),
				], 'Dict')],

			["a = 554\n{a: 42, b: (a + a) + 1}", new FinalVal((object) [
				'a' => new FinalVal(42, 'Int'),
				'b' => new FinalVal(1109, 'Int'),
				], 'Dict')],

			["a = 100 + 454\n{a: 42, b: a + 1}", new FinalVal((object) [
				'a' => new FinalVal(42, 'Int'),
				'b' => new FinalVal(555, 'Int'),
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
			['40 + 2', new FinalVal(42, 'Int')],
			['40 + (1 + 1)', new FinalVal(42, 'Int')],
			['(10 + 30) + (1 + 1)', new FinalVal(42, 'Int')],
			['2 * ((10 + 30) + (1 + 1))', new FinalVal(84, 'Int')],
			['2 * 10 + 30 + 1 + 1', new FinalVal(52, 'Int')],
			['(2 * 10) + 30 + 1 + 1', new FinalVal(52, 'Int')],
			['2 * 10 div 30 + 1 + 1', new FinalVal(2, 'Int')],
			['(2 * 10) div 30 + 1 + 1', new FinalVal(2, 'Int')],
			['((2 * 10) div 30) + 1 + 1', new FinalVal(2, 'Int')],
			['2 * 10 mod 30 + 1 + 1', new FinalVal(22, 'Int')],
			['(2 * 10) mod 30 + 1 + 1', new FinalVal(22, 'Int')],
			['((2 * 10) mod 30) + 1 + 1', new FinalVal(22, 'Int')],

			// @TODO
		];
	}



	/**
	 * @return array<array<mixed>>
	 */
	static function dataFinalValWithSymbol(): array
	{
		return [
			["a = 2\n40 + a", new FinalVal(42, 'Int')],
			["a = 2\nb = 40\nb + a", new FinalVal(42, 'Int')],
			["a = 2\nb = 20\n(b + b) + a", new FinalVal(42, 'Int')],

			["a = 554\n{a: 42, b: a + 1}", new FinalVal((object) [
					'a' => new FinalVal(42, 'Int'),
					'b' => new FinalVal(555, 'Int'),
				], 'Dict')],

			["a = 5\nb = 13\ncalc = a + 1 * b\ncalc", new FinalVal(18, 'Int')],
			// @TODO
		];
	}



	/**
	 * @return array<array<mixed>>
	 */
	static function dataVariadicVal(): array
	{
		return [
			['40 + a', VariadicVal::Expr_(Expr::Bin_(
					new FinalVal(40, 'Int'),
					new MathOperator('+'),
					new BindVal('a', '?')
					)
				, '?'
				, [ new BindVal('a', '?'),
					])],

			["b = 13\ncalc = a + 1 * b\ncalc", VariadicVal::Expr_(Expr::Bin_(
				new BindVal('a', '?'),
				new MathOperator('+'),
				new FinalVal(13, 'Int')
				), '?', [
					new BindVal('a', '?'),
				])],

			['40 + (a + a)', VariadicVal::Expr_(Expr::Bin_(
					new FinalVal(40, 'Int'),
					new MathOperator('+'),
					Expr::Bin_(
						new BindVal('a', '?'),
						new MathOperator('+'),
						new BindVal('a', '?')
						)
					)
				, '?'
				, [ new BindVal('a', '?'),
				])],

			['40 + (a + b)', VariadicVal::Expr_(Expr::Bin_(
					new FinalVal(40, 'Int'),
					new MathOperator('+'),
					Expr::Bin_(
						new BindVal('a', '?'),
						new MathOperator('+'),
						new BindVal('b', '?')
						)
					)
				, '?'
				, [ new BindVal('a', '?'),
					new BindVal('b', '?'),
					])],

			["list.at 2 [\"une\", a, \"trois\"]", VariadicVal::Expr_(Expr::Func_(new ListFunc('list.at'), [
					new FinalVal(2, 'Int'),
					Composite::List_([
						new FinalVal("une", 'Str'),
						new BindVal("a", '?'),
						new FinalVal("trois", 'Str'),
						]),
					]),
				'?',
				[ new BindVal('a', '?') ]
				)],

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
			. "inc = (a) -> a + 1 * b\n"
			. "inc 29",
				new FinalVal(42, 'Int'),
				],

			["inc = (a) -> a + 1\n"
			."inc 41",
				new FinalVal(42, 'Int'),
				],

			[""
			."a = 5\n"
			."inc = (a) -> a + b\n"
			."inc 41",
				VariadicVal::Expr_(Expr::Bin_(
					new FinalVal(41, 'Int'),
					new MathOperator('+'),
					new BindVal("b", '?')
				), '?', [
					new BindVal("b", '?'),
				]),
				],

			["id = () -> 42\nid ()",
				new FinalVal(42, 'Int'),
				],

			// @TODO
		];
	}



	/**
	 * @return array<array<mixed>>
	 */
	static function dataShortLinkBind(): array
	{
		return [
			['a', VariadicVal::ShortLinkBind(new BindVal('a', '?')
				, '?'
				, [ new BindVal('a', '?'),
					])],
			["b = 1\na", VariadicVal::ShortLinkBind(new BindVal('a', '?')
				, '?'
				, [ new BindVal('a', '?'),
					])],
			["b = c\na", VariadicVal::ShortLinkBind(new BindVal('a', '?')
				, '?'
				, [ new BindVal('a', '?'),
					])],
		];
	}



	/**
	 * @return array<array<mixed>>
	 */
	static function dataPredicators(): array
	{
		return [
			["True",
				new FinalVal(true, 'Symbol'),
				],

			["1 == 2",
				new FinalVal(False, 'Symbol'),
				],

			["1 == 2 || 2 == 3",
				new FinalVal(False, 'Symbol'),
				],

			["1 == 2 or 2 == 3",
				new FinalVal(False, 'Symbol'),
				],

			["1 OR 2 OR 3",
				new FinalVal(True, 'Symbol'),
				],

			["1 || 2 || 3",
				new FinalVal(True, 'Symbol'),
				],

			["(1 == 1) && 1",
				new FinalVal(True, 'Symbol'),
				],

			["6 == 6 && (2 + 1) == 3",
				new FinalVal(True, 'Symbol'),
				],

			["6 == a && (2 + 1) == 3",
				VariadicVal::Expr_(Expr::Bin_(Expr::Bin_(
						new FinalVal(6, 'Int'),
						new PredicateFunction('=='),
						new BindVal('a', '?')
						),
					new PredicateFunction('&&'),
					new FinalVal(True, 'Symbol')
					), '?', [
						new BindVal('a', '?'),
					]),
				],
		];
	}



	private function compile($src)
	{
		return Compiler::WithDefaultLibraries()
			->compile($src);
	}

}
