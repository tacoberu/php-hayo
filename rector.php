<?php declare(strict_types = 1);

use Rector\Config\RectorConfig;
use Rector\Set\ValueObject\DowngradeLevelSetList;
use Rector\CodeQuality\Rector\ClassMethod\LocallyCalledStaticMethodToNonStaticRector;
use Rector\Php71\Rector\List_\ListToArrayDestructRector;
use Rector\Php74\Rector\Closure\ClosureToArrowFunctionRector;


return RectorConfig::configure()
	->withPaths([
		__DIR__ . '/libs',
		//~ __DIR__ . '/tests',
	])
	->withSets([
		DowngradeLevelSetList::DOWN_TO_PHP_74,
	])
    ->withPreparedSets(
        deadCode: true,
        codeQuality: true,
        typeDeclarations: true,
        earlyReturn: true,
    )
    ->withSkip([
        LocallyCalledStaticMethodToNonStaticRector::class, // mění static na non-static
        ListToArrayDestructRector::class, // mění static na non-static
        ClosureToArrowFunctionRector::class,
        // SymplifyQuoteEscapeRector::class,  // Pokud nechceš měnit uvozovky
        // RecastingRemovalRector::class,     // Odstraňuje zbytečné přetypování
    ])
    ->withPhpSets(
        php74: true
    )
    ->withParallel()  // Výrazně zrychlí běh
    ->withCache(__DIR__ . '/temp/rector')
    ;
