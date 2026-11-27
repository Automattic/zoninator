<?php

declare(strict_types=1);

use Rector\CodingStyle\Rector\ClassMethod\MakeInheritedMethodVisibilitySameAsParentRector;
use Rector\Config\RectorConfig;
use Rector\Php54\Rector\Array_\LongArrayToShortArrayRector;
use Rector\Php81\Rector\FuncCall\NullToStrictStringFuncCallArgRector;
use Rector\Php83\Rector\ClassMethod\AddOverrideAttributeToOverriddenMethodsRector;
use Rector\Php84\Rector\Param\ExplicitNullableParamTypeRector;

return RectorConfig::configure()
	->withPaths(
		array(
			__DIR__ . '/src',
			__DIR__ . '/tests',
			__DIR__ . '/functions.php',
			__DIR__ . '/zoninator.php',
		)
	)
	->withSkip(
		array(
			LongArrayToShortArrayRector::class,
			// Child classes can legitimately relax the visibility of a method, so while this
			// might not be desirable, changing them from public to the original parent-defined
			// protected would count as a breaking change.
			MakeInheritedMethodVisibilitySameAsParentRector::class,
		)
	)
	->withPhpSets( php82: true )
	->withRules(
		array(
			// Opt-in: no PHP set includes it.
			NullToStrictStringFuncCallArgRector::class,

			// Changes from later PHP sets that still run on PHP 8.2:
			// 8.3
			AddOverrideAttributeToOverriddenMethodsRector::class,

			// 8.4
			ExplicitNullableParamTypeRector::class,
		)
	)
	->withPreparedSets( deadCode: true, codeQuality: true, instanceOf: true, codingStyle: true )
	->withTypeCoverageLevel( 1 );
