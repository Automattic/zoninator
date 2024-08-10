<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Php54\Rector\Array_\LongArrayToShortArrayRector;
use Rector\ValueObject\PhpVersion;

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
		)
	)
	->withPhpSets( php70: true )
	->withTypeCoverageLevel( 0 );
