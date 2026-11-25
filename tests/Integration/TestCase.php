<?php
/**
 * Base test case for integration tests.
 *
 * @package Automattic\Zoninator
 */

namespace Automattic\Zoninator\Tests\Integration;

use Automattic\Zoninator\Application\ZoneLocker;
use Automattic\Zoninator\Infrastructure\WordPress\Feed;
use Automattic\Zoninator\Infrastructure\WordPress\Settings;
use Automattic\Zoninator\Infrastructure\WordPress\Taxonomy;
use Automattic\Zoninator\Infrastructure\WordPress\Zones;
use Yoast\WPTestUtils\WPIntegration\TestCase as WPIntegrationTestCase;

/**
 * Base test case for integration tests, with the plugin's own objects to hand.
 */
abstract class TestCase extends WPIntegrationTestCase {

	protected static function zones(): Zones {
		return zoninator_container()->get( Zones::class );
	}

	protected static function locker(): ZoneLocker {
		return zoninator_container()->get( ZoneLocker::class );
	}

	protected static function taxonomy(): Taxonomy {
		return zoninator_container()->get( Taxonomy::class );
	}

	protected static function settings(): Settings {
		return zoninator_container()->get( Settings::class );
	}

	protected static function feed(): Feed {
		return zoninator_container()->get( Feed::class );
	}
}
