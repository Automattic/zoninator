<?php

declare(strict_types=1);

use Rector\CodingStyle\Rector\ClassMethod\MakeInheritedMethodVisibilitySameAsParentRector;
use Rector\Config\RectorConfig;
use Rector\Php54\Rector\Array_\LongArrayToShortArrayRector;
use Rector\Strict\Rector\Empty_\DisallowedEmptyRuleFixerRector;

return RectorConfig::configure()
	->withPaths(
		array(
			__DIR__ . '/includes',
			__DIR__ . '/lib',
			__DIR__ . '/tests',
			__DIR__ . '/functions.php',
			__DIR__ . '/widget.zone-posts.php',
			__DIR__ . '/zoninator.php',
		)
	)
	->withSkip(
		array(
			LongArrayToShortArrayRector::class,
			// Need a better understanding of intent in this file before switching out the use of empty().
			DisallowedEmptyRuleFixerRector::class => array(
				__DIR__ . '/lib/zoninator_rest/type/class-zoninator-rest-type-registry.php',
			),
			// Child classes can legitimately relax the visibility of a method, so while this
			// might not be desirable, changing them from public to the original parent-defined
			// protected would count as a breaking change.
			MakeInheritedMethodVisibilitySameAsParentRector::class,
		)
	)
	->withPhpSets( php74: true )
	->withPreparedSets( deadCode: true, codeQuality: true, instanceOf: true, codingStyle: true )
	->withTypeCoverageLevel( 1 );
